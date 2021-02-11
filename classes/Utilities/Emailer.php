<?php
/**
 * Utility Class: Emailer.
 *
 * Utility class used for sending emails.
 *
 * @package Wsal
 * @since 3.3.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Emailer Utility Class
 *
 * This utility class is used to send emails from WSAL.
 */
class WSAL_Utilities_Emailer {

	/**
	 * Sends the plugin deactivation email template.
	 */
	public static function send_deactivation_email() {
		// Get the required variables.
		$wsal            = WpSecurityAuditLog::GetInstance();
		$home_url        = home_url();
		$safe_url        = str_replace( array( 'http://', 'https://' ), '', $home_url );
		$type_name       = $wsal->settings()->get_type_username(); // Get the data to display.
		$user            = _wp_get_current_user();
		$datetime_format = $wsal->settings()->GetDatetimeFormat( false );
		$now = current_time( 'timestamp' );
		$date_time       = str_replace(
			'$$$',
			substr( number_format( fmod( $now, 1 ), 3 ), 2 ),
			date( $datetime_format, $now )
		);

		// Checks for display name.
		$display_name = '';
		if ( $user && $user instanceof WP_User ) {
			if ( 'display_name' === $type_name && ! empty( $user->display_name ) ) {
				$display_name = $user->display_name;
			} elseif ( 'first_last_name' === $type_name && ( ! empty( $user->first_name ) || ! empty( $user->last_name ) ) ) {
				$display_name = $user->first_name . ' ' . $user->last_name;
			} else {
				$display_name = $user->user_login;
			}
		}

		/* Translators: %s: Home URL */
		$subject = sprintf( esc_html__( 'WP Activity Log plugin disabled on %s', 'wp-security-audit-log' ), $safe_url );

		// Set template body.
		$body  = esc_html__( 'Hello admin,', 'wp-security-audit-log' );
		$body .= '<br>';
		$body .= '<br>';

		/* Translators: 1. User display name, 2. Home URL, 3. Date and time */
		$body .= sprintf( esc_html__( 'This is a notification to let you know that the user %1$s has deactivated the plugin WP Activity Log on the website %2$s on %3$s.', 'wp-security-audit-log' ), $display_name, '<a href="' . $home_url . '" target="_blank">' . $safe_url . '</a>', $date_time );

		/**
		 * Get the email address to deliver the deactivation email to.
		 *
		 * Filterable and defaults to the admin email address.
		 *
		 * @since 3.5.2
		 *
		 * @var string
		 */
		$delivery_address = apply_filters( 'wsal_filter_deactivation_email_delivery_address', get_bloginfo( 'admin_email' ) );
		if ( filter_var( $delivery_address, FILTER_VALIDATE_EMAIL ) ) {
			// Send the email.
			self::send_email( $delivery_address, $subject, $body );
		}
	}

	/**
	 * Send Email.
	 *
	 * @param string $email_address - Email Address.
	 * @param string $subject       - Email subject.
	 * @param string $content       - Email content.
	 * @return bool
	 */
	public static function send_email( $email_address, $subject, $content, $headers = '', $attachments = array() ) {

		if ( empty( $email_address ) ) {
			// Get email addresses even when there is the Username.
			$email_address = self::get_emails( $email_address );
		}

		// @see: http://codex.wordpress.org/Function_Reference/wp_mail
		add_filter( 'wp_mail_content_type', array( __CLASS__, 'set_html_content_type' ) );
		add_filter( 'wp_mail_from', array( __CLASS__, 'custom_wp_mail_from' ) );
		add_filter( 'wp_mail_from_name', array( __CLASS__, 'custom_wp_mail_from_name' ) );

		$result = wp_mail( $email_address, $subject, $content, $headers, $attachments );

		/**
		 * Reset content-type to avoid conflicts.
		 *
		 * @see http://core.trac.wordpress.org/ticket/23578
		 */
		remove_filter( 'wp_mail_content_type', array( __CLASS__, 'set_html_content_type' ) );
		remove_filter( 'wp_mail_from', array( __CLASS__, 'custom_wp_mail_from' ) );
		remove_filter( 'wp_mail_from_name', array( __CLASS__, 'custom_wp_mail_from_name' ) );
		return $result;
	}

	/**
	 * Get email addresses by usernames.
	 *
	 * @param string $input_email - Comma separated emails.
	 * @return string
	 */
	public static function get_emails( $input_email ) {
		$emails_arr        = array();
		$input_email       = trim( $input_email );
		$email_or_username = explode( ',', $input_email );

		foreach ( $email_or_username as $token ) {
			$token = htmlspecialchars( stripslashes( trim( $token ) ) );

			// Check if e-mail address is well-formed.
			if ( ! is_email( $token ) ) {
				$user = get_user_by( 'login', $token );
				if ( $user && $user instanceof WP_User ) {
					array_push( $emails_arr, $user->user_email );
				}
			} else {
				array_push( $emails_arr, $token );
			}
		}
		return implode( ',', $emails_arr );
	}

	/**
	 * Filter the mail content type.
	 */
	public static function set_html_content_type() {
		return 'text/html';
	}

	/**
	 * Return if there is a from-email in the setting or the original passed.
	 *
	 * @param string $original_email_from – Original passed.
	 * @return string
	 */
	public static function custom_wp_mail_from( $original_email_from ) {
		$wsal       = WpSecurityAuditLog::GetInstance();
		$use_email  = $wsal->settings()->get_use_email();
		$email_from = $wsal->settings()->GetFromEmail();
		if ( ! empty( $email_from ) && 'custom_email' === $use_email ) {
			return $email_from;
		} else {
			return $original_email_from;
		}
	}

	/**
	 * Return if there is a display-name in the setting or the original passed.
	 *
	 * @param string $original_email_from_name – Original passed.
	 * @return string
	 */
	public static function custom_wp_mail_from_name( $original_email_from_name ) {
		$wsal            = WpSecurityAuditLog::GetInstance();
		$use_email       = $wsal->settings()->get_use_email();
		$email_from_name = $wsal->settings()->GetDisplayName();
		if ( ! empty( $email_from_name ) && 'custom_email' === $use_email ) {
			return $email_from_name;
		} else {
			return $original_email_from_name;
		}
	}
}
