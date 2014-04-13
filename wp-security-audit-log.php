<?php
/*
Plugin Name: WP Security Audit Log
Plugin URI: http://www.wpwhitesecurity.com/wordpress-security-plugins/wp-security-audit-log/
Description: Identify WordPress security issues before they become a problem and keep track of everything happening on your WordPress, including WordPress users activity. Similar to Windows Event Log and Linux Syslog, WP Security Audit Log will generate a security alert for everything that happens on your WordPress blog or website. Use the Audit Log Viewer included in the plugin to see all the security alerts.
Author: WP White Security
Version: 1.0
Author URI: http://www.wpwhitesecurity.com/
License: GPL2

    WP Security Audit Log
    Copyright(c) 2013  Robert Abela  (email : robert@wpwhitesecurity.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class WpSecurityAuditLog {
	
	// <editor-fold desc="Properties & Constants">
	
	const PLG_CLS_PRFX = 'WSAL_';
	
	/**
	 * Views supervisor.
	 * @var WSAL_ViewManager
	 */
	public $views;
	
	/**
	 * Logger supervisor.
	 * @var WSAL_AlertManager
	 */
	public $alerts;
	
	/**
	 * Sensors supervisor.
	 * @var WSAL_SensorManager
	 */
	public $sensors;
	
	/**
	 * Constants manager.
	 * @var WSAL_ConstantManager
	 */
	public $constants;
	
	// </editor-fold>
	
	// <editor-fold desc="Entry Points">
	
	/**
	 * Standard singleton pattern.
	 * @return \self Returns the current plugin instance.
	 */
	public static function GetInstance(){
		static $instance = null;
		if(!$instance)$instance = new self();
		return $instance;
	}
	
	/**
	 * Initialize plugin.
	 */
	public function __construct(){
		spl_autoload_register(array($this, 'LoadClass'));
		
		// load dependencies
		$this->views = new WSAL_ViewManager($this);
		$this->alerts = new WSAL_AlertManager($this);
		$this->sensors = new WSAL_SensorManager($this);
		$this->constants = new WSAL_ConstantManager();
		
		// listen to general events
		$this->sensors->HookEvents();
		
		// listen for installation event
		register_activation_hook(__FILE__, array($this, 'Install'));
	}
	
	public function Install(){
		WSAL_DB_ActiveRecord::InstallAll();
		if($this->CanUpgrade())$this->Upgrade();
	}
	
	public function Uninstall(){
		WSAL_DB_ActiveRecord::UninstallAll();
	}
	
	public function CanUpgrade(){
		global $wpdb;
		$table = $wpdb->base_prefix . 'wordpress_auditlog_events';
		return $wpdb->get_var('SHOW TABLES LIKE "'.$table.'"') == $table;
	}
	
	public function Upgrade(){
		global $wpdb;
		static $migTypes = array(
			3000 => 5006
		);
		// load data
		$sql = 'SELECT * FROM ' . $wpdb->base_prefix . 'wordpress_auditlog_events';
		$events = array();
		foreach($wpdb->get_results($sql, ARRAY_A) as $item)
			$events[$item['EventID']] = $item;
		$sql = 'SELECT * FROM ' . $wpdb->base_prefix . 'wordpress_auditlog';
		$auditlog = $wpdb->get_results($sql, ARRAY_A);
		// migrate using db logger
		$lgr = new WSAL_Loggers_Database($this);
		foreach($auditlog as $entry){
			$data = array(
				'ClientIP' => $entry['UserIP'],
				'UserAgent' => '',
				'CurrentUserID' => $entry['UserID'],
			);
			if($entry['UserName'])
				$data['Username'] = base64_decode($entry['UserName']);
			$mesg = $events[$entry['EventID']]['EventDescription'];
			$date = strtotime($entry['EventDate']);
			$type = $entry['EventID'];
			if(isset($migTypes[$type]))$type = $migTypes[$type];
			// convert message from '<strong>%s</strong>' to '%Arg1%' format
			$c = 0; $n = '<strong>%s</strong>'; $l = strlen($n);
			while(($pos = strpos($mesg, $n)) !== false){
				$mesg = substr_replace($mesg, '%MigratedArg' . ($c++) .'%', $pos, $l);
			}
			$data['MigratedMesg'] = $mesg;
			// generate new meta data args
			$temp = unserialize(base64_decode($entry['EventData']));
			foreach((array)$temp as $i => $item)
				$data['MigratedArg' . $i] = $item;
			// send event data to logger!
			$lgr->Log($type, $data, $date, $entry['BlogId'], true);
		}
	}
	
	public function GetBaseUrl(){
		return plugins_url('', __FILE__);
	}
	
	public function GetBaseDir(){
		return plugin_dir_path(__FILE__);
	}
	
	public function GetBaseName(){
		return plugin_basename(__FILE__);
	}
	
	// </editor-fold>
	
	/**
	 * This is the class autoloader. You should not call this directly.
	 * @param string $class Class name.
	 * @return boolean True if class is found and loaded, false otherwise.
	 */
	public function LoadClass($class){
		if(substr($class, 0, strlen(self::PLG_CLS_PRFX)) == self::PLG_CLS_PRFX){
			$file = str_replace('_', DIRECTORY_SEPARATOR, substr($class, strlen(self::PLG_CLS_PRFX)));
			$file = $this->GetBaseDir() . 'classes' . DIRECTORY_SEPARATOR . $file . '.php';
			if(file_exists($file)){
				require_once($file);
				return class_exists($class, false) || interface_exists($class, false);
			}
		}
		return false;
	}
	
	/**
	 * Returns the class name of a particular file that contains the class.
	 * @param string $file File name.
	 * @return string Class name.
	 */
	public function GetClassFileClassName($file){
		$base = str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $this->GetBaseDir() . 'classes' . DIRECTORY_SEPARATOR);
		$file = str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $file);
		return str_replace(
			array($base, '\\', '/'),
			array(self::PLG_CLS_PRFX, '_', '_'),
			substr($file, 0, -4)
		);
	}
}

// Load extra files
require_once('defaults.php');

// Create & Run the plugin
return WpSecurityAuditLog::GetInstance();
