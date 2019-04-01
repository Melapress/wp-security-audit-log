<?php
/**
 * View: External DB Page
 *
 * WSAL external db page.
 *
 * @since 1.0.0
 * @package Wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * External DB Add-On promo Page.
 * Used only if the plugin is not activated.
 *
 * @package Wsal
 */
class WSAL_Views_ExternalDB extends WSAL_AbstractView {

	/**
	 * Method: Get View Title.
	 */
	public function GetTitle() {
		return __( 'External DB Add-On', 'wp-security-audit-log' );
	}

	/**
	 * Method: Get View Icon.
	 */
	public function GetIcon() {
		return 'dashicons-external';
	}

	/**
	 * Method: Get View Name.
	 */
	public function GetName() {
		return __( 'DB & Integrations &#8682;', 'wp-security-audit-log' );
	}

	/**
	 * Method: Get View Weight.
	 */
	public function GetWeight() {
		return 10;
	}

	/**
	 * Check if the page title is visible.
	 *
	 * @return boolean
	 */
	public function is_title_visible() {
		return false;
	}

	/**
	 * Method: Get View Header.
	 */
	public function Header() {
		// Extension Page CSS.
		wp_enqueue_style(
			'extensions',
			$this->_plugin->GetBaseUrl() . '/css/extensions.css',
			array(),
			filemtime( $this->_plugin->GetBaseDir() . '/css/extensions.css' )
		);

		// Swipebox CSS.
		wp_enqueue_style(
			'wsal-swipebox-css',
			$this->_plugin->GetBaseUrl() . '/css/swipebox.min.css',
			array(),
			filemtime( $this->_plugin->GetBaseDir() . '/css/swipebox.min.css' )
		);
	}

	/**
	 * Method: Get View Footer.
	 */
	public function Footer() {
		// jQuery.
		wp_enqueue_script( 'jquery' );

		// Swipebox JS.
		wp_register_script(
			'wsal-swipebox-js',
			$this->_plugin->GetBaseUrl() . '/js/jquery.swipebox.min.js',
			array( 'jquery' ),
			filemtime( $this->_plugin->GetBaseDir() . '/js/jquery.swipebox.min.js' ),
			false
		);
		wp_enqueue_script( 'wsal-swipebox-js' );

		// Extensions JS.
		wp_register_script(
			'wsal-extensions-js',
			$this->_plugin->GetBaseUrl() . '/js/extensions.js',
			array( 'wsal-swipebox-js' ),
			filemtime( $this->_plugin->GetBaseDir() . '/js/extensions.js' ),
			false
		);
		wp_enqueue_script( 'wsal-extensions-js' );
	}

	/**
	 * Page View.
	 */
	public function Render() {
		$title        = __( 'Audit Log Database & Integration Tools', 'wp-security-audit-log' );
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
