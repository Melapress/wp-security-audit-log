<?php
/**
 * LearnDash Sensor helper.
 *
 * @package wsal
 * @subpackage sensors
 *
 * @since 5.6.0
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors\Helpers;

use WSAL\Helpers\WP_Helper;

// Exit if accessed directly.
if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! \class_exists( '\WSAL\WP_Sensors\Helpers\LearnDash_Helper' ) ) {
	/**
	 * Helper Sensor class for LearnDash.
	 *
	 * @package wsal
	 * @subpackage sensors-helpers
	 *
	 * @since 5.6.0
	 */
	class LearnDash_Helper {

		/**
		 * Stores if the plugin is active.
		 *
		 * @var bool
		 *
		 * @since 5.6.0
		 */
		private static $plugin_active = null;

		/**
		 * Stores if the plugin is active for sensors.
		 *
		 * @var bool
		 *
		 * @since 5.6.0
		 */
		private static $plugin_active_for_sensors = null;

		/**
		 * Check if LearnDash is active.
		 *
		 * @return bool
		 *
		 * @since 5.6.0
		 */
		public static function is_learndash_active() {
			if ( null === self::$plugin_active ) {
				if ( \function_exists( 'learndash_get_post_type_slug' ) ) {
					self::$plugin_active = true;
				} else {
					self::$plugin_active = false;
				}
			}

			return self::$plugin_active;
		}

		/**
		 * Check if plugin alerts should be loaded.
		 *
		 * @return boolean
		 *
		 * @since 5.6.0
		 */
		public static function load_alerts_for_sensor(): bool {
			if ( null === self::$plugin_active_for_sensors ) {
				self::$plugin_active_for_sensors = ( WP_Helper::is_plugin_active( 'sfwd-lms/sfwd_lms.php' ) );
			}

			return self::$plugin_active_for_sensors;
		}

		/**
		 * Register custom event objects for WSAL.
		 *
		 * @param array $objects - Array of objects current registered within WSAL.
		 *
		 * @return array
		 *
		 * @since 5.6.0
		 */
		public static function wsal_learndash_add_custom_event_objects( $objects ) {
			$new_objects = array(
				'learndash_courses'      => \esc_html__( 'LearnDash Courses', 'wp-security-audit-log' ),
				'learndash_lessons'      => \esc_html__( 'LearnDash Lessons', 'wp-security-audit-log' ),
				'learndash_topics'       => \esc_html__( 'LearnDash Topics', 'wp-security-audit-log' ),
				'learndash_quizzes'      => \esc_html__( 'LearnDash Quizzes', 'wp-security-audit-log' ),
				'learndash_groups'       => \esc_html__( 'LearnDash Groups', 'wp-security-audit-log' ),
				'learndash_settings'     => \esc_html__( 'LearnDash Settings', 'wp-security-audit-log' ),
				'learndash_certificates' => \esc_html__( 'LearnDash Certificates', 'wp-security-audit-log' ),
				'learndash_students'     => \esc_html__( 'LearnDash Students', 'wp-security-audit-log' ),
			);

			// combine the two arrays.
			$objects = \array_merge( $objects, $new_objects );

			return $objects;
		}

		/**
		 * Returns a list of custom post types associated with LearnDash.
		 *
		 * @return array - List of custom post types.
		 *
		 * @since 5.6.0
		 */
		public static function get_custom_post_types(): array {
			return array( 'sfwd-courses', 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz', 'groups', 'sfwd-question', 'sfwd-certificates' );
		}

		/**
		 * Returns a list of custom taxonomies associated with LearnDash.
		 *
		 * @return array - List of custom taxonomies.
		 *
		 * @since 5.6.0
		 */
		public static function get_custom_taxonomies(): array {
			return array( 'ld_course_category', 'ld_course_tag', 'ld_lesson_category', 'ld_lesson_tag', 'ld_topic_category', 'ld_topic_tag', 'ld_quiz_category', 'ld_group_category', 'ld_group_tag' );
		}

		/**
		 * Ignore Learndash CPTs for the WP Content sensors.
		 *
		 * @param array $post_types - An array of default post_types.
		 *
		 * @return array
		 *
		 * @since 5.6.0
		 */
		public static function wsal_learndash_add_custom_ignored_cpt( $post_types ) {
			$new_post_types = self::get_custom_post_types();

			// combine the two arrays.
			$post_types = \array_merge( $post_types, $new_post_types );

			return $post_types;
		}

		/**
		 * Gets the filename of the plugin this extension is targeting.
		 *
		 * @return string
		 *
		 * @since 5.6.0
		 */
		public static function get_plugin_filename(): string {
			return 'sfwd-lms/sfwd_lms.php';
		}

		/**
		 * Get the author name from a post.
		 *
		 * @param \WP_Post $post - Post object.
		 *
		 * @return string
		 *
		 * @since 5.6.0
		 */
		public static function get_author_name( $post ): string {
			$post_author = \get_userdata( (int) $post->post_author );
			return $post_author ? $post_author->user_login : self::get_ld_unknown_string();
		}

		/**
		 * Get the author display name from a post.
		 *
		 * @param \WP_Post $post - Post object.
		 *
		 * @return string
		 *
		 * @since 5.6.0
		 */
		public static function get_author_display_name( $post ): string {
			$author_id           = (int) $post->post_author;
			$author_data         = \get_userdata( $author_id );
			$author_display_name = $author_data ? $author_data->display_name : self::get_ld_unknown_string();

			return $author_display_name;
		}


		/**
		 * Get WordPress categories as comma-separated string.
		 *
		 * @param int $post_id - Post ID.
		 *
		 * @return string
		 *
		 * @since 5.6.0
		 */
		public static function get_post_categories( $post_id ): string {
			$categories = \wp_get_post_terms( $post_id, 'category', array( 'fields' => 'names' ) );
			return ! empty( $categories ) && ! \is_wp_error( $categories ) ? \implode( ', ', $categories ) : self::get_ld_none_string();
		}

		/**
		 * Get the price type from a Learndash post.
		 *
		 * Retrieves the LearnDash price type meta value (e.g., 'open', 'free', 'paynow', 'subscribe', 'closed').
		 *
		 * @param int $post_id - Post ID.
		 *
		 * @return string - The price type as string, e.g. 'free' or 'Not set' if not available.
		 *
		 * @since 5.6.0
		 */
		public static function get_ld_post_price_type( $post_id ) {
			return \get_post_meta( $post_id, '_ld_price_type', true ) ?? \esc_html__( 'Not set', 'wp-security-audit-log' );
		}

		/**
		 * Get LearnDash post categories as comma-separated string.
		 *
		 * @param int    $post_id - Post ID.
		 * @param string $post_type - Post type.
		 *
		 * @return string
		 *
		 * @since 5.6.0
		 */
		public static function get_ld_post_categories( $post_id, $post_type ): string {

			$taxonomy_key = 'ld_course_category';

			if ( 'sfwd-lessons' === $post_type ) {
				$taxonomy_key = 'ld_lesson_category';
			} elseif ( 'sfwd-topic' === $post_type ) {
				$taxonomy_key = 'ld_topic_category';
			} elseif ( 'sfwd-quiz' === $post_type ) {
				$taxonomy_key = 'ld_quiz_category';
			} elseif ( 'groups' === $post_type ) {
				$taxonomy_key = 'ld_group_category';
			}

			$categories = \wp_get_post_terms( $post_id, $taxonomy_key, array( 'fields' => 'names' ) );

			if ( ! empty( $categories ) && ! \is_wp_error( $categories ) ) {
				$categories = \implode( ', ', $categories );
			} else {
				$categories = self::get_ld_none_string();
			}

			return $categories;
		}

		/**
		 * Build post event variables.
		 *
		 * @param \WP_Post $post       - Post object.
		 *
		 * @return array
		 *
		 * @since 5.6.0
		 */
		public static function build_ld_post_event_variables( \WP_Post $post ): array {
			$variables = array(
				'PostTitle'      => \esc_html( $post->post_title ),
				'PostID'         => $post->ID,
				'PostAuthor'     => self::get_author_name( $post ),
				'Categories'     => self::get_post_categories( $post->ID ),
				'LdPostCategory' => self::get_ld_post_categories( $post->ID, $post->post_type ),
			);

			if ( in_array( $post->post_type, array( 'sfwd-courses', 'groups' ), true ) ) {
				$variables['PriceType'] = self::get_ld_post_price_type( $post->ID );
			}

			return $variables;
		}



		/**
		 * Get term names from term IDs.
		 *
		 * @param array  $term_ids - Array of term IDs.
		 * @param string $taxonomy - Taxonomy slug.
		 *
		 * @return string
		 *
		 * @since 5.6.0
		 */
		public static function get_term_names_from_ids( $term_ids, $taxonomy ): string {
			$term_names = array();

			foreach ( $term_ids as $term_id ) {
				$term = \get_term_by( 'term_taxonomy_id', $term_id, $taxonomy );
				if ( $term && ! \is_wp_error( $term ) ) {
					$term_names[] = $term->name;
				}
			}

			return ! empty( $term_names ) ? \implode( ', ', $term_names ) : '';
		}

		/**
		 * Format lessons, topics, and quizzes under ld_course_steps metadata for later comparison.
		 *
		 * @param int    $post_id - Post ID.
		 * @param string $meta_key - Meta key being updated.
		 *
		 * @return array The course steps metadata.
		 *
		 * @since 5.6.0
		 */
		public static function format_course_steps_metadata( $post_id, $meta_key ): array {
			$old_meta_record   = \get_post_meta( $post_id, $meta_key, true );
			$unserialized_meta = \maybe_unserialize( $old_meta_record );

			$lessons = array();
			$topics  = array();
			$quizzes = array();

			if ( \is_array( $unserialized_meta ) || \is_object( $unserialized_meta ) ) {
				$lessons = self::extract_learndash_items( $unserialized_meta, 'sfwd-lessons' );
				$topics  = self::extract_learndash_items( $unserialized_meta, 'sfwd-topic' );
				$quizzes = self::extract_learndash_items( $unserialized_meta, 'sfwd-quiz' );
			}

			$course_steps_meta = array(
				'lessons'      => array_keys( $lessons ),
				'topics'       => $topics,
				'quizzes'      => array_keys( $quizzes ),
				'raw_metadata' => $unserialized_meta,
			);

			return $course_steps_meta;
		}

		/**
		 * Extracts lesson or topic IDs from LearnDash course steps metadata.
		 *
		 * @param array  $data      - Unserialized course steps metadata (array/object).
		 * @param string $post_type - The post type to extract ('sfwd-lessons' or 'sfwd-topic').
		 *
		 * @return array - Array of IDs. For lessons: associative array (id => data), for topics: numeric array of IDs.
		 *
		 * @since 5.6.0
		 */
		public static function extract_learndash_items( $data, $post_type ): array {
			return self::search_nested_ld_structure( $data, $post_type, array() );
		}

		/**
		 * Recursively searches nested structure for LearnDash items.
		 *
		 * @param mixed  $node      - Current node being examined.
		 * @param string $post_type - The post type to extract.
		 * @param array  $items     - Accumulated items found so far.
		 *
		 * @return array - Updated items array.
		 *
		 * @since 5.6.0
		 */
		public static function search_nested_ld_structure( $node, $post_type, $items = array() ): array {

			// Return early if this isn't an array.
			if ( ! \is_array( $node ) ) {
				return $items;
			}

			foreach ( $node as $key => $value ) {
				if ( $post_type === $key && ( \is_array( $value ) || \is_object( $value ) ) ) {
					foreach ( (array) $value as $item_id => $item_data ) {
						if ( 'sfwd-lessons' === $post_type || 'sfwd-quiz' === $post_type ) {
							$items[ (string) $item_id ] = $item_data;
						} elseif ( 'sfwd-topic' === $post_type ) {
							$items[] = (int) $item_id;
						}
					}

					$items = self::search_nested_ld_structure( $value, $post_type, $items );
					continue;
				}

				if ( \is_array( $value ) || \is_object( $value ) ) {
					$items = self::search_nested_ld_structure( $value, $post_type, $items );
				}
			}

			return $items;
		}

		/**
		 * Get enrollment status label from course prerequisites or points settings.
		 *
		 * @param array $meta - Course meta data.
		 *
		 * @return string - Formatted enrollment requirement label.
		 *
		 * @since 5.6.0
		 */
		public static function get_enrollment_status_label( $meta ): string {

			$course_req   = $meta['sfwd-courses_requirements_for_enrollment'] ?? self::get_ld_none_string();
			$prereq_value = $meta['sfwd-courses_course_prerequisite'] ?? array();

			// If requirements are based on points.
			if ( 'course_points_enabled' === $course_req && isset( $meta['sfwd-courses_course_points_access'] ) ) {
				return \esc_html__( 'Points: ', 'wp-security-audit-log' ) . (string) $meta['sfwd-courses_course_points_access'];
			}

			// If requirements are based on prerequisite courses.
			if ( 'course_prerequisite_enabled' === $course_req ) {
				$course_titles = array();

				if ( is_array( $prereq_value ) ) {
					// Extract course titles from course post IDs.
					foreach ( $prereq_value as $prereq_id ) {

						$title = \get_the_title( (int) $prereq_id );

						if ( ! empty( $title ) ) {
							$course_titles[] = $title;
						}
					}

					if ( ! empty( $course_titles ) ) {
						return implode( ', ', $course_titles );
					}
				}
			}

			// Fallback to None if we did not find any requirements.
			return self::get_ld_none_string();
		}

		/**
		 * Formats duration in seconds to HH MM format.
		 *
		 * @param mixed $seconds - Duration in seconds.
		 *
		 * @return string - Formatted duration string.
		 *
		 * @since 5.6.0
		 */
		public static function format_duration_for_display( $seconds ): string {
			$seconds_string = (string) $seconds;

			if ( empty( $seconds_string ) ) {
				return self::get_ld_none_string();
			}

			$seconds_int = (int) $seconds;
			$hours       = (int) \floor( $seconds_int / 3600 );
			$minutes     = (int) \floor( ( $seconds_int % 3600 ) / 60 );

			$parts = array();

			if ( $hours > 0 ) {
				$parts[] = \sprintf(
				/* translators: %s: number of hours */
					\_n( '%s hour', '%s hours', $hours, 'wp-security-audit-log' ),
					\number_format_i18n( $hours )
				);
			}

			if ( $minutes > 0 ) {
				$parts[] = \sprintf(
				/* translators: %d: number of minutes */
					\_n( '%d minute', '%d minutes', $minutes, 'wp-security-audit-log' ),
					\number_format_i18n( $minutes )
				);
			}

			return implode( ', ', $parts );
		}

		/**
		 * Get taxonomy edit link.
		 *
		 * @param int    $term_id - Term ID.
		 * @param string $taxonomy - Taxonomy slug.
		 * @param string $post_type - Post type (optional). If provided, uses term.php format with post_type.
		 *
		 * @return string|null
		 *
		 * @since 5.6.0
		 */
		public static function get_ld_taxonomy_edit_link( $term_id, $taxonomy = 'post_tag', $post_type = '' ) {
			if ( empty( $term_id ) ) {
				return null;
			}

			if ( ! empty( $post_type ) ) {
				$term_args = array(
					'taxonomy'  => $taxonomy,
					'tag_ID'    => $term_id,
					'post_type' => $post_type,
				);

				return \add_query_arg( $term_args, \network_admin_url( 'term.php' ) );
			}

			// Use term.php format, standard WordPress format for taxonomy term list as fall back if we don't have a post type.
			$term_args = array(
				'taxonomy' => $taxonomy,
				'tag_ID'   => $term_id,
			);

			return \add_query_arg( $term_args, \network_admin_url( 'term.php' ) );
		}

		/**
		 * Build a map of quiz locations with their parent IDs across course/lesson/topic hierarchy.
		 *
		 * @param array $steps_data - The steps['h'] data structure as provided by Learndash 'ld_course_steps' metadata.
		 * @param int   $post_id - Course ID.
		 *
		 * @return array - Array with 'course_level', 'lesson_level', 'topic_level' keys, each containing arrays of quiz_id/parent_id pairs.
		 *
		 * @since 5.6.0
		 */
		public static function map_quizzes_by_level( $steps_data, $post_id ) {
			$quiz_map = array(
				'course_level' => array(),
				'lesson_level' => array(),
				'topic_level'  => array(),
			);

			// Course-level quizzes.
			if ( isset( $steps_data['sfwd-quiz'] ) && ! empty( $steps_data['sfwd-quiz'] ) ) {
				foreach ( array_keys( $steps_data['sfwd-quiz'] ) as $quiz_id ) {
					$quiz_map['course_level'][] = array(
						'quiz_id'   => $quiz_id,
						'parent_id' => $post_id,
					);
				}
			}

			// Lesson-level and topic-level quizzes.
			if ( isset( $steps_data['sfwd-lessons'] ) && ! empty( $steps_data['sfwd-lessons'] ) ) {
				foreach ( $steps_data['sfwd-lessons'] as $lesson_id => $lesson_data ) {
					// Lesson-level quizzes.
					if ( isset( $lesson_data['sfwd-quiz'] ) && ! empty( $lesson_data['sfwd-quiz'] ) ) {
						foreach ( array_keys( $lesson_data['sfwd-quiz'] ) as $quiz_id ) {
							$quiz_map['lesson_level'][] = array(
								'quiz_id'   => $quiz_id,
								'parent_id' => $lesson_id,
							);
						}
					}

					// Topic-level quizzes.
					if ( isset( $lesson_data['sfwd-topic'] ) && ! empty( $lesson_data['sfwd-topic'] ) ) {
						foreach ( $lesson_data['sfwd-topic'] as $topic_id => $topic_data ) {
							if ( isset( $topic_data['sfwd-quiz'] ) && ! empty( $topic_data['sfwd-quiz'] ) ) {
								foreach ( array_keys( $topic_data['sfwd-quiz'] ) as $quiz_id ) {
									$quiz_map['topic_level'][] = array(
										'quiz_id'   => $quiz_id,
										'parent_id' => $topic_id,
									);
								}
							}
						}
					}
				}
			}

			return $quiz_map;
		}

		/**
		 * Get post titles from an array of post IDs.
		 *
		 * @param array $post_ids - Array of post IDs.
		 *
		 * @return array - Array of post titles.
		 *
		 * @since 5.6.0
		 */
		public static function get_post_titles_array( array $post_ids ): array {
			$titles = array();

			$post_ids = \array_values( \array_filter( $post_ids ) );

			foreach ( $post_ids as $post_id ) {
				$titles[] = \get_the_title( $post_id );
			}

			return $titles;
		}

		/**
		 * Get all enrolled course IDs for a user from LearnDash enrollment meta.
		 *
		 * @param int $user_id - User ID.
		 *
		 * @return array - Array of course IDs.
		 *
		 * @since 5.6.0
		 */
		public static function get_user_enrolled_courses( $user_id ) {
			$all_meta   = \get_user_meta( $user_id );
			$course_ids = array();

			foreach ( $all_meta as $meta_key => $meta_value ) {
				if ( \preg_match( '/^course_(\d+)_access_from$/', $meta_key, $matches ) ) {
					$course_ids[] = (int) $matches[1];
				}
			}

			return $course_ids;
		}

		/**
		 * Get all enrolled group IDs for a user.
		 *
		 * @param int $user_id - User ID.
		 *
		 * @return array - Array of group IDs.
		 *
		 * @since 5.6.0
		 */
		public static function get_user_enrolled_groups( $user_id ) {
			if ( \function_exists( 'learndash_get_users_group_ids' ) ) {
				return \learndash_get_users_group_ids( $user_id );
			}

			return array();
		}

		/**
		 * Checks if a new post is a duplicate of a source post.
		 *
		 * @param \WP_Post $new_post       The new post object.
		 * @param int      $source_post_id The ID of the source post.
		 *
		 * @return bool True if it is a duplicate, false otherwise.
		 *
		 * @since 5.6.0
		 */
		public static function is_post_duplicate( $new_post, $source_post_id ) {
			$source_post = \get_post( $source_post_id );

			if ( ! $source_post || $source_post->post_type !== $new_post->post_type ) {
				return false;
			}

			// Compare content.
			if ( $new_post->post_content !== $source_post->post_content ) {
				return false;
			}

			return true;
		}

		/**
		 * Check if a post was just created (within the last few seconds).
		 *
		 * @param \WP_Post $post - Post object.
		 *
		 * @return bool - True if post was just created, false otherwise.
		 *
		 * @since 5.6.0
		 */
		public static function is_post_just_created( $post ) {
			if ( ! $post ) {
				return false;
			}

			// Use get_post_datetime to handle timezones and GMT/local fallback.
			$post_datetime = \get_post_datetime( $post, 'date', 'gmt' );

			if ( ! $post_datetime ) {
				return false;
			}

			$created_timestamp = $post_datetime->getTimestamp();
			$current_timestamp = \time();

			// For scheduled posts, use modified date as they are "created" in the future.
			if ( 'future' === $post->post_status ) {
				$mod_datetime = \get_post_datetime( $post, 'modified', 'gmt' );
				if ( $mod_datetime ) {
					$created_timestamp = $mod_datetime->getTimestamp();
				}
			}

			$age = $current_timestamp - $created_timestamp;

			// Check if created within last 30 seconds, let's also set -5 to allow for minor time differences.
			return $age >= -5 && $age < 30;
		}

		/**
		 * Format lesson order for display: titles if 10 or fewer lessons, IDs if more than 10.
		 *
		 * @param array $lesson_ids - Ordered array of lesson IDs.
		 *
		 * @return string - Formatted comma-separated string of lesson titles or IDs.
		 *
		 * @since 5.6.0
		 */
		public static function format_lesson_order_for_display( $lesson_ids ) {
			if ( empty( $lesson_ids ) ) {
				return '';
			}

			// If 10 or fewer lessons, show titles.
			if ( \count( $lesson_ids ) <= 10 ) {

				$titles = array();

				foreach ( $lesson_ids as $lesson_id ) {
					$titles[] = \get_the_title( $lesson_id );
				}
				return \implode( ', ', $titles );
			}

			// If more than 10 lessons, show IDs.
			return \implode( ', ', $lesson_ids );
		}

		/**
		 * Get translated 'None' string for LearnDash fields.
		 *
		 * @return string
		 *
		 * @since 5.6.0
		 */
		public static function get_ld_none_string() {
			return \esc_html__( 'None', 'wp-security-audit-log' );
		}

		/**
		 * Get translated 'Unknown' string for LearnDash fields.
		 *
		 * @return string
		 *
		 * @since 5.6.0
		 */
		public static function get_ld_unknown_string() {
			return \esc_html__( 'Unknown', 'wp-security-audit-log' );
		}

		/**
		 * Get the student count label.
		 *
		 * @param int $count - Student count.
		 *
		 * @return string
		 *
		 * @since 5.6.0
		 */
		public static function get_student_count_label( $count ) {
			/* translators: %d: Number of students */
			return $count > 0 ? sprintf( \_n( '%d student', '%d students', $count, 'wp-security-audit-log' ), $count ) : self::get_ld_none_string();
		}

		/**
		 * Format date to a valid float value.
		 *
		 * @param mixed $date_value - Date value.
		 *
		 * @return float
		 *
		 * @since 5.6.0
		 */
		public static function cast_date_to_float( $date_value ): float {
			if ( empty( $date_value ) || '0' === (string) $date_value ) {
				return 0;
			}

			return (float) $date_value;
		}

		/**
		 * Determine if a LearnDash post was created via the LearnDash Course Builder.
		 *
		 * LearnDash saves posts created via the course builder in 2 steps:
		 * 1. First, an empty post with a default name and slug (e.g., "lesson", "topic", "quiz").
		 * 2. Second, it updates the post with the name and slug specified by the user.
		 *
		 * This method detects the second step by checking if:
		 * - The post slug changed from the default builder slug to a user-specified slug.
		 * - The post author remained the same (it's impossible to change author from the course builder).
		 *
		 * @param \WP_Post $post_before - Post object before the update.
		 * @param \WP_Post $post_after  - Post object after the update.
		 *
		 * @return bool - True if post was created via the Course Builder, false otherwise.
		 *
		 * @since 5.6.0
		 */
		public static function ld_post_created_via_ld_course_builder( $post_before, $post_after ) {
			// List post types that can be created via the Learndash course builder.
			$ld_builder_post_types = array( 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz' );

			$default_post_title_from_builder = array(
				'sfwd-lessons' => 'Lesson',
				'sfwd-topic'   => 'Topic',
				'sfwd-quiz'    => 'Quiz',
			);

			// If not a valid post type, return false.
			if ( ! \in_array( $post_after->post_type, $ld_builder_post_types, true ) ) {
				return false;
			}

			// Return early if author is not the same.
			if ( (int) $post_before->post_author !== (int) $post_after->post_author ) {
				return false;
			}

			$post_type = $post_after->post_type;

			// Check if the post title changed from the default one set by the builder to the one specified by the user.
			if ( $default_post_title_from_builder[ $post_type ] === $post_before->post_title && $default_post_title_from_builder[ $post_type ] !== $post_after->post_title ) {
				return true;
			}

			return false;
		}


		/**
		 * Get Learndash start and end dates from post meta, for courses and groups.
		 *
		 * @param int    $post_id - Post ID.
		 * @param string $post_type - Post type ('sfwd-courses' or 'groups').
		 *
		 * @return array - Array with 'start_date' and 'end_date' keys.
		 *
		 * @since 5.6.0
		 */
		public static function get_ld_start_end_dates_meta( $post_id, $post_type ): array {

			$meta_key       = '';
			$start_date_key = '';
			$end_date_key   = '';

			$dates = array(
				'start_date' => null,
				'end_date'   => null,
			);

			// Return early if post type is not supported.
			if ( ! in_array( $post_type, array( 'sfwd-courses', 'groups' ), true ) ) {
				return $dates;
			}

			// Determine meta key and date field keys based on post type.
			if ( 'sfwd-courses' === $post_type ) {
				$meta_key       = '_sfwd-courses';
				$start_date_key = 'sfwd-courses_course_start_date';
				$end_date_key   = 'sfwd-courses_course_end_date';
			} elseif ( 'groups' === $post_type ) {
				$meta_key       = '_groups';
				$start_date_key = 'groups_group_start_date';
				$end_date_key   = 'groups_group_end_date';
			}

			// Get current dates from database (before any updates).
			$current_meta      = \get_post_meta( $post_id, $meta_key, true );
			$unserialized_meta = \maybe_unserialize( $current_meta );

			if ( \is_array( $unserialized_meta ) ) {
				$start_date = $unserialized_meta[ $start_date_key ] ?? null;
				$end_date   = $unserialized_meta[ $end_date_key ] ?? null;

				$dates = array(
					'start_date' => $start_date,
					'end_date'   => $end_date,
				);
			}

			return $dates;
		}

		/**
		 * Get a comma-separated string of course titles enrolled in a group.
		 *
		 * @param int $group_id - Group ID.
		 *
		 * @return string - Comma-separated course titles or 'None' if no courses.
		 *
		 * @since 5.6.0
		 */
		public static function get_ld_courses_string_by_group_id( $group_id ) {
			$available_courses = self::get_ld_none_string();

			if ( \function_exists( 'learndash_group_enrolled_courses' ) ) {
				$course_ids = \learndash_group_enrolled_courses( $group_id );

				if ( ! empty( $course_ids ) && \is_array( $course_ids ) ) {
					$course_titles     = self::get_post_titles_array( $course_ids );
					$available_courses = \implode( ', ', $course_titles );
				}
			}

			return $available_courses;
		}

		/**
		 * Get the parent lesson ID for a topic.
		 *
		 * @param int $topic_id - Topic post ID.
		 *
		 * @return int|null - Lesson ID or null if not found.
		 *
		 * @since 5.6.0
		 */
		public static function get_topic_lesson_id( $topic_id ) {
			if ( empty( $topic_id ) ) {
				return null;
			}

			// LearnDash stores the parent lesson ID in post meta.
			$lesson_id = \get_post_meta( $topic_id, 'lesson_id', true );

			if ( ! empty( $lesson_id ) ) {
				return (int) $lesson_id;
			}

			// Fallback: try using LearnDash function if available.
			if ( \function_exists( 'learndash_get_lesson_id' ) ) {
				$ld_lesson_id = \learndash_get_lesson_id( $topic_id );
				if ( ! empty( $ld_lesson_id ) ) {
					return (int) $ld_lesson_id;
				}
			}

			return null;
		}

		/**
		 * Get a comma-separated string of group titles for a course.
		 *
		 * @param int $course_id - Course ID.
		 *
		 * @return string - Comma-separated group titles or 'None' if no groups.
		 *
		 * @since 5.6.0
		 */
		public static function get_ld_post_groups_string( $course_id ) {
			if ( empty( $course_id ) ) {
				return self::get_ld_none_string();
			}

			$group_ids = array();

			// Get all groups that have this course enrolled.
			if ( \function_exists( 'learndash_get_course_groups' ) ) {
				$group_ids = \learndash_get_course_groups( $course_id, true );
			}

			if ( empty( $group_ids ) || ! \is_array( $group_ids ) ) {
				return self::get_ld_none_string();
			}

			$group_titles = self::get_post_titles_array( $group_ids );

			if ( ! empty( $group_titles ) ) {
				return \implode( ', ', $group_titles );
			}

			return self::get_ld_none_string();
		}

		/**
		 * Get LearnDash transaction price amount with currency.
		 *
		 * @param int $transaction_id - Transaction post ID.
		 *
		 * @return string - Price amount with currency prefix (e.g., 'USD 99.00') or empty string.
		 *
		 * @since 5.6.0
		 */
		public static function get_ld_price_amount_with_currency( $transaction_id ) {
			$pricing_info_meta = \get_post_meta( $transaction_id, 'pricing_info', true );
			$pricing_info      = \maybe_unserialize( $pricing_info_meta );

			$price_amount = '';
			$currency     = '';

			if ( \is_array( $pricing_info ) ) {
				$price_amount = $pricing_info['price'] ?? '';
				$currency     = $pricing_info['currency'] ?? '';
			}

			if ( ! empty( $currency ) && ! empty( $price_amount ) ) {
				$price_amount = $currency . ' ' . $price_amount;
			}

			return $price_amount;
		}

		/**
		 * Get the admin edit link for a LearnDash transaction post.
		 *
		 * @param \WP_Post $transaction_post - Transaction post object.
		 *
		 * @return string - Admin URL to edit the transaction or its parent order.
		 *
		 * @since 5.6.0
		 */
		public static function get_ld_tx_post_edit_link( $transaction_post ) {
			$transaction_id        = (int) $transaction_post->ID;
			$parent_transaction_id = (int) $transaction_post->post_parent;
			$order_edit_link       = '';

			if ( $parent_transaction_id > 0 ) {
				$order_edit_link = \admin_url( 'post.php?post=' . $parent_transaction_id . '&action=edit' );
			} else {
				$order_edit_link = \admin_url( 'post.php?post=' . $transaction_id . '&action=edit' );
			}

			return $order_edit_link;
		}


		/**
		 * Format progress as a percentage string.
		 *
		 * @param int $completed - Number of completed items.
		 * @param int $total - Total number of items.
		 *
		 * @return string Progress percentage formatted as "X.X%".
		 *
		 * @since 5.6.0
		 */
		private static function format_progress_percentage( $completed, $total ): string {
			if ( $total > 0 ) {
				$progress = ( $completed / $total ) * 100;
				return \round( $progress, 1 ) . '%';
			}

			return '0%';
		}

		/**
		 * Calculate course progress percentage.
		 *
		 * @param int $user_id - User ID.
		 * @param int $course_id - Course ID.
		 *
		 * @return string Progress percentage.
		 *
		 * @since 5.6.0
		 */
		public static function calculate_course_progress( $user_id, $course_id ): string {
			if ( \function_exists( 'learndash_course_get_completed_steps' ) ) {

				if ( 'sfwd-courses' === \get_post_type( $course_id ) ) {
					$completed_steps = \learndash_course_get_completed_steps( $user_id, $course_id );
					$total_steps     = \learndash_get_course_steps_count( $course_id );

					return self::format_progress_percentage( $completed_steps, $total_steps );
				}
			}

			return '0%';
		}

		/**
		 * Calculate lesson progress percentage.
		 *
		 * @param int $user_id - User ID.
		 * @param int $lesson_id - Lesson ID.
		 *
		 * @return string Progress percentage.
		 *
		 * @since 5.6.0
		 */
		public static function calculate_lesson_progress( $user_id, $lesson_id ) {
			if ( \function_exists( 'learndash_get_topic_list' ) && \function_exists( 'learndash_is_topic_complete' ) ) {
				// Get all topics for this lesson.
				$topics = \learndash_get_topic_list( $lesson_id );

				if ( ! empty( $topics ) && \is_array( $topics ) ) {
					$total_topics     = \count( $topics );
					$completed_topics = 0;

					// Count how many topics are completed.
					foreach ( $topics as $topic ) {
						if ( \learndash_is_topic_complete( $user_id, $topic->ID ) ) {
							++$completed_topics;
						}
					}

					return self::format_progress_percentage( $completed_topics, $total_topics );
				}
			}

			return '0%';
		}

		/**
		 * Get quiz result (percentage) from the most recent quiz attempt.
		 *
		 * @param int $user_id - User ID.
		 * @param int $quiz_id - Quiz ID.
		 *
		 * @return string - Quiz result as percentage string (e.g., "85%") or empty string if not available.
		 *
		 * @since 5.6.0
		 */
		public static function get_quiz_result( $user_id, $quiz_id ) {
			$quiz_attempts = \get_user_meta( $user_id, '_sfwd-quizzes', true );

			if ( empty( $quiz_attempts ) || ! \is_array( $quiz_attempts ) ) {
				return '';
			}

			// Find the most recent attempt for this quiz.
			$latest_attempt = null;
			$latest_time    = 0;

			foreach ( $quiz_attempts as $attempt ) {
				if ( isset( $attempt['quiz'] ) && (int) $attempt['quiz'] === (int) $quiz_id ) {
					$attempt_time = isset( $attempt['time'] ) ? (int) $attempt['time'] : 0;

					if ( $attempt_time > $latest_time ) {
						$latest_time    = $attempt_time;
						$latest_attempt = $attempt;
					}
				}
			}

			if ( null === $latest_attempt || ! isset( $latest_attempt['percentage'] ) ) {
				return '';
			}

			$result = round( $latest_attempt['percentage'], 2 ) . '%';

			return $result;
		}

		/**
		 * Get quiz type (release schedule) label.
		 *
		 * @param int $quiz_id - Quiz ID.
		 *
		 * @return string - Quiz type label: "Immediately", "Enrollment-based", "Specific date", or empty string.
		 *
		 * @since 5.6.0
		 */
		public static function get_quiz_type( $quiz_id ) {
			$lesson_schedule = \learndash_get_setting( $quiz_id, 'lesson_schedule' );

			$value = '';

			if ( empty( $lesson_schedule ) ) {
				$value = \esc_html__( 'Immediately', 'wp-security-audit-log' );
			}

			if ( 'visible_after' === $lesson_schedule ) {
				$value = \esc_html__( 'Enrollment-based', 'wp-security-audit-log' );
			}

			if ( 'visible_after_specific_date' === $lesson_schedule ) {
				$value = \esc_html__( 'Specific date', 'wp-security-audit-log' );
			}

			return $value;
		}

		/**
		 * Get user's course groups as comma-separated string.
		 *
		 * @param int $user_id - User ID.
		 * @param int $course_id - Course ID.
		 *
		 * @return string Comma-separated group names or 'None'.
		 *
		 * @since 5.6.0
		 */
		public static function get_user_course_groups( $user_id, $course_id ) {
			if ( ! $course_id ) {
				return self::get_ld_none_string();
			}

			if ( ! \function_exists( 'learndash_get_users_group_ids' ) || ! \function_exists( 'learndash_group_enrolled_courses' ) ) {
				return self::get_ld_none_string();
			}

			// Get all groups the user is in.
			$user_groups = \learndash_get_users_group_ids( $user_id );

			if ( empty( $user_groups ) ) {
				return self::get_ld_none_string();
			}

			// Filter groups that have this course.
			$course_groups = array();

			foreach ( $user_groups as $group_id ) {
				$group_courses = \learndash_group_enrolled_courses( $group_id );
				if ( \in_array( $course_id, $group_courses, true ) ) {
					$course_groups[] = \get_the_title( $group_id );
				}
			}

			if ( empty( $course_groups ) ) {
				return self::get_ld_none_string();
			}

			return \implode( ', ', $course_groups );
		}

		/**
		 * Build event data arrays for all quiz answer submissions.
		 *
		 * @param object $quiz_attempt - Quiz attempt record from wp_learndash_pro_quiz_statistic_ref table.
		 * @param array  $answers - Array of answer records from wp_learndash_pro_quiz_statistic table.
		 *
		 * @return array Array of event data arrays, one for each answer.
		 *
		 * @since 5.6.0
		 */
		public static function get_quiz_answers_event_data( $quiz_attempt, $answers ): array {
			if ( empty( $answers ) || ! $quiz_attempt ) {
				return array();
			}

			$user_id      = (int) $quiz_attempt->user_id;
			$quiz_post_id = (int) $quiz_attempt->quiz_post_id;
			$course_id    = (int) $quiz_attempt->course_post_id;

			$user = \get_user_by( 'ID', $user_id );

			if ( ! $user ) {
				return array();
			}

			$first_name = \get_user_meta( $user_id, 'first_name', true );
			$last_name  = \get_user_meta( $user_id, 'last_name', true );

			$quiz_title    = \get_the_title( $quiz_post_id );
			$course_title  = $course_id ? \get_the_title( $course_id ) : '';
			$course_groups = self::get_user_course_groups( $user_id, $course_id );

			$price_type = '';

			if ( $course_id ) {
				$course_meta = \get_post_meta( $course_id, '_sfwd-courses', true );
				$price_type  = $course_meta['sfwd-courses_course_price_type'] ?? '';
			}

			// Calculate quiz totals.
			$total_questions = \count( $answers );
			$correct_count   = 0;
			$total_points    = 0;

			foreach ( $answers as $answer ) {
				$correct_count += (int) $answer->correct_count;
				$total_points  += (float) $answer->points;
			}

			// Build event data for each answer.
			$events_data = array();

			foreach ( $answers as $index => $answer ) {
				$question_number = $index + 1;
				$question_title  = \get_the_title( $answer->question_post_id );
				$is_correct      = (int) $answer->correct_count === 1;
				$points_earned   = (float) $answer->points;
				$quiz_progress   = self::format_progress_percentage( $question_number, $total_questions );

				$events_data[] = array(
					'TargetUsername' => $user->user_login,
					'FirstName'      => $first_name,
					'LastName'       => $last_name,
					'QuizTitle'      => $quiz_title,
					'QuizProgress'   => $quiz_progress,
					'CourseGroup'    => $course_groups,
					'PriceType'      => $price_type,
					'CourseTitle'    => $course_title,
					'QuestionTitle'  => $question_title,
					'IsCorrect'      => $is_correct ? 'Yes' : 'No',
					'PointsEarned'   => $points_earned,
					'TotalQuestions' => $total_questions,
					'CorrectAnswers' => $correct_count,
					'TotalScore'     => $total_points,
				);
			}

			return $events_data;
		}
	}
}
