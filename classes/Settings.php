<?php

class WSAL_Settings {
	/**
	 * @var WpSecurityAuditLog
	 */
	protected $_plugin;
	
	public function __construct(WpSecurityAuditLog $plugin){
		$this->_plugin = $plugin;
	}
	
	public function GetMaxAllowedAlerts(){
		return 5000;
	}
	
	public function GetPruningDate(){
		
	}
	
	public function GetPruningLimit(){
		
	}
}
