<?php
/**
 * Class: Helper responsible for extracting settings values.
 *
 * Helper class used for settings.
 *
 * @package wsal
 *
 * @since 5.0.0
 */

declare(strict_types=1);

namespace WSAL\Helpers;

use WSAL\Helpers\Settings_Helper;
use WSAL\Controllers\Alert_Manager;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Helpers\Plugin_Settings_Helper' ) ) {
	/**
	 * Responsible for setting operations.
	 *
	 * @since 5.0.0
	 */
	class Plugin_Settings_Helper {

		/**
		 * Alerts enabled in Geek mode.
		 *
		 * @var int[]
		 *
		 * @since 5.0.0
		 */
		private static $geek_alerts = array( 1004, 1005, 1006, 1007, 2023, 2024, 2053, 2054, 2055, 2062, 2100, 2111, 2112, 2124, 2125, 2131, 2132, 2094, 2095, 2043, 2071, 2082, 2083, 2085, 2089, 4014, 4015, 4016, 5019, 5025, 6001, 6002, 6008, 6010, 6011, 6012, 6013, 6014, 6015, 6016, 6017, 6018, 6024, 6025 );

		/**
		 * Pruning Date.
		 *
		 * @var string
		 *
		 * @since 5.0.0
		 */
		private static $pruning = '';


		/**
		 * Allowed Plugin Viewers.
		 *
		 * @var array
		 *
		 * @since 5.0.0
		 */
		private static $viewers = array();

		/**
		 * Return current pruning unit.
		 *
		 * @return string
		 *
		 * @since 5.0.0
		 */
		public static function get_pruning_unit() {
			return Settings_Helper::get_option_value( 'pruning-unit', 'months' );
		}

		/**
		 * Set pruning alerts limit.
		 *
		 * @param int $newvalue - The new maximum number of alerts.
		 *
		 * @since 5.0.0
		 */
		public static function set_pruning_limit( $newvalue ) {
			Settings_Helper::set_option_value( 'pruning-limit', max( (int) $newvalue, 1 ) );
		}

		/**
		 * Sets the plugin setting that enabled data pruning limit.
		 *
		 * @param bool $enabled If true, the limit is enabled.
		 *
		 * @since 5.0.0
		 */
		public static function set_pruning_limit_enabled( $enabled ) {
			Settings_Helper::set_boolean_option_value( 'pruning-limit-e', $enabled );
		}

		/**
		 * Method: Set Login Page Notification.
		 *
		 * @param bool $enable - Enable/Disable.
		 *
		 * @since 5.0.0
		 */
		public static function set_login_page_notification( $enable ) {
			// Only trigger an event if an actual changes is made.
			$old_setting = Settings_Helper::get_boolean_option_value( 'login_page_notification', false );
			$enable      = Settings_Helper::string_to_bool( $enable );
			if ( $old_setting !== $enable ) {
				$event_id   = 6046;
				$alert_data = array(
					'EventType' => ( $enable ) ? 'enabled' : 'disabled',
				);
				Alert_Manager::trigger_event( $event_id, $alert_data );
			}
			Settings_Helper::set_boolean_option_value( 'login_page_notification', $enable );
		}

		/**
		 * Method: Set Login Page Notification Text.
		 *
		 * @param string $text - Login Page Notification Text.
		 *
		 * @since 5.0.0
		 */
		public static function set_login_page_notification_text( $text ) {
			$text        = wp_kses( $text, self::get_allowed_html_tags() );
			$old_setting = Settings_Helper::get_option_value( 'login_page_notification_text' );
			if ( ! empty( $old_setting ) && ! empty( $text ) && ! is_null( $old_setting ) && $old_setting !== $text ) {
				Alert_Manager::trigger_event( 6047 );
			}
			Settings_Helper::set_option_value( 'login_page_notification_text', $text );
		}

		/**
		 * Method: Set allowed  HTML tags.
		 *
		 * @since 5.0.0
		 */
		public static function get_allowed_html_tags() {
			// Set allowed HTML tags.
			return array(
				'a'      => array(
					'href'   => array(),
					'title'  => array(),
					'target' => array(),
				),
				'br'     => array(),
				'code'   => array(),
				'em'     => array(),
				'strong' => array(),
				'p'      => array(
					'class' => array(),
				),
			);
		}

		/**
		 * Enable Basic Mode.
		 *
		 * @since 5.0.0
		 */
		public static function set_basic_mode() {
			// Disable alerts of geek mode and alerts to be always disabled.
			Settings_Helper::set_disabled_alerts( array_merge( self::$geek_alerts, Settings_Helper::get_default_always_disabled_alerts() ) );
		}

		/**
		 * Returns the geek alers codes.
		 *
		 * @return array
		 *
		 * @since 5.0.0
		 */
		public static function get_geek_alerts(): array {
			return self::$geek_alerts;
		}

		/**
		 * Enable Geek Mode.
		 *
		 * @since 5.0.0
		 */
		public static function set_geek_mode() {
			Settings_Helper::set_disabled_alerts( Settings_Helper::get_default_always_disabled_alerts() ); // Disable alerts to be always disabled.
		}

		/**
		 * The default pruning date.
		 *
		 * @return string
		 *
		 * @since 5.0.0
		 */
		public static function get_default_pruning_date() {
			return '3 months';
		}

		/**
		 * Enables or disables plugin's incognito mode.
		 *
		 * @param bool $enabled If true, the incognito mode gets enabled.
		 *
		 * @since 5.0.0
		 */
		public static function set_incognito( $enabled ) {
			$old_value = Settings_Helper::get_option_value( 'hide-plugin' );
			$old_value = ( 'yes' === $old_value );
			if ( $old_value !== $enabled ) {
				$alert_data = array(
					'EventType' => ( $enabled ) ? 'enabled' : 'disabled',
				);
				Alert_Manager::trigger_event( 6051, $alert_data );
			}

			Settings_Helper::set_boolean_option_value( 'hide-plugin', $enabled );
		}

		/**
		 * Set restrict plugin setting.
		 *
		 * @param string $setting – Setting.
		 *
		 * @since 5.0.0
		 */
		public static function set_restrict_plugin_setting( $setting ) {
			$old_value = Settings_Helper::get_option_value( 'restrict-plugin-settings', 'only_admins' );

			if ( ! is_null( $old_value ) && $old_value !== $setting ) {
				$alert_data = array(
					'new_setting'      => ucfirst( str_replace( '_', ' ', $setting ) ),
					'previous_setting' => ucfirst( str_replace( '_', ' ', $old_value ) ),
				);
				Alert_Manager::trigger_event( 6049, $alert_data );
			}

			Settings_Helper::set_option_value( 'restrict-plugin-settings', $setting, true );
		}

		/**
		 * Sets the plugin setting that allows data deletion on plugin uninstall.
		 *
		 * @param mixed $enabled If true, data deletion on plugin uninstall gets enabled.
		 *
		 * @since 5.0.0
		 */
		public static function set_delete_data( $enabled ) {
			Settings_Helper::set_boolean_option_value( 'delete-data', $enabled );
		}

		/**
		 * Get restrict plugin setting.
		 *
		 * @since 5.0.0
		 */
		public static function get_restrict_plugin_setting() {
			return Settings_Helper::get_option_value( 'restrict-plugin-settings', 'only_admins' );
		}

		/**
		 * Get restriction setting for viewing the log viewer in multisite context.
		 *
		 * @since 5.0.0
		 */
		public static function get_restrict_log_viewer() {
			return Settings_Helper::get_option_value( 'restrict-log-viewer', 'only_admins' );
		}

		/**
		 * Set restriction setting for viewing the log viewer in multisite context.
		 *
		 * @param string $setting – Setting.
		 *
		 * @since 5.0.0
		 */
		public static function set_restrict_log_viewer( $setting ) {
			Settings_Helper::set_option_value( 'restrict-log-viewer', $setting, true );
		}

		/**
		 * Sets the setting that decides if IP address should be determined based on proxy.
		 *
		 * @param bool $enabled True if IP address should be determined based on proxy.
		 */
		public static function set_main_ip_from_proxy( $enabled ) {
			$old_value = Settings_Helper::get_boolean_option_value( 'use-proxy-ip' );
			$enabled   = Settings_Helper::string_to_bool( $enabled );
			if ( $old_value !== $enabled ) {
				$alert_data = array(
					'EventType' => ( $enabled ) ? 'enabled' : 'disabled',
				);
				Alert_Manager::trigger_event( 6048, $alert_data );
			}
			Settings_Helper::set_boolean_option_value( 'use-proxy-ip', $enabled );
		}

		/**
		 * Checks if internal IP filtering is enabled.
		 *
		 * @return bool
		 *
		 * @since 5.0.0
		 */
		public static function is_internal_ips_filtered() {
			return Settings_Helper::get_boolean_option_value( 'filter-internal-ip', false );
		}

		/**
		 * Enables or disables the internal IP filtering.
		 *
		 * @param bool $enabled True if internal IP filtering should be enabled.
		 *
		 * @since 5.0.0
		 */
		public static function set_internal_ips_filtering( $enabled ) {
			Settings_Helper::set_boolean_option_value( 'filter-internal-ip', $enabled );
		}

		/**
		 * Updates the timezone handling setting.
		 *
		 * @param string $newvalue New setting value.
		 *
		 * @since 5.0.0
		 */
		public static function set_timezone( $newvalue ) {
			Settings_Helper::set_option_value( 'timezone', $newvalue );
		}

		/**
		 * Stores the option that dicates if milliseconds show in admin list view
		 * for event times. This is always a bool. When it's not a bool it's set
		 * to `true` to match default.
		 *
		 * @method set_show_milliseconds
		 *
		 * @since 5.1.0
		 *
		 * @param mixed $newvalue ideally always bool. If not bool then it's cast to true.
		 */
		public static function set_show_milliseconds( $newvalue ) {
			Settings_Helper::set_boolean_option_value( 'show_milliseconds', $newvalue );
		}

		/**
		 * Get type of username to display.
		 *
		 * @since 5.0.0
		 */
		public static function get_type_username() {
			return Settings_Helper::get_option_value( 'type_username', 'display_name' );
		}

		/**
		 * Set type of username to display.
		 *
		 * @param string $newvalue - New value variable.
		 *
		 * @since 5.0.0
		 */
		public static function set_type_username( $newvalue ) {
			Settings_Helper::set_option_value( 'type_username', $newvalue );
		}

		/**
		 * Sets the log limit for failed login attempts.
		 *
		 * @param int $value - Failed login limit.
		 *
		 * @since 5.0.0
		 */
		// public static function set_failed_login_limit( $value ) {
		// if ( ! empty( $value ) ) {
		// Settings_Helper::set_option_value( 'log-failed-login-limit', abs( (int) $value ) );
		// } else {
		// Settings_Helper::set_option_value( 'log-failed-login-limit', - 1 );
		// }
		// }

		/**
		 * Sets the log limit for failed login attempts for visitor.
		 *
		 * @param int $value - Failed login limit.
		 *
		 * @since 5.0.0
		 */
		public static function set_visitor_failed_login_limit( $value ) {
			if ( ! empty( $value ) ) {
				Settings_Helper::set_option_value( 'log-visitor-failed-login-limit', abs( (int) $value ) );
			} else {
				Settings_Helper::set_option_value( 'log-visitor-failed-login-limit', - 1 );
			}
		}

		/**
		 * Method: Get Token Type.
		 *
		 * @param string $token - Token type.
		 * @param string $type - Type of the input to check.
		 *
		 * @return string
		 *
		 * @since 5.0.0
		 */
		public static function get_token_type( $token, $type = false ) {
			// Get users.
			$users = array();
			foreach ( get_users( 'blog_id=0&fields[]=user_login' ) as $obj ) {
				$users[] = $obj->user_login;
			}

			// Check if the token matched users.
			if ( in_array( $token, $users, true ) ) {

				// That is shitty code, in order to keep backwards compatibility
				// that code is added.
				// Meaning - if that is AJAX call, and it comes from the roles and not users,
				// we have no business here - the token is valid and within users, but we are looking
				// for roles.
				if ( false !== $type && 'ExRole' === $type ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf

				} else {
					return 'user';
				}
			}

			if ( false !== $type && 'ExUser' === $type ) {
				// We are checking for a user, meaning that at this point we have not find one
				// bounce.

				return 'other';
			}

			// Get user roles.
			$roles = array_keys( get_editable_roles() );

			// Check if the token matched user roles.
			if ( in_array( $token, $roles, true ) ) {
				return 'role';
			}

			if ( false !== $type && 'ExRole' === $type ) {
				// We are checking for a role, meaning that at this point we have not find one
				// bounce.

				return 'other';
			}

			// Get custom post types.
			$post_types = get_post_types( array(), 'names', 'and' );
			// if we are running multisite and have networkwide cpt tracker get the
			// list from and merge to the post_types array.
			if ( WP_Helper::is_multisite() && class_exists( '\WSAL\Multisite\NetworkWide\CPTsTracker' ) ) {
				$network_cpts = \WSAL\Multisite\NetworkWide\CPTsTracker::get_network_data_list();
				foreach ( $network_cpts as $cpt ) {
					$post_types[ $cpt ] = $cpt;
				}
			}

			// Check if the token matched post types.
			if ( in_array( $token, $post_types, true ) ) {
				return 'cpts';
			}

			// Check if the token matched post stati.
			if ( in_array( $token, get_post_stati(), true ) ) {
				return 'status';
			}

			// Check if the token matches a URL.
			if ( ( false !== strpos( $token, home_url() ) ) && filter_var( $token, FILTER_VALIDATE_URL ) ) {
				return 'urls';
			}

			// Check for IP range.
			if ( false !== strpos( $token, '-' ) ) {
				$ip_range = \WSAL\Helpers\Settings_Helper::get_ipv4_by_range( $token );

				if ( $ip_range && filter_var( $ip_range->lower, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) && filter_var( $ip_range->upper, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) { // Validate IPv4.
					return 'ip';
				}
			}

			// Check if the token matches an IP address.
			if (
			filter_var( $token, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) // Validate IPv4.
			|| filter_var( $token, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) // Validate IPv6.
			) {
				return 'ip';
			}

			return 'other';
		}

		/**
		 * Query WSAL Options from DB.
		 *
		 * @return array - WSAL Options array.
		 *
		 * @since 5.0.0
		 */
		public static function get_plugin_settings() {
			global $wpdb;

			return $wpdb->get_results("SELECT * FROM $wpdb->options WHERE option_name LIKE 'wsal_%'"); // phpcs:ignore
		}

		/**
		 * Stores the ID of user who restricted the plugin settings access to "only me".
		 *
		 * @param int $user_id User ID.
		 *
		 * @since 5.0.0
		 */
		public static function set_only_me_user_id( $user_id ) {
			Settings_Helper::set_option_value( 'only-me-user-id', $user_id, true );
		}

		/**
		 * Deactivate MainWP Child Stealth Mode.
		 *
		 * @since 5.0.0
		 */
		public static function deactivate_mainwp_child_stealth_mode() {
			self::set_incognito( false ); // Disable incognito mode to hide WSAL on plugins page.
			self::set_restrict_plugin_setting( 'only_admins' );
			self::set_restrict_log_viewer( 'only_admins' );
			Settings_Helper::set_boolean_option_value( 'mwp-child-stealth-mode', false ); // Disable stealth mode option.
		}

		/**
		 * Retrieves the settings enforced by MainWP from local database.
		 *
		 * @return array Settings enforced by MainWP.
		 *
		 * @since 5.0.0
		 */
		public static function get_mainwp_enforced_settings() {
			return Settings_Helper::get_option_value( 'mainwp_enforced_settings', array() );
		}

		/**
		 * Stores the settings enforced by MainWP in local database.
		 *
		 * @param array $settings Enforced settings.
		 *
		 * @since 5.0.0
		 */
		public static function set_mainwp_enforced_settings( $settings ) {
			Settings_Helper::set_option_value( 'mainwp_enforced_settings', $settings );
		}

		/**
		 * Deletes the settings enforced by MainWP from local database.
		 *
		 * @since 5.0.0
		 */
		public static function delete_mainwp_enforced_settings() {
			Settings_Helper::delete_option_value( 'mainwp_enforced_settings' );
		}

		/**
		 * The current pruning date.
		 *
		 * @return string
		 *
		 * @since 5.0.0
		 */
		public static function get_pruning_date() {
			if ( empty( trim( self::$pruning ) ) ) {
				self::$pruning = Settings_Helper::get_option_value( 'pruning-date' );
				if ( is_null( self::$pruning ) ) {
					self::$pruning = self::get_default_pruning_date();
				} elseif ( ! strtotime( self::$pruning ) ) {
					self::$pruning = self::get_default_pruning_date();
				}
			}

			return self::$pruning;
		}

		/**
		 * Set Plugin Viewers.
		 *
		 * @param array $users_or_roles – Users/Roles.
		 *
		 * @since 5.0.0
		 */
		public static function set_allowed_plugin_viewers( $users_or_roles ) {
			$old_value = Settings_Helper::get_option_value( 'plugin-viewers', '' );
			$changes   = Settings_Helper::determine_added_and_removed_items( $old_value, implode( ',', $users_or_roles ) );

			if ( ! empty( $changes['added'] ) ) {
				foreach ( $changes['added'] as $user ) {
					Alert_Manager::trigger_event(
						6050,
						array(
							'user'           => $user,
							'previous_users' => ( empty( $old_value ) ) ? \WSAL\Helpers\Settings_Helper::tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
							'EventType'      => 'added',
						)
					);
				}
			}

			if ( ! empty( $changes['removed'] ) && ! empty( $old_value ) ) {
				foreach ( $changes['removed'] as $user ) {
					if ( ! empty( $user ) ) {
						Alert_Manager::trigger_event(
							6050,
							array(
								'user'           => $user,
								'previous_users' => empty( $old_value ) ? \WSAL\Helpers\Settings_Helper::tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
								'EventType'      => 'removed',
							)
						);
					}
				}
			}

			self::$viewers = $users_or_roles;
			Settings_Helper::set_option_value( 'plugin-viewers', implode( ',', self::$viewers ), true );
		}

		/**
		 * Get Plugin Viewers.
		 *
		 * @return array List of users allowed to view the plugin.
		 *
		 * @since 5.0.0
		 */
		public static function get_allowed_plugin_viewers() {
			if ( empty( self::$viewers ) ) {
				self::$viewers = array_unique( array_filter( explode( ',', Settings_Helper::get_option_value( 'plugin-viewers', '' ) ) ) );
			}

			return self::$viewers;
		}

		/**
		 * Set MainWP Child Stealth Mode.
		 *
		 * Set the plugin in stealth mode for MainWP child sites.
		 *
		 * Following steps are taken in stealth mode:
		 *   1. Freemius connection is skipped.
		 *   2. Freemius notices are removed.
		 *   3. WSAL's incognito mode is set.
		 *   4. Other site admins are restricted.
		 *   5. The current user is set as the sole editor of WSAL.
		 *   6. Stealth mode option is saved.
		 *
		 * @since 3.2.3.3
		 */
		public static function set_mainwp_child_stealth_mode() {
			if (
			! Settings_Helper::get_boolean_option_value( 'mwp-child-stealth-mode', false ) // MainWP Child Stealth Mode is not already active.
			&& \WpSecurityAuditLog::is_mainwp_active() // And if MainWP Child plugin is installed & active.
			) {
				// Check if freemius state is anonymous.
				if ( ! wsal_freemius()->is_premium() && 'anonymous' === Settings_Helper::get_option_value( 'freemius_state', 'anonymous' ) ) {
					// Update Freemius state to skipped.
					Settings_Helper::set_option_value( 'wsal_freemius_state', 'skipped', true );

					if ( ! WP_Helper::is_multisite() ) {
						wsal_freemius()->skip_connection(); // Opt out.
					} else {
						wsal_freemius()->skip_connection( null, true ); // Opt out for all websites.
					}

					// Connect account notice.
					if ( class_exists( 'FS_Admin_Notices' ) ) {
						\FS_Admin_Notices::instance( 'wp-security-audit-log' )->remove_sticky( 'connect_account' );
					}
				}

				if ( ! wsal_freemius()->is_premium() ) {
					// Remove Freemius trial promotion notice.
					if ( class_exists( 'FS_Admin_Notices' ) ) {
						\FS_Admin_Notices::instance( 'wp-security-audit-log' )->remove_sticky( 'trial_promotion' );
					}
				}

				self::set_incognito( true ); // Incognito mode to hide WSAL on plugins page.
				self::set_restrict_log_viewer( 'only_me' );
				self::set_restrict_plugin_setting( 'only_me' );
				// Current user with fallback to default admin (in case this is triggered using WP CLI or something similar).
				$only_me_user_id = is_user_logged_in() ? get_current_user_id() : 1;
				self::set_only_me_user_id( $only_me_user_id );
				Settings_Helper::set_boolean_option_value( 'mwp-child-stealth-mode', true ); // Save stealth mode option.
			}
		}

		/**
		 * IP excluded from monitoring.
		 *
		 * @param array $ip IP addresses to exclude from monitoring.
		 *
		 * @since 5.0.0
		 */
		public static function set_excluded_monitoring_ip( $ip ) {
			$old_value = Settings_Helper::get_option_value( 'excluded-ip', array() );
			$changes   = Settings_Helper::determine_added_and_removed_items( $old_value, implode( ',', $ip ) );

			if ( ! empty( $changes['added'] ) ) {
				foreach ( $changes['added'] as $user ) {
					Alert_Manager::trigger_event(
						6055,
						array(
							'ip'           => $user,
							'previous_ips' => ( empty( $old_value ) ) ? Settings_Helper::tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
							'EventType'    => 'added',
						)
					);
				}
			}
			if ( ! empty( $changes['removed'] ) && ! empty( $old_value ) ) {
				foreach ( $changes['removed'] as $user ) {
					Alert_Manager::trigger_event(
						6055,
						array(
							'ip'           => $user,
							'previous_ips' => empty( $old_value ) ? Settings_Helper::tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
							'EventType'    => 'removed',
						)
					);
				}
			}

			Settings_Helper::set_excluded_monitoring_ips( $ip );
		}

		/**
		 * Set Custom Post Types excluded from monitoring.
		 *
		 * @param array $post_types - Array of post types to exclude.
		 *
		 * @since 2.6.7
		 */
		public static function set_excluded_post_types( $post_types ) {
			$old_value = Settings_Helper::get_option_value( 'custom-post-types', array() );
			$changes   = Settings_Helper::determine_added_and_removed_items( $old_value, implode( ',', $post_types ) );

			if ( ! empty( $changes['added'] ) ) {
				foreach ( $changes['added'] as $post_type ) {
					Alert_Manager::trigger_event(
						6056,
						array(
							'post_type'      => $post_type,
							'previous_types' => ( empty( $old_value ) ) ? Settings_Helper::tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
							'EventType'      => 'added',
						)
					);
				}
			}

			if ( ! empty( $changes['removed'] ) && ! empty( $old_value ) ) {
				foreach ( $changes['removed'] as $post_type ) {
					Alert_Manager::trigger_event(
						6056,
						array(
							'post_type'      => $post_type,
							'previous_types' => empty( $old_value ) ? Settings_Helper::tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
							'EventType'      => 'removed',
						)
					);
				}
			}

			Settings_Helper::set_option_value( 'custom-post-types', esc_html( implode( ',', $post_types ) ) );
		}

		/**
		 * Set Post Statuses excluded from monitoring.
		 *
		 * @param array $post_statuses - Array of post types to exclude.
		 *
		 * @since 5.0.0
		 */
		public static function set_excluded_post_statuses( $post_statuses ) {
			$old_value = Settings_Helper::get_option_value( 'excluded-post-status', array() );
			$changes   = Settings_Helper::determine_added_and_removed_items( $old_value, implode( ',', $post_statuses ) );

			if ( ! empty( $changes['added'] ) ) {
				foreach ( $changes['added'] as $post_status ) {
					Alert_Manager::trigger_event(
						6062,
						array(
							'post_status'     => $post_status,
							'previous_status' => ( empty( $old_value ) ) ? Settings_Helper::tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
							'EventType'       => 'added',
						)
					);
				}
			}

			if ( ! empty( $changes['removed'] ) && ! empty( $old_value ) ) {
				foreach ( $changes['removed'] as $post_status ) {
					Alert_Manager::trigger_event(
						6062,
						array(
							'post_status'     => $post_status,
							'previous_status' => empty( $old_value ) ? Settings_Helper::tidy_blank_values( $old_value ) : str_replace( ',', ', ', $old_value ),
							'EventType'       => 'removed',
						)
					);
				}
			}

			Settings_Helper::set_option_value( 'excluded-post-status', esc_html( implode( ',', $post_statuses ) ) );
		}
	}
}
