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
		if($occ->IsLoaded() && $occ->GetMetaValue('ClientIP') === $curip){
			// update existing record
			$meta = $occ->GetMetaArray();
			if(!isset($meta['Usernames']))
				$meta['Usernames'] = array();
			$meta['Usernames'][] = $username;
			if(!isset($meta['Attempts']))
				$meta['Attempts'] = 0;
			$meta['Attempts'] = $meta['Attempts'] + 1;
			$occ->SetMeta($meta);
			$occ->created_on = current_time('timestamp');
			$occ->Save();
		}else{
			// create a new record
			$this->plugin->alerts->Trigger(1002, array(
				'Usernames' => array($username),
				'Attempts' => 1
			));
		}
	}
	
}
