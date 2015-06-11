<?php
require_once(__DIR__ . '/../Connector/ConnectorFactory.php');

abstract class WSAL_Models_ActiveRecord{

	/**
	 * @var_$connector Data connector;
	 */
	protected $connector;

	protected $id = false;

	protected $adapterName = null;

	public function setId($id)
	{
		$this->id = $id;
	}

	public function getId()
	{
		return $this->id;
	}
	
	const STATE_UNKNOWN  = 'unknown';
	const STATE_CREATED  = 'created';
	const STATE_UPDATED  = 'updated';
	const STATE_DELETED  = 'deleted';
	const STATE_LOADED   = 'loaded';

	protected $_state = self::STATE_UNKNOWN;

	public function __construct($data = null) {
		if(!$this->adapterName)
			throw new Exception('Class "' . __CLASS__ . '" requires "adapterName" to be set.');
		$this->connector = $this->getConnector();
		if(!is_null($data)){
			$this->LoadData($data);
			$this->_state = self::STATE_LOADED;
		}
	}

	protected function getConnector()
	{
		return WSAL_Connector_ConnectorFactory::GetConnector();
	}

	protected function getAdapter()
	{
		return $this->connector->getAdapter($this->adapterName);
	}
	

	/**
	 * Load record from DB.
	 * @param string $cond (Optional) Load condition.
	 * @param array $args (Optional) Load condition arguments.
	 */
	public function Load($cond = '%d', $args = array(1)){
		$this->_state = self::STATE_UNKNOWN;

		$data = $this->getAdapter()->Load($cond, $args);
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
	 * Save this active record
	 * @return integer|boolean Either the number of modified/inserted rows or false on failure.
	 */
	public function Save()
	{
		$this->_state = self::STATE_UNKNOWN;

		$updateId = $this->getId();
		$result = $this->getAdapter()->Save($this);
		if ($result !== false) {
			$this->_state = (!empty($updateId))?self::STATE_UPDATED:self::STATE_CREATED;
		}
		return $result;
	}

	/**
	 * Deletes this active record
	 */
	public function Delete()
	{
		$this->_state = self::STATE_UNKNOWN;
		$result = $this->getAdapter()->Delete($this);
		if($result !== false)
			$this->_state = self::STATE_DELETED;
		
		return $result;
	}

	public function Count($cond = '%d', $args = array(1)) {
		$result = $this->getAdapter()->Count($cond, $args);
		return $result;
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
	 * @return WSAL_Models_ActiveRecord
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
