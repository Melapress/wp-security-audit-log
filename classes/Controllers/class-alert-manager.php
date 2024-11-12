<?php
/**
 * Controller: Alert Manager.
 *
 * Alert manager class file.
 *
 * @since     4.5
 *
 * @package   wsal
 * @subpackage controllers
 */

declare(strict_types=1);

namespace WSAL\Controllers;

use WSAL\Helpers\Logger;
use WSAL\Helpers\WP_Helper;
use WSAL\Helpers\User_Helper;
use WSAL\Controllers\Constants;
use WSAL\Helpers\Classes_Helper;
use WSAL\Helpers\Settings_Helper;
use WSAL\Entities\Metadata_Entity;
use WSAL\Entities\Occurrences_Entity;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Controllers\Alert_Manager' ) ) {
	/**
	 * Provides logging functionality for the comments.
	 *
	 * @since 4.5.0
	 */
	class Alert_Manager {
		/**
		 * Holds list of the ignored \WP_Post types.
		 */
		public const IGNORED_POST_TYPES = array(
			'attachment',          // Attachment CPT.
			'revision',            // Revision CPT.
			'nav_menu_item',       // Nav menu item CPT.
			'customize_changeset', // Customize changeset CPT.
			'custom_css',          // Custom CSS CPT.
			'wp_template',         // Gutenberg templates.
		);

		/**
		 * Array of loggers (WSAL_AbstractLogger).
		 *
		 * @var WSAL_AbstractLogger[]
		 *
		 * @since 4.5.0
		 */
		private static $loggers = array();

		/**
		 * Contains a list of alerts to trigger.
		 *
		 * @var array
		 *
		 * @since 4.5.0
		 */
		private static $pipeline = array();

		/**
		 * Holds the array with the excluded post types.
		 *
		 * @var array
		 *
		 * @since 4.5.0
		 */
		private static $ignored_cpts = array();

		/**
		 * Holds an array with all the registered alerts.
		 *
		 * @var array
		 *
		 * @since 4.5.0
		 */
		private static $alerts = array();

		/**
		 * Array of Deprecated Events.
		 *
		 * @since 4.5.0
		 *
		 * @var array
		 */
		private static $deprecated_events = array();

		/**
		 * Contains an array of alerts that have been triggered for this request.
		 *
		 * @var int[]
		 *
		 * @since 4.5.0
		 */
		private static $triggered_types = array();

		/**
		 * Holds the array of all the excluded users from the settings.
		 *
		 * @var array
		 *
		 * @since 4.5.0
		 */
		private static $excluded_users = array();

		/**
		 * Holds the array of all the excluded roles from the settings.
		 *
		 * @var array
		 *
		 * @since 4.5.0
		 */
		private static $excluded_roles = array();

		/**
		 * Holds an array of all the post types.
		 *
		 * @var array
		 *
		 * @since 4.5.0
		 */
		private static $all_post_types = array();

		/**
		 * Amount of seconds to check back for the given alert occurrence.
		 *
		 * @var int
		 *
		 * @since 4.5.0
		 */
		private static $seconds_to_check_back = 5;

		/**
		 * Holds a cached value if the checked alerts which were recently fired.
		 *
		 * @var array
		 *
		 * @since 4.5.0
		 */
		private static $cached_alert_checks = array();

		/**
		 * WP Users.
		 *
		 * Store WP Users for caching purposes.
		 *
		 * @var WP_User[]
		 *
		 * @since 4.5.0
		 */
		private static $wp_users = array();

		/**
		 * Is IP address disabled.
		 *
		 * Store WP Users for caching purposes.
		 *
		 * @var bool
		 *
		 * @since 4.5.0
		 */
		private static $is_ip_disabled = null;

		/**
		 * Holds the array with the event types
		 *
		 * @var array
		 *
		 * @since 4.5.0
		 */
		private static $event_types = array();

		/**
		 * Holds the array with the objects data
		 *
		 * @var array
		 *
		 * @since 4.5.0
		 */
		private static $objects_data = array();

		/**
		 * Initializes the class and adds the hooks.
		 *
		 * @return void
		 *
		 * @since 4.5.0
		 */
		public static function init() {
			// phpcs:disable
			// phpcs:enable
			\add_action( 'shutdown', array( __CLASS__, 'commit_pipeline' ), 8 );
		}

		/**
		 * Trigger an alert.
		 *
		 * @param int   $type    - Alert type.
		 * @param array $data    - Alert data.
		 * @param mixed $delayed - False if delayed, function if not.
		 *
		 * @since 4.5.0
		 */
		public static function trigger_event( $type, $data = array(), $delayed = false ) {
			// Check if IP is disabled.
			if ( self::is_ip_address_disabled() ) {
				return;
			}

			// Check if PostType index is set in data array.
			if ( isset( $data['PostType'] ) && ! empty( $data['PostType'] ) ) {
				// If the post type is disabled then return.
				if ( self::is_disabled_post_type( $data['PostType'] ) ) {
					return;
				}
			}

			// If the post status is disabled then return.
			if ( isset( $data['PostStatus'] ) && ! empty( $data['PostStatus'] ) ) {
				if ( self::is_disabled_post_status( $data['PostStatus'] ) ) {
					return;
				}
			}

			// Figure out the username.
			$username = User_Helper::get_current_user()->user_login;

			// If user switching plugin class exists and filter is set to disable then try to get the old user.
			if ( apply_filters( 'wsal_disable_user_switching_plugin_tracking', false ) && class_exists( '\user_switching' ) ) {
				$old_user = \user_switching::get_old_user();
				if ( isset( $old_user->user_login ) ) {
					// Looks like this is a switched user so setup original user values for use when logging.
					$username              = $old_user->user_login;
					$data['Username']      = $old_user->user_login;
					$data['CurrentUserID'] = $old_user->ID;
				}
			}

			if ( empty( $username ) && ! empty( $data['Username'] ) ) {
				$username = $data['Username'];
			}

			// Get current user roles.
			if ( isset( $old_user ) && false !== $old_user ) {
				// looks like this is a switched user so setup original user
				// roles and values for later user.
				$roles                    = User_Helper::get_user_roles( $old_user );
				$data['CurrentUserRoles'] = $roles;
			} else {
				// not a switched user so get the current user roles.
				$roles = User_Helper::get_user_roles();
			}
			if ( empty( $roles ) && ! empty( $data['CurrentUserRoles'] ) ) {
				$roles = $data['CurrentUserRoles'];
			}

			// If user or user role is enabled then go ahead.
			if ( self::check_enable_user_roles( $username, $roles ) ) {
				$data['Timestamp'] = ( isset( $data['Timestamp'] ) && ! empty( $data['Timestamp'] ) ) ? $data['Timestamp'] : current_time( 'U.u', 'true' );
				if ( $delayed ) {
					self::trigger_event_if( $type, $data, null );
				} else {
					self::commit_item( $type, $data, null );
				}
			}
		}

		/**
		 * Method: Returns if IP is disabled or not.
		 *
		 * @return bool True if current IP address is disabled.
		 *
		 * @since 4.5.0
		 */
		private static function is_ip_address_disabled() {
			if ( null === self::$is_ip_disabled ) {
				self::$is_ip_disabled = false;
				$ip                   = Settings_Helper::get_main_client_ip();
				$excluded_ips         = Settings_Helper::get_excluded_monitoring_ips();

				if ( ! empty( $excluded_ips ) ) {
					foreach ( $excluded_ips as $excluded_ip ) {
						if ( false !== strpos( $excluded_ip, '-' ) ) {
							$ip_range = Settings_Helper::get_ipv4_by_range( $excluded_ip );
							$ip_range = $ip_range->lower . '-' . $ip_range->upper;

							if ( Settings_Helper::check_ipv4_in_range( $ip, $ip_range ) ) {
								self::$is_ip_disabled = true;

								break;
							}
						} elseif ( $ip === $excluded_ip ) {
							self::$is_ip_disabled = true;

							break;
						}
					}
				}
			}

			return self::$is_ip_disabled;
		}

		/**
		 * Method: Check whether post type is disabled or not.
		 *
		 * @param string $post_type - Post type.
		 *
		 * @return bool - True if disabled, False if otherwise.
		 *
		 * @since 4.5.0
		 */
		public static function is_disabled_post_type( $post_type ) {
			return in_array( $post_type, self::get_all_post_types(), true );
		}

		/**
		 * Method: Check whether post status is disabled or not.
		 *
		 * @param string $post_status - Post status.
		 *
		 * @return bool - True if disabled, False if otherwise.
		 *
		 * @since 5.0.0
		 */
		public static function is_disabled_post_status( $post_status ) {
			return in_array( $post_status, Settings_Helper::get_excluded_post_statuses(), true );
		}

		/**
		 * Returns array with all post types
		 *
		 * @return array
		 *
		 * @since 4.5.0
		 */
		public static function get_all_post_types(): array {
			if ( empty( self::$all_post_types ) ) {
				self::$all_post_types = array_merge( Settings_Helper::get_excluded_post_types(), self::get_ignored_post_types() );
			}

			if ( ! \is_array( self::$all_post_types ) ) {
				self::$all_post_types = array();
			}

			return self::$all_post_types;
		}

		/**
		 * Returns all the ignored post types - post types are \WP_Post types
		 * Note: There is a difference between ignored types and disabled types.
		 *
		 * @since 4.5.0
		 */
		public static function get_ignored_post_types(): array {
			if ( empty( self::$ignored_cpts ) ) {
				/*
				 * Filter: `wsal_ignored_custom_post_types`
				 *
				 * Ignored custom post types filter.
				 *
				 * @param array $ignored_cpts - Array of custom post types.
				 *
				 * @since 3.3.1
				 */
				self::$ignored_cpts = apply_filters(
					'wsal_ignored_custom_post_types',
					array_unique(
						array_merge(
							Settings_Helper::get_excluded_post_types(),
							self::IGNORED_POST_TYPES
						)
					)
				);
			}

			if ( ! \is_array( self::$ignored_cpts ) ) {
				self::$ignored_cpts = array();
			}

			return self::$ignored_cpts;
		}

		/**
		 * Removes type from the ignored post types.
		 *
		 * @param string $post_type - The name of the post type to remove.
		 *
		 * @return void
		 *
		 * @since 4.5.0
		 */
		public static function remove_from_ignored_post_types( string $post_type ) {
			if ( empty( self::$ignored_cpts ) ) {
				self::get_ignored_post_types();
			}

			$key = array_search( $post_type, self::$ignored_cpts, true );

			if ( false !== $key ) {
				unset( self::$ignored_cpts[ $key ] );
			}
		}

		/**
		 * Check enable user and roles.
		 *
		 * @param string $user  - Username.
		 * @param array  $roles - User roles.
		 *
		 * @return bool - True if enable false otherwise.
		 *
		 * @since 4.5.0
		 */
		public static function check_enable_user_roles( $user, $roles ) {
			if ( '' !== $user && self::is_disabled_user( $user ) ) {
				return false;
			}

			if ( '' !== $roles && self::is_disabled_role( $roles ) ) {
				return false;
			}

			return true;
		}

		/**
		 * Returns whether user is enabled or not.
		 *
		 * @param string $user - Username.
		 *
		 * @return bool True if disabled, false otherwise.
		 *
		 * @since 4.5.0
		 */
		public static function is_disabled_user( $user ) {
			if ( empty( self::$excluded_users ) ) {
				self::$excluded_users = Settings_Helper::get_excluded_monitoring_users();
			}

			return in_array( $user, self::$excluded_users, true );
		}

		/**
		 * Returns whether user is enabled or not.
		 *
		 * @param array $roles - User roles.
		 *
		 * @return bool True if disabled, false otherwise.
		 *
		 * @since 4.5.0
		 */
		public static function is_disabled_role( $roles ) {
			$is_disabled = false;

			if ( empty( self::$excluded_roles ) ) {
				self::$excluded_roles = Settings_Helper::get_excluded_monitoring_roles();
			}

			if ( ! \is_array( self::$excluded_roles ) ) {
				self::$excluded_roles = array( self::$excluded_roles );
			}

			if ( ! \is_array( $roles ) ) {
				$roles = array( $roles );
			}

			foreach ( $roles as $role ) {
				if ( in_array( $role, self::$excluded_roles, true ) ) {
					$is_disabled = true;
				}
			}

			return $is_disabled;
		}

		/**
		 * Trigger only if a condition is met at the end of request.
		 *
		 * @param int      $type - Alert type ID.
		 * @param array    $data - Alert data.
		 * @param callable $cond - A future condition callback (receives an object of type WSAL_AlertManager as parameter).
		 *
		 * @since 4.5.0
		 */
		public static function trigger_event_if( $type, $data, $cond = null ) {
			// Check if IP is disabled.
			if ( self::is_ip_address_disabled() ) {
				return;
			}

			// Check if PostType index is set in data array.
			if ( isset( $data['PostType'] ) && ! empty( $data['PostType'] ) ) {
				// If the post type is disabled then return.
				if ( self::is_disabled_post_type( $data['PostType'] ) ) {
					return;
				}
			}

			// If the post status is disabled then return.
			if ( isset( $data['PostStatus'] ) && ! empty( $data['PostStatus'] ) ) {
				if ( self::is_disabled_post_status( $data['PostStatus'] ) ) {
					return;
				}
			}

			$username = null;

			// if user switching plugin class exists and filter is set to disable then try get the old user.
			if ( \apply_filters( 'wsal_disable_user_switching_plugin_tracking', false ) && class_exists( '\user_switching' ) ) {
				$old_user = \user_switching::get_old_user();
				if ( isset( $old_user->user_login ) ) {
					// looks like this is a switched user so setup original user
					// values for use when logging.
					$username              = $old_user->user_login;
					$data['Username']      = $old_user->user_login;
					$data['CurrentUserID'] = $old_user->ID;
				}
			}

			$roles = array();
			if ( 1000 === $type ) {
				// When event 1000 is triggered, the user is not logged in.
				// We need to extract the username and user roles from the event data.
				$username = array_key_exists( 'Username', $data ) ? $data['Username'] : null;
				$roles    = array_key_exists( 'CurrentUserRoles', $data ) ? $data['CurrentUserRoles'] : array();
			} elseif ( class_exists( 'user_switching' ) && isset( $old_user ) && false !== $old_user ) {
				// looks like this is a switched user so setup original user
				// roles and values for later user.
				$roles                    = User_Helper::get_user_roles( $old_user );
				$data['CurrentUserRoles'] = $roles;
			} else {
				$username = User_Helper::get_current_user()->user_login;
				$roles    = User_Helper::get_user_roles();
			}

			if ( self::check_enable_user_roles( $username, $roles ) ) {
				if ( ! array_key_exists( 'Timestamp', $data ) ) {
					$data['Timestamp'] = current_time( 'U.u', 'true' );
				}
				self::$pipeline[] = array(
					'type' => $type,
					'data' => $data,
					'cond' => $cond,
				);
			}
		}

		/**
		 * Method: Commit an alert now.
		 *
		 * @param int   $type   - Alert type.
		 * @param array $data   - Data of the alert.
		 * @param array $cond   - Condition for the alert.
		 * @param bool  $_retry - Retry.
		 *
		 * @return mixed
		 *
		 * @internal
		 *
		 * @since 4.5.0
		 */
		protected static function commit_item( $type, $data, $cond, $_retry = true ) {
			// Double NOT operation here is intentional. Same as ! ( bool ) [ $value ]
			// NOTE: return false on a true condition to compensate.
			if ( ! $cond || (bool) call_user_func( $cond ) ) {
				if ( self::is_enabled( $type ) ) {
					if ( isset( self::get_alerts()[ $type ] ) ) {
						// Ok, convert alert to a log entry.
						self::$triggered_types[] = $type;
						self::log( $type, $data );
					} elseif ( $_retry ) {

						return self::commit_item( $type, $data, $cond, false );
					} else {
						// In general this shouldn't happen, but it could, so we handle it here.
						/* translators: Event ID */
						$error_message = sprintf( esc_html__( 'Event with code %d has not be registered.', 'wp-security-audit-log' ), $type );
						Logger::log( $error_message );
					}
				}
			}
		}

		/**
		 * Returns whether alert of type $type is enabled or not.
		 *
		 * @param int $type Alert type.
		 *
		 * @return bool True if enabled, false otherwise.
		 *
		 * @since 4.5.0
		 */
		public static function is_enabled( $type ) {
			$disabled_events = Settings_Helper::get_disabled_alerts();

			return ! in_array( $type, $disabled_events, true );
		}

		/**
		 * Register a whole group of items.
		 *
		 * @param array $groups - An array with group name as the index and an array of group items as the value.
		 *                      Item values is an array of [type, code, description, message, object, event type] respectively.
		 *
		 * @since 5.1.1
		 */
		public static function register_group( $groups ) {
			foreach ( $groups as $name => $group ) {
				foreach ( $group as $subname => $subgroup ) {
					foreach ( $subgroup as $item ) {
						self::register( $name, $subname, $item );
					}
				}
			}
		}

		/**
		 * Register an alert type.
		 *
		 * @param string $category    Category name.
		 * @param string $subcategory Subcategory name.
		 * @param array  $info        Event information from defaults.php.
		 *
		 * @since 4.5.0
		 */
		public static function register( $category, $subcategory, $info ) {
			// Default for optional fields.
			$metadata   = array();
			$links      = array();
			$object     = '';
			$event_type = '';

			$definition_items_count = count( $info );
			if ( 8 === $definition_items_count ) {
				// Most recent event definition introduced in version 4.2.1.
				list($code, $severity, $desc, $message, $metadata, $links, $object, $event_type) = $info;
			} elseif ( 6 === $definition_items_count ) {
				// Legacy event definition for backwards compatibility (used prior to version 4.2.1).
				list($code, $severity, $desc, $message, $object, $event_type) = $info;
			} else {
				// Even older legacy event definition for backwards compatibility.
				list($code, $severity, $desc, $message) = $info;
			}

			if ( is_string( $links ) ) {
				$links = array( $links );
			}

			if ( isset( self::$alerts[ $code ] ) ) {
				\add_action( 'admin_notices', array( __CLASS__, 'duplicate_event_notice' ) );
				/* Translators: Event ID */
				$error_message = sprintf( esc_html__( 'Event %s already registered with WP Activity Log.', 'wp-security-audit-log' ), $code );
				Logger::log( $error_message );

				return;
			}

			/**
			 * WSAL Filter: `wsal_event_metadata_definition`.
			 *
			 * Filters event metadata definition before registering specific event with the alert manager. This is the
			 * preferred way to change metadata definition of built-in events.
			 *
			 * @param array $metadata - Event data.
			 * @param int   $code     - Event ID.
			 *
			 * @since 4.5.0
			 */
			$metadata = \apply_filters( 'wsal_event_metadata_definition', $metadata, $code );

			self::$alerts[ $code ] = array(
				'code'        => $code,
				'severity'    => $severity,
				'category'    => $category,
				'subcategory' => $subcategory,
				'desc'        => $desc,
				'message'     => $message,
				'metadata'    => $metadata,
				'links'       => $links,
				'object'      => $object,
				'event_type'  => $event_type,
			);
		}

		/**
		 * Duplicate Event Notice.
		 *
		 * @since 4.5.0
		 */
		public static function duplicate_event_notice() {
			$class   = 'notice notice-error';
			$message = __( 'You have custom events that are using the same ID or IDs which are already registered in the plugin, so they have been disabled.', 'wp-security-audit-log' );
			printf(
				/* Translators: 1.CSS classes, 2. Notice, 3. Contact us link */
				'<div class="%1$s"><p>%2$s %3$s ' . \esc_html__( '%4$s to help you solve this issue.', 'wp-security-audit-log' ) . '</p></div>',
				\esc_attr( $class ),
				'<span style="color:#dc3232; font-weight:bold;">' . \esc_html__( 'ERROR:', 'wp-security-audit-log' ) . '</span>',
				\esc_html( $message ),
				'<a href="https://melapress.com/contact" target="_blank">' . \esc_html__( 'Contact us', 'wp-security-audit-log' ) . '</a>'
			);
		}

		/**
		 * Converts an Alert into a Log entry (by invoking loggers).
		 * You should not call this method directly.
		 *
		 * @param int   $event_id   - Alert type.
		 * @param array $event_data - Misc alert data.
		 */
		public static function log( $event_id, $event_data = array() ) {
			$alert_obj = self::get_alerts()[ $event_id ];

			if ( ! isset( $event_data['ClientIP'] ) ) {
				$client_ip = Settings_Helper::get_main_client_ip();
				if ( ! empty( $client_ip ) ) {
					$event_data['ClientIP'] = $client_ip;
				}
			}
			if ( ! isset( $event_data['OtherIPs'] ) && Settings_Helper::get_boolean_option_value( 'use-proxy-ip' ) ) {
				$other_ips = Settings_Helper::get_client_ips();
				if ( ! empty( $other_ips ) ) {
					$event_data['OtherIPs'] = $other_ips;
				}
			}
			if ( ! isset( $event_data['UserAgent'] ) ) {
				if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
					$event_data['UserAgent'] = \sanitize_text_field( \wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
				}
			}
			if ( ! isset( $event_data['Username'] ) && ! isset( $event_data['CurrentUserID'] ) ) {
				if ( function_exists( 'get_current_user_id' ) ) {
					$event_data['CurrentUserID'] = \get_current_user_id();
					if ( 0 !== $event_data['CurrentUserID'] ) {
						$event_data['Username'] = \get_user_by( 'ID', $event_data['CurrentUserID'] )->user_login;
					}
					if ( 0 === $event_data['CurrentUserID'] ) {
						if ( 'system' === \strtolower( $alert_obj['object'] ) ) {
							$event_data['Username'] = 'System';
						} elseif ( str_starts_with( \strtolower( $alert_obj['object'] ), 'woocommerce' ) ) {
								$event_data['Username'] = 'WooCommerce System';
						} else {
							$event_data['Username'] = 'Unknown User';
						}
					}
				}
			}
			if ( isset( $event_data['CurrentUserID'] ) && ! isset( $event_data['Username'] ) ) {
				if ( 0 === $event_data['CurrentUserID'] ) {
					if ( 'system' === \strtolower( $alert_obj['object'] ) ) {
						$event_data['Username'] = 'System';
					} elseif ( str_starts_with( \strtolower( $alert_obj['object'] ), 'woocommerce' ) ) {
						$event_data['Username'] = 'WooCommerce System';
					} else {
						$event_data['Username'] = 'Unknown User';
					}
				} else {
					$user = \get_user_by( 'ID', $event_data['CurrentUserID'] );
					if ( $user ) {
						$event_data['Username'] = $user->user_login;
					} else {
						$event_data['Username'] = 'Deleted';
					}
				}
			}
			if ( ! isset( $event_data['CurrentUserRoles'] ) && function_exists( 'is_user_logged_in' ) && \is_user_logged_in() ) {
				$current_user_roles = User_Helper::get_user_roles();
				if ( ! empty( $current_user_roles ) ) {
					$event_data['CurrentUserRoles'] = $current_user_roles;
				}
			}

			// If the user sessions plugin is loaded try to attach the SessionID.
			if ( ! isset( $event_data['SessionID'] ) && class_exists( '\WSAL\Helpers\User_Sessions_Helper' ) ) {
				// Try to get the session id generated from logged in cookie.
				$session_id = \WSAL\Helpers\User_Sessions_Helper::get_session_id_from_logged_in_user_cookie();
				// If we have a SessionID then add it to event_data.
				if ( ! empty( $session_id ) ) {
					$event_data['SessionID'] = $session_id;
				}
			}

			// Get event severity.
			$alert_code = $alert_obj ? Constants::get_constant_code( $alert_obj['severity'] ) : -1;

			if ( -1 !== $alert_code ) {
				$event_data['Severity'] = $alert_code;
			}

			/*
			 * In cases where we were not able to figure out a severity already
			 * use a default of 200: info.
			 *
			 * @since 4.5.0
			 */
			if ( ! isset( $event_data['Severity'] ) ) {
				// Assuming this is a misclassified item and using info code.
				// INFO (200): Interesting events.
				$event_data['Severity'] = 200;
			}

			// Add event object.
			if ( $alert_obj && ! isset( $event_data['Object'] ) ) {
				$event_data['Object'] = $alert_obj['object'];
			}

			// Add event type.
			if ( $alert_obj && ! isset( $event_data['EventType'] ) ) {
				$event_data['EventType'] = $alert_obj['event_type'];
			}

			// Append further details if in multisite.
			if ( WP_Helper::is_multisite() ) {
				$event_data['SiteID']  = get_current_blog_id();
				$event_data['SiteURL'] = get_site_url( $event_data['SiteID'] );
			}

			/**
			 * WSAL Filter: `wsal_event_id_before_log`.
			 *
			 * Filters event id before logging it to the database.
			 *
			 * @since 3.3.1
			 *
			 * @param int   $event_id   - Event ID.
			 * @param array $event_data - Event data.
			 */
			$event_id = apply_filters( 'wsal_event_id_before_log', $event_id, $event_data );

			/**
			 * WSAL Filter: `wsal_event_data_before_log`.
			 *
			 * Filters event data before logging it to the database.
			 *
			 * @since 3.3.1
			 *
			 * @param array $event_data - Event data.
			 * @param int   $event_id   - Event ID.
			 */
			$event_data = apply_filters( 'wsal_event_data_before_log', $event_data, $event_id );

			// phpcs:ignore

			foreach ( self::get_loggers() as $logger ) {
				// phpcs:disable
				// phpcs:enable
				$logger::log( $event_id, $event_data );
			}
			// phpcs:disable
		}

		/**
		 * Returns all the alerts. If the array with the alerts is not initialized - it first tries to initialize it.
		 *
		 * @since 4.5.0
		 */
		public static function get_alerts(): array {
			if ( empty( self::$alerts ) ) {
				if ( ! \function_exists( 'set_wsal_alerts' ) ) {
					\WpSecurityAuditLog::load_defaults();
				}
				set_wsal_alerts();
			}

			if ( ! array( self::$alerts ) ) {
				self::$alerts = array();
			}

			return self::$alerts;
		}

		/**
		 * Add newly created logger to list.
		 *
		 * @param \WSAL_AbstractLogger $logger The new logger.
		 *
		 * @since 4.5.0
		 */
		public static function add_logger_instance( \WSAL_AbstractLogger $logger ) {
			self::get_loggers();
			self::$loggers[] = $logger;
		}

		/**
		 * Collects all loggers (if not already) and returns them.
		 *
		 * @since 4.5.0
		 */
		public static function get_loggers(): array {
			if ( empty( self::$loggers ) ) {
				$loggers_list = Classes_Helper::get_classes_by_namespace( '\WSAL\Loggers' );

				foreach ( $loggers_list as $class_name ) {
					self::$loggers[] = ( new $class_name() );
				}
			}

			return self::$loggers;
		}


		/**
		 * Method: Runs over triggered alerts in pipeline and passes them to loggers.
		 *
		 * @internal
		 *
		 * @since 5.1.1
		 */
		public static function commit_pipeline() {
			foreach ( self::$pipeline as $key => $item ) {
				unset( self::$pipeline[ $key ] );
				self::commit_item( $item['type'], $item['data'], $item['cond'] );
				self::$pipeline[ $key ] = $item;
			}
		}

		/**
		 * Returns the list with all the deprecated events.
		 *
		 * @return array
		 *
		 * @since 4.5.0
		 */
		public static function get_deprecated_events() {
			if ( empty( self::$deprecated_events ) ) {
				self::$deprecated_events = apply_filters( 'wsal_deprecated_event_ids', array( 2004, 2005, 2006, 2007, 2009, 2013, 2015, 2018, 2020, 2022, 2026, 2028, 2059, 2060, 2061, 2064, 2066, 2069, 2075, 2087, 2102, 2103, 2113, 2114, 2115, 2116, 2117, 2118, 5020, 5026, 2107, 2003, 2029, 2030, 2031, 2032, 2033, 2034, 2035, 2036, 2037, 2038, 2039, 2040, 2041, 2056, 2057, 2058, 2063, 2067, 2068, 2070, 2072, 2076, 2088, 2104, 2105, 5021, 5027, 2108 ) );
			}

			return self::$deprecated_events;
		}

		/**
		 * Method: True if at the end of request an alert of this type will be triggered.
		 *
		 * @param int $type  - Alert type ID.
		 * @param int $count - A minimum number of event occurrences.
		 *
		 * @since 4.5.0
		 */
		public static function will_trigger( $type, $count = 1 ): bool {
			$number_found = 0;
			foreach ( self::$pipeline as $item ) {
				if ( $item['type'] === $type ) {
					++$number_found;
					if ( 1 === $count || $number_found === $count ) {
						return true;
					}
				}
			}

			return false;
		}

		/**
		 * Method: True if an alert has been or will be triggered in this request, false otherwise.
		 *
		 * @param int $type  - Alert type ID.
		 * @param int $count - A minimum number of event occurrences.
		 *
		 * @return bool
		 *
		 * @since 4.5.0
		 */
		public static function will_or_has_triggered( $type, $count = 1 ) {
			return in_array( $type, self::$triggered_types, true ) || self::will_trigger( $type, $count );
		}

		/**
		 * Method: True if an alert has been or will be triggered in this request, false otherwise.
		 *
		 * @param int $type  - Alert type ID.
		 * @param int $count - A minimum number of event occurrences.
		 *
		 * @return bool
		 *
		 * @since 4.5.0
		 */
		public static function has_triggered( $type, $count = 1 ) {
			return in_array( $type, self::$triggered_types, true );
		}

		/**
		 * Method: Returns array of alerts by category.
		 *
		 * @param string $category - Alerts category.
		 *
		 * @return WSAL_Alert[]
		 *
		 * @since 4.5.0
		 */
		public static function get_alerts_by_category( $category ) {
			// Categorized alerts array.
			$alerts = array();
			foreach ( self::get_alerts() as $alert ) {
				if ( $category === $alert['category'] ) {
					$alerts[ $alert['code'] ] = $alert;
				}
			}

			return $alerts;
		}

		/**
		 * Method: Returns array of alerts by sub-category.
		 *
		 * @param string $sub_category - Alerts sub-category.
		 *
		 * @return WSAL_Alert[]
		 *
		 * @since 4.5.0
		 */
		public static function get_alerts_by_sub_category( $sub_category ) {
			// Sub-categorized alerts array.
			$alerts = array();
			foreach ( self::get_alerts() as $alert ) {
				if ( $sub_category === $alert['subcategory'] ) {
					$alerts[ $alert['code'] ] = $alert;
				}
			}

			return $alerts;
		}

		/**
		 * Check if the alert was triggered.
		 *
		 * @param integer|array $alert_id - Alert code.
		 *
		 * @return boolean
		 *
		 * @since 4.5.0
		 */
		public static function was_triggered( $alert_id ) {

			$last_occurrence = Occurrences_Entity::build_query(
				array( 'alert_id' => 'alert_id' ),
				array(),
				array( 'created_on' => 'DESC' ),
				array( 1 )
			);

			if ( ! empty( $last_occurrence ) && isset( $last_occurrence[0]['alert_id'] ) ) {
				if ( ! is_array( $alert_id ) && (int) $last_occurrence[0]['alert_id'] === (int) $alert_id ) {
					return true;
				} elseif ( is_array( $alert_id ) && in_array( (int) $last_occurrence[0]['alert_id'], $alert_id, true ) ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Check if the alert was triggered recently.
		 *
		 * Checks last 5 events if they occurred less than self::$seconds_to_check_back seconds ago.
		 *
		 * @param int|array $alert_id - Alert code.
		 *
		 * @return bool
		 *
		 * @since 4.6.3
		 */
		public static function was_triggered_recently( $alert_id ) {
			// if we have already checked this don't check again.
			if ( isset( self::$cached_alert_checks ) && array_key_exists( $alert_id, self::$cached_alert_checks ) && self::$cached_alert_checks[ $alert_id ] ) {
				return true;
			}

			$last_occurrences = self::get_latest_events( 5 );

			$known_to_trigger = false;

			if ( \is_array( $last_occurrences ) ) {
				foreach ( $last_occurrences as $last_occurrence ) {
					if ( $known_to_trigger ) {
						break;
					}
					if ( ! empty( $last_occurrence ) && \is_array( ! empty( $last_occurrence ) ) && \key_exists( 'created_on', $last_occurrence ) && ( $last_occurrence['created_on'] + self::$seconds_to_check_back ) > time() ) {
						if ( ! is_array( $alert_id ) && (int) $last_occurrence['alert_id'] === $alert_id ) {
							$known_to_trigger = true;
						} elseif ( is_array( $alert_id ) && in_array( (int) $last_occurrence[0]['alert_id'], $alert_id, true ) ) {
							$known_to_trigger = true;
						}
					}
				}
			}
			// once we know the answer to this don't check again to avoid queries.
			self::$cached_alert_checks[ $alert_id ] = $known_to_trigger;

			return $known_to_trigger;
		}

		/**
		 * Get latest events from DB.
		 *
		 * @param int  $limit – Number of events.
		 * @param bool $include_meta - Should we include meta to the collected events.
		 * @param bool $first - If is set to true, it will extract oldest events (default is most recent ones).
		 *
		 * @return array|bool
		 *
		 * @since 4.5.0
		 * @since 5.0.0 $first flag is added
		 */
		public static function get_latest_events( $limit = 1, bool $include_meta = false, bool $first = false ) {

			if ( ! Occurrences_Entity::get_connection()->has_connected ) {
				// Connection problem while using external database (if local database is used, we would see WordPress's
				// "Error Establishing a Database Connection" screen).
				return false;
			}

			$direction = 'DESC';
			if ( $first ) {
				$direction = 'ASC';
			}

			$query = array();

			// Get site id.
			$site_id = (int) WP_Helper::get_view_site_id();

			$site_id = \apply_filters( 'wsal_alter_site_id', $site_id );

			// if we have a blog id then add it.
			if ( $site_id && 0 < $site_id ) {
				$query['AND'][] = array( ' site_id = %s ' => $site_id );
			}

			if ( ! $include_meta ) {
				$events = Occurrences_Entity::build_query( array(), $query, array( 'created_on' => $direction ), array( $limit ) );
			} else {

				$meta_table_name = Metadata_Entity::get_table_name();
				$join_clause     = array(
					$meta_table_name => array(
						'direction'   => 'LEFT',
						'join_fields' => array(
							array(
								'join_field_left'  => 'occurrence_id',
								'join_table_right' => Occurrences_Entity::get_table_name(),
								'join_field_right' => 'id',
							),
						),
					),
				);
				// order results by date and return the query.
				$meta_full_fields_array       = Metadata_Entity::prepare_full_select_statement();
				$occurrence_full_fields_array = Occurrences_Entity::prepare_full_select_statement();

				/**
				 * Limit here is set to $limit * 15, because now we have to extract metadata as well.
				 * Because of that we can not use limit directly here. We will extract enough data to include the metadata as well and limit the results later on - still fastest than creating enormous amount of queries.
				 * Currently there are no more than 10 records (meta) per occurrence, we are using 12 just in case.
				 */
				$events = Occurrences_Entity::build_query( array_merge( $meta_full_fields_array, $occurrence_full_fields_array ), $query, array( 'created_on' => $direction ), array( $limit * 12 ), $join_clause );

				$events = Occurrences_Entity::prepare_with_meta_data( $events );

				$events = array_slice( $events, 0, $limit );
			}

			if ( ! empty( $events ) && is_array( $events ) ) {
				return $events;
			}

			return array();
		}

		/**
		 * Method: Log the message for sensor.
		 *
		 * @param int    $type    - Type of alert.
		 * @param string $message - Alert message.
		 * @param mixed  $args    - Message arguments.
		 *
		 * @since 5.1.1
		 */
		public static function log_problem( $type, $message, $args ) {
			self::trigger_event(
				$type,
				array(
					'Message' => $message,
					'Context' => $args,
					'Trace'   => debug_backtrace(),
				)
			);
		}

		/**
		 * Method: Log error message for sensor.
		 *
		 * @param string $message - Alert message.
		 * @param mixed  $args    - Message arguments.
		 *
		 * @since 4.5.0
		 */
		public static function log_error( $message, $args ) {
			self::log_problem( 0001, $message, $args );
		}

		/**
		 * Method: Log warning message for sensor.
		 *
		 * @param string $message - Alert message.
		 * @param mixed  $args    - Message arguments.
		 *
		 * @since 4.5.0
		 */
		public static function log_warn( $message, $args ) {
			self::log_problem( 0002, $message, $args );
		}

		/**
		 * Method: Log info message for sensor.
		 *
		 * @param string $message - Alert message.
		 * @param mixed  $args    - Message arguments.
		 *
		 * @since 4.5.0
		 */
		protected function log_info( $message, $args ) {
			self::log_problem( 0003, $message, $args );
		}

		/**
		 * Get event type data array or optionally just value of a single type.
		 *
		 * @param string $type A type that the string is requested for (optional).
		 *
		 * @return array|string
		 *
		 * @since 4.5.0
		 */
		public static function get_event_type_data( $type = '' ) {
			if ( empty( self::$event_types ) ) {

				self::$event_types = array(
					'login'        => esc_html__( 'Login', 'wp-security-audit-log' ),
					'logout'       => esc_html__( 'Logout', 'wp-security-audit-log' ),
					'installed'    => esc_html__( 'Installed', 'wp-security-audit-log' ),
					'activated'    => esc_html__( 'Activated', 'wp-security-audit-log' ),
					'deactivated'  => esc_html__( 'Deactivated', 'wp-security-audit-log' ),
					'uninstalled'  => esc_html__( 'Uninstalled', 'wp-security-audit-log' ),
					'updated'      => esc_html__( 'Updated', 'wp-security-audit-log' ),
					'created'      => esc_html__( 'Created', 'wp-security-audit-log' ),
					'modified'     => esc_html__( 'Modified', 'wp-security-audit-log' ),
					'deleted'      => esc_html__( 'Deleted', 'wp-security-audit-log' ),
					'published'    => esc_html__( 'Published', 'wp-security-audit-log' ),
					'approved'     => esc_html__( 'Approved', 'wp-security-audit-log' ),
					'unapproved'   => esc_html__( 'Unapproved', 'wp-security-audit-log' ),
					'enabled'      => esc_html__( 'Enabled', 'wp-security-audit-log' ),
					'disabled'     => esc_html__( 'Disabled', 'wp-security-audit-log' ),
					'added'        => esc_html__( 'Added', 'wp-security-audit-log' ),
					'failed-login' => esc_html__( 'Failed Login', 'wp-security-audit-log' ),
					'blocked'      => esc_html__( 'Blocked', 'wp-security-audit-log' ),
					'uploaded'     => esc_html__( 'Uploaded', 'wp-security-audit-log' ),
					'restored'     => esc_html__( 'Restored', 'wp-security-audit-log' ),
					'opened'       => esc_html__( 'Opened', 'wp-security-audit-log' ),
					'viewed'       => esc_html__( 'Viewed', 'wp-security-audit-log' ),
					'started'      => esc_html__( 'Started', 'wp-security-audit-log' ),
					'stopped'      => esc_html__( 'Stopped', 'wp-security-audit-log' ),
					'removed'      => esc_html__( 'Removed', 'wp-security-audit-log' ),
					'unblocked'    => esc_html__( 'Unblocked', 'wp-security-audit-log' ),
					'renamed'      => esc_html__( 'Renamed', 'wp-security-audit-log' ),
					'duplicated'   => esc_html__( 'Duplicated', 'wp-security-audit-log' ),
					'submitted'    => esc_html__( 'Submitted', 'wp-security-audit-log' ),
					'revoked'      => esc_html__( 'Revoked', 'wp-security-audit-log' ),
					'sent'         => esc_html__( 'Sent', 'wp-security-audit-log' ),
					'executed'     => esc_html__( 'Executed', 'wp-security-audit-log' ),
				);
				// sort the types alphabetically.
				asort( self::$event_types );
				self::$event_types = apply_filters(
					'wsal_event_type_data',
					self::$event_types
				);
			}

			/*
			 * If a specific type was requested then try return that otherwise the
			 * full array gets returned.
			 *
			 * @since 4.0.3
			 */
			if ( ! empty( $type ) ) {
				// NOTE: if we requested type doesn't exist returns 'unknown type'.
				return ( isset( self::$event_types[ $type ] ) ) ? self::$event_types[ $type ] : __( 'unknown type', 'wp-security-audit-log' );
			}

			// if a specific type was not requested return the full array.
			return self::$event_types;
		}

		/**
		 * Get event objects.
		 *
		 * @param string $object An object the string is requested for (optional).
		 *
		 * @return array|string
		 *
		 * @since 4.5.0
		 */
		public static function get_event_objects_data( $object = '' ) {
			if ( empty( self::$objects_data ) ) {

				self::$objects_data = array(
					'user'              => esc_html__( 'User', 'wp-security-audit-log' ),
					'system'            => esc_html__( 'System', 'wp-security-audit-log' ),
					'plugin'            => esc_html__( 'Plugin', 'wp-security-audit-log' ),
					'database'          => esc_html__( 'Database', 'wp-security-audit-log' ),
					'post'              => esc_html__( 'Post', 'wp-security-audit-log' ),
					'file'              => esc_html__( 'File', 'wp-security-audit-log' ),
					'tag'               => esc_html__( 'Tag', 'wp-security-audit-log' ),
					'comment'           => esc_html__( 'Comment', 'wp-security-audit-log' ),
					'setting'           => esc_html__( 'Setting', 'wp-security-audit-log' ),
					'system-setting'    => esc_html__( 'System Setting', 'wp-security-audit-log' ),
					'cron-job'          => esc_html__( 'Cron Jobs', 'wp-security-audit-log' ),
					'mainwp-network'    => esc_html__( 'MainWP Network', 'wp-security-audit-log' ),
					'mainwp'            => esc_html__( 'MainWP', 'wp-security-audit-log' ),
					'category'          => esc_html__( 'Category', 'wp-security-audit-log' ),
					'custom-field'      => esc_html__( 'Custom Field', 'wp-security-audit-log' ),
					'widget'            => esc_html__( 'Widget', 'wp-security-audit-log' ),
					'menu'              => esc_html__( 'Menu', 'wp-security-audit-log' ),
					'theme'             => esc_html__( 'Theme', 'wp-security-audit-log' ),
					'activity-log'      => esc_html__( 'Activity log', 'wp-security-audit-log' ),
					'wp-activity-log'   => esc_html__( 'WP Activity Log', 'wp-security-audit-log' ),
					'multisite-network' => esc_html__( 'Multisite Network', 'wp-security-audit-log' ),
					'ip-address'        => esc_html__( 'IP Address', 'wp-security-audit-log' ),
				);

				asort( self::$objects_data );
				self::$objects_data = apply_filters(
					'wsal_event_objects',
					self::$objects_data
				);
			}

			/*
			 * If a specific object was requested then try return that otherwise
			 * the full array gets returned.
			 *
			 * @since 4.0.3
			 */
			if ( ! empty( $object ) ) {
				// NOTE: if we requested object doesn't exist returns 'unknown object'.
				return ( isset( self::$objects_data[ $object ] ) ) ? self::$objects_data[ $object ] : __( 'unknown object', 'wp-security-audit-log' );
			}

			// if a specific object was not requested return the full array.
			return self::$objects_data;
		}

		/**
		 * Return user data array of the events.
		 *
		 * @param string $username – Username.
		 *
		 * @return array
		 *
		 * @since 4.5.0
		 */
		public static function get_event_user_data( $username ) {
			// User data.
			$user_data = array();

			// Handle WSAL usernames.
			if ( empty( $username ) ) {
				$user_data['username'] = 'System';
			} elseif ( 'Plugin' === $username ) {
				$user_data['username'] = 'Plugin';
			} elseif ( 'Plugins' === $username ) {
				$user_data['username'] = 'Plugins';
			} elseif ( 'Website Visitor' === $username || 'Unregistered user' === $username ) {
				$user_data['username'] = 'Unregistered user';
			} else {
				// Check WP user.
				if ( isset( self::$wp_users[ $username ] ) ) {
					// Retrieve from users cache.
					$user_data = self::$wp_users[ $username ];
				} else {
					// Get user from WP.
					$user = \get_user_by( 'login', $username );

					if ( $user && $user instanceof \WP_User ) {
						// Store the user data in class member.
						self::$wp_users[ $username ] = array(
							'ID'            => $user->ID,
							'user_login'    => $user->user_login,
							'first_name'    => $user->first_name,
							'last_name'     => $user->last_name,
							'display_name'  => $user->display_name,
							'user_email'    => $user->user_email,
							'user_nicename' => $user->user_nicename,
							'user_roles'    => User_Helper::get_user_roles( $user ),
						);

						$user_data = self::$wp_users[ $username ];
					}
				}

				// Set user data.
				if ( ! $user ) {
					$user_data['username'] = 'System';
				}
			}

			return $user_data;
		}

		/**
		 * Returns the cached wp users.
		 *
		 * @return array
		 *
		 * @since 4.5.0
		 */
		public static function get_wp_users() {
			return self::$wp_users;
		}

		/**
		 * Get event for WP-Admin bar.
		 *
		 * @param bool $from_db - Query from DB if set to true.
		 *
		 * @return WSAL_Models_Occurrence|bool
		 *
		 * @since 4.5.0
		 */
		public static function get_admin_bar_event( $from_db = false ) {
			// Get event from transient.
			$event_transient = 'wsal_admin_bar_event';
			$admin_bar_event = WP_Helper::get_transient( $event_transient );
			if ( false === $admin_bar_event || false !== $from_db ) {
				$event = self::get_latest_events( 1 );

				if ( $event ) {
					WP_Helper::set_transient( $event_transient, $event[0], 30 * MINUTE_IN_SECONDS );
					$admin_bar_event = $event[0];
				}
			}

			return $admin_bar_event;
		}

		/**
		 * Returns all supported alerts.
		 *
		 * @param bool $sorted – Sort the alerts array or not.
		 *
		 * @return array
		 *
		 * @since 4.5.0
		 */
		public static function get_categorized_alerts( $sorted = true ) {

			$result = array();

			foreach ( self::get_alerts() as $alert ) {
				if ( ! isset( $result[ \html_entity_decode( $alert['category'] ) ] ) ) {
					$result[ \html_entity_decode( $alert['category'] ) ] = array();
				}
				if ( ! isset( $result[ \html_entity_decode( $alert['category'] ) ][ \html_entity_decode( $alert['subcategory'] ) ] ) ) {
					$result[ \html_entity_decode( $alert['category'] ) ][ \html_entity_decode( $alert['subcategory'] ) ] = array();
				}
				$result[ \html_entity_decode( $alert['category'] ) ][ \html_entity_decode( $alert['subcategory'] ) ][] = $alert;
			}

			if ( $sorted ) {
				ksort( $result );
			}

			return $result;
		}

		/**
		 * Returns give alert property by its id
		 *
		 * @param int    $alert_id - The id of the alert.
		 * @param string $property - The property name.
		 *
		 * @return mixed
		 *
		 * @since 4.5.0
		 */
		public static function get_alert_property( $alert_id, $property ) {

			if ( isset( self::get_alerts()[ $alert_id ] ) && isset( self::get_alerts()[ $alert_id ][ $property ] ) ) {
				return self::get_alerts()[ $alert_id ][ $property ];
			}

			return false;
		}
	}
}
