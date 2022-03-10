<?php
/**
 * Sensor: Meta Data
 *
 * Meta Data sensor file.
 *
 * @since      1.0.0
 * @package    wsal
 * @subpackage sensors
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom fields (posts, pages, custom posts and users) sensor.
 *
 * 2053 User created a custom field for a post
 * 2062 User updated a custom field name for a post
 * 2054 User updated a custom field value for a post
 * 2055 User deleted a custom field from a post
 * 4015 User updated a custom field value for a user
 * 4016 User created a custom field value for a user
 * 4017 User changed first name for a user
 * 4018 User changed last name for a user
 * 4019 User changed nickname for a user
 * 4020 User changed the display name for a user
 *
 * @package    wsal
 * @subpackage sensors
 * @since      1.0.0
 */
class WSAL_Sensors_MetaData extends WSAL_AbstractMetaDataSensor {

	/**
	 * Empty meta counter.
	 *
	 * @var int
	 */
	private $null_meta_counter = 0;

	/**
	 * Listening to events using WP hooks.
	 */
	public function hook_events() {
		add_action( 'add_post_meta', array( $this, 'event_post_meta_created' ), 10, 3 );
		add_action( 'update_post_meta', array( $this, 'event_post_meta_updating' ), 10, 3 );
		add_action( 'updated_post_meta', array( $this, 'event_post_meta_updated' ), 10, 4 );
		add_action( 'deleted_post_meta', array( $this, 'event_post_meta_deleted' ), 10, 4 );
		add_action( 'save_post', array( $this, 'reset_null_meta_counter' ), 10 );
		add_action( 'add_user_meta', array( $this, 'event_user_meta_created' ), 10, 3 );
		add_action( 'update_user_meta', array( $this, 'event_user_meta_updating' ), 10, 3 );
		add_action( 'updated_user_meta', array( $this, 'event_user_meta_updated' ), 10, 4 );
		add_action( 'user_register', array( $this, 'reset_null_meta_counter' ), 10 );
	}

	/**
	 * Created a custom field.
	 *
	 * @param int    $object_id - Object ID.
	 * @param string $meta_key - Meta key.
	 * @param mixed  $meta_value - Meta value.
	 */
	public function event_post_meta_created( $object_id, $meta_key, $meta_value ) {
		if ( ! $this->can_log_meta_key( 'post', $object_id, $meta_key ) || is_array( $meta_value ) ) {
			return;
		}

		if ( empty( $meta_value ) && ( $this->null_meta_counter < 1 ) ) { // Report only one NULL meta value.
			$this->null_meta_counter += 1;
		} elseif ( $this->null_meta_counter >= 1 ) { // Do not report if NULL meta values are more than one.
			return;
		}

		// Get post object.
		$post = get_post( $object_id );

		// Return if the post object is null or the post type is revision.
		if ( null === $post || 'revision' === $post->post_type ) {
			return;
		}

		// Remove WC coupons from ignored array.
		$key = array_search( 'shop_coupon', $this->plugin->alerts->ignored_cpts ); // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
		if ( false !== $key ) {
			unset( $this->plugin->alerts->ignored_cpts[ $key ] );
		}

		// Ignore post types we are not interested in.
		$ignored_cpts = $this->plugin->alerts->ignored_cpts;

		// Ignore updates from ignored custom post types.
		if ( in_array( $post->post_type, $ignored_cpts, true ) ) {
			return;
		}

		/**
		 * WSAL Filter: `wsal_before_post_meta_create_event`
		 *
		 * Runs before logging event for post meta created i.e. 2053.
		 * This filter can be used as check to whether log this event or not.
		 *
		 * @since 3.3.1
		 *
		 * @param bool    $log_event  - True if log meta event, false if not.
		 * @param string  $meta_key   - Meta key.
		 * @param mixed   $meta_value - Meta value.
		 * @param WP_Post $post       - Post object.
		 */
		$log_meta_event = apply_filters( 'wsal_before_post_meta_create_event', true, $meta_key, $meta_value, $post );

		if ( $log_meta_event ) {
			$editor_link = $this->get_editor_link( $post );
			$this->plugin->alerts->trigger_event(
				2053,
				array(
					'PostID'             => $object_id,
					'PostTitle'          => $post->post_title,
					'PostStatus'         => $post->post_status,
					'PostType'           => $post->post_type,
					'PostDate'           => $post->post_date,
					'PostUrl'            => get_permalink( $post->ID ),
					'MetaKey'            => $meta_key,
					'MetaValue'          => $meta_value,
					'MetaLink'           => $meta_key,
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
	public function event_post_meta_updating( $meta_id, $object_id, $meta_key ) {
		static $meta_type = 'post';
		$meta             = get_metadata_by_mid( $meta_type, $meta_id );

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
	 * @param mixed  $meta_value - Meta value.
	 */
	public function event_post_meta_updated( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( ! $this->can_log_meta_key( 'post', $object_id, $meta_key ) || is_array( $meta_value ) ) {
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
		 * @param array  $this->old_meta - Array of metadata holding keys & values of old metadata before updating the current post.
		 * @param string $meta_key       - Meta key.
		 * @param mixed  $meta_value     - Meta value.
		 * @since 3.2.2
		 */
		do_action( 'wsal_post_meta_updated', $meta_id, $object_id, $this->old_meta, $meta_key, $meta_value );

		if ( isset( $this->old_meta[ $meta_id ] ) ) {
			/**
			 * WSAL Filter: `wsal_before_post_meta_update_event`
			 *
			 * Runs before logging events for post meta updated i.e. 2054 or 2062.
			 * This filter can be used as check to whether log these events or not.
			 *
			 * @param bool     $log_event  - True if log meta event 2054 or 2062, false if not.
			 * @param string   $meta_key   - Meta key.
			 * @param mixed    $meta_value - Meta value.
			 * @param stdClass $old_meta   - Old meta value and key object.
			 * @param WP_Post  $post       - Post object.
			 * @param integer  $meta_id    - Meta ID.
			 *
			 * @since 3.3.1
			 */
			$log_meta_event = apply_filters( 'wsal_before_post_meta_update_event', true, $meta_key, $meta_value, $this->old_meta[ $meta_id ], $post, $meta_id );

			// Check change in meta key.
			if ( $log_meta_event && $this->old_meta[ $meta_id ]->key !== $meta_key ) {
				$editor_link = $this->get_editor_link( $post );
				$this->plugin->alerts->trigger_event(
					2062,
					array(
						'PostID'             => $object_id,
						'PostTitle'          => $post->post_title,
						'PostStatus'         => $post->post_status,
						'PostType'           => $post->post_type,
						'PostDate'           => $post->post_date,
						'PostUrl'            => get_permalink( $post->ID ),
						'MetaID'             => $meta_id,
						'MetaKeyNew'         => $meta_key,
						'MetaKeyOld'         => $this->old_meta[ $meta_id ]->key,
						'MetaValue'          => $meta_value,
						'MetaLink'           => $meta_key,
						$editor_link['name'] => $editor_link['value'],
					)
				);
			} elseif ( $log_meta_event && $this->old_meta[ $meta_id ]->val !== $meta_value ) { // Check change in meta value.
				$editor_link = $this->get_editor_link( $post );
				$this->plugin->alerts->trigger_event_if(
					2054,
					array(
						'PostID'             => $object_id,
						'PostTitle'          => $post->post_title,
						'PostStatus'         => $post->post_status,
						'PostType'           => $post->post_type,
						'PostDate'           => $post->post_date,
						'PostUrl'            => get_permalink( $post->ID ),
						'MetaID'             => $meta_id,
						'MetaKey'            => $meta_key,
						'MetaValueNew'       => $meta_value,
						'MetaValueOld'       => $this->old_meta[ $meta_id ]->val,
						'MetaLink'           => $meta_key,
						$editor_link['name'] => $editor_link['value'],
					),
					/**
					 * Don't fire if there's already an event 2131 or 2132 (ACF relationship change).
					 *
					 * @param WSAL_AlertManager $manager
					 * @return bool
					 */
					function ( $manager ) {
						return ! $manager->will_or_has_triggered( 2131 )
							&& ! $manager->will_or_has_triggered( 2132 );
					}
				);
			}
			// Remove old meta update data.
			unset( $this->old_meta[ $meta_id ] );
		}
	}

	/**
	 * Deleted a custom field.
	 *
	 * @param int    $meta_ids - Meta IDs.
	 * @param int    $object_id - Object ID.
	 * @param string $meta_key - Meta key.
	 * @param mixed  $meta_value - Meta value.
	 */
	public function event_post_meta_deleted( $meta_ids, $object_id, $meta_key, $meta_value ) {
		// If meta key starts with "_" then return.
		if ( '_' === substr( $meta_key, 0, 1 ) ) {
			return;
		}

		// Get post object.
		$post = get_post( $object_id );

		// Return if the post object is null.
		if ( null === $post ) {
			return;
		}

		$editor_link = $this->get_editor_link( $post );
		foreach ( $meta_ids as $meta_id ) {
			if ( ! $this->can_log_meta_key( 'post', $object_id, $meta_key ) ) {
				continue;
			}

			/**
			 * WSAL Filter: `wsal_before_post_meta_delete_event`
			 *
			 * Runs before logging event for post meta deleted i.e. 2054.
			 * This filter can be used as check to whether log this event or not.
			 *
			 * @since 3.3.1
			 *
			 * @param bool     $log_event  - True if log meta event 2055, false if not.
			 * @param string   $meta_key   - Meta key.
			 * @param mixed    $meta_value - Meta value.
			 * @param WP_Post  $post       - Post object.
			 * @param integer  $meta_id    - Meta ID.
			 */
			$log_meta_event = apply_filters( 'wsal_before_post_meta_delete_event', true, $meta_key, $meta_value, $post, $meta_id );

			// Ignore updates from ignored custom post types.
			if ( in_array( $post->post_type, $this->plugin->alerts->ignored_cpts, true ) ) {
				return;
			}

			// If not allowed to log meta event then skip it.
			if ( ! $log_meta_event ) {
				continue;
			}

			if ( 'trash' !== $post->post_status ) {
				$this->plugin->alerts->trigger_event(
					2055,
					array(
						'PostID'             => $object_id,
						'PostTitle'          => $post->post_title,
						'PostStatus'         => $post->post_status,
						'PostType'           => $post->post_type,
						'PostDate'           => $post->post_date,
						'PostUrl'            => get_permalink( $post->ID ),
						'MetaID'             => $meta_id,
						'MetaKey'            => $meta_key,
						'MetaValue'          => $meta_value,
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
	 * Create a custom field name/value.
	 *
	 * @param int    $object_id - Object ID.
	 * @param string $meta_key - Meta key.
	 * @param mixed  $meta_value - Meta value.
	 */
	public function event_user_meta_created( $object_id, $meta_key, $meta_value ) {
		// Check to see if we can log the meta key.
		if ( ! $this->can_log_meta_key( 'user', $object_id, $meta_key ) || is_array( $meta_value ) ) {
			return;
		}

		if ( $this->is_woocommerce_user_meta( $meta_key ) ) {
			return;
		}

		if ( empty( $meta_value ) && ( $this->null_meta_counter < 1 ) ) { // Report only one NULL meta value.
			$this->null_meta_counter += 1;
		} elseif ( $this->null_meta_counter >= 1 ) { // Do not report if NULL meta values are more than one.
			return;
		}

		// Get user.
		$user = get_user_by( 'ID', $object_id );

		$this->plugin->alerts->trigger_event_if(
			4016,
			array(
				'TargetUsername'    => $user->user_login,
				'custom_field_name' => $meta_key,
				'new_value'         => $meta_value,
				'FirstName'         => $user->user_firstname,
				'LastName'          => $user->user_lastname,
				'Roles'             => is_array( $user->roles ) ? implode( ', ', $user->roles ) : $user->roles,
				'EditUserLink'      => add_query_arg( 'user_id', $user->ID, admin_url( 'user-edit.php' ) ),
			),
			array( $this, 'must_not_contain_new_user_alert' )
		);
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
	 * @param mixed  $meta_value - Meta value.
	 */
	public function event_user_meta_updated( $meta_id, $object_id, $meta_key, $meta_value ) {
		// Check to see if we can log the meta key.
		if ( ! $this->can_log_meta_key( 'user', $object_id, $meta_key ) || is_array( $meta_value ) ) {
			return;
		}

		if ( $this->is_woocommerce_user_meta( $meta_key ) ) {
			return;
		}

		if ( 'last_update' === $meta_key ) { // Contains timestamp for last user update so ignore it.
			return;
		}

		$username_meta = array( 'first_name', 'last_name', 'nickname' ); // User profile name related meta.
		$user          = get_user_by( 'ID', $object_id ); // Get user.

		if ( isset( $this->old_meta[ $meta_id ] ) && ! in_array( $meta_key, $username_meta, true ) ) {
			// Check change in meta value.
			if ( $this->old_meta[ $meta_id ]->val !== $meta_value ) {
				$this->plugin->alerts->trigger_event_if(
					4015,
					array(
						'TargetUsername'    => $user->user_login,
						'custom_field_name' => $meta_key,
						'new_value'         => $meta_value,
						'old_value'         => $this->old_meta[ $meta_id ]->val,
						'FirstName'         => $user->user_firstname,
						'LastName'          => $user->user_lastname,
						'Roles'             => is_array( $user->roles ) ? implode( ', ', $user->roles ) : $user->roles,
						'EditUserLink'      => add_query_arg( 'user_id', $user->ID, admin_url( 'user-edit.php' ) ),
						'MetaLink'          => $meta_key,
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
					if ( $this->old_meta[ $meta_id ]->val !== $meta_value ) {
						$this->plugin->alerts->trigger_event(
							4017,
							array(
								'TargetUsername' => $user->user_login,
								'new_firstname'  => $meta_value,
								'old_firstname'  => $this->old_meta[ $meta_id ]->val,
								'FirstName'      => $user->user_firstname,
								'LastName'       => $user->user_lastname,
								'Roles'          => is_array( $user->roles ) ? implode( ', ', $user->roles ) : $user->roles,
								'EditUserLink'   => add_query_arg( 'user_id', $user->ID, admin_url( 'user-edit.php' ) ),
								'MetaLink'       => $meta_key,
							)
						);
					}
					break;

				case 'last_name':
					if ( $this->old_meta[ $meta_id ]->val !== $meta_value ) {
						$this->plugin->alerts->trigger_event(
							4018,
							array(
								'TargetUsername' => $user->user_login,
								'new_lastname'   => $meta_value,
								'old_lastname'   => $this->old_meta[ $meta_id ]->val,
								'FirstName'      => $user->user_firstname,
								'LastName'       => $user->user_lastname,
								'Roles'          => is_array( $user->roles ) ? implode( ', ', $user->roles ) : $user->roles,
								'EditUserLink'   => add_query_arg( 'user_id', $user->ID, admin_url( 'user-edit.php' ) ),
							)
						);
					}
					break;

				case 'nickname':
					if ( $this->old_meta[ $meta_id ]->val !== $meta_value ) {
						$this->plugin->alerts->trigger_event(
							4019,
							array(
								'TargetUsername' => $user->user_login,
								'new_nickname'   => $meta_value,
								'old_nickname'   => $this->old_meta[ $meta_id ]->val,
								'FirstName'      => $user->user_firstname,
								'LastName'       => $user->user_lastname,
								'Roles'          => is_array( $user->roles ) ? implode( ', ', $user->roles ) : $user->roles,
								'EditUserLink'   => add_query_arg( 'user_id', $user->ID, admin_url( 'user-edit.php' ) ),
							)
						);
					}
					break;

				default:
					break;
			}
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
	public function must_not_contain_role_changes( $manager ) {
		return ! $manager->will_or_has_triggered( 4002 );
	}

	/**
	 * Method: This function make sures that alert 4001
	 * has not been triggered before creating user meta.
	 *
	 * @param WSAL_AlertManager $manager - WSAL Alert Manager.
	 * @return bool
	 * @since 3.2.3
	 */
	public function must_not_contain_new_user_alert( $manager ) {
		return ! $manager->will_or_has_triggered( 4001 ) && ! $manager->will_or_has_triggered( 4012 );
	}

	/**
	 * Check if meta key belongs to WooCommerce user meta.
	 *
	 * @param string $meta_key - Meta key.
	 * @return boolean
	 */
	private function is_woocommerce_user_meta( $meta_key ) {
		// Check for WooCommerce user profile keys.
		if ( false !== strpos( $meta_key, 'shipping_' ) || false !== strpos( $meta_key, 'billing_' ) ) {
			// Remove the prefix to avoid redundancy in the meta keys.
			$address_key = str_replace( array( 'shipping_', 'billing_' ), '', $meta_key );

			// WC address meta keys without prefix.
			$meta_keys = array( 'first_name', 'last_name', 'company', 'country', 'address_1', 'address_2', 'city', 'state', 'postcode', 'phone', 'email' );

			if ( in_array( $address_key, $meta_keys, true ) ) {
				return true;
			}
		}

		// Meta key does not belong to WooCommerce.
		return false;
	}
}
