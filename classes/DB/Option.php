<?php

class WSAL_DB_Option extends WSAL_DB_ActiveRecord 
{
	protected $_table = 'wsal_options';
	protected $_idkey = 'id';

	public $id = 0;
	public $option_name = '';
	public static $option_name_maxlength = 100;
	public $option_value = array(); // force mixed type

	public function SetOptionValue($name, $value)
	{	
		$this->GetNamedOption($name);
		$this->option_name = $name;
		$this->option_value = $value;
		$this->Save();
	}
	
	public function GetOptionValue($name, $default = array())
	{
		$this->GetNamedOption($name);
		return $this->IsLoaded() ? $this->option_value : $default;
	}

	public function GetNamedOption($name)
	{
		return $this->Load('option_name = %s', array($name));
	}

}