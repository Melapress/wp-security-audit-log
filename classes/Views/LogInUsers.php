<?php
/**
 * View: Users Sessions Page
 *
 * WSAL users sessions page.
 *
 * @since      1.0.0
 * @package    wsal
 * @subpackage views
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * User Sessions Management Add-On promo Page.
 * Used only if the plugin is not activated.
 *
 * @package    wsal
 * @subpackage views
 */
class WSAL_Views_LogInUsers extends WSAL_ExtensionPlaceholderView {

	/**
	 * {@inheritDoc}
	 */
	public function get_title() {
		return esc_html__( 'User Sessions Management Extension', 'wp-security-audit-log' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return esc_html__( 'Logged In Users &#8682;', 'wp-security-audit-log' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_weight() {
		return 7;
	}

	/**
	 * {@inheritDoc}
	 */
	public function render() {
		$title        = esc_html__( 'Real-Time Users Sessions Management', 'wp-security-audit-log' );
		$description  = esc_html__( 'Better manage your users’ logins and sessions. Upgrade to premium and:', 'wp-security-audit-log' );
		$addon_img    = trailingslashit( WSAL_BASE_URL ) . 'img/' . $this->get_safe_view_name() . '.png';
		$subtext      = false;
		$premium_list = array(
			esc_html__( 'See who is logged in to your site', 'wp-security-audit-log' ),
			esc_html__( 'When they logged in and from where', 'wp-security-audit-log' ),
			esc_html__( 'The last change they have done in real-time', 'wp-security-audit-log' ),
			esc_html__( 'Terminate any users’ session with a click of a button', 'wp-security-audit-log' ),
			esc_html__( 'Limit or block multiple sessions for the same user', 'wp-security-audit-log' ),
			esc_html__( 'Get alerted of multiple same user sessions', 'wp-security-audit-log' ),
		);
		$screenshots  = array(
			array(
				'desc' => esc_html__( 'See who is logged in to your WordPress site and multisite network in real-time.', 'wp-security-audit-log' ),
				'img'  => trailingslashit( WSAL_BASE_URL ) . 'img/users-sessions-management/user_sessions_1.png',
			),
			array(
				'desc' => esc_html__( 'Limit, manage and block multiple same user sessions easily.', 'wp-security-audit-log' ),
				'img'  => trailingslashit( WSAL_BASE_URL ) . 'img/users-sessions-management/user_sessions_2.png',
			),
		);

		require_once dirname( __FILE__ ) . '/addons/html-view.php';
	}
}
