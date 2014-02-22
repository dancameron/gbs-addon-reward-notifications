<?php

/**
 * 
 *
 * @package GBS
 * @subpackage Base
 */
class SEC_Reward_Notifications extends Group_Buying_Controller {
	const USER_META_SENT = 'sec_last_time_sent_to_this_user';
	const EMAIL_SENT = 'sec_last_time_rewards_notification_email_was_sent_v2';
	const LAST_EMAIL_SENT_TO = 'sec_last_time_rewards_notification_email_was_sent_v2';
	const NOTIFICATION_TYPE = 'reward_notifications';
	const NOTIFICATION_TYPE_WO = 'reward_notifications_wo';
	private static $last_email_sent = 0;
	private static $last_email_sent_to = 0;
	private static $period_date_format = 'ndH'; // Should be set to 'm' for a monthly send; 'ndHi' will send every minute; 'ndH' every hour
	
	public static function init() {
		self::$last_email_sent = get_option( self::EMAIL_SENT, date( self::$period_date_format, current_time('timestamp' ) ) );
		self::$last_email_sent_to = get_option( self::LAST_EMAIL_SENT_TO, 0 );
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

	public function maybe_send_notifications() {
		$current_month = date( self::$period_date_format, current_time('timestamp' ) );
		if ( $current_month > self::$last_email_sent ) {
			self::send_notifications( TRUE );
		}
		self::send_notifications();	
	}


	public static function send_notifications( $periodic_send = FALSE ) {
		$users = self::find_users_to_get_notified( $periodic_send );
		foreach ( $users as $user ) {
			self::send_notification( $user );
		}
		self::update_last_email_sent_time();
	}


	public function find_users_to_get_notified( $first_timers = FALSE ) {
		$users = array();
		$query_args = array( 'fields' => array( 'ID', 'user_email' ), 'meta_query' => array() );
		
		// First timers don't have a meta for the last time they've received 
		// an update notification.
		if ( $first_timers ) {
			$query_args['meta_query']['relation'] = 'OR';
			$query_args['meta_query'][] = array(
											'key' => self::USER_META_SENT,
											'compare' => 'NOT EXISTS'
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

	public function send_notification( $user ) {
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


}