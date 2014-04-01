<?php

class WSAL_Sensors_LogInOut extends WSAL_AbstractSensor {

	public function HookEvents() {
		add_action('wp_login', array($this, 'EventLogin'), 10, 2);
		add_action('wp_logout', array($this, 'EventLogout'));
		add_action('wp_login_failed', array($this, 'EventLoginFailure'));
	}
	
	public function EventLogin($user_login, $user){
		$this->plugin->alerts->Trigger(1000, array('Username' => $user_login));
	}
	
	public function EventLogout(){
		$this->plugin->alerts->Trigger(1001);
	}
	
	public function EventLoginFailure($username){
		$this->plugin->alerts->Trigger(1002, array('Username' => $username));
	}
	
}
