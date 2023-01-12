<?php
/**
 * Class: Helper responsible for extracting settings values.
 *
 * Helper class used for settings.
 *
 * @package wsal
 */

declare(strict_types=1);

namespace WSAL\Helpers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Helpers\Settings_Helper' ) ) {

	/**
	 * Responsible for setting operations
	 *
	 * @since 4.4.3
	 */
	class Settings_Helper {

		/**
		 * Gets the value of an option.
		 *
		 * @method get_option_value
		 *
		 * @param  string $option_name option name we want to get a value for.
		 * @param  mixed  $default     a default value to use when one doesn't exist.
		 *
		 * @return mixed
		 *
		 * @since 4.4.3
		 */
		public static function get_option_value( $option_name = '', $default = null ) {
			// bail early if no option name was requested.
			if ( empty( $option_name ) || ! is_string( $option_name ) ) {
				return;
			}

			$actual_option_name = self::convert_name_prefix_if_needed( $option_name );

			if ( 0 !== strpos( $option_name, WSAL_PREFIX ) ) {
				$actual_option_name = WSAL_PREFIX . $option_name;
			}

			return self::get_option_value_internal( $actual_option_name, $default );
		}

		/**
		 * Gets the boolean value of an option.
		 *
		 * @method get_option_value
		 *
		 * @param  string $option_name option name we want to get a value for.
		 * @param  mixed  $default     a default value to use when one doesn't exist.
		 *
		 * @return mixed
		 *
		 * @since 4.4.3
		 */
		public static function get_boolean_option_value( $option_name = '', $default = null ) {

			return \WSAL\Helpers\Options::string_to_bool( self::get_option_value( $option_name, \WSAL\Helpers\Options::string_to_bool( $default ) ) );
		}

		/**
		 * Sets the value of an option.
		 *
		 * @method set_option_value
		 *
		 * @param  string $option_name The name of option to save.
		 * @param  mixed  $value       A value to store under the option name.
		 * @param  bool   $autoload    Whether or not to autoload this option.
		 * @return bool Whether or not the option w\as updated.
		 *
		 * @since 4.4.3
		 */
		public static function set_option_value( $option_name = '', $value = null, $autoload = false ) {
			// bail early if no option name or value w\as passed.
			if ( empty( $option_name ) || null === $value ) {
				return;
			}

			$actual_option_name = self::convert_name_prefix_if_needed( $option_name );

			return self::set_option_value_internal( $actual_option_name, $value, $autoload );
		}

		/**
		 * Sets the boolean value of an option.
		 *
		 * @method set_option_value
		 *
		 * @param  string $option_name The name of option to save.
		 * @param  mixed  $value       A value to store under the option name.
		 * @param  bool   $autoload    Whether or not to autoload this option.
		 * @return bool Whether or not the option w\as updated.
		 *
		 * @since 4.4.3
		 */
		public static function set_boolean_option_value( $option_name = '', $value = null, $autoload = false ) {
			return self::set_option_value( $option_name, \WSAL\Helpers\Options::bool_to_string( \WSAL\Helpers\Options::string_to_bool( $value ) ), $autoload );
		}

		/**
		 * Deletes option value.
		 *
		 * @param string $option_name - The name of the option / setting which needs to be deleted.
		 *
		 * @return mixed
		 *
		 * @since 4.4.3
		 */
		public static function delete_option_value( string $option_name = '' ) {
			return WP_Helper::delete_global_option( $option_name );
		}

		/**
		 * This method returns all the connections for the MySQL databases, and filters them based on the type specified.
		 * The possible types currently supported are:
		 *  - archive (default)
		 *  - adapter
		 *
		 * The idea is to return all the connections available for given type, so the user can not choose same connection for different types. The reason for that is that the plugin currently is using the same table names for archiving and storing the logs, which on the other hand could lead to problems.
		 *
		 * @param string $type - The type of the connection to filter by.
		 *
		 * @return array
		 *
		 * @since 4.4.3
		 */
		public static function get_mysql_connections_exclude_by_type( string $type = 'archive' ): array {
			$connections = self::get_options_by_prefix( WSAL_CONN_PREFIX, true );
			foreach ( $connections as $key => &$connection ) {
				$connection['option_value'] = \maybe_unserialize( $connection['option_value'] );
				if ( 'mysql' !== $connection['option_value']['type'] ) {
					unset( $connections[ $key ] );
				}
			}
			unset( $connection );
			$external_connection = self::get_options_by_prefix( 'adapter', true );
			if ( is_array( $external_connection ) && ! empty( $external_connection ) ) {
				$external_connection = $external_connection[0];
			}
			$archive_connection = self::get_default_connection_for_type( 'archive' );
			if ( 'archive' === $type ) {
				if ( ! empty( $external_connection ) ) {
					foreach ( $connections as $key => &$connection ) {
						if ( $external_connection['option_value'] === $connection['option_value']['name'] ) {
							unset( $connections[ $key ] );
						}
					}
					unset( $connection );
				}
			} else {
				if ( ! empty( $archive_connection ) ) {
					foreach ( $connections as $key => &$connection ) {
						if ( $archive_connection['option_value'] === $connection['option_value']['name'] ) {
							unset( $connections[ $key ] );
						}
					}
				}
			}

			return $connections;
		}

		/**
		 * Returns the currently selected connection for MySQL of given type
		 * The possible types currently supported are:
		 *  - archive (default)
		 *  - adapter
		 *
		 * @param string $type - The type of connection.
		 *
		 * @return array
		 *
		 * @since 4.4.3
		 */
		public static function get_default_connection_for_type( string $type = 'archive' ): array {
			if ( 'archive' === $type ) {
				$connection = self::get_options_by_prefix( 'archive-connection', true );
				if ( isset( $connection[0] ) ) {
					return $connection[0];
				}
			} else {
				$connection = self::get_options_by_prefix( 'adapter-connection', true );
				if ( isset( $connection[0] ) ) {
					return $connection[0];
				}
			}

			return array();
		}

		/**
		 * Get options by prefix.
		 *
		 * @param string  $opt_prefix - Prefix.
		 * @param boolean $as_array - Should we return array instead of object.
		 *
		 * @return array|null - Options.
		 *
		 * @since 4.4.3
		 */
		public static function get_options_by_prefix( $opt_prefix, $as_array = false ) {
			global $wpdb;

			$opt_prefix = self::convert_name_prefix_if_needed( $opt_prefix );

			$prepared_query	= $wpdb->prepare( // phpcs:ignore
                "SELECT * FROM {$wpdb->base_prefix}options WHERE option_name LIKE %s;",
                $opt_prefix . '%%'
			);

			return $wpdb->get_results( $prepared_query, ($as_array)?ARRAY_A:OBJECT); // phpcs:ignore
		}

		/**
		 * Returns all the connections currently stored in the options
		 *
		 * @return array
		 *
		 * @since 4.4.3
		 */
		public static function get_all_connections() {
			$connections_options = self::get_options_by_prefix( WSAL_CONN_PREFIX, true );

			$connections = array();

			foreach ( $connections_options as $connection ) {
				$connections[] = \maybe_unserialize( $connection['option_value'] );
			}

			return $connections;
		}

		/**
		 * Gets configuration data for all mirrors.
		 *
		 * @return array
		 *
		 * @since 4.4.3
		 */
		public static function get_all_mirrors() {
			$mirrors_options = self::get_options_by_prefix( WSAL_MIRROR_PREFIX, true );

			$mirrors = array();

			foreach ( $mirrors_options as $mirror ) {
				$mirrors[] = \maybe_unserialize( $mirror['option_value'] );
			}

			return $mirrors;

		}

		/**
		 * Check if archiving is enabled.
		 *
		 * @return bool value
		 *
		 * @since 4.4.3
		 */
		public static function is_archiving_enabled() {
			return self::get_option_value( 'archiving-e' );
		}

		/**
		 * Checks if archiving is enabled, the connection is selected and the archiving is not stopped
		 *
		 * @return boolean
		 *
		 * @since 4.4.3
		 */
		public static function is_archiving_set_and_enabled(): bool {
			return ( self::is_archiving_enabled() && self::get_default_connection_for_type( 'archive' ) && ! self::is_archiving_stopped() );
		}

		/**
		 * Check if archiving stop.
		 *
		 * @return bool value
		 *
		 * @since 4.4.3
		 */
		public static function is_archiving_stopped() {
			return self::get_option_value( 'archiving-stop' );
		}

		/**
		 * Adds prefix to the given option name is necessary
		 *
		 * @param string $name - The option name to check and add prefix if needed.
		 *
		 * @return string
		 *
		 * @since 4.4.3
		 */
		private static function convert_name_prefix_if_needed( string $name ): string {
			$name = trim( $name );
			if ( 0 !== strpos( $name, WSAL_PREFIX ) ) {
				$name = WSAL_PREFIX . $name;
			}

			return $name;
		}

		/**
		 * Internal function used to get the value of an option. Any necessary prefixes are already contained in the option
		 * name.
		 *
		 * @param string $option_name Option name we want to get a value for including necessary plugin prefix.
		 * @param mixed  $default     a default value to use when one doesn't exist.
		 *
		 * @return mixed
		 *
		 * @since 4.4.3
		 */
		private static function get_option_value_internal( $option_name = '', $default = null ) {
			// bail early if no option name w\as requested.
			if ( empty( $option_name ) || ! is_string( $option_name ) ) {
				return;
			}

			if ( \is_multisite() ) {
				\switch_to_blog( \get_main_network_id() );
			}

			$result = \get_option( $option_name, $default );

			if ( \is_multisite() ) {
				\restore_current_blog();
			}

			return \maybe_unserialize( $result );
		}


		/**
		 * Internal function used to set the value of an option. Any necessary prefixes are already contained in the option
		 * name.
		 *
		 * @param string $option_name Option name we want to save a value for including necessary plugin prefix.
		 * @param mixed  $value       A value to store under the option name.
		 * @param bool   $autoload    Whether to autoload this option.
		 *
		 * @return bool Whether the option w\as updated.
		 * @since  latest
		 */
		private static function set_option_value_internal( $option_name = '', $value = null, $autoload = false ) {
			// bail early if no option name or value w\as passed.
			if ( empty( $option_name ) || null === $value ) {
				return;
			}

			if ( is_multisite() ) {
				\switch_to_blog( \get_main_network_id() );
			}

			if ( false === $autoload ) {
				\delete_option( $option_name );
				$result = \add_option( $option_name, $value, '', $autoload );
			} else {
				$result = \update_option( $option_name, $value, $autoload );
			}

			if ( \is_multisite() ) {
				\restore_current_blog();
			}

			return $result;
		}
	}
}
