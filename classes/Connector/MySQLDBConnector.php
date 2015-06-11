<?php
require_once('ConnectorInterface.php');
require_once('AbstractConnector.php');


class WSAL_Connector_MySQLDB extends WSAL_Connector_AbstractConnector implements WSAL_Connector_ConnectorInterface
{
    public function __construct()
    {
        parent::__construct("MySQL");
    }
    
    /**
     * Creates a connection and returns it
     * @param bool $is_external Returns existing wpdb connection when false
     * @return Instance of WPDB
     */
    private function createConnection($is_external = false)
    {
        if ($is_external) {
            $user = "root";
            $password = "";
            $database = "wordpress-clean-2";
            //$database = "cleanwordpress";
            $hostname = "localhost";
            $base_prefix = "wp_";
            $newWpdb = new wpdb($user, $password, $database, $hostname);
            $newWpdb->set_prefix($base_prefix);
            return $newWpdb;
        } else {
            global $wpdb;
            return $wpdb;
        }
    }

    /**
     * Returns a wpdb instance
     */
    public function getConnection($is_external = true)
    {
        if (!empty($this->connection)) {
            return $this->connection;
        } else {
            $this->connection = $this->createConnection($is_external);
            return $this->connection;
        }
    }

    /**
     * Gets an adapter for the specified model
     */
    public function getAdapter($class_name)
    {
        $objName = 'WSAL_MySQL_'.$class_name.'Adapter';
        return new $objName($this->getConnection());
    }

    /**
     * Checks if the necessary tables are available
     */
    public function isInstalled()
    {
        $wpdb = $this->getConnection();
        $table = $wpdb->base_prefix . 'wsal_occurrences';
        return ($wpdb->get_var('SHOW TABLES LIKE "'.$table.'"') == $table);
    }

    /**
     * Checks if old version tables are available
     */
    public function canMigrate()
    {
        $wpdb = $this->getConnection();
        $table = $wpdb->base_prefix . 'wordpress_auditlog_events';
        return ($wpdb->get_var('SHOW TABLES LIKE "'.$table.'"') == $table);
    }

    /**
     * Install all DB tables.
     */
    public function installAllAdapters()
    {
        $plugin = WpSecurityAuditLog::GetInstance();
        foreach (glob($this->getAdaptersDirectory() . '/*.php') as $file) {
            $class = $plugin->GetClassFileClassName($file);
            if (is_subclass_of($class, __CLASS__)) {
                $class = new $class();
                $class->Install();
            }
        }
    }
    
    /**
     * Uninstall all DB tables.
     */
    public function uninstallAllAdapters()
    {
        $plugin = WpSecurityAuditLog::GetInstance();
        foreach (glob($this->getAdaptersDirectory() . '/*.php') as $file) {
            $class = $plugin->GetClassFileClassName($file);
            if (is_subclass_of($class, __CLASS__)) {
                $class = new $class();
                $class->Uninstall();
            }
        }
    }
}
