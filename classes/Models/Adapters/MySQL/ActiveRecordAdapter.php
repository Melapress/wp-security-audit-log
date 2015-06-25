<?php

class WSAL_Adapters_MySQL_ActiveRecord implements WSAL_Adapters_ActiveRecordInterface {
    
    protected $connection;

    /**
     * Contains the table name
     * @var string
     */
    protected $_table;

    /**
     * Contains primary key column name, override as required.
     * @var string
     */
    protected $_idkey = '';

    public function __construct($conn)
    {
        $this->connection = $conn;
    }

    public function GetModel()
    {
        return new WSAL_Models_ActiveRecord(); 
    }
    
    /**
     * @return string Returns table name.
     */
    public function GetTable()
    {
        //global $wpdb;
        $_wpdb = $this->connection;
        return $_wpdb->base_prefix . $this->_table;
    }
    
    /**
     * @return string SQL table options (constraints, foreign keys, indexes etc).
     */
    protected function GetTableOptions()
    {
        return '    PRIMARY KEY  (' . $this->_idkey . ')';
    }
    
    /**
     * @return array Returns this records' columns.
     */
    public function GetColumns()
    {
        $model = $this->GetModel();
        
        if(!isset($this->_column_cache)){
            $this->_column_cache = array();
            foreach(array_keys(get_object_vars($model)) as $col)
                if(trim($col) && $col[0] != '_')
                    $this->_column_cache[] = $col;
        }
        return $this->_column_cache;
    }
    
    /**
     * @deprecated
     * @return boolean Returns whether table structure is installed or not.
     */
    public function IsInstalled(){
        //global $wpdb;
        $_wpdb = $this->connection;
        $sql = 'SHOW TABLES LIKE "' . $this->GetTable() . '"';
        return $_wpdb->get_var($sql) == $this->GetTable();
    }
    
    /**
     * Install this ActiveRecord structure into DB.
     */
    public function Install(){
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($this->_GetInstallQuery());
    }
    
    /**
     * Remove this ActiveRecord structure into DB.
     */
    public function Uninstall()
    {
        //global $wpdb;
        $_wpdb = $this->connection;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $_wpdb->query($this->_GetUninstallQuery());
    }
    
    /**
     * Save an active record to DB.
     * @return integer|boolean Either the number of modified/inserted rows or false on failure.
     */
    public function Save($activeRecord)
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
    
    /**
     * Load record from DB.
     * @param string $cond (Optional) Load condition.
     * @param array $args (Optional) Load condition arguments.
     */
    public function Load($cond = '%d', $args = array(1))
    {
        //global $wpdb;
        $_wpdb = $this->connection;
        
        $sql = $_wpdb->prepare('SELECT * FROM '.$this->GetTable().' WHERE '. $cond, $args);
        $data = $_wpdb->get_row($sql, ARRAY_A);

        return $data;
    }

    public function LoadArray($cond, $args = array())
    {
        //global $wpdb;
        $_wpdb = $this->connection;
        $result = array();
        $sql = $_wpdb->prepare('SELECT * FROM '.$this->GetTable().' WHERE '. $cond, $args);
        foreach ($_wpdb->get_results($sql, ARRAY_A) as $data) {
            $result[] = $data;
        }
        return $result;

    }
    
    /**
     * Delete DB record.
     * @return int|boolean Either the amount of deleted rows or False on error.
     */
    public function Delete($activeRecord)
    {
        //global $wpdb;
        $_wpdb = $this->connection;
        $result = $_wpdb->delete(
            $this->GetTable(),
            $activeRecord->getId()
        );
        return $result;
    }
    
    /**
     * Delete records in DB matching a query.
     * @param string $query Full SQL query.
     * @param array $args (Optional) Query arguments.
     */
    public function DeleteQuery($query, $args = array())
    {
        //global $wpdb;
        $_wpdb = $this->connection;
        $sql = count($args) ? $_wpdb->prepare($query, $args) : $query;
        $_wpdb->query($sql);
    }
    
    /**
     * Load multiple records from DB.
     * @param string $cond (Optional) Load condition (eg: 'some_id = %d' ).
     * @param array $args (Optional) Load condition arguments (rg: array(45) ).
     * @return self[] List of loaded records.
     */
    public function LoadMulti($cond, $args = array())
    {
        //global $wpdb;
        $_wpdb = $this->connection;
        $result = array();
        $sql = (!is_array($args) || !count($args)) // do we really need to prepare() or not?
            ? ($cond)
            : $_wpdb->prepare($cond, $args)
        ;
        foreach ($_wpdb->get_results($sql, ARRAY_A) as $data) {
            $result[] = $this->getModel()->LoadData($data);
        }
        return $result;

    }
    
    /**
     * Load multiple records from DB and call a callback for each record.
     * This function is very memory-efficient, it doesn't load records in bulk.
     * @param callable $callback The callback to invoke.
     * @param string $cond (Optional) Load condition.
     * @param array $args (Optional) Load condition arguments.
     */
    public function LoadAndCallForEach($callback, $cond = '%d', $args = array(1))
    {
        //global $wpdb;
        $_wpdb = $this->connection;
        $class = get_called_class();
        $sql = $_wpdb->prepare('SELECT * FROM ' . $this->GetTable() . ' WHERE '.$cond, $args);
        foreach ($_wpdb->get_results($sql, ARRAY_A) as $data) {
            call_user_func($callback, new $class($data));
        }
    }
    
    /**
     * Count records in the DB matching a condition.
     * If no parameters are given, this counts the number of records in the DB table.
     * @param string $cond (Optional) Query condition.
     * @param array $args (Optional) Condition arguments.
     * @return int Number of matching records.
     */
    public function Count($cond = '%d', $args = array(1))
    {
        //global $wpdb;
        $_wpdb = $this->connection;
        $class = get_called_class();
        $sql = $_wpdb->prepare('SELECT COUNT(*) FROM ' . $this->GetTable() . ' WHERE ' . $cond, $args);
        return (int)$_wpdb->get_var($sql);
    }
    
    /**
     * Count records in the DB matching a query.
     * @param string $query Full SQL query.
     * @param array $args (Optional) Query arguments.
     * @return int Number of matching records.
     */
    public function CountQuery($query, $args = array())
    {
        //global $wpdb;
        $_wpdb = $this->connection;
        $sql = count($args) ? $_wpdb->prepare($query, $args) : $query;
        return (int)$_wpdb->get_var($sql);
    }
    
    /**
     * Similar to LoadMulti but allows the use of a full SQL query.
     * @param string $query Full SQL query.
     * @param array $args (Optional) Query arguments.
     * @return self[] List of loaded records.
     */
    public function LoadMultiQuery($query, $args = array())
    {
        //global $wpdb;
        $_wpdb = $this->connection;
        $class = get_called_class();
        $result = array();
        $sql = count($args) ? $_wpdb->prepare($query, $args) :  $query;
        foreach ($_wpdb->get_results($sql, ARRAY_A) as $data) {
            $result[] = $this->getModel()->LoadData($data);
        }
        return $result;
    }

    /**
     * @return string Must return SQL for creating table.
     */
    protected function _GetInstallQuery()
    {
        $wpdb = $this->connection;
        
        $class = get_class($this);
        $copy = new $class($this->connection);
        
        $sql = 'CREATE TABLE ' . $this->GetTable() . ' (' . PHP_EOL;
        
        foreach ($this->GetColumns() as $key) {
            $sql .= '    ';
            switch(true) {
                case $key == $copy->_idkey:
                    $sql .= $key . ' BIGINT NOT NULL AUTO_INCREMENT,' . PHP_EOL;
                    break;
                case is_integer($copy->$key):
                    $sql .= $key . ' BIGINT NOT NULL,' . PHP_EOL;
                    break;
                case is_float($copy->$key):
                    $sql .= $key . ' DOUBLE NOT NULL,' . PHP_EOL;
                    break;
                case is_string($copy->$key):
                    $maxlength = $key . '_maxlength';
                    if (property_exists($class, $maxlength)) {
                        $sql .= $key . ' VARCHAR(' . intval($class::$$maxlength) . ') NOT NULL,' . PHP_EOL;
                    } else {
                        $sql .= $key . ' TEXT NOT NULL,' . PHP_EOL;
                    }
                    break;
                case is_bool($copy->$key):
                    $sql .= $key . ' BIT NOT NULL,' . PHP_EOL;
                    break;
                case is_array($copy->$key):
                case is_object($copy->$key):
                    $sql .= $key . ' LONGTEXT NOT NULL,' . PHP_EOL;
                    break;
            }
        }
        
        $sql .= $this->GetTableOptions() . PHP_EOL;
        
        $sql .= ')';
        
        if (! empty($wpdb->charset)) {
            $sql .= ' DEFAULT CHARACTER SET ' . $wpdb->charset;
        }
        if (! empty($wpdb->collate)) {
            $sql .= ' COLLATE ' . $wpdb->collate;
        }
        
        return $sql;
        
    }
}
