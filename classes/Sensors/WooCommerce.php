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

        add_action('create_product_cat', array($this, 'EventCategoryCreation'), 10, 1);
        // add_action('edit_product_cat', array($this, 'EventCategoryChanged'), 10, 1);
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
            $changes = 0 + $this->EventCreation($oldpost, $newpost);
            // Change Visibility
            if (!$changes) {
                //$changes = $this->EventChangedVisibility($oldpost);
            }
            // Change Type
            if (!$changes) {
                //$changes = $this->EventChangedType($oldpost);
            }
            // Change status
            if (!$changes) {
                //$changes = $this->EventChangedStatus($oldpost);
            }
            // Change Order, Parent or URL
            if (!$changes) {
                //$changes = $this->EventChanged($oldpost, $newpost);
            }
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

    private function EventCreation($old_post, $new_post)
    {
        $original = isset($_POST['original_post_status']) ? $_POST['original_post_status'] : '';
        if ($old_post->post_status == 'draft' || $original == 'auto-draft') {
            if ($old_post->post_type == 'product') {
                $editorLink = $this->GetEditorLink($new_post);
                if ($new_post->post_status == 'draft') {
                    $this->plugin->alerts->Trigger(9000, array(
                        'ProductTitle' => $new_post->post_title,
                        $editorLink['name'] => $editorLink['value']
                    ));
                    return 1;
                } else if ($new_post->post_status == 'publish') {
                    $this->plugin->alerts->Trigger(9001, array(
                        'ProductTitle' => $new_post->post_title,
                        'ProductUrl' => get_permalink($new_post->ID),
                        $editorLink['name'] => $editorLink['value']
                    ));
                    return 1;
                }
            }
        }
        return 0;
    }

    public function EventCategoryCreation($term_id = null)
    {
        $term = get_term($term_id);
        if (!empty($term)) {
            $this->plugin->alerts->Trigger(9002, array(
                'CategoryName' => $term->name,
                'Slug' => $term->slug
            ));
        }
    }

    /**
     * Not implemented
     */
    public function EventCategoryChanged($term_id = null)
    {
        $old_term = get_term($term_id);
        if (isset($_POST['taxonomy'])) {
            // new $term in $_POST
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
