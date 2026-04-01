<?php
/**
 * Responsible for the Twilio extension plugin settings
 *
 * @package    wsal
 * @subpackage twilio
 * @since 5.1.1
 * @copyright  2026 Melapress
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3 or higher
 * @link       https://wordpress.org/plugins/wp-security-audit-log/
 */

declare(strict_types=1);

namespace WSAL\Controllers\Twilio;

use WSAL\Views\Notifications;
use WSAL\Helpers\Credential_Settings_Helper;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Twilio settings class
 */
if ( ! class_exists( '\WSAL\Controllers\Twilio\Twilio' ) ) {

	/**
	 * Responsible for setting different 2FA Twilio settings
	 *
	 * @since 5.1.1
	 */
	class Twilio {

		public const SETTINGS_NAME = WSAL_PREFIX . 'twilio';

		public const NONCE_NAME = WSAL_PREFIX . 'twilio';

		public const POLICY_SETTINGS_NAME = 'enable_twilio';

		/**
		 * Twilio Account SID
		 *
		 * @var string
		 *
		 * @since 5.1.1
		 */
		protected static $sid_key = null;

		/**
		 * Twilio Account AUTH
		 *
		 * @var string
		 *
		 * @since 5.1.1
		 */
		protected static $auth_key = null;

		/**
		 * Twilio Account number / ID
		 *
		 * @var string
		 *
		 * @since 5.1.1
		 */
		protected static $number_id_key = null;

		/**
		 * All the extension settings and their values
		 *
		 * @var array
		 *
		 * @since 5.1.1
		 */
		private static $settings = null;

		/**
		 * Internal class cache for status of the twilio
		 *
		 * @var bool
		 *
		 * @since 5.2.0
		 */
		private static $is_set = null;

		/**
		 * Inits the class hooks
		 *
		 * @return void
		 *
		 * @since 5.1.1
		 */
		public static function init() {

			\register_setting(
				self::SETTINGS_NAME,
				self::SETTINGS_NAME,
				array( __CLASS__, 'validate_and_sanitize' )
			);

			// AJAX calls part.
			\add_action( 'wp_ajax_wsal_store_twilio_api_key', array( __CLASS__, 'store_twilio_api_key_ajax' ) );

			self::$settings = Notifications::get_global_notifications_setting();
		}

		/**
		 * Stores the Twilio Credentials key via AJAX request.
		 *
		 * Encrypts all three credential values before persisting them.
		 *
		 * @return void
		 *
		 * @since 5.1.1
		 * @since 5.6.2 - Encrypt credentials before saving.
		 */
		public static function store_twilio_api_key_ajax() {
			if ( \wp_doing_ajax() ) {
				if ( isset( $_POST['_wpnonce'] ) ) {
					$nonce_check = \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE_NAME );
					if ( ! $nonce_check ) {
						\wp_send_json_error( new \WP_Error( 500, \esc_html__( 'Nonce checking failed', 'wp-security-audit-log' ) ), 400 );
					}
				} else {
					\wp_send_json_error( new \WP_Error( 500, \esc_html__( 'Nonce is not provided', 'wp-security-audit-log' ) ), 400 );
				}
			} else {
				\wp_send_json_error( new \WP_Error( 500, \esc_html__( 'Not allowed', 'wp-security-audit-log' ) ), 400 );
			}

			if ( \current_user_can( 'manage_options' ) ) {

				$twilio_sid   = \sanitize_text_field( \wp_unslash( $_POST['twilio_sid'] ?? '' ) );
				$twilio_auth  = \sanitize_text_field( \wp_unslash( $_POST['twilio_auth'] ?? '' ) );
				$twilio_phone = \sanitize_text_field( \wp_unslash( $_POST['twilio_id'] ?? '' ) );
				$options      = Notifications::get_global_notifications_setting();

				$sid_masked   = Credential_Settings_Helper::is_submitted_credential_unchanged( $twilio_sid, $options['twilio_notification_account_sid'] ?? '' );
				$auth_masked  = Credential_Settings_Helper::is_submitted_credential_unchanged( $twilio_auth, $options['twilio_notification_auth_token'] ?? '' );
				$phone_masked = Credential_Settings_Helper::is_submitted_credential_unchanged( $twilio_phone, $options['twilio_notification_phone_number'] ?? '' );

				/**
				 * If all three fields are unchanged, the user changed nothing.
				 * Keep the existing DB values and return success immediately.
				 */
				if ( $sid_masked && $auth_masked && $phone_masked ) {
					\wp_send_json_success();
				}

				/**
				 * If some fields are masked and others are new, substitute the
				 * stored decrypted values for the masked fields so validation
				 * can run against a complete set of credentials.
				 */
				if ( $sid_masked ) {
					$twilio_sid = $options['twilio_notification_account_sid'] ?? '';
				}

				if ( $auth_masked ) {
					$twilio_auth = $options['twilio_notification_auth_token'] ?? '';
				}

				if ( $phone_masked ) {
					$twilio_phone = $options['twilio_notification_phone_number'] ?? '';
				}

				$result = Credential_Settings_Helper::validate_and_save_twilio(
					$twilio_sid,
					$twilio_auth,
					$twilio_phone,
					$options
				);

				if ( false !== $result ) {
					Notifications::set_global_notifications_setting( $result );
					\wp_send_json_success();
				}

				$twilio_cred_error_message = \__( 'Invalid Twilio credentials: No keys and numbers provided or the provided ones are invalid. Please check and provide the details again.', 'wp-security-audit-log' );

				$concat_twilio_error = \esc_html( $twilio_cred_error_message . Twilio_API::get_twilio_error() );

				\wp_send_json_error( $concat_twilio_error );
			}
			\wp_send_json_error( new \WP_Error( 500, \esc_html__( 'Not allowed', 'wp-security-audit-log' ) ), 400 );
		}

		/**
		 * Checks if the method is whole set.
		 *
		 * @return boolean
		 *
		 * @since 5.1.1
		 */
		public static function is_set() {
			if ( null === self::$is_set ) {
				self::$is_set = false;

				$key           = self::get_twilio_sid_key();
				$auth          = self::get_twilio_auth_key();
				$number_id_key = self::get_twilio_number_id_key();
				if ( $key && $auth && $number_id_key ) {

					if ( Twilio_API::check_credentials( $key, $auth, $number_id_key ) ) {
						self::$is_set = true;

						return true;
					}
				}
			}

			return self::$is_set;
		}

		/**
		 * Adds JS setting to the first time wizard set up page - adds Twilio key store key - the key can be stored from the wizard directly.
		 *
		 * @param array $settings - Array with the current wizard JS settings.
		 *
		 * @return array
		 *
		 * @since 5.1.1
		 */
		public static function js_wizard_settings( array $settings ): array {
			$settings['storeKey'] = true;

			return $settings;
		}

		/**
		 * Returns the Twilio stored settings
		 *
		 * @return array
		 *
		 * @since 5.1.1
		 */
		public static function get_settings(): array {
			if ( null === self::$settings ) {
				self::$settings = Notifications::get_global_notifications_setting();
			}

			return self::$settings;
		}

		/**
		 * Returns the currently stored Twilio SID key or false if there is none.
		 *
		 * PHP constant \WSAL_TWILIO_ACCOUNT_SID takes priority over the DB value.
		 *
		 * @return bool|string
		 *
		 * @since 5.1.1
		 * @since 5.6.2 - Added constant override and encrypted storage support.
		 */
		public static function get_twilio_sid_key() {
			if ( null === self::$sid_key ) {
				self::$sid_key = false;

				if ( defined( '\WSAL_TWILIO_ACCOUNT_SID' ) && '' !== \WSAL_TWILIO_ACCOUNT_SID ) {
					self::$sid_key = \WSAL_TWILIO_ACCOUNT_SID;

					return self::$sid_key;
				}

				if ( isset( self::get_settings()['twilio_notification_account_sid'] ) ) {
					self::$sid_key = self::get_settings()['twilio_notification_account_sid'];
				}
			}

			return self::$sid_key;
		}

		/**
		 * Returns the currently stored Twilio AUTH key or false if there is none.
		 *
		 * PHP constant \WSAL_TWILIO_AUTH_TOKEN takes priority over the DB value.
		 *
		 * @return bool|string
		 *
		 * @since 5.1.1
		 * @since 5.6.2 - Added constant override and encrypted storage support.
		 */
		public static function get_twilio_auth_key() {
			if ( null === self::$auth_key ) {
				self::$auth_key = false;

				if ( defined( '\WSAL_TWILIO_AUTH_TOKEN' ) && '' !== \WSAL_TWILIO_AUTH_TOKEN ) {
					self::$auth_key = \WSAL_TWILIO_AUTH_TOKEN;

					return self::$auth_key;
				}

				if ( isset( self::get_settings()['twilio_notification_auth_token'] ) ) {
					self::$auth_key = self::get_settings()['twilio_notification_auth_token'];
				}
			}

			return self::$auth_key;
		}

		/**
		 * Returns the currently stored Twilio phone number / alphanumeric ID or false if there is none.
		 *
		 * PHP constant \WSAL_TWILIO_PHONE_NUMBER takes priority over the DB value.
		 *
		 * @return bool|string
		 *
		 * @since 5.1.1
		 * @since 5.6.2 - Added constant override and encrypted storage support.
		 */
		public static function get_twilio_number_id_key() {
			if ( null === self::$number_id_key ) {
				self::$number_id_key = false;

				if ( defined( '\WSAL_TWILIO_PHONE_NUMBER' ) && '' !== \WSAL_TWILIO_PHONE_NUMBER ) {
					self::$number_id_key = \WSAL_TWILIO_PHONE_NUMBER;

					return self::$number_id_key;
				}

				if ( isset( self::get_settings()['twilio_notification_phone_number'] ) ) {
					self::$number_id_key = self::$settings['twilio_notification_phone_number'];
				}
			}

			return self::$number_id_key;
		}

		/**
		 * Extracts the setting value
		 *
		 * @param string $setting - The name of the setting which value needs to be extracted.
		 *
		 * @return mixed
		 *
		 * @since 5.1.1
		 */
		public static function get_twilio_setting( string $setting ) {
			if ( ! isset( $setting ) ) {
				return '';
			}

			if ( isset( self::get_settings()[ $setting ] ) ) {
				return self::get_settings()[ $setting ];
			}

			return '';
		}
	}
}
