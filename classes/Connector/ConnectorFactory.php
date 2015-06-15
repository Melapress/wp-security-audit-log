<?php
require_once('MySQLDBConnector.php');

abstract class WSAL_Connector_ConnectorFactory
{
    public static $connector;
    public static $defaultConnector;
    public static $adapter;

    /**
     * Returns the a default WPDB connector for saving options
     */
    public static function GetDefaultConnector()
    {
         return new WSAL_Connector_MySQLDB();
    }

    /**
     * Returns a connector singleton
     * @return WSAL_Connector_ConnectorInterface
     */
    public static function GetConnector()
    {
        $connectionConfig = array();
        //TO DO: Load connection config

        $type = "mysql"; //Use type from config

        if (self::$connector == null) {
            switch (strtolower($type)) {
                //TO DO: Add other connectors
                case 'mysql':
                default:
                    //use config
                    self::$connector = new WSAL_Connector_MySQLDB($connectionConfig);
            }
        }
        return self::$connector;
    }
}
