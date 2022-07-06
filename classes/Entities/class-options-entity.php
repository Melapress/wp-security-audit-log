<?php
/**
 * Entity: Options.
 *
 * User Options table.
 *
 * Important: This table is no longer in use, and will be removed if it is presented.
 * That code is for migration purposes only.
 * At some point it was dropped, but in order to keep backwards compatibility, its content needs to be transferred to the main options table.
 *
 * @package wsal
 */

declare(strict_types=1);

namespace WSAL\Entities;

use \WSAL\Helpers\WP_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Entities\Options_Entity' ) ) {

	/**
	 * Responsible for the options (legacy).
	 */
	class Options_Entity extends Abstract_Entity {

		/**
		 * Contains the table name.
		 *
		 * @var string
		 */
		protected static $table = 'wsal_options';

		/**
		 * Transfers all the options from the legacy options table to the global WP options.
		 *
		 * @return void
		 *
		 * @since      4.4.2.1
		 */
		public static function transfer_options() {
			global $wpdb;

			$results = $wpdb->get_results( 'SELECT * FROM ' . self::get_table_name(), ARRAY_A ); // phpcs:ignore

			foreach ( $results as $data ) {
				$option_name   = $data['option_name'];
				$option_name   = \str_replace( 'wsal-', 'wsal_', $option_name );
				$option_exists = WP_Helper::get_global_option( $option_name, false );
				if ( false === $option_exists ) {

					WP_Helper::set_global_option( $option_name, \maybe_unserialize( $data['option_value'] ) );
                }
			}
		}

		/**
		 * Drop the table from the DB.
		 * The method from the abstract class is not used because that table must be in the local database, so we just need to user WP_DB
		 */
		public static function drop_table() {
			global $wpdb;

			$table_name = self::get_table_name();

			$wpdb->query( 'DROP TABLE IF EXISTS ' . $table_name ); // phpcs:ignore
		}

		/**
		 * Returns the the table name
		 * The method from the abstract class is not used because that table must be in the local database, so we just need to user WP_DB
		 *
		 * @return string
		 *
		 * @since      4.4.2.1
		 */
		public static function get_table_name(): string {
			global $wpdb;

			return $wpdb->prefix . static::$table;
		}
	}
}
