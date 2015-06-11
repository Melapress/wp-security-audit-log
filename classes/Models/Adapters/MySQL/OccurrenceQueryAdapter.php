<?php

class WSAL_Adapters_MySQL_OccurrenceQuery extends WSAL_Adapters_MySQL_Query implements WSAL_Adapters_OccurrenceQueryInterface {
	
	const LIKE_LEFT = 'l';
	const LIKE_RIGHT = 'r';
	private $connection;
	/**
	 * Contains meta-specific arguments to be AND'ed together
	 * @var array
	 */
	public $meta_where = array();
	
	/**
	 * Contains arguments to be used in meta conditions.
	 * @var array
	 */
	public $meta_args = array();

	public function __construct($conn) {
		$this->connection = $conn;
	}
	
	public function GetCond(){
		$cond = parent::GetCond();
		if (count($this->meta_where)) {
			$tmp = new WSAL_Adapters_MySQL_Meta();
			$cond[] = 'id IN (
				SELECT DISTINCT occurrence_id
				FROM ' . $tmp->GetTable() . '
				WHERE ' . implode(' AND ', $this->meta_where) . '
			)';
		}
		return $cond;
	}
	
	public function GetArgs(){
		$args = parent::GetArgs();
		foreach ($this->meta_args as $arg) $args[] = $arg;
		return $args;
	}
	
	/**
	 * Find occurrences matching an exact named meta value.
	 * @param string $name Meta name.
	 * @param string $value Meta value.
	 */
	public function WhereMetaIs($name, $value){
		$this->meta_where[] = 'name = %s AND value = %s';
		$this->meta_args[] = $name;
		$this->meta_args[] = $value;
	}
	
	/**
	 * Find occurrences matching a named meta containing a value.
	 * @param string $name Meta name.
	 * @param string $value Meta value.
	 * @param string $type Where to check for (left, right, both or none) see LIKE_* constants
	 */
	public function WhereMetaLike($name, $value, $type){
		$this->meta_where[] = 'name = %s AND value LIKE %s';
		$this->meta_args[] = $name;
		$value = esc_sql($value);
		if (strpos($type, self::LIKE_LEFT) !== false) $value = '%' . $value;
		if (strpos($type, self::LIKE_RIGHT) !== false) $value = $value . '%';
		$this->meta_args[] = $value;
	}
	
	/**
	 * Find occurrences matching a meta condition.
	 * @param string $cond Meta condition.
	 * @param array $args Condition arguments.
	 */
	public function WhereMeta($cond, $args){
		$this->meta_where[] = $cond;
		foreach ($args as $arg) $this->meta_args[] = $arg;
	}
	
	public function Delete(){
		//global $wpdb;
		$_wpdb = $this->connection;
		// get relevant occurrence ids
		$occids = $_wpdb->get_col($this->GetSql('select'));
		
		if (count($occids)) {
			// delete meta data: back up columns, remove them for DELETE and generate sql
			$cols = $this->columns;
			$this->columns = array('occurrence_id');

			//get meta adapter
			//metaAdapter->deleteBYOccurenceIds(...);
			$tmp = new WSAL_Adapters_MySQL_Meta();
			$sql = 'DELETE FROM ' . $tmp->GetTable() . ' WHERE occurrence_id IN (' . implode(',', $occids) . ')';

			// restore columns
			$this->columns = $cols;

			// execute query
			call_user_func(array($this->ar_cls, 'DeleteQuery'), $sql, $this->GetArgs());
		}
		
		// delete occurrences
		parent::Delete();
	}
}
