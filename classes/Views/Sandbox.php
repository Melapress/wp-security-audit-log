<?php

class WSAL_Views_Sandbox extends WSAL_AbstractView {
	
	public function __construct(WpSecurityAuditLog $plugin) {
		parent::__construct($plugin);
		add_action('wp_ajax_AjaxExecute', array($this, 'AjaxExecute'));
	}
	
	public function GetTitle() {
		return 'Sandbox';
	}
	
	public function GetIcon() {
		return 'dashicons-admin-generic';
	}
	
	public function GetName() {
		return 'Sandbox';
	}
	
	public function GetWeight() {
		return 5;
	}
	
	protected $exec_errors = array();
	protected $exec_result = null;
	
	public function HandleError($code, $message, $filename = 'unknown', $lineno = 0){
		$this->exec_errors[] = new ErrorException($message, $code, 0, $filename, $lineno);
		return true;
	}
	
	protected function Execute($code){
		try {
			error_reporting(-1);
			ini_set('display_errors', false);
			if(function_exists('xdebug_disable'))xdebug_disable();
			set_error_handler(array($this, 'HandleError'));
			ob_start();
			$this->exec_result = eval($code);
			ob_end_clean();
			if(($e = error_get_last()) && !count($this->exec_errors))
				$this->HandleError($e['type'], $e['message'], $e['file'], $e['line']);
			restore_error_handler();
		} catch (Exception $ex) {
			$this->exec_errors[] = $ex;
		}
	}
	
	public function AjaxExecute(){
		if(!$this->_plugin->settings->CurrentUserCan('view'))
			die('Access Denied.');
		if(!isset($_REQUEST['code']))
			die('Code parameter expected.');

		echo '<!DOCTYPE html><html><head>';
		echo '<link rel="stylesheet" id="open-sans-css" href="' . $this->_plugin->GetBaseUrl() . '/css/nice_r.css" type="text/css" media="all">';
		echo '<script type="text/javascript" src="'.$this->_plugin->GetBaseUrl() . '/js/nice_r.js"></script>';
		echo '<style type="text/css">';
		echo 'html, body { margin: 0; padding: 0; }';
		echo '.nice_r { position: absolute; padding: 8px; }';
		echo '.nice_r a { overflow: visible; }';
		echo '</style>';
		echo '</head><body>';
		
		$this->Execute(stripslashes_deep($_REQUEST['code']));
		
		switch(true){
			case !count($this->exec_errors) && isset($this->exec_result):
				$result = $this->exec_result;
				break;
			case count($this->exec_errors) && isset($this->exec_result):
				$result = array('Result' => $this->exec_result, 'Errors' => $this->exec_errors);
				break;
			case count($this->exec_errors) && !isset($this->exec_result):
				$result = $this->exec_errors;
				break;
		}
		
		if(isset($result)){
			$result = new WSAL_Nicer($result);
			$result->render();
		}else echo 'FATAL ERROR';
		
		echo '</body></html>';
		die;
	}
	
	public function Render(){
		?><form id="sandbox" method="post" target="execframe" action="<?php echo admin_url('admin-ajax.php'); ?>">
			<input type="hidden" name="action" value="AjaxExecute" />
			<div>
				<textarea name="code" style="width: 49%; height: 200px; font: 12px Consolas;">return wp_get_current_user();</textarea>
				<iframe name="execframe" style="width: 49%; height: 200px; border: 1px solid #ddd; background: #FFF; position: absolute; right: 16px; box-sizing: border-box;"></iframe>
			</div>
			<input style="" type="submit" name="submit" id="submit" class="button button-primary" value="Execute">
		</form><?php
	}
	
}