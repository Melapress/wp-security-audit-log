<?php

class WSAL_Sensors_Multisite extends WSAL_AbstractSensor {

	public function HookEvents() {
		add_action('wpmu_new_blog', array($this, 'EventNewBlog'), 10, 1);
		add_action('archive_blog', array($this, 'EventArchiveBlog'));
		add_action('unarchive_blog', array($this, 'EventUnarchiveBlog'));
		add_action('activate_blog', array($this, 'EventActivateBlog'));
		add_action('deactivate_blog', array($this, 'EventDeactivateBlog'));
		add_action('delete_blog', array($this, 'EventDeleteBlog'));
		add_action('add_user_to_blog', array($this, 'EventUserAddedToBlog'), 10, 3);
		add_action('remove_user_from_blog', array($this, 'EventUserRemovedFromBlog'));
	}

	public function EventNewBlog($blog_id){
		$this->plugin->alerts->Trigger(7000, array(
			'BlogID' => $blog_id,
			'SiteName' => get_blog_option($blog_id, 'blogname'),
		));
	}
	
	public function EventArchiveBlog($blog_id){
		$this->plugin->alerts->Trigger(7001, array(
			'BlogID' => $blog_id,
			'SiteName' => get_blog_option($blog_id, 'blogname'),
		));
	}
	
	public function EventUnarchiveBlog($blog_id){
		$this->plugin->alerts->Trigger(7002, array(
			'BlogID' => $blog_id,
			'SiteName' => get_blog_option($blog_id, 'blogname'),
		));
	}
	
	public function EventActivateBlog($blog_id){
		$this->plugin->alerts->Trigger(7003, array(
			'BlogID' => $blog_id,
			'SiteName' => get_blog_option($blog_id, 'blogname'),
		));
	}
	
	public function EventDeactivateBlog($blog_id){
		$this->plugin->alerts->Trigger(7004, array(
			'BlogID' => $blog_id,
			'SiteName' => get_blog_option($blog_id, 'blogname'),
		));
	}
	
	public function EventDeleteBlog($blog_id){
		$this->plugin->alerts->Trigger(7005, array(
			'BlogID' => $blog_id,
			'SiteName' => get_blog_option($blog_id, 'blogname'),
		));
	}
	
	public function EventUserAddedToBlog($user_id, $role, $blog_id){
		$this->plugin->alerts->TriggerIf(4010, array(
			'TargetUserID' => $user_id,
			'TargetUsername' => get_userdata($user_id)->user_login,
			'TargetUserRole' => $role,
			'BlogID' => $blog_id,
			'SiteName' => get_blog_option($blog_id, 'blogname'),
		), array($this, 'MustNotContainCreateUser'));
	}
	
	public function EventUserRemovedFromBlog($user_id){
		$user = get_userdata($user_id);
        $blog_id = (isset($_REQUEST['id']) ? $_REQUEST['id'] : 0);
		$this->plugin->alerts->TriggerIf(4011, array(
			'TargetUserID' => $user_id,
			'TargetUsername' => $user->user_login,
			'TargetUserRole' => is_array($user->roles) ? implode(', ', $user->roles) : $user->roles,
			'BlogID' => $blog_id,
			'SiteName' => get_blog_option($blog_id, 'blogname'),
		), array($this, 'MustNotContainCreateUser'));
	}
	
	public function MustNotContainCreateUser(WSAL_AlertManager $mgr){
		return !$mgr->WillTrigger(4012);
	}
}