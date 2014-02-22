<?php

/**
 * Load via GBS Add-On API
 */
class Reward_Notifications_Addon extends Group_Buying_Controller {
	
	public static function init() {
		require_once('Notifications.php');

		SEC_Reward_Notifications::init();
	}

	public static function sec_addon( $addons ) {
		$addons['reward_notifications'] = array(
			'label' => self::__( 'Reward Notifications' ),
			'description' => self::__( 'Periodic emails sent to accounts with or without rewards.' ),
			'files' => array(),
			'callbacks' => array(
				array( __CLASS__, 'init' ),
			)
		);
		return $addons;
	}

}