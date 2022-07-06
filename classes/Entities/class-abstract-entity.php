<?php
/**
 * Entity: Abstract.
 *
 * @package wsal
 */

declare(strict_types=1);

namespace WSAL\Entities;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Entities\Abstract_Entity' ) ) {

	/**
	 * Responsible for the common entity operations.
	 */
	abstract class Abstract_Entity {

		/**
		 * Contains the table name.
		 *
		 * @var string
		 */
		private static $table = '';

		/**
		 * Holds the DB connection (for caching purposes)
		 *
		 * @var [type]
		 *
		 * @since      4.4.2.1
		 */
		private static $connection = null;

		/**
		 * Returns the the table name
		 *
		 * @return string
		 *
		 * @since      4.4.2.1
		 */
		public static function get_table_name(): string {
			return self::get_connection()->base_prefix . static::$table;
		}

		/**
		 * Returns the current connection (used by the plugin)
		 *
		 * @return \WPDB @see \WSAL_Connector_MySQLDB
		 *
		 * @since      4.4.2.1
		 */
		public static function get_connection() {
			if ( null === self::$connection ) {
				if ( self::is_external_db() ) {
					// Get DB connector.
					$db_config = \WSAL_Connector_ConnectorFactory::get_config(); // Get DB connector configuration.

					// Get connector for DB.
					$custom_mysql = \WSAL_Connector_ConnectorFactory::get_connector( $db_config );
				} else {
					$custom_mysql = \WSAL_Connector_ConnectorFactory::get_default_connector();
				}
				self::$connection = $custom_mysql->get_connection();
			}
			return self::$connection;
		}

		/**
		 * Sets connection to the given value.
		 *
		 * @param \WPDB $connection - The connection to set to @see \WSAL_Connector_MySQLDB .
		 *
		 * @return void
		 *
		 * @since      4.4.2.1
		 */
		public static function set_connection( $connection ) {
			self::$connection = $connection;
		}

		/**
		 * As this is static class, we need to destroy the connection sometimes.
		 *
		 * @return void
		 *
		 * @since      4.4.2.1
		 */
		public static function destroy_connection() {
			self::$connection = null;
			// Destroy factory connection as well.
			\WSAL_Connector_ConnectorFactory::destroy_connection();
		}

		/**
		 * Checks if the table needs to be recreated / created
		 *
		 * @param string $table_name - The name of the table to check for.
		 * @param string $create_ddl - The create table syntax.
		 *
		 * @return bool
		 *
		 * @since      4.4.2.1
		 */
		public static function maybe_create_table( string $table_name, string $create_ddl ): bool {
			foreach ( self::get_connection()->get_col( 'SHOW TABLES', 0 ) as $table ) {
				if ( $table === $table_name ) {
					return true;
				}
			}
			// Didn't find it, so try to create it.
			self::get_connection()->query( $create_ddl );

			// We cannot directly tell that whether this succeeded!
			foreach ( self::get_connection()->get_col( 'SHOW TABLES', 0 ) as $table ) {
				if ( $table === $table_name ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Checks if external database is in use.
		 *
		 * @return boolean
		 *
		 * @since      4.4.2.1
		 */
		public static function is_external_db(): bool {
			$db_config = \WSAL_Connector_ConnectorFactory::get_config();

			return is_array( $db_config ) && ! empty( $db_config );
		}

		/**
		 * Checks for given column existence using custom connection.
		 *
		 * @param string       $table_name - The name of the table.
		 * @param string       $col_name - The name of the column.
		 * @param string       $col_type - Type of the column.
		 * @param boolean|null $is_null - Is it null.
		 * @param mixed        $key - Is it key.
		 * @param mixed        $default - The default value of the column.
		 * @param mixed        $extra - Extra parameters.
		 *
		 * @return boolean - True if the column exists and all given parameters are the same, false otherwise.
		 *
		 * @since      4.4.2.1
		 */
		public static function check_column(
			string $table_name,
			string $col_name,
			string $col_type,
			bool $is_null = null,
			$key = null,
			$default = null,
			$extra = null ): bool {

			$diffs   = 0;
			$results = self::get_connection()->get_results( "DESC $table_name" );

			foreach ( $results as $row ) {

				if ( $row->Field === $col_name ) { // phpcs:ignore

					// Got our column, check the params.
					if ( ( null !== $col_type ) && ( strtolower( str_replace( ' ', '', $row->Type ) ) !== strtolower( str_replace( ' ', '', $col_type ) ) ) ) { // phpcs:ignore
						++$diffs;
					}
					if ( ( null !== $is_null ) && ( $row->Null !== $is_null ) ) { // phpcs:ignore
						++$diffs;
					}
					if ( ( null !== $key ) && ( $row->Key !== $key ) ) { // phpcs:ignore
						++$diffs;
					}
					if ( ( null !== $default ) && ( $row->Default !== $default ) ) { // phpcs:ignore
						++$diffs;
					}
					if ( ( null !== $extra ) && ( $row->Extra !== $extra ) ) { // phpcs:ignore
						++$diffs;
					}

					if ( $diffs > 0 ) {
						return false;
					}

					return true;
				} // End if found our column.
			}

			return false;
		}

		/**
		 * Checks and returns last mysql error
		 *
		 * @param \WPDB $_wpdb - The Mysql resource class.
		 *
		 * @return integer
		 *
		 * @since      4.4.2.1
		 */
		public static function get_last_sql_error( $_wpdb ): int {
			$code = 0;
			if ( $_wpdb->dbh instanceof \mysqli ) {
				$code = \mysqli_errno( $_wpdb->dbh ); // phpcs:ignore
			}

			if ( is_resource( $_wpdb->dbh ) ) {
				// Please do not report this code as a PHP 7 incompatibility. Observe the surrounding logic.
				// phpcs:ignore
				$code = mysql_errno( $_wpdb->dbh );
			}
			return $code;
		}

		/**
		 * Drop the table from the DB.
		 */
		public static function drop_table() {
			$table_name = self::get_table_name();
			self::get_connection()->query( 'DROP TABLE IF EXISTS ' . $table_name ); // phpcs:ignore
		}

		/**
		 * Checks if give table exists
		 *
		 * @param string $table_name - The table to check for.
		 *
		 * @return boolean
		 *
		 * @since      4.4.2.1
		 */
		public static function check_table_exists( string $table_name ): bool {
			foreach ( self::get_connection()->get_col( 'SHOW TABLES', 0 ) as $table ) {
				if ( $table === $table_name ) {
					return true;
				}
			}
			return false;
		}
	}
}
