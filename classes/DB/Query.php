<?php

/**
 * @todo Add group-by support
 * @todo Add limit/top support
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
	public function GetSql(){
		switch($this->GetDbType()){
			case 'mysql':
				return 'SELECT ' . implode(',', $this->columns)
					. ' FROM ' . implode(',', $this->from)
					. (count($this->joins) ? implode(' ', $this->where) : '')
					. (count($this->where) ? (' WHERE ' . implode(' AND ', $this->where)) : '')
					. (count($this->order) ? (' ORDER BY ' . implode(', ', $this->order)) : '')
				;
			default:
				throw new Exception('SQL generation for "' . $this->GetDbType() . '" databases is not supported.');
		}
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
}
