<?php
/**
 * Support for BBPress forum
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
            $changes = 0 + $this->EventForumCreation($oldpost, $newpost);
            if (!$changes) {
                $oldVisibility = isset($_POST['original_post_status']) ? $_POST['original_post_status'] : '';
                $newVisibility = isset($_POST['visibility']) ? $_POST['visibility'] : '';

                if ($oldVisibility != $newVisibility) {
                    $this->EventForumChangedVisibility($oldpost, $newpost, $oldVisibility, $newVisibility);
                } else {
                    $this->EventForumChanged($oldpost, $newpost);
                }
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

    private function EventForumChangedVisibility($old_post, $new_post, $oldVisibility = null, $newVisibility = null)
    {
        if ($old_post->post_password) {
            $oldVisibility = __('Password Protected', 'wp-security-audit-log');
        }
        if ($new_post->post_password) {
            $newVisibility = __('Password Protected', 'wp-security-audit-log');
        }

        if ($oldVisibility != $newVisibility) {
            $this->plugin->alerts->Trigger(8002, array(
                'ForumName' => $new_post->post_title,
                'OldVisibility' => $oldVisibility,
                'NewVisibility' => $newVisibility
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
        } elseif ($new_post->post_status == 'publish') {
            if ($old_post->post_content != $new_post->post_content) {
                /* To do: check status */
                $this->plugin->alerts->Trigger(8001, array(
                    'ForumName' => $new_post->post_title,
                    'OldStatus' => $old_post->post_status,
                    'NewStatus' => $new_post->post_status
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
