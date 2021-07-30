<?php
/**
 * Sensor: Advanced Custom Fields
 *
 * Advanced Custom Fields sensor file.
 *
 * @since 4.1.3
 * @package wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sensor for events specific to Advanced Custom Fields plugin.
 *
 * 2131 ACF relationship added
 * 2132 ACF relationship removed
 *
 * @package wsal
 * @subpackage sensors
 * @since 4.1.3
 */
class WSAL_Sensors_ACFMeta extends WSAL_AbstractMetaDataSensor {

	/**
	 * Array of meta data being updated.
	 *
	 * @var array
	 */
	protected $old_meta = array();

	/**
	 * Listening to events using WP hooks.
	 */
	public function HookEvents() {
		add_filter( "acf/pre_update_value", array( $this, 'prepare_relationship_update_check' ), 10, 4 );

		//  relationship field is only available for posts to we don't need to check other meta types (comment, term, or user)
		add_action( "updated_post_meta", array( $this, 'on_field_updated' ), 10, 4 );
		add_action( "deleted_post_meta", array( $this, 'on_field_updated' ), 10, 4 );
	}

	/**
	 * Runs before an ACF field value is updated. It stores locally information
	 * about relationship fields that are being updated.
	 *
	 * @param mixed $check
	 * @param mixed $value
	 * @param int $post_id
	 * @param array $field
	 *
	 * @return mixed
	 */
	public function prepare_relationship_update_check( $check, $value, $post_id, $field ) {
		if ( 'relationship' == $field['type'] ) {
			$this->old_meta[ $field['name'] ] = [
				'field'   => $field,
				'value'   => get_field( $field['name'] ),
				'post_id' => $post_id
			];
		}

		return $check;
	}

	/**
	 * Fires immediately after updating metadata of a specific type.
	 *
	 * @param int $meta_id ID of updated metadata entry.
	 * @param int $object_id ID of the object metadata is for.
	 * @param string $meta_key Metadata key.
	 * @param mixed $_meta_value Metadata value. Serialized if non-scalar.
	 */
	public function on_field_updated( $meta_id, $object_id, $meta_key, $_meta_value ) {
		if ( in_array( $meta_key, array_keys( $this->old_meta ) ) ) {
			if ( $this->CanLogMetaKey( 'post', $object_id, $meta_key ) ) {
				$old_value = $this->convert_to_array_of_post_ids( $this->old_meta[ $meta_key ]['value'] );
				$new_value = $this->convert_to_array_of_post_ids( $_meta_value );
				$removed   = array_diff( $old_value, $new_value );
				$added     = array_diff( $new_value, $old_value );

				if ( ! empty( $added ) ) {
					$this->log_event( 2131, $added, $object_id, $meta_key, $meta_id );
				}

				if ( ! empty( $removed ) ) {
					$this->log_event( 2132, $removed, $object_id, $meta_key, $meta_id );
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
	 */
	private function convert_to_array_of_post_ids( $value ) {
		$result = [];
		if ( is_array( $value ) ) {
			$result = array_map( function ( $item ) {
				return ( $item instanceof WP_Post ) ? $item->ID : intval( $item );
			}, $value );
		}

		return $result;
	}

	/**
	 * Log event related to ACF relationship field.
	 *
	 * @param int $event_id
	 * @param int[]|WP_Post[] $relationship_post_ids
	 * @param int $object_id
	 * @param string $meta_key
	 * @param int $meta_id
	 */
	private function log_event( $event_id, $relationship_post_ids, $object_id, $meta_key, $meta_id ) {
		$post        = get_post( $object_id );
		$editor_link = $this->GetEditorLink( $object_id );
		$this->plugin->alerts->Trigger(
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
				'Relationships'      => $this->format_relationships_label( $relationship_post_ids ),
				'MetaLink'           => $meta_key,
				$editor_link['name'] => $editor_link['value'],
			)
		);
	}

	/**
	 * Formats the relationship label for the activity log entry.
	 *
	 * @param int[] $post_ids
	 *
	 * @return string
	 */
	private function format_relationships_label( $post_ids ) {
		return implode( ', ', array_map( function ( $post_id ) {
			return get_the_title( $post_id ) . ' (' . $post_id . ')';
		}, $post_ids ) );
	}
}
