<?php

class WSAL_Sensors_Content extends WSAL_AbstractSensor {

	public function HookEvents() {
		if(is_admin())add_action('init', array($this, 'EventWordpressInit'));
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
	
	public function EventWordpressInit(){
		// load old post, if applicable
		$this->RetrieveOldPost();
		// check for category changes
		$this->CheckCategoryCreation();
		$this->CheckCategoryDeletion();
	}
	
	protected function RetrieveOldPost(){
        if (isset($_POST) && isset($_POST['post_ID'])
			&& !(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
			&& !(isset($_POST['action']) && $_POST['action'] == 'autosave')
		){
			$this->_OldPost = get_post(intval($_POST['post_ID']));
		}
	}

	protected function CheckCategoryCreation(){
		if (empty($_POST)) return;

		$categoryName = '';
		if(!empty($_POST['screen']) && !empty($_POST['tag-name']) &&
			$_POST['screen'] == 'edit-category' &&
			$_POST['taxonomy'] == 'category' &&
			$_POST['action'] == 'add-tag')
		{
			$categoryName = $_POST['tag-name'];
		}
		elseif(!empty($_POST['newcategory']) && $_POST['action'] == 'add-category')
		{
			$categoryName = $_POST['newcategory'];
		}
		
		if($categoryName){
			$this->plugin->alerts->Trigger(2023, array(
				'CategoryName' => $categoryName,
			));
		}
	}

	protected function CheckCategoryDeletion(){
		if (empty($_POST)) return;
		$action = !empty($_POST['action']) ? $_POST['action']
			: (!empty($_POST['action2']) ? $_POST['action2'] : '');
		if (!$action) return;

		$categoryIds = array();

		if($action == 'delete' && $_POST['taxonomy'] == 'category' && !empty($_POST['delete_tags'])){
			// bulk delete
			$categoryIds[] = $_POST['delete_tags'];
		}elseif($action == 'delete-tag' && $_POST['taxonomy'] == 'category' && !empty($_POST['tag_ID'])){
			// single delete
			$categoryIds[] = $_POST['tag_ID'];
		}

		foreach($categoryIds as $categoryID){
			$category = get_category($categoryID);
			$this->plugin->alerts->Trigger(2024, array(
				'CategoryID' => $categoryID,
				'CategoryName' => $category->cat_name,
			));
		}
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
				$this->CheckVisibilityChange($this->_OldPost, $post, $oldStatus, $newStatus);
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
	
	protected function CheckStatusChange($oldpost, $newpost){
		// TODO Finish this.
	}
	
	protected function CheckContentChange($oldpost, $newpost){
		// TODO Finish this.
	}
	
	protected function CheckVisibilityChange($oldpost, $newpost, $oldStatus, $newStatus){
		$oldVisibility = '';
		$newVisibility = '';
		$oldPostPassword = $oldpost->post_password;
		$newPostPassword = $newpost->post_password;
		switch(true){
			case ($oldStatus == 'publish' && $newStatus == 'publish'):
				switch(true){
					case (!empty($newPostPassword) && !empty($oldPostPassword)):
						return; // nothing really changed, ignore call
					case (empty($newPostPassword) && !empty($oldPostPassword)):
						$oldVisibility = __('Password Protected');
						$newVisibility = __('Public');
						break;
					case (!empty($newPostPassword)):
						$oldVisibility = __('Public');
						$newVisibility = __('Password Protected');
						break;
				}
			case ($oldStatus == 'publish' && $newStatus == 'private'):
				switch(true){
					case (empty($newPostPassword) && empty($oldPostPassword)):
						$oldVisibility = __('Public');
						$newVisibility = __('Private');
						break;
					case (!empty($oldPostPassword)):
						$oldVisibility = __('Password Protected');
						$newVisibility = __('Private');
						break;
				}
				break;
			case ($oldStatus == 'private' && $newStatus == 'publish'):
				switch(true){
					case (empty($oldPostPassword) && empty($newPostPassword)):
						$oldVisibility = __('Private');
						$newVisibility = __('Public');
						break;
					case (empty($oldPostPassword) && !empty($newPostPassword)):
						$oldVisibility = __('Private');
						$newVisibility = __('Password Protected');
						break;
				}
				break;
		}
		
        if($oldVisibility != $newVisibility){
			$event = $this->GetEventTypeForPostType($oldpost, 2025, 2026, 2040);
			$this->plugin->alerts->Trigger($event, array(
				'PostID' => $oldpost->ID,
				'PostType' => $oldpost->post_type,
				'PostTitle' => $oldpost->post_title,
				'OldVisibility' => $oldVisibility,
				'NewVisibility' => $newVisibility,
			));
        }
	}
}