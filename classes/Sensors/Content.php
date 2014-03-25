<?php

class WSAL_Sensors_Content extends WSAL_AbstractSensor {

	public function HookEvents() {
		if(is_admin())add_action('init', array($this, 'EventFindOldPost'));
		add_action('transition_post_status', array($this, 'EventPostStatusChange'), 10, 3);
	}
	
	protected $_OldPost = null;
	
	public function EventFindOldPost(){
		// ignorable states
        if (!isset($_POST) || !isset($_POST['post_ID'])) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (isset($_POST['action']) && $_POST['action'] == 'autosave') return;
        
		$this->_OldPost = get_post(intval($_POST['post_ID']));
	}
	
	public function EventPostStatusChange($newStatus, $oldStatus, $post){
		// ignorable states
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (empty($post->post_type)) return;
        if ($post->post_type == 'revision') return;
        if ($newStatus == 'auto-draft' || ($oldStatus == 'new' && $newStatus=='auto-draft')) return;
		
		// run checks
		if($this->_OldPost){
			$this->CheckDateChange($this->_OldPost, $post);
		}
	}
	
	protected function CheckDateChange($oldpost, $newpost){
        $from = strtotime($oldpost->post_date);
        $to = strtotime($newpost->post_date);
        if($from != $to){
			$event = $this->GetEventTypeForPostType($oldpost, 2027, 2028, 2041);
			$this->plugin->alerts->Trigger($event, array(
				'PostID' => $oldpost->ID,
				'PostTitle' => $oldpost->post_title,
				'OldDate' => $from,
				'NewDate' => $to,
			));
        }
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
}