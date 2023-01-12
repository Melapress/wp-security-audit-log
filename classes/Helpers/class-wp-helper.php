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
	 * @since 4.4.2.1
	 */
	class WP_Helper {

		/**
		 * Hold the user roles as array - Human readable is used for key of the array, and the internal role name is the value.
		 *
		 * @var array
		 *
		 * @since 4.4.2.1
		 */
		private static $user_roles = array();

		/**
		 * Hold the user roles as array - Internal role name is used for key of the array, and the human readable format is the value.
		 *
		 * @var array
		 *
		 * @since 4.4.2.1
		 */
		private static $user_roles_wp = array();

		/**
		 * Keeps the value of the multisite install of the WP
		 *
		 * @var bool
		 *
		 * @since 4.4.2.1
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
		 * @since 4.4.2.1
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
		 * @since 4.4.2.1
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
		 * @since 4.4.2.1
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
		 * @since 4.4.2.1
		 */
		public static function is_multisite() {
			if ( null === self::$is_multisite ) {
				self::$is_multisite = function_exists( 'is_multisite' ) && is_multisite();
			}
			return self::$is_multisite;
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
		 * @since 4.4.2.1
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
		 * @since 4.4.2.1
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
		 * @since 4.4.2.1
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
		 * @since 4.4.2.1
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
		 * Original WP function expects to provide name of the cron as well as the cron parameters.
		 * Unfortunately this is not possible as these parameters are dynamically generated, that function searches for the cron name only.
		 *
		 * @param string $name - Name of the cron to search for.
		 *
		 * @return boolean
		 *
		 * @since 4.4.3
		 */
		public static function check_for_cron_job( string $name = '' ): bool {
			if ( '' !== trim( $name ) ) {
				$crons = _get_cron_array();

				foreach ( $crons as $cron ) {
					if ( isset( $cron[ $name ] ) ) {
						return true;
					}
				}
			}

			return false;
		}

		/**
		 * Check whether we are on an admin and plugin page.
		 *
		 * @since 4.4.3
		 *
		 * @param array|string $slug ID(s) of a plugin page. Possible values: 'general', 'logs', 'about' or array of them.
		 *
		 * @return bool
		 */
		public static function is_admin_page( $slug = array() ) { // phpcs:ignore Generic.Metrics.NestingLevel.MaxExceeded

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$cur_page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
			$check    = WSAL_PREFIX_PAGE;

			return \is_admin() && ( false !== strpos( $cur_page, $check ) );
		}

		/**
		 * Remove all non-WP Mail SMTP plugin notices from our plugin pages.
		 *
		 * @since 4.4.3
		 */
		public static function hide_unrelated_notices() {

			// Bail if we're not on our screen or page.
			if ( ! self::is_admin_page() ) {
				return;
			}

			self::remove_unrelated_actions( 'user_admin_notices' );
			self::remove_unrelated_actions( 'admin_notices' );
			self::remove_unrelated_actions( 'all_admin_notices' );
			self::remove_unrelated_actions( 'network_admin_notices' );
		}

		/**
		 * Remove all non-WP Mail SMTP notices from the our plugin pages based on the provided action hook.
		 *
		 * @since 4.4.3
		 *
		 * @param string $action The name of the action.
		 */
		private static function remove_unrelated_actions( $action ) {

			global $wp_filter;

			if ( empty( $wp_filter[ $action ]->callbacks ) || ! is_array( $wp_filter[ $action ]->callbacks ) ) {
				return;
			}

			foreach ( $wp_filter[ $action ]->callbacks as $priority => $hooks ) {
				foreach ( $hooks as $name => $arr ) {
					if (
					( // Cover object method callback case.
						is_array( $arr['function'] ) &&
						isset( $arr['function'][0] ) &&
						is_object( $arr['function'][0] ) &&
						strpos( strtolower( get_class( $arr['function'][0] ) ), WSAL_PREFIX ) !== false
					) ||
					( // Cover class static method callback case.
						! empty( $name ) &&
						strpos( strtolower( $name ), WSAL_PREFIX ) !== false
					)
					) {
						continue;
					}

					unset( $wp_filter[ $action ]->callbacks[ $priority ][ $name ] );
				}
			}
		}

		/**
		 * Sets the internal variable with all the existing WP roles
		 * If this is multisite - the super admin role is also added. In the WP you can have user without any other role but super admin.
		 *
		 * @return void
		 *
		 * @since 4.4.2.1
		 */
		private static function set_roles() {
			if ( empty( self::$user_roles ) ) {
				global $wp_roles;

				if ( null === $wp_roles ) {
					wp_roles();
				}

				self::$user_roles = array_flip( $wp_roles->get_names() );

				if ( \is_multisite() ) {
					self::$user_roles['Super Admin'] = 'superadmin';
				}
			}
		}

		/**
		 * Adds settings name prefix if it needs to be added.
		 *
		 * @param string $name - The name of the setting.
		 *
		 * @return string
		 *
		 * @since 4.4.2.1
		 */
		private static function prefix_name( string $name ): string {

			if ( false === strpos( $name, WSAL_PREFIX ) ) {

				$name = WSAL_PREFIX . $name;
			}

			return $name;
		}
	}
}
