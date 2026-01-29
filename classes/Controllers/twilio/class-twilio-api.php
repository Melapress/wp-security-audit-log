<?php
/**
 * Responsible for the all the API calls and responses from the Twilio.
 *
 * @package    wsal
 * @subpackage twilio
 *
 * @since 5.1.1
 *
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 *
 * @see       https://wordpress.org/plugins/wp-security-audit-log/
 */

declare(strict_types=1);

namespace WSAL\Controllers\Twilio;

use WSAL\Helpers\Logger;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/*
 * Wizard steps twilio class
 */
if ( ! class_exists( '\WSAL\Controllers\Twilio\Twilio_API' ) ) {
	/**
	 * Responsible for communication with the Twilio API.
	 *
	 * @since 5.1.1
	 */
	class Twilio_API {

		public const CHECK_PHONE_NUMBER_API_URL = 'https://api.twilio.com/2010-04-01/Accounts/%s/IncomingPhoneNumbers.json?PhoneNumber=%s';

		public const SEND_SMS_API_URL = 'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json';

		/**
		 * Keeps the Error Message from Twilio.
		 *
		 * @var string
		 *
		 * @since 5.1.1
		 */
		private static $twilio_error_message = '';

		/**
		 * Send SMS to a specific number.
		 *
		 * @param string $to_number - Phone number to which SMS should be sent.
		 * @param string $message   - SMS body.
		 *
		 * @since 5.1.1
		 */
		public static function send_sms( string $to_number, string $message ): bool {
			self::$twilio_error_message = '';
			if ( $to_number && $message && Twilio::get_twilio_sid_key() && Twilio::get_twilio_auth_key() ) {

				try {

					$response = \wp_remote_post(
						\sprintf(
							self::SEND_SMS_API_URL,
							Twilio::get_twilio_sid_key(),
						),
						array(
							'body'    =>
								array(
									'To'   => $to_number,
									'Body' => $message,
									'From' => Twilio::get_twilio_number_id_key(),

								),
							'headers' => array(
								'Content-Type'  => 'application/x-www-form-urlencoded',
								'Authorization' => 'Basic ' . base64_encode( Twilio::get_twilio_sid_key() . ':' . Twilio::get_twilio_auth_key() ),
							),
						)
					);

					$res = json_decode( \wp_remote_retrieve_body( $response ), true );

					self::$twilio_error_message = isset( $res['message'] ) ? $res['message'] : ( isset( $res['error_message'] ) ? $res['error_message'] : '' );

					if ( 200 < (int) $res['status'] && isset( self::$twilio_error_message ) && ! empty( self::$twilio_error_message ) ) {
						Logger::log( self::$twilio_error_message );
						return false;
					}
					if ( ( false !== ( filter_var( $res['status'], FILTER_VALIDATE_INT ) ) ) ) {
						return false;
					}

					return true;

				} catch ( \Exception $e ) {
					self::$twilio_error_message = $e->getMessage();

					return false;
				}
			}
			self::$twilio_error_message = esc_html__( 'Twilio is not set correctly', 'wp-security-audit-log' );

			return false;
		}

		/**
		 * Checks given credentials against the Twilio API.
		 *
		 * @param string $sid   - The Twilio SID key.
		 * @param string $auth  - The Twilio Authentication token.
		 * @param string $phone - The Twilio Incoming phone.
		 *
		 * @since 5.1.1
		 */
		public static function check_credentials( string $sid, string $auth, string $phone ): bool {
			self::$twilio_error_message = '';

			try {

				$response = \wp_remote_get(
					\sprintf(
						self::CHECK_PHONE_NUMBER_API_URL,
						$sid,
						$phone
					),
					array(
						'headers' => array(
							'Content-Type'  => 'application/x-www-form-urlencoded',
							'Authorization' => 'Basic ' . base64_encode( $sid . ':' . $auth ),
						),
					)
				);

				$res = json_decode( \wp_remote_retrieve_body( $response ), true );

				if ( isset( $res['incoming_phone_numbers'][0]['sid'] ) && isset( $res['incoming_phone_numbers'][0]['status'] ) && 'in-use' === $res['incoming_phone_numbers'][0]['status'] ) {
					return true;
				} else {
					self::$twilio_error_message = esc_html__( 'Twilio is not set correctly', 'wp-security-audit-log' );

					if ( isset( $res['code'] ) ) {
						self::$twilio_error_message .= ' ' . esc_html__( 'Code from Twilio:', 'wp-security-audit-log' ) . ' ' . $res['code'];
					}

					if ( isset( $res['more_info'] ) ) {
						self::$twilio_error_message .= ' ' . esc_html__( 'More info form Twilio:', 'wp-security-audit-log' ) . ' ' . $res['more_info'];
					}

					return false;
				}
			} catch ( \Exception $e ) {
				self::$twilio_error_message = $e->getMessage();

				return false;
			}
		}

		/**
		 * Returns the error stored from Twilio.
		 *
		 * @since 5.1.1
		 */
		public static function get_twilio_error(): string {
			return self::$twilio_error_message;
		}
	}
}
