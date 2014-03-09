<?php

class WSAL_DB_Log extends WSAL_DB_ActiveRecord {
	protected $_table = 'wsal_logs';
	protected $_idkey = 'id';
	
	public $id = 0;
	public $type = 0;
	public $code = 0;
	public $message = '';
	
	protected $_latest_occurrence;
	public function GetLatestOccurrence(){
		if(!isset($this->_latest_occurrence)){
			$this->_latest_occurrence = new WSAL_DB_Occurrence();
			$sql = 'log_id = %d ORDER BY created_on DESC LIMIT 1';
			$this->_latest_occurrence->Load($sql, array($this->id));
		}
		return $this->_latest_occurrence;
	}
	
	protected $_occurrences;
	public function GetOccurrences(){
		if(!isset($this->_occurrences)){
			$this->_occurrences = WSAL_DB_Occurrence::LoadMulti('log_id = %d', array($this->id));
		}
		return $this->_occurrences;
	}
	
	public function FormatMessage($values){
		$keys = array();
		$vals = array_values($values);
		foreach(array_keys($values) as $key) $keys[] = "%$key%";
		return str_replace($keys, $vals, $this->message);
	}
}