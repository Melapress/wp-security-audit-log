<?php
/**
 * Class: WSAL Settings.
 *
 * WSAL settings class.
 *
 * @package wsal
 */

use WSAL\Controllers\Connection;
use WSAL\Helpers\Settings_Helper;
use WSAL\Helpers\Plugin_Settings_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class is the actual controller of the Settings Page.
 *
 * @package wsal
 */
class WSAL_Settings {
	/**
	 * Instance of the main plugin.
	 *
	 * @var WpSecurityAuditLog
	 */
	protected $plugin;

	public const ERROR_CODE_INVALID_IP = 901;

	/**
	 * List of Site Admins.
	 *
	 * @var array
	 */
	private $site_admins = array();

	/**
	 * IDs of disabled alerts.
	 *
	 * @var array
	 */
	protected $disabled = null;

	/**
	 * Alerts per page.
	 *
	 * @var int
	 */
	protected $per_page = null;

	/**
	 * Custom user meta fields excluded from monitoring.
	 *
	 * @var array
	 */
	protected $excluded_user_meta = array();

	/**
	 * Current screen object.
	 *
	 * @var WP_Screen
	 */
	private $current_screen = '';

	/**
	 * Method: Constructor.
	 *
	 * @param WpSecurityAuditLog $plugin - Instance of WpSecurityAuditLog.
	 */
	public function __construct( WpSecurityAuditLog $plugin ) {
		$this->plugin = $plugin;

		add_action( 'deactivated_plugin', array( $this, 'reset_stealth_mode' ), 10, 1 );
	}


	/**
	 * Reset Stealth Mode on MainWP Child plugin deactivation.
	 *
	 * @param string $plugin — Plugin.
	 */
	public function reset_stealth_mode( $plugin ) {
		if ( 'mainwp-child/mainwp-child.php' !== $plugin ) {
			return;
		}

		if ( \WSAL\Helpers\Settings_Helper::get_boolean_option_value( 'mwp-child-stealth-mode', false ) ) {
			Plugin_Settings_Helper::deactivate_mainwp_child_stealth_mode();
		}
	}

	public static function get_frontend_events() {
		return Settings_Helper::get_frontend_events();
	}
}
