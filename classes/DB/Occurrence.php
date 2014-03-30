<?php

class WSAL_DB_Occurrence extends WSAL_DB_ActiveRecord {
	protected $_table = 'wsal_occurrences';
	protected $_idkey = 'id';
	
	public $id = 0;
	public $alert_id = 0;
	public $created_on = 0;
	public $is_read = false;
	public $is_migrated = false;
	
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
	
	/**
	 * Returns the first meta value from a given set of names. Useful when you have a mix of items that could provide a particular detail.
	 * @param array $names List of meta names.
	 * @return \WSAL_DB_Meta The first meta item that exists.
	 */
	public function GetFirstNamedMeta($names){
		$meta = new WSAL_DB_Meta();
		$query = '(' . str_repeat('name = %s OR ', count($names)).'0)';
		$query = 'occurrence_id = %d AND ' . $query . ' LIMIT 1';
		array_unshift($names, $this->id); // prepend args with occurrence id
		$meta->Load($query, $names);
		return $meta->IsLoaded() ? $meta : null;
	}
	
	public function GetAlert(){
		return WpSecurityAuditLog::GetInstance()->alerts->GetAlert($this->alert_id);
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
	
	protected function GetMetaExprValue($expr){
		// TODO Handle function calls (and methods?)
		$expr = explode('->', $expr);
		$meta = array_shift($expr);
		$meta = $this->GetMetaValue($meta);
		foreach($expr as $part){
			if(is_scalar($meta))return $meta; // this isn't 100% correct
			$meta = is_array($meta) ? $meta[$part] : $meta->$part;
		}
		return (string)$meta;
	}
	
	protected function GetFormattedMesg($mesg){
		// tokenize message with regex
		$mesg = preg_split('/(%.*?%)/', $mesg, -1, PREG_SPLIT_DELIM_CAPTURE);
		// handle tokenized message
		foreach($mesg as $i=>$token){
			// handle escaped percent sign
			if($token == '%%'){
				$mesg[$i] = '%';
			}else
			// handle complex expressions
			if(substr($token, 0, 1) == '%' && substr($token, -1, 1) == '%'){
				$mesg[$i] = $this->GetMetaExprValue(substr($token, 1, -1));
			}
		}
		// compact message and return
		return implode('', $mesg);
	}
	
	/**
	 * @return string Full-formatted message.
	 */
	public function GetMessage(){
		if(!isset($this->_cachedmessage)){
			// get correct message entry
			if($this->is_migrated){
				$this->_cachedmessage = $this->GetMetaValue('MigratedMesg');
			}else{
				$this->_cachedmessage = $this->GetAlert()->mesg;
			}
			// fill variables in message
			$this->_cachedmessage = $this->GetFormattedMesg($this->_cachedmessage);
		}
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
			SELECT *, COUNT(alert_id) as count
			FROM (
				SELECT *
				FROM ' . $temp->GetTable() . '
				ORDER BY created_on DESC
			) AS temp_table
			GROUP BY alert_id
			LIMIT %d
		', array($limit));
	}
	
}