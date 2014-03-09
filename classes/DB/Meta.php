<?php

class WSAL_DB_Meta extends WSAL_DB_ActiveRecord {
	protected $_table = 'wsal_metadata';
	protected $_idkey = 'id';
	
	public $id = 0;
	public $occurrence_id = 0;
	public $name = '';
	public $value = array(); // force mixed type
}
