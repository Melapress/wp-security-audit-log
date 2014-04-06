<?php

class WSAL_Sensors_System extends WSAL_AbstractSensor {

	public function HookEvents() {
		add_action('admin_init', array($this, 'EventAdminInit'));
	}
	
	public function EventAdminInit(){
		
		// make sure user can actually modify target options
		if(!current_user_can('manage_options'))return;
		
		$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
		$is_option_page = isset($_POST) && !empty($_POST['option_page']);
		$is_permalink_page = isset($_POST) && basename($_SERVER['SCRIPT_NAME']) == 'options-permalink.php';
			
		if($is_option_page && (get_option('users_can_register') xor isset($_POST['users_can_register']))){
			$old = get_option('users_can_register') ? 'Enabled' : 'Disabled';
			$new = isset($_POST['users_can_register']) ? 'Enabled' : 'Disabled';
			if($old !== $new){
				$this->plugin->alerts->Trigger(6001, array(
					'OldValue' => $old,
					'NewValue' => $new,
					'CurrentUserID' => wp_get_current_user()->ID,
				));
			}
		}

		if($is_option_page && !empty($_POST['default_role'])){
			$old = get_option('default_role');
			$new = trim($_POST['default_role']);
			if($old !== $new){
				$this->plugin->alerts->Trigger(6002, array(
					'OldRole' => $old,
					'NewRole' => $new,
					'CurrentUserID' => wp_get_current_user()->ID,
				));
			}
		}

		if($is_option_page && !empty($_POST['admin_email'])){
			$old = get_option('admin_email');
			$new = trim($_POST['admin_email']);
			if($old !== $new){
				$this->plugin->alerts->Trigger(6003, array(
					'OldEmail' => $old,
					'NewEmail' => $new,
					'CurrentUserID' => wp_get_current_user()->ID,
				));
			}
		}
		
		if($is_permalink_page && !empty($_POST['permalink_structure'])){
			$old = get_option('permalink_structure');
			$new = trim($_POST['permalink_structure']);
			if($old !== $new){
				$this->plugin->alerts->Trigger(6005, array(
					'OldPattern' => $old,
					'NewPattern' => $new,
					'CurrentUserID' => wp_get_current_user()->ID,
				));
			}
		}
		
		if($action == 'do-core-upgrade' && isset($_REQUEST['version'])){
			$oldVersion = get_bloginfo('version');
			$newVersion = $_REQUEST['version'];
			if($oldVersion !== $newVersion){
				$this->plugin->alerts->Trigger(6004, array(
					'OldVersion' => $oldVersion,
					'NewVersion' => $newVersion,
				));
			}
		}
	}
	
}
