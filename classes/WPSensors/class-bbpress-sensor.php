<?php
/**
 * Sensor: BBPress.
 *
 * BBPress sensor class file.
 *
 * @since 1.0.0
 *
 * @package Wsal
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors;

use WSAL\Controllers\Alert_Manager;
use WSAL\Helpers\WP_Helper;
use WSAL\WP_Sensors\Helpers\BBPress_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\BBPress_Sensor' ) ) {
	/**
	 * Support for BBPress Forum Plugin.
	 *
	 * 8000 User created new forum
	 * 8001 User changed status of a forum
	 * 8002 User changed visibility of a forum
	 * 8003 User changed the URL of a forum
	 * 8004 User changed order of a forum
	 * 8005 User moved forum to trash
	 * 8006 User permanently deleted forum
	 * 8007 User restored forum from trash
	 * 8008 User changed the parent of a forum
	 * 8011 User changed type of a forum
	 * 8014 User created new topic
	 * 8015 User changed status of a topic
	 * 8016 User changed type of a topic
	 * 8017 User changed URL of a topic
	 * 8018 User changed the forum of a topic
	 * 8019 User moved topic to trash
	 * 8020 User permanently deleted topic
	 * 8021 User restored topic from trash
	 * 8022 User changed visibility of a topic
	 *
	 * @package Wsal
	 * @subpackage Sensors
	 */
	class BBPress_Sensor {
		/**
		 * Old permalink.
		 *
		 * @var string
		 */
		private static $oldlink = null;

		/**
		 * Listening to events using WP hooks.
		 *
		 * @since 4.6.0
		 */
		public static function init() {

			if ( BBPress_Helper::is_bbpress_active() ) {
				if ( current_user_can( 'edit_posts' ) ) {
					add_action( 'admin_init', array( __CLASS__, 'event_admin_init' ) );
				}
				add_action( 'post_updated', array( __CLASS__, 'check_forum_change' ), 10, 3 );
				add_action( 'delete_post', array( __CLASS__, 'event_forum_deleted' ), 10, 1 );
				add_action( 'wp_trash_post', array( __CLASS__, 'event_forum_trashed' ), 10, 1 );
				add_action( 'untrash_post', array( __CLASS__, 'event_forum_untrashed' ) );
				add_action( 'create_term', array( __CLASS__, 'check_tags_change' ), 10, 3 );
				add_action( 'delete_topic-tag', array( __CLASS__, 'event_topic_tag_deleted' ), 10, 4 );
				add_action( 'wp_update_term_data', array( __CLASS__, 'event_topic_tag_updated' ), 10, 4 );
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
			add_filter(
				'wsal_event_objects',
				array(
					'\WSAL\WP_Sensors\Helpers\BBPress_Helper',
					'wsal_bbpress_add_custom_event_objects',
				),
				10,
				2
			);
			if ( BBPress_Helper::is_bbpress_active() ) {
				add_filter(
					'wsal_togglealerts_obsolete_events',
					array(
						'\WSAL\WP_Sensors\Helpers\BBPress_Helper',
						'wsal_bbpress_extension_togglealerts_obsolete_events',
					)
				);
				add_filter(
					'wsal_ignored_custom_post_types',
					array(
						'\WSAL\WP_Sensors\Helpers\BBPress_Helper',
						'wsal_bbpress_extension_add_custom_ignored_cpt',
					)
				);
			}
		}

		/**
		 * Check for newly created topic tags.
		 *
		 * @param int    $term_id  - New term id.
		 * @param int    $tt_id    - New taxonomy id.
		 * @param string $taxonomy - Taxonomy type.
		 *
		 * @return int - Term id.
		 *
		 * @since 4.6.0
		 */
		public static function check_tags_change( $term_id, $tt_id, $taxonomy ) {
			if ( 'topic-tag' === $taxonomy ) {
				$term = get_term( $term_id );
				Alert_Manager::trigger_event(
					8024,
					array(
						'TagName'       => $term->name,
						'slug'          => $term->slug,
						'EditorLinkTag' => self::get_taxonomy_edit_link( $term_id ),
					)
				);
			}

			return $term_id;
		}

		/**
		 * Check Product Tag Deletion Event.
		 *
		 * @param int   $term_id      - Term ID.
		 * @param int   $tt_id        - Term taxonomy ID.
		 * @param mixed $deleted_term - Copy of the already-deleted term, in the form specified by the parent function. WP_Error otherwise.
		 * @param array $object_ids   - List of term object IDs.
		 *
		 * @since 4.6.0
		 */
		public static function event_topic_tag_deleted( $term_id, $tt_id, $deleted_term, $object_ids ) {
			if ( 'topic-tag' === $deleted_term->taxonomy ) {
				Alert_Manager::trigger_event(
					8025,
					array(
						'TagName' => sanitize_text_field( $deleted_term->name ),
						'slug'    => sanitize_text_field( $deleted_term->slug ),
					)
				);
			}
		}

		/**
		 * Check Product Category Updated Events.
		 *
		 * @param array  $data     - Term data to be updated.
		 * @param int    $term_id  - Term ID.
		 * @param string $taxonomy - Taxonomy slug.
		 * @param array  $args     - Arguments passed to wp_update_term().
		 *
		 * @since 4.6.0
		 */
		public static function event_topic_tag_updated( $data, $term_id, $taxonomy, $args ) {
			if ( 'topic-tag' === $taxonomy ) {
				// Get term data.
				$new_name      = isset( $data['name'] ) ? $data['name'] : false;
				$new_slug      = isset( $data['slug'] ) ? $data['slug'] : false;
				$new_parent_id = isset( $args['parent'] ) ? $args['parent'] : false;

				// New parent category.
				$new_parent_cat = false;
				if ( 0 !== $new_parent_id ) {
					$new_parent_cat = get_term( $new_parent_id, $taxonomy );
				}

				// Get old data.
				$term     = get_term( $term_id, $taxonomy );
				$old_name = $term->name;
				$old_slug = $term->slug;

				// Old parent category.
				$old_parent_cat = false;
				if ( $term->parent ) {
					$old_parent_cat = get_term( $term->parent, $taxonomy );
				}

				// Update if both names are not same.
				if ( $old_name !== $new_name ) {
					Alert_Manager::trigger_event(
						8026,
						array(
							'OldName'       => sanitize_text_field( $old_name ),
							'NewName'       => sanitize_text_field( $new_name ),
							'slug'          => sanitize_text_field( $term->slug ),
							'EditorLinkTag' => self::get_taxonomy_edit_link( $term_id ),
						)
					);
				}

				// Update if both slugs are not same.
				if ( $old_slug !== $new_slug ) {
					Alert_Manager::trigger_event(
						8027,
						array(
							'TagName'       => sanitize_text_field( $new_name ),
							'slug'          => sanitize_text_field( $old_slug ),
							'NewSlug'       => sanitize_text_field( $new_slug ),
							'EditorLinkTag' => self::get_taxonomy_edit_link( $term_id ),
						)
					);
				}
			}

			return $data;
		}

		/**
		 * Builds category link.
		 *
		 * @param int    $tag_id   - Tag ID.
		 * @param string $taxonomy - Taxonomy.
		 *
		 * @return string|null - Link.
		 *
		 * @since 4.6.0
		 */
		private static function get_taxonomy_edit_link( $tag_id, $taxonomy = 'topic-tag' ) {
			$tag_args = array(
				'post_type' => 'topic',
				'taxonomy'  => $taxonomy,
				'tag_ID'    => $tag_id,
			);

			return ! empty( $tag_id ) ? add_query_arg( $tag_args, \network_admin_url( 'term.php' ) ) : null;
		}

		/**
		 * Triggered when a user accesses the admin area.
		 *
		 * @since 4.6.0
		 */
		public static function event_admin_init() {
			// Load old data, if applicable.
			self::retrieve_old_data();
			// Check for Ajax changes.
			self::trigger_ajax_change();
		}

		/**
		 * Retrieve Old data.
		 *
		 * @global mixed $_POST post data
		 *
		 * @since 4.6.0
		 */
		private static function retrieve_old_data() {
			// Filter $_POST array for security.
			$post_array = filter_input_array( INPUT_POST );

			if ( isset( $post_array['post_ID'] )
			&& isset( $post_array['_wpnonce'] )
			&& ! wp_verify_nonce( $post_array['_wpnonce'], 'update-post_' . $post_array['post_ID'] ) ) {
				return false;
			}

			if ( isset( $post_array ) && isset( $post_array['post_ID'] )
			&& ! ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			&& ! ( isset( $post_array['action'] ) && 'autosave' === $post_array['action'] )
			) {
				$post_id       = intval( $post_array['post_ID'] );
				self::$oldlink = get_permalink( $post_id );
			}
		}

		/**
		 * Calls event forum changes.
		 *
		 * @param int      $post_id - Post ID.
		 * @param stdClass $newpost - The new post.
		 * @param stdClass $oldpost - The old post.
		 *
		 * @since 4.6.0
		 */
		public static function check_forum_change( $post_id, $newpost, $oldpost ) {
			if ( self::check_bb_press( $oldpost ) ) {
				$changes = 0 + self::event_forum_creation( $oldpost, $newpost );
				if ( ! $changes ) {
					// Change Visibility.
					$changes = self::event_forum_changed_visibility( $oldpost );
					// Change Type.
					$changes = self::event_forum_changed_type( $oldpost );
					// Change status.
					$changes = self::event_forum_changed_status( $oldpost );
					// Change Order, Parent or URL.
					$changes = self::event_forum_changed( $oldpost, $newpost );
				}
			}
		}

		/**
		 * Permanently deleted.
		 *
		 * @param int $post_id Post ID.
		 *
		 * @since 4.6.0
		 */
		public static function event_forum_deleted( $post_id ) {
			$post = get_post( $post_id );
			if ( self::check_bb_press( $post ) ) {
				switch ( $post->post_type ) {
					case 'forum':
						self::event_forum_by_code( $post, 8006 );

						break;
					case 'topic':
						self::event_topic_by_code( $post, 8020 );

						break;
				}
			}
		}

		/**
		 * Moved to Trash.
		 *
		 * @param int $post_id Post ID.
		 *
		 * @since 4.6.0
		 */
		public static function event_forum_trashed( $post_id ) {
			$post = get_post( $post_id );
			if ( self::check_bb_press( $post ) ) {
				switch ( $post->post_type ) {
					case 'forum':
						self::event_forum_by_code( $post, 8005 );

						break;
					case 'topic':
						self::event_topic_by_code( $post, 8019 );

						break;
				}
			}
		}

		/**
		 * Restored from Trash.
		 *
		 * @param int $post_id Post ID.
		 *
		 * @since 4.6.0
		 */
		public static function event_forum_untrashed( $post_id ) {
			$post = get_post( $post_id );
			if ( self::check_bb_press( $post ) ) {
				switch ( $post->post_type ) {
					case 'forum':
						self::event_forum_by_code( $post, 8007 );

						break;
					case 'topic':
						self::event_topic_by_code( $post, 8021 );

						break;
				}
			}
		}

		/**
		 * Check post type.
		 *
		 * @param stdClass $post Post.
		 *
		 * @return bool
		 *
		 * @since 4.6.0
		 */
		private static function check_bb_press( $post ) {
			switch ( $post->post_type ) {
				case 'forum':
				case 'topic':
				case 'reply':
					return true;
				default:
					return false;
			}
		}

		/**
		 * Event post creation.
		 *
		 * @param stdClass $old_post - The old post.
		 * @param stdClass $new_post - The new post.
		 *
		 * @return bool
		 *
		 * @since 4.6.0
		 */
		private static function event_forum_creation( $old_post, $new_post ) {
			// Filter $_POST array for security.
			$post_array = filter_input_array( INPUT_POST );

			if ( isset( $post_array['post_ID'] )
			&& isset( $post_array['_wpnonce'] )
			&& ! wp_verify_nonce( $post_array['_wpnonce'], 'update-post_' . $post_array['post_ID'] ) ) {
				return false;
			}

			$original = isset( $post_array['original_post_status'] ) ? \sanitize_text_field( \wp_unslash( $post_array['original_post_status'] ) ) : '';
			if ( 'draft' === $old_post->post_status || 'auto-draft' === $original ) {
				$editor_link = self::get_editor_link( $new_post );
				if ( 'publish' === $new_post->post_status ) {
					switch ( $old_post->post_type ) {
						case 'forum':
							Alert_Manager::trigger_event(
								8000,
								array(
									'ForumName'          => $new_post->post_title,
									'ForumURL'           => get_permalink( $new_post->ID ),
									$editor_link['name'] => $editor_link['value'],
								)
							);

							break;
						case 'topic':
							Alert_Manager::trigger_event(
								8014,
								array(
									'TopicName'          => $new_post->post_title,
									'TopicURL'           => get_permalink( $new_post->ID ),
									$editor_link['name'] => $editor_link['value'],
								)
							);

							break;
					}

					return 1;
				}
			}

			return 0;
		}

		/**
		 * Event post changed visibility.
		 *
		 * @param stdClass $post The post.
		 *
		 * @return bool $result
		 *
		 * @since 4.6.0
		 */
		private static function event_forum_changed_visibility( $post ) {
			// Filter $_POST array for security.
			$post_array = filter_input_array( INPUT_POST );

			if ( isset( $post_array['post_ID'] )
			&& isset( $post_array['_wpnonce'] )
			&& ! wp_verify_nonce( $post_array['_wpnonce'], 'update-post_' . $post_array['post_ID'] ) ) {
				return false;
			}

			$result      = 0;
			$editor_link = self::get_editor_link( $post );
			switch ( $post->post_type ) {
				case 'forum':
					$old_visibility = ! empty( $post_array['visibility'] ) ? \sanitize_text_field( \wp_unslash( $post_array['visibility'] ) ) : '';
					$new_visibility = ! empty( $post_array['bbp_forum_visibility'] ) ? \sanitize_text_field( \wp_unslash( $post_array['bbp_forum_visibility'] ) ) : '';
					$new_visibility = ( 'publish' === $new_visibility ) ? 'public' : $new_visibility;

					if ( ! empty( $new_visibility ) && 'auto-draft' !== $old_visibility && $old_visibility !== $new_visibility ) {
						Alert_Manager::trigger_event(
							8002,
							array(
								'ForumName'          => $post->post_title,
								'OldVisibility'      => $old_visibility,
								'NewVisibility'      => $new_visibility,
								$editor_link['name'] => $editor_link['value'],
							)
						);
						$result = 1;
					}

					break;
				case 'topic':
					$old_visibility = ! empty( $post_array['hidden_post_visibility'] ) ? \sanitize_text_field( \wp_unslash( $post_array['hidden_post_visibility'] ) ) : '';
					$new_visibility = ! empty( $post_array['visibility'] ) ? \sanitize_text_field( \wp_unslash( $post_array['visibility'] ) ) : '';
					$new_visibility = ( 'password' === $new_visibility ) ? 'password protected' : $new_visibility;

					if ( ! empty( $new_visibility ) && 'auto-draft' !== $old_visibility && $old_visibility !== $new_visibility ) {
						Alert_Manager::trigger_event(
							8022,
							array(
								'TopicName'          => $post->post_title,
								'OldVisibility'      => $old_visibility,
								'NewVisibility'      => $new_visibility,
								$editor_link['name'] => $editor_link['value'],
							)
						);
						$result = 1;
					}

					break;
			}

			return $result;
		}

		/**
		 * Event post changed type.
		 *
		 * @param stdClass $post The post.
		 *
		 * @return bool $result
		 *
		 * @since 4.6.0
		 */
		private static function event_forum_changed_type( $post ) {
			// Filter $_POST array for security.
			$post_array = filter_input_array( INPUT_POST );

			if ( isset( $post_array['post_ID'] )
			&& isset( $post_array['_wpnonce'] )
			&& ! wp_verify_nonce( $post_array['_wpnonce'], 'update-post_' . $post_array['post_ID'] ) ) {
				return false;
			}

			$result      = 0;
			$editor_link = self::get_editor_link( $post );
			switch ( $post->post_type ) {
				case 'forum':
					$bbp_forum_type = get_post_meta( $post->ID, '_bbp_forum_type', true );
					$old_type       = ! empty( $bbp_forum_type ) ? $bbp_forum_type : 'forum';
					$new_type       = ! empty( $post_array['bbp_forum_type'] ) ? \sanitize_text_field( \wp_unslash( $post_array['bbp_forum_type'] ) ) : '';
					if ( ! empty( $new_type ) && $old_type !== $new_type ) {
						Alert_Manager::trigger_event(
							8011,
							array(
								'ForumName'          => $post->post_title,
								'OldType'            => $old_type,
								'NewType'            => $new_type,
								$editor_link['name'] => $editor_link['value'],
							)
						);
						$result = 1;
					}

					break;
				case 'topic':
					if ( ! empty( $post_array['parent_id'] ) ) {
						$post_id = \intval( $post_array['parent_id'] );
					} else {
						$post_id = $post->ID;
					}
					$bbp_sticky_topics       = maybe_unserialize( get_post_meta( $post_id, '_bbp_sticky_topics', true ) );
					$fn                      = WP_Helper::is_multisite() ? 'get_site_option' : 'get_option';
					$bbp_super_sticky_topics = maybe_unserialize( $fn( '_bbp_super_sticky_topics' ) );
					if ( ! empty( $bbp_sticky_topics ) && in_array( $post->ID, $bbp_sticky_topics, true ) ) {
						$old_type = 'sticky';
					} elseif ( ! empty( $bbp_super_sticky_topics ) && in_array( $post->ID, $bbp_super_sticky_topics, true ) ) {
						$old_type = 'super';
					} else {
						$old_type = 'unstick';
					}
					$new_type = ! empty( $post_array['bbp_stick_topic'] ) ? \sanitize_text_field( \wp_unslash( $post_array['bbp_stick_topic'] ) ) : '';
					if ( ! empty( $new_type ) && $old_type !== $new_type ) {
						Alert_Manager::trigger_event(
							8016,
							array(
								'TopicName'          => $post->post_title,
								'OldType'            => ( 'unstick' === $old_type ) ? 'normal' : ( ( 'super' === $old_type ) ? 'super sticky' : $old_type ),
								'NewType'            => ( 'unstick' === $new_type ) ? 'normal' : ( ( 'super' === $new_type ) ? 'super sticky' : $new_type ),
								$editor_link['name'] => $editor_link['value'],
							)
						);
						$result = 1;
					}

					break;
			}

			return $result;
		}

		/**
		 * Event post changed status.
		 *
		 * @param stdClass $post The post.
		 *
		 * @return bool $result
		 *
		 * @since 4.6.0
		 */
		private static function event_forum_changed_status( $post ) {
			// Filter $_POST and $_GET array for security.
			$post_array = filter_input_array( INPUT_POST );
			$get_array  = filter_input_array( INPUT_GET );

			if ( isset( $post_array['post_ID'] )
			&& isset( $post_array['_wpnonce'] )
			&& ! wp_verify_nonce( $post_array['_wpnonce'], 'update-post_' . $post_array['post_ID'] ) ) {
				return false;
			}

			$result      = 0;
			$editor_link = self::get_editor_link( $post );
			switch ( $post->post_type ) {
				case 'forum':
					$bbp_status = get_post_meta( $post->ID, '_bbp_status', true );
					$old_status = ! empty( $bbp_status ) ? $bbp_status : 'open';
					$new_status = ! empty( $post_array['bbp_forum_status'] ) ? \sanitize_text_field( \wp_unslash( $post_array['bbp_forum_status'] ) ) : '';
					if ( ! empty( $new_status ) && $old_status !== $new_status ) {
						Alert_Manager::trigger_event(
							8001,
							array(
								'ForumName'          => $post->post_title,
								'OldStatus'          => $old_status,
								'NewStatus'          => $new_status,
								$editor_link['name'] => $editor_link['value'],
							)
						);
						$result = 1;
					}

					break;
				case 'topic':
					$old_status = ! empty( $post_array['original_post_status'] ) ? \sanitize_text_field( \wp_unslash( $post_array['original_post_status'] ) ) : '';
					$new_status = ! empty( $post_array['post_status'] ) ? \sanitize_text_field( \wp_unslash( $post_array['post_status'] ) ) : '';
					// In case of Ajax request Spam/Not spam.
					if ( isset( $get_array['action'] ) && 'bbp_toggle_topic_spam' === $get_array['action'] ) {
						$old_status = $post->post_status;
						$new_status = 'spam';
						if ( isset( $get_array['post_status'] ) && 'spam' === $get_array['post_status'] ) {
							$new_status = 'publish';
						}
					}
					// In case of Ajax request Close/Open.
					if ( isset( $get_array['action'] ) && 'bbp_toggle_topic_close' === $get_array['action'] ) {
						$old_status = $post->post_status;
						$new_status = 'closed';
						if ( isset( $get_array['post_status'] ) && 'closed' === $get_array['post_status'] ) {
							$new_status = 'publish';
						}
					}
					if ( ! empty( $new_status ) && $old_status !== $new_status ) {
						Alert_Manager::trigger_event(
							8015,
							array(
								'TopicName'          => $post->post_title,
								'OldStatus'          => ( 'publish' === $old_status ) ? 'open' : $old_status,
								'NewStatus'          => ( 'publish' === $new_status ) ? 'open' : $new_status,
								$editor_link['name'] => $editor_link['value'],
							)
						);
						$result = 1;
					}

					break;
			}

			return $result;
		}

		/**
		 * Event post changed (order, parent, URL).
		 *
		 * @param stdClass $old_post - The old post.
		 * @param stdClass $new_post - The new post.
		 *
		 * @return bool $result
		 *
		 * @since 4.6.0
		 */
		private static function event_forum_changed( $old_post, $new_post ) {
			$editor_link = self::get_editor_link( $new_post );
			// Changed Order.
			if ( $old_post->menu_order !== $new_post->menu_order ) {
				Alert_Manager::trigger_event(
					8004,
					array(
						'ForumName'          => $new_post->post_title,
						'OldOrder'           => $old_post->menu_order,
						'NewOrder'           => $new_post->menu_order,
						$editor_link['name'] => $editor_link['value'],
					)
				);

				return 1;
			}
			// Changed Parent.
			if ( $old_post->post_parent !== $new_post->post_parent ) {
				switch ( $old_post->post_type ) {
					case 'forum':
						Alert_Manager::trigger_event(
							8008,
							array(
								'ForumName'          => $new_post->post_title,
								'OldParent'          => $old_post->post_parent ? get_the_title( $old_post->post_parent ) : 'no parent',
								'NewParent'          => $new_post->post_parent ? get_the_title( $new_post->post_parent ) : 'no parent',
								$editor_link['name'] => $editor_link['value'],
							)
						);

						break;
					case 'topic':
						Alert_Manager::trigger_event(
							8018,
							array(
								'TopicName'          => $new_post->post_title,
								'OldForum'           => $old_post->post_parent ? get_the_title( $old_post->post_parent ) : 'no parent',
								'NewForum'           => $new_post->post_parent ? get_the_title( $new_post->post_parent ) : 'no parent',
								$editor_link['name'] => $editor_link['value'],
							)
						);

						break;
				}

				return 1;
			}
			// Changed URL.
			$old_link = self::$oldlink;
			$new_link = get_permalink( $new_post->ID );
			if ( ! empty( $old_link ) && $old_link !== $new_link ) {
				switch ( $old_post->post_type ) {
					case 'forum':
						Alert_Manager::trigger_event(
							8003,
							array(
								'ForumName'          => $new_post->post_title,
								'OldUrl'             => $old_link,
								'NewUrl'             => $new_link,
								$editor_link['name'] => $editor_link['value'],
							)
						);

						break;
					case 'topic':
						Alert_Manager::trigger_event(
							8017,
							array(
								'TopicName'          => $new_post->post_title,
								'OldUrl'             => $old_link,
								'NewUrl'             => $new_link,
								$editor_link['name'] => $editor_link['value'],
							)
						);

						break;
				}

				return 1;
			}

			return 0;
		}

		/**
		 * Trigger Event (Forum).
		 *
		 * @param stdClass $post  - The post.
		 * @param int      $event - Event code.
		 *
		 * @since 4.6.0
		 */
		private static function event_forum_by_code( $post, $event ) {
			$editor_link = self::get_editor_link( $post );
			Alert_Manager::trigger_event(
				$event,
				array(
					'ForumID'            => $post->ID,
					'ForumName'          => $post->post_title,
					$editor_link['name'] => $editor_link['value'],
				)
			);
		}

		/**
		 * Trigger Event (Topic).
		 *
		 * @param stdClass $post  - The post.
		 * @param int      $event - Event code.
		 *
		 * @since 4.6.0
		 */
		private static function event_topic_by_code( $post, $event ) {
			$editor_link = self::get_editor_link( $post );
			Alert_Manager::trigger_event(
				$event,
				array(
					'TopicID'            => $post->ID,
					'TopicName'          => $post->post_title,
					$editor_link['name'] => $editor_link['value'],
				)
			);
		}

		/**
		 * Trigger of ajax events generated in the Topic Grid.
		 *
		 * @global mixed $_GET Get data
		 *
		 * @since 4.6.0
		 */
		public static function trigger_ajax_change() {
			// Filter $_GET array for security.
			$get_array = filter_input_array( INPUT_GET );

			if ( ! empty( $get_array['post_type'] ) && ! empty( $get_array['topic_id'] ) ) {
				if ( 'topic' === $get_array['post_type'] ) {
					$post = get_post( $get_array['topic_id'] );

					// Topic type.
					if ( isset( $get_array['action'] ) && 'bbp_toggle_topic_stick' === $get_array['action'] ) {
						if ( ! empty( $post->post_parent ) ) {
							$post_id = $post->post_parent;
						} else {
							$post_id = $get_array['topic_id'];
						}

						$bbp_sticky_topics       = maybe_unserialize( get_post_meta( $post_id, '_bbp_sticky_topics', true ) );
						$fn                      = WP_Helper::is_multisite() ? 'get_site_option' : 'get_option';
						$bbp_super_sticky_topics = maybe_unserialize( $fn( '_bbp_super_sticky_topics' ) );
						if ( ! empty( $bbp_sticky_topics ) && in_array( $get_array['topic_id'], $bbp_sticky_topics, true ) ) {
							$old_type = 'sticky';
						} elseif ( ! empty( $bbp_super_sticky_topics ) && in_array( $get_array['topic_id'], $bbp_super_sticky_topics, true ) ) {
							$old_type = 'super sticky';
						} else {
							$old_type = 'normal';
						}

						switch ( $old_type ) {
							case 'sticky':
							case 'super sticky':
								$new_type = 'normal';

								break;
							case 'normal':
								if ( isset( $get_array['super'] ) && 1 === $get_array['super'] ) {
									$new_type = 'super sticky';
								} else {
									$new_type = 'sticky';
								}

								break;
						}
						$editor_link = self::get_editor_link( $post );

						if ( ! empty( $new_type ) && $old_type !== $new_type ) {
							Alert_Manager::trigger_event(
								8016,
								array(
									'TopicName'          => $post->post_title,
									'OldType'            => $old_type,
									'NewType'            => $new_type,
									$editor_link['name'] => $editor_link['value'],
								)
							);
						}
					}
				}
			}

			if ( ! empty( $get_array['post_type'] ) && ! empty( $get_array['forum_id'] ) ) {
				if ( 'forum' === $get_array['post_type'] ) {
					$post = get_post( $get_array['forum_id'] );

					// Topic type.
					if ( isset( $get_array['action'] ) && 'bbp_toggle_forum_close' === $get_array['action'] ) {
						if ( ! empty( $post->post_parent ) ) {
							$post_id = $post->post_parent;
						} else {
							$post_id = $get_array['forum_id'];
						}

						$editor_link = self::get_editor_link( $post );

						$bbp_status = get_post_meta( $post->ID, '_bbp_status', true );
						$old_status = ! empty( $bbp_status ) ? $bbp_status : 'open';
						$new_status = ( 'closed' === $old_status ) ? 'open' : 'closed';
						if ( ! empty( $new_status ) && $old_status !== $new_status ) {
							Alert_Manager::trigger_event(
								8001,
								array(
									'ForumName'          => $post->post_title,
									'OldStatus'          => $old_status,
									'NewStatus'          => $new_status,
									$editor_link['name'] => $editor_link['value'],
								)
							);
						}
					}
				}
			}
		}

		/**
		 * Get editor link.
		 *
		 * @param stdClass $post - The post.
		 *
		 * @return array $editor_link_array - Name and value link.
		 *
		 * @since 4.6.0
		 */
		private static function get_editor_link( $post ) {
			$name = 'EditorLink';
			switch ( $post->post_type ) {
				case 'forum':
					$name .= 'Forum';

					break;
				case 'topic':
					$name .= 'Topic';

					break;
			}
			$value             = get_edit_post_link( $post->ID );
			$editor_link_array = array(
				'name'  => $name,
				'value' => $value,
			);

			return $editor_link_array;
		}
	}
}
