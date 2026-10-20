<?php
/**
 * Responsible for the User's operations
 *
 * @package    wsal
 * @subpackage helpers
 * @since      4.6.0
 * @copyright  2026 Melapress
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3 or higher
 * @link       https://wordpress.org/plugins/wp-security-audit-log/
 */

declare(strict_types=1);

namespace WSAL\Helpers;

use WSAL\Adapter\User_Sessions;
use WSAL\Entities\Reports_Entity;
use WSAL\Helpers\Settings_Helper;
use WSAL\Entities\Metadata_Entity;
use WSAL\Licensing\Licensing_Factory;
use WSAL\Entities\Occurrences_Entity;
use WSAL\Entities\Generated_Reports_Entity;
use WSAL\Entities\Custom_Notifications_Entity;

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
		 * Runs the uninstall sequence.
		 *
		 * @return void
		 *
		 * @since 4.6.0
		 * @since 5.6.7 - Added custom notifications table removal.
		 * @since 5.6.8 - Frees the EDD license activations and removes the license data, whatever the "delete data" setting.
		 */
		public static function uninstall() {
			$is_uninstalling = defined( 'WP_UNINSTALL_PLUGIN' ) || \doing_action( 'uninstall_' . WSAL_BASE_NAME );

			// The license is removed whatever the "delete data" setting is, but only when the plugin is really deleted.
			if ( $is_uninstalling ) {
				Licensing_Factory::remove_license_on_uninstall();
			}

			/**
			 * During uninstall, WordPress loads the main plugin file, which starts Action Scheduler,
			 * and then deletes the plugin files before the request ends. Its shutdown callback and recurring
			 * cron event are removed so they don't try to load files that no longer exist, which would
			 * cause a fatal error.
			 */
			if ( $is_uninstalling && class_exists( '\ActionScheduler_QueueRunner', false ) ) {
				\ActionScheduler_QueueRunner::instance()->unhook_dispatch_async_request();

				\wp_clear_scheduled_hook( \ActionScheduler_QueueRunner::WP_CRON_HOOK, array( 'WP Cron' ) );
			}

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
