<?php
/**
 * @package Wsal
 * MySQL database TmpUser class.
 *
 * This class is used for create a temporary table to store the WP users
 * when the External DB Add-On is activated and the Alerts are stored on an external DB
 * because the query between plugin tables and the internal wp_uses table is not possible.
 * @see  WSAL_Adapters_MySQL_ActiveRecord->GetReportGrouped()
 */
class WSAL_Adapters_MySQL_TmpUser extends WSAL_Adapters_MySQL_ActiveRecord {

    protected $_table = 'wsal_tmp_users';

    /**
     * @return WSAL_Models_TmpUser
     */
    public function GetModel()
    {
        return new WSAL_Models_TmpUser();
    }
    
    public function __construct($conn)
    {
        parent::__construct($conn);
    }
    
    /**
     * @return string Must return SQL for creating table.
     */
    protected function _GetInstallQuery($prefix = false)
    {
        $_wpdb = $this->connection;
        $table_name = ($prefix) ? $this->GetWPTable() : $this->GetTable();
        $sql = 'CREATE TABLE IF NOT EXISTS ' . $table_name . ' (' . PHP_EOL;
        $sql .= 'ID BIGINT NOT NULL,' . PHP_EOL;
        $sql .= 'user_login VARCHAR(60) NOT NULL,' . PHP_EOL;
        $sql .= 'INDEX (ID)' . PHP_EOL;
        $sql .= ')';
        if (!empty($_wpdb->charset)) {
            $sql .= ' DEFAULT CHARACTER SET ' . $_wpdb->charset;
        }
        return $sql;
    }
}
