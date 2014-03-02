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
		
		$sql = 'CREATE TABLE ' . $this->GetTable() . ' (' . PHP_EOL;
		foreach($this->GetColumns() as $key) {
			switch(true) {
				case $key == $this->_idkey:
					$sql .= $key . ' BIGINT NOT NULL AUTO_INCREMENT,'.PHP_EOL;
					break;
				case is_integer($this->$key):
					$sql .= $key . ' BIGINT NOT NULL,'.PHP_EOL;
					break;
				case is_float($this->$key):
					$sql .= $key . ' FLOAT NOT NULL,'.PHP_EOL;
					break;
				case is_string($this->$key):
					$sql .= $key . ' TEXT NOT NULL,'.PHP_EOL;
					break;
				case is_bool($this->$key):
					$sql .= $key . ' BIT NOT NULL,'.PHP_EOL;
					break;
				case is_array($this->$key):
				case is_object($this->$key):
					$sql .= $key . ' LONGTEXT NOT NULL,'.PHP_EOL;
					break;
			}
		}
		$sql .= 'CONSTRAINT PK_' . $this->GetTable().'_'.$this->_idkey
			. ' PRIMARY KEY (' . $this->_idkey . ')' . PHP_EOL
			. ' )';
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
	 * @return string Returns table name.
	 */
	public function GetTable(){
		global $wpdb;
		return $wpdb->prefix . $this->_table;
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
	 * @return boolean Returns whether table structure is installed or not.
	 */
	public function IsInstalled(){
		global $wpdb;
		$sql = 'SHOW TABLES LIKE ' . $this->GetTable();
		return $wpdb->get_var($sql) == $this->GetTable();
	}
	
	/**
	 * Install this ActiveRecord structure into DB.
	 */
	public function Install(){
		if(!$this->IsInstalled()) {
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($this->_GetInstallQuery());
		}
	}
	
	/**
	 * Remove this ActiveRecord structure into DB.
	 */
	public function Uninstall(){
		if($this->IsInstalled()) {
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($this->_GetUninstallQuery());
		}
	}
	
	/**
	 * Save record to DB.
	 */
	public function Save(){
		$this->_state = self::STATE_UNKNOWN;
		global $wpdb;
		$data = array();
		$format = array();
		foreach($this->GetColumns() as $key){
			$val = $this->$key;
			$deffmt = '%s';
			if(is_int($val))$deffmt = '%d';
			if(is_float($val))$deffmt = '%f';
			if(is_array($val) || is_object($val)){
				$data[$key] = json_encode($val);
			}else{
				$data[$key] = $val;
			}
			$format[] = $deffmt;
		}
		$wpdb->replace($this->GetTable(), $data, $format);
		if($wpdb->insert_id){
			$this->{$this->_idkey} = $wpdb->insert_id;
			$this->_state = self::STATE_CREATED;
		}else{
			$this->_state = self::STATE_UPDATED;
		}
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
		$this->LoadData($wpdb->get_row($sql, ARRAY_A));
		$this->_state = self::STATE_LOADED;
	}
	
	/**
	 * Load object data from variable.
	 * @param array|object $data Data array or object.
	 */
	public function LoadData($data){
		foreach((array)$data as $key => $val){
			if(isset($this->$key)){
				switch(true){
					case is_array($this->$key):
						$this->$key = (array)json_decode($val);
						break;
					case is_object($this->$key):
						$this->$key = (object)json_decode($val);
						break;
					case is_int($this->$key):
						$this->$key = (int)$val;
						break;
					case is_float($this->$key):
						$this->$key = (float)$val;
						break;
					case is_bool($this->$key):
						$this->$key = (bool)$val;
						break;
					default:
						throw new Exception('Unsupported type "'.gettype($this->$key).'"');
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
		return $wpdb->delete(
			$this->GetTable(),
			array($this->_idkey => $this->{$this->_idkey})
		);
	}
	
	/**
	 * Load multiple records from DB.
	 * @param string $cond (Optional) Load condition.
	 * @param array $args (Optional) Load condition arguments.
	 * @return array List of loaded records.
	 */
	public static function LoadMulti($cond = '%d', $args = array(1)){
		global $wpdb;
		$class = get_called_class();
		$result = array();
		$sql = $wpdb->prepare('SELECT * FROM '.$this->GetTable().' WHERE '.$cond, $args);
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
		$sql = $wpdb->prepare('SELECT * FROM '.$this->GetTable().' WHERE '.$cond, $args);
		foreach($wpdb->get_results($sql, ARRAY_A) as $data){
			$callback(new $class($data));
		}
	}
	
	public static function InstallAll(){
		$plugin = WpSecurityAuditLog::GetInstance();
		foreach(glob(dirname(__FILE__) . '/*.php') as $file){
			$class = $plugin->GetClassFileClassName($file);
			if($class != __CLASS__){
				$class = new $class();
				$class->Install();
			}
		}
	}
	
	public static function UninstallAll(){
		$plugin = WpSecurityAuditLog::GetInstance();
		foreach(glob(dirname(__FILE__) . '/*.php') as $file){
			$class = $plugin->GetClassFileClassName($file);
			if($class != __CLASS__){
				$class = new $class();
				$class->Uninstall();
			}
		}
	}
}
