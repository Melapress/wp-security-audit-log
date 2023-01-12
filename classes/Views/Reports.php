<?php
/**
 * View: Reports Page
 *
 * WSAL reports page.
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
 * Reports Add-On promo Page.
 * Used only if the plugin is not activated.
 *
 * @package    wsal
 * @subpackage views
 */
class WSAL_Views_Reports extends WSAL_ExtensionPlaceholderView {

	/**
	 * {@inheritDoc}
	 */
	public function get_title() {
		return esc_html__( 'Reports Extension', 'wp-security-audit-log' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return esc_html__( 'Create Reports &#8682;', 'wp-security-audit-log' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_weight() {
		return 3;
	}

	/**
	 * Page View.
	 */
	public function render() {
		$title        = esc_html__( 'Individual, Scheduled & Automated Reports', 'wp-security-audit-log' );
		$description  = esc_html__( 'Many are not fans of reports, however reports are vital in business. With them you can make informed decisions that allow you to improve user productivity and the business. Upgrade to Premium so you can:', 'wp-security-audit-log' );
		$addon_img    = trailingslashit( WSAL_BASE_URL ) . 'img/' . $this->get_safe_view_name() . '.jpg';
		$premium_list = array(
			esc_html__( 'Generate any type of user and site (in multisite) activity report', 'wp-security-audit-log' ),
			esc_html__( 'Automate and schedule daily, weekly, monthly and quarterly reports', 'wp-security-audit-log' ),
			esc_html__( 'Received reports automatically via email', 'wp-security-audit-log' ),
			esc_html__( 'Create statistics reports about users’ views, logins, activity from IP addresses & more', 'wp-security-audit-log' ),
		);
		$subtext      = esc_html__( 'Reports are vital to the success of your business and management of your site.', 'wp-security-audit-log' );
		$screenshots  = array(
			array(
				'desc' => esc_html__( 'Generate a HTML or CSV report.', 'wp-security-audit-log' ),
				'img'  => trailingslashit( WSAL_BASE_URL ) . 'img/reports/reports_1.png',
			),
			array(
				'desc' => esc_html__( 'Easily configure a criteria for your reports.', 'wp-security-audit-log' ),
				'img'  => trailingslashit( WSAL_BASE_URL ) . 'img/reports/reports_2.png',
			),
			array(
				'desc' => esc_html__( 'Schedule reports that are sent to you by email automatically.', 'wp-security-audit-log' ),
				'img'  => trailingslashit( WSAL_BASE_URL ) . 'img/reports/reports_3.png',
			),
		);

		require_once dirname( __FILE__ ) . '/addons/html-view.php';
	}
}
