<?php

class WSAL_Sensors_System extends WSAL_AbstractSensor {

	public function HookEvents() {
		add_action('automatic_updates_complete', array($this, 'EventWordpressUpgrade'));
		add_action('admin_init', array($this, 'EventCheckForUpdatedSettings'));
	}
	
	public function EventWordpressUpgrade(){
		// TODO Finish this and make sure WP action is correct
	}
	
	public function EventCheckForUpdatedSettings(){
		if(isset($_POST) && !empty($_POST['option_page'])){
			
			// make sure user can actually modify target options
			$option_page = $_POST['option_page'];
			$capability = apply_filters("option_page_capability_{$option_page}", 'manage_options');
			if(!current_user_can($capability))return;
			
			if(get_option('users_can_register') xor isset($_POST['users_can_register'])){
				$from = get_option('users_can_register') ? 'Enabled' : 'Disabled';
				$to = isset($_POST['users_can_register']) ? 'Enabled' : 'Disabled';
				if($from !== $to){
					$this->plugin->alerts->Trigger(6001, array(
						'OldValue' => $from,
						'NewValue' => $to,
						'CurrentUserID' => wp_get_current_user()->ID,
					));
				}
			}
			
			if(!empty($_POST['default_role'])){
				$from = get_option('default_role');
				$to = trim($_POST['default_role']);
				if($from !== $to){
					$this->plugin->alerts->Trigger(6002, array(
						'OldRole' => $from,
						'NewRole' => $to,
						'CurrentUserID' => wp_get_current_user()->ID,
					));
				}
			}
			
			if(!empty($_POST['admin_email'])){
				$from = get_option('admin_email');
				$to = trim($_POST['admin_email']);
				if($from !== $to){
					$this->plugin->alerts->Trigger(6003, array(
						'OldEmail' => $from,
						'NewEmail' => $to,
						'CurrentUserID' => wp_get_current_user()->ID,
					));
				}
			}
			
		}
	}
	
}
