<?php

class WSAL_Sensors_PluginsThemes extends WSAL_AbstractSensor {

	public function HookEvents() {
		add_action('switch_theme', array($this, 'EventThemeActivated'));
	}
	
	public function EventThemeActivated($themeName){
		$newtheme = null;
		foreach(wp_get_themes() as $theme){
			if($theme->Name == $themeName){
				$newtheme = $theme;
				break;
			}
		}
		$this->plugin->alerts->Trigger(5006, array(
			'NewTheme' => (object)array(
				'Name' => $newtheme->Name,
				'ThemeURI' => $newtheme->ThemeURI,
				'Description' => $newtheme->Description,
				'Author' => $newtheme->Author,
				'Version' => $newtheme->Version,
				'get_template_directory' => $newtheme->get_template_directory(),
			),
			'CurrentUserID' => wp_get_current_user()->ID,
		));
	}
	
}
