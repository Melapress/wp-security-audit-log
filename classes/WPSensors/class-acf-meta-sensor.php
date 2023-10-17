<?php
/**
 * Sensor: ACF meta
 *
 * ACF Meta sensor class file.
 *
 * @since     4.6.0
 * @package   wsal
 * @subpackage sensors
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors;

use WSAL\Controllers\Alert_Manager;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\ACF_Meta_Sensor' ) ) {

	/**
	 * Sensor for events specific to Advanced Custom Fields plugin.
	 *
	 * 2131 ACF relationship added
	 * 2132 ACF relationship removed
	 *
	 * @package    wsal
	 * @subpackage sensors
	 * @since      4.1.3
	 */
	class ACF_Meta_Sensor {

		/**
		 * Array of meta data being updated.
		 *
		 * @var array
		 *
		 * @since 4.5.0
		 */
		private static $old_meta = array();

		/**
		 * Inits the main hooks
		 *
		 * @return void
		 *
		 * @since 4.5.0
		 */
		public static function init() {
			add_filter( 'acf/pre_update_value', array( __CLASS__, 'prepare_relationship_update_check' ), 10, 4 );

			// Relationship field is only available for posts to we don't need to check other meta types (comment, term, or user).
			add_action( 'updated_post_meta', array( __CLASS__, 'on_field_updated' ), 10, 4 );
			add_action( 'deleted_post_meta', array( __CLASS__, 'on_field_updated' ), 10, 4 );
		}

		/**
		 * Runs before an ACF field value is updated. It stores locally information
		 * about relationship fields that are being updated.
		 *
		 * @param mixed      $check   Variable allowing short-circuiting of update_value logic.
		 * @param mixed      $value   The new value.
		 * @param int|string $post_id The post id.
		 * @param array      $field   The field array.
		 *
		 * @return mixed
		 *
		 * @since 4.5.0
		 */
		public static function prepare_relationship_update_check( $check, $value, $post_id, $field ) {
			if ( 'relationship' === $field['type'] ) {
				self::$old_meta[ $field['name'] ] = array(
					'field'   => $field,
					'value'   => \get_field( $field['name'] ),
					'post_id' => $post_id,
				);
			}

			return $check;
		}

		/**
		 * Fires immediately after updating metadata of a specific type.
		 *
		 * @param int    $meta_id ID of updated metadata entry.
		 * @param int    $object_id ID of the object metadata is for.
		 * @param string $meta_key Metadata key.
		 * @param mixed  $_meta_value Metadata value. Serialized if non-scalar.
		 *
		 * @since 4.5.0
		 */
		public static function on_field_updated( $meta_id, $object_id, $meta_key, $_meta_value ) {
			if ( in_array( $meta_key, array_keys( self::$old_meta ) ) ) { // phpcs:ignore
				if ( WP_Meta_Data_Sensor::can_log_meta_key( 'post', $object_id, $meta_key ) ) {
					$old_value = self::convert_to_array_of_post_ids( self::$old_meta[ $meta_key ]['value'] );
					$new_value = self::convert_to_array_of_post_ids( $_meta_value );
					$removed   = array_diff( $old_value, $new_value );
					$added     = array_diff( $new_value, $old_value );

					if ( ! empty( $added ) ) {
						self::log_event( 2131, $added, $object_id, $meta_key, $meta_id );
					}

					if ( ! empty( $removed ) ) {
						self::log_event( 2132, $removed, $object_id, $meta_key, $meta_id );
					}
				}
			}
		}

		/**
		 * Convert arbitrary value to an array of post IDs.
		 *
		 * @param mixed $value An array of posts or post IDs or post ID as string.
		 *
		 * @return int[]
		 *
		 * @since 4.5.0
		 */
		private static function convert_to_array_of_post_ids( $value ) {
			$result = array();
			if ( is_array( $value ) ) {
				$result = array_map(
					function ( $item ) {
						return ( $item instanceof \WP_Post ) ? $item->ID : intval( $item );
					},
					$value
				);
			}

			return $result;
		}

		/**
		 * Log event related to ACF relationship field.
		 *
		 * @param int             $event_id              Event ID.
		 * @param int[]|WP_Post[] $relationship_post_ids Posts or post IDs.
		 * @param int             $object_id             Object ID.
		 * @param string          $meta_key              Meta key.
		 * @param int             $meta_id               Meta ID.
		 *
		 * @since 4.5.0
		 */
		private static function log_event( $event_id, $relationship_post_ids, $object_id, $meta_key, $meta_id ) {
			$post        = get_post( $object_id );
			$editor_link = WP_Content_Sensor::get_editor_link( $object_id );
			Alert_Manager::trigger_event(
				$event_id,
				array(
					'PostID'             => $object_id,
					'PostTitle'          => $post->post_title,
					'PostStatus'         => $post->post_status,
					'PostType'           => $post->post_type,
					'PostDate'           => $post->post_date,
					'PostUrl'            => get_permalink( $post->ID ),
					'MetaID'             => $meta_id,
					'MetaKey'            => $meta_key,
					'Relationships'      => self::format_relationships_label( $relationship_post_ids ),
					'MetaLink'           => $meta_key,
					$editor_link['name'] => $editor_link['value'],
				)
			);
		}

		/**
		 * Formats the relationship label for the activity log entry.
		 *
		 * @param int[] $post_ids List of post IDs.
		 *
		 * @return string
		 *
		 * @since 4.5.0
		 */
		private static function format_relationships_label( $post_ids ) {
			return implode(
				', ',
				array_map(
					function ( $post_id ) {
						return get_the_title( $post_id ) . ' (' . $post_id . ')';
					},
					$post_ids
				)
			);
		}
	}
}
