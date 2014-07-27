<?php

// since other plugins might use this class
if(!class_exists('EDD_SL_Plugin_Updater')){
	require_once('EDD_SL_Plugin_Updater.php');
}

class WSAL_LicenseManager {
	/**
	 * @var WpSecurityAuditLog
	 */
	protected $plugin;
	
	public $plugins = array();
	
	public function __construct(WpSecurityAuditLog $plugin){
		$this->plugin = $plugin;
		
		
	}
	
	protected function GetStoreUrl(){
		
	}
	
	public function CountPlugins(){
		return count($this->plugins);
	}
	
	public function AddPremiumPlugin($pluginFile){
		$name = sanitize_key($pluginFile);
		$licenseKey = trim($this->plugin->GetGlobalOption("license_$name"));
		$licenseStatus = trim($this->plugin->GetGlobalOption("status_$name"));
		$pluginData = get_plugin_data($pluginFile);
		
		$this->plugins[$name] = array(
			'LicenseKey' => $licenseKey,
			'LicenseStatus' => $licenseStatus,
			'PluginData' => $pluginData,
			'EddUpdater' => new EDD_SL_Plugin_Updater(
				$this->GetStoreUrl(),
				$pluginFile,
				array( 
					'license' 	=> $licenseKey,
					'item_name' => $pluginData['Name'],
					'author' 	=> $pluginData['Author'],
					'version' 	=> $pluginData['Version'],
				)
			),
		);
	}
}
