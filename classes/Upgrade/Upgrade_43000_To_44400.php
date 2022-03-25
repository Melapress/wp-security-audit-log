<?php
/**
 * Class WSAL_Upgrade_43000_to_44400.
 *
 * @package wsal
 * @subpackage upgrade
 *
 * @since 4.4.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class handles upgrade changes from version 43000 to 44400.
 *
 * @package wsal
 * @subpackage upgrade
 *
 * @since 4.4.0
 */
class WSAL_Upgrade_43000_To_44400 {

	/**
	 * Plugin instance.
	 *
	 * @var WpSecurityAuditLog
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param WpSecurityAuditLog $plugin Plugin instance.
	 */
	public function __construct( WpSecurityAuditLog $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Runs the upgrade process.
	 */
	public function run() {

		// Delete unwanted usermeta.
		global $wpdb;
		$wpdb->query( // phpcs:ignore
			$wpdb->prepare(
				"DELETE FROM {$wpdb->usermeta} WHERE meta_key = '%s';", // phpcs:ignore
				'wsal-notice-update-44-notice'
			)
		);

		if ( class_exists( 'WSAL_Extension_Manager' ) ) {
			WSAL_Extension_Manager::include_extension( 'external-db' );
		}

		if ( ! did_action( 'wsal_init' ) ) {
			// We need to call wsal init manually because it does not run as before the upgrade procedure is triggered.
			do_action( 'wsal_init', $this->plugin );
		}

		// Remove some forgotten WFCM settings from the options table.
		$this->remove_wfcm_leftover_settings();

		// Change occurrence table in local database.
		$this->upgrade_occurrence_table( 'local' );


		$this->stop_autoloading_some_settings();
	}

	/**
	 * Removes a bunch of legacy WFCM extension related settings.
	 */
	private function remove_wfcm_leftover_settings() {
		// Remove all settings related to WFCM plugin.
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
			$this->plugin->delete_global_setting( $setting_name );
		}
	}

	/**
	 * Upgrades an occurrence table using given connection.
	 *
	 * It also kicks-off a metadata migration in the background.
	 *
	 * @param string|array $connection Connection alias or configuration data.
	 *
	 * @throws Freemius_Exception Freemius exception.
	 */
	private function upgrade_occurrence_table( $connection ) {
		$connector = $this->plugin->get_connector( $connection );
		/** @var WSAL_Adapters_MySQL_Occurrence $occurrence_adapter */
		$occurrence_adapter = $connector->get_adapter( 'Occurrence' );

		// Skip the upgrade it the table does not exist for some reason.
		if ( ! $connector->is_installed() ) {
			return;
		}

		$table_name = $occurrence_adapter->get_table();
		$connector->query( $this->get_occurrence_table_upgrade_query( $table_name ) );

		// Check if there are any events to process.
		if ( $occurrence_adapter->count() > 0 ) {
			// Create a background job to migrate the metadata.
			$job_info = array(
				'start_time'             => current_time( 'timestamp' ), // phpcs:ignore
				'processed_events_count' => 0,
				'batch_size'             => 50,
				'connection'             => is_array( $connection ) ? $connection['name'] : $connection,
			);

			// Store the initial info to the db.
			WSAL_Upgrade_MetadataMigration::store_migration_info( $job_info );

			// Create and dispatch the background process itself.
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
			. ' DROP COLUMN is_read, '
			. ' DROP COLUMN is_migrated, '
			. " ADD client_ip VARCHAR(255) NOT NULL DEFAULT '',"
			. ' ADD severity BIGINT NOT NULL DEFAULT 0,'
			. " ADD object VARCHAR(255) NOT NULL DEFAULT '',"
			. " ADD event_type VARCHAR(255) NOT NULL DEFAULT '',"
			. " ADD user_agent VARCHAR(255) NOT NULL DEFAULT '',"
			. " ADD user_roles VARCHAR(255) NOT NULL DEFAULT '',"
			. ' ADD username VARCHAR(255) NULL,'
			. ' ADD user_id BIGINT NULL ,'
			. " ADD session_id VARCHAR(255) NOT NULL DEFAULT '',"
			. " ADD post_status VARCHAR(255) NOT NULL DEFAULT '',"
			. " ADD post_type VARCHAR(255) NOT NULL DEFAULT '',"
			. ' ADD post_id BIGINT NOT NULL DEFAULT 0;';
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
			'wsal_only-me-user-id',
		);

		// phpcs:disable
		global $wpdb;
		$plugin_options = $wpdb->get_results(
			'SELECT option_name, option_value '
			. " FROM $wpdb->options "
			. " WHERE option_name LIKE '" . WpSecurityAuditLog::OPTIONS_PREFIX . "%';",
			ARRAY_A
		);
		// phpcs:enable

		if ( ! empty( $plugin_options ) ) {
			foreach ( $plugin_options as $option ) {
				if ( ! in_array( $option['option_name'], $settings_to_leave_on_autoload ) ) { // phpcs:ignore
					$value = maybe_unserialize( $option['option_value'] );
					$this->plugin->set_global_setting( $option['option_name'], $value, false );
				}
			}
		}
	}
}
