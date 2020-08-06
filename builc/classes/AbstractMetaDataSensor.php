<?php
/**
 * Sensor: Abstract meta data
 *
 * Abstract meta data sensor file.
 *
 * @since 4.1.3
 * @package Wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract sensor for meta data.
 *
 * @package Wsal
 * @subpackage Sensors
 * @since 4.1.3
 */
abstract class WSAL_AbstractMetaDataSensor extends WSAL_AbstractSensor {

	/**
	 * Array of meta data being updated.
	 *
	 * @var array
	 */
	protected $old_meta = array();

	/**
	 * Check "Excluded Custom Fields" or meta keys starts with "_".
	 *
	 * @param int $object_id - Object ID.
	 * @param string $meta_key - Meta key.
	 *
	 * @return boolean can log true|false
	 */
	protected function CanLogMetaKey( $object_id, $meta_key ) {
		// Check if excluded meta key or starts with _.
		if ( '_' === substr( $meta_key, 0, 1 ) ) {
			/**
			 * List of hidden keys allowed to log.
			 *
			 * @since 3.4.1
			 */
			$log_hidden_keys = apply_filters( 'wsal_log_hidden_meta_keys', array() );

			// If the meta key is allowed to log then return true.
			if ( in_array( $meta_key, $log_hidden_keys, true ) ) {
				return true;
			}

			return false;
		} elseif ( $this->IsExcludedCustomFields( $meta_key ) ) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Get editor link.
	 *
	 * @param stdClass|int $post - The post.
	 *
	 * @return array $editor_link - Name and value link
	 */
	protected function GetEditorLink( $post ) {
		$post_id = is_int( $post ) ? intval( $post ) : $post->ID;
		return array(
			'name'  => 'EditorLinkPost',
			'value' => get_edit_post_link( $post_id ),
		);
	}

	/**
	 * Check "Excluded Custom Fields".
	 * Used in the above function.
	 *
	 * @param string $custom - Custom meta key.
	 *
	 * @return boolean is excluded from monitoring true|false
	 */
	public function IsExcludedCustomFields( $custom ) {
		$custom_fields = $this->plugin->settings()->GetExcludedMonitoringCustom();

		if ( in_array( $custom, $custom_fields ) ) {
			return true;
		}

		foreach ( $custom_fields as $field ) {
			if ( false !== strpos( $field, '*' ) ) {
				// Wildcard str[any_character] when you enter (str*).
				if ( substr( $field, - 1 ) == '*' ) {
					$field = rtrim( $field, '*' );
					if ( preg_match( "/^$field/", $custom ) ) {
						return true;
					}
				}

				// Wildcard [any_character]str when you enter (*str).
				if ( '*' === substr( $field, 0, 1 ) ) {
					$field = ltrim( $field, '*' );
					if ( preg_match( "/$field$/", $custom ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}
}
