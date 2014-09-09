<?php

/**
 * @todo Add group-by support
 */
class WSAL_DB_Query {
	/**
	 * @var string
	 */
	protected $ar_cls;
	
	/**
	 * @var WSAL_DB_ActiveRecord
	 */
	protected $ar_obj;
	
	/**
	 * Array of table names to read from.
	 * @var array
	 */
	public $from = array();
	
	/**
	 * Array of columns to select.
	 * @var array
	 */
	public $columns = array('*');
	
	/**
	 * Array of conditions AND'ed together.
	 * @var array
	 */
	public $where = array();
	
	/**
	 * Use for ordering the result set. First items count most.
	 * @var array
	 */
	public $order = array();
	
	/**
	 * Array of join components.
	 * @var array
	 */
	public $joins = array();
	
	/**
	 * Array of values to be substituted in query.
	 * @var array
	 */
	public $args = array();
	
	/**
	 * The amount of records to skip in result.
	 * @var int
	 */
	public $offset = 0;
	
	/**
	 * The maximum number of records in result.
	 * @var int
	 */
	public $length = 0;
	
	/**
	 * @param string $ar_class Name of class that extends ActiveRecord class.
	 */
	public function __construct($ar_class) {
		$this->ar_cls = $ar_class;
		$this->ar_obj = new $ar_class();
		$this->from = array($this->ar_obj->GetTable());
	}
	
	public function GetDbType(){
		global $wpdb;
		return $wpdb->is_mysql  ? 'mysql' : 'unknown';
	}
	
	/**
	 * @return string Generated sql.
	 */
	public function GetSql($verb = 'select'){
		$where = $this->GetCond();
		switch($this->GetDbType()){
			case 'mysql':
				return strtoupper($verb) . ' ' . implode(',', $this->columns)
					. ' FROM ' . implode(',', $this->from)
					. (count($this->joins) ? implode(' ', $this->where) : '')
					. (count($where) ? (' WHERE ' . implode(' AND ', $where)) : '')
				// @todo GROUP BY goes here
				// @todo HAVING goes here
					. (count($this->order) ? (' ORDER BY ' . implode(', ', $this->order)) : '')
					. ($this->length ? (' LIMIT ' . ($this->offset ? ($this->offset . ', ') : '') . ' ' . $this->length) : '')
				;
			default:
				throw new Exception('SQL generation for "' . $this->GetDbType() . '" databases is not supported.');
		}
	}
	
	/**
	 * @return array Array of conditions.
	 */
	public function GetCond(){
		return $this->where;
	}
	
	/**
	 * @return array Arguments used in query.
	 */
	public function GetArgs(){
		return $this->args;
	}
	
	/**
	 * @return WSAL_DB_ActiveRecord[] Execute query and return data as $ar_cls objects.
	 */
	public function Execute(){
		return call_user_func(array($this->ar_cls, 'LoadMultiQuery'), $this->GetSql(), $this->GetArgs());
	}
	
	/**
	 * @return int Use query for counting records.
	 */
	public function Count(){
		// back up columns, use COUNT as default column and generate sql
		$cols = $this->columns;
		$this->columns = array('COUNT(*)');
		$sql = $this->GetSql();
		
		// restore columns
		$this->columns = $cols;
		
		// execute query and return result
		return call_user_func(array($this->ar_cls, 'CountQuery'), $sql, $this->GetArgs());
	}
	
	/**
	 * Find occurrences matching a condition.
	 * @param string $cond The condition.
	 * @param array $args Condition arguments.
	 */
	public function Where($cond, $args){
		$this->where[] = $cond;
		foreach ($args as $arg) $this->args[] = $arg;
	}
	
	/**
	 * Use query for deleting records.
	 */
	public function Delete(){
		// back up columns, remove them for DELETE and generate sql
		$cols = $this->columns;
		$this->columns = array();
		$sql = $this->GetSql('delete');
		
		// restore columns
		$this->columns = $cols;
		
		// execute query
		call_user_func(array($this->ar_cls, 'DeleteQuery'), $sql, $this->GetArgs());
	}
}
