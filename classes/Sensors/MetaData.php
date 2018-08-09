<?php
/**
 * Sensor: Meta Data
 *
 * Meta Data sensor file.
 *
 * @since 1.0.0
 * @package Wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
 * 4015 User updated a custom field value for a user
 * 4016 User created a custom field value for a user
 * 4017 User changed first name for a user
 * 4018 User changed last name for a user
 * 4019 User changed nickname for a user
 * 4020 User changed the display name for a user
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
		add_action( 'profile_update', array( $this, 'event_userdata_updated' ), 10, 2 );
	}

	/**
	 * Check "Excluded Custom Fields" or meta keys starts with "_".
	 *
	 * @param int    $object_id - Object ID.
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
		$custom_fields = $this->plugin->settings->GetExcludedMonitoringCustom();
		if ( in_array( $custom, $custom_fields ) ) {
			return true;
		}
		foreach ( $custom_fields as $field ) {
			if ( false !== strpos( $field, '*' ) ) {
				// Wildcard str[any_character] when you enter (str*).
				if ( substr( $field, -1 ) == '*' ) {
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
		// return (in_array($custom, $custom_fields)) ? true : false;.
	}

	/**
	 * Created a custom field.
	 *
	 * @param int    $object_id - Object ID.
	 * @param string $meta_key - Meta key.
	 * @param mix    $meta_value - Meta value.
	 */
	public function EventPostMetaCreated( $object_id, $meta_key, $meta_value ) {
		if ( ! $this->CanLogMetaKey( $object_id, $meta_key ) || is_array( $meta_value ) ) {
			return;
		}

		// Get post object.
		$post = get_post( $object_id );

		// Return if the post object is null or the post type is revision.
		if ( null === $post || 'revision' === $post->post_type ) {
			return;
		}

		if ( empty( $meta_value ) && ( $this->null_meta_counter < 1 ) ) { // Report only one NULL meta value.
			$this->null_meta_counter += 1;
		} elseif ( $this->null_meta_counter >= 1 ) { // Do not report if NULL meta values are more than one.
			return;
		}

		// Filter $_POST global array for security.
		$post_array = filter_input_array( INPUT_POST );

		// Check nonce.
		if ( isset( $post_array['_ajax_nonce-add-meta'] ) && ! wp_verify_nonce( $post_array['_ajax_nonce-add-meta'], 'add-meta' ) ) {
			return false;
		} elseif ( isset( $post_array['_wpnonce'] ) && isset( $post_array['post_ID'] ) && ! wp_verify_nonce( $post_array['_wpnonce'], 'update-post_' . $post_array['post_ID'] ) ) {
			return false;
		}

		// WP Dashboard action.
		$wp_action = array( 'add-meta' );

		// Check MainWP $_POST members.
		$new_post    = filter_input( INPUT_POST, 'new_post' );
		$post_custom = filter_input( INPUT_POST, 'post_custom' );

		// Check if the post is coming from MainWP.
		$mainwp = filter_input( INPUT_POST, 'mainwpsignature', FILTER_SANITIZE_STRING );

		if (
			( // Either coming from WP admin panel.
				isset( $post_array['action'] )
				&& (
					'editpost' === $post_array['action']
					|| in_array( $post_array['action'], $wp_action, true )
				)
			) || ( // OR from MainWP dashboard.
				! empty( $new_post )
				&& ! empty( $post_custom )
				&& ! empty( $mainwp )
			)
		) {
			$editor_link = $this->GetEditorLink( $post );
			$this->plugin->alerts->Trigger(
				2053, array(
					'PostID' => $object_id,
					'PostTitle' => $post->post_title,
					'PostStatus' => $post->post_status,
					'PostType' => $post->post_type,
					'PostDate' => $post->post_date,
					'PostUrl' => get_permalink( $post->ID ),
					'MetaKey' => $meta_key,
					'MetaValue' => $meta_value,
					'MetaLink' => $meta_key,
					$editor_link['name'] => $editor_link['value'],
				)
			);
		}
	}

	/**
	 * Sets the old meta.
	 *
	 * @param int    $meta_id - Meta ID.
	 * @param int    $object_id - Object ID.
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
	 * @param int    $meta_id - Meta ID.
	 * @param int    $object_id - Object ID.
	 * @param string $meta_key - Meta key.
	 * @param mix    $meta_value - Meta value.
	 */
	public function EventPostMetaUpdated( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( ! $this->CanLogMetaKey( $object_id, $meta_key ) || is_array( $meta_value ) ) {
			return;
		}

		// Get post object.
		$post = get_post( $object_id );

		// Return if the post object is null or the post type is revision.
		if ( null === $post || 'revision' === $post->post_type ) {
			return;
		}

		/**
		 * WSAL Action Hook.
		 *
		 * Runs before logging events for post meta updated i.e. 2062 or 2054.
		 *
		 * This hook can be used to log events for updated post meta on the
		 * front-end since the plugin only supports events for updating post
		 * meta via wp admin panel.
		 *
		 * @param int    $meta_id        - Meta ID.
		 * @param int    $object_id      - Post ID.
		 * @param array  $this->old_meta - Array of meta data holding keys & values of old meta data before updating the current post.
		 * @param string $meta_key       - Meta key.
		 * @param mixed  $meta_value     - Meta value.
		 * @since 3.2.2
		 */
		do_action( 'wsal_post_meta_updated', $meta_id, $object_id, $this->old_meta, $meta_key, $meta_value );

		// Filter $_POST global array for security.
		$post_array = filter_input_array( INPUT_POST );

		// Check nonce.
		if ( isset( $post_array['_ajax_nonce'] ) && ! wp_verify_nonce( $post_array['_ajax_nonce'], 'change-meta' ) ) {
			return false;
		} elseif ( isset( $post_array['_wpnonce'] ) && isset( $post_array['post_ID'] ) && ! wp_verify_nonce( $post_array['_wpnonce'], 'update-post_' . $post_array['post_ID'] ) ) {
			return false;
		}

		// WP Dashboard action.
		$wp_action = array( 'add-meta' );

		// Check MainWP $_POST members.
		$new_post    = filter_input( INPUT_POST, 'new_post' );
		$post_custom = filter_input( INPUT_POST, 'post_custom' );

		// Check if the post is coming from MainWP.
		$mainwp = filter_input( INPUT_POST, 'mainwpsignature', FILTER_SANITIZE_STRING );

		if (
			(
				isset( $post_array['action'] )
				&& (
					'editpost' === $post_array['action']
					|| in_array( $post_array['action'], $wp_action, true )
				)
			) || (
				! empty( $new_post )
				&& ! empty( $post_custom )
				&& ! empty( $mainwp )
			)
		) {
			$editor_link = $this->GetEditorLink( $post );
			if ( isset( $this->old_meta[ $meta_id ] ) ) {
				// Check change in meta key.
				if ( $this->old_meta[ $meta_id ]->key != $meta_key ) {
					$this->plugin->alerts->Trigger(
						2062, array(
							'PostID' => $object_id,
							'PostTitle' => $post->post_title,
							'PostStatus' => $post->post_status,
							'PostType' => $post->post_type,
							'PostDate' => $post->post_date,
							'PostUrl' => get_permalink( $post->ID ),
							'MetaID' => $meta_id,
							'MetaKeyNew' => $meta_key,
							'MetaKeyOld' => $this->old_meta[ $meta_id ]->key,
							'MetaValue' => $meta_value,
							'MetaLink' => $meta_key,
							$editor_link['name'] => $editor_link['value'],
						)
					);
				} elseif ( $this->old_meta[ $meta_id ]->val != $meta_value ) { // Check change in meta value.
					$this->plugin->alerts->Trigger(
						2054, array(
							'PostID' => $object_id,
							'PostTitle' => $post->post_title,
							'PostStatus' => $post->post_status,
							'PostType' => $post->post_type,
							'PostDate' => $post->post_date,
							'PostUrl' => get_permalink( $post->ID ),
							'MetaID' => $meta_id,
							'MetaKey' => $meta_key,
							'MetaValueNew' => $meta_value,
							'MetaValueOld' => $this->old_meta[ $meta_id ]->val,
							'MetaLink' => $meta_key,
							$editor_link['name'] => $editor_link['value'],
							'ReportText' => $this->old_meta[ $meta_id ]->val . '|' . $meta_value,
						)
					);
				}
				// Remove old meta update data.
				unset( $this->old_meta[ $meta_id ] );
			}
		}
	}

	/**
	 * Deleted a custom field.
	 *
	 * @param int    $meta_ids - Meta IDs.
	 * @param int    $object_id - Object ID.
	 * @param string $meta_key - Meta key.
	 * @param mix    $meta_value - Meta value.
	 */
	public function EventPostMetaDeleted( $meta_ids, $object_id, $meta_key, $meta_value ) {
		// If meta key starts with "_" then return.
		if ( '_' == substr( $meta_key, 0, 1 ) ) {
			return;
		}

		// Get post object.
		$post = get_post( $object_id );

		// Return if the post object is null.
		if ( null === $post ) {
			return;
		}

		// Filter $_POST global array for security.
		$post_array = filter_input_array( INPUT_POST );

		// Check nonce.
		if ( isset( $post_array['_ajax_nonce'] ) && ! wp_verify_nonce( $post_array['_ajax_nonce'], 'delete-meta_' . $post_array['id'] ) ) {
			return false;
		} elseif ( isset( $post_array['_wpnonce'] ) && isset( $post_array['post_ID'] ) && ! wp_verify_nonce( $post_array['_wpnonce'], 'update-post_' . $post_array['post_ID'] ) ) {
			return false;
		}

		// WP Dashboard action.
		$wp_action = array( 'delete-meta' );

		// Check MainWP $_POST members.
		$new_post    = filter_input( INPUT_POST, 'new_post' );
		$post_custom = filter_input( INPUT_POST, 'post_custom' );

		// Check if the post is coming from MainWP.
		$mainwp = filter_input( INPUT_POST, 'mainwpsignature', FILTER_SANITIZE_STRING );

		if (
			(
				isset( $post_array['action'] )
				&& in_array( $post_array['action'], $wp_action, true )
			) || (
				! empty( $new_post )
				&& ! empty( $post_custom )
				&& ! empty( $mainwp )
			)
		) {
			$editor_link = $this->GetEditorLink( $post );
			foreach ( $meta_ids as $meta_id ) {
				if ( ! $this->CanLogMetaKey( $object_id, $meta_key ) ) {
					continue;
				}
				$this->plugin->alerts->Trigger(
					2055, array(
						'PostID' => $object_id,
						'PostTitle' => $post->post_title,
						'PostStatus' => $post->post_status,
						'PostType' => $post->post_type,
						'PostDate' => $post->post_date,
						'PostUrl' => get_permalink( $post->ID ),
						'MetaID' => $meta_id,
						'MetaKey' => $meta_key,
						'MetaValue' => $meta_value,
						$editor_link['name'] => $editor_link['value'],
					)
				);
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
	 * @param stdClass $post - The post.
	 * @return array $editor_link - Name and value link
	 */
	private function GetEditorLink( $post ) {
		$name = 'EditorLink';
		$name .= ( 'page' == $post->post_type ) ? 'Page' : 'Post';
		$value = get_edit_post_link( $post->ID );
		$editor_link = array(
			'name' => $name,
			'value' => $value,
		);
		return $editor_link;
	}

	/**
	 * Create a custom field name/value.
	 *
	 * @param int    $object_id - Object ID.
	 * @param string $meta_key - Meta key.
	 * @param mix    $meta_value - Meta value.
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
		} elseif ( $this->null_meta_counter >= 1 ) { // Do not report if NULL meta values are more than one.
			return;
		}

		// Filter $_POST global array for security.
		$post_array = filter_input_array( INPUT_POST );

		// Check nonce.
		if ( isset( $post_array['_wpnonce'] ) && ! wp_verify_nonce( $post_array['_wpnonce'], 'update-user_' . $user->ID ) ) {
			return false;
		}

		// If update action is set then trigger the alert.
		if ( isset( $post_array['action'] ) && ( 'update' == $post_array['action'] || 'createuser' == $post_array['action'] ) ) {
			$this->plugin->alerts->TriggerIf(
				4016,
				array(
					'TargetUsername'    => $user->user_login,
					'custom_field_name' => $meta_key,
					'new_value'         => $meta_value,
				),
				array( $this, 'must_not_contain_new_user_alert' )
			);
		}
	}

	/**
	 * Sets the old meta.
	 *
	 * @param int    $meta_id - Meta ID.
	 * @param int    $object_id - Object ID.
	 * @param string $meta_key - Meta key.
	 */
	public function event_user_meta_updating( $meta_id, $object_id, $meta_key ) {
		static $meta_type = 'user';
		$meta             = get_metadata_by_mid( $meta_type, $meta_id );

		// Set old meta array.
		$this->old_meta[ $meta_id ] = (object) array(
			'key' => ( $meta ) ? $meta->meta_key : $meta_key,
			'val' => get_metadata( $meta_type, $object_id, $meta_key, true ),
		);
	}

	/**
	 * Updated a custom field name/value.
	 *
	 * @param int    $meta_id - Meta ID.
	 * @param int    $object_id - Object ID.
	 * @param string $meta_key - Meta key.
	 * @param mix    $meta_value - Meta value.
	 */
	public function event_user_meta_updated( $meta_id, $object_id, $meta_key, $meta_value ) {
		// Get user.
		$user = get_user_by( 'ID', $object_id );

		// Check to see if we can log the meta key.
		if ( ! $this->CanLogMetaKey( $object_id, $meta_key ) || is_array( $meta_value ) ) {
			return;
		}

		// User profile name related meta.
		$username_meta = array( 'first_name', 'last_name', 'nickname' );

		// Filter $_POST global array for security.
		$post_array = filter_input_array( INPUT_POST );

		// If update action is set then trigger the alert.
		if (
			(
				isset( $post_array['_wpnonce'] ) // WP Dashboard Support.
				&& wp_verify_nonce( $post_array['_wpnonce'], 'update-user_' . $user->ID )
				&& isset( $post_array['action'] )
				&& 'update' == $post_array['action']
			) || (
				isset( $post_array['_um_account'] ) // Ultimate Member Plugin support.
				&& '1' === $post_array['_um_account']
				&& isset( $post_array['_um_account_tab'] )
				&& 'general' === $post_array['_um_account_tab']
			) || (
				isset( $post_array['action'] ) && 'update_user' === $post_array['action'] // MainWP action.
				&& isset( $post_array['mainwpsignature'] ) && ! empty( $post_array['mainwpsignature'] ) // MainWP Signature.
			)
		) {
			if ( isset( $this->old_meta[ $meta_id ] ) && ! in_array( $meta_key, $username_meta, true ) ) {
				// Check change in meta value.
				if ( $this->old_meta[ $meta_id ]->val != $meta_value ) {
					$this->plugin->alerts->TriggerIf(
						4015,
						array(
							'TargetUsername'    => $user->user_login,
							'custom_field_name' => $meta_key,
							'new_value'         => $meta_value,
							'old_value'         => $this->old_meta[ $meta_id ]->val,
							'ReportText'        => $this->old_meta[ $meta_id ]->val . '|' . $meta_value,
						),
						array( $this, 'must_not_contain_role_changes' )
					);
				}
				// Remove old meta update data.
				unset( $this->old_meta[ $meta_id ] );
			} elseif ( isset( $this->old_meta[ $meta_id ] ) && in_array( $meta_key, $username_meta, true ) ) {
				// Detect the alert based on meta key.
				switch ( $meta_key ) {
					case 'first_name':
						if ( $this->old_meta[ $meta_id ]->val != $meta_value ) {
							$this->plugin->alerts->Trigger(
								4017, array(
									'TargetUsername' => $user->user_login,
									'new_firstname'  => $meta_value,
									'old_firstname'  => $this->old_meta[ $meta_id ]->val,
								)
							);
						}
						break;

					case 'last_name':
						if ( $this->old_meta[ $meta_id ]->val != $meta_value ) {
							$this->plugin->alerts->Trigger(
								4018, array(
									'TargetUsername' => $user->user_login,
									'new_lastname'   => $meta_value,
									'old_lastname'   => $this->old_meta[ $meta_id ]->val,
								)
							);
						}
						break;

					case 'nickname':
						if ( $this->old_meta[ $meta_id ]->val != $meta_value ) {
							$this->plugin->alerts->Trigger(
								4019, array(
									'TargetUsername' => $user->user_login,
									'new_nickname'   => $meta_value,
									'old_nickname'   => $this->old_meta[ $meta_id ]->val,
								)
							);
						}
						break;

					default:
						break;
				}
			}
		}
	}

	/**
	 * Method: Updated user data.
	 *
	 * @param int    $user_id       User ID.
	 * @param object $old_user_data Object containing user's data prior to update.
	 * @since 2.6.9
	 */
	public function event_userdata_updated( $user_id, $old_user_data ) {
		// Get user display name.
		$old_display_name = $old_user_data->display_name;

		// Get user's current data.
		$new_userdata     = get_userdata( $user_id );
		$new_display_name = $new_userdata->display_name;

		// Alert if display name is changed.
		if ( $old_display_name !== $new_display_name ) {
			$this->plugin->alerts->Trigger(
				4020, array(
					'TargetUsername'  => $new_userdata->user_login,
					'new_displayname' => $new_display_name,
					'old_displayname' => $old_display_name,
				)
			);
		}
	}

	/**
	 * Method: This function make sures that alert 4002
	 * has not been triggered before updating user meta.
	 *
	 * @param WSAL_AlertManager $manager - WSAL Alert Manager.
	 * @return bool
	 * @since 3.2.3
	 */
	public function must_not_contain_role_changes( WSAL_AlertManager $manager ) {
		return ! $manager->WillOrHasTriggered( 4002 );
	}

	/**
	 * Method: This function make sures that alert 4001
	 * has not been triggered before creating user meta.
	 *
	 * @param WSAL_AlertManager $manager - WSAL Alert Manager.
	 * @return bool
	 * @since 3.2.3
	 */
	public function must_not_contain_new_user_alert( WSAL_AlertManager $manager ) {
		return ! $manager->WillOrHasTriggered( 4001 );
	}
}
