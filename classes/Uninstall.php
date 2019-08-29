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
		}

		// Clear scheduled hooks.
		wp_clear_scheduled_hook( 'wsal_cleanup' );
	}

	/**
	 * Returns the name of the WSAL table.
	 *
	 * @param string $table - Name of the WSAL table (without prefix).
	 * @return string
	 */
	private static function get_table( $table ) {
		return $GLOBALS['wpdb']->base_prefix . 'wsal_' . $table; // Using base_prefix because we don't have multiple tables on multisite.
	}

	/**
	 * Check if a table exists.
	 *
	 * @param string $table - Name of the WSAL table (without prefix).
	 * @return bool
	 */
	private static function table_exists( $table ) {
		global $wpdb;

		return (bool) count( $wpdb->get_results( $wpdb->prepare( 'SHOW TABLES LIKE %s', self::get_table( $table ) ) ) );
	}

	/**
	 * Get option from WSAL options table.
	 *
	 * @param string $name - Option name.
	 * @return mixed
	 */
	private static function get_option( $name ) {
		global $wpdb;

		$name       = 'wsal-' . $name;
		$table_name = self::get_table( 'options' );
		return $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $table_name WHERE option_name = %s", $name ) );
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
	 * Checks if the removal of data is allowed in the options table.
	 *
	 * @return bool
	 */
	private static function should_uninstall() {
		return self::table_exists( 'options' ) && '1' === self::get_option( 'delete-data' );
	}
}
