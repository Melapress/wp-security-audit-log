<?php

class WSAL_Models_Meta extends WSAL_Models_ActiveRecord {
	
	protected $adapterName = "Meta"; 

	public $id = 0;
	public $occurrence_id = 0;
	public $name = '';
	public static $name_maxlength = 100;
	public $value = array(); // force mixed type


}
