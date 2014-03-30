<?php

class WSAL_Views_AuditLog extends WSAL_AbstractView {
	
	protected $_plugin;
	
	protected $_listview;
	
	public function SetPlugin(WpSecurityAuditLog $plugin) {
		$this->_plugin = $plugin;
	}
	
	public function __construct(WpSecurityAuditLog $plugin) {
		parent::__construct($plugin);
		$this->_listview = new WSAL_Views_AuditLogList_Internal($plugin);
		add_action('wp_ajax_AjaxInspector', array($this, 'AjaxInspector'));
	}
	
	public function GetTitle() {
		return 'Audit Log Viewer';
	}
	
	public function GetIcon() {
		return 'dashicons-welcome-view-site';
	}
	
	public function GetName() {
		return 'Audit Log Viewer';
	}
	
	public function GetWeight(){
		return 1;
	}
	
	public function Render(){
		?><form id="audit-log-viewer" method="get">
			<input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
			<?php $this->_listview->prepare_items(); ?>
			<?php $this->_listview->display(); ?>
		</form><?php
	}
	
	public function AjaxInspector(){
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
		return array(
			'cb'   => '<input type="checkbox" />',
			//'read' => 'Read',
			'type' => 'Code',
			'code' => 'Type',
			'crtd' => 'Date',
			'user' => 'Username',
			'mesg' => 'Message',
			'more' => '',
		);
	}

	public function column_cb($item){
		return '<input type="checkbox" value="'.$item['id'].'" '
			 . 'name="'.esc_attr($this->_args['singular']).'[]"/>';
	}

	public function get_sortable_columns(){
		return array(
			'read' => array('read', false),
			'code' => array('code', false),
			'type' => array('type', false),
			'crtd' => array('crtd', true),
			'user' => array('user', false),
		);
	}
	
	public function column_default($item, $column_name){
		switch($column_name){
			case 'read':
				return '<span class="log-read log-read-'
					. ($item['read'] ? 'old' : 'new')
					. '" title="Click to toggle."></span>';
			case 'type':
				return str_pad($item['type'], 4, '0', STR_PAD_LEFT);
			case 'code':
				$code = $this->_plugin->alerts->GetAlert($item['type']);
				$code = $code ? $code->code : 0;
				$const = (object)array('name' => 'E_UNKNOWN', 'value' => 0, 'description' => 'Unknown error code.');
				$const = $this->_plugin->constants->GetConstantBy('value', $code, $const);
				return '<span class="log-type log-type-' . $const->value
					. '" title="' . esc_html($const->name . ': ' . $const->description) . '"></span>';
			case 'crtd':
				return $item['crtd'] ? date('Y-m-d h:i:s A', $item['crtd']) : '<i>unknown</i>';
			case 'user':
				return !is_null($item['user']) ? esc_html($item['user']) : '<i>unknown</i>';
			case 'more':
				$url = admin_url('admin-ajax.php') . '?action=AjaxInspector&amp;occurrence=' . $item['id'];
				return '<a class="more-info thickbox" title="Alert Data Inspector"'
					. ' href="' . $url . '&amp;TB_iframe=true&amp;width=600&amp;height=550">&hellip;</a>';
			default:
				return isset($item[$column_name])
					? esc_html($item[$column_name])
					: 'Column "' . esc_html($column_name) . '" not found';
		}
	}
	
	public function group_alerts(){
		return false;
	}

	public function reorder_items_str($a, $b){
		$result = strcmp($a[$this->_orderby], $b[$this->_orderby]);
		return ($this->_order === 'asc') ? $result : -$result;
	}
	
	public function reorder_items_int($a, $b){
		$result = $a[$this->_orderby] - $b[$this->_orderby];
		return ($this->_order === 'asc') ? $result : -$result;
	}
	
	protected function get_username(WSAL_DB_Occurrence $occ){
		$meta = $occ->GetFirstNamedMeta(array('Username', 'CurrentUserID'));
		if($meta){
			switch(true){
				case $meta->name == 'Username':
					return $meta->value;
				case $meta->name == 'CurrentUserID':
					return ($data = get_userdata($meta->value)) ? $data->user_login : null;
			}
		}
		return null;
	}
	
	public function prepare_items() {
		$per_page = 20;

		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array($columns, $hidden, $sortable);

		//$this->process_bulk_action();

		$data = array();
		foreach(
			$this->group_alerts()
				? WSAL_DB_Occurrence::GetNewestUnique()
				: WSAL_DB_Occurrence::LoadMulti('1 ORDER BY created_on DESC')
			as $occ){
			$log = $occ->GetAlert();
			$data[] = array(
				'id'   => $occ->id,
				'read' => $occ->is_read,
				'type' => $occ->alert_id,
				'code' => $log->code,
				'crtd' => $occ->created_on,
				'user' => $this->get_username($occ),
				'mesg' => $occ->GetMessage(),
			);
		}

		$this->_orderby = (!empty($_REQUEST['orderby']) && isset($sortable[$_REQUEST['orderby']])) ? $_REQUEST['orderby'] : 'crtd';
		$this->_order = (!empty($_REQUEST['order']) && $_REQUEST['order']=='asc') ? 'asc' : 'desc';
		$numorder = in_array($this->_orderby, array('code', 'type', 'crtd'));
		usort($data, array($this, $numorder ? 'reorder_items_int' : 'reorder_items_str'));

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
