<?php
/**
 * User utility class.
 *
 * @package wsal
 * @since 4.3.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Utility class for some user related stuff.
 *
 * @package wsal
 * @since 4.3.0
 */
class WSAL_Utilities_UsersUtils {

	/**
	 * Local user cache. Keys are usernames and values are user IDs.
	 *
	 * @var array
	 */
	private static $cached_users = array();

	/**
	 * Local static cache for the value of setting determining the preferred user data to display as label.
	 *
	 * @var string
	 */
	private static $user_label_setting;

	/**
	 * Build the correct label to display for a given user.
	 *
	 * @param WpSecurityAuditLog $plugin Instance of WpSecurityAuditLog.
	 * @param WP_User            $user   WordPress user object.
	 *
	 * @return string
	 * @since 4.3.0
	 */
	public static function get_display_label( $plugin, $user ) {
		if ( ! isset( self::$user_label_setting ) ) {
			self::$user_label_setting = $plugin->settings()->get_type_username();
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
	 * @since 4.3.1 Made the meta attribute mandatory, changed to static and moved from occurrence to alert.
	 */
	public static function get_username( $meta = null ) {
		if ( ! is_array( $meta ) ) {
			return '';
		}

		if ( isset( $meta['Username'] ) ) {
			return $meta['Username'];
		} elseif ( isset( $meta['CurrentUserID'] ) ) {
			$data = get_userdata( $meta['CurrentUserID'] );

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
	 * @since 4.3.4.
	 */
	public static function get_tooltip_user_content( $user ) {

		if ( ! isset( $user->ID ) ) {
			return '';
		}

		$tooltip  = '<strong>' . esc_attr__( 'Username: ', 'wp-security-audit-log' ) . '</strong>' . $user->data->user_login . '</br>';
		$tooltip .= ( ! empty( $user->data->first_name ) ) ? '<strong>' . esc_attr__( 'First name: ', 'wp-security-audit-log' ) . '</strong>' . $user->data->first_name . '</br>' : '';
		$tooltip .= ( ! empty( $user->data->first_name ) ) ? '<strong>' . esc_attr__( 'Last Name: ', 'wp-security-audit-log' ) . '</strong>' . $user->data->first_name . '</br>' : '';
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
	 * @since 4.3.4
	 */
	public static function get_roles_label( $roles ) {
		if ( is_array( $roles ) && count( $roles ) ) {
			return esc_html( ucwords( implode( ', ', $roles ) ) );
		}

		if ( is_string( $roles ) && '' !== $roles ) {
			return esc_html( ucwords( str_replace( array( '"', '[', ']' ), ' ', $roles ) ) );
		}

		return '<i>' . esc_html__( 'Unknown', 'wp-security-audit-log' ) . '</i>';
	}
}
