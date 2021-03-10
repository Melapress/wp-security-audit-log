<?php
/**
 * Class: Dashboard widget alert Formatter class.
 *
 * @since 4.2.1
 * @package Wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WSAL_DashboardWidgetFormatter
 *
 */
class WSAL_DashboardWidgetFormatter extends WSAL_AlertFormatter {

	public function __construct( $plugin ) {
		parent::__construct( $plugin );
		$this->js_infused_links_allowed = false;
		$this->supports_hyperlinks      = false;
		$this->supports_metadata        = false;
	}

}
