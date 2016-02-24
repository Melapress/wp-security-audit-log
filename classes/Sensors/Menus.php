<?php

class WSAL_Sensors_Menus extends WSAL_AbstractSensor
{
    protected $_OldMenu = null;
    protected $_OldMenuTerms = array();
    protected $_OldMenuItems = array();
    protected $_OldMenuLocations = null;

    public function HookEvents()
    {
        add_action('wp_create_nav_menu', array($this, 'CreateMenu'), 10, 2);
        add_action('wp_delete_nav_menu', array($this, 'DeleteMenu'), 10, 1);
        add_action('wp_update_nav_menu', array($this, 'UpdateMenu'), 10, 2);
        add_action('admin_init', array($this, 'EventAdminInit'));
        // Customizer trigger
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
            $is_occurred_event = false;
            if (isset($_POST['menu-item-title']) && isset($_POST['menu-item-type'])) {
                $contentNamesNew = array_values($_POST['menu-item-title']);
                $contentTypesNew = array_values($_POST['menu-item-object']);

                if (isset($menu_data['menu-name'])) {
                    $addedNames = array_diff_assoc($contentNamesNew, $contentNamesOld);
                    $addedTypes = array_diff_assoc($contentTypesNew, $contentTypesOld);
                    // Add Items to the menu
                    if (count($addedNames) > 0 && count($addedTypes) > 0) {
                        $contentName = implode(",", $addedNames);
                        $contentType = implode(",", array_unique($addedTypes));
                        $is_occurred_event = true;
                        $this->EventAddItems($contentType, $contentName, $menu_data['menu-name']);
                    }

                    $removedNames = array_diff_assoc($contentNamesOld, $contentNamesNew);
                    $removedTypes = array_diff_assoc($contentTypesOld, $contentTypesNew);
                    // Remove items from the menu
                    if (count($removedNames) > 0 && count($removedTypes) > 0) {
                        $contentName = implode(",", $removedNames);
                        $contentType = implode(",", array_unique($removedTypes));
                        $is_occurred_event = true;
                        $this->EventRemoveItems($contentType, $contentName, $menu_data['menu-name']);
                    }

                    // Modified Items in the menu
                    if (!$is_occurred_event && count($addedNames) > 0) {
                        $contentType = array();
                        foreach (array_keys($addedNames) as $key) {
                            array_push($contentType, $contentTypesOld[$key]);
                        }
                        $contentType = implode(",", array_unique($contentType));
                        $contentName = implode(",", $addedNames);
                        $this->EventModifiedItems($contentType, $contentName, $menu_data['menu-name']);
                    }

                    // Enable/Disable menu setting
                    $fn = $this->IsMultisite() ? 'get_site_option' : 'get_option';
                    $nav_menu_options = maybe_unserialize($fn('nav_menu_options'));
                    $auto_add = null;
                    if (isset($nav_menu_options['auto_add'])) {
                        if (in_array($menu_id, $nav_menu_options['auto_add'])) {
                            if (empty($_POST['auto-add-pages'])) {
                                $auto_add = "Disabled";
                            }
                        } else {
                            if (isset($_POST['auto-add-pages'])) {
                                $auto_add = "Enabled";
                            }
                        }
                    } else {
                        if (isset($_POST['auto-add-pages'])) {
                            $auto_add = "Enabled";
                        }
                    }
                    // Alert 2082 Auto add pages
                    if (!empty($auto_add)) {
                        $this->EventMenuSetting($menu_data['menu-name'], $auto_add, "Auto add pages");
                    }
                    
                    $nav_menu_locations = get_nav_menu_locations();

                    $locationPrimary = null;
                    if (isset($this->_OldMenuLocations['primary']) && isset($nav_menu_locations['primary'])) {
                        if ($nav_menu_locations['primary'] == $menu_id && $this->_OldMenuLocations['primary'] != $nav_menu_locations['primary']) {
                            $locationPrimary = "Enabled";
                        }
                    } elseif (empty($this->_OldMenuLocations['primary']) && isset($nav_menu_locations['primary'])) {
                        if ($nav_menu_locations['primary'] == $menu_id) {
                            $locationPrimary = "Enabled";
                        }
                    } elseif (isset($this->_OldMenuLocations['primary']) && empty($nav_menu_locations['primary'])) {
                        if ($this->_OldMenuLocations['primary'] == $menu_id) {
                            $locationPrimary = "Disabled";
                        }
                    }
                    // Alert 2082 Primary menu
                    if (!empty($locationPrimary)) {
                        $this->EventMenuSetting($menu_data['menu-name'], $locationPrimary, "Location: primary menu");
                    }
                    
                    $locationSocial = null;
                    if (isset($this->_OldMenuLocations['social']) && isset($nav_menu_locations['social'])) {
                        if ($nav_menu_locations['social'] == $menu_id && $this->_OldMenuLocations['social'] != $nav_menu_locations['social']) {
                            $locationSocial = "Enabled";
                        }
                    } elseif (empty($this->_OldMenuLocations['social']) && isset($nav_menu_locations['social'])) {
                        if ($nav_menu_locations['social'] == $menu_id) {
                            $locationSocial = "Enabled";
                        }
                    } elseif (isset($this->_OldMenuLocations['social']) && empty($nav_menu_locations['social'])) {
                        if ($this->_OldMenuLocations['social'] == $menu_id) {
                            $locationSocial = "Disabled";
                        }
                    }
                    // Alert 2082 Social links menu
                    if (!empty($locationSocial)) {
                        $this->EventMenuSetting($menu_data['menu-name'], $locationSocial, "Location: social menu");
                    }
                }
            }
        }
    }
    
    public function EventAdminInit()
    {
        $is_nav_menu = basename($_SERVER['SCRIPT_NAME']) == 'nav-menus.php';
        if ($is_nav_menu) {
            if (isset($_GET['action']) && $_GET['action'] == 'delete') {
                if (isset($_GET['menu'])) {
                    $this->_OldMenu = wp_get_nav_menu_object($_GET['menu']);
                }
            }
            $this->_OldMenuLocations = get_nav_menu_locations();
        }
    }

    public function CustomizeInit()
    {
        $menus = wp_get_nav_menus();
        if (!empty($menus)) {
            foreach ($menus as $menu) {
                array_push($this->_OldMenuTerms, $menu->name);
                $items = wp_get_nav_menu_items($menu->term_id);
                if (!empty($items)) {
                    foreach ($items as $item) {
                        array_push($this->_OldMenuItems, array('item_id' => $item->ID, 'title' => $item->title, 'object' => $item->object, 'menu_name' => $menu->name));
                    }
                }
            }
        }
        $this->_OldMenuLocations = get_nav_menu_locations();
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
        if (isset($_POST['action']) && $_POST['action'] == 'customize_save') {
            if (isset($_POST['wp_customize'], $_POST['customized'])) {
                $customized = json_decode(wp_unslash($_POST['customized']), true);
                if (is_array($customized)) {
                    foreach ($customized as $key => $value) {
                        if (!empty($value['nav_menu_term_id'])) {
                            $is_occurred_event = false;
                            $menu = wp_get_nav_menu_object($value['nav_menu_term_id']);
                            $content_name = !empty($value['title']) ? $value['title'] : "no title";
                            if (!empty($this->_OldMenuItems)) {
                                foreach ($this->_OldMenuItems as $old_item) {
                                    $item_id = substr(trim($key, ']'), 14);
                                    // Modified Items in the menu
                                    if ($old_item['item_id'] == $item_id && $old_item['title'] != $content_name) {
                                        $is_occurred_event = true;
                                        $this->EventModifiedItems($value['type_label'], $content_name, $menu->name);
                                    }
                                }
                            }
                            // Add Items to the menu
                            if (!$is_occurred_event) {
                                $this->EventAddItems($value['type_label'], $content_name, $menu->name);
                            }
                        } else {
                            // Setting Auto add pages
                            if (!empty($value) && isset($value['auto_add'])) {
                                if ($value['auto_add']) {
                                    $this->EventMenuSetting($value['name'], 'Enabled', "Auto add pages");
                                } else {
                                    $this->EventMenuSetting($value['name'], 'Disabled', "Auto add pages");
                                }
                            }
                            // Setting Location
                            if (false !== strpos($key, 'nav_menu_locations[')) {
                                $loc = substr(trim($key, ']'), 19);
                                if (!empty($value)) {
                                    $menu = wp_get_nav_menu_object($value);
                                    $this->EventMenuSetting($menu->name, "Enabled", "Location: ".$loc." menu");
                                } else {
                                    if (!empty($this->_OldMenuLocations[$loc])) {
                                        $menu = wp_get_nav_menu_object($this->_OldMenuLocations[$loc]);
                                        $this->EventMenuSetting($menu->name, "Disabled", "Location: ".$loc." menu");
                                    }
                                }
                            }
                            // Remove items from the menu
                            if (false !== strpos($key, 'nav_menu_item[')) {
                                $item_id = substr(trim($key, ']'), 14);
                                if (!empty($this->_OldMenuItems)) {
                                    foreach ($this->_OldMenuItems as $old_item) {
                                        if ($old_item['item_id'] == $item_id) {
                                            $this->EventRemoveItems($old_item['object'], $old_item['title'], $old_item['menu_name']);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    private function EventAddItems($content_type, $content_name, $menu_name)
    {
        $this->plugin->alerts->Trigger(2079, array(
            'ContentType' => $content_type,
            'ContentName' => $content_name,
            'MenuName' => $menu_name
        ));
    }

    private function EventRemoveItems($content_type, $content_name, $menu_name)
    {
        $this->plugin->alerts->Trigger(2080, array(
            'ContentType' => $content_type,
            'ContentName' => $content_name,
            'MenuName' => $menu_name
        ));
    }

    private function EventMenuSetting($menu_name, $status, $menu_setting)
    {
        $this->plugin->alerts->Trigger(2082, array(
            'Status' => $status,
            'MenuSetting' => $menu_setting,
            'MenuName' => $menu_name
        ));
    }

    private function EventModifiedItems($content_type, $content_name, $menu_name)
    {
        $this->plugin->alerts->Trigger(2083, array(
            'ContentType' => $content_type,
            'ContentName' => $content_name,
            'MenuName' => $menu_name
        ));
    }
}
