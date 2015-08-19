<?php
require_once('ConnectorInterface.php');
require_once('AbstractConnector.php');


class WSAL_Connector_MySQLDB extends WSAL_Connector_AbstractConnector implements WSAL_Connector_ConnectorInterface
{
    protected $connectionConfig = null;
    protected $key = null;
    public function __construct($connectionConfig = null)
    {
        $this->connectionConfig = $connectionConfig;
        parent::__construct("MySQL");
        require_once($this->getAdaptersDirectory() . '/OptionAdapter.php');
        $this->key = pack('H*', "bcb04b7e103a0cd8b54763051cef08bc55abe029fdebae5e1d417e2ffb2a00a3");
    }

    function test_wp_die_callback() {
        return array( $this, 'test_die_handler' );
    }

    function test_die_handler( $message, $title = '', $args = array() ) {       
       throw new Exception("DB Connection failed");
    }
    
    public function TestConnection()
    {
        error_reporting(E_ALL ^ E_WARNING);
        add_filter( 'wp_die_handler', array( $this, 'test_wp_die_callback' ) );
        $connection = $this->createConnection();
    }

    /**
     * Creates a connection and returns it
     * @return Instance of WPDB
     */
    private function createConnection()
    {
        if (!empty($this->connectionConfig)) {
            //TO DO: Use the provided connection config
            $connectionConfig = $this->connectionConfig;
            $password = $this->decryptString($connectionConfig['password']);
            $newWpdb = new wpdb($connectionConfig['user'], $password, $connectionConfig['name'], $connectionConfig['hostname']);
            $newWpdb->set_prefix($connectionConfig['base_prefix']);
            return $newWpdb;
        } else {
            global $wpdb;
            return $wpdb;
        }
    }

    /**
     * Returns a wpdb instance
     */
    public function getConnection()
    {
        if (!empty($this->connection)) {
            return $this->connection;
        } else {
            $this->connection = $this->createConnection();
            return $this->connection;
        }
    }

    /**
     * Gets an adapter for the specified model
     */
    public function getAdapter($class_name)
    {
        $objName = $this->getAdapterClassName($class_name);
        return new $objName($this->getConnection());
    }

    protected function getAdapterClassName($class_name)
    {
        return 'WSAL_Adapters_MySQL_'.$class_name;
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
    public function installAll($excludeOptions = false)
    {
        $plugin = WpSecurityAuditLog::GetInstance();

        foreach (glob($this->getAdaptersDirectory() . DIRECTORY_SEPARATOR . '*.php') as $file) {
            $filePath = explode(DIRECTORY_SEPARATOR, $file);
            $fileName = $filePath[count($filePath) - 1];
            $className = $this->getAdapterClassName(str_replace("Adapter.php", "", $fileName));

            $class = new $className($this->getConnection());
            if ($excludeOptions && $class instanceof WSAL_Adapters_MySQL_Option) {
                continue;
            }
            
            if (is_subclass_of($class, "WSAL_Adapters_MySQL_ActiveRecord")) {
                $class->Install();
            }
        }
    }
    
    /**
     * Uninstall all DB tables.
     */
    public function uninstallAll()
    {
        $plugin = WpSecurityAuditLog::GetInstance();

        foreach (glob($this->getAdaptersDirectory() . DIRECTORY_SEPARATOR . '*.php') as $file) {
            $filePath = explode(DIRECTORY_SEPARATOR, $file);
            $fileName = $filePath[count($filePath) - 1];
            $className = $this->getAdapterClassName(str_replace("Adapter.php", "", $fileName));

            $class = new $className($this->getConnection());
            if (is_subclass_of($class, "WSAL_Adapters_MySQL_ActiveRecord")) {
                $class->Uninstall();
            }
        }
    }

    public function Migrate()
    {
        global $wpdb;
        $_wpdb = $this->getConnection();

        // Load data Occurrences
        $occurrence = new WSAL_Adapters_MySQL_Occurrence($wpdb);
        $sql = 'SELECT * FROM ' . $occurrence->GetTable();
        $occurrences = $wpdb->get_results($sql, ARRAY_A);

        // Insert data
        $occurrenceNew = new WSAL_Adapters_MySQL_Occurrence($_wpdb);
        $sql = 'INSERT INTO ' . $occurrenceNew->GetTable() . ' (site_id, alert_id, created_on, is_read, is_migrated) VALUES ' ;
        foreach ($occurrences as $entry) {
            $sql .= '('.$entry['site_id'].', '.$entry['alert_id'].', '.$entry['created_on'].', '.$entry['is_read'].', 1), ';
        }
        $sql = rtrim($sql, ", ");
        $_wpdb->query($sql);

        // Load data Meta
        $meta = new WSAL_Adapters_MySQL_Meta($wpdb);
        $sql = 'SELECT * FROM ' . $meta->GetTable();
        $metadata = $wpdb->get_results($sql, ARRAY_A);

        // Insert data
        $metaNew = new WSAL_Adapters_MySQL_Meta($_wpdb);
        $sql = 'INSERT INTO ' . $metaNew->GetTable() . ' (occurrence_id, name, value) VALUES ' ;
        foreach ($metadata as $entry) {
            $sql .= '('.$entry['occurrence_id'].', \''.$entry['name'].'\', \''.$entry['value'].'\'), ';
        }
        $sql = rtrim($sql, ", ");
        $_wpdb->query($sql);
    }

    public function encryptString($plaintext)
    {
        $key = pack('H*', "bcb04b7e103a0cd8b54763051cef08bc55abe029fdebae5e1d417e2ffb2a00a3");

        # create a random IV to use with CBC encoding
        $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
        $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);

        # creates a cipher text compatible with AES (Rijndael block size = 128)
        # to keep the text confidential
        # only suitable for encoded input that never ends with value 00h
        # (because of default zero padding)
        $ciphertext = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $plaintext, MCRYPT_MODE_CBC, $iv);

        # prepend the IV for it to be available for decryption
        $ciphertext = $iv . $ciphertext;
        
        # encode the resulting cipher text so it can be represented by a string
        $ciphertext_base64 = base64_encode($ciphertext);
        
        return $ciphertext_base64;
    }
    

    # === WARNING ===

    # Resulting cipher text has no integrity or authenticity added
    # and is not protected against padding oracle attacks.
    
    # --- DECRYPTION ---
    private function decryptString($ciphertext_base64)
    {
        $key = pack('H*', "bcb04b7e103a0cd8b54763051cef08bc55abe029fdebae5e1d417e2ffb2a00a3");
        $ciphertext_dec = base64_decode($ciphertext_base64);
        $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
    
        # retrieves the IV, iv_size should be created using mcrypt_get_iv_size()
        $iv_dec = substr($ciphertext_dec, 0, $iv_size);
        
        # retrieves the cipher text (everything except the $iv_size in the front)
        $ciphertext_dec = substr($ciphertext_dec, $iv_size);

        # may remove 00h valued characters from end of plain text
        $plaintext_dec = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $ciphertext_dec, MCRYPT_MODE_CBC, $iv_dec);
        
        return rtrim($plaintext_dec, "\0");
    }
    
}
