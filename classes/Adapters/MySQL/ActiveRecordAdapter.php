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
	protected $_table;

	/**
	 * Contains primary key column name, override as required.
	 *
	 * @var string
	 */
	protected $_idkey = '';

	/**
	 * Local cache for a list of columns.
	 *
	 * @var string[]
	 */
	protected $_column_cache = [];

	/**
	 * Method: Constructor.
	 *
	 * @param object $connection - DB connection object.
	 */
	public function __construct( $connection ) {
		$this->connection = $connection;
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
	 * Returns table name.
	 *
	 * @return string
	 */
	public function GetTable() {
		$_wpdb = $this->connection;
		return $_wpdb->base_prefix . $this->_table;
	}

	/**
	 * Used for WordPress prefix
	 *
	 * @return string Returns table name of WordPress.
	 */
	public function GetWPTable() {
		global $wpdb;
		return $wpdb->base_prefix . $this->_table;
	}

	/**
	 * SQL table options (constraints, foreign keys, indexes etc).
	 *
	 * @return string
	 */
	protected function GetTableOptions() {
		return '    PRIMARY KEY  (' . $this->_idkey . ')';
	}

	/**
	 * Returns this records' columns.
	 *
	 * @return array
	 */
	public function GetColumns() {
		$model = $this->GetModel();

		if ( empty( $this->_column_cache ) ) {
			$this->_column_cache = array();
			foreach ( array_keys( get_object_vars( $model ) ) as $col ) {
				if ( trim( $col ) && $col[0] != '_' ) {
					$this->_column_cache[] = $col;
				}
			}
		}

		return $this->_column_cache;
	}

	/**
	 * @inheritDoc
	 */
	public function IsInstalled() {
		$_wpdb = $this->connection;
		$sql   = "SHOW TABLES LIKE '" . $this->GetTable() . "'";

		// Table transient.
		$wsal_table_transient = 'wsal_' . strtolower( $this->GetTable() ) . '_status';
		$wsal_db_table_status = get_transient( $wsal_table_transient );

		// If transient does not exist, then run SQL query.
		if ( ! $wsal_db_table_status ) {
			$wsal_db_table_status = strtolower( $_wpdb->get_var( $sql ) ) == strtolower( $this->GetTable() );
			set_transient( $wsal_table_transient, $wsal_db_table_status, DAY_IN_SECONDS );
		}
		return $wsal_db_table_status;
	}

	/**
	 * @inheritDoc
	 */
	public function Install() {
		$_wpdb = $this->connection;
		$_wpdb->query( $this->_GetInstallQuery() );
	}

	/**
	 * Install this ActiveRecord structure into DB WordPress.
	 */
	public function InstallOriginal() {
		global $wpdb;
		$wpdb->query( $this->_GetInstallQuery( true ) );
	}

	/**
	 * @inheritDoc
	 */
	public function Uninstall() {
		$_wpdb = $this->connection;

		// Check if table exists.
		if ( $this->table_exists() ) {
			$_wpdb->query( $this->_GetUninstallQuery() );
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
		$table_exists_query = "SHOW TABLES LIKE '" . $this->GetTable() . "'";
		return $_wpdb->query( $table_exists_query );
	}

	/**
	 * @inheritDoc
	 */
	public function Save( $active_record ) {
		$_wpdb  = $this->connection;
		$copy   = $active_record;
		$data   = array();
		$format = array();

		$columns = $this->GetColumns();
		foreach ( $columns as $index => $key ) {
			if ( $key == $this->_idkey ) {
				$_id_index = $index;
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
				$data[ $key ] = WSAL_Helpers_DataHelper::JsonEncode( $val );
			} else {
				$data[ $key ] = $val;
			}

			$format[] = $deffmt;
		}

		if ( isset( $data[ $this->_idkey ] ) && empty( $data[ $this->_idkey ] ) ) {
			unset( $data[ $this->_idkey ] );
			unset( $format[ $_id_index ] );
		}

		$result = $_wpdb->replace( $this->GetTable(), $data, $format );

		if ( false !== $result && $_wpdb->insert_id ) {
			$copy->setId( $_wpdb->insert_id );
		}
		return $result;
	}

	/**
	 * Load record from DB (Single row).
	 *
	 * @param string $cond - (Optional) Load condition.
	 * @param array $args - (Optional) Load condition arguments.
	 *
	 * @return array
	 */
	public function Load( $cond = '%d', $args = array( 1 ) ) {
		$_wpdb = $this->connection;
		$sql   = $_wpdb->prepare( 'SELECT * FROM ' . $this->GetTable() . ' WHERE ' . $cond, $args );
		return $_wpdb->get_row( $sql, ARRAY_A );
	}

	/**
	 * Load records from DB (Multi rows).
	 *
	 * @param string $cond Load condition.
	 * @param array $args (Optional) Load condition arguments.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function LoadArray( $cond, $args = array() ) {
		$_wpdb  = $this->connection;
		$result = array();
		$sql    = $_wpdb->prepare( 'SELECT * FROM ' . $this->GetTable() . ' WHERE ' . $cond, $args );
		foreach ( $_wpdb->get_results( $sql, ARRAY_A ) as $data ) {
			$result[] = $this->getModel()->LoadData( $data );
		}
		return $result;
	}

	/**
	 * @inheritDoc
	 */
	public function Delete( $active_record ) {
		$_wpdb = $this->connection;

		return $_wpdb->delete(
			$this->GetTable(),
			array(
				$this->_idkey => $active_record->getId()
			),
			array( '%d')
		);
	}

	/**
	 * Delete records in DB matching a query.
	 *
	 * @param string $query Full SQL query.
	 * @param array $args (Optional) Query arguments.
	 *
	 * @return int|bool
	 */
	public function DeleteQuery( $query, $args = array() ) {
		$_wpdb  = $this->connection;
		$sql    = count( $args ) ? $_wpdb->prepare( $query, $args ) : $query;
		return $_wpdb->query( $sql );
	}

	/**
	 * @inheritDoc
	 */
	public function LoadMulti( $cond, $args = array() ) {
		$_wpdb  = $this->connection;
		$result = array();
		$sql    = ( ! is_array( $args ) || ! count( $args ) ) // Do we really need to prepare() or not?
			? ( $cond )
			: $_wpdb->prepare( $cond, $args );
		foreach ( $_wpdb->get_results( $sql, ARRAY_A ) as $data ) {
			$result[] = $this->getModel()->LoadData( $data );
		}
		return $result;
	}

	/**
	 * @inheritDoc
	 */
	public function LoadAndCallForEach( $callback, $cond = '%d', $args = array( 1 ) ) {
		$_wpdb = $this->connection;
		$class = get_called_class();
		$sql   = $_wpdb->prepare( 'SELECT * FROM ' . $this->GetTable() . ' WHERE ' . $cond, $args );
		foreach ( $_wpdb->get_results( $sql, ARRAY_A ) as $data ) {
			call_user_func( $callback, new $class( $data ) );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function Count( $cond = '%d', $args = array( 1 ) ) {
		$_wpdb = $this->connection;
		$sql   = $_wpdb->prepare( 'SELECT COUNT(*) FROM ' . $this->GetTable() . ' WHERE ' . $cond, $args );
		return (int) $_wpdb->get_var( $sql );
	}

	/**
	 * Count records in the DB matching a query.
	 *
	 * @param string $query Full SQL query.
	 * @param array  $args (Optional) Query arguments.
	 * @return int Number of matching records.
	 */
	public function CountQuery( $query, $args = array() ) {
		$_wpdb = $this->connection;
		$sql   = count( $args ) ? $_wpdb->prepare( $query, $args ) : $query;
		return (int) $_wpdb->get_var( $sql );
	}

	/**
	 * @inheritDoc
	 */
	public function LoadMultiQuery( $query, $args = array() ) {
		$_wpdb  = $this->connection;
		$result = array();
		$sql    = count( $args ) ? $_wpdb->prepare( $query, $args ) : $query;
		foreach ( $_wpdb->get_results( $sql, ARRAY_A ) as $data ) {
			$result[] = $this->getModel()->LoadData( $data );
		}
		return $result;
	}

	/**
	 * Generate SQL column definition string for the CREATE TABLE statement.
	 *
	 * @param object $copy
	 * @param string $key
	 *
	 * @return string
	 */
	protected function _GetSqlColumnDefinition( $copy, $key ) {
		$result = '    ';
		switch ( true ) {
			case ( $key === $this->_idkey ):
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
				//  fallback for any other columns would go here
				break;
		}

		return $result;
	}

	/**
	 * Table install query.
	 *
	 * @param string|false $prefix - (Optional) Table prefix.
	 *
	 * @return string - Must return SQL for creating table.
	 */
	protected function _GetInstallQuery( $prefix = false ) {
		$_wpdb      = $this->connection;
		$class      = get_class( $this );
		$copy       = new $class( $this->connection );
		$table_name = $this->GetTable();
		$sql        = 'CREATE TABLE IF NOT EXISTS ' . $table_name . ' (' . PHP_EOL;
		$cols       = $this->GetColumns();
		foreach ( $cols as $key ) {
			$sql .= $this->_GetSqlColumnDefinition( $copy, $key );
		}

		$sql .= $this->GetTableOptions() . PHP_EOL;
		$sql .= ') ' . $_wpdb->get_charset_collate();

		return $sql;
	}

	/**
	 * Update `option_value` column of the Options table
	 * of WSAL to LONGTEXT.
	 *
	 * @since 3.2.3
	 */
	public function update_value_column() {
		global $wpdb;
		$sql  = 'ALTER TABLE ' . $this->GetTable();
		$sql .= ' MODIFY COLUMN option_value LONGTEXT NOT NULL';
		$wpdb->query( $sql );
	}

	/**
	 * Must return SQL for removing table (at a minimum, it should be ` 'DROP TABLE ' . $this->_table `).
	 *
	 * @return string
	 */
	protected function _GetUninstallQuery() {
		return 'DROP TABLE IF EXISTS ' . $this->GetTable();
	}

	/**
	 * Get Users user_login.
	 *
	 * @param int $_user_id - User ID.
	 *
	 * @return string comma separated users login
	 */
	private function GetUserNames( $_user_id ) {
		global $wpdb;

		$user_names = null;
		if ( ! empty( $_user_id ) && 'null' != $_user_id && ! is_null( $_user_id ) ) {
			$sql = 'SELECT user_login FROM ' . $wpdb->users . ' WHERE find_in_set(ID, @userId) > 0';
			$wpdb->query( "SET @userId = $_user_id" );
			$result      = $wpdb->get_results( $sql, ARRAY_A );
			$users_array = array();
			foreach ( $result as $item ) {
				$users_array[] = '"' . $item['user_login'] . '"';
			}
			$user_names = implode( ', ', $users_array );
		}

		return $user_names;
	}

	/**
	 * Function used in WSAL reporting extension.
	 *
	 * @param WSAL_ReportArgs $report_args Report arguments.
	 * @param int $_next_date - (Optional) Created on >.
	 * @param int $_limit - (Optional) Limit.
	 *
	 * @return array Report results
	 */
	public function GetReporting( $report_args, $_next_date = null, $_limit = 0 ) {

		$_wpdb = $this->connection;
		$query   = $this->build_reporting_query( $report_args, false, $_next_date, $_limit );
		$results = $_wpdb->get_results( $query );
		if ( ! empty( $results ) ) {
			$last_item           = end( $results );
			$results['lastDate'] = $last_item->created_on;
		}

		return $results;

	}

	/**
	 * Generates SQL where statement based on given report args.
	 *
	 * @param WSAL_ReportArgs $report_args
	 *
	 * @return string
	 */
	private function build_where_statement( $report_args ) {
		$_site_id                = null;
		$sites_negate_expression = '';
		if ( $report_args->site__in ) {
			$_site_id = $this->formatArrayForQuery( $report_args->site__in );
		} else if ( $report_args->site__not_in ) {
			$_site_id                = $this->formatArrayForQuery( $report_args->site__not_in );
			$sites_negate_expression = 'NOT';
		}

		$_user_id                = null;
		$users_negate_expression = '';
		$users_subselect_operand = 'OR';
		if ( $report_args->user__in ) {
			$_user_id = $this->formatArrayForQuery( $report_args->user__in );
		} else if ( $report_args->user__not_in ) {
			$_user_id                = $this->formatArrayForQuery( $report_args->user__not_in );
			$users_negate_expression = 'NOT';
			$users_subselect_operand = 'AND';
		}

		$user_names = $this->GetUserNames( $_user_id );

		$_role_name              = null;
		$roles_negate_expression = '';
		if ( $report_args->role__in ) {
			$_role_name = $this->formatArrayForQueryRegex( $report_args->role__in );
		} else if ( $report_args->role__not_in ) {
			$_role_name              = $this->formatArrayForQueryRegex( $report_args->role__not_in );
			$roles_negate_expression = 'NOT';
		}

		$_alert_code = null;
		$alert_code_negate_expression = '';
		if ( $report_args->code__in ) {
			$_alert_code = $this->formatArrayForQuery( $report_args->code__in );
		} else if ( $report_args->code__not_in) {
			$_alert_code = $this->formatArrayForQuery( $report_args->code__not_in );
			$alert_code_negate_expression = 'NOT';
		}

		$_post_ids = null;
		$post_ids_negate_expression = '';
		if ( $report_args->post__in ) {
			$_post_ids = $this->formatArrayForQueryRegex( $report_args->post__in );
		} else if ($report_args->post__not_in) {
			$_post_ids = $this->formatArrayForQueryRegex( $report_args->post__not_in );
			$post_ids_negate_expression = 'NOT';
		}
		
		$_post_types = null;
		$post_types_negate_expression = '';
		if ( $report_args->post_type__in ) {
			$_post_types = $this->formatArrayForQueryRegex( $report_args->post_type__in );
		} else if ($report_args->post_type__not_in) {
			$_post_types = $this->formatArrayForQueryRegex( $report_args->post_type__not_in );
			$post_types_negate_expression = 'NOT';
		}

		$_post_statuses = null;
		$post_statuses_negate_expression = '';
		if ( $report_args->post_status__in ) {
			$_post_statuses = $this->formatArrayForQueryRegex( $report_args->post_status__in );
		} else if ($report_args->post_status__not_in) {
			$_post_statuses = $this->formatArrayForQueryRegex( $report_args->post_status__not_in );
			$post_statuses_negate_expression = 'NOT';
		}

		$_ip_addresses                  = null;
		$ip_addresses_negate_expression = '';
		if ( $report_args->ip__in ) {
			$_ip_addresses = $this->formatArrayForQuery( $report_args->ip__in );
		} else if ( $report_args->ip__not_in ) {
			$_ip_addresses                  = $this->formatArrayForQuery( $report_args->ip__not_in );
			$ip_addresses_negate_expression = 'NOT';
		}

		$_objects                  = null;
		$objects_negate_expression = '';
		if ( $report_args->object__in ) {
			$_objects = $this->formatArrayForQuery( $report_args->object__in );
		} else if ( $report_args->object__not_in ) {
			$_objects                  = $this->formatArrayForQuery( $report_args->object__not_in );
			$objects_negate_expression = 'NOT';
		}

		$_event_types                  = null;
		$event_types_negate_expression = '';
		if ( $report_args->type__in ) {
			$_event_types = $this->formatArrayForQuery( $report_args->type__in );
		} else if ( $report_args->type__not_in ) {
			$_event_types                  = $this->formatArrayForQuery( $report_args->type__not_in );
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

		$where_statement = " WHERE 1 = 1 ";

		if ( ! empty( $users_condition_parts ) ) {
			$where_statement .= ' AND ( ' . implode( $users_subselect_operand, $users_condition_parts ) . ' ) ';
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
	 * Builds SQL query for the main report.
	 *
	 * @param WSAL_ReportArgs $report_args
	 * @param bool $count_only If true, the resulting query will only provide a count of matching entries is returned.
	 * @param int $_next_date - (Optional) Created on >.
	 * @param int $_limit - (Optional) Limit.
	 *
	 * @return string
	 */
	private function build_reporting_query( $report_args, $count_only, $_next_date = null, $_limit = 0 ) {
		$occurrence = new WSAL_Adapters_MySQL_Occurrence( $this->connection );
		$table_occ  = $occurrence->GetTable();

		$select_fields = $count_only ? 'COUNT(1)' : "occ.id,
			occ.alert_id,
			occ.site_id,
			occ.created_on,
			replace( replace( replace( occ.user_roles, '[', ''), ']', ''), '\\'', '') AS roles,
			occ.client_ip AS ip,
			occ.user_agent AS ua,
			COALESCE( occ.username, occ.user_id ) as user_id,
			occ.object,
			occ.event_type,
			occ.post_id,
			occ.post_type,
			occ.post_status";

		$sql = "SELECT {$select_fields} FROM {$table_occ} AS occ ";

		$sql .= $this->build_where_statement( $report_args );
		if ( ! empty( $_next_date ) ) {
			$sql .= ' AND occ.created_on < ' . $_next_date;
		}

		$sql .= " ORDER BY created_on DESC ";

		if ( ! empty( $_limit ) ) {
			$sql .= " LIMIT {$_limit}";
		}

		return $sql;
	}

	/**
	 * Function used in WSAL reporting extension.
	 * Check if criteria are matching in the DB.
	 *
	 * @param WSAL_ReportArgs $report_args - Query conditions.
	 *
	 * @return int count of distinct values
	 */
	public function CheckMatchReportCriteria( $report_args ) {
		$query = $this->build_reporting_query( $report_args, true );

		return (int) $this->connection->get_var( $query );
	}

	/**
	 * Function used in WSAL reporting extension.
	 * List of unique IP addresses used by the same user.
	 *
	 * @param WSAL_ReportArgs $report_args Report arguments.
	 * @param int $_limit - (Optional) Limit.
	 *
	 * @return array Report results grouped by IP and Username
	 */
	public function GetReportGrouped( $report_args, $_limit = 0 ) {

		global $wpdb;
		$_wpdb = $this->connection;

		// Tables.
		$occurrence = new WSAL_Adapters_MySQL_Occurrence( $_wpdb );
		$table_occ  = $occurrence->GetTable();

		// Get temp table `wsal_tmp_users`.
		$tmp_users = new WSAL_Adapters_MySQL_TmpUser( $_wpdb );
		// If the table exist.
		if ( $tmp_users->IsInstalled() ) {
			$table_users = $tmp_users->GetTable();
			$this->TempUsers( $table_users );
		} else {
			$table_users = $wpdb->users;
		}

		$where_statement = $this->build_where_statement( $report_args );
		$sql = "SELECT DISTINCT * FROM (
				    SELECT DISTINCT
						occ.site_id,
				        occ.client_ip AS ip,
						occ.username AS user_login 
					FROM $table_occ AS occ
					{$where_statement}
					HAVING user_login IS NOT NULL
				UNION ALL
				SELECT DISTINCT
					occ.site_id,
				  	occ.client_ip AS ip,
				  	u.user_login AS user_login
				FROM $table_occ AS occ
				JOIN $table_users AS u ON u.ID = occ.user_id  
				{$where_statement}
				HAVING user_login IS NOT NULL
    		) ip_logins
			WHERE user_login NOT IN ( 'Unregistered user', 'Plugins', 'Plugin')
			ORDER BY user_login ASC;";

		if ( ! empty( $_limit ) ) {
			$sql .= " LIMIT {$_limit}";
		}

		$grouped_types = array();
		$results       = $_wpdb->get_results( $sql );
		if ( ! empty( $results ) ) {
			foreach ( $results as $key => $row ) {
				// Get the display_name only for the first row & if the user_login changed from the previous row.
				$row->display_name = '';
				if ( 0 == $key || ( $key > 1 && $results[ ( $key - 1 ) ]->user_login != $row->user_login ) ) {
					$row->display_name = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT display_name "
							. " FROM {$wpdb->users} "
							. " WHERE user_login = %s;",
							$row->user_login
						)
					);
				}

				if ( ! isset( $grouped_types[ $row->user_login ] ) ) {
					$grouped_types[ $row->user_login ] = array(
						'site_id'      => $row->site_id,
						'user_login'   => $row->user_login,
						'display_name' => $row->display_name,
						'ips'          => array(),
					);
				}

				$grouped_types[ $row->user_login ]['ips'][] = $row->ip;
			}
		}

		return $grouped_types;
	}

	/**
	 * DELETE from table `tmp_users` and populate with users.
	 * It is used in the query of the above function.
	 *
	 * @param string $table_users - Table name.
	 */
	private function TempUsers( $table_users ) {
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
	 * @param string       $table        Table name
	 * @param array        $data         Data to update (in column => value pairs).
	 *                                   Both $data columns and $data values should be "raw" (neither should be SQL escaped).
	 *                                   Sending a null value will cause the column to be set to NULL - the corresponding
	 *                                   format is ignored in this case.
	 * @param array        $where        A named array of WHERE clauses (in column => value pairs).
	 *                                   Multiple clauses will be joined with ANDs.
	 *                                   Both $where columns and $where values should be "raw".
	 *                                   Sending a null value will create an IS NULL comparison - the corresponding format will be ignored in this case.
	 * @return int|false The number of rows updated, or false on error.
	 * @since 4.1.3
	 */
	public function UpdateQuery( $table, $data, $where ) {
		return $this->connection->update( $table, $data, $where );
	}

	/**
	 * @inheritDoc
	 */
	 public function GetModel() {
		 return new WSAL_Models_Query();
	 }

	/**
	 * @param array $data
	 *
	 * @return string
	 * @since 4.3.2
	 */
	protected function formatArrayForQuery( $data ) {
		return "'" . implode( ',', $data ) . "'";
	}

	/**
	 * @param array $data
	 *
	 * @return string
	 * @since 4.3.2
	 */
	protected function formatArrayForQueryRegex( $data ) {
		$result = array();
		foreach ( $data as $item ) {
			array_push( $result, esc_sql( preg_quote( $item ) ) );
		}

		return "'" . implode( '|', $result ) . "'";
	}
}
