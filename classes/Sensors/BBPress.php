<?php
/**
 * Support for BBPress Forum Plugin
 */
class WSAL_Sensors_BBPress extends WSAL_AbstractSensor
{

    public function HookEvents()
    {
        add_action('post_updated', array($this, 'CheckForumChange'), 10, 3);
        add_action('delete_post', array($this, 'EventForumDeleted'), 10, 1);
        add_action('wp_trash_post', array($this, 'EventForumTrashed'), 10, 1);
        add_action('untrash_post', array($this, 'EventForumUntrashed'));
    }

    public function CheckForumChange($post_ID, $newpost, $oldpost)
    {
        if ($this->CheckBBPress($oldpost)) {
            // Change Visibility
            $changes = 0 + $this->EventForumChangedVisibility($oldpost);
            // Change Type
            if (!$changes) {
                $changes = $this->EventForumChangedType($oldpost);
            }
            // Change Order, Link or Parent
            if (!$changes) {
                $this->EventForumChanged($oldpost, $newpost);
            }
            // Creation and change status
            if (!$changes) {
                $changes = $this->EventForumCreation($oldpost, $newpost);
            }
        }
    }

    public function EventForumDeleted($post_id)
    {
        $post = get_post($post_id);
        if ($this->CheckBBPress($post)) {
            $this->EventBBPressByCode($post, 8006);
        }
    }
    
    public function EventForumTrashed($post_id)
    {
        $post = get_post($post_id);
        if ($this->CheckBBPress($post)) {
            $this->EventBBPressByCode($post, 8005);
        }
    }
    
    public function EventForumUntrashed($post_id)
    {
        $post = get_post($post_id);
        if ($this->CheckBBPress($post)) {
            $this->EventBBPressByCode($post, 8007);
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

    private function EventForumChangedVisibility($post)
    {
        error_log(print_r($_REQUEST, true));
        $oldVisibility = !empty($_REQUEST['visibility']) ? $_REQUEST['visibility'] : '';
        $newVisibility = !empty($_REQUEST['bbp_forum_visibility']) ? $_REQUEST['bbp_forum_visibility'] : '';
        $newVisibility = ($newVisibility == 'publish') ? 'public' : $newVisibility;

        if (!empty($newVisibility) && $oldVisibility != 'auto-draft' && $oldVisibility != $newVisibility) {
            $this->plugin->alerts->Trigger(8002, array(
                'ForumName' => $post->post_title,
                'OldVisibility' => $oldVisibility,
                'NewVisibility' => $newVisibility
            ));
            return 1;
        }
        return 0;
    }

    private function EventForumChangedType($post)
    {
        $bbp_forum_type = get_post_meta($post->ID, '_bbp_forum_type', true);
        $oldType = !empty($bbp_forum_type) ? $bbp_forum_type : 'forum';
        $newType = !empty($_REQUEST['bbp_forum_type']) ? $_REQUEST['bbp_forum_type'] : '';
        if (!empty($newType) && $oldType != $newType) {
            $this->plugin->alerts->Trigger(8011, array(
                'ForumName' => $post->post_title,
                'OldType' => $oldType,
                'NewType' => $newType
            ));
            return 1;
        }
        return 0;
    }

    private function EventForumChanged($old_post, $new_post)
    {
        $oldLink = get_permalink($old_post->ID);
        $newLink = get_permalink($new_post->ID);
        if ($oldLink != $newLink) {
            $this->plugin->alerts->Trigger(8003, array(
                'ForumName' => $new_post->post_title,
                'OldUrl' => $oldLink,
                'NewUrl' => $newLink
            ));
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
            $this->plugin->alerts->Trigger(8008, array(
                'ForumName' => $new_post->post_title,
                'OldParent' => $old_post->post_parent ? get_the_title($old_post->post_parent) : 'no parent',
                'NewParent' => $new_post->post_parent ? get_the_title($new_post->post_parent) : 'no parent'
            ));
            return 1;
        }
        return 0;
    }
    private function EventForumCreation($old_post, $new_post)
    {
        $result = 0;
        if ($old_post->post_status == 'draft') {
            if ($new_post->post_status == 'publish') {
                $this->plugin->alerts->Trigger(8000, array(
                    'ForumName' => $new_post->post_title,
                    'ForumURL' => get_permalink($new_post->ID)
                ));
                $result = 1;
            }
        } else {
            $bbp_status = get_post_meta($old_post->ID, '_bbp_status', true);
            $oldStatus = !empty($bbp_status) ? $bbp_status : 'open';
            $newStatus = !empty($_REQUEST['bbp_forum_status']) ? $_REQUEST['bbp_forum_status'] : '';
            if (!empty($newStatus) && $oldStatus != $newStatus) {
                /* To do: check status */
                $this->plugin->alerts->Trigger(8001, array(
                    'ForumName' => $new_post->post_title,
                    'OldStatus' => $oldStatus,
                    'NewStatus' => $newStatus
                ));
                $result = 1;
            }
        }
        return $result;
    }

    private function EventBBPressByCode($post, $event)
    {
        $this->plugin->alerts->Trigger($event, array(
            'ForumID' => $post->ID,
            'ForumName' => $post->post_title
        ));
    }
}
