<?php
/**
 * Custom Sensors for Rank Math
 * Class file for alert manager.
 *
 * @since 5.4.0
 *
 * @package Wsal
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors;

use WSAL\Controllers\Alert_Manager;
use WSAL\Helpers\Settings_Helper;
use WSAL\WP_Sensors\Helpers\Rank_Math_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Plugin_Sensors\Rank_Math_Sensor' ) ) {
	/**
	 * Custom sensor for Rank Math plugin.
	 *
	 * @since 5.4.0
	 */
	class Rank_Math_Sensor {

		public const SUPPORTED_META_KEYS = array(
			'rank_math_title'           => array(
				'alert_id'       => 10702,
				'old_value_name' => 'OldSEOTitle',
				'new_value_name' => 'NewSEOTitle',
			),
			'rank_math_description'     => array(
				'alert_id'       => 10703,
				'old_value_name' => 'old_desc',
				'new_value_name' => 'new_desc',
			),
			'rank_math_focus_keyword'   => array(
				'alert_id'       => 10704,
				'old_value_name' => 'old_keywords',
				'new_value_name' => 'new_keywords',
			),
			'rank_math_pillar_content'  => array(
				'alert_id'       => 10705,
				'old_value_name' => 'old_status',
				'new_value_name' => 'new_status',
				'event_type'     => true,
			),
			'rank_math_robots'          => array(
				'alert_id'       => null,
				'old_value_name' => 'old_status',
				'new_value_name' => 'new_status',
				'event_type'     => true,
				'supported_vals' => array(
					'nofollow'     => array(
						'alert_id' => 10707,
					),
					'noimageindex' => array(
						'alert_id' => 10708,
					),
					'noarchive'    => array(
						'alert_id' => 10709,
					),
					'nosnippet'    => array(
						'alert_id' => 10710,
					),
					'index'        => array(
						'alert_id' => 10706,
					),
				),
			),
			'rank_math_advanced_robots' => array(
				'alert_id'       => null,
				'old_value_name' => 'old_status',
				'new_value_name' => 'new_status',
				'event_type'     => true,
				'supported_vals' => array(
					'max-snippet'       => array(
						'alert_id' => 10711,
					),
					'max-video-preview' => array(
						'alert_id' => 10712,
					),
					'max-image-preview' => array(
						'alert_id' => 10713,
					),
				),
				'extract_vals'   => true,
			),
			'rank_math_canonical_url'   => array(
				'alert_id'       => 10714,
				'old_value_name' => 'OldCanonicalUrl',
				'new_value_name' => 'NewCanonicalUrl',
			),
		);

		/**
		 * Class cache for the type of the event.
		 *
		 * @var bool
		 *
		 * @since 5.4.0
		 */
		private static $add_event = null;

		/**
		 * Stores the old values of the metadata, so the can be present in the alert, if the update is successful.
		 *
		 * @var array
		 *
		 * @since 5.4.0
		 */
		private static $old_vals = array();

		/**
		 * Hook events related to sensor.
		 *
		 * @since 5.4.0
		 */
		public static function init() {
			if ( Rank_Math_Helper::is_rank_math_active() ) {
				\add_action( 'rank_math/module_changed', array( __CLASS__, 'module_change' ), 10, 2 );

				\add_action( 'update_post_metadata', array( __CLASS__, 'store_old_values' ), 10, 5 );
				\add_action( 'delete_post_metadata', array( __CLASS__, 'store_old_values_delete' ), 10, 5 );

				\add_action( 'updated_post_meta', array( __CLASS__, 'post_meta_updated' ), 10, 4 );
				\add_action( 'added_post_meta', array( __CLASS__, 'post_meta_added' ), 10, 4 );
				\add_action( 'deleted_post_meta', array( __CLASS__, 'post_meta_updated' ), 10, 4 );
			}
		}

		/**
		 * Loads all the plugin dependencies early.
		 *
		 * @return void
		 *
		 * @since 5.4.0
		 */
		public static function early_init() {
			\add_filter(
				'wsal_event_objects',
				array( Rank_Math_Helper::class, 'wsal_rank_math_add_custom_event_objects' ),
				10,
				2
			);
		}

		/**
		 * Triggers when module status changes
		 *
		 * @param string $module - The name of the module.
		 * @param string $state - The state of the module.
		 *
		 * @return void
		 *
		 * @since 5.4.0
		 */
		public static function module_change( $module, $state ) {
			Alert_Manager::trigger_event(
				10701,
				array(
					'ModuleName' => $module,
					'state'      => $state,
					'EventType'  => ( 'on' === $state ) ? 'activated' : 'deactivated',
				)
			);
		}

		/**
		 * Fires immediately after updating metadata of a specific type.
		 *
		 * @param int    $meta_id ID of updated metadata entry.
		 * @param int    $object_id ID of the object metadata is for.
		 * @param string $meta_key Metadata key.
		 * @param mixed  $_meta_value Metadata value. Serialized if non-scalar.
		 *
		 * @since 5.4.0
		 */
		public static function post_meta_updated( $meta_id, $object_id, $meta_key, $_meta_value ) {

			if ( ! ( isset( self::SUPPORTED_META_KEYS[ $meta_key ] ) ) ) {
				return;
			} else {
				self::store_event( $meta_id, $object_id, $meta_key, $_meta_value );
			}
		}
		/**
		 * Fires immediately after updating metadata of a specific type.
		 *
		 * @param int    $meta_id ID of updated metadata entry.
		 * @param int    $object_id ID of the object metadata is for.
		 * @param string $meta_key Metadata key.
		 * @param mixed  $_meta_value Metadata value. Serialized if non-scalar.
		 *
		 * @since 5.4.0
		 */
		public static function post_meta_added( $meta_id, $object_id, $meta_key, $_meta_value ) {

			self::$add_event = true;

			self::post_meta_updated( $meta_id, $object_id, $meta_key, $_meta_value );
		}

		/**
		 * Stores the old value of the metadata before updating it.
		 *
		 * @param null|bool $check      Whether to allow updating metadata for the given type.
		 * @param int       $object_id  ID of the object metadata is for.
		 * @param string    $meta_key   Metadata key.
		 * @param mixed     $meta_value Metadata value. Must be serializable if non-scalar.
		 * @param bool      $delete_all Whether to delete the matching metadata entries
		 *                              for all objects, ignoring the specified $object_id.
		 *                              Default false.
		 *
		 * @return void
		 *
		 * @since 5.4.0
		 */
		public static function store_old_values_delete( $check, $object_id, $meta_key, $meta_value, $delete_all ): void {
			if ( ! ( isset( self::SUPPORTED_META_KEYS[ $meta_key ] ) ) ) {
				return;
			} else {
				self::store_old_values( $check, $object_id, $meta_key, $meta_value, $meta_value );
			}
		}

		/**
		 * Stores the old value of the metadata before updating it.
		 *
		 * @param null|bool $check      Whether to allow updating metadata for the given type.
		 * @param int       $object_id  ID of the object metadata is for.
		 * @param string    $meta_key   Metadata key.
		 * @param mixed     $meta_value Metadata value. Must be serializable if non-scalar.
		 * @param mixed     $prev_value Optional. Previous value to check before updating.
		 *                              If specified, only update existing metadata entries with
		 *                              this value. Otherwise, update all entries.
		 *
		 * @return void
		 *
		 * @since 5.4.0
		 */
		public static function store_old_values( $check, $object_id, $meta_key, $meta_value, $prev_value ): void {
			if ( ! ( isset( self::SUPPORTED_META_KEYS[ $meta_key ] ) ) ) {
				return;
			} else {
				if ( empty( $prev_value ) ) {
					// Unfortunately RnakMath doesn't provide the old value in the update_post_metadata hook.
					$prev_value = \get_metadata_raw( 'post', $object_id, $meta_key );
					if ( \is_countable( $prev_value ) && count( $prev_value ) === 1 ) {
						$prev_value = $prev_value[0];
					}
				}

				self::$old_vals[ $meta_key ] = $prev_value;
			}
		}

		/**
		 * Fires focus_keyword metadata changes.
		 *
		 * @param int    $meta_id ID of updated metadata entry.
		 * @param int    $object_id ID of the object metadata is for.
		 * @param string $meta_key Metadata key.
		 * @param mixed  $_meta_value Metadata value. Serialized if non-scalar.
		 *
		 * @since 5.4.0
		 */
		private static function store_event( $meta_id, $object_id, $meta_key, $_meta_value ) {

			if ( ! ( isset( self::SUPPORTED_META_KEYS[ $meta_key ] ) ) ) {
				return;
			}
			$alert_id = self::SUPPORTED_META_KEYS[ $meta_key ]['alert_id'];

			$post = \get_post( $object_id );

			$old_value = self::$old_vals[ $meta_key ] ?? '';

			$alert_vals = array(
				'PostID'         => $post->ID,
				'PostType'       => $post->post_type,
				'PostTitle'      => $post->post_title,
				'PostStatus'     => $post->post_status,
				'PostDate'       => $post->post_date,
				'PostUrl'        => \get_permalink( $post->ID ),
				'EditorLinkPost' => \get_edit_post_link( $post->ID ),
			);

			if ( isset( self::SUPPORTED_META_KEYS[ $meta_key ]['supported_vals'] ) && \is_array( self::SUPPORTED_META_KEYS[ $meta_key ]['supported_vals'] ) ) {
				foreach ( self::SUPPORTED_META_KEYS[ $meta_key ]['supported_vals'] as $key => $val ) {

					$alert_id = $val['alert_id'];

					if ( ! \is_array( $old_value ) ) {
						$old_value = array( $old_value );
					}
					if ( ! \is_array( $_meta_value ) ) {
						$_meta_value = array( $_meta_value );
					}

					if ( \in_array( $key, $_meta_value, true ) || isset( $_meta_value[ $key ] ) ) {

						// If it is associative array - extract the value.
						if ( isset( self::SUPPORTED_META_KEYS[ $meta_key ]['extract_vals'] ) && self::SUPPORTED_META_KEYS[ $meta_key ]['extract_vals'] && isset( $_meta_value[ $key ] ) ) {
							$alert_vals[ self::SUPPORTED_META_KEYS[ $meta_key ]['new_value_name'] ] = $_meta_value[ $key ];
						} else {
							$alert_vals[ self::SUPPORTED_META_KEYS[ $meta_key ]['new_value_name'] ] = 'on';
						}
					} else {
						$alert_vals[ self::SUPPORTED_META_KEYS[ $meta_key ]['new_value_name'] ] = 'off';
					}
					if ( \in_array( $key, $old_value, true ) || isset( $old_value[ $key ] ) ) {
						if ( isset( self::SUPPORTED_META_KEYS[ $meta_key ]['extract_vals'] ) && self::SUPPORTED_META_KEYS[ $meta_key ]['extract_vals'] && isset( $old_value[ $key ] ) ) {
							$alert_vals[ self::SUPPORTED_META_KEYS[ $meta_key ]['old_value_name'] ] = $old_value[ $key ];
						} else {
							$alert_vals[ self::SUPPORTED_META_KEYS[ $meta_key ]['old_value_name'] ] = 'on';
						}
					} elseif ( self::$add_event ) {
						$alert_vals[ self::SUPPORTED_META_KEYS[ $meta_key ]['old_value_name'] ] = null;
					} else {
						$alert_vals[ self::SUPPORTED_META_KEYS[ $meta_key ]['old_value_name'] ] = 'off';
					}

					if ( $alert_vals[ self::SUPPORTED_META_KEYS[ $meta_key ]['old_value_name'] ] !== $alert_vals[ self::SUPPORTED_META_KEYS[ $meta_key ]['new_value_name'] ] ) {

						if ( isset( self::SUPPORTED_META_KEYS[ $meta_key ]['event_type'] ) && self::SUPPORTED_META_KEYS[ $meta_key ]['event_type'] ) {

							if ( isset( self::SUPPORTED_META_KEYS[ $meta_key ]['extract_vals'] ) && self::SUPPORTED_META_KEYS[ $meta_key ]['extract_vals'] ) {
								if ( isset( $old_value[ $key ] ) && isset( $_meta_value[ $key ] ) ) {
									$alert_vals['EventType'] = 'modified';
								} else {
									$alert_vals['EventType'] = ( 'off' !== $alert_vals[ self::SUPPORTED_META_KEYS[ $meta_key ]['new_value_name'] ] ) ? 'enabled' : 'disabled';
								}
							} else {
								$alert_vals['EventType'] = ( Settings_Helper::string_to_bool( $alert_vals[ self::SUPPORTED_META_KEYS[ $meta_key ]['new_value_name'] ] ) ) ? 'enabled' : 'disabled';
							}
						}

						Alert_Manager::trigger_event(
							$alert_id,
							$alert_vals
						);
					}
				}
			} else {

				$alert_vals[ self::SUPPORTED_META_KEYS[ $meta_key ]['old_value_name'] ] = $old_value;
				$alert_vals[ self::SUPPORTED_META_KEYS[ $meta_key ]['new_value_name'] ] = $_meta_value;

				if ( isset( self::SUPPORTED_META_KEYS[ $meta_key ]['event_type'] ) && self::SUPPORTED_META_KEYS[ $meta_key ]['event_type'] ) {
					$alert_vals['EventType'] = ( Settings_Helper::string_to_bool( $_meta_value ) ) ? 'enabled' : 'disabled';
				}

				Alert_Manager::trigger_event(
					$alert_id,
					$alert_vals
				);
			}
		}
	}
}
