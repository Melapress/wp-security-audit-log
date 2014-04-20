<?php

class WSAL_Sensors_PluginsThemes extends WSAL_AbstractSensor {

	public function HookEvents() {
		if(is_admin())add_action('shutdown', array($this, 'EventAdminShutdown'));
		add_action('switch_theme', array($this, 'EventThemeActivated'));
	}
	
	public function EventAdminShutdown(){
		$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
		$action = isset($_REQUEST['action2']) ? $_REQUEST['action2'] : $action;
		
		// install plugin
        if(($action=='install-plugin' || $action=='upload-plugin') && !empty($_GET['plugin'])){
			$newPlugin = null;
			$pluginName = $_GET['plugin'];
			foreach(get_plugins() as $pluginFile => $plugin){
				if(strtolower(str_replace(' ', '-', $plugin['Name'])) == $pluginName){
					$newPlugin = $plugin;
					break;
				}
			}
			$pluginPath = $newPlugin ? plugin_dir_path(WP_PLUGIN_DIR . '/' . $pluginFile) : '';
			$this->plugin->alerts->Trigger(5000, array(
				'PluginName' => $pluginName,
				'PluginPath' => $pluginPath,
				'PluginData' => (object)array(
					'Name' => $newPlugin['Name'],
					'PluginURI' => $newPlugin['PluginURI'],
					'Version' => $newPlugin['Version'],
					'Author' => $newPlugin['Author'],
					'Network' => $newPlugin['Network'] ? 'True' : 'False',
				),
			));
        }
		
		// activate plugin
        if(in_array($action, array('activate', 'activate-selected'))){
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
        if(in_array($action, array('deactivate', 'deactivate-selected'))){
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
        if(in_array($action, array('delete-selected'))){
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
		
		// install theme
        if($action=='install-theme' && !empty($_GET['theme'])){
			$themeName = $_GET['theme'];
			$newTheme = null;
			foreach(wp_get_themes() as $theme){
				if(strtolower(str_replace(' ', '-', $theme->Name)) == $themeName){
					$newTheme = $theme;
					break;
				}
			}
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
	}
	
	public function EventThemeActivated($themeName){
		$newTheme = null;
		foreach(wp_get_themes() as $theme){
			if($theme->Name == $themeName){
				$newTheme = $theme;
				break;
			}
		}
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
