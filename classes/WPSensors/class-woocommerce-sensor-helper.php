<?php
/**
 * Sensor: WooCommerce
 *
 * WooCommerce sensor file.
 *
 * @package Wsal
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors_Helpers;

use WSAL\Controllers\Alert_Manager;
use WSAL\WP_Sensors\WooCommerce_Sensor;
use WSAL\WP_Sensors\Helpers\Woocommerce_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Plugin_Sensors\WooCommerce_Sensor_Helper' ) ) {

	/**
	 * Support for WooCommerce Plugin.
	 *
	 * @package Wsal
	 */
	class WooCommerce_Sensor_Helper {

		/**
		 * Is Event 9016 Logged?
		 *
		 * @since 3.3.1
		 *
		 * @var array
		 */
		private static $last_9016_type = array();

		/**
		 * Trigger events 9003
		 *
		 * @param array  $old_cats - Old Categories.
		 * @param array  $new_cats - New Categories.
		 * @param object $oldpost  - Old product object.
		 * @param object $newpost  - New product object.
		 * @return int
		 *
		 * @since 4.6.0
		 */
		public static function check_categories_change( $old_cats, $new_cats, $oldpost, $newpost ) {
			if ( 'trash' === $newpost->post_status || 'trash' === $oldpost->post_status ) {
				return 0;
			}

			$old_cats = is_array( $old_cats ) ? implode( ', ', $old_cats ) : $old_cats;
			$new_cats = is_array( $new_cats ) ? implode( ', ', $new_cats ) : $new_cats;
			if ( ! empty( $old_cats ) && $old_cats !== $new_cats ) {
				$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $newpost );
				Alert_Manager::trigger_event(
					9003,
					array(
						'ProductTitle'       => sanitize_text_field( $newpost->post_title ),
						'ProductStatus'      => sanitize_text_field( $newpost->post_status ),
						'PostStatus'         => sanitize_text_field( $newpost->post_status ),
						'PostID'             => esc_attr( $newpost->ID ),
						'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $newpost->ID ) ),
						'OldCategories'      => $old_cats ? sanitize_text_field( $old_cats ) : __( 'no categories', 'wp-security-audit-log' ),
						'NewCategories'      => $new_cats ? sanitize_text_field( $new_cats ) : __( 'no categories', 'wp-security-audit-log' ),
						$editor_link['name'] => $editor_link['value'],
					)
				);
				return 1;
			}
			return 0;
		}

		/**
		 * Trigger events 9004
		 *
		 * @param object $oldpost - Old product object.
		 * @param object $newpost - New product object.
		 * @return int
		 *
		 * @since 4.6.0
		 */
		public static function check_short_description_change( $oldpost, $newpost ) {
			if ( 'auto-draft' === $oldpost->post_status ) {
				return 0;
			}

			if ( $oldpost->post_excerpt !== $newpost->post_excerpt ) {
				if ( 'product' === $newpost->post_type ) {
					$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $oldpost );
					Alert_Manager::trigger_event(
						9004,
						array(
							'PostID'             => esc_attr( $oldpost->ID ),
							'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $oldpost->ID ) ),
							'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
							'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
							'PostStatus'         => sanitize_text_field( $oldpost->post_status ),
							'OldDescription'     => $oldpost->post_excerpt,
							'NewDescription'     => $newpost->post_excerpt,
							$editor_link['name'] => $editor_link['value'],
						)
					);
					return 1;
				} elseif ( 'shop_coupon' === $newpost->post_type ) {
					$coupon_data                   = WooCommerce_Sensor::get_coupon_event_data( $newpost );
					$coupon_data['OldDescription'] = $oldpost->post_excerpt;
					$coupon_data['NewDescription'] = $newpost->post_excerpt;
					Alert_Manager::trigger_event( 9069, $coupon_data );
					return 1;
				}
			}
			return 0;
		}

		/**
		 * Trigger events 9005
		 *
		 * @param object $oldpost - Old product object.
		 * @param object $newpost - New product object.
		 * @return int
		 *
		 * @since 4.6.0
		 */
		public static function check_text_change( $oldpost, $newpost ) {
			if ( 'auto-draft' === $oldpost->post_status ) {
				return 0;
			}
			if ( $oldpost->post_content !== $newpost->post_content ) {
				$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $oldpost );
				Alert_Manager::trigger_event(
					9005,
					array(
						'PostID'             => esc_attr( $oldpost->ID ),
						'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $oldpost->ID ) ),
						'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
						'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
						'PostStatus'         => sanitize_text_field( $oldpost->post_status ),
						$editor_link['name'] => $editor_link['value'],
					)
				);
				return 1;
			}
			return 0;
		}

		/**
		 * Trigger events 9006
		 *
		 * @param string $old_link - Old product link.
		 * @param string $new_link - New product link.
		 * @param object $post     - Product object.
		 *
		 * @return int
		 *
		 * @since 4.6.0
		 */
		public static function check_permalink_change( $old_link, $new_link, $post ) {
			if ( ! empty( $old_link ) && $old_link && $new_link && ( $old_link !== $new_link ) ) {
				$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $post );
				Alert_Manager::trigger_event(
					9006,
					array(
						'PostID'             => esc_attr( $post->ID ),
						'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $post->ID ) ),
						'ProductTitle'       => sanitize_text_field( $post->post_title ),
						'ProductStatus'      => sanitize_text_field( $post->post_status ),
						'PostStatus'         => sanitize_text_field( $post->post_status ),
						'OldUrl'             => $old_link,
						'NewUrl'             => $new_link,
						$editor_link['name'] => $editor_link['value'],
						'ReportText'         => '"' . $old_link . '"|"' . $new_link . '"',
					)
				);
				return 1;
			}
			return 0;
		}

		/**
		 * Trigger events 9007
		 *
		 * @param WP_Post $post - Product object.
		 *
		 * @return int
		 *
		 * @since 4.6.0
		 */
		public static function check_product_type_change( $post ) {
			$old_type = isset( WooCommerce_Sensor::get_old_data()['type'] ) ? WooCommerce_Sensor::get_old_data()['type'] : false;
			$new_type = isset( WooCommerce_Sensor::get_new_data()['type'] ) ? WooCommerce_Sensor::get_new_data()['type'] : false;

			if ( $old_type !== $new_type ) {
				$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $post );
				Alert_Manager::trigger_event(
					9007,
					array(
						'PostID'             => esc_attr( $post->ID ),
						'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $post->ID ) ),
						'ProductTitle'       => sanitize_text_field( $post->post_title ),
						'ProductStatus'      => sanitize_text_field( $post->post_status ),
						'PostStatus'         => sanitize_text_field( $post->post_status ),
						'OldType'            => $old_type,
						'NewType'            => $new_type,
						$editor_link['name'] => $editor_link['value'],
					)
				);
				return 1;
			}
			return 0;
		}

		/**
		 * Trigger events 9008
		 *
		 * @param object $oldpost - Old product object.
		 * @param object $newpost - New product object.
		 * @return int
		 *
		 * @since 4.6.0
		 */
		public static function check_date_change( $oldpost, $newpost ) {
			if ( 'draft' === $oldpost->post_status || 'auto-draft' === $oldpost->post_status ) {
				return 0;
			}

			$from = strtotime( $oldpost->post_date );
			$to   = strtotime( $newpost->post_date );

			if ( $from !== $to ) {
				if ( 'shop_coupon' === $newpost->post_type ) {
					$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $oldpost );
					Alert_Manager::trigger_event(
						9126,
						array(
							'CouponID'           => esc_attr( $oldpost->ID ),
							'CouponName'         => sanitize_text_field( $oldpost->post_title ),
							'CouponStatus'       => sanitize_text_field( $oldpost->post_status ),
							'PostStatus'         => sanitize_text_field( $oldpost->post_status ),
							'OldDate'            => $oldpost->post_date,
							'NewDate'            => $newpost->post_date,
							$editor_link['name'] => $editor_link['value'],
						)
					);
					return 1;
				} else {
					$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $oldpost );
					Alert_Manager::trigger_event(
						9008,
						array(
							'PostID'             => esc_attr( $oldpost->ID ),
							'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $oldpost->ID ) ),
							'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
							'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
							'PostStatus'         => sanitize_text_field( $oldpost->post_status ),
							'OldDate'            => $oldpost->post_date,
							'NewDate'            => $newpost->post_date,
							$editor_link['name'] => $editor_link['value'],
						)
					);
					return 1;
				}
			}
			return 0;
		}

		/**
		 * Trigger events 9009
		 *
		 * @param WP_Post $oldpost - Old product object.
		 * @param WP_Post $newpost - New product object.
		 * @return int
		 *
		 * @since 4.6.0
		 */
		public static function check_visibility_change( $oldpost, $newpost ) {
			if ( 'draft' === WooCommerce_Sensor::get_old_status() || 'draft' === $newpost->post_status ) {
				return;
			}

			$old_visibility = '';
			$new_visibility = '';

			if ( $oldpost->post_password ) {
				$old_visibility = __( 'Password Protected', 'wp-security-audit-log' );
			} elseif ( 'publish' === WooCommerce_Sensor::get_old_status() ) {
				$old_visibility = __( 'Public', 'wp-security-audit-log' );
			} elseif ( 'private' === WooCommerce_Sensor::get_old_status() ) {
				$old_visibility = __( 'Private', 'wp-security-audit-log' );
			}

			if ( $newpost->post_password ) {
				$new_visibility = __( 'Password Protected', 'wp-security-audit-log' );
			} elseif ( 'publish' === $newpost->post_status ) {
				$new_visibility = __( 'Public', 'wp-security-audit-log' );
			} elseif ( 'private' === $newpost->post_status ) {
				$new_visibility = __( 'Private', 'wp-security-audit-log' );
			}

			if ( $old_visibility && $new_visibility && ( $old_visibility !== $new_visibility ) ) {
				if ( 'shop_coupon' === $newpost->post_type ) {
					$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $oldpost );
					Alert_Manager::trigger_event(
						9125,
						array(
							'CouponID'           => esc_attr( $oldpost->ID ),
							'CouponCode'         => sanitize_text_field( $oldpost->post_title ),
							'CouponStatus'       => sanitize_text_field( $oldpost->post_status ),
							'PostStatus'         => sanitize_text_field( $oldpost->post_status ),
							'OldVisibility'      => $old_visibility,
							'NewVisibility'      => $new_visibility,
							$editor_link['name'] => $editor_link['value'],
						)
					);
					return 1;
				} else {
					$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $oldpost );
					Alert_Manager::trigger_event(
						9009,
						array(
							'PostID'             => esc_attr( $oldpost->ID ),
							'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $oldpost->ID ) ),
							'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
							'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
							'PostStatus'         => sanitize_text_field( $oldpost->post_status ),
							'OldVisibility'      => $old_visibility,
							'NewVisibility'      => $new_visibility,
							$editor_link['name'] => $editor_link['value'],
						)
					);
					return 1;
				}
			}
			return 0;
		}

		/**
		 * Check Title Change.
		 *
		 * Trigger event 9071.
		 *
		 * @since 3.3.1
		 *
		 * @param object $oldpost - Old product object.
		 * @param object $newpost - New product object.
		 * @return int
		 */
		public static function check_title_change( $oldpost, $newpost ) {
			if ( 'auto-draft' === $oldpost->post_status ) {
				return 0;
			}

			if ( 'shop_coupon' === $newpost->post_type && $oldpost->post_title !== $newpost->post_title ) {
				// Get coupon event data.
				$coupon_data = WooCommerce_Sensor::get_coupon_event_data( $newpost );

				// Set old and new titles.
				$coupon_data['OldName'] = $oldpost->post_title;
				$coupon_data['NewName'] = $newpost->post_title;

				// Log the event.
				Alert_Manager::trigger_event( 9071, $coupon_data );

				return 1;
			} elseif ( 'product' === $newpost->post_type && $oldpost->post_title !== $newpost->post_title ) {
				// Get editor link.
				$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $newpost );

				// Log the event.
				Alert_Manager::trigger_event(
					9077,
					array(
						'PostID'             => esc_attr( $newpost->ID ),
						'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $newpost->ID ) ),
						'ProductStatus'      => sanitize_text_field( $newpost->post_status ),
						'PostStatus'         => sanitize_text_field( $newpost->post_status ),
						'ProductTitle'       => sanitize_text_field( $newpost->post_title ),
						'OldTitle'           => sanitize_text_field( $oldpost->post_title ),
						'NewTitle'           => sanitize_text_field( $newpost->post_title ),
						'ProductUrl'         => get_permalink( $newpost->ID ),
						$editor_link['name'] => $editor_link['value'],
					)
				);
				return 1;
			}
			return 0;
		}

		/**
		 * Trigger events 9042
		 *
		 * @since 3.3.1
		 *
		 * @param WP_Post $post - Product object.
		 * @return int
		 */
		public static function check_catalog_visibility_change( $post ) {
			// Get product data.
			$old_visibility = isset( WooCommerce_Sensor::get_old_data()['catalog_visibility'] ) ? WooCommerce_Sensor::get_old_data()['catalog_visibility'] : false;
			$new_visibility = isset( WooCommerce_Sensor::get_new_data()['catalog_visibility'] ) ? WooCommerce_Sensor::get_new_data()['catalog_visibility'] : false;

			// Get WooCommerce visibility options.
			$wc_visibilities = wc_get_product_visibility_options();

			if ( ( $old_visibility && $new_visibility ) && ( $old_visibility !== $new_visibility ) ) {
				$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $post );
				Alert_Manager::trigger_event(
					9042,
					array(
						'PostID'             => esc_attr( $post->ID ),
						'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $post->ID ) ),
						'ProductTitle'       => sanitize_text_field( $post->post_title ),
						'ProductStatus'      => sanitize_text_field( $post->post_status ),
						'PostStatus'         => sanitize_text_field( $post->post_status ),
						'OldVisibility'      => isset( $wc_visibilities[ $old_visibility ] ) ? $wc_visibilities[ $old_visibility ] : false,
						'NewVisibility'      => isset( $wc_visibilities[ $new_visibility ] ) ? $wc_visibilities[ $new_visibility ] : false,
						$editor_link['name'] => $editor_link['value'],
					)
				);
				return 1;
			}
			return 0;
		}

		/**
		 * Trigger events 9043
		 *
		 * @since 3.3.1
		 *
		 * @param WP_Post $post - Product object.
		 * @return int
		 */
		public static function check_featured_product( $post ) {
			$old_featured = isset( WooCommerce_Sensor::get_old_data()['featured'] ) ? WooCommerce_Sensor::get_old_data()['featured'] : false;
			$new_featured = isset( WooCommerce_Sensor::get_new_data()['featured'] ) ? WooCommerce_Sensor::get_new_data()['featured'] : false;

			if ( $old_featured !== $new_featured ) {
				$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $post );
				Alert_Manager::trigger_event(
					9043,
					array(
						'PostID'             => esc_attr( $post->ID ),
						'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $post->ID ) ),
						'ProductTitle'       => sanitize_text_field( $post->post_title ),
						'ProductStatus'      => sanitize_text_field( $post->post_status ),
						'PostStatus'         => sanitize_text_field( $post->post_status ),
						'EventType'          => $new_featured ? 'enabled' : 'disabled',
						$editor_link['name'] => $editor_link['value'],
					)
				);
				return 1;
			}
			return 0;
		}

		/**
		 * Trigger events 9044
		 *
		 * @since 3.3.1
		 *
		 * @param object $oldpost       - Old product object.
		 * @param string $old_backorder - Old backorder value.
		 * @param string $new_backorder - New backorder value.
		 * @return int
		 */
		public static function check_backorders_setting( $oldpost, $old_backorder = '', $new_backorder = '' ) {
			// Get product data.
			if ( '' === $old_backorder ) {
				$old_backorder = isset( WooCommerce_Sensor::get_old_data()['backorders'] ) ? WooCommerce_Sensor::get_old_data()['backorders'] : false;
			}
			if ( '' === $new_backorder ) {
				$new_backorder = isset( WooCommerce_Sensor::get_new_data()['backorders'] ) ? WooCommerce_Sensor::get_new_data()['backorders'] : false;
			}

			if ( $old_backorder !== $new_backorder ) {
				$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $oldpost );
				Alert_Manager::trigger_event(
					9044,
					array(
						'PostID'             => esc_attr( $oldpost->ID ),
						'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $oldpost->ID ) ),
						'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
						'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
						'PostStatus'         => sanitize_text_field( $oldpost->post_status ),
						'OldStatus'          => $old_backorder,
						'NewStatus'          => $new_backorder,
						$editor_link['name'] => $editor_link['value'],
					)
				);
				return 1;
			}
			return 0;
		}

		/**
		 * Trigger events 9045
		 *
		 * @since 3.3.1
		 *
		 * @param object $oldpost - Old product object.
		 * @return int
		 */
		public static function check_upsells_change( $oldpost ) {
			// Get product data.
			$old_upsell_ids = isset( WooCommerce_Sensor::get_old_data()['upsell_ids'] ) ? WooCommerce_Sensor::get_old_data()['upsell_ids'] : false;
			$new_upsell_ids = isset( WooCommerce_Sensor::get_new_data()['upsell_ids'] ) ? WooCommerce_Sensor::get_new_data()['upsell_ids'] : false;

			// Compute the difference.
			$added_upsells   = array();
			$removed_upsells = array();

			if ( is_array( $new_upsell_ids ) && is_array( $old_upsell_ids ) ) {
				$added_upsells   = array_diff( $new_upsell_ids, $old_upsell_ids );
				$removed_upsells = array_diff( $old_upsell_ids, $new_upsell_ids );
			}

			// Get editor link.
			$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $oldpost );

			// Return.
			$return = 0;

			// Added upsell products.
			if ( ! empty( $added_upsells ) && is_array( $added_upsells ) ) {
				foreach ( $added_upsells as $added_upsell ) {
					$upsell_title = get_the_title( $added_upsell );
					Alert_Manager::trigger_event(
						9045,
						array(
							'EventType'          => 'added',
							'PostID'             => esc_attr( $oldpost->ID ),
							'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $oldpost->ID ) ),
							'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
							'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
							'PostStatus'         => sanitize_text_field( $oldpost->post_status ),
							'UpsellTitle'        => sanitize_text_field( $upsell_title ),
							'UpsellID'           => sanitize_text_field( $added_upsell ),
							$editor_link['name'] => $editor_link['value'],
						)
					);
				}
				$return = 1;
			}

			// Removed upsell products.
			if ( ! empty( $removed_upsells ) && is_array( $removed_upsells ) ) {
				foreach ( $removed_upsells as $removed_upsell ) {
					$upsell_title = get_the_title( $removed_upsell );
					Alert_Manager::trigger_event(
						9045,
						array(
							'EventType'          => 'removed',
							'PostID'             => esc_attr( $oldpost->ID ),
							'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
							'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
							'PostStatus'         => sanitize_text_field( $oldpost->post_status ),
							'UpsellTitle'        => sanitize_text_field( $upsell_title ),
							'UpsellID'           => sanitize_text_field( $removed_upsell ),
							$editor_link['name'] => $editor_link['value'],
						)
					);
				}
				$return = 1;
			}
			return $return;
		}

		/**
		 * Trigger events 9046
		 *
		 * @since 3.3.1
		 *
		 * @param object $oldpost - Old product object.
		 * @return int
		 */
		public static function check_cross_sell_change( $oldpost ) {
			// Get product data.
			$old_cross_sell_ids = isset( WooCommerce_Sensor::get_old_data()['cross_sell_ids'] ) ? WooCommerce_Sensor::get_old_data()['cross_sell_ids'] : false;
			$new_cross_sell_ids = isset( WooCommerce_Sensor::get_new_data()['cross_sell_ids'] ) ? WooCommerce_Sensor::get_new_data()['cross_sell_ids'] : false;

			// Compute the difference.
			$added_cross_sells   = array();
			$removed_cross_sells = array();
			if ( is_array( $new_cross_sell_ids ) && is_array( $old_cross_sell_ids ) ) {
				$added_cross_sells   = array_diff( $new_cross_sell_ids, $old_cross_sell_ids );
				$removed_cross_sells = array_diff( $old_cross_sell_ids, $new_cross_sell_ids );
			}

			// Get editor link.
			$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $oldpost );

			// Return.
			$return = 0;

			// Added cross-sell products.
			if ( ! empty( $added_cross_sells ) && is_array( $added_cross_sells ) ) {
				foreach ( $added_cross_sells as $added_cross_sell ) {
					$cross_sell_title = get_the_title( $added_cross_sell );
					Alert_Manager::trigger_event(
						9046,
						array(
							'EventType'          => 'added',
							'PostID'             => esc_attr( $oldpost->ID ),
							'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $oldpost->ID ) ),
							'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
							'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
							'PostStatus'         => sanitize_text_field( $oldpost->post_status ),
							'CrossSellTitle'     => sanitize_text_field( $cross_sell_title ),
							'CrossSellID'        => sanitize_text_field( $added_cross_sell ),
							$editor_link['name'] => $editor_link['value'],
						)
					);
				}
				$return = 1;
			}

			// Removed cross-sell products.
			if ( ! empty( $removed_cross_sells ) && is_array( $removed_cross_sells ) ) {
				foreach ( $removed_cross_sells as $removed_cross_sell ) {
					$cross_sell_title = get_the_title( $removed_cross_sell );
					Alert_Manager::trigger_event(
						9046,
						array(
							'EventType'          => 'removed',
							'PostID'             => esc_attr( $oldpost->ID ),
							'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
							'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
							'PostStatus'         => sanitize_text_field( $oldpost->post_status ),
							'CrossSellTitle'     => sanitize_text_field( $cross_sell_title ),
							'CrossSellID'        => sanitize_text_field( $removed_cross_sell ),
							$editor_link['name'] => $editor_link['value'],
						)
					);
				}
				$return = 1;
			}
			return $return;
		}

		/**
		 * Trigger events 9010.
		 *
		 * @param object $oldpost - Old product object.
		 * @param object $newpost - New product object.
		 * @return int
		 *
		 * @since 4.6.0
		 */
		public static function check_modify_change( $oldpost, $newpost ) {
			if ( 'trash' === $oldpost->post_status || 'trash' === $newpost->post_status || 'auto-draft' === $oldpost->post_status ) {
				return 0;
			}

			// If the only change by this point is the "post_modified" time, then we really dont want
			// to trigger this event.
			$check_for_changes = array_diff( (array) $oldpost, (array) $newpost );
			if ( ! empty( $check_for_changes ) && array_key_exists( 'post_modified', $check_for_changes ) ) {
				return 0;
			}

			// Get Yoast alerts.
			$yoast_alerts         = Alert_Manager::get_alerts_by_category( 'Yoast SEO' );
			$yoast_metabox_alerts = Alert_Manager::get_alerts_by_category( 'Yoast SEO Meta Box' );
			$yoast_alerts         = $yoast_alerts + $yoast_metabox_alerts;

			// Check all alerts.
			foreach ( $yoast_alerts as $alert_code => $alert ) {
				if ( Alert_Manager::will_or_has_triggered( $alert_code ) ) {
					return 0; // Return if any Yoast alert has or will trigger.
				}
			}

			$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $oldpost );
			Alert_Manager::trigger_event_if(
				9010,
				array(
					'PostID'             => esc_attr( $oldpost->ID ),
					'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $oldpost->ID ) ),
					'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
					'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
					'PostStatus'         => sanitize_text_field( $oldpost->post_status ),
					'ProductUrl'         => get_post_permalink( $oldpost->ID ),
					$editor_link['name'] => $editor_link['value'],
				),
				array( __CLASS__, 'do_not_detect_variation_changes_as_product_modified' )
			);
		}

		/**
		 * Ensure 9010 does not fire for variable product changes.
		 *
		 * @return bool - Was triggered.
		 *
		 * @since 4.6.0
		 */
		public static function do_not_detect_variation_changes_as_product_modified() {
			if ( Alert_Manager::will_or_has_triggered( 9016 ) || Alert_Manager::will_or_has_triggered( 9017 ) ) {
				return false;
			}
			return true;
		}
		/**
		 * Moved to Trash 9012, 9037.
		 *
		 * @param int $post_id - Product/Order ID.
		 *
		 * @since 4.6.0
		 */
		public static function event_trashed( $post_id ) {
			$post = wc_get_order( $post_id );
			if ( false === $post ) {
				$post = get_post( $post_id );
			}
			if ( empty( $post ) || ! $post instanceof \WP_Post ) {
				if ( ! is_a( $post, '\Automattic\WooCommerce\Admin\Overrides\Order' ) ) {
					return;
				}
			}

			if ( $post instanceof \WP_Post && 'product' === $post->post_type ) {
				Alert_Manager::trigger_event(
					9012,
					array(
						'PostID'        => esc_attr( $post->ID ),
						'SKU'           => esc_attr( WooCommerce_Sensor::get_product_sku( $post->ID ) ),
						'ProductTitle'  => sanitize_text_field( $post->post_title ),
						'ProductStatus' => sanitize_text_field( $post->post_status ),
						'PostStatus'    => sanitize_text_field( $post->post_status ),
						'ProductUrl'    => get_post_permalink( $post->ID ),
					)
				);
			} elseif ( is_a( $post, '\Automattic\WooCommerce\Admin\Overrides\Order' ) ) {
				$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $post );
				Alert_Manager::trigger_event(
					9037,
					array(
						'OrderID'            => esc_attr( $post->get_id() ),
						'OrderTitle'         => sanitize_text_field( Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $post->get_id() ) ),
						'OrderStatus'        => \wc_get_order_status_name( $post->get_status() ),
						'OrderStatusSlug'    => $post->get_status(),
						'PostStatus'         => 'wc-' . $post->get_status(),
						$editor_link['name'] => $editor_link['value'],
					)
				);
			} elseif ( 'shop_order' === $post->post_type ) {
				$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $post );
				Alert_Manager::trigger_event(
					9037,
					array(
						'OrderID'            => esc_attr( $post->ID ),
						'OrderTitle'         => sanitize_text_field( Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $post->ID ) ),
						'OrderStatus'        => \wc_get_order_status_name( $post->post_status ),
						'OrderStatusSlug'    => sanitize_text_field( $post->post_status ),
						'PostStatus'         => 'wc-' . $post->get_status(),
						$editor_link['name'] => $editor_link['value'],
					)
				);
			} elseif ( 'shop_coupon' === $post->post_type ) {
				$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $post );
				Alert_Manager::trigger_event(
					9123,
					array(
						'CouponID'           => esc_attr( $post->ID ),
						'CouponName'         => sanitize_text_field( $post->post_title ),
						'CouponStatus'       => sanitize_text_field( $post->post_status ),
						'PostStatus'         => sanitize_text_field( $post->post_status ),
						$editor_link['name'] => $editor_link['value'],
					)
				);
			}
		}

		/**
		 * Permanently deleted 9013 or 9039.
		 *
		 * @param int $post_id - Product/Order ID.
		 *
		 * @since 4.6.0
		 */
		public static function event_deleted( $post_id ) {
			$post = wc_get_order( $post_id );
			if ( false === $post ) {
				$post = get_post( $post_id );
			}
			if ( empty( $post ) || ! $post instanceof \WP_Post ) {
				if ( ! is_a( $post, '\Automattic\WooCommerce\Admin\Overrides\Order' ) ) {
					return;
				}
			}

			if ( $post instanceof \WP_Post && 'product' === $post->post_type ) {
				Alert_Manager::trigger_event(
					9013,
					array(
						'PostID'       => esc_attr( $post->ID ),
						'SKU'          => esc_attr( WooCommerce_Sensor::get_product_sku( $post->ID ) ),
						'ProductTitle' => sanitize_text_field( $post->post_title ),
					)
				);
			} elseif ( is_a( $post, '\Automattic\WooCommerce\Admin\Overrides\Order' ) ) {
				Alert_Manager::trigger_event(
					9039,
					array( 'OrderTitle' => sanitize_text_field( Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $post_id ) ) )
				);
			} elseif ( 'shop_order' === $post->post_type ) {
				Alert_Manager::trigger_event(
					9039,
					array( 'OrderTitle' => sanitize_text_field( Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $post_id ) ) )
				);
			} elseif ( 'shop_coupon' === $post->post_type ) {
				Alert_Manager::trigger_event(
					9124,
					array(
						'CouponID'   => esc_attr( $post->ID ),
						'CouponCode' => sanitize_text_field( $post->post_title ),
					)
				);
			}
		}

		/**
		 * Restored from Trash 9014
		 *
		 * @param int $post_id - Product ID.
		 *
		 * @since 4.6.0
		 */
		public static function event_untrashed( $post_id ) {
			$post = wc_get_order( $post_id );
			if ( false === $post ) {
				$post = get_post( $post_id );
			}
			if ( empty( $post ) || ! $post instanceof \WP_Post ) {
				if ( ! is_a( $post, '\Automattic\WooCommerce\Admin\Overrides\Order' ) ) {
					return;
				}
			}

			if ( $post instanceof \WP_Post && 'product' === $post->post_type ) {
				$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $post );
				Alert_Manager::trigger_event(
					9014,
					array(
						'PostID'             => esc_attr( $post->ID ),
						'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $post->ID ) ),
						'ProductTitle'       => sanitize_text_field( $post->post_title ),
						'ProductStatus'      => sanitize_text_field( $post->post_status ),
						'PostStatus'         => sanitize_text_field( $post->post_status ),
						$editor_link['name'] => $editor_link['value'],
					)
				);
			} elseif ( is_a( $post, '\Automattic\WooCommerce\Admin\Overrides\Order' ) ) {
				$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $post );
				Alert_Manager::trigger_event(
					9038,
					array(
						'OrderID'            => esc_attr( $post->get_id() ),
						'OrderTitle'         => sanitize_text_field( Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $post_id ) ),
						'OrderStatus'        => \wc_get_order_status_name( $post->get_status() ),
						'OrderStatusSlug'    => $post->get_status(),
						'PostStatus'         => 'wc-' . $post->get_status(),
						$editor_link['name'] => $editor_link['value'],
					)
				);
			} elseif ( 'shop_order' === $post->post_type ) {
				$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $post );
				Alert_Manager::trigger_event(
					9038,
					array(
						'OrderID'            => esc_attr( $post->ID ),
						'OrderTitle'         => sanitize_text_field( Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $post_id ) ),
						'OrderStatus'        => \wc_get_order_status_name( $post->post_status ),
						'OrderStatusSlug'    => sanitize_text_field( $post->post_status ),
						'PostStatus'         => $post->post_status,
						$editor_link['name'] => $editor_link['value'],
					)
				);
			} elseif ( 'shop_coupon' === $post->post_type ) {
				$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $post );
				Alert_Manager::trigger_event(
					9127,
					array(
						'CouponID'           => esc_attr( $post->ID ),
						'CouponCode'         => sanitize_text_field( $post->post_title ),
						'CouponStatus'       => sanitize_text_field( $post->post_status ),
						'PostStatus'         => sanitize_text_field( $post->post_status ),
						$editor_link['name'] => $editor_link['value'],
					)
				);
			}
		}

		/**
		 * Viewing Product Event.
		 *
		 * Alerts for viewing of product post type for WooCommerce.
		 *
		 * @since 4.6.0
		 */
		public static function viewing_product() {

			// Retrieve the current post object.
			$product = get_queried_object();

			// Check product post type.
			if ( ! empty( $product ) && $product instanceof \WP_Post && 'product' !== $product->post_type || ! empty( $product ) && ! isset( $product->post_status ) || ! function_exists( 'wc_get_product' ) ) {
				return $product;
			}

			if ( is_user_logged_in() && ! is_admin() ) {
				$current_path = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : false;
				if (
					! empty( $_SERVER['HTTP_REFERER'] )
					&& ! empty( $current_path )
					&& false !== strpos( sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ), $current_path )
				) {
					// Ignore this if we were on the same page so we avoid double audit entries.
					return;
				}

				if ( ! empty( $product->post_title ) ) {
					$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $product );
					Alert_Manager::trigger_event(
						9073,
						array(
							'PostID'             => esc_attr( $product->ID ),
							'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $product->ID ) ),
							'PostType'           => 'post', // Set to post to allow event to trigger (products are usually ignored).
							'ProductStatus'      => sanitize_text_field( $product->post_status ),
							'PostStatus'         => sanitize_text_field( $product->post_status ),
							'ProductTitle'       => sanitize_text_field( $product->post_title ),
							'ProductUrl'         => get_permalink( $product->ID ),
							$editor_link['name'] => $editor_link['value'],
						)
					);
				}
			}
		}

		/**
		 * Trigger events 9015
		 *
		 * @param object $oldpost - Old product object.
		 * @param object $newpost - New product object.
		 * @return int
		 *
		 * @since 4.6.0
		 */
		public static function check_status_change( $oldpost, $newpost ) {
			if ( 'draft' === $oldpost->post_status || 'auto-draft' === $oldpost->post_status ) {
				return 0;
			}
			if ( $oldpost->post_status !== $newpost->post_status ) {
				if ( 'trash' !== $oldpost->post_status && 'trash' !== $newpost->post_status ) {
					if ( 'product' === $newpost->post_type ) {
						$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $oldpost );
						Alert_Manager::trigger_event(
							9015,
							array(
								'PostID'             => esc_attr( $oldpost->ID ),
								'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $oldpost->ID ) ),
								'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
								'OldStatus'          => sanitize_text_field( $oldpost->post_status ),
								'NewStatus'          => sanitize_text_field( $newpost->post_status ),
								$editor_link['name'] => $editor_link['value'],
							)
						);
						return 1;
					}
					if ( 'shop_coupon' === $newpost->post_type ) {
						// Get coupon data.
						$coupon_data = WooCommerce_Sensor::get_coupon_event_data( $newpost );
						// Set status event data.
						$coupon_data['OldStatus'] = sanitize_text_field( $oldpost->post_status );
						$coupon_data['NewStatus'] = sanitize_text_field( $newpost->post_status );
						// Log the event.
						Alert_Manager::trigger_event( 9070, $coupon_data );
						return 1;
					}
				}
			}
			return 0;
		}

		/**
		 * Trigger events 9016
		 *
		 * @param WP_Post $post - Product object.
		 * @return int
		 *
		 * @since 4.6.0
		 */
		public static function check_price_change( $post ) {
			$result         = 0;
			$old_price      = isset( WooCommerce_Sensor::get_old_data()['regular_price'] ) ? WooCommerce_Sensor::get_old_data()['regular_price'] : false;
			$old_sale_price = isset( WooCommerce_Sensor::get_old_data()['sale_price'] ) ? WooCommerce_Sensor::get_old_data()['sale_price'] : false;
			$new_price      = isset( WooCommerce_Sensor::get_new_data()['regular_price'] ) ? WooCommerce_Sensor::get_new_data()['regular_price'] : false;
			$new_sale_price = isset( WooCommerce_Sensor::get_new_data()['sale_price'] ) ? WooCommerce_Sensor::get_new_data()['sale_price'] : false;

			if ( ( $new_price ) && ( $old_price !== $new_price ) ) {
				$result = self::event_price( $post, 'Regular price', $old_price, $new_price );
			}
			if ( ( false !== $new_sale_price ) && ( $old_sale_price !== $new_sale_price ) ) {
				$result = self::event_price( $post, 'Sale price', $old_sale_price, $new_sale_price );
			}
			return $result;
		}

		/**
		 * Group the Price changes in one function
		 *
		 * @param object $post      - Old Product Object.
		 * @param string $type      - Price Type.
		 * @param int    $old_price - Old Product Price.
		 * @param int    $new_price - New Product Price.
		 * @return int
		 *
		 * @since 4.6.0
		 */
		public static function event_price( $post, $type, $old_price, $new_price ) {
			$currency    = WooCommerce_Sensor_Helper_Second::get_currency_symbol( WooCommerce_Sensor_Helper_Second::get_config( 'currency' ) );
			$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $post );

			if ( empty( self::$last_9016_type ) || ! in_array( $type, self::$last_9016_type, true ) ) {
				// WC does not like data being accessed directly.
				$post_id = method_exists( $post, 'get_id' ) ? $post->get_id() : $post->ID;
				Alert_Manager::trigger_event(
					9016,
					array(
						'PostID'             => $post_id,
						'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $post_id ) ),
						'ProductTitle'       => sanitize_text_field( $post->post_title ),
						'ProductStatus'      => sanitize_text_field( $post->post_status ),
						'PostStatus'         => sanitize_text_field( $post->post_status ),
						'PriceType'          => $type,
						'OldPrice'           => ! empty( $old_price ) ? $currency . $old_price : 0,
						'NewPrice'           => ( '' === $new_price ) ? 0 : $currency . $new_price,
						$editor_link['name'] => $editor_link['value'],
					)
				);
				array_push( self::$last_9016_type, $type );
				return 1;
			}
		}

		/**
		 * Trigger events 9017
		 *
		 * @param object $oldpost - Old product object.
		 * @param string $old_sku - Old SKU.
		 * @param string $new_sku - New SKU.
		 * @return int
		 *
		 * @since 4.6.0
		 */
		public static function check_sku_change( $oldpost, $old_sku = '', $new_sku = '' ) {
			if ( '' === $old_sku && '' === $new_sku ) {
				$old_sku = isset( WooCommerce_Sensor::get_old_data()['sku'] ) ? WooCommerce_Sensor::get_old_data()['sku'] : false;
				$new_sku = isset( WooCommerce_Sensor::get_new_data()['sku'] ) ? WooCommerce_Sensor::get_new_data()['sku'] : false;
			}

			if ( $new_sku && ( $old_sku !== $new_sku ) ) {
				$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $oldpost );
				Alert_Manager::trigger_event(
					9017,
					array(
						'PostID'             => esc_attr( $oldpost->ID ),
						'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $oldpost->ID ) ),
						'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
						'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
						'PostStatus'         => sanitize_text_field( $oldpost->post_status ),
						'OldSku'             => ! empty( $old_sku ) ? $old_sku : 0,
						'NewSku'             => $new_sku,
						$editor_link['name'] => $editor_link['value'],
					)
				);
				return 1;
			}
			return 0;
		}

		/**
		 * Trigger events 9018
		 *
		 * @param object $oldpost    - Old product object.
		 * @param string $old_status - Old status.
		 * @param string $new_status - New status.
		 * @return int
		 *
		 * @since 4.6.0
		 */
		public static function check_stock_status_change( $oldpost, $old_status = '', $new_status = '' ) {
			if ( '' === $old_status && '' === $new_status ) {
				$old_status = isset( WooCommerce_Sensor::get_old_data()['stock_status'] ) ? WooCommerce_Sensor::get_old_data()['stock_status'] : false;
				$new_status = isset( WooCommerce_Sensor::get_new_data()['stock_status'] ) ? WooCommerce_Sensor::get_new_data()['stock_status'] : false;
			}

			if ( ( $old_status && $new_status ) && ( $old_status !== $new_status ) ) {
				$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $oldpost );
				Alert_Manager::trigger_event(
					9018,
					array(
						'PostID'             => esc_attr( $oldpost->ID ),
						'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $oldpost->ID ) ),
						'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
						'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
						'PostStatus'         => sanitize_text_field( $oldpost->post_status ),
						'OldStatus'          => sanitize_text_field( self::get_stock_status_name( $old_status ) ),
						'NewStatus'          => sanitize_text_field( self::get_stock_status_name( $new_status ) ),
						$editor_link['name'] => $editor_link['value'],
					)
				);
				return 1;
			}
			return 0;
		}

		/**
		 * Trigger events 9157, 9158
		 *
		 * @param object $oldproduct    - Old product object.
		 *
		 * @return int
		 *
		 * @since 5.3.0
		 */
		public static function check_sold_individualy_change( $oldproduct ) {
			$old_product_data = WooCommerce_Sensor_Helper_Second::get_product_data( $oldproduct );
			$old_status       = $old_product_data['sold_individually'];
			$new_status       = WooCommerce_Sensor::get_new_data()['sold_individually'];

			if ( ( isset( $old_status ) && isset( $new_status ) ) && ( $old_status !== $new_status ) ) {
				$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( WooCommerce_Sensor::get_old_post() );
				$data        = array(
					'PostID'             => esc_attr( WooCommerce_Sensor::get_old_post()->ID ),
					'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( WooCommerce_Sensor::get_old_post()->ID ) ),
					'ProductTitle'       => sanitize_text_field( WooCommerce_Sensor::get_old_post()->post_title ),
					'ProductStatus'      => sanitize_text_field( WooCommerce_Sensor::get_old_post()->post_status ),
					'PostStatus'         => sanitize_text_field( WooCommerce_Sensor::get_old_post()->post_status ),
					'OldStatus'          => ( $old_status ) ? __( 'Enabled', 'wp-security-audit-log' ) : __( 'Disabled', 'wp-security-audit-log' ),
					'NewStatus'          => ( $new_status ) ? __( 'Enabled', 'wp-security-audit-log' ) : __( 'Disabled', 'wp-security-audit-log' ),
					$editor_link['name'] => $editor_link['value'],
				);

				$event_id = 9158;
				if ( $new_status ) {
					$event_id = 9157;
				}
				Alert_Manager::trigger_event(
					$event_id,
					$data
				);
				return 1;
			}
			return 0;
		}

		/**
		 * Trigger events 9019
		 *
		 * @param object $oldpost   - Old product object.
		 * @param mixed  $old_value - Old stock quantity.
		 * @param mixed  $new_value - New stock quantity.
		 * @return int
		 *
		 * @since 4.6.0
		 */
		public static function check_stock_quantity_change( $oldpost, $old_value = false, $new_value = false ) {
			if ( false === $old_value && false === $new_value ) {
				if ( WooCommerce_Sensor::get_old_data()['manage_stock'] ) {
					$old_value = isset( WooCommerce_Sensor::get_old_data()['stock_quantity'] ) ? WooCommerce_Sensor::get_old_data()['stock_quantity'] : false;
				} else {
					$old_value = false;
				}
				if ( WooCommerce_Sensor::get_old_data()['manage_stock'] ) {
					$new_value = isset( WooCommerce_Sensor::get_new_data()['stock_quantity'] ) ? WooCommerce_Sensor::get_new_data()['stock_quantity'] : false;
				} else {
					$new_value = false;
				}
			}

			if ( $new_value && ( $old_value !== $new_value ) ) {
				$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $oldpost );
				// WC does not like data being accessed directly.
				$post_id = method_exists( $oldpost, 'get_id' ) ? $oldpost->get_id() : $oldpost->ID;
				Alert_Manager::trigger_event(
					9019,
					array(
						'PostID'             => $post_id,
						'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $post_id ) ),
						'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
						'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
						'PostStatus'         => sanitize_text_field( $oldpost->post_status ),
						'OldValue'           => ! empty( $old_value ) ? $old_value : '0',
						'NewValue'           => $new_value,
						$editor_link['name'] => $editor_link['value'],
					)
				);
				return 1;
			}
			return 0;
		}

		/**
		 * Trigger events 9020
		 *
		 * @param object $oldpost  - Old product object.
		 * @param object $newpost  - New product object.
		 * @param mixed  $virtual  - Product virtual data.
		 * @param mixed  $download - Product downloadable data.
		 * @return int
		 *
		 * @since 4.6.0
		 */
		public static function check_type_change( $oldpost, $newpost = null, $virtual = false, $download = false ) {
			if ( 'trash' === $oldpost->post_status ) {
				return 0;
			}

			if ( $newpost && $newpost instanceof \WP_Post && 'trash' === $newpost->post_status ) {
				return 0;
			}

			// Set initial variables.
			$old_virtual  = false;
			$new_virtual  = false;
			$old_download = false;
			$new_download = false;

			// Get simple product virtual data.
			if ( false === $virtual ) {
				$old_virtual = isset( WooCommerce_Sensor::get_old_data()['virtual'] ) ? WooCommerce_Sensor::get_old_data()['virtual'] : false;
				$new_virtual = isset( WooCommerce_Sensor::get_new_data()['virtual'] ) ? WooCommerce_Sensor::get_new_data()['virtual'] : false;
			} elseif ( is_array( $virtual ) ) {
				$old_virtual = ( isset( $virtual['old'] ) && $virtual['old'] ) ? 'yes' : 'no';
				$new_virtual = ( isset( $virtual['new'] ) && $virtual['new'] ) ? 'yes' : 'no';
			}

			// Get simple product downloadable data.
			if ( false === $download ) {
				$old_download = isset( WooCommerce_Sensor::get_old_data()['downloadable'] ) ? WooCommerce_Sensor::get_old_data()['downloadable'] : false;
				$new_download = isset( WooCommerce_Sensor::get_new_data()['downloadable'] ) ? WooCommerce_Sensor::get_new_data()['downloadable'] : false;
			} elseif ( is_array( $download ) ) {
				$old_download = ( isset( $download['old'] ) && $download['old'] ) ? 'yes' : 'no';
				$new_download = ( isset( $download['new'] ) && $download['new'] ) ? 'yes' : 'no';
			}

			// Return variable.
			$result = 0;

			if ( $old_virtual && $new_virtual && $old_virtual !== $new_virtual ) {
				$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $oldpost );
				// WC does not like data being accessed directly.
				$post_id = method_exists( $oldpost, 'get_id' ) ? $oldpost->get_id() : $oldpost->ID;
				Alert_Manager::trigger_event(
					9020,
					array(
						'PostID'             => $post_id,
						'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $post_id ) ),
						'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
						'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
						'PostStatus'         => sanitize_text_field( $oldpost->post_status ),
						'OldType'            => 'yes' === $old_virtual ? 'Virtual' : 'Non-Virtual',
						'NewType'            => 'yes' === $new_virtual ? 'Virtual' : 'Non-Virtual',
						$editor_link['name'] => $editor_link['value'],
					)
				);
				$result = 1;
			}

			if ( $old_download && $new_download && $old_download !== $new_download ) {
				$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $oldpost );
				// WC does not like data being accessed directly.
				$post_id = method_exists( $oldpost, 'get_id' ) ? $oldpost->get_id() : $oldpost->ID;
				Alert_Manager::trigger_event(
					9020,
					array(
						'PostID'             => esc_attr( $post_id ),
						'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $post_id ) ),
						'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
						'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
						'PostStatus'         => sanitize_text_field( $oldpost->post_status ),
						'OldType'            => ( 'yes' === $old_download ) ? 'Downloadable' : 'Non-Downloadable',
						'NewType'            => ( 'yes' === $new_download ) ? 'Downloadable' : 'Non-Downloadable',
						$editor_link['name'] => $editor_link['value'],
					)
				);
				$result = 1;
			}
			return $result;
		}

		/**
		 * Trigger events 9021
		 *
		 * @param object $oldpost    - Old product object.
		 * @param string $old_weight - (Optional) Old weight.
		 * @param string $new_weight - (Optional) New weight.
		 * @return int
		 *
		 * @since 4.6.0
		 */
		public static function check_weight_change( $oldpost, $old_weight = '', $new_weight = '' ) {
			if ( '' === $old_weight && '' === $new_weight ) {
				$old_weight = isset( WooCommerce_Sensor::get_old_data()['weight'] ) ? WooCommerce_Sensor::get_old_data()['weight'] : false;
				$new_weight = isset( WooCommerce_Sensor::get_new_data()['weight'] ) ? WooCommerce_Sensor::get_new_data()['weight'] : false;
			}

			if ( $new_weight && ( $old_weight !== $new_weight ) ) {
				$weight_unit = WooCommerce_Sensor_Helper_Second::get_config( 'weight_unit' );
				$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $oldpost );
				// WC does not like data being accessed directly.
				$post_id = method_exists( $oldpost, 'get_id' ) ? $oldpost->get_id() : $oldpost->ID;
				Alert_Manager::trigger_event(
					9021,
					array(
						'PostID'             => $post_id,
						'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $post_id ) ),
						'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
						'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
						'PostStatus'         => sanitize_text_field( $oldpost->post_status ),
						'OldWeight'          => ! empty( $old_weight ) ? $old_weight . ' ' . $weight_unit : 0,
						'NewWeight'          => $new_weight . ' ' . $weight_unit,
						$editor_link['name'] => $editor_link['value'],
					)
				);
				return 1;
			}
			return 0;
		}

		/**
		 * Trigger events 9022
		 *
		 * @param object $oldpost - Old product object.
		 * @param mixed  $length  - (Optional) Product lenght.
		 * @param mixed  $width   - (Optional) Product width.
		 * @param mixed  $height  - (Optional) Product height.
		 * @return int
		 *
		 * @since 4.6.0
		 */
		public static function check_dimensions_change( $oldpost, $length = false, $width = false, $height = false ) {
			// Get product dimensions data.
			$result = 0;

			$old_length = false;
			$new_length = false;
			$old_width  = false;
			$new_width  = false;
			$old_height = false;
			$new_height = false;

			// Length.
			if ( false === $length ) {
				$old_length = isset( WooCommerce_Sensor::get_old_data()['length'] ) ? WooCommerce_Sensor::get_old_data()['length'] : false;
				$new_length = isset( WooCommerce_Sensor::get_new_data()['length'] ) ? WooCommerce_Sensor::get_new_data()['length'] : false;
			} elseif ( is_array( $length ) ) {
				$old_length = isset( $length['old'] ) ? $length['old'] : false;
				$new_length = isset( $length['new'] ) ? $length['new'] : false;
			}

			// Width.
			if ( false === $width ) {
				$old_width = isset( WooCommerce_Sensor::get_old_data()['width'] ) ? WooCommerce_Sensor::get_old_data()['width'] : false;
				$new_width = isset( WooCommerce_Sensor::get_new_data()['width'] ) ? WooCommerce_Sensor::get_new_data()['width'] : false;
			} elseif ( is_array( $width ) ) {
				$old_width = isset( $width['old'] ) ? $width['old'] : false;
				$new_width = isset( $width['new'] ) ? $width['new'] : false;
			}

			// Height.
			if ( false === $height ) {
				$old_height = isset( WooCommerce_Sensor::get_old_data()['height'] ) ? WooCommerce_Sensor::get_old_data()['height'] : false;
				$new_height = isset( WooCommerce_Sensor::get_new_data()['height'] ) ? WooCommerce_Sensor::get_new_data()['height'] : false;
			} elseif ( is_array( $height ) ) {
				$old_height = isset( $height['old'] ) ? $height['old'] : false;
				$new_height = isset( $height['new'] ) ? $height['new'] : false;
			}

			if ( $new_length && ( $old_length !== $new_length ) ) {
				$result = self::event_dimension( $oldpost, 'Length', $old_length, $new_length );
			}
			if ( $new_width && ( $old_width !== $new_width ) ) {
				$result = self::event_dimension( $oldpost, 'Width', $old_width, $new_width );
			}
			if ( $new_height && ( $old_height !== $new_height ) ) {
				$result = self::event_dimension( $oldpost, 'Height', $old_height, $new_height );
			}
			return $result;
		}

		/**
		 * Group the Dimension changes in one function.
		 *
		 * @param object $oldpost       - Old Product object.
		 * @param string $type          - Dimension type.
		 * @param string $old_dimension - Old dimension.
		 * @param string $new_dimension - New dimension.
		 * @return int
		 *
		 * @since 4.6.0
		 */
		public static function event_dimension( $oldpost, $type, $old_dimension, $new_dimension ) {
			$dimension_unit = WooCommerce_Sensor_Helper_Second::get_config( 'dimension_unit' );
			$editor_link    = WooCommerce_Sensor_Helper_Second::get_editor_link( $oldpost );
			// WC does not like data being accessed directly.
			$post_id = method_exists( $oldpost, 'get_id' ) ? $oldpost->get_id() : $oldpost->ID;
			Alert_Manager::trigger_event(
				9022,
				array(
					'PostID'             => $post_id,
					'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $post_id ) ),
					'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
					'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
					'PostStatus'         => sanitize_text_field( $oldpost->post_status ),
					'DimensionType'      => $type,
					'OldDimension'       => ! empty( $old_dimension ) ? $old_dimension . ' ' . $dimension_unit : 0,
					'NewDimension'       => $new_dimension . ' ' . $dimension_unit,
					$editor_link['name'] => $editor_link['value'],
				)
			);
			return 1;
		}

		/**
		 * Trigger events 9023, 9024, 9025, 9026
		 *
		 * @param object $oldpost    - Old product object.
		 * @param mixed  $file_names - (Optional) New product file names.
		 * @param mixed  $file_urls  - (Optional) New product file urls.
		 * @return int
		 *
		 * @since 4.6.0
		 */
		public static function check_downloadable_file_change( $oldpost, $file_names = false, $file_urls = false ) {
			// Get product data.
			$result         = 0;
			$is_url_changed = false;
			$editor_link    = WooCommerce_Sensor_Helper_Second::get_editor_link( $oldpost );

			if ( false === $file_names ) {
				$old_file_names = isset( WooCommerce_Sensor::get_old_data()['file_names'] ) ? WooCommerce_Sensor::get_old_data()['file_names'] : array();
				$new_file_names = isset( WooCommerce_Sensor::get_new_data()['file_names'] ) ? WooCommerce_Sensor::get_new_data()['file_names'] : array();
			} else {
				$old_file_names = isset( $file_names['old'] ) ? $file_names['old'] : array();
				$new_file_names = isset( $file_names['new'] ) ? $file_names['new'] : array();
			}

			if ( false === $file_urls ) {
				$old_file_urls = isset( WooCommerce_Sensor::get_old_data()['file_urls'] ) ? WooCommerce_Sensor::get_old_data()['file_urls'] : array();
				$new_file_urls = isset( WooCommerce_Sensor::get_new_data()['file_urls'] ) ? WooCommerce_Sensor::get_new_data()['file_urls'] : array();
			} else {
				$old_file_urls = isset( $file_urls['old'] ) ? $file_urls['old'] : array();
				$new_file_urls = isset( $file_urls['new'] ) ? $file_urls['new'] : array();
			}

			$added_urls   = array_diff( $new_file_urls, $old_file_urls );
			$removed_urls = array_diff( $old_file_urls, $new_file_urls );
			$added_names  = array_diff( $new_file_names, $old_file_names );

			// Added files to the product.
			if ( count( $added_urls ) > 0 ) {
				// If the file has only changed URL.
				if ( count( $new_file_urls ) === count( $old_file_urls ) ) {
					$is_url_changed = true;
				} else {
					// WC does not like data being accessed directly.
					$post_id = method_exists( $oldpost, 'get_id' ) ? $oldpost->get_id() : $oldpost->ID;
					foreach ( $added_urls as $key => $url ) {
						Alert_Manager::trigger_event(
							9023,
							array(
								'PostID'             => $post_id,
								'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $post_id ) ),
								'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
								'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
								'PostStatus'         => sanitize_text_field( $oldpost->post_status ),
								'FileName'           => sanitize_text_field( $new_file_names[ $key ] ),
								'FileUrl'            => $url,
								$editor_link['name'] => $editor_link['value'],
							)
						);
					}
					$result = 1;
				}
			}

			// Removed files from the product.
			if ( count( $removed_urls ) > 0 ) {
				// If the file has only changed URL.
				if ( count( $new_file_urls ) === count( $old_file_urls ) ) {
					$is_url_changed = true;
				} else {
					foreach ( $removed_urls as $key => $url ) {
						Alert_Manager::trigger_event(
							9024,
							array(
								'PostID'             => esc_attr( $oldpost->ID ),
								'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $oldpost->ID ) ),
								'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
								'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
								'PostStatus'         => sanitize_text_field( $oldpost->post_status ),
								'FileName'           => sanitize_text_field( $old_file_names[ $key ] ),
								'FileUrl'            => $url,
								$editor_link['name'] => $editor_link['value'],
							)
						);
					}
					$result = 1;
				}
			}

			if ( count( $added_names ) > 0 ) {
				// If the file has only changed Name.
				if ( count( $new_file_names ) === count( $old_file_names ) ) {
					foreach ( $added_names as $key => $name ) {
						Alert_Manager::trigger_event(
							9025,
							array(
								'PostID'             => esc_attr( $oldpost->ID ),
								'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $oldpost->ID ) ),
								'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
								'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
								'PostStatus'         => sanitize_text_field( $oldpost->post_status ),
								'OldName'            => sanitize_text_field( $old_file_names[ $key ] ),
								'NewName'            => sanitize_text_field( $name ),
								$editor_link['name'] => $editor_link['value'],
							)
						);
					}
					$result = 1;
				}
			}

			if ( $is_url_changed ) {
				foreach ( $added_urls as $key => $url ) {
					Alert_Manager::trigger_event(
						9026,
						array(
							'PostID'             => esc_attr( $oldpost->ID ),
							'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $oldpost->ID ) ),
							'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
							'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
							'PostStatus'         => sanitize_text_field( $oldpost->post_status ),
							'FileName'           => sanitize_text_field( $new_file_names[ $key ] ),
							'OldUrl'             => $removed_urls[ $key ],
							'NewUrl'             => $url,
							$editor_link['name'] => $editor_link['value'],
						)
					);
				}
				$result = 1;
			}
			return $result;
		}

		/**
		 * Trigger events Settings: 9027, 9028, 9029, 9030, 9031, 9032, 9033
		 *
		 * @param string       $option - Option name.
		 * @param string|array $old_value - Previous value.
		 * @param mixed        $value - New value.
		 *
		 * @return void
		 *
		 * @since 4.6.0
		 */
		public static function settings_updated( $option, $old_value, $value ) {

			// Verify WooCommerce settings page nonce.
			if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'woocommerce-settings' ) ) {
				// Verify settings page via $_GET array.
				if ( isset( $_GET['page'] ) && 'wc-settings' === sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) {
					if ( isset( $_GET['tab'] ) && 'products' === sanitize_text_field( wp_unslash( $_GET['tab'] ) ) ) {
						// Check weight unit event.
						if ( isset( $_POST['woocommerce_weight_unit'] ) && 'woocommerce_weight_unit' === $option ) {
							$old_unit = $old_value;
							$new_unit = sanitize_text_field( wp_unslash( $value ) );
							if ( $old_unit !== $new_unit ) {
								Alert_Manager::trigger_event(
									9027,
									array(
										'OldUnit' => sanitize_text_field( $old_unit ),
										'NewUnit' => sanitize_text_field( $new_unit ),
									)
								);
							}
						}

						// Check dimension unit event.
						if ( isset( $_POST['woocommerce_dimension_unit'] ) && 'woocommerce_dimension_unit' === $option ) {
							$old_unit = $old_value;
							$new_unit = sanitize_text_field( wp_unslash( $value ) );
							if ( $old_unit !== $new_unit ) {
								Alert_Manager::trigger_event(
									9028,
									array(
										'OldUnit' => sanitize_text_field( $old_unit ),
										'NewUnit' => sanitize_text_field( $new_unit ),
									)
								);
							}
						}

						// Check dimension unit event.
						if ( 'woocommerce_enable_reviews' === $option ) {
							$old_unit = $old_value;
							$new_unit = sanitize_text_field( wp_unslash( $value ) );
							if ( $old_unit !== $new_unit ) {
								$event_type = ( 'yes' == $value ) ? 'enabled' : 'disabled';
								Alert_Manager::trigger_event(
									9100,
									array(
										'EventType' => $event_type,
									)
								);
							}
						}

						if ( 'woocommerce_review_rating_verification_label' === $option ) {
							$old_unit = $old_value;
							$new_unit = sanitize_text_field( wp_unslash( $value ) );
							if ( $old_unit !== $new_unit ) {
								$event_type = ( 'yes' == $value ) ? 'enabled' : 'disabled';
								Alert_Manager::trigger_event(
									9107,
									array(
										'EventType' => $event_type,
									)
								);
							}
						}

						if ( 'woocommerce_review_rating_verification_required' === $option ) {
							$old_unit = $old_value;
							$new_unit = sanitize_text_field( wp_unslash( $value ) );
							if ( $old_unit !== $new_unit ) {
								$event_type = ( 'yes' == $value ) ? 'enabled' : 'disabled';
								Alert_Manager::trigger_event(
									9108,
									array(
										'EventType' => $event_type,
									)
								);
							}
						}

						if ( 'woocommerce_enable_review_rating' === $option ) {
							$old_unit = $old_value;
							$new_unit = sanitize_text_field( wp_unslash( $value ) );
							if ( $old_unit !== $new_unit ) {
								$event_type = ( 'yes' == $value ) ? 'enabled' : 'disabled';
								Alert_Manager::trigger_event(
									9109,
									array(
										'EventType' => $event_type,
									)
								);
							}
						}

						if ( 'woocommerce_review_rating_required' === $option ) {
							$old_unit = $old_value;
							$new_unit = sanitize_text_field( wp_unslash( $value ) );
							if ( $old_unit !== $new_unit ) {
								$event_type = ( 'yes' == $value ) ? 'enabled' : 'disabled';
								Alert_Manager::trigger_event(
									9110,
									array(
										'EventType' => $event_type,
									)
								);
							}
						}
					} elseif ( isset( $_GET['tab'] ) && 'account' === sanitize_text_field( wp_unslash( $_GET['tab'] ) ) ) {
						// Guest Checkout event.
						if ( 'woocommerce_enable_guest_checkout' === $option ) {
							$old_enable_guest_checkout = $old_value;
							$new_enable_guest_checkout = isset( $_POST['woocommerce_enable_guest_checkout'] ) ? 'yes' : 'no';
							if ( $old_enable_guest_checkout !== $new_enable_guest_checkout ) {
								$status = ( 'yes' === $new_enable_guest_checkout ) ? 'enabled' : 'disabled';
								Alert_Manager::trigger_event( 9033, array( 'EventType' => $status ) );
							}
						}

						if ( 'woocommerce_enable_checkout_login_reminder' === $option ) {
							if ( $old_value !== $value ) {
								Alert_Manager::trigger_event(
									9144,
									array(
										'EventType' => ( 'yes' === $value ) ? 'enabled' : 'disabled',
									)
								);
							}
						}

						if ( 'woocommerce_enable_signup_and_login_from_checkout' === $option ) {
							if ( $old_value !== $value ) {
								Alert_Manager::trigger_event(
									9145,
									array(
										'EventType' => ( 'yes' === $value ) ? 'enabled' : 'disabled',
									)
								);
							}
						}

						if ( 'woocommerce_enable_myaccount_registration' === $option ) {
							if ( $old_value !== $value ) {
								Alert_Manager::trigger_event(
									9146,
									array(
										'EventType' => ( 'yes' === $value ) ? 'enabled' : 'disabled',
									)
								);
							}
						}

						if ( 'woocommerce_registration_generate_username' === $option ) {
							if ( $old_value !== $value ) {
								Alert_Manager::trigger_event(
									9147,
									array(
										'EventType' => ( 'yes' === $value ) ? 'enabled' : 'disabled',
									)
								);
							}
						}

						if ( 'woocommerce_registration_generate_password' === $option ) {
							if ( $old_value !== $value ) {
								Alert_Manager::trigger_event(
									9148,
									array(
										'EventType' => ( 'yes' === $value ) ? 'enabled' : 'disabled',
									)
								);
							}
						}

						if ( 'woocommerce_erasure_request_removes_order_data' === $option ) {
							if ( $old_value !== $value ) {
								Alert_Manager::trigger_event(
									9149,
									array(
										'EventType' => ( 'yes' === $value ) ? 'enabled' : 'disabled',
									)
								);
							}
						}

						if ( 'woocommerce_erasure_request_removes_download_data' === $option ) {
							if ( $old_value !== $value ) {
								Alert_Manager::trigger_event(
									9150,
									array(
										'EventType' => ( 'yes' === $value ) ? 'enabled' : 'disabled',
									)
								);
							}
						}

						if ( 'woocommerce_allow_bulk_remove_personal_data' === $option ) {
							if ( $old_value !== $value ) {
								Alert_Manager::trigger_event(
									9151,
									array(
										'EventType' => ( 'yes' === $value ) ? 'enabled' : 'disabled',
									)
								);
							}
						}

						if ( 'woocommerce_registration_privacy_policy_text' === $option ) {
							if ( $old_value !== $value ) {
								Alert_Manager::trigger_event(
									9152,
									array(
										'old_setting' => $old_value,
										'new_setting' => $value,
									)
								);
							}
						}

						if ( 'woocommerce_checkout_privacy_policy_text' === $option ) {
							if ( $old_value !== $value ) {
								Alert_Manager::trigger_event(
									9153,
									array(
										'old_setting' => $old_value,
										'new_setting' => $value,
									)
								);
							}
						}
					} if ( isset( $_GET['tab'] ) && 'tax' === sanitize_text_field( wp_unslash( $_GET['tab'] ) ) ) {
						// Check prices entered with tax setting.
						if ( isset( $_POST['woocommerce_prices_include_tax'] ) && 'woocommerce_prices_include_tax' === $option ) {
							$old_price_tax = $old_value;
							$new_price_tax = sanitize_text_field( wp_unslash( $_POST['woocommerce_prices_include_tax'] ) );
							if ( $old_price_tax !== $new_price_tax ) {
								Alert_Manager::trigger_event( 9078, array( 'TaxStatus' => 'yes' === $new_price_tax ? 'including' : 'excluding' ) );
							}
						}

						// Check calculate tax based on setting.
						if ( isset( $_POST['woocommerce_tax_based_on'] ) && 'woocommerce_tax_based_on' === $option ) {
							$old_tax_base = $old_value;
							$new_tax_base = sanitize_text_field( wp_unslash( $_POST['woocommerce_tax_based_on'] ) );
							if ( $old_tax_base !== $new_tax_base ) {
								$setting = '';
								if ( 'shipping' === $new_tax_base ) {
									$setting = __( 'Customer shipping address', 'wp-security-audit-log' );
								} elseif ( 'billing' === $new_tax_base ) {
									$setting = __( 'Customer billing address', 'wp-security-audit-log' );
								} elseif ( 'base' === $new_tax_base ) {
									$setting = __( 'Shop base address', 'wp-security-audit-log' );
								} else {
									$setting = __( 'Customer shipping address', 'wp-security-audit-log' );
								}
								Alert_Manager::trigger_event(
									9079,
									array(
										'Setting'    => sanitize_text_field( $setting ),
										'OldTaxBase' => sanitize_text_field( $old_tax_base ),
										'NewTaxBase' => sanitize_text_field( $new_tax_base ),
									)
								);
							}
						}

						// Check shipping tax class setting.
						if ( isset( $_POST['woocommerce_shipping_tax_class'] ) && 'woocommerce_shipping_tax_class' === $option ) {
							$old_tax_class = $old_value;
							$new_tax_class = sanitize_text_field( wp_unslash( $_POST['woocommerce_shipping_tax_class'] ) );
							if ( $old_tax_class !== $new_tax_class ) {
								$setting = '';
								if ( 'inherit' === $new_tax_class ) {
									$setting = __( 'Shipping tax class based on cart items', 'wp-security-audit-log' );
								} elseif ( 'reduced-rate' === $new_tax_class ) {
									$setting = __( 'Reduced rate' );
								} elseif ( 'zero-rate' === $new_tax_class ) {
									$setting = __( 'Zero rate', 'wp-security-audit-log' );
								} elseif ( empty( $new_tax_class ) ) {
									$setting = __( 'Standard', 'wp-security-audit-log' );
								} else {
									$setting = __( 'Shipping tax class based on cart items', 'wp-security-audit-log' );
								}
								Alert_Manager::trigger_event(
									9080,
									array(
										'Setting'     => sanitize_text_field( $setting ),
										'OldTaxClass' => sanitize_text_field( $old_tax_class ),
										'NewTaxClass' => sanitize_text_field( $new_tax_class ),
									)
								);
							}
						}

						// Check rounding of tax setting.
						if ( 'woocommerce_tax_round_at_subtotal' === $option ) {
							$old_tax_round = $old_value;
							$new_tax_round = isset( $_POST['woocommerce_tax_round_at_subtotal'] ) ? 'yes' : 'no';
							if ( $old_tax_round !== $new_tax_round ) {
								Alert_Manager::trigger_event( 9081, array( 'EventType' => 'yes' === $new_tax_round ? 'enabled' : 'disabled' ) );
							}
						}
					} elseif ( empty( $_GET['tab'] ) || 'general' === sanitize_text_field( wp_unslash( $_GET['tab'] ) ) ) {
						// "Enable Coupon" event.
						if ( 'woocommerce_enable_coupons' === $option ) {
							$old_enable_coupons = $old_value;
							$new_enable_coupons = isset( $_POST['woocommerce_enable_coupons'] ) ? 'yes' : 'no';
							if ( $old_enable_coupons !== $new_enable_coupons ) {
								$status = 'yes' === $new_enable_coupons ? 'enabled' : 'disabled';
								Alert_Manager::trigger_event( 9032, array( 'EventType' => $status ) );
							}
						}

						if ( isset( $_POST['woocommerce_store_address'] ) && 'woocommerce_store_address' === $option || isset( $_POST['woocommerce_store_address_2'] ) && 'woocommerce_store_address_2' === $option || isset( $_POST['woocommerce_store_city'] ) && 'woocommerce_store_city' === $option || isset( $_POST['woocommerce_default_country'] ) && 'woocommerce_default_country' === $option || isset( $_POST['woocommerce_store_postcode'] ) && 'woocommerce_store_postcode' === $option ) {
							// Default country event.
							if ( 'woocommerce_store_address' === $option ) {
								WooCommerce_Sensor::set_old_location_data( $old_value . ', ' . WooCommerce_Sensor_Helper_Second::get_config( 'store_address_2' ) . ', ' . WooCommerce_Sensor_Helper_Second::get_config( 'store_city' ) . ', ' . WC()->countries->countries[ strtok( WooCommerce_Sensor_Helper_Second::get_config( 'default_country' ), ':' ) ] . ', ' . WooCommerce_Sensor_Helper_Second::get_config( 'store_postcode' ) );
								WooCommerce_Sensor::set_new_location_data( sanitize_text_field( wp_unslash( $_POST['woocommerce_store_address'] ) ) . ', ' . WooCommerce_Sensor_Helper_Second::get_config( 'store_address_2' ) . ', ' . WooCommerce_Sensor_Helper_Second::get_config( 'store_city' ) . ', ' . WC()->countries->countries[ strtok( WooCommerce_Sensor_Helper_Second::get_config( 'default_country' ), ':' ) ] . ', ' . WooCommerce_Sensor_Helper_Second::get_config( 'store_postcode' ) );
							}
							if ( 'woocommerce_store_address_2' === $option ) {
								WooCommerce_Sensor::set_old_location_data( WooCommerce_Sensor_Helper_Second::get_config( 'store_address' ) . ', ' . $old_value . ', ' . WooCommerce_Sensor_Helper_Second::get_config( 'store_city' ) . ', ' . WC()->countries->countries[ strtok( WooCommerce_Sensor_Helper_Second::get_config( 'default_country' ), ':' ) ] . ', ' . WooCommerce_Sensor_Helper_Second::get_config( 'store_postcode' ) );
								WooCommerce_Sensor::set_new_location_data( WooCommerce_Sensor_Helper_Second::get_config( 'store_address' ) . ', ' . sanitize_text_field( wp_unslash( $_POST['woocommerce_store_address_2'] ) ) . ', ' . WooCommerce_Sensor_Helper_Second::get_config( 'store_city' ) . ', ' . WC()->countries->countries[ strtok( WooCommerce_Sensor_Helper_Second::get_config( 'default_country' ), ':' ) ] . ', ' . WooCommerce_Sensor_Helper_Second::get_config( 'store_postcode' ) );
							}
							if ( 'woocommerce_store_city' === $option ) {
								WooCommerce_Sensor::set_old_location_data( WooCommerce_Sensor_Helper_Second::get_config( 'store_address' ) . ', ' . WooCommerce_Sensor_Helper_Second::get_config( 'store_address_2' ) . ', ' . $old_value . ', ' . WooCommerce_Sensor_Helper_Second::get_config( 'default_country' ) . ', ' . WooCommerce_Sensor_Helper_Second::get_config( 'store_postcode' ) );
								WooCommerce_Sensor::set_new_location_data( WooCommerce_Sensor_Helper_Second::get_config( 'store_address' ) . ', ' . WooCommerce_Sensor_Helper_Second::get_config( 'store_address_2' ) . ', ' . sanitize_text_field( wp_unslash( $_POST['woocommerce_store_city'] ) ) . ', ' . WC()->countries->countries[ strtok( WooCommerce_Sensor_Helper_Second::get_config( 'default_country' ), ':' ) ] . ', ' . WooCommerce_Sensor_Helper_Second::get_config( 'store_postcode' ) );
							}
							if ( 'woocommerce_default_country' === $option ) {
								WooCommerce_Sensor::set_old_location_data( WooCommerce_Sensor_Helper_Second::get_config( 'store_address' ) . ', ' . WooCommerce_Sensor_Helper_Second::get_config( 'store_address_2' ) . ', ' . WooCommerce_Sensor_Helper_Second::get_config( 'store_address' ) . ', ' . WC()->countries->countries[ strtok( $old_value, ':' ) ] . ', ' . $old_value );
								WooCommerce_Sensor::set_new_location_data( WooCommerce_Sensor_Helper_Second::get_config( 'store_address' ) . ', ' . WooCommerce_Sensor_Helper_Second::get_config( 'store_address_2' ) . ', ' . WooCommerce_Sensor_Helper_Second::get_config( 'store_address' ) . ', ' . WC()->countries->countries[ strtok( sanitize_text_field( wp_unslash( $_POST['woocommerce_default_country'] ) ), ':' ) ] . ', ' . WooCommerce_Sensor_Helper_Second::get_config( 'store_postcode' ) );
							}
							if ( 'woocommerce_store_postcode' === $option ) {
								WooCommerce_Sensor::set_old_location_data( WooCommerce_Sensor_Helper_Second::get_config( 'store_address' ) . ', ' . WooCommerce_Sensor_Helper_Second::get_config( 'store_address_2' ) . ', ' . WooCommerce_Sensor_Helper_Second::get_config( 'store_address' ) . ', ' . WooCommerce_Sensor_Helper_Second::get_config( 'default_country' ) . ', ' . $old_value );
								WooCommerce_Sensor::set_new_location_data( WooCommerce_Sensor_Helper_Second::get_config( 'store_address' ) . ', ' . WooCommerce_Sensor_Helper_Second::get_config( 'store_address_2' ) . ', ' . WooCommerce_Sensor_Helper_Second::get_config( 'store_address' ) . ', ' . WC()->countries->countries[ strtok( WooCommerce_Sensor_Helper_Second::get_config( 'default_country' ), ':' ) ] . ', ' . sanitize_text_field( wp_unslash( $_POST['woocommerce_store_postcode'] ) ) );
							}

							if ( WooCommerce_Sensor::get_old_location_data() !== WooCommerce_Sensor::get_new_location_data() ) {
								sleep( 1 );
								WooCommerce_Sensor::set_new_location_data( sanitize_text_field( wp_unslash( $_POST['woocommerce_store_address'] ) ) . ', ' . sanitize_text_field( wp_unslash( $_POST['woocommerce_store_address_2'] ) ) . ', ' . sanitize_text_field( wp_unslash( $_POST['woocommerce_store_city'] ) ) . ', ' . WC()->countries->countries[ strtok( sanitize_text_field( wp_unslash( $_POST['woocommerce_default_country'] ) ), ':' ) ] . ', ' . sanitize_text_field( wp_unslash( $_POST['woocommerce_store_postcode'] ) ) );
								if ( ! Alert_Manager::was_triggered_recently( 9029 ) ) {
									Alert_Manager::trigger_event(
										9029,
										array(
											'OldLocation' => sanitize_text_field( WooCommerce_Sensor::get_old_location_data() ),
											'NewLocation' => sanitize_text_field( WooCommerce_Sensor::get_new_location_data() ),
										)
									);
								}
							}
						}

						if ( 'woocommerce_allowed_countries' === $option ) {
							if ( $old_value !== $value ) {
								Alert_Manager::trigger_event(
									9085,
									array(
										'old' => sanitize_text_field( $old_value ),
										'new' => sanitize_text_field( $value ),
									)
								);
							}
						}

						if ( 'woocommerce_specific_allowed_countries' === $option ) {

							if ( $old_value !== $value ) {
								// Check if any old values are present.
								if ( ! empty( $old_value ) ) {
									$old_country_codes = '';
									// add each old country to a string to output in alert.
									foreach ( $old_value as $old_country_code ) {
										$old_country_codes .= WC()->countries->countries[ $old_country_code ] . ', ';
									}
								} else {
									$old_country_codes = __( 'None, ', 'wp-security-audit-log' );
								}
								// Check if any new values are present.
								if ( ! empty( $value ) ) {
									$country_codes = '';
									foreach ( $value as $country_code ) {
										$country_codes .= WC()->countries->countries[ $country_code ] . ', ';
									}
								} else {
									$country_codes = __( 'None', 'wp-security-audit-log' );
								}
								Alert_Manager::trigger_event(
									9087,
									array(
										'old' => rtrim( $old_country_codes, ', ' ),
										'new' => rtrim( $country_codes, ', ' ),
									)
								);
							}
						}

						if ( 'woocommerce_all_except_countries' === $option ) {

							if ( $old_value !== $value ) {
								// Check if any old values are present.
								if ( ! empty( $old_value ) ) {
									$old_country_codes = '';
									// add each old country to a string to output in alert.
									foreach ( $old_value as $old_country_code ) {
										$old_country_codes .= WC()->countries->countries[ $old_country_code ] . ', ';
									}
								} else {
									$old_country_codes = __( 'None, ', 'wp-security-audit-log' );
								}
								// Check if any new values are present.
								if ( ! empty( $value ) ) {
									$country_codes = '';
									foreach ( $value as $country_code ) {
										$country_codes .= WC()->countries->countries[ $country_code ] . ', ';
									}
								} else {
									$country_codes = __( 'None', 'wp-security-audit-log' );
								}
								Alert_Manager::trigger_event(
									9086,
									array(
										'old' => rtrim( $old_country_codes, ', ' ),
										'new' => rtrim( $country_codes, ', ' ),
									)
								);
							}
						}

						if ( 'woocommerce_ship_to_countries' === $option && 'NULL' !== $value ) {
							if ( $old_value !== $value ) {
								$value     = ( '' === $value ) ? __( 'Ship to all countries you sell to', 'wp-security-audit-log' ) : $value;
								$old_value = ( '' === $old_value ) ? __( 'Ship to all countries you sell to', 'wp-security-audit-log' ) : $old_value;

								Alert_Manager::trigger_event(
									9088,
									array(
										'old' => $old_value,
										'new' => $value,
									)
								);
							}
						}

						if ( 'woocommerce_specific_ship_to_countries' === $option && 'NULL' !== $value ) {

							if ( $old_value !== $value ) {
								// Check if any old values are present.
								if ( ! empty( $old_value ) ) {
									$old_country_codes = '';
									// add each old country to a string to output in alert.
									foreach ( $old_value as $old_country_code ) {
										$old_country_codes .= WC()->countries->countries[ $old_country_code ] . ', ';
									}
								} else {
									$old_country_codes = __( 'None, ', 'wp-security-audit-log' );
								}
								// Check if any new values are present.
								if ( ! empty( $value ) ) {
									$country_codes = '';
									foreach ( $value as $country_code ) {
										$country_codes .= WC()->countries->countries[ $country_code ] . ', ';
									}
								} else {
									$country_codes = __( 'None', 'wp-security-audit-log' );
								}
								Alert_Manager::trigger_event(
									9089,
									array(
										'old' => rtrim( $old_country_codes, ', ' ),
										'new' => rtrim( $country_codes, ', ' ),
									)
								);
							}
						}

						if ( 'woocommerce_default_customer_address' === $option ) {
							if ( $old_value !== $value ) {
								$value     = ( '' === $value ) ? __( 'No default location', 'wp-security-audit-log' ) : $value;
								$old_value = ( '' === $old_value ) ? __( 'No default location', 'wp-security-audit-log' ) : $old_value;

								Alert_Manager::trigger_event(
									9090,
									array(
										'old' => sanitize_text_field( $old_value ),
										'new' => sanitize_text_field( $value ),
									)
								);
							}
						}

						// Calculate taxes event.
						if ( 'woocommerce_calc_taxes' === $option ) {
							$old_calc_taxes = $old_value;
							$new_calc_taxes = isset( $_POST['woocommerce_calc_taxes'] ) ? 'yes' : 'no';
							if ( $old_calc_taxes !== $new_calc_taxes ) {
								$status = 'yes' === $new_calc_taxes ? 'enabled' : 'disabled';
								Alert_Manager::trigger_event( 9030, array( 'EventType' => $status ) );
							}
						}

						if ( 'woocommerce_currency_pos' === $option ) {
							if ( $old_value !== $value ) {
								Alert_Manager::trigger_event(
									9115,
									array(
										'old_setting' => $old_value,
										'new_setting' => $value,
									)
								);
							}
						}

						if ( 'woocommerce_price_thousand_sep' === $option ) {
							if ( $old_value !== $value ) {
								Alert_Manager::trigger_event(
									9116,
									array(
										'old_setting' => $old_value,
										'new_setting' => $value,
									)
								);
							}
						}

						if ( 'woocommerce_price_decimal_sep' === $option ) {
							if ( $old_value !== $value ) {
								Alert_Manager::trigger_event(
									9117,
									array(
										'old_setting' => $old_value,
										'new_setting' => $value,
									)
								);
							}
						}

						if ( 'woocommerce_price_num_decimals' === $option ) {
							if ( $old_value !== $value ) {
								Alert_Manager::trigger_event(
									9118,
									array(
										'old_setting' => $old_value,
										'new_setting' => $value,
									)
								);
							}
						}

						// Store current event.
						if ( 'woocommerce_currency' === $option ) {
							if ( isset( $_POST['woocommerce_currency'] ) ) {
								if ( 'NULL' === $old_value ) {
									$old_value = get_option( ' woocommerce_currency' );
								} else {
									$old_currency = $old_value;
								}
								$new_currency = sanitize_text_field( wp_unslash( $_POST['woocommerce_currency'] ) );
								if ( $old_currency !== $new_currency ) {
									Alert_Manager::trigger_event(
										9031,
										array(
											'OldCurrency' => sanitize_text_field( $old_currency ),
											'NewCurrency' => sanitize_text_field( $new_currency ),
										)
									);
								}
							}
						}
					} elseif ( empty( $_GET['tab'] ) || 'advanced' === sanitize_text_field( wp_unslash( $_GET['tab'] ) ) ) {
						if ( 'woocommerce_cart_page_id' === $option ) {
							if ( $old_value !== $value ) {
								if ( 'NULL' === $old_value ) {
									$old_value = get_option( ' woocommerce_cart_page_id' );
								} else {
									$old_value = get_the_title( $old_value );
								}
								Alert_Manager::trigger_event(
									9091,
									array(
										'old' => sanitize_text_field( $old_value ),
										'new' => get_the_title( $value ),
									)
								);
							}
						}

						if ( 'woocommerce_checkout_page_id' === $option ) {
							if ( $old_value !== $value ) {
								if ( 'NULL' === $old_value ) {
									$old_value = get_option( ' woocommerce_cart_page_id' );
								} else {
									$old_value = get_the_title( $old_value );
								}
								Alert_Manager::trigger_event(
									9092,
									array(
										'old' => sanitize_text_field( $old_value ),
										'new' => get_the_title( $value ),
									)
								);
							}
						}

						if ( 'woocommerce_myaccount_page_id' === $option ) {
							if ( $old_value !== $value ) {
								Alert_Manager::trigger_event(
									9093,
									array(
										'old' => get_the_title( $old_value ),
										'new' => get_the_title( $value ),
									)
								);
							}
						}

						if ( 'woocommerce_terms_page_id' === $option ) {
							if ( $old_value !== $value ) {
								Alert_Manager::trigger_event(
									9094,
									array(
										'old' => get_the_title( $old_value ),
										'new' => get_the_title( $value ),
									)
								);
							}
						}

						if ( strpos( $option, 'woocommerce_checkout' ) !== false ) {
							if ( $old_value !== $value ) {
								Alert_Manager::trigger_event(
									9111,
									array(
										'endpoint_name' => str_replace( 'woocommerce_checkout_', '', str_replace( '_endpoint', '', $option ) ),
										'old'           => $old_value,
										'new_value'     => $value,
									)
								);
							}
						}

						if ( strpos( $option, 'woocommerce_myaccount' ) !== false ) {
							if ( $old_value !== $value ) {
								Alert_Manager::trigger_event(
									9112,
									array(
										'endpoint_name' => str_replace( 'woocommerce_myaccount_', '', str_replace( '_endpoint', '', $option ) ),
										'old'           => $old_value,
										'new_value'     => $value,
									)
								);
							}
						}
					} elseif ( empty( $_GET['tab'] ) || 'shipping' === sanitize_text_field( wp_unslash( $_GET['tab'] ) ) ) {
						if ( 'woocommerce_enable_shipping_calc' === $option ) {
							if ( $old_value !== $value ) {
								Alert_Manager::trigger_event(
									9140,
									array(
										'EventType' => ( 'yes' === $value ) ? 'enabled' : 'disabled',
									)
								);
							}
						}

						if ( 'woocommerce_shipping_cost_requires_address' === $option ) {
							if ( $old_value !== $value ) {
								Alert_Manager::trigger_event(
									9141,
									array(
										'EventType' => ( $value ) ? 'enabled' : 'disabled',
									)
								);
							}
						}

						if ( 'woocommerce_shipping_debug_mode' === $option ) {
							if ( $old_value !== $value ) {
								Alert_Manager::trigger_event(
									9143,
									array(
										'EventType' => ( $value ) ? 'enabled' : 'disabled',
									)
								);
							}
						}

						if ( 'woocommerce_ship_to_destination' === $option ) {
							if ( $old_value !== $value ) {
								Alert_Manager::trigger_event(
									9142,
									array(
										'old_setting' => $old_value,
										'new_setting' => $value,
									)
								);
							}
						}
					} elseif ( empty( $_GET['tab'] ) || 'site-visibility' === sanitize_text_field( wp_unslash( $_GET['tab'] ) ) ) {

						if ( 'woocommerce_coming_soon' === $option ) {
							$old_unit = $old_value;
							$new_unit = sanitize_text_field( wp_unslash( $value ) );
							if ( $old_unit !== $new_unit ) {
								$event_type = ( 'yes' == $new_unit ) ? __( 'Coming soon', 'wp-security-audit-log' ) : __( 'Live', 'wp-security-audit-log' );
								$old_staus  = ( 'yes' == $old_unit ) ? __( 'Coming soon', 'wp-security-audit-log' ) : __( 'Live', 'wp-security-audit-log' );
								Alert_Manager::trigger_event(
									9159,
									array(
										'EventType'  => 'modified',
										'old_status' => $old_staus,
										'new_status' => $event_type,
									)
								);
							}
						}
					}
				}
			}
		}

		/**
		 * Trigger events Settings: 9027, 9028, 9029, 9030, 9031, 9032, 9033
		 *
		 * @since 4.6.0
		 */
		public static function check_settings_change() {
			// Verify WooCommerce settings page nonce.
			if ( isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'woocommerce-settings' ) ) {
				// Verify settings page via $_GET array.
				if ( isset( $_GET['page'] ) && 'wc-settings' === sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) {
					if ( isset( $_GET['tab'] ) && 'checkout' === sanitize_text_field( wp_unslash( $_GET['tab'] ) ) ) {
						// Get payment method.
						$gateway = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : false;

						// Set to true if payment gateways are enabled or disabled.
						$status_change = false;

						// Check Cash On Delivery section.
						if ( $gateway && 'cod' === sanitize_text_field( wp_unslash( $gateway ) ) ) {
							// Check COD event.
							$old_cash_on_delivery = WooCommerce_Sensor_Helper_Second::get_config( 'cod_settings' );
							$old_cash_on_delivery = isset( $old_cash_on_delivery['enabled'] ) ? $old_cash_on_delivery['enabled'] : '';
							$new_cash_on_delivery = isset( $_POST['woocommerce_cod_enabled'] ) ? 'yes' : 'no';

							// Check status change.
							if ( $old_cash_on_delivery !== $new_cash_on_delivery ) {
								$status = 'yes' === $new_cash_on_delivery ? 'enabled' : 'disabled';
								Alert_Manager::trigger_event( 9034, array( 'EventType' => $status ) );
								$status_change = true;
							}
						} elseif ( $gateway ) {
							// Get old gateway settings.
							$gateway_settings   = WooCommerce_Sensor_Helper_Second::get_config( $gateway . '_settings' );
							$old_gateway_status = isset( $gateway_settings['enabled'] ) ? $gateway_settings['enabled'] : false;
							$new_gateway_status = isset( $_POST[ 'woocommerce_' . $gateway . '_enabled' ] ) ? 'yes' : 'no';

							// Check status change.
							if ( $old_gateway_status !== $new_gateway_status ) {
								// Gateway enabled.
								Alert_Manager::trigger_event(
									9074,
									array(
										'GatewayID'   => sanitize_text_field( $gateway ),
										'GatewayName' => isset( $gateway_settings['title'] ) ? $gateway_settings['title'] : false,
										'EventType'   => 'yes' === $new_gateway_status ? 'enabled' : 'disabled',
									)
								);
								$status_change = true;
							}
						}

						if ( $gateway && ! $status_change ) {
							$gateway_settings = WooCommerce_Sensor_Helper_Second::get_config( $gateway . '_settings' );
							Alert_Manager::trigger_event_if(
								9076,
								array(
									'GatewayID'   => sanitize_text_field( $gateway ),
									'GatewayName' => isset( $gateway_settings['title'] ) ? $gateway_settings['title'] : false,
								),
								Alert_Manager::will_or_has_triggered( 9074 )
							);
						}
					}
				}

				$webhook_id = ( isset( $_POST['webhook_id'] ) ) ? \sanitize_text_field( wp_unslash( $_POST['webhook_id'] ) ) : false;
				if ( $webhook_id ) {
					// Gather POSTed (freshest) data.
					$new_webhook_data = array(
						'id'           => $webhook_id,
						'name'         => isset( $_POST['webhook_name'] ) ? \sanitize_text_field( wp_unslash( $_POST['webhook_name'] ) ) : '',
						'delivery_url' => isset( $_POST['webhook_delivery_url'] ) ? \sanitize_text_field( wp_unslash( $_POST['webhook_delivery_url'] ) ) : '',
						'topic'        => isset( $_POST['webhook_topic'] ) ? \sanitize_text_field( wp_unslash( $_POST['webhook_topic'] ) ) : '',
						'status'       => isset( $_POST['webhook_status'] ) ? \sanitize_text_field( wp_unslash( $_POST['webhook_status'] ) ) : '',
						'secret'       => isset( $_POST['webhook_secret'] ) ? \sanitize_text_field( wp_unslash( $_POST['webhook_secret'] ) ) : '',
					);

					// Get a current copy of the soon to be "old" version for comparison.
					$data_store     = \WC_Data_Store::load( 'webhook' );
					$webhooks       = $data_store->search_webhooks( array( 'limit' => -1 ) );
					$old_hook_found = false;
					foreach ( $webhooks as $wc_webkook_key => $lookup_id ) {
						$id_we_want = intval( $webhook_id );
						if ( $id_we_want === $lookup_id && ! $old_hook_found ) {
							$old_hook_found = true;
							$old_webhook    = wc_get_webhook( $id_we_want );
							continue;
						}
					}

					if ( ! $old_hook_found ) {
						return;
					}

					$alert_needed = false;

					if ( isset( $old_webhook ) ) {
						// Tidy up data for comparison.
						$old_webhook_data = array(
							'id'           => $webhook_id,
							'name'         => $old_webhook->get_name(),
							'delivery_url' => $old_webhook->get_delivery_url(),
							'topic'        => $old_webhook->get_topic(),
							'status'       => $old_webhook->get_status(),
							'secret'       => $old_webhook->get_secret(),
						);

						foreach ( $new_webhook_data as $key => $data ) {
							if ( $old_webhook_data[ $key ] !== $new_webhook_data[ $key ] ) {
								$alert_needed = true;
							}
						}

						if ( $alert_needed ) {
							$editor_link = Woocommerce_Helper::create_webhook_editor_link( $webhook_id );
							Alert_Manager::trigger_event(
								9122,
								array(
									'HookName'          => $new_webhook_data['name'],
									'OldHookName'       => $old_webhook_data['name'],
									'DeliveryURL'       => $new_webhook_data['delivery_url'],
									'OldDeliveryURL'    => $old_webhook_data['delivery_url'],
									'Topic'             => $new_webhook_data['topic'],
									'OldTopic'          => $old_webhook_data['topic'],
									'Status'            => $new_webhook_data['status'],
									'OldStatus'         => $old_webhook_data['status'],
									'Secret'            => $new_webhook_data['secret'],
									'OldSecret'         => $old_webhook_data['secret'],
									'EditorLinkWebhook' => $editor_link,
								)
							);
						}
					}
				}
			}

			// Verify nonce for payment gateways.
			if ( isset( $_POST['security'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'woocommerce-toggle-payment-gateway-enabled' ) ) {
				if ( isset( $_POST['action'] ) && 'woocommerce_toggle_gateway_enabled' === sanitize_text_field( wp_unslash( $_POST['action'] ) ) ) {
					// Get payment gateways.
					$payment_gateways = WC()->payment_gateways->payment_gateways();

					if ( ! empty( $payment_gateways ) ) {
						foreach ( $payment_gateways as $gateway ) {
							// Get gateway ID.
							$gateway_id = isset( $_POST['gateway_id'] ) ? sanitize_text_field( wp_unslash( $_POST['gateway_id'] ) ) : false;

							// Check if the gateway id matches any available gateway ids.
							if ( ! in_array( $gateway_id, array( $gateway->id, sanitize_title( get_class( $gateway ) ) ), true ) ) {
								continue;
							}
							$enabled = $gateway->get_option( 'enabled', 'no' );

							if ( 'cod' === $gateway->id ) {
								$status = ! wc_string_to_bool( $enabled ) ? 'enabled' : 'disabled';
								Alert_Manager::trigger_event( 9034, array( 'EventType' => $status ) );
							} else {
								// Gateway enabled.
								Alert_Manager::trigger_event(
									9074,
									array(
										'GatewayID'   => sanitize_text_field( $gateway->id ),
										'GatewayName' => sanitize_text_field( $gateway->title ),
										'EventType'   => ! wc_string_to_bool( $enabled ) ? 'enabled' : 'disabled',
									)
								);
							}
						}
					}
				}
			}

			// Verify nonce for shipping zones events.
			if ( isset( $_POST['wc_shipping_zones_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wc_shipping_zones_nonce'] ) ), 'wc_shipping_zones_nonce' ) ) {

				if ( isset( $_POST['changes'] ) && ! empty( $_POST['changes'] ) ) {
					$changes = $_POST['changes']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					foreach ( $changes as $key => $zone ) {
						if ( ! is_integer( $key ) ) {
							continue;
						}

						if ( isset( $zone['zone_id'], $zone['deleted'] ) && 'deleted' === $zone['deleted'] ) {
							$zone_obj = new \WC_Shipping_Zone( sanitize_text_field( $zone['zone_id'] ) );
							Alert_Manager::trigger_event(
								9082,
								array(
									'ShippingZoneID'   => sanitize_text_field( $zone['zone_id'] ),
									'EventType'        => 'deleted',
									'ShippingZoneName' => sanitize_text_field( $zone_obj->get_zone_name() ),
								)
							);
						}
					}
				}
			}
		}

		/**
		 * Get Stock Status Name.
		 *
		 * @param string $slug - Stock slug.
		 * @return string
		 *
		 * @since 4.6.0
		 */
		public static function get_stock_status_name( $slug ) {
			if ( 'instock' === $slug ) {
				return __( 'In stock', 'wp-security-audit-log' );
			} elseif ( 'outofstock' === $slug ) {
				return __( 'Out of stock', 'wp-security-audit-log' );
			} elseif ( 'onbackorder' === $slug ) {
				return __( 'On backorder', 'wp-security-audit-log' );
			}
		}

		/**
		 * Return: Product Categories.
		 *
		 * @param object $post - Product post object.
		 * @return array
		 *
		 * @since 4.6.0
		 */
		public static function get_product_categories( $post ) {
			return wp_get_post_terms( $post->ID, 'product_cat', array( 'fields' => 'names' ) );
		}
	}
}
