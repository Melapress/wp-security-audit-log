<?php
/**
 * @package Wsal
 * @subpackage Sensors
 * Support for BBPress Forum Plugin.
 *
 * 8000 User created new forum
 * 8001 User changed status of a forum
 * 8002 User changed visibility of a forum
 * 8003 User changed the URL of a forum
 * 8004 User changed order of a forum
 * 8005 User moved forum to trash
 * 8006 User permanently deleted forum
 * 8007 User restored forum from trash
 * 8008 User changed the parent of a forum
 * 8011 User changed type of a forum
 * 8014 User created new topic
 * 8015 User changed status of a topic
 * 8016 User changed type of a topic
 * 8017 User changed URL of a topic
 * 8018 User changed the forum of a topic
 * 8019 User moved topic to trash
 * 8020 User permanently deleted topic
 * 8021 User restored topic from trash
 * 8022 User changed visibility of a topic
 */
class WSAL_Sensors_BBPress extends WSAL_AbstractSensor
{
    /**
     * @var string old permalink
     */
    protected $_OldLink = null;

    /**
     * Listening to events using WP hooks.
     */
    public function HookEvents()
    {
        if (current_user_can("edit_posts")) {
            add_action('admin_init', array($this, 'EventAdminInit'));
        }
        add_action('post_updated', array($this, 'CheckForumChange'), 10, 3);
        add_action('delete_post', array($this, 'EventForumDeleted'), 10, 1);
        add_action('wp_trash_post', array($this, 'EventForumTrashed'), 10, 1);
        add_action('untrash_post', array($this, 'EventForumUntrashed'));
    }

    /**
     * Triggered when a user accesses the admin area.
     */
    public function EventAdminInit()
    {
        // load old data, if applicable
        $this->RetrieveOldData();
        // check for Ajax changes
        $this->TriggerAjaxChange();
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
            $this->_OldLink = get_permalink($postID);
        }
    }

    /**
     * Calls event forum changes.
     * @param integer $post_ID post ID
     * @param stdClass $newpost the new post
     * @param stdClass $oldpost the old post
     */
    public function CheckForumChange($post_ID, $newpost, $oldpost)
    {
        if ($this->CheckBBPress($oldpost)) {
            $changes = 0 + $this->EventForumCreation($oldpost, $newpost);
            // Change Visibility
            if (!$changes) {
                $changes = $this->EventForumChangedVisibility($oldpost);
            }
            // Change Type
            if (!$changes) {
                $changes = $this->EventForumChangedType($oldpost);
            }
            // Change status
            if (!$changes) {
                $changes = $this->EventForumChangedStatus($oldpost);
            }
            // Change Order, Parent or URL
            if (!$changes) {
                $changes = $this->EventForumChanged($oldpost, $newpost);
            }
        }
    }

    /**
     * Permanently deleted.
     * @param integer $post_id post ID
     */
    public function EventForumDeleted($post_id)
    {
        $post = get_post($post_id);
        if ($this->CheckBBPress($post)) {
            switch ($post->post_type) {
                case 'forum':
                    $this->EventForumByCode($post, 8006);
                    break;
                case 'topic':
                    $this->EventTopicByCode($post, 8020);
                    break;
            }
        }
    }
    
    /**
     * Moved to Trash.
     * @param integer $post_id post ID
     */
    public function EventForumTrashed($post_id)
    {
        $post = get_post($post_id);
        if ($this->CheckBBPress($post)) {
            switch ($post->post_type) {
                case 'forum':
                    $this->EventForumByCode($post, 8005);
                    break;
                case 'topic':
                    $this->EventTopicByCode($post, 8019);
                    break;
            }
        }
    }

    /**
     * Restored from Trash.
     * @param integer $post_id post ID
     */
    public function EventForumUntrashed($post_id)
    {
        $post = get_post($post_id);
        if ($this->CheckBBPress($post)) {
            switch ($post->post_type) {
                case 'forum':
                    $this->EventForumByCode($post, 8007);
                    break;
                case 'topic':
                    $this->EventTopicByCode($post, 8021);
                    break;
            }
        }
    }

    /**
     * Check post type.
     * @param stdClass $post post
     * @return boolean
     */
    private function CheckBBPress($post)
    {
        switch ($post->post_type) {
            case 'forum':
            case 'topic':
            case 'reply':
                return true;
            default:
                return false;
        }
    }

    /**
     * Event post creation.
     * @param stdClass $old_post the old post
     * @param stdClass $new_post the new post
     * @return boolean
     */
    private function EventForumCreation($old_post, $new_post)
    {
        $original = isset($_POST['original_post_status']) ? $_POST['original_post_status'] : '';
        if ($old_post->post_status == 'draft' || $original == 'auto-draft') {
            $editorLink = $this->GetEditorLink($new_post);
            if ($new_post->post_status == 'publish') {
                switch ($old_post->post_type) {
                    case 'forum':
                        $this->plugin->alerts->Trigger(8000, array(
                            'ForumName' => $new_post->post_title,
                            'ForumURL' => get_permalink($new_post->ID),
                            $editorLink['name'] => $editorLink['value']
                        ));
                        break;
                    case 'topic':
                        $this->plugin->alerts->Trigger(8014, array(
                            'TopicName' => $new_post->post_title,
                            'TopicURL' => get_permalink($new_post->ID),
                            $editorLink['name'] => $editorLink['value']
                        ));
                        break;
                }
                return 1;
            }
        }
        return 0;
    }

    /**
     * Event post changed visibility.
     * @param stdClass $post the post
     * @return boolean $result
     */
    private function EventForumChangedVisibility($post)
    {
        $result = 0;
        $editorLink = $this->GetEditorLink($post);
        switch ($post->post_type) {
            case 'forum':
                $oldVisibility = !empty($_REQUEST['visibility']) ? $_REQUEST['visibility'] : '';
                $newVisibility = !empty($_REQUEST['bbp_forum_visibility']) ? $_REQUEST['bbp_forum_visibility'] : '';
                $newVisibility = ($newVisibility == 'publish') ? 'public' : $newVisibility;

                if (!empty($newVisibility) && $oldVisibility != 'auto-draft' && $oldVisibility != $newVisibility) {
                    $this->plugin->alerts->Trigger(8002, array(
                        'ForumName' => $post->post_title,
                        'OldVisibility' => $oldVisibility,
                        'NewVisibility' => $newVisibility,
                        $editorLink['name'] => $editorLink['value']
                    ));
                    $result = 1;
                }
                break;
            case 'topic':
                $oldVisibility = !empty($_REQUEST['hidden_post_visibility']) ? $_REQUEST['hidden_post_visibility'] : '';
                $newVisibility = !empty($_REQUEST['visibility']) ? $_REQUEST['visibility'] : '';
                $newVisibility = ($newVisibility == 'password') ? 'password protected' : $newVisibility;

                if (!empty($newVisibility) && $oldVisibility != 'auto-draft' && $oldVisibility != $newVisibility) {
                    $this->plugin->alerts->Trigger(8022, array(
                        'TopicName' => $post->post_title,
                        'OldVisibility' => $oldVisibility,
                        'NewVisibility' => $newVisibility,
                        $editorLink['name'] => $editorLink['value']
                    ));
                    $result = 1;
                }
                break;
        }
        return $result;
    }

    /**
     * Event post changed type.
     * @param stdClass $post the post
     * @return boolean $result
     */
    private function EventForumChangedType($post)
    {
        $result = 0;
        $editorLink = $this->GetEditorLink($post);
        switch ($post->post_type) {
            case 'forum':
                $bbp_forum_type = get_post_meta($post->ID, '_bbp_forum_type', true);
                $oldType = !empty($bbp_forum_type) ? $bbp_forum_type : 'forum';
                $newType = !empty($_POST['bbp_forum_type']) ? $_POST['bbp_forum_type'] : '';
                if (!empty($newType) && $oldType != $newType) {
                    $this->plugin->alerts->Trigger(8011, array(
                        'ForumName' => $post->post_title,
                        'OldType' => $oldType,
                        'NewType' => $newType,
                        $editorLink['name'] => $editorLink['value']
                    ));
                    $result = 1;
                }
                break;
            case 'topic':
                if (!empty($_POST['parent_id'])) {
                    $post_id = $_POST['parent_id'];
                } else {
                    $post_id = $post->ID;
                }
                $bbp_sticky_topics = maybe_unserialize(get_post_meta($post_id, '_bbp_sticky_topics', true));
                $fn = $this->IsMultisite() ? 'get_site_option' : 'get_option';
                $bbp_super_sticky_topics = maybe_unserialize($fn('_bbp_super_sticky_topics'));
                if (!empty($bbp_sticky_topics) && in_array($post->ID, $bbp_sticky_topics)) {
                    $oldType = 'sticky';
                } elseif (!empty($bbp_super_sticky_topics) && in_array($post->ID, $bbp_super_sticky_topics)) {
                    $oldType = 'super';
                } else {
                    $oldType = 'unstick';
                }
                $newType = !empty($_POST['bbp_stick_topic']) ? $_POST['bbp_stick_topic'] : '';
                if (!empty($newType) && $oldType != $newType) {
                    $this->plugin->alerts->Trigger(8016, array(
                        'TopicName' => $post->post_title,
                        'OldType' => ($oldType == 'unstick') ? 'normal' : (($oldType == 'super') ? 'super sticky' : $oldType),
                        'NewType' => ($newType == 'unstick') ? 'normal' : (($newType == 'super') ? 'super sticky' : $newType),
                        $editorLink['name'] => $editorLink['value']
                    ));
                    $result = 1;
                }
                break;
        }
        return $result;
    }

    /**
     * Event post changed status.
     * @param stdClass $post the post
     * @return boolean $result
     */
    private function EventForumChangedStatus($post)
    {
        $result = 0;
        $editorLink = $this->GetEditorLink($post);
        switch ($post->post_type) {
            case 'forum':
                $bbp_status = get_post_meta($post->ID, '_bbp_status', true);
                $oldStatus = !empty($bbp_status) ? $bbp_status : 'open';
                $newStatus = !empty($_REQUEST['bbp_forum_status']) ? $_REQUEST['bbp_forum_status'] : '';
                if (!empty($newStatus) && $oldStatus != $newStatus) {
                    $this->plugin->alerts->Trigger(8001, array(
                        'ForumName' => $post->post_title,
                        'OldStatus' => $oldStatus,
                        'NewStatus' => $newStatus,
                        $editorLink['name'] => $editorLink['value']
                    ));
                    $result = 1;
                }
                break;
            case 'topic':
                $oldStatus = !empty($_REQUEST['original_post_status']) ? $_REQUEST['original_post_status'] : '';
                $newStatus = !empty($_REQUEST['post_status']) ? $_REQUEST['post_status'] : '';
                // In case of Ajax request Spam/Not spam
                if (isset($_GET['action']) && $_GET['action'] == 'bbp_toggle_topic_spam') {
                    $oldStatus = $post->post_status;
                    $newStatus = 'spam';
                    if (isset($_GET['post_status']) && $_GET['post_status'] == 'spam') {
                        $newStatus = 'publish';
                    }
                }
                // In case of Ajax request Close/Open
                if (isset($_GET['action']) && $_GET['action'] == 'bbp_toggle_topic_close') {
                    $oldStatus = $post->post_status;
                    $newStatus = 'closed';
                    if (isset($_GET['post_status']) && $_GET['post_status'] == 'closed') {
                        $newStatus = 'publish';
                    }
                }
                if (!empty($newStatus) && $oldStatus != $newStatus) {
                    $this->plugin->alerts->Trigger(8015, array(
                        'TopicName' => $post->post_title,
                        'OldStatus' => ($oldStatus == 'publish') ? 'open' : $oldStatus,
                        'NewStatus' => ($newStatus == 'publish') ? 'open' : $newStatus,
                        $editorLink['name'] => $editorLink['value']
                    ));
                    $result = 1;
                }
                break;
        }
        return $result;
    }

    /**
     * Event post changed (order, parent, URL).
     * @param stdClass $old_post the old post
     * @param stdClass $new_post the new post
     * @return boolean $result
     */
    private function EventForumChanged($old_post, $new_post)
    {
        $editorLink = $this->GetEditorLink($new_post);
        // Changed Order
        if ($old_post->menu_order != $new_post->menu_order) {
            $this->plugin->alerts->Trigger(8004, array(
                'ForumName' => $new_post->post_title,
                'OldOrder' => $old_post->menu_order,
                'NewOrder' => $new_post->menu_order,
                $editorLink['name'] => $editorLink['value']
            ));
            return 1;
        }
        // Changed Parent
        if ($old_post->post_parent != $new_post->post_parent) {
            switch ($old_post->post_type) {
                case 'forum':
                    $this->plugin->alerts->Trigger(8008, array(
                        'ForumName' => $new_post->post_title,
                        'OldParent' => $old_post->post_parent ? get_the_title($old_post->post_parent) : 'no parent',
                        'NewParent' => $new_post->post_parent ? get_the_title($new_post->post_parent) : 'no parent',
                        $editorLink['name'] => $editorLink['value']
                    ));
                    break;
                case 'topic':
                    $this->plugin->alerts->Trigger(8018, array(
                        'TopicName' => $new_post->post_title,
                        'OldForum' => $old_post->post_parent ? get_the_title($old_post->post_parent) : 'no parent',
                        'NewForum' => $new_post->post_parent ? get_the_title($new_post->post_parent) : 'no parent',
                        $editorLink['name'] => $editorLink['value']
                    ));
                    break;
            }
            return 1;
        }
        // Changed URL
        $oldLink = $this->_OldLink;
        $newLink = get_permalink($new_post->ID);
        if (!empty($oldLink) && $oldLink != $newLink) {
            switch ($old_post->post_type) {
                case 'forum':
                    $this->plugin->alerts->Trigger(8003, array(
                        'ForumName' => $new_post->post_title,
                        'OldUrl' => $oldLink,
                        'NewUrl' => $newLink,
                        $editorLink['name'] => $editorLink['value']
                    ));
                    break;
                case 'topic':
                    $this->plugin->alerts->Trigger(8017, array(
                        'TopicName' => $new_post->post_title,
                        'OldUrl' => $oldLink,
                        'NewUrl' => $newLink,
                        $editorLink['name'] => $editorLink['value']
                    ));
                    break;
            }
            return 1;
        }
        return 0;
    }

    /**
     * Trigger Event (Forum).
     * @param stdClass $post the post
     * @param integer $event event code
     */
    private function EventForumByCode($post, $event)
    {
        $editorLink = $this->GetEditorLink($post);
        $this->plugin->alerts->Trigger($event, array(
            'ForumID' => $post->ID,
            'ForumName' => $post->post_title,
            $editorLink['name'] => $editorLink['value']
        ));
    }

    /**
     * Trigger Event (Topic).
     * @param stdClass $post the post
     * @param integer $event event code
     */
    private function EventTopicByCode($post, $event)
    {
        $editorLink = $this->GetEditorLink($post);
        $this->plugin->alerts->Trigger($event, array(
            'TopicID' => $post->ID,
            'TopicName' => $post->post_title,
            $editorLink['name'] => $editorLink['value']
        ));
    }

    /**
     * Trigger of ajax events generated in the Topic Grid
     * @global mixed $_GET Get data
     */
    public function TriggerAjaxChange()
    {
        if (!empty($_GET['post_type']) && !empty($_GET['topic_id'])) {
            if ($_GET['post_type'] == 'topic') {
                $post = get_post($_GET['topic_id']);
                
                // Topic type
                if (isset($_GET['action']) && $_GET['action'] == 'bbp_toggle_topic_stick') {
                    if (!empty($post->post_parent)) {
                        $post_id = $post->post_parent;
                    } else {
                        $post_id = $_GET['topic_id'];
                    }
                    
                    $bbp_sticky_topics = maybe_unserialize(get_post_meta($post_id, '_bbp_sticky_topics', true));
                    $fn = $this->IsMultisite() ? 'get_site_option' : 'get_option';
                    $bbp_super_sticky_topics = maybe_unserialize($fn('_bbp_super_sticky_topics'));
                    if (!empty($bbp_sticky_topics) && in_array($_GET['topic_id'], $bbp_sticky_topics)) {
                        $oldType = 'sticky';
                    } elseif (!empty($bbp_super_sticky_topics) && in_array($_GET['topic_id'], $bbp_super_sticky_topics)) {
                        $oldType = 'super sticky';
                    } else {
                        $oldType = 'normal';
                    }

                    switch ($oldType) {
                        case 'sticky':
                        case 'super sticky':
                            $newType = 'normal';
                            break;
                        case 'normal':
                            if (isset($_GET['super']) && $_GET['super'] == 1) {
                                $newType = 'super sticky';
                            } else {
                                $newType = 'sticky';
                            }
                            break;
                    }
                    $editorLink = $this->GetEditorLink($post);

                    if (!empty($newType) && $oldType != $newType) {
                        $this->plugin->alerts->Trigger(8016, array(
                            'TopicName' => $post->post_title,
                            'OldType' => $oldType,
                            'NewType' => $newType,
                            $editorLink['name'] => $editorLink['value']
                        ));
                    }
                }
            }
        }
    }

    /**
     * Get editor link.
     * @param stdClass $post the post
     * @return array $aLink name and value link
     */
    private function GetEditorLink($post)
    {
        $name = 'EditorLink';
        switch ($post->post_type) {
            case 'forum':
                $name .= 'Forum' ;
                break;
            case 'topic':
                $name .= 'Topic' ;
                break;
        }
        $value = get_edit_post_link($post->ID);
        $aLink = array(
            'name' => $name,
            'value' => $value,
        );
        return $aLink;
    }
}
