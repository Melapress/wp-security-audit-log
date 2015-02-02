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
	protected $_OldLink = null;
	protected $_OldCats = null;
	protected $_OldTmpl = null;
	protected $_OldStky = null;
	
	public function EventWordpressInit(){
		// load old data, if applicable
		$this->RetrieveOldData();
		// check for category changes
		$this->CheckCategoryCreation();
		$this->CheckCategoryDeletion();
	}
	
	protected function RetrieveOldData(){
        if (isset($_POST) && isset($_POST['post_ID'])
			&& !(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
			&& !(isset($_POST['action']) && $_POST['action'] == 'autosave')
		){
			$postID = intval($_POST['post_ID']);
			$this->_OldPost = get_post($postID);
			$this->_OldLink = get_permalink($postID);
			$this->_OldTmpl = $this->GetPostTemplate($this->_OldPost);
			$this->_OldCats = $this->GetPostCategories($this->_OldPost);
			$this->_OldStky = in_array($postID, get_option('sticky_posts'));
		}
	}
	
	protected function GetPostTemplate($post){
		$id = $post->ID;
		$template = get_page_template_slug($id);
		$pagename = $post->post_name;

		$templates = array();
		if ( $template && 0 === validate_file( $template ) ) $templates[] = $template;
		if ( $pagename ) $templates[] = "page-$pagename.php";
		if ( $id ) $templates[] = "page-$id.php";
		$templates[] = 'page.php';

		return get_query_template( 'page', $templates );
	}

	protected function GetPostCategories($post){
		return wp_get_post_categories($post->ID, array('fields' => 'names'));
	}

	public function EventPostChanged($newStatus, $oldStatus, $post){
		// ignorable states
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (empty($post->post_type)) return;
        if ($post->post_type == 'revision') return;
		
		$original = isset($_POST['original_post_status']) ? $_POST['original_post_status'] : '';
		
		WSAL_Sensors_Request::SetVars(array(
			'$newStatus' => $newStatus,
			'$oldStatus' => $oldStatus,
			'$original' => $original,
		));
		
        // run checks
		if($this->_OldPost){
			if ($oldStatus == 'auto-draft' || $original == 'auto-draft'){
				
				// Handle create post events
				$this->CheckPostCreation($this->_OldPost, $post);
				
			}else{
				
				// Handle update post events
				$changes = 0
					+ $this->CheckDateChange($this->_OldPost, $post)
					+ $this->CheckAuthorChange($this->_OldPost, $post)
					+ $this->CheckStatusChange($this->_OldPost, $post)
					+ $this->CheckParentChange($this->_OldPost, $post)
					+ $this->CheckStickyChange($this->_OldStky, isset($_REQUEST['sticky']), $post)
					+ $this->CheckVisibilityChange($this->_OldPost, $post, $oldStatus, $newStatus)
					+ $this->CheckTemplateChange($this->_OldTmpl, $this->GetPostTemplate($post), $post)
					+ $this->CheckCategoriesChange($this->_OldCats, $this->GetPostCategories($post), $post)
				;
				if(!$changes)
					$changes = $this->CheckPermalinkChange($this->_OldLink, get_permalink($post->ID), $post);
				if(!$changes)
					$changes = $this->CheckModificationChange($this->_OldPost, $post);
				
			}
		}
	}
	
	protected function CheckPostCreation($oldPost, $newPost){
		$event = 0;
		switch($newPost->post_status){
			case 'publish':
				$event = $this->GetEventTypeForPostType($oldPost, 2001, 2005, 2030);
				break;
			case 'draft':
				$event = $this->GetEventTypeForPostType($oldPost, 2000, 2004, 2029);
				break;
		}
		if($event)$this->plugin->alerts->Trigger($event, array(
			'PostID' => $newPost->ID,
			'PostType' => $newPost->post_type,
			'PostTitle' => $newPost->post_title,
			'PostUrl' => get_permalink($newPost->ID),
		));
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

		if(isset($_POST['taxonomy'])){
			if($action == 'delete' && $_POST['taxonomy'] == 'category' && !empty($_POST['delete_tags'])){
				// bulk delete
				$categoryIds[] = $_POST['delete_tags'];
			}elseif($action == 'delete-tag' && $_POST['taxonomy'] == 'category' && !empty($_POST['tag_ID'])){
				// single delete
				$categoryIds[] = $_POST['tag_ID'];
			}
		}

		foreach($categoryIds as $categoryID){
			$category = get_category($categoryID);
			$this->plugin->alerts->Trigger(2024, array(
				'CategoryID' => $categoryID,
				'CategoryName' => $category->cat_name,
			));
		}
	}
	
	public function EventPostDeleted($post_id){
		$post = get_post($post_id);
		if(!in_array($post->post_type, array('attachment', 'revision'))){ // ignore attachments and revisions
			$event = $this->GetEventTypeForPostType($post, 2008, 2009, 2033);
			$this->plugin->alerts->Trigger($event, array(
				'PostID' => $post->ID,
				'PostType' => $post->post_type,
				'PostTitle' => $post->post_title,
			));
		}
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
		if($oldpost->post_status == 'draft')return;
        if($from != $to){
			$event = $this->GetEventTypeForPostType($oldpost, 2027, 2028, 2041);
			$this->plugin->alerts->Trigger($event, array(
				'PostID' => $oldpost->ID,
				'PostType' => $oldpost->post_type,
				'PostTitle' => $oldpost->post_title,
				'OldDate' => $oldpost->post_date,
				'NewDate' => $newpost->post_date,
			));
			return 1;
        }
	}
	
	protected function CheckCategoriesChange($oldCats, $newCats, $post){
		$oldCats = implode(', ', $oldCats);
		$newCats = implode(', ', $newCats);
        if($oldCats != $newCats){
			$event = $this->GetEventTypeForPostType($post, 2016, 0, 2036);
			if($event){
				$this->plugin->alerts->Trigger($event, array(
					'PostID' => $post->ID,
					'PostType' => $post->post_type,
					'PostTitle' => $post->post_title,
					'OldCategories' => $oldCats ? $oldCats : 'no categories',
					'NewCategories' => $newCats ? $newCats : 'no categories',
				));
				return 1;
			}
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
			return 1;
        }
	}
	
	protected function CheckStatusChange($oldpost, $newpost){
        if($oldpost->post_status != $newpost->post_status){
			if(isset($_REQUEST['publish'])){
				// special case (publishing a post)
				$event = $this->GetEventTypeForPostType($oldpost, 2001, 2005, 2030);
				$this->plugin->alerts->Trigger($event, array(
					'PostID' => $newpost->ID,
					'PostType' => $newpost->post_type,
					'PostTitle' => $newpost->post_title,
					'PostUrl' => get_permalink($newpost->ID),
				));
			}else{
				$event = $this->GetEventTypeForPostType($oldpost, 2021, 2022, 2039);
				$this->plugin->alerts->Trigger($event, array(
					'PostID' => $oldpost->ID,
					'PostType' => $oldpost->post_type,
					'PostTitle' => $oldpost->post_title,
					'OldStatus' => $oldpost->post_status,
					'NewStatus' => $newpost->post_status,
				));
			}
			return 1;
		}
	}
	
	protected function CheckParentChange($oldpost, $newpost){
        if($oldpost->post_parent != $newpost->post_parent){
			$event = $this->GetEventTypeForPostType($oldpost, 0, 2047, 0);
			if($event){
				$this->plugin->alerts->Trigger($event, array(
					'PostID' => $oldpost->ID,
					'PostType' => $oldpost->post_type,
					'PostTitle' => $oldpost->post_title,
					'OldParent' => $oldpost->post_parent,
					'NewParent' => $newpost->post_parent,
					'OldParentName' => $oldpost->post_parent ? get_the_title($oldpost->post_parent) : 'no parent',
					'NewParentName' => $newpost->post_parent ? get_the_title($newpost->post_parent) : 'no parent',
				));
				return 1;
			}
        }
	}
	
	protected function CheckPermalinkChange($oldLink, $newLink, $post){
        if($oldLink != $newLink){
			$event = $this->GetEventTypeForPostType($post, 2017, 2018, 2037);
			$this->plugin->alerts->Trigger($event, array(
				'PostID' => $post->ID,
				'PostType' => $post->post_type,
				'PostTitle' => $post->post_title,
				'OldUrl' => $oldLink,
				'NewUrl' => $newLink,
			));
			return 1;
        }
	}
	
	protected function CheckVisibilityChange($oldpost, $newpost, $oldStatus, $newStatus){
		if($oldStatus == 'draft' || $newStatus == 'draft')return;
		
		$oldVisibility = '';
		$newVisibility = '';
		
		if($oldpost->post_password){
			$oldVisibility = __('Password Protected', 'wp-security-audit-log');
		}elseif($oldStatus == 'publish'){
			$oldVisibility = __('Public', 'wp-security-audit-log');
		}elseif($oldStatus == 'private'){
			$oldVisibility = __('Private', 'wp-security-audit-log');
		}
		
		if($newpost->post_password){
			$newVisibility = __('Password Protected', 'wp-security-audit-log');
		}elseif($newStatus == 'publish'){
			$newVisibility = __('Public', 'wp-security-audit-log');
		}elseif($newStatus == 'private'){
			$newVisibility = __('Private', 'wp-security-audit-log');
		}
		
        if($oldVisibility && $newVisibility && ($oldVisibility != $newVisibility)){
			$event = $this->GetEventTypeForPostType($oldpost, 2025, 2026, 2040);
			$this->plugin->alerts->Trigger($event, array(
				'PostID' => $oldpost->ID,
				'PostType' => $oldpost->post_type,
				'PostTitle' => $oldpost->post_title,
				'OldVisibility' => $oldVisibility,
				'NewVisibility' => $newVisibility,
			));
			return 1;
        }
	}
	
	protected function CheckTemplateChange($oldTmpl, $newTmpl, $post){
        if($oldTmpl != $newTmpl){
			$event = $this->GetEventTypeForPostType($post, 0, 2048, 0);
			if($event){
				$this->plugin->alerts->Trigger($event, array(
					'PostID' => $post->ID,
					'PostType' => $post->post_type,
					'PostTitle' => $post->post_title,
					'OldTemplate' => ucwords(str_replace(array('-' , '_'), ' ', basename($oldTmpl, '.php'))),
					'NewTemplate' => ucwords(str_replace(array('-' , '_'), ' ', basename($newTmpl, '.php'))),
					'OldTemplatePath' => $oldTmpl,
					'NewTemplatePath' => $newTmpl,
				));
				return 1;
			}
        }
	}
	
	protected function CheckStickyChange($oldStky, $newStky, $post){
		if($oldStky != $newStky){
			$event = $newStky ? 2049 : 2050;
			$this->plugin->alerts->Trigger($event, array(
				'PostID' => $post->ID,
				'PostType' => $post->post_type,
				'PostTitle' => $post->post_title,
			));
			return 1;
        }
	}
	
	protected function CheckModificationChange($oldpost, $newpost){
		$contentChanged = $oldpost->post_content != $newpost->post_content; // TODO what about excerpts?
		
		if($oldpost->post_modified != $newpost->post_modified){
			$event = 0;
			// @see http://codex.wordpress.org/Class_Reference/WP_Query#Status_Parameters
			switch($oldpost->post_status){ // TODO or should this be $newpost?
				case 'draft':
					if($contentChanged){
						$event = $this->GetEventTypeForPostType($newpost, 2068, 2069, 2070);
					}else{
						$event = $this->GetEventTypeForPostType($newpost, 2003, 2007, 2032);
					}
					break;
				case 'publish':
					if($contentChanged){
						$event = $this->GetEventTypeForPostType($newpost, 2065, 2066, 2067);
					}else{
						$event = $this->GetEventTypeForPostType($newpost, 2002, 2006, 2031);
					}
					break;
			}
			if($event){
				$this->plugin->alerts->Trigger($event, array(
					'PostID' => $oldpost->ID,
					'PostType' => $oldpost->post_type,
					'PostTitle' => $oldpost->post_title,
					'PostUrl' => get_permalink($oldpost->ID), // TODO or should this be $newpost?
				));
				return 1;
			}
		}
	}
}
