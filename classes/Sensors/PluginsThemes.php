<?php

class WSAL_Sensors_PluginsThemes extends WSAL_AbstractSensor {

	public function HookEvents() {
		add_action('admin_init', array($this, 'EventAdminInit'));
		if(is_admin())add_action('shutdown', array($this, 'EventAdminShutdown'));
		add_action('switch_theme', array($this, 'EventThemeActivated'));
	}
	
	protected $old_themes;
	protected $old_plugins;
	
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
        if(($action=='install-plugin' || $action=='upload-plugin')){
			$newPlugin = array_values(array_diff(array_keys(get_plugins()), array_keys($this->old_plugins)));
			if(count($newPlugin) != 1)
				return $this->LogError(
						'Expected exactly one new plugin but found ' . count($newPlugin),
						array('NewPlugin' => $newPlugin, 'OldPlugins' => $this->old_plugins, 'NewPlugins' => get_plugins())
					);
			$newPluginPath = $newPlugin[0];
			$newPlugin = get_plugins();
			$newPlugin = $newPlugin[$newPluginPath];
			$newPluginPath = plugin_dir_path(WP_PLUGIN_DIR . '/' . $newPluginPath[0]);
			$this->plugin->alerts->Trigger(5000, array(
				'NewPlugin' => (object)array(
					'Name' => $newPlugin['Name'],
					'PluginURI' => $newPlugin['PluginURI'],
					'Version' => $newPlugin['Version'],
					'Author' => $newPlugin['Author'],
					'Network' => $newPlugin['Network'] ? 'True' : 'False',
					'plugin_dir_path' => $newPluginPath,
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
        if($action=='install-theme' || $action=='upload-theme'){
			$newTheme = array_diff(wp_get_themes(), $this->old_themes);
			if(count($newTheme) != 1)
				return $this->LogError(
						'Expected exactly one new theme but found ' . count($newTheme),
						array('OldThemes' => $this->old_themes, 'NewThemes' => wp_get_themes())
					);
			$newTheme = array_shift($newTheme);
			$this->plugin->alerts->Trigger(5005, array(
				'NewTheme' => (object)array(
					'Name' => $newTheme->Name,
					'ThemeURI' => $newTheme->ThemeURI,
					'Description' => $newTheme->Description,
					'Author' => $newTheme->Author,
					'Version' => $newTheme->Version,
					'get_template_directory' => $newTheme->get_template_directory(),
				),
			));
		}
		
		// uninstall theme
        if($is_themes && in_array($action, array('delete-selected'))){
			if(!isset($_REQUEST['verify-delete'])){
				
				// first step, before user approves deletion
				// TODO store plugin data in session here
			}else{
				// second step, after deletion approval
				// TODO use plugin data from session
				/*foreach($_REQUEST['checked'] as $themeFile){
					
				}*/

			}
		}
	}
	
	public function EventThemeActivated($themeName){
		$newTheme = null;
		foreach(wp_get_themes() as $theme){
			if($theme->Name == $themeName){
				$newTheme = $theme;
				break;
			}
		}
		if($newTheme == null)
			return $this->LogError(
					'Could not locate theme named "'.$newTheme.'".',
					array('ThemeName' => $themeName, 'Themes' => wp_get_themes())
				);
		$this->plugin->alerts->Trigger(5006, array(
			'NewTheme' => (object)array(
				'Name' => $newTheme->Name,
				'ThemeURI' => $newTheme->ThemeURI,
				'Description' => $newTheme->Description,
				'Author' => $newTheme->Author,
				'Version' => $newTheme->Version,
				'get_template_directory' => $newTheme->get_template_directory(),
			),
		));
	}
	
}
