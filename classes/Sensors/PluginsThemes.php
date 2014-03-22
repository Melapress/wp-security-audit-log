<?php

class WSAL_Sensors_PluginsThemes extends WSAL_AbstractSensor {

	public function HookEvents() {
		add_action('switch_theme', array($this, 'EventThemeActivated'));
	}
	
	public function EventThemeActivated($themeName){
		$themes = wp_get_themes();
		$this->plugin->alerts->Trigger(5006, array(
			'NewTheme' => isset($themes[$themeName]) ? $themes[$themeName] : null,
			'CurrentUserID' => wp_get_current_user()->ID,
		));
	}
	
}
