<?php

class WSAL_Views_Support extends WSAL_AbstractView {
	
	public function GetTitle() {
		return 'Support';
	}
	
	public function GetIcon() {
		return 'dashicons-sos';
	}
	
	public function GetName() {
		return 'Support';
	}
	
	public function GetWeight() {
		return 5;
	}
	
	public function Render(){
		?><p>
			Thank you for showing interest and using our plugin.
			If you encounter any issues running this plugin,
			or have suggestions or queries, please get in touch with us on
			<a href="mailto:plugins@wpwhitesecurity.com">plugins@wpwhitesecurity.com</a>.
		</p><?php
	}
	
}