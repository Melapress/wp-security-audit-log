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

use WSAL\Controllers\Alert_Manager;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Helpers\Settings_Helper' ) ) {
	/**
	 * Responsible for setting operations.
	 *
	 * @since 4.4.3
	 */
	class Settings_Helper {
		/**
		 * Option name for front-end events.
		 *
		 * @var string
		 */
		public const FRONT_END_EVENTS_OPTION_NAME = 'wsal_frontend-events';

		public const ERROR_CODE_INVALID_IP = 901;

		/**
		 * IP excluded from monitoring.
		 *
		 * @var array
		 *
		 * @since 4.3.0
		 */
		private static $excluded_ips = array();

		/**
		 * Holds the array with the excluded post types.
		 *
		 * @var array
		 *
		 * @since 4.3.0
		 */
		private static $excluded_post_types = array();

		/**
		 * Custom post meta fields excluded from monitoring.
		 *
		 * @var array
		 *
		 * @since 4.5.0
		 */
		private static $excluded_post_meta = array();

		/**
		 * Custom user meta fields excluded from monitoring.
		 *
		 * @var array
		 *
		 * @since 4.5.0
		 */
		private static $excluded_user_meta = array();

		/**
		 * Users excluded from monitoring.
		 *
		 * @var array
		 *
		 * @since 4.5.0
		 */
		private static $excluded_users = array();

		/**
		 * Users excluded from monitoring.
		 *
		 * @var array
		 *
		 * @since 4.5.0
		 */
		private static $excluded_roles = array();

		/**
		 * Holds the main IP of the client.
		 *
		 * @var string
		 *
		 * @since 4.5.0
		 */
		private static $main_client_ip = '';

		/**
		 * Array with all the client IP addresses.
		 *
		 * @var array
		 *
		 * @since 4.5.0
		 */
		private static $client_ips = array();

		/**
		 * Alerts disabled by default - duplication of the above for faster access via static call.
		 *
		 * @var int[]
		 *
		 * @since 4.5.0
		 */
		private static $default_always_disabled_alerts = array( 5010, 5011, 5012, 5013, 5014, 5015, 5016, 5017, 5018, 5022, 5023, 5024 );

		/**
		 * Holds the array with the disabled alerts codes.
		 *
		 * @var array
		 *
		 * @since 4.5.0
		 */
		private static $disabled_alerts = array();

		/**
		 * Is the database logging enabled or not.
		 *
		 * @var bool
		 *
		 * @since 4.5.0
		 */
		private static $database_logging_enabled = null;

		/**
		 * Is the database logging enabled or not.
		 *
		 * @var bool
		 *
		 * @since 4.5.1
		 */
		private static $frontend_events = null;

		/**
		 * Gets the value of an option.
		 *
		 * @method get_option_value
		 *
		 * @param string $option_name option name we want to get a value for.
		 * @param mixed  $default     a default value to use when one doesn't exist.
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
		 * @param string $option_name option name we want to get a value for.
		 * @param mixed  $default     a default value to use when one doesn't exist.
		 *
		 * @return mixed
		 *
		 * @since 4.4.3
		 */
		public static function get_boolean_option_value( $option_name = '', $default = null ) {
			return self::string_to_bool( self::get_option_value( $option_name, self::string_to_bool( $default ) ) );
		}

		/**
		 * Sets the value of an option.
		 *
		 * @method set_option_value
		 *
		 * @param string $option_name The name of option to save.
		 * @param mixed  $value       A value to store under the option name.
		 * @param bool   $autoload    Whether or not to autoload this option.
		 *
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
		 * @param string $option_name The name of option to save.
		 * @param mixed  $value       A value to store under the option name.
		 * @param bool   $autoload    Whether or not to autoload this option.
		 *
		 * @return bool Whether or not the option w\as updated.
		 *
		 * @since 4.4.3
		 */
		public static function set_boolean_option_value( $option_name = '', $value = null, $autoload = false ) {
			return self::set_option_value( $option_name, self::bool_to_string( self::string_to_bool( $value ) ), $autoload );
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
		 *  - adapter.
		 *
		 * The idea is to return all the connections available for given type, so the user can not choose same connection for different types. The reason for that is that the plugin currently is using the same table names for archiving and storing the logs, which on the other hand could lead to problems.
		 *
		 * @param string $type - The type of the connection to filter by.
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
			} elseif ( ! empty( $archive_connection ) ) {
				foreach ( $connections as $key => &$connection ) {
					if ( $archive_connection['option_value'] === $connection['option_value']['name'] ) {
						unset( $connections[ $key ] );
					}
				}
			}

			return $connections;
		}

		/**
		 * Returns the currently selected connection for MySQL of given type
		 * The possible types currently supported are:
		 *  - archive (default)
		 *  - adapter.
		 *
		 * @param string $type - The type of connection.
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
		 * @param string $opt_prefix - Prefix.
		 * @param bool   $as_array   - Should we return array instead of object.
		 *
		 * @return array|null - Options.
		 *
		 * @since 4.4.3
		 */
		public static function get_options_by_prefix( $opt_prefix, $as_array = false ) {
			global $wpdb;

			$opt_prefix = self::convert_name_prefix_if_needed( $opt_prefix );

			$prepared_query = $wpdb->prepare( // phpcs:ignore
				"SELECT * FROM {$wpdb->base_prefix}options WHERE option_name LIKE %s;",
				$opt_prefix . '%%'
			);

			return $wpdb->get_results($prepared_query, ($as_array) ? ARRAY_A : OBJECT); // phpcs:ignore
		}

		/**
		 * Returns all the connections currently stored in the options.
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
		 * Returns the connection for the passed mirror.
		 *
		 * @param string $connection_name - The name of the connection.
		 *
		 * @return array|false
		 *
		 * @since 4.4.3.2
		 */
		public static function get_connection_by_name( $connection_name ) {
			$connections = self::get_all_connections();
			foreach ( $connections as $connection ) {
				if ( $connection['name'] === $connection_name ) {
					return $connection;
				}
			}

			return false;
		}

		/**
		 * Gets configuration data for all mirrors.
		 *
		 * @return array
		 *
		 * @since 4.4.3
		 */
		public static function get_all_mirrors() {
			$mirrors_options = self::get_options_by_prefix( \WSAL_MIRROR_PREFIX, true );

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
		 * Checks if archiving is enabled, the connection is selected and the archiving is not stopped.
		 *
		 * @since 4.4.3
		 */
		public static function is_archiving_set_and_enabled(): bool {
			return self::is_archiving_enabled() && self::get_default_connection_for_type( 'archive' ) && ! self::is_archiving_stopped();
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
		 * Adds prefix to the given option name is necessary.
		 *
		 * @param string $name - The option name to check and add prefix if needed.
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

			if ( WP_Helper::is_multisite() ) {
				\switch_to_blog( \get_main_network_id() );
			}

			$result = \get_option( $option_name, $default );

			if ( WP_Helper::is_multisite() ) {
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
		 *
		 * @since  latest
		 */
		private static function set_option_value_internal( $option_name = '', $value = null, $autoload = false ) {
			// bail early if no option name or value w\as passed.
			if ( empty( $option_name ) || null === $value ) {
				return;
			}

			if ( WP_Helper::is_multisite() ) {
				\switch_to_blog( \get_main_network_id() );
			}

			if ( false === $autoload ) {
				\delete_option( $option_name );
				$result = \add_option( $option_name, $value, '', $autoload );
			} else {
				$result = \update_option( $option_name, $value, $autoload );
			}

			if ( WP_Helper::is_multisite() ) {
				\restore_current_blog();
			}

			return $result;
		}

		/**
		 * Get main client IP.
		 *
		 * @return string|null
		 *
		 * @since 4.5.0
		 */
		public static function get_main_client_ip() {
			if ( '' === self::$main_client_ip ) {
				if ( self::get_boolean_option_value( 'use-proxy-ip' ) ) {
					// TODO: The algorithm below just gets the first IP in the list...we might want to make this more intelligent somehow.
					$ips                  = self::get_client_ips();
					$ips                  = reset( $ips );
					self::$main_client_ip = isset( $ips[0] ) ? $ips[0] : '';
				} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
					$ip                   = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
					self::$main_client_ip = self::normalize_ip( $ip );

					if ( ! Validator::validate_ip( self::$main_client_ip ) ) {
						self::$main_client_ip = 'Error ' . self::ERROR_CODE_INVALID_IP . ': Invalid IP Address';
					}
				}
			}

			return self::$main_client_ip;
		}

		/**
		 * Get client IP addresses.
		 *
		 * @return array
		 *
		 * @since 4.5.0
		 */
		public static function get_client_ips() {
			if ( empty( self::$client_ips ) ) {
				$proxy_headers = array(
					'HTTP_CLIENT_IP',
					'HTTP_X_FORWARDED_FOR',
					'HTTP_X_FORWARDED',
					'HTTP_X_CLUSTER_CLIENT_IP',
					'X-ORIGINAL-FORWARDED-FOR',
					'HTTP_FORWARDED_FOR',
					'HTTP_FORWARDED',
					'REMOTE_ADDR',
					// Cloudflare.
					'HTTP_CF-Connecting-IP',
					'HTTP_TRUE_CLIENT_IP',
				);
				foreach ( $proxy_headers as $key ) {
					if ( isset( $_SERVER[ $key ] ) ) {
						self::$client_ips[ $key ] = array();

						foreach ( explode( ',', sanitize_text_field( \wp_unslash( $_SERVER[ $key ] ) ) ) as $ip ) {
							$ip = self::normalize_ip( $ip );
							if ( Validator::validate_ip( $ip ) ) {
								self::$client_ips[ $key ][] = $ip;
							}
						}
					}
				}
			}

			return self::$client_ips;
		}

		/**
		 * Normalize IP address, i.e., remove the port number.
		 *
		 * @param string $ip - IP address.
		 *
		 * @since 4.5.0
		 *
		 * @return string
		 */
		public static function normalize_ip( $ip ) {
			$ip = trim( $ip );

			if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 ) ) {
				return $ip;
			}

			$ip = parse_url( 'http://' . $ip, PHP_URL_HOST );

			$ip = str_replace( array( '[', ']' ), '', $ip );

			return $ip;
		}

		/**
		 * Retrieves a list of IP addresses to exclude from monitoring.
		 *
		 * @return array List of IP addresses to exclude from monitoring.
		 *
		 * @since 4.5.0
		 */
		public static function get_excluded_monitoring_ip() {
			if ( empty( self::$excluded_ips ) ) {
				self::$excluded_ips = array_unique( array_filter( explode( ',', self::get_option_value( 'excluded-ip', '' ) ) ) );
			}

			return self::$excluded_ips;
		}

		/**
		 * Return the range of IP address from 127.0.0.0-24 to 127.0.0.0-127.0.0.24 format.
		 *
		 * @param string $range - Range of IP address.
		 *
		 * @return object
		 *
		 * @since 4.5.0
		 */
		public static function get_ipv4_by_range( $range ) {
			list($lower_ip, $upper_ip) = explode( '-', $range, 2 );

			$lower_arr = explode( '.', $lower_ip );
			$count     = count( $lower_arr );
			unset( $lower_arr[ $count - 1 ] );
			$upper_ip = implode( '.', $lower_arr ) . '.' . $upper_ip;

			return (object) array(
				'lower' => $lower_ip,
				'upper' => $upper_ip,
			);
		}

		/**
		 * Check if IP is in range for IPv4.
		 *
		 * This function takes 2 arguments, an IP address and a "range" in several different formats.
		 *
		 * Network ranges can be specified as:
		 * 1. Wildcard format:     1.2.3.*
		 * 2. CIDR format:         1.2.3/24  OR  1.2.3.4/255.255.255.0
		 * 3. Start-End IP format: 1.2.3.0-1.2.3.255
		 *
		 * The function will return true if the supplied IP is within the range.
		 * Note little validation is done on the range inputs - it expects you to
		 * use one of the above 3 formats.
		 *
		 * @see https://github.com/cloudflarearchive/Cloudflare-Tools/blob/master/cloudflare/ip_in_range.php#L55
		 *
		 * @param string $ip    - IP address.
		 * @param string $range - Range of IP address.
		 *
		 * @return bool
		 *
		 * @since 4.5.0
		 */
		public static function check_ipv4_in_range( $ip, $range ) {
			if ( false !== strpos( $range, '/' ) ) {
				// $range is in IP/NETMASK format.
				list($range, $netmask) = explode( '/', $range, 2 );

				if ( false !== strpos( $netmask, '.' ) ) {
					// $netmask is a 255.255.0.0 format.
					$netmask     = str_replace( '*', '0', $netmask );
					$netmask_dec = ip2long( $netmask );

					return ( ip2long( $ip ) & $netmask_dec ) === ( ip2long( $range ) & $netmask_dec );
				} else {
					// $netmask is a CIDR size block
					// fix the range argument.
					$x       = explode( '.', $range );
					$x_count = count( $x );

					while ( $x_count < 4 ) {
						$x[]     = '0';
						$x_count = count( $x );
					}

					list($a, $b, $c, $d) = $x;
					$range               = sprintf( '%u.%u.%u.%u', empty( $a ) ? '0' : $a, empty( $b ) ? '0' : $b, empty( $c ) ? '0' : $c, empty( $d ) ? '0' : $d );
					$range_dec           = ip2long( $range );
					$ip_dec              = ip2long( $ip );

					// Strategy 1 - Create the netmask with 'netmask' 1s and then fill it to 32 with 0s
					// $netmask_dec = bindec(str_pad('', $netmask, '1') . str_pad('', 32-$netmask, '0'));
					// Strategy 2 - Use math to create it.
					$wildcard_dec = pow( 2, ( 32 - $netmask ) ) - 1;
					$netmask_dec  = ~ $wildcard_dec;

					return ( $ip_dec & $netmask_dec ) === ( $range_dec & $netmask_dec );
				}
			} else {
				// Range might be 255.255.*.* or 1.2.3.0-1.2.3.255.
				if ( false !== strpos( $range, '*' ) ) { // a.b.*.* format
					// Just convert to A-B format by setting * to 0 for A and 255 for B.
					$lower = str_replace( '*', '0', $range );
					$upper = str_replace( '*', '255', $range );
					$range = "$lower-$upper";
				}

				// A-B format.
				if ( false !== strpos( $range, '-' ) ) {
					list($lower, $upper) = explode( '-', $range, 2 );
					$lower_dec           = (float) sprintf( '%u', ip2long( $lower ) );
					$upper_dec           = (float) sprintf( '%u', ip2long( $upper ) );
					$ip_dec              = (float) sprintf( '%u', ip2long( $ip ) );

					return ( $ip_dec >= $lower_dec ) && ( $ip_dec <= $upper_dec );
				}

				return false;
			}
		}

		/**
		 * Get Custom Post Types excluded from monitoring.
		 *
		 * @since 4.5.0
		 */
		public static function get_excluded_post_types(): array {
			if ( empty( self::$excluded_post_types ) ) {
				self::$excluded_post_types = array();
				if ( ! is_null( self::get_option_value( 'custom-post-types' ) ) ) {
					self::$excluded_post_types = array_unique( array_filter( explode( ',', self::get_option_value( 'custom-post-types' ) ) ) );
				}
			}

			return self::$excluded_post_types;
		}

		/**
		 * Method: Set Disabled Alerts.
		 *
		 * @param array $types IDs alerts to disable.
		 */
		public static function set_disabled_alerts( $types ) {
			self::$disabled_alerts = array_unique( array_map( 'intval', $types ) );
			self::set_option_value( 'disabled-alerts', implode( ',', self::$disabled_alerts ) );
		}

		/**
		 * Return IDs of disabled alerts.
		 *
		 * @return array
		 *
		 * @since 4.5.0
		 */
		public static function get_disabled_alerts() {
			if ( ! self::$disabled_alerts ) {
				$disabled_defaults     = self::get_default_disabled_alerts() + self::get_default_always_disabled_alerts();
				self::$disabled_alerts = implode( ',', $disabled_defaults );
				self::$disabled_alerts = self::get_option_value( 'disabled-alerts', self::$disabled_alerts );
				self::$disabled_alerts = ( '' === self::$disabled_alerts ) ? array() : explode( ',', self::$disabled_alerts );
				self::$disabled_alerts = array_map( 'intval', self::$disabled_alerts );

				self::$disabled_alerts = ( ! is_array( self::$disabled_alerts ) ) ? explode( ',', self::$disabled_alerts ) : self::$disabled_alerts;
			}

			if ( ! \is_array( self::$disabled_alerts ) ) {
				self::$disabled_alerts = array();
			}

			return self::$disabled_alerts;
		}

		/**
		 * Retrieves a list of alerts disabled by default.
		 *
		 * @return int[] List of alerts disabled by default.
		 *
		 * @since 4.5.0
		 */
		public static function get_default_disabled_alerts() {
			return array( 0000, 0001, 0002, 0003, 0004, 0005 );
		}

		/**
		 * Returns default disabled alerts statically.
		 *
		 * @since 4.5.0
		 */
		public static function get_default_always_disabled_alerts(): array {
			return self::$default_always_disabled_alerts;
		}

		/**
		 * Checks if the database logging is enabled.
		 *
		 * The database logging is enabled if there are no mirrors or if dedicated database option is not false.
		 *
		 * @return bool
		 *
		 * @since 4.5.0
		 */
		public static function is_database_logging_enabled() {
			if ( null === self::$database_logging_enabled ) {
				$is_disabled = self::get_boolean_option_value( 'db_logging_disabled', false );

				if ( ! $is_disabled ) {
					// If the logging is enabled, we don't need to check mirrors.
					if ( class_exists( 'WSAL_Extension_Manager' ) ) {
						if ( class_exists( 'WSAL_Ext_Plugin' ) ) {
							$ext_plugin = new \WSAL_Ext_Plugin();

							$wsal                   = \WpSecurityAuditLog::get_instance();
							$wsal->external_db_util = new \WSAL_Ext_Common( $wsal, $ext_plugin );

							if ( ! $is_disabled || is_null( $wsal->external_db_util ) ) {
								self::$database_logging_enabled = true;

								return self::$database_logging_enabled;
							}
						}
					}
				}

				// If the logging is disabled, we also need to check if there are any available mirrors.
				$mirrors = self::get_all_mirrors();

				self::$database_logging_enabled = empty( $mirrors );
			}

			return self::$database_logging_enabled;
		}

		/**
		 * Retrieves a list of post meta fields excluded from monitoring.
		 *
		 * @return array
		 *
		 * @since 4.5.0
		 */
		public static function get_excluded_post_meta_fields() {
			if ( empty( self::$excluded_post_meta ) ) {
				self::$excluded_post_meta = array_unique( array_filter( explode( ',', self::get_option_value( 'excluded-post-meta', '' ) ) ) );
				asort( self::$excluded_post_meta );
			}

			return self::$excluded_post_meta;
		}

		/**
		 * Updates custom post meta fields excluded from monitoring.
		 *
		 * @param array $custom Excluded post meta fields.
		 */
		public static function set_excluded_post_meta_fields( $custom ) {
			$old_value = self::get_excluded_post_meta_fields();
			$changes   = self::determine_added_and_removed_items( $old_value, implode( ',', $custom ) );

			if ( ! empty( $changes['added'] ) ) {
				foreach ( $changes['added'] as $custom_field ) {
					Alert_Manager::trigger_event(
						6057,
						array(
							'custom_field'    => $custom_field,
							'previous_fields' => ( empty( $old_value ) ) ? self::tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
							'EventType'       => 'added',
						)
					);
				}
			}

			if ( ! empty( $changes['removed'] ) && ! empty( $old_value ) ) {
				foreach ( $changes['removed'] as $custom_field ) {
					Alert_Manager::trigger_event(
						6057,
						array(
							'custom_field'    => $custom_field,
							'previous_fields' => empty( $old_value ) ? self::tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
							'EventType'       => 'removed',
						)
					);
				}
			}

			self::$excluded_post_meta = $custom;
			self::set_option_value( 'excluded-post-meta', esc_html( implode( ',', self::$excluded_post_meta ) ) );
		}

		/**
		 * Determines added and removed items between 2 arrays.
		 *
		 * @param array|string $old_value Old list. Support comma separated string.
		 * @param array|string $value     New list. Support comma separated string.
		 *
		 * @return array
		 *
		 * @since 4.5.0
		 */
		public static function determine_added_and_removed_items( $old_value, $value ) {
			$old_value         = ( ! is_array( $old_value ) ) ? explode( ',', $old_value ) : $old_value;
			$value             = ( ! is_array( $value ) ) ? explode( ',', $value ) : $value;
			$return            = array();
			$return['removed'] = array_filter( array_diff( $old_value, $value ) );
			$return['added']   = array_filter( array_diff( $value, $old_value ) );

			return $return;
		}

		/**
		 * Tidies-up the blank values.
		 *
		 * @param string $value Value.
		 *
		 * @return string Tidies up value.
		 *
		 * @since 4.5.0
		 */
		public static function tidy_blank_values( $value ) {
			return ( empty( $value ) ) ? __( 'None provided', 'wp-security-audit-log' ) : $value;
		}

		/**
		 * Retrieves a list of user meta fields excluded from monitoring.
		 *
		 * @return array
		 *
		 * @since 4.5.0
		 */
		public static function get_excluded_user_meta_fields() {
			if ( empty( self::$excluded_user_meta ) ) {
				$excluded_user_meta = self::get_option_value( 'excluded-user-meta', '' );
				if ( ! is_null( $excluded_user_meta ) && ! empty( $excluded_user_meta ) ) {
					self::$excluded_user_meta = array_unique( array_filter( explode( ',', $excluded_user_meta ) ) );
					asort( self::$excluded_user_meta );
				} else {
					self::$excluded_user_meta = array();
				}
			}

			return self::$excluded_user_meta;
		}

		/**
		 * Updates custom user meta fields excluded from monitoring.
		 *
		 * @param array $custom Custom user meta fields excluded from monitoring.
		 *
		 * @since 4.5.0
		 */
		public static function set_excluded_user_meta_fields( $custom ) {
			$old_value = self::get_excluded_user_meta_fields();
			$changes   = self::determine_added_and_removed_items( $old_value, implode( ',', $custom ) );

			if ( ! empty( $changes['added'] ) ) {
				foreach ( $changes['added'] as $custom_field ) {
					Alert_Manager::trigger_event(
						6058,
						array(
							'custom_field'    => $custom_field,
							'previous_fields' => ( empty( $old_value ) ) ? self::tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
							'EventType'       => 'added',
						)
					);
				}
			}

			if ( ! empty( $changes['removed'] ) && ! empty( $old_value ) ) {
				foreach ( $changes['removed'] as $custom_field ) {
					Alert_manager::trigger_event(
						6058,
						array(
							'custom_field'    => $custom_field,
							'previous_fields' => empty( $old_value ) ? self::tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
							'EventType'       => 'removed',
						)
					);
				}
			}

			self::$excluded_user_meta = $custom;
			self::set_option_value( 'excluded-user-meta', esc_html( implode( ',', self::$excluded_user_meta ) ) );
		}

		/**
		 * Retrieves the users excluded from monitoring.
		 *
		 * @return array Users excluded from monitoring.
		 *
		 * @since 4.5.0
		 */
		public static function get_excluded_monitoring_users() {
			if ( empty( self::$excluded_users ) ) {
				self::$excluded_users = self::get_option_value( 'excluded-users', array() );
			}

			if ( is_string( self::$excluded_users ) ) {
				self::$excluded_users = array_unique( array_filter( explode( ',', self::$excluded_users ) ) );
				self::set_option_value( 'excluded-users', self::$excluded_users );
			}

			return self::$excluded_users;
		}

		/**
		 * Sets the users excluded from monitoring.
		 *
		 * @param array $users Users to be excluded.
		 *
		 * @since 4.5.0
		 */
		public static function set_excluded_monitoring_users( $users ) {
			$old_value = self::get_option_value( 'excluded-users', array() );
			$changes   = self::determine_added_and_removed_items( $old_value, $users );

			if ( ! empty( $changes['added'] ) ) {
				foreach ( $changes['added'] as $user ) {
					Alert_Manager::trigger_event(
						6053,
						array(
							'user'           => $user,
							'previous_users' => ( empty( $old_value ) ) ? self::tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
							'EventType'      => 'added',
						)
					);
				}
			}
			if ( ! empty( $changes['removed'] ) && ! empty( $old_value ) ) {
				foreach ( $changes['removed'] as $user ) {
					Alert_Manager::trigger_event(
						6053,
						array(
							'user'           => $user,
							'previous_users' => empty( $old_value ) ? self::tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
							'EventType'      => 'removed',
						)
					);
				}
			}

			self::$excluded_users = $users;
			self::set_option_value( 'excluded-users', array_unique( array_filter( $users ) ) );
		}

		/**
		 * Set roles excluded from monitoring.
		 *
		 * @param array $roles - Array of roles.
		 *
		 * @since 4.5.0
		 */
		public static function set_excluded_monitoring_roles( $roles ) {
			// Trigger alert.
			$old_value = self::get_option_value( 'excluded-roles', array() );
			$changes   = self::determine_added_and_removed_items( $old_value, $roles );

			if ( ! empty( $changes['added'] ) ) {
				foreach ( $changes['added'] as $user ) {
					Alert_Manager::trigger_event(
						6054,
						array(
							'role'           => $user,
							'previous_users' => ( empty( $old_value ) ) ? self::tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
							'EventType'      => 'added',
						)
					);
				}
			}
			if ( ! empty( $changes['removed'] ) && ! empty( $old_value ) ) {
				foreach ( $changes['removed'] as $user ) {
					Alert_Manager::trigger_event(
						6054,
						array(
							'role'           => $user,
							'previous_users' => empty( $old_value ) ? self::tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
							'EventType'      => 'removed',
						)
					);
				}
			}

			self::$excluded_roles = $roles;
			self::set_option_value( 'excluded-roles', array_unique( array_filter( $roles ) ) );
		}

		/**
		 * Get roles excluded from monitoring.
		 *
		 * @return array
		 *
		 * @since 4.5.0
		 */
		public static function get_excluded_monitoring_roles() {
			if ( empty( self::$excluded_roles ) ) {
				self::$excluded_roles = self::get_option_value( 'excluded-roles', array() );
			}

			if ( is_string( self::$excluded_roles ) ) {
				self::$excluded_roles = array_unique( array_filter( explode( ',', self::$excluded_roles ) ) );
				self::set_option_value( 'excluded-roles', self::$excluded_roles );
			}

			return self::$excluded_roles;
		}

		/**
		 * Check whether to log requests to file or not. Disabled by default and can be enabled only using a custom filter
		 * wsal_request_logging_enabled.
		 *
		 * @return bool
		 *
		 * @since 4.5.0
		 */
		public static function is_request_logging_enabled() {
			return apply_filters( 'wsal_request_logging_enabled', false );
		}

		/**
		 * Get options by prefix (notifications stored in json format).
		 *
		 * @param string $opt_prefix - Prefix.
		 *
		 * @return array|null - Options.
		 *
		 * @since 4.5.0
		 */
		public static function get_notifications_setting( $opt_prefix ) {
			global $wpdb;
			$prepared_query = $wpdb->prepare( // phpcs:ignore
				"SELECT * FROM {$wpdb->base_prefix}options WHERE option_name LIKE %s;",
				$opt_prefix . '%%'
			);

			return $wpdb->get_results($prepared_query); // phpcs:ignore
		}

		/**
		 * Loads notification.
		 *
		 * @param int $id Notification ID.
		 *
		 * @return array|object|void|null
		 *
		 * @since 4.5.0
		 */
		public static function get_notification( $id ) {
			global $wpdb;
			$prepared_query = $wpdb->prepare( "SELECT * FROM {$wpdb->options} WHERE option_id = %d LIMIT 1;", $id );

			return $wpdb->get_row($prepared_query); // phpcs:ignore
		}

		/**
		 * Number of options start with prefix.
		 *
		 * @param string $opt_prefix - Prefix.
		 *
		 * @return int Indicates the number of items.
		 *
		 * @since 4.5.0
		 */
		public static function count_notifications( $opt_prefix ) {
			global $wpdb;

			$prepared_query = $wpdb->prepare(
				"SELECT COUNT(option_id) FROM {$wpdb->options} WHERE option_name LIKE %s;",
				$opt_prefix . '%%'
			);

			return (int) $wpdb->get_var($prepared_query); // phpcs:ignore
		}

		/**
		 * Converts a string (e.g. 'yes' or 'no') to a bool.
		 *
		 * @param string $string String to convert.
		 *
		 * @return bool
		 *
		 * @since 4.5.0
		 */
		public static function string_to_bool( $string ) {
			return is_bool( $string ) ? $string : ( 'yes' === $string || 1 === $string || 'true' === $string || '1' === $string || 'on' === $string || 'enable' === $string );
		}

		/**
		 * Converts a bool to a 'yes' or 'no'.
		 *
		 * @param bool $bool String to convert.
		 *
		 * @return string
		 *
		 * @since 4.5.0
		 */
		public static function bool_to_string( $bool ) {
			if ( ! is_bool( $bool ) ) {
				$bool = self::string_to_bool( $bool );
			}

			return true === $bool ? 'yes' : 'no';
		}


		/**
		 * Retrieves the full path to plugin's working directory. Returns a folder path with a trailing slash. It also
		 * creates the folder unless the $skip_creation parameter is set to true.
		 *
		 * Default path is "{uploads folder}/wp-activity-log/" and can be change only using a constant WSAL_WORKING_DIR_PATH.
		 *
		 * @param string $path          Optional path relative to the working directory.
		 * @param bool   $skip_creation If true, the folder will not be created.
		 * @param bool   $ignore_site   If true, there will be no sub-site specific subfolder in multisite context.
		 *
		 * @return string|WP_Error
		 *
		 * @since 4.5.0
		 */
		public static function get_working_dir_path_static( $path = '', $skip_creation = false, $ignore_site = false ) {
			$result = '';

			// Work out the working directory base path.
			if ( defined( '\WSAL_WORKING_DIR_PATH' ) ) {
				$result = trailingslashit( \WSAL_WORKING_DIR_PATH );
			} else {
				$upload_dir = wp_upload_dir( null, false );
				if ( is_array( $upload_dir ) && array_key_exists( 'basedir', $upload_dir ) ) {
					$result = $upload_dir['basedir'] . '/wp-activity-log/';
				} elseif ( defined( 'WP_CONTENT_DIR' ) ) {
					// Fallback in case there is a problem with filesystem.
					$result = WP_CONTENT_DIR . '/uploads/wp-activity-log/';
				}
			}

			if ( empty( $result ) ) {
				// Empty result here means invalid custom path or a problem with WordPress (uploads folder issue or mission WP_CONTENT_DIR).
				return new \WP_Error( 'wsal_working_dir_base_missing', __( 'The base of WSAL working directory cannot be determined. Custom path is invalid or there is some other issue with your WordPress installation.' ) );
			}

			// Append site specific subfolder in multisite context.
			if ( ! $ignore_site && WP_Helper::is_multisite() ) {
				$site_id = get_current_blog_id();
				if ( $site_id > 0 ) {
					$result .= 'sites/' . $site_id . '/';
				}
			}

			// Append optional path passed as a parameter.
			if ( $path && is_string( $path ) ) {
				$result .= $path . '/';
			}

			$result = str_replace( '/', DIRECTORY_SEPARATOR, $result );

			if ( ! file_exists( $result ) ) {
				if ( ! $skip_creation ) {
					if ( ! wp_mkdir_p( $result ) ) {
						return new \WP_Error(
							'mkdir_failed',
							sprintf(
								/* translators: %s: Directory path. */
								__( 'Unable to create directory %s. Is its parent directory writable by the server?' ),
								esc_html( $result )
							)
						);
					}
				}

				File_Helper::create_index_file( $result );
				File_Helper::create_htaccess_file( $result );
			}

			return $result;
		}

		/**
		 * Get WSAL's frontend events option.
		 *
		 * @return array
		 *
		 * @since 4.5.0
		 */
		public static function get_frontend_events() {
			if ( null === self::$frontend_events ) {
				// Option defaults.
				$default               = array(
					'register'    => false,
					'login'       => false,
					'woocommerce' => false,
				);
				self::$frontend_events = self::get_option_value( self::FRONT_END_EVENTS_OPTION_NAME, $default );
			}

			// Get the option.
			return self::$frontend_events;
		}

		/**
		 * Set WSAL's frontend events option.
		 *
		 * @param array $value - Option values.
		 *
		 * @return bool
		 *
		 * @since 4.5.0
		 */
		public static function set_frontend_events( $value = array() ) {
			self::$frontend_events = $value;
			return self::set_option_value( self::FRONT_END_EVENTS_OPTION_NAME, $value, true );
		}

		/**
		 * Returns the timezone of the WordPress
		 *
		 * @return mixed
		 *
		 * @since 4.5.0
		 */
		public static function get_timezone() {
			return self::get_option_value( 'timezone', 'wp' );
		}

		/**
		 * Helper method to get the stored setting to determine if milliseconds
		 * appear in the admin list view. This should always be a bool.
		 *
		 * @method get_show_milliseconds
		 *
		 * @since  latest
		 *
		 * @return bool
		 */
		public static function get_show_milliseconds() {
			return self::get_boolean_option_value( 'show_milliseconds', true );
		}

		/**
		 * Date format based on WordPress date settings. It can be optionally sanitized to get format compatible with
		 * JavaScript date and time picker widgets.
		 *
		 * Note: This function must not be used to display actual date and time values anywhere. For that use function GetDateTimeFormat.
		 *
		 * @param bool $sanitized If true, the format is sanitized for use with JavaScript date and time picker widgets.
		 *
		 * @return string
		 */
		public static function get_date_format( $sanitized = false ) {
			if ( $sanitized ) {
				return 'Y-m-d';
			}

			return get_option( 'date_format' );
		}

		/**
		 * Time format based on WordPress date settings. It can be optionally sanitized to get format compatible with
		 * JavaScript date and time picker widgets.
		 *
		 * Note: This function must not be used to display actual date and time values anywhere. For that use function GetDateTimeFormat.
		 *
		 * @param bool $sanitize If true, the format is sanitized for use with JavaScript date and time picker widgets.
		 *
		 * @return string
		 */
		public static function get_time_format( $sanitize = false ) {
			$result = get_option( 'time_format' );
			if ( $sanitize ) {
				$search  = array( 'a', 'A', 'T', ' ' );
				$replace = array( '', '', '', '' );
				$result  = str_replace( $search, $replace, $result );
			}

			return $result;
		}

		/**
		 * Determines datetime format to be displayed in any UI in the plugin (logs in administration, emails, reports,
		 * notifications etc.).
		 *
		 * Note: Format returned by this function is not compatible with JavaScript date and time picker widgets. Use
		 * functions GetTimeFormat and GetDateFormat for those.
		 *
		 * @param bool $line_break             - True if line break otherwise false.
		 * @param bool $use_nb_space_for_am_pm - True if non-breakable space should be placed before the AM/PM chars.
		 *
		 * @return string
		 */
		public static function get_datetime_format( $line_break = true, $use_nb_space_for_am_pm = true ) {
			$result = self::get_date_format();

			$result .= $line_break ? '<\b\r>' : ' ';

			$time_format    = self::get_time_format();
			$has_am_pm      = false;
			$am_pm_fraction = false;
			$am_pm_pattern  = '/(?i)(\s+A)/';
			if ( preg_match( $am_pm_pattern, $time_format, $am_pm_matches ) ) {
				$has_am_pm      = true;
				$am_pm_fraction = $am_pm_matches[0];
				$time_format    = preg_replace( $am_pm_pattern, '', $time_format );
			}

			// Check if the time format does not have seconds.
			if ( false === stripos( $time_format, 's' ) ) {
				$time_format .= ':s'; // Add seconds to time format.
			}

			if ( self::get_show_milliseconds() ) {
				$time_format .= '.$$$'; // Add milliseconds to time format.
			}

			if ( $has_am_pm ) {
				$time_format .= preg_replace( '/\s/', $use_nb_space_for_am_pm ? '&\n\b\s\p;' : ' ', $am_pm_fraction );
			}

			$result .= $time_format;

			return $result;
		}
	}
}
