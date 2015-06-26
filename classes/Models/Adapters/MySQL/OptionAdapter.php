<?php

class WSAL_Adapters_MySQL_Option extends WSAL_Adapters_MySQL_ActiveRecord
{

    protected $_table = 'wsal_options';
    protected $_idkey = 'id';

    public $id = 0;
    public $option_name = '';
    public static $option_name_maxlength = 100;
    public $option_value = '';
    
    public function __construct($conn)
    {
        parent::__construct($conn);
    }


    public function GetModel()
    {
        return new WSAL_Models_Option();
    }

    public function GetNamedOption($name)
    {
        return $this->Load('option_name = %s', array($name));
    }

}
