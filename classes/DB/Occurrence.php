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
	
	/**
	 * @return string Full-formatted message.
	 */
	public function GetMessage(){
		if(!isset($this->_cachedmessage)){
			if($this->is_migrated){
				$tmp = $this->GetMetaValue('MigratedMesg');
			}else{
				$tmp = $this->GetAlert()->mesg;
			}
			// TODO Support object traversal
			// TODO Support method execution
			// TODO Read metadata on-demand
			$values = $this->GetMetaArray();
			$keys = array();
			$vals = array();
			foreach($values as $key => $val){
				if(is_scalar($val)){
					$keys[] = "%$key%";
					$vals[] = $val;
				}
			}
			$tmp = str_replace($keys, $vals, $tmp);
			$this->_cachedmessage = $tmp;
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
			SELECT *
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