<?php
/**
 * View: Reports Page
 *
 * WSAL reports page.
 *
 * @since 1.0.0
 * @package Wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reports Add-On promo Page.
 * Used only if the plugin is not activated.
 *
 * @package Wsal
 */
class WSAL_Views_Reports extends WSAL_AbstractView {

	/**
	 * Method: Get View Title.
	 */
	public function GetTitle() {
		return __( 'Reports Add-On', 'wp-security-audit-log' );
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
		return __( 'Reports &#8682;', 'wp-security-audit-log' );
	}

	/**
	 * Method: Get View Weight.
	 */
	public function GetWeight() {
		return 9;
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
		$title        = __( 'Individual, Scheduled & Automated Reports', 'wp-security-audit-log' );
		$description  = __( 'Many are not fans of reports, however reports are vital in business. With them you can make informed decisions that allow you to improve user productivity and the business. Upgrade to Premium so you can:', 'wp-security-audit-log' );
		$addon_img    = trailingslashit( WSAL_BASE_URL ) . 'img/' . $this->GetSafeViewName() . '.jpg';
		$premium_list = array(
			__( 'Generate any type of user and site (in multisite) activity report', 'wp-security-audit-log' ),
			__( 'Automate and schedule daily, weekly, monthly and quarterly reports', 'wp-security-audit-log' ),
			__( 'Received reports automatically via email', 'wp-security-audit-log' ),
			__( 'Create statistics reports about users’ views, logins, activity from IP addresses & more', 'wp-security-audit-log' ),
		);
		$subtext      = __( 'Reports are vital to the success of your business and management of your site.', 'wp-security-audit-log' );
		$screenshots  = array(
			array(
				'desc' => __( 'Generate a HTML or CSV report.', 'wp-security-audit-log' ),
				'img'  => trailingslashit( WSAL_BASE_URL ) . 'img/reports/reports_1.png',
			),
			array(
				'desc' => __( 'Easily configure a criteria for your reports.', 'wp-security-audit-log' ),
				'img'  => trailingslashit( WSAL_BASE_URL ) . 'img/reports/reports_2.png',
			),
			array(
				'desc' => __( 'Schedule reports that are sent to you by email automatically.', 'wp-security-audit-log' ),
				'img'  => trailingslashit( WSAL_BASE_URL ) . 'img/reports/reports_3.png',
			),
		);

		require_once dirname( __FILE__ ) . '/addons/html-view.php';
	}
}
