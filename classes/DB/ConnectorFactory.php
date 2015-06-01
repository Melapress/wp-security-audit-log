<?php
require_once('MySQLDBConnector.php');

abstract class WSAL_ConnectorFactory
{
	public static $connector;

	public function __construct() {
		
	}

	public static function GetConnector($type = '') {
		switch ($type) {
	        case 'mariadb':
	    		
	 			break;
	        case 'pgsql':
	        	
	            break;
	        case 'sybase':
	        	
	            break;
	        case 'oracle':
	        	
	            break;
	        case 'mssql':
	        	
	            break;
	        case 'sqlite':

	            break;
	        default :
	        	$conn = new WSAL_MySQL_DB_Connector();
	        	self::$connector = $conn->GetConnection();
	    }
	    return self::$connector;
	}

	public static function GetAdapter($class_name) {
		if (!empty(self::$connector)) {
			$adapter = self::$connector->GetAdapter($class_name);
			return $adapter;
		}
	}
    
}