<?php

/**
 * Wordpress options are always loaded from the default wordpress database.
 */
class WSAL_Models_Option {

	protected $_table = 'wsal_options';
	protected $_idkey = 'id';
	
	const STATE_UNKNOWN  = 'unknown';
	const STATE_CREATED  = 'created';
	const STATE_UPDATED  = 'updated';
	const STATE_DELETED  = 'deleted';
	const STATE_LOADED   = 'loaded';

	protected $_state = self::STATE_UNKNOWN;
	public $id = 0;
	public $option_name = '';
	public static $option_name_maxlength = 100;
	public $option_value = ''; 

	public function SetOptionValue($name, $value)
	{	
		$this->GetNamedOption($name);
		$this->option_name = $name;
		// Serialize if $value is array or object
		$value = maybe_serialize($value);
		$this->option_value = $value;
		$this->SaveOption();
	}
	
	public function GetOptionValue($name, $default = array())
	{
		$this->GetNamedOption($name);
		// Unerialize if $value is array or object
		$this->option_value = maybe_unserialize($this->option_value);
		return $this->IsLoaded() ? $this->option_value : $default;
	}

	public function GetNamedOption($name)
	{
		return $this->LoadOption('option_name = %s', array($name));
	}

	/**
	 * Load record from DB.
	 * @param string $cond (Optional) Load condition.
	 * @param array $args (Optional) Load condition arguments.
	 */
	public function LoadOption($cond = '%d', $args = array(1)){
		global $wpdb;
		$this->_state = self::STATE_UNKNOWN;
		
		$sql = $wpdb->prepare('SELECT * FROM '.$this->GetTable().' WHERE '.$cond, $args);
		$data = $wpdb->get_row($sql, ARRAY_A);
		
		if(!is_null($data)){
			$this->LoadData($data);
			$this->_state = self::STATE_LOADED;
		}
	}

	/**
	 * Save record to DB.
	 * @return integer|boolean Either the number of modified/inserted rows or false on failure.
	 */
	public function SaveOption(){
		$this->_state = self::STATE_UNKNOWN;
		global $wpdb;
		$copy = get_class($this);
		$copy = new $copy;
		$data = array();
		$format = array();
		foreach($this->GetColumns() as $key){
			$val = $this->$key;
			$deffmt = '%s';
			if(is_int($copy->$key))$deffmt = '%d';
			if(is_float($copy->$key))$deffmt = '%f';
			if(is_array($copy->$key) || is_object($copy->$key)){
				$data[$key] = $this->_JsonEncode($val);
			}else{
				$data[$key] = $val;
			}
			$format[] = $deffmt;
		}
		$result = $wpdb->replace($this->GetTable(), $data, $format);
		if($wpdb->insert_id){
			$this->{$this->_idkey} = $wpdb->insert_id;
			if($result !== false)
				$this->_state = self::STATE_CREATED;
		}else{
			if($result !== false)
				$this->_state = self::STATE_UPDATED;
		}
		return $result;
	}

	/**
	 * @deprecated
	 * @return boolean Returns whether table structure is installed or not.
	 */
	public function IsInstalled(){
		global $wpdb;
		$sql = 'SHOW TABLES LIKE "' . $this->GetTable() . '"';
		return $wpdb->get_var($sql) == $this->GetTable();
	}

	public function GetTable(){
		global $wpdb;
		return $wpdb->base_prefix . $this->_table;
	}

	/**
	 * Load object data from variable.
	 * @param array|object $data Data array or object.
	 */
	public function LoadData($data){
		$copy = get_class($this);
		$copy = new $copy;
		foreach((array)$data as $key => $val){
			if(isset($copy->$key)){
				switch(true){
					case is_array($copy->$key):
					case is_object($copy->$key):
						$this->$key = $this->_JsonDecode($val);
						break;
					case is_int($copy->$key):
						$this->$key = (int)$val;
						break;
					case is_float($copy->$key):
						$this->$key = (float)$val;
						break;
					case is_bool($copy->$key):
						$this->$key = (bool)$val;
						break;
					case is_string($copy->$key):
						$this->$key = (string)$val;
						break;
					default:
						throw new Exception('Unsupported type "'.gettype($copy->$key).'"');
				}
			}
		}
	}

	/**
	 * @return boolean
	 */
	public function IsLoaded(){
		return $this->_state == self::STATE_LOADED;
	}

	/**
	 * @return array Returns this records' columns.
	 */
	public function GetColumns(){
		if(!isset($this->_column_cache)){
			$this->_column_cache = array();
			foreach(array_keys(get_object_vars($this)) as $col)
				if(trim($col) && $col[0] != '_')
					$this->_column_cache[] = $col;
		}
		return $this->_column_cache;
	}

}