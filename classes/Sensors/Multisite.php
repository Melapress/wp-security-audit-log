<?php

class WSAL_Sensors_Multisite extends WSAL_AbstractSensor {

	public function HookEvents() {
		add_action('wpmu_new_blog', array($this, 'EventNewBlog'), 10, 1);
		add_action('archive_blog', array($this, 'EventArchiveBlog'));
		add_action('unarchive_blog', array($this, 'EventUnarchiveBlog'));
		add_action('activate_blog', array($this, 'EventActivateBlog'));
		add_action('deactivate_blog', array($this, 'EventDeactivateBlog'));
		add_action('delete_blog', array($this, 'EventDeleteBlog'));
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
	
}