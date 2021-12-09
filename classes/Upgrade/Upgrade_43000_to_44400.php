<?php

/**
 * Class handles upgrade changes from version 43000 to 44400.
 *
 * @package wsal
 * @subpackage upgrade
 *
 * @since 4.4.0
 */
class WSAL_Upgrade_43000_to_44400 {

	/**
	 * Plugin instance.
	 *
	 * @var WpSecurityAuditLog
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param WpSecurityAuditLog $plugin
	 */
	public function __construct( WpSecurityAuditLog $plugin ) {
		$this->plugin = $plugin;
	}


	/**
	 * Runs the upgrade process.
	 */
	public function run() {

		//  remove some forgotten WFCM settings from the options table
		$this->remove_wfcm_leftover_settings();

		//  change occurrence table in local database
		$this->upgrade_occurrence_table( 'local' );

		//  ...as well as in external and/or archive database
		if ( ! is_null( $this->plugin->external_db_util ) ) {

			//  delete invalid external and archive connects leftover from legacy versions
			$this->delete_invalid_active_connections();

			foreach ( [ 'archive-connection', 'adapter-connection' ] as $connection_option_name ) {
				$connection_name = $this->plugin->GetGlobalSetting( $connection_option_name, null );
				if ( ! is_null( $connection_name ) ) {
					$db_connection = $this->plugin->external_db_util->get_connection( $connection_name );
					if ( is_array( $db_connection ) ) {
						$this->upgrade_occurrence_table( $db_connection );
					}
				}
			}
		}

		if ( ! WSAL_Extension_Manager::is_messaging_available() || ! WSAL_Extension_Manager::is_mirroring_available() ) {
			//  check if SMS notifications or any external mirrors are setup + force plugin to show a notice
			$mirrors_in_use = false;
			if ( ! is_null( $this->plugin->external_db_util ) ) {
				$mirrors        = $this->plugin->external_db_util->get_all_mirrors();
				$mirrors_in_use = ! empty( $mirrors );
			}

			$notifications_in_use = false;
			if ( ! $mirrors_in_use && ! is_null( $this->plugin->notifications_util ) ) {
				$notifications = $this->plugin->notifications_util->GetNotifications();
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
				$this->plugin->SetGlobalBooleanSetting( 'show-helper-plugin-needed-nudge', true, false );
			}
		}

		$this->stop_autoloading_some_settings();
	}

	/**
	 * Removes a bunch of legacy WFCM extension related settings.
	 */
	private function remove_wfcm_leftover_settings() {
		//  remove all settings related to WFCM plugin
		$not_found_page_related_settings = [
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
			'wsal_scanned_dirs'
		];
		foreach ( $not_found_page_related_settings as $setting_name ) {
			$this->plugin->DeleteGlobalSetting( $setting_name );
		}
	}

	/**
	 * Upgrades an occurrence table using given connection.
	 *
	 * It also kicks-off a metadata migration in the background.
	 *
	 * @param string|array $connection Connection alias or configuration data.
	 *
	 * @throws Freemius_Exception
	 */
	private function upgrade_occurrence_table( $connection ) {
		$connector = $this->plugin->getConnector( $connection );
		/** @var WSAL_Adapters_MySQL_Occurrence $occurrence_adapter */
		$occurrence_adapter = $connector->getAdapter( 'Occurrence' );

		$table_name = $occurrence_adapter->GetTable();
		$connector->query( $this->get_occurrence_table_upgrade_query( $table_name ) );

		//  check if there are any events to process
		if ( $occurrence_adapter->Count() > 0 ) {
			//  create a background job to migrate the metadata
			$job_info = array(
				'start_time'             => current_time( 'timestamp' ),
				'processed_events_count' => 0,
				'batch_size'             => 50,
				'connection'             => is_array( $connection ) ? $connection['name'] : $connection
			);

			//  store the initial info to the db
			WSAL_Upgrade_MetadataMigration::store_migration_info( $job_info );

			//  create and dispatch the background process itself
			$bg_process = new WSAL_Upgrade_MetadataMigration();
			$bg_process->push_to_queue( $job_info );
			$bg_process->save();
			$bg_process->dispatch();
		}
	}

	/**
	 * Builds an upgrade query for the occurrence table.
	 *
	 * @param string $table_name Table name.
	 *
	 * @return string
	 */
	private function get_occurrence_table_upgrade_query( $table_name ) {
		return "ALTER TABLE {$table_name}"
		       . " DROP COLUMN is_read, "
		       . " DROP COLUMN is_migrated, "
		       . " ADD client_ip VARCHAR(255) NOT NULL DEFAULT '',"
		       . " ADD severity BIGINT NOT NULL DEFAULT 0,"
		       . " ADD object VARCHAR(255) NOT NULL DEFAULT '',"
		       . " ADD event_type VARCHAR(255) NOT NULL DEFAULT '',"
		       . " ADD user_agent VARCHAR(255) NOT NULL DEFAULT '',"
		       . " ADD user_roles VARCHAR(255) NOT NULL DEFAULT '',"
		       . " ADD username VARCHAR(255) NULL,"
		       . " ADD user_id BIGINT NULL ,"
		       . " ADD session_id VARCHAR(255) NOT NULL DEFAULT '',"
		       . " ADD post_status VARCHAR(255) NOT NULL DEFAULT '',"
		       . " ADD post_type VARCHAR(255) NOT NULL DEFAULT '',"
		       . " ADD post_id BIGINT NOT NULL DEFAULT 0;";
	}

	/**
	 * Function deletes legacy external and archive connections with incorrect (empty) database credentials. These are
	 * leftovers from a bug in one of older plugin versions.
	 */
	private function delete_invalid_active_connections() {
		foreach ( [ 'archive-connection', 'adapter-connection' ] as $connection_option_name ) {
			$connection_name = $this->plugin->GetGlobalSetting( $connection_option_name, null );
			if ( ! is_null( $connection_name ) ) {
				$db_connection = $this->plugin->external_db_util->get_connection( $connection_name );
				if ( is_array( $db_connection ) && empty( $db_connection['hostname'] ) && empty( $db_connection['db_name'] ) ) {
					if ( 'adapter-connection' === $connection_option_name ) {
						$this->plugin->external_db_util->RemoveExternalStorageConfig();
					} else if ( 'archive-connection' === $connection_option_name ) {
						$this->plugin->external_db_util->RemoveArchivingConfig();
						$this->plugin->external_db_util->DeleteGlobalSetting( 'archiving-e' );
						$this->plugin->external_db_util->DeleteGlobalSetting( 'archiving-last-created' );
					}

					//  function WSAL_Ext_Common::delete_connection is not used on purpose because it would try to
					//  trigger an event which would result in error while doing this clean-up
					$this->plugin->external_db_util->DeleteGlobalSetting( WSAL_CONN_PREFIX . $connection_name );
				}
			}
		}
	}

	/**
	 * Change all but selected plugin settings to stop autoloading.
	 */
	private function stop_autoloading_some_settings() {
		$settings_to_leave_on_autoload = array(
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
			'wsal_freemius_state',
			'wsal_only-me-user-id'
		);

		global $wpdb;
		$plugin_options = $wpdb->get_results(
			"SELECT option_name, option_value "
			. " FROM $wpdb->options "
			. " WHERE option_name LIKE '" . WpSecurityAuditLog::OPTIONS_PREFIX . "%';",
			ARRAY_A
		);

		if ( ! empty( $plugin_options ) ) {
			foreach ( $plugin_options as $option ) {
				if ( ! in_array( $option['option_name'], $settings_to_leave_on_autoload ) ) {
					$value = maybe_unserialize( $option['option_value'] );
					$this->plugin->SetGlobalSetting( $option['option_name'], $value, false );
				}
			}
		}
	}
}