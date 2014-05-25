<?php

class WSAL_ViewManager {
	
	/**
	 * @var WSAL_AbstractView[] 
	 */
	public $views = array();
	
	/**
	 * @var WpSecurityAuditLog
	 */
	protected $_plugin;

	public function __construct(WpSecurityAuditLog $plugin){
		$this->_plugin = $plugin;
		
		// load views
		foreach(glob(dirname(__FILE__) . '/Views/*.php') as $file){
			$class = $this->_plugin->GetClassFileClassName($file);
			$tmp = new $class($this->_plugin);
			$this->views[] = $tmp;
		}
		
		// order views by weight
		usort($this->views, array($this, 'OrderByWeight'));
		
		// add menus
		add_action('admin_menu', array($this, 'AddAdminMenus'));
		add_action('network_admin_menu', array($this, 'AddAdminMenus'));
		
		// add plugin shortcut links
		add_filter('plugin_action_links_' . $plugin->GetBaseName(), array($this, 'AddPluginShortcuts'));
		
		// render header
		add_action('admin_enqueue_scripts', array($this, 'RenderViewHeader'));
		
		// render footer
		add_action('admin_footer', array($this, 'RenderViewFooter'));
	}
	
	public function OrderByWeight(WSAL_AbstractView $a, WSAL_AbstractView $b){
		$wa = $a->GetWeight();
		$wb = $b->GetWeight();
		switch(true){
			case $wa < $wb:
				return -1;
			case $wa > $wb:
				return 1;
			default:
				return 0;
		}
	}
	
	public function AddAdminMenus(){
		if($this->_plugin->settings->CurrentUserCan('view') && count($this->views)){
			// add main menu
			add_menu_page(
				'WP Security Audit Log',
				'Audit Log',
				'read', // no capability requirement
				$this->views[0]->GetSafeViewName(),
				array($this, 'RenderViewBody'),
				$this->views[0]->GetIcon()
			);

			// add menu items
			foreach($this->views as $view){
				if($view->IsVisible()){
					add_submenu_page(
						$this->views[0]->GetSafeViewName(),
						$view->GetTitle(),
						$view->GetName(),
						'read', // no capability requirement
						$view->GetSafeViewName(),
						array($this, 'RenderViewBody'),
						$view->GetIcon()
					);
				}
			}
		}
	}
	
	public function AddPluginShortcuts($old_links){
		$new_links = array();
		foreach($this->views as $view){
			if($view->HasPluginShortcutLink()){
				$new_links[] =
					'<a href="'
							. admin_url('admin.php?page='
								. $view->GetSafeViewName()
							) . '">'
						. $view->GetName()
					. '</a>';
			}
		}
		return array_merge($new_links, $old_links);
	}
	
	protected function GetBackendPageIndex(){
		if(isset($_REQUEST['page']))
			foreach($this->views as $i => $view)
				if($_REQUEST['page'] == $view->GetSafeViewName())
					return $i;
		return 0;
	}
	
	public function RenderViewHeader(){
		$view_id = $this->GetBackendPageIndex();
		$this->views[$view_id]->Header();
	}
	
	public function RenderViewFooter(){
		$view_id = $this->GetBackendPageIndex();
		$this->views[$view_id]->Footer();
	}
	
	public function RenderViewBody(){
		$view_id = $this->GetBackendPageIndex();
		?><div class="wrap">
			<div id="icon-plugins" class="icon32"><br></div>
			<h2><?php _e($this->views[$view_id]->GetTitle(), 'wp-security-audit-log'); ?></h2>
			<?php $this->views[$view_id]->Render(); ?>
		</div><?php
	}
	
}