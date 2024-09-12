<?php
/**
 * Responsible for the User's operations
 *
 * @package    wsal
 * @subpackage helpers
 * @since      4.6.0
 * @copyright  2024 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WSAL\Helpers;

use WSAL\Adapter\User_Sessions;
use WSAL\Entities\Reports_Entity;
use WSAL\Helpers\Settings_Helper;
use WSAL\Entities\Metadata_Entity;
use WSAL\Entities\Occurrences_Entity;
use WSAL\Entities\Generated_Reports_Entity;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * User's utils class
 */
if ( ! class_exists( '\WSAL\Helpers\Uninstall_Helper' ) ) {
	/**
	 * Utility class for uninstalling the plugin.
	 *
	 * @package wsal
	 *
	 * @since 4.6.0
	 */
	class Uninstall_Helper {

		/**
		 * Runs the uninstall sequence
		 *
		 * @return void
		 *
		 * @since 4.6.0
		 */
		public static function uninstall() {
			if ( Settings_Helper::get_boolean_option_value( 'delete-data' ) ) {
				Occurrences_Entity::drop_table();
				Metadata_Entity::drop_table();

				if ( \class_exists( '\WSAL\Extensions\Views\Reports' ) ) {
					Reports_Entity::drop_table();
					Generated_Reports_Entity::drop_table();
				}

				if ( \class_exists( '\WSAL\Adapter\User_Sessions' ) ) {
					User_Sessions::drop_table();
				}

				// Delete the archive and other data (if any).

				Settings_Helper::delete_all_settings();
			}
		}
	}
}
