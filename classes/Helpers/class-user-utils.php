<?php
/**
 * Responsible for the User's operations
 *
 * @package    wsal
 * @subpackage helpers
 * @since      4.6.0
 * @copyright  2024 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WSAL\Helpers;

use WSAL\Helpers\Settings_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * User's utils class
 */
if ( ! class_exists( '\WSAL\Helpers\User_Utils' ) ) {
	/**
	 * Utility class for some user related stuff.
	 *
	 * @package wsal
	 * @since 4.6.0
	 */
	class User_Utils {

		/**
		 * Local user cache. Keys are usernames and values are user IDs.
		 *
		 * @var array
		 *
		 * @since 4.6.0
		 */
		private static $cached_users = array();

		/**
		 * Local static cache for the value of setting determining the preferred user data to display as label.
		 *
		 * @var string
		 *
		 * @since 4.6.0
		 */
		private static $user_label_setting;

		/**
		 * Build the correct label to display for a given user.
		 *
		 * @param WP_User $user   WordPress user object.
		 *
		 * @return string
		 *
		 * @since 4.6.0
		 */
		public static function get_display_label( $user ): string {
			if ( ! isset( self::$user_label_setting ) ) {
				self::$user_label_setting = Settings_Helper::get_option_value( 'type_username', 'display_name' );
			}

			if ( 'display_name' === self::$user_label_setting && ! empty( $user->display_name ) ) {
				return $user->display_name;
			}

			if ( 'first_last_name' === self::$user_label_setting && ( ! empty( $user->first_name ) || ! empty( $user->last_name ) ) ) {
				return trim(
					implode(
						' ',
						array(
							$user->first_name,
							$user->last_name,
						)
					)
				);
			}

			return $user->user_login;
		}

		/**
		 * Gets the username.
		 *
		 * @param array $meta - Event meta data.
		 *
		 * @return string User's username.
		 *
		 * @since 4.6.0
		 */
		public static function get_username( $meta = null ) {
			if ( ! is_array( $meta ) ) {
				return '';
			}

			if ( isset( $meta['Username'] ) ) {
				return $meta['Username'];
			} elseif ( isset( $meta['CurrentUserID'] ) ) {
				$data = \get_userdata( $meta['CurrentUserID'] );

				return $data ? $data->user_login : '';
			}

			return '';
		}

		/**
		 * Get the user details for the tooltip.
		 *
		 * @param WP_User $user - User data.
		 *
		 * @return string HTML representing a tooltip with user's details.
		 *
		 * @since 4.6.0
		 */
		public static function get_tooltip_user_content( $user ) {

			if ( ! isset( $user->ID ) ) {
				return '';
			}

			$tooltip  = '<strong>' . esc_attr__( 'Username: ', 'wp-security-audit-log' ) . '</strong>' . $user->data->user_login . '</br>';
			$tooltip .= ( ! empty( $user->data->first_name ) ) ? '<strong>' . esc_attr__( 'First name: ', 'wp-security-audit-log' ) . '</strong>' . $user->data->first_name . '</br>' : '';
			$tooltip .= ( ! empty( $user->data->last_name ) ) ? '<strong>' . esc_attr__( 'Last Name: ', 'wp-security-audit-log' ) . '</strong>' . $user->data->last_name . '</br>' : '';
			$tooltip .= '<strong>' . esc_attr__( 'Email: ', 'wp-security-audit-log' ) . '</strong>' . $user->data->user_email . '</br>';
			$tooltip .= '<strong>' . esc_attr__( 'Nickname: ', 'wp-security-audit-log' ) . '</strong>' . $user->data->user_nicename . '</br></br>';

			/**
			 * WSAL Filter: `wsal_additional_user_tooltip_content'
			 *
			 * Allows 3rd parties to append HTML to the user tooltip content in audit log viewer.
			 *
			 * @since 4.4.0
			 *
			 * @param string $content Blank string to append to.
			 * @param object  $user  - User object.
			 */
			$additional_content = apply_filters( 'wsal_additional_user_tooltip_content', '', $user );

			$tooltip .= $additional_content;

			return $tooltip;
		}

		/**
		 * Retrieves user ID using either the username of user ID.
		 *
		 * @param int|string $user_login User login or ID.
		 *
		 * @return int|null
		 *
		 * @since 4.6.0
		 */
		public static function swap_login_for_id( $user_login ) {

			if ( isset( self::$cached_users[ $user_login ] ) ) {
				return self::$cached_users[ $user_login ];
			}

			global $wpdb;
			$user_id = $wpdb->get_var( // phpcs:ignore
				$wpdb->prepare(
					"SELECT ID FROM $wpdb->users WHERE user_login = %s OR ID = %d;",
					$user_login,
					$user_login
				)
			);

			if ( false === $user_id || 0 === strlen( $user_id ) ) {
				return null;

			}

			self::$cached_users[ $user_login ] = intval( $user_id );

			return self::$cached_users[ $user_login ];
		}

		/**
		 * Populates the label showing user roles in audit log, sessions list, etc.
		 *
		 * @param string|string[] $roles User roles.
		 *
		 * @return string
		 *
		 * @since 4.6.0
		 */
		public static function get_roles_label( $roles ): string {
			global $wp_roles;
			$role_names = array();

			if ( is_array( $roles ) && count( $roles ) ) {
				foreach ( $roles as $index => $role_slug ) {
					$role_names[ $index ] = ( isset( $wp_roles->roles[ $role_slug ] ) ) ? translate_user_role( $wp_roles->roles[ $role_slug ]['name'] ) : ucwords( $role_slug );
				}
				return esc_html( implode( ', ', $role_names ) );
			}

			if ( is_string( $roles ) && '' !== $roles ) {
				$roles     = trim( str_replace( array( '"', '[', ']' ), ' ', $roles ) );
				$role_name = ( isset( $wp_roles->roles[ $roles ] ) ) ? translate_user_role( $wp_roles->roles[ $roles ]['name'] ) : ucwords( $roles );
				return esc_html( $role_name );
			}

			return '<i>' . esc_html__( 'Unknown', 'wp-security-audit-log' ) . '</i>';
		}
	}
}
