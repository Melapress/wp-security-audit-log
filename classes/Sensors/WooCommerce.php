<?php
/**
 * Support for WooCommerce Plugin
 */
class WSAL_Sensors_WooCommerce extends WSAL_AbstractSensor
{
    protected $_OldPost = null;
    protected $_OldLink = null;
    protected $_OldCats = null;
    protected $_OldData = null;
    protected $_OldStockStatus = null;

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
            $this->_OldLink = get_post_permalink($postID);
            $this->_OldCats = $this->GetProductCategories($this->_OldPost);
            $this->_OldData = $this->GetProductData($this->_OldPost);
            $this->_OldStockStatus = get_post_meta($postID, '_stock_status', true);
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
                // Change Short description, Text, URL, Product Data, Date, Visibility
                $changes = 0
                    + $this->CheckShortDescriptionChange($oldpost, $newpost)
                    + $this->CheckTextChange($oldpost, $newpost)
                    + $this->CheckProductDataChange($this->_OldData, $newpost)
                    + $this->CheckDateChange($oldpost, $newpost)
                    + $this->CheckVisibilityChange($oldpost)
                    + $this->CheckStatusChange($oldpost, $newpost)
                    + $this->CheckPriceChange($oldpost)
                    + $this->CheckSKUChange($oldpost)
                    + $this->CheckStockStatusChange($oldpost)
                    + $this->CheckStockQuantityChange($oldpost)
                    + $this->CheckTypeChange($oldpost)
                    + $this->CheckWeightChange($oldpost)
                ;

                if ($changes) {
                    // if one of the above changes happen
                    $this->CheckModifyChange($oldpost);
                }
            }
            if (!$changes) {
                // Change status
                $changes = $this->CheckPermalinkChange($this->_OldLink, get_post_permalink($post_ID), $newpost);
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
                        'ProductUrl' => get_post_permalink($new_post->ID),
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
        $oldCats = is_array($oldCats) ? implode(', ', $oldCats) : $oldCats;
        $newCats = is_array($newCats) ? implode(', ', $newCats) : $newCats;
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
        if (($oldLink && $newLink) && ($oldLink != $newLink)) {
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
            $oldData = is_array($oldData) ? implode(', ', $oldData) : $oldData;
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
     * Trigger events 9008
     */
    protected function CheckDateChange($oldpost, $newpost)
    {
        $from = strtotime($oldpost->post_date);
        $to = strtotime($newpost->post_date);
        if ($oldpost->post_status == 'draft') {
            return 0;
        }
        if ($from != $to) {
            $editorLink = $this->GetEditorLink($oldpost);
            $this->plugin->alerts->Trigger(9008, array(
                'ProductTitle' => $oldpost->post_title,
                'OldDate' => $oldpost->post_date,
                'NewDate' => $newpost->post_date,
                $editorLink['name'] => $editorLink['value']
            ));
            return 1;
        }
        return 0;
    }

    /**
     * Trigger events 9009
     */
    protected function CheckVisibilityChange($oldpost)
    {
        $oldVisibility = isset($_POST['hidden_post_visibility']) ? $_POST['hidden_post_visibility'] : null;
        $newVisibility = isset($_POST['visibility']) ? $_POST['visibility'] : null;
        
        if ($oldVisibility == 'password') {
            $oldVisibility = __('Password Protected', 'wp-security-audit-log');
        } else {
            $oldVisibility = ucfirst($oldVisibility);
        }
        
        if ($newVisibility == 'password') {
            $newVisibility = __('Password Protected', 'wp-security-audit-log');
        } else {
            $newVisibility = ucfirst($newVisibility);
        }
        
        if (($oldVisibility && $newVisibility) && ($oldVisibility != $newVisibility)) {
            $editorLink = $this->GetEditorLink($oldpost);
            $this->plugin->alerts->Trigger(9009, array(
                'ProductTitle' => $oldpost->post_title,
                'OldVisibility' => $oldVisibility,
                'NewVisibility' => $newVisibility,
                $editorLink['name'] => $editorLink['value']
            ));
            return 1;
        }
        return 0;
    }

    /**
     * Trigger events 9010, 9011
     */
    protected function CheckModifyChange($oldpost)
    {
        $editorLink = $this->GetEditorLink($oldpost);
        if ($oldpost->post_status == 'publish') {
            $this->plugin->alerts->Trigger(9010, array(
                'ProductTitle' => $oldpost->post_title,
                'ProductUrl' => get_post_permalink($oldpost->ID),
                $editorLink['name'] => $editorLink['value']
            ));
        } else if ($oldpost->post_status == 'draft') {
            $this->plugin->alerts->Trigger(9011, array(
                'ProductTitle' => $oldpost->post_title,
                $editorLink['name'] => $editorLink['value']
            ));
        }
    }

    /**
     * Moved to Trash 9012
     */
    public function EventTrashed($post_id)
    {
        $post = get_post($post_id);
        if ($this->CheckWooCommerce($post)) {
            $this->plugin->alerts->Trigger(9012, array(
                'ProductTitle' => $post->post_title,
                'ProductUrl' => get_post_permalink($post->ID)
            ));
        }
    }

    /**
     * Permanently deleted 9013
     */
    public function EventDeleted($post_id)
    {
        $post = get_post($post_id);
        if ($this->CheckWooCommerce($post)) {
            $this->plugin->alerts->Trigger(9013, array(
                'ProductTitle' => $post->post_title
            ));
        }
    }

    /**
     * Restored from Trash 9014
     */
    public function EventUntrashed($post_id)
    {
        $post = get_post($post_id);
        if ($this->CheckWooCommerce($post)) {
            $editorLink = $this->GetEditorLink($post);
            $this->plugin->alerts->Trigger(9014, array(
                'ProductTitle' => $post->post_title,
                $editorLink['name'] => $editorLink['value']
            ));
        }
    }

    /**
     * Trigger events 9015
     */
    protected function CheckStatusChange($oldpost, $newpost)
    {
        if ($oldpost->post_status != $newpost->post_status) {
            $editorLink = $this->GetEditorLink($oldpost);
            $this->plugin->alerts->Trigger(9015, array(
                'ProductTitle' => $oldpost->post_title,
                'OldStatus' => $oldpost->post_status,
                'NewStatus' => $newpost->post_status,
                $editorLink['name'] => $editorLink['value']
            ));
            return 1;
        }
        return 0;
    }

    /**
     * Trigger events 9016
     */
    protected function CheckPriceChange($oldpost)
    {
        $result = 0;
        $oldPrice = get_post_meta($oldpost->ID, '_regular_price', true);
        $oldSalePrice = get_post_meta($oldpost->ID, '_sale_price', true);
        $newPrice = isset($_POST['_regular_price']) ? $_POST['_regular_price'] : null;
        $newSalePrice = isset($_POST['_sale_price']) ? $_POST['_sale_price'] : null;

        if (($newPrice) && ($oldPrice != $newPrice)) {
            $editorLink = $this->GetEditorLink($oldpost);
            $this->plugin->alerts->Trigger(9016, array(
                'ProductTitle' => $oldpost->post_title,
                'PriceType' => 'Regular price',
                'OldPrice' => (!empty($oldPrice) ? $oldPrice : 0),
                'NewPrice' => $newPrice,
                $editorLink['name'] => $editorLink['value']
            ));
            $result = 1;
        }
        if (($newSalePrice) && ($oldSalePrice != $newSalePrice)) {
            $editorLink = $this->GetEditorLink($oldpost);
            $this->plugin->alerts->Trigger(9016, array(
                'ProductTitle' => $oldpost->post_title,
                'PriceType' => 'Sale price',
                'OldPrice' => (!empty($oldSalePrice) ? $oldSalePrice : 0),
                'NewPrice' => $newSalePrice,
                $editorLink['name'] => $editorLink['value']
            ));
            $result = 1;
        }
        return $result;
    }

    /**
     * Trigger events 9017
     */
    protected function CheckSKUChange($oldpost)
    {
        $oldSku = get_post_meta($oldpost->ID, '_sku', true);
        $newSku = isset($_POST['_sku']) ? $_POST['_sku'] : null;

        if (($newSku) && ($oldSku != $newSku)) {
            $editorLink = $this->GetEditorLink($oldpost);
            $this->plugin->alerts->Trigger(9017, array(
                'ProductTitle' => $oldpost->post_title,
                'OldSku' => (!empty($oldSku) ? $oldSku : 0),
                'NewSku' => $newSku,
                $editorLink['name'] => $editorLink['value']
            ));
            return 1;
        }
        return 0;
    }

    /**
     * Trigger events 9018
     */
    protected function CheckStockStatusChange($oldpost)
    {
        $oldStatus = $this->_OldStockStatus;
        $newStatus = isset($_POST['_stock_status']) ? $_POST['_stock_status'] : null;

        if (($oldStatus && $newStatus) && ($oldStatus != $newStatus)) {
            $editorLink = $this->GetEditorLink($oldpost);
            $this->plugin->alerts->Trigger(9018, array(
                'ProductTitle' => $oldpost->post_title,
                'OldStatus' => $this->GetStockStatusName($oldStatus),
                'NewStatus' => $this->GetStockStatusName($newStatus),
                $editorLink['name'] => $editorLink['value']
            ));
            return 1;
        }
        return 0;
    }

    /**
     * Trigger events 9019
     */
    protected function CheckStockQuantityChange($oldpost)
    {
        $oldValue  = get_post_meta($oldpost->ID, '_stock', true);
        $newValue = isset($_POST['_stock']) ? $_POST['_stock'] : null;

        if (($newValue) && ($oldValue != $newValue)) {
            $editorLink = $this->GetEditorLink($oldpost);
            $this->plugin->alerts->Trigger(9019, array(
                'ProductTitle' => $oldpost->post_title,
                'OldValue' => (!empty($oldValue) ? $oldValue : 0),
                'NewValue' => $newValue,
                $editorLink['name'] => $editorLink['value']
            ));
            return 1;
        }
        return 0;
    }

    /**
     * Trigger events 9020
     */
    protected function CheckTypeChange($oldpost)
    {
        $result = 0;
        $oldVirtual  = get_post_meta($oldpost->ID, '_virtual', true);
        $newVirtual = isset($_POST['_virtual']) ? 'yes' : 'no';
        $oldDownloadable  = get_post_meta($oldpost->ID, '_downloadable', true);
        $newDownloadable = isset($_POST['_downloadable']) ? 'yes' : 'no';

        if (($oldVirtual && $newVirtual) && ($oldVirtual != $newVirtual)) {
            $editorLink = $this->GetEditorLink($oldpost);
            $this->plugin->alerts->Trigger(9020, array(
                'ProductTitle' => $oldpost->post_title,
                'Type' => ($newVirtual == 'no') ? 'Non Virtual' : 'Virtual',
                $editorLink['name'] => $editorLink['value']
            ));
            $result = 1;
        }
        if (($oldDownloadable && $newDownloadable) && ($oldDownloadable != $newDownloadable)) {
            $editorLink = $this->GetEditorLink($oldpost);
            $this->plugin->alerts->Trigger(9020, array(
                'ProductTitle' => $oldpost->post_title,
                'Type' => ($newDownloadable == 'no') ? 'Non Downloadable' : 'Downloadable',
                $editorLink['name'] => $editorLink['value']
            ));
            $result = 1;
        }
        return $result;
    }

    /**
     * Trigger events 9021
     */
    protected function CheckWeightChange($oldpost)
    {
        $oldWeight  = get_post_meta($oldpost->ID, '_weight', true);
        $newWeight = isset($_POST['_weight']) ? $_POST['_weight'] : null;

        if (($newWeight) && ($oldWeight != $newWeight)) {
            $editorLink = $this->GetEditorLink($oldpost);
            $this->plugin->alerts->Trigger(9021, array(
                'ProductTitle' => $oldpost->post_title,
                'OldWeight' => (!empty($oldWeight) ? $oldWeight : 0),
                'NewWeight' => $newWeight,
                $editorLink['name'] => $editorLink['value']
            ));
            return 1;
        }
        return 0;
    }

    private function GetStockStatusName($slug)
    {
        if ($slug == 'instock') {
            return __('In stock', 'wp-security-audit-log');
        } else if ($slug == 'outofstock') {
            return __('Out of stock', 'wp-security-audit-log');
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
