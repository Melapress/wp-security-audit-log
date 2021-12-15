<?php
/**
 * Adapter: Occurrence.
 *
 * MySQL database Occurrence class.
 *
 * @package wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MySQL database Occurrence class.
 *
 * MySQL wsal_occurrences table used for to store the alerts.
 *
 * @package wsal
 */
class WSAL_Adapters_MySQL_Occurrence extends WSAL_Adapters_MySQL_ActiveRecord implements WSAL_Adapters_OccurrenceInterface {

	/**
	 * @inheritDoc
	 */
	protected $_table = 'wsal_occurrences';

	/**
	 * Contains primary key column name, override as required.
	 *
	 * @var string
	 */
	protected $_idkey = 'id';

	/**
	 * Occurrence id.
	 *
	 * @var int
	 */
	public $id = 0;

	/**
	 * Site id.
	 *
	 * @var int
	 */
	public $site_id = 0;

	/**
	 * Alert id.
	 *
	 * @var int
	 */
	public $alert_id = 0;

	/**
	 * Created on.
	 *
	 * @var string
	 */
	public $created_on = 0.0;

	/**
	 * Client IP address.
	 *
	 * @var string
	 * @since 4.4.0
	 */
	public $client_ip = '';

	/**
	 * Severity.
	 *
	 * @var int
	 * @since 4.4.0
	 */
	public $severity = '';

	/**
	 * Event object.
	 *
	 * @var string
	 * @since 4.4.0
	 */
	public $object = '';

	/**
	 * Event type.
	 *
	 * @var string
	 * @since 4.4.0
	 */
	public $event_type = '';

	/**
	 * User agent string.
	 *
	 * @var string
	 * @since 4.4.0
	 */
	public $user_agent = '';

	/**
	 * Comma separated user roles of the user belonging to the event.
	 *
	 * @var string
	 * @since 4.4.0
	 */
	public $user_roles = '';

	/**
	 * Username of the user belonging to the event.
	 *
	 * @var string
	 * @since 4.4.0
	 */
	public $username = null;

	/**
	 * User ID of the user belonging to the event.
	 *
	 * @var int
	 * @since 4.4.0
	 */
	public $user_id = null;

	/**
	 * Session ID.
	 *
	 * @var string
	 * @since 4.4.0
	 */
	public $session_id = '';

	/**
	 * Post status.
	 *
	 * @var string
	 * @since 4.4.0
	 */
	public $post_status = '';

	/**
	 * Post status.
	 *
	 * @var string
	 * @since 4.4.0
	 */
	public $post_type = '';

	/**
	 * Post ID.
	 *
	 * @var int
	 * @since 4.4.0
	 */
	public $post_id = 0;

	/**
	 * @inheritDoc
	 */
	protected function GetTableOptions() {
		return parent::GetTableOptions() . ',' . PHP_EOL
		       . '    KEY site_alert_created (site_id,alert_id,created_on)';
	}

	/**
	 * @inheritDoc
	 *
	 * @return WSAL_Models_Occurrence
	 */
	public function GetModel() {
		$result = new WSAL_Models_Occurrence();
		$result->setAdapter( $this );

		return $result;
	}

	/**
	 * @inheritDoc
	 */
	public function GetMultiMeta( $occurrence ) {
		$meta = new WSAL_Adapters_MySQL_Meta( $this->connection );

		return $meta->LoadArray( 'occurrence_id = %d', array( $occurrence->id ) );
	}

	/**
	 * @inheritDoc
	 */
	public function GetNamedMeta( $occurrence, $name ) {
		$meta = new WSAL_Adapters_MySQL_Meta( $this->connection );

		return $meta->LoadByNameAndOccurrenceId( $name, $occurrence->id );
	}

	/**
	 * @inheritDoc
	 */
	public function GetFirstNamedMeta( $occurrence, $names ) {
		$meta  = new WSAL_Adapters_MySQL_Meta( $this->connection );
		$query = '(' . str_repeat( 'name = %s OR ', count( $names ) ) . '0)';
		$query = 'occurrence_id = %d AND ' . $query . ' ORDER BY name DESC LIMIT 1';
		array_unshift( $names, $occurrence->id ); // Prepend args with occurrence id.

		return $meta->getModel()->LoadData( $meta->Load( $query, $names ) );
	}

	/**
	 * @inheritDoc
	 */
	public function CheckKnownUsers( $args = array() ) {
		return $this->LoadMultiQuery(
			"SELECT * FROM `{$this->GetTable()}` "
			. "	WHERE client_ip = %s "
			. " AND username = %s "
			. " AND alert_id = %d "
			. " AND site_id = %d "
			. " AND ( created_on BETWEEN %d AND %d );",
			$args
		);
	}

	/**
	 * @inheritDoc
	 */
	public function CheckUnknownUsers( $args = array() ) {
		return $this->LoadMultiQuery(
			"SELECT * FROM `{$this->GetTable()}` "
			. " WHERE client_ip = %s "
			. " AND alert_id = %d "
			. " AND site_id = %d "
			. " AND ( created_on BETWEEN %d AND %d );",
			$args
		);
	}

	/**
	 * @inheritDoc
	 */
	public function check_alert_1003( $args = array() ) {
		return $this->LoadMultiQuery(
			'SELECT * FROM `' . $this->GetTable() . '`
			WHERE (alert_id = %d)
			AND (site_id = %d)
			AND (created_on BETWEEN %d AND %d);',
			$args
		);
	}

	/**
	 * @inheritDoc
	 */
	public function GetByPostID( $post_id ) {
		return $this->LoadMultiQuery(
			"SELECT occurrence.* FROM `{$this->GetTable()}` "
			. " WHERE post_id = %d "
			. " ORDER BY created_on DESC;",
			array( $post_id )
		);
	}

	/**
	 * Create relevant indexes on the occurrence table.
	 */
	public function create_indexes() {
		$index_exists  = false;
		$db_connection = $this->get_connection();
		// check if an index exists.
		if ( $db_connection->query( 'SELECT COUNT(1) IndexIsThere FROM INFORMATION_SCHEMA.STATISTICS WHERE table_schema=DATABASE() AND table_name="' . $this->GetTable() . '" AND index_name="created_on"' ) ) {
			// query succeeded, does index exist?
			$index_exists = ( isset( $db_connection->last_result[0]->IndexIsThere ) ) ? $db_connection->last_result[0]->IndexIsThere : false;
		}
		// if no index exists then make one.
		if ( ! $index_exists ) {
			$db_connection->query( 'CREATE INDEX created_on ON ' . $this->GetTable() . ' (created_on)' );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function get_all_with_meta_to_migrate( $limit ) {
		$meta_adapter = new WSAL_Adapters_MySQL_Meta( $this->connection );

		$meta_keys = array_map( function ( $value ) {
			return '"' . $value . '"';
		}, array_keys( WSAL_Models_Occurrence::$migrated_meta ) );

		return $this->LoadMultiQuery(
			"SELECT o.* FROM `{$this->GetTable()}` o "
			. " INNER JOIN `{$meta_adapter->GetTable()}` m "
			. " ON m.occurrence_id = o.id "
			. " WHERE m.name IN (" . implode( ',', $meta_keys ) . ") "
			. " GROUP BY o.id "
			. " ORDER BY created_on DESC "
			. " LIMIT 0, %d;",
			array( $limit )
		);
	}

	/**
	 * Get distinct values of IPs.
	 *
	 * @param int $limit - (Optional) Limit.
	 *
	 * @return array - Distinct values of IPs.
	 */
	public function GetMatchingIPs( $limit = null ) {
		$_wpdb = $this->connection;
		$sql   = "SELECT DISTINCT client_ip FROM {$this->GetTable()}";
		if ( ! is_null( $limit ) ) {
			$sql .= ' LIMIT ' . $limit;
		}
		$ips    = $_wpdb->get_col( $sql );
		$result = array();
		foreach ( $ips as $ip ) {
			if ( 0 === strlen( trim( $ip ) ) ) {
				continue;
			}
			array_push( $result, $ip );
		}

		return array_unique( $result );
	}

	/**
	 * @inheritDoc
	 *
	 * username and user_id columns have to be added manually because function get_object_vars doesn't return
	 * uninitialised properties. These two cannot have the default value set because some database queries rely on
	 * having null values in the database.
	 *
	 * @since 4.4.0
	 */
	public function GetColumns() {
		if ( ! empty( $this->_column_cache ) ) {
			return $this->_column_cache;
		}

		$result = parent::GetColumns();
		foreach ( [ 'username', 'user_id' ] as $extra_column ) {
			if ( ! in_array( $extra_column, $result ) ) {
				array_push( $result, $extra_column );
			}
		}

		$this->_column_cache = $result;

		return $result;
	}

	/**
	 * @inheritDoc
	 *
	 * @param WSAL_Models_Occurrence $copy
	 *
	 * @since 4.4.0
	 */
	protected function _GetSqlColumnDefinition( $copy, $key ) {
		if ( 'username' === $key ) {
			return " username VARCHAR(255) NULL, ";
		}

		if ( 'user_id' === $key ) {
			return " user_id BIGINT NULL, ";
		}

		if ( is_string( $copy->$key ) ) {
			return $key . ' VARCHAR(255) NOT NULL,' . PHP_EOL;
		}

		return parent::_GetSqlColumnDefinition( $copy, $key );
	}
}
