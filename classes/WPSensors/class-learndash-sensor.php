<?php
/**
 * Custom Sensors for LearnDash plugin.
 *
 * Class file for alert manager.
 *
 * @package wsal
 * @subpackage wsal-learndash
 *
 * @since 5.6.0
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors;

use WSAL\Helpers\Settings_Helper;
use WSAL\Controllers\Alert_Manager;
use WSAL\Helpers\DateTime_Formatter_Helper;
use WSAL\WP_Sensors\Helpers\LearnDash_Helper;
use WSAL\WP_Sensors\Alerts\LearnDash_Custom_Alerts;

// Exit if accessed directly.
if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! \class_exists( '\WSAL\WP_Sensors\LearnDash_Sensor' ) ) {
	/**
	 * Custom sensor for LearnDash plugin.
	 *
	 * @since 5.6.0
	 */
	class LearnDash_Sensor {

		/**
		 * Course steps metadata store before updating metadata.
		 *
		 * Structure: [ (string) $post_id => array( 'lessons' => [...], 'topics' => [...], 'quizzes' => [...] ) ]
		 *
		 * @var array
		 *
		 * @since 5.6.0
		 */
		private static $before_course_steps = array();

		/**
		 * Course metadata store before updating metadata.
		 * ! used specifically to track changes in the _sfwd-courses metadata.
		 *
		 * Structure: [ (string) $post_id => array( 'certificate' => $cert_id, 'price_type' => $price_type ) ]
		 *
		 * @var array
		 *
		 * @since 5.6.0
		 */
		private static $before_course_meta = array();

		/**
		 * Group metadata store before updating metadata.
		 * Used specifically to track changes in the _groups metadata.
		 *
		 * Structure: [ (string) $post_id => array( 'groups_group_start_date' => $timestamp, 'groups_group_end_date' => $timestamp ) ]
		 *
		 * @var array
		 *
		 * @since 5.6.0
		 */
		private static $before_group_meta = array();

		/**
		 * Miscellaneous single-value metadata store for tracking before-state of metadata updates.
		 * Structure: [ (string) $post_id ][ $meta_key ] = $meta_value
		 *
		 * @var array
		 *
		 * @since 5.6.0
		 */
		private static $ld_misc_metadata = array();

		/**
		 * Course users before update.
		 * Structure: [ (int) $course_id => array( $user_id_1, $user_id_2, ... ) ]
		 *
		 * @var array
		 *
		 * @since 5.6.0
		 */
		private static $before_course_users = array();

		/**
		 * Group users before update.
		 * Structure: [ (int) $group_id => array( $user_id_1, $user_id_2, ... ) ]
		 *
		 * @var array
		 *
		 * @since 5.6.0
		 */
		private static $before_group_users = array();

		/**
		 * Stores the source post ID during a duplication/cloning request.
		 *
		 * @var int|null
		 *
		 * @since 5.6.0
		 */
		private static $duplication_source_id = null;

		/**
		 * Original LearnDash dates captured early in admin_init before any meta updates.
		 *
		 * Structure: [ (int) $post_id => array(
		 *     'post_type'  => 'sfwd-courses' or 'groups',
		 *     'start_date' => $timestamp,
		 *     'end_date'   => $timestamp,
		 * )]
		 *
		 * @var array
		 *
		 * @since 5.6.0
		 */
		private static $before_ld_start_end_dates = array();

		/**
		 * Pending date change events to be checked after post save completes.
		 * Used to prevent duplicate events from LearnDash's multiple meta updates,
		 * since Learndash will trigger update meta twice: one for start date and one for end date.
		 *
		 * Structure: [ (int) $post_id => array(
		 *     'meta_key' => '_sfwd-courses' or '_groups',
		 *     'checked'  => false,
		 * )]
		 *
		 * @var array
		 *
		 * @since 5.6.0
		 */
		private static $pending_date_events = array();

		/**
		 * Loads all the plugin dependencies early.
		 *
		 * @return void
		 *
		 * @since 5.6.0
		 */
		public static function early_init() {
			if ( LearnDash_Helper::is_learndash_active() ) {
				\add_filter(
					'wsal_default_disabled_alerts',
					array( LearnDash_Custom_Alerts::class, 'add_default_disabled_alerts' )
				);

				self::maybe_apply_first_detection_disabled_alerts();

				\add_filter(
					'wsal_event_objects',
					array( LearnDash_Helper::class, 'wsal_learndash_add_custom_event_objects' ),
					10,
					2
				);

				\add_filter(
					'wsal_ignored_custom_post_types',
					array( LearnDash_Helper::class, 'wsal_learndash_add_custom_ignored_cpt' )
				);

			}
		}

		/**
		 * Hook events related to sensor.
		 *
		 * @return void
		 *
		 * @since 5.6.0
		 */
		public static function init() {
			if ( LearnDash_Helper::is_learndash_active() && \is_user_logged_in() ) {
				\add_action( 'admin_init', array( __CLASS__, 'store_prev_user_activity_data' ) );
				\add_action( 'admin_init', array( __CLASS__, 'maybe_save_duplicate_action_post_id' ) );
				\add_action( 'wp_after_insert_post', array( __CLASS__, 'ld_saved_post_event_triggers' ), 10, 4 );
				\add_action( 'wp_trash_post', array( __CLASS__, 'ld_post_trashed' ), 10, 1 );
				\add_action( 'before_delete_post', array( __CLASS__, 'ld_post_perma_deleted' ), 10, 2 );
				\add_action( 'untrash_post', array( __CLASS__, 'ld_post_restored' ), 10, 1 );

				\add_action( 'create_term', array( __CLASS__, 'ld_taxonomy_creation_triggers' ), 10, 4 );
				\add_action( 'delete_term', array( __CLASS__, 'ld_taxonomy_deletion_triggers' ), 10, 5 );

			}
		}

		/**
		 * Triggered when a user accesses the admin area.
		 *
		 * @since 5.6.0
		 */
		public static function store_prev_user_activity_data() {
			// Verify nonce before accessing any POST data.
			$nonce   = isset( $_POST['_wpnonce'] ) ? \sanitize_text_field( \wp_unslash( $_POST['_wpnonce'] ) ) : '';
			$post_id = isset( $_POST['post_ID'] ) ? (int) $_POST['post_ID'] : 0;

			if ( empty( $nonce ) || ! \wp_verify_nonce( $nonce, 'update-post_' . $post_id ) ) {
				return;
			}

			$post_type = \sanitize_text_field( \wp_unslash( $_POST['post_type'] ?? '' ) );

			if ( 'sfwd-courses' === $post_type ) {
				if ( isset( $_POST['post_ID'] ) && isset( $_POST['learndash_course_users_nonce'] ) ) {
					$post_id = (int) $_POST['post_ID'];

					if ( \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['learndash_course_users_nonce'] ) ), 'learndash_course_users_nonce_' . $post_id ) ) {
						if ( \function_exists( 'learndash_get_course_users_access_from_meta' ) ) {
							self::$before_course_users[ $post_id ] = \learndash_get_course_users_access_from_meta( $post_id );
						}
					}
				}
			}

			if ( 'groups' === $post_type ) {
				if ( isset( $_POST['post_ID'] ) ) {
					$post_id      = (int) $_POST['post_ID'];
					$nonce_field  = 'learndash_group_users-' . $post_id . '-nonce';
					$nonce_action = 'learndash_group_users-' . $post_id;

					if ( isset( $_POST[ $nonce_field ] ) ) {
						if ( \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST[ $nonce_field ] ) ), $nonce_action ) ) {
							if ( \function_exists( 'learndash_get_groups_user_ids' ) ) {
								self::$before_group_users[ $post_id ] = \learndash_get_groups_user_ids( $post_id, true );
							}
						}
					}
				}
			}
		}

		/**
		 * Captures original LearnDash dates from DB before any meta updates.
		 * This method handles both courses and groups.
		 *
		 * @return void
		 *
		 * @since 5.6.0
		 */
		public static function capture_before_ld_start_end_dates() {
			// Verify nonce before accessing any POST data.
			$nonce   = isset( $_POST['_wpnonce'] ) ? \sanitize_text_field( \wp_unslash( $_POST['_wpnonce'] ) ) : '';
			$post_id = isset( $_POST['post_ID'] ) ? (int) $_POST['post_ID'] : 0;

			if ( empty( $nonce ) || ! \wp_verify_nonce( $nonce, 'update-post_' . $post_id ) ) {
				return;
			}

			$post_type = \sanitize_text_field( \wp_unslash( $_POST['post_type'] ?? '' ) );

			// Only process courses and groups.
			if ( 'sfwd-courses' !== $post_type && 'groups' !== $post_type ) {
				return;
			}

			$post_dates = LearnDash_Helper::get_ld_start_end_dates_meta( $post_id, $post_type );

			if ( isset( $post_dates['start_date'] ) && isset( $post_dates['end_date'] ) ) {
				// Store original dates.
				self::$before_ld_start_end_dates[ $post_id ] = array(
					'post_type'  => $post_type,
					'start_date' => $post_dates['start_date'],
					'end_date'   => $post_dates['end_date'],
				);
			}
		}

		/**
		 * Checks and triggers pending date change events after post save completes.
		 * This fires once per request after all LearnDash meta updates are complete,
		 * preventing duplicate events from multiple meta_update hook calls that would happen with LearnDash.
		 *
		 * @param int $post_id - Post ID.
		 *
		 * @return void
		 *
		 * @since 5.6.0
		 */
		public static function maybe_trigger_start_end_date_events( int $post_id ) {

			// Check if this post has pending date events to check.
			if ( ! isset( self::$pending_date_events[ $post_id ] ) ) {
				return;
			}

			// Verify we have original dates captured.
			if ( ! isset( self::$before_ld_start_end_dates[ $post_id ] ) ) {
				return;
			}

			// Skip if already checked.
			if ( self::$pending_date_events[ $post_id ]['checked'] ) {
				return;
			}

			// Mark as checked to prevent duplicate processing.
			self::$pending_date_events[ $post_id ]['checked'] = true;

			// Get the meta key to check.
			$meta_key = self::$pending_date_events[ $post_id ]['meta_key'];

			// Get values after updates from database after all meta updates.
			$final_meta     = \get_post_meta( $post_id, $meta_key, true );
			$final_metadata = \maybe_unserialize( $final_meta );

			if ( ! \is_array( $final_metadata ) ) {

				// Clean up for this post.
				unset( self::$pending_date_events[ $post_id ] );
				unset( self::$before_ld_start_end_dates[ $post_id ] );
				return;
			}

			// Get original dates from Phase 1 capture.
			$original_dates = self::$before_ld_start_end_dates[ $post_id ];

			// Determine event ID and date keys based on meta_key.
			if ( '_sfwd-courses' === $meta_key ) {
				$start_date_key = 'sfwd-courses_course_start_date';
				$end_date_key   = 'sfwd-courses_course_end_date';
				$event_id       = 11056;
			} elseif ( '_groups' === $meta_key ) {
				$start_date_key = 'groups_group_start_date';
				$end_date_key   = 'groups_group_end_date';
				$event_id       = 11507;
			} else {
				return;
			}

			/**
			 * Compare dates
			 */
			$original_start = LearnDash_Helper::cast_date_to_float( $original_dates['start_date'] );
			$original_end   = LearnDash_Helper::cast_date_to_float( $original_dates['end_date'] );
			$final_start    = LearnDash_Helper::cast_date_to_float( $final_metadata[ $start_date_key ] ?? '' );
			$final_end      = LearnDash_Helper::cast_date_to_float( $final_metadata[ $end_date_key ] ?? '' );

			// Only trigger if something actually changed.
			if ( $original_start === $final_start && $original_end === $final_end ) {
				// Clean up for this post.
				unset( self::$pending_date_events[ $post_id ] );
				unset( self::$before_ld_start_end_dates[ $post_id ] );
				return;
			}

			/**
			 * Format dates for display.
			 *
			 * LD format in case of need:
			 * $formatted_old_start = empty( $before_start_date ) ? $not_set_string : \date_i18n( 'd F Y @ H:i', (int) $before_start_date );
			 * $formatted_new_start = empty( $after_start_date ) ? $not_set_string : \date_i18n( 'd F Y @ H:i', (int) $after_start_date );
			 * $formatted_old_end   = empty( $before_end_date ) ? $not_set_string : \date_i18n( 'd F Y @ H:i', (int) $before_end_date );
			 * $formatted_new_end   = empty( $after_end_date ) ? $not_set_string : \date_i18n( 'd F Y @ H:i', (int) $after_end_date );
			 */
			$not_set_string      = \esc_html__( 'Not set', 'wp-security-audit-log' );
			$formatted_old_start = empty( $original_start ) ? $not_set_string : DateTime_Formatter_Helper::get_formatted_date_time( $original_start, 'datetime' );
			$formatted_new_start = empty( $final_start ) ? $not_set_string : DateTime_Formatter_Helper::get_formatted_date_time( $final_start, 'datetime' );
			$formatted_old_end   = empty( $original_end ) ? $not_set_string : DateTime_Formatter_Helper::get_formatted_date_time( $original_end, 'datetime' );
			$formatted_new_end   = empty( $final_end ) ? $not_set_string : DateTime_Formatter_Helper::get_formatted_date_time( $final_end, 'datetime' );

			$event_variables = array(
				'PostTitle'      => \get_the_title( $post_id ),
				'PostID'         => $post_id,
				'OldStartDate'   => $formatted_old_start,
				'NewStartDate'   => $formatted_new_start,
				'OldEndDate'     => $formatted_old_end,
				'NewEndDate'     => $formatted_new_end,
				'EditorLinkPost' => \esc_url( \get_edit_post_link( $post_id ) ),
			);

			Alert_Manager::trigger_event( $event_id, $event_variables );

			// Clean up for this post.
			unset( self::$pending_date_events[ $post_id ] );
			unset( self::$before_ld_start_end_dates[ $post_id ] );
		}

		/**
		 * Detects if a duplication action is being performed and stores the source post ID.
		 *
		 * @return void
		 *
		 * @since 5.6.0
		 */
		public static function maybe_save_duplicate_action_post_id() {

			// Check for common duplication actions in $_REQUEST.
			$action = isset( $_REQUEST['action'] ) ? \sanitize_text_field( \wp_unslash( $_REQUEST['action'] ) ) : '';

			$ld_clone_actions = array(
				'learndash_cloning_action_course',
				'learndash_cloning_action_lesson',
				'learndash_cloning_action_topic',
			);

			if ( empty( $action ) || ! in_array( $action, $ld_clone_actions, true ) ) {
				return;
			}

			// Extract and sanitize object_id and nonce from REQUEST.
			$object_id = isset( $_REQUEST['object_id'] ) ? (int) $_REQUEST['object_id'] : 0;
			$nonce     = isset( $_REQUEST['nonce'] ) ? \sanitize_text_field( \wp_unslash( $_REQUEST['nonce'] ) ) : '';

			// Verify nonce with LearnDash's action format.
			if ( empty( $nonce ) || ! \wp_verify_nonce( $nonce, $action . $object_id ) ) {
				return;
			}

			// Store the clone source post ID for later use.
			self::$duplication_source_id = $object_id;
		}

		/**
		 * Ignore LearnDash user meta events.
		 *
		 * @param bool   $ignore_event - True if ignore meta event, false if not.
		 * @param string $meta_key     - Meta key.
		 * @param mixed  $meta_value   - Meta value.
		 * @param int    $user_id      - User ID.
		 *
		 * @return bool
		 *
		 * @since 5.6.0
		 */
		public static function ignore_learndash_user_meta_events( $ignore_event, $meta_key, $meta_value, $user_id ) {

			if ( $ignore_event ) {
				return $ignore_event;
			}

			/**
			 * Ignore LearnDash course enrollment meta keys.
			 */
			if ( \preg_match( '/^learndash_course_\d+_enrolled_at$/', $meta_key ) || \preg_match( '/^course_\d+_access_from$/', $meta_key ) ) {
				return true;
			}

			/**
			 * Ignore LearnDash group enrollment meta keys.
			 */
			if ( \preg_match( '/^learndash_group_\d+_enrolled_at$/', $meta_key ) || \preg_match( '/^group_\d+_access_from$/', $meta_key ) || \preg_match( '/^learndash_group_users_\d+$/', $meta_key ) ) {
				return true;
			}

			/**
			 * Ignore LearnDash quiz-related meta keys.
			 */
			if ( \preg_match( '/^quiz_time_\d+$/', $meta_key ) ) {
				return true;
			}

			return $ignore_event;
		}

		/**
		 * Trigger the create event for LearnDash posts
		 *
		 * @param string $post_type - The post type, used to trigger the correct event.
		 * @param array  $event_variables - Event variables.
		 *
		 * @since 5.6.0
		 */
		public static function trigger_ld_created_post_event( $post_type, $event_variables ) {
			if ( 'sfwd-courses' === $post_type ) {
				Alert_Manager::trigger_event( 11000, $event_variables );
			} elseif ( 'sfwd-lessons' === $post_type ) {
				Alert_Manager::trigger_event( 11200, $event_variables );
			} elseif ( 'sfwd-topic' === $post_type ) {
				Alert_Manager::trigger_event( 11400, $event_variables );
			} elseif ( 'groups' === $post_type ) {
				Alert_Manager::trigger_event( 11500, $event_variables );
			} elseif ( 'sfwd-certificates' === $post_type ) {
			}
		}

		/**
		 * Trigger the publish event for LearnDash posts
		 *
		 * @param string $post_type - The post type, used to trigger the correct event.
		 * @param array  $event_variables - Event variables.
		 *
		 * @since 5.6.0
		 */
		public static function trigger_ld_published_post_event( $post_type, $event_variables ) {
			if ( 'sfwd-courses' === $post_type ) {
				Alert_Manager::trigger_event( 11001, $event_variables );
			} elseif ( 'sfwd-lessons' === $post_type ) {
				Alert_Manager::trigger_event( 11201, $event_variables );
			} elseif ( 'sfwd-topic' === $post_type ) {
				Alert_Manager::trigger_event( 11401, $event_variables );
			} elseif ( 'groups' === $post_type ) {
				Alert_Manager::trigger_event( 11501, $event_variables );
			} elseif ( 'sfwd-certificates' === $post_type ) {
			}
		}

		/**
		 * Triggers duplication event if a source post ID was detected.
		 *
		 * @param \WP_Post $post                 - The duplicated post object.
		 * @param array    $post_event_variables - Event variables for the post.
		 *
		 * @return bool - True if duplication event was triggered, false otherwise.
		 *
		 * @since 5.6.0
		 */
		public static function maybe_trigger_duplication_event( $post, $post_event_variables ) {

			if ( self::$duplication_source_id && LearnDash_Helper::is_post_duplicate( $post, self::$duplication_source_id ) ) {

				// Override PostTitle with source post.
				$post_event_variables['PostTitle'] = \esc_html( \get_the_title( self::$duplication_source_id ) );

				// Get categories from the SOURCE post.
				$post_event_variables['Categories']     = LearnDash_Helper::get_post_categories( self::$duplication_source_id );
				$post_event_variables['LdPostCategory'] = LearnDash_Helper::get_ld_post_categories( self::$duplication_source_id, $post->post_type );

				if ( 'sfwd-courses' === $post->post_type ) {
					Alert_Manager::trigger_event( 11006, $post_event_variables );

					return true;
				} elseif ( 'sfwd-lessons' === $post->post_type ) {
					Alert_Manager::trigger_event( 11209, $post_event_variables );

					return true;
				} elseif ( 'sfwd-topic' === $post->post_type ) {
					Alert_Manager::trigger_event( 11406, $post_event_variables );

					return true;
				}
			}

			return false;
		}

		/**
		 * Learndash event triggers whenever a user saves a post: create, publish, update.
		 *
		 * @param int           $post_id - Post ID.
		 * @param \WP_Post      $post - Post object that was just updated.
		 * @param bool          $update - Whether this is an existing post being updated.
		 * @param null|\WP_Post $post_before - Null for new posts, the WP_Post object prior to the update for updated posts.
		 *
		 * @return void
		 *
		 * @since 5.6.0
		 */
		public static function ld_saved_post_event_triggers( $post_id, $post, $update, $post_before ) {

			if ( 'auto-draft' === $post->post_status || 'trash' === $post->post_status ) {
				return;
			}

			$ld_post_types = LearnDash_Helper::get_custom_post_types();

			if ( ! \in_array( $post->post_type, $ld_post_types, true ) ) {
				return;
			}

			$post_event_variables                   = LearnDash_Helper::build_ld_post_event_variables( $post );
			$post_event_variables['PostStatus']     = $post->post_status;
			$post_event_variables['EditorLinkPost'] = \esc_url( \get_edit_post_link( $post->ID ) );

			// Check for course UI post creation.
			if ( $post_before && Learndash_Helper::ld_post_created_via_ld_course_builder( $post_before, $post, $update ) ) {
				self::trigger_ld_created_post_event( $post->post_type, $post_event_variables );

				// Return to avoid further processing.
				return;
			}

			// Check for duplication.
			if ( self::maybe_trigger_duplication_event( $post, $post_event_variables ) ) {
				// Return to avoid further processing.
				return;
			}

			/**
			 * A post is considered truly created if it's displayed in its post list.
			 */
			if ( ( 'draft' === $post->post_status || 'publish' === $post->post_status ) && $update && 'auto-draft' === $post_before->post_status ) {
				self::trigger_ld_created_post_event( $post->post_type, $post_event_variables );
			}

			if ( 'publish' === $post->post_status && $update && ( 'draft' === $post_before->post_status || 'auto-draft' === $post_before->post_status ) ) {

				// Unset PostStatus from variables, it's not needed in published event.
				unset( $post_event_variables['PostStatus'] );

				self::trigger_ld_published_post_event( $post->post_type, $post_event_variables );


				// Return to avoid further processing.
				return;

			} elseif ( $post_before && 'auto-draft' !== $post_before->post_status ) {

			}
		}

		/**
		 * Detect when a Learndash post is moved to trash.
		 *
		 * @param int $post_id - Post ID.
		 *
		 * @return void
		 *
		 * @since 5.6.0
		 */
		public static function ld_post_trashed( $post_id ) {
			$post = \get_post( $post_id );

			$ld_post_types = LearnDash_Helper::get_custom_post_types();

			if ( ! in_array( $post->post_type, $ld_post_types, true ) ) {
				return;
			}

			$event_variables               = LearnDash_Helper::build_ld_post_event_variables( $post );
			$event_variables['PostStatus'] = $post->post_status;

			if ( 'sfwd-courses' === $post->post_type ) {
				Alert_Manager::trigger_event( 11003, $event_variables );
			} elseif ( 'sfwd-lessons' === $post->post_type ) {
				Alert_Manager::trigger_event( 11206, $event_variables );
			} elseif ( 'sfwd-topic' === $post->post_type ) {
				Alert_Manager::trigger_event( 11404, $event_variables );
			} elseif ( 'groups' === $post->post_type ) {
				Alert_Manager::trigger_event( 11503, $event_variables );
			} elseif ( 'sfwd-certificates' === $post->post_type ) {
			}
		}

		/**
		 * Detect when a Learndash post is permanently deleted.
		 *
		 * @param int      $post_id - Post ID.
		 * @param \WP_Post $post - Post object.
		 *
		 * @return void
		 *
		 * @since 5.6.0
		 */
		public static function ld_post_perma_deleted( $post_id, $post ) {

			$ld_post_types = LearnDash_Helper::get_custom_post_types();

			if ( ! in_array( $post->post_type, $ld_post_types, true ) ) {
				return;
			}

			$event_variables = LearnDash_Helper::build_ld_post_event_variables( $post );

			if ( 'sfwd-courses' === $post->post_type ) {
				Alert_Manager::trigger_event( 11004, $event_variables );
			} elseif ( 'sfwd-lessons' === $post->post_type ) {
				Alert_Manager::trigger_event( 11207, $event_variables );
			} elseif ( 'sfwd-topic' === $post->post_type ) {
				Alert_Manager::trigger_event( 11405, $event_variables );
			} elseif ( 'groups' === $post->post_type ) {
				Alert_Manager::trigger_event( 11504, $event_variables );
			} elseif ( 'sfwd-certificates' === $post->post_type ) {
			}
		}

		/**
		 * Detect when a Learndash post is restored from trash.
		 *
		 * @param int $post_id - Post ID.
		 *
		 * @return void
		 *
		 * @since 5.6.0
		 */
		public static function ld_post_restored( $post_id ) {
			$post = \get_post( $post_id );

			$ld_post_types = LearnDash_Helper::get_custom_post_types();

			if ( ! in_array( $post->post_type, $ld_post_types, true ) ) {
				return;
			}

			$event_variables                   = LearnDash_Helper::build_ld_post_event_variables( $post );
			$event_variables['EditorLinkPost'] = \esc_url( \get_edit_post_link( $post->ID ) );

			if ( 'sfwd-courses' === $post->post_type ) {
				Alert_Manager::trigger_event( 11005, $event_variables );
			} elseif ( 'sfwd-lessons' === $post->post_type ) {
				Alert_Manager::trigger_event( 11208, $event_variables );
			} elseif ( 'groups' === $post->post_type ) {
				Alert_Manager::trigger_event( 11505, $event_variables );
			} elseif ( 'sfwd-certificates' === $post->post_type ) {
			}
		}


		/**
		 * New course category created trigger.
		 *
		 * @param int    $term_id - Term ID.
		 * @param int    $tt_id - Term taxonomy ID.
		 * @param string $taxonomy - Taxonomy slug.
		 * @param array  $args - Arguments passed to wp_insert_term().
		 *
		 * @return void
		 *
		 * @since 5.6.0
		 */
		public static function ld_taxonomy_creation_triggers( $term_id, $tt_id, $taxonomy, $args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
			$ld_taxonomies = LearnDash_Helper::get_custom_taxonomies();

			if ( ! \in_array( $taxonomy, $ld_taxonomies, true ) ) {
				return;
			}

			$term = \get_term( $term_id, $taxonomy );

			$event_variables = array(
				'TaxonomyTitle' => $term->name,
				'Slug'          => $term->slug,
			);

			if ( 'ld_course_category' === $taxonomy ) {
				$event_variables['CategoryLink'] = LearnDash_Helper::get_ld_taxonomy_edit_link( $term_id, 'ld_course_category', 'sfwd-courses' );

				Alert_Manager::trigger_event( 11080, $event_variables );
			} elseif ( 'ld_lesson_category' === $taxonomy ) {
				$event_variables['CategoryLink'] = LearnDash_Helper::get_ld_taxonomy_edit_link( $term_id, 'ld_lesson_category', 'sfwd-lessons' );

				Alert_Manager::trigger_event( 11300, $event_variables );
			} elseif ( 'ld_course_tag' === $taxonomy ) {
				$event_variables['TagLink'] = LearnDash_Helper::get_ld_taxonomy_edit_link( $term_id, 'ld_course_tag', 'sfwd-courses' );

				Alert_Manager::trigger_event( 11090, $event_variables );
			} elseif ( 'ld_lesson_tag' === $taxonomy ) {
				$event_variables['TagLink'] = LearnDash_Helper::get_ld_taxonomy_edit_link( $term_id, 'ld_lesson_tag', 'sfwd-lessons' );

				Alert_Manager::trigger_event( 11350, $event_variables );
			} elseif ( 'ld_topic_tag' === $taxonomy ) {
				$event_variables['TagLink'] = LearnDash_Helper::get_ld_taxonomy_edit_link( $term_id, 'ld_topic_tag', 'sfwd-topic' );

				Alert_Manager::trigger_event( 11450, $event_variables );
			}
		}

		/**
		 * Course category deleted trigger.
		 *
		 * @param int      $term_id - Term ID.
		 * @param int      $tt_id - Term taxonomy ID.
		 * @param string   $taxonomy - Taxonomy slug.
		 * @param \WP_Term $deleted_term - Copy of the already-deleted term.
		 * @param array    $object_ids - List of term object IDs.
		 *
		 * @return void
		 *
		 * @since 5.6.0
		 */
		public static function ld_taxonomy_deletion_triggers( $term_id, $tt_id, $taxonomy, $deleted_term, $object_ids ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
			$ld_taxonomies = LearnDash_Helper::get_custom_taxonomies();

			if ( ! \in_array( $taxonomy, $ld_taxonomies, true ) ) {
				return;
			}

			$event_variables = array(
				'TaxonomyTitle' => $deleted_term->name,
				'Slug'          => $deleted_term->slug,
			);

			if ( 'ld_course_category' === $taxonomy ) {
				Alert_Manager::trigger_event( 11081, $event_variables );
			} elseif ( 'ld_lesson_category' === $taxonomy ) {
				Alert_Manager::trigger_event( 11301, $event_variables );
			} elseif ( 'ld_course_tag' === $taxonomy ) {
				Alert_Manager::trigger_event( 11091, $event_variables );
			} elseif ( 'ld_lesson_tag' === $taxonomy ) {
				Alert_Manager::trigger_event( 11351, $event_variables );
			} elseif ( 'ld_topic_tag' === $taxonomy ) {
				Alert_Manager::trigger_event( 11451, $event_variables );
			}
		}


		/**
		 * Applies default disabled alerts when LearnDash is first detected by an existing WSAL install.
		 *
		 * This ensures high-volume events are disabled even when WSAL was installed before LearnDash.
		 *
		 * @return void
		 *
		 * @since 5.6.0
		 */
		private static function maybe_apply_first_detection_disabled_alerts() {
			$processed_plugins = \get_option( 'wsal_detected_plugins_processed', array() );

			if ( in_array( 'sfwd-lms', $processed_plugins, true ) ) {
				return;
			}

			$disabled_alerts = Settings_Helper::get_disabled_alerts();

			$learndash_defaults = LearnDash_Custom_Alerts::get_default_disabled_alerts();
			$disabled_alerts    = array_unique( array_merge( $disabled_alerts, $learndash_defaults ) );

			Settings_Helper::set_disabled_alerts( $disabled_alerts );

			$processed_plugins[] = 'sfwd-lms';

			\update_option( 'wsal_detected_plugins_processed', $processed_plugins );
		}
	}
}
