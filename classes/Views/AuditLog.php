<?php

class WSAL_Views_AuditLog extends WSAL_AbstractView {
	
	protected $_listview;
	
	public function __construct(WpSecurityAuditLog $plugin) {
		parent::__construct($plugin);
		$this->_listview = new WSAL_Views_AuditLogList_Internal($plugin);
		add_action('wp_ajax_AjaxInspector', array($this, 'AjaxInspector'));
		add_action('wp_ajax_AjaxRefresh', array($this, 'AjaxRefresh'));
	}
	
	public function HasPluginShortcutLink(){
		return true;
	}
	
	public function GetTitle() {
		return 'Audit Log Viewer';
	}
	
	public function GetIcon() {
		return $this->_wpversion < 3.8
			? $this->_plugin->GetBaseUrl() . '/img/logo-main-menu.png'
			: 'dashicons-welcome-view-site';
	}
	
	public function GetName() {
		return 'Audit Log Viewer';
	}
	
	public function GetWeight(){
		return 1;
	}
	
	public function Render(){
		if(!$this->_plugin->settings->CurrentUserCan('view')){
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		?><form id="audit-log-viewer" method="get">
			<input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
			<?php $this->_listview->prepare_items(); ?>
			<?php $this->_listview->display(); ?>
		</form>
		<script type="text/javascript">
			jQuery(document).ready(function(){
				var cnt = <?php echo WSAL_DB_Occurrence::Count(); ?>;
				var url = <?php echo json_encode(admin_url('admin-ajax.php') . '?action=AjaxRefresh&logcount='); ?>;
				var ajx = null;
				var chk = function(){
					if(ajx)ajx.abort();
					ajx = jQuery.post(url + cnt, function(data){
						ajx = null;
						if(data !== 'false'){
							cnt = data;
							jQuery('#audit-log-viewer').load(location.href + ' #audit-log-viewer');
						}
						chk();
					});
				};
				setInterval(chk, 40000);
				chk();
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
	
	public function Header(){
		add_thickbox();
		wp_enqueue_style(
			'auditlog',
			$this->_plugin->GetBaseUrl() . '/css/auditlog.css',
			array(),
			filemtime($this->_plugin->GetBaseDir() . '/css/auditlog.css')
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
		_e('No events so far.');
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
			'mesg' => 'Message',
		);
		if($this->_plugin->settings->IsDataInspectorEnabled())
			$cols['data'] = '';
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
				return !is_null($item->GetUsername()) ? esc_html($item->GetUsername()) : '<i>unknown</i>';
			case 'scip':
				return !is_null($item->GetSourceIP()) ? esc_html($item->GetSourceIP()) : '<i>unknown</i>';
			case 'mesg':
				return $item->GetMessage(array($this, 'meta_formatter'));
			case 'data':
				$url = admin_url('admin-ajax.php') . '?action=AjaxInspector&amp;occurrence=' . $item->id;
				return '<a class="more-info thickbox" title="Alert Data Inspector"'
					. ' href="' . $url . '&amp;TB_iframe=true&amp;width=600&amp;height=550">&hellip;</a>';
			default:
				return isset($item->$column_name)
					? esc_html($item->$column_name)
					: 'Column "' . esc_html($column_name) . '" not found';
		}
	}
	
	public function group_alerts(){
		return false;
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
	
	public function prepare_items() {
		$per_page = 20;

		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array($columns, $hidden, $sortable);

		//$this->process_bulk_action();
		
		$data = $this->group_alerts()
			? WSAL_DB_Occurrence::GetNewestUnique()
			: WSAL_DB_Occurrence::LoadMulti('1 ORDER BY created_on DESC');
		
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
