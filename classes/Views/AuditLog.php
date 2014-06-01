<?php

class WSAL_Views_AuditLog extends WSAL_AbstractView {
	
	protected $_listview;
	
	public function __construct(WpSecurityAuditLog $plugin) {
		parent::__construct($plugin);
		$this->_listview = new WSAL_Views_AuditLogList_Internal($plugin);
		add_action('wp_ajax_AjaxInspector', array($this, 'AjaxInspector'));
		add_action('wp_ajax_AjaxRefresh', array($this, 'AjaxRefresh'));
		add_action('wp_ajax_AjaxSetIpp', array($this, 'AjaxSetIpp'));
	}
	
	public function HasPluginShortcutLink(){
		return true;
	}
	
	public function GetTitle() {
		return __('Audit Log Viewer', 'wp-security-audit-log');
	}
	
	public function GetIcon() {
		return $this->_wpversion < 3.8
			? $this->_plugin->GetBaseUrl() . '/img/logo-main-menu.png'
			: 'dashicons-welcome-view-site';
	}
	
	public function GetName() {
		return __('Audit Log Viewer', 'wp-security-audit-log');
	}
	
	public function GetWeight(){
		return 1;
	}
	
	public function Render(){
		if(!$this->_plugin->settings->CurrentUserCan('view')){
			wp_die( __( 'You do not have sufficient permissions to access this page.' , 'wp-security-audit-log') );
		}
		
		?><form id="audit-log-viewer" method="post">
			<input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
			<input type="hidden" id="wsal-cbid" name="wsal-cbid" value="<?php echo esc_attr(isset($_REQUEST['wsal-cbid']) ? $_REQUEST['wsal-cbid'] : ''); ?>" />
			<?php $this->_listview->prepare_items(); ?>
			<?php $this->_listview->display(); ?>
		</form><?php
		
		?><script type="text/javascript">
			jQuery(document).ready(function(){
				WsalAuditLogInit(<?php echo json_encode(array(
					'ajaxurl' => admin_url('admin-ajax.php'),
					'tr8n' => array(
						'numofitems' => __('Please enter the number of alerts you would like to see on one page:', 'wp-security-audit-log'),
					),
					'autorefresh' => array(
						'enabled' => $this->_plugin->settings->IsRefreshAlertsEnabled(),
						'token' => (int)WSAL_DB_Occurrence::Count(),
					),
				)); ?>);
			});
		</script><?php
	}
	
	public function AjaxInspector(){
		if(!$this->_plugin->settings->CurrentUserCan('view'))
			die('Access Denied.');
		if(!isset($_REQUEST['occurrence']))
			die('Occurrence parameter expected.');
		$occ = new WSAL_DB_Occurrence();
		$occ->Load('id = %d', array((int)$_REQUEST['occurrence']));

		echo '<!DOCTYPE html><html><head>';
		echo '<link rel="stylesheet" id="open-sans-css" href="' . $this->_plugin->GetBaseUrl() . '/css/nice_r.css" type="text/css" media="all">';
		echo '<script type="text/javascript" src="'.$this->_plugin->GetBaseUrl() . '/js/nice_r.js"></script>';
		echo '<style type="text/css">';
		echo 'html, body { margin: 0; padding: 0; }';
		echo '.nice_r { position: absolute; padding: 8px; }';
		echo '.nice_r a { overflow: visible; }';
		echo '</style>';
		echo '</head><body>';
		$nicer = new WSAL_Nicer($occ->GetMetaArray());
		$nicer->render();
		echo '</body></html>';
		die;
	}
	
	public function AjaxRefresh(){
		if(!$this->_plugin->settings->CurrentUserCan('view'))
			die('Access Denied.');
		if(!isset($_REQUEST['logcount']))
			die('Log count parameter expected.');
		
		$old = (int)$_REQUEST['logcount'];
		$max = 40; // 40*500msec = 20sec
		
		do{
			$new = WSAL_DB_Occurrence::Count();
			usleep(500000); // 500msec
		}while(($old == $new) && (--$max > 0));
		
		echo $old == $new ? 'false' : $new;
		die;
	}
	
	public function AjaxSetIpp(){
		if(!$this->_plugin->settings->CurrentUserCan('view'))
			die('Access Denied.');
		if(!isset($_REQUEST['count']))
			die('Count parameter expected.');
		$this->_plugin->settings->SetViewPerPage((int)$_REQUEST['count']);
		die;
	}
	
	public function Header(){
		add_thickbox();
		wp_enqueue_style(
			'auditlog',
			$this->_plugin->GetBaseUrl() . '/css/auditlog.css',
			array(),
			filemtime($this->_plugin->GetBaseDir() . '/css/auditlog.css')
		);
	}
	
	public function Footer() {
		wp_enqueue_script(
			'auditlog',
			$this->_plugin->GetBaseUrl() . '/js/auditlog.js',
			array(),
			filemtime($this->_plugin->GetBaseDir() . '/js/auditlog.js')
		);
	}
	
}

require_once(ABSPATH . 'wp-admin/includes/admin.php');
require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');

class WSAL_Views_AuditLogList_Internal extends WP_List_Table {

	/**
	 * @var WpSecurityAuditLog
	 */
	protected $_plugin;
	
	public function __construct($plugin){
		$this->_plugin = $plugin;
		
		parent::__construct(array(
			'singular'  => 'log',
			'plural'    => 'logs',
			'ajax'      => true,
			'screen'    => 'interval-list',
		));
	}

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
			// TODO should I check wp_is_large_network()?
			$curr = $this->get_view_site_id();
			$sites = wp_get_sites();
			?><div class="wsal-ssa wsal-ssa-<?php echo $which; ?>">
				<select class="wsal-ssas" onchange="WsalSsasChange(value);">
					<option value="0"><?php _e('All Sites', 'wp-security-audit-log'); ?></option>
					<?php foreach($sites as $site){ ?>
						<?php $info = get_blog_details($site['blog_id'], true); ?>
						<option
							value="<?php echo $info->blog_id; ?>"
							<?php if($info->blog_id == $curr)echo 'selected="selected"'; ?>><?php
							echo esc_html($info->blogname) . ' (' . esc_html($info->domain) . ')';
						?></option>
					<?php } ?>
				</select>
			</div><?php
		}
	}

	public function get_columns(){
		$cols = array(
			//'cb'   => '<input type="checkbox" />',
			//'read' => 'Read',
			'type' => 'Code',
			'code' => 'Type',
			'crtd' => 'Date',
			'user' => 'Username',
			'scip' => 'Source IP',
		);
		if($this->is_multisite() && $this->is_main_blog() && !$this->is_specific_view()){
			$cols['site'] = 'Site';
		}
		$cols['mesg'] = 'Message';
		if($this->_plugin->settings->IsDataInspectorEnabled()){
			$cols['data'] = '';
		}
		return $cols;
	}

	public function column_cb(WSAL_DB_Occurrence $item){
		return '<input type="checkbox" value="'.$item->id.'" '
			 . 'name="'.esc_attr($this->_args['singular']).'[]"/>';
	}

	public function get_sortable_columns(){
		return array(
			'read' => array('is_read', false),
			'code' => array('code', false),
			'type' => array('alert_id', false),
			'crtd' => array('created_on', true),
			'user' => array('user', false),
			'scip' => array('scip', false),
			'site' => array('site', false),
		);
	}
	
	public function column_default(WSAL_DB_Occurrence $item, $column_name){
		switch($column_name){
			case 'read':
				return '<span class="log-read log-read-'
					. ($item->is_read ? 'old' : 'new')
					. '" title="Click to toggle."></span>';
			case 'type':
				return str_pad($item->alert_id, 4, '0', STR_PAD_LEFT);
			case 'code':
				$code = $this->_plugin->alerts->GetAlert($item->alert_id);
				$code = $code ? $code->code : 0;
				$const = (object)array('name' => 'E_UNKNOWN', 'value' => 0, 'description' => 'Unknown error code.');
				$const = $this->_plugin->constants->GetConstantBy('value', $code, $const);
				return '<span class="log-type log-type-' . $const->value
					. '" title="' . esc_html($const->name . ': ' . $const->description) . '"></span>';
			case 'crtd':
				return $item->created_on ? date('Y-m-d h:i:s A', $item->created_on) : '<i>unknown</i>';
			case 'user':
				$username = $item->GetUsername();
				if($username && ($user = get_userdatabylogin($username))){
					$image = get_avatar($user->ID, 32);
					$uhtml = '<a href="' . admin_url('user-edit.php?user_id=' . $user->ID)
							. '" target="_blank">' . esc_html($user->display_name) . '</a>';
					$roles = $item->GetUserRoles();
					$roles = (is_array($roles) && count($roles))
							? esc_html(ucwords(implode(', ', $roles)))
							: '<i>' . __('Unknown', 'wp-security-audit-log') . '</i>';
				}else{
					$image = get_avatar(0, 32);
					$uhtml = '<i>' . __('Unknown', 'wp-security-audit-log') . '</i>';
					$roles = '<i>' . __('System', 'wp-security-audit-log') . '</i>';
				}
				return $image . $uhtml . '<br/>' . $roles;
			case 'scip':
				return !is_null($item->GetSourceIP()) ? esc_html($item->GetSourceIP()) : '<i>unknown</i>';
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
		return function_exists('is_multisite') && is_multisite();
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
			case !function_exists('is_multisite') || !is_multisite():
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
		
		$bid = (int)$this->get_view_site_id();
		$sql = ($bid ? "site_id=$bid" : '1') . ' ORDER BY created_on DESC';
		$data = WSAL_DB_Occurrence::LoadMulti($sql, array());
		
		if(count($data)){
			$this->_orderby = (!empty($_REQUEST['orderby']) && isset($sortable[$_REQUEST['orderby']])) ? $_REQUEST['orderby'] : 'created_on';
			$this->_order = (!empty($_REQUEST['order']) && $_REQUEST['order']=='asc') ? 'asc' : 'desc';
			if(isset($data[0]->{$this->_orderby})){
				$numorder = in_array($this->_orderby, array('code', 'type', 'created_on'));
				usort($data, array($this, $numorder ? 'reorder_items_int' : 'reorder_items_str'));
			}
		}

		$current_page = $this->get_pagenum();

		$total_items = count($data);

		$data = array_slice($data, ($current_page - 1) * $per_page, $per_page);

		$this->items = $data;

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil($total_items / $per_page)
		) );
	}

}
