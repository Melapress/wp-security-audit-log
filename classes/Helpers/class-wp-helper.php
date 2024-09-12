<?php
/**
 * Responsible for the WP core functionalities.
 *
 * @package    wsal
 * @subpackage helpers
 *
 * @since      4.4.2
 *
 * @copyright  2024 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 *
 * @see       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WSAL\Helpers;

use WSAL\MainWP\MainWP_Addon;
use WSAL\MainWP\MainWP_Helper;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/*
 * WP helper class
 */
if ( ! class_exists( '\WSAL\Helpers\WP_Helper' ) ) {
	/**
	 * All the WP functionality must go trough this class.
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
		 * Keeps the value of the multisite install of the WP.
		 *
		 * @var bool
		 *
		 * @since 4.4.2.1
		 */
		private static $is_multisite = null;

		/**
		 * Holds array with all the sites in multisite WP installation.
		 *
		 * @var array
		 */
		private static $sites = array();

		/**
		 * Holds array with all the site urls in multisite WP installation. The urls are the keys and values are the IDs.
		 *
		 * @var array
		 */
		private static $site_urls = array();

		/**
		 * Internal cache array for site urls extracted as info
		 *
		 * @var array
		 */
		private static $blogs_info = array();

		/**
		 * Checks if specific role exists.
		 *
		 * @param string $role - The name of the role to check.
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
		 * Returns the currently available WP roles - the Human readable format is the key.
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
		 * Returns the currently available WP roles.
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
		 * @return bool
		 *
		 * @since 4.4.2.1
		 */
		public static function is_multisite() {
			if ( null === self::$is_multisite ) {
				self::$is_multisite = function_exists( 'is_multisite' ) && is_multisite();
			}

			return \apply_filters( 'wsal_override_is_multisite', self::$is_multisite );
		}

		/**
		 * Collects blogs URLs - used for mainWP site check.
		 *
		 * @return array
		 *
		 * @since 5.0.0
		 */
		public static function get_site_urls() {
			$sites = self::get_multi_sites();

			foreach ( $sites as $site_object ) {
				$url                     = \get_blogaddress_by_id( $site_object->blog_id );
				self::$site_urls[ $url ] = $site_object->blog_id;
			}

			return self::$site_urls;
		}

		/**
		 * Deletes a plugin option from the WP options table.
		 *
		 * Handled option name with and without the prefix for backwards compatibility.
		 *
		 * @since  4.0.2
		 *
		 * @param string $option_name Name of the option to delete.
		 *
		 * @return bool
		 */
		public static function delete_global_option( $option_name = '' ) {
			$prefixed_name = self::prefix_name( $option_name );

			if ( self::is_multisite() ) {
				\switch_to_blog( \get_main_network_id() );
			}

			$result = \delete_option( $prefixed_name );

			if ( self::is_multisite() ) {
				\restore_current_blog();
			}

			return $result;
		}

		/**
		 * Just an alias for update_global_option.
		 *
		 * @param string $setting_name - The name of the option.
		 * @param mixed  $new_value    - The value to be stored.
		 * @param bool   $autoload     - Should that option be autoloaded or not? No effect on network wide options.
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
		 *
		 * @since  4.1.3
		 */
		public static function update_global_option( $option_name = '', $value = null, $autoload = false ) {
			// bail early if no option name or value was passed.
			if ( empty( $option_name ) || null === $value ) {
				return;
			}

			$prefixed_name = self::prefix_name( $option_name );

			if ( self::is_multisite() ) {
				\switch_to_blog( \get_main_network_id() );
			}

			$result = \update_option( $prefixed_name, $value, $autoload );

			if ( self::is_multisite() ) {
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
		 *
		 * @since  4.1.3
		 */
		public static function get_global_option( $option_name = '', $default = null ) {
			// bail early if no option name was requested.
			if ( empty( $option_name ) || ! is_string( $option_name ) ) {
				return;
			}

			if ( self::is_multisite() ) {
				switch_to_blog( get_main_network_id() );
			}

			$prefixed_name = self::prefix_name( $option_name );

			$result = \get_option( $prefixed_name, $default );

			if ( self::is_multisite() ) {
				restore_current_blog();
			}

			return maybe_unserialize( $result );
		}

		/**
		 * Collects all the sites from multisite WP installation.
		 *
		 * @since 4.6.0
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
		 *
		 * @since 4.6.0
		 */
		public static function is_plugin_installed(): bool {
			global $wpdb;
			$plugin_options = $wpdb->get_results("SELECT option_name FROM $wpdb->options WHERE option_name LIKE 'wsal_%'"); // phpcs:ignore

			if ( ! empty( $plugin_options ) && 2 < count( $plugin_options ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Gets all active plugins in current WordPress installation.
		 *
		 * @since 4.4.2.1
		 */
		public static function get_active_plugins(): array {
			$active_plugins = array();
			if ( self::is_multisite() ) {
				$active_plugins = array_keys( get_site_option( 'active_sitewide_plugins', array() ) );
			} else {
				$active_plugins = get_option( 'active_plugins' );
			}

			return $active_plugins;
		}

		/**
		 * Collects all active plugins for the current WP installation.
		 *
		 * @return array
		 *
		 * @since 4.6.0
		 */
		public static function get_all_active_plugins(): array {
			$plugins = array();
			if ( self::is_multisite() ) {
				$plugins = wp_get_active_network_plugins();
			}

			$plugins = \array_merge( $plugins, wp_get_active_and_valid_plugins() );

			return $plugins;
		}

		/**
		 * Original WP function expects to provide name of the cron as well as the cron parameters.
		 * Unfortunately this is not possible as these parameters are dynamically generated, that function searches for the cron name only.
		 *
		 * @param string $name - Name of the cron to search for.
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
		 * Get editor link.
		 *
		 * @param stdClass|int $post - The post.
		 *
		 * @return array $editor_link - Name and value link
		 *
		 * @since 4.5.0
		 */
		public static function get_editor_link( $post ) {
			$post_id = is_int( $post ) ? intval( $post ) : $post->ID;

			return array(
				'name'  => 'EditorLinkPost',
				'value' => get_edit_post_link( $post_id ),
			);
		}

		/**
		 * Method: Get view site id.
		 *
		 * @since 4.5.0
		 *
		 * @return int
		 */
		public static function get_view_site_id() {
			switch ( true ) {
				// Non-multisite.
				case ! self::is_multisite():
					return false;
					// Multisite + main site view.
				case self::is_main_blog() && ! self::is_specific_view():
					return -1;
					// Multisite + switched site view.
				case self::is_main_blog() && self::is_specific_view():
					return self::get_specific_view();
					// Multisite + local site view.
				default:
					return \get_current_blog_id();
			}
		}

		/**
		 * Method: Get a specific view.
		 *
		 * @since 4.5.0
		 *
		 * @return int
		 */
		public static function get_specific_view() {
			return isset( $_REQUEST['wsal-cbid'] ) ? (int) sanitize_text_field( wp_unslash( $_REQUEST['wsal-cbid'] ) ) : 0;
		}

		/**
		 * Method: Check if the blog is main blog.
		 *
		 * @since 4.5.0
		 */
		public static function is_main_blog(): bool {
			return 1 === get_current_blog_id();
		}

		/**
		 * Method: Check if it is a specific view.
		 *
		 * @since 4.5.0
		 *
		 * @return bool
		 */
		public static function is_specific_view() {
			return isset( $_REQUEST['wsal-cbid'] ) && 0 !== (int) $_REQUEST['wsal-cbid'];
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
							false !== strpos( strtolower( get_class( $arr['function'][0] ) ), WSAL_PREFIX )
						) ||
						( // Cover class static method callback case.
							! empty( $name ) &&
							false !== strpos( strtolower( $name ), WSAL_PREFIX )
						) ||
						( // Cover class static method callback case.
							! empty( $name ) &&
							false !== strpos( strtolower( $name ), 'wsal\\' )
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

				if ( self::is_multisite() ) {
					self::$user_roles['Super Admin'] = 'superadmin';
				}
			}
		}

		/**
		 * Adds settings name prefix if it needs to be added.
		 *
		 * @param string $name - The name of the setting.
		 *
		 * @since 4.4.2.1
		 */
		private static function prefix_name( string $name ): string {
			if ( false === strpos( $name, WSAL_PREFIX ) ) {
				$name = WSAL_PREFIX . $name;
			}

			return $name;
		}

		/**
		 * Retrieves the value of a transient. If this is a multisite, the network transient is retrieved.
		 *
		 * If the transient does not exist, does not have a value, or has expired,
		 * then the return value will be false.
		 *
		 * @param string $transient Transient name. Expected to not be SQL-escaped.
		 *
		 * @return mixed Value of transient.
		 *
		 * @since 4.5.0
		 */
		public static function get_transient( $transient ) {
			return self::is_multisite() ? get_site_transient( $transient ) : get_transient( $transient );
		}

		/**
		 * Sets/updates the value of a transient. If this is a multisite, the network transient is set/updated.
		 *
		 * You do not need to serialize values. If the value needs to be serialized,
		 * then it will be serialized before it is set.
		 *
		 * @param string $transient  Transient name. Expected to not be SQL-escaped.
		 *                           Must be 172 characters or fewer in length.
		 * @param mixed  $value      Transient value. Must be serializable if non-scalar.
		 *                           Expected to not be SQL-escaped.
		 * @param int    $expiration Optional. Time until expiration in seconds. Default 0 (no expiration).
		 *
		 * @return bool True if the value was set, false otherwise.
		 *
		 * @since 4.5.0
		 */
		public static function set_transient( $transient, $value, $expiration = 0 ) {
			return self::is_multisite() ? set_site_transient( $transient, $value, $expiration ) : set_transient( $transient, $value, $expiration );
		}

		/**
		 * Checks if we are currently on the login screen.
		 *
		 * @since 4.5.0
		 */
		public static function is_login_screen(): bool {

			$login = parse_url( site_url( 'wp-login.php' ), PHP_URL_PATH ) === parse_url( \wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated

			return \apply_filters( 'wsal_login_screen_url', $login );
		}

		/**
		 * Checks if we are currently on the register page.
		 *
		 * @since 4.5.0
		 */
		public static function is_register_page(): bool {
			if ( self::is_login_screen() && ! empty( $_REQUEST['action'] ) && 'register' === $_REQUEST['action'] ) {
				return true;
			}

			return false;
		}

		/**
		 * Retrieves blog info for given site based on current multisite situation. Optimizes for performance using local
		 * cache.
		 *
		 * @param int $site_id Site ID.
		 *
		 * @return array
		 *
		 * @since 4.5.0
		 */
		public static function get_blog_info( $site_id ) {
			// Blog details.
			if ( isset( self::$blogs_info[ $site_id ] ) ) {
				return self::$blogs_info[ $site_id ];
			}
			if ( self::is_multisite() ) {
				$blog_info = \get_blog_details( $site_id, true );
				$blog_name = \esc_html__( 'Unknown Site', 'wp-security-audit-log' );
				$blog_url  = '';

				if ( $blog_info ) {
					$blog_name = \esc_html( $blog_info->blogname );
					$blog_url  = \esc_attr( $blog_info->siteurl );
				}
			} else {
				$blog_name = \get_bloginfo( 'name' );
				$blog_url  = '';

				if ( empty( $blog_name ) ) {
					$blog_name = __( 'Unknown Site', 'wp-security-audit-log' );
				} else {
					$blog_name = \esc_html( $blog_name );
					$blog_url  = \esc_attr( \get_bloginfo( 'url' ) );
				}
			}

			if ( MainWP_Addon::check_mainwp_plugin_active() ) {
				$sites = MainWP_Helper::get_all_sites_array();
				foreach ( $sites as $site ) {
					if ( $site_id === $site->blog_id ) {
						$blog_name = esc_html( $site->blogname );
						$blog_url  = esc_attr( $site->siteurl );
						break;
					}
				}
			}

			self::$blogs_info[ $site_id ] = array(
				'name' => $blog_name,
				'url'  => $blog_url,
			);

			return self::$blogs_info[ $site_id ];
		}

		/**
		 * Determines whether a plugin is active.
		 *
		 * @uses is_plugin_active() Uses this WP core function after making sure that this function is available.
		 *
		 * @param string $plugin Path to the main plugin file from plugins directory.
		 *
		 * @return bool True, if in the active plugins list. False, not in the list.
		 *
		 * @since 4.6.0
		 */
		public static function is_plugin_active( $plugin ) {
			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			return is_plugin_active( $plugin );
		}

		/**
		 * Returns the full path to the admin area. Multisite admin is taken of consideration.
		 *
		 * @param string $additional_path - If there is additional path to add to the admin area.
		 *
		 * @return string
		 *
		 * @since 4.5.0
		 */
		public static function get_admin_url( string $additional_path = '' ) {
			if ( self::is_multisite() ) {
				return network_admin_url( $additional_path );
			}

			return get_admin_url( null, $additional_path );
		}

		/**
		 * Query sites from WPDB.
		 *
		 * @since 5.0.0
		 *
		 * @param int|null $limit — Maximum number of sites to return (null = no limit).
		 *
		 * @return object — Object with keys: blog_id, blogname, domain
		 */
		public static function get_sites( $limit = null ) {
			if ( self::$is_multisite ) {
				global $wpdb;
				// Build query.
				$sql = 'SELECT blog_id, domain FROM ' . $wpdb->blogs;
				if ( ! is_null( $limit ) ) {
					$sql .= ' LIMIT ' . $limit;
				}

				// Execute query.
				$res = $wpdb->get_results($sql); // phpcs:ignore

				// Modify result.
				foreach ( $res as $row ) {
					$row->blogname = \get_blog_option( $row->blog_id, 'blogname' );
				}
			} else {
				$res           = new \stdClass();
				$res->blog_id  = \get_current_blog_id();
				$res->blogname = esc_html( \get_bloginfo( 'name' ) );
				$res           = array( $res );
			}

			if ( MainWP_Addon::check_mainwp_plugin_active() ) {
				$res = MainWP_Helper::get_all_sites_array();
			}

			// Return result.
			return $res;
		}

		/**
		 * The number of sites on the network.
		 *
		 * @since 5.0.0
		 *
		 * @return int
		 */
		public static function get_site_count() {
			global $wpdb;
			$sql = 'SELECT COUNT(*) FROM ' . $wpdb->blogs;

			return (int) $wpdb->get_var($sql); // phpcs:ignore
		}

		/**
		 * Returns associate array with user roles names.
		 *
		 * @return array
		 *
		 * @since 5.0.0
		 */
		public static function get_translated_roles(): array {
			global $wp_roles;

			if ( null === $wp_roles ) {
				wp_roles();
			}

			$roles = wp_roles()->get_names();

			foreach ( $roles as $inner => $role ) {
				$role_names[ $inner ] = translate_user_role( $role );
			}
			if ( self::is_multisite() ) {
				$role_names['superadmin'] = translate_user_role( 'Super Admin' );
			}

			return $role_names;
		}
	}
}
