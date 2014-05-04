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
	
	protected $exec_data = array();
	protected $exec_info = array();
	
	public function HandleError($code, $message, $filename = 'unknown', $lineno = 0){
		if(!isset($this->exec_data['Errors']))
			$this->exec_data['Errors'] = array();
		$this->exec_data['Errors'] = new ErrorException($message, $code, 0, $filename, $lineno);
		return true;
	}
	
	protected function BeforeExecute(){
		$this->exec_info['_Time'] = microtime(true);
		$this->exec_info['_Mem'] = memory_get_usage();
	}
	
	protected function AfterExecute(){
		$this->exec_info['Time'] = microtime(true) - $this->exec_info['_Time'];
		$this->exec_info['Mem'] = memory_get_usage() - $this->exec_info['_Mem'];
		if($this->exec_info['Time'] < 0.001){
			$this->exec_info['Time'] = number_format($this->exec_info['Time'] * 1000, 3) . 'ms';
		}else{
			$this->exec_info['Time'] = number_format($this->exec_info['Time'], 3) . 's';
		}
		$this->exec_info['Mem'] = size_format($this->exec_info['Mem']);
		$this->exec_info['Queries'] = get_num_queries();
	}
	
	protected function Execute($code){
		try {
			error_reporting(-1);
			ini_set('display_errors', false);
			if(function_exists('xdebug_disable'))xdebug_disable();
			
			set_error_handler(array($this, 'HandleError'));
			
			ob_start();
			
			$this->BeforeExecute();
			$this->exec_data['Result'] = eval($code);
			$this->AfterExecute();
			
			$this->exec_data['Output'] = ob_get_clean();
			
			if(!$this->exec_data['Output'])
				unset($this->exec_data['Output']);
			
			restore_error_handler();
		} catch (Exception $ex) {
			if(!isset($this->exec_data['Errors']))
				$this->exec_data['Errors'] = array();
			$this->exec_data['Errors'][] = $ex;
		}
	}
	
	public function AjaxExecuteResponse(){
		echo '<!DOCTYPE html><html><head>';
		echo '<link rel="stylesheet" id="open-sans-css" href="' . $this->_plugin->GetBaseUrl() . '/css/nice_r.css" type="text/css" media="all">';
		echo '<script type="text/javascript" src="'.$this->_plugin->GetBaseUrl() . '/js/nice_r.js"></script>';
		echo '<style type="text/css">';
		echo 'html, body { margin: 0; padding: 0; }';
		echo '.nice_r { position: absolute; padding: 8px; }';
		echo '.nice_r a { overflow: visible; }';
		echo '.faerror { font: 14px Arial; background: #FCC; text-align: center; padding: 32px; }';
		echo '.einfo { list-style: none; margin: 0; font: 10px Tahoma; position: fixed; bottom: 0; left: 0; right: 0; background: #EEE; padding: 2px; border-top: 3px solid #FAFAFA; }';
		echo '.einfo li { float: left; padding-right: 4px; border-right: 1px solid #CCC; margin-right: 4px; }';
		echo '</style>';
		echo '</head><body>';
		
		if(($e = error_get_last()) && !isset($this->exec_data['Errors']) && !count($this->exec_data['Errors']))
			$this->HandleError($e['type'], $e['message'], $e['file'], $e['line']);
		
		if(count($this->exec_data)){
			$result = new WSAL_Nicer($this->exec_data);
			$result->render();
		}else echo '<div class="faerror">FATAL ERROR</div>';
		
		if(count($this->exec_info)){
			echo '<ul class="einfo">';
			foreach($this->exec_info as $key => $val){
				if($key && $key[0] != '_')
					echo '<li>'.esc_html($key).': '.esc_html($val).'</li>';
			}
			echo '</ul>';
		}
		
		echo '</body></html>';
	}
	
	public function AjaxExecute(){
		if(!$this->_plugin->settings->CurrentUserCan('view'))
			die('Access Denied.');
		if(!isset($_REQUEST['code']))
			die('Code parameter expected.');

		register_shutdown_function(array($this, 'AjaxExecuteResponse'));
		$this->Execute(stripslashes_deep($_REQUEST['code']));
		die;
	}
	
	public function Render(){
		$code = 'return wp_get_current_user();';
		?><form id="sandbox" method="post" target="execframe" action="<?php echo admin_url('admin-ajax.php'); ?>">
			<input type="hidden" name="action" value="AjaxExecute" />
			<div style="resize: vertical; height: 400px; overflow: auto; margin: 16px 0; padding-bottom: 12px;">
				<div style="overflow: hidden; height: 100%; position: relative; box-sizing:">
					<textarea name="code" style="resize: none; width: 49%; height: 100%; font: 12px Consolas; box-sizing: border-box;"><?php echo esc_html($code); ?></textarea>
					<iframe name="execframe" style="resize: none; width: 49%; height: 100%; border: 1px solid #ddd; background: #FFF; position: absolute; right: 0; box-sizing: border-box;"></iframe>
				</div>
			</div>
			<input style="" type="submit" name="submit" id="submit" class="button button-primary" value="Execute">
		</form><?php
	}
	
}