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
	
	const MAIN_VIEW = 'wsal-main';

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
		// add main menu
		add_menu_page(
			'WP Security Audit Log',
			'Security Audit Log',
			'manage_options', // admin & superadmin
			self::MAIN_VIEW,
			array($this, 'RenderViewBody'),
			count($this->views) ? $this->views[0]->GetIcon() : ''
		);
		
		// add menu items
		foreach($this->views as $i => $view){
			add_submenu_page(
				self::MAIN_VIEW,
				$view->GetTitle(),
				$view->GetName(),
				'manage_options', // admin & superadmin
				$i == 0 ? self::MAIN_VIEW : $view->GetSafeViewName(),
				array($this, 'RenderViewBody'),
				$view->GetIcon()
			);
		}
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
			<h2><?php _e($this->views[$view_id]->GetTitle()); ?></h2>
			<?php $this->views[$view_id]->Render(); ?>
		</div><?php
	}
	
}