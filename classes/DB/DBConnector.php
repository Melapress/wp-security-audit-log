<?php

abstract class WSAL_DB_Connector
{
	protected static $_connection = NULL;

	public function __construct()
	{
		
	}
    
    /**
	 *
	 */
	protected static function CreateConnection()
	{
		$user = "root";
		$password = "";
		$database = "wordpress-clean-2";
		//$database = "cleanwordpress";
		$hostname = "localhost";
		$base_prefix = "wp_";
		$newWpdb = new wpdb($user, $password, $database, $hostname);
		$newWpdb->set_prefix($base_prefix);
		self::$_connection = $newWpdb;
	}

	/**
	 *
	 */
	public static function GetConnection($is_external = true)
	{
		if ($is_external) {
			if (!empty(self::$_connection)) {
				return self::$_connection; 
			} else {
				self::CreateConnection();
				return self::$_connection;
			}
		} else {
			global $wpdb;
			return $wpdb;
		}
	}
}