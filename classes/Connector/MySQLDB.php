<?php
/**
 * Class: MySQL DB Connector.
 *
 * MySQL Connector Class
 * It uses wpdb WordPress DB Class
 *
 * @package wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MySQL Connector Class
 * It uses wpdb WordPress DB Class
 *
 * @package wsal
 */
class WSAL_Connector_MySQLDB extends WSAL_Connector_AbstractConnector implements WSAL_Connector_ConnectorInterface {

	/**
	 * Connection Configuration.
	 *
	 * @var array
	 */
	protected $connection_config = null;

	/**
	 * Method: Constructor.
	 *
	 * @param array $connection_config - Connection config.
	 */
	public function __construct( $connection_config = null ) {
		$this->connection_config = $connection_config;
		parent::__construct( 'MySQL' );
	}

	/**
	 * Test the connection.
	 *
	 * @return bool
	 * @throws Exception - Connection failed.
	 */
	public function TestConnection() {
		error_reporting( E_ALL ^ ( E_NOTICE | E_WARNING | E_DEPRECATED ) ); // phpcs:ignore
		$connection_config = $this->connection_config;
		$password          = $this->decrypt_string( $connection_config['password'] );

		$new_wpdb = new wpdbCustom( $connection_config['user'], $password, $connection_config['db_name'], $connection_config['hostname'], $connection_config['is_ssl'], $connection_config['is_cc'], $connection_config['ssl_ca'], $connection_config['ssl_cert'], $connection_config['ssl_key'] );

		if ( isset( $new_wpdb->error ) && isset( $new_wpdb->dbh ) ) {
			throw new Exception( $new_wpdb->dbh->error, $new_wpdb->dbh->errno );
		} elseif ( ! isset( $new_wpdb->dbh ) ) {
			$error_code = mysqli_connect_errno(); // phpcs:ignore

			if ( 1045 === $error_code ) {
				throw new Exception( __( 'Error establishing a database connection. DB username or password are not valid.' ), $error_code );
			} else {
				$error_message = mysqli_connect_error(); // phpcs:ignore
				// if we get an error message from mysqli then use it otherwise use a generic message.
				if ( $error_message ) {
					throw new Exception(
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
			throw new Exception( 'Error: Database ' . $connection_config['db_name'] . ' is unknown.', '1046' );
		} elseif ( ! $new_wpdb->has_connected ) {
			throw new Exception( 'Error establishing a database connection.' );
		} else {
			return true;
		}
	}

	/**
	 * Decrypt the encrypted string.
	 * Decrypt string, after reads it from the DB.
	 *
	 * @param string $ciphertext_base64 - encrypted string.
	 *
	 * @return string
	 * @since  2.6.3
	 */
	public function decrypt_string( $ciphertext_base64 ) {
		$encrypt_method = 'AES-256-CBC';
		$secret_key     = $this->truncateKey();
		$secret_iv      = $this->get_openssl_iv();

		// Hash the key.
		$key = hash( 'sha256', $secret_key );

		// iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning.
		$iv = substr( hash( 'sha256', $secret_iv ), 0, 16 );

		return openssl_decrypt( base64_decode( $ciphertext_base64 ), $encrypt_method, $key, 0, $iv ); // phpcs:ignore
	}

	/**
	 * Truncate string longer than 32 characters.
	 * Authentication Unique Key
	 *
	 * @return string AUTH_KEY
	 * @see wp-config.php
	 */
	private function truncateKey() {
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
	 * @since 2.6.3
	 */
	private function get_openssl_iv() {
		$secret_openssl_iv = 'і-(аэ┤#≥и┴зейН';
		$key_size          = strlen( $secret_openssl_iv );
		if ( $key_size > 32 ) {
			return substr( $secret_openssl_iv, 0, 32 );
		} else {
			return $secret_openssl_iv;
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function close_connection() {
		$current_wpdb = $this->get_connection();

		return $current_wpdb->close();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_connection() {
		if ( ! empty( $this->connection ) ) {
			return $this->connection;
		} else {
			$this->connection = $this->create_connection();

			return $this->connection;
		}
	}

	/**
	 * Creates a connection and returns it
	 *
	 * @return wpdb Instance of WPDB.
	 */
	private function create_connection() {
		if ( ! empty( $this->connection_config ) ) {
			$connection_config = $this->connection_config;
			$password          = $this->decrypt_string( $connection_config['password'] );
			$new_wpdb          = new wpdbCustom( $connection_config['user'], $password, $connection_config['db_name'], $connection_config['hostname'], $connection_config['is_ssl'], $connection_config['is_cc'], $connection_config['ssl_ca'], $connection_config['ssl_cert'], $connection_config['ssl_key'] );
			if ( array_key_exists( 'baseprefix', $connection_config ) ) {
				$new_wpdb->set_prefix( $connection_config['baseprefix'] );
			}

			return $new_wpdb;
		} else {
			global $wpdb;

			return $wpdb;
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_adapter( $class_name ) {
		$obj_name = $this->get_adapter_class_name( $class_name );

		return new $obj_name( $this->get_connection() );
	}

	/**
	 * Gets an adapter class name for the specified model.
	 *
	 * @param string $class_name - Class name.
	 *
	 * @return string
	 */
	protected function get_adapter_class_name( $class_name ) {
		return 'WSAL_Adapters_MySQL_' . $class_name;
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_installed() {
		$wpdb      = $this->get_connection();
		$table     = $wpdb->base_prefix . 'wsal_occurrences';
		$db_result = $wpdb->query( "SELECT COUNT(1) FROM {$table};" ); // phpcs:ignore

		return 1 === $db_result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function install_all( $is_external_database = false ) {
		$adapter_list = WSAL_Utilities_FileSystemUtils::read_files_in_folder( $this->get_adapters_directory(), '*.php' );
		$adapter_list = apply_filters( 'wsal_install_adapters_list', $adapter_list );
		foreach ( $adapter_list as $file ) {
			$file_path  = explode( DIRECTORY_SEPARATOR, $file );
			$file_name  = $file_path[ count( $file_path ) - 1 ];
			$class_name = $this->get_adapter_class_name( str_replace( 'Adapter.php', '', $file_name ) );
			$this->install_single( $class_name, $is_external_database );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function install_single( $class_name, $is_external_database = false ) {
		if ( ! class_exists( $class_name ) ) {
			return;
		}

		$class = new $class_name( $this->get_connection() );
		if ( $is_external_database && $class instanceof WSAL_Adapters_MySQL_Session ) {
			// Sessions table should only ever exist only in local database.
			return;
		}

		if ( ! $is_external_database && $class instanceof WSAL_Adapters_MySQL_TmpUser ) {
			// Exclude the tmp_users table for local database.
			return;
		}

		if ( is_subclass_of( $class, 'WSAL_Adapters_MySQL_ActiveRecord' ) ) {
			$class->install();
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function uninstall_all() {
		foreach ( WSAL_Utilities_FileSystemUtils::read_files_in_folder( $this->get_adapters_directory(), '*.php' ) as $file ) {
			$file_path  = explode( DIRECTORY_SEPARATOR, $file );
			$file_name  = $file_path[ count( $file_path ) - 1 ];
			$class_name = $this->get_adapter_class_name( str_replace( 'Adapter.php', '', $file_name ) );

			$class = new $class_name( $this->get_connection() );
			if ( is_subclass_of( $class, 'WSAL_Adapters_MySQL_ActiveRecord' ) ) {
				$class->uninstall();
			}
		}
	}

	/**
	 * Migrate Occurrences from WP DB to External DB.
	 *
	 * @param integer $limit - Limit.
	 *
	 * @return int
	 */
	public function migrate_occurrence_from_local_to_external( $limit ) {
		global $wpdb;

		return $this->migrate_occurrence( $wpdb, $this->get_connection(), $limit );
	}

	/**
	 * Migrates occurrence data and related metadata from a source database to the target database.
	 *
	 * It also deletes the tables in the source database when there is no more data to migrate.
	 *
	 * @param wpdb $source_db Source database.
	 * @param wpdb $target_db Target database.
	 * @param int  $limit     Number of entries to migrate.
	 *
	 * @return int Number of occurrence entries migrated.
	 */
	private function migrate_occurrence( $source_db, $target_db, $limit ) {
		// Load occurrence data from the source database.
		$occurrence_adapter_source = new WSAL_Adapters_MySQL_Occurrence( $source_db );
		if ( ! $occurrence_adapter_source->is_installed() ) {
			return 0;
		}

		$sql         = 'SELECT * FROM ' . $occurrence_adapter_source->get_table() . ' LIMIT ' . $limit;
		$occurrences = $source_db->get_results( $sql, ARRAY_A );

		// No more data to migrate, delete the old tables.
		if ( empty( $occurrences ) ) {
			$this->delete_after_migrate( $occurrence_adapter_source );
			$this->delete_after_migrate( new WSAL_Adapters_MySQL_Meta( $source_db ) );

			return 0;
		}

		// Insert data to the target database.
		$occurrence_adapter_target    = new WSAL_Adapters_MySQL_Occurrence( $target_db );
		$occurrence_table_name_target = $occurrence_adapter_target->get_table();

		$occurrence_ids_to_delete = array();
		foreach ( $occurrences as $entry ) {

			$target_db->insert(
				$occurrence_table_name_target,
				array(
					'site_id'     => $entry['site_id'],
					'alert_id'    => $entry['alert_id'],
					'created_on'  => $entry['created_on'],
					'client_ip'   => $entry['client_ip'],
					'severity'    => $entry['severity'],
					'object'      => $entry['object'],
					'event_type'  => $entry['event_type'],
					'user_agent'  => $entry['user_agent'],
					'user_roles'  => $entry['user_roles'],
					'username'    => $entry['username'],
					'user_id'     => $entry['user_id'],
					'session_id'  => $entry['session_id'],
					'post_status' => $entry['post_status'],
					'post_type'   => $entry['post_type'],
					'post_id'     => $entry['post_id'],
				),
				array( '%d', '%d', '%f', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d' )
			);

			$old_entry_id = intval( $entry['id'] );
			$new_entry_id = $target_db->insert_id;
			$this->migrate_meta( $source_db, $target_db, $old_entry_id, $new_entry_id );

			array_push( $occurrence_ids_to_delete, $old_entry_id );
		}

		// Delete migrated events and associated meta data.
		$meta_adapter_source = new WSAL_Adapters_MySQL_Meta( $source_db );
		$source_db->query( 'DELETE FROM ' . $occurrence_adapter_source->get_table() . ' WHERE id IN (' . implode( ',', $occurrence_ids_to_delete ) . ');' );
		$source_db->query( 'DELETE FROM ' . $meta_adapter_source->get_table() . ' WHERE occurrence_id IN (' . implode( ',', $occurrence_ids_to_delete ) . ');' );

		return count( $occurrence_ids_to_delete );
	}

	/**
	 * Delete after Migrate alerts.
	 *
	 * @param object $record - Type of record.
	 */
	private function delete_after_migrate( $record ) {
		global $wpdb;
		$sql = 'DROP TABLE IF EXISTS ' . $record->get_table();
		$wpdb->query( $sql ); // phpcs:ignore
	}

	/**
	 * Migrate Metadata from WP DB to External DB.
	 *
	 * @param wpdb $source_db         Source database.
	 * @param wpdb $target_db         Target database.
	 * @param int  $old_occurrence_id Old occurrence ID.
	 * @param int  $new_occurrence_id New occurrence ID.
	 *
	 * @return int Number of metadata migrated.
	 */
	private function migrate_meta( $source_db, $target_db, $old_occurrence_id, $new_occurrence_id ) {

		// Load meta data from the source database.
		$meta_adapter_source = new WSAL_Adapters_MySQL_Meta( $source_db );
		if ( ! $meta_adapter_source->is_installed() ) {
			return 0;
		}

		$query          = 'SELECT * FROM ' . $meta_adapter_source->get_table() . ' WHERE occurrence_id = %d;';
		$prepared_query = $source_db->prepare( $query, $old_occurrence_id );
		$metadata       = $source_db->get_results( $prepared_query, ARRAY_A );

		// Insert meta data to target database.
		if ( ! empty( $metadata ) ) {
			$meta_adapter_target = new WSAL_Adapters_MySQL_Meta( $target_db );

			$query = 'INSERT INTO ' . $meta_adapter_target->get_table() . ' (occurrence_id, name, value) VALUES ';
			foreach ( $metadata as $entry ) {
				$query .= $target_db->prepare(
					'( %d, %s, %s ), ',
					$new_occurrence_id,
					$entry['name'],
					$entry['value']
				);
			}
			$query = rtrim( $query, ', ' );
			$target_db->query( $query );

			return count( $metadata );
		}

		return 0;
	}

	/**
	 * Migrate Back Occurrences from External DB to WP DB.
	 *
	 * @param integer $limit - Limit.
	 *
	 * @return array
	 */
	public function migrate_occurrence_from_external_to_local( $limit ) {
		global $wpdb;
		return $this->migrate_occurrence( $this->get_connection(), $wpdb, $limit );
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
	public function encrypt_string( $plaintext ) {

		$ciphertext     = false;
		$encrypt_method = 'AES-256-CBC';
		$secret_key     = $this->truncateKey();
		$secret_iv      = $this->get_openssl_iv();

		// Hash the key.
		$key = hash( 'sha256', $secret_key );

		// iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning.
		$iv = substr( hash( 'sha256', $secret_iv ), 0, 16 );

		$ciphertext = openssl_encrypt( $plaintext, $encrypt_method, $key, 0, $iv );
		$ciphertext = base64_encode( $ciphertext ); // phpcs:ignore

		return $ciphertext;
	}

	/**
	 * Archiving Occurrences Table.
	 * Read from current DB and copy into Archive DB.
	 *
	 * @param array $args - Archive Database and limit by count OR by date.
	 *
	 * @return array|false|null
	 *
	 * @phpcs:disable Generic.Commenting.DocComment.MissingShort
	 */
	public function archive_occurrence( $args ) {
		$_wpdb = $this->get_connection();
		/** @var wpdbCustom $archive_db */
		$archive_db = $args['archive_db'];

		// Load data Occurrences from WP.
		$occurrence = new WSAL_Adapters_MySQL_Occurrence( $_wpdb );
		if ( ! $occurrence->is_installed() ) {
			return null;
		}
		if ( ! empty( $args['by_date'] ) ) {
			$sql = 'SELECT * FROM ' . $occurrence->get_table() . ' WHERE created_on <= ' . $args['by_date'];
		}

		if ( ! empty( $args['by_limit'] ) ) {
			$sql = 'SELECT occ.* FROM ' . $occurrence->get_table() . ' occ
			LEFT JOIN (SELECT id FROM ' . $occurrence->get_table() . ' order by created_on DESC limit ' . $args['by_limit'] . ') as ids
			on ids.id = occ.id
			WHERE ids.id IS NULL';
		}
		if ( ! empty( $args['last_created_on'] ) ) {
			$sql .= ' AND created_on > ' . $args['last_created_on'];
		}
		$sql .= ' ORDER BY created_on ASC';
		if ( ! empty( $args['limit'] ) ) {
			$sql .= ' LIMIT ' . $args['limit'];
		}
		$occurrences = $_wpdb->get_results( $sql, ARRAY_A );

		// Insert data to Archive DB.
		if ( ! empty( $occurrences ) ) {
			$last                    = end( $occurrences );
			$args['last_created_on'] = $last['created_on'];
			$args['occurrence_ids']  = array();

			$occurrence_new = new WSAL_Adapters_MySQL_Occurrence( $archive_db );

			$sql = 'INSERT INTO ' . $occurrence_new->get_table() . ' ( id, site_id, alert_id, created_on, client_ip, severity, object, event_type, user_agent, user_roles, username, user_id, session_id, post_status, post_type, post_id ) VALUES ';
			foreach ( $occurrences as $entry ) {

				$sql .= $archive_db->prepare(
					'( %d, %d, %d, %f, %s, %d, %s, %s, %s, %s, %s, %d, %s, %s, %s, %d ), ',
					intval( $entry['id'] ),
					intval( $entry['site_id'] ),
					intval( $entry['alert_id'] ),
					$entry['created_on'],
					$entry['client_ip'],
					$entry['severity'],
					$entry['object'],
					$entry['event_type'],
					$entry['user_agent'],
					$entry['user_roles'],
					$entry['username'],
					intval( $entry['user_id'] ),
					$entry['session_id'],
					$entry['post_status'],
					$entry['post_type'],
					intval( $entry['post_id'] )
				);

				$args['occurrence_ids'][] = intval( $entry['id'] );
			}

			$sql = rtrim( $sql, ', ' );
			$archive_db->query( $sql );

			return $args;
		} else {
			return false;
		}
	}

	/**
	 * Archiving Metadata Table.
	 * Read from current DB and copy into Archive DB.
	 *
	 * @param array $args - Archive Database and occurrences IDs.
	 *
	 * @return array|false|null
	 */
	public function archive_meta( $args ) {
		$_wpdb = $this->get_connection();
		/** @var wpdbCustom $archive_db */
		$archive_db = $args['archive_db'];

		// Load data Meta from WP.
		$meta = new WSAL_Adapters_MySQL_Meta( $_wpdb );
		if ( ! $meta->is_installed() ) {
			return null;
		}
		$s_occurrence_ids = implode( ', ', $args['occurrence_ids'] );
		$sql              = 'SELECT * FROM ' . $meta->get_table() . ' WHERE occurrence_id IN (' . $s_occurrence_ids . ')';
		$metadata         = $_wpdb->get_results( $sql, ARRAY_A );

		// Insert data to Archive DB.
		if ( ! empty( $metadata ) ) {
			$meta_new = new WSAL_Adapters_MySQL_Meta( $archive_db );

			$sql = 'INSERT INTO ' . $meta_new->get_table() . ' (occurrence_id, name, value) VALUES ';
			foreach ( $metadata as $entry ) {
				$sql .= $archive_db->prepare(
					'( %d, %s, %s ), ',
					intval( $entry['occurrence_id'] ),
					$entry['name'],
					$entry['value']
				);
			}
			$sql = rtrim( $sql, ', ' );
			$archive_db->query( $sql );

			return $args;
		} else {
			return false;
		}
	}

	/**
	 * Delete Occurrences and Metadata after archiving.
	 *
	 * @param array $args - Archive Database and occurrences IDs.
	 */
	public function delete_after_archive( $args ) {
		$_wpdb = $this->get_connection();

		$s_occurrence_ids = implode( ', ', $args['occurrence_ids'] );

		$occurrence = new WSAL_Adapters_MySQL_Occurrence( $_wpdb );
		$sql        = 'DELETE FROM ' . $occurrence->get_table() . ' WHERE id IN (' . $s_occurrence_ids . ')';
		$_wpdb->query( $sql );

		$meta = new WSAL_Adapters_MySQL_Meta( $_wpdb );
		$sql  = 'DELETE FROM ' . $meta->get_table() . ' WHERE occurrence_id IN (' . $s_occurrence_ids . ')';
		$_wpdb->query( $sql );
	}

	/**
	 * Purge plugin occurrence & meta tables.
	 *
	 * @return bool
	 */
	public function purge_activity() {
		// Get connection.
		$wpdb = $this->get_connection();

		// Get occurrence model.
		$occ       = new WSAL_Adapters_MySQL_Occurrence( $wpdb );
		$sql       = 'TRUNCATE ' . $occ->get_table();
		$query_occ = $wpdb->query( $sql ); // phpcs:ignore

		// Get meta model.
		$meta       = new WSAL_Adapters_MySQL_Meta( $wpdb );
		$sql        = 'TRUNCATE ' . $meta->get_table();
		$query_meta = $wpdb->query( $sql ); // phpcs:ignore

		// If both queries are successful, then return true.
		return $query_occ && $query_meta;
	}

	/**
	 * {@inheritDoc}
	 */
	public function query( $query ) {
		return $this->get_connection()->query( $query );
	}
}
