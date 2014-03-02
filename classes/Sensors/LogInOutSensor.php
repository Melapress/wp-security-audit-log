<?php

class WSAL_Sensors_LogInOutSensor extends WSAL_Sensors_AbstractSensor {
/*
            array( 'id' => 1000, 'category' => WPPH_E_NOTICE_TEXT, 'text' => __('Successfully logged in.',WPPH_PLUGIN_TEXT_DOMAIN)),
            array( 'id' => 1001, 'category' => WPPH_E_NOTICE_TEXT, 'text' => __('Successfully logged out.',WPPH_PLUGIN_TEXT_DOMAIN)),
            array( 'id' => 1002, 'category' => WPPH_E_WARNING_TEXT, 'text' => __('Failed login detected using <strong>%s</strong> as username.',WPPH_PLUGIN_TEXT_DOMAIN)),
*/

	public function HookEvents() {
		add_action('wp_login', array($this, 'EventLogin'), 10, 2);
		add_action('wp_logout', array($this, 'EventLogout'));
		add_action('wp_login_failed', array($this, 'EventLoginFailure'));
	}
	
	public function EventLogin(){
		$this->plugin->logger->Log(1000, E_NOTICE, 'Successfully logged in.');
	}
	
	public function EventLogout(){
		$this->plugin->logger->Log(1001, E_NOTICE, 'Successfully logged out.');
	}
	
	public function EventLoginFailure($username){
		$msg = 'Failed login detected using <strong>%Username%</strong> as username.';
		$this->plugin->logger->Log(1002, E_WARNING, $msg, array('Username' => $username));
	}
	
}
