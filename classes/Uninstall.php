<?php
/**
 * WSAL Uninstall.
 *
 * @package wsal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Uninstall class.
 *
 * @since 3.4.3
 */
class WSAL_Uninstall {

	/**
	 * Begin uninstallation.
	 */
	public static function uninstall() {
		// Drop the tables.
		if ( self::should_uninstall() ) {
			self::drop_table( 'options' );
			self::drop_table( 'occurrences' );
			self::drop_table( 'metadata' );
			self::drop_table( 'sessions' );
		}

		// Check if we have set things to delete upon uninstall.
		if ( self::should_data_be_deleted() ) {
			self::delete_options_from_wp_options();
		}

		// Clear scheduled hooks.
		wp_clear_scheduled_hook( 'wsal_delete_logins' );
		wp_clear_scheduled_hook( 'wsal_cleanup' );
	}

	/**
	 * Checks if the removal of data is allowed in the options table.
	 *
	 * @return bool
	 */
	private static function should_uninstall() {
		return self::should_data_be_deleted();
	}

	private static function should_data_be_deleted() {
		return in_array( get_option( 'wsal_delete-data' ), [ 'yes', 1, '1', 'y', 'true', true ] );
	}

	/**
	 * Returns the name of the WSAL table.
	 *
	 * @param string $table - Name of the WSAL table (without prefix).
	 *
	 * @return string
	 */
	private static function get_table( $table ) {
		return $GLOBALS['wpdb']->base_prefix . 'wsal_' . $table; // Using base_prefix because we don't have multiple tables on multisite.
	}

	/**
	 * Drop a table from the DB.
	 *
	 * @param string $name - Name of the WSAL table (without prefix).
	 */
	private static function drop_table( $name ) {
		global $wpdb;
		$table_name = self::get_table( $name );
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $table_name );
	}

	/**
	 * Delete wsal options from wp_options table.
	 */
	public static function delete_options_from_wp_options() {
		global $wpdb;
		$plugin_options = $wpdb->get_results( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE 'wsal_%'" );

		foreach ( $plugin_options as $option ) {
			delete_option( $option->option_name );
		}

		// Remove wsal specific freemius entry.
		delete_option( 'fs_wsalp' );
		
		// Ensue entry is fully cleared.
		delete_network_option( 0 ,'wsal_networkwide_tracker_cpts' );

		//  @todo delete also options from site-level tables in multisite context
	}
}
