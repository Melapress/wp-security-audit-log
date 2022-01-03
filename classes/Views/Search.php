<?php
/**
 * View: Users Sessions Page
 *
 * WSAL users sessions page.
 *
 * @since 1.0.0
 * @package wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Search Add-On promo Page.
 * Used only if the plugin is not activated.
 *
 * @package wsal
 */
class WSAL_Views_Search extends WSAL_ExtensionPlaceholderView {

	/**
	 * Method: Get View Title.
	 */
	public function GetTitle() {
		return __( 'Search Extension', 'wp-security-audit-log' );
	}

	/**
	 * Method: Get View Name.
	 */
	public function GetName() {
		return __( 'Search &#8682;', 'wp-security-audit-log' );
	}

	/**
	 * Method: Get View Weight.
	 */
	public function GetWeight() {
		return 11;
	}

	/**
	 * Page View.
	 */
	public function Render() {
		$title        = __( 'Search & Filters for the Activity Log', 'wp-security-audit-log' );
		$description  = __( 'You can find all the information you want in the activity log, if you know what you are looking for and have the right tools. Upgrade to premium so you can:', 'wp-security-audit-log' );
		$addon_img    = trailingslashit( WSAL_BASE_URL ) . 'img/' . $this->GetSafeViewName() . '.jpg';
		$premium_list = array(
			__( 'Do text searches and use filters to fine tune the search results', 'wp-security-audit-log' ),
			__( 'Easily find when and who did a specific change on your site', 'wp-security-audit-log' ),
			__( 'Easily identify and track back suspicious user behaviour', 'wp-security-audit-log' ),
			__( 'Search for the cause of a problem and ease troubleshooting', 'wp-security-audit-log' ),
			__( 'Save search terms and filters for future use and improved productivity', 'wp-security-audit-log' ),
		);
		$subtext      = false;
		$screenshots  = array(
			array(
				'desc' => __( 'Use the text search to find a specific change.', 'wp-security-audit-log' ),
				'img'  => trailingslashit( WSAL_BASE_URL ) . 'img/search/search_1.png',
			),
			array(
				'desc' => __( 'Configure any filter you need to fine tune the search results and find what you are looking for with much less effort.', 'wp-security-audit-log' ),
				'img'  => trailingslashit( WSAL_BASE_URL ) . 'img/search/search_2.png',
			),
			array(
				'desc' => __( 'Save search terms and filters to run the searches again in the future with just a single click.', 'wp-security-audit-log' ),
				'img'  => trailingslashit( WSAL_BASE_URL ) . 'img/search/search_3.png',
			),
		);

		require_once dirname( __FILE__ ) . '/addons/html-view.php';
	}
}
