<?php
require_once('Connector.php');


class WSAL_MySQL_DB_Connector implements Connector
{
	protected static $connection = NULL;

	public function __construct() {
		require_once('AdapterInterfaces/ActiveRecordInterface.php');
		require_once('AdapterInterfaces/MetaInterface.php');
		require_once('AdapterInterfaces/OccurrenceInterface.php');
		require_once('AdapterInterfaces/QueryInterface.php');
		require_once('AdapterInterfaces/OccurrenceQueryInterface.php');

		require_once('MySQLModelAdapters/ActiveRecordAdapter.php');
		require_once('MySQLModelAdapters/MetaAdapter.php');
		require_once('MySQLModelAdapters/OccurrenceAdapter.php');
		require_once('MySQLModelAdapters/QueryAdapter.php');
		require_once('MySQLModelAdapters/OccurrenceQueryAdapter.php');
		$this->GetConnection();
	}
    
    /**
	 *
	 */
	private static function CreateConnection() {
		$user = "root";
		$password = "";
		$database = "wordpress-clean-2";
		//$database = "cleanwordpress";
		$hostname = "localhost";
		$base_prefix = "wp_";
		$newWpdb = new wpdb($user, $password, $database, $hostname);
		$newWpdb->set_prefix($base_prefix);
		self::$connection = $newWpdb;
	}

	/**
	 *
	 */
	public static function GetConnection($is_external = true) {
		if ($is_external) {
			if (!empty(self::$connection)) {
				return self::$connection; 
			} else {
				self::CreateConnection();
				return self::$connection;
			}
		} else {
			global $wpdb;
			self::$connection = $wpdb;
			return self::$connection;
		}
	}

	public static function GetAdapter($class_name) { error_log($class_name);
		$objName = 'WSAL_MySQL_'.$class_name.'Adapter';
		return new $objName(self::$connection);
	}
}