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
	 * Logger supervisor.
	 * @var WSAL_Logging_Supervisor
	 */
	public $logger;
	
	/**
	 * Sensors supervisor.
	 * @var WSAL_Sensors_Supervisor
	 */
	public $sensors;
	
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
		$this->logger = new WSAL_Logging_Supervisor($this);
		$this->sensors = new WSAL_Sensors_Supervisor($this);
		// listen for installation event
		register_activation_hook(__FILE__, array($this, 'Install'));
	}
	
	public function Install(){
		WSAL_DB_ActiveRecord::InstallAll();
	}
	
	public function Uninstall(){
		WSAL_DB_ActiveRecord::UninstallAll();
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
			$file = plugin_dir_path(__FILE__) . 'classes' . DIRECTORY_SEPARATOR . $file . '.php';
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
		$base = str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, plugin_dir_path(__FILE__) . 'classes' . DIRECTORY_SEPARATOR);
		return str_replace(
			array($base, '\\', '/'),
			array(self::PLG_CLS_PRFX, '_', '_'),
			substr($file, 0, -4)
		);
	}
}

// Create & Run the plugin
return WpSecurityAuditLog::GetInstance();
