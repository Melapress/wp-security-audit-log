<?php

require_once(ABSPATH . 'wp-admin/includes/admin.php');
require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');

class WSAL_AuditLogListView extends WP_List_Table {

    /**
     * @var WpSecurityAuditLog
     */
    protected $_plugin;
    
    public function __construct($plugin){
        $this->_plugin = $plugin;
        
        $this->_gmt_offset_sec = get_option('gmt_offset') * HOUR_IN_SECONDS;
        
        parent::__construct(array(
            'singular'  => 'log',
            'plural'    => 'logs',
            'ajax'      => true,
            'screen'    => 'interval-list',
        ));
    }
    
    protected $_gmt_offset_sec = 0;

    public function no_items(){
        _e('No events so far.', 'wp-security-audit-log');
    }
    
    public function extra_tablenav($which){
        // items-per-page widget
        $o = __('Other', 'wp-security-audit-log');
        $p = $this->_plugin->settings->GetViewPerPage();
        $items = array($o, 5, 10, 15, 30, 50);
        if (!in_array($p, $items)) $items[] = $p;
        if ($p == $o || $p == 0) $p = $o[1]; // a sane default if things goes bust
        
        ?><div class="wsal-ipp wsal-ipp-<?php echo $which; ?>">
            <?php _e('Show ', 'wp-security-audit-log'); ?>
            <select class="wsal-ipps" onfocus="WsalIppsFocus(value);" onchange="WsalIppsChange(value);">
                <?php foreach($items as $item){ ?>
                    <option
                        value="<?php echo is_string($item) ? '' : $item; ?>"
                        <?php if($item == $p)echo 'selected="selected"'; ?>><?php
                        echo $item;
                    ?></option>
                <?php } ?>
            </select>
            <?php _e(' Items', 'wp-security-audit-log'); ?>
        </div><?php
        
        // show site alerts widget
        if($this->is_multisite() && $this->is_main_blog()){
            $curr = $this->get_view_site_id();
            ?><div class="wsal-ssa wsal-ssa-<?php echo $which; ?>">
                <?php if($this->get_site_count() > 15){ ?>
                    <?php $curr = $curr ? get_blog_details($curr) : null; ?>
                    <?php $curr = $curr ? ($curr->blogname . ' (' . $curr->domain . ')') : 'All Sites'; ?>
                    <input type="text" class="wsal-ssas" value="<?php echo esc_attr($curr); ?>"/>
                <?php }else{ ?>
                    <select class="wsal-ssas" onchange="WsalSsasChange(value);">
                        <option value="0"><?php _e('All Sites', 'wp-security-audit-log'); ?></option>
                        <?php foreach($this->get_sites() as $info){ ?>
                            <option value="<?php echo $info->blog_id; ?>"
                                <?php if($info->blog_id == $curr)echo 'selected="selected"'; ?>><?php
                                echo esc_html($info->blogname) . ' (' . esc_html($info->domain) . ')';
                            ?></option>
                        <?php } ?>
                    </select>
                <?php } ?>
            </div><?php
        }
    }
    
    /**
     * @param int|null $limit Maximum number of sites to return (null = no limit).
     * @return object Object with keys: blog_id, blogname, domain
     */
    public function get_sites($limit = null){
        global $wpdb;
        
        // build query
        $sql = 'SELECT blog_id, domain FROM ' . $wpdb->blogs;
        if(!is_null($limit))$sql .= ' LIMIT ' . $limit;
        
        // execute query
        $res = $wpdb->get_results($sql);
        
        // modify result
        foreach($res as $row){
            $row->blogname = get_blog_option($row->blog_id, 'blogname');
        }
        
        // return result
        return $res;
    }
    
    /**
     * @return int The number of sites on the network.
     */
    public function get_site_count(){
        global $wpdb;
        $sql = 'SELECT COUNT(*) FROM ' . $wpdb->blogs;
        return (int)$wpdb->get_var($sql);
    }

    public function get_columns(){
        $cols = array(
            //'cb'   => '<input type="checkbox" />',
            //'read' => __('Read', 'wp-security-audit-log'),
            'type' => __('Code', 'wp-security-audit-log'),
            'code' => __('Type', 'wp-security-audit-log'),
            'crtd' => __('Date', 'wp-security-audit-log'),
            'user' => __('Username', 'wp-security-audit-log'),
            'scip' => __('Source IP', 'wp-security-audit-log'),
        );
        if($this->is_multisite() && $this->is_main_blog() && !$this->is_specific_view()){
            $cols['site'] = __('Site', 'wp-security-audit-log');
        }
        $cols['mesg'] = __('Message', 'wp-security-audit-log');
        if($this->_plugin->settings->IsDataInspectorEnabled()){
            $cols['data'] = '';
        }
        $sel_columns = $this->_plugin->settings->GetColumnsSelected();
        if (!empty($sel_columns)) {
            unset($cols);
            $sel_columns = (array)json_decode($sel_columns);
            foreach ($sel_columns as $key => $value) {
                switch ($key) {
                    case 'alert_code':
                        $cols['type'] = __('Code', 'wp-security-audit-log');
                        break;
                    case 'type':
                        $cols['code'] = __('Type', 'wp-security-audit-log');
                        break;
                    case 'date':
                        $cols['crtd'] = __('Date', 'wp-security-audit-log');
                        break;
                    case 'username':
                        $cols['user'] = __('Username', 'wp-security-audit-log');
                        break;
                    case 'source_ip':
                        $cols['scip'] = __('Source IP', 'wp-security-audit-log');
                        break;
                    case 'site':
                        $cols['site'] = __('Site', 'wp-security-audit-log');
                        break;
                    case 'message':
                        $cols['mesg'] = __('Message', 'wp-security-audit-log');
                        break;
                }
            }
        }
        return $cols;
    }

    public function column_cb($item){
        return '<input type="checkbox" value="'.$item->id.'" '
             . 'name="'.esc_attr($this->_args['singular']).'[]"/>';
    }

    public function get_sortable_columns(){
        return array(
            'read' => array('is_read', false),
            //'code' => array('code', false),
            'type' => array('alert_id', false),
            'crtd' => array('created_on', true),
            'user' => array('user', true),
            'scip' => array('scip', false)
        );
    }
    
    public function column_default($item, $column_name){
        //example: $item->getMetaValue('CurrentUserID')

        if (!$this->_plugin->settings->GetDatetimeFormat()) $datetimeFormat = 'h:i:s.$$$&\n\b\s\p;A';
        else $datetimeFormat = 'H:i:s.$$$';
        switch($column_name){
            case 'read':
                return '<span class="log-read log-read-'
                    . ($item->is_read ? 'old' : 'new')
                    . '" title="' . __('Click to toggle.', 'wp-security-audit-log') . '"></span>';
            case 'type':
                return str_pad($item->alert_id, 4, '0', STR_PAD_LEFT);
            case 'code':
                $code = $this->_plugin->alerts->GetAlert($item->alert_id);
                $code = $code ? $code->code : 0;
                $const = (object)array('name' => 'E_UNKNOWN', 'value' => 0, 'description' => __('Unknown error code.', 'wp-security-audit-log'));
                $const = $this->_plugin->constants->GetConstantBy('value', $code, $const);
                return '<span class="log-type log-type-' . $const->value
                    . '" title="' . esc_html($const->name . ': ' . $const->description) . '"></span>';
            case 'crtd':
                return $item->created_on ? (
                        str_replace(
                            '$$$',
                            substr(number_format(fmod($item->created_on + $this->_gmt_offset_sec, 1), 3), 2),
                            date('Y-m-d<\b\r>'.$datetimeFormat, $item->created_on + $this->_gmt_offset_sec)
                        )
                    ) : '<i>unknown</i>';
            case 'user':
                $username = $item->GetUsername();
                if ($username && ($user = get_user_by('login', $username))) {
                    $image = get_avatar($user->ID, 32);
                    $uhtml = '<a href="' . admin_url('user-edit.php?user_id=' . $user->ID)
                            . '" target="_blank">' . esc_html($user->display_name) . '</a>';
                    $roles = $item->GetUserRoles();
                    if (is_array($roles) && count($roles)) {
                        $roles = __(esc_html(ucwords(implode(', ', $roles))));
                    } else if (is_string($roles) && $roles != '') {
                        $roles = __(esc_html(ucwords(str_replace(array("\"", "[", "]"), " ", $roles))));
                    } else {
                        $roles = '<i>' . __('Unknown', 'wp-security-audit-log') . '</i>';
                    }
                } elseif ($username == 'Plugin') {
                    $image = '<img src="'. $this->_plugin->GetBaseUrl() . '/img/plugin-logo.png" class="avatar avatar-32 photo" width="32" height="32" alt=""/>';
                    $uhtml = '<i>' . __('Plugin', 'wp-security-audit-log') . '</i>';
                    $roles = '';
                } else {
                    $image = '<img src="'. $this->_plugin->GetBaseUrl() . '/img/wordpress-logo-32.png" class="avatar avatar-32 photo" width="32" height="32" alt=""/>';
                    $uhtml = '<i>' . __('System', 'wp-security-audit-log') . '</i>';
                    $roles = '';
                }
                return $image . $uhtml . '<br/>' . $roles;
            case 'scip':
                $scip = $item->GetSourceIP();
                if (is_string($scip)) {
                    $scip = str_replace(array("\"", "[", "]"), "", $scip);
                }
                $oips = array(); //$item->GetOtherIPs();
                // if there's no IP...
                if (is_null($scip) || $scip == '') return '<i>unknown</i>';
                // if there's only one IP...
                $link = "http://whatismyipaddress.com/ip/" . $scip ."?utm_source=plugin&utm_medium=referral&utm_campaign=WPSAL";
                if (count($oips) < 2) return "<a target='_blank' href='$link'>". esc_html($scip) .'</a>';
                // if there are many IPs...
                $html  = "<a target='_blank' href='http://whatismyipaddress.com/ip/$scip'>". esc_html($scip) .'</a>'.' <a href="javascript:;" onclick="jQuery(this).hide().next().show();">(more&hellip;)</a><div style="display: none;">';
                foreach($oips as $ip)if($scip!=$ip)$html .= '<div>' . $ip . '</div>';
                $html .= '</div>';
                return $html;
            case 'site':
                $info = get_blog_details($item->site_id, true);
                return !$info ? ('Unknown Site '.$item->site_id)
                    : ('<a href="' . esc_attr($info->siteurl) . '">' . esc_html($info->blogname) . '</a>');
            case 'mesg':
                return '<div id="Event' . $item->id . '">' . $item->GetMessage(array($this, 'meta_formatter')) . '</div>';
            case 'data':
                $url = admin_url('admin-ajax.php') . '?action=AjaxInspector&amp;occurrence=' . $item->id;
                return '<a class="more-info thickbox" title="' . __('Alert Data Inspector', 'wp-security-audit-log') . '"'
                    . ' href="' . $url . '&amp;TB_iframe=true&amp;width=600&amp;height=550">&hellip;</a>';
            default:
                return isset($item->$column_name)
                    ? esc_html($item->$column_name)
                    : 'Column "' . esc_html($column_name) . '" not found';
        }
    }

    public function reorder_items_str($a, $b){
        $result = strcmp($a->{$this->_orderby}, $b->{$this->_orderby});
        return ($this->_order === 'asc') ? $result : -$result;
    }
    
    public function reorder_items_int($a, $b){
        $result = $a->{$this->_orderby} - $b->{$this->_orderby};
        return ($this->_order === 'asc') ? $result : -$result;
    }
    
    public function meta_formatter($name, $value){
        switch(true){
            
            case $name == '%Message%':
                return esc_html($value);

            case $name == '%PromoMessage%':
                return '<p class="promo-alert">' . $value .'</p>';

            case $name == '%PromoLink%':
                return $value;

            case $name == '%MetaLink%':
                if (!empty($value)) {
                    return "<a href=\"#\" onclick=\"WsalDisableCustom(this, '".$value."');\"> Exclude Custom Field from the Monitoring</a>";
                } else {
                    return "";
                }

            case $name == '%RevisionLink%':
                if (!empty($value) && $value != 'NULL') {
                    return ' Click <a target="_blank" href="'.$value.'">here</a> to see the content changes.';
                } else {
                    return "";
                }
                
            case in_array($name, array('%MetaValue%', '%MetaValueOld%', '%MetaValueNew%')):
                return '<strong>' . (
                    strlen($value) > 50 ? (esc_html(substr($value, 0, 50)) . '&hellip;') :  esc_html($value)
                ) . '</strong>';
                
            case strncmp($value, 'http://', 7) === 0:
            case strncmp($value, 'https://', 7) === 0:
                return '<a href="' . esc_html($value) . '"'
                    . ' title="' . esc_html($value) . '"'
                    . ' target="_blank">'
                        . esc_html(parse_url($value, PHP_URL_HOST)) . '/&hellip;/'
                        . esc_html(basename(parse_url($value, PHP_URL_PATH)))
                    . '</a>';
                
            default:
                return '<strong>' . esc_html($value) . '</strong>';
        }
    }
    
    protected function is_multisite(){
        return $this->_plugin->IsMultisite();
    }
    
    protected function is_main_blog(){
        return get_current_blog_id() == 1;
    }
    
    protected function is_specific_view(){
        return isset($_REQUEST['wsal-cbid']) && $_REQUEST['wsal-cbid'] != '0';
    }
    
    protected function get_specific_view(){
        return isset($_REQUEST['wsal-cbid']) ? (int)$_REQUEST['wsal-cbid'] : 0;
    }
    
    protected function get_view_site_id(){
        switch(true){
            
            // non-multisite
            case !$this->is_multisite():
                return 0;
            
            // multisite + main site view
            case $this->is_main_blog() && !$this->is_specific_view():
                return 0;
            
            // multisite + switched site view
            case $this->is_main_blog() && $this->is_specific_view():
                return $this->get_specific_view();
            
            // multisite + local site view
            default:
                return get_current_blog_id();
            
        }
    }
    
    public function prepare_items() {
        $per_page = $this->_plugin->settings->GetViewPerPage();

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, $hidden, $sortable);

        //$this->process_bulk_action();
        
        //TO DO: Get rid of OccurrenceQuery and use the Occurence Model
        $query = new WSAL_Models_OccurrenceQuery(); 

        $bid = (int)$this->get_view_site_id();
        if ($bid) {
            $query->addCondition("site_id = %s ", $bid);
        }
        
        $query = apply_filters('wsal_auditlog_query', $query);
        
        $total_items = $query->getAdapter()->Count($query);

        if (empty($_REQUEST["orderby"])) {
            $query->addOrderBy("created_on", true);
        } else {
            $orderByField = $_REQUEST["orderby"];

            $isDescending = true;
            if (!empty($_REQUEST['order']) && $_REQUEST["order"] == "asc") {
                $isDescending = false;
            }

            //TO DO: Allow order by meta values
            if ($orderByField == "scip") {
                $query->addMetaJoin();
                $query->addOrderBy('CASE WHEN meta.name = "ClientIP" THEN meta.value END', $isDescending);
            } else if ($orderByField == "user") {
                $query->addMetaJoin();
                $query->addOrderBy('CASE WHEN meta.name = "CurrentUserID" THEN meta.value END', $isDescending);
            } else {
                $tmp = new WSAL_Models_Occurrence();
                //Making sure the field exists to order by
                if (isset($tmp->{$orderByField})) {
                    // TODO we used to use a custom comparator ... is it safe to let MySQL do the ordering now?
                    $query->addOrderBy($_REQUEST["orderby"], $isDescending);

                } else {
                    $query->addOrderBy("created_on", true);
                }
            }
        }

        /** @todo Modify $query instead */
        /** @deprecated */
        //$data = array_slice($data, ($this->get_pagenum() - 1) * $per_page, $per_page);
        $query->setOffset(($this->get_pagenum() - 1) * $per_page);
        $query->setLimit($per_page);

        $this->items = $query->getAdapter()->Execute($query);

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));
    }

    public function single_row($item)
    {
        if ($item->alert_id == 9999) {
            echo '<tr style="background-color: #D5E46E">';
            $this->single_row_columns($item);
            echo '</tr>';
        } else {
            parent::single_row($item);
        }
    }
}
