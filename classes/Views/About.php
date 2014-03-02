<?php

class WSAL_Views_About implements WSAL_ViewInterface {
	
	public function SetPlugin(WpSecurityAuditLog $plugin) {
	}
	
	public function GetTitle() {
		return 'About Us';
	}
	
	public function GetIcon() {
		return 'dashicons-editor-help';
	}
	
	public function GetName() {
		return 'About';
	}
	
	public function GetWeight(){
		return 4;
	}
	
	public function Render(){
		?><p>
			WP Security Audit Log is a WordPress security plugin developed by
			<a href="http://www.wpwhitesecurity.com" target="_blank">WP White Security</a>.
		</p><?php
	}
	
}