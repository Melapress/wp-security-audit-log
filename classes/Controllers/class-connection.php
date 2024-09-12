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

use WSAL\Helpers\Classes_Helper;
use WSAL\Helpers\Settings_Helper;
use WSAL\Entities\Metadata_Entity;
use WSAL\Entities\Occurrences_Entity;
use WSAL\Entities\Archive\Archive_Records;
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
		 * Local cache for mirror types.
		 *
		 * @var array
		 *
		 * @since 5.0.0
		 */
		private static $mirror_types;

		/**
		 * Archive DB Connection Object.
		 *
		 * @var object
		 *
		 * @since 5.0.0
		 */
		private static $archive_db = null;

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
			$connection     = ( $connection_raw instanceof \stdClass ) ? json_decode( json_encode( $connection_raw ), true ) : $connection_raw;
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

			return openssl_decrypt( base64_decode( $ciphertext_base64 ), $encrypt_method, $key, 0, $iv );
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
			error_reporting( E_ALL ^ ( E_NOTICE | E_WARNING | E_DEPRECATED ) );
			if ( ! $connection_config ) {
				$connection_config = self::get_config();
			}

			if ( ! $connection_config ) {
				// No configuration provided or config is empty - that means that local DB is in use - return true.
				return true;
			}
			$password = self::decrypt_string( $connection_config['password'] );

			$new_wpdb = new MySQL_Connection( $connection_config['user'], $password, $connection_config['db_name'], $connection_config['hostname'], $connection_config['is_ssl'], $connection_config['is_cc'], $connection_config['ssl_ca'], $connection_config['ssl_cert'], $connection_config['ssl_key'] );

			if ( isset( $new_wpdb->error ) && isset( $new_wpdb->dbh ) ) {
				throw new \Exception( $new_wpdb->dbh->error, $new_wpdb->dbh->errno ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			} elseif ( ! isset( $new_wpdb->dbh ) ) {
				$error_code = mysqli_connect_errno(); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_connect_errno

				if ( 1045 === $error_code ) {
					throw new \Exception( __( 'Error establishing a database connection. DB username or password are not valid.' ), $error_code ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				} else {
					$error_message = mysqli_connect_error(); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_connect_error
					// if we get an error message from mysqli then use it otherwise use a generic message.
					if ( $error_message ) {
						throw new \Exception(
							sprintf(
							/* translators: 1 - mysqli error code, 2 - mysqli error message */
								__( 'Code %1$d: %2$s', 'wp-security-audit-log' ), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
								\esc_attr( $error_code ),
								\esc_attr( $error_message )
							),
							$error_code  // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
						);
					}
				}
			} elseif ( isset( $new_wpdb->db_select_error ) ) {
				throw new \Exception( 'Error: Database ' . $connection_config['db_name'] . ' is unknown.', 1046 ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
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
			$connection = new MySQL_Connection( $connection_config['user'], $password, $connection_config['db_name'], $connection_config['hostname'], $connection_config['is_ssl'], $connection_config['is_cc'], $connection_config['ssl_ca'], $connection_config['ssl_cert'], $connection_config['ssl_key'] ); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysql_connection
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

		/**
		 * Migrate to external database.
		 *
		 * @param string $connection_name External connection name.
		 * @param int    $limit           - Limit.
		 *
		 * @return int
		 *
		 * @since 5.0.0
		 */
		public static function migrate_occurrence( $connection_name, $limit ) {
			$args['limit']      = $limit;
			$args['archive_db'] = self::get_connection( $connection_name );
			$count              = 0;

			Archive_Records::archive( $args, $count );

			return $count;
		}

		/**
		 * Migrate back to WP database
		 *
		 * @param int $limit - Limit.
		 *
		 * @return int
		 *
		 * @since 5.0.0
		 */
		public static function migrate_back_occurrence( $limit ) {
			global $wpdb;

			$args['limit']      = $limit;
			$args['archive_db'] = $wpdb;
			$count              = 0;

			Archive_Records::archive( $args, $count );

			return $count;
		}

		/**
		 * Recreate DB tables on WP.
		 *
		 * @since 5.0.0
		 */
		public static function recreate_tables() {
			Occurrences_Entity::create_table();
			Metadata_Entity::create_table();
		}

		/**
		 * Set Connection Object.
		 *
		 * @since 5.0.0
		 *
		 * @param array|\stdClass $connection - Connection object.
		 */
		public static function save_connection( $connection ) {
			// Stop here if no connection provided.
			if ( empty( $connection ) ) {
				return;
			}

			if ( $connection instanceof \stdClass ) {
				$connection = (array) $connection;
			}

			if ( isset( $connection['name'] ) ) {
				$connection_name = $connection['name'];

				Settings_Helper::set_option_value( WSAL_CONN_PREFIX . $connection_name, $connection );
			}
		}

		/**
		 * Delete Connection Object.
		 *
		 * @param string $connection_name - Connection name.
		 *
		 * @since 5.0.0
		 */
		public static function delete_connection( $connection_name ) {
			$connection = self::load_connection_config( $connection_name );

			if ( is_array( $connection ) && array_key_exists( 'type', $connection ) ) {
				Alert_MAnager::trigger_event_if(
					6320,
					array(
						'EventType' => 'deleted',
						'type'      => $connection['type'],
						'name'      => $connection_name,
					),
					function () {
						return ! Alert_Manager::will_trigger( 6321 );
					}
				);
			}

			Settings_Helper::delete_option_value( WSAL_CONN_PREFIX . $connection_name );
		}

		/**
		 * Remove External DB config.
		 *
		 * @since 5.0.0
		 */
		public static function remove_external_storage_config() {
			// Get archive connection.
			$adapter_conn_name = Settings_Helper::get_option_value( 'adapter-connection' );
			if ( $adapter_conn_name ) {
				$adapter_connection             = self::load_connection_config( $adapter_conn_name );
				$adapter_connection['used_for'] = '';
				self::save_connection( $adapter_connection );
			}

			Settings_Helper::delete_option_value( 'adapter-connection' );
		}

		/**
		 * Updates given connection to be used for external storage.
		 *
		 * @param string $connection_name Connection name.
		 * @since 5.0.0
		 */
		public static function update_connection_as_external( $connection_name ) {
			// Set external storage to be used for logging events from now on.
			$db_connection = self::load_connection_config( $connection_name );

			// Error handling.
			if ( empty( $db_connection ) ) {
				return false;
			}

			// Set connection's used_for attribute.
			$db_connection['used_for'] = __( 'External Storage', 'wp-security-audit-log' );
			Settings_Helper::set_option_value( 'adapter-connection', $connection_name, true );
			self::save_connection( $db_connection );

			return true;
		}

		/**
		 * Finds all mirrors using a specific connection.
		 *
		 * @param string $connection_name Connection name.
		 *
		 * @return array[]
		 * @since 5.0.0
		 */
		public static function get_mirrors_by_connection_name( $connection_name ) {
			$mirrors = \WSAL\Helpers\Settings_Helper::get_all_mirrors();
			$result  = array();
			if ( ! empty( $mirrors ) ) {
				foreach ( $mirrors as $mirror ) {
					if ( $connection_name === $mirror['connection'] ) {
						array_push( $result, $mirror );
					}
				}
			}

			return $result;
		}

		/**
		 * Gets mirror types.
		 *
		 * @return array List of mirror types.
		 *
		 * @since 5.0.0
		 */
		public static function get_mirror_types() {
			if ( ! isset( self::$mirror_types ) ) {

				$mirrors = Classes_Helper::get_classes_by_namespace( 'WSAL\Extensions\ExternalDB\Mirrors' );

				$result = array();

				foreach ( $mirrors as $mirror ) {
					$result [ $mirror::get_type() ] = array(
						'name'   => $mirror::get_name(),
						'config' => $mirror::get_config_definition(),
						'class'  => $mirror,
					);
				}

				// $file_filter = $this->get_base_dir() . 'classes' . DIRECTORY_SEPARATOR . 'mirrors' . DIRECTORY_SEPARATOR . '*Connection.php';
				// foreach ( glob( $file_filter ) as $file ) {
				// $base_filename = basename( $file );
				// $class_name    = 'WSAL_Ext_Mirrors_' . substr( $base_filename, 0, strlen( $base_filename ) - 4 );
				// try {
				// require_once $file;
				// $result [ $class_name::get_type() ] = array(
				// 'name'   => $class_name::get_name(),
				// 'config' => $class_name::get_config_definition(),
				// 'class'  => $class_name,
				// );
				// } catch ( Exception $exception ) {  // phpcs:ignore
				// Skip unsuitable class.
				// TODO log to debug log.
				// }
				// }

				self::$mirror_types = $result;
			}

			return self::$mirror_types;
		}

		/**
		 * Delete mirror.
		 *
		 * @param string $mirror_name - Mirror name.
		 *
		 * @since 5.0.0
		 */
		public static function delete_mirror( $mirror_name ) {

			Alert_Manager::trigger_event(
				6326,
				array(
					'EventType' => 'deleted',
					'name'      => $mirror_name,
				)
			);

			Settings_Helper::delete_option_value( \WSAL_MIRROR_PREFIX . $mirror_name );
		}

		/**
		 * Set Mirror Object.
		 *
		 * @since 5.0.0
		 *
		 * @param array|\stdClass $mirror Mirror data.
		 */
		public static function save_mirror( $mirror ) {
			if ( empty( $mirror ) ) {
				return;
			}

			$mirror_name = ( $mirror instanceof \stdClass ) ? $mirror->name : $mirror['name'];

			$old_value = Settings_Helper::get_option_value( \WSAL_MIRROR_PREFIX . $mirror_name );

			if ( ! isset( $old_value['state'] ) ) {
				Alert_Manager::trigger_event(
					6323,
					array(
						'EventType'  => 'added',
						'connection' => ( $mirror instanceof \stdClass ) ? $mirror->connection : $mirror['connection'],
						'name'       => $mirror_name,
					)
				);
			} elseif ( isset( $old_value['state'] ) && $old_value['state'] !== $mirror['state'] ) {
				Alert_Manager::trigger_event(
					6325,
					array(
						'EventType'  => ( $mirror['state'] ) ? 'enabled' : 'disabled',
						'connection' => ( $mirror instanceof \stdClass ) ? $mirror->connection : $mirror['connection'],
						'name'       => $mirror_name,
					)
				);
			} else {
				Alert_Manager::trigger_event(
					6324,
					array(
						'EventType'  => 'modified',
						'connection' => ( $mirror instanceof \stdClass ) ? $mirror->connection : $mirror['connection'],
						'name'       => $mirror_name,
					)
				);
			}

			Settings_Helper::set_option_value( \WSAL_MIRROR_PREFIX . $mirror_name, $mirror );
		}

		/**
		 * Return Mirror Object.
		 *
		 * @since 5.0.0
		 *
		 * @param string $mirror_name - Mirror name.
		 * @return array|bool
		 */
		public static function get_mirror( $mirror_name ) {
			if ( empty( $mirror_name ) ) {
				return false;
			}
			$result_raw = Settings_Helper::get_option_value( \WSAL_MIRROR_PREFIX . $mirror_name );
			$result     = maybe_unserialize( $result_raw );
			return ( $result instanceof \stdClass ) ? json_decode( json_encode( $result ), true ) : $result; // phpcs:ignore
		}

		/**
		 * Returns the archive connection or null
		 *
		 * @return \WPDB|null
		 *
		 * @since 5.0.0
		 */
		public static function get_archive_database_connection() {
			if ( ! empty( self::$archive_db ) ) {
				return self::$archive_db;
			} else {
				$connection_config = Settings_Helper::get_archive_config();
				if ( empty( $connection_config ) ) {
					return null;
				} else {
					// Get archive DB connection.
					self::$archive_db = self::build_connection( $connection_config );

					// Check object for disconnection or other errors.
					$connected = true;
					if ( isset( self::$archive_db->dbh->errno ) ) {
						$connected = ! ( 0 !== (int) self::$archive_db->dbh->errno ); // Database connection error check.
					} elseif ( is_wp_error( self::$archive_db->error ) ) {
						$connected = false;
					}

					if ( $connected ) {
						return self::$archive_db;
					} else {
						return null;
					}
				}
			}
		}

		/**
		 * Enable/Disable archiving cron job started option.
		 *
		 * @param bool $value - Value.
		 *
		 * @since 5.0.0
		 */
		public static function set_archiving_cron_started( $value ) {
			if ( ! empty( $value ) ) {
				Settings_Helper::set_option_value( 'archiving-cron-started', 1 );
			} else {
				Settings_Helper::delete_option_value( 'archiving-cron-started' );
			}
		}

		/**
		 * Archive alerts (Occurrences table)
		 *
		 * @param array $args - Arguments array.
		 *
		 * @return array|false|null
		 *
		 * @since 5.0.0
		 */
		public static function archive( $args ) {
			$args['archive_db'] = self::get_archive_database_connection();
			if ( empty( $args['archive_db'] ) ) {
				return false;
			}
			$last_created_on = Settings_Helper::get_option_value( 'archiving-last-created' );
			if ( ! empty( $last_created_on ) ) {
				$args['last_created_on'] = $last_created_on;
			}

			return Archive_Records::archive( $args );
		}

		/**
		 * Archiving alerts.
		 *
		 * @since 5.0.0
		 */
		public static function archiving_alerts() {
			if ( ! Settings_Helper::is_archiving_cron_started() ) {
				set_time_limit( 0 );
				// Start archiving.
				self::set_archiving_cron_started( true );

				$args          = array();
				$args['limit'] = 500;
				$args_result   = false;

				$num             = Settings_Helper::get_archiving_date();
				$type            = Settings_Helper::get_archiving_date_type();
				$now             = current_time( 'timestamp' );
				$args['by_date'] = strtotime( '-' . $num . ' ' . $type, $now );
				$args_result     = self::archive( $args );

				// End archiving.
				self::set_archiving_cron_started( false );
			}
		}

		/**
		 * Remove the archiving config.
		 *
		 * @since 5.0.0
		 */
		public static function remove_archiving_config() {
			// Get archive connection.
			$archive_conn_name = Settings_Helper::get_option_value( 'archive-connection' );

			if ( $archive_conn_name ) {
				$archive_connection             = self::load_connection_config( $archive_conn_name );
				$archive_connection['used_for'] = '';
				self::save_connection( $archive_connection );
			}

			Settings_Helper::delete_option_value( 'archive-connection' );
			Settings_Helper::delete_option_value( 'archiving-date' );
			Settings_Helper::delete_option_value( 'archiving-date-type' );
			Settings_Helper::delete_option_value( 'archiving-run-every' );
			Settings_Helper::delete_option_value( 'archiving-daily-e' );
			Settings_Helper::delete_option_value( 'archiving-weekly-e' );
			Settings_Helper::delete_option_value( 'archiving-week-day' );
			Settings_Helper::delete_option_value( 'archiving-time' );
			Settings_Helper::delete_option_value( 'archiving-stop' );
		}

		/**
		 * Enable/Disable archiving.
		 *
		 * @param bool $enabled - Value.
		 *
		 * @since 5.0.0
		 */
		public static function set_archiving_enabled( $enabled ) {
			Settings_Helper::set_option_value( 'archiving-e', $enabled );
			if ( empty( $enabled ) ) {
				self::remove_archiving_config();
				Settings_Helper::delete_option_value( 'archiving-last-created' );
			}
		}
	}
}
