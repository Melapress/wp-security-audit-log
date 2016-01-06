<?php
/**
 * Support for BBPress Forum Plugin
 */
class WSAL_Sensors_BBPress extends WSAL_AbstractSensor
{
    protected $_OldPost = null;
    protected $_OldLink = null;

    public function HookEvents()
    {
        if (current_user_can("edit_posts")) {
            add_action('admin_init', array($this, 'RetrieveOldData'));
        }
        add_action('post_updated', array($this, 'CheckForumChange'), 10, 3);
        add_action('delete_post', array($this, 'EventForumDeleted'), 10, 1);
        add_action('wp_trash_post', array($this, 'EventForumTrashed'), 10, 1);
        add_action('untrash_post', array($this, 'EventForumUntrashed'));
    }

    public function RetrieveOldData()
    {
        if (isset($_POST) && isset($_POST['post_ID'])
            && !(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            && !(isset($_POST['action']) && $_POST['action'] == 'autosave')
        ) {
            $postID = intval($_POST['post_ID']);
            $this->_OldPost = get_post($postID);
            $this->_OldLink = get_permalink($postID);
        }
    }

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
            // Change Order, Link or Parent
            if (!$changes) {
                $changes = $this->EventForumChanged($oldpost, $newpost);
            }
        }
    }

    /**
     * Permanently deleted
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
     * Moved to Trash
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
     * Restored from Trash
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

    private function EventForumCreation($old_post, $new_post)
    {
        $original = isset($_POST['original_post_status']) ? $_POST['original_post_status'] : '';
        if ($old_post->post_status == 'draft' || $original == 'auto-draft') {
            if ($new_post->post_status == 'publish') {
                switch ($old_post->post_type) {
                    case 'forum':
                        $this->plugin->alerts->Trigger(8000, array(
                            'ForumName' => $new_post->post_title,
                            'ForumURL' => get_permalink($new_post->ID)
                        ));
                        break;
                    case 'topic':
                        $this->plugin->alerts->Trigger(8014, array(
                            'TopicName' => $new_post->post_title,
                            'TopicURL' => get_permalink($new_post->ID)
                        ));
                        break;
                }
                return 1;
            }
        }
        return 0;
    }

    private function EventForumChangedVisibility($post)
    {
        $result = 0;
        switch ($post->post_type) {
            case 'forum':
                $oldVisibility = !empty($_REQUEST['visibility']) ? $_REQUEST['visibility'] : '';
                $newVisibility = !empty($_REQUEST['bbp_forum_visibility']) ? $_REQUEST['bbp_forum_visibility'] : '';
                $newVisibility = ($newVisibility == 'publish') ? 'public' : $newVisibility;

                if (!empty($newVisibility) && $oldVisibility != 'auto-draft' && $oldVisibility != $newVisibility) {
                    $this->plugin->alerts->Trigger(8002, array(
                        'ForumName' => $post->post_title,
                        'OldVisibility' => $oldVisibility,
                        'NewVisibility' => $newVisibility
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
                        'NewVisibility' => $newVisibility
                    ));
                    $result = 1;
                }
                break;
        }
        return $result;
    }

    private function EventForumChangedType($post)
    {
        $result = 0;
        switch ($post->post_type) {
            case 'forum':
                $bbp_forum_type = get_post_meta($post->ID, '_bbp_forum_type', true);
                $oldType = !empty($bbp_forum_type) ? $bbp_forum_type : 'forum';
                $newType = !empty($_POST['bbp_forum_type']) ? $_POST['bbp_forum_type'] : '';
                if (!empty($newType) && $oldType != $newType) {
                    $this->plugin->alerts->Trigger(8011, array(
                        'ForumName' => $post->post_title,
                        'OldType' => $oldType,
                        'NewType' => $newType
                    ));
                    $result = 1;
                }
                break;
            case 'topic':
                $bbp_sticky_topics = maybe_unserialize(get_post_meta($post->ID, '_bbp_sticky_topics', true));
                $bbp_super_sticky_topics = maybe_unserialize(get_option('_bbp_super_sticky_topics'));
                if (!empty($bbp_sticky_topics) && in_array($post->ID, $bbp_sticky_topics)) {
                    $oldType = 'stick';
                } elseif (!empty($bbp_super_sticky_topics) && in_array($post->ID, $bbp_super_sticky_topics)) {
                    $oldType = 'super';
                } else {
                    $oldType = 'unstick';
                }
                $newType = !empty($_POST['bbp_stick_topic']) ? $_POST['bbp_stick_topic'] : '';
               
                if (!empty($newType) && $oldType != $newType) {
                    $this->plugin->alerts->Trigger(8016, array(
                        'TopicName' => $post->post_title,
                        'OldType' => ($oldType == 'unstick') ? 'normal' : ($oldType == 'super') ? 'super sticky' : $oldType,
                        'NewType' => ($newType == 'unstick') ? 'normal' : ($newType == 'super') ? 'super sticky' : $newType
                    ));
                    $result = 1;
                }
                break;
        }
        return $result;
    }

    private function EventForumChangedStatus($post)
    {
        $result = 0;
        switch ($post->post_type) {
            case 'forum':
                $bbp_status = get_post_meta($post->ID, '_bbp_status', true);
                $oldStatus = !empty($bbp_status) ? $bbp_status : 'open';
                $newStatus = !empty($_REQUEST['bbp_forum_status']) ? $_REQUEST['bbp_forum_status'] : '';
                if (!empty($newStatus) && $oldStatus != $newStatus) {
                    $this->plugin->alerts->Trigger(8001, array(
                        'ForumName' => $post->post_title,
                        'OldStatus' => $oldStatus,
                        'NewStatus' => $newStatus
                    ));
                    $result = 1;
                }
                break;
            case 'topic':
                $oldStatus = !empty($_REQUEST['original_post_status']) ? $_REQUEST['original_post_status'] : '';
                $newStatus = !empty($_REQUEST['post_status']) ? $_REQUEST['post_status'] : '';
                if (!empty($newStatus) && $oldStatus != $newStatus) {
                    $this->plugin->alerts->Trigger(8015, array(
                        'TopicName' => $post->post_title,
                        'OldStatus' => ($oldStatus == 'publish') ? 'open' : $oldStatus,
                        'NewStatus' => ($newStatus == 'publish') ? 'open' : $newStatus
                    ));
                    $result = 1;
                }
                break;
        }
        return $result;
    }

    private function EventForumChanged($old_post, $new_post)
    {
        $oldLink = $this->_OldLink;
        $newLink = get_permalink($new_post->ID);
        if (!empty($oldLink) && $oldLink != $newLink) {
            switch ($old_post->post_type) {
                case 'forum':
                    $this->plugin->alerts->Trigger(8003, array(
                        'ForumName' => $new_post->post_title,
                        'OldUrl' => $oldLink,
                        'NewUrl' => $newLink
                    ));
                    break;
                case 'topic':
                    $this->plugin->alerts->Trigger(8017, array(
                        'TopicName' => $new_post->post_title,
                        'OldUrl' => $oldLink,
                        'NewUrl' => $newLink
                    ));
                    break;
            }
            return 1;
        }
        
        if ($old_post->menu_order != $new_post->menu_order) {
            $this->plugin->alerts->Trigger(8004, array(
                'ForumName' => $new_post->post_title,
                'OldOrder' => $old_post->menu_order,
                'NewOrder' => $new_post->menu_order
            ));
            return 1;
        }

        if ($old_post->post_parent != $new_post->post_parent) {
            switch ($old_post->post_type) {
                case 'forum':
                    $this->plugin->alerts->Trigger(8008, array(
                        'ForumName' => $new_post->post_title,
                        'OldParent' => $old_post->post_parent ? get_the_title($old_post->post_parent) : 'no parent',
                        'NewParent' => $new_post->post_parent ? get_the_title($new_post->post_parent) : 'no parent'
                    ));
                    break;
                case 'topic':
                    $this->plugin->alerts->Trigger(8018, array(
                        'TopicName' => $new_post->post_title,
                        'OldForum' => $old_post->post_parent ? get_the_title($old_post->post_parent) : 'no parent',
                        'NewForum' => $new_post->post_parent ? get_the_title($new_post->post_parent) : 'no parent'
                    ));
                    break;
            }
            return 1;
        }
        return 0;
    }

    private function EventForumByCode($post, $event)
    {
        $this->plugin->alerts->Trigger($event, array(
            'ForumID' => $post->ID,
            'ForumName' => $post->post_title
        ));
    }

    private function EventTopicByCode($post, $event)
    {
        $this->plugin->alerts->Trigger($event, array(
            'TopicID' => $post->ID,
            'TopicName' => $post->post_title
        ));
    }
}
