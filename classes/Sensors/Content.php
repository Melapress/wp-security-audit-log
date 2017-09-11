<?php
/**
 * @package Wsal
 * @subpackage Sensors
 * Wordpress contents (posts, pages and custom posts).
 *
 * 2000 User created a new blog post and saved it as draft
 * 2001 User published a blog post
 * 2002 User modified a published blog post
 * 2003 User modified a draft blog post
 * 2008 User permanently deleted a blog post from the trash
 * 2012 User moved a blog post to the trash
 * 2014 User restored a blog post from trash
 * 2016 User changed blog post category
 * 2017 User changed blog post URL
 * 2019 User changed blog post author
 * 2021 User changed blog post status
 * 2023 User created new category
 * 2024 User deleted category
 * 2025 User changed the visibility of a blog post
 * 2027 User changed the date of a blog post
 * 2049 User set a post as sticky
 * 2050 User removed post from sticky
 * 2052 User changed generic tables
 * 2065 User modified content for a published post
 * 2068 User modified content for a draft post
 * 2072 User modified content of a post
 * 2073 User submitted a post for review
 * 2074 User scheduled a post
 * 2086 User changed title of a post
 * 2100 User opened a post in the editor
 * 2101 User viewed a post
 * 2111 User disabled Comments/Trackbacks and Pingbacks on a published post
 * 2112 User enabled Comments/Trackbacks and Pingbacks on a published post
 * 2113 User disabled Comments/Trackbacks and Pingbacks on a draft post
 * 2114 User enabled Comments/Trackbacks and Pingbacks on a draft post
 * 2004 User created a new WordPress page and saved it as draft
 * 2005 User published a WordPress page
 * 2006 User modified a published WordPress page
 * 2007 User modified a draft WordPress page
 * 2009 User permanently deleted a page from the trash
 * 2013 User moved WordPress page to the trash
 * 2015 User restored a WordPress page from trash
 * 2018 User changed page URL
 * 2020 User changed page author
 * 2022 User changed page status
 * 2026 User changed the visibility of a page post
 * 2028 User changed the date of a page post
 * 2047 User changed the parent of a page
 * 2048 User changed the template of a page
 * 2066 User modified content for a published page
 * 2069 User modified content for a draft page
 * 2075 User scheduled a page
 * 2087 User changed title of a page
 * 2102 User opened a page in the editor
 * 2103 User viewed a page
 * 2115 User disabled Comments/Trackbacks and Pingbacks on a published page
 * 2116 User enabled Comments/Trackbacks and Pingbacks on a published page
 * 2117 User disabled Comments/Trackbacks and Pingbacks on a draft page
 * 2118 User enabled Comments/Trackbacks and Pingbacks on a draft page
 * 2029 User created a new post with custom post type and saved it as draft
 * 2030 User published a post with custom post type
 * 2031 User modified a post with custom post type
 * 2032 User modified a draft post with custom post type
 * 2033 User permanently deleted post with custom post type
 * 2034 User moved post with custom post type to trash
 * 2035 User restored post with custom post type from trash
 * 2036 User changed the category of a post with custom post type
 * 2037 User changed the URL of a post with custom post type
 * 2038 User changed the author or post with custom post type
 * 2039 User changed the status of post with custom post type
 * 2040 User changed the visibility of a post with custom post type
 * 2041 User changed the date of post with custom post type
 * 2067 User modified content for a published custom post type
 * 2070 User modified content for a draft custom post type
 * 2076 User scheduled a custom post type
 * 2088 User changed title of a custom post type
 * 2104 User opened a custom post type in the editor
 * 2105 User viewed a custom post type
 */
class WSAL_Sensors_Content extends WSAL_AbstractSensor {
	/**
	 * @var stdClass old post
	 */
	protected $_OldPost = null;

	/**
	 * @var string old permalink
	 */
	protected $_OldLink = null;

	/**
	 * @var array old categories
	 */
	protected $_OldCats = null;

	/**
	 * @var string old path to file
	 */
	protected $_OldTmpl = null;

	/**
	 * @var boolean old post is marked as sticky
	 */
	protected $_OldStky = null;

	/**
	 * Listening to events using WP hooks.
	 */
	public function HookEvents()
	{
		if (current_user_can("edit_posts")) {
			add_action('admin_init', array($this, 'EventWordpressInit'));
		}
		add_action('transition_post_status', array($this, 'EventPostChanged'), 10, 3);
		add_action('delete_post', array($this, 'EventPostDeleted'), 10, 1);
		add_action('wp_trash_post', array($this, 'EventPostTrashed'), 10, 1);
		add_action('untrash_post', array($this, 'EventPostUntrashed'));
		add_action('edit_category', array($this, 'EventChangedCategoryParent'));
		add_action('save_post', array($this, 'SetRevisionLink'), 10, 3);
		add_action('publish_future_post', array($this, 'EventPublishFuture'), 10, 1);
		// to do change with 'create_term' instead 'create_category' for trigger Tags
		add_action('create_category', array($this, 'EventCategoryCreation'), 10, 1);

		add_action( 'wp_head', array( $this, 'ViewingPost' ), 10 );
		add_filter('post_edit_form_tag', array($this, 'EditingPost'), 10, 1);
	}

	/**
	 * Gets the alert code based on the type of post.
	 * @param stdClass $post the post
	 * @param integer $typePost alert code type post
	 * @param integer $typePage alert code type page
	 * @param integer $typeCustom alert code type custom
	 * @return integer alert code
	 */
	protected function GetEventTypeForPostType($post, $typePost, $typePage, $typeCustom)
	{
		switch ($post->post_type) {
			case 'page':
				return $typePage;
			case 'post':
				return $typePost;
			default:
				return $typeCustom;
		}
	}

	/**
	 * Triggered when a user accesses the admin area.
	 */
	public function EventWordpressInit()
	{
		// load old data, if applicable
		$this->RetrieveOldData();
		// check for category changes
		$this->CheckCategoryDeletion();
	}

	/**
	 * Retrieve Old data.
	 * @global mixed $_POST post data
	 */
	protected function RetrieveOldData()
	{
		if (isset($_POST) && isset($_POST['post_ID'])
			&& !(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
			&& !(isset($_POST['action']) && $_POST['action'] == 'autosave')
		) {
			$postID = intval($_POST['post_ID']);
			$this->_OldPost = get_post($postID);
			$this->_OldLink = get_permalink($postID);
			$this->_OldTmpl = $this->GetPostTemplate($this->_OldPost);
			$this->_OldCats = $this->GetPostCategories($this->_OldPost);
			$this->_OldStky = in_array($postID, get_option('sticky_posts'));
		}
	}

	/**
	 * Get the template path.
	 * @param stdClass $post the post
	 * @return string full path to file
	 */
	protected function GetPostTemplate($post)
	{
		$id = $post->ID;
		$template = get_page_template_slug($id);
		$pagename = $post->post_name;

		$templates = array();
		if ($template && 0 === validate_file($template)) {
			$templates[] = $template;
		}
		if ($pagename) {
			$templates[] = "page-$pagename.php";
		}
		if ($id) {
			$templates[] = "page-$id.php";
		}
		$templates[] = 'page.php';

		return get_query_template('page', $templates);
	}

	/**
	 * Get post categories (array of category names).
	 * @param stdClass $post the post
	 * @return array list of categories
	 */
	protected function GetPostCategories($post)
	{
		return wp_get_post_categories($post->ID, array('fields' => 'names'));
	}

	/**
	 * Check all the post changes.
	 * @param string $newStatus new status
	 * @param string $oldStatus old status
	 * @param stdClass $post the post
	 */
	public function EventPostChanged($newStatus, $oldStatus, $post)
	{
		// ignorable states
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}
		if (empty($post->post_type)) {
			return;
		}
		if ($post->post_type == 'revision') {
			return;
		}

		$original = isset($_POST['original_post_status']) ? $_POST['original_post_status'] : '';

		WSAL_Sensors_Request::SetVars(array(
			'$newStatus' => $newStatus,
			'$oldStatus' => $oldStatus,
			'$original' => $original,
		));
		// run checks
		if ($this->_OldPost) {
			if ($this->CheckOtherSensors($this->_OldPost)) {
				return;
			}
			if ($oldStatus == 'auto-draft' || $original == 'auto-draft') {
				// Handle create post events
				$this->CheckPostCreation($this->_OldPost, $post);
			} else {
				// Handle update post events
				$changes = 0
					+ $this->CheckAuthorChange($this->_OldPost, $post)
					+ $this->CheckStatusChange($this->_OldPost, $post)
					+ $this->CheckParentChange($this->_OldPost, $post)
					+ $this->CheckStickyChange($this->_OldStky, isset($_REQUEST['sticky']), $post)
					+ $this->CheckVisibilityChange($this->_OldPost, $post, $oldStatus, $newStatus)
					+ $this->CheckTemplateChange($this->_OldTmpl, $this->GetPostTemplate($post), $post)
					+ $this->CheckCategoriesChange($this->_OldCats, $this->GetPostCategories($post), $post)
				;

				if (!$changes) {
					$changes = $this->CheckDateChange($this->_OldPost, $post);
					if (!$changes) {
						$changes = $this->CheckPermalinkChange($this->_OldLink, get_permalink($post->ID), $post);
						// Comments/Trackbacks and Pingbacks
						if (!$changes) {
							$changes = $this->CheckCommentsPings($this->_OldPost, $post);
							if (!$changes) {
								$changes = $this->CheckModificationChange($post->ID, $this->_OldPost, $post);
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Check post creation.
	 * @global array $_POST
	 * @param stdClass $oldPost old post
	 * @param stdClass $newPost new post
	 */
	protected function CheckPostCreation($oldPost, $newPost)
	{
		$WPActions = array('editpost', 'heartbeat');
		if (isset($_POST['action']) && in_array($_POST['action'], $WPActions)) {
			if (!in_array($newPost->post_type, array('attachment', 'revision', 'nav_menu_item'))) {
				$event = 0;
				$is_scheduled = false;
				switch ($newPost->post_status) {
					case 'publish':
						$event = $this->GetEventTypeForPostType($newPost, 2001, 2005, 2030);
						break;
					case 'draft':
						$event = $this->GetEventTypeForPostType($newPost, 2000, 2004, 2029);
						break;
					case 'future':
						$event = $this->GetEventTypeForPostType($newPost, 2074, 2075, 2076);
						$is_scheduled = true;
						break;
					case 'pending':
						$event = 2073;
						break;
				}
				if ($event) {
					$editorLink = $this->GetEditorLink($newPost);
					if ($is_scheduled) {
						$this->plugin->alerts->Trigger($event, array(
							'PostType' => $newPost->post_type,
							'PostTitle' => $newPost->post_title,
							'PublishingDate' => $newPost->post_date,
							$editorLink['name'] => $editorLink['value']
						));
					} else {
						$this->plugin->alerts->Trigger($event, array(
							'PostID' => $newPost->ID,
							'PostType' => $newPost->post_type,
							'PostTitle' => $newPost->post_title,
							'PostUrl' => get_permalink($newPost->ID),
							$editorLink['name'] => $editorLink['value']
						));
					}
				}
			}
		}
	}

	/**
	 * Post future publishing.
	 * @param integer $post_id post ID
	 */
	public function EventPublishFuture($post_id)
	{
		$post = get_post($post_id);
		$event = $this->GetEventTypeForPostType($post, 2001, 2005, 2030);

		if ($event) {
			$editorLink = $this->GetEditorLink($newPost);
			$this->plugin->alerts->Trigger($event, array(
				'PostID' => $post->ID,
				'PostType' => $post->post_type,
				'PostTitle' => $post->post_title,
				'PostUrl' => get_permalink($post->ID),
				$editorLink['name'] => $editorLink['value']
			));
		}
	}

	/**
	 * Post permanently deleted.
	 * @param integer $post_id post ID
	 */
	public function EventPostDeleted($post_id)
	{
		$post = get_post($post_id);
		if ($this->CheckOtherSensors($post)) {
			return;
		}
		$WPActions = array('delete');
		if (isset($_REQUEST['action']) && in_array($_REQUEST['action'], $WPActions)) {
			if (!in_array($post->post_type, array('attachment', 'revision', 'nav_menu_item'))) { // ignore attachments, revisions and menu items
				$event = $this->GetEventTypeForPostType($post, 2008, 2009, 2033);
				// check WordPress backend operations
				if ($this->CheckAutoDraft($event, $post->post_title)) {
					return;
				}
				$editorLink = $this->GetEditorLink($post);
				$this->plugin->alerts->Trigger($event, array(
					'PostID' => $post->ID,
					'PostType' => $post->post_type,
					'PostTitle' => $post->post_title,
				));
			}
		}
	}

	/**
	 * Post moved to the trash.
	 * @param integer $post_id post ID
	 */
	public function EventPostTrashed($post_id)
	{
		$post = get_post($post_id);
		if ($this->CheckOtherSensors($post)) {
			return;
		}
		$event = $this->GetEventTypeForPostType($post, 2012, 2013, 2034);
		$editorLink = $this->GetEditorLink($post);
		$this->plugin->alerts->Trigger($event, array(
			'PostID' => $post->ID,
			'PostType' => $post->post_type,
			'PostTitle' => $post->post_title,
			'PostUrl' => get_permalink($post->ID),
			$editorLink['name'] => $editorLink['value']
		));
	}

	/**
	 * Post restored from trash.
	 * @param integer $post_id post ID
	 */
	public function EventPostUntrashed($post_id)
	{
		$post = get_post($post_id);
		if ($this->CheckOtherSensors($post)) {
			return;
		}
		$event = $this->GetEventTypeForPostType($post, 2014, 2015, 2035);
		$editorLink = $this->GetEditorLink($post);
		$this->plugin->alerts->Trigger($event, array(
			'PostID' => $post->ID,
			'PostType' => $post->post_type,
			'PostTitle' => $post->post_title,
			$editorLink['name'] => $editorLink['value']
		));
	}

	/**
	 * Post date changed.
	 * @param stdClass $oldPost old post
	 * @param stdClass $newPost new post
	 */
	protected function CheckDateChange($oldpost, $newpost)
	{
		$from = strtotime($oldpost->post_date);
		$to = strtotime($newpost->post_date);
		if ($oldpost->post_status == 'draft') {
			return 0;
		}
		$pending = $this->CheckReviewPendingChange($oldpost, $newpost);
		if ($pending) {
			return 0;
		}
		if ($from != $to) {
			$event = $this->GetEventTypeForPostType($oldpost, 2027, 2028, 2041);
			$editorLink = $this->GetEditorLink($oldpost);
			$this->plugin->alerts->Trigger($event, array(
				'PostID' => $oldpost->ID,
				'PostType' => $oldpost->post_type,
				'PostTitle' => $oldpost->post_title,
				'OldDate' => $oldpost->post_date,
				'NewDate' => $newpost->post_date,
				$editorLink['name'] => $editorLink['value']
			));
			return 1;
		}
		return 0;
	}

	/**
	 * Revision used.
	 * @param stdClass $oldPost old post
	 * @param stdClass $newPost new post
	 */
	protected function CheckReviewPendingChange($oldpost, $newpost)
	{
		if ($oldpost->post_status == 'pending') {
			$editorLink = $this->GetEditorLink($oldpost);
			$this->plugin->alerts->Trigger(2072, array(
				'PostID' => $oldpost->ID,
				'PostType' => $oldpost->post_type,
				'PostTitle' => $oldpost->post_title,
				$editorLink['name'] => $editorLink['value']
			));
			return 1;
		}
		return 0;
	}

	/**
	 * Categories changed.
	 * @param array $oldCats old categories
	 * @param array $newCats new categories
	 * @param stdClass $post the post
	 */
	protected function CheckCategoriesChange($oldCats, $newCats, $post)
	{
		$oldCats = implode(', ', $oldCats);
		$newCats = implode(', ', $newCats);
		if ($oldCats != $newCats) {
			$event = $this->GetEventTypeForPostType($post, 2016, 0, 2036);
			if ($event) {
				$editorLink = $this->GetEditorLink($post);
				$this->plugin->alerts->Trigger($event, array(
					'PostID' => $post->ID,
					'PostType' => $post->post_type,
					'PostTitle' => $post->post_title,
					'OldCategories' => $oldCats ? $oldCats : 'no categories',
					'NewCategories' => $newCats ? $newCats : 'no categories',
					$editorLink['name'] => $editorLink['value']
				));
				return 1;
			}
		}
	}

	/**
	 * Author changed.
	 * @param stdClass $oldPost old post
	 * @param stdClass $newPost new post
	 */
	protected function CheckAuthorChange($oldpost, $newpost)
	{
		if ($oldpost->post_author != $newpost->post_author) {
			$event = $this->GetEventTypeForPostType($oldpost, 2019, 2020, 2038);
			$editorLink = $this->GetEditorLink($oldpost);
			$oldAuthor = get_userdata($oldpost->post_author);
			$oldAuthor = (is_object($oldAuthor)) ? $oldAuthor->user_login : 'N/A';
			$newAuthor = get_userdata($newpost->post_author);
			$newAuthor = (is_object($newAuthor)) ? $newAuthor->user_login : 'N/A';
			$this->plugin->alerts->Trigger($event, array(
				'PostID' => $oldpost->ID,
				'PostType' => $oldpost->post_type,
				'PostTitle' => $oldpost->post_title,
				'OldAuthor' => $oldAuthor,
				'NewAuthor' => $newAuthor,
				$editorLink['name'] => $editorLink['value']
			));
			return 1;
		}
	}

	/**
	 * Status changed.
	 * @param stdClass $oldPost old post
	 * @param stdClass $newPost new post
	 */
	protected function CheckStatusChange($oldpost, $newpost)
	{
		if ($oldpost->post_status != $newpost->post_status) {
			if (isset($_REQUEST['publish'])) {
				// special case (publishing a post)
				$event = $this->GetEventTypeForPostType($oldpost, 2001, 2005, 2030);
				$editorLink = $this->GetEditorLink($newpost);
				$this->plugin->alerts->Trigger($event, array(
					'PostID' => $newpost->ID,
					'PostType' => $newpost->post_type,
					'PostTitle' => $newpost->post_title,
					'PostUrl' => get_permalink($newpost->ID),
					$editorLink['name'] => $editorLink['value']
				));
			} else {
				$event = $this->GetEventTypeForPostType($oldpost, 2021, 2022, 2039);
				$editorLink = $this->GetEditorLink($oldpost);
				$this->plugin->alerts->Trigger($event, array(
					'PostID' => $oldpost->ID,
					'PostType' => $oldpost->post_type,
					'PostTitle' => $oldpost->post_title,
					'OldStatus' => $oldpost->post_status,
					'NewStatus' => $newpost->post_status,
					$editorLink['name'] => $editorLink['value']
				));
			}
			return 1;
		}
	}

	/**
	 * Post parent changed.
	 * @param stdClass $oldPost old post
	 * @param stdClass $newPost new post
	 */
	protected function CheckParentChange($oldpost, $newpost)
	{
		if ($oldpost->post_parent != $newpost->post_parent) {
			$event = $this->GetEventTypeForPostType($oldpost, 0, 2047, 0);
			if ($event) {
				$editorLink = $this->GetEditorLink($oldpost);
				$this->plugin->alerts->Trigger($event, array(
					'PostID' => $oldpost->ID,
					'PostType' => $oldpost->post_type,
					'PostTitle' => $oldpost->post_title,
					'OldParent' => $oldpost->post_parent,
					'NewParent' => $newpost->post_parent,
					'OldParentName' => $oldpost->post_parent ? get_the_title($oldpost->post_parent) : 'no parent',
					'NewParentName' => $newpost->post_parent ? get_the_title($newpost->post_parent) : 'no parent',
					$editorLink['name'] => $editorLink['value']
				));
				return 1;
			}
		}
	}

	/**
	 * Permalink changed.
	 * @param string $oldLink old permalink
	 * @param string $newLink new permalink
	 * @param stdClass $post the post
	 */
	protected function CheckPermalinkChange($oldLink, $newLink, $post)
	{
		if ($oldLink != $newLink) {
			$event = $this->GetEventTypeForPostType($post, 2017, 2018, 2037);
			$editorLink = $this->GetEditorLink($post);
			$this->plugin->alerts->Trigger($event, array(
				'PostID' => $post->ID,
				'PostType' => $post->post_type,
				'PostTitle' => $post->post_title,
				'OldUrl' => $oldLink,
				'NewUrl' => $newLink,
				$editorLink['name'] => $editorLink['value']
			));
			return 1;
		}
		return 0;
	}

	/**
	 * Post visibility changed.
	 * @param stdClass $oldPost old post
	 * @param stdClass $newPost new post
	 * @param string $oldStatus old status
	 * @param string $newStatus new status
	 */
	protected function CheckVisibilityChange($oldpost, $newpost, $oldStatus, $newStatus)
	{
		if ($oldStatus == 'draft' || $newStatus == 'draft') {
			return;
		}

		$oldVisibility = '';
		$newVisibility = '';

		if ($oldpost->post_password) {
			$oldVisibility = __('Password Protected', 'wp-security-audit-log');
		} elseif ($oldStatus == 'publish') {
			$oldVisibility = __('Public', 'wp-security-audit-log');
		} elseif ($oldStatus == 'private') {
			$oldVisibility = __('Private', 'wp-security-audit-log');
		}

		if ($newpost->post_password) {
			$newVisibility = __('Password Protected', 'wp-security-audit-log');
		} elseif ($newStatus == 'publish') {
			$newVisibility = __('Public', 'wp-security-audit-log');
		} elseif ($newStatus == 'private') {
			$newVisibility = __('Private', 'wp-security-audit-log');
		}

		if ($oldVisibility && $newVisibility && ($oldVisibility != $newVisibility)) {
			$event = $this->GetEventTypeForPostType($oldpost, 2025, 2026, 2040);
			$editorLink = $this->GetEditorLink($oldpost);
			$this->plugin->alerts->Trigger($event, array(
				'PostID' => $oldpost->ID,
				'PostType' => $oldpost->post_type,
				'PostTitle' => $oldpost->post_title,
				'OldVisibility' => $oldVisibility,
				'NewVisibility' => $newVisibility,
				$editorLink['name'] => $editorLink['value']
			));
			return 1;
		}
	}

	/**
	 * Post template changed.
	 * @param string $oldTmpl old template path
	 * @param string $newTmpl new template path
	 * @param stdClass $post the post
	 */
	protected function CheckTemplateChange($oldTmpl, $newTmpl, $post)
	{
		if ($oldTmpl != $newTmpl) {
			$event = $this->GetEventTypeForPostType($post, 0, 2048, 0);
			if ($event) {
				$editorLink = $this->GetEditorLink($post);
				$this->plugin->alerts->Trigger($event, array(
					'PostID' => $post->ID,
					'PostType' => $post->post_type,
					'PostTitle' => $post->post_title,
					'OldTemplate' => ucwords(str_replace(array('-' , '_'), ' ', basename($oldTmpl, '.php'))),
					'NewTemplate' => ucwords(str_replace(array('-' , '_'), ' ', basename($newTmpl, '.php'))),
					'OldTemplatePath' => $oldTmpl,
					'NewTemplatePath' => $newTmpl,
					$editorLink['name'] => $editorLink['value']
				));
				return 1;
			}
		}
	}

	/**
	 * Post sets as sticky changes.
	 * @param string $oldTmpl old template path
	 * @param string $newTmpl new template path
	 * @param stdClass $post the post
	 */
	protected function CheckStickyChange($oldStky, $newStky, $post)
	{
		if ($oldStky != $newStky) {
			$event = $newStky ? 2049 : 2050;
			$editorLink = $this->GetEditorLink($post);
			$this->plugin->alerts->Trigger($event, array(
				'PostID' => $post->ID,
				'PostType' => $post->post_type,
				'PostTitle' => $post->post_title,
				'PostUrl' => get_permalink($post->ID),
				$editorLink['name'] => $editorLink['value']
			));
			return 1;
		}
	}

	/**
	 * Post modified content.
	 * @param integer $post_ID post ID
	 * @param stdClass $oldPost old post
	 * @param stdClass $newPost new post
	 */
	public function CheckModificationChange($post_ID, $oldpost, $newpost)
	{
		if ($this->CheckOtherSensors($oldpost)) {
			return;
		}
		$changes = $this->CheckTitleChange($oldpost, $newpost);
		if (!$changes) {
			$contentChanged = $oldpost->post_content != $newpost->post_content; // TODO what about excerpts?

			if ($oldpost->post_modified != $newpost->post_modified) {
				$event = 0;
				// @see http://codex.wordpress.org/Class_Reference/WP_Query#Status_Parameters
				switch ($oldpost->post_status) { // TODO or should this be $newpost?
					case 'draft':
						if ($contentChanged) {
							$event = $this->GetEventTypeForPostType($newpost, 2068, 2069, 2070);
						} else {
							$event = $this->GetEventTypeForPostType($newpost, 2003, 2007, 2032);
						}
						break;
					case 'publish':
						if ($contentChanged) {
							$event = $this->GetEventTypeForPostType($newpost, 2065, 2066, 2067);
						} else {
							$event = $this->GetEventTypeForPostType($newpost, 2002, 2006, 2031);
						}
						break;
				}
				if ($event) {
					$editorLink = $this->GetEditorLink($oldpost);
					$this->plugin->alerts->Trigger($event, array(
						'PostID' => $post_ID,
						'PostType' => $oldpost->post_type,
						'PostTitle' => $oldpost->post_title,
						'PostUrl' => get_permalink($post_ID),
						$editorLink['name'] => $editorLink['value']
					));
					return 1;
				}
			}
		}
	}

	/**
	 * New category created.
	 * @param integer $category_id category ID
	 */
	public function EventCategoryCreation($category_id)
	{
		$category = get_category($category_id);
		$category_link = $this->getCategoryLink($category_id);
		$this->plugin->alerts->Trigger(2023, array(
			'CategoryName' => $category->name,
			'Slug' => $category->slug,
			'CategoryLink' => $category_link
		));
	}

	/**
	 * Category deleted.
	 * @global array $_POST post data
	 */
	protected function CheckCategoryDeletion()
	{
		if (empty($_POST)) {
			return;
		}
		$action = !empty($_POST['action']) ? $_POST['action']
			: (!empty($_POST['action2']) ? $_POST['action2'] : '');
		if (!$action) {
			return;
		}

		$categoryIds = array();

		if (isset($_POST['taxonomy'])) {
			if ($action == 'delete' && $_POST['taxonomy'] == 'category' && !empty($_POST['delete_tags'])) {
				// bulk delete
				$categoryIds[] = $_POST['delete_tags'];
			} elseif ($action == 'delete-tag' && $_POST['taxonomy'] == 'category' && !empty($_POST['tag_ID'])) {
				// single delete
				$categoryIds[] = $_POST['tag_ID'];
			}
		}

		foreach ($categoryIds as $categoryID) {
			$category = get_category($categoryID);
			$this->plugin->alerts->Trigger(2024, array(
				'CategoryID' => $categoryID,
				'CategoryName' => $category->cat_name,
				'Slug' => $category->slug
			));
		}
	}

	/**
	 * Changed the parent of the category.
	 * @global array $_POST post data
	 */
	public function EventChangedCategoryParent()
	{
		if (empty($_POST)) {
			return;
		}
		if (!current_user_can("manage_categories")) {
			return;
		}
		if (isset($_POST['name']) && isset($_POST['tag_ID'])) {
			$category = get_category($_POST['tag_ID']);
			$category_link = $this->getCategoryLink($_POST['tag_ID']);
			if ($category->parent != 0) {
				$oldParent = get_category($category->parent);
				$oldParentName = (empty($oldParent))? 'no parent' : $oldParent->name;
			} else {
				$oldParentName = 'no parent';
			}
			if (isset($_POST['parent'])) {
				$newParent = get_category($_POST['parent']);
				$newParentName = (empty($newParent))? 'no parent' : $newParent->name;
			}
			$this->plugin->alerts->Trigger(2052, array(
				'CategoryName' => $category->name,
				'OldParent' => $oldParentName,
				'NewParent' => $newParentName,
				'CategoryLink' => $category_link
			));
		}
	}

	/**
	 * Check auto draft and the setting: Hide Plugin in Plugins Page
	 * @param integer $code alert code
	 * @param string $title title
	 * @return boolean
	 */
	private function CheckAutoDraft($code, $title)
	{
		if ($code == 2008 && $title == "auto-draft") {
			// to do check setting else return false
			if ($this->plugin->settings->IsWPBackend() == 1) {
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * Builds revision link.
	 * @param integer $revision_id revision ID
	 * @return string|null link
	 */
	private function getRevisionLink($revision_id)
	{
		if (!empty($revision_id)) {
			return admin_url('revision.php?revision='.$revision_id);
		} else {
			return null;
		}
	}

	/**
	 * Builds category link.
	 * @param integer $category_id category ID
	 * @return string|null link
	 */
	private function getCategoryLink($category_id)
	{
		if (!empty($category_id)) {
			return admin_url('term.php?taxnomy=category&tag_ID='.$category_id);
		} else {
			return null;
		}
	}

	/**
	 * Ignore post from BBPress, WooCommerce Plugin
	 * Triggered on the Sensors
	 *
	 * @param stdClass $post the post.
	 */
	private function CheckOtherSensors( $post ) {
		if ( empty( $post ) || ! isset( $post->post_type ) ) {
			return false;
		}
		switch ( $post->post_type ) {
			case 'forum':
			case 'topic':
			case 'reply':
			case 'product':
				return true;
			default:
				return false;
		}
	}

	/**
	 * Triggered after save post for add revision link.
	 * @param integer $post_id post ID
	 * @param stdClass $post post
	 */
	public function SetRevisionLink($post_id, $post, $update)
	{
		$revisions = wp_get_post_revisions($post_id);
		if (!empty($revisions)) {
			$revision = array_shift($revisions);

			$objOcc = new  WSAL_Models_Occurrence();
			$occ = $objOcc->GetByPostID($post_id);
			$occ = count($occ) ? $occ[0] : null;
			if (!empty($occ)) {
				$revisionLink = $this->getRevisionLink($revision->ID);
				if (!empty($revisionLink)) {
					$occ->SetMetaValue('RevisionLink', $revisionLink);
				}
			}
		}
	}

	/**
	 * Alerts for Viewing of Posts, Pages and Custom Posts.
	 */
	public function ViewingPost() {
		// Retrieve the current post object.
		$post = get_queried_object();
		if ( is_user_logged_in() ) {
			if ( ! is_admin() ) {
				if ( $this->CheckOtherSensors( $post ) ) {
					return $post->post_title;
				}

				$currentPath = $_SERVER['REQUEST_URI'];
				if ( ! empty( $_SERVER['HTTP_REFERER'] )
					&& strpos( $_SERVER['HTTP_REFERER'], $currentPath ) !== false ) {
					// Ignore this if we were on the same page so we avoid double audit entries.
					return;
				}
				if ( ! empty( $post->post_title ) ) {
					$event = $this->GetEventTypeForPostType( $post, 2101, 2103, 2105 );
					$this->plugin->alerts->Trigger( $event, array(
						'PostType'  => $post->post_type,
						'PostTitle' => $post->post_title,
						'PostUrl'   => get_permalink( $post->ID ),
					) );
				}
			}
		}
	}

	/**
	 * Alerts for Editing of Posts, Pages and Custom Posts.
	 * @param stdClass $post post
	 */
	public function EditingPost($post)
	{
		if (is_user_logged_in()) {
			if (is_admin()) {
				if ($this->CheckOtherSensors($post)) {
					return $post;
				}
				$currentPath = $_SERVER["SCRIPT_NAME"] . "?post=" . $post->ID;
				if (!empty($_SERVER["HTTP_REFERER"])
					&& strpos($_SERVER["HTTP_REFERER"], $currentPath) !== false) {
					//Ignore this if we were on the same page so we avoid double audit entries
					return $post;
				}
				if (!empty($post->post_title)) {
					$event = $this->GetEventTypeForPostType($post, 2100, 2102, 2104);
					if (!$this->WasTriggered($event)) {
						$editorLink = $this->GetEditorLink($post);
						$this->plugin->alerts->Trigger($event, array(
							'PostType' => $post->post_type,
							'PostTitle' => $post->post_title,
							$editorLink['name'] => $editorLink['value']
						));
					}
				}
			}
		}
		return $post;
	}

	/**
	 * Check if the alert was triggered.
	 * @param integer $alert_id alert code
	 * @return boolean
	 */
	private function WasTriggered($alert_id)
	{
		$query = new WSAL_Models_OccurrenceQuery();
		$query->addOrderBy("created_on", true);
		$query->setLimit(1);
		$lastOccurence = $query->getAdapter()->Execute($query);
		if (!empty($lastOccurence)) {
			if ($lastOccurence[0]->alert_id == $alert_id) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Changed title of a post.
	 * @param stdClass $oldPost old post
	 * @param stdClass $newPost new post
	 */
	private function CheckTitleChange($oldpost, $newpost)
	{
		if ($oldpost->post_title != $newpost->post_title) {
			$event = $this->GetEventTypeForPostType($newpost, 2086, 2087, 2088);
			$editorLink = $this->GetEditorLink($oldpost);
			$this->plugin->alerts->Trigger($event, array(
				'OldTitle' => $oldpost->post_title,
				'NewTitle' => $newpost->post_title,
				$editorLink['name'] => $editorLink['value']
			));
			return 1;
		}
		return 0;
	}

	/**
	 * Comments/Trackbacks and Pingbacks check.
	 * @param stdClass $oldPost old post
	 * @param stdClass $newPost new post
	 */
	private function CheckCommentsPings($oldpost, $newpost)
	{
		$result = 0;
		// Comments
		if ($oldpost->comment_status != $newpost->comment_status) {
			$type = 'Comments';

			if ($newpost->comment_status == 'open') {
				$event = $this->GetCommentsPingsEvent($newpost, 'enable');
			} else {
				$event = $this->GetCommentsPingsEvent($newpost, 'disable');
			}

			$this->plugin->alerts->Trigger($event, array(
				'Type' => $type,
				'PostTitle' => $newpost->post_title,
				'PostUrl' => get_permalink($newpost->ID)
			));
			$result = 1;
		}
		// Trackbacks and Pingbacks
		if ($oldpost->ping_status != $newpost->ping_status) {
			$type = 'Trackbacks and Pingbacks';

			if ($newpost->ping_status == 'open') {
				$event = $this->GetCommentsPingsEvent($newpost, 'enable');
			} else {
				$event = $this->GetCommentsPingsEvent($newpost, 'disable');
			}

			$this->plugin->alerts->Trigger($event, array(
				'Type' => $type,
				'PostTitle' => $newpost->post_title,
				'PostUrl' => get_permalink($newpost->ID)
			));
			$result = 1;
		}
		return $result;
	}

	/**
	 * Comments/Trackbacks and Pingbacks event code.
	 * @param stdClass $post the post
	 * @param string $status the status
	 */
	private function GetCommentsPingsEvent($post, $status)
	{
		if ($post->post_type == 'post') {
			if ($post->post_status == 'publish') {
				if ($status == 'disable') {
					$event = 2111;
				} else {
					$event = 2112;
				}
			} else {
				if ($status == 'disable') {
					$event = 2113;
				} else {
					$event = 2114;
				}
			}
		} else {
			if ($post->post_status == 'publish') {
				if ($status == 'disable') {
					$event = 2115;
				} else {
					$event = 2116;
				}
			} else {
				if ($status == 'disable') {
					$event = 2117;
				} else {
					$event = 2118;
				}
			}
		}
		return $event;
	}

	/**
	 * Get editor link.
	 * @param stdClass $post the post
	 * @return array $aLink name and value link
	 */
	private function GetEditorLink($post)
	{
		$name = 'EditorLink';
		$name .= ($post->post_type == 'page') ? 'Page' : 'Post' ;
		$value = get_edit_post_link($post->ID);
		$aLink = array(
			'name' => $name,
			'value' => $value,
		);
		return $aLink;
	}
}
