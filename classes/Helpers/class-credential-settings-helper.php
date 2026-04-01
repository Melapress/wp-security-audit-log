<?php
/**
 * Manages credential-specific encryption settings and operations.
 *
 * @package    wsal
 * @subpackage helpers
 *
 * @since 5.6.2
 *
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 *
 * @see        https://wordpress.org/plugins/wp-security-audit-log/
 */

declare(strict_types=1);

namespace WSAL\Helpers;

use WSAL\Controllers\Slack\Slack_API;
use WSAL\Controllers\Twilio\Twilio_API;

defined( 'ABSPATH' ) || exit;

/**
 * Credential settings helper class.
 *
 * @since 5.6.2
 */
if ( ! class_exists( '\WSAL\Helpers\Credential_Settings_Helper' ) ) {

	/**
	 * Bridges Encryption_Helper with the plugin notification settings.
	 *
	 * Knows which settings are encrypted credentials, how to validate
	 * them against third-party APIs, and how to save them securely.
	 *
	 * @since 5.6.2
	 */
	class Credential_Settings_Helper {

		/**
		 * Returns the setting keys that store encrypted credentials.
		 *
		 * @return array
		 *
		 * @since 5.6.2
		 */
		public static function get_encrypted_setting_keys(): array {
			return array(
				'twilio_notification_account_sid',
				'twilio_notification_auth_token',
				'twilio_notification_phone_number',
				'slack_notification_auth_token',
				'notification_bitly_shorten_key',
			);
		}

		/**
		 * Returns the mapping of setting keys to their PHP constant overrides.
		 *
		 * @return array
		 *
		 * @since 5.6.2
		 */
		public static function get_constant_overrides(): array {
			return array(
				'twilio_notification_account_sid'  => 'WSAL_TWILIO_ACCOUNT_SID',
				'twilio_notification_auth_token'   => 'WSAL_TWILIO_AUTH_TOKEN',
				'twilio_notification_phone_number' => 'WSAL_TWILIO_PHONE_NUMBER',
				'slack_notification_auth_token'    => 'WSAL_SLACK_AUTH_TOKEN',
			);
		}

		/**
		 * Checks if a given setting key has an active PHP constant override.
		 *
		 * @param string $setting_key - The setting key to check.
		 *
		 * @return bool
		 *
		 * @since 5.6.2
		 */
		public static function has_active_constant( string $setting_key ): bool {
			$overrides = self::get_constant_overrides();

			if ( ! isset( $overrides[ $setting_key ] ) ) {
				return false;
			}

			// Is constant defined and with a value?
			$is_constant_defined = defined( $overrides[ $setting_key ] ) && '' !== constant( $overrides[ $setting_key ] );

			return $is_constant_defined;
		}

		/**
		 * Returns the alert text for a credential setting that has an active PHP constant override
		 *
		 * @param string $setting_key - The setting key to check.
		 *
		 * @return string - The alert text, or an empty string when no override is active.
		 *
		 * @since 5.6.2
		 */
		public static function get_constant_alert_text( string $setting_key ): string {
			if ( ! self::has_active_constant( $setting_key ) ) {
				return '';
			}

			$overrides     = self::get_constant_overrides();
			$constant_name = $overrides[ $setting_key ];

			return sprintf(
				/* translators: %s: PHP constant name (e.g. WSAL_TWILIO_ACCOUNT_SID). */
				\esc_html__( 'This value is currently defined via the %s PHP constant and takes priority over the value saved in the database.', 'wp-security-audit-log' ),
				$constant_name
			);
		}

		/**
		 * Validates Twilio credentials and encrypts them into the options array.
		 *
		 * @param string $sid - The Twilio Account SID.
		 * @param string $auth - The Twilio Auth Token.
		 * @param string $phone - The Twilio phone number or alphanumeric ID.
		 * @param array  $options - The current options array to merge into.
		 *
		 * @return array|false - Updated options array on success, false if validation or encryption fails.
		 *
		 * @since 5.6.2
		 */
		public static function validate_and_save_twilio( string $sid, string $auth, string $phone, array $options = array() ) {
			if ( '' === $sid || '' === $auth || '' === $phone ) {
				return false;
			}

			if ( ! Twilio_API::check_credentials( $sid, $auth, $phone ) ) {
				return false;
			}

			$options['twilio_notification_account_sid']  = Encryption_Helper::encrypt( $sid );
			$options['twilio_notification_auth_token']   = Encryption_Helper::encrypt( $auth );
			$options['twilio_notification_phone_number'] = Encryption_Helper::encrypt( $phone );

			if ( '' === $options['twilio_notification_account_sid'] || '' === $options['twilio_notification_auth_token'] || '' === $options['twilio_notification_phone_number'] ) {
				return false;
			}

			return $options;
		}

		/**
		 * Validates a Slack token and encrypts it into the options array.
		 *
		 * @param string $token - The Slack Bot token.
		 * @param array  $options - The current options array to merge into.
		 *
		 * @return array|false - Updated options array on success, false if validation or encryption fails.
		 *
		 * @since 5.6.2
		 */
		public static function validate_and_save_slack( string $token, array $options = array() ) {
			if ( '' === $token ) {
				return false;
			}

			if ( ! Slack_API::verify_slack_token( $token ) ) {
				return false;
			}

			$encrypted_token = Encryption_Helper::encrypt( $token );

			if ( '' === $encrypted_token ) {
				return false;
			}

			$options['slack_notification_auth_token'] = $encrypted_token;

			return $options;
		}

		/**
		 * Checks if a submitted credential value matches the masked form of the currently stored value.
		 *
		 * Supports both raw encrypted DB values and already decrypted values.
		 *
		 * @param string $submitted_value - The submitted form value.
		 * @param string $current_value - The current stored credential value.
		 *
		 * @return bool
		 *
		 * @since 5.6.2
		 */
		public static function is_submitted_credential_unchanged( string $submitted_value, string $current_value ): bool {
			if ( '' === $submitted_value || '' === $current_value ) {
				return false;
			}

			$decrypted_string = Encryption_Helper::maybe_decrypt( $current_value );

			$is_credendial_unchanged = Encryption_Helper::mask( $decrypted_string ) === $submitted_value;

			return $is_credendial_unchanged;
		}

		/**
		 * Returns the existing encrypted DB values for credentials that were not changed by the user (submitted as masked values).
		 *
		 * @param array $post_array - The submitted form data.
		 * @param array $current_raw_settings - The raw (encrypted) settings from the DB.
		 *
		 * @return array - The encrypted values to preserve for unchanged credentials.
		 *
		 * @since 5.6.2
		 */
		public static function get_encrypted_unchanged_credentials( array $post_array, array $current_raw_settings ): array {
			$options         = array();
			$credential_keys = self::get_encrypted_setting_keys();

			foreach ( $credential_keys as $credential_key ) {
				if ( ! isset( $post_array[ $credential_key ] ) || ! isset( $current_raw_settings[ $credential_key ] )
				) {
					continue;
				}

				$is_credential_key_unchanged = self::is_submitted_credential_unchanged( $post_array[ $credential_key ], $current_raw_settings[ $credential_key ] );

				if ( $is_credential_key_unchanged ) {
					$options[ $credential_key ] = $current_raw_settings[ $credential_key ];
				}
			}

			return $options;
		}

		/**
		 * Empties masked credential values in the submitted form array so downstream validation skips them naturally.
		 *
		 * @param array $post_array - The submitted form data.
		 * @param array $current_raw_settings - The raw (encrypted) settings from the DB.
		 *
		 * @return array - The post array with unchanged credential keys set to empty strings.
		 *
		 * @since 5.6.2
		 */
		public static function empty_masked_values_from_post_data( array $post_array, array $current_raw_settings ): array {
			$credential_keys = self::get_encrypted_setting_keys();

			foreach ( $credential_keys as $credential_key ) {
				if ( ! isset( $post_array[ $credential_key ] ) || ! isset( $current_raw_settings[ $credential_key ] )
				) {
					continue;
				}

				$is_credential_key_unchanged = self::is_submitted_credential_unchanged( $post_array[ $credential_key ], $current_raw_settings[ $credential_key ] );

				if ( $is_credential_key_unchanged ) {
					$post_array[ $credential_key ] = '';
				}
			}

			return $post_array;
		}

		/**
		 * Decrypt all encrypted credential values in a settings array and establish if migration from plaintext is needed.
		 *
		 * @param array $settings - The settings array.
		 *
		 * @return array {
		 *     @type array $settings       The settings array with credentials decrypted.
		 *     @type bool  $needs_migration Whether any values failed decryption (likely legacy plaintext).
		 * }
		 *
		 * @since 5.6.2
		 */
		public static function decrypt_credentials( array $settings ): array {
			$needs_migration = false;
			$encrypted_keys  = self::get_encrypted_setting_keys();

			foreach ( $encrypted_keys as $key ) {
				if ( isset( $settings[ $key ] ) && is_string( $settings[ $key ] ) && '' !== $settings[ $key ] ) {
					$decrypted = Encryption_Helper::decrypt( $settings[ $key ] );

					if ( false !== $decrypted ) {
						$settings[ $key ] = $decrypted;
					} else {
						$needs_migration = true;
					}
				}
			}

			return array(
				'settings'        => $settings,
				'needs_migration' => $needs_migration,
			);
		}

		/**
		 * Ensure all credential values in a settings array are encrypted before DB write.
		 *
		 * Skips values that are already encrypted payloads.
		 *
		 * @param array $settings - The settings array.
		 *
		 * @return array - The settings array with credential values encrypted as needed.
		 *
		 * @since 5.6.2
		 */
		public static function encrypt_credentials( array $settings ): array {
			$encrypted_keys = self::get_encrypted_setting_keys();

			foreach ( $encrypted_keys as $key ) {
				if ( isset( $settings[ $key ] ) && is_string( $settings[ $key ] ) && '' !== $settings[ $key ] ) {
					if ( Encryption_Helper::is_encrypted_payload( $settings[ $key ] ) ) {
						continue;
					}

					$encrypted_value = Encryption_Helper::encrypt( $settings[ $key ] );

					if ( '' !== $encrypted_value ) {
						$settings[ $key ] = $encrypted_value;
					}
				}
			}

			return $settings;
		}

		/**
		 * Migrate legacy plaintext credentials to encrypted storage.
		 *
		 * Encrypts any plaintext credential fields found in the given settings
		 * array and returns the updated array. The caller is responsible for
		 * persisting the result.
		 *
		 * @param array $settings - The settings array to migrate.
		 *
		 * @return array The settings array with plaintext credentials encrypted.
		 *
		 * @since 5.6.2
		 */
		public static function migrate_plaintext_credentials( array $settings ): array {
			$keys = self::get_encrypted_setting_keys();

			foreach ( $keys as $key ) {
				if ( isset( $settings[ $key ] ) && is_string( $settings[ $key ] ) && '' !== $settings[ $key ] ) {
					/**
					 * Skip values that look like encrypted payloads but fail to decrypt
					 * (e.g. after salt rotation). Re-encrypting those would permanently
					 * lose the credential. Only encrypt genuine plaintext values.
					 */
					if ( Encryption_Helper::is_encrypted_payload( $settings[ $key ] ) ) {
						continue;
					}

					$encrypted_value = Encryption_Helper::encrypt( \sanitize_text_field( $settings[ $key ] ) );

					if ( '' !== $encrypted_value ) {
						$settings[ $key ] = $encrypted_value;
					}
				}
			}

			return $settings;
		}
	}
}
