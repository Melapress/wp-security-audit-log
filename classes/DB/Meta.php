<?php

class WSAL_DB_Meta extends WSAL_DB_ActiveRecord {
	protected $_table = 'wsal_metadata';
	protected $_idkey = 'id';
	
	public $id = 0;
	public $occurrence_id = 0;
	public $name = '';
	public static $name_maxlength = 100;
	public $value = array(); // force mixed type
	
	protected function GetTableOptions(){
		return parent::GetTableOptions() . ',' . PHP_EOL
				. '    KEY occurrence_name (occurrence_id,name)';
	}
}
