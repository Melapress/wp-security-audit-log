<?php
/**
 * @package Wsal
 * MySQL database Option class.
 *
 * MySQL wsal_options table used for to store the plugin settings and Add-Ons settings.
 */
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

    /**
     * @return WSAL_Models_Option
     */
    public function GetModel()
    {
        return new WSAL_Models_Option();
    }

    /**
     * Get option by name.
     * @param string $name option_name
     * @return string|null option value
     */
    public function GetNamedOption($name)
    {
        if ($this->IsInstalled()) {
            return $this->Load('option_name = %s', array($name));
        } else {
            return null;
        }
    }

    /**
     * Get options by prefix (notifications stored in json format).
     * @param string $opt_prefix prefix
     * @return array|null options
     */
    public function GetNotificationsSetting($opt_prefix)
    {
        if ($this->IsInstalled()) {
            return $this->LoadArray('option_name LIKE %s', array($opt_prefix."%"));
        } else {
            return null;
        }
    }

    /**
     * Get option by id (notifications stored in json format).
     * @param int $id option ID
     * @return string|null option
     */
    public function GetNotification($id)
    {
        if ($this->IsInstalled()) {
            return $this->Load('id = %d', array($id));
        } else {
            return null;
        }
    }

    /**
     * Delete option by name.
     * @param string $name option_name
     * @return boolean.
     */
    public function DeleteByName($name)
    {
        if (!empty($name)) {
            $sql = "DELETE FROM " . $this->GetTable() . " WHERE option_name = '". $name ."'";
            // execute query
            return parent::DeleteQuery($sql);
        } else {
            return false;
        }
    }

    /**
     * Delete options start with prefix.
     * @param string $opt_prefix prefix
     * @return boolean.
     */
    public function DeleteByPrefix($opt_prefix)
    {
        if (!empty($opt_prefix)) {
            $sql = "DELETE FROM " . $this->GetTable() . " WHERE option_name LIKE '". $opt_prefix ."%'";
            // execute query
            return parent::DeleteQuery($sql);
        } else {
            return false;
        }
    }

    /**
     * Number of options start with prefix.
     * @param string $opt_prefix prefix
     * @return integer Indicates the number of items.
     */
    public function CountNotifications($opt_prefix)
    {
        $_wpdb = $this->connection;
        $sql = "SELECT COUNT(id) FROM " . $this->GetTable() . " WHERE option_name LIKE '". $opt_prefix ."%'";
        return (int)$_wpdb->get_var($sql);
    }
}
