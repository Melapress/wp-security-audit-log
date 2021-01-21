<?php
/**
 * View: Users Sessions Page
 *
 * WSAL users sessions page.
 *
 * @since 1.0.0
 * @package Wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * User Sessions Management Add-On promo Page.
 * Used only if the plugin is not activated.
 *
 * @package Wsal
 */
class WSAL_Views_LogInUsers extends WSAL_ExtensionPlaceholderView {

	/**
	 * Method: Get View Title.
	 */
	public function GetTitle() {
		return __( 'User Sessions Management Extension', 'wp-security-audit-log' );
	}

	/**
	 * Method: Get View Name.
	 */
	public function GetName() {
		return __( 'Logged In Users &#8682;', 'wp-security-audit-log' );
	}

	/**
	 * Method: Get View Weight.
	 */
	public function GetWeight() {
		return 7;
	}

	/**
	 * Page View.
	 */
	public function Render() {
		$title        = __( 'Real-Time Users Sessions Management', 'wp-security-audit-log' );
		$description  = __( 'Better manage your users’ logins and sessions. Upgrade to premium and:', 'wp-security-audit-log' );
		$addon_img    = trailingslashit( WSAL_BASE_URL ) . 'img/' . $this->GetSafeViewName() . '.png';
		$subtext      = false;
		$premium_list = array(
			__( 'See who is logged in to your site', 'wp-security-audit-log' ),
			__( 'When they logged in and from where', 'wp-security-audit-log' ),
			__( 'The last change they have done in real-time', 'wp-security-audit-log' ),
			__( 'Terminate any users’ session with a click of a button', 'wp-security-audit-log' ),
			__( 'Limit or block multiple sessions for the same user', 'wp-security-audit-log' ),
			__( 'Get alerted of multiple same user sessions', 'wp-security-audit-log' ),
		);
		$screenshots  = array(
			array(
				'desc' => __( 'See who is logged in to your WordPress site and multisite network in real-time.', 'wp-security-audit-log' ),
				'img'  => trailingslashit( WSAL_BASE_URL ) . 'img/users-sessions-management/user_sessions_1.png',
			),
			array(
				'desc' => __( 'Limit, manage and block multiple same user sessions easily.', 'wp-security-audit-log' ),
				'img'  => trailingslashit( WSAL_BASE_URL ) . 'img/users-sessions-management/user_sessions_2.png',
			),
		);

		require_once dirname( __FILE__ ) . '/addons/html-view.php';
	}
}
