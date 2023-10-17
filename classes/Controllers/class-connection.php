<?php
/**
 * Controller: DB Connection.
 *
 * Connection class file.
 *
 * @since     4.6
 *
 * @package   wsal
 * @subpackage controllers
 *
 * @author Stoil Dobrev <sdobreff@gmail.com>
 */

declare(strict_types=1);

namespace WSAL\Controllers;

use WSAL\Helpers\Settings_Helper;
use WSAL\Entities\DBConnection\MySQL_Connection;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Controllers\Connection' ) ) {
	/**
	 * Provides connection to the DB (WPDB instance).
	 *
	 * @since 4.6.0
	 */
	class Connection {
		/**
		 * Holds the configuration of the connection (if there is one).
		 *
		 * @var bool
		 *
		 * @since 4.6.0
		 */
		private static $connection_config = false;

		/**
		 * Holds the connection (if there is one).
		 *
		 * @var bool|\WPDB
		 *
		 * @since 4.6.0
		 */
		private static $connection = false;

		/**
		 * Enabled archive mode. It forces archive connector by default.
		 *
		 * @var bool
		 *
		 * @since 4.6.0
		 */
		private static $archive_mode = false;

		/**
		 * Get the adapter config stored in the DB.
		 *
		 * @param string|null $connection_name - The connection name to extract the configuration for. Default is used if null.
		 *
		 * @return array|null adapter config
		 *
		 * @since 4.6.0
		 */
		public static function get_config( $connection_name = null ) {
			if ( null !== $connection_name ) {
				$connection_config = self::load_connection_config( $connection_name );

				return $connection_config;
			}
			if ( false === self::$connection_config ) {
				$connection_name = Settings_Helper::get_option_value( 'adapter-connection' );

				if ( empty( $connection_name ) ) {
					self::$connection_config = null;
				}

				self::$connection_config = self::load_connection_config( $connection_name );
			}

			return self::$connection_config;
		}

		/**
		 * Loads connection config using its name.
		 *
		 * @param string $connection_name Connection name.
		 *
		 * @return array|null
		 *
		 * @since 4.6.0
		 */
		public static function load_connection_config( $connection_name ) {
			if ( empty( $connection_name ) ) {
				// No connection - means default \WPDB connection.
				return null;
			}

			/**
			 * Reused code from the external DB module.
			 *
			 * @see WSAL_Ext_Common::get_connection()
			 */
			$connection_raw = maybe_unserialize( Settings_Helper::get_option_value( 'connection-' . $connection_name ) );
			$connection = ($connection_raw instanceof \stdClass) ? json_decode(json_encode($connection_raw), true) : $connection_raw; // phpcs:ignore
			if ( ! is_array( $connection ) || empty( $connection ) ) {
				return null;
			}

			return $connection;
		}

		/**
		 * Returns the connection to specified connection name or the default used if no name is specified.
		 *
		 * @param string|null $connection_name - The connection name to extract the configuration and create connection. Default is used if null.
		 *
		 * @return \wpdb
		 *
		 * @since 4.6.0
		 */
		public static function get_connection( $connection_name = null ) {
			return self::create_connection( $connection_name );
		}

		/**
		 * Destroys the current connection
		 *
		 * @return void
		 *
		 * @since 4.6.0
		 */
		public static function destroy_connection() {
			self::$connection = false;
		}

		/**
		 * Closes the DB connection.
		 *
		 * @return bool
		 *
		 * @since 4.6.0
		 */
		public static function close_connection() {
			$current_wpdb = self::get_connection();

			return $current_wpdb->close();
		}

		/**
		 * Decrypt the encrypted string.
		 * Decrypt string, after reads it from the DB.
		 *
		 * @param string $ciphertext_base64 - encrypted string.
		 *
		 * @return string
		 *
		 * @since 4.6.0
		 */
		public static function decrypt_string( $ciphertext_base64 ) {
			$encrypt_method = 'AES-256-CBC';
			$secret_key     = self::truncate_key();
			$secret_iv      = self::get_openssl_iv();

			// Hash the key.
			$key = hash( 'sha256', $secret_key );

			// iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning.
			$iv = substr( hash( 'sha256', $secret_iv ), 0, 16 );

			return openssl_decrypt(base64_decode($ciphertext_base64), $encrypt_method, $key, 0, $iv); // phpcs:ignore
		}

		/**
		 * Enables archive mode.
		 *
		 * @since 4.6.0
		 */
		public static function enable_archive_mode() {
			self::$archive_mode = true;
		}

		/**
		 * Is that an archive connection or not?
		 *
		 * @return boolean
		 *
		 * @since 4.6.0
		 */
		public static function is_archive_mode() {
			return self::$archive_mode;
		}

		/**
		 * Disables archive mode.
		 *
		 * @since 4.6.0
		 */
		public static function disable_archive_mode() {
			self::$archive_mode = false;
		}

		/**
		 * Test the connection.
		 *
		 * @param array $connection_config - Connection configuration to test.
		 *
		 * @return bool
		 * @throws \Exception - Connection failed.
		 *
		 * @since 4.6.0
		 */
		public static function test_connection( array $connection_config = null ) {
			error_reporting( E_ALL ^ ( E_NOTICE | E_WARNING | E_DEPRECATED ) ); // phpcs:ignore
			if ( ! $connection_config ) {
				$connection_config = self::get_config();
			}

			if ( ! $connection_config ) {
				// No configuration provided or config is empty - that means that local DB is in use - return true.
				return true;
			}
			$password = self::decrypt_string( $connection_config['password'] );

			$new_wpdb = new MySQL_Connection( $connection_config['user'], $password, $connection_config['db_name'], $connection_config['hostname'], $connection_config['is_ssl'], $connection_config['is_cc'], $connection_config['ssl_ca'], $connection_config['ssl_cert'], $connection_config['ssl_key'] ); // phpcs:ignore -- Accessing the database directly should be avoided.

			if ( isset( $new_wpdb->error ) && isset( $new_wpdb->dbh ) ) {
				throw new \Exception( $new_wpdb->dbh->error, $new_wpdb->dbh->errno );
			} elseif ( ! isset( $new_wpdb->dbh ) ) {
				$error_code = mysqli_connect_errno(); // phpcs:ignore

				if ( 1045 === $error_code ) {
					throw new \Exception( __( 'Error establishing a database connection. DB username or password are not valid.' ), $error_code );
				} else {
					$error_message = mysqli_connect_error(); // phpcs:ignore
					// if we get an error message from mysqli then use it otherwise use a generic message.
					if ( $error_message ) {
						throw new \Exception(
							sprintf(
							/* translators: 1 - mysqli error code, 2 - mysqli error message */
								__( 'Code %1$d: %2$s', 'wp-security-audit-log' ),
								$error_code,
								$error_message
							),
							$error_code
						);
					}
				}
			} elseif ( isset( $new_wpdb->db_select_error ) ) {
				throw new \Exception( 'Error: Database ' . $connection_config['db_name'] . ' is unknown.', 1046 );
			} elseif ( ! $new_wpdb->has_connected ) {
				throw new \Exception( 'Error establishing a database connection.' );
			} else {
				return true;
			}
		}

		/**
		 * Displays admin notice if database connection is not available.
		 *
		 * @since 4.6.0
		 */
		public static function display_notice_if_connection_not_available() {
			// Check database connection.
			$wsal_db = self::get_connection();

			// Add connectivity notice.
			if ( ! $wsal_db->check_connection( false ) ) {
				?>
			<div class="notice notice-error">
				<p><?php esc_html_e( 'There are connectivity issues with the database where the WordPress activity log is stored.', 'wp-security-audit-log' ); ?></p>
			</div>
				<?php
			}
		}

		/**
		 * Check the adapter config with a test connection.
		 *
		 * @param array $config Configuration data.
		 *
		 * @return boolean true|false
		 *
		 * @throws \Exception - Connection failed.
		 *
		 * @since 4.6.0
		 */
		public static function check_config( $config ) {
			// Only mysql supported at the moment.
			if ( array_key_exists( 'type', $config ) && 'mysql' === $config['type'] ) {
				try {
					return self::test_connection( $config );
				} catch ( \Exception $e ) {
					return false;
				}
			}

			return false;
		}

		/**
		 * Encrypt plain text.
		 * Encrypt string, before saves it to the DB.
		 *
		 * @param string $plaintext - Plain text that is going to be encrypted.
		 *
		 * @return string
		 * @since  2.6.3
		 */
		public static function encrypt_string( $plaintext ) {

			$ciphertext     = false;
			$encrypt_method = 'AES-256-CBC';
			$secret_key     = self::truncate_key();
			$secret_iv      = self::get_openssl_iv();

			// Hash the key.
			$key = hash( 'sha256', $secret_key );

			// iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning.
			$iv = substr( hash( 'sha256', $secret_iv ), 0, 16 );

			$ciphertext = openssl_encrypt( $plaintext, $encrypt_method, $key, 0, $iv );
			$ciphertext = base64_encode( $ciphertext ); // phpcs:ignore

			return $ciphertext;
		}

		/**
		 * Truncate string longer than 32 characters.
		 * Authentication Unique Key.
		 *
		 * @return string AUTH_KEY
		 *
		 * @see wp-config.php
		 *
		 * @since 4.6.0
		 */
		private static function truncate_key() {
			if ( ! defined( 'AUTH_KEY' ) ) {
				return 'x4>Tg@G-Kr6a]o-eJeP^?UO)KW;LbV)I';
			}
			$key_size = strlen( AUTH_KEY );
			if ( $key_size > 32 ) {
				return substr( AUTH_KEY, 0, 32 );
			} else {
				return AUTH_KEY;
			}
		}

		/**
		 * Get OpenSSL IV for DB.
		 *
		 * @since 4.6.0
		 */
		private static function get_openssl_iv() {
			$secret_openssl_iv = 'і-(аэ┤#≥и┴зейН';
			$key_size          = strlen( $secret_openssl_iv );
			if ( $key_size > 32 ) {
				return substr( $secret_openssl_iv, 0, 32 );
			} else {
				return $secret_openssl_iv;
			}
		}

		/**
		 * Creates a connection and returns it.
		 *
		 * @param string|null $connection_name - The connection name to extract the configuration and create connection. Default is used if null.
		 *
		 * @return wpdb Instance of WPDB.
		 *
		 * @since 4.6.0
		 */
		private static function create_connection( $connection_name = null ) {
			if ( 'local' === $connection_name ) {
				global $wpdb;

				return $wpdb;
			}
			if ( null !== $connection_name ) {
				$connection_config = self::load_connection_config( $connection_name );

				return self::build_connection( $connection_config );
			}
			if ( false === self::$connection ) {
				$connection_config = self::get_config( $connection_name );

				self::$connection = self::build_connection( $connection_config );

				return self::$connection;
			} elseif ( self::$connection instanceof \wpdb ) {
				return self::$connection;
			} else {
				global $wpdb;

				self::$connection = $wpdb;

				return self::$connection;
			}
		}

		/**
		 * Creates a new connection and returns it.
		 *
		 * @param array $connection_config - All of the connection parameters.
		 *
		 * @return \wpdb
		 *
		 * @since 4.6.0
		 */
		public static function build_connection( $connection_config = null ) {
			if ( empty( $connection_config ) ) {
				global $wpdb;

				return $wpdb;
			}
			$password   = self::decrypt_string( $connection_config['password'] );
			$connection = new MySQL_Connection( $connection_config['user'], $password, $connection_config['db_name'], $connection_config['hostname'], $connection_config['is_ssl'], $connection_config['is_cc'], $connection_config['ssl_ca'], $connection_config['ssl_cert'], $connection_config['ssl_key'] ); // phpcs:ignore -- Accessing database directly should be avoided
			if ( array_key_exists( 'baseprefix', $connection_config ) ) {
				$connection->set_prefix( $connection_config['baseprefix'] );
			}

			return $connection;
		}

		/**
		 * Checks is the connection is for an external database.
		 *
		 * @since 4.6.0
		 * @return boolean
		 */
		public static function is_external() {
			$db_config = self::get_config();

			return is_array( $db_config ) && ! empty( $db_config );
		}

		/**
		 * Should we write directly to the database connection or use a cron job for this.
		 *
		 * @return boolean
		 *
		 * @since 4.6.0
		 */
		public static function is_writing_directly(): bool {
			$db_config = self::get_config();
			if ( is_array( $db_config ) && ! empty( $db_config ) ) {
				if ( isset( $db_config['direct'] ) && true === (bool) $db_config['direct'] ) {
					return true;
				} else {
					return false;
				}
			}

			return true;
		}
	}
}
