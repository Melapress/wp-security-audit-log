<?php
require_once('MySQLDBConnector.php');

abstract class WSAL_Models_ConnectorFactory
{
	public static $connector;
	public static $adapter;

	public static function GetConnector() {
		//open config file
		$type = "mysql"; //check type from config
		
		if (self::$connector == null) {
			switch (strtolower($type)) {
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
					//use config
					self::$connector = new WSAL_MySQL_DB_Connector();
			}
		}
		return self::$connector;
	}
    
}