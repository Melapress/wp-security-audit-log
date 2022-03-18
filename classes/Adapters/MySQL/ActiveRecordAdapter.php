<?php
/**
 * Adapter: Active Record.
 *
 * MySQL database ActiveRecord class.
 *
 * @package wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MySQL database ActiveRecord class.
 *
 * MySQL generic table used for Save, Read, Create or Delete
 * elements in the Database.
 *
 * There are also the functions used in the Report Add-On to get the reports.
 *
 * @package wsal
 */
class WSAL_Adapters_MySQL_ActiveRecord implements WSAL_Adapters_ActiveRecordInterface {

	/**
	 * DB Connection
	 *
	 * @var array
	 */
	protected $connection;

	/**
	 * Contains the table name
	 *
	 * @var string
	 */
	protected $table;

	/**
	 * Contains primary key column name, override as required.
	 *
	 * @var string
	 */
	protected $idkey = '';

	/**
	 * Local cache for a list of columns.
	 *
	 * @var string[]
	 */
	protected $column_cache = array();

	/**
	 * Method: Constructor.
	 *
	 * @param object $connection - DB connection object.
	 */
	public function __construct( $connection ) {
		$this->connection = $connection;
	}

	/**
	 * Works out the grouping data entities for given statistical report type.
	 *
	 * @param int    $statistics_report_type Statistical report type.
	 * @param string $grouping_period        Period to use for data grouping.
	 *
	 * @return string[]|null
	 * @since 4.4.0
	 */
	public static function get_grouping( $statistics_report_type, $grouping_period ) {
		$grouping = null;
		if ( ! is_null( $statistics_report_type ) ) {
			$grouping = array( 'site' );
			if ( ! is_null( $grouping_period ) ) {
				array_push( $grouping, $grouping_period );
			}

			switch ( $statistics_report_type ) {
				case WSAL_Rep_Common::DIFFERENT_IP:
					array_push( $grouping, 'users' );
					array_push( $grouping, 'ips' );
					break;
				case WSAL_Rep_Common::ALL_IPS:
					array_push( $grouping, 'ips' );
					break;
				case WSAL_Rep_Common::LOGIN_ALL:
				case WSAL_Rep_Common::LOGIN_BY_USER:
				case WSAL_Rep_Common::LOGIN_BY_ROLE:
				case WSAL_Rep_Common::PUBLISHED_ALL:
				case WSAL_Rep_Common::PUBLISHED_BY_USER:
				case WSAL_Rep_Common::PUBLISHED_BY_ROLE:
				case WSAL_Rep_Common::ALL_USERS:
					array_push( $grouping, 'users' );
					break;

				case WSAL_Rep_Common::VIEWS_ALL:
					array_push( $grouping, 'posts' );
					break;

				case WSAL_Rep_Common::VIEWS_BY_USER:
				case WSAL_Rep_Common::VIEWS_BY_ROLE:
					array_push( $grouping, 'users' );
					array_push( $grouping, 'posts' );
					break;
			}
		}

		return $grouping;
	}

	/**
	 * Method: Get connection.
	 *
	 * @return object – DB connection object.
	 */
	public function get_connection() {
		return $this->connection;
	}

	/**
	 * Used for WordPress prefix
	 *
	 * @return string Returns table name of WordPress.
	 */
	public function get_wp_table() {
		global $wpdb;

		return $wpdb->base_prefix . $this->table;
	}

	/**
	 * {@inheritDoc}
	 */
	public function install() {
		$_wpdb = $this->connection;
		$_wpdb->query( $this->get_install_query() );
	}

	/**
	 * Table install query.
	 *
	 * @param string|false $prefix - (Optional) Table prefix.
	 *
	 * @return string - Must return SQL for creating table.
	 */
	protected function get_install_query( $prefix = false ) {
		$_wpdb      = $this->connection;
		$class      = get_class( $this );
		$copy       = new $class( $this->connection );
		$table_name = $this->get_table();
		$sql        = 'CREATE TABLE IF NOT EXISTS ' . $table_name . ' (' . PHP_EOL;
		$cols       = $this->get_columns();
		foreach ( $cols as $key ) {
			$sql .= $this->get_sql_column_definition( $copy, $key );
		}

		$sql .= $this->get_table_options() . PHP_EOL;
		$sql .= ') ' . $_wpdb->get_charset_collate();

		return $sql;
	}

	/**
	 * Returns table name.
	 *
	 * @return string
	 */
	public function get_table() {
		$_wpdb = $this->connection;

		return $_wpdb->base_prefix . $this->table;
	}

	/**
	 * Returns this records' columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		$model = $this->get_model();

		if ( empty( $this->column_cache ) ) {
			$this->column_cache = array();
			foreach ( array_keys( get_object_vars( $model ) ) as $col ) {
				if ( trim( $col ) && $col[0] != '_' ) { // phpcs:ignore
					$this->column_cache[] = $col;
				}
			}
		}

		return $this->column_cache;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_model() {
		return new WSAL_Models_Query();
	}

	/**
	 * Generate SQL column definition string for the CREATE TABLE statement.
	 *
	 * @param object $copy Object copy to populate.
	 * @param string $key  Column key.
	 *
	 * @return string
	 */
	protected function get_sql_column_definition( $copy, $key ) {
		$result = '    ';
		switch ( true ) {
			case ( $key === $this->idkey ):
				$result .= $key . ' BIGINT NOT NULL AUTO_INCREMENT,' . PHP_EOL;
				break;
			case is_int( $copy->$key ):
				$result .= $key . ' BIGINT NOT NULL,' . PHP_EOL;
				break;
			case is_float( $copy->$key ):
				$result .= $key . ' DOUBLE NOT NULL,' . PHP_EOL;
				break;
			case is_string( $copy->$key ):
				$maxlength = $key . '_maxlength';
				if ( property_exists( $copy, $maxlength ) ) {
					// The double `$$` is intentional.
					$result .= $key . ' VARCHAR(' . (int) $copy::$$maxlength . ') NOT NULL,' . PHP_EOL;
				} else {
					$result .= $key . ' LONGTEXT NOT NULL,' . PHP_EOL;
				}
				break;
			case is_bool( $copy->$key ):
				$result .= $key . ' BIT NOT NULL,' . PHP_EOL;
				break;
			case is_array( $copy->$key ):
			case is_object( $copy->$key ):
				$result .= $key . ' LONGTEXT NOT NULL,' . PHP_EOL;
				break;
			default:
				// Fallback for any other columns would go here.
				break;
		}

		return $result;
	}

	/**
	 * SQL table options (constraints, foreign keys, indexes etc).
	 *
	 * @return string
	 */
	protected function get_table_options() {
		return '    PRIMARY KEY  (' . $this->idkey . ')';
	}

	/**
	 * Install this ActiveRecord structure into DB WordPress.
	 */
	public function install_original() {
		global $wpdb;
		$wpdb->query( $this->get_install_query( true ) ); // phpcs:ignore
	}

	/**
	 * {@inheritDoc}
	 */
	public function uninstall() {
		$_wpdb = $this->connection;

		// Check if table exists.
		if ( $this->table_exists() ) {
			$_wpdb->query( $this->get_uninstall_query() );
		}
	}

	/**
	 * Check if table exists.
	 *
	 * @return bool – True if exists, false if not.
	 */
	public function table_exists() {
		$_wpdb = $this->connection;

		// Query table exists.
		$table_exists_query = "SHOW TABLES LIKE '" . $this->get_table() . "'";

		return $_wpdb->query( $table_exists_query );
	}

	/**
	 * Must return SQL for removing table (at a minimum, it should be ` 'DROP TABLE ' . $this->_table `).
	 *
	 * @return string
	 */
	protected function get_uninstall_query() {
		return 'DROP TABLE IF EXISTS ' . $this->get_table();
	}

	/**
	 * {@inheritDoc}
	 */
	public function save( $active_record ) {
		$_wpdb  = $this->connection;
		$copy   = $active_record;
		$data   = array();
		$format = array();

		$columns = $this->get_columns();
		foreach ( $columns as $index => $key ) {
			if ( $key == $this->idkey ) { // phpcs:ignore
				$id_index = $index;
			}

			$val    = $copy->$key;
			$deffmt = '%s';
			if ( is_int( $copy->$key ) ) {
				$deffmt = '%d';
			}

			if ( is_float( $copy->$key ) ) {
				$deffmt = '%f';
			}

			if ( is_array( $copy->$key ) || is_object( $copy->$key ) ) {
				$data[ $key ] = WSAL_Helpers_DataHelper::json_encode( $val );
			} else {
				$data[ $key ] = $val;
			}

			$format[] = $deffmt;
		}

		if ( isset( $data[ $this->idkey ] ) && empty( $data[ $this->idkey ] ) ) {
			unset( $data[ $this->idkey ] );
			unset( $format[ $id_index ] );
		}

		$result = $_wpdb->replace( $this->get_table(), $data, $format );

		if ( false !== $result && $_wpdb->insert_id ) {
			$copy->set_id( $_wpdb->insert_id );
		}

		return $result;
	}

	/**
	 * Load record from DB (Single row).
	 *
	 * @param string $cond - (Optional) Load condition.
	 * @param array  $args - (Optional) Load condition arguments.
	 *
	 * @return array
	 */
	public function load( $cond = '%d', $args = array( 1 ) ) {
		$_wpdb = $this->connection;
		$sql   = $_wpdb->prepare( 'SELECT * FROM ' . $this->get_table() . ' WHERE ' . $cond, $args );

		return $_wpdb->get_row( $sql, ARRAY_A );
	}

	/**
	 * Load records from DB (Multi rows).
	 *
	 * @param string $cond Load condition.
	 * @param array  $args (Optional) Load condition arguments.
	 *
	 * @return array
	 */
	public function load_array( $cond, $args = array() ) {
		$_wpdb  = $this->connection;
		$result = array();
		$sql    = $_wpdb->prepare( 'SELECT * FROM ' . $this->get_table() . ' WHERE ' . $cond, $args );
		foreach ( $_wpdb->get_results( $sql, ARRAY_A ) as $data ) {
			$result[] = $this->get_model()->load_data( $data );
		}

		return $result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete( $active_record ) {
		$_wpdb = $this->connection;

		return $_wpdb->delete(
			$this->get_table(),
			array(
				$this->idkey => $active_record->get_id(),
			),
			array( '%d' )
		);
	}

	/**
	 * Delete records in DB matching a query.
	 *
	 * @param string $query Full SQL query.
	 * @param array  $args  (Optional) Query arguments.
	 *
	 * @return int|bool
	 */
	public function delete_query( $query, $args = array() ) {
		$_wpdb = $this->connection;
		$sql   = count( $args ) ? $_wpdb->prepare( $query, $args ) : $query;

		return $_wpdb->query( $sql );
	}

	/**
	 * {@inheritDoc}
	 */
	public function load_multi( $cond, $args = array() ) {
		$_wpdb  = $this->connection;
		$result = array();
		$sql    = ( ! is_array( $args ) || ! count( $args ) ) // Do we really need to prepare() or not?
			? ( $cond )
			: $_wpdb->prepare( $cond, $args );
		foreach ( $_wpdb->get_results( $sql, ARRAY_A ) as $data ) {
			$result[] = $this->get_model()->load_data( $data );
		}

		return $result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function load_and_call_for_each( $callback, $cond = '%d', $args = array( 1 ) ) {
		$_wpdb = $this->connection;
		$class = get_called_class();
		$sql   = $_wpdb->prepare( 'SELECT * FROM ' . $this->get_table() . ' WHERE ' . $cond, $args );
		foreach ( $_wpdb->get_results( $sql, ARRAY_A ) as $data ) {
			call_user_func( $callback, new $class( $data ) );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function count( $cond = '%d', $args = array( 1 ) ) {
		$_wpdb = $this->connection;
		$sql   = $_wpdb->prepare( 'SELECT COUNT(*) FROM ' . $this->get_table() . ' WHERE ' . $cond, $args );

		return (int) $_wpdb->get_var( $sql );
	}

	/**
	 * Count records in the DB matching a query.
	 *
	 * @param string $query Full SQL query.
	 * @param array  $args  (Optional) Query arguments.
	 *
	 * @return int Number of matching records.
	 */
	public function count_query( $query, $args = array() ) {
		$_wpdb = $this->connection;
		$sql   = count( $args ) ? $_wpdb->prepare( $query, $args ) : $query;

		return (int) $_wpdb->get_var( $sql );
	}

	/**
	 * {@inheritDoc}
	 */
	public function load_multi_query( $query, $args = array() ) {
		$_wpdb  = $this->connection;
		$result = array();
		$sql    = count( $args ) ? $_wpdb->prepare( $query, $args ) : $query;
		foreach ( $_wpdb->get_results( $sql, ARRAY_A ) as $data ) {
			$result[] = $this->get_model()->load_data( $data );
		}

		return $result;
	}

	/**
	 * Retrieves report data for generic (alerts based) report. Function used in WSAL reporting extension.
	 *
	 * @param WSAL_ReportArgs $report_args            Report arguments.
	 * @param int             $next_date              (Optional) Created on >.
	 * @param int             $limit                  (Optional) Limit.
	 * @param int             $statistics_report_type Statistics report type.
	 * @param string          $grouping_period        Period to use for data grouping.
	 *
	 * @return array Report results
	 */
	public function get_report_data( $report_args, $next_date = null, $limit = 0, $statistics_report_type = null, $grouping_period = null ) {

		// Figure out the grouping statement and the columns' selection.
		$grouping = self::get_grouping( $statistics_report_type, $grouping_period );

		// Build the SQL query and runs it.
		$query = $this->build_reporting_query( $report_args, false, $grouping, $next_date, $limit );

		// Statistical reports expect data as array, regular reports use objects.
		$result_format = is_null( $statistics_report_type ) ? OBJECT : ARRAY_A;
		$results       = $this->connection->get_results( $query, $result_format );

		if ( ! empty( $results ) ) {
			$last_item = end( $results );
			if ( is_object( $last_item ) && property_exists( $last_item, 'created_on' ) ) {
				$results['lastDate'] = $last_item->created_on;
			} elseif ( is_array( $last_item ) && array_key_exists( 'created_on', $last_item ) ) {
				$results['lastDate'] = $last_item['created_on'];
			}
		}

		return $results;
	}

	/**
	 * Builds an SQL query for the main report.
	 *
	 * @param WSAL_ReportArgs $report_args Report arguments.
	 * @param bool            $count_only  If true, the resulting query will only provide a count of matching entries
	 *                                     is
	 *                                     returned.
	 * @param array           $grouping    Grouping criteria. Drives the selected columns as well as the GROUP BY
	 *                                     statement. Only null value prevents grouping. Empty array means to group by
	 *                                     time period only.
	 * @param int             $next_date   (Optional) Created on >.
	 * @param int             $limit       (Optional) Limit.
	 *
	 * @return string
	 */
	private function build_reporting_query( $report_args, $count_only, $grouping = null, $next_date = null, $limit = 0 ) {
		$occurrence = new WSAL_Adapters_MySQL_Occurrence( $this->connection );
		$table_occ  = $occurrence->get_table();

		if ( $count_only ) {
			$select_fields = array( 'COUNT(1) as count' );
			$group_by      = array( 'occ.id' );
		} elseif ( is_null( $grouping ) ) {
			$select_fields = array(
				'occ.id',
				'occ.alert_id',
				'occ.site_id',
				'occ.created_on',
				"replace( replace( replace( occ.user_roles, '[', ''), ']', ''), '\\'', '') AS roles",
				'occ.client_ip AS ip',
				'occ.user_agent AS ua',
				'COALESCE( occ.username, occ.user_id ) as user_id',
				'occ.object',
				'occ.event_type',
				'occ.post_id',
				'occ.post_type',
				'occ.post_status',
			);
		} else {
			$select_fields = array();
			$group_by      = array();
			foreach ( $grouping as $grouping_item ) {
				switch ( $grouping_item ) {
					case 'site':
						array_push( $select_fields, 'site_id' );
						array_push( $group_by, 'site_id' );
						break;
					case 'users':
						array_push( $select_fields, 'COALESCE( occ.username, occ.user_id ) as user' );
						array_push( $group_by, 'user' );
						break;
					case 'posts':
						array_push( $select_fields, 'post_id' );
						array_push( $group_by, 'post_id' );
						break;
					case 'day':
						array_push( $select_fields, 'DATE_FORMAT( FROM_UNIXTIME( occ.created_on ), "%Y-%m-%d" ) AS period' );
						array_push( $group_by, 'period' );
						break;
					case 'week':
						array_push( $select_fields, 'DATE_FORMAT( FROM_UNIXTIME( occ.created_on ), "%Y-%u" ) AS period' );
						array_push( $group_by, 'period' );
						break;
					case 'month':
						array_push( $select_fields, 'DATE_FORMAT( FROM_UNIXTIME( occ.created_on ), "%Y-%m" ) AS period' );
						array_push( $group_by, 'period' );
						break;
				}
			}

			array_push( $select_fields, 'COUNT(*) as count' );
		}

		$sql = 'SELECT ' . implode( ',', $select_fields ) . ' FROM ' . $table_occ . ' AS occ ';

		$sql .= $this->build_where_statement( $report_args );
		if ( ! empty( $next_date ) ) {
			$sql .= ' AND occ.created_on < ' . $next_date;
		}

		if ( isset( $group_by ) && ! empty( $group_by ) ) {
			$sql          .= ' GROUP BY ' . implode( ',', $group_by );
			$orderby_parts = array_map(
				function ( $item ) {
					return 'period' === $item ? $item . ' DESC ' : $item . ' ASC ';
				},
				$group_by
			);

			$sql .= ' ORDER BY ' . implode( ',', $orderby_parts );
		} else {
			$sql .= ' ORDER BY created_on DESC ';
		}

		if ( ! empty( $limit ) ) {
			$sql .= " LIMIT {$limit}";
		}

		return $sql;
	}

	/**
	 * Generates SQL where statement based on given report args.
	 *
	 * @param WSAL_ReportArgs $report_args Report arguments.
	 *
	 * @return string
	 */
	private function build_where_statement( $report_args ) {
		$_site_id                = null;
		$sites_negate_expression = '';
		if ( $report_args->site__in ) {
			$_site_id = $this->format_array_for_query( $report_args->site__in );
		} elseif ( $report_args->site__not_in ) {
			$_site_id                = $this->format_array_for_query( $report_args->site__not_in );
			$sites_negate_expression = 'NOT';
		}

		$_user_id                = null;
		$users_negate_expression = '';
		if ( $report_args->user__in ) {
			$_user_id = $this->format_array_for_query( $report_args->user__in );
		} elseif ( $report_args->user__not_in ) {
			$_user_id                = $this->format_array_for_query( $report_args->user__not_in );
			$users_negate_expression = 'NOT';
		}

		$user_names = $this->get_user_names( $_user_id );

		$_role_name              = null;
		$roles_negate_expression = '';
		if ( $report_args->role__in ) {
			$_role_name = $this->format_array_for_query_regex( $report_args->role__in );
		} elseif ( $report_args->role__not_in ) {
			$_role_name              = $this->format_array_for_query_regex( $report_args->role__not_in );
			$roles_negate_expression = 'NOT';
		}

		$_alert_code                  = null;
		$alert_code_negate_expression = '';
		if ( $report_args->code__in ) {
			$_alert_code = $this->format_array_for_query( $report_args->code__in );
		} elseif ( $report_args->code__not_in ) {
			$_alert_code                  = $this->format_array_for_query( $report_args->code__not_in );
			$alert_code_negate_expression = 'NOT';
		}

		$_post_ids                  = null;
		$post_ids_negate_expression = '';
		if ( $report_args->post__in ) {
			$_post_ids = $this->format_array_for_query_regex( $report_args->post__in );
		} elseif ( $report_args->post__not_in ) {
			$_post_ids                  = $this->format_array_for_query_regex( $report_args->post__not_in );
			$post_ids_negate_expression = 'NOT';
		}

		$_post_types                  = null;
		$post_types_negate_expression = '';
		if ( $report_args->post_type__in ) {
			$_post_types = $this->format_array_for_query_regex( $report_args->post_type__in );
		} elseif ( $report_args->post_type__not_in ) {
			$_post_types                  = $this->format_array_for_query_regex( $report_args->post_type__not_in );
			$post_types_negate_expression = 'NOT';
		}

		$_post_statuses                  = null;
		$post_statuses_negate_expression = '';
		if ( $report_args->post_status__in ) {
			$_post_statuses = $this->format_array_for_query_regex( $report_args->post_status__in );
		} elseif ( $report_args->post_status__not_in ) {
			$_post_statuses                  = $this->format_array_for_query_regex( $report_args->post_status__not_in );
			$post_statuses_negate_expression = 'NOT';
		}

		$_ip_addresses                  = null;
		$ip_addresses_negate_expression = '';
		if ( $report_args->ip__in ) {
			$_ip_addresses = $this->format_array_for_query( $report_args->ip__in );
		} elseif ( $report_args->ip__not_in ) {
			$_ip_addresses                  = $this->format_array_for_query( $report_args->ip__not_in );
			$ip_addresses_negate_expression = 'NOT';
		}

		$_objects                  = null;
		$objects_negate_expression = '';
		if ( $report_args->object__in ) {
			$_objects = $this->format_array_for_query( $report_args->object__in );
		} elseif ( $report_args->object__not_in ) {
			$_objects                  = $this->format_array_for_query( $report_args->object__not_in );
			$objects_negate_expression = 'NOT';
		}

		$_event_types                  = null;
		$event_types_negate_expression = '';
		if ( $report_args->type__in ) {
			$_event_types = $this->format_array_for_query( $report_args->type__in );
		} elseif ( $report_args->type__not_in ) {
			$_event_types                  = $this->format_array_for_query( $report_args->type__not_in );
			$event_types_negate_expression = 'NOT';
		}

		$_start_timestamp = null;
		if ( $report_args->start_date ) {
			$start_datetime   = DateTime::createFromFormat( 'Y-m-d H:i:s', $report_args->start_date . ' 00:00:00' );
			$_start_timestamp = $start_datetime->format( 'U' );
		}

		$_end_timestamp = null;
		if ( $report_args->end_date ) {
			$end_datetime   = DateTime::createFromFormat( 'Y-m-d H:i:s', $report_args->end_date . ' 23:59:59' );
			$_end_timestamp = $end_datetime->format( 'U' );
		}

		$users_condition_parts = array();
		if ( ! is_null( $_user_id ) ) {
			array_push( $users_condition_parts, " {$users_negate_expression} find_in_set( occ.user_id, $_user_id ) > 0 " );
		}

		if ( ! is_null( $user_names ) ) {
			array_push( $users_condition_parts, " {$users_negate_expression} replace( occ.username, '\"', '' ) IN ( $user_names ) " );
		}

		$where_statement = ' WHERE 1 = 1 ';

		if ( ! empty( $users_condition_parts ) ) {
			$where_statement .= ' AND ( ' . implode( 'OR', $users_condition_parts ) . ' ) ';
		}

		if ( ! is_null( $_site_id ) ) {
			$where_statement .= " AND {$sites_negate_expression} find_in_set( occ.site_id, {$_site_id} ) > 0 ";
		}

		if ( ! is_null( $_role_name ) ) {
			$where_statement .= " AND user_roles {$roles_negate_expression} REGEXP {$_role_name} ";
		}

		if ( ! is_null( $_ip_addresses ) ) {
			$where_statement .= " AND {$ip_addresses_negate_expression} find_in_set( occ.client_ip, {$_ip_addresses} ) > 0 ";
		}

		if ( ! is_null( $_objects ) ) {
			$where_statement .= " AND {$objects_negate_expression} find_in_set( occ.object, {$_objects} ) > 0 ";
		}

		if ( ! is_null( $_event_types ) ) {
			$where_statement .= " AND {$event_types_negate_expression} find_in_set( occ.event_type, {$_event_types} ) > 0 ";
		}

		if ( ! is_null( $_alert_code ) ) {
			$where_statement .= " AND {$alert_code_negate_expression} find_in_set( occ.alert_id, {$_alert_code} ) > 0 ";
		}

		if ( ! is_null( $_post_ids ) ) {
			$where_statement .= " AND {$post_ids_negate_expression} find_in_set( occ.post_id, {$_post_ids} ) > 0 ";
		}

		if ( ! is_null( $_post_statuses ) ) {
			$where_statement .= " AND {$post_statuses_negate_expression} find_in_set( occ.post_status, {$_post_statuses} ) > 0 ";
		}

		if ( ! is_null( $_post_types ) ) {
			$where_statement .= " AND {$post_types_negate_expression} find_in_set( occ.post_type, {$_post_types} ) > 0 ";
		}

		if ( ! is_null( $_start_timestamp ) ) {
			$where_statement .= " AND occ.created_on >= {$_start_timestamp} ";
		}

		if ( ! is_null( $_end_timestamp ) ) {
			$where_statement .= " AND occ.created_on <= {$_end_timestamp} ";
		}

		return $where_statement;
	}

	/**
	 * Formats array for use in SQL query.
	 *
	 * @param array $data Data to format.
	 *
	 * @return string
	 * @since 4.3.2
	 */
	protected function format_array_for_query( $data ) {
		return "'" . implode( ',', $data ) . "'";
	}

	/**
	 * Get Users user_login.
	 *
	 * @param int $user_id - User ID.
	 *
	 * @return string comma separated users login
	 */
	private function get_user_names( $user_id ) {
		global $wpdb;

		$user_names = null;
		if ( ! empty( $user_id ) && 'null' !== $user_id && ! is_null( $user_id ) ) {
			$sql = 'SELECT user_login FROM ' . $wpdb->users . ' WHERE find_in_set(ID, @userId) > 0';
			$wpdb->query( "SET @userId = $user_id" ); // phpcs:ignore
			$result      = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore
			$users_array = array();
			foreach ( $result as $item ) {
				$users_array[] = '"' . $item['user_login'] . '"';
			}
			$user_names = implode( ', ', $users_array );
		}

		return $user_names;
	}

	/**
	 * Formats data as SQL query regex.
	 *
	 * @param array $data Data to format.
	 *
	 * @return string
	 * @since 4.3.2
	 */
	protected function format_array_for_query_regex( $data ) {
		$result = array();
		foreach ( $data as $item ) {
			array_push( $result, esc_sql( preg_quote( $item ) ) ); // phpcs:ignore
		}

		return "'" . implode( '|', $result ) . "'";
	}

	/**
	 * Function used in WSAL reporting extension.
	 * Check if criteria are matching in the DB.
	 *
	 * @param WSAL_ReportArgs $report_args - Query conditions.
	 *
	 * @return int Count of distinct values.
	 */
	public function check_match_report_criteria( $report_args ) {
		$query = $this->build_reporting_query( $report_args, true );

		return (int) $this->connection->get_var( $query );
	}

	/**
	 * Retrieves report data for IP address based reports. Function is used in WSAL reporting extension.
	 *
	 * @param WSAL_ReportArgs $report_args            Report arguments.
	 * @param int             $limit                  (Optional) Limit.
	 * @param int             $statistics_report_type Statistics report type.
	 * @param string          $grouping_period        Period to use for data grouping.
	 *
	 * @return array Raw report results as objects. Content depends on the report type.
	 */
	public function get_ip_address_report_data( $report_args, $limit = 0, $statistics_report_type = null, $grouping_period = null ) {
		global $wpdb;
		$_wpdb = $this->connection;

		// Tables.
		$occurrence = new WSAL_Adapters_MySQL_Occurrence( $_wpdb );
		$table_occ  = $occurrence->get_table();

		// Get temp table `wsal_tmp_users`.
		$tmp_users = new WSAL_Adapters_MySQL_TmpUser( $_wpdb );
		// If the table exist.
		if ( $tmp_users->is_installed() ) {
			$table_users = $tmp_users->get_table();
			$this->temp_users( $table_users );
		} else {
			$table_users = $wpdb->users;
		}

		// Figure out the grouping statement and the columns' selection.
		$grouping = self::get_grouping( $statistics_report_type, $grouping_period );

		// Figure out the selected columns and group by statement.
		$group_by_columns = array(
			'site_id',
		);

		if ( in_array( 'users', $grouping, true ) ) {
			array_push( $group_by_columns, 'username' );
		}

		if ( in_array( 'ips', $grouping, true ) ) {
			array_push( $group_by_columns, 'client_ip' );
		}

		$select_fields = $group_by_columns;
		foreach ( $grouping as $grouping_item ) {
			switch ( $grouping_item ) {
				case 'day':
					array_unshift( $select_fields, 'DATE_FORMAT( FROM_UNIXTIME( created_on ), "%Y-%m-%d" ) AS period' );
					array_unshift( $group_by_columns, 'period' );
					break;
				case 'week':
					array_unshift( $select_fields, 'DATE_FORMAT( FROM_UNIXTIME( created_on ), "%Y-%u" ) AS period' );
					array_unshift( $group_by_columns, 'period' );
					break;
				case 'month':
					array_unshift( $select_fields, 'DATE_FORMAT( FROM_UNIXTIME( created_on ), "%Y-%m" ) AS period' );
					array_unshift( $group_by_columns, 'period' );
					break;
			}
		}

		$where_statement = $this->build_where_statement( $report_args );

		$sql = '
			SELECT ' . implode( ',', $select_fields ) . " FROM (
			    SELECT occ.created_on, occ.site_id, occ.username, occ.client_ip 
				FROM $table_occ AS occ
				{$where_statement}
				HAVING username IS NOT NULL AND username NOT IN ( 'Unregistered user', 'Plugins', 'Plugin')
			UNION ALL
				SELECT occ.created_on, occ.site_id, u.user_login as username, occ.client_ip 
				FROM $table_occ AS occ
				JOIN $table_users AS u ON u.ID = occ.user_id  
				{$where_statement}
			HAVING username IS NOT NULL AND username NOT IN ( 'Unregistered user', 'Plugins', 'Plugin')
        ) ip_logins
		GROUP BY " . implode( ',', $group_by_columns );

		$orderby_parts = array_map(
			function ( $item ) {
				return 'period' === $item ? $item . ' DESC ' : $item . ' ASC ';
			},
			$group_by_columns
		);

		$sql .= ' ORDER BY ' . implode( ',', $orderby_parts );

		if ( ! empty( $limit ) ) {
			$sql .= " LIMIT {$limit}";
		}

		$results = $_wpdb->get_results( $sql, ARRAY_A );
		if ( is_array( $results ) && ! empty( $results ) ) {
			return $results;
		}

		return array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_installed() {
		$_wpdb = $this->connection;
		$sql   = "SHOW TABLES LIKE '" . $this->get_table() . "'";

		// Table transient.
		$wsal_table_transient = 'wsal_' . strtolower( $this->get_table() ) . '_status';
		$wsal_db_table_status = get_transient( $wsal_table_transient );

		// If transient does not exist, then run SQL query.
		if ( ! $wsal_db_table_status ) {
			$wsal_db_table_status = strtolower( $_wpdb->get_var( $sql ) ) === strtolower( $this->get_table() );
			set_transient( $wsal_table_transient, $wsal_db_table_status, DAY_IN_SECONDS );
		}

		return $wsal_db_table_status;
	}

	/**
	 * DELETE from table `tmp_users` and populate with users.
	 * It is used in the query of the above function.
	 *
	 * @param string $table_users - Table name.
	 */
	private function temp_users( $table_users ) {
		$_wpdb = $this->connection;
		$sql   = "DELETE FROM $table_users";
		$_wpdb->query( $sql );

		$sql   = "INSERT INTO $table_users (ID, user_login) VALUES ";
		$users = get_users(
			array(
				'fields' => array( 'ID', 'user_login' ),
			)
		);
		foreach ( $users as $user ) {
			$sql .= '(' . $user->ID . ', \'' . $user->user_login . '\'), ';
		}
		$sql = rtrim( $sql, ', ' );
		$_wpdb->query( $sql );
	}

	/**
	 * Updates records in DB matching a query.
	 *
	 * @param string $table              Table name.
	 * @param array  $data               Data to update (in column => value pairs).
	 *                                   Both $data columns and $data values should be "raw" (neither should be SQL
	 *                                   escaped). Sending a null value will cause the column to be set to NULL - the
	 *                                   corresponding format is ignored in this case.
	 * @param array  $where              A named array of WHERE clauses (in column => value pairs).
	 *                                   Multiple clauses will be joined with ANDs.
	 *                                   Both $where columns and $where values should be "raw".
	 *                                   Sending a null value will create an IS NULL comparison - the corresponding
	 *                                   format will be ignored in this case.
	 *
	 * @return int|false The number of rows updated, or false on error.
	 * @since 4.1.3
	 */
	public function update_query( $table, $data, $where ) {
		return $this->connection->update( $table, $data, $where );
	}
}
