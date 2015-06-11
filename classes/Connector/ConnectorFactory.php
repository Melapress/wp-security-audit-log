<?php
require_once('MySQLDBConnector.php');

abstract class WSAL_Connector_ConnectorFactory
{
	public static $connector;
	public static $adapter;

	/**
	 * Returns a connector singleton
	 * @return WSAL_Connector_ConnectorInterface
	 */
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
					self::$connector = new WSAL_Connector_MySQLDB();
			}
		}
		return self::$connector;
	}
    
}