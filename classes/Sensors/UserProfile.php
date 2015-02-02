<?php

class WSAL_Sensors_UserProfile extends WSAL_AbstractSensor {

	public function HookEvents() {
		add_action('admin_init', array($this, 'EventAdminInit'));
		add_action('user_register', array($this, 'EventUserRegister'));
        add_action('edit_user_profile_update', array($this, 'EventUserChanged'));
        add_action('personal_options_update', array($this, 'EventUserChanged'));
        add_action('delete_user', array($this, 'EventUserDeleted'));
        add_action('wpmu_delete_user', array($this, 'EventUserDeleted'));
        add_action('set_user_role', array($this, 'EventUserRoleChanged'), 10, 3);
	}
	
	protected $old_superadmins;
	
	protected function IsMultisite(){
		return function_exists('is_multisite') && is_multisite();
	}
	
	public function EventAdminInit(){
		if($this->IsMultisite()){
			$this->old_superadmins = get_super_admins();
		}
	}
	
	public function EventUserRegister($user_id){
		$user = get_userdata($user_id);
		$ismu = function_exists('is_multisite') && is_multisite();
		$event = $ismu ? 4012 : (is_user_logged_in() ? 4001 : 4000);
		$this->plugin->alerts->Trigger($event, array(
			'NewUserID' => $user_id,
			'NewUserData' => (object)array(
				'Username' => $user->user_login,
				'FirstName' => $user->user_firstname,
				'LastName' => $user->user_lastname,
				'Email' => $user->user_email,
				'Roles' => is_array($user->roles) ? implode(', ', $user->roles) : $user->roles,
			),
		), true);
	}
	
	public function EventUserRoleChanged($user_id, $role, $oldRoles){
		$user = get_userdata($user_id);
		
		$oldRole = count($oldRoles) ? implode(', ', $oldRoles) : '';
		$newRole = $role;
		if($oldRole != $newRole){
			$this->plugin->alerts->TriggerIf(4002, array(
				'TargetUserID' => $user_id,
				'TargetUsername' => $user->user_login,
				'OldRole' => $oldRole,
				'NewRole' => $newRole,
			), array($this, 'MustNotContainUserChanges'));
		}
	}

	public function EventUserChanged($user_id){
		$user = get_userdata($user_id);
		
		// roles changed
        /*if(!empty($_REQUEST['role'])){
			$oldRole = count($user->roles) ? $user->roles[0] : '';
            $newRole = trim($_REQUEST['role']);
            if($oldRole != $newRole){
				$this->plugin->alerts->Trigger(4002, array(
					'TargetUserID' => $user_id,
					'TargetUsername' => $user->user_login,
					'OldRole' => $oldRole,
					'NewRole' => $newRole,
				), true);
            }
        }*/

        // password changed
        if(!empty($_REQUEST['pass1'])){
			$event = $user_id == get_current_user_id() ? 4003 : 4004;
			$this->plugin->alerts->Trigger($event, array(
				'TargetUserID' => $user_id,
				'TargetUserData' => (object)array(
					'Username' => $user->user_login,
					'Roles' => is_array($user->roles) ? implode(', ', $user->roles) : $user->roles,
				),
			));
        }

        // email changed
        if(!empty($_REQUEST['email'])){
			$oldEmail = $user->user_email;
            $newEmail = trim($_REQUEST['email']);
            if($oldEmail != $newEmail){
				$event = $user_id == get_current_user_id() ? 4005 : 4006;
				$this->plugin->alerts->Trigger($event, array(
					'TargetUserID' => $user_id,
					'TargetUsername' => $user->user_login,
					'OldEmail' => $oldEmail,
					'NewEmail' => $newEmail,
				));
            }
        }
		
		if($this->IsMultisite()){
			$username = $user->user_login;
			$enabled = isset($_REQUEST['super_admin']);
			
			if($user_id != get_current_user_id()){
				
				// super admin enabled
				if($enabled && !in_array($username, $this->old_superadmins)){
					$this->plugin->alerts->Trigger(4008, array(
						'TargetUserID' => $user_id,
						'TargetUsername' => $user->user_login,
					));
				}

				// super admin disabled
				if(!$enabled && in_array($username, $this->old_superadmins)){
					$this->plugin->alerts->Trigger(4009, array(
						'TargetUserID' => $user_id,
						'TargetUsername' => $user->user_login,
					));
				}
				
			}
		}
	}
	
	public function EventUserDeleted($user_id){
		$user = get_userdata($user_id);
		$role = is_array($user->roles) ? implode(', ', $user->roles) : $user->roles;
		$this->plugin->alerts->TriggerIf(4007, array(
			'TargetUserID' => $user_id,
			'TargetUserData' => (object)array(
				'Username' => $user->user_login,
				'FirstName' => $user->user_firstname,
				'LastName' => $user->user_lastname,
				'Email' => $user->user_email,
				'Roles' => $role ? $role : 'none',
			),
		), array($this, 'MustNotContainCreateUser'));
	}
	
	public function MustNotContainCreateUser(WSAL_AlertManager $mgr){
		return !$mgr->WillTrigger(4012);
	}
	
	public function MustNotContainUserChanges(WSAL_AlertManager $mgr){
		return !(  $mgr->WillOrHasTriggered(4010)
				|| $mgr->WillOrHasTriggered(4011)
				|| $mgr->WillOrHasTriggered(4012)
				|| $mgr->WillOrHasTriggered(4000)
				|| $mgr->WillOrHasTriggered(4001)
			);
	}
}
