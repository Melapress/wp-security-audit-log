<?php

class WSAL_Sensors_LogInOut extends WSAL_AbstractSensor {

	public function HookEvents() {
		add_action('wp_login', array($this, 'EventLogin'), 10, 2);
		add_action('wp_logout', array($this, 'EventLogout'));
		add_action('wp_login_failed', array($this, 'EventLoginFailure'));
	}
	
	public function EventLogin($user_login, $user){
		$this->plugin->alerts->Trigger(1000, array(
			'Username' => $user_login,
			'CurrentUserRoles' => $user->roles,
		));
	}
	
	public function EventLogout(){
		$this->plugin->alerts->Trigger(1001);
	}
	
	public function EventLoginFailure($username){
		list($y, $m, $d) = explode('-', date('Y-m-d'));
		
		$tt1 = new WSAL_DB_Occurrence();
		$tt2 = new WSAL_DB_Meta();
		
		$occ = WSAL_DB_Occurrence::LoadMultiQuery('
			SELECT * FROM `' . $tt1->GetTable() . '`
			WHERE alert_id = %d AND site_id = %d
				AND (created_on BETWEEN %d AND %d)
				AND id IN (
					SELECT occurrence_id as id
					FROM `' . $tt2->GetTable() . '`
					WHERE (name = "ClientIP" AND value = %s)
					   OR (name = "Username" AND value = %s)
					GROUP BY occurrence_id
					HAVING COUNT(*) = 2
				)
		', array(
			1002,
			(function_exists('get_current_blog_id') ? get_current_blog_id() : 0),
			mktime(0, 0, 0, $m, $d, $y),
			mktime(0, 0, 0, $m, $d + 1, $y) - 1,
			json_encode(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''),
			json_encode($username),
		));
		
		$occ = count($occ) ? $occ[0] : null;
		
		if($occ && $occ->IsLoaded()){
			// update existing record
			$occ->SetMetaValue('Attempts',
				$occ->GetMetaValue('Attempts', 0) + 1
			);
			$occ->created_on = null;
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
