<?php

class WSAL_DB_Occurrence extends WSAL_DB_ActiveRecord {
	protected $_table = 'wsal_occurrences';
	protected $_idkey = 'id';
	
	public $id = 0;
	public $log_id = 0;
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
	
	public function SetMeta($data){
		foreach((array)$data as $key => $val)
			$this->SetMetaValue($key, $val);
	}
	
}