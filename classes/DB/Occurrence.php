<?php

class WSAL_DB_Occurrence extends WSAL_DB_ActiveRecord {
	protected $_table = 'wsal_occurrences';
	protected $_idkey = 'id';
	
	public $id = 0;
	public $log_id = 0;
	public $is_read = false;
	public $created_on = 0;
	
	protected $_meta;
	
	public function GetMeta(){
		if(!isset($this->_meta)){
			$this->_meta = WSAL_DB_Meta::LoadMulti('occurrence_id = %d', array($this->id));
		}
		return $this->_meta;
	}
	
	public function GetNamedMeta($name){
		$meta = new WSAL_DB_Meta();
		$meta->Load('occurrence_id = %d AND name = %s', array($this->id, $name));
		return $meta;
	}
	
	public function GetMetaValue($name){
		return $this->GetNamedMeta($name)->value;
	}
	
	public function SetMetaValue($name, $value){
		$meta = $this->GetNamedMeta($name);
		$meta->occurrence_id = $this->id;
		$meta->name = $name;
		$meta->value = $value;
		$meta->Save();
	}
	
	public function GetMetaArray(){
		$result = array();
		foreach($this->GetMeta() as $meta)
			$result[$meta->name] = $meta->value;
		return $result;
	}
	
	public function SetMeta($data){
		foreach((array)$data as $key => $val)
			$this->SetMetaValue($key, $val);
	}
	
	/**
	 * @return WSAL_DB_Log
	 */
	public function GetLog(){
		return self::CacheLoad('WSAL_DB_Log', 'id = %d', array($this->log_id));
	}
	
	/**
	 * @return string Full-formatted message.
	 */
	public function GetMessage(){
		if(!isset($this->_cachedmessage))
			$this->_cachedmessage = $this->GetLog()->FormatMessage($this->GetMetaArray());
		return $this->_cachedmessage;
	}
	
	/**
	 * Returns newest unique occurrences.
	 * @param integer $limit Maximum limit.
	 * @return WSAL_DB_Occurrence[]
	 */
	public static function GetNewestUnique($limit = PHP_INT_MAX){
		$temp = new self();
		return self::LoadMultiQuery('
			SELECT *
			FROM (
				SELECT *
				FROM ' . $temp->GetTable() . '
				ORDER BY created_on DESC
			) AS temp_table
			GROUP BY log_id
			LIMIT %d
		', array($limit));
	}
	
}