<?php

class WSAL_ViewManager {
	
	/**
	 * @var WSAL_ViewInterface[] 
	 */
	public $views = array();
	
	/**
	 * @var WpSecurityAuditLog
	 */
	protected $plugin;

	public function __construct(WpSecurityAuditLog $plugin){
		$this->plugin = $plugin;
		
		// load views
		foreach(glob(dirname(__FILE__) . '/Views/*.php') as $file){
			$class = $plugin->GetClassFileClassName($file);
			$tmp = new $class($plugin);
			$tmp->SetPlugin($plugin);
			$this->views[] = $tmp;
		}
		
		// order views by weight
		usort($this->views, array($this, 'OrderByWeight'));
		
		// add menus
		add_action('admin_menu', array($this, 'AddAdminMenus'));
	}
	
	public function OrderByWeight(WSAL_ViewInterface $a, WSAL_ViewInterface $b){
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
			'Audit Log',
			'manage_options', // admin & superadmin
			'wsal-main',
			array($this, 'Render_0'),
			'dashicons-welcome-view-site'
		);
		
		// add menu items
		foreach($this->views as $i => $view){
			add_submenu_page(
				'wsal-main',
				$view->GetTitle(),
				$view->GetName(),
				'manage_options', // admin & superadmin
				$i == 0 ? 'wsal-main' : 'wsal-' . strtolower($view->GetName()),
				array($this, 'Render_' . $i),
				$view->GetIcon()
			);
		}
	}
	
	public function __call($name, $args){
		$name = explode('_', $name, 2);
		if(count($name) == 2 && $name[0] == 'Render'){
			$name = (int)$name[1];
			?><div class="wrap">
				<h2><?php _e($this->views[$name]->GetTitle()); ?></h2>
				<?php $this->views[$name]->Render(); ?>
			</div><?php
		}
	}
	
}