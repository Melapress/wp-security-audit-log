<?php
/**
 * Generic encryption helper using AES-256-GCM authenticated encryption.
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

defined( 'ABSPATH' ) || exit;

/**
 * Encryption helper class.
 *
 * @since 5.6.2
 */
if ( ! class_exists( '\WSAL\Helpers\Encryption_Helper' ) ) {

	/**
	 * Provides generic encrypt / decrypt using AES-256-GCM.
	 *
	 * Uses a versioned payload format so future algorithm changes can be
	 * handled without guesswork. Current version (v1) uses AES-256-GCM.
	 *
	 * @since 5.6.2
	 */
	class Encryption_Helper {

		/**
		 * GCM cipher algorithm.
		 *
		 * @var string
		 */
		private const CIPHER_GCM = 'aes-256-gcm';

		/**
		 * Current payload version identifier.
		 *
		 * @var string
		 */
		private const ENCRYPTION_VERSION = 'v1';

		/**
		 * GCM authentication tag length in bytes.
		 *
		 * @var int
		 */
		private const GCM_TAG_LENGTH = 16;

		/**
		 * Encrypt a plaintext string using AES-256-GCM.
		 *
		 * Returns a versioned, base64-encoded payload. Logs an error and returns
		 * empty string on failure.
		 *
		 * @param string $plaintext - The value to encrypt.
		 *
		 * @return string
		 *
		 * @since 5.6.2
		 */
		public static function encrypt( string $plaintext ): string {
			if ( '' === $plaintext ) {
				return '';
			}

			$key                   = self::derive_key();
			$iv_length             = openssl_cipher_iv_length( self::CIPHER_GCM );
			$initialization_vector = random_bytes( $iv_length );
			$auth_tag              = '';

			$ciphertext = openssl_encrypt(
				$plaintext,
				self::CIPHER_GCM,
				$key,
				OPENSSL_RAW_DATA,
				$initialization_vector,
				$auth_tag,
				'',
				self::GCM_TAG_LENGTH
			);

			if ( false === $ciphertext ) {
				error_log( 'WSAL Encryption_Helper: openssl_encrypt failed.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return '';
			}

			$payload = self::ENCRYPTION_VERSION . ':' . base64_encode( $initialization_vector ) . ':' . base64_encode( $ciphertext ) . ':' . base64_encode( $auth_tag ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

			return base64_encode( $payload ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		/**
		 * Decrypt a payload previously produced by encrypt().
		 *
		 * @param string $stored - The stored encrypted value.
		 *
		 * @return string|false - plaintext string on success, false on failure (invalid format or decryption failure).
		 *
		 * @since 5.6.2
		 */
		public static function decrypt( string $stored ) {
			if ( '' === $stored ) {
				return false;
			}

			$decoded = base64_decode( $stored, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

			if ( false === $decoded ) {
				return false;
			}

			if ( 0 === strpos( $decoded, 'v1:' ) ) {
				return self::decrypt_v1( $decoded );
			}

			return false;
		}

		/**
		 * Try to decrypt a stored value; return the raw value as fallback.
		 *
		 * @param string $stored - The stored value (encrypted or plaintext).
		 *
		 * @return string - The decrypted value if decryption was successful, otherwise the original stored value.
		 *
		 * @since 5.6.2
		 */
		public static function maybe_decrypt( string $stored ): string {
			if ( '' === $stored ) {
				return '';
			}

			$decrypted = self::decrypt( $stored );

			if ( false !== $decrypted ) {
				return $decrypted;
			} else {
				return $stored;
			}
		}

		/**
		 * Mask a string for display, showing only the first 4 and last 4 characters.
		 *
		 * @param string $value - The plaintext value.
		 *
		 * @return string
		 *
		 * @since 5.6.2
		 */
		public static function mask( string $value ): string {
			$length = strlen( $value );
			if ( $length <= 8 ) {
				return str_repeat( '*', $length );
			}

			/**
			 * Use 2-char prefix/suffix for values 9–12 chars so the masked
			 * region always has at least 5 asterisks. For 13+ chars the
			 * standard 4-char prefix/suffix is used.
			 */
			$visible = ( $length < 13 ) ? 2 : 4;

			return substr( $value, 0, $visible ) . str_repeat( '*', $length - ( $visible * 2 ) ) . substr( $value, -$visible );
		}

		/**
		 * Check if a value is masked (contains only leading/trailing chars and asterisks).
		 *
		 * @param string $value - The value to check.
		 *
		 * @return bool
		 *
		 * @since 5.6.2
		 */
		public static function is_masked( string $value ): bool {
			return (bool) preg_match( '/^.{2,4}\*{5,}.{2,4}$/', $value );
		}

		/**
		 * Check if a stored value looks like an encrypted payload produced by this class.
		 *
		 * This does NOT attempt actual decryption — it only checks the structural
		 * format (base64-encoded string that decodes to a v1: prefixed payload).
		 *
		 * @param string $stored - The stored value to check.
		 *
		 * @return bool
		 *
		 * @since 5.6.2
		 */
		public static function is_encrypted_payload( string $stored ): bool {
			if ( '' === $stored ) {
				return false;
			}

			$decoded = base64_decode( $stored, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

			if ( false === $decoded ) {
				return false;
			}

			$is_encrypted_string = 0 === strpos( $decoded, self::ENCRYPTION_VERSION . ':' );

			return $is_encrypted_string;
		}

		/**
		 * Decrypt a v1 (AES-256-GCM) payload.
		 *
		 * @param string $decoded - The base64-decoded payload string.
		 *
		 * @return string|false - plaintext string on success, false on failure (invalid format or decryption failure).
		 *
		 * @since 5.6.2
		 */
		private static function decrypt_v1( string $decoded ) {
			$parts = explode( ':', $decoded );

			if ( 4 !== count( $parts ) ) {
				return false;
			}

			list( $version, $initialization_vector_b64, $ciphertext_b64, $auth_tag_b64 ) = $parts;

			if ( 'v1' !== $version ) {
				return false;
			}

			// Init vector: make sure that encrypting the same text twice produces different ciphertext.
			$initialization_vector = base64_decode( $initialization_vector_b64, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

			// Encrypted value.
			$ciphertext = base64_decode( $ciphertext_b64, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

			// GCM integrity signature. A short value (16 bytes) that proves the ciphertext hasn't been modified and was encrypted with the correct key.
			$auth_tag = base64_decode( $auth_tag_b64, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

			// If any of these is false, it means the base64 decoding failed, which indicates an invalid payload format.
			if ( false === $initialization_vector || false === $ciphertext || false === $auth_tag ) {
				return false;
			}

			$key       = self::derive_key();
			$plaintext = openssl_decrypt( $ciphertext, self::CIPHER_GCM, $key, OPENSSL_RAW_DATA, $initialization_vector, $auth_tag );

			$result = false === $plaintext ? false : $plaintext;

			return $result;
		}

		/**
		 * Returns the stable encryption salt for notification settings that needs to be encrypted,
		 * generating and persisting it on first use.
		 *
		 * @return string
		 *
		 * @since 5.6.2
		 */
		public static function get_or_create_encryption_salt(): string {
			$salt = Settings_Helper::get_option_value( 'notification_credentials_salt' );

			if ( empty( $salt ) ) {
				$salt = base64_encode( random_bytes( 32 ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				Settings_Helper::set_option_value( 'notification_credentials_salt', $salt, true );
			}

			return $salt;
		}

		/**
		 * Derive the encryption key from the stable, self-generated salt.
		 *
		 * @return string
		 *
		 * @since 5.6.2
		 */
		private static function derive_key(): string {
			$salt = self::get_or_create_encryption_salt();

			return hash( 'sha256', $salt . 'wsal_credential_encryption', true );
		}
	}
}
