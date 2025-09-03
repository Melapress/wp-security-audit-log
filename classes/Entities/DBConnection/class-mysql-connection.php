<?php
/**
 * Class: Custom DB Class
 *
 * Test the DB connection.
 * It uses wpdb WordPress DB Class.
 *
 * @package wsal
 */

namespace WSAL\Entities\DBConnection;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Entities\DBConnection\MySQL_Connection' ) ) {
	/**
	 * Test the DB connection.
	 * It uses wpdb WordPress DB Class.
	 *
	 * @package wsal
	 */
	class MySQL_Connection extends \wpdb {

		/**
		 * Holds the database connection error string
		 *
		 * @var string
		 *
		 * @since 4.6.0
		 */
		public static $error_string = null;

		/**
		 * Overwrite wpdb class for set $allow_bail to false
		 * and hide the print of the error
		 *
		 * @global string $wp_version
		 * @param string $dbuser          - MySQL database user.
		 * @param string $dbpassword      - MySQL database password.
		 * @param string $dbname          - MySQL database name.
		 * @param string $dbhost          - MySQL database host.
		 * @param bool   $is_ssl          - Set if connection is SSL encrypted.
		 * @param bool   $is_cc           - Set if connection has client certificates.
		 * @param string $ssl_ca          - Certificate Authority.
		 * @param string $ssl_cert        - Client Certificate.
		 * @param string $ssl_key         - Client Key.
		 * @param string $dbport          - MySQL database host.
		 */
		public function __construct( $dbuser, $dbpassword, $dbname, $dbhost, $is_ssl, $is_cc, $ssl_ca, $ssl_cert, $ssl_key, $dbport = '' ) {

			if ( WP_DEBUG && WP_DEBUG_DISPLAY ) {
				$this->show_errors();
			}

			if ( function_exists( 'mysqli_connect' ) ) {
				if ( defined( 'WP_USE_EXT_MYSQL' ) ) {
					$this->use_mysqli = ! \WP_USE_EXT_MYSQL;
				} elseif ( version_compare( phpversion(), '5.5', '>=' ) || ! function_exists( 'mysql_connect' ) ) {
					$this->use_mysqli = true;
				} elseif ( false !== strpos( $GLOBALS['wp_version'], '-' ) ) {
					$this->use_mysqli = true;
				}
			}

			$this->dbuser     = $dbuser;
			$this->dbpassword = $dbpassword;
			$this->dbname     = $dbname;
			$this->dbhost     = $dbhost;
			$this->dbport     = $dbport ?? false;

			// wp-config.php creation will manually connect when ready.
			if ( defined( 'WP_SETUP_CONFIG' ) ) {
				return;
			}

			if ( $is_ssl && ! defined( 'MYSQL_CLIENT_FLAGS' ) ) {
				define( 'MYSQL_CLIENT_FLAGS', MYSQLI_CLIENT_SSL | MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT );
			}

			if ( $is_cc ) {
				if ( ! empty( $ssl_key ) && is_file( $ssl_key ) && ! defined( 'MYSQL_SSL_KEY' ) ) {
					define( 'MYSQL_SSL_KEY', $ssl_key );
				}
				if ( ! empty( $ssl_cert ) && is_file( $ssl_cert ) && ! defined( 'MYSQL_SSL_CERT' ) ) {
					define( 'MYSQL_SSL_CERT', $ssl_cert );
				}
				if ( ! empty( $ssl_ca ) && is_file( $ssl_ca ) && ! defined( 'MYSQL_SSL_CA' ) ) {
					define( 'MYSQL_SSL_CA', $ssl_ca );
				}
			}

			$this->db_connect( false );
		}

		/**
		 * Return an array with the Port and the Socket values for the MySQL connection.
		 *
		 * @return array - an array with the port and socket values.
		 *
		 * @since 5.5.0
		 */
		private function get_port_and_socket() {

			$port   = null;
			$socket = null;
			$host   = $this->dbhost;

			// Check if host contains a colon (old WSAL format was: IP:PORT or IP:/socket).
			$port_or_socket = strstr( $host, ':' );

			if ( ! empty( $this->dbport ) ) {
				// Prefer explicit port if provided.
				if ( is_numeric( $this->dbport ) ) {
					$port = intval( $this->dbport );
				} elseif ( strpos( $this->dbport, '/' ) === 0 ) {
					$socket = $this->dbport;
				}
				// Remove port/socket from host if present.
				if ( ! empty( $port_or_socket ) ) {
					$host = substr( $host, 0, strpos( $host, ':' ) );
				}
			} elseif ( ! empty( $port_or_socket ) ) {
				// Host contains :PORT or :/socket.
				$host_part = substr( $host, 0, strpos( $host, ':' ) );
				$extra     = substr( $port_or_socket, 1 );

				$host = $host_part;

				if ( strpos( $extra, '/' ) === 0 ) {
					// If this is a socket path.
					$socket = $extra;
				} elseif ( is_numeric( $extra ) ) {
					// If it's a port number.
					$port = intval( $extra );
				} else {
					// Rare case: port:socket (e.g., 3306:/tmp/mysql.sock).
					$parts = explode( ':', $extra, 2 );
					if ( is_numeric( $parts[0] ) ) {
						$port = intval( $parts[0] );
					}
					if ( isset( $parts[1] ) && strpos( $parts[1], '/' ) === 0 ) {
						$socket = $parts[1];
					}
				}
			}

			return array(
				'port'   => $port,
				'socket' => $socket,
			);
		}

		/**
		 * Connect to and select database.
		 *
		 * If $allow_bail is false, the lack of database connection will need
		 * to be handled manually.
		 *
		 * @param bool $allow_bail Optional. Allows the function to bail. Default true.
		 *
		 * @return bool True with a successful connection, false on failure.
		 * @since 3.0.0
		 * @since 3.9.0 $allow_bail parameter added.
		 */
		public function db_connect( $allow_bail = true ) {
			$this->is_mysql = true;
			$client_flags   = defined( 'MYSQL_CLIENT_FLAGS' ) ? MYSQL_CLIENT_FLAGS : 0;
			if ( $this->use_mysqli ) {
				/*
				* Set the MySQLi error reporting off because WordPress handles its own.
				* This is due to the default value change from `MYSQLI_REPORT_OFF`
				* to `MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT` in PHP 8.1.
				*/
				mysqli_report( MYSQLI_REPORT_OFF ); // phpcs:ignore -- Direct DB access

				$this->dbh = mysqli_init(); // phpcs:ignore

				// mysqli_real_connect doesn't support the host param including a port or socket
				// like mysql_connect does. This duplicates how mysql_connect detects a port and/or socket file.
				$port_and_socket = $this->get_port_and_socket();
				$port            = $port_and_socket['port'] ?? null;
				$socket          = $port_and_socket['socket'] ?? null;
				$host            = $this->dbhost;

				// Set SSL certs if we want to use secure DB connections.
				$ssl_opts     = array(
					'KEY'     => ( defined( 'MYSQL_SSL_KEY' ) && is_file( MYSQL_SSL_KEY ) ) ? MYSQL_SSL_KEY : null,
					'CERT'    => ( defined( 'MYSQL_SSL_CERT' ) && is_file( MYSQL_SSL_CERT ) ) ? MYSQL_SSL_CERT : null,
					'CA'      => ( defined( 'MYSQL_SSL_CA' ) && is_file( MYSQL_SSL_CA ) ) ? MYSQL_SSL_CA : null,
					'CA_PATH' => ( defined( 'MYSQL_SSL_CA_PATH' ) && is_dir( \MYSQL_SSL_CA_PATH ) ) ? \MYSQL_SSL_CA_PATH : null,
					'CIPHER'  => ( defined( 'MYSQL_SSL_CIPHER' ) ) ? \MYSQL_SSL_CIPHER : null,
				);
				$ssl_opts_set = false;
				foreach ( $ssl_opts as $ssl_opt_val ) {
					if ( ! is_null( $ssl_opt_val ) ) {
						$ssl_opts_set = true;
						break;
					}
				}
				if ( MYSQLI_CLIENT_SSL !== ( $client_flags & MYSQLI_CLIENT_SSL ) ) {
					$ssl_opts_set = false;
				}
				if ( $ssl_opts_set ) {
					mysqli_ssl_set( // phpcs:ignore
						$this->dbh,
						$ssl_opts['KEY'],
						$ssl_opts['CERT'],
						$ssl_opts['CA'],
						$ssl_opts['CA_PATH'],
						$ssl_opts['CIPHER']
					);
				}

				if ( WP_DEBUG ) {
					mysqli_real_connect( $this->dbh, $host, $this->dbuser, $this->dbpassword, null, $port, $socket, $client_flags ); // phpcs:ignore
				} else {
					@mysqli_real_connect( $this->dbh, $host, $this->dbuser, $this->dbpassword, null, $port, $socket, $client_flags ); // phpcs:ignore
				}

				if ( $this->dbh->connect_errno ) {
					if ( 2002 === (int) $this->dbh->connect_errno ) {
						self::$error_string = __( 'The plugin detected connectivity issues coming from your external database connection. Please check the status of your external database connection and try again!', 'wp-security-audit-log' );
					}
				}

				if ( $this->dbh->connect_errno ) {
					$this->dbh = null;

					/**
					 * It's possible ext/mysqli is misconfigured. Fall back to ext/mysql if:
					 *  - We haven't previously connected, and
					 *  - WP_USE_EXT_MYSQL isn't set to false, and
					 *  - ext/mysql is loaded.
					 */
					$attempt_fallback = true;

					if ( $this->has_connected
					|| ( defined( 'WP_USE_EXT_MYSQL' ) && ! \WP_USE_EXT_MYSQL )
					|| ( ! function_exists( 'mysql_connect' ) ) ) {
						$attempt_fallback = false;
					}

					if ( $attempt_fallback ) {
						$this->use_mysqli = false;
						return $this->db_connect( $allow_bail );
					}
				}
			}

			if ( ! $this->dbh && $allow_bail ) {
				return false;
			} elseif ( $this->dbh ) {
				if ( ! $this->has_connected ) {
					$this->init_charset();
				}

				$this->has_connected = true;

				$this->set_charset( $this->dbh );

				$this->ready = true;
				$this->set_sql_mode();
				$this->select( $this->dbname, $this->dbh );

				return true;
			}

			return false;
		}

		/**
		 * Selects a database using the current database connection.
		 *
		 * The database name will be changed based on the current database
		 * connection. On failure, the execution will bail and display an DB error.
		 *
		 * @since 0.71
		 *
		 * @param string        $db  MySQL database name.
		 * @param resource|null $dbh Optional link identifier.
		 */
		public function select( $db, $dbh = null ) {
			if ( is_null( $dbh ) ) {
				$dbh = $this->dbh;
			}

			if ( $this->use_mysqli ) {
				$success = mysqli_select_db( $dbh, $db ); // phpcs:ignore
			} else {
				$success = mysql_select_db( $db, $dbh ); // phpcs:ignore
			}

			if ( ! $success ) {
				$this->ready           = false;
				$this->db_select_error = true;
			}
		}
	}
}
