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
	
	protected $plugins = array();
	
	public function __construct(WpSecurityAuditLog $plugin){
		$this->plugin = $plugin;
	}
	
	protected function GetStoreUrl(){
		return 'http://wpwhitesecurity.com/';
	}
	
	public function CountPlugins(){
		return count($this->plugins);
	}
	
	public function Plugins(){
		foreach($this->plugins as $name => $pluginFile){
			if(is_string($pluginFile)){
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
		}
		return $this->plugins;
	}
	
	public function AddPremiumPlugin($pluginFile){
		$name = sanitize_key($pluginFile);
		$this->plugins[$name] = $pluginFile;
	}
	
	protected function GetBlogIds(){
		global $wpdb;
		$sql = 'SELECT blog_id FROM ' . $wpdb->blogs;
		return $wpdb->get_col($sql);
	}
	
	public function ActivateLicense($name, $license){
		$this->plugin->settings->SetLicenseKey($name, $license);

		$plugin = $this->Plugins();
		$api_params = array(
			'edd_action'=> 'activate_license',
			'license' 	=> urlencode($license),
			'item_name' => urlencode($plugin[$name]['PluginData']['Name']),
			'url'       => urlencode(home_url()),
		);
		
		$blog_ids = $this->plugin->IsMultisite() ? $this->GetBlogIds() : array(1);
		
		foreach($blog_ids as $blog_id){
			
			if($this->plugin->IsMultisite())
				$api_params['url'] = urlencode(get_home_url($blog_id));
			
			$response = wp_remote_get(
				add_query_arg($api_params, $this->GetStoreUrl()),
				array('timeout' => 15, 'sslverify' => false)
			);

			if (is_wp_error($response)) {
				$this->plugin->settings->SetLicenseErrors($name, 'Invalid Licensing Server Response: ' . $response->get_error_message());
				return false;
			}

			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			if(is_object($license_data)){
				$this->plugin->settings->SetLicenseStatus($name, $license_data->license);
				if($license_data->license !== 'valid'){
					$error = 'License Not Valid';
					if (isset($license_data->error)) $error .= ': ' . ucfirst(str_replace('_', ' ', $license_data->error));
					$this->plugin->settings->SetLicenseErrors($name, $error);
					return false;
				}
			}else{
				$this->plugin->settings->SetLicenseErrors($name, 'Unexpected Licensing Server Response');
				return false;
			}
		}
		
		return true;
	}
	
	public function DeactivateLicense($name){
		$this->plugin->settings->SetLicenseStatus($name, '');
	}
}
