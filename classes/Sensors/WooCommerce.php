<?php
/**
 * Support for _WooCommerce Plugin
 */
class WSAL_Sensors_WooCommerce extends WSAL_AbstractSensor
{
    protected $_OldPost = null;
    protected $_OldLink = null;
    protected $_OldCats = null;
    protected $_OldData = null;

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
        do_action('get_the_terms', array($this, 'EventTest'), 10, 2);
    }

    public function EventTest($first, $second = null)
    {
        error_log(print_r($first, true));
        error_log(print_r($second, true));
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
            $this->_OldCats = $this->GetProductCategories($this->_OldPost);
            $this->_OldData = $this->GetProductData($this->_OldPost);
        }
    }

    public function EventChanged($post_ID, $newpost, $oldpost)
    {
        if ($this->CheckWooCommerce($oldpost)) {
            $changes = 0 + $this->EventCreation($oldpost, $newpost);
            if (!$changes) {
                // Change Categories
                $changes = $this->CheckCategoriesChange($this->_OldCats, $this->GetProductCategories($newpost), $newpost);
            }
            if (!$changes) {
                // Change short description AND text
                $changes = 0
                    + $this->CheckShortDescriptionChange($oldpost, $newpost)
                    + $this->CheckTextChange($oldpost, $newpost)
                    + $this->CheckPermalinkChange($this->_OldLink, get_permalink($post_ID), $newpost);
                    + $this->CheckProductDataChange($this->_OldData, $newpost);
                ;
            }
            if (!$changes) {
                // Change status
                //$changes = $this->EventChangedStatus($oldpost);
            }
            if (!$changes) {
                // Change Order, Parent or URL
                //$changes = $this->EventChanged($oldpost, $newpost);
            }
        }
    }

    /**
     * Trigger events 9000, 9001
     */
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

    /**
     * Trigger events 9002
     */
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

    /**
     * Trigger events 9003
     */
    protected function CheckCategoriesChange($oldCats, $newCats, $post)
    {
        $oldCats = implode(', ', $oldCats);
        $newCats = implode(', ', $newCats);
        if ($oldCats != $newCats) {
            $editorLink = $this->GetEditorLink($post);
            $this->plugin->alerts->Trigger(9003, array(
                'ProductTitle' => $post->post_title,
                'OldCategories' => $oldCats ? $oldCats : 'no categories',
                'NewCategories' => $newCats ? $newCats : 'no categories',
                $editorLink['name'] => $editorLink['value']
            ));
            return 1;
        }
        return 0;
    }

    /**
     * Trigger events 9004
     */
    protected function CheckShortDescriptionChange($oldpost, $newpost)
    {
        if ($oldpost->post_excerpt != $newpost->post_excerpt) {
            $editorLink = $this->GetEditorLink($oldpost);
            $this->plugin->alerts->Trigger(9004, array(
                'ProductTitle' => $oldpost->post_title,
                $editorLink['name'] => $editorLink['value']
            ));
            return 1;
        }
        return 0;
    }

    /**
     * Trigger events 9005
     */
    protected function CheckTextChange($oldpost, $newpost)
    {
        if ($oldpost->post_content != $newpost->post_content) {
            $editorLink = $this->GetEditorLink($oldpost);
            $this->plugin->alerts->Trigger(9005, array(
                'ProductTitle' => $oldpost->post_title,
                $editorLink['name'] => $editorLink['value']
            ));
            return 1;
        }
        return 0;
    }

    /**
     * Trigger events 9006
     */
    protected function CheckPermalinkChange($oldLink, $newLink, $post)
    {
        if ($oldLink != $newLink) {
            $editorLink = $this->GetEditorLink($post);
            $this->plugin->alerts->Trigger(9006, array(
                'ProductTitle' => $post->post_title,
                'OldUrl' => $oldLink,
                'NewUrl' => $newLink,
                $editorLink['name'] => $editorLink['value']
            ));
            return 1;
        }
        return 0;
    }

    /**
     * Trigger events 9007
     */
    protected function CheckProductDataChange($oldData, $post)
    {
        if (isset($_POST['product-type'])) {
            $oldData = implode(', ', $oldData);
            $newData = $_POST['product-type'];
            if ($oldData != $newData) {
                $editorLink = $this->GetEditorLink($post);
                $this->plugin->alerts->Trigger(9007, array(
                    'ProductTitle' => $post->post_title,
                    $editorLink['name'] => $editorLink['value']
                ));
                return 1;
            }
        }
        return 0;
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

    protected function GetProductCategories($post)
    {
        return wp_get_post_terms($post->ID, 'product_cat', array("fields" => "names"));
    }

    protected function GetProductData($post)
    {
        return wp_get_post_terms($post->ID, 'product_type', array("fields" => "names"));
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
