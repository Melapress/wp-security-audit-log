<?php
/**
 * View: External DB Page
 *
 * WSAL external db page.
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
 * External DB Add-On promo Page.
 * Used only if the plugin is not activated.
 *
 * @package    wsal
 * @subpackage views
 */
class WSAL_Views_ExternalDB extends WSAL_ExtensionPlaceholderView {

	/**
	 * {@inheritDoc}
	 */
	public function get_title() {
		return esc_html__( 'External DB Extension', 'wp-security-audit-log' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return esc_html__( 'Integrations &#8682;', 'wp-security-audit-log' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_weight() {
		return 10;
	}

	/**
	 * {@inheritDoc}
	 */
	public function render() {
		$title        = esc_html__( 'Activity log database & integration tools', 'wp-security-audit-log' );
		$description  = esc_html__( 'There are several benefits to segregating the logs from the main site database, and to be able to mirror the logs to third party and centralized business solutions. Upgrade to premium and:', 'wp-security-audit-log' );
		$addon_img    = trailingslashit( WSAL_BASE_URL ) . 'img/' . $this->get_safe_view_name() . '.jpg';
		$premium_list = array(
			esc_html__( 'Store the audit logs of your sites on an external database', 'wp-security-audit-log' ),
			esc_html__( 'Configuring archiving and store older log data in a segregated database', 'wp-security-audit-log' ),
			esc_html__( 'Mirror the logs to syslog, Slack, Papertrail and central business communication services', 'wp-security-audit-log' ),
			esc_html__( 'Configure filters to filter what is mirrored and archived in the databases and services', 'wp-security-audit-log' ),
		);
		$subtext      = false;
		$screenshots  = array(
			array(
				'desc' => esc_html__( 'Easily configure integration and database connections thanks to a user friendly wizard.', 'wp-security-audit-log' ),
				'img'  => trailingslashit( WSAL_BASE_URL ) . 'img/external-db/db_integrations_1.png',
			),
			array(
				'desc' => esc_html__( 'Configure activity log filters for third party services connections.', 'wp-security-audit-log' ),
				'img'  => trailingslashit( WSAL_BASE_URL ) . 'img/external-db/db_integrations_2.png',
			),
			array(
				'desc' => esc_html__( 'Configure an unlimited number of connections to different databases and third party services.', 'wp-security-audit-log' ),
				'img'  => trailingslashit( WSAL_BASE_URL ) . 'img/external-db/db_integrations_3.png',
			),
		);

		require_once dirname( __FILE__ ) . '/addons/html-view.php';
	}
}
