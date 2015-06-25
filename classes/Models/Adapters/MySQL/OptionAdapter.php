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

    public function SaveOptions($activeRecord)
    {
        //global $wpdb;
        $_wpdb = $this->connection; 
        $copy = $activeRecord;
        $data = array();
        $format = array();
        foreach ($this->GetColumns() as $key) {

            $val = $copy->$key;
            $deffmt = '%s';
            if (is_int($copy->$key)) {
              $deffmt = '%d';
            }
            if (is_float($copy->$key)) {
                $deffmt = '%f';
            }
            if (is_array($copy->$key) || is_object($copy->$key)) {
                $data[$key] = WSAL_Helpers_DataHelper::JsonEncode($val);
            } else {
                $data[$key] = $val;
            }
            $format[] = $deffmt;
        }
        $result = $_wpdb->replace($this->GetTable(), $data, $format);
            
        if ($result !== false) {
            if ($_wpdb->insert_id) {
                $copy->setId($_wpdb->insert_id);
            }
        }
        return $result;
    }
}
