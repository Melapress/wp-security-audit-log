<?php
/**
 * Responsible for the WP core functionalities
 *
 * @package    wsal
 * @subpackage helpers
 * @since      4.4.2
 * @copyright  2022 WP White Security
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WSAL\Helpers;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * WP helper class
 */
if ( ! class_exists( '\WSAL\Helpers\WP_Helper' ) ) {

	/**
	 * All the WP functionality must go trough this class
	 *
	 * @since      4.4.2.1
	 */
	class WP_Helper {

		const SETTINGS_PREFIX = 'wsal_';

		/**
		 * Hold the user roles as array - Human readable is used for key of the array, and the internal role name is the value.
		 *
		 * @var array
		 *
		 * @since      4.4.2.1
		 */
		private static $user_roles = array();

		/**
		 * Hold the user roles as array - Internal role name is used for key of the array, and the human readable format is the value.
		 *
		 * @var array
		 *
		 * @since      4.4.2.1
		 */
		private static $user_roles_wp = array();

		/**
		 * Keeps the value of the multisite install of the WP
		 *
		 * @var bool
		 *
		 * @since      4.4.2.1
		 */
		private static $is_multisite = null;

		/**
		 * Holds array with all the sites in multisite WP installation
		 *
		 * @var array
		 */
		private static $sites = array();

		/**
		 * Checks if specific role exists
		 *
		 * @param string $role - The name of the role to check.
		 *
		 * @return boolean
		 *
		 * @since      4.4.2.1
		 */
		public static function is_role_exists( string $role ): bool {
			self::set_roles();

			if ( in_array( $role, self::$user_roles, true ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Returns the currently available WP roles - the Human readable format is the key
		 *
		 * @return array
		 *
		 * @since      4.4.2.1
		 */
		public static function get_roles() {
			self::set_roles();

			return self::$user_roles;
		}

		/**
		 * Returns the currently available WP roles
		 *
		 * @return array
		 *
		 * @since      4.4.2.1
		 */
		public static function get_roles_wp() {
			if ( empty( self::$user_roles_wp ) ) {
				self::set_roles();
				self::$user_roles_wp = array_flip( self::$user_roles );
			}

			return self::$user_roles_wp;
		}

		/**
		 * Check is this is a multisite setup.
		 *
		 * @return boolean
		 *
		 * @since      4.4.2.1
		 */
		public static function is_multisite() {
			if ( null === self::$is_multisite ) {
				self::$is_multisite = function_exists( 'is_multisite' ) && is_multisite();
			}
			return self::$is_multisite;
		}

		/**
		 * Returns an option by given name
		 *
		 * @param string $setting_name - The name of the option.
		 * @param mixed  $default_value - The default value if there is no one stored.
		 *
		 * @return mixed
		 *
		 * @since      4.4.2.1
		 */
		public static function get_option( $setting_name, $default_value = false ) {
			$prefixed_name = self::prefix_name( $setting_name );
			return \get_option( $prefixed_name, $default_value );
		}

		/**
		 * Just an alias for update_option
		 *
		 * @param string  $setting_name - The name of the option.
		 * @param mixed   $new_value - The value to be stored.
		 * @param boolean $autoload - Should that option be autoloaded or not? No effect on network wide options.
		 *
		 * @return mixed
		 *
		 * @since      4.4.2.1
		 */
		public static function set_option( $setting_name, $new_value, $autoload = false ) {
			return self::update_option( $setting_name, $new_value, $autoload );
		}

		/**
		 * Updates an option by a given name with a given value
		 *
		 * @param string  $setting_name - The name of the setting to update.
		 * @param mixed   $new_value - The value to be stored.
		 * @param boolean $autoload - Should that option be autoloaded or not? No effect on network wide options.
		 *
		 * @return mixed
		 *
		 * @since      4.4.2.1
		 */
		public static function update_option( $setting_name, $new_value, $autoload = false ) {
			$prefixed_name = self::prefix_name( $setting_name );
			return \update_option( $prefixed_name, $new_value, $autoload );
		}

		/**
		 * Deletes an option by a given name form the global settings
		 *
		 * @param string $setting_name - The name of the option to delete.
		 *
		 * @return mixed
		 *
		 * @since      4.4.2.1
		 */
		public static function delete_option( $setting_name ) {
			$prefixed_name = self::prefix_name( $setting_name );
			return \delete_option( $prefixed_name );
		}

		/**
		 * Deletes a plugin option from the WP options table.
		 *
		 * Handled option name with and without the prefix for backwards compatibility.
		 *
		 * @since  4.0.2
		 * @param  string $option_name Name of the option to delete.
		 * @return bool
		 */
		public static function delete_global_option( $option_name = '' ) {
			$prefixed_name = self::prefix_name( $option_name );

			if ( self::is_multisite() ) {
				\switch_to_blog( \get_main_network_id() );
			}

			$result = \delete_option( $prefixed_name );

			if ( is_multisite() ) {
				\restore_current_blog();
			}

			return $result;
		}

		/**
		 * Just an alias for update_global_option
		 *
		 * @param string  $setting_name - The name of the option.
		 * @param mixed   $new_value - The value to be stored.
		 * @param boolean $autoload - Should that option be autoloaded or not? No effect on network wide options.
		 *
		 * @return mixed
		 *
		 * @since      4.4.2.1
		 */
		public static function set_global_option( $setting_name, $new_value, $autoload = false ) {
			return self::update_global_option( $setting_name, $new_value, $autoload );
		}

		/**
		 * Internal function used to set the value of an option. Any necessary prefixes are already contained in the option
		 * name.
		 *
		 * @param string $option_name Option name we want to save a value for including necessary plugin prefix.
		 * @param mixed  $value       A value to store under the option name.
		 * @param bool   $autoload    Whether to autoload this option.
		 *
		 * @return bool Whether the option was updated.
		 * @since  4.1.3
		 */
		public static function update_global_option( $option_name = '', $value = null, $autoload = false ) {
			// bail early if no option name or value was passed.
			if ( empty( $option_name ) || null === $value ) {
				return;
			}

			$prefixed_name = self::prefix_name( $option_name );

			if ( is_multisite() ) {
				\switch_to_blog( \get_main_network_id() );
			}

			$result = \update_option( $prefixed_name, $value, $autoload );

			if ( \is_multisite() ) {
				\restore_current_blog();
			}

			return $result;
		}

		/**
		 * Internal function used to get the value of an option. Any necessary prefixes are already contained in the option
		 * name.
		 *
		 * @param string $option_name Option name we want to get a value for including necessary plugin prefix.
		 * @param mixed  $default     a default value to use when one doesn't exist.
		 *
		 * @return mixed
		 * @since  4.1.3
		 */
		public static function get_global_option( $option_name = '', $default = null ) {
			// bail early if no option name was requested.
			if ( empty( $option_name ) || ! is_string( $option_name ) ) {
				return;
			}

			if ( is_multisite() ) {
				switch_to_blog( get_main_network_id() );
			}

			$prefixed_name = self::prefix_name( $option_name );

			$result = \get_option( $prefixed_name, $default );

			if ( is_multisite() ) {
				restore_current_blog();
			}

			return maybe_unserialize( $result );
		}


		/**
		 * Removes event from the cron by given name
		 *
		 * @param string $event_name -The name of the event.
		 *
		 * @return void
		 *
		 * @since      4.4.2.1
		 */
		public static function un_schedule_event( string $event_name ) {
			$schedule_time = wp_next_scheduled( $event_name );
			if ( $schedule_time ) {
				wp_unschedule_event( $schedule_time, $event_name, array() );
			}
		}

		/**
		 * Collects all the sites from multisite WP installation
		 *
		 * @return array
		 */
		public static function get_multi_sites(): array {
			if ( self::is_multisite() ) {
				if ( empty( self::$sites ) ) {

					self::$sites = \get_sites();
				}

				return self::$sites;
			}

			return array();
		}

		/**
		 * Deletes a transient. If this is a multisite, the network transient is deleted.
		 *
		 * @param string $transient Transient name. Expected to not be SQL-escaped.
		 *
		 * @return bool True if the transient was deleted, false otherwise.
		 *
		 * @since      4.4.2.1
		 */
		public static function delete_transient( $transient ) {
			return self::is_multisite() ? delete_site_transient( $transient ) : delete_transient( $transient );
		}

		/**
		 * Check wsal options from wp_options table and determines if plugin is installed.
		 */
		public static function is_plugin_installed(): bool {
			global $wpdb;
			$plugin_options = $wpdb->get_results( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE '%wsal_%'" ); // phpcs:ignore

			if ( ! empty( $plugin_options ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Gets all active plugins in current WordPress installation.
		 *
		 * @return array
		 *
		 * @since      4.4.2.1
		 */
		public static function get_active_plugins(): array {
			$active_plugins = array();
			if ( function_exists( 'is_multisite' ) && is_multisite() ) {
				$active_plugins = array_keys( get_site_option( 'active_sitewide_plugins', array() ) );
			} else {
				$active_plugins = get_option( 'active_plugins' );
			}

			return $active_plugins;
		}

		/**
		 * Sets the internal variable with all the existing WP roles
		 *
		 * @return void
		 *
		 * @since      4.4.2.1
		 */
		private static function set_roles() {
			if ( empty( self::$user_roles ) ) {
				global $wp_roles;

				if ( null === $wp_roles ) {
					wp_roles();
				}

				self::$user_roles = array_flip( $wp_roles->get_names() );
			}
		}

		/**
		 * Adds settings name prefix if it needs to be added.
		 *
		 * @param string $name - The name of the setting.
		 *
		 * @return string
		 *
		 * @since      4.4.2.1
		 */
		private static function prefix_name( string $name ): string {

			if ( false === strpos( $name, self::SETTINGS_PREFIX ) ) {

				$name = self::SETTINGS_PREFIX . $name;
            }

			return $name;
		}
	}
}
