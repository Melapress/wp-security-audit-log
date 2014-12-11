<?php

abstract class WSAL_DB_ActiveRecord {
	
	/**
	 * Contains table name, override as required.
	 * @var string
	 */
	protected $_table = '';
	
	/**
	 * Contains primary key column name, override as required.
	 * @var string
	 */
	protected $_idkey = '';
	
	const STATE_UNKNOWN  = 'unknown';
	const STATE_CREATED  = 'created';
	const STATE_UPDATED  = 'updated';
	const STATE_DELETED  = 'deleted';
	const STATE_LOADED   = 'loaded';
	
	protected $_state = self::STATE_UNKNOWN;
	
	public function __construct($data = null) {
		if(!$this->_table)
			throw new Exception('Class "' . __CLASS__ . '" requires "_table" to be set.');
		if(!$this->_idkey)
			throw new Exception('Class "' . __CLASS__ . '" requires "_idkey" to be set.');
		if(!is_null($data)){
			$this->LoadData($data);
			$this->_state = self::STATE_LOADED;
		}
	}

	/**
	 * @return string Must return SQL for creating table.
	 */
	protected function _GetInstallQuery(){
		global $wpdb;
		
		$class = get_class($this);
		$copy = new $class();
		
		$sql = 'CREATE TABLE ' . $this->GetTable() . ' (' . PHP_EOL;
		
		foreach($this->GetColumns() as $key) {
			$sql .= '    ';
			switch(true) {
				case $key == $copy->_idkey:
					$sql .= $key . ' BIGINT NOT NULL AUTO_INCREMENT,' . PHP_EOL;
					break;
				case is_integer($copy->$key):
					$sql .= $key . ' BIGINT NOT NULL,' . PHP_EOL;
					break;
				case is_float($copy->$key):
					$sql .= $key . ' DOUBLE NOT NULL,' . PHP_EOL;
					break;
				case is_string($copy->$key):
					$maxlength = $key . '_maxlength';
					if(property_exists($class, $maxlength)){
						$sql .= $key . ' VARCHAR(' . intval($class::$$maxlength) . ') NOT NULL,' . PHP_EOL;
					}else{
						$sql .= $key . ' TEXT NOT NULL,' . PHP_EOL;
					}
					break;
				case is_bool($copy->$key):
					$sql .= $key . ' BIT NOT NULL,' . PHP_EOL;
					break;
				case is_array($copy->$key):
				case is_object($copy->$key):
					$sql .= $key . ' LONGTEXT NOT NULL,' . PHP_EOL;
					break;
			}
		}
		
		$sql .= $this->GetTableOptions() . PHP_EOL;
		
		$sql .= ')';
		
		if ( ! empty($wpdb->charset) )
			$sql .= ' DEFAULT CHARACTER SET ' . $wpdb->charset;
		if ( ! empty($wpdb->collate) )
			$sql .= ' COLLATE ' . $wpdb->collate;
		
		return $sql;
	}
	
	/**
	 * @return string Must return SQL for removing table (at a minimum, it should be ` 'DROP TABLE ' . $this->_table `).
	 */
	protected function _GetUninstallQuery(){
		return  'DROP TABLE ' . $this->GetTable();
	}
	
	/**
	 * A wrapper for JSON encoding that fixes potential issues.
	 * @param mixed $data The data to encode.
	 * @return string JSON string.
	 */
	protected function _JsonEncode($data){
		return @json_encode($data);
	}
	
	/**
	 * A wrapper for JSON encoding that fixes potential issues.
	 * @param string $data The JSON string to decode.
	 * @return mixed Decoded data.
	 */
	protected function _JsonDecode($data){
		return @json_decode($data);
	}
	
	/**
	 * @return string Returns table name.
	 */
	public function GetTable(){
		global $wpdb;
		return $wpdb->base_prefix . $this->_table;
	}
	
	/**
	 * @return string SQL table options (constraints, foreign keys, indexes etc).
	 */
	protected function GetTableOptions(){
		return '    PRIMARY KEY  (' . $this->_idkey . ')';
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
	
	/**
	 * @deprecated
	 * @return boolean Returns whether table structure is installed or not.
	 */
	public function IsInstalled(){
		global $wpdb;
		$sql = 'SHOW TABLES LIKE "' . $this->GetTable() . '"';
		return $wpdb->get_var($sql) == $this->GetTable();
	}
	
	/**
	 * Install this ActiveRecord structure into DB.
	 */
	public function Install(){
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($this->_GetInstallQuery());
	}
	
	/**
	 * Remove this ActiveRecord structure into DB.
	 */
	public function Uninstall(){
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($this->_GetUninstallQuery());
	}
	
	/**
	 * Save record to DB.
	 * @return integer|boolean Either the number of modified/inserted rows or false on failure.
	 */
	public function Save(){
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
	 * Load record from DB.
	 * @param string $cond (Optional) Load condition.
	 * @param array $args (Optional) Load condition arguments.
	 */
	public function Load($cond = '%d', $args = array(1)){
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
	 * Delete DB record.
	 * @return int|boolean Either the amount of deleted rows or False on error.
	 */
	public function Delete(){
		global $wpdb;
		
		$this->_state = self::STATE_UNKNOWN;
	
		$result = $wpdb->delete(
			$this->GetTable(),
			array($this->_idkey => $this->{$this->_idkey})
		);
		
		if($result !== false)
			$this->_state = self::STATE_DELETED;
		
		return $result;
	}
	
	/**
	 * Delete records in DB matching a query.
	 * @param string $query Full SQL query.
	 * @param array $args (Optional) Query arguments.
	 */
	public static function DeleteQuery($query, $args = array()){
		global $wpdb;
		$sql = count($args) ? $wpdb->prepare($query, $args) : $query;
		$wpdb->query($sql);
	}
	
	/**
	 * Load multiple records from DB.
	 * @param string $cond (Optional) Load condition (eg: 'some_id = %d' ).
	 * @param array $args (Optional) Load condition arguments (rg: array(45) ).
	 * @return self[] List of loaded records.
	 */
	public static function LoadMulti($cond, $args = array()){
		global $wpdb;
		$class = get_called_class();
		$result = array();
		$temp = new $class();
		$sql = (!is_array($args) || !count($args)) // do we really need to prepare() or not?
			? ('SELECT * FROM ' . $temp->GetTable() . ' WHERE ' . $cond)
			: $wpdb->prepare('SELECT * FROM ' . $temp->GetTable() . ' WHERE ' . $cond, $args)
		;
		foreach($wpdb->get_results($sql, ARRAY_A) as $data){
			$result[] = new $class($data);
		}
		return $result;
	}
	
	/**
	 * Load multiple records from DB and call a callback for each record.
	 * This function is very memory-efficient, it doesn't load records in bulk.
	 * @param callable $callback The callback to invoke.
	 * @param string $cond (Optional) Load condition.
	 * @param array $args (Optional) Load condition arguments.
	 */
	public static function LoadAndCallForEach($callback, $cond = '%d', $args = array(1)){
		global $wpdb;
		$class = get_called_class();
		$temp = new $class();
		$sql = $wpdb->prepare('SELECT * FROM ' . $temp->GetTable() . ' WHERE '.$cond, $args);
		foreach($wpdb->get_results($sql, ARRAY_A) as $data){
			call_user_func($callback, new $class($data));
		}
	}
	
	/**
	 * Count records in the DB matching a condition.
	 * If no parameters are given, this counts the number of records in the DB table.
	 * @param string $cond (Optional) Query condition.
	 * @param array $args (Optional) Condition arguments.
	 * @return int Number of matching records.
	 */
	public static function Count($cond = '%d', $args = array(1)){
		global $wpdb;
		$class = get_called_class();
		$temp = new $class();
		$sql = $wpdb->prepare('SELECT COUNT(*) FROM ' . $temp->GetTable() . ' WHERE ' . $cond, $args);
		return (int)$wpdb->get_var($sql);
	}
	
	/**
	 * Count records in the DB matching a query.
	 * @param string $query Full SQL query.
	 * @param array $args (Optional) Query arguments.
	 * @return int Number of matching records.
	 */
	public static function CountQuery($query, $args = array()){
		global $wpdb;
		$sql = count($args) ? $wpdb->prepare($query, $args) : $query;
		return (int)$wpdb->get_var($sql);
	}
	
	/**
	 * Similar to LoadMulti but allows the use of a full SQL query.
	 * @param string $query Full SQL query.
	 * @param array $args (Optional) Query arguments.
	 * @return self[] List of loaded records.
	 */
	public static function LoadMultiQuery($query, $args = array()){
		global $wpdb;
		$class = get_called_class();
		$result = array();
		$sql = count($args) ? $wpdb->prepare($query, $args) :  $query;
		foreach($wpdb->get_results($sql, ARRAY_A) as $data){
			$result[] = new $class($data);
		}
		return $result;
	}
	
	/**
	 * Install all DB tables.
	 */
	public static function InstallAll(){
		$plugin = WpSecurityAuditLog::GetInstance();
		foreach(glob(dirname(__FILE__) . '/*.php') as $file){
			$class = $plugin->GetClassFileClassName($file);
			if(is_subclass_of($class, __CLASS__)){
				$class = new $class();
				$class->Install();
			}
		}
	}
	
	/**
	 * Uninstall all DB tables.
	 */
	public static function UninstallAll(){
		$plugin = WpSecurityAuditLog::GetInstance();
		foreach(glob(dirname(__FILE__) . '/*.php') as $file){
			$class = $plugin->GetClassFileClassName($file);
			if(is_subclass_of($class, __CLASS__)){
				$class = new $class();
				$class->Uninstall();
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
	 * @return boolean
	 */
	public function IsSaved(){
		return $this->_state == self::STATE_CREATED
			|| $this->_state == self::STATE_UPDATED;
	}
	
	/**
	 * @return boolean
	 */
	public function IsCreated(){
		return $this->_state == self::STATE_CREATED;
	}
	
	/**
	 * @return boolean
	 */
	public function IsUpdated(){
		return $this->_state == self::STATE_UPDATED;
	}
	
	/**
	 * @return boolean
	 */
	public function IsDeleted(){
		return $this->_state == self::STATE_DELETED;
	}
	
	protected static $_cache = array();
	
	/**
	 * Load ActiveRecord from DB or cache.
	 * @param string $target ActiveRecord class name.
	 * @param string $query Load condition.
	 * @param array $args Arguments used in condition.
	 * @return WSAL_DB_ActiveRecord
	 */
	protected static function CacheLoad($target, $query, $args){
		$index = $target . '::' . vsprintf($query, $args);
		if(!isset(self::$_cache[$index])){
			self::$_cache[$index] = new $target();
			self::$_cache[$index]->Load($query, $args);
		}
		return self::$_cache[$index];
	}
	
	/**
	 * Remove ActiveRecord cache.
	 * @param string $target ActiveRecord class name.
	 * @param string $query Load condition.
	 * @param array $args Arguments used in condition.
	 */
	protected static function CacheRemove($target, $query, $args){
		$index = $target . '::' . sprintf($query, $args);
		if(!isset(self::$_cache[$index])){
			unset(self::$_cache[$index]);
		}
	}
	
	/**
	 * Clear the cache.
	 */
	protected static function CacheClear(){
		self::$_cache = array();
	}
}
