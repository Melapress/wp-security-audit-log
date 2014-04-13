<?php

class WSAL_Views_Settings extends WSAL_AbstractView {
	
	public function __construct(WpSecurityAuditLog $plugin) {
		parent::__construct($plugin);
	}
	
	public function HasPluginShortcutLink(){
		return true;
	}
	
	public function GetTitle() {
		return 'Settings';
	}
	
	public function GetIcon() {
		return 'dashicons-admin-generic';
	}
	
	public function GetName() {
		return 'Settings';
	}
	
	public function GetWeight() {
		return 2;
	}
	
	public function Render(){
		?>settings<?php
	}
	
}