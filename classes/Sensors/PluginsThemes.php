<?php

class WSAL_Sensors_PluginsThemes extends WSAL_AbstractSensor {

	public function HookEvents() {
		add_action('admin_init', array($this, 'EventAdminInit'));
		if(is_admin())add_action('shutdown', array($this, 'EventAdminShutdown'));
		add_action('switch_theme', array($this, 'EventThemeActivated'));
	}
	
	protected $old_themes = array();
	protected $old_plugins = array();
	
	public function EventAdminInit(){
		$this->old_themes = wp_get_themes();
		$this->old_plugins = get_plugins();
	}
	
	public function EventAdminShutdown(){
		$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
		$action = isset($_REQUEST['action2']) ? $_REQUEST['action2'] : $action;
		$actype = basename($_SERVER['SCRIPT_NAME'], '.php');
		$is_themes = $actype == 'themes';
		$is_plugins = $actype == 'plugins';
		
		// install plugin
        if(in_array($action, array('install-plugin', 'upload-plugin'))){
			$plugin = array_values(array_diff(array_keys(get_plugins()), array_keys($this->old_plugins)));
			if(count($plugin) != 1)
				return $this->LogError(
						'Expected exactly one new plugin but found ' . count($plugin),
						array('NewPlugin' => $plugin, 'OldPlugins' => $this->old_plugins, 'NewPlugins' => get_plugins())
					);
			$pluginPath = $plugin[0];
			$plugin = get_plugins();
			$plugin = $plugin[$pluginPath];
			$pluginPath = plugin_dir_path(WP_PLUGIN_DIR . '/' . $pluginPath[0]);
			$this->plugin->alerts->Trigger(5000, array(
				'Plugin' => (object)array(
					'Name' => $plugin['Name'],
					'PluginURI' => $plugin['PluginURI'],
					'Version' => $plugin['Version'],
					'Author' => $plugin['Author'],
					'Network' => $plugin['Network'] ? 'True' : 'False',
					'plugin_dir_path' => $pluginPath,
				),
			));
        }
		
		// activate plugin
        if($is_plugins && in_array($action, array('activate', 'activate-selected'))){
			if(isset($_REQUEST['plugin'])){
				if(!isset($_REQUEST['checked']))
					$_REQUEST['checked'] = array();
				$_REQUEST['checked'][] = $_REQUEST['plugin'];
			}
			foreach($_REQUEST['checked'] as $pluginFile){
				$pluginFile = WP_PLUGIN_DIR . '/' . $pluginFile;
				$pluginData = get_plugin_data($pluginFile, false, true);
				$this->plugin->alerts->Trigger(5001, array(
					'PluginFile' => $pluginFile,
					'PluginData' => (object)array(
						'Name' => $pluginData['Name'],
						'PluginURI' => $pluginData['PluginURI'],
						'Version' => $pluginData['Version'],
						'Author' => $pluginData['Author'],
						'Network' => $pluginData['Network'] ? 'True' : 'False',
					),
				));
			}
		}
		
		// deactivate plugin
        if($is_plugins && in_array($action, array('deactivate', 'deactivate-selected'))){
			if(isset($_REQUEST['plugin'])){
				if(!isset($_REQUEST['checked']))
					$_REQUEST['checked'] = array();
				$_REQUEST['checked'][] = $_REQUEST['plugin'];
			}
			foreach($_REQUEST['checked'] as $pluginFile){
				$pluginFile = WP_PLUGIN_DIR . '/' . $pluginFile;
				$pluginData = get_plugin_data($pluginFile, false, true);
				$this->plugin->alerts->Trigger(5002, array(
					'PluginFile' => $pluginFile,
					'PluginData' => (object)array(
						'Name' => $pluginData['Name'],
						'PluginURI' => $pluginData['PluginURI'],
						'Version' => $pluginData['Version'],
						'Author' => $pluginData['Author'],
						'Network' => $pluginData['Network'] ? 'True' : 'False',
					),
				));
			}
		}
		
		// uninstall plugin
        if($is_plugins && in_array($action, array('delete-selected'))){
			if(!isset($_REQUEST['verify-delete'])){
				// first step, before user approves deletion
				// TODO store plugin data in session here
			}else{
				// second step, after deletion approval
				// TODO use plugin data from session
				foreach($_REQUEST['checked'] as $pluginFile){
					$pluginName = basename($pluginFile, '.php');
					$pluginName = str_replace(array('_', '-', '  '), ' ', $pluginName);
					$pluginName = ucwords($pluginName);
					$pluginFile = WP_PLUGIN_DIR . '/' . $pluginFile;
					$this->plugin->alerts->Trigger(5003, array(
						'PluginFile' => $pluginFile,
						'PluginData' => (object)array(
							'Name' => $pluginName,
						),
					));
				}
			}
		}
		
		// upgrade plugin
        if(in_array($action, array('upgrade-plugin', 'update-selected'))){
			if(isset($_REQUEST['plugin'])){
				if(!isset($_REQUEST['checked']))
					$_REQUEST['checked'] = array();
				$_REQUEST['checked'][] = $_REQUEST['plugin'];
			}
			if(isset($_REQUEST['checked'])){
				if(!is_array($_REQUEST['checked'])){
					$_REQUEST['checked'] = array($_REQUEST['checked']);
				}
				foreach($_REQUEST['checked'] as $pluginFile){
					$pluginFile = WP_PLUGIN_DIR . '/' . $pluginFile;
					$pluginData = get_plugin_data($pluginFile, false, true);
					$this->plugin->alerts->Trigger(5004, array(
						'PluginFile' => $pluginFile,
						'PluginData' => (object)array(
							'Name' => $pluginData['Name'],
							'PluginURI' => $pluginData['PluginURI'],
							'Version' => $pluginData['Version'],
							'Author' => $pluginData['Author'],
							'Network' => $pluginData['Network'] ? 'True' : 'False',
						),
					));
				}
			}
		}
		
		// install theme
        if(in_array($action, array('install-theme', 'upload-theme'))){
			$themes = array_diff(wp_get_themes(), $this->old_themes);
			foreach($themes as $theme){
				$this->plugin->alerts->Trigger(5005, array(
					'Theme' => (object)array(
						'Name' => $theme->Name,
						'ThemeURI' => $theme->ThemeURI,
						'Description' => $theme->Description,
						'Author' => $theme->Author,
						'Version' => $theme->Version,
						'get_template_directory' => $theme->get_template_directory(),
					),
				));
			}
		}
		
		// uninstall theme
        if($is_themes && in_array($action, array('delete-selected', 'delete'))){
			foreach($this->GetRemovedThemes() as $theme){
				$this->plugin->alerts->Trigger(5007, array(
					'Theme' => (object)array(
						'Name' => $theme->Name,
						'ThemeURI' => $theme->ThemeURI,
						'Description' => $theme->Description,
						'Author' => $theme->Author,
						'Version' => $theme->Version,
						'get_template_directory' => $theme->get_template_directory(),
					),
				));
			}
		}
	}
	
	public function EventThemeActivated($themeName){
		$theme = null;
		foreach(wp_get_themes() as $item){
			if($item->Name == $themeName){
				$theme = $item;
				break;
			}
		}
		if($theme == null)
			return $this->LogError(
					'Could not locate theme named "' . $theme . '".',
					array('ThemeName' => $themeName, 'Themes' => wp_get_themes())
				);
		$this->plugin->alerts->Trigger(5006, array(
			'Theme' => (object)array(
				'Name' => $theme->Name,
				'ThemeURI' => $theme->ThemeURI,
				'Description' => $theme->Description,
				'Author' => $theme->Author,
				'Version' => $theme->Version,
				'get_template_directory' => $theme->get_template_directory(),
			),
		));
	}
	
	protected function GetRemovedThemes(){
		$result = $this->old_themes;
		foreach($result as $i => $theme)
			if(file_exists($theme->get_template_directory()))
				unset($result[$i]);
		return array_values($result);
	}
	
}
