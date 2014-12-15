<?php
/*
Plugin Name: WP Security Audit Log
Plugin URI: http://www.wpwhitesecurity.com/wordpress-security-plugins/wp-security-audit-log/
Description: Identify WordPress security issues before they become a problem and keep track of everything happening on your WordPress, including WordPress users activity. Similar to Windows Event Log and Linux Syslog, WP Security Audit Log will generate a security alert for everything that happens on your WordPress blog or website. Use the Audit Log Viewer included in the plugin to see all the security alerts.
Author: WP White Security
Version: 1.3.2
Text Domain: wp-security-audit-log
Author URI: http://www.wpwhitesecurity.com/
License: GPL2

    WP Security Audit Log
    Copyright(c) 2014  Robert Abela  (email : robert@wpwhitesecurity.com)

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
	
	const MIN_PHP_VERSION = '5.3.0';
	
	const OPT_PRFX = 'wsal-';
	
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
	 * Settings manager.
	 * @var WSAL_Settings
	 */
	public $settings;
	
	/**
	 * Class loading manager.
	 * @var WSAL_Autoloader
	 */
	public $autoloader;
	
	/**
	 * Constants manager.
	 * @var WSAL_ConstantManager
	 */
	public $constants;
	
	/**
	 * Licenses manager.
	 * @var WSAL_LicenseManager
	 */
	public $licensing;
	
	/**
	 * Simple profiler.
	 * @var WSAL_SimpleProfiler
	 */
	public $profiler;
	
	/**
	 * Contains a list of cleanup callbacks.
	 * @var callable[]
	 */
	protected $_cleanup_hooks = array();
	
	// </editor-fold>
	
	// <editor-fold desc="Entry Points">
	
	/**
	 * Standard singleton pattern.
	 * WARNING! To ensure the system always works as expected, AVOID using this method.
	 * Instead, make use of the plugin instance provided by 'wsal_init' action.
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
		// profiler has to be loaded manually
		require_once('classes/SimpleProfiler.php');
		$this->profiler = new WSAL_SimpleProfiler();
		
		// load autoloader and register base paths
		require_once('classes/Autoloader.php');
		$this->autoloader = new WSAL_Autoloader($this);
		$this->autoloader->Register(self::PLG_CLS_PRFX, $this->GetBaseDir() . 'classes' . DIRECTORY_SEPARATOR);
		
		// load dependencies
		$this->views = new WSAL_ViewManager($this);
		$this->alerts = new WSAL_AlertManager($this);
		$this->sensors = new WSAL_SensorManager($this);
		$this->settings = new WSAL_Settings($this);
		$this->constants = new WSAL_ConstantManager($this);
		$this->licensing = new WSAL_LicenseManager($this);
		$this->widgets = new WSAL_WidgetManager($this);
		
		// listen for installation event
		register_activation_hook(__FILE__, array($this, 'Install'));
		
		// listen for cleanup event
		add_action('wsal_cleanup', array($this, 'CleanUp'));
		
		// render wsal header
		add_action('admin_enqueue_scripts', array($this, 'RenderHeader'));
		
		// render wsal footer
		add_action('admin_footer', array($this, 'RenderFooter'));
		
		// hide plugin
		if($this->settings->IsIncognito())
			add_action('admin_head', array($this, 'HidePlugin'));
	}
	
	/**
	 * @internal Render plugin stuff in page header.
	 */
	public function RenderHeader(){
		// common.css?
	}
	
	/**
	 * @internal Render plugin stuff in page footer.
	 */
	public function RenderFooter(){
		wp_enqueue_script(
			'wsal-common',
			$this->GetBaseUrl() . '/js/common.js',
			array('jquery'),
			filemtime($this->GetBaseDir() . '/js/common.js')
		);
	}
	
	/**
	 * @internal Load the rest of the system.
	 */
	public function Load(){
		// load translations
		load_plugin_textdomain('wp-security-audit-log', false, basename( dirname( __FILE__ ) ) . '/languages/');

		// tell the world we've just finished loading
		$s = $this->profiler->Start('WSAL Init Hook');
		do_action('wsal_init', $this);
		$s->Stop();
	}
	
	/**
	 * Install all assets required for a useable system.
	 */
	public function Install(){
		if (version_compare(PHP_VERSION, self::MIN_PHP_VERSION) < 0) {
			?><html>
				<head>
					<link rel="stylesheet" href="<?php
						echo esc_attr($this->GetBaseUrl() . '/css/install-error.css?v=' . filemtime($this->GetBaseDir() . '/css/install-error.css'));
					?>" type="text/css" media="all"/>
				</head><body>
					<div class="warn-wrap">
						<div class="warn-icon-tri"></div><div class="warn-icon-chr">!</div><div class="warn-icon-cir"></div>
						<?php echo sprintf(__('You are using a version of PHP that is older than %s, which is no longer supported.<br/>Contact us on <a href="mailto:plugins@wpwhitesecurity.com">plugins@wpwhitesecurity.com</a> to help you switch the version of PHP you are using.'), self::MIN_PHP_VERSION); ?>
					</div>
				</body>
			</html><?php
			die(1);
		}
		
		$PreInstalled = $this->IsInstalled();
		
		// if system already installed, do updates now (if any)
		$OldVersion = $this->GetOldVersion();
		$NewVersion = $this->GetNewVersion();
		if ($PreInstalled && $OldVersion != $NewVersion) $this->Update($OldVersion, $NewVersion);
		
		// ensure that the system is installed and schema is correct
		WSAL_DB_ActiveRecord::InstallAll();
		
		// if system wasn't installed, try migration now
		if (!$PreInstalled && $this->CanMigrate()) $this->Migrate();
		
		// install cleanup hook (remove older one if it exists)
		wp_clear_scheduled_hook('wsal_cleanup');
		wp_schedule_event(current_time('timestamp') + 600, 'hourly', 'wsal_cleanup');
	}
	
	/**
	 * Run some code that updates critical components required for a newwer version.
	 * @param string $old_version The old version.
	 * @param string $new_version The new version.
	 */
	public function Update($old_version, $new_version){
		// update version in db
		$this->GetGlobalOption('version', $new_version);
		
		// disable all developer options
		$this->settings->ClearDevOptions();
		
		// do version-to-version specific changes
		if(version_compare($old_version, '1.2.3') == -1){
			// ... an example
		}
	}
	
	/**
	 * Uninstall plugin.
	 */
	public function Uninstall(){
		WSAL_DB_ActiveRecord::UninstallAll();
		wp_clear_scheduled_hook('wsal_cleanup');
	}
	
	/**
	 * Migrate data from old plugin.
	 */
	public function Migrate(){
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
			foreach($this->alerts->GetLoggers() as $logger){
				$logger->Log($type, $data, $date, $entry['BlogId'], true);
			}
		}
		
		// migrate settings
		$this->settings->SetAllowedPluginEditors(
			get_option('WPPH_PLUGIN_ALLOW_CHANGE')
		);
		$this->settings->SetAllowedPluginViewers(
			get_option('WPPH_PLUGIN_ALLOW_ACCESS')
		);
		$s = get_option('wpph_plugin_settings');
		//$this->settings->SetPruningDate(($s->daysToKeep ? $s->daysToKeep : 30) . ' days');
		//$this->settings->SetPruningLimit(min($s->eventsToKeep, 1));
		$this->settings->SetViewPerPage(max($s->showEventsViewList, 5));
		$this->settings->SetWidgetsEnabled(!!$s->showDW);
	}
	
	// </editor-fold>
	
	// <editor-fold desc="Utility Methods">
	
	/**
	 * @return string The current plugin version (according to plugin file metadata).
	 */
	public function GetNewVersion(){
		$version = get_plugin_data(__FILE__, false, false);
		return isset($version['Version']) ? $version['Version'] : '0.0.0';
	}
	
	/**
	 * @return string The plugin version as stored in DB (will be the old version during an update/install).
	 */
	public function GetOldVersion(){
		return $this->GetGlobalOption('version', '0.0.0');
	}
	
	/**
	 * @internal To be called in admin header for hiding plugin form Plugins list.
	 */
	public function HidePlugin(){
		?><style type="text/css">.wp-list-table.plugins #wp-security-audit-log { display: none; }</style><?php
	}
	
	/**
	 * Returns the class name of a particular file that contains the class.
	 * @param string $file File name.
	 * @return string Class name.
	 * @deprecated since 1.2.5 Use autoloader->GetClassFileClassName() instead.
	 */
	public function GetClassFileClassName($file){
		return $this->autoloader->GetClassFileClassName($file);
	}
	
	/**
	 * @return boolean Whether we are running on multisite or not.
	 */
	public function IsMultisite(){
		return function_exists('is_multisite') && is_multisite();
	}
	
	/**
	 * Get a global option.
	 * @param string $option Option name.
	 * @param mixed $default (Optional) Value returned when option is not set (defaults to false).
	 * @param string $prefix (Optional) A prefix used before option name.
	 * @return mixed Option's value or $default if option not set.
	 */
	public function GetGlobalOption($option, $default = false, $prefix = self::OPT_PRFX){
		$fn = $this->IsMultisite() ? 'get_site_option' : 'get_option';
		return $fn($prefix . $option, $default);
	}
	
	/**
	 * Set a global option.
	 * @param string $option Option name.
	 * @param mixed $value New value for option.
	 * @param string $prefix (Optional) A prefix used before option name.
	 */
	public function SetGlobalOption($option, $value, $prefix = self::OPT_PRFX){
		$fn = $this->IsMultisite() ? 'update_site_option' : 'update_option';
		$fn($prefix . $option, $value);
	}
	
	/**
	 * Get a user-specific option.
	 * @param string $option Option name.
	 * @param mixed $default (Optional) Value returned when option is not set (defaults to false).
	 * @param string $prefix (Optional) A prefix used before option name.
	 * @return mixed Option's value or $default if option not set.
	 */
	public function GetUserOption($option, $default = false, $prefix = self::OPT_PRFX){
		$result = get_user_option($prefix . $option, get_current_user_id());
		return $result === false ? $default : $result;
	}
	
	/**
	 * Set a user-specific option.
	 * @param string $option Option name.
	 * @param mixed $value New value for option.
	 * @param string $prefix (Optional) A prefix used before option name.
	 */
	public function SetUserOption($option, $value, $prefix = self::OPT_PRFX){
		update_user_option(get_current_user_id(), $prefix . $option, $value, false);
	}
	
	/**
	 * Run cleanup routines.
	 */
	public function CleanUp(){
		$s = $this->profiler->Start('Clean Up');
		foreach($this->_cleanup_hooks as $hook)
			call_user_func($hook);
		$s->Stop();
	}
	
	/**
	 * Add callback to be called when a cleanup operation is required.
	 * @param callable $hook
	 */
	public function AddCleanupHook($hook){
		$this->_cleanup_hooks[] = $hook;
	}
	
	/**
	 * Remove a callback from the cleanup callbacks list.
	 * @param callable $hook
	 */
	public function RemoveCleanupHook($hook){
		while(($pos = array_search($hook, $this->_cleanup_hooks)) !== false)
			unset($this->_cleanup_hooks[$pos]);
	}
	
	/**
	 * Do we have an existing installation? This only applies for version 1.0 onwards.
	 * @return boolean
	 */
	public function IsInstalled(){
		global $wpdb;
		$table = $wpdb->base_prefix . 'wsal_occurrences';
		return ($wpdb->get_var('SHOW TABLES LIKE "'.$table.'"') == $table);
	}
	
	/**
	 * @return boolean Whether the old plugin was present or not.
	 */
	public function CanMigrate(){
		global $wpdb;
		$table = $wpdb->base_prefix . 'wordpress_auditlog_events';
		return ($wpdb->get_var('SHOW TABLES LIKE "'.$table.'"') == $table);
	}
	
	/**
	 * @return string Absolute URL to plugin directory WITHOUT final slash.
	 */
	public function GetBaseUrl(){
		return plugins_url('', __FILE__);
	}
	
	/**
	 * @return string Full path to plugin directory WITH final slash.
	 */
	public function GetBaseDir(){
		return plugin_dir_path(__FILE__);
	}
	
	/**
	 * @return string Plugin directory name.
	 */
	public function GetBaseName(){
		return plugin_basename(__FILE__);
	}
	
	/**
	 * Load default configuration / data.
	 */
	public function LoadDefaults(){
		$s = $this->profiler->Start('Load Defaults');
		require_once('defaults.php');
		$s->Stop();
	}
	
	// </editor-fold>
}

// Profile WSAL load time
$s = WpSecurityAuditLog::GetInstance()->profiler->Start('WSAL Init');

// Begin load sequence
add_action('plugins_loaded', array(WpSecurityAuditLog::GetInstance(), 'Load'));

// Load extra files
WpSecurityAuditLog::GetInstance()->LoadDefaults();

// Start listening to events
WpSecurityAuditLog::GetInstance()->sensors->HookEvents();

// End profile snapshot
$s->Stop();

// Create & Run the plugin
return WpSecurityAuditLog::GetInstance();
