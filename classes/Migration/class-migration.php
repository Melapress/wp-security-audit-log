<?php
/**
 * Abstract migration class.
 *
 * @package    wsal
 * @subpackage utils
 * @copyright  2022 WP White Security
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WSAL\Utils;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

use \WSAL\Helpers\WP_Helper;

/**
 * Migration class
 */
if ( ! class_exists( '\WSAL\Utils\Migration' ) ) {

	/**
	 * Put all you migration methods here
	 *
	 * @package WSAL\Utils
	 * @since 1.6
	 */
	class Migration extends Abstract_Migration {

		/**
		 * The name of the option from which we should extract version
		 * Note: version is expected in version format - 1.0.0; 1; 1.0; 1.0.0.0
		 * Note: only numbers will be processed
		 *
		 * @var string
		 *
		 * @since      4.4.2.1
		 */
		protected static $version_option_name = WSAL_PREFIX . 'plugin_version';

		/**
		 * The constant name where the plugin version is stored
		 * Note: version is expected in version format - 1.0.0; 1; 1.0; 1.0.0.0
		 * Note: only numbers will be processed
		 *
		 * @var string
		 *
		 * @since      4.4.2.1
		 */
		protected static $const_name_of_plugin_version = 'WSAL_VERSION';

		/**
		 * Marks 442 update as started
		 *
		 * @var boolean
		 *
		 * @since      4.4.2.1
		 */
		protected static $_442_started = false;

		/**
		 * Migration for version upto 4.4.2
		 *
		 * Note: The migration methods need to be in line with the @see WSAL\Utils\Abstract_Migration::$pad_length
		 *
		 * @return void
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
			$wsal->load_defaults();

			// Load dependencies.
			if ( ! isset( $wsal->alerts ) ) {
				$wsal->alerts = new \WSAL_AlertManager( $wsal );
			}

			if ( ! isset( $wsal->constants ) ) {
				$wsal->constants = new \WSAL_ConstantManager();
			}

			$wsal->sensors = new \WSAL_SensorManager( $wsal );

			$disabled_alerts = WP_Helper::get_global_option( 'disabled-alerts', false );

			$always_disabled_alerts = implode( ',', $wsal->settings()->always_disabled_alerts );

			$disabled_alerts = implode( ',', \array_merge( \explode( ',', $disabled_alerts ), \explode( ',', $always_disabled_alerts ) ) );

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
			if ( ! $wsal->get_global_boolean_setting( 'mwp-child-stealth-mode', false ) ) {
				$wsal->settings()->set_mainwp_child_stealth_mode();
			}

			WP_Helper::delete_global_option( WSAL_PREFIX . 'addon_available_notice_dismissed' );

			// Removes old file scanning options.
			global $wpdb;
			$plugin_options = $wpdb->get_results( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE 'wsal_local_files_%'" ); // phpcs:ignore
			if ( ! empty( $plugin_options ) ) {
				foreach ( $plugin_options as $option ) {
					WP_Helper::delete_global_option( $option->option_name );
				}
			}

			// Remove 'system' entry from the front-end events array as it was removed along with 404 tracking.
			$frontend_events = \WSAL_Settings::get_frontend_events();
			if ( array_key_exists( 'system', $frontend_events ) ) {
				unset( $frontend_events['system'] );
				\WSAL_Settings::set_frontend_events( $frontend_events );
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
			WP_Helper::un_schedule_event( 'wsal_log_files_pruning' );

			// Delete custom logging dir path from the settings.
			WP_Helper::delete_global_option( 'custom-logging-dir' );

			// Delete dev options from the settings.
			WP_Helper::delete_global_option( 'dev-options' );

			if ( class_exists( '\WSAL_Extension_Manager' ) ) {
				\WSAL_Extension_Manager::include_extension( 'external-db' );

				// Delete cron jobs related to mirror scheduling.

				// This was previously a constant in WSAL_Ext_Plugin, but we removed it in version 4.3.
				$scheduled_hook_mirroring = 'wsal_run_mirroring';

				$mirrors = \WSAL_Ext_Common::get_config_options_for_group( 'mirror-' );
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
			if ( ! class_exists( '\Aws\CloudWatchLogs\CloudWatchLogsClient' ) && ( ! defined( 'WSAL_LOAD_AWS_SDK' ) || ! WSAL_LOAD_AWS_SDK ) ) {

				if ( class_exists( '\WSAL_Ext_Mirrors_AWSCloudWatchConnection' ) ) {

					if ( ! is_null( $wsal->external_db_util ) ) {
						$connections = $wsal->external_db_util->get_all_connections();
						if ( ! empty( $connections ) ) {
							foreach ( $connections as $connection ) {
								if ( \WSAL_Ext_Mirrors_AWSCloudWatchConnection::get_type() === $connection['type'] ) {
									$wsal->set_global_boolean_setting( 'show-aws-sdk-config-nudge-4_3_2', true );
									break;
								}
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
			if ( ! is_null( $wsal->external_db_util ) ) {
				foreach ( array( 'archive-connection', 'adapter-connection' ) as $connection_option_name ) {
					$connection_name = WP_Helper::get_global_option( $connection_option_name, null );
					if ( ! is_null( $connection_name ) ) {
						$db_connection = $wsal->external_db_util->get_connection( $connection_name );
						if ( is_array( $db_connection ) && empty( $db_connection['hostname'] ) && empty( $db_connection['db_name'] ) ) {
							if ( 'adapter-connection' === $connection_option_name ) {
								$wsal->external_db_util->remove_external_storage_config();
							} elseif ( 'archive-connection' === $connection_option_name ) {
								$wsal->external_db_util->remove_archiving_config();
								WP_Helper::delete_global_option( 'archiving-e' );
								WP_Helper::delete_global_option( 'archiving-last-created' );
							}

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
					if ( ! is_null( $wsal->external_db_util ) ) {
						$mirrors        = $wsal->external_db_util->get_all_mirrors();
						$mirrors_in_use = ! empty( $mirrors );
					}

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
						$wsal->set_global_boolean_setting( 'show-helper-plugin-needed-nudge', true, false );
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

				// Store the initial info to the db.
				\WSAL\Migration\Metadata_Migration_440::store_migration_info( $job_info );

				// Create and dispatch the background process itself.
				$bg_process = new \WSAL\Migration\Metadata_Migration_440( 'external' );
				$bg_process->push_to_queue( $job_info );
				$bg_process->save();
				$bg_process->dispatch();
			}
			// Archive is in use.
			$connection = WP_Helper::get_global_option( 'archive-connection' );
			if ( ! empty( $connection ) ) {
				$connection_config = \WSAL_Connector_ConnectorFactory::load_connection_config( $connection );

				\WSAL\Entities\Occurrences_Entity::set_connection(
                    ( new \WSAL_Connector_MySQLDB( $connection_config ) )->get_connection()
                );

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

					// Store the initial info to the db.
					\WSAL\Migration\Metadata_Migration_440::store_migration_info( $job_info );

					// Create and dispatch the background process itself.
					$bg_process = new \WSAL\Migration\Metadata_Migration_440( 'archive' );
					$bg_process->push_to_queue( $job_info );
					$bg_process->save();
					$bg_process->dispatch();
				}
			}
		}

		/**
		 * Migration for version upto 4.4.2.1
		 *
		 * Note: The migration methods need to be in line with the @see WSAL\Utils\Abstract_Migration::$pad_length
		 *
		 * @return void
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
	}
}
