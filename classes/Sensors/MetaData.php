<?php
/**
 * Custom fields (posts, pages, custom posts and users) sensor.
 *
 * 2053 User created a custom field for a post
 * 2056 User created a custom field for a custom post type
 * 2059 User created a custom field for a page
 * 2062 User updated a custom field name for a post
 * 2063 User updated a custom field name for a custom post type
 * 2064 User updated a custom field name for a page
 * 2060 User updated a custom field value for a page
 * 2057 User updated a custom field for a custom post type
 * 2054 User updated a custom field value for a post
 * 2055 User deleted a custom field from a post
 * 2058 User deleted a custom field from a custom post type
 * 2061 User deleted a custom field from a page
 *
 * @package Wsal
 * @subpackage Sensors
 * @since 1.0.0
 */
class WSAL_Sensors_MetaData extends WSAL_AbstractSensor {

	/**
	 * Array of meta data being updated.
	 *
	 * @var array
	 */
	protected $old_meta = array();

	/**
	 * Empty meta counter.
	 *
	 * @var int
	 */
	private $null_meta_counter = 0;

	/**
	 * Listening to events using WP hooks.
	 */
	public function HookEvents() {
		add_action( 'add_post_meta', array( $this, 'EventPostMetaCreated' ), 10, 3 );
		add_action( 'update_post_meta', array( $this, 'EventPostMetaUpdating' ), 10, 3 );
		add_action( 'updated_post_meta', array( $this, 'EventPostMetaUpdated' ), 10, 4 );
		add_action( 'deleted_post_meta', array( $this, 'EventPostMetaDeleted' ), 10, 4 );
		add_action( 'save_post', array( $this, 'reset_null_meta_counter' ), 10 );

		add_action( 'add_user_meta', array( $this, 'event_user_meta_created' ), 10, 3 );
		add_action( 'update_user_meta', array( $this, 'event_user_meta_updating' ), 10, 3 );
		add_action( 'updated_user_meta', array( $this, 'event_user_meta_updated' ), 10, 4 );
		add_action( 'user_register', array( $this, 'reset_null_meta_counter' ), 10 );
	}

	/**
	 * Check "Excluded Custom Fields" or meta keys starts with "_".
	 *
	 * @param int 	 $object_id - Object ID.
	 * @param string $meta_key - Meta key.
	 * @return boolean can log true|false
	 */
	protected function CanLogMetaKey( $object_id, $meta_key ) {
		// Check if excluded meta key or starts with _.
		if ( substr( $meta_key, 0, 1 ) == '_' ) {
			return false;
		} elseif ( $this->IsExcludedCustomFields( $meta_key ) ) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Check "Excluded Custom Fields".
	 * Used in the above function.
	 *
	 * @param string $custom - Custom meta key.
	 * @return boolean is excluded from monitoring true|false
	 */
	public function IsExcludedCustomFields( $custom ) {
		$customFields = $this->plugin->settings->GetExcludedMonitoringCustom();
		if ( in_array( $custom, $customFields ) ) {
			return true;
		}
		foreach ( $customFields as $field ) {
			if ( false !== strpos( $field, "*" ) ) {
				// wildcard str[any_character] when you enter (str*)
				if ( substr( $field, -1 ) == '*') {
					$field = rtrim( $field, '*' );
					if ( preg_match( "/^$field/", $custom ) ) {
						return true;
					}
				}
				// Wildcard [any_character]str when you enter (*str).
				if ( '*' == substr( $field, 0, 1 ) ) {
					$field = ltrim( $field, '*' );
					if ( preg_match( "/$field$/", $custom ) ) {
						return true;
					}
				}
			}
		}
		return false;
		// return (in_array($custom, $customFields)) ? true : false;
	}

	/**
	 * Created a custom field.
	 *
	 * @param int 	 $object_id - Object ID.
	 * @param string $meta_key - Meta key.
	 * @param mix 	 $meta_value - Meta value.
	 */
	public function EventPostMetaCreated( $object_id, $meta_key, $meta_value ) {
		$post = get_post( $object_id );
		if ( ! $this->CanLogMetaKey( $object_id, $meta_key ) || is_array( $meta_value ) ) {
			return;
		}

		if ( 'revision' == $post->post_type ) {
			return;
		}

		if ( empty( $meta_value ) && ( $this->null_meta_counter < 1 ) ) { // Report only one NULL meta value.
			$this->null_meta_counter += 1;
		} else { // Do not report if NULL meta values are more than one.
			return;
		}

		$wp_action = array( 'add-meta' );

		if ( isset( $_POST['action'] ) && ( 'editpost' == $_POST['action'] || in_array( $_POST['action'], $wp_action ) ) ) {
			$editorLink = $this->GetEditorLink( $post );
			switch ( $post->post_type ) {
				case 'page':
					$this->plugin->alerts->Trigger( 2059, array(
						'PostID' => $object_id,
						'PostTitle' => $post->post_title,
						'MetaKey' => $meta_key,
						'MetaValue' => $meta_value,
						'MetaLink' => $meta_key,
						$editorLink['name'] => $editorLink['value'],
					) );
					break;
				case 'post':
					$this->plugin->alerts->Trigger( 2053, array(
						'PostID' => $object_id,
						'PostTitle' => $post->post_title,
						'MetaKey' => $meta_key,
						'MetaValue' => $meta_value,
						'MetaLink' => $meta_key,
						$editorLink['name'] => $editorLink['value'],
					) );
					break;
				default:
					$this->plugin->alerts->Trigger( 2056, array(
						'PostID' => $object_id,
						'PostTitle' => $post->post_title,
						'PostType' => $post->post_type,
						'MetaKey' => $meta_key,
						'MetaValue' => $meta_value,
						'MetaLink' => $meta_key,
						$editorLink['name'] => $editorLink['value'],
					) );
					break;
			}
		}
	}

	/**
	 * Sets the old meta.
	 *
	 * @param int 	 $meta_id - Meta ID.
	 * @param int 	 $object_id - Object ID.
	 * @param string $meta_key - Meta key.
	 */
	public function EventPostMetaUpdating( $meta_id, $object_id, $meta_key ) {
		static $meta_type = 'post';
		$this->old_meta[ $meta_id ] = (object) array(
			'key' => ( $meta = get_metadata_by_mid( $meta_type, $meta_id ) ) ? $meta->meta_key : $meta_key,
			'val' => get_metadata( $meta_type, $object_id, $meta_key, true ),
		);
	}

	/**
	 * Updated a custom field name/value.
	 *
	 * @param int 	 $meta_id - Meta ID.
	 * @param int 	 $object_id - Object ID.
	 * @param string $meta_key - Meta key.
	 * @param mix 	 $meta_value - Meta value.
	 */
	public function EventPostMetaUpdated( $meta_id, $object_id, $meta_key, $meta_value ) {
		$post = get_post( $object_id );
		if ( ! $this->CanLogMetaKey( $object_id, $meta_key ) || is_array( $meta_value ) ) {
			return;
		}

		if ( 'revision' == $post->post_type ) {
			return;
		}

		$wp_action = array( 'add-meta' );

		if ( isset( $_POST['action'] ) && ( 'editpost' == $_POST['action'] || in_array( $_POST['action'], $wp_action ) ) ) {
			$editorLink = $this->GetEditorLink( $post );
			if ( isset( $this->old_meta[ $meta_id ] ) ) {
				// Check change in meta key.
				if ( $this->old_meta[ $meta_id ]->key != $meta_key ) {
					switch ( $post->post_type ) {
						case 'page':
							$this->plugin->alerts->Trigger( 2064, array(
								'PostID' => $object_id,
								'PostTitle' => $post->post_title,
								'MetaID' => $meta_id,
								'MetaKeyNew' => $meta_key,
								'MetaKeyOld' => $this->old_meta[$meta_id]->key,
								'MetaValue' => $meta_value,
								'MetaLink' => $meta_key,
								$editorLink['name'] => $editorLink['value'],
							) );
							break;
						case 'post':
							$this->plugin->alerts->Trigger( 2062, array(
								'PostID' => $object_id,
								'PostTitle' => $post->post_title,
								'MetaID' => $meta_id,
								'MetaKeyNew' => $meta_key,
								'MetaKeyOld' => $this->old_meta[$meta_id]->key,
								'MetaValue' => $meta_value,
								'MetaLink' => $meta_key,
								$editorLink['name'] => $editorLink['value'],
							) );
							break;
						default:
							$this->plugin->alerts->Trigger( 2063, array(
								'PostID' => $object_id,
								'PostTitle' => $post->post_title,
								'PostType' => $post->post_type,
								'MetaID' => $meta_id,
								'MetaKeyNew' => $meta_key,
								'MetaKeyOld' => $this->old_meta[$meta_id]->key,
								'MetaValue' => $meta_value,
								'MetaLink' => $smeta_key,
								$editorLink['name'] => $editorLink['value'],
							) );
							break;
					}
				} elseif ( $this->old_meta[ $meta_id ]->val != $meta_value ) { // Check change in meta value.
					switch ( $post->post_type ) {
						case 'page':
							$this->plugin->alerts->Trigger( 2060, array(
								'PostID' => $object_id,
								'PostTitle' => $post->post_title,
								'MetaID' => $meta_id,
								'MetaKey' => $meta_key,
								'MetaValueNew' => $meta_value,
								'MetaValueOld' => $this->old_meta[$meta_id]->val,
								'MetaLink' => $meta_key,
								$editorLink['name'] => $editorLink['value'],
							) );
							break;
						case 'post':
							$this->plugin->alerts->Trigger( 2054, array(
								'PostID' => $object_id,
								'PostTitle' => $post->post_title,
								'MetaID' => $meta_id,
								'MetaKey' => $meta_key,
								'MetaValueNew' => $meta_value,
								'MetaValueOld' => $this->old_meta[$meta_id]->val,
								'MetaLink' => $meta_key,
								$editorLink['name'] => $editorLink['value'],
							) );
							break;
						default:
							$this->plugin->alerts->Trigger( 2057, array(
								'PostID' => $object_id,
								'PostTitle' => $post->post_title,
								'PostType' => $post->post_type,
								'MetaID' => $meta_id,
								'MetaKey' => $meta_key,
								'MetaValueNew' => $meta_value,
								'MetaValueOld' => $this->old_meta[$meta_id]->val,
								'MetaLink' => $meta_key,
								$editorLink['name'] => $editorLink['value'],
							) );
							break;
					}
				}
				// Remove old meta update data.
				unset( $this->old_meta[ $meta_id ] );
			}
		}
	}

	/**
	 * Deleted a custom field.
	 *
	 * @param int 	 $meta_ids - Meta IDs.
	 * @param int 	 $object_id - Object ID.
	 * @param string $meta_key - Meta key.
	 * @param mix 	 $meta_value - Meta value.
	 */
	public function EventPostMetaDeleted( $meta_ids, $object_id, $meta_key, $meta_value ) {

		// If meta key starts with "_" then return.
		if ( '_' == substr( $meta_key, 0, 1 ) ) {
			return;
		}

		$post = get_post( $object_id );

		$wp_action = array( 'delete-meta' );

		if ( isset( $_POST['action'] ) && in_array( $_POST['action'], $wp_action ) ) {
			$editorLink = $this->GetEditorLink( $post );
			foreach ( $meta_ids as $meta_id ) {
				if ( ! $this->CanLogMetaKey( $object_id, $meta_key ) ) {
					continue;
				}
				switch ( $post->post_type ) {
					case 'page':
						$this->plugin->alerts->Trigger( 2061, array(
							'PostID' => $object_id,
							'PostTitle' => $post->post_title,
							'MetaID' => $meta_id,
							'MetaKey' => $meta_key,
							'MetaValue' => $meta_value,
							$editorLink['name'] => $editorLink['value'],
						) );
						break;
					case 'post':
						$this->plugin->alerts->Trigger( 2055, array(
							'PostID' => $object_id,
							'PostTitle' => $post->post_title,
							'MetaID' => $meta_id,
							'MetaKey' => $meta_key,
							'MetaValue' => $meta_value,
							$editorLink['name'] => $editorLink['value'],
						) );
						break;
					default:
						$this->plugin->alerts->Trigger( 2058, array(
							'PostID' => $object_id,
							'PostTitle' => $post->post_title,
							'PostType' => $post->post_type,
							'MetaID' => $meta_id,
							'MetaKey' => $meta_key,
							'MetaValue' => $meta_value,
							$editorLink['name'] => $editorLink['value'],
						) );
						break;
				}
			}
		}
	}

	/**
	 * Method: Reset Null Meta Counter.
	 *
	 * @since 2.6.5
	 */
	public function reset_null_meta_counter() {
		$this->null_meta_counter = 0;
	}

	/**
	 * Get editor link.
	 *
	 * @param stdClass $post the post.
	 * @return array $aLink name and value link
	 */
	private function GetEditorLink( $post ) {
		$name = 'EditorLink';
		$name .= ( 'page' == $post->post_type ) ? 'Page' : 'Post';
		$value = get_edit_post_link( $post->ID );
		$aLink = array(
			'name' => $name,
			'value' => $value,
		);
		return $aLink;
	}

	/**
	 * Create a custom field name/value.
	 *
	 * @param int 	 $object_id - Object ID.
	 * @param string $meta_key - Meta key.
	 * @param mix 	 $meta_value - Meta value.
	 */
	public function event_user_meta_created( $object_id, $meta_key, $meta_value ) {

		// Get user.
		$user = get_user_by( 'ID', $object_id );

		// Check to see if we can log the meta key.
		if ( ! $this->CanLogMetaKey( $object_id, $meta_key ) || is_array( $meta_value ) ) {
			return;
		}

		if ( empty( $meta_value ) && ( $this->null_meta_counter < 1 ) ) { // Report only one NULL meta value.
			$this->null_meta_counter += 1;
		} else { // Do not report if NULL meta values are more than one.
			return;
		}

		// Get POST array.
		$post_array = $_POST;

		// If update action is set then trigger the alert.
		if ( isset( $post_array['action'] ) && ( 'update' == $post_array['action'] || 'createuser' == $post_array['action'] ) ) {
			$this->plugin->alerts->Trigger( 4016, array(
				'TargetUsername'	=> $user->user_login,
				'custom_field_name' => $meta_key,
				'new_value' 		=> $meta_value,
			) );
		}
	}

	/**
	 * Sets the old meta.
	 *
	 * @param int 	 $meta_id - Meta ID.
	 * @param int 	 $object_id - Object ID.
	 * @param string $meta_key - Meta key.
	 */
	public function event_user_meta_updating( $meta_id, $object_id, $meta_key ) {
		static $meta_type = 'user';
		$this->old_meta[ $meta_id ] = (object) array(
			'key' => ( $meta = get_metadata_by_mid( $meta_type, $meta_id ) ) ? $meta->meta_key : $meta_key,
			'val' => get_metadata( $meta_type, $object_id, $meta_key, true ),
		);
	}

	/**
	 * Updated a custom field name/value.
	 *
	 * @param int 	 $meta_id - Meta ID.
	 * @param int 	 $object_id - Object ID.
	 * @param string $meta_key - Meta key.
	 * @param mix 	 $meta_value - Meta value.
	 */
	public function event_user_meta_updated( $meta_id, $object_id, $meta_key, $meta_value ) {

		// Get user.
		$user = get_user_by( 'ID', $object_id );

		// Check to see if we can log the meta key.
		if ( ! $this->CanLogMetaKey( $object_id, $meta_key ) || is_array( $meta_value ) ) {
			return;
		}

		// Get POST array.
		$post_array = $_POST;

		// If update action is set then trigger the alert.
		if ( isset( $post_array['action'] ) && 'update' == $post_array['action'] ) {
			if ( isset( $this->old_meta[ $meta_id ] ) ) {
				// Check change in meta value.
				if ( $this->old_meta[ $meta_id ]->val != $meta_value ) {
					$this->plugin->alerts->Trigger( 4015, array(
						'TargetUsername'	=> $user->user_login,
						'custom_field_name' => $meta_key,
						'new_value' 		=> $meta_value,
						'old_value' 		=> $this->old_meta[ $meta_id ]->val,
					) );
				}
				// Remove old meta update data.
				unset( $this->old_meta[ $meta_id ] );
			}
		}
	}
}
