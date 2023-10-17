<?php
/**
 * Custom Sensors for WPForms
 *
 * Class file for alert manager.
 *
 * @since   1.0.0
 * @package Wsal
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors;

use WSAL\Controllers\Alert_Manager;
use WSAL\WP_Sensors\Helpers\WPForms_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\WPForms_Sensor' ) ) {
	/**
	 * Custom sensor class to process WPForms events.
	 *
	 * @since 4.6.0
	 */
	class WPForms_Sensor {

		/**
		 * Holds a cached value if the checked alert has recently fired.
		 *
		 * @var \WP_Post
		 *
		 * @since 4.6.0
		 */
		private static $old_post = null;

		/**
		 * Hook events related to sensor.
		 *
		 * @since 4.6.0
		 */
		public static function init() {
			if ( WPForms_Helper::is_wpforms_active() ) {
				add_action( 'pre_post_update', array( __CLASS__, 'get_before_post_edit_data' ), 10, 2 );
				add_action( 'save_post', array( __CLASS__, 'event_form_saved' ), 10, 3 );
				add_action( 'delete_post', array( __CLASS__, 'event_form_deleted' ), 10, 1 );
				add_action( 'wpforms_pre_delete', array( __CLASS__, 'event_entry_deleted' ), 10, 1 );
				add_action( 'wpforms_pro_admin_entries_edit_submit_completed', array( __CLASS__, 'event_entry_modified' ), 5, 4 );
				add_action( 'updated_option', array( __CLASS__, 'event_settings_updated' ), 10, 3 );
				add_action( 'added_option', array( __CLASS__, 'event_added_option' ), 10, 2 );
				add_action( 'wpforms_plugin_activated', array( __CLASS__, 'addon_plugin_activated' ), 10, 1 );
				add_action( 'wpforms_plugin_deactivated', array( __CLASS__, 'addon_plugin_deactivated' ), 10, 1 );
				add_action( 'wpforms_plugin_installed', array( __CLASS__, 'addon_plugin_installed' ), 10, 1 );
				add_action( 'wpforms_process_complete', array( __CLASS__, 'event_entry_added' ), 10, 4 );
				add_action( 'wpforms_post_delete_entry', array( __CLASS__, 'event_entry_deleted' ), 10, 1 );
			}
		}

		/**
		 * Loads all the plugin dependencies early.
		 *
		 * @return void
		 *
		 * @since 4.6.0
		 */
		public static function early_init() {
			/**
			 * Add our filters.
			 */
			add_filter(
				'wsal_event_objects',
				array( '\WSAL\WP_Sensors\Helpers\WPForms_Helper', 'wsal_wpforms_add_custom_event_objects' )
			);
			if ( WPForms_Helper::is_wpforms_active() ) {
				add_filter(
					'wsal_ignored_custom_post_types',
					array( '\WSAL\WP_Sensors\Helpers\WPForms_Helper', 'wsal_wpforms_add_custom_ignored_cpt' )
				);
			}
		}

		/**
		 * Get Post Data.
		 *
		 * Collect old post data before post update event.
		 *
		 * @since 4.6.0
		 *
		 * @param int $post_id - Post ID.
		 */
		public static function get_before_post_edit_data( $post_id ) {
			$post_id = absint( $post_id ); // Making sure that the post id is integer.
			$post    = get_post( $post_id ); // Get post.

			// If post exists.
			if ( ! empty( $post ) && $post instanceof \WP_Post ) {
				self::$old_post = $post;
			}
		}

		/**
		 * Trigger event when an entry is added,
		 *
		 * @param array $fields - Submitted form fields.
		 * @param array $entry - Entry content.
		 * @param array $form_data - For. data.
		 * @param int   $entry_id - entry id.
		 * @return void
		 *
		 * @since 4.6.0
		 */
		public static function event_entry_added( $fields, $entry, $form_data, $entry_id ) {

			$alert_code  = 5523;
			$editor_link = esc_url(
				add_query_arg(
					array(
						'view'     => 'edit',
						'entry_id' => $entry_id,
					),
					admin_url( 'admin.php?page=wpforms-entries' )
				)
			);

			// Grab form content.
			$form_content = '';
			$field_values = array_values( $fields );
			foreach ( $field_values as $value ) {
				$form_content .= implode( ',', $value );
			}

			// Search it for any email address.
			$email_address = self::extract_emails( $form_content );

			// Now let's see if we have more than one email present, if so, just grab the 1st one.
			if ( $email_address && is_array( $email_address ) ) {
				$email_address = $email_address[0];
			} elseif ( $email_address && ! is_array( $email_address ) ) {
				$email_address = $email_address;
			} else {
				$email_address = esc_html__( 'No email provided', 'wp-security-audit-log' );
			}

			$variables = array(
				'form_name'       => sanitize_text_field( $form_data['settings']['form_title'] ),
				'form_id'         => sanitize_text_field( $form_data['id'] ),
				'entry_email'     => $email_address,
				'EditorLinkEntry' => $editor_link,
			);

			Alert_Manager::trigger_event_if( $alert_code, $variables, array( __CLASS__, 'check_if_duplicate' ) );
		}

		/**
		 * Form renamed event.
		 *
		 * Detect when forms title has been changed.
		 *
		 * @since 4.6.0
		 *
		 * @param int    $post_id - Post ID.
		 * @param object $post    - Post data.
		 * @param bool   $update  - Whether this is an existing post being updated or not.
		 */
		public static function event_form_saved( $post_id, $post, $update ) {
			$post_id             = absint( $post_id ); // Making sure that the post id is integer.
			$form                = get_post( $post_id );
			$has_alert_triggered = false; // Create a variable so we can determine if an alert has already fired.

			// Handling form creation. First lets check an old post was set and its not flagged as an update, then finally check its not a duplicate.
			if ( ! isset( self::$old_post->post_title ) && ! $update && ! preg_match( '/\s\(ID #[0-9].*?\)/', $form->post_title ) && 'wpforms' === $post->post_type ) {
				$alert_code  = 5500;
				$editor_link = self::create_form_post_editor_link( $post_id );

				$variables = array(
					'EventType'      => 'created',
					'PostTitle'      => sanitize_text_field( $post->post_title ),
					'PostID'         => $post_id,
					'EditorLinkForm' => $editor_link,
				);

				Alert_Manager::trigger_event_if( $alert_code, $variables, array( __CLASS__, 'check_if_duplicate' ) );
				$has_alert_triggered = true;

				// Handling form rename. Check if this is a form and if an old title is set.
			} elseif ( isset( self::$old_post->post_title ) && self::$old_post->post_title !== $post->post_title && 'wpforms' === $post->post_type && $update ) {

				// Checking to ensure this is not a draft or fresh form.
				if ( isset( $post->post_status ) && 'auto-draft' !== $post->post_status ) {
					$alert_code  = 5506;
					$post        = get_post( $post_id );
					$editor_link = self::create_form_post_editor_link( $post_id );

					$variables = array(
						'EventType'      => 'renamed',
						'old_form_name'  => sanitize_text_field( self::$old_post->post_title ),
						'new_form_name'  => sanitize_text_field( $post->post_title ),
						'PostID'         => $post_id,
						'EditorLinkForm' => $editor_link,
					);

					Alert_Manager::trigger_event_if( $alert_code, $variables, array( __CLASS__, 'must_not_be_new_form' ) );
					$has_alert_triggered = true;
				}
			}

			// Handling duplicated forms by checking to see if the post has ID # in the title.
			if ( preg_match( '/\s\(ID #[0-9].*?\)/', $form->post_title ) && 'wpforms' === $form->post_type ) {
				$post_created  = new \DateTime( $form->post_date_gmt );
				$post_modified = new \DateTime( $form->post_modified_gmt );
				$alert_code    = 5502;

				// Check if this is indeed a new form.
				if ( $form->post_date_gmt === $form->post_modified_gmt ) {
					// Grab old form ID from its post content.
					$old_form_content = self::json_decode_encode( self::$old_post->post_content );
					$editor_link      = self::create_form_post_editor_link( $post_id );

					if ( isset( $old_form_content->id ) ) {
						$variables = array(
							'OldPostTitle'             => sanitize_text_field( self::$old_post->post_title ),
							'PostTitle'                => sanitize_text_field( $form->post_title ),
							'SourceID'                 => sanitize_text_field( $old_form_content->id ),
							'PostID'                   => $post_id,
							'EditorLinkFormDuplicated' => $editor_link,
						);
						Alert_Manager::trigger_event( $alert_code, $variables );
						$has_alert_triggered = true;
						remove_action( 'save_post', array( __CLASS__, 'event_form_saved' ), 10, 3 );
					}
				}
			}

			if ( 'wpforms' === $form->post_type && isset( self::$old_post ) ) {
				if ( isset( $post->post_status ) && 'auto-draft' !== $post->post_status ) {
					$form_content     = self::json_decode_encode( $form->post_content );
					$old_form_content = self::json_decode_encode( self::$old_post->post_content );
					$post_created     = new \DateTime( $post->post_date_gmt );
					$post_modified    = new \DateTime( $post->post_modified_gmt );
					$editor_link      = self::create_form_post_editor_link( $post_id );

					if ( isset( $form_content->settings->antispam ) && ! isset( $old_form_content->settings->antispam ) || isset( $old_form_content->settings->antispam ) && ! isset( $form_content->settings->antispam ) ) {
						$alert_code = 5513;
						$variables  = array(
							'EventType'      => ( isset( $form_content->settings->antispam ) ) ? 'enabled' : 'disabled',
							'form_name'      => sanitize_text_field( $form->post_title ),
							'form_id'        => $post_id,
							'EditorLinkForm' => $editor_link,
						);
						Alert_Manager::trigger_event( $alert_code, $variables );
					}
					if ( isset( $form_content->settings->dynamic_population ) && ! isset( $old_form_content->settings->dynamic_population ) || ! isset( $form_content->settings->dynamic_population ) && isset( $old_form_content->settings->dynamic_population ) ) {
						$alert_code = 5514;
						$variables  = array(
							'EventType'      => ( isset( $form_content->settings->dynamic_population ) ) ? 'enabled' : 'disabled',
							'form_name'      => sanitize_text_field( $form->post_title ),
							'form_id'        => $post_id,
							'EditorLinkForm' => $editor_link,
						);
						Alert_Manager::trigger_event( $alert_code, $variables );
					}
					if ( isset( $form_content->settings->ajax_submit ) && ! isset( $old_form_content->settings->ajax_submit ) || ! isset( $form_content->settings->ajax_submit ) && isset( $old_form_content->settings->ajax_submit ) ) {
						$alert_code = 5515;
						$variables  = array(
							'EventType'      => ( isset( $form_content->settings->ajax_submit ) ) ? 'enabled' : 'disabled',
							'form_name'      => sanitize_text_field( $form->post_title ),
							'form_id'        => $post_id,
							'EditorLinkForm' => $editor_link,
						);
						Alert_Manager::trigger_event( $alert_code, $variables );
					}

					if ( isset( $form_content->settings->confirmations ) && isset( $old_form_content->settings->confirmations ) ) {
						$form_content_array     = self::json_decode_encode( $form_content->settings->confirmations, true, true );
						$old_form_content_array = self::json_decode_encode( $old_form_content->settings->confirmations, true, true );

						$changes       = self::determine_added_removed_and_changed_items( $form_content_array, $old_form_content_array );
						$added_items   = $changes['added'];
						$removed_items = $changes['deleted'];
						$changed_items = $changes['modified'];

						// Check new content size determine if something has been added.
						if ( count( $form_content_array ) > count( $old_form_content_array ) ) {
							$alert_code = 5518;
							foreach ( $added_items as $confirmation ) {
								if ( isset( $confirmation['name'] ) ) {
									$confirmation_name = $confirmation['name'];
								} else {
									$confirmation_name = esc_html__( 'Default confirmation', 'wp-security-audit-log' );
								}
								$variables = array(
									'EventType'         => 'added',
									'confirmation_name' => sanitize_text_field( $confirmation_name ),
									'form_name'         => sanitize_text_field( $form->post_title ),
									'form_id'           => $post_id,
									'EditorLinkForm'    => $editor_link,
								);
								Alert_Manager::trigger_event_if( $alert_code, $variables, array( __CLASS__, 'must_not_be_new_form' ) );
								$has_alert_triggered = true;
							}
							// Check new content size determine if something has been removed.
						} elseif ( count( $form_content_array ) < count( $old_form_content_array ) ) {
							$alert_code = 5518;
							foreach ( $removed_items as $confirmation ) {
								if ( isset( $confirmation['name'] ) ) {
									$confirmation_name = $confirmation['name'];
								} else {
									$confirmation_name = esc_html__( 'Default confirmation', 'wp-security-audit-log' );
								}
								$variables = array(
									'EventType'         => 'deleted',
									'confirmation_name' => sanitize_text_field( $confirmation_name ),
									'form_name'         => sanitize_text_field( $form->post_title ),
									'form_id'           => $post_id,
									'EditorLinkForm'    => $editor_link,
								);
								Alert_Manager::trigger_event_if( $alert_code, $variables, array( __CLASS__, 'must_not_be_new_form' ) );
								$has_alert_triggered = true;
							}
						} elseif ( ! empty( $changed_items ) ) {
							foreach ( $changed_items as $key => $confirmation ) {
								$new_array = (array) $form_content->settings->confirmations;
								if ( empty( $new_array ) || ! isset( $new_array[ $key ] ) ) {
									continue;
								}
								$new_changed_item = (array) $new_array[ $key ];

								$confirmation_changes = array(
									'type',
									'page',
									'redirect',
									'message',
								);

								foreach ( $confirmation_changes as $change_type ) {
									if ( ! isset( $confirmation[ $change_type ] ) ) {
										continue;
									}

									if ( strip_tags( $new_changed_item[ $change_type ] ) !== strip_tags( $confirmation[ $change_type ] ) ) {
										if ( 'type' === $change_type ) {
											$alert_code = 5519;
										} elseif ( 'page' === $change_type ) {
											$alert_code                       = 5520;
											$new_changed_item[ $change_type ] = get_the_title( $new_changed_item[ $change_type ] );
											$confirmation[ $change_type ]     = get_the_title( $confirmation[ $change_type ] );
										} elseif ( 'redirect' === $change_type ) {
											$alert_code = 5521;
										} elseif ( 'message' === $change_type ) {
											$alert_code                   = 5522;
											$confirmation[ $change_type ] = sanitize_text_field( wp_strip_all_tags( $confirmation[ $change_type ] ) );
										}
										if ( ! isset( $alert_code ) ) {
											continue;
										}

										if ( isset( $new_changed_item['name'] ) ) {
											$confirmation_name = $new_changed_item['name'];
										} else {
											$confirmation_name = esc_html__( 'Default confirmation', 'wp-security-audit-log' );
										}

										$variables = array(
											'confirmation_name' => $confirmation_name,
											'old_value' => $confirmation[ $change_type ],
											'new_value' => $new_changed_item[ $change_type ],
											'form_name' => sanitize_text_field( $form_content->settings->form_title ),
											'form_id'   => $post_id,
											'EditorLinkForm' => $editor_link,
										);
										Alert_Manager::trigger_event_if( $alert_code, $variables, array( __CLASS__, 'must_not_be_new_form' ) );
										$has_alert_triggered = true;
									}
								}
							}
						}
					}
				}
			}

			// Handling form notifications.
			if ( 'wpforms' === $form->post_type && isset( self::$old_post ) && $update && ! Alert_Manager::was_triggered_recently( 5500 ) ) {
				// Checking to ensure this is not a draft or fresh form.
				if ( isset( $post->post_status ) && 'auto-draft' !== $post->post_status ) {
					$form_content     = self::json_decode_encode( $form->post_content );
					$old_form_content = self::json_decode_encode( self::$old_post->post_content );
					$post_created     = new \DateTime( $post->post_date_gmt );
					$post_modified    = new \DateTime( $post->post_modified_gmt );
					$editor_link      = self::create_form_post_editor_link( $post_id );

					// Create 2 arrays from the notification object for comparison later.
					if ( isset( $form_content->settings->notifications ) && isset( $old_form_content->settings->notifications ) ) {
						$form_content_array     = self::json_decode_encode( $form_content->settings->notifications, true, true );
						$old_form_content_array = self::json_decode_encode( $old_form_content->settings->notifications, true, true );

						$changes       = self::determine_added_removed_and_changed_items( $form_content_array, $old_form_content_array );
						$added_items   = $changes['added'];
						$removed_items = $changes['deleted'];
						$changed_items = $changes['modified'];

						// Check new content size determine if something has been added.
						if ( count( $form_content_array ) > count( $old_form_content_array ) ) {
							$alert_code = 5503;
							foreach ( $added_items as $notification ) {
								if ( isset( $notification['notification_name'] ) ) {
									$notification_name = $notification['notification_name'];
								} else {
									$notification_name = esc_html__( 'Default Notification', 'wp-security-audit-log' );
								}
								$variables = array(
									'EventType'        => 'created',
									'notifiation_name' => sanitize_text_field( $notification_name ),
									'form_name'        => sanitize_text_field( $form->post_title ),
									'PostID'           => $post_id,
									'EditorLinkForm'   => $editor_link,
								);
								Alert_Manager::trigger_event_if( $alert_code, $variables, array( __CLASS__, 'must_not_be_new_form' ) );
								$has_alert_triggered = true;
							}
							// Check new content size determine if something has been removed.
						} elseif ( count( $form_content_array ) < count( $old_form_content_array ) ) {
							$alert_code = 5503;
							foreach ( $removed_items as $notification ) {
								if ( isset( $notification['notification_name'] ) ) {
									$notification_name = $notification['notification_name'];
								} else {
									$notification_name = esc_html__( 'Default Notification', 'wp-security-audit-log' );
								}
								$variables = array(
									'EventType'        => 'deleted',
									'notifiation_name' => sanitize_text_field( $notification_name ),
									'form_name'        => sanitize_text_field( $form->post_title ),
									'PostID'           => $post_id,
									'EditorLinkForm'   => $editor_link,
								);
								Alert_Manager::trigger_event_if( $alert_code, $variables, array( __CLASS__, 'must_not_be_new_form' ) );
								$has_alert_triggered = true;
							}
							// Compare old post and new post to see if the notifications have been disabled.
						} elseif ( isset( $old_form_content->settings->notification_enable ) && ! isset( $form_content->settings->notification_enable ) ) {
							$alert_code = 5505;
							$variables  = array(
								'EventType'      => 'disabled',
								'form_name'      => sanitize_text_field( $form_content->settings->form_title ),
								'PostID'         => $post_id,
								'EditorLinkForm' => $editor_link,
							);
							Alert_Manager::trigger_event_if( $alert_code, $variables, array( __CLASS__, 'must_not_be_new_form' ) );
							$has_alert_triggered = true;

						} elseif ( ! isset( $old_form_content->settings->notification_enable ) && isset( $form_content->settings->notification_enable ) ) {
							$alert_code = 5505;
							$variables  = array(
								'EventType'      => 'enabled',
								'form_name'      => sanitize_text_field( $form_content->settings->form_title ),
								'PostID'         => $post_id,
								'EditorLinkForm' => $editor_link,
							);
							Alert_Manager::trigger_event_if( $alert_code, $variables, array( __CLASS__, 'must_not_be_new_form' ) );
							$has_alert_triggered = true;

							// Finally, as none of the above triggered anything, lets see if the notifications themselves have been modified.
						} elseif ( ! empty( $changed_items ) ) {

							// Check time and also if there is an actual change in the post content.
							if ( abs( $post_created->diff( $post_modified )->s ) <= 1 ) {
								// post hasn't changed return without event trigger.
								return;
							}

							$alert_code = 5503;
							foreach ( $changed_items as $key => $notification ) {
								if ( isset( $notification['notification_name'] ) ) {
									$notification_name = $notification['notification_name'];
								} else {
									$notification_name = esc_html__( 'Default Notification', 'wp-security-audit-log' );
								}

								$new_array        = (array) $form_content->settings->notifications;
								$new_changed_item = (array) $new_array[ $key ];
								$new_name         = isset( $new_changed_item['notification_name'] ) ? $new_changed_item['notification_name'] : false;

								if ( ! $new_name ) {
									continue;
								}

								$notification_metas = array(
									'email',
									'subject',
									'sender_name',
									'sender_address',
									'replyto',
									'message',
								);

								foreach ( $notification_metas as $metas ) {
									if ( isset( $changed_items[ $key ][ $metas ] ) && $new_changed_item[ $metas ] !== $changed_items[ $key ][ $metas ] ) {
										$alert_code = 5517;
										$variables  = array(
											'EventType' => 'modified',
											'metadata_name' => $metas,
											'old_value' => $changed_items[ $key ][ $metas ],
											'new_value' => $new_changed_item[ $metas ],
											'form_name' => sanitize_text_field( $form->post_title ),
											'notification_name' => $notification_name,
											'form_id'   => $post_id,
											'EditorLinkForm' => $editor_link,
										);
										Alert_Manager::trigger_event_if( $alert_code, $variables, array( __CLASS__, 'must_not_be_new_form' ) );
										$alert_code = null;
									}
								}

								if ( $notification_name !== $new_name ) {
									$alert_code = 5516;
									$variables  = array(
										'EventType'      => 'modified',
										'old_name'       => sanitize_text_field( $notification_name ),
										'new_name'       => sanitize_text_field( $new_name ),
										'form_name'      => sanitize_text_field( $form->post_title ),
										'form_id'        => $post_id,
										'EditorLinkForm' => $editor_link,
									);
								} else {
									$variables = array(
										'EventType'        => 'modified',
										'notifiation_name' => sanitize_text_field( $notification_name ),
										'form_name'        => sanitize_text_field( $form->post_title ),
										'PostID'           => $post_id,
										'EditorLinkForm'   => $editor_link,
									);
								}
								if ( $alert_code ) {
									Alert_Manager::trigger_event_if( $alert_code, $variables, array( __CLASS__, 'must_not_be_new_form' ) );
								}
								$has_alert_triggered = true;
							}
						}
					}
				}
			}

			// Handling fields.
			if ( 'wpforms' === $form->post_type && isset( self::$old_post ) ) {
				// Checking to ensure this is not a draft or fresh form.
				if ( isset( $post->post_status ) && 'auto-draft' !== $post->post_status ) {
					$form_content     = self::json_decode_encode( $form->post_content );
					$old_form_content = self::json_decode_encode( self::$old_post->post_content );
					$post_created     = new \DateTime( $post->post_date_gmt );
					$post_modified    = new \DateTime( $post->post_modified_gmt );
					$editor_link      = self::create_form_post_editor_link( $post_id );

					// First lets see if we have BOTH old and new content to compare.
					if ( isset( $form_content->fields ) && isset( $old_form_content->fields ) && serialize( $form_content->fields ) !== serialize( $old_form_content->fields ) ) {
						// Create 2 arrays from the fields object for comparison later.
						$form_content_array     = self::json_decode_encode( $form_content->fields, true, true );
						$old_form_content_array = self::json_decode_encode( $old_form_content->fields, true, true );

						// Compare the 2 arrays and create array of added items.
						if ( $form_content_array !== $old_form_content_array ) {
							$compare_added_items = array_diff(
								array_map( 'serialize', $form_content_array ),
								array_map( 'serialize', $old_form_content_array )
							);
							$added_items         = array_map( 'unserialize', $compare_added_items );
						} else {
							$added_items = $form_content_array;
						}

						$changes       = self::determine_added_removed_and_changed_items( $form_content_array, $old_form_content_array );
						$added_items   = $changes['added'];
						$removed_items = $changes['deleted'];
						$changed_items = $changes['modified'];
						$changed_items = array_intersect_key( $added_items, $changed_items );

						if ( ! empty( $added_items ) ) {
							$added_items = array_diff(
								array_map( 'serialize', $added_items ),
								array_map( 'serialize', $changed_items )
							);
							$added_items = array_map( 'unserialize', $added_items );
						}

						// Check new content size determine if something has been added.
						if ( $added_items && $added_items !== $changed_items ) {
							$alert_code = 5501;
							foreach ( $added_items as $fields ) {
								$field_name = self::get_type_if_field_has_no_name( $fields );
								$variables  = array(
									'EventType'      => 'created',
									'field_name'     => $field_name,
									'form_name'      => sanitize_text_field( $form->post_title ),
									'PostID'         => $post_id,
									'EditorLinkForm' => $editor_link,
								);
								Alert_Manager::trigger_event_if( $alert_code, $variables, array( __CLASS__, 'must_not_be_new_form' ) );
								$has_alert_triggered = true;
							}
						}

						// Check new content size determine if something has been removed.
						if ( $removed_items ) {
							$alert_code = 5501;
							foreach ( $removed_items as $fields => $value ) {

								if ( ! empty( $changed_items ) ) {
									if ( ! $changed_items[ $fields ] ) {
										$field_name = self::get_type_if_field_has_no_name( $value );
										$variables  = array(
											'EventType'  => 'deleted',
											'field_name' => $field_name,
											'form_name'  => sanitize_text_field( $form->post_title ),
											'PostID'     => $post_id,
											'EditorLinkForm' => $editor_link,
										);
										Alert_Manager::trigger_event_if( $alert_code, $variables, array( __CLASS__, 'must_not_be_new_form' ) );
										$has_alert_triggered = true;
									}
								} else {
									$field_name = self::get_type_if_field_has_no_name( $value );
									$variables  = array(
										'EventType'      => 'deleted',
										'field_name'     => $field_name,
										'form_name'      => sanitize_text_field( $form->post_title ),
										'PostID'         => $post_id,
										'EditorLinkForm' => $editor_link,
									);
									Alert_Manager::trigger_event_if( $alert_code, $variables, array( __CLASS__, 'must_not_be_new_form' ) );
									$has_alert_triggered = true;
								}
							}
						}

						// Check content to see if anything has been modified.
						if ( ! empty( $changed_items ) && ! Alert_Manager::was_triggered_recently( 5500 ) ) {
							$alert_code = 5501;
							foreach ( $changed_items as $fields ) {
								$field_name = self::get_type_if_field_has_no_name( $fields );
								$variables  = array(
									'EventType'      => 'modified',
									'field_name'     => $field_name,
									'form_name'      => sanitize_text_field( $form->post_title ),
									'PostID'         => $post_id,
									'EditorLinkForm' => $editor_link,
								);
								Alert_Manager::trigger_event_if( $alert_code, $variables, array( __CLASS__, 'must_not_be_new_form' ) );
								$has_alert_triggered = true;
							}
						}

						// Now we shall check if we have just a single new field thats been added.
					} elseif ( isset( $form_content->fields ) && ! isset( $old_form_content->fields ) ) {
						// Create 2 arrays from the fields object for comparison later.
						$form_content_array = self::json_decode_encode( $form_content->fields, true, true );
						$alert_code         = 5501;
						foreach ( $form_content_array as $fields ) {
							$variables = array(
								'EventType'      => 'created',
								'field_name'     => sanitize_text_field( $fields['label'] ),
								'form_name'      => sanitize_text_field( $form->post_title ),
								'PostID'         => $post_id,
								'EditorLinkForm' => $editor_link,
							);
							Alert_Manager::trigger_event_if( $alert_code, $variables, array( __CLASS__, 'must_not_be_new_form' ) );
							$has_alert_triggered = true;
						}

						// Finally we shall check if we have just a single new field thats been removed.
					} elseif ( ! isset( $form_content->fields ) && isset( $old_form_content->fields ) ) {
						// Create 2 arrays from the fields object for comparison later.
						$form_content_array = self::json_decode_encode( $old_form_content->fields, true, true );
						$alert_code         = 5501;
						foreach ( $form_content_array as $fields ) {
							$variables = array(
								'EventType'      => 'deleted',
								'field_name'     => sanitize_text_field( $fields['label'] ),
								'form_name'      => sanitize_text_field( $form->post_title ),
								'PostID'         => $post_id,
								'EditorLinkForm' => $editor_link,
							);
							Alert_Manager::trigger_event_if( $alert_code, $variables, array( __CLASS__, 'must_not_be_new_form' ) );
							$has_alert_triggered = true;
						}
					}
				}
			}

			// Finally, if all of the above didn't catch anything, but the form as still been modified in some way, lets handle that.
			if ( ! $has_alert_triggered && 'wpforms' === $form->post_type && isset( self::$old_post ) && ! $update && ! Alert_Manager::was_triggered_recently( 5500 ) ) {
				if ( isset( $post->post_status ) && 'auto-draft' !== $post->post_status ) {
					$alert_code       = 5500;
					$form_content     = self::json_decode_encode( $form->post_content );
					$old_form_content = self::json_decode_encode( self::$old_post->post_content );

					// First, lets check the content is available in the current and old post.
					if ( isset( $form_content ) && isset( $old_form_content ) ) {

						// Content is found, so lets create some arrays to compare for changes.
						$form_content_array     = self::json_decode_encode( $form_content, true, true );
						$old_form_content_array = self::json_decode_encode( $old_form_content, true, true );
						$compare_changed_items  = array_diff_assoc(
							array_map( 'serialize', $old_form_content_array ),
							array_map( 'serialize', $form_content_array )
						);

						// Round up any changes into a neat array, could expand in this later also.
						$changed_items = array_map( 'unserialize', $compare_changed_items );

						// Now lets check if anything has been added to our array, if it has, somethings changed so lets alert.
						if ( $changed_items ) {
							$editor_link = self::create_form_post_editor_link( $post_id );

							$variables = array(
								'EventType'      => 'modified',
								'PostTitle'      => sanitize_text_field( $post->post_title ),
								'PostID'         => $post_id,
								'EditorLinkForm' => $editor_link,
							);

							Alert_Manager::trigger_event_if( $alert_code, $variables, array( __CLASS__, 'check_if_duplicate' ) );
							remove_action( 'save_post', array( __CLASS__, 'event_form_saved' ), 10, 3 );
						}
					}
				}
			}
		}

		/**
		 * Form deleted event.
		 *
		 * Detect when a form has been fully deleted.
		 *
		 * @since 4.6.0
		 *
		 * @param int $post_id - Post ID.
		 */
		public static function event_form_deleted( $post_id ) {
			$alert_code = 5500;
			$post_id    = absint( $post_id );
			$post       = get_post( $post_id );
			if ( 'wpforms' === $post->post_type ) {
				$variables = array(
					'EventType' => 'deleted',
					'PostTitle' => $post->post_title,
					'PostID'    => $post_id,
				);

				Alert_Manager::trigger_event( $alert_code, $variables );
			}
		}

		/**
		 * Delete entry event.
		 *
		 * Detect when an entry has been deleted.
		 *
		 * @param int $row_id - Row ID.
		 *
		 * @since 4.6.0
		 */
		public static function event_entry_deleted( $row_id ) {
			$alert_code = 5504;

			if ( is_null( $row_id ) || ! isset( $row_id ) ) {
				return;
			}

			if ( \is_null( wpforms()->entry ) ) {
				return;
			}

			$entry = wpforms()->entry->get( $row_id );

			if ( is_null( $entry ) ) {
				return;
			}

			$form = get_post( $entry->form_id );

			// Grab from content.
			$form_content = (string) $entry->fields;

			// Search it for any email address.
			$email_address = self::extract_emails( $form_content );

			// Now lets see if we have more than one email present, if so, just grab the 1st one.
			if ( $email_address && is_array( $email_address ) ) {
				$email_address = $email_address[0];
			} elseif ( $email_address && ! is_array( $email_address ) ) {
				$email_address = $email_address;
			} else {
				$email_address = esc_html__( 'No email provided', 'wp-security-audit-log' );
			}

			$editor_link = self::create_form_post_editor_link( $entry->form_id );

			$variables = array(
				'entry_email'    => sanitize_text_field( $email_address ),
				'entry_id'       => sanitize_text_field( $row_id ),
				'form_name'      => sanitize_text_field( $form->post_title ),
				'form_id'        => $entry->form_id,
				'EditorLinkForm' => $editor_link,
			);
			Alert_Manager::trigger_event( $alert_code, $variables );
			remove_action( 'wpforms_pre_delete', array( __CLASS__, 'event_entry_deleted' ), 10, 1 );
		}

		/**
		 * Trigger event when entry is modified.
		 *
		 * @param array  $form_data - Form data.
		 * @param int    $response - Response.
		 * @param array  $updated_fields - New fields.
		 * @param object $entry - Entry data.
		 * @return void
		 *
		 * @since 4.6.0
		 */
		public static function event_entry_modified( $form_data, $response, $updated_fields, $entry ) {
			$alert_code = 5507;

			$fields = self::json_decode_encode( $entry->fields, false, true );

			foreach ( $updated_fields as $updated_field ) {

				$modified_value = array( array_search( $updated_field['name'], array_column( $fields, 'name', 'value' ), true ) );

				$editor_link = esc_url(
					add_query_arg(
						array(
							'view'     => 'edit',
							'entry_id' => $entry->entry_id,
						),
						admin_url( 'admin.php?page=wpforms-entries' )
					)
				);

				if ( isset( $updated_field['name'] ) ) {
					$variables = array(
						'entry_id'        => $entry->entry_id,
						'form_name'       => $form_data['settings']['form_title'],
						'field_name'      => $updated_field['name'],
						'old_value'       => implode( $modified_value ),
						'new_value'       => $updated_field['value'],
						'EditorLinkEntry' => $editor_link,
					);

					Alert_Manager::trigger_event( $alert_code, $variables );
				}
			}
		}

		/**
		 * Trigger event when settings are update.
		 *
		 * @param string $option_name - Option being updated.
		 * @param array  $old_value - Previous value.
		 * @param array  $value - Updated value.
		 * @return void
		 *
		 * @since 4.6.0
		 */
		public static function event_settings_updated( $option_name, $old_value, $value ) {

			if ( $value !== $old_value ) {

				if ( ! is_array( $old_value ) || ! is_array( $value ) ) {
					return;
				}

				// For access settings, we need to check its the correct thing updateing.
				if ( 'wp_user_roles' === $option_name ) {
					// Gather role names as we need them later.
					$roles = wp_roles()->get_names();
					// Array of possible capabilities which wpforms can add/remove from a role.
					$wpforms_caps = array( 'wpforms_create_forms', 'wpforms_view_own_forms', 'wpforms_view_others_forms', 'wpforms_edit_own_forms', 'wpforms_edit_others_forms', 'wpforms_delete_own_forms', 'wpforms_delete_others_forms', 'wpforms_view_entries_own_forms', 'wpforms_view_entries_others_forms', 'wpforms_edit_entries_own_forms', 'wpforms_edit_entries_others_forms', 'wpforms_delete_entries_own_forms', 'wpforms_delete_entries_others_forms' );
					// Create empty arrays to be filled later.
					$updated_new = array();
					$updated_old = array();

					// Loop through each availble role and build a simple array of the available
					// wpforms capabilities, assiging applicable roles to each as we find them.
					foreach ( $roles as $role_index_name => $role_label ) {

						// Create array of current values.
						if ( isset( $value[ $role_index_name ] ) ) {
							foreach ( $wpforms_caps as $capability ) {
								if ( self::array_key_exists_recursive( $capability, $value[ $role_index_name ] ) ) {

									$role = $value[ $role_index_name ]['name'];

									if ( ! isset( $updated_new[ $capability ]['roles'] ) ) {
										$updated_new[ $capability ]['roles'] = array();
									}
									$updated_new[ $capability ]['roles'] +=
									array( $role => $role );
								}
								// Fill up array with capability anyway, even if its blank.
								if ( ! isset( $updated_new[ $capability ] ) ) {
									$updated_new[ $capability ] = array(
										'roles' => array(),
									);
								}
							}
						}

						// Create array of old values for comparison.
						if ( isset( $old_value[ $role_index_name ] ) ) {
							foreach ( $wpforms_caps as $capability ) {
								if ( self::array_key_exists_recursive( $capability, $old_value[ $role_index_name ] ) ) {

									$role = $value[ $role_index_name ]['name'];

									if ( ! isset( $updated_old[ $capability ]['roles'] ) ) {
										$updated_old[ $capability ]['roles'] = array();
									}
									$updated_old[ $capability ]['roles'] +=
									array( $role => $role );
								}
								// Fill up array with capability anyway, even if its blank.
								if ( ! isset( $updated_old[ $capability ] ) ) {
									$updated_old[ $capability ] = array(
										'roles' => array(),
									);
								}
							}
						}
					}

					// Detect changes for each wpforms capability and fire off 5508 if a change is found.
					foreach ( $wpforms_caps as $wpforms_capability ) {
						// Compare old and new to see if something has been tinkered with.
						if ( isset( $updated_new[ $wpforms_capability ] ) && $updated_new[ $wpforms_capability ] !== $updated_old[ $wpforms_capability ] ) {
							$alert_code = 5508;
							// Tidy up name for event.
							$setting_name = ucwords( str_replace( '_', ' ', str_replace( 'wpforms', '', $wpforms_capability ) ) );
							// Determine the type of setting thats been changed.
							if ( strpos( $wpforms_capability, 'own' ) !== false ) {
								$setting_type = esc_html__( 'Own', 'wp-security-audit-log' );
							} elseif ( strpos( $wpforms_capability, 'other' ) !== false ) {
								$setting_type = esc_html__( 'Other', 'wp-security-audit-log' );
							} else {
								$setting_type = esc_html__( 'N/A', 'wp-security-audit-log' );
							}
							// Setup event variables using above.
							$variables = array(
								'setting_name' => $setting_name,
								'setting_type' => $setting_type,
								'old_value'    => implode( ', ', $updated_old[ $wpforms_capability ]['roles'] ),
								'new_value'    => implode( ', ', $updated_new[ $wpforms_capability ]['roles'] ),
							);
							// Fire off 5508.
							Alert_Manager::trigger_event( $alert_code, $variables );
						}
					}
				}

				// Event 5509 (Change of currency).
				if ( 'wpforms_settings' === $option_name && isset( $value['currency'] ) && function_exists( 'wpforms_get_currencies' ) ) {
					$wp_forms_currencies = wpforms_get_currencies();

					if ( isset( $old_value['currency'] ) ) {
						$old_value = $wp_forms_currencies[ $old_value['currency'] ]['name'] . ' (' . $old_value['currency'] . ')';
					} else {
						$old_value = null;
					}
					$alert_code = 5509;
					$variables  = array(
						'old_value' => $old_value,
						'new_value' => $wp_forms_currencies[ $value['currency'] ]['name'] . ' (' . $value['currency'] . ')',
					);

					Alert_Manager::trigger_event( $alert_code, $variables );
				}

				// Event 5510 (Integration enabled/disabled).
				if ( 'wpforms_providers' === $option_name ) {

					$providers = array(
						'mailchimpv3',
						'aweber',
						'constant-contact',
						'zapier',
						'getresponse',
						'drip',
						'campaign-monitor',
					);

					foreach ( $providers as $provider ) {
						if ( isset( $value[ $provider ] ) ) {
							if ( ! empty( $value[ $provider ] ) && empty( $old_value[ $provider ] ) ) {
								$event_type       = 'added';
								$connection_label = array_column( $value[ $provider ], 'label' );
							} else {
								$event_type       = 'deleted';
								$connection_label = array_column( $old_value[ $provider ], 'label' );
							}

							// Tidy labels up.
							if ( 'mailchimpv3' === $provider ) {
								$provider = esc_html__( 'Mailchimp', 'wp-security-audit-log' );
							} elseif ( 'getresponse' === $provider ) {
								$provider = esc_html__( 'GetResponse', 'wp-security-audit-log' );
							}

							$alert_code      = 5510;
							$connection_name = ! empty( $connection_label ) ? $connection_label[0] : null;
							$variables       = array(
								'EventType'       => $event_type,
								'service_name'    => ucwords( str_replace( '-', ' ', $provider ) ),
								'connection_name' => $connection_name,
							);
							Alert_Manager::trigger_event( $alert_code, $variables );
						}
					}
				}
			}
		}

		/**
		 * Detect initial changes to WPforms option. These typically use the "added_option"
		 * hook as no previous option is present to update.
		 *
		 * @param  string $option_name Name of option being changed.
		 * @param  array  $value       New values.
		 *
		 * @since 4.6.0
		 */
		public static function event_added_option( $option_name, $value ) {
			// Event 5509 (Initial change of currency).
			if ( 'wpforms_settings' === $option_name && isset( $value['currency'] ) && function_exists( 'wpforms_get_currencies' ) ) {
				$wp_forms_currencies = wpforms_get_currencies();
				$alert_code          = 5509;
				$variables           = array(
					'old_value' => $wp_forms_currencies['USD']['name'] . ' (USD)',
					'new_value' => $wp_forms_currencies[ $value['currency'] ]['name'] . ' (' . $value['currency'] . ')',
				);

				Alert_Manager::trigger_event( $alert_code, $variables );
			}
		}

		/**
		 * Trigger event when addon is activated.
		 *
		 * @param  string $plugin - Activated addon name.
		 * @return void
		 *
		 * @since 4.6.0
		 */
		public static function addon_plugin_activated( $plugin ) {
			$event_type = 'activated';
			self::generate_addon_event( $plugin, $event_type );
		}

		/**
		 * Trigger event when addon is deactivated.
		 *
		 * @param  string $plugin - Deactivated addon name.
		 * @return void
		 *
		 * @since 4.6.0
		 */
		public static function addon_plugin_deactivated( $plugin ) {
			$event_type = 'deactivated';
			self::generate_addon_event( $plugin, $event_type );
		}

		/**
		 * Trigger event when addon is installed.
		 *
		 * @param  string $plugin - Installed addon name.
		 * @return void
		 *
		 * @since 4.6.0
		 */
		public static function addon_plugin_installed( $plugin ) {
			$event_type = 'installed';
			self::generate_addon_event( $plugin, $event_type );
		}

		/**
		 * Trigger addon alert.
		 *
		 * @param string $plugin - Plugin being installed etc.
		 * @param string $event_type - Event type.
		 * @return void
		 *
		 * @since 4.6.0
		 */
		public static function generate_addon_event( $plugin, $event_type ) {
			$alert_code       = 5511;
			$tidy_plugin_name = preg_replace( '/\.[^.]+$/', '', basename( $plugin ) );
			$variables        = array(
				'EventType'  => $event_type,
				'addon_name' => str_replace( 'Wpforms', 'WPForms', ucwords( str_replace( '-', ' ', $tidy_plugin_name ) ) ),
			);
			Alert_Manager::trigger_event( $alert_code, $variables );
		}

		/**
		 * Method: This function make sures that alert 5501
		 * has not been triggered before triggering.
		 *
		 * @return bool
		 *
		 * @since 4.6.0
		 */
		public static function check_other_changes() {
			if ( Alert_Manager::will_or_has_triggered( 5501 ) ) {
				return false;
			}
			return true;
		}

		/**
		 * Method: This function make sures that alert 5500
		 * has not been triggered before triggering.
		 *
		 * @return bool
		 *
		 * @since 4.6.0
		 */
		public static function must_not_be_new_form() {
			if ( Alert_Manager::will_or_has_triggered( 5500 ) ) {
				return false;
			}
			return true;
		}

		/**
		 * Method: This function make sures that alert 5502
		 * has not been triggered before triggering
		 *
		 * @return bool
		 *
		 * @since 4.6.0
		 */
		public static function check_if_duplicate() {
			if ( Alert_Manager::will_or_has_triggered( 5502 ) ) {
				return false;
			}
			return true;
		}

		/**
		 * Extract email address from a string.
		 *
		 * @param string $string  - String to search.
		 *
		 * @return string
		 *
		 * @since 4.6.0
		 */
		private static function extract_emails( $string ) {
			// This regular expression extracts all emails from a string.
			$regexp = '/([a-z0-9_\.\-])+\@(([a-z0-9\-])+\.)+([a-z0-9]{2,4})+/i';
			preg_match( $regexp, $string, $m );
			return isset( $m[0] ) ? $m[0] : array();
		}

		/**
		 * Checks multi-dimensional arrays for a key.
		 *
		 * @param string $key - Needle..
		 * @param array  $array - Haystack.
		 * @return array|false;
		 *
		 * @since 4.6.0
		 */
		private static function array_key_exists_recursive( $key, $array ) {
			if ( is_array( $array ) && array_key_exists( $key, $array ) ) {
				return true;
			}
			if ( is_array( $array ) ) {
				foreach ( $array as $k => $value ) {
					if ( is_array( $value ) && self::array_key_exists_recursive( $key, $value ) ) {
						return true;
					}
				}
			}
			return false;
		}

		/**
		 * Return the fields type if it has no provided label to display.
		 *
		 * @param array $fields - Fields to check.
		 * @return string
		 *
		 * @since 4.6.0
		 */
		private static function get_type_if_field_has_no_name( $fields ) {
			return ( empty( $fields['label'] ) ) ? sanitize_text_field( $fields['type'] ) : sanitize_text_field( $fields['label'] );
		}

		/**
		 * Checks for added, removed or modified items given an old/new array.
		 *
		 * @param array $new_array - Newer array to compare.
		 * @param array $old_array - Older array to compare.
		 * @return array $result    - Array containing changes.
		 *
		 * @since 4.6.0
		 */
		private static function determine_added_removed_and_changed_items( $new_array, $old_array ) {
			$result = array(
				'added'    => array(),
				'deleted'  => array(),
				'modified' => array(),
			);
			// Compare the 2 arrays and create array of added items.
			$compare_added_items = array_diff(
				array_map( 'serialize', $new_array ),
				array_map( 'serialize', $old_array )
			);
			$added_items         = array_map( 'unserialize', $compare_added_items );
			$result['added']     = $added_items;

			// Compare the 2 arrays and create array of removed items.
			$compare_removed_items = array_diff(
				array_map( 'serialize', $old_array ),
				array_map( 'serialize', $new_array )
			);
			$removed_items         = array_map( 'unserialize', $compare_removed_items );
			$result['deleted']     = $removed_items;

			// Compare the 2 arrays and create array of changed.
			$compare_changed_items = array_diff_assoc(
				array_map( 'serialize', $old_array ),
				array_map( 'serialize', $new_array )
			);
			$changed_items         = array_map( 'unserialize', $compare_removed_items );
			$result['modified']    = $changed_items;

			return $result;
		}

		/**
		 * Creates an editor link for a given form_ID.
		 *
		 * @param  int $post_id        - Forms ID.
		 * @return string $editor_link - URL to edit screen.
		 *
		 * @since 4.6.0
		 */
		private static function create_form_post_editor_link( $post_id ) {
			$editor_link = esc_url(
				add_query_arg(
					array(
						'view'    => 'fields',
						'form_id' => $post_id,
					),
					admin_url( 'admin.php?page=wpforms-builder' )
				)
			);

			return $editor_link;
		}

		/**
		 * Decodes given item, using WP functions where possible.
		 *
		 * @param string  $item_to_decode - Item to be decoded.
		 * @param boolean $needs_encoding_first - Do we need to encode first.
		 * @param boolean $decode_associative - Return associative array from decode.
		 *
		 * @return mixed item_to_decode - Decoded string.
		 *
		 * @since 4.6.0
		 */
		private static function json_decode_encode( $item_to_decode, $needs_encoding_first = false, $decode_associative = null ) {
			if ( $needs_encoding_first ) {
				$item_to_decode = ( function_exists( 'wp_json_encode' ) ) ? wp_json_encode( $item_to_decode ) : json_encode( $item_to_decode );
			}
			return json_decode( $item_to_decode, $decode_associative );
		}
	}
}
