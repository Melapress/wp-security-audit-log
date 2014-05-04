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
		$occ = new WSAL_DB_Occurrence();
		
		list($y, $m, $d) = explode('-', date('Y-m-d'));
		$occ->Load('alert_id = %d AND site_id = %d'
				. ' AND (created_on BETWEEN %d AND %d)',
				array(
					1002,
					(function_exists('get_current_blog_id') ? get_current_blog_id() : 0),
					mktime(0, 0, 0, $m, $d, $y),
					mktime(0, 0, 0, $m, $d + 1, $y) - 1,
				));
		
		$curip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
		if($occ->IsLoaded()
		&& $occ->GetMetaValue('ClientIP') === $curip
		&& $occ->GetMetaValue('Username') === $username){
			// update existing record
			$occ->SetMetaValue('Attempts',
				$occ->GetMetaValue('Attempts', 0) + 1
			);
			$occ->created_on = current_time('timestamp');
			$occ->Save();
		}else{
			// create a new record
			$this->plugin->alerts->Trigger(1002, array(
				'Username' => $username,
				'Attempts' => 1
			));
		}
	}
	
}
