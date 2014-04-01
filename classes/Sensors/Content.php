<?php

class WSAL_Sensors_Content extends WSAL_AbstractSensor {

	public function HookEvents() {
		if(is_admin())add_action('init', array($this, 'EventRegisterOldPost'));
		add_action('transition_post_status', array($this, 'EventPostChanged'), 10, 3);
		add_action('delete_post', array($this, 'EventPostDeleted'), 10, 1);
		add_action('wp_trash_post', array($this, 'EventPostTrashed'), 10, 1);
		add_action('untrash_post', array($this, 'EventPostUntrashed'));
	}
	
	protected function GetEventTypeForPostType($post, $typePost, $typePage, $typeCustom){
		switch($post->post_type){
			case 'page':
				return $typePage;
			case 'post':
				return $typePost;
			default:
				return $typeCustom;
		}
	}
	
	protected $_OldPost = null;
	
	public function EventRegisterOldPost(){
		// ignorable states
        if (!isset($_POST) || !isset($_POST['post_ID'])) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (isset($_POST['action']) && $_POST['action'] == 'autosave') return;
        
		$this->_OldPost = get_post(intval($_POST['post_ID']));
	}
	
	public function EventPostChanged($newStatus, $oldStatus, $post){
		// ignorable states
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (empty($post->post_type)) return;
        if ($post->post_type == 'revision') return;
		
		WSAL_Sensors_Request::SetVars(array(
			'$newStatus' => $newStatus,
			'$oldStatus' => $oldStatus,
		));
		
        // run checks
		if($this->_OldPost){
			if ($newStatus == 'publish' && $oldStatus == 'auto-draft'){
				// TODO What's the difference between created and published new post?
				$event = $this->GetEventTypeForPostType($post, 2000, 2004, 2029);
				$this->plugin->alerts->Trigger($event, array(
					'PostID' => $post->ID,
					'PostType' => $post->post_type,
					'PostTitle' => $post->post_title,
					'PostUrl' => get_permalink($post->ID),
				));
			}else{
				$this->CheckDateChange($this->_OldPost, $post);
				$this->CheckCategoriesChange($this->_OldPost, $post);
				$this->CheckAuthorChange($this->_OldPost, $post);
				//$this->CheckStatusChange($this->_OldPost, $post);
				//$this->CheckContentChange($this->_OldPost, $post);
				//$this->CheckVisibilityChange($this->_OldPost, $post);
			}
		}
	}
	
	public function EventPostDeleted($post_id){
		$post = get_post($post_id);
		$event = $this->GetEventTypeForPostType($post, 2008, 2009, 2033);
		$this->plugin->alerts->Trigger($event, array(
			'PostID' => $post->ID,
			'PostType' => $post->post_type,
			'PostTitle' => $post->post_title,
		));
	}
	
	public function EventPostTrashed($post_id){
		$post = get_post($post_id);
		$event = $this->GetEventTypeForPostType($post, 2012, 2013, 2034);
		$this->plugin->alerts->Trigger($event, array(
			'PostID' => $post->ID,
			'PostType' => $post->post_type,
			'PostTitle' => $post->post_title,
		));
	}
	
	public function EventPostUntrashed($post_id){
		$post = get_post($post_id);
		$event = $this->GetEventTypeForPostType($post, 2014, 2015, 2035);
		$this->plugin->alerts->Trigger($event, array(
			'PostID' => $post->ID,
			'PostType' => $post->post_type,
			'PostTitle' => $post->post_title,
		));
	}
	
	protected function CheckDateChange($oldpost, $newpost){
        $from = strtotime($oldpost->post_date);
        $to = strtotime($newpost->post_date);
        if($from != $to){
			$event = $this->GetEventTypeForPostType($oldpost, 2027, 2028, 2041);
			$this->plugin->alerts->Trigger($event, array(
				'PostID' => $oldpost->ID,
				'PostType' => $oldpost->post_type,
				'PostTitle' => $oldpost->post_title,
				'OldDate' => $oldpost->post_date,
				'NewDate' => $newpost->post_date,
			));
        }
	}
	
	protected function CheckCategoriesChange($oldpost, $newpost){
		// TODO get old/new cats
        if(false){ // TODO compare old/new cats
			$event = $this->GetEventTypeForPostType($oldpost, 2016, 0, 2036);
			$this->plugin->alerts->Trigger($event, array(
				'PostID' => $oldpost->ID,
				'PostType' => $oldpost->post_type,
				'PostTitle' => $oldpost->post_title,
				// TODO store old/new cats
			));
        }
	}
	
	protected function CheckAuthorChange($oldpost, $newpost){
        if($oldpost->post_author != $newpost->post_author){
			$event = $this->GetEventTypeForPostType($oldpost, 2019, 2020, 2038);
			$this->plugin->alerts->Trigger($event, array(
				'PostID' => $oldpost->ID,
				'PostType' => $oldpost->post_type,
				'PostTitle' => $oldpost->post_title,
				'OldAuthor' => get_userdata($oldpost->post_author)->user_login,
				'NewAuthor' => get_userdata($newpost->post_author)->user_login,
			));
        }
	}
}