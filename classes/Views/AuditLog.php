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
			'read' => 'Read',
			'code' => 'Code',
			'type' => 'Type',
			'crtd' => 'Date',
			'mesg' => 'Message',
			'more' => '',
		);
	}

	public function column_cb($item){
		return '<input type="checkbox" value="'.$item['id'].'" '
			 . 'name="'.esc_attr($this->_args['singular']).'[]"/>';
	}

	public function column_default($item, $column_name){
		switch($column_name){
			case 'read':
				return '<span class="log-read log-read-'
					. ($item['read'] ? 'old' : 'new')
					. '" title="Click to toggle."></span>';
			case 'code':
				$const = (object)array('name' => 'E_UNKNOWN', 'value' => 0, 'description' => 'Unknown error code.');
				$const = $this->_plugin->constants->GetConstantBy('value', $item['code'], $const);
				return '<span class="log-type log-type-' . $const->value
					. '" title="' . esc_html($const->name . ': ' . $const->description) . '"></span>';
			case 'crtd':
				return date('Y-m-d H:i:s', $item['date']);
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

	public function prepare_items() {
		$per_page = 5;

		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array($columns, $hidden, $sortable);

		//$this->process_bulk_action();

		$data = array();
		foreach(WSAL_DB_Occurrence::GetNewestUnique() as $occ){
			$log = $occ->GetLog();
			$data[] = array(
				'id'   => $occ->id,
				'read' => $occ->is_read,
				'type' => $log->type,
				'code' => $log->code,
				'date' => $occ->created_on,
				'mesg' => $occ->GetMessage(),
			);
		}

		function usort_reorder($a, $b){
			$orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'date';
			$order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'desc';
			$result = strcmp($a[$orderby], $b[$orderby]);
			return ($order === 'asc') ? $result : -$result;
		}
		usort($data, 'usort_reorder');

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
