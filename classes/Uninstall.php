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
			if ( self::table_exists( 'sessions' ) ) {
				self::drop_table( 'sessions' );
			}
		}

		// Check if we have set things to delete upon uninstall.
		if ( self::should_data_be_deleted() ) {
			self::delete_options_from_wp_options();
		}

		// Clear scheduled hooks.
		wp_clear_scheduled_hook( 'wsal_cleanup' );
	}

	/**
	 * Checks if the removal of data is allowed in the options table.
	 *
	 * @return bool
	 */
	private static function should_uninstall() {
		return self::should_data_be_deleted() && self::table_exists( 'occurrences' );
	}

	private static function should_data_be_deleted() {
		return in_array( get_option( 'wsal_delete-data' ), [ 'yes', 1, '1', 'y', 'true', true ] );
	}

	/**
	 * Check if a table exists.
	 *
	 * @param string $table - Name of the WSAL table (without prefix).
	 *
	 * @return bool
	 */
	private static function table_exists( $table ) {
		global $wpdb;

		return (bool) count( $wpdb->get_results( $wpdb->prepare( 'SHOW TABLES LIKE %s', self::get_table( $table ) ) ) );
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
		$wpdb->query( 'DROP TABLE ' . $table_name );
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

		//  @todo delete also options from site-level tables in multisite context
	}
}
