<?php

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
	 * Local static cache for the value of setting determining the prefered user data to display as label.
	 *
	 * @var string
	 */
	private static $user_label_setting;

	/**
	 * @param WpSecurityAuditLog $plugin Instance of WpSecurityAuditLog.
	 * @param WP_User $user WordPress user object.
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
			return trim( implode( ' ', [
				$user->first_name,
				$user->last_name
			] ) );
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
	public static function GetUsername( $meta = null ) {
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
	 * @since latest.
	 */
	public static function get_tooltip_user_content( $user ) {

		if ( ! isset( $user->ID ) ) {
			return '';
		}

		$tooltip = '<strong>' . esc_attr__( 'Username: ', 'wp-security-audit-log' ) . '</strong>' . $user->data->user_login . '</br>';
		$tooltip .= ( ! empty( $user->data->first_name ) ) ? '<strong>' . esc_attr__( 'First name: ', 'wp-security-audit-log' ) . '</strong>' . $user->data->first_name . '</br>' : '';
		$tooltip .= ( ! empty( $user->data->first_name ) ) ? '<strong>' . esc_attr__( 'Last Name: ', 'wp-security-audit-log' ) . '</strong>' . $user->data->first_name . '</br>' : '';
		$tooltip .= '<strong>' . esc_attr__( 'Email: ', 'wp-security-audit-log' ) . '</strong>' . $user->data->user_email . '</br>';
		$tooltip .= '<strong>' . esc_attr__( 'Nickname: ', 'wp-security-audit-log' ) . '</strong>' . $user->data->user_nicename . '</br></br>';

		return $tooltip;
	}
}