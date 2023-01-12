<?php
/**
 * View: Email Notifications Page
 *
 * WSAL email notifications page.
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
 * Email Notifications Add-On promo Page.
 * Used only if the plugin is not activated.
 *
 * @package    wsal
 * @subpackage views
 */
class WSAL_Views_EmailNotifications extends WSAL_ExtensionPlaceholderView {

	/**
	 * {@inheritDoc}
	 */
	public function get_title() {
		return esc_html__( 'Notifications Extension', 'wp-security-audit-log' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return esc_html__( 'Email Notifications &#8682;', 'wp-security-audit-log' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_weight() {
		return 2;
	}

	/**
	 * {@inheritDoc}
	 */
	public function render() {
		$title        = esc_html__( 'Email & SMS Notifications', 'wp-security-audit-log' );
		$description  = esc_html__( 'Get instantly alerted of important changes on your site via email notifications & SMS messages. Upgrade to premium and:', 'wp-security-audit-log' );
		$addon_img    = trailingslashit( WSAL_BASE_URL ) . 'img/' . $this->get_safe_view_name() . '.jpg';
		$premium_list = array(
			esc_html__( 'Configure any type of email notification', 'wp-security-audit-log' ),
			esc_html__( 'Configure SMS messages for instant critical alerts', 'wp-security-audit-log' ),
			esc_html__( 'Receive notifications for when users login, change their password or change content', 'wp-security-audit-log' ),
			esc_html__( 'Get alerted of site changes like plugin installs, theme changes etc', 'wp-security-audit-log' ),
			esc_html__( 'Enable built-in security email notifications of suspicious user activity', 'wp-security-audit-log' ),
			esc_html__( 'Personalize all email and SMS templates', 'wp-security-audit-log' ),
			esc_html__( 'Use the trigger builder to configure any type of notification criteria!', 'wp-security-audit-log' ),
		);
		$subtext      = esc_html__( 'Getting started is really easy. You can use one of the plugin’s built-in notifications or create your own using the easy to use trigger builder.', 'wp-security-audit-log' );
		$screenshots  = array(
			array(
				'desc' => esc_html__( 'Email and SMS notifications instantly alert you of important changes on your WordPress site.', 'wp-security-audit-log' ),
				'img'  => trailingslashit( WSAL_BASE_URL ) . 'img/notifications/notifications_1.png',
			),
			array(
				'desc' => esc_html__( 'Easily enable any of the built-in security and user management notifications.', 'wp-security-audit-log' ),
				'img'  => trailingslashit( WSAL_BASE_URL ) . 'img/notifications/notifications_2.png',
			),
			array(
				'desc' => esc_html__( 'Use the trigger builder to configure any type of email and SMS notification to get instantly alerted of site changes that are important to you and your business.', 'wp-security-audit-log' ),
				'img'  => trailingslashit( WSAL_BASE_URL ) . 'img/notifications/notifications_3.png',
			),
			array(
				'desc' => esc_html__( 'All email and SMS templates are configurable, allowing you to personalize them.', 'wp-security-audit-log' ),
				'img'  => trailingslashit( WSAL_BASE_URL ) . 'img/notifications/notifications_4.png',
			),
		);

		require_once dirname( __FILE__ ) . '/addons/html-view.php';
	}
}
