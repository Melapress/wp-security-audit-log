<?php
/**
 * View: External DB Page
 *
 * WSAL external db page.
 *
 * @since 1.0.0
 * @package wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * External DB Add-On promo Page.
 * Used only if the plugin is not activated.
 *
 * @package wsal
 */
class WSAL_Views_ExternalDB extends WSAL_ExtensionPlaceholderView {

	/**
	 * Method: Get View Title.
	 */
	public function GetTitle() {
		return __( 'External DB Extension', 'wp-security-audit-log' );
	}

	/**
	 * Method: Get View Name.
	 */
	public function GetName() {
		return __( 'Integrations &#8682;', 'wp-security-audit-log' );
	}

	/**
	 * Method: Get View Weight.
	 */
	public function GetWeight() {
		return 10;
	}

	/**
	 * Page View.
	 */
	public function Render() {
		$title        = __( 'Activity log database & integration tools', 'wp-security-audit-log' );
		$description  = __( 'There are several benefits to segregating the logs from the main site database, and to be able to mirror the logs to third party and centralized business solutions. Upgrade to premium and:', 'wp-security-audit-log' );
		$addon_img    = trailingslashit( WSAL_BASE_URL ) . 'img/' . $this->GetSafeViewName() . '.jpg';
		$premium_list = array(
			__( 'Store the audit logs of your sites on an external database', 'wp-security-audit-log' ),
			__( 'Configuring archiving and store older log data in a segregated database', 'wp-security-audit-log' ),
			__( 'Mirror the logs to syslog, Slack, Papertrail and central business communication services', 'wp-security-audit-log' ),
			__( 'Configure filters to filter what is mirrored and archived in the databases and services', 'wp-security-audit-log' ),
		);
		$subtext      = false;
		$screenshots  = array(
			array(
				'desc' => __( 'Easily configure integration and database connections thanks to a user friendly wizard.', 'wp-security-audit-log' ),
				'img'  => trailingslashit( WSAL_BASE_URL ) . 'img/external-db/db_integrations_1.png',
			),
			array(
				'desc' => __( 'Configure activity log filters for third party services connections.', 'wp-security-audit-log' ),
				'img'  => trailingslashit( WSAL_BASE_URL ) . 'img/external-db/db_integrations_2.png',
			),
			array(
				'desc' => __( 'Configure an unlimited number of connections to different databases and third party services.', 'wp-security-audit-log' ),
				'img'  => trailingslashit( WSAL_BASE_URL ) . 'img/external-db/db_integrations_3.png',
			),
		);

		require_once dirname( __FILE__ ) . '/addons/html-view.php';
	}
}
