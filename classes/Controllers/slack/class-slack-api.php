<?php
/**
 * Responsible for the all the API calls and responses from the Slack.
 *
 * @package    wsal
 * @subpackage slack
 *
 * @since 5.3.4
 *
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 *
 * @see       https://wordpress.org/plugins/wp-security-audit-log/
 */

declare(strict_types=1);

namespace WSAL\Controllers\Slack;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/*
 * Api class for slack
 */
if ( ! class_exists( '\WSAL\Controllers\Slack\Slack_API' ) ) {
	/**
	 * Responsible for communication with the Slack API.
	 *
	 * @since 5.3.4
	 */
	class Slack_API {

		/**
		 * Error message
		 *
		 * @var string
		 *
		 * @since 5.3.4
		 */
		public static $error = null;

		/**
		 * Response valid message
		 *
		 * @var string
		 *
		 * @since 5.3.4
		 */
		public static $valid_message = null;

		/**
		 * Send Slack message to a specific channel.
		 *
		 * @param string $bot_token - API Auth token to use.
		 * @param string $channel_name   - The name of the channel.
		 * @param string $text   - Text body to send.
		 *
		 * @since 5.3.4
		 */
		public static function send_slack_message_via_api( ?string $bot_token, string $channel_name, string $text ) {

			if ( empty( $bot_token ) ) {
				$bot_token = Slack::get_slack_auth_key();
			}

			$url  = 'https://slack.com/api/chat.postMessage';
			$data = array(
				'channel' => $channel_name,
				'text'    => $text,
			);

			$headers = array(
				'Content-Type'  => 'application/json; charset=utf-8',
				'Authorization' => 'Bearer ' . $bot_token,
			);

			$args = array(
				'method'  => 'POST',
				'headers' => $headers,
				'body'    => json_encode( $data ),
			);

			$response = wp_remote_post( $url, $args );

			if ( is_wp_error( $response ) ) {
				self::$error = $response->get_error_message();

				return false;
			} else {
				return true;
			}
		}

		/**
		 * Verify the Slack token.
		 *
		 * @param string $token - The token to verify.
		 *
		 * @return bool
		 *
		 * @since 5.3.4
		 */
		public static function verify_slack_token( $token ) {
			$url     = 'https://slack.com/api/auth.test';
			$headers = array(
				'Content-Type'  => 'application/x-www-form-urlencoded',
				'Authorization' => 'Bearer ' . $token,
			);

			$args = array(
				'method'  => 'POST',
				'headers' => $headers,
			);

			$response = wp_remote_post( $url, $args );

			if ( is_wp_error( $response ) ) {
				self::$error = $response->get_error_message();

				return false;
			} else {
				$response_data = json_decode( $response['body'], true );
				if ( $response_data['ok'] ) {
					self::$valid_message = $response_data;

					return true;
				} else {
					self::$error = $response_data['error'];

					return false;
				}
			}

			self::$error = 'Unknown error';

			return false;
		}

		/**
		 * Returns the error stored from Slack.
		 *
		 * @since 5.3.4
		 */
		public static function get_slack_error(): string {
			$error = self::$error;
			if ( \is_array( self::$error ) ) {
				$error = print_r( self::$error, true );
			}

			return (string) $error;
		}
	}
}
