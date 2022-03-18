<?php
/**
 * Adapter: Query.
 *
 * MySQL database Query class.
 *
 * @package wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MySQL database Query class.
 *
 * The SQL query is created in this class, here the SQL is filled with
 * the arguments.
 *
 * @package wsal
 * @subpackage adapters
 */
class WSAL_Adapters_MySQL_Query implements WSAL_Adapters_QueryInterface {

	/**
	 * DB Connection
	 *
	 * @var wpdb
	 */
	protected $connection;

	/**
	 * Method: Constructor.
	 *
	 * @param array $connection - Connection array.
	 */
	public function __construct( $connection ) {
		$this->connection = $connection;
	}

	/**
	 * Get the SQL filled with the args.
	 *
	 * @param WSAL_Models_Query $query - Query object.
	 * @param array             $args  - Args of the query.
	 *
	 * @return string Generated sql.
	 */
	protected function get_sql( $query, &$args = array() ) {
		$conditions       = $query->get_conditions();
		$search_condition = $this->search_condition( $query );
		$s_where_clause   = '';
		foreach ( $conditions as $field_name => $field_value ) {
			if ( empty( $s_where_clause ) ) {
				$s_where_clause .= ' WHERE ';
			} else {
				$s_where_clause .= ' AND ';
			}

			if ( is_array( $field_value ) ) {
				$sub_where_clause = '(';
				foreach ( $field_value as $or_field_name => $or_field_value ) {
					if ( is_array( $or_field_value ) ) {
						foreach ( $or_field_value as $value ) {
							if ( '(' != $sub_where_clause ) { // phpcs:ignore
								$sub_where_clause .= ' OR ';
							}
							$sub_where_clause .= $or_field_name;
							$args[]            = $value;
						}
					} else {
						if ( '(' != $sub_where_clause ) { // phpcs:ignore
							$sub_where_clause .= ' OR ';
						}
						$sub_where_clause .= $or_field_name;
						$args[]            = $or_field_value;
					}
				}
				$sub_where_clause .= ')';
				$s_where_clause   .= $sub_where_clause;
			} else {
				$s_where_clause .= $field_name;
				$args[]          = $field_value;
			}
		}

		$from_data_sets = $query->get_from();
		$columns        = $query->get_columns();
		$order_bys      = $query->get_order_by();

		$s_limit_clause = '';
		if ( $query->get_limit() ) {
			$s_limit_clause .= ' LIMIT ';
			if ( $query->get_offset() ) {
				$s_limit_clause .= $query->get_offset() . ', ';
			}
			$s_limit_clause .= $query->get_limit();
		}

		$join_clause = '';
		if ( $query->has_meta_join() ) {
			$meta        = new WSAL_Adapters_MySQL_Meta( $this->connection );
			$occurrence  = new WSAL_Adapters_MySQL_Occurrence( $this->connection );
			$join_clause = ' LEFT JOIN ' . $meta->get_table() . ' AS meta ON meta.occurrence_id = ' . $occurrence->get_table() . '.id ';
		}
		$fields = ( empty( $columns ) ) ? $from_data_sets[0] . '.*' : implode( ',', $columns );
		if ( ! empty( $search_condition ) ) {
			$args[] = $search_condition['args'];
		}

		$search_statement = '';
		if ( ! empty( $search_condition ) ) {
			$search_statement = empty( $s_where_clause ) ? ' WHERE ' . $search_condition['sql'] : ' AND ' . $search_condition['sql'];
		}

		$sql = 'SELECT ' . $fields
			. ' FROM ' . implode( ',', $from_data_sets )
			. $join_clause
			. $s_where_clause
			. $search_statement
			// @todo GROUP BY goes here
			. ( ! empty( $order_bys ) ? ( ' ORDER BY ' . implode( ', ', array_keys( $order_bys ) ) . ' ' . implode( ', ', array_values( $order_bys ) ) ) : '' )
			. $s_limit_clause;
		return $sql;
	}

	/**
	 * Get an instance of the ActiveRecord Adapter.
	 *
	 * @return WSAL_Adapters_MySQL_ActiveRecord
	 */
	protected function get_active_record_adapter() {
		return new WSAL_Adapters_MySQL_ActiveRecord( $this->connection );
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute_query( $query ) {
		$args = array();
		$sql  = $this->get_sql( $query, $args );

		$args = array_filter(
			$args,
			function ( $item ) {
				return ( '' !== $item );
			}
		);

		$occurrence_adapter = $query->get_connector()->get_adapter( 'Occurrence' );

		if ( in_array( $occurrence_adapter->get_table(), $query->get_from(), true ) ) {
			return $occurrence_adapter->load_multi( $sql, $args );
		} else {
			return $this->get_active_record_adapter()->load_multi( $sql, $args );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function count( $query ) {
		// Back up columns, use COUNT as default column and generate sql.
		$cols = $query->get_columns();
		$query->clear_columns();
		$query->add_column( 'COUNT(*)' );

		$args = array();
		$sql  = $this->get_sql( $query, $args );

		// Restore columns.
		$query->set_columns( $cols );
		// Execute query and return result.
		return $this->get_active_record_adapter()->count_query( $sql, $args );
	}

	/**
	 * Count DELETE query
	 *
	 * @param object $query - Query object.
	 * @return integer counting records.
	 */
	public function count_delete( $query ) {
		$result = $this->get_sql_delete( $query, true );
		// Execute query and return result.
		return $this->get_active_record_adapter()->count_query( $result['sql'], $result['args'] );
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete( $query ) {
		$result = $this->get_sql_delete( $query );
		$this->delete_metas( $query, $result['args'] );
		return $this->get_active_record_adapter()->delete_query( $result['sql'], $result['args'] );
	}

	/**
	 * Load occurrence IDs then delete Metadata by occurrence_id
	 *
	 * @param WSAL_Models_Query $query - Query object.
	 * @param array             $args  - Args of the query.
	 */
	public function delete_metas( $query, $args ) {
		// Back up columns, use COUNT as default column and generate sql.
		$cols = $query->get_columns();
		$query->clear_columns();
		$query->add_column( 'id' );
		$sql = $this->get_sql( $query );
		// Restore columns.
		$query->set_columns( $cols );

		$_wpdb   = $this->connection;
		$occ_ids = array();
		$sql     = ( ! empty( $args ) ? $_wpdb->prepare( $sql, $args ) : $sql );
		foreach ( $_wpdb->get_results( $sql, ARRAY_A ) as $data ) {
			$occ_ids[] = $data['id'];
		}
		$meta = new WSAL_Adapters_MySQL_Meta( $this->connection );
		$meta->delete_by_occurrence_ids( $occ_ids );
	}

	/**
	 * Get the DELETE query SQL filled with the args.
	 *
	 * @param WSAL_Models_Query $query     - Query object.
	 * @param bool              $get_count - Get count.
	 *
	 * @return string - Generated sql.
	 */
	public function get_sql_delete( $query, $get_count = false ) {
		$result = array();
		$args   = array();
		// Back up columns, remove them for DELETE and generate sql.
		$cols = $query->get_columns();
		$query->clear_columns();

		$conditions = $query->get_conditions();

		$s_where_clause = '';
		foreach ( $conditions as $field_name => $field_value ) {
			if ( empty( $s_where_clause ) ) {
				$s_where_clause .= ' WHERE ';
			} else {
				$s_where_clause .= ' AND ';
			}
			$s_where_clause .= $field_name;
			$args[]          = $field_value;
		}

		$from_data_sets = $query->get_from();
		$order_bys      = $query->get_order_by();

		$s_limit_clause = '';
		if ( $query->get_limit() ) {
			$s_limit_clause .= ' LIMIT ';
			if ( $query->get_offset() ) {
				$s_limit_clause .= $query->get_offset() . ', ';
			}
			$s_limit_clause .= $query->get_limit();
		}
		$result['sql']  = ( $get_count ? 'SELECT COUNT(*) FROM ' : 'DELETE FROM ' )
			. implode( ',', $from_data_sets )
			. $s_where_clause
			. ( ! empty( $order_bys ) ? ( ' ORDER BY ' . implode( ', ', array_keys( $order_bys ) ) . ' ' . implode( ', ', array_values( $order_bys ) ) ) : '' )
			. $s_limit_clause;
		$result['args'] = $args;
		// Restore columns.
		$query->set_columns( $cols );

		return $result;
	}

	/**
	 * Search by alert code OR by Metadata value.
	 *
	 * @param WSAL_Models_Query $query - Query object.
	 */
	public function search_condition( $query ) {
		$condition = $query->get_search_condition();
		if ( empty( $condition ) ) {
			return null;
		}

		$search_conditions = array();
		$meta              = new WSAL_Adapters_MySQL_Meta( $this->connection );
		$occurrence        = new WSAL_Adapters_MySQL_Occurrence( $this->connection );
		if ( is_numeric( $condition ) && 4 === strlen( $condition ) ) {
			$search_conditions['sql'] = $occurrence->get_table() . '.alert_id LIKE %s';
		} else {
			$search_conditions['sql'] = $occurrence->get_table() . '.id IN (
				SELECT DISTINCT occurrence_id
					FROM ' . $meta->get_table() . '
					WHERE TRIM(BOTH "\"" FROM value) LIKE %s
				)';
		}
		$search_conditions['args'] = '%' . $condition . '%';
		return $search_conditions;
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_connected() {
		return ( $this->connection && $this->connection->has_connected );
	}

	/**
	 * {@inheritDoc}
	 */
	public function Execute( $query ) {
		return $this->execute_query( $query );
	}
}
