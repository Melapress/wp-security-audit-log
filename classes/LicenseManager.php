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
		return 'http://wpwhitesecurity.com/';
	}
	
	public function CountPlugins(){
		return count($this->plugins);
	}
	
	public function AddPremiumPlugin($pluginFile){
		$name = sanitize_key($pluginFile);
		$pluginData = get_plugin_data($pluginFile);
		
		$this->plugins[$name] = array(
			'PluginData' => $pluginData,
			'EddUpdater' => new EDD_SL_Plugin_Updater(
				$this->GetStoreUrl(),
				$pluginFile,
				array( 
					'license' 	=> $this->plugin->settings->GetLicenseKey($name),
					'item_name' => $pluginData['Name'],
					'author' 	=> $pluginData['Author'],
					'version' 	=> $pluginData['Version'],
				)
			),
		);
	}
	
	public function ActivateLicense($name, $license){
		$this->plugin->settings->SetLicenseKey($name, $license);

		$api_params = array(
			'edd_action'=> 'activate_license',
			'license' 	=> $license,
			'item_name' => urlencode($name),
			'url'       => home_url()
		);
		
		$response = wp_remote_get(
			add_query_arg($api_params, $this->GetStoreUrl()),
			array('timeout' => 15, 'sslverify' => false)
		);

		if (is_wp_error($response)) {
			$this->plugin->settings->SetLicenseErrors($name, 'Invalid Licensing Server Response');
			return false;
		}

		$license_data = json_decode( wp_remote_retrieve_body( $response ) );
		
		if(is_object($license_data)){
			$this->plugin->settings->SetLicenseStatus($name, $license_data->license);
			if($license_data->license !== 'valid')
				$this->plugin->settings->SetLicenseErrors($name, 'License Not Valid');
		}else{
			$this->plugin->settings->SetLicenseErrors($name, 'Unexpected Licensing Server Response');
		}
		
		return true;
	}
	
	public function DeactivateLicense($name){
		$this->plugin->settings->SetLicenseStatus($name, '');
	}
}
