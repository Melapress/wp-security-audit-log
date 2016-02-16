<?php

class WSAL_Sensors_Menus extends WSAL_AbstractSensor
{
    protected $_OldMenu = null;

    public function HookEvents()
    {
        add_action('wp_create_nav_menu', array($this, 'CreateMenu'), 10, 2);
        add_action('wp_delete_nav_menu', array($this, 'DeleteMenu'), 10, 1);
        add_action('admin_init', array($this, 'EventAdminInit'));
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
    
    public function EventAdminInit()
    {
        $is_nav_menu = basename($_SERVER['SCRIPT_NAME']) == 'nav-menus.php';
        if ($is_nav_menu && isset($_GET['action']) && $_GET['action'] == 'delete') {
            if (isset($_GET['menu'])) {
                $this->_OldMenu = wp_get_nav_menu_object($_GET['menu']);
            }
        }
    }
}
