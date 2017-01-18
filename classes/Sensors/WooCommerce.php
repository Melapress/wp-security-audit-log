<?php
/**
 * Support for _WooCommerce Plugin
 */
class WSAL_Sensors_WooCommerce extends WSAL_AbstractSensor
{
    protected $_OldPost = null;
    protected $_OldLink = null;

    public function HookEvents()
    {
        if (current_user_can("edit_posts")) {
            add_action('admin_init', array($this, 'EventAdminInit'));
        }
        add_action('post_updated', array($this, 'EventChanged'), 10, 3);
        add_action('delete_post', array($this, 'EventDeleted'), 10, 1);
        add_action('wp_trash_post', array($this, 'EventTrashed'), 10, 1);
        add_action('untrash_post', array($this, 'EventUntrashed'));
    }

    public function EventAdminInit()
    {
        // load old data, if applicable
        $this->RetrieveOldData();
    }

    protected function RetrieveOldData()
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

    public function EventChanged($post_ID, $newpost, $oldpost)
    {
        if ($this->CheckWooCommerce($oldpost)) {
            error_log("EventChanged");
            error_log(print_r($newpost, true));
            error_log(print_r($oldpost, true));
        }
    }

    /**
     * Permanently deleted
     */
    public function EventDeleted($post_id)
    {
        $post = get_post($post_id);
        if ($this->CheckWooCommerce($post)) {
            error_log("EventDeleted");
        }
    }
    
    /**
     * Moved to Trash
     */
    public function EventTrashed($post_id)
    {
        $post = get_post($post_id);
        if ($this->CheckWooCommerce($post)) {
            error_log("EventTrashed");
        }
    }

    /**
     * Restored from Trash
     */
    public function EventUntrashed($post_id)
    {
        $post = get_post($post_id);
        if ($this->CheckWooCommerce($post)) {
            error_log("EventUnTrashed");
        }
    }

    private function CheckWooCommerce($post)
    {
        switch ($post->post_type) {
            case 'product':
                return true;
            default:
                return false;
        }
    }

    private function GetEditorLink($post)
    {
        $name = 'EditorLinkProduct';
        $value = get_edit_post_link($post->ID);
        $aLink = array(
            'name' => $name,
            'value' => $value,
        );
        return $aLink;
    }
}
