<?php
/*
Plugin Name: SeC Addon - Reward Notifications
Version: 1.2
Plugin URI: http://groupbuyingsite.com/marketplace
Description: Periodic emails sent to accounts with or without rewards.
Author: Sprout Venture
Author URI: http://sproutventure.com/wordpress
Plugin Author: Dan Cameron
Text Domain: group-buying
*/

define( 'SEC_REWARD_NOTIFICATIONS_PATH', WP_PLUGIN_DIR . '/' . basename( dirname( __FILE__ ) ) . '/' );
define ('SEC_REWARD_NOTIFICATIONS_URL', plugins_url( '', __FILE__) );

// Load after all other plugins since we need to be compatible with groupbuyingsite
add_action( 'plugins_loaded', 'sec_reward_notifications_addon' );
function sec_reward_notifications_addon() {
	if ( class_exists('Group_Buying_Controller') ) {
		require_once 'classes/Reward_Notifications_Addon.php';
		// Hook this plugin into the GBS add-ons controller
		add_filter( 'gb_addons', array( 'Reward_Notifications_Addon', 'sec_addon' ), 10, 1 );	
	}
	
}