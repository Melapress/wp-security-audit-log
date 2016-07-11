<?php

class WSAL_Sensors_System extends WSAL_AbstractSensor
{
    const TRANSIENT_404 = 'wsal-404-attempts';

    public function HookEvents()
    {
        add_action('wsal_prune', array($this, 'EventPruneEvents'), 10, 2);
        add_action('admin_init', array($this, 'EventAdminInit'));

        add_action('automatic_updates_complete', array($this, 'WPUpdate'), 10, 1);
        add_filter('template_redirect', array($this, 'Event404'));
    }
    
    /**
     * @param int $count The number of deleted events.
     * @param string $query Query that selected events for deletion.
     */
    public function EventPruneEvents($count, $query)
    {
        $this->plugin->alerts->Trigger(6000, array(
            'EventCount' => $count,
            'PruneQuery' => $query,
        ));
    }

    protected function Get404LogLimit()
    {
        return 10;
    }
    
    protected function Get404Expiration()
    {
        return 24 * 60 * 60;
    }

    protected function IsPast404Limit($site_id, $username, $ip)
    {
        $get_fn = $this->IsMultisite() ? 'get_site_transient' : 'get_transient';
        $data = $get_fn(self::TRANSIENT_404);
        return ($data !== false) && isset($data[$site_id.":".$username.":".$ip]) && ($data[$site_id.":".$username.":".$ip] > $this->Get404LogLimit());
    }
    
    protected function Increment404($site_id, $username, $ip)
    {
        $get_fn = $this->IsMultisite() ? 'get_site_transient' : 'get_transient';
        $set_fn = $this->IsMultisite() ? 'set_site_transient' : 'set_transient';

        $data = $get_fn(self::TRANSIENT_404);
        if (!$data) {
            $data = array();
        }
        if (!isset($data[$site_id.":".$username.":".$ip])) {
            $data[$site_id.":".$username.":".$ip] = 1;
        }
        $data[$site_id.":".$username.":".$ip]++;
        $set_fn(self::TRANSIENT_404, $data, $this->Get404Expiration());
    }
    
    public function Event404()
    {
        global $wp_query;
        if (!$wp_query->is_404) {
            return;
        }
        $msg = 'times';

        list($y, $m, $d) = explode('-', date('Y-m-d'));

        $site_id = (function_exists('get_current_blog_id') ? get_current_blog_id() : 0);
        $ip = $this->plugin->settings->GetMainClientIP();

        if (!is_user_logged_in()) {
            $username = "Website Visitor";
        } else {
            $username = wp_get_current_user()->user_login;
        }
        
        if ($this->IsPast404Limit($site_id, $username, $ip)) {
            return;
        }

        $objOcc = new  WSAL_Models_Occurrence();

        $occ = $objOcc->CheckAlert404(
            array(
                $ip,
                $username,
                6007,
                $site_id,
                mktime(0, 0, 0, $m, $d, $y),
                mktime(0, 0, 0, $m, $d + 1, $y) - 1
            )
        );
            
        $occ = count($occ) ? $occ[0] : null;
        if (!empty($occ)) {
            // update existing record
            $this->Increment404($site_id, $username, $ip);
            $new = $occ->GetMetaValue('Attempts', 0) + 1;
            
            if ($new > $this->Get404LogLimit()) {
                $new = 'more than ' . $this->Get404LogLimit();
                $msg .= ' This could possible be a scan, therefore keep an eye on the activity from this IP Address';
            }
            $occ->UpdateMetaValue('Attempts', $new);
            $occ->UpdateMetaValue('Username', $username);
            $occ->UpdateMetaValue('Msg', $msg);
            $occ->created_on = null;
            $occ->Save();
        } else {
            // create a new record
            $fields =  array(
                'Attempts' => 1,
                'Username' => $username,
                'Msg' => $msg
            );
            $this->plugin->alerts->Trigger(6007, $fields);
        }
    }

    public function EventAdminInit()
    {
        // make sure user can actually modify target options
        if (!current_user_can('manage_options')) return;
        
        $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
        $actype = basename($_SERVER['SCRIPT_NAME'], '.php');
        $is_option_page = $actype == 'options';
        $is_network_settings = $actype == 'settings';
        $is_permalink_page = $actype == 'options-permalink';
        
        if ($is_option_page && (get_option('users_can_register') xor isset($_POST['users_can_register']))) {
            $old = get_option('users_can_register') ? 'Enabled' : 'Disabled';
            $new = isset($_POST['users_can_register']) ? 'Enabled' : 'Disabled';
            if ($old !== $new) {
                $this->plugin->alerts->Trigger(6001, array(
                    'OldValue' => $old,
                    'NewValue' => $new,
                    'CurrentUserID' => wp_get_current_user()->ID,
                ));
            }
        }

        if ($is_option_page && !empty($_POST['default_role'])) {
            $old = get_option('default_role');
            $new = trim($_POST['default_role']);
            if ($old !== $new) {
                $this->plugin->alerts->Trigger(6002, array(
                    'OldRole' => $old,
                    'NewRole' => $new,
                    'CurrentUserID' => wp_get_current_user()->ID,
                ));
            }
        }

        if ($is_option_page && !empty($_POST['admin_email'])) {
            $old = get_option('admin_email');
            $new = trim($_POST['admin_email']);
            if ($old !== $new) {
                $this->plugin->alerts->Trigger(6003, array(
                    'OldEmail' => $old,
                    'NewEmail' => $new,
                    'CurrentUserID' => wp_get_current_user()->ID,
                ));
            }
        }
        
        if ($is_network_settings && !empty($_POST['admin_email'])) {
            $old = get_site_option('admin_email');
            $new = trim($_POST['admin_email']);
            if ($old !== $new) {
                $this->plugin->alerts->Trigger(6003, array(
                    'OldEmail' => $old,
                    'NewEmail' => $new,
                    'CurrentUserID' => wp_get_current_user()->ID,
                ));
            }
        }
        
        if ($is_permalink_page && !empty($_POST['permalink_structure'])) {
            $old = get_option('permalink_structure');
            $new = trim($_POST['permalink_structure']);
            if ($old !== $new) {
                $this->plugin->alerts->Trigger(6005, array(
                    'OldPattern' => $old,
                    'NewPattern' => $new,
                    'CurrentUserID' => wp_get_current_user()->ID,
                ));
            }
        }
        
        if ($action == 'do-core-upgrade' && isset($_REQUEST['version'])) {
            $oldVersion = get_bloginfo('version');
            $newVersion = $_REQUEST['version'];
            if ($oldVersion !== $newVersion) {
                $this->plugin->alerts->Trigger(6004, array(
                    'OldVersion' => $oldVersion,
                    'NewVersion' => $newVersion,
                ));
            }
        }
        
        /* BBPress Forum support  Setting */
        if ($action == 'update' && isset($_REQUEST['_bbp_default_role'])) {
            $oldRole = get_option('_bbp_default_role');
            $newRole = $_REQUEST['_bbp_default_role'];
            if ($oldRole !== $newRole) {
                $this->plugin->alerts->Trigger(8009, array(
                    'OldRole' => $oldRole,
                    'NewRole' => $newRole
                ));
            }
        }

        if ($action == 'update' && isset($_REQUEST['option_page']) && ($_REQUEST['option_page'] == 'bbpress')) {
            // Anonymous posting
            $allow_anonymous = get_option('_bbp_allow_anonymous');
            $oldStatus = !empty($allow_anonymous) ? 1 : 0;
            $newStatus = !empty($_REQUEST['_bbp_allow_anonymous']) ? 1 : 0;
            if ($oldStatus != $newStatus) {
                $status = ($newStatus == 1) ? 'Enabled' : 'Disabled';
                $this->plugin->alerts->Trigger(8010, array(
                    'Status' => $status
                ));
            }
            // Disallow editing after
            $bbp_edit_lock = get_option('_bbp_edit_lock');
            $oldTime = !empty($bbp_edit_lock) ? $bbp_edit_lock : '';
            $newTime = !empty($_REQUEST['_bbp_edit_lock']) ? $_REQUEST['_bbp_edit_lock'] : '';
            if ($oldTime != $newTime) {
                $this->plugin->alerts->Trigger(8012, array(
                    'OldTime' => $oldTime,
                    'NewTime' => $newTime
                ));
            }
            // Throttle posting every
            $bbp_throttle_time = get_option('_bbp_throttle_time');
            $oldTime2 = !empty($bbp_throttle_time) ? $bbp_throttle_time : '';
            $newTime2 = !empty($_REQUEST['_bbp_throttle_time']) ? $_REQUEST['_bbp_throttle_time'] : '';
            if ($oldTime2 != $newTime2) {
                $this->plugin->alerts->Trigger(8013, array(
                    'OldTime' => $oldTime2,
                    'NewTime' => $newTime2
                ));
            }
        }
    }

    /**
     * WordPress auto core update
     */
    public function WPUpdate($automatic)
    {
        if (isset($automatic['core'][0])) {
            $obj = $automatic['core'][0];
            $oldVersion = get_bloginfo('version');
            $this->plugin->alerts->Trigger(6004, array(
                'OldVersion' => $oldVersion,
                'NewVersion' => $obj->item->version.' (auto update)'
            ));
        }
    }
}
