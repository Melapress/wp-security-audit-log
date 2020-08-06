<?php
/**
 * Class: MySQL DB Connector.
 *
 * MySQL Connector Class
 * It uses wpdb WordPress DB Class
 *
 * @package Wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MySQL Connector Class
 * It uses wpdb WordPress DB Class
 *
 * @package Wsal
 */
class WSAL_Connector_MySQLDB extends WSAL_Connector_AbstractConnector implements WSAL_Connector_ConnectorInterface {

	/**
	 * Connection Configuration.
	 *
	 * @var array
	 */
	protected $connectionConfig = null;

	/**
	 * Method: Constructor.
	 *
	 * @param array $connection_config - Connection config.
	 */
	public function __construct( $connection_config = null ) {
		$this->connectionConfig = $connection_config;
		parent::__construct( 'MySQL' );
		require_once $this->getAdaptersDirectory() . '/OptionAdapter.php';
	}

	/**
	 * Test the connection.
	 *
	 * @throws Exception - Connection failed.
	 * @return bool
	 */
	public function TestConnection() {
		error_reporting( E_ALL ^ ( E_NOTICE | E_WARNING | E_DEPRECATED ) );
		$connection_config = $this->connectionConfig;
		$password          = $this->decryptString( $connection_config['password'] );

		$new_wpdb = new wpdbCustom( $connection_config['user'], $password, $connection_config['name'], $connection_config['hostname'], $connection_config['is_ssl'], $connection_config['is_cc'], $connection_config['ssl_ca'], $connection_config['ssl_cert'], $connection_config['ssl_key'], true );

		if ( isset( $new_wpdb->error ) && isset( $new_wpdb->dbh ) ) {
			throw new Exception( $new_wpdb->dbh->error, $new_wpdb->dbh->errno );
		} elseif ( ! isset( $new_wpdb->dbh ) ) {
			$error_code = mysqli_connect_errno();

			if ( 1045 === $error_code ) {
				throw new Exception( __( 'Error establishing a database connection. DB username or password are not valid.' ), $error_code );
			} else {
				$error_message = mysqli_connect_error();
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
			throw new Exception( 'Error: Database ' . $connection_config['name'] . ' is unknown.', '1046' );
		} elseif ( ! $new_wpdb->has_connected ) {
			throw new Exception( 'Error establishing a database connection.' );
		} else {
			return true;
		}
	}

	/**
	 * Creates a connection and returns it
	 *
	 * @return wpdb Instance of WPDB.
	 */
	private function createConnection() {
		if ( ! empty( $this->connectionConfig ) ) {
			// TO DO: Use the provided connection config.
			$connection_config = $this->connectionConfig;
			$password          = $this->decryptString( $connection_config['password'] );
			$new_wpdb          = new wpdbCustom( $connection_config['user'], $password, $connection_config['name'], $connection_config['hostname'], $connection_config['is_ssl'], $connection_config['is_cc'], $connection_config['ssl_ca'], $connection_config['ssl_cert'], $connection_config['ssl_key'] );
			$new_wpdb->set_prefix( $connection_config['base_prefix'] );
			return $new_wpdb;
		} else {
			global $wpdb;
			return $wpdb;
		}
	}

	/**
	 * Returns a wpdb instance
	 *
	 * @return wpdb
	 */
	public function getConnection() {
		if ( ! empty( $this->connection ) ) {
			return $this->connection;
		} else {
			$this->connection = $this->createConnection();
			return $this->connection;
		}
	}

	/**
	 * Close DB connection
	 */
	public function closeConnection() {
		$current_wpdb = $this->getConnection();
		$result       = $current_wpdb->close();
		return $result;
	}

	/**
	 * Gets an adapter for the specified model.
	 *
	 * @param string $class_name - Class name.
	 * @return WSAL_Adapters_ActiveRecordInterface
	 */
	public function getAdapter( $class_name ) {
		$obj_name = $this->getAdapterClassName( $class_name );
		return new $obj_name( $this->getConnection() );
	}

	/**
	 * Gets an adapter class name for the specified model.
	 *
	 * @param string $class_name - Class name.
	 * @return string
	 */
	protected function getAdapterClassName( $class_name ) {
		return 'WSAL_Adapters_MySQL_' . $class_name;
	}

	/**
	 * Checks if the necessary tables are available
	 *
	 * @return bool true|false
	 */
	public function isInstalled() {
		$wpdb  = $this->getConnection();
		$table = $wpdb->base_prefix . 'wsal_occurrences';
		return $table === $wpdb->get_var( "SHOW TABLES LIKE '" . $table . "'" );
	}

	/**
	 * Checks if old version tables are available
	 *
	 * @return bool true|false
	 */
	public function canMigrate() {
		$wpdb  = $this->getConnection();
		$table = $wpdb->base_prefix . 'wordpress_auditlog_events';
		return $table === $wpdb->get_var( "SHOW TABLES LIKE '" . $table . "'" );
	}

	/**
	 * Install all DB tables.
	 *
	 * @param bool $is_external_database If true, some tables will not be created.
	 */
	public function installAll( $is_external_database = false ) {
		$plugin = WpSecurityAuditLog::GetInstance();
		$adapter_list = glob( $this->getAdaptersDirectory() . DIRECTORY_SEPARATOR . '*.php' );
		$adapter_list = apply_filters( 'wsal_install_apapters_list', $adapter_list );
		foreach ( $adapter_list as $file ) {
			$file_path  = explode( DIRECTORY_SEPARATOR, $file );
			$file_name  = $file_path[ count( $file_path ) - 1 ];
			$class_name = $this->getAdapterClassName( str_replace( 'Adapter.php', '', $file_name ) );

			if ( class_exists( $class_name ) ) {
				$class = new $class_name( $this->getConnection() );
			}

			if ( $is_external_database && $class instanceof WSAL_Adapters_MySQL_Session ) {
				//  sessions table should only ever exist only in local database
				continue;
			}

			if ( $is_external_database && $class instanceof WSAL_Adapters_MySQL_Option ) {
				//  options table should only ever exist only in local database
				continue;
			}

			if ( ! $is_external_database && $class instanceof WSAL_Adapters_MySQL_TmpUser ) {
				//  exclude the tmp_users table for local database
				continue;
			}

			if ( is_subclass_of( $class, 'WSAL_Adapters_MySQL_ActiveRecord' ) ) {
				$class->Install();
			}
		}
	}

	/**
	 * Uninstall all DB tables.
	 */
	public function uninstallAll() {
		$plugin = WpSecurityAuditLog::GetInstance();

		foreach ( glob( $this->getAdaptersDirectory() . DIRECTORY_SEPARATOR . '*.php' ) as $file ) {
			$file_path  = explode( DIRECTORY_SEPARATOR, $file );
			$file_name  = $file_path[ count( $file_path ) - 1 ];
			$class_name = $this->getAdapterClassName( str_replace( 'Adapter.php', '', $file_name ) );

			$class = new $class_name( $this->getConnection() );
			if ( is_subclass_of( $class, 'WSAL_Adapters_MySQL_ActiveRecord' ) ) {
				$class->Uninstall();
			}
		}
	}

	/**
	 * Increase occurrence ID
	 *
	 * @return integer MAX(id)
	 */
	private function GetIncreaseOccurrence() {
		$_wpdb          = $this->getConnection();
		$occurrence_new = new WSAL_Adapters_MySQL_Occurrence( $_wpdb );
		$sql            = 'SELECT MAX(id) FROM ' . $occurrence_new->GetTable();
		return (int) $_wpdb->get_var( $sql );
	}

	/**
	 * Migrate Metadata from WP DB to External DB.
	 *
	 * @param integer $index - Index.
	 * @param integer $limit - Limit.
	 */
	public function MigrateMeta( $index, $limit ) {
		$result = null;
		$offset = ( $index * $limit );
		global $wpdb;
		$_wpdb = $this->getConnection();
		// Add +1 because an alert is generated after delete the metadata table.
		$increase_occurrence_id = $this->GetIncreaseOccurrence() + 1;

		// Load data Meta from WP.
		$meta = new WSAL_Adapters_MySQL_Meta( $wpdb );
		if ( ! $meta->IsInstalled() ) {
			$result['empty'] = true;
			return $result;
		}
		$sql      = 'SELECT * FROM ' . $meta->GetWPTable() . ' LIMIT ' . $limit . ' OFFSET ' . $offset;
		$metadata = $wpdb->get_results( $sql, ARRAY_A );

		// Insert data to External DB.
		if ( ! empty( $metadata ) ) {
			$meta_new = new WSAL_Adapters_MySQL_Meta( $_wpdb );

			$index++;
			$sql = 'INSERT INTO ' . $meta_new->GetTable() . ' (occurrence_id, name, value) VALUES ';
			foreach ( $metadata as $entry ) {
				$occurrence_id = intval( $entry['occurrence_id'] ) + $increase_occurrence_id;
				$sql          .= '(' . $occurrence_id . ', \'' . $entry['name'] . '\', \'' . str_replace( array( "'", "\'" ), "\'", $entry['value'] ) . '\'), ';
			}
			$sql = rtrim( $sql, ', ' );
			$_wpdb->query( $sql );

			$result['complete'] = false;
		} else {
			$result['complete'] = true;
			$this->DeleteAfterMigrate( $meta );
		}
		$result['index'] = $index;
		return $result;
	}

	/**
	 * Migrate Occurrences from WP DB to External DB.
	 *
	 * @param integer $index - Index.
	 * @param integer $limit - Limit.
	 */
	public function MigrateOccurrence( $index, $limit ) {
		$result = null;
		$offset = ( $index * $limit );
		global $wpdb;
		$_wpdb = $this->getConnection();

		// Load data Occurrences from WP.
		$occurrence = new WSAL_Adapters_MySQL_Occurrence( $wpdb );
		if ( ! $occurrence->IsInstalled() ) {
			$result['empty'] = true;
			return $result;
		}
		$sql             = 'SELECT * FROM ' . $occurrence->GetWPTable() . ' LIMIT ' . $limit . ' OFFSET ' . $offset;
		$occurrences     = $wpdb->get_results( $sql, ARRAY_A );
		$result['count'] = count( $occurrences );

		// Insert data to External DB.
		if ( ! empty( $occurrences ) ) {
			$occurrence_new = new WSAL_Adapters_MySQL_Occurrence( $_wpdb );

			$index++;
			$sql = 'INSERT INTO ' . $occurrence_new->GetTable() . ' (site_id, alert_id, created_on, is_read) VALUES ';
			foreach ( $occurrences as $entry ) {
				$sql .= '(' . $entry['site_id'] . ', ' . $entry['alert_id'] . ', ' . $entry['created_on'] . ', ' . $entry['is_read'] . '), ';
			}
			$sql = rtrim( $sql, ', ' );
			$_wpdb->query( $sql );

			$result['complete'] = false;
		} else {
			$result['complete'] = true;
			$this->DeleteAfterMigrate( $occurrence );
		}
		$result['index'] = $index;
		return $result;
	}

	/**
	 * Migrate Back Occurrences from External DB to WP DB.
	 *
	 * @param integer $index - Index.
	 * @param integer $limit - Limit.
	 *
	 * @return array
	 */
	public function MigrateBackOccurrence( $index, $limit ) {
		$result = null;
		$offset = ( $index * $limit );
		global $wpdb;
		$_wpdb = $this->getConnection();

		// Load data Occurrences from External DB.
		$occurrence = new WSAL_Adapters_MySQL_Occurrence( $_wpdb );
		if ( ! $occurrence->IsInstalled() ) {
			$result['empty'] = true;
			return $result;
		}
		$sql             = 'SELECT * FROM ' . $occurrence->GetTable() . ' LIMIT ' . $limit . ' OFFSET ' . $offset;
		$occurrences     = $_wpdb->get_results( $sql, ARRAY_A );
		$result['count'] = count( $occurrences );

		// Insert data to WP.
		if ( ! empty( $occurrences ) ) {
			$occurrence_wp = new WSAL_Adapters_MySQL_Occurrence( $wpdb );

			$index++;
			$sql = 'INSERT INTO ' . $occurrence_wp->GetWPTable() . ' (id, site_id, alert_id, created_on, is_read) VALUES ';
			foreach ( $occurrences as $entry ) {
				$sql .= '(' . $entry['id'] . ', ' . $entry['site_id'] . ', ' . $entry['alert_id'] . ', ' . $entry['created_on'] . ', ' . $entry['is_read'] . '), ';
			}
			$sql = rtrim( $sql, ', ' );
			$wpdb->query( $sql );

			$result['complete'] = false;
		} else {
			$result['complete'] = true;
		}
		$result['index'] = $index;
		return $result;
	}

	/**
	 * Migrate Back Metadata from External DB to WP DB.
	 *
	 * @param integer $index - Index.
	 * @param integer $limit - Limit.
	 */
	public function MigrateBackMeta( $index, $limit ) {
		$result = null;
		$offset = ( $index * $limit );
		global $wpdb;
		$_wpdb = $this->getConnection();

		// Load data Meta from External DB.
		$meta = new WSAL_Adapters_MySQL_Meta( $_wpdb );
		if ( ! $meta->IsInstalled() ) {
			$result['empty'] = true;
			return $result;
		}
		$sql      = 'SELECT * FROM ' . $meta->GetTable() . ' LIMIT ' . $limit . ' OFFSET ' . $offset;
		$metadata = $_wpdb->get_results( $sql, ARRAY_A );

		// Insert data to WP.
		if ( ! empty( $metadata ) ) {
			$meta_wp = new WSAL_Adapters_MySQL_Meta( $wpdb );

			$index++;
			$sql = 'INSERT INTO ' . $meta_wp->GetWPTable() . ' (occurrence_id, name, value) VALUES ';
			foreach ( $metadata as $entry ) {
				$sql .= '(' . $entry['occurrence_id'] . ', \'' . $entry['name'] . '\', \'' . str_replace( array( "'", "\'" ), "\'", $entry['value'] ) . '\'), ';
			}
			$sql = rtrim( $sql, ', ' );
			$wpdb->query( $sql );

			$result['complete'] = false;
		} else {
			$result['complete'] = true;
		}
		$result['index'] = $index;
		return $result;
	}

	/**
	 * Delete after Migrate alerts.
	 *
	 * @param object $record - Type of record.
	 */
	private function DeleteAfterMigrate( $record ) {
		global $wpdb;
		$sql = 'DROP TABLE IF EXISTS ' . $record->GetTable();
		$wpdb->query( $sql );
	}

	/**
	 * Encrypt plain text.
	 * Encrypt string, before saves it to the DB.
	 *
	 * @param  string $plaintext - Plain text that is going to be encrypted.
	 * @return string
	 * @since  2.6.3
	 */
	public function encryptString( $plaintext ) {

		$ciphertext     = false;
		$encrypt_method = 'AES-256-CBC';
		$secret_key     = $this->truncateKey();
		$secret_iv      = $this->get_openssl_iv();

		// Hash the key.
		$key = hash( 'sha256', $secret_key );

		// iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning.
		$iv = substr( hash( 'sha256', $secret_iv ), 0, 16 );

		$ciphertext = openssl_encrypt( $plaintext, $encrypt_method, $key, 0, $iv );
		$ciphertext = base64_encode( $ciphertext );

		return $ciphertext;
	}

	/**
	 * Encrypt plain text - Fallback.
	 *
	 * @param string $plaintext - Plain text that is going to be encrypted.
	 * @deprecated 3.2.3.3
	 */
	public function encryptString_fallback( $plaintext ) {
		$wsal = WpSecurityAuditLog::GetInstance();
		$wsal->wsal_deprecate( __METHOD__, '3.2.3.3' );
	}

	/**
	 * Decrypt the encrypted string.
	 * Decrypt string, after reads it from the DB.
	 *
	 * @param  string $ciphertext_base64 - encrypted string.
	 * @return string
	 * @since  2.6.3
	 */
	public function decryptString( $ciphertext_base64 ) {

		$plaintext      = false;
		$encrypt_method = 'AES-256-CBC';
		$secret_key     = $this->truncateKey();
		$secret_iv      = $this->get_openssl_iv();

		// Hash the key.
		$key = hash( 'sha256', $secret_key );

		// iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning.
		$iv = substr( hash( 'sha256', $secret_iv ), 0, 16 );

		$plaintext = openssl_decrypt( base64_decode( $ciphertext_base64 ), $encrypt_method, $key, 0, $iv );

		return $plaintext;
	}

	/**
	 * Decrypt the encrypted string - Fallback.
	 *
	 * @param string $ciphertext_base64 - Encrypted string.
	 * @deprecated 3.2.3.3
	 */
	public function decryptString_fallback( $ciphertext_base64 ) {
		$wsal = WpSecurityAuditLog::GetInstance();
		$wsal->wsal_deprecate( __METHOD__, '3.2.3.3' );
	}

	/**
	 * Mirroring Occurrences and Metadata Tables.
	 * Read from current DB and copy into Mirroring DB.
	 *
	 * @param array $args - Archive Database and limit by date.
	 */
	public function MirroringAlertsToDB( $args ) {
		$last_event          = null;
		$first_occurrence_id = null;
		$_wpdb               = $this->getConnection();
		$mirroring_db        = $args['mirroring_db'];

		// Load data Occurrences from WP.
		$occurrence = new WSAL_Adapters_MySQL_Occurrence( $_wpdb );
		if ( ! $occurrence->IsInstalled() ) {
			return null;
		}

		if ( isset( $args['filter'] ) && 'event-codes' === $args['filter'] && isset( $args['events'] ) ) {
			$sql = 'SELECT * FROM ' . $occurrence->GetTable() . ' WHERE id > ' . $args['last_occ_id'] . ' AND created_on > ' . $args['last_created_on'] . ' AND alert_id IN ' . $args['events'];
		} elseif ( isset( $args['filter'] ) && 'except-codes' === $args['filter'] && isset( $args['events'] ) ) {
			$sql = 'SELECT * FROM ' . $occurrence->GetTable() . ' WHERE id > ' . $args['last_occ_id'] . ' AND created_on > ' . $args['last_created_on'] . ' AND alert_id NOT IN ' . $args['events'];
		} else {
			$sql = 'SELECT * FROM ' . $occurrence->GetTable() . ' WHERE id > ' . $args['last_occ_id'] . ' AND created_on > ' . $args['last_created_on'];
		}

		// Query the events.
		$occurrences = $_wpdb->get_results( $sql, ARRAY_A );

		if ( ! empty( $occurrences ) ) {
			$occurrence_new = new WSAL_Adapters_MySQL_Occurrence( $mirroring_db );
			$sql            = 'INSERT INTO ' . $occurrence_new->GetTable() . ' (id, site_id, alert_id, created_on, is_read) VALUES ';

			foreach ( $occurrences as $entry ) {
				$sql .= '(' . $entry['id'] . ', ' . $entry['site_id'] . ', ' . $entry['alert_id'] . ', ' . $entry['created_on'] . ', ' . $entry['is_read'] . '), ';

				// Save the first id.
				if ( is_null( $first_occurrence_id ) ) {
					$first_occurrence_id = $entry['id'];
				}

				$last_event = $entry;
			}

			$sql = rtrim( $sql, ', ' );
			$mirroring_db->query( $sql );
		}

		// Load data Meta from WP.
		$meta = new WSAL_Adapters_MySQL_Meta( $_wpdb );

		if ( ! $meta->IsInstalled() ) {
			return null;
		}

		if ( ! empty( $first_occurrence_id ) ) {
			$sql      = 'SELECT * FROM ' . $meta->GetTable() . ' WHERE occurrence_id >= ' . $first_occurrence_id;
			$metadata = $_wpdb->get_results( $sql, ARRAY_A );

			if ( ! empty( $metadata ) ) {
				$meta_new = new WSAL_Adapters_MySQL_Meta( $mirroring_db );
				$sql      = 'INSERT INTO ' . $meta_new->GetTable() . ' (occurrence_id, name, value) VALUES ';

				foreach ( $metadata as $entry ) {
					$sql .= '(' . $entry['occurrence_id'] . ', \'' . $entry['name'] . '\', \'' . str_replace( array( "'", "\'" ), "\'", $entry['value'] ) . '\'), ';
				}

				$sql = rtrim( $sql, ', ' );
				$mirroring_db->query( $sql );
			}
		}

		return $last_event;
	}

	/**
	 * Archiving Occurrences Table.
	 * Read from current DB and copy into Archive DB.
	 *
	 * @param array $args - Archive Database and limit by count OR by date.
	 */
	public function ArchiveOccurrence( $args ) {
		$_wpdb      = $this->getConnection();
		$archive_db = $args['archive_db'];

		// Load data Occurrences from WP.
		$occurrence = new WSAL_Adapters_MySQL_Occurrence( $_wpdb );
		if ( ! $occurrence->IsInstalled() ) {
			return null;
		}
		if ( ! empty( $args['by_date'] ) ) {
			$sql = 'SELECT * FROM ' . $occurrence->GetTable() . ' WHERE created_on <= ' . $args['by_date'];
		}

		if ( ! empty( $args['by_limit'] ) ) {
			$sql = 'SELECT occ.* FROM ' . $occurrence->GetTable() . ' occ
			LEFT JOIN (SELECT id FROM ' . $occurrence->GetTable() . ' order by created_on DESC limit ' . $args['by_limit'] . ') as ids
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
			$args['occurence_ids']   = array();

			$occurrence_new = new WSAL_Adapters_MySQL_Occurrence( $archive_db );

			$sql = 'INSERT INTO ' . $occurrence_new->GetTable() . ' (id, site_id, alert_id, created_on, is_read) VALUES ';
			foreach ( $occurrences as $entry ) {
				$sql                    .= '(' . $entry['id'] . ', ' . $entry['site_id'] . ', ' . $entry['alert_id'] . ', ' . $entry['created_on'] . ', ' . $entry['is_read'] . '), ';
				$args['occurence_ids'][] = $entry['id'];
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
	 */
	public function ArchiveMeta( $args ) {
		$_wpdb      = $this->getConnection();
		$archive_db = $args['archive_db'];

		// Load data Meta from WP.
		$meta = new WSAL_Adapters_MySQL_Meta( $_wpdb );
		if ( ! $meta->IsInstalled() ) {
			return null;
		}
		$s_occurence_ids = implode( ', ', $args['occurence_ids'] );
		$sql             = 'SELECT * FROM ' . $meta->GetTable() . ' WHERE occurrence_id IN (' . $s_occurence_ids . ')';
		$metadata        = $_wpdb->get_results( $sql, ARRAY_A );

		// Insert data to Archive DB.
		if ( ! empty( $metadata ) ) {
			$meta_new = new WSAL_Adapters_MySQL_Meta( $archive_db );

			$sql = 'INSERT INTO ' . $meta_new->GetTable() . ' (occurrence_id, name, value) VALUES ';
			foreach ( $metadata as $entry ) {
				$sql .= '(' . $entry['occurrence_id'] . ', \'' . $entry['name'] . '\', \'' . str_replace( array( "'", "\'" ), "\'", $entry['value'] ) . '\'), ';
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
	public function DeleteAfterArchive( $args ) {
		$_wpdb      = $this->getConnection();
		$archive_db = $args['archive_db'];

		$s_occurence_ids = implode( ', ', $args['occurence_ids'] );

		$occurrence = new WSAL_Adapters_MySQL_Occurrence( $_wpdb );
		$sql        = 'DELETE FROM ' . $occurrence->GetTable() . ' WHERE id IN (' . $s_occurence_ids . ')';
		$_wpdb->query( $sql );

		$meta = new WSAL_Adapters_MySQL_Meta( $_wpdb );
		$sql  = 'DELETE FROM ' . $meta->GetTable() . ' WHERE occurrence_id IN (' . $s_occurence_ids . ')';
		$_wpdb->query( $sql );
	}

	/**
	 * Truncate string longer than 32 characters.
	 * Authentication Unique Key
	 *
	 * @see wp-config.php
	 * @return string AUTH_KEY
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
	 * Purge plugin occurrence & meta tables.
	 *
	 * @return bool
	 */
	public function purge_activity() {
		// Get connection.
		$wpdb = $this->getConnection();

		// Get occurrence model.
		$occ       = new WSAL_Adapters_MySQL_Occurrence( $wpdb );
		$sql       = 'TRUNCATE ' . $occ->GetTable();
		$query_occ = $wpdb->query( $sql );

		// Get meta model.
		$meta       = new WSAL_Adapters_MySQL_Meta( $wpdb );
		$sql        = 'TRUNCATE ' . $meta->GetTable();
		$query_meta = $wpdb->query( $sql );

		// If both queries are successful, then return true.
		return $query_occ && $query_meta;
	}
}
