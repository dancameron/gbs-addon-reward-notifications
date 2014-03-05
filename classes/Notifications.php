<?php

/**
 * 
 *
 * @package GBS
 * @subpackage Base
 */
class SEC_Reward_Notifications extends Group_Buying_Controller {
	const USER_META_SENT = 'sec_last_time_sent_to_this_user_v2';
	const EMAIL_SENT = 'sec_last_time_rewards_notification_email_was_sent_v1';
	const NOTIFICATION_TYPE = 'reward_notifications';
	const NOTIFICATION_TYPE_WO = 'reward_notifications_wo';
	private static $last_email_sent = 0;
	private static $last_email_sent_to = 0;
	private static $period_date_format = 'm'; // Should be set to 'm' for a monthly send; 'ndHi' will send every minute; 'ndH' every hour
	
	public static function init() {
		self::$last_email_sent = get_option( self::EMAIL_SENT, date( self::$period_date_format, current_time('timestamp' )-(2764800) ) );
		// notification
		add_filter( 'gb_notification_types', array( get_class(), 'register_notification_type' ), 10, 1 );
		add_filter( 'gb_notification_shortcodes', array( get_class(), 'register_notification_shortcodes' ) );

		// cron to send emails
		if ( GBS_DEV ) {
			add_action( 'admin_init', array( get_class(), 'maybe_send_notifications' ) );
		} else {
			add_action( self::CRON_HOOK, array( get_class(), 'maybe_send_notifications' ) );
		}
	}

	////////////////////////////////
	// Notification Registration //
	////////////////////////////////

	/**
	 * Register notifications
	 * @param  array $notifications 
	 * @return array                
	 */
	public function register_notification_type( $notifications ) {
		$notifications[self::NOTIFICATION_TYPE] = array(
			'name' => gb__( 'Reward Notification' ),
			'description' => gb__( "Customize the periodic notification sent to your users telling them how many rewards they have." ),
			'shortcodes' => array( 'date', 'name', 'username', 'site_title', 'site_url', 'account_rewards', 'account_balance' ),
			'default_title' => gb__( 'Your Reward Balance at ' . get_bloginfo( 'name' ) ),
			'default_content' => sprintf( 'You have [account_rewards] at %s.', get_bloginfo( 'name' ) ),
			'allow_preference' => TRUE
		);
		// notification sent to 
		$notifications[self::NOTIFICATION_TYPE_WO] = array(
			'name' => gb__( 'Reward Notification (w/o rewards)' ),
			'description' => gb__( "Customize the periodic notification sent to your users without any rewards." ),
			'shortcodes' => array( 'date', 'name', 'username', 'site_title', 'site_url', 'account_rewards', 'account_balance' ),
			'default_title' => gb__( 'Your Reward Balance at ' . get_bloginfo( 'name' ) ),
			'default_content' => sprintf( 'You have no rewards at %s.', get_bloginfo( 'name' ) ),
			'allow_preference' => TRUE
		);
		return $notifications;
	}

	/**
	 * Register the shortcodes
	 * @param  array $default_shortcodes 
	 * @return array                     
	 */
	public function register_notification_shortcodes( $default_shortcodes ) {
		$summary_report_shortcodes = array(
				'account_balance' => array(
					'description' => self::__( 'Used to display the account balance.' ),
					'callback' => array( get_class(), 'shortcode_account_balance' )
				),
				'account_rewards' => array(
					'description' => self::__( 'Used to display the reward balance.' ),
					'callback' => array( get_class(), 'shortcode_account_rewards' )
				),
			);
		return array_merge( $default_shortcodes, $summary_report_shortcodes );
	}

	public function shortcode_account_balance( $atts, $content, $code, $data ) {
		$rewards = 'N/A';
		if ( isset( $data['account_balance'] ) ) {
			$rewards = $data['account_balance'];
		}
		return $rewards;
	}

	public function shortcode_account_rewards( $atts, $content, $code, $data ) {
		$rewards = 'N/A';
		if ( isset( $data['account_rewards'] ) ) {
			$rewards = $data['account_rewards'];
		}
		return $rewards;
	}

	/////////////////////////////
	// Send the notifications //
	/////////////////////////////


	/**
	 * Send a notification if the term has passed; by comparing the current month number to that of the last send
	 * @return  
	 */
	public function maybe_send_notifications() {
		// hold notifications
		// add_filter( 'sec_hold_reward_notification', '__return_true' );
		if ( apply_filters( 'sec_hold_reward_notification', FALSE ) )
			return;

		$current_month = date( self::$period_date_format, current_time('timestamp' ) );
		if ( $current_month > self::$last_email_sent ) {
			self::send_notifications();
		}
	}

	/**
	 * Find the users to be notified and send the notification
	 * @param  boolean $periodic_send Is this a notification that is sent out to all users or just those that
	 * haven't received the latest notification
	 * @return                  
	 */
	public static function send_notifications( $periodic_send = TRUE ) {
		$users = self::find_users_to_get_notified( $periodic_send );
		foreach ( $users as $user ) {
			self::send_notification( $user );
		}
		self::update_last_email_sent_time();
	}

	/**
	 * Find users to get notified using meta querys
	 * @param  boolean $first_timers Find users without meta of the last notification they received.
	 * @return 
	 */
	public function find_users_to_get_notified( $first_timers = TRUE ) {
		$users = array();
		$query_args = array( 'fields' => array( 'ID', 'user_email' ), 'meta_query' => array() );

		$query_args['meta_query'] = array();
		// First timers don't have a meta for the last time they've received 
		// an update notification.
		if ( $first_timers ) {
			$query_args['meta_query']['relation'] = 'OR';
			$query_args['meta_query'][] = array(
											'key' => self::USER_META_SENT,
											'compare' => 'NOT EXISTS',
     										'value' => '' // This is ignored, but is necessary...
											);
		}

		// Last sent before the current month. 
		$current_month = date( self::$period_date_format, current_time('timestamp' ) );
		$query_args['meta_query'][] = array(
										'key' => self::USER_META_SENT,
										'value' => $current_month,
										'compare' => '<'
										);
		
		$users = get_users( $query_args );

		return $users;
	}

	/**
	 * Send the notificaiton to the user and update the user's meta.
	 * Checks to see if the user has any type of balance and destinguishes which notification to send.
	 * @param  object  $user      ID and user_email
	 * @param  integer $date_sent current month
	 * @return              
	 */
	public function send_notification( $user, $date_sent = 0 ) {
		$user_id = $user->ID;
		$recipient = $user->user_email;
		$balance = (int)gb_get_account_balance( $user_id, 'balance' );
		$reward_points = (int)gb_get_account_balance( $user_id, 'points' );
		$data = array(
			'user_id' => $user_id,
			'account_balance' => $balance,
			'account_rewards' => $reward_points
		);
		// If the account has rewards or an account balance
		if ( $balance > 0 || $reward_points > 0 ) {
			Group_Buying_Notifications::send_notification( self::NOTIFICATION_TYPE, $data, $recipient );
		}
		else {
			Group_Buying_Notifications::send_notification( self::NOTIFICATION_TYPE_WO, $data, $recipient );
		}

		// Flag that the user has received a notification
		$current_month = date( self::$period_date_format, current_time('timestamp' ) );
		update_usermeta( $user_id, self::USER_META_SENT, $current_month );

		do_action( 'sec_rewards_notification_sent', $user );
	}

	/**
	 * Updated by month number
	 * @param  int $time 
	 * @return        
	 */
	public function update_last_email_sent_time() {
		self::$last_email_sent = date( self::$period_date_format, current_time('timestamp' ) );
		update_option( self::EMAIL_SENT, self::$last_email_sent );
	}
}