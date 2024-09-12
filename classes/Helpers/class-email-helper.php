<?php
/**
 * Utility Class: Emailer.
 *
 * Utility class used for sending emails.
 *
 * @package wsal
 * @since 3.3.1
 */

declare(strict_types=1);

namespace WSAL\Helpers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Helpers\Email_Helper' ) ) {
	/**
	 * Emailer Utility Class
	 *
	 * This utility class is used to send emails from WSAL.
	 *
	 * @since 5.0.0
	 */
	class Email_Helper {

		/**
		 * Sends the plugin deactivation email template.
		 *
		 * @since 5.0.0
		 */
		public static function send_deactivation_email() {
			// Get the required variables.
			$home_url        = home_url();
			$safe_url        = str_replace( array( 'http://', 'https://' ), '', $home_url );
			$type_name       = Settings_Helper::get_option_value( 'type_username', 'display_name' ); // Get the data to display.
			$user            = User_Helper::get_current_user();
			$datetime_format = Settings_Helper::get_datetime_format( false );
			$now             = current_time( 'timestamp' ); // phpcs:ignore
			$date_time       = str_replace(
				'$$$',
				substr( number_format( fmod( $now, 1 ), 3 ), 2 ),
			date( $datetime_format, $now ) // phpcs:ignore
			);

			// Checks for display name.
			$display_name = '';
			if ( $user && $user instanceof \WP_User ) {
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
		 * @param string $headers       Email headers.
		 * @param array  $attachments   Email attachments.
		 *
		 * @return bool
		 *
		 * @since 5.0.0
		 */
		public static function send_email( $email_address, $subject, $content, $headers = '', $attachments = array() ) {

			if ( empty( $email_address ) ) {
				// Get email addresses even when there is the Username.
				$email_address = self::get_emails( $email_address );
			}

			if ( ! empty( $headers ) ) {
				$headers = array_merge_recursive( (array) $headers, array( 'Content-Type: ' . self::set_html_content_type() . '; charset=UTF-8' ) );
			} else {
				$headers = array( 'Content-Type: ' . self::set_html_content_type() . '; charset=UTF-8' );
			}

			// @see: http://codex.wordpress.org/Function_Reference/wp_mail
			\add_filter( 'wp_mail_from', array( __CLASS__, 'custom_wp_mail_from' ), PHP_INT_MAX );
			\add_filter( 'wp_mail_from_name', array( __CLASS__, 'custom_wp_mail_from_name' ) );

			$result = \wp_mail( $email_address, $subject, $content, $headers, $attachments );

			/**
			 * Reset content-type to avoid conflicts.
			 *
			 * @see http://core.trac.wordpress.org/ticket/23578
			 */
			\remove_filter( 'wp_mail_from', array( __CLASS__, 'custom_wp_mail_from' ), PHP_INT_MAX );
			\remove_filter( 'wp_mail_from_name', array( __CLASS__, 'custom_wp_mail_from_name' ) );

			return $result;
		}

		/**
		 * Get email addresses by usernames.
		 *
		 * @param string $input_email - Comma separated emails.
		 *
		 * @return string
		 *
		 * @since 5.0.0
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
					if ( $user && $user instanceof \WP_User ) {
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
		 *
		 * @since 5.0.0
		 */
		public static function set_html_content_type() {
			return 'text/html';
		}

		/**
		 * Return if there is a from-email in the setting or the original passed.
		 *
		 * @param string $original_email_from – Original passed.
		 *
		 * @return string
		 *
		 * @since 5.0.0
		 */
		public static function custom_wp_mail_from( $original_email_from ) {
			$use_email  = Settings_Helper::get_option_value( 'use-email', 'default_email' );
			$email_from = Settings_Helper::get_option_value( 'from-email' );
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
		 *
		 * @return string
		 *
		 * @since 5.0.0
		 */
		public static function custom_wp_mail_from_name( $original_email_from_name ) {
			$use_email       = Settings_Helper::get_option_value( 'use-email', 'default_email' );
			$email_from_name = Settings_Helper::get_option_value( 'display-name' );
			if ( ! empty( $email_from_name ) && 'custom_email' === $use_email ) {
				return $email_from_name;
			} else {
				if ( ! empty( self::get_default_email_address() ) ) {
					return self::get_default_email_address();
				}

				return $original_email_from_name;
			}
		}

		/**
		 * Builds and returns the default email address used for the "from" email address when email is send
		 *
		 * @return string
		 *
		 * @since 5.0.0
		 */
		public static function get_default_email_address(): string {
			$sitename = \wp_parse_url( \network_home_url(), PHP_URL_HOST );

			$from_email = '';

			if ( null !== $sitename ) {
				$from_email = 'wsal@';
				if ( \str_starts_with( $sitename, 'www.' ) ) {
					$sitename = substr( $sitename, 4 );
				}

				$from_email .= $sitename;
			}

			return $from_email;
		}
	}
}
