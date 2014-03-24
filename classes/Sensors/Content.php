<?php

class WSAL_Sensors_Content extends WSAL_AbstractSensor {

	public function HookEvents() {
		add_action('transition_post_status', array($this, 'EventPostStatusChange'), 10, 3);
	}
	
	public function EventPostStatusChange($newStatus, $oldStatus, $post){
		/*$this->plugin->alerts->Trigger(2045, array(
			'WidgetName' => $wName,
			'OldSidebar' => $fromSidebar,
			'NewSidebar' => $toSidebar,
		));*/
	}
}