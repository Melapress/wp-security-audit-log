<?php

class WSAL_Sensors_Menus extends WSAL_AbstractSensor
{
    protected $_OldMenu = null;
    protected $_OldMenuTerms = array();
    protected $_OldMenuItems = null;

    public function HookEvents()
    {
        add_action('wp_create_nav_menu', array($this, 'CreateMenu'), 10, 2);
        add_action('wp_delete_nav_menu', array($this, 'DeleteMenu'), 10, 1);
        add_action('wp_update_nav_menu', array($this, 'UpdateMenu'), 10, 2);
        add_action('admin_init', array($this, 'EventAdminInit'));
        // Customizer
        add_action('customize_register', array($this, 'CustomizeInit'));
        add_action('customize_save_after', array($this, 'CustomizeSave'));
    }
    
    public function CreateMenu($term_id, $menu_data)
    {
        $this->plugin->alerts->Trigger(2078, array(
            'MenuName' => $menu_data['menu-name']
        ));
    }
    
    public function DeleteMenu($term_id)
    {
        if ($this->_OldMenu) {
            $this->plugin->alerts->Trigger(2081, array(
                'MenuName' => $this->_OldMenu->name
            ));
        }
    }

    public function UpdateMenu($menu_id, $menu_data)
    {
        $menu = wp_get_nav_menu_object($menu_id);
        $items = wp_get_nav_menu_items($menu_id);

        if (isset($items)) {
            $contentNamesOld = array();
            $contentTypesOld = array();

            foreach ($items as $item) {
                array_push($contentNamesOld, $item->title);
                array_push($contentTypesOld, $item->object);
            }

            if (isset($_POST['menu-item-title']) && isset($_POST['menu-item-type'])) {
                $contentNamesNew = array_values($_POST['menu-item-title']);
                $contentTypesNew = array_values($_POST['menu-item-type']);

                $addedNames = array_diff_assoc($contentNamesNew, $contentNamesOld);
                $addedTypes = array_diff_assoc($contentTypesNew, $contentTypesOld);

                if (isset($menu_data['menu-name']) && count($addedNames) > 0 && count($addedTypes) > 0) {
                    $contentName = implode(",", $addedNames);
                    $contentType = implode(",", array_unique($addedTypes));

                    $this->plugin->alerts->Trigger(2079, array(
                        'ContentType' => $contentType,
                        'ContentName' => $contentName,
                        'MenuName' => $menu_data['menu-name']
                    ));
                }

                $removedNames = array_diff_assoc($contentNamesOld, $contentNamesNew);
                $removedTypes = array_diff_assoc($contentTypesOld, $contentTypesNew);

                if (isset($menu_data['menu-name']) && count($removedNames) > 0 && count($removedTypes) > 0) {
                    $contentName = implode(",", $removedNames);
                    $contentType = implode(",", array_unique($removedTypes));

                    $this->plugin->alerts->Trigger(2080, array(
                        'ContentType' => $contentType,
                        'ContentName' => $contentName,
                        'MenuName' => $menu_data['menu-name']
                    ));
                }
            }
        }
    }
    
    public function EventAdminInit()
    {
        $is_nav_menu = basename($_SERVER['SCRIPT_NAME']) == 'nav-menus.php';
        if ($is_nav_menu && isset($_GET['action']) && $_GET['action'] == 'delete') {
            if (isset($_GET['menu'])) {
                $this->_OldMenu = wp_get_nav_menu_object($_GET['menu']);
            }
        }
    }

    public function CustomizeInit()
    {
        $menus = wp_get_nav_menus();
        if (!empty($menus)) {
            foreach ($menus as $menu) {
                array_push($this->_OldMenuTerms, $menu->name);
            }
        }
    }

    public function CustomizeSave()
    {
        $updateMenu = array();
        $menus = wp_get_nav_menus();
        if (!empty($menus)) {
            foreach ($menus as $menu) {
                array_push($updateMenu, $menu->name);
            }
        }
        if (isset($updateMenu) && isset($this->_OldMenuTerms)) {
            $terms = array_diff($this->_OldMenuTerms, $updateMenu);
            if (isset($terms)) {
                foreach ($terms as $term) {
                    $this->plugin->alerts->Trigger(2081, array(
                        'MenuName' => $term
                    ));
                }
            }
        }
    }
}
