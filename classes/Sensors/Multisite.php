<?php
/**
 * @package Wsal
 * @subpackage Sensors
 * Multisite sensor.
 *
 * 4010 Existing user added to a site
 * 4011 User removed from site
 * 4012 New network user created
 * 7000 New site added on the network
 * 7001 Existing site archived
 * 7002 Archived site has been unarchived
 * 7003 Deactivated site has been activated
 * 7004 Site has been deactivated
 * 7005 Existing site deleted from network
 * 5008 Activated theme on network
 * 5009 Deactivated theme from network
 */
class WSAL_Sensors_Multisite extends WSAL_AbstractSensor
{
    protected $old_allowedthemes = null;

    /**
     * Listening to events using WP hooks.
     */
    public function HookEvents()
    {
        if ($this->plugin->IsMultisite()) {
            add_action('admin_init', array($this, 'EventAdminInit'));
            if (current_user_can('switch_themes')) {
                add_action('shutdown', array($this, 'EventAdminShutdown'));
            }
            add_action('wpmu_new_blog', array($this, 'EventNewBlog'), 10, 1);
            add_action('archive_blog', array($this, 'EventArchiveBlog'));
            add_action('unarchive_blog', array($this, 'EventUnarchiveBlog'));
            add_action('activate_blog', array($this, 'EventActivateBlog'));
            add_action('deactivate_blog', array($this, 'EventDeactivateBlog'));
            add_action('delete_blog', array($this, 'EventDeleteBlog'));
            add_action('add_user_to_blog', array($this, 'EventUserAddedToBlog'), 10, 3);
            add_action('remove_user_from_blog', array($this, 'EventUserRemovedFromBlog'));
        }
    }
    
    /**
     * Triggered when a user accesses the admin area.
     */
    public function EventAdminInit()
    {
        $this->old_allowedthemes = array_keys((array)get_site_option('allowedthemes'));
    }
    
    /**
     * Activated/Deactivated theme on network.
     */
    public function EventAdminShutdown()
    {
        if (is_null($this->old_allowedthemes)) {
            return;
        }
        $new_allowedthemes = array_keys((array)get_site_option('allowedthemes'));
        
        // check for enabled themes
        foreach ($new_allowedthemes as $theme) {
            if (!in_array($theme, (array)$this->old_allowedthemes)) {
                $theme = wp_get_theme($theme);
                $this->plugin->alerts->Trigger(5008, array(
                    'Theme' => (object)array(
                        'Name' => $theme->Name,
                        'ThemeURI' => $theme->ThemeURI,
                        'Description' => $theme->Description,
                        'Author' => $theme->Author,
                        'Version' => $theme->Version,
                        'get_template_directory' => $theme->get_template_directory(),
                    ),
                ));
            }
        }
        
        // check for disabled themes
        foreach ((array)$this->old_allowedthemes as $theme) {
            if (!in_array($theme, $new_allowedthemes)) {
                $theme = wp_get_theme($theme);
                $this->plugin->alerts->Trigger(5009, array(
                    'Theme' => (object)array(
                        'Name' => $theme->Name,
                        'ThemeURI' => $theme->ThemeURI,
                        'Description' => $theme->Description,
                        'Author' => $theme->Author,
                        'Version' => $theme->Version,
                        'get_template_directory' => $theme->get_template_directory(),
                    ),
                ));
            }
        }
    }

    /**
     * New site added on the network.
     */
    public function EventNewBlog($blog_id)
    {
        $this->plugin->alerts->Trigger(7000, array(
            'BlogID' => $blog_id,
            'SiteName' => get_blog_option($blog_id, 'blogname'),
        ));
    }
    
    /**
     * Existing site archived.
     */
    public function EventArchiveBlog($blog_id)
    {
        $this->plugin->alerts->Trigger(7001, array(
            'BlogID' => $blog_id,
            'SiteName' => get_blog_option($blog_id, 'blogname'),
        ));
    }
    
    /**
     * Archived site has been unarchived.
     */
    public function EventUnarchiveBlog($blog_id)
    {
        $this->plugin->alerts->Trigger(7002, array(
            'BlogID' => $blog_id,
            'SiteName' => get_blog_option($blog_id, 'blogname'),
        ));
    }
    
    /**
     * Deactivated site has been activated.
     */
    public function EventActivateBlog($blog_id)
    {
        $this->plugin->alerts->Trigger(7003, array(
            'BlogID' => $blog_id,
            'SiteName' => get_blog_option($blog_id, 'blogname'),
        ));
    }
    
    /**
     * Site has been deactivated.
     */
    public function EventDeactivateBlog($blog_id)
    {
        $this->plugin->alerts->Trigger(7004, array(
            'BlogID' => $blog_id,
            'SiteName' => get_blog_option($blog_id, 'blogname'),
        ));
    }
    
    /**
     * Existing site deleted from network.
     */
    public function EventDeleteBlog($blog_id)
    {
        $this->plugin->alerts->Trigger(7005, array(
            'BlogID' => $blog_id,
            'SiteName' => get_blog_option($blog_id, 'blogname'),
        ));
    }
    
    /**
     * Existing user added to a site.
     */
    public function EventUserAddedToBlog($user_id, $role, $blog_id)
    {
        $this->plugin->alerts->TriggerIf(4010, array(
            'TargetUserID' => $user_id,
            'TargetUsername' => get_userdata($user_id)->user_login,
            'TargetUserRole' => $role,
            'BlogID' => $blog_id,
            'SiteName' => get_blog_option($blog_id, 'blogname'),
        ), array($this, 'MustNotContainCreateUser'));
    }
    
    /**
     * User removed from site.
     */
    public function EventUserRemovedFromBlog($user_id)
    {
        $user = get_userdata($user_id);
        $blog_id = (isset($_REQUEST['id']) ? $_REQUEST['id'] : 0);
        $this->plugin->alerts->TriggerIf(4011, array(
            'TargetUserID' => $user_id,
            'TargetUsername' => $user->user_login,
            'TargetUserRole' => is_array($user->roles) ? implode(', ', $user->roles) : $user->roles,
            'BlogID' => $blog_id,
            'SiteName' => get_blog_option($blog_id, 'blogname'),
        ), array($this, 'MustNotContainCreateUser'));
    }
    
    /**
     * New network user created.
     */
    public function MustNotContainCreateUser(WSAL_AlertManager $mgr)
    {
        return !$mgr->WillTrigger(4012);
    }
}
