<?php
/**
 * Abstract migration class.
 *
 * @package    wsal
 * @subpackage utils
 * @copyright  2024 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WSAL\Utils;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

use WSAL_Ext_MirrorLogger;
use WSAL\Helpers\WP_Helper;
use WSAL\Controllers\Connection;
use WSAL\Controllers\Cron_Jobs;
use WSAL\Entities\Reports_Entity;
use WSAL\Helpers\Settings_Helper;
use WSAL\Entities\Metadata_Entity;
use WSAL\Entities\Occurrences_Entity;
use WSAL\Controllers\Plugin_Extensions;
use WSAL\Helpers\Plugin_Settings_Helper;
use WSAL\Entities\Generated_Reports_Entity;
use WSAL\Reports\Controllers\Statistic_Reports;

/**
 * Migration class
 */
if ( ! class_exists( '\WSAL\Utils\Migration' ) ) {

	/**
	 * Put all you migration methods here
	 *
	 * @package WSAL\Utils
	 *
	 * @since 4.4.0
	 */
	class Migration extends Abstract_Migration {

		/**
		 * The name of the option from which we should extract version
		 * Note: version is expected in version format - 1.0.0; 1; 1.0; 1.0.0.0
		 * Note: only numbers will be processed
		 *
		 * @var string
		 *
		 * @since 4.4.2.1
		 */
		protected static $version_option_name = WSAL_PREFIX . 'plugin_version';

		/**
		 * The constant name where the plugin version is stored
		 * Note: version is expected in version format - 1.0.0; 1; 1.0; 1.0.0.0
		 * Note: only numbers will be processed
		 *
		 * @var string
		 *
		 * @since 4.4.2.1
		 */
		protected static $const_name_of_plugin_version = 'WSAL_VERSION';

		/**
		 * Marks 442 update as started
		 *
		 * @var boolean
		 *
		 * @since 4.4.2.1
		 */
		protected static $_442_started = false;

		/**
		 * Migration for version upto 4.4.2
		 *
		 * Note: The migration methods need to be in line with the @see WSAL\Utils\Abstract_Migration::$pad_length
		 *
		 * @return void
		 *
		 * @since 4.4.0
		 */
		protected static function migrate_up_to_4420() {

			self::$_442_started = true;

			// If the legacy table exists, lets extract the options and remove it.
			if ( \WSAL\Entities\Options_Entity::check_table_exists( \WSAL\Entities\Options_Entity::get_table_name() ) ) {
				\WSAL\Entities\Options_Entity::transfer_options();
				\WSAL\Entities\Options_Entity::drop_table();
				// That will reread the connection, as from the import above that might be changed to external.
				\WSAL\Entities\Options_Entity::destroy_connection();
			}

			$wsal = \WpSecurityAuditLog::get_instance();
			$wsal::load_freemius();
			$wsal::load_defaults();

			$disabled_alerts = WP_Helper::get_global_option( 'disabled-alerts', false );

			if ( ! \is_array( $disabled_alerts ) ) {
				$disabled_alerts = \explode( ',', $disabled_alerts );

				\array_walk( $disabled_alerts, 'trim' );
			}

			$always_disabled_alerts = Settings_Helper::get_default_always_disabled_alerts();

			if ( ! \is_array( $always_disabled_alerts ) ) {
				$always_disabled_alerts = \explode( ',', $always_disabled_alerts );

				\array_walk( $always_disabled_alerts, 'trim' );
			}

			$disabled_alerts = \array_merge( $disabled_alerts, $always_disabled_alerts );

			/**
			 * That is split only for clarity
			 */
			if ( false === $disabled_alerts ) {
				WP_Helper::set_global_option( 'disabled-alerts', $always_disabled_alerts );
			} elseif ( $disabled_alerts !== $always_disabled_alerts ) {
				WP_Helper::update_global_option( 'disabled-alerts', $disabled_alerts );
			}

			self::remove_notice( 'wsal-notice-wsal-privacy-notice-3.2' );

			WP_Helper::delete_transient( 'wsal-is-advert-dismissed' );

			/**
			 * MainWP Child Stealth Mode Update
			 *
			 * This update only needs to run if the stealth mode option
			 * does not exist on free version.
			 *
			 * @since 3.2.3.3
			 */
			if ( ! Settings_Helper::get_boolean_option_value( 'mwp-child-stealth-mode', false ) ) {
				Plugin_Settings_Helper::set_mainwp_child_stealth_mode();
			}

			WP_Helper::delete_global_option( WSAL_PREFIX . 'addon_available_notice_dismissed' );

			// Removes old file scanning options.
			global $wpdb;
			$plugin_options = $wpdb->get_results( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE 'wsal_local_files_%'" );
			if ( ! empty( $plugin_options ) ) {
				foreach ( $plugin_options as $option ) {
					WP_Helper::delete_global_option( $option->option_name );
				}
			}

			// Remove 'system' entry from the front-end events array as it was removed along with 404 tracking.
			$frontend_events = Settings_Helper::get_frontend_events();
			if ( array_key_exists( 'system', $frontend_events ) ) {
				unset( $frontend_events['system'] );
				Settings_Helper::set_frontend_events( $frontend_events );
			}

			// Remove all settings related to 404 tracking.
			$not_found_page_related_settings = array(
				WSAL_PREFIX . 'log-404',
				WSAL_PREFIX . 'purge-404-log',
				WSAL_PREFIX . 'log-404-referrer',
				WSAL_PREFIX . 'log-visitor-404',
				WSAL_PREFIX . 'purge-visitor-404-log',
				WSAL_PREFIX . 'log-visitor-404-referrer',
				WSAL_PREFIX . 'excluded-urls',
			);
			foreach ( $not_found_page_related_settings as $setting_name ) {
				WP_Helper::delete_global_option( $setting_name );
			}

			// Remove cron job for purging 404 logs.
			Cron_Jobs::un_schedule_event( 'wsal_log_files_pruning' );

			// Delete custom logging dir path from the settings.
			WP_Helper::delete_global_option( 'custom-logging-dir' );

			// Delete dev options from the settings.
			WP_Helper::delete_global_option( 'dev-options' );

			// phpcs:disable
			// phpcs:enable

			if ( class_exists( '\WSAL_Extension_Manager' ) ) {
				\WSAL_Extension_Manager::include_extension( 'external-db' );

				// Delete cron jobs related to mirror scheduling.

				// This was previously a constant in WSAL_Ext_Plugin, but we removed it in version 4.3.
				$scheduled_hook_mirroring = 'wsal_run_mirroring';

				$mirrors = Settings_Helper::get_all_mirrors();
				if ( ! empty( $mirrors ) ) {
					foreach ( $mirrors as $mirror ) {
						// Check if mirror details are valid.
						if ( ! empty( $mirror ) ) {
							$mirror_args = array( $mirror['name'] );
							if ( wp_next_scheduled( $scheduled_hook_mirroring, $mirror_args ) ) {
								wp_clear_scheduled_hook( $scheduled_hook_mirroring, $mirror_args );
							}
						}
					}
				}

				if ( wp_next_scheduled( $scheduled_hook_mirroring ) ) {
					wp_clear_scheduled_hook( $scheduled_hook_mirroring );
				}
			}

			// Remove nonessential settings related to mysql adapters.
			$mysql_related_settings = array(
				WSAL_PREFIX . 'adapter-type',
				WSAL_PREFIX . 'adapter-user',
				WSAL_PREFIX . 'adapter-password',
				WSAL_PREFIX . 'adapter-name',
				WSAL_PREFIX . 'adapter-hostname',
				WSAL_PREFIX . 'adapter-url-base-prefix',
				WSAL_PREFIX . 'adapter-base-prefix',
				WSAL_PREFIX . 'adapter-ssl',
				WSAL_PREFIX . 'adapter-client-certificate',
				WSAL_PREFIX . 'adapter-ssl-ca',
				WSAL_PREFIX . 'adapter-ssl-cert',
				WSAL_PREFIX . 'adapter-ssl-key',
				WSAL_PREFIX . 'archive-type',
				WSAL_PREFIX . 'archive-user',
				WSAL_PREFIX . 'archive-password',
				WSAL_PREFIX . 'archive-name',
				WSAL_PREFIX . 'archive-hostname',
				WSAL_PREFIX . 'archive-url-base-prefix',
				WSAL_PREFIX . 'archive-base-prefix',
				WSAL_PREFIX . 'archive-ssl',
				WSAL_PREFIX . 'archive-client-certificate',
				WSAL_PREFIX . 'archive-ssl-ca',
				WSAL_PREFIX . 'archive-ssl-cert',
				WSAL_PREFIX . 'archive-ssl-key',
			);

			foreach ( $mysql_related_settings as $setting_name ) {
				WP_Helper::delete_global_option( $setting_name );
			}

			// Remove options related to the external db buffer.
			WP_Helper::delete_global_option( 'adapter-use-buffer' );
			WP_Helper::delete_global_option( 'temp_alerts' );

			// Remove cron job for flushing the buffered events.
			if ( wp_next_scheduled( 'wsal_log_events_ext_db' ) ) {
				wp_clear_scheduled_hook( 'wsal_log_events_ext_db' );
			}

			// If AWS SDK is not available and an AWS CLoudWatch connection is present, let's create
			// a notice to nudge the user.
			if ( ! class_exists( '\Aws\CloudWatchLogs\CloudWatchLogsClient' ) && ( ! defined( 'WSAL_LOAD_AWS_SDK' ) || ! \WSAL_LOAD_AWS_SDK ) ) {

				if ( class_exists( '\WSAL\Extensions\ExternalDB\Mirrors\WSAL_Ext_Mirrors_AWSCloudWatchConnection' ) ) {

					$connections = Settings_Helper::get_all_connections();
					if ( ! empty( $connections ) ) {
						foreach ( $connections as $connection ) {
							if ( \WSAL\Extensions\ExternalDB\Mirrors\WSAL_Ext_Mirrors_AWSCloudWatchConnection::get_type() === $connection['type'] ) {
								Settings_Helper::set_boolean_option_value( 'show-aws-sdk-config-nudge-4_3_2', true );
								break;
							}
						}
					}
				}
			}

			// Remove options from autoloading.
			$remove_from_autoload = array(
				'wsal_adapter-connection',
				'wsal_admin-bar-notif-updates',
				'wsal_admin-blocking-plugins-support',
				'wsal_bbpress_addon_available_notice_dismissed',
				'wsal_connection-asd',
				'wsal_connection-aws',
				'wsal_connection-digital_ocean',
				'wsal_connection-freemysqlhosting',
				'wsal_connection-local_mysql_aaa',
				'wsal_connection-local_mysql_eee',
				'wsal_connection-local_mysql_xyz',
				'wsal_connection-testik',
				'wsal_custom-post-types',
				'wsal_daily-summary-email',
				'wsal_db_version',
				'wsal_delete-data',
				'wsal_disable-admin-bar-notif',
				'wsal_disable-daily-summary',
				'wsal_disable-widgets',
				'wsal_disabled-alerts',
				'wsal_dismissed-privacy-notice',
				'wsal_display-name',
				'wsal_events-nav-type',
				'wsal_from-email',
				'wsal_frontend-events',
				'wsal_generated_reports',
				'wsal_gravityforms_addon_available_notice_dismissed',
				'wsal_hide-plugin',
				'wsal_installed_plugin_addon_available',
				'wsal_log-failed-login-limit',
				'wsal_log-visitor-failed-login-limit',
				'wsal_login_page_notification',
				'wsal_login_page_notification_text',
				'wsal_mirror-aws',
				'wsal_mwp-child-stealth-mode',
				'wsal_notification-35727674910782585943',
				'wsal_notification-built-in-10',
				'wsal_notification-built-in-37',
				'wsal_notification-built-in-9',
				'wsal_periodic-report-all',
				'wsal_periodic-report-always-empty',
				'wsal_periodic-report-bbb',
				'wsal_periodic-reports-empty-emails-enabled',
				'wsal_plugin-viewers',
				'wsal_pruning-date',
				'wsal_pruning-date-e',
				'wsal_pruning-unit',
				'wsal_reports-user-autocomplete',
				'wsal_restrict-log-viewer',
				'wsal_restrict-plugin-settings',
				'wsal_setup-modal-dismissed',
				'wsal_twilio-account-sid',
				'wsal_twilio-auth-token',
				'wsal_twilio-number',
				'wsal_use-email',
				'wsal_use-proxy-ip',
				'wsal_version',
				'wsal_woocommerce_addon_available_notice_dismissed',
				'wsal_wp-seo_addon_available_notice_dismissed',
				'wsal_wpforms_addon_available_notice_dismissed',
			);

			foreach ( $remove_from_autoload as $option ) {
				$option_value = WP_Helper::get_global_option( $option, null );

				if ( null !== $option_value ) {
					WP_Helper::delete_global_option( $option );
					WP_Helper::set_global_option( $option, $option_value, false );
				}
			}

			// Set options to autoloading.
			$add_to_autoload = array(
				'wsal_adapter-connection',
				'wsal_admin-bar-notif-updates',
				'wsal_db_version',
				'wsal_disable-admin-bar-notif',
				'wsal_frontend-events',
				'wsal_plugin-viewers',
				'wsal_restrict-log-viewer',
				'wsal_restrict-plugin-settings',
				'wsal_setup-modal-dismissed',
				'wsal_version',
			);

			foreach ( $add_to_autoload as $option ) {
				$option_value = WP_Helper::get_global_option( $option, null );
				if ( null !== $option_value ) {
					WP_Helper::delete_global_option( $option );
					WP_Helper::set_global_option( $option, $option_value, true );
				}
			}

			// Change the name of the option storing excluded post meta fields.
			$excluded_custom_fields = WP_Helper::get_global_option( 'excluded-custom', null );
			if ( ! is_null( $excluded_custom_fields ) ) {
				WP_Helper::set_global_option( 'excluded-post-meta', $excluded_custom_fields );
				WP_Helper::delete_global_option( 'excluded-custom' );
			}

			$not_found_page_related_settings = array(
				'wsal_scan-in-progress',
				'wsal_last-scanned',
				'wsal_is_initial_scan_0',
				'wsal_is_initial_scan_1',
				'wsal_is_initial_scan_2',
				'wsal_is_initial_scan_3',
				'wsal_is_initial_scan_4',
				'wsal_is_initial_scan_5',
				'wsal_is_initial_scan_6',
				'wsal_last_scan_start',
				'wsal_scanned_dirs',
			);
			foreach ( $not_found_page_related_settings as $setting_name ) {
				WP_Helper::delete_global_option( $setting_name );
			}

			WP_Helper::delete_global_option( 'reports-user-autocomplete' );

			// External DB settings.
			foreach ( array( 'archive-connection', 'adapter-connection' ) as $connection_option_name ) {
				$connection_name = WP_Helper::get_global_option( $connection_option_name, null );
				if ( ! is_null( $connection_name ) ) {
					$db_connection = Connection::load_connection_config( $connection_name );
					if ( is_array( $db_connection ) && empty( $db_connection['hostname'] ) && empty( $db_connection['db_name'] ) ) {
						if ( 'adapter-connection' === $connection_option_name ) {
							Connection::remove_external_storage_config();
						} elseif ( 'archive-connection' === $connection_option_name ) {
							Connection::remove_archiving_config();
							WP_Helper::delete_global_option( 'archiving-e' );
							WP_Helper::delete_global_option( 'archiving-last-created' );
						}

						if ( defined( 'WSAL_CONN_PREFIX' ) ) {
							// Function WSAL_Ext_Common::delete_connection is not used on purpose because it would try to
							// trigger an event which would result in error while doing this clean-up.
							WP_Helper::delete_global_option( WSAL_CONN_PREFIX . $connection_name );
						}
					}
				}
			}

			// Extension manager will be available only if the license is already active.
			if ( class_exists( '\WSAL_Extension_Manager' ) ) {
				if ( ! \WSAL_Extension_Manager::is_messaging_available() || ! \WSAL_Extension_Manager::is_mirroring_available() ) {
					// Check if SMS notifications or any external mirrors are setup + force plugin to show a notice.
					$mirrors_in_use = false;
					$mirrors        = Settings_Helper::get_all_mirrors();
					$mirrors_in_use = ! empty( $mirrors );

					$notifications_in_use = false;
					if ( ! $mirrors_in_use && ! is_null( $wsal->notifications_util ) ) {
						$notifications = $wsal->notifications_util->get_notifications();
						if ( ! empty( $notifications ) ) {
							foreach ( $notifications as $notification ) {
								$item = maybe_unserialize( $notification->option_value );
								if ( strlen( $item->phone ) > 0 ) {
									$notifications_in_use = true;
									break;
								}
							}
						}
					}

					if ( $notifications_in_use || $mirrors_in_use ) {
						Settings_Helper::set_boolean_option_value( 'show-helper-plugin-needed-nudge', true, false );
					}
				}
			}

			/**
			 * User session table should be always in the local database.
			 *
			 * Premium or not does not matter. User can had premium but in time of the upgrade, their license could be expired,
			 * that does not mean that they will never switch back to the premium version.
			 */
			$table_exists = \WSAL\Entities\Occurrences_Entity::check_table_exists( $wpdb->base_prefix . 'wsal_sessions' );
			if ( $table_exists ) {
				$column_exists = \WSAL\Entities\Occurrences_Entity::check_column(
					$wpdb->base_prefix . 'wsal_sessions',
					'session_token',
					'varchar( 255 )'
				);

				if ( ! $column_exists ) {
					$alter_query = 'ALTER TABLE `' . $wpdb->base_prefix . 'wsal_sessions` CHANGE `session_token` `session_token` VARCHAR(128)
					NOT NULL;';

					$wpdb->query( $alter_query ); // phpcs:ignore
				}
			}

			\WSAL\Entities\Occurrences_Entity::destroy_connection();

			// First check if the table exists, if not then we can execute the migration process.
			if ( ! \WSAL\Entities\Occurrences_Entity::check_table_exists() ) {
				\WSAL\Entities\Occurrences_Entity::create_table();
				// Remove metatdata table if one exists and recreate it.
				Metadata_Entity::drop_table();
				Metadata_Entity::create_table();
			} else {

				// If one of the new columns exists there is no need to alter the table.
				$column_exists = \WSAL\Entities\Occurrences_Entity::check_column(
					\WSAL\Entities\Occurrences_Entity::get_table_name(),
					'client_ip',
					'varchar( 255 )'
				);

				if ( ! $column_exists ) {
					$upgrade_sql = \WSAL\Entities\Occurrences_Entity::get_upgrade_query();
					\WSAL\Entities\Occurrences_Entity::get_connection()->query( $upgrade_sql );

					$connection = WP_Helper::get_global_option( 'adapter-connection' );
					if ( empty( $connection ) ) {
						$connection = 'local';
					}

					// Create a background job to migrate the metadata.
					$job_info = array(
						'start_time'             => current_time( 'timestamp' ), // phpcs:ignore
						'processed_events_count' => 0,
						'batch_size'             => 50,
						'connection'             => $connection,
					);

					// Create and dispatch the background process itself.
					$bg_process = new \WSAL\Migration\Metadata_Migration_440( $connection );

					// Store the initial info to the db.
					\WSAL\Migration\Metadata_Migration_440::store_migration_info( $job_info );
					$bg_process->push_to_queue( $job_info );
					$bg_process->save();
					$bg_process->dispatch();
				}
			}

			// Archive is in use.
			$connection = WP_Helper::get_global_option( 'archive-connection' );
			if ( ! empty( $connection ) ) {
				\WSAL\Entities\Occurrences_Entity::set_connection(
					Connection::get_connection( $connection )
				);

				// First check if the table exists, if not then we can execute the migration process.
				if ( ! \WSAL\Entities\Occurrences_Entity::check_table_exists() ) {
					\WSAL\Entities\Occurrences_Entity::create_table();
					// Remove metatdata table if one exists and recreate it.
					Metadata_Entity::drop_table();
					Metadata_Entity::create_table();
				} else {

					// If one of the new columns exists there is no need to alter the table.
					$column_exists = \WSAL\Entities\Occurrences_Entity::check_column(
						\WSAL\Entities\Occurrences_Entity::get_table_name(),
						'client_ip',
						'varchar( 255 )'
					);
					if ( ! $column_exists ) {
						$upgrade_sql = \WSAL\Entities\Occurrences_Entity::get_upgrade_query();
						\WSAL\Entities\Occurrences_Entity::get_connection()->query( $upgrade_sql );

						// Create a background job to migrate the metadata.
						$job_info = array(
						'start_time'             => current_time( 'timestamp' ), // phpcs:ignore
						'processed_events_count' => 0,
						'batch_size'             => 50,
						'connection'             => $connection,
						);

						// Create and dispatch the background process itself.
						$bg_process = new \WSAL\Migration\Metadata_Migration_440( 'archive' );

						// Store the initial info to the db.
						\WSAL\Migration\Metadata_Migration_440::store_migration_info( $job_info );
						$bg_process->push_to_queue( $job_info );
						$bg_process->save();
						$bg_process->dispatch();
					}
				}
			}
		}

		/**
		 * Migration for version upto 4.4.2.1
		 *
		 * Note: The migration methods need to be in line with the @see WSAL\Utils\Abstract_Migration::$pad_length
		 *
		 * @return void
		 *
		 * @since 4.4.2.1
		 */
		protected static function migrate_up_to_4421() {
			\WSAL\Helpers\WP_Helper::delete_global_option( 'migration-started' );

			if ( ! self::$_442_started ) {
				self::migrate_up_to_4420();
			}

			global $wpdb;
			/**
			 * User session table should be always in the local database.
			 *
			 * Premium or not does not matter. User can had premium but in time of the upgrade, their license could be expired,
			 * that does not mean that they will never switch back to the premium version.
			 */
			$table_exists = \WSAL\Entities\Occurrences_Entity::check_table_exists( $wpdb->base_prefix . 'wsal_sessions' );
			if ( $table_exists ) {
				$column_exists    = \WSAL\Entities\Occurrences_Entity::check_column(
					$wpdb->base_prefix . 'wsal_sessions',
					'sites',
					'longtext'
				);
				$column_exists_id = \WSAL\Entities\Occurrences_Entity::check_column(
					$wpdb->base_prefix . 'wsal_sessions',
					'id',
					'bigint'
				);

				if ( ! $column_exists || $column_exists_id ) {
					$alter_query = 'DROP TABLE `' . $wpdb->base_prefix . 'wsal_sessions`;';

					$wpdb->query( $alter_query ); // phpcs:ignore

					if ( class_exists( '\WSAL\Adapter\User_Sessions' ) ) {
						\WSAL\Adapter\User_Sessions::create_table();
					}
				}
			}
		}

		/**
		 * Migration for version upto 4.4.3
		 *
		 * Note: The migration methods need to be in line with the @see WSAL\Utils\Abstract_Migration::$pad_length
		 *
		 * @return void
		 *
		 * @since 4.4.3
		 */
		protected static function migrate_up_to_4430() {

			// phpcs:disable
			// phpcs:enable

			if ( class_exists( 'WSAL_Ext_MirrorLogger' ) && method_exists( '\WSAL\Helpers\Settings_Helper', 'get_working_dir_path_static' ) ) {

				$working_dir_path = Settings_Helper::get_working_dir_path_static();

				if ( file_exists( $working_dir_path . WSAL_Ext_MirrorLogger::FILE_NAME_FAILED_LOGS . '.json' ) ) {
					rename( $working_dir_path . WSAL_Ext_MirrorLogger::FILE_NAME_FAILED_LOGS . '.json', $working_dir_path . WSAL_Ext_MirrorLogger::FILE_NAME_FAILED_LOGS . '.php' );

					$line = fgets(
						fopen( $working_dir_path . WSAL_Ext_MirrorLogger::FILE_NAME_FAILED_LOGS . '.php', 'r' )
					);
					if ( false === strpos( $line, '<?php' ) ) {
						$fp_source = fopen( $working_dir_path . WSAL_Ext_MirrorLogger::FILE_NAME_FAILED_LOGS . '.php', 'r' );
						$fp_dest   = fopen( $working_dir_path . WSAL_Ext_MirrorLogger::FILE_NAME_FAILED_LOGS . '.php.tmp', 'w' ); // better to generate a real temp filename.
						fwrite( $fp_dest, '<?php' . "\n" );
						while ( ! feof( $fp_source ) ) {
							fwrite( $fp_dest, fread( $fp_source, 8192 ) );
						}
						fclose( $fp_source );
						fclose( $fp_dest );
						unlink( $working_dir_path . WSAL_Ext_MirrorLogger::FILE_NAME_FAILED_LOGS . '.php' );
						rename( $working_dir_path . WSAL_Ext_MirrorLogger::FILE_NAME_FAILED_LOGS . '.php.tmp', $working_dir_path . WSAL_Ext_MirrorLogger::FILE_NAME_FAILED_LOGS . '.php' );
					}
				}
			}
		}

		/**
		 * Migration for version upto 4.5.0
		 *
		 * Note: The migration methods need to be in line with the @see WSAL\Utils\Abstract_Migration::$pad_length
		 *
		 * @return void
		 *
		 * @since 4.5.0
		 */
		protected static function migrate_up_to_4500() {
			Metadata_Entity::create_indexes();
			\WSAL\Entities\Occurrences_Entity::create_indexes();
		}

		/**
		 * Migration for version upto 4.5.2
		 *
		 * Converts excluded users to array
		 *
		 * Note: The migration methods need to be in line with the @see WSAL\Utils\Abstract_Migration::$pad_length
		 *
		 * @return void
		 *
		 * @since 4.5.2
		 */
		protected static function migrate_up_to_4520() {
			$excluded_users = Settings_Helper::get_option_value( 'excluded-users', array() );
			if ( is_string( $excluded_users ) ) {
				$excluded_users = array_unique( array_filter( explode( ',', $excluded_users ) ) );
				Settings_Helper::set_option_value( 'excluded-users', $excluded_users );
			}
			$excluded_roles = Settings_Helper::get_option_value( 'excluded-roles', array() );
			if ( is_string( $excluded_roles ) ) {
				$excluded_roles = array_unique( array_filter( explode( ',', $excluded_roles ) ) );
				Settings_Helper::set_option_value( 'excluded-roles', $excluded_roles );
			}
		}

		/**
		 * Migration for version upto 4.6.0
		 *
		 * Removes some redundant options
		 *
		 * Note: The migration methods need to be in line with the @see WSAL\Utils\Abstract_Migration::$pad_length
		 *
		 * @return void
		 *
		 * @since 4.6.0
		 */
		protected static function migrate_up_to_4600() {
			WP_Helper::delete_global_option( 'wsal_version' );
			WP_Helper::delete_global_option( 'disable-refresh' );
			Plugin_Extensions::deactivate_plugins();
			WP_Helper::set_global_option( 'extensions-merged-notice', true );
			// phpcs:disable
			/* @free:start */
			// phpcs:enable
			WP_Helper::set_global_option( 'free-search-try', true );
			// phpcs:disable
			/* @free:end */
			// phpcs:enable
		}

		/**
		 * Migration for version upto 4.6.1
		 *
		 * Removes some redundant options
		 *
		 * Note: The migration methods need to be in line with the @see WSAL\Utils\Abstract_Migration::$pad_length
		 *
		 * @return void
		 *
		 * @since 4.6.1
		 */
		protected static function migrate_up_to_4610() {
			WP_Helper::delete_global_option( 'events-nav-type' );
		}

		/**
		 * Migration for version upto 5.0.0
		 *
		 * Removes some redundant options
		 *
		 * Note: The migration methods need to be in line with the @see WSAL\Utils\Abstract_Migration::$pad_length
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function migrate_up_to_5000() {
			self::migrate_up_to_4610();

			self::migrate_users();

			if ( \class_exists( Generated_Reports_Entity::class, false ) ) {
				/**
				 * Migrate generated reports - if there are any.
				 */
				$reports = Settings_Helper::get_option_value( 'generated_reports', array() );
				if ( ! empty( $reports ) ) {

					foreach ( $reports as $report_array ) {

						$username = \get_userdata( $report_array['user'] )->user_login;
						$filename = pathinfo( $report_array['file'], PATHINFO_FILENAME );

						$report_filters = array();

						if ( isset( $report_array['filters']['type_statistics'] ) && ! empty( $report_array['filters']['type_statistics'] ) ) {
							$report_type = Statistic_Reports::get_statistical_report_from_legacy_type()[ (int) $report_array['filters']['type_statistics'] ];

							$report_filters[ esc_html__( 'Report Type', 'wp-security-audit-log' ) ] = Statistic_Reports::get_statistical_report_title()[ $report_type ];
						}

						Generated_Reports_Entity::save(
							array(
								'generated_report_user_id' => (int) $report_array['user'],
								'generated_report_username' => $username,
								'generated_report_filters' => $report_array['filters'],
								'generated_report_file'    => $filename,
								'generated_report_name'    => $filename,
								'created_on'               => $report_array['time'],
								'generated_report_format'  => ( isset( $report_array['filters']['type_statistics'] ) && ! empty( $report_array['filters']['type_statistics'] ) ) ? 999 : 0,
								'generated_report_filters_normalized' => $report_filters,
								'generated_report_header_columns' => '',
								'generated_report_where_clause' => '',
								'generated_report_finished' => true,
								'generated_report_to_date' => 0,
								'generated_report_number_of_records' => -1,
							)
						);
					}
				}

				$reports_white_labeling                  = array();
				$reports_white_labeling['business_name'] = Settings_Helper::get_option_value( 'reports-business-name', null );
				Settings_Helper::delete_option_value( 'reports-business-name' );
				$reports_white_labeling['name_surname'] = Settings_Helper::get_option_value( 'reports-contact-name', null );
				Settings_Helper::delete_option_value( 'reports-contact-name' );
				$reports_white_labeling['email'] = Settings_Helper::get_option_value( 'reports-contact-email', null );
				Settings_Helper::delete_option_value( 'reports-contact-email' );
				$reports_white_labeling['phone_number'] = Settings_Helper::get_option_value( 'reports-contact-phone', null );
				Settings_Helper::delete_option_value( 'reports-contact-phone' );
				$reports_white_labeling['logo'] = Settings_Helper::get_option_value( 'reports-custom-logo', null );
				Settings_Helper::delete_option_value( 'reports-custom-logo' );
				$reports_white_labeling['logo_url'] = Settings_Helper::get_option_value( 'reports-custom-logo-link', null );
				Settings_Helper::delete_option_value( 'reports-custom-logo-link' );

				$reports_white_labeling = array_filter( $reports_white_labeling );

				if ( ! empty( $reports_white_labeling ) ) {
					Settings_Helper::set_option_value( 'report-white-label-settings', $reports_white_labeling );
				}

				$reports_column_settings                                       = array();
				$reports_column_settings['reports_auto_purge_older_than_days'] = (int) Settings_Helper::get_option_value( 'reports-pruning-threshold', 30 );
				Settings_Helper::delete_option_value( 'reports-pruning-threshold' );
				$reports_column_settings['reports_auto_purge_enabled'] = (bool) Settings_Helper::get_option_value( 'reports-pruning-enabled', true );
				Settings_Helper::delete_option_value( 'reports-pruning-enabled' );
				$reports_column_settings['reports_send_empty_summary_emails'] = (bool) Settings_Helper::get_option_value( 'periodic-reports-empty-emails-enabled', false );
				Settings_Helper::delete_option_value( 'periodic-reports-empty-emails-enabled' );

				$reports_column_settings = array_filter( $reports_column_settings );

				if ( ! empty( $reports_column_settings ) ) {
					Settings_Helper::set_option_value( 'report-generate-columns-settings', $reports_column_settings );
				}

				foreach ( self::get_all_periodic_reports() as $report ) {
					if ( ! $report->owner ) {
						$username = __( 'System', 'wp-security-audit-log' );
					} else {
						$userdata = \WP_User::get_data_by( 'id', $report->owner );

						if ( ! $userdata ) {
							$username = __( 'System', 'wp-security-audit-log' );
						}

						$username = $userdata->user_login;

					}

					$only_these_post_titles = array();
					if ( isset( $report->post_ids ) ) {
						foreach ( $report->post_ids as $id ) {
							$only_these_post_titles[] = get_the_title( $id );
						}
					}

					$except_these_post_titles = array();
					if ( isset( $report->post_ids_excluded ) ) {
						foreach ( $report->post_ids_excluded as $id ) {
							$except_these_post_titles[] = get_the_title( $id );
						}
					}

					$data = array(
						'report_type_sites'      => ( isset( $report->sites ) && ! empty( $report->sites ) ) ? 'only_these' : ( ( isset( $report->sites_excluded ) && ! empty( $report->sites_excluded ) ) ? 'all_except' : '' ),
						'only_these_sites'       => ( isset( $report->sites ) ) ? $report->sites : array(),
						'except_these_sites'     => ( isset( $report->sites_excluded ) ) ? $report->sites_excluded : array(),

						'report_type_users'      => ( isset( $report->users ) && ! empty( $report->users ) ) ? 'only_these' : ( ( isset( $report->users_excluded ) && ! empty( $report->users_excluded ) ) ? 'all_except' : '' ),
						'only_these_users'       => ( isset( $report->users ) ) ? $report->users : array(),
						'except_these_users'     => ( isset( $report->users_excluded ) ) ? $report->users_excluded : array(),

						'report_type_roles'      => ( isset( $report->roles ) && ! empty( $report->roles ) ) ? 'only_these' : ( ( isset( $report->roles_excluded ) && ! empty( $report->roles_excluded ) ) ? 'all_except' : '' ),
						'only_these_roles'       => ( isset( $report->roles ) ) ? $report->roles : array(),
						'except_these_roles'     => ( isset( $report->roles_excluded ) ) ? $report->roles_excluded : array(),

						'report_type_ips'        => ( isset( $report->ipAddresses ) && ! empty( $report->ipAddresses ) ) ? 'only_these' : ( ( isset( $report->ipAddresses_excluded ) && ! empty( $report->ipAddresses_excluded ) ) ? 'all_except' : '' ), // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					'only_these_ips'             => ( isset( $report->ipAddresses ) ) ? $report->ipAddresses : array(), // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					'except_these_ips'           => ( isset( $report->ipAddresses_excluded ) ) ? $report->ipAddresses_excluded : array(), // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

					'report_type_objects'        => ( isset( $report->objects ) && ! empty( $report->objects ) ) ? 'only_these' : ( ( isset( $report->objects_excluded ) && ! empty( $report->objects_excluded ) ) ? 'all_except' : '' ),
					'only_these_objects'         => ( isset( $report->objects ) ) ? $report->objects : array(),
					'except_these_objects'       => ( isset( $report->objects_excluded ) ) ? $report->objects_excluded : array(),

					'report_type_event_types'    => ( isset( $report->event_types ) && ! empty( $report->event_types ) ) ? 'only_these' : ( ( isset( $report->event_types_excluded ) && ! empty( $report->event_types_excluded ) ) ? 'all_except' : '' ),
					'only_these_event_types'     => ( isset( $report->event_types ) ) ? $report->event_types : array(),
					'except_these_event_types'   => ( isset( $report->event_types_excluded ) ) ? $report->event_types_excluded : array(),

					'report_type_post_titles'    => ( isset( $only_these_post_titles ) && ! empty( $only_these_post_titles ) ) ? 'only_these' : ( ( isset( $except_these_post_titles ) && ! empty( $except_these_post_titles ) ) ? 'all_except' : '' ),
					'only_these_post_titles'     => ( isset( $only_these_post_titles ) ) ? $only_these_post_titles : array(),
					'except_these_post_titles'   => ( isset( $except_these_post_titles ) ) ? $except_these_post_titles : array(),

					'report_type_post_types'     => ( isset( $report->post_types ) && ! empty( $report->post_types ) ) ? 'only_these' : ( ( isset( $report->post_types_excluded ) && ! empty( $report->post_types_excluded ) ) ? 'all_except' : '' ),
					'only_these_post_types'      => ( isset( $report->post_types ) ) ? $report->post_types : array(),
					'except_these_post_types'    => ( isset( $report->post_types_excluded ) ) ? $report->post_types_excluded : array(),

					'report_type_post_statuses'  => ( isset( $report->post_statuses ) && ! empty( $report->post_statuses ) ) ? 'only_these' : ( ( isset( $report->post_statuses_excluded ) && ! empty( $report->post_statuses_excluded ) ) ? 'all_except' : '' ),
					'only_these_post_statuses'   => ( isset( $report->post_statuses ) ) ? $report->post_statuses : array(),
					'except_these_post_statuses' => ( isset( $report->post_statuses_excluded ) ) ? $report->post_statuses_excluded : array(),

					'report_type_alert_ids'      => ( isset( $report->alert_ids ) && ! empty( $report->alert_ids ) ) ? 'only_these' : ( ( isset( $report->alert_ids_excluded ) && ! empty( $report->alert_ids_excluded ) ) ? 'all_except' : '' ),
					'only_these_alert_ids'       => ( isset( $report->alert_ids ) ) ? $report->alert_ids : array(),
					'except_these_alert_ids'     => ( isset( $report->alert_ids_excluded ) ) ? $report->alert_ids_excluded : array(),

					'report_type_alert_groups'   => ( isset( $report->alert_groups ) && ! empty( $report->alert_groups ) ) ? 'only_these' : ( ( isset( $report->alert_groups_excluded ) && ! empty( $report->alert_groups_excluded ) ) ? 'all_except' : '' ),
					'only_these_alert_groups'    => ( isset( $report->alert_groups ) ) ? $report->alert_groups : array(),
					'except_these_alert_groups'  => ( isset( $report->alert_groups_excluded ) ) ? $report->alert_groups_excluded : array(),

					'report_type_severities'     => ( isset( $report->severities ) && ! empty( $report->severities ) ) ? 'only_these' : ( ( isset( $report->severities_excluded ) && ! empty( $report->severities_excluded ) ) ? 'all_except' : '' ),
					'only_these_severities'      => ( isset( $report->severities ) ) ? $report->severities : array(),
					'except_these_severities'    => ( isset( $report->severities_excluded ) ) ? $report->severities_excluded : array(),

					'report_start_date'          => '',
					'report_end_date'            => '',
					'report_tag'                 => '',
					'report_include_archive'     => false,
					'report_title'               => ( isset( $report->custom_title ) ) ? $report->custom_title : '',
					'report_comment'             => ( isset( $report->comment ) ) ? $report->comment : '',
					'report_metadata'            => ( isset( $report->no_meta ) ) ? $report->no_meta : true,
					);

					if ( empty( $data['only_these_sites'] ) ) {
						unset( $data['only_these_sites'] );
					}
					if ( empty( $data['except_these_sites'] ) ) {
						unset( $data['except_these_sites'] );
					}

					if ( empty( $data['only_these_users'] ) ) {
						unset( $data['only_these_users'] );
					}
					if ( empty( $data['except_these_users'] ) ) {
						unset( $data['except_these_users'] );
					}

					if ( empty( $data['only_these_roles'] ) ) {
						unset( $data['only_these_roles'] );
					}
					if ( empty( $data['except_these_roles'] ) ) {
						unset( $data['except_these_roles'] );
					}

					if ( empty( $data['only_these_ips'] ) ) {
						unset( $data['only_these_ips'] );
					}
					if ( empty( $data['except_these_ips'] ) ) {
						unset( $data['except_these_ips'] );
					}

					if ( empty( $data['only_these_objects'] ) ) {
						unset( $data['only_these_objects'] );
					}
					if ( empty( $data['except_these_objects'] ) ) {
						unset( $data['except_these_objects'] );
					}

					if ( empty( $data['only_these_event_types'] ) ) {
						unset( $data['only_these_event_types'] );
					}
					if ( empty( $data['except_these_event_types'] ) ) {
						unset( $data['except_these_event_types'] );
					}

					if ( empty( $data['only_these_post_titles'] ) ) {
						unset( $data['only_these_post_titles'] );
					}
					if ( empty( $data['except_these_post_titles'] ) ) {
						unset( $data['except_these_post_titles'] );
					}

					if ( empty( $data['only_these_post_types'] ) ) {
						unset( $data['only_these_post_types'] );
					}
					if ( empty( $data['except_these_post_types'] ) ) {
						unset( $data['except_these_post_types'] );
					}

					if ( empty( $data['only_these_post_statuses'] ) ) {
						unset( $data['only_these_post_statuses'] );
					}
					if ( empty( $data['except_these_post_statuses'] ) ) {
						unset( $data['except_these_post_statuses'] );
					}

					if ( empty( $data['only_these_alert_ids'] ) ) {
						unset( $data['only_these_alert_ids'] );
					}
					if ( empty( $data['except_these_alert_ids'] ) ) {
						unset( $data['except_these_alert_ids'] );
					}

					if ( empty( $data['only_these_alert_groups'] ) ) {
						unset( $data['only_these_alert_groups'] );
					}
					if ( empty( $data['except_these_alert_groups'] ) ) {
						unset( $data['except_these_alert_groups'] );
					}

					if ( empty( $data['only_these_severities'] ) ) {
						unset( $data['only_these_severities'] );
					}
					if ( empty( $data['except_these_severities'] ) ) {
						unset( $data['except_these_severities'] );
					}

					Reports_Entity::save(
						array(
							'report_name'  => $report->title,
							'created_on'   => $report->dateAdded, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						'report_user_id'   => $report->owner,
						'report_username'  => $username,
						'report_frequency' => Reports_Entity::get_frequency_from_name( (string) $report->frequency ),
						'report_format'    => 0,
						'report_email'     => $report->email,
						'report_data'      => $data,
						'report_tag'       => '',
						'report_disabled'  => false,
						)
					);
				}
			}
		}

		/**
		 * Migration for version upto 5.1.1
		 *
		 * Removes some redundant options
		 *
		 * Note: The migration methods need to be in line with the @see WSAL\Utils\Abstract_Migration::$pad_length
		 *
		 * @return void
		 *
		 * @since 5.1.1
		 */
		protected static function migrate_up_to_5110() {
			Cron_Jobs::un_schedule_event( 'wsal_delete_logins' );
			Cron_Jobs::un_schedule_event( 'wsal_cleanup' );

			$disabled_alerts = WP_Helper::get_global_option( 'disabled-alerts', false );

			if ( ! is_array( $disabled_alerts ) ) {
				$disabled_alerts = \explode( ',', $disabled_alerts );

				\array_walk( $disabled_alerts, 'trim' );

				WP_Helper::update_global_option( 'disabled-alerts', $disabled_alerts );
			}
		}

		/**
		 * Previous version of the plugin do not store username or user_id consistently, that method fixed that (in the best way possible) - if there is no user with that username 0 is stored as user_id, if user with that id does not exist anymore 'Deleted' is stored as username (check update_user_name_and_user_id method)
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function migrate_users() {
			$updated_records = self::update_user_name_and_user_id( Occurrences_Entity::get_connection() );

			$hooks_array = array(
				'wsal_migrate_users' => array(
					'time' => 'fiveminutes',
					'hook' => array( __CLASS__, 'migrate_users' ),
					'args' => array(),
				),
			);

			if ( 0 === $updated_records ) {
				Cron_Jobs::remove_cron_option( 'wsal_migrate_users' );
				$wsal_db = null;

			} else {
				Cron_Jobs::store_cron_option( $hooks_array );
			}
		}

		/**
		 * Checks and replace if empty user_id and username respectively.
		 *
		 * @param \wpdb $connection - The connection object to use.
		 *
		 * @return integer
		 *
		 * @since 5.0.0
		 */
		private static function update_user_name_and_user_id( $connection = null ): int {

			$results = array();

			$local_user_cache = array();

			$sql = 'SELECT * FROM ' . Occurrences_Entity::get_table_name( $connection ) . " WHERE 1 AND username IS NOT NULL AND username NOT IN ('Plugin', 'Plugins', 'Website Visitor', 'System' ) AND user_id IS NULL ORDER BY created_on DESC LIMIT 100";

			$records = Occurrences_Entity::load_query( $sql, $connection );

			$results['no_user_ids'] = \count( $records );

			if ( ! empty( $records ) ) {
				foreach ( $records as $record ) {
					if ( ! isset( $local_user_cache[ $record['username'] ] ) ) {
						$user = \get_user_by( 'login', $record['username'] );
						if ( \is_a( $user, '\WP_User' ) ) {
							$local_user_cache[ $record['username'] ] = $user->ID;
						} else {
							// Try to extract the user data from the meta (probably user is not around anymore).
							$meta_result = Metadata_Entity::load_by_name_and_occurrence_id( 'UserData', $record['id'] );
							if ( isset( $meta_result['value'] ) ) {
								$user = \maybe_unserialize( $meta_result['value'] );
								if ( \is_array( $user ) ) {
									$local_user_cache[ $record['username'] ] = $user['ID'];
								}
								if ( \is_object( $user ) ) {
									$local_user_cache[ $record['username'] ] = $user->ID;
								}
							}
						}
					}
					if ( isset( $local_user_cache[ $record['username'] ] ) ) {
						$record['user_id'] = $local_user_cache[ $record['username'] ];
						$connection->replace(
							Occurrences_Entity::get_table_name( $connection ),
							$record,
							array( '%d', '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d' )
						);
					} else {
						$record['user_id'] = 0;
						$connection->replace(
							Occurrences_Entity::get_table_name( $connection ),
							$record,
							array( '%d', '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d' )
						);
					}
				}
			}

			$sql = 'SELECT * FROM ' . Occurrences_Entity::get_table_name( $connection ) . ' WHERE 1 AND username IS NULL AND user_id IS NOT NULL ORDER BY created_on DESC LIMIT 100';

			$records = Occurrences_Entity::load_query( $sql, $connection );

			$results['no_user_names'] = \count( $records );

			$local_user_cache = \array_flip( $local_user_cache );

			if ( ! empty( $records ) ) {
				foreach ( $records as $record ) {
					if ( ! isset( $local_user_cache[ $record['user_id'] ] ) ) {
						$user = \get_user_by( 'ID', $record['user_id'] );
						if ( \is_a( $user, '\WP_User' ) ) {
							$local_user_cache[ $record['user_id'] ] = $user->user_login;
						} else {
							// Try to extract the user data from the meta (probably user is not around anymore).
							$meta_result = Metadata_Entity::load_by_name_and_occurrence_id( 'UserData', $record['id'] );
							if ( isset( $meta_result['value'] ) ) {
								$user = \maybe_unserialize( $meta_result['value'] );
								if ( \is_array( $user ) ) {
									$local_user_cache[ $record['user_id'] ] = $user['user_login'];
								}
								if ( \is_object( $user ) ) {
									$local_user_cache[ $record['user_id'] ] = $user->user_login;
								}
							}
						}
					}
					if ( isset( $local_user_cache[ $record['user_id'] ] ) ) {
						$record['username'] = $local_user_cache[ $record['user_id'] ];
						$connection->replace(
							Occurrences_Entity::get_table_name( $connection ),
							$record,
							array( '%d', '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d' )
						);
					} else {
						$record['username'] = 'Deleted';
						$connection->replace(
							Occurrences_Entity::get_table_name( $connection ),
							$record,
							array( '%d', '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d' )
						);
					}
				}
			}

			return ( (int) $results['no_user_ids'] + (int) $results['no_user_names'] );
		}

		/**
		 * Extracts all of the periodic reports
		 *
		 * @return array
		 *
		 * @since 5.0.0
		 */
		private static function get_all_periodic_reports(): array {

			$result  = array();
			$reports = Settings_Helper::get_notifications_setting( WSAL_PREFIX . 'periodic-report-' );
			if ( ! empty( $reports ) ) {
				foreach ( $reports as $report ) {
					$result[ $report->option_name ] = self::patch_legacy_report_object( unserialize( $report->option_value ) ); // @codingStandardsIgnoreLine
				}
			}

			return $result;
		}

		/**
		 * Takes care of legacy reporting objects and collects their properties
		 *
		 * @param mixed $report - The report to be checked and its properties patched.
		 *
		 * @return mixed
		 *
		 * @since 5.0.0
		 */
		private static function patch_legacy_report_object( $report ) {
			if ( property_exists( $report, 'viewState' ) && property_exists( $report, 'triggers' ) ) {
				if ( in_array( 'codes', $report->viewState, true ) ) { // phpcs:ignore
					// Specific event IDs were selected.
					$index             = array_search( 'codes', $report->viewState, true ); // phpcs:ignore
					$codes             = $report->triggers[ $index ]['alert_id'];
					$report->alert_ids = $codes;
				} elseif ( count( $report->viewState ) < 20 ) { // phpcs:ignore
					// Specific groups were selected.
					$report->alert_ids = $report->viewState; // phpcs:ignore
				}
			}

			return $report;
		}
	}
}
