<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Utility class for some user related stuff.
 *
 * @package Wsal
 * @since latest
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
	 * @since latest
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
}