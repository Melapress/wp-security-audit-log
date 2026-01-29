<?php
/**
 * Custom Alerts for the LearnDash plugin.
 *
 * Class file for alert manager.
 *
 * @package wsal
 * @subpackage wsal-learndash
 *
 * @since 5.6.0
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors\Alerts;

use WSAL\MainWP\MainWP_Addon;
use WSAL\Controllers\Constants;
use WSAL\WP_Sensors\Helpers\LearnDash_Helper;

// Exit if accessed directly.
if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! \class_exists( '\WSAL\WP_Sensors\Alerts\LearnDash_Custom_Alerts' ) ) {
	/**
	 * Custom sensor for the LearnDash plugin.
	 *
	 * @since 5.6.0
	 */
	class LearnDash_Custom_Alerts {

		/**
		 * Returns the structure of the alerts for extension.
		 *
		 * @return array
		 *
		 * @since 5.6.0
		 */
		public static function get_custom_alerts(): array {
			if ( ( \method_exists( LearnDash_Helper::class, 'load_alerts_for_sensor' ) && LearnDash_Helper::load_alerts_for_sensor() ) || MainWP_Addon::check_mainwp_plugin_active() ) {
				return array(
					\esc_html__( 'LearnDash LMS', 'wp-security-audit-log' ) => array(
						\esc_html__( 'Courses', 'wp-security-audit-log' ) => self::get_courses_array(),
						\esc_html__( 'Lessons', 'wp-security-audit-log' ) => self::get_lessons_array(),
						\esc_html__( 'Topics', 'wp-security-audit-log' ) => self::get_topics_array(),
						\esc_html__( 'Groups', 'wp-security-audit-log' ) => self::get_groups_array(),
						\esc_html__( 'Certificates', 'wp-security-audit-log' ) => self::get_certificates_array(),
						\esc_html__( 'Students', 'wp-security-audit-log' ) => self::get_students_array(),
					),
				);
			}

			return array();
		}

		/**
		 * Returns the list of LearnDash alerts that should be disabled by default.
		 *
		 * These are student activity events that can generate a high log volume of events.
		 *
		 * @return int[] - List of alert IDs.
		 *
		 * @since 5.6.0
		 */
		public static function get_default_disabled_alerts(): array {
			return array( 11017, 11556, 11557, 11558, 11559, 11561, 11562, 11563, 11564 );
		}

		/**
		 * Adds LearnDash default disabled alerts to the global list.
		 *
		 * @param int[] $alerts - Current list of default disabled alert IDs.
		 *
		 * @return int[] - Updated list with LearnDash alerts included.
		 *
		 * @since 5.6.0
		 */
		public static function add_default_disabled_alerts( array $alerts ): array {
			return \array_merge( $alerts, self::get_default_disabled_alerts() );
		}

		/**
		 * Returns an array with all the events attached to the sensor (if there are different types of events, this method will merge them into one array - the events ids will be used as keys)
		 *
		 * @return array
		 *
		 * @since 5.6.0
		 */
		public static function get_alerts_array(): array {

			return self::get_courses_array() +
			self::get_lessons_array() +
			self::get_topics_array() +
			self::get_groups_array() +
			self::get_certificates_array() +
			self::get_students_array();
		}

		/**
		 * Learndash Courses Events
		 *
		 * @return array
		 *
		 * @since 5.6.0
		 */
		public static function get_courses_array(): array {
			return array(
				11000 => array(
					11000,
					WSAL_INFORMATIONAL,
					\esc_html__( 'A course was created', 'wp-security-audit-log' ),
					\esc_html__( 'Created the course %PostTitle%.', 'wp-security-audit-log' ),
					array(
						\esc_html__( 'Course ID', 'wp-security-audit-log' ) => '%PostID%',
						\esc_html__( 'Course author', 'wp-security-audit-log' ) => '%PostAuthor%',
						\esc_html__( 'Categories', 'wp-security-audit-log' ) => '%Categories%',
						\esc_html__( 'Course category', 'wp-security-audit-log' ) => '%LdPostCategory%',
						\esc_html__( 'Course status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					Constants::wsaldefaults_build_links( array( 'EditorLinkPost', 'PostUrlIfPublished' ) ),
					'learndash_courses',
					'created',
				),
				11001 => array(
					11001,
					WSAL_LOW,
					\esc_html__( 'A course was published', 'wp-security-audit-log' ),
					\esc_html__( 'Published the course %PostTitle%.', 'wp-security-audit-log' ),
					array(
						\esc_html__( 'Course ID', 'wp-security-audit-log' ) => '%PostID%',
						\esc_html__( 'Course author', 'wp-security-audit-log' ) => '%PostAuthor%',
						\esc_html__( 'Price type', 'wp-security-audit-log' ) => '%PriceType%',
						\esc_html__( 'Categories', 'wp-security-audit-log' ) => '%Categories%',
						\esc_html__( 'Course category', 'wp-security-audit-log' ) => '%LdPostCategory%',
					),
					Constants::wsaldefaults_build_links( array( 'EditorLinkPost', 'PostUrlIfPublished' ) ),
					'learndash_courses',
					'published',
				),
				11003 => array(
					11003,
					WSAL_HIGH,
					\esc_html__( 'A course was moved to trash', 'wp-security-audit-log' ),
					\esc_html__( 'Moved the course %PostTitle% to trash.', 'wp-security-audit-log' ),
					array(
						\esc_html__( 'Course ID', 'wp-security-audit-log' ) => '%PostID%',
						\esc_html__( 'Course author', 'wp-security-audit-log' ) => '%PostAuthor%',
						\esc_html__( 'Price type', 'wp-security-audit-log' ) => '%PriceType%',
						\esc_html__( 'Categories', 'wp-security-audit-log' ) => '%Categories%',
						\esc_html__( 'Course category', 'wp-security-audit-log' ) => '%LdPostCategory%',
						\esc_html__( 'Course status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					array(),
					'learndash_courses',
					'deleted',
				),
				11004 => array(
					11004,
					WSAL_HIGH,
					\esc_html__( 'A course was permanently deleted', 'wp-security-audit-log' ),
					\esc_html__( 'Permanently deleted the course %PostTitle%.', 'wp-security-audit-log' ),
					array(
						\esc_html__( 'Course ID', 'wp-security-audit-log' ) => '%PostID%',
						\esc_html__( 'Price type', 'wp-security-audit-log' ) => '%PriceType%',
						\esc_html__( 'Categories', 'wp-security-audit-log' ) => '%Categories%',
						\esc_html__( 'Course category', 'wp-security-audit-log' ) => '%LdPostCategory%',
					),
					array(),
					'learndash_courses',
					'deleted',
				),
				11005 => array(
					11005,
					WSAL_LOW,
					\esc_html__( 'A course was restored from trash', 'wp-security-audit-log' ),
					\esc_html__( 'Restored the course %PostTitle% from trash.', 'wp-security-audit-log' ),
					array(
						\esc_html__( 'Course ID', 'wp-security-audit-log' ) => '%PostID%',
						\esc_html__( 'Course author', 'wp-security-audit-log' ) => '%PostAuthor%',
						\esc_html__( 'Price type', 'wp-security-audit-log' ) => '%PriceType%',
						\esc_html__( 'Categories', 'wp-security-audit-log' ) => '%Categories%',
						\esc_html__( 'Course category', 'wp-security-audit-log' ) => '%LdPostCategory%',
					),
					Constants::wsaldefaults_build_links( array( 'EditorLinkPost', 'PostUrlIfPublished' ) ),
					'learndash_courses',
					'restored',
				),
				11006 => array(
					11006,
					WSAL_INFORMATIONAL,
					\esc_html__( 'A course was duplicated', 'wp-security-audit-log' ),
					\esc_html__( 'Duplicated the course %PostTitle%.', 'wp-security-audit-log' ),
					array(
						\esc_html__( 'Course ID', 'wp-security-audit-log' ) => '%PostID%',
						\esc_html__( 'Price type', 'wp-security-audit-log' ) => '%PriceType%',
						\esc_html__( 'Category', 'wp-security-audit-log' ) => '%Categories%',
						\esc_html__( 'Course category', 'wp-security-audit-log' ) => '%LdPostCategory%',
					),
					array(),
					'learndash_courses',
					'duplicated',
				),
				11080 => array(
					11080,
					WSAL_INFORMATIONAL,
					\esc_html__( 'Course category created', 'wp-security-audit-log' ),
					\esc_html__( 'Created the course category %TaxonomyTitle%.', 'wp-security-audit-log' ),
					array(
						\esc_html__( 'Slug', 'wp-security-audit-log' ) => '%Slug%',
					),
					Constants::wsaldefaults_build_links( array( 'CategoryLink' ) ),
					'learndash_courses',
					'created',
				),
				11081 => array(
					11081,
					WSAL_INFORMATIONAL,
					\esc_html__( 'Course category deleted', 'wp-security-audit-log' ),
					\esc_html__( 'Deleted the course category %TaxonomyTitle%.', 'wp-security-audit-log' ),
					array(
						\esc_html__( 'Slug', 'wp-security-audit-log' ) => '%Slug%',
					),
					array(),
					'learndash_courses',
					'deleted',
				),
				11090 => array(
					11090,
					WSAL_INFORMATIONAL,
					\esc_html__( 'Course tag created', 'wp-security-audit-log' ),
					\esc_html__( 'Created the course tag %TaxonomyTitle%.', 'wp-security-audit-log' ),
					array(
						\esc_html__( 'Slug', 'wp-security-audit-log' ) => '%Slug%',
					),
					array( \esc_html__( 'View tag', 'wp-security-audit-log' ) => '%TagLink%' ),
					'learndash_courses',
					'created',
				),
				11091 => array(
					11091,
					WSAL_INFORMATIONAL,
					\esc_html__( 'Course tag deleted', 'wp-security-audit-log' ),
					\esc_html__( 'Deleted the course tag %TaxonomyTitle%.', 'wp-security-audit-log' ),
					array(
						\esc_html__( 'Slug', 'wp-security-audit-log' ) => '%Slug%',
					),
					array(),
					'learndash_courses',
					'deleted',
				),
			);
		}

		/**
		 * Learndash Lessons Events
		 *
		 * @return array
		 *
		 * @since 5.6.0
		 */
		public static function get_lessons_array(): array {
			return array(
				11200 => array(
					11200,
					WSAL_INFORMATIONAL,
					\esc_html__( 'A lesson was created', 'wp-security-audit-log' ),
					\esc_html__( 'Created the lesson %PostTitle%.', 'wp-security-audit-log' ),
					array(
						\esc_html__( 'Lesson ID', 'wp-security-audit-log' ) => '%PostID%',
						\esc_html__( 'Lesson author', 'wp-security-audit-log' ) => '%PostAuthor%',
						\esc_html__( 'Category', 'wp-security-audit-log' ) => '%Categories%',
						\esc_html__( 'Lesson category', 'wp-security-audit-log' ) => '%LdPostCategory%',
						\esc_html__( 'Lesson status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					Constants::wsaldefaults_build_links( array( 'EditorLinkPost' ) ),
					'learndash_lessons',
					'created',
				),
				11201 => array(
					11201,
					WSAL_INFORMATIONAL,
					\esc_html__( 'A lesson was published', 'wp-security-audit-log' ),
					\esc_html__( 'Published the lesson %PostTitle%.', 'wp-security-audit-log' ),
					array(
						\esc_html__( 'Lesson ID', 'wp-security-audit-log' ) => '%PostID%',
						\esc_html__( 'Lesson author', 'wp-security-audit-log' ) => '%PostAuthor%',
						\esc_html__( 'Category', 'wp-security-audit-log' ) => '%Categories%',
						\esc_html__( 'Lesson category', 'wp-security-audit-log' ) => '%LdPostCategory%',
					),
					Constants::wsaldefaults_build_links( array( 'EditorLinkPost', 'PostUrlIfPublished' ) ),
					'learndash_lessons',
					'published',
				),
				11206 => array(
					11206,
					WSAL_MEDIUM,
					\esc_html__( 'A lesson was moved to trash', 'wp-security-audit-log' ),
					\esc_html__( 'Moved the lesson %PostTitle% to trash.', 'wp-security-audit-log' ),
					array(
						\esc_html__( 'Lesson ID', 'wp-security-audit-log' ) => '%PostID%',
						\esc_html__( 'Category', 'wp-security-audit-log' ) => '%Categories%',
						\esc_html__( 'Lesson category', 'wp-security-audit-log' ) => '%LdPostCategory%',
						\esc_html__( 'Lesson status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					Constants::wsaldefaults_build_links( array( 'PostUrlIfPublished' ) ),
					'learndash_lessons',
					'deleted',
				),

				11207 => array(
					11207,
					WSAL_HIGH,
					\esc_html__( 'A lesson was permanently deleted', 'wp-security-audit-log' ),
					\esc_html__( 'Permanently deleted the lesson %PostTitle%.', 'wp-security-audit-log' ),
					array(
						\esc_html__( 'Lesson ID', 'wp-security-audit-log' ) => '%PostID%',
						\esc_html__( 'Category', 'wp-security-audit-log' ) => '%Categories%',
						\esc_html__( 'Lesson category', 'wp-security-audit-log' ) => '%LdPostCategory%',
					),
					array(),
					'learndash_lessons',
					'deleted',
				),
				11208 => array(
					11208,
					WSAL_LOW,
					\esc_html__( 'A lesson was restored from trash', 'wp-security-audit-log' ),
					\esc_html__( 'Restored the lesson %PostTitle% from trash.', 'wp-security-audit-log' ),
					array(
						\esc_html__( 'Lesson ID', 'wp-security-audit-log' ) => '%PostID%',
						\esc_html__( 'Category', 'wp-security-audit-log' ) => '%Categories%',
						\esc_html__( 'Lesson category', 'wp-security-audit-log' ) => '%LdPostCategory%',
					),
					array(),
					'learndash_lessons',
					'restored',
				),
				11209 => array(
					11209,
					WSAL_INFORMATIONAL,
					\esc_html__( 'A lesson was duplicated', 'wp-security-audit-log' ),
					\esc_html__( 'Duplicated the lesson %PostTitle%.', 'wp-security-audit-log' ),
					array(
						\esc_html__( 'Lesson ID', 'wp-security-audit-log' ) => '%PostID%',
						\esc_html__( 'Category', 'wp-security-audit-log' ) => '%Categories%',
						\esc_html__( 'Lesson category', 'wp-security-audit-log' ) => '%LdPostCategory%',
					),
					array(),
					'learndash_lessons',
					'duplicated',
				),
				11300 => array(
					11300,
					WSAL_INFORMATIONAL,
					\esc_html__( 'A lesson category was created', 'wp-security-audit-log' ),
					\esc_html__( 'Created the lesson category %TaxonomyTitle%.', 'wp-security-audit-log' ),
					array(
						\esc_html__( 'Slug', 'wp-security-audit-log' ) => '%Slug%',
					),
					array( \esc_html__( 'View lesson category', 'wp-security-audit-log' ) => '%CategoryLink%' ),
					'learndash_lessons',
					'created',
				),
				11301 => array(
					11301,
					WSAL_INFORMATIONAL,
					\esc_html__( 'A lesson category was deleted', 'wp-security-audit-log' ),
					\esc_html__( 'Deleted the lesson category %TaxonomyTitle%.', 'wp-security-audit-log' ),
					array(
						\esc_html__( 'Slug', 'wp-security-audit-log' ) => '%Slug%',
					),
					array(),
					'learndash_lessons',
					'deleted',
				),
				11350 => array(
					11350,
					WSAL_INFORMATIONAL,
					\esc_html__( 'A lesson tag was created', 'wp-security-audit-log' ),
					\esc_html__( 'Created the lesson tag %TaxonomyTitle%', 'wp-security-audit-log' ),
					array(
						\esc_html__( 'Slug', 'wp-security-audit-log' ) => '%Slug%',
					),
					array( \esc_html__( 'View lesson tag', 'wp-security-audit-log' ) => '%TagLink%' ),
					'learndash_lessons',
					'created',
				),
				11351 => array(
					11351,
					WSAL_INFORMATIONAL,
					\esc_html__( 'A lesson tag was deleted', 'wp-security-audit-log' ),
					\esc_html__( 'Deleted the lesson tag %TaxonomyTitle%', 'wp-security-audit-log' ),
					array(
						\esc_html__( 'Slug', 'wp-security-audit-log' ) => '%Slug%',
					),
					array(),
					'learndash_lessons',
					'deleted',
				),
			);
		}

		/**
		 * Learndash Topics Events
		 *
		 * @return array
		 *
		 * @since 5.6.0
		 */
		public static function get_topics_array(): array {
			return array(
				11400 => array(
					11400,
					WSAL_INFORMATIONAL,
					\esc_html__( 'A topic was created', 'wp-security-audit-log' ),
					\esc_html__( 'Created the topic %PostTitle%.', 'wp-security-audit-log' ),
					array(
						\esc_html__( 'Topic ID', 'wp-security-audit-log' ) => '%PostID%',
						\esc_html__( 'Topic category', 'wp-security-audit-log' ) => '%LdPostCategory%',
						\esc_html__( 'Topic status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					Constants::wsaldefaults_build_links( array( 'EditorLinkPost' ) ),
					'learndash_topics',
					'created',
				),
				11401 => array(
					11401,
					WSAL_INFORMATIONAL,
					\esc_html__( 'A topic was published', 'wp-security-audit-log' ),
					\esc_html__( 'Published the topic %PostTitle%.', 'wp-security-audit-log' ),
					array(
						\esc_html__( 'Topic ID', 'wp-security-audit-log' ) => '%PostID%',
						\esc_html__( 'Topic category', 'wp-security-audit-log' ) => '%LdPostCategory%',
					),
					Constants::wsaldefaults_build_links( array( 'EditorLinkPost' ) ),
					'learndash_topics',
					'published',
				),
				11404 => array(
					11404,
					WSAL_MEDIUM,
					\esc_html__( 'A topic was moved to trash', 'wp-security-audit-log' ),
					\esc_html__( 'Moved the topic %PostTitle% to trash.', 'wp-security-audit-log' ),
					array(
						\esc_html__( 'Topic ID', 'wp-security-audit-log' ) => '%PostID%',
						\esc_html__( 'Topic category', 'wp-security-audit-log' ) => '%LdPostCategory%',
						\esc_html__( 'Topic status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					Constants::wsaldefaults_build_links( array( 'EditorLinkPost', 'PostUrlIfPublished' ) ),
					'learndash_topics',
					'deleted',
				),
				11405 => array(
					11405,
					WSAL_INFORMATIONAL,
					\esc_html__( 'A topic was permanently deleted', 'wp-security-audit-log' ),
					\esc_html__( 'Permanently deleted the topic %PostTitle%.', 'wp-security-audit-log' ),
					array(
						\esc_html__( 'Topic ID', 'wp-security-audit-log' ) => '%PostID%',
						\esc_html__( 'Topic category', 'wp-security-audit-log' ) => '%LdPostCategory%',
					),
					array(),
					'learndash_topics',
					'deleted',
				),
				11406 => array(
					11406,
					WSAL_INFORMATIONAL,
					\esc_html__( 'A topic was duplicated', 'wp-security-audit-log' ),
					\esc_html__( 'Duplicated the topic %PostTitle%.', 'wp-security-audit-log' ),
					array(
						\esc_html__( 'Topic ID', 'wp-security-audit-log' ) => '%PostID%',
						\esc_html__( 'Topic category', 'wp-security-audit-log' ) => '%LdPostCategory%',
					),
					array(),
					'learndash_topics',
					'duplicated',
				),
				11450 => array(
					11450,
					WSAL_INFORMATIONAL,
					\esc_html__( 'Topic tag created', 'wp-security-audit-log' ),
					\esc_html__( 'Created the topic tag %TaxonomyTitle%.', 'wp-security-audit-log' ),
					array(
						\esc_html__( 'Slug', 'wp-security-audit-log' ) => '%Slug%',
					),
					array( \esc_html__( 'View tag', 'wp-security-audit-log' ) => '%TagLink%' ),
					'learndash_topics',
					'created',
				),
				11451 => array(
					11451,
					WSAL_INFORMATIONAL,
					\esc_html__( 'Topic tag deleted', 'wp-security-audit-log' ),
					\esc_html__( 'Deleted the topic tag %TaxonomyTitle%.', 'wp-security-audit-log' ),
					array(
						\esc_html__( 'Slug', 'wp-security-audit-log' ) => '%Slug%',
					),
					array(),
					'learndash_topics',
					'deleted',
				),
			);
		}

		/**
		 * Learndash Groups Events
		 *
		 * @return array
		 *
		 * @since 5.6.0
		 */
		public static function get_groups_array(): array {
			return array(
				11500 => array(
					11500,
					WSAL_INFORMATIONAL,
					\esc_html__( 'A group was created', 'wp-security-audit-log' ),
					\esc_html__( 'Created the group %PostTitle%.', 'wp-security-audit-log' ),
					array(
						\esc_html__( 'Group author', 'wp-security-audit-log' ) => '%PostAuthor%',
						\esc_html__( 'Category', 'wp-security-audit-log' ) => '%Categories%',
						\esc_html__( 'Group category', 'wp-security-audit-log' ) => '%LdPostCategory%',
						\esc_html__( 'Group status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					Constants::wsaldefaults_build_links( array( 'EditorLinkPost' ) ),
					'learndash_groups',
					'created',
				),
				11501 => array(
					11501,
					WSAL_INFORMATIONAL,
					\esc_html__( 'A group was published', 'wp-security-audit-log' ),
					\esc_html__( 'Published the group %PostTitle%.', 'wp-security-audit-log' ),
					array(
						\esc_html__( 'Group author', 'wp-security-audit-log' ) => '%PostAuthor%',
						\esc_html__( 'Price type', 'wp-security-audit-log' ) => '%PriceType%',
						\esc_html__( 'Category', 'wp-security-audit-log' ) => '%Categories%',
						\esc_html__( 'Group category', 'wp-security-audit-log' ) => '%LdPostCategory%',
					),
					Constants::wsaldefaults_build_links( array( 'EditorLinkPost', 'PostUrlIfPublished' ) ),
					'learndash_groups',
					'published',
				),
				11503 => array(
					11503,
					WSAL_MEDIUM,
					\esc_html__( 'A group was moved to trash', 'wp-security-audit-log' ),
					\esc_html__( 'Moved the group %PostTitle% to trash.', 'wp-security-audit-log' ),
					array(
						\esc_html__( 'Group author', 'wp-security-audit-log' ) => '%PostAuthor%',
						\esc_html__( 'Price type', 'wp-security-audit-log' ) => '%PriceType%',
						\esc_html__( 'Category', 'wp-security-audit-log' ) => '%Categories%',
						\esc_html__( 'Group category', 'wp-security-audit-log' ) => '%LdPostCategory%',
						\esc_html__( 'Group status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					Constants::wsaldefaults_build_links( array( 'EditorLinkPost' ) ),
					'learndash_groups',
					'deleted',
				),
				11504 => array(
					11504,
					WSAL_INFORMATIONAL,
					\esc_html__( 'A group was permanently deleted', 'wp-security-audit-log' ),
					\esc_html__( 'Permanently deleted the group %PostTitle%.', 'wp-security-audit-log' ),
					array(
						\esc_html__( 'Category', 'wp-security-audit-log' ) => '%Categories%',
						\esc_html__( 'Group category', 'wp-security-audit-log' ) => '%LdPostCategory%',
					),
					array(),
					'learndash_groups',
					'deleted',
				),
				11505 => array(
					11505,
					WSAL_LOW,
					\esc_html__( 'A group was restored from trash', 'wp-security-audit-log' ),
					\esc_html__( 'Restored the group %PostTitle% from trash.', 'wp-security-audit-log' ),
					array(
						\esc_html__( 'Group author', 'wp-security-audit-log' ) => '%PostAuthor%',
						\esc_html__( 'Price type', 'wp-security-audit-log' ) => '%PriceType%',
						\esc_html__( 'Category', 'wp-security-audit-log' ) => '%Categories%',
						\esc_html__( 'Group category', 'wp-security-audit-log' ) => '%LdPostCategory%',
					),
					Constants::wsaldefaults_build_links( array( 'EditorLinkPost', 'PostUrlIfPublished' ) ),
					'learndash_groups',
					'restored',
				),
			);
		}

		/**
		 * Learndash Certificates Events
		 *
		 * @return array
		 *
		 * @since 5.6.0
		 */
		public static function get_certificates_array(): array {
			return array(
			);
		}

		/**
		 * Learndash Students Events
		 *
		 * @return array
		 *
		 * @since 5.6.0
		 */
		public static function get_students_array(): array {
			return array(
			);
		}
	}
}
