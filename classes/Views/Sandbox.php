<?php

class WSAL_Views_Sandbox extends WSAL_AbstractView {
	
	public function __construct(WpSecurityAuditLog $plugin) {
		parent::__construct($plugin);
		if(is_admin())add_action('wp_ajax_AjaxExecute', array($this, 'AjaxExecute'));
	}
	
	public function GetTitle() {
		return __('Sandbox', 'wp-security-audit-log');
	}
	
	public function GetIcon() {
		return 'dashicons-admin-generic';
	}
	
	public function GetName() {
		return __('Sandbox', 'wp-security-audit-log');
	}
	
	public function GetWeight() {
		return 5;
	}
	
	public function IsVisible(){
		return $this->_plugin->settings->CurrentUserCan('edit')
				&& $this->_plugin->settings->IsSandboxPageEnabled();
	}
	
	protected $exec_data = array();
	protected $exec_info = array();
	
	protected $snippets = array(
		'' => '',
		'Current WP User' => 'return wp_get_current_user();',
		
		'Clean PHP Error Events' => '
class OccurrenceCleanupTask extends WSAL_AbstractSandboxTask {
			
	protected $event_ids = array(0000, 0001, 0002, 0003, 0004, 0005);

	protected function Execute(){
		global $wpdb;
		$occs = WSAL_DB_Occurrence::LoadMulti(\'alert_id IN (\'.implode(\',\', $this->event_ids).\')\');
		$c = count($occs);
		$this->Message($c ? (\'Removing \' . $c . \' events...\') : \'No events to remove!\');
		foreach($occs as $i => $occ){
			$this->Message(\'Removing Event \' . $occ->id . \'...\', true);
			$occ->Delete();
			$this->Progress(($i + 1) / $c * 100);
		}
	}
}
new OccurrenceCleanupTask();',
		
		'Multisite Site Creator' => '
class DummySiteCreatorTask extends WSAL_AbstractSandboxTask {
			
	protected $sites_to_create = 100;
	protected $site_host = \'localhost\';
	protected $site_path = \'/wordpress-3.8/test$i/\';
	protected $site_name = \'Test $i\';

	protected function Execute(){
		global $wpdb;
		$l = $wpdb->get_var("SELECT blog_id FROM $wpdb->blogs ORDER BY blog_id DESC LIMIT 1") + 1;
		$this->Message(\'Creating \' . $this->sites_to_create . \' new sites...\');
		for($i = $l; $i <= $this->sites_to_create + $l; $i++){
			$this->Progress(($i - $l) / $this->sites_to_create * 100);
			wpmu_create_blog(
				str_replace(\'$i\', $i, $this->site_host),
				str_replace(\'$i\', $i, $this->site_path),
				str_replace(\'$i\', $i, $this->site_name),
			1);
		}
	}
}
new DummySiteCreatorTask();',
	);
	
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
		echo '</style>';
		echo '</head><body>';
		
		if(($e = error_get_last()) && (!isset($this->exec_data['Errors']) || !count($this->exec_data['Errors'])))
			$this->HandleError($e['type'], $e['message'], $e['file'], $e['line']);
		
		if(count($this->exec_data)){
			$result = new WSAL_Nicer($this->exec_data, true);
			$result->render();
		}else echo '<div class="faerror">FATAL ERROR</div>';
		
		if(count($this->exec_info)){
			?><script type="text/javascript">
				window.parent.SandboxUpdateState(<?php
					$res = array();
					foreach($this->exec_info as $key => $val)
						if($key && $key[0] != '_')
							$res[$key] = $val;
					echo json_encode($res);
				?>);
			</script><?php
		}
		
		echo '</body></html>';
	}
	
	public function AjaxExecute(){
		if(!$this->_plugin->settings->IsSandboxPageEnabled())
			die('Sandbox Disabled.');
		if(!$this->_plugin->settings->CurrentUserCan('edit'))
			die('Access Denied.');
		if(!isset($_REQUEST['code']))
			die('Code parameter expected.');

		register_shutdown_function(array($this, 'AjaxExecuteResponse'));
		$this->Execute(stripslashes_deep($_REQUEST['code']));
		die;
	}
	
	public function Render(){
		$snpt = isset($_REQUEST['snippet']) ? $_REQUEST['snippet'] : '';
		$code = isset($this->snippets[$snpt]) ? $this->snippets[$snpt] : '';
		?><form id="sandbox" method="post" target="execframe" action="<?php echo admin_url('admin-ajax.php'); ?>">
			<input type="hidden" name="action" value="AjaxExecute" />
			<div id="sandbox-wrap-wrap">
				<div id="sandbox-wrap">
					<textarea name="code" id="sandbox-code"><?php echo esc_html($code); ?></textarea>
					<iframe id="sandbox-result" name="execframe"></iframe>
				</div>
				<div id="sandbox-status">Ready.</div>
			</div>
			<label for="sandbox-snippet" style="float: left; line-height: 26px; display: inline-block; margin-right: 32px; border-right: 1px dotted #CCC; padding-right: 32px;">
				Use Snippet: 
				<?php $code = json_encode(admin_url('admin.php?page=wsal-sandbox') . '&snippet='); ?>
				<select id="sandbox-snippet" onchange="location = <?php echo esc_attr($code); ?> + encodeURIComponent(this.value);"><?php
					foreach(array_keys($this->snippets) as $name){
						?><option value="<?php echo esc_attr($name); ?>"<?php if($name == $snpt)echo ' selected="selected"'; ?>><?php _e($name, 'wp-security-audit-log'); ?></option><?php
					}
				?></select>
			</label>
			<input type="submit" name="submit" id="sandbox-submit" class="button button-primary" value="Execute">
			<img id="sandbox-loader" style="margin: 6px 12px; display: none;" src="http://cdnjs.cloudflare.com/ajax/libs/jstree/3.0.0-beta10/themes/default/throbber.gif" width="16" height="16" alt="Loading..."/>
		</form><?php
	}
	
	public function Header(){
		?><link rel="stylesheet" href="//cdn.jsdelivr.net/codemirror/4.0.3/codemirror.css">
		<style type="text/css">
			#sandbox-wrap-wrap {
				resize: vertical; height: 400px; overflow: auto; margin: 16px 0; padding-bottom: 16px; position: relative; border: 1px solid #DDD;
			}
			#sandbox-wrap {
				overflow: hidden; height: 100% !important; position: relative; box-sizing: border-box;
			}
			#sandbox-wrap textarea,
			#sandbox-wrap .CodeMirror {
				resize: none; width: 50%; height: 100%; border-bottom: 1px solid #ddd; font: 12px Consolas; box-sizing: border-box;
			}
			#sandbox-wrap iframe {
				resize: none; width: 50%; height: 100%; border-bottom: 1px solid #ddd; background: #FFF; position: absolute; top: 0; right: 0; box-sizing: border-box; border-left: 4px solid #DDD;
			}
			#sandbox-status {
				font: 10px Tahoma; padding: 2px; position: absolute; left: 0; right: 16px; bottom: 0;
			}
			#sandbox-status ul {
				list-style: none; margin: 0;
			}
			#sandbox-status li {
				float: left; padding-right: 4px; border-right: 1px solid #CCC; margin: 0 4px 0 0;
			}
		</style><?php
	}
	
	public function Footer(){
		?><script src="//cdn.jsdelivr.net/codemirror/4.0.3/codemirror.js"></script>
		<script src="//cdn.jsdelivr.net/codemirror/4.0.3/addon/edit/matchbrackets.js"></script>
		<script src="//cdn.jsdelivr.net/codemirror/4.0.3/mode/clike/clike.js"></script>
		<script src="//cdn.jsdelivr.net/codemirror/4.0.3/mode/php/php.js"></script>
		<script type="text/javascript">
			jQuery(document).ready(function(){
				var ed = CodeMirror.fromTextArea(
					jQuery('#sandbox-code')[0],
					{
						lineNumbers: true,
						matchBrackets: true,
						mode: "text/x-php",
						indentUnit: 4,
						indentWithTabs: true
					}
				);
				
				jQuery('#sandbox').submit(function(){
					if(!ed.isClean())jQuery('#sandbox-snippet').val('');
					jQuery('#sandbox-loader').show();
				});
				
				jQuery('#sandbox-result').on('load error', function(){
					jQuery('#sandbox-loader').hide();
				});
				
				//jQuery('#sandbox').submit();
			});
			
			function SandboxUpdateState(data){
				var ul = jQuery('<ul/>');
				for(var key in data)
					ul.append(jQuery('<li/>').text(key + ': ' + data[key]));
				jQuery('#sandbox-status').html('').append(ul);
			}
		</script><?php
	}
	
}