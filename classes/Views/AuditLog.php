<?php

class WSAL_Views_AuditLog extends WSAL_AbstractView {
	/**
	 * @var WSAL_AuditLogListView
	 */
	protected $_listview;
	
	public function __construct(WpSecurityAuditLog $plugin) {
		parent::__construct($plugin);
		add_action('wp_ajax_AjaxInspector', array($this, 'AjaxInspector'));
		add_action('wp_ajax_AjaxRefresh', array($this, 'AjaxRefresh'));
		add_action('wp_ajax_AjaxSetIpp', array($this, 'AjaxSetIpp'));
		add_action('wp_ajax_AjaxSearchSite', array($this, 'AjaxSearchSite'));
		add_action('all_admin_notices', array($this, 'AdminNoticesNotificationsExtension'));
		
		$this->RegisterNotice('notifications-extension');
	}
	
	public function AdminNoticesNotificationsExtension() {
		$NotificationExtensionInstalled = $this->_plugin->licensing->IsLicenseValid('wsal-notifications-extension.php');
		$IsCurrentView = $this->_plugin->views->GetActiveView() == $this;
		if($IsCurrentView && !$this->IsNoticeDismissed('notifications-extension') && !$NotificationExtensionInstalled){
			?><div class="updated" data-notice-name="notifications-extension">
				<p><?php _e('Get notified instantly via email of important changes on your WordPress', 'wp-security-audit-log'); ?></p>
				<p>
					<?php $url = 'http://www.wpwhitesecurity.com/plugins-premium-extensions/email-notifications-wordpress/?utm_source=plugin&utm_medium=auditlogviewer&utm_campaign=notifications'; ?>
					<a href="<?php echo esc_attr($url); ?>" target="_blank"><?php _e('Learn More', 'wp-security-audit-log'); ?></a>
					| <a href="javascript:;" class="wsal-dismiss-notification"><?php _e('Dismiss this notice', 'wp-security-audit-log'); ?></a>
				</p>
			</div><?php
		}
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
	
	protected function GetListView(){
		if (is_null($this->_listview)) $this->_listview = new WSAL_AuditLogListView($this->_plugin);
		return $this->_listview;
	}
	
	public function Render(){
		if(!$this->_plugin->settings->CurrentUserCan('view')){
			wp_die( __( 'You do not have sufficient permissions to access this page.' , 'wp-security-audit-log') );
		}
		
		$this->GetListView()->prepare_items();
		
		?><form id="audit-log-viewer" method="post">
			<div id="audit-log-viewer-content">
				<input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
				<input type="hidden" id="wsal-cbid" name="wsal-cbid" value="<?php echo esc_attr(isset($_REQUEST['wsal-cbid']) ? $_REQUEST['wsal-cbid'] : '0'); ?>" />
				<?php do_action('wsal_auditlog_before_view', $this->GetListView()); ?>
				<?php $this->GetListView()->display(); ?>
				<?php do_action('wsal_auditlog_after_view', $this->GetListView()); ?>
			</div>
		</form><?php
		
		?><script type="text/javascript">
			jQuery(document).ready(function(){
				WsalAuditLogInit(<?php echo json_encode(array(
					'ajaxurl' => admin_url('admin-ajax.php'),
					'tr8n' => array(
						'numofitems' => __('Please enter the number of alerts you would like to see on one page:', 'wp-security-audit-log'),
						'searchback' => __('All Sites', 'wp-security-audit-log'),
						'searchnone' =>  __('No Results', 'wp-security-audit-log'),
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
		
		session_write_close(); // fixes session lock issue
		
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
	
	public function AjaxSearchSite(){
		if(!$this->_plugin->settings->CurrentUserCan('view'))
			die('Access Denied.');
		if(!isset($_REQUEST['search']))
			die('Search parameter expected.');
		
		$grp1 = array();
		$grp2 = array();
		
		$search = $_REQUEST['search'];
		
		foreach($this->GetListView()->get_sites() as $site){
			if(stripos($site->blogname, $search) !== false)
				$grp1[] = $site;
			else
				if(stripos($site->domain, $search) !== false)
					$grp2[] = $site;
		}
		
		die(json_encode(array_slice($grp1 + $grp2, 0, 7)));
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
		wp_enqueue_script('jquery');
		wp_enqueue_script('suggest');
		wp_enqueue_script(
			'auditlog',
			$this->_plugin->GetBaseUrl() . '/js/auditlog.js',
			array(),
			filemtime($this->_plugin->GetBaseDir() . '/js/auditlog.js')
		);
	}
}
