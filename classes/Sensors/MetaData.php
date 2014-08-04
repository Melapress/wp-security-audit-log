<?php

class WSAL_Sensors_MetaData extends WSAL_AbstractSensor {

	public function HookEvents() {
		add_action('added_post_meta', array($this,   'EventPostMetaCreated'), 10, 4);
		add_action('update_post_meta', array($this,  'EventPostMetaUpdating'), 10, 3);
		add_action('updated_post_meta', array($this, 'EventPostMetaUpdated'), 10, 4);
		add_action('deleted_post_meta', array($this, 'EventPostMetaDeleted'), 10, 4);
	}
	
	protected $old_meta = array();
	
	protected function CanLogPostMeta($meta_id, $object_id, $meta_key){
		return substr($meta_key, 0, 1) != '_';
	}
	
	public function EventPostMetaCreated($meta_id, $object_id, $meta_key, $_meta_value){
		$post = get_post($object_id);
		
		if(!$this->CanLogPostMeta($meta_id, $object_id, $meta_key))return;
		
		switch($post->post_type){
			case 'page':
				$this->plugin->alerts->Trigger(2059, array(
					'PostID' => $object_id,
					'PostTitle' => $post->post_title,
					'MetaID' => $meta_id,
					'MetaKey' => $meta_key,
					'MetaValue' => $_meta_value,
				));
				break;
			case 'post':
				$this->plugin->alerts->Trigger(2053, array(
					'PostID' => $object_id,
					'PostTitle' => $post->post_title,
					'MetaID' => $meta_id,
					'MetaKey' => $meta_key,
					'MetaValue' => $_meta_value,
				));
				break;
			default:
				$this->plugin->alerts->Trigger(2056, array(
					'PostID' => $object_id,
					'PostTitle' => $post->post_title,
					'PostType' => $post->post_type,
					'MetaID' => $meta_id,
					'MetaKey' => $meta_key,
					'MetaValue' => $_meta_value,
				));
				break;
		}
	}
	
	public function EventPostMetaUpdating($meta_id, $object_id, $meta_key){
		static $meta_type = 'post';
		$this->old_meta[$meta_id] = (object)array(
			'key' => ($meta = get_metadata_by_mid($meta_type, $meta_id)) ? $meta->meta_key : $meta_key,
			'val' => get_metadata($meta_type, $object_id, $meta_key, true),
		);
	}
	
	public function EventPostMetaUpdated($meta_id, $object_id, $meta_key, $_meta_value){
		$post = get_post($object_id);
		
		if(!$this->CanLogPostMeta($meta_id, $object_id, $meta_key))return;
		
		if(isset($this->old_meta[$meta_id])){
			
			// check change in meta key
			if($this->old_meta[$meta_id]->key != $meta_key){
				switch($post->post_type){
					case 'page':
						$this->plugin->alerts->Trigger(2064, array(
							'PostID' => $object_id,
							'PostTitle' => $post->post_title,
							'MetaID' => $meta_id,
							'MetaKeyNew' => $meta_key,
							'MetaKeyOld' => $this->old_meta[$meta_id]->key,
							'MetaValue' => $_meta_value,
						));
						break;
					case 'post':
						$this->plugin->alerts->Trigger(2062, array(
							'PostID' => $object_id,
							'PostTitle' => $post->post_title,
							'MetaID' => $meta_id,
							'MetaKeyNew' => $meta_key,
							'MetaKeyOld' => $this->old_meta[$meta_id]->key,
							'MetaValue' => $_meta_value,
						));
						break;
					default:
						$this->plugin->alerts->Trigger(2063, array(
							'PostID' => $object_id,
							'PostTitle' => $post->post_title,
							'PostType' => $post->post_type,
							'MetaID' => $meta_id,
							'MetaKeyNew' => $meta_key,
							'MetaKeyOld' => $this->old_meta[$meta_id]->key,
							'MetaValue' => $_meta_value,
						));
						break;
				}
			}
			else
			// check change in meta value
			if($this->old_meta[$meta_id]->val != $_meta_value){
				switch($post->post_type){
					case 'page':
						$this->plugin->alerts->Trigger(2060, array(
							'PostID' => $object_id,
							'PostTitle' => $post->post_title,
							'MetaID' => $meta_id,
							'MetaKey' => $meta_key,
							'MetaValueNew' => $_meta_value,
							'MetaValueOld' => $this->old_meta[$meta_id]->val,
						));
						break;
					case 'post':
						$this->plugin->alerts->Trigger(2054, array(
							'PostID' => $object_id,
							'PostTitle' => $post->post_title,
							'MetaID' => $meta_id,
							'MetaKey' => $meta_key,
							'MetaValueNew' => $_meta_value,
							'MetaValueOld' => $this->old_meta[$meta_id]->val,
						));
						break;
					default:
						$this->plugin->alerts->Trigger(2057, array(
							'PostID' => $object_id,
							'PostTitle' => $post->post_title,
							'PostType' => $post->post_type,
							'MetaID' => $meta_id,
							'MetaKey' => $meta_key,
							'MetaValueNew' => $_meta_value,
							'MetaValueOld' => $this->old_meta[$meta_id]->val,
						));
						break;
				}
			}
			
			// remove old meta update data
			unset($this->old_meta[$meta_id]);
		}
	}
	
	public function EventPostMetaDeleted($meta_ids, $object_id, $meta_key, $_meta_value){
		$post = get_post($object_id);
		
		foreach($meta_ids as $meta_id){
			
			if(!$this->CanLogPostMeta($meta_id, $object_id, $meta_key))continue;
			
			switch($post->post_type){
				case 'page':
					$this->plugin->alerts->Trigger(2061, array(
						'PostID' => $object_id,
						'PostTitle' => $post->post_title,
						'MetaID' => $meta_id,
						'MetaKey' => $meta_key,
						'MetaValue' => $_meta_value,
					));
					break;
				case 'post':
					$this->plugin->alerts->Trigger(2055, array(
						'PostID' => $object_id,
						'PostTitle' => $post->post_title,
						'MetaID' => $meta_id,
						'MetaKey' => $meta_key,
						'MetaValue' => $_meta_value,
					));
					break;
				default:
					$this->plugin->alerts->Trigger(2058, array(
						'PostID' => $object_id,
						'PostTitle' => $post->post_title,
						'PostType' => $post->post_type,
						'MetaID' => $meta_id,
						'MetaKey' => $meta_key,
						'MetaValue' => $_meta_value,
					));
					break;
			}
		}
	}
}
