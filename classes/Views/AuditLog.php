<?php

require_once(ABSPATH . 'wp-admin/includes/template.php');
require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');

class WSAL_Views_AuditLog extends WP_List_Table implements WSAL_ViewInterface {
	
	public function SetPlugin(WpSecurityAuditLog $plugin) {
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
		$this->prepare_items();
		?><form id="audit-log-viewer" method="get">
			<input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
			<?php $this->display() ?>
		</form><?php
	}
	
	// <editor-fold desc="WP_List_Table Methods">
	
	public function __construct(){
		parent::__construct(array(
			'singular'  => 'log',
			'plural'    => 'logs',
			'ajax'      => true
		));
	}
	
	public function get_columns(){
		return array(
			'cb'   => '<input type="checkbox" />',
			'read' => 'Read',
			'type' => 'Type',
			'code' => 'Code',
			'date' => 'Date',
			'mesg' => 'Message',
		);
	}
	
	public function column_cb($item){
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			esc_attr($this->_args['singular']),
			$item['ID']
		);
	}

	public function column_default($item, $column_name){
		switch(true){
			case $column_name == 'date':
				return date('r', $item[$column_name]);
			case isset($item[$column_name]):
				return $item[$column_name];
			default:
				return '<pre>' . esc_html(print_r($item, true)) . '</pre>';
		}
	}
	
	public function prepare_items() {
		$per_page = 5;

		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array($columns, $hidden, $sortable);

		//$this->process_bulk_action();

		$data = array(
			array(
				'read' => false,
				'type' => E_NOTICE,
				'code' => 1000,
				'date' => strtotime('4 Feb 2014'),
				'mesg' => 'A new event',
			),
			array(
				'read' => false,
				'type' => E_NOTICE,
				'code' => 1001,
				'date' => strtotime('4 Dec 2013'),
				'mesg' => 'An older event',
			),
		);

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

	// </editor-fold>

}