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

use WC_Product;
use WSAL\Helpers\User_Helper;
use WSAL\Controllers\Alert_Manager;
use WSAL\WP_Sensors\WooCommerce_Sensor;
use WSAL\WP_Sensors\Helpers\Woocommerce_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Plugin_Sensors\WooCommerce_Sensor_Helper_Second' ) ) {

	/**
	 * Support for WooCommerce Plugin.
	 *
	 * @package Wsal
	 */
	class WooCommerce_Sensor_Helper_Second {

		/**
		 * Is Event 9067 Logged?
		 *
		 * @since 3.3.1
		 *
		 * @var boolean
		 */
		private static $is_9067_logged = false;

		/**
		 * Is Event 9068 Logged?
		 *
		 * @since 3.3.1
		 *
		 * @var boolean
		 */
		private static $is_9068_logged = false;

		/**
		 * Count changed meta fields. Set to 1 as thats the minumum number of fields that can be changed.
		 *
		 * @var integer
		 *
		 * @since 4.6.0
		 */
		private static $updated_field_count = 1;

		/**
		 * Count changed shippinh meta fields. Set to 1 as thats the minumum number of fields that can be changed.
		 *
		 * @var integer
		 *
		 * @since 4.6.0
		 */
		private static $updated_shipping_field_count = 1;

		/**
		 * Returns Product Data.
		 *
		 * Returns an array containing only WooCommerce product specific data.
		 * This array contains the following data:
		 *  1. Product type.
		 *  2. Catalog visibility.
		 *  3. Featured product.
		 *  4. Regular price.
		 *  5. Sale price.
		 *  6. SKU.
		 *  7. Stock status.
		 *  8. stock quantity.
		 *  9. Virtual.
		 * 10. Downloadable.
		 * 11. Weight.
		 * 12. Length.
		 * 13. Width.
		 * 14. Height.
		 * 15. Backorders.
		 * 16. Upsell IDs.
		 * 17. Cross sell IDs.
		 * 18. File names.
		 * 19. File URLs.
		 *
		 * @param WC_Product $product - Product post object.
		 *
		 * @return array Product data as array. Empty if the argument is not a valid WooCommerce product.
		 *
		 * @since 4.6.0
		 */
		public static function get_product_data( $product ) {
			if ( ! is_a( $product, 'WC_Product' ) ) {
				return array();
			}

			$product_data = array(
				'type'               => $product->get_type(),
				'catalog_visibility' => $product->get_catalog_visibility(),
				'featured'           => $product->get_featured(),
				'regular_price'      => $product->get_regular_price(),
				'sale_price'         => $product->get_sale_price(),
				'sku'                => $product->get_sku(),
				'stock_status'       => $product->get_stock_status(),
				'stock_quantity'     => $product->get_stock_quantity(),
				'virtual'            => $product->is_virtual() ? 'yes' : 'no',
				'downloadable'       => $product->is_downloadable() ? 'yes' : 'no',
				'weight'             => $product->get_weight(),
				'length'             => $product->get_length(),
				'width'              => $product->get_width(),
				'height'             => $product->get_height(),
				'backorders'         => $product->get_backorders(),
				'upsell_ids'         => $product->get_upsell_ids(),
				'cross_sell_ids'     => $product->get_cross_sell_ids(),
				'tax_status'         => $product->get_tax_status(),
				'tax_class'          => $product->get_tax_class(),
				'low_stock_amount'   => $product->get_low_stock_amount(),
				'manage_stock'       => $product->get_manage_stock(),
				'sold_individually'  => $product->get_sold_individually(),
				'file_names'         => array(),
				'file_urls'          => array(),
			);

			foreach ( $product->get_downloads() as $download ) {
				array_push( $product_data['file_names'], $download->get_name() );
				array_push( $product_data['file_urls'], $download->get_file() );
			}
			return $product_data;
		}

		/**
		 * Get the config setting
		 *
		 * @param string $option_name - Option Name.
		 * @return string
		 *
		 * @since 4.6.0
		 */
		public static function get_config( $option_name ) {
			// If this is multisite AND we have some kind of value, we can return it.
			if ( is_multisite() && ! empty( get_site_option( 'woocommerce_' . $option_name ) ) ) {
				// get_site_option is not empty, so lets return it.
				return get_site_option( 'woocommerce_' . $option_name );
			} else {
				// Otherwise, looking the sites wp_options table, even in multisite.
				return get_option( 'woocommerce_' . $option_name );
			}
		}


		/**
		 * Check post type.
		 *
		 * @param stdClass $post - Post.
		 * @return bool
		 *
		 * @since 4.6.0
		 */
		public static function check_woo_commerce( $post ) {
			switch ( $post->post_type ) {
				case 'product':
					return true;
				default:
					return false;
			}
		}

		/**
		 * Get editor link.
		 *
		 * @param stdClass $post      - The post.
		 * @return array $editor_link - Name and value link.
		 *
		 * @since 4.6.0
		 */
		public static function get_editor_link( $post ) {

			$post_type = '';

			if ( ! isset( $post ) ) {
				$editor_link = array(
					'name'  => '', // Meta key.
					'value' => '', // Meta value.
				);

				return $editor_link;
			}

			if ( \method_exists( $post, 'get_id' ) ) {
				$post_type = get_post_type( $post->get_id() );
			} else {
				$post_type = get_post_type( $post->ID );
			}

			// Meta value key.
			if ( is_a( $post, '\Automattic\WooCommerce\Admin\Overrides\Order' ) || 'shop_order' === $post_type ) {
				$name = 'EditorLinkOrder';
			} elseif ( 'shop_coupon' === $post_type ) {
				$name = 'EditorLinkCoupon';
			} else {
				$name = 'EditorLinkProduct';
			}

			// Get editor post link URL.
			if ( is_a( $post, '\Automattic\WooCommerce\Admin\Overrides\Order' ) ) {
				$value = $post->get_edit_order_url();
			} else {
				$product_id = '';
				if ( \method_exists( $post, 'get_id' ) ) {
					$product_id = $post->get_id();
				} else {
					$product_id = $post->ID;
				}
				$value = get_edit_post_link( $product_id );
			}

			// If the URL is not empty then set values.
			if ( ! empty( $value ) ) {
				$editor_link = array(
					'name'  => $name, // Meta key.
					'value' => $value, // Meta value.
				);
			} else {
				$product_id = '';
				if ( \method_exists( $post, 'get_id' ) ) {
					$product_id = $post->get_id();
				} else {
					$product_id = $post->ID;
				}
				// Get post object.
				$post = get_post( $product_id );

				// Set URL action.
				if ( 'revision' === $post_type ) {
					$action = '';
				} else {
					$action = '&action=edit';
				}

				// Get and check post type object.
				$post_type_object = get_post_type_object( $post_type );
				if ( ! $post_type_object ) {
					return;
				}

				// Set editor link manually.
				if ( $post_type_object->_edit_link ) {
					$link = \network_admin_url( sprintf( $post_type_object->_edit_link . $action, $product_id ) );
				} else {
					$link = '';
				}

				$editor_link = array(
					'name'  => $name, // Meta key.
					'value' => $link, // Meta value.
				);
			}

			return $editor_link;
		}

		/**
		 * Alerts for editing of product post type for WooCommerce.
		 *
		 * @param WP_Post $product - Product post type.
		 *
		 * @since 4.6.0
		 */
		public static function editing_product( $product ) {
			// Check product post type.
			if ( 'product' !== $product->post_type ) {
				return $product;
			}

			if ( is_user_logged_in() && is_admin() ) {
				// Filter $_SERVER array for security.
				$server_array = filter_input_array( INPUT_SERVER );

				$current_path = isset( $server_array['SCRIPT_NAME'] ) ? \sanitize_text_field( \wp_unslash( $server_array['SCRIPT_NAME'] ) ) . '?post=' . $product->ID : false;
				if ( ! empty( $server_array['HTTP_REFERER'] )
					&& strpos( $server_array['HTTP_REFERER'], $current_path ) !== false ) {
					// Ignore this if we were on the same page so we avoid double audit entries.
					return $product;
				}
				if ( ! empty( $product->post_title ) ) {
					$event = 9072;
					if ( ( ! Alert_Manager::was_triggered( $event ) || ! Alert_Manager::was_triggered_recently( 9000 ) ) & ! Alert_Manager::was_triggered( 9001 ) ) {
						$editor_link = self::get_editor_link( $product );
						Alert_Manager::trigger_event_if(
							$event,
							array(
								'PostID'             => esc_attr( $product->ID ),
								'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $product->ID ) ),
								'ProductStatus'      => sanitize_text_field( $product->post_status ),
								'PostStatus'         => sanitize_text_field( $product->post_status ),
								'ProductTitle'       => sanitize_text_field( $product->post_title ),
								'ProductUrl'         => get_permalink( $product->ID ),
								$editor_link['name'] => $editor_link['value'],
							),
							array( __CLASS__, 'must_not_be_fresh_post' )
						);
					}
				}
			}
			return $product;
		}

		/**
		 * Check if is a fresh post.
		 *
		 * @return bool - Has triggered or not.
		 *
		 * @since 4.6.0
		 */
		public static function must_not_be_fresh_post() {
			if ( Alert_Manager::will_or_has_triggered( 9000 ) ) {
				return false;
			}
			return true;
		}

		/**
		 * Get Currency symbol.
		 *
		 * @param string $currency - Currency (default: '').
		 * @return string
		 *
		 * @since 4.6.0
		 */
		public static function get_currency_symbol( $currency = '' ) {
			$symbols         = \get_woocommerce_currency_symbols();
			$currency_symbol = isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : '';

			return $currency_symbol;
		}

		/**
		 * WooCommerce Order Status Changed Event.
		 *
		 * @since 3.3.1
		 *
		 * @param integer  $order_id    – Order ID.
		 * @param string   $status_from – Status changing from.
		 * @param string   $status_to   – Status changing to.
		 * @param WC_Order $order       – WooCommerce order object.
		 *
		 * @since 4.6.0
		 */
		public static function event_order_status_changed( $order_id, $status_from, $status_to, $order ) {
			$edit_link  = self::get_editor_link( $order );
			$event_data = array(
				'OrderID'          => \esc_attr( $order_id ),
				'OrderTitle'       => \sanitize_text_field( Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $order ) ),
				'OrderStatus'      => \wc_get_order_status_name( $status_to ),
				'OrderStatusSlug'  => \sanitize_text_field( $status_to ),
				'PostStatus'       => 'wc-' . $status_to,
				$edit_link['name'] => $edit_link['value'],
			);
			Alert_Manager::trigger_event_if( 9036, $event_data, array( __CLASS__, 'must_not_contain_refund' ) );
		}

		/**
		 * Report additional products being added to an order.
		 *
		 * @param int    $item_id - Item ID.
		 * @param object $item - Item data.
		 * @param int    $order_id - Order ID.
		 * @return void
		 *
		 * @since 4.6.0
		 */
		public static function event_order_items_added( $item_id, $item, $order_id ) {

			if ( empty( $order_id ) ) {
				$order_id = (int) wc_get_order_id_by_order_item_id( $item_id );
				if ( ! isset( $order_id ) || intval( $order_id ) <= 0 ) {
					return;
				}
			}

			if ( $item instanceof \WC_Order_Item_Product ) {
				$product = $item->get_product();
				if ( $product instanceof \WC_Product ) {
					$order      = wc_get_order( $order_id );
					$order_post = wc_get_order( $order_id );
					$edit_link  = self::get_editor_link( $order_post );
					$event_data = array(
						'OrderID'          => esc_attr( $order_id ),
						'OrderTitle'       => Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $order ),
						'ProductTitle'     => $product->get_name(),
						'ProductID'        => $product->get_id(),
						'SKU'              => WooCommerce_Sensor::get_product_sku( $product->get_id() ),
						'OrderStatus'      => \wc_get_order_status_name( $order_post->get_status() ),
						'OrderStatusSlug'  => $order_post->get_status(),
						'PostStatus'       => 'wc-' . $order_post->get_status(),
						'EventType'        => 'added',
						$edit_link['name'] => $edit_link['value'],
					);
					Alert_Manager::trigger_event_if( 9130, $event_data, array( __CLASS__, 'ignore_if_new_order' ) );
				}
			}

			if ( $item instanceof \WC_Order_Item_Fee ) {
				$order = wc_get_order( $order_id );
				if ( $order ) {
					$order_post = wc_get_order( $order_id );
					$edit_link  = self::get_editor_link( $order_post );
					$event_data = array(
						'OrderID'          => esc_attr( $order_id ),
						'OrderTitle'       => Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $order ),
						'FeeAmount'        => $item->get_amount(),
						'OrderStatus'      => \wc_get_order_status_name( $order_post->get_status() ),
						'OrderStatusSlug'  => $order_post->get_status(),
						'PostStatus'       => 'wc-' . $order_post->get_status(),
						'EventType'        => 'added',
						$edit_link['name'] => $edit_link['value'],
					);
					Alert_Manager::trigger_event_if( 9132, $event_data, array( __CLASS__, 'ignore_if_new_order' ) );
				}
			}

			if ( $item instanceof \WC_Order_Item_Coupon ) {
				$order = wc_get_order( $order_id );
				if ( $order ) {
					$order_post = wc_get_order( $order_id );
					$edit_link  = self::get_editor_link( $order_post );
					$event_data = array(
						'OrderID'          => esc_attr( $order_id ),
						'OrderTitle'       => Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $order ),
						'CouponName'       => $item->get_name(),
						'CouponValue'      => $item->get_discount(),
						'OrderStatus'      => \wc_get_order_status_name( $order_post->get_status() ),
						'OrderStatusSlug'  => $order_post->get_status(),
						'PostStatus'       => 'wc-' . $order_post->get_status(),
						'EventType'        => 'added',
						$edit_link['name'] => $edit_link['value'],
					);
					Alert_Manager::trigger_event_if( 9134, $event_data, array( __CLASS__, 'ignore_if_new_order' ) );
				}
			}

			if ( $item instanceof \WC_Order_Item_Tax ) {
				$order = wc_get_order( $order_id );
				if ( $order ) {
					$order_post = wc_get_order( $order_id );
					$edit_link  = self::get_editor_link( $order_post );
					$event_data = array(
						'OrderID'          => esc_attr( $order_id ),
						'OrderTitle'       => Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $order ),
						'TaxName'          => $item->get_name(),
						'OrderStatus'      => \wc_get_order_status_name( $order_post->get_status() ),
						'OrderStatusSlug'  => $order_post->get_status(),
						'PostStatus'       => 'wc-' . $order_post->get_status(),
						'EventType'        => 'added',
						$edit_link['name'] => $edit_link['value'],
					);
					Alert_Manager::trigger_event_if( 9135, $event_data, array( __CLASS__, 'ignore_if_new_order' ) );
				}
			}

			if ( $item instanceof \WC_Order_Item_Shipping ) {
				$order = wc_get_order( $order_id );
				if ( $order ) {
					$order_post = wc_get_order( $order_id );
					$edit_link  = self::get_editor_link( $order_post );
					$event_data = array(
						'OrderID'          => esc_attr( $order_id ),
						'OrderTitle'       => Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $order ),
						'OrderStatus'      => \wc_get_order_status_name( $order_post->get_status() ),
						'OrderStatusSlug'  => $order_post->get_status(),
						'PostStatus'       => 'wc-' . $order_post->get_status(),
						'EventType'        => 'added',
						$edit_link['name'] => $edit_link['value'],
					);
					Alert_Manager::trigger_event_if( 9137, $event_data, array( __CLASS__, 'ignore_if_new_order' ) );
				}
			}
		}

		/**
		 * Report additional products being removed from an an order.
		 *
		 * @param int $item_id - Item ID.
		 *
		 * @return void
		 *
		 * @since 4.6.0
		 */
		public static function event_order_items_removed( $item_id ) {
			$order_id = wc_get_order_id_by_order_item_id( $item_id );
			$order    = wc_get_order( $order_id );

			if ( $order instanceof \WC_Order || $order instanceof \WC_Order_Refund ) {
				if ( isset( $order->get_items()[ $item_id ] ) ) {
					$item    = $order->get_items()[ $item_id ];
					$product = $item->get_product();

					$order_post = wc_get_order( $order_id );
					$edit_link  = self::get_editor_link( $order_post );
					$event_data = array(
						'OrderID'          => esc_attr( $order_id ),
						'OrderTitle'       => Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $order ),
						'ProductTitle'     => $product->get_name(),
						'ProductID'        => $product->get_id(),
						'SKU'              => WooCommerce_Sensor::get_product_sku( $product->get_id() ),
						'OrderStatus'      => \wc_get_order_status_name( $order_post->get_status() ),
						'OrderStatusSlug'  => $order_post->get_status(),
						'PostStatus'       => 'wc-' . $order_post->get_status(),
						'EventType'        => 'removed',
						$edit_link['name'] => $edit_link['value'],
					);
					Alert_Manager::trigger_event( 9130, $event_data );
				}

				if ( isset( $order->get_fees()[ $item_id ] ) ) {
					$item = $order->get_fees()[ $item_id ];

					$order_post = wc_get_order( $order_id );
					$edit_link  = self::get_editor_link( $order_post );
					$event_data = array(
						'OrderID'          => esc_attr( $order_id ),
						'OrderTitle'       => Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $order ),
						'FeeAmount'        => $item->get_amount(),
						'OrderStatus'      => \wc_get_order_status_name( $order_post->get_status() ),
						'OrderStatusSlug'  => $order_post->get_status(),
						'PostStatus'       => 'wc-' . $order_post->get_status(),
						'EventType'        => 'removed',
						$edit_link['name'] => $edit_link['value'],
					);
					Alert_Manager::trigger_event( 9132, $event_data );
				}

				if ( isset( $order->get_coupons()[ $item_id ] ) ) {
					$item = $order->get_coupons()[ $item_id ];

					$order_post = wc_get_order( $order_id );
					$edit_link  = self::get_editor_link( $order_post );
					$event_data = array(
						'OrderID'          => esc_attr( $order_id ),
						'OrderTitle'       => Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $order ),
						'CouponName'       => $item->get_name(),
						'CouponValue'      => $item->get_discount(),
						'OrderStatus'      => \wc_get_order_status_name( $order_post->get_status() ),
						'OrderStatusSlug'  => $order_post->get_status(),
						'PostStatus'       => 'wc-' . $order_post->get_status(),
						'EventType'        => 'removed',
						$edit_link['name'] => $edit_link['value'],
					);
					Alert_Manager::trigger_event( 9134, $event_data );
				}

				if ( isset( $order->get_taxes()[ $item_id ] ) ) {
					$item = $order->get_taxes()[ $item_id ];

					$order_post = wc_get_order( $order_id );
					$edit_link  = self::get_editor_link( $order_post );
					$event_data = array(
						'OrderID'          => esc_attr( $order_id ),
						'OrderTitle'       => Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $order ),
						'TaxName'          => $item->get_name(),
						'OrderStatus'      => \wc_get_order_status_name( $order_post->get_status() ),
						'OrderStatusSlug'  => $order_post->get_status(),
						'PostStatus'       => 'wc-' . $order_post->get_status(),
						'EventType'        => 'removed',
						$edit_link['name'] => $edit_link['value'],
					);
					Alert_Manager::trigger_event( 9135, $event_data );
				}

				if ( isset( $order->get_shipping_methods()[ $item_id ] ) ) {
					$item = $order->get_shipping_method()[ $item_id ];

					$order_post = wc_get_order( $order_id );
					$edit_link  = self::get_editor_link( $order_post );
					$event_data = array(
						'OrderID'          => esc_attr( $order_id ),
						'OrderTitle'       => Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $order ),
						'OrderStatus'      => \wc_get_order_status_name( $order_post->get_status() ),
						'OrderStatusSlug'  => $order_post->get_status(),
						'PostStatus'       => 'wc-' . $order_post->get_status(),
						'EventType'        => 'removed',
						$edit_link['name'] => $edit_link['value'],
					);
					Alert_Manager::trigger_event( 9137, $event_data );
				}
			}
		}

		/**
		 * Detect changes within a WC order.
		 *
		 * @param int   $order_id - Order ID being changed.
		 * @param array $items - Items being changed.
		 * @return void
		 *
		 * @since 4.6.0
		 */
		public static function event_order_items_quantity_changed( $order_id, $items ) {
			$order = wc_get_order( $order_id );

			$output = array();

			if ( isset( $_POST['items'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				if ( is_array( $_POST['items'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
					$data = array_map( 'sanitize_text_field', wp_unslash( $_POST['items'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
					foreach ( $data as $key => $value ) {
						$items['order_item_id'][]          = $key;
						$output[ 'order_item_qty' . $key ] = $value;
					}
				} else {
					parse_str( sanitize_text_field( wp_unslash( $_POST['items'] ) ), $output ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
				}
			} else {
				$output = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}

			if ( ! isset( $items['order_item_id'] ) ) {
				return;
			}

			foreach ( $items['order_item_id'] as $item_id ) {
				if ( isset( $order->get_items()[ $item_id ] ) ) {
					$item = $order->get_items()[ $item_id ];
					if ( $item instanceof \WC_Order_Item_Product ) {
						$product      = $item->get_product();
						$old_quantity = $item->get_quantity();
						$order        = wc_get_order( $order_id );
						$order_post   = wc_get_order( $order_id );
						$edit_link    = self::get_editor_link( $order_post );
						if ( isset( $output[ 'order_item_qty' . $item_id ] ) && intval( $output[ 'order_item_qty' . $item_id ] ) !== intval( $old_quantity ) ) {
							$event_data = array(
								'OrderID'          => esc_attr( $order_id ),
								'OrderTitle'       => Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $order ),
								'ProductID'        => $product->get_id(),
								'SKU'              => WooCommerce_Sensor::get_product_sku( $product->get_id() ),
								'NewQuantity'      => $output[ 'order_item_qty' . $item_id ],
								'OldQuantity'      => $old_quantity,
								'ProductTitle'     => $product->get_name(),
								'OrderStatus'      => \wc_get_order_status_name( $order_post->get_status() ),
								'OrderStatusSlug'  => $order_post->get_status(),
								'PostStatus'       => 'wc-' . $order_post->get_status(),
								$edit_link['name'] => $edit_link['value'],
							);
							Alert_Manager::trigger_event( 9131, $event_data );
						}
					}
				}
				if ( isset( $order->get_fees()[ $item_id ] ) ) {
					$item = $order->get_fees()[ $item_id ];
					if ( $item instanceof \WC_Order_Item_Fee ) {
						$order      = wc_get_order( $order_id );
						$order_post = wc_get_order( $order_id );
						$edit_link  = self::get_editor_link( $order_post );
						if ( isset( $output[ 'line_total' . $item_id ] ) && intval( $output[ 'line_total' . $item_id ] ) !== intval( $item->get_amount() ) ) {
							$event_data = array(
								'OrderID'          => esc_attr( $order_id ),
								'OrderTitle'       => Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $order ),
								'FeeAmount'        => $output[ 'line_total' . $item_id ],
								'OldFeeAmount'     => $item->get_amount(),
								'OrderStatus'      => \wc_get_order_status_name( $order_post->get_status() ),
								'OrderStatusSlug'  => $order_post->get_status(),
								'PostStatus'       => 'wc-' . $order_post->get_status(),
								$edit_link['name'] => $edit_link['value'],
							);
							Alert_Manager::trigger_event( 9133, $event_data );
						}
					}
				}
			}
		}

		/**
		 * Checks if event 9041 has triggered or if it will
		 * trigger.
		 *
		 * @since 3.3.1.1
		 *
		 * @return boolean
		 */
		public static function must_not_contain_refund() {
			if ( Alert_Manager::will_or_has_triggered( 9041 ) ) {
				return false;
			}
			return true;
		}

		/**
		 * Checks if event 9035 has triggered or if it will
		 * trigger.
		 *
		 * @since 1.3.0
		 *
		 * @return boolean
		 */
		public static function ignore_if_new_order() {
			if ( Alert_Manager::will_or_has_triggered( 9035 ) ) {
				return false;
			}
			return true;
		}

		/**
		 * Checks if event 9041 or 9036 has triggered or if it will
		 * trigger.
		 *
		 * @since 4.1.3
		 *
		 * @return boolean
		 */
		public static function must_not_contain_refund_or_modification() {
			if ( Alert_Manager::has_triggered( 9040 ) ) {
				return false;
			}
			if ( Alert_Manager::will_or_has_triggered( 9041 ) ) {
				return false;
			}
			if ( Alert_Manager::will_or_has_triggered( 9036 ) ) {
				return false;
			}
			if ( Alert_Manager::will_or_has_triggered( 9038 ) ) {
				return false;
			}
			if ( Alert_Manager::will_or_has_triggered( 9120 ) ) {
				return false;
			}
			if ( Alert_Manager::will_or_has_triggered( 9120 ) ) {
				return false;
			}
			if ( Alert_Manager::will_or_has_triggered( 9130 ) || Alert_Manager::was_triggered_recently( 9130 ) ) {
				return false;
			}
			if ( Alert_Manager::will_or_has_triggered( 9131 ) || Alert_Manager::was_triggered_recently( 9131 ) ) {
				return false;
			}
			if ( Alert_Manager::will_or_has_triggered( 9132 ) || Alert_Manager::was_triggered_recently( 9132 ) ) {
				return false;
			}
			// if ( Alert_Manager::will_or_has_triggered( 9133 ) || Alert_Manager::was_triggered_recently( 9133 ) ) {
			// return false;
			// }
			if ( Alert_Manager::will_or_has_triggered( 9134 ) || Alert_Manager::was_triggered_recently( 9134 ) ) {
				return false;
			}
			if ( Alert_Manager::will_or_has_triggered( 9137 ) || Alert_Manager::was_triggered_recently( 9137 ) ) {
				return false;
			}
			return true;
		}

		/**
		 * WooCommerce Order Modified.
		 *
		 * @since 3.3.1
		 *
		 * @param integer $order_id – Order id.
		 * @param WP_Post $oldorder – Old order WP_Post object.
		 * @param WP_Post $neworder – New order WP_Post object.
		 */
		public static function check_order_modify_change( $order_id, $oldorder, $neworder ) {
			if ( 'trash' === $neworder->post_status ) {
				return 0;
			}

			// Dont fire if we know an item was added/removed recently.
			if ( Alert_Manager::was_triggered_recently( 9120 ) || Alert_Manager::was_triggered_recently( 9130 ) || Alert_Manager::was_triggered_recently( 9131 ) || Alert_Manager::was_triggered_recently( 9132 ) | Alert_Manager::was_triggered_recently( 9133 ) || Alert_Manager::was_triggered_recently( 9134 ) || Alert_Manager::was_triggered_recently( 9135 ) || Alert_Manager::was_triggered_recently( 9137 ) ) {
				return;
			}

			$order     = \wc_get_order( $order_id );
			$new_order = \wc_get_order( $neworder->ID );
			$items     = $order->get_items( array( 'fee' ) );

			self::event_order_items_quantity_changed( $order_id, $items );

			$difference = self::order_recursive_array_diff( (array) WooCommerce_Sensor::get_old_order(), (array) $order );

			if ( ! empty( $difference ) ) {
				// Get editor link.
				$edit_link = self::get_editor_link( $oldorder );

				// Set event data.
				$event_data = array(
					'OrderID'          => \esc_attr( $order_id ),
					'OrderTitle'       => \sanitize_text_field( Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $order_id ) ),
					'OrderStatus'      => \wc_get_order_status_name( $new_order->get_status() ),
					'OrderStatusSlug'  => \sanitize_text_field( $new_order->get_status() ),
					'PostStatus'       => 'wc-' . $order->get_status(),
					$edit_link['name'] => $edit_link['value'],
				);

				// Log event.
				Alert_Manager::trigger_event_if( 9040, $event_data, array( __CLASS__, 'must_not_contain_refund_or_modification' ) );
			}
		}

		/**
		 * Little helper to recursively compare arrays, which is this case is WC order details.
		 *
		 * @param array $old_details - Array of old details.
		 * @param array $new_details - Array of new details.
		 *
		 * @return array
		 *
		 * @since 4.6.0
		 */
		public static function order_recursive_array_diff( $old_details, $new_details ) {
			$r            = array();
			$ignored_keys = array(
				'date_modified',
			);
			foreach ( $old_details as $k => $v ) {
				if ( in_array( $k, $ignored_keys, true ) ) {
					continue;
				}
				if ( array_key_exists( $k, $new_details ) ) {
					if ( is_array( $v ) ) {
						$rad = self::order_recursive_array_diff( $v, (array) $new_details[ $k ] );
						if ( count( $rad ) ) {
							$r[ $k ] = $rad;
						}
					} elseif ( $v !== $new_details[ $k ] ) {
							$r[ $k ] = $v;
					}
				} else {
					$r[ $k ] = $v;
				}
			}
			return $r;
		}

		/**
		 * WooCommerce Bulk Order Modified.
		 *
		 * @since 3.3.1
		 *
		 * @param array  $order_ids – Bulk Order ids.
		 * @param string $action    – Bulk action to perform.
		 * @return array
		 */
		public static function event_bulk_order_actions( $order_ids, $action ) {
			// Check for remove personal data action.
			if ( 'remove_personal_data' === $action ) {
				foreach ( $order_ids as $order_id ) {
					$order_post = wc_get_order( $order_id );

					// Get editor link.
					$edit_link = self::get_editor_link( $order_post );

					// Log event.
					Alert_Manager::trigger_event_if(
						9040,
						array(
							'OrderID'          => esc_attr( $order_id ),
							'OrderTitle'       => sanitize_text_field( Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $order_id ) ),
							'OrderStatus'      => \wc_get_order_status_name( $order_post->get_status() ),
							'OrderStatusSlug'  => $order_post->get_status(),
							'PostStatus'       => 'wc-' . $order_post->get_status(),
							$edit_link['name'] => $edit_link['value'],
						),
						array( __CLASS__, 'must_not_contain_refund_or_modification' )
					);
				}
			}
			return $order_ids;
		}

		/**
		 * WooCommerce Order Refunded.
		 *
		 * @since 3.3.1
		 *
		 * @param integer $order_id  – Order ID.
		 * @param integer $refund_id – Refund ID.
		 */
		public static function event_order_refunded( $order_id, $refund_id ) {
			// Get order post object.
			$order_obj        = wc_get_order( $order_id );
			$edit_link        = self::get_editor_link( $order_obj );
			$order            = wc_get_order( $order_id );
			$customer_user_id = $order->get_user_id();
			$username         = esc_html__( 'Guest', 'wp-security-audit-log' );

			if ( 0 !== $customer_user_id ) {
				$user     = get_user_by( 'id', $customer_user_id );
				$username = $user->user_login;
			}

			$date          = $order->get_date_created();
			$date_format   = get_option( 'date_format' );
			$time_format   = get_option( 'time_format' );
			$created_date  = $date->date( $date_format . ' ' . $time_format );
			$refund_amount = '';
			$refund_reason = '';
			$currency      = get_woocommerce_currency_symbol( $order->get_currency() );
			$refunds       = $order->get_refunds();

			foreach ( $refunds as $refund_object ) {
				$id = $refund_object->get_id();
				if ( $id === $refund_id ) {
					$amount        = $refund_object->get_amount();
					$refund_amount = ( $amount ) ? $amount : '0.00';
					$reason        = $refund_object->get_reason();
					$refund_reason = ( $reason ) ? $reason : esc_html__( 'None supplied', 'wp-security-audit-log' );
				}
			}

			Alert_Manager::trigger_event(
				9041,
				array(
					'OrderID'          => esc_attr( $order_id ),
					'RefundID'         => esc_attr( $refund_id ),
					'CustomerUser'     => $username,
					'RefundedAmount'   => $currency . $refund_amount,
					'Reason'           => $refund_reason,
					'OrderDate'        => $created_date,
					'OrderTitle'       => sanitize_text_field( Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $order_id ) ),
					'OrderStatus'      => \wc_get_order_status_name( $order_obj->get_status() ),
					'OrderStatusSlug'  => $order_obj->get_status(),
					'PostStatus'       => 'wc-' . $order_obj->get_status(),
					$edit_link['name'] => $edit_link['value'],
				)
			);
		}

		/**
		 * WooCommerce Order Refunded.
		 *
		 * @since 3.3.1
		 *
		 * @param integer $refund_id – Refund ID.
		 * @param integer $order_id  – Order ID.
		 */
		public static function event_order_refund_removed( $refund_id, $order_id ) {
			// Get order post object.
			$order_obj        = wc_get_order( $order_id );
			$edit_link        = self::get_editor_link( $order_obj );
			$order            = wc_get_order( $order_id );
			$customer_user_id = $order->get_user_id();
			$username         = esc_html__( 'Guest', 'wp-security-audit-log' );

			if ( 0 !== $customer_user_id ) {
				$user     = get_user_by( 'id', $customer_user_id );
				$username = $user->user_login;
			}

			$date         = $order->get_date_created();
			$date_format  = get_option( 'date_format' );
			$time_format  = get_option( 'time_format' );
			$created_date = $date->date( $date_format . ' ' . $time_format );

			Alert_Manager::trigger_event(
				9136,
				array(
					'OrderID'          => esc_attr( $order_id ),
					'RefundID'         => esc_attr( $refund_id ),
					'CustomerUser'     => $username,
					'OrderDate'        => $created_date,
					'OrderTitle'       => sanitize_text_field( Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $order_id ) ),
					'OrderStatus'      => \wc_get_order_status_name( $order_obj->post_status ),
					'OrderStatusSlug'  => sanitize_text_field( $order_obj->post_status ),
					'PostStatus'       => 'wc-' . $order_obj->get_status(),
					$edit_link['name'] => $edit_link['value'],
				)
			);
		}

		/**
		 * WooCommerce New Attribute Event.
		 *
		 * @since 3.3.1
		 *
		 * @param int   $attr_id   - Attribute ID.
		 * @param array $attr_data - Attribute data array.
		 */
		public static function event_attribute_added( $attr_id, $attr_data ) {
			if ( $attr_id && is_array( $attr_data ) ) {
				Alert_Manager::trigger_event( 9057, self::get_attribute_event_data( $attr_id, $attr_data ) );
			}
		}

		/**
		 * WooCommerce Attribute Deleted Event.
		 *
		 * @since 3.3.1
		 *
		 * @param int    $id       - Attribute ID.
		 * @param string $name     - Attribute name.
		 * @param string $taxonomy - Attribute taxonomy name.
		 */
		public static function event_attribute_deleted( $id, $name, $taxonomy ) {
			// Get the attribute.
			$attribute = wc_get_attribute( $id );

			// Check id and attribute object.
			if ( $id && ! is_null( $attribute ) ) {
				Alert_Manager::trigger_event(
					9058,
					array(
						'AttributeID'      => esc_attr( $id ),
						'AttributeName'    => isset( $attribute->name ) ? sanitize_text_field( $attribute->name ) : false,
						'AttributeSlug'    => isset( $attribute->slug ) ? sanitize_text_field( str_replace( 'pa_', '', $attribute->slug ) ) : false,
						'AttributeType'    => isset( $attribute->type ) ? sanitize_text_field( $attribute->type ) : false,
						'AttributeOrderby' => isset( $attribute->order_by ) ? sanitize_text_field( $attribute->order_by ) : false,
						'AttributePublic'  => isset( $attribute->has_archives ) ? sanitize_text_field( $attribute->has_archives ) : '0',
						'Taxonomy'         => sanitize_text_field( $taxonomy ),
					)
				);
			}
		}

		/**
		 * Retrieve Attribute Data before editing.
		 *
		 * @since 3.3.1
		 */
		public static function retrieve_attribute_data() {
			$save_attribute = isset( $_POST['save_attribute'] ) ? true : false;
			$post_type      = isset( $_GET['post_type'] ) ? sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) : false;
			$page           = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : false;
			$attribute_id   = isset( $_GET['edit'] ) ? absint( sanitize_text_field( wp_unslash( $_GET['edit'] ) ) ) : false;

			if ( $save_attribute && ! empty( $post_type ) && ! empty( $page ) && ! empty( $attribute_id ) && 'product' === $post_type && 'product_attributes' === $page ) {
				// Verify nonce.
				check_admin_referer( 'woocommerce-save-attribute_' . $attribute_id );

				// Get attribute data.
				WooCommerce_Sensor::set_old_attr_data( wc_get_attribute( $attribute_id ) );
			}
		}

		/**
		 * WooCommerce Attribute Updated Events.
		 *
		 * @since 3.3.1
		 *
		 * @param int    $id       - Added attribute ID.
		 * @param array  $data     - Attribute data.
		 * @param string $old_slug - Attribute old name.
		 */
		public static function event_attribute_updated( $id, $data, $old_slug ) {
			// Check the attribute slug.
			if ( isset( $data['attribute_name'] ) && $data['attribute_name'] !== $old_slug ) {
				$attr_event            = self::get_attribute_event_data( $id, $data );
				$attr_event['OldSlug'] = $old_slug;
				$attr_event['NewSlug'] = $data['attribute_name'];
				Alert_Manager::trigger_event( 9059, $attr_event );
			}

			// Check the attribute name.
			if ( isset( $data['attribute_label'] ) && isset( WooCommerce_Sensor::get_old_attr_data()->name ) && WooCommerce_Sensor::get_old_attr_data()->name !== $data['attribute_label'] ) {
				$attr_event            = self::get_attribute_event_data( $id, $data );
				$attr_event['OldName'] = WooCommerce_Sensor::get_old_attr_data()->name;
				$attr_event['NewName'] = $data['attribute_label'];
				Alert_Manager::trigger_event( 9060, $attr_event );
			}

			// Check the attribute orderby.
			if ( isset( $data['attribute_orderby'] ) && isset( WooCommerce_Sensor::get_old_attr_data()->order_by ) && WooCommerce_Sensor::get_old_attr_data()->order_by !== $data['attribute_orderby'] ) {

				$attr_event = self::get_attribute_event_data( $id, $data );

				// Get user data.
				if ( ! \is_user_logged_in() ) {
					$attr_event['Username'] = 'Unregistered user';
					$v['CurrentUserID']     = 0;
				} else {
					$attr_event['Username']      = User_Helper::get_current_user()->user_login;
					$attr_event['CurrentUserID'] = User_Helper::get_current_user()->ID;
				}
				$attr_event['OldSortOrder'] = WooCommerce_Sensor::get_old_attr_data()->order_by;
				$attr_event['NewSortOrder'] = $data['attribute_orderby'];
				Alert_Manager::trigger_event( 9061, $attr_event );
			}

			// Check the attribute archives.
			if ( isset( $data['attribute_public'] ) && isset( WooCommerce_Sensor::get_old_attr_data()->has_archives ) && (int) WooCommerce_Sensor::get_old_attr_data()->has_archives !== $data['attribute_public'] ) {
				$attr_event              = self::get_attribute_event_data( $id, $data );
				$attr_event['EventType'] = 1 === $data['attribute_public'] ? 'enabled' : 'disabled';
				$attr_event['Slug']      = $old_slug;
				Alert_Manager::trigger_event( 9062, $attr_event );
			}
		}

		/**
		 * Return Attribute Events Data.
		 *
		 * @since 3.3.1
		 *
		 * @param int   $attr_id - Added attribute ID.
		 * @param array $data    - Attribute data.
		 * @return array
		 */
		public static function get_attribute_event_data( $attr_id, $data ) {
			return array(
				'AttributeID'      => $attr_id,
				'AttributeName'    => isset( $data['attribute_label'] ) ? sanitize_text_field( $data['attribute_label'] ) : false,
				'AttributeSlug'    => isset( $data['attribute_name'] ) ? sanitize_text_field( $data['attribute_name'] ) : false,
				'AttributeType'    => isset( $data['attribute_type'] ) ? sanitize_text_field( $data['attribute_type'] ) : false,
				'AttributeOrderby' => isset( $data['attribute_orderby'] ) ? sanitize_text_field( $data['attribute_orderby'] ) : false,
				'AttributePublic'  => isset( $data['attribute_public'] ) ? sanitize_text_field( $data['attribute_public'] ) : '0',
			);
		}

		/**
		 * Check AJAX changes for WooCommerce.
		 *
		 * @since 3.3.1
		 */
		public static function check_wc_ajax_change_events() {
			$action  = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : false;
			$is_data = isset( $_POST['data'] ) ? true : false;

			// WooCommerce order actions.
			$wc_order_actions = array(
				'woocommerce_add_order_item',
				'woocommerce_save_order_items',
				'woocommerce_remove_order_item',
				'woocommerce_add_coupon_discount',
				'woocommerce_remove_order_coupon',
			);

			// Check for save attributes action.
			if ( $is_data && 'woocommerce_save_attributes' === $action ) {
				// Check nonce.
				check_ajax_referer( 'save-attributes', 'security' );

				$post_id = isset( $_POST['post_id'] ) ? sanitize_text_field( wp_unslash( $_POST['post_id'] ) ) : false;
				if ( ! $post_id ) {
					return;
				}

				$post = \wc_get_product( $post_id );
				if ( ! $post ) {
					return;
				}

				// Get the attributes data.
				parse_str( $_POST['data'], $data ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				self::check_attributes_change( $post, $data );
			} elseif ( 'woocommerce_save_variations' === $action ) {
				// Check nonce.
				check_ajax_referer( 'save-variations', 'security' );

				$product_id = isset( $_POST['product_id'] ) ? sanitize_text_field( wp_unslash( $_POST['product_id'] ) ) : false;
				if ( ! $product_id ) {
					return;
				}

				$post = \wc_get_product( $product_id );
				if ( ! $post ) {
					return;
				}
				// Grab posted data so we have something to process.
				$data = wp_unslash( $_POST );

				self::check_variations_change( $post, $data );
			} elseif ( in_array( $action, $wc_order_actions, true ) ) {
				// Check nonce.
				check_ajax_referer( 'order-item', 'security' );

				// Get order ID.
				$order_id = isset( $_POST['order_id'] ) ? absint( sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) ) : false;
				if ( ! $order_id ) {
					return;
				}

				// Get order post.
				$order = wc_get_order( $order_id );

				// Get editor link.
				$edit_link = self::get_editor_link( $order );

				// Log event.
				Alert_Manager::trigger_event_if(
					9040,
					array(
						'OrderID'          => esc_attr( $order_id ),
						'OrderTitle'       => sanitize_text_field( Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $order_id ) ),
						'OrderStatus'      => $order->get_status() ? \wc_get_order_status_name( $order->get_status() ) : false,
						'OrderStatusSlug'  => $order->get_status(),
						'PostStatus'       => 'wc-' . $order->get_status(),
						$edit_link['name'] => $edit_link['value'],
					),
					array( __CLASS__, 'must_not_contain_refund_or_modification' )
				);
			}
		}

		/**
		 * Triggers when post is ipdated, checks if that is an order, and compare it to the old one to detect any changes
		 *
		 * @param int      $order_id - The order ID.
		 * @param \WP_Post $order - The actual order object.
		 *
		 * @return void
		 *
		 * @since 4.6.0
		 */
		public static function order_updated( $order_id, $order ) {
			// Get order post.
			$order = wc_get_order( $order_id );

			if ( isset( $order ) && ! empty( $order ) && ! empty( WooCommerce_Sensor::get_old_meta_data() ) ) {

				$new_meta              = get_post_meta( $order_id, '', false );
				$compare_changed_items = array_diff_assoc(
					array_map( 'serialize', $new_meta ),
					array_map( 'serialize', WooCommerce_Sensor::get_old_meta_data() )
				);

				if ( ! empty( $compare_changed_items ) ) {
					// Get editor link.
					$edit_link = self::get_editor_link( $order );

					// Log event.
					Alert_Manager::trigger_event_if(
						9040,
						array(
							'OrderID'          => esc_attr( $order_id ),
							'OrderTitle'       => sanitize_text_field( Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $order_id ) ),
							'OrderStatus'      => $order->get_status() ? \wc_get_order_status_name( $order->get_status() ) : false,
							'OrderStatusSlug'  => $order->get_status(),
							'PostStatus'       => 'wc-' . $order->get_status(),
							$edit_link['name'] => $edit_link['value'],
						),
						array( __CLASS__, 'must_not_contain_refund_or_modification' )
					);
				}
			}
		}

		/**
		 * Check Product Attributes Change.
		 *
		 * @since 3.3.1
		 *
		 * @param WP_Post $oldpost - WP Post type object.
		 * @param array   $data    - Data array.
		 * @return int
		 */
		public static function check_attributes_change( $oldpost, $data = false ) {
			$product_id = '';
			$title      = '';
			$status     = '';

			if ( isset( $oldpost->ID ) && ! empty( $oldpost->ID ) ) {
				$post_attributes = get_post_meta( $oldpost->ID, '_product_attributes', true ); // Get post attribute meta.
				$product_id      = $oldpost->ID;
				$title           = $oldpost->post_title;
				$status          = $oldpost->post_status;
			} else {
				$post_attributes = get_post_meta( $oldpost->get_id(), '_product_attributes', true ); // Get post attribute meta.
				$product_id      = $oldpost->get_id();
				$title           = $oldpost->get_title();
				$status          = $oldpost->get_status();
			}
			$post_attributes = ! $post_attributes ? array() : $post_attributes;

			if ( ! $data ) {
				$data = array_map( 'sanitize_text_field', wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}

			$attribute_names      = isset( $data['attribute_names'] ) && ! empty( $data['attribute_names'] ) ? array_map( 'sanitize_text_field', wp_unslash( $data['attribute_names'] ) ) : false;
			$attribute_position   = isset( $data['attribute_position'] ) && ! empty( $data['attribute_position'] ) ? array_map( 'sanitize_text_field', wp_unslash( $data['attribute_position'] ) ) : false;
			$attribute_visibility = isset( $data['attribute_visibility'] ) && ! empty( $data['attribute_visibility'] ) ? array_map( 'sanitize_text_field', wp_unslash( $data['attribute_visibility'] ) ) : false;
			$attribute_values     = isset( $data['attribute_values'] ) && ! empty( $data['attribute_values'] ) ? array_map( 'sanitize_text_field', wp_unslash( $data['attribute_values'] ) ) : false;

			if ( ! empty( $attribute_names ) && ! empty( $attribute_values ) ) {
				$new_attributes = array();
				foreach ( $attribute_names as $key => $name ) {
					$attr_key                    = self::get_attribute_key( $name );
					$new_attributes[ $attr_key ] = array(
						'name'       => $name,
						'value'      => isset( $attribute_values[ $key ] ) ? sanitize_text_field( self::get_string_attribute_value( $attribute_values[ $key ] ) ) : false,
						'position'   => isset( $attribute_position[ $key ] ) ? sanitize_text_field( $attribute_position[ $key ] ) : false,
						'is_visible' => isset( $attribute_visibility[ $key ] ) ? sanitize_text_field( $attribute_visibility[ $key ] ) : false,
					);
				}

				// Compare old and new attributes.

				$added_attributes   = array_diff_key( $new_attributes, $post_attributes );
				$deleted_attributes = array_diff_key( $post_attributes, $new_attributes );

				$compare_changed_items = array_diff_assoc(
					array_map( 'serialize', $added_attributes ),
					array_map( 'serialize', $deleted_attributes )
				);
				$changed_items         = array_map( 'unserialize', $compare_changed_items );

				$compare_added = array_diff(
					array_map( 'serialize', $added_attributes ),
					array_map( 'serialize', $changed_items )
				);
				$diff_check    = array_map( 'unserialize', $compare_added );

				// Get product editor link.
				$editor_link = self::get_editor_link( $oldpost );

				// Result.
				$result = 0;

				// Event 9047.
				if ( ! empty( $added_attributes ) && empty( $diff_check ) ) {
					foreach ( $added_attributes as $added_attribute ) {
						$old_name     = array( array_search( $added_attribute['value'], array_column( $changed_items, 'value', 'name' ), true ) );
						$deleted_name = array( array_search( $added_attribute['value'], array_column( $deleted_attributes, 'value', 'name' ), true ) );
						if ( ! empty( $old_name[0] ) && ! empty( $deleted_name[0] ) ) {
							continue;
						}
						if ( $added_attribute && ! empty( $added_attribute['name'] ) && ! empty( $added_attribute['value'] ) ) {
							Alert_Manager::trigger_event(
								9047,
								array(
									'AttributeName'      => sanitize_text_field( $added_attribute['name'] ),
									'AttributeValue'     => sanitize_text_field( $added_attribute['value'] ),
									'ProductID'          => esc_attr( $product_id ),
									'SKU'                => WooCommerce_Sensor::get_product_sku( $product_id ),
									'ProductTitle'       => sanitize_text_field( $title ),
									'ProductStatus'      => sanitize_text_field( $status ),
									'PostStatus'         => sanitize_text_field( $status ),
									$editor_link['name'] => $editor_link['value'],
								)
							);
							$result = 1;
						}
					}
				}

				// Event 9050.
				if ( ! empty( $deleted_attributes ) ) {
					foreach ( $deleted_attributes as $deleted_attribute ) {
						$old_name = array( array_search( $deleted_attribute['value'], array_column( $changed_items, 'value', 'name' ), true ) );
						if ( ! empty( $old_name[0] ) ) {
							continue;
						}
						Alert_Manager::trigger_event(
							9050,
							array(
								'AttributeName'      => sanitize_text_field( $deleted_attribute['name'] ),
								'AttributeValue'     => sanitize_text_field( $deleted_attribute['value'] ),
								'ProductID'          => esc_attr( $product_id ),
								'SKU'                => WooCommerce_Sensor::get_product_sku( $product_id ),
								'ProductTitle'       => sanitize_text_field( $title ),
								'ProductStatus'      => sanitize_text_field( $status ),
								'PostStatus'         => sanitize_text_field( $status ),
								'ProductUrl'         => get_permalink( $product_id ),
								$editor_link['name'] => $editor_link['value'],
							)
						);
						$result = 1;
					}
				}

				// Event 9049.
				if ( ! empty( $changed_items ) ) {
					foreach ( $changed_items as $attr_key => $new_attr ) {
						// Get old and new attribute names.
						$old_name = array( array_search( $new_attr['value'], array_column( $deleted_attributes, 'value', 'name' ), true ) );
						if ( ! empty( $old_name ) ) {
							$old_name = (string) $old_name[0];
						} else {
							$old_name = isset( $post_attributes[ $attr_key ]['name'] ) ? $post_attributes[ $attr_key ]['name'] : false;
						}
						$new_name = isset( $new_attr['name'] ) ? $new_attr['name'] : false;

						if ( ! empty( $old_attributes ) && isset( $old_attributes[ $attr_key ] ) ) {
							$old_name = $old_attributes[ $attr_key ]['name'];
						}

						// Name change.
						if ( ! empty( $old_name ) && $old_name !== $new_name ) {
							if ( ! $new_name ) {
								continue;
							}
							Alert_Manager::trigger_event(
								9049,
								array(
									'AttributeName'      => sanitize_text_field( $new_attr['name'] ),
									'OldValue'           => sanitize_text_field( $old_name ),
									'NewValue'           => sanitize_text_field( $new_name ),
									'ProductID'          => esc_attr( $product_id ),
									'SKU'                => WooCommerce_Sensor::get_product_sku( $product_id ),
									'ProductTitle'       => sanitize_text_field( $title ),
									'ProductStatus'      => sanitize_text_field( $status ),
									'PostStatus'         => sanitize_text_field( $status ),
									'ProductUrl'         => get_permalink( $product_id ),
									$editor_link['name'] => $editor_link['value'],
								)
							);
							$result = 1;
						}
					}
				}

				if ( ! empty( $new_attributes ) ) {
					foreach ( $new_attributes as $attr_key => $new_attr ) {

						// Get old attribute value.
						$old_value = '';
						if ( false !== strpos( $attr_key, 'pa_' ) ) {
							$old_value = self::get_wc_product_attributes( $oldpost, $attr_key );
						} else {
							$old_value = isset( $post_attributes[ $attr_key ]['value'] ) ? $post_attributes[ $attr_key ]['value'] : false;
						}
						$new_value = isset( $new_attr['value'] ) ? $new_attr['value'] : false; // Get new attribute value.

						// Get old and new attribute names.
						$old_name = array( array_search( $new_attr['value'], array_column( $deleted_attributes, 'value', 'name' ), true ) );
						if ( ! empty( $old_name ) ) {
							$old_name = (string) $old_name[0];
						} else {
							$old_name = isset( $post_attributes[ $attr_key ]['name'] ) ? $post_attributes[ $attr_key ]['name'] : false;
						}
						$new_name = isset( $new_attr['name'] ) ? $new_attr['name'] : false;

						// Get old and new attribute visibility.
						$old_visible = isset( $post_attributes[ $attr_key ]['is_visible'] ) ? (int) $post_attributes[ $attr_key ]['is_visible'] : false;
						$new_visible = isset( $new_attr['is_visible'] ) ? (int) $new_attr['is_visible'] : false;

						if ( ! empty( $old_attributes ) && isset( $old_attributes[ $attr_key ] ) ) {
							$old_value   = $old_attributes[ $attr_key ]['value'];
							$old_name    = $old_attributes[ $attr_key ]['name'];
							$old_visible = $old_attributes[ $attr_key ]['is_visible'];
						}

						// Value change.
						if ( $old_value && $new_value && $old_value !== $new_value ) {
							Alert_Manager::trigger_event(
								9048,
								array(
									'AttributeName'      => sanitize_text_field( $new_attr['name'] ),
									'OldValue'           => sanitize_text_field( $old_value ),
									'NewValue'           => sanitize_text_field( $new_value ),
									'ProductID'          => esc_attr( $product_id ),
									'SKU'                => WooCommerce_Sensor::get_product_sku( $product_id ),
									'ProductTitle'       => sanitize_text_field( $title ),
									'ProductStatus'      => sanitize_text_field( $status ),
									'PostStatus'         => sanitize_text_field( $status ),
									$editor_link['name'] => $editor_link['value'],
								)
							);
							$result = 1;
						}

						// Visibility change.
						if ( ! empty( $oldpost ) && ! empty( $new_attr['name'] ) && $old_visible !== $new_visible && ! $result ) {
							Alert_Manager::trigger_event(
								9051,
								array(
									'AttributeName'       => sanitize_text_field( $new_attr['name'] ),
									'AttributeVisiblilty' => 1 === $new_visible ? __( 'Visible', 'wp-security-audit-log' ) : __( 'Non-Visible', 'wp-security-audit-log' ),
									'OldAttributeVisiblilty' => 1 === $old_visible ? __( 'Visible', 'wp-security-audit-log' ) : __( 'Non-Visible', 'wp-security-audit-log' ),
									'ProductID'           => esc_attr( $product_id ),
									'SKU'                 => WooCommerce_Sensor::get_product_sku( $product_id ),
									'ProductTitle'        => sanitize_text_field( $title ),
									'ProductStatus'       => sanitize_text_field( $status ),
									'PostStatus'          => sanitize_text_field( $status ),
									$editor_link['name']  => $editor_link['value'],
								)
							);
							$result = 1;
						}
					}
				}

				return $result;
			}
			return 0;
		}

		/**
		 * Check Product Image Change.
		 *
		 * @param WP_Post $oldpost - WP Post type object.
		 * @param array   $data    - Data array.
		 * @return int
		 *
		 * @since 4.6.0
		 */
		public static function check_image_change( $oldpost, $data = false ) {

			if ( ! $data ) {
				$data = array_map( 'sanitize_text_field', wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing
			}

			if ( ! isset( $data['_thumbnail_id'] ) ) {
				return 0;
			}

			// Setup our variables.
			$thumb_id                = get_post_thumbnail_id( $oldpost->ID );
			$old_attachment_metadata = Woocommerce_Sensor::get_old_attachment_metadata();
			$attachment_metadata     = wp_get_attachment_metadata( $data['_thumbnail_id'] );
			$get_upload_dir          = wp_get_upload_dir();
			$event_id                = 9095;
			$editor_link             = self::get_editor_link( $oldpost );
			$alert_needed            = false;

			// Push editor link into event data early.
			$event_data = array(
				$editor_link['name'] => $editor_link['value'],
			);

			$event_data['PostID']        = $oldpost->ID;
			$event_data['ProductTitle']  = sanitize_text_field( $oldpost->post_title );
			$event_data['ProductStatus'] = sanitize_text_field( $oldpost->post_status );
			$event_data['PostStatus']    = sanitize_text_field( $oldpost->post_status );
			$event_data['SKU']           = WooCommerce_Sensor::get_product_sku( $oldpost->ID );

			// Featued image added.
			if ( empty( $old_attachment_metadata ) && ! empty( $attachment_metadata ) && isset( $attachment_metadata['file'] ) ) {
				$event_data['EventType'] = 'added';
				$event_data['name']      = ( isset( $attachment_metadata['file'] ) && ! empty( $attachment_metadata['file'] ) ) ? basename( $attachment_metadata['file'] ) : __( 'File is missing', 'wp-security-audit-log' );
				$event_data['path']      = ( isset( $attachment_metadata['file'] ) && ! empty( $attachment_metadata['file'] ) ) ? $get_upload_dir['basedir'] . DIRECTORY_SEPARATOR . $attachment_metadata['file'] : __( 'File is missing', 'wp-security-audit-log' );
				$alert_needed            = true;
			}

			// Featued image removed.
			if ( empty( $attachment_metadata ) && ! empty( $old_attachment_metadata ) ) {
				$event_data['EventType'] = 'deleted';
				$event_data['name']      = ( isset( $old_attachment_metadata['file'] ) && ! empty( $old_attachment_metadata['file'] ) ) ? basename( $old_attachment_metadata['file'] ) : __( 'File is missing', 'wp-security-audit-log' );
				$event_data['path']      = ( isset( $old_attachment_metadata['file'] ) && ! empty( $old_attachment_metadata['file'] ) ) ? $get_upload_dir['basedir'] . DIRECTORY_SEPARATOR . $old_attachment_metadata['file'] : __( 'File is missing', 'wp-security-audit-log' );
				$alert_needed            = true;
			}

			// Featured image modified.
			if ( ! empty( $attachment_metadata ) && ! empty( $old_attachment_metadata ) && ( $attachment_metadata !== $old_attachment_metadata ) ) {
				$event_id                = 9096;
				$event_data['EventType'] = 'modified';
				$event_data['old_name']  = ( isset( $old_attachment_metadata['file'] ) && ! empty( $old_attachment_metadata['file'] ) ) ? basename( $old_attachment_metadata['file'] ) : __( 'File is missing', 'wp-security-audit-log' );
				$event_data['old_path']  = ( isset( $old_attachment_metadata['file'] ) && ! empty( $old_attachment_metadata['file'] ) ) ? $get_upload_dir['basedir'] . DIRECTORY_SEPARATOR . $old_attachment_metadata['file'] : __( 'File is missing', 'wp-security-audit-log' );
				$event_data['name']      = ( isset( $attachment_metadata['file'] ) && ! empty( $attachment_metadata['file'] ) ) ? basename( $attachment_metadata['file'] ) : __( 'File is missing', 'wp-security-audit-log' );
				$event_data['path']      = ( isset( $attachment_metadata['file'] ) && ! empty( $attachment_metadata['file'] ) ) ? $get_upload_dir['basedir'] . DIRECTORY_SEPARATOR . $attachment_metadata['file'] : __( 'File is missing', 'wp-security-audit-log' );
				$alert_needed            = true;
			}

			// Its go time.
			if ( $alert_needed ) {
				Alert_Manager::trigger_event( $event_id, $event_data );
				return 1;
			}

			return 0;
		}

		/**
		 * Check Product Download Limit Change.
		 *
		 * @param WP_Post $oldpost - WP Post type object.
		 * @param array   $data    - Data array.
		 * @return int
		 *
		 * @since 4.6.0
		 */
		public static function check_download_limit_change( $oldpost, $data = false ) {

			if ( ! $data ) {
				$data = array_map( 'sanitize_text_field', wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing
			}

			if ( ! isset( $data['_download_expiry'] ) || ! isset( $data['_download_limit'] ) ) {
				return 0;
			}

			$event_id     = false;
			$editor_link  = self::get_editor_link( WooCommerce_Sensor::get_old_post() );
			$alert_needed = false;

			// Push editor link into event data early.
			$event_data = array(
				$editor_link['name'] => $editor_link['value'],
			);

			$event_data['new_value']     = $data['_download_expiry'];
			$event_data['product_name']  = $data['post_title'];
			$event_data['ID']            = $data['post_ID'];
			$event_data['ProductStatus'] = $data['post_status'];
			$event_data['PostStatus']    = $data['post_status'];

			if ( isset( $oldpost['_download_limit'] ) && intval( $oldpost['_download_limit'][0] ) < 0 ) {
				$oldpost['_download_limit'][0] = __( 'Unlimited', 'wp-security-audit-log' );
			}

			if ( isset( $oldpost['_download_expire'] ) && intval( $oldpost['_download_expiry'][0] ) < 0 ) {
				$oldpost['_download_expiry'][0] = __( 'Never', 'wp-security-audit-log' );
			}

			if ( ! $data['_download_limit'] ) {
				$data['_download_limit'] = __( 'Unlimited', 'wp-security-audit-log' );
			}

			if ( ! $data['_download_expiry'] ) {
				$data['_download_expiry'] = __( 'Never', 'wp-security-audit-log' );
			}

			// Event 9097 (Modified the download limit of the product).
			if ( isset( $oldpost['_download_limit'] ) && $oldpost['_download_limit'][0] !== $data['_download_limit'] ) {
				$event_id                     = 9097;
				$event_data['previous_value'] = $oldpost['_download_limit'][0];
				$event_data['new_value']      = $data['_download_limit'];
				$event_data['SKU']            = WooCommerce_Sensor::get_product_sku( $data['post_ID'] );
				Alert_Manager::trigger_event( $event_id, $event_data );

				$alert_needed = true;
			}

			// Event 9098 (Modified the download expires of the product).
			if ( isset( $oldpost['_download_expiry'] ) && $oldpost['_download_expiry'][0] !== $data['_download_expiry'] ) {

				// The empty value for this is stored as -1, so double check we actually have a change to report.
				$old_value = ( '-1' === $oldpost['_download_expiry'][0] ) ? 'Never' : $oldpost['_download_expiry'][0];
				if ( $old_value === $data['_download_expiry'] ) {
					return false;
				}

				$event_id                     = 9098;
				$event_data['previous_value'] = $old_value;
				$event_data['new_value']      = $data['_download_expiry'];
				$event_data['SKU']            = WooCommerce_Sensor::get_product_sku( $data['post_ID'] );
				Alert_Manager::trigger_event( $event_id, $event_data );

				$alert_needed = true;
			}

			return $alert_needed;
		}

		/**
		 * Trigger alert if change of tax status or class occurs.
		 *
		 * @param WC_Product $product - Old post meta data.
		 * @param array      $oldpost - Old post.
		 * @param WC_Product $post    - new post.
		 *
		 * @return void
		 *
		 * @since 4.6.0
		 */
		public static function check_tax_status_change( $product, $oldpost, $post ) {
			if ( ! isset( $oldpost['_tax_status'] ) || ! isset( $post['tax_status'] ) ) {
				return;
			}

			// Tax status.
			$old_status = $oldpost['_tax_status'][0];
			$status     = $post['tax_status'];

			if ( $status !== $old_status ) {
				$editor_link = self::get_editor_link( $product );
				Alert_Manager::trigger_event(
					9113,
					array(
						'PostID'             => $product->ID,
						'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $product->ID ) ),
						'ProductTitle'       => $product->post_title,
						'ProductStatus'      => $product->post_status,
						'PostStatus'         => $product->post_status,
						'old_tax_status'     => $old_status,
						'new_tax_status'     => $status,
						$editor_link['name'] => $editor_link['value'],
					)
				);
			}

			// Tax class.
			$old_class = ( empty( $oldpost['_tax_class'][0] ) ) ? 'standard' : $oldpost['_tax_class'][0];
			$class     = ( empty( $post['tax_class'] ) ) ? 'standard' : $post['tax_class'];

			if ( $class !== $old_class ) {
				$editor_link = self::get_editor_link( $product );
				Alert_Manager::trigger_event(
					9114,
					array(
						'PostID'             => $product->ID,
						'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $product->ID ) ),
						'ProductTitle'       => $product->post_title,
						'ProductStatus'      => $product->post_status,
						'PostStatus'         => $product->post_status,
						'old_tax_class'      => $old_class,
						'new_tax_class'      => $class,
						$editor_link['name'] => $editor_link['value'],
					)
				);
			}
		}

		/**
		 * Trigger alert if change of low stock threshold occurs.
		 *
		 * @param WC_Product $product - Old post meta data.
		 * @param array      $oldpost - Old post.
		 * @param WC_Product $post    - new post.
		 * @return void
		 *
		 * @since 4.6.0
		 */
		public static function check_low_stock_threshold_change( $product, $oldpost, $post ) {
			$old_status = ( isset( $oldpost['_low_stock_amount'] ) ) ? $oldpost['_low_stock_amount'][0] : __( 'Store Default', 'wp-security-audit-log' );
			$status     = ( isset( $post['low_stock_amount'] ) && ! empty( $post['low_stock_amount'] ) ) ? $post['low_stock_amount'] : __( 'Store Default', 'wp-security-audit-log' );

			if ( $status !== $old_status ) {
				$editor_link = self::get_editor_link( $product );
				Alert_Manager::trigger_event(
					9119,
					array(
						'PostID'               => $product->ID,
						'SKU'                  => esc_attr( WooCommerce_Sensor::get_product_sku( $product->ID ) ),
						'ProductTitle'         => $product->post_title,
						'ProductStatus'        => $product->post_status,
						'PostStatus'           => $product->post_status,
						'old_low_stock_amount' => $old_status,
						'new_low_stock_amount' => $status,
						$editor_link['name']   => $editor_link['value'],
					)
				);
			}
		}

		/**
		 * Check Product Variations Change.
		 *
		 * @since 3.3.1.2
		 *
		 * @param WP_Post $oldpost - WP Post type object.
		 * @param array   $data    - Data array.
		 * @return int
		 */
		public static function check_variations_change( $oldpost, $data = false ) {
			if ( ! $data ) {
				$data = array_map( 'sanitize_text_field', wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}

			if ( ! empty( $data['variable_post_id'] ) ) {
				foreach ( $data['variable_post_id'] as $key => $post_id ) {
					$post_id   = absint( $post_id );
					$variation = new \WC_Product_Variation( $post_id );

					// Copy and set the product variation.
					$product              = $oldpost;
					$product->post_title  = $variation->get_name();
					$product->post_status = $variation->get_status();

					// Check regular price.
					$old_price = (int) $variation->get_regular_price();
					$new_price = isset( $data['variable_regular_price'][ $key ] ) ? (int) sanitize_text_field( wp_unslash( $data['variable_regular_price'][ $key ] ) ) : false;
					if ( $old_price !== $new_price ) {
						$result = WooCommerce_Sensor_Helper::event_price( $product, 'Regular price', $old_price, $new_price );
					}

					// Check sale price.
					$old_sale_price = (int) $variation->get_sale_price();
					$new_sale_price = isset( $data['variable_sale_price'][ $key ] ) ? (int) sanitize_text_field( wp_unslash( $data['variable_sale_price'][ $key ] ) ) : false;
					if ( $old_sale_price !== $new_sale_price ) {
						$result = WooCommerce_Sensor_Helper::event_price( $product, 'Sale price', $old_sale_price, $new_sale_price );
					}

					// Check product SKU.
					$old_sku = $variation->get_sku();
					$new_sku = isset( $data['variable_sku'][ $key ] ) ? sanitize_text_field( wp_unslash( $data['variable_sku'][ $key ] ) ) : false;
					if ( $old_sku !== $new_sku ) {
						$result = WooCommerce_Sensor_Helper::check_sku_change( $product, $old_sku, $new_sku );
					}

					// Check product virtual.
					$virtual['old'] = $variation->is_virtual();
					$virtual['new'] = isset( $data['variable_is_virtual'][ $key ] ) ? true : false;
					if ( $virtual['old'] !== $virtual['new'] ) {
						$result = WooCommerce_Sensor_Helper::check_type_change( $product, null, $virtual );
					}

					// Check product downloadable.
					$download['old'] = $variation->is_downloadable();
					$download['new'] = isset( $data['variable_is_downloadable'][ $key ] ) ? true : false;
					if ( $download['old'] !== $download['new'] ) {
						$result = WooCommerce_Sensor_Helper::check_type_change( $product, null, false, $download );
					}

					// Check product stock status.
					$old_stock_status = $variation->get_stock_status();
					$new_stock_status = isset( $data['variable_stock_status'][ $key ] ) ? sanitize_text_field( wp_unslash( $data['variable_stock_status'][ $key ] ) ) : false;
					if ( $old_stock_status !== $new_stock_status ) {
						$result = WooCommerce_Sensor_Helper::check_stock_status_change( $product, $old_stock_status, $new_stock_status );
					}

					// Check product stock quantity.
					$old_stock = $variation->get_stock_quantity();
					$new_stock = isset( $data['variable_stock'][ $key ] ) ? (int) sanitize_text_field( wp_unslash( $data['variable_stock'][ $key ] ) ) : false;
					if ( $old_stock !== $new_stock ) {
						$result = WooCommerce_Sensor_Helper::check_stock_quantity_change( $product, $old_stock, $new_stock );
					}

					// Check product weight.
					$old_weight = $variation->get_weight();
					$new_weight = isset( $data['variable_weight'][ $key ] ) ? sanitize_text_field( wp_unslash( $data['variable_weight'][ $key ] ) ) : false;
					if ( $old_weight !== $new_weight ) {
						$result = WooCommerce_Sensor_Helper::check_weight_change( $product, $old_weight, $new_weight );
					}

					// Check product dimensions change.
					$length['old'] = $variation->get_length();
					$length['new'] = isset( $data['variable_length'][ $key ] ) ? sanitize_text_field( wp_unslash( $data['variable_length'][ $key ] ) ) : false;
					$width['old']  = $variation->get_width();
					$width['new']  = isset( $data['variable_width'][ $key ] ) ? sanitize_text_field( wp_unslash( $data['variable_width'][ $key ] ) ) : false;
					$height['old'] = $variation->get_height();
					$height['new'] = isset( $data['variable_height'][ $key ] ) ? sanitize_text_field( wp_unslash( $data['variable_height'][ $key ] ) ) : false;
					WooCommerce_Sensor_Helper::check_dimensions_change( $product, $length, $width, $height );

					// Check product downloads change.
					$file_names['new'] = isset( $data['_wc_variation_file_names'][ $post_id ] ) ? array_map( 'sanitize_text_field', wp_unslash( $data['_wc_variation_file_names'][ $post_id ] ) ) : array();
					$file_urls['new']  = isset( $data['_wc_variation_file_urls'][ $post_id ] ) ? array_map( 'esc_url_raw', wp_unslash( $data['_wc_variation_file_urls'][ $post_id ] ) ) : array();
					$file_names['old'] = array();
					$file_urls['old']  = array();

					// Set product old downloads data.
					foreach ( $variation->get_downloads() as $download ) {
						array_push( $file_names['old'], $download->get_name() );
						array_push( $file_urls['old'], $download->get_file() );
					}
					WooCommerce_Sensor_Helper::check_downloadable_file_change( $product, $file_names, $file_urls );

					// Check backorders change.
					$old_backorder = $variation->get_backorders();
					$new_backorder = isset( $data['variable_backorders'][ $key ] ) ? sanitize_text_field( wp_unslash( $data['variable_backorders'][ $key ] ) ) : false;
					WooCommerce_Sensor_Helper::check_backorders_setting( $product, $old_backorder, $new_backorder );
				}
			}
			return 1;
		}

		/**
		 * Returns the attribute key using attribute name.
		 *
		 * @since 3.3.1
		 *
		 * @param string $attribute_name - Attribute name.
		 * @return string
		 */
		public static function get_attribute_key( $attribute_name = '' ) {
			return str_replace( ' ', '-', strtolower( $attribute_name ) );
		}

		/**
		 * Return the attribute value string using WooCommerce attribute value field.
		 *
		 * @since 3.3.1
		 *
		 * @param string $attribute_value - WooCommerce attribute value field.
		 * @return string
		 */
		public static function get_string_attribute_value( $attribute_value = '' ) {
			// New attribute value.
			$new_attr_value = '';

			// Check if incoming value is string.
			if ( is_string( $attribute_value ) ) {
				$new_attr_value = sanitize_text_field( wp_unslash( $attribute_value ) );
			} elseif ( is_array( $attribute_value ) ) {
				// If the incoming value is an array, it is an array of term ids.
				$term_attr_values = array_map( 'sanitize_text_field', wp_unslash( $attribute_value ) );

				$new_attr_value = array();
				foreach ( $term_attr_values as $term_id ) {
					$term = get_term( $term_id ); // Get term.
					if ( null !== $term && ! is_wp_error( $term ) ) {
						$new_attr_value[] = $term->name;
					}
				}
				$new_attr_value = implode( ' | ', $new_attr_value );
			}
			return $new_attr_value;
		}

		/**
		 * Return product attribute value.
		 *
		 * @since 3.3.1
		 *
		 * @param WP_Post $product  - Product post object.
		 * @param string  $taxonomy - Taxonomy.
		 * @return string
		 */
		public static function get_wc_product_attributes( $product, $taxonomy ) {
			$attribute_terms = wp_get_post_terms( $product->ID, $taxonomy );
			$product_attrs   = array();

			if ( ! is_wp_error( $attribute_terms ) ) {
				foreach ( $attribute_terms as $single_term ) {
					$product_attrs[] = $single_term->term_id;
				}
			}
			return self::get_string_attribute_value( $product_attrs );
		}

		/**
		 * Check Product Category Updated Events.
		 *
		 * @since 3.3.1
		 *
		 * @param array  $data     - Term data to be updated.
		 * @param int    $term_id  - Term ID.
		 * @param string $taxonomy - Taxonomy slug.
		 * @param array  $args     - Arguments passed to wp_update_term().
		 */
		public static function event_product_cat_updated( $data, $term_id, $taxonomy, $args ) {

			// Check if the taxonomy is `product_cat`.
			if ( 'product_cat' === $taxonomy ) {
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

				// Update if both slugs are not same.
				if ( $old_slug !== $new_slug ) {
					Alert_Manager::trigger_event(
						9053,
						array(
							'CategoryID'     => esc_attr( $term_id ),
							'CategoryName'   => sanitize_text_field( $new_name ),
							'OldSlug'        => sanitize_text_field( $old_slug ),
							'NewSlug'        => sanitize_text_field( $new_slug ),
							'ProductCatLink' => Woocommerce_Helper::get_taxonomy_edit_link( $term_id ),
						)
					);
				}

				// Update if both parent categories are not same.
				if ( $term->parent !== $new_parent_id ) {
					Alert_Manager::trigger_event(
						9054,
						array(
							'CategoryID'     => esc_attr( $term_id ),
							'CategoryName'   => sanitize_text_field( $new_name ),
							'CategorySlug'   => sanitize_text_field( $term->slug ),
							'ProductCatLink' => Woocommerce_Helper::get_taxonomy_edit_link( $term_id ),
							'OldParentID'    => isset( $old_parent_cat->term_id ) ? esc_attr( $old_parent_cat->term_id ) : false,
							'OldParentCat'   => isset( $old_parent_cat->name ) ? sanitize_text_field( $old_parent_cat->name ) : false,
							'NewParentID'    => isset( $new_parent_cat->term_id ) ? esc_attr( $new_parent_cat->term_id ) : false,
							'NewParentCat'   => isset( $new_parent_cat->name ) ? sanitize_text_field( $new_parent_cat->name ) : false,
						)
					);
				}

				// Update if both names are not same.
				if ( $old_name !== $new_name ) {
					Alert_Manager::trigger_event(
						9056,
						array(
							'CategoryID'     => esc_attr( $term_id ),
							'CategoryName'   => sanitize_text_field( $new_name ),
							'OldName'        => sanitize_text_field( $old_name ),
							'NewName'        => sanitize_text_field( $new_name ),
							'CategorySlug'   => sanitize_text_field( $term->slug ),
							'ProductCatLink' => Woocommerce_Helper::get_taxonomy_edit_link( $term_id ),
						)
					);
				}
			}

			if ( 'product_tag' === $taxonomy ) {
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

				// Update if both slugs are not same.
				if ( $old_slug !== $new_slug ) {
					Alert_Manager::trigger_event(
						9104,
						array(
							'TagName'        => sanitize_text_field( $new_name ),
							'OldSlug'        => sanitize_text_field( $old_slug ),
							'NewSlug'        => sanitize_text_field( $new_slug ),
							'ProductTagLink' => Woocommerce_Helper::get_taxonomy_edit_link( $term_id, 'product_tag' ),
						)
					);
				}

				// Update if both names are not same.
				if ( $old_name !== $new_name ) {
					Alert_Manager::trigger_event(
						9103,
						array(
							'OldName'        => sanitize_text_field( $old_name ),
							'NewName'        => sanitize_text_field( $new_name ),
							'Slug'           => sanitize_text_field( $term->slug ),
							'ProductTagLink' => Woocommerce_Helper::get_taxonomy_edit_link( $term_id, 'product_tag' ),
						)
					);
				}
			}
			return $data;
		}

		/**
		 * Check Product Category Display Type Meta Event.
		 *
		 * @since 3.3.1
		 *
		 * @param int    $meta_id    - ID of the metadata entry to update.
		 * @param int    $object_id  - Object ID.
		 * @param string $meta_key   - Meta key.
		 * @param mixed  $meta_value - Meta value.
		 */
		public static function event_cat_display_updated( $meta_id, $object_id, $meta_key, $meta_value ) {
			// Check `display_type` meta key.
			if ( 'display_type' !== $meta_key ) {
				return;
			}

			// Get previous value.
			$old_display = get_term_meta( $object_id, $meta_key, true );

			// Get term.
			$term = get_term( $object_id, 'product_cat' );

			// Check if display type changed.
			if ( $meta_value !== $old_display ) {
				Alert_Manager::trigger_event(
					9055,
					array(
						'CategoryID'     => esc_attr( $object_id ),
						'CategoryName'   => sanitize_text_field( $term->name ),
						'CategorySlug'   => sanitize_text_field( $term->slug ),
						'OldDisplayType' => sanitize_text_field( $old_display ),
						'NewDisplayType' => sanitize_text_field( $meta_value ),
						'ProductCatLink' => Woocommerce_Helper::get_taxonomy_edit_link( $object_id ),
					)
				);
			}
		}

		/**
		 * Check Product Category Deletion Event.
		 *
		 * @since 3.3.1
		 *
		 * @param int   $term_id      - Term ID.
		 * @param int   $tt_id        - Term taxonomy ID.
		 * @param mixed $deleted_term - Copy of the already-deleted term, in the form specified by the parent function. WP_Error otherwise.
		 * @param array $object_ids   - List of term object IDs.
		 */
		public static function event_product_cat_deleted( $term_id, $tt_id, $deleted_term, $object_ids ) {
			if ( 'product_cat' === $deleted_term->taxonomy ) {
				Alert_Manager::trigger_event(
					9052,
					array(
						'CategoryID'   => esc_attr( $deleted_term->term_id ),
						'CategoryName' => sanitize_text_field( $deleted_term->name ),
						'CategorySlug' => sanitize_text_field( $deleted_term->slug ),
					)
				);
			}
		}

		/**
		 * Check Product Tag Deletion Event.
		 *
		 * @since 1.4.0
		 *
		 * @param int   $term_id      - Term ID.
		 * @param int   $tt_id        - Term taxonomy ID.
		 * @param mixed $deleted_term - Copy of the already-deleted term, in the form specified by the parent function. WP_Error otherwise.
		 * @param array $object_ids   - List of term object IDs.
		 */
		public static function event_product_tag_deleted( $term_id, $tt_id, $deleted_term, $object_ids ) {
			if ( 'product_tag' === $deleted_term->taxonomy ) {
				Alert_Manager::trigger_event(
					9102,
					array(
						'ID'   => esc_attr( $deleted_term->term_id ),
						'Name' => sanitize_text_field( $deleted_term->name ),
						'Slug' => sanitize_text_field( $deleted_term->slug ),
					)
				);
			}
		}

		/**
		 * Check Created Events for Coupon Meta.
		 *
		 * @since 3.3.1
		 *
		 * @param bool    $log_event  - True if log event 2053 for coupon meta, false if not.
		 * @param string  $meta_key   - Meta key.
		 * @param mixed   $meta_value - Meta value.
		 * @param WP_Post $coupon     - Coupon CPT object.
		 * @return bool
		 */
		public static function log_coupon_meta_created_event( $log_event, $meta_key, $meta_value, $coupon ) {

			$usage_restriction_meta = array( 'individual_use', 'product_ids', 'exclude_product_ids', 'product_categories', 'exclude_product_categories', 'exclude_sale_items', 'minimum_amount', 'maximum_amount', 'customer_email' );

			if ( ! empty( $meta_key ) && in_array( $meta_key, $usage_restriction_meta, true ) ) {
				self::log_coupon_meta_update_events( $log_event, $meta_key, $meta_value, false, $coupon );
				return false;
			}

			if ( ! empty( $meta_key ) && 'shop_coupon' === $coupon->post_type && in_array( $meta_key, Woocommerce_Sensor::get_coupon_meta(), true ) ) {
				return false;
			}
			// Do not report total sales as its added automatically when a product is published.
			if ( ! empty( $meta_key ) && 'total_sales' === $meta_key ) {
				return false;
			}

			return $log_event;
		}

		/**
		 * Check Updated Events for Coupon Meta.
		 *
		 * @since 3.3.1
		 *
		 * @param bool     $log_meta_event - True if log meta events 2054 or 2062, false if not.
		 * @param string   $meta_key       - Meta key.
		 * @param mixed    $meta_value     - Meta value.
		 * @param stdClass $old_meta_obj   - Old meta value and key object.
		 * @param WP_Post  $coupon         - Coupon CPT object.
		 * @return bool
		 */
		public static function log_coupon_meta_update_events( $log_meta_event, $meta_key, $meta_value, $old_meta_obj, $coupon ) {

			if ( \is_array( $old_meta_obj ) ) {
				$old_meta_obj = (object) $old_meta_obj;
			}

			// If meta key does not match with any coupon meta key, then return.
			if ( ! empty( $meta_key ) && ( ! in_array( $meta_key, Woocommerce_Sensor::get_coupon_meta(), true ) || 'shop_coupon' !== $coupon->post_type ) ) {
				return $log_meta_event;
			}

			$ignore_coupon_meta     = array( 'usage_count', 'free_shipping' ); // Ignore these meta keys.
			$usage_restriction_meta = array( 'individual_use', 'product_ids', 'exclude_product_ids', 'product_categories', 'exclude_product_categories', 'exclude_sale_items', 'minimum_amount', 'maximum_amount', 'customer_email' ); // Event 9067.
			$usage_limits_meta      = array( 'usage_limit', 'usage_limit_per_user', 'limit_usage_to_x_items' ); // Event 9068.

			if ( in_array( $meta_key, $ignore_coupon_meta, true ) && $meta_value !== $old_meta_obj->val ) {
				return false;
			} elseif ( isset( $old_meta_obj->val ) && $meta_value !== $old_meta_obj->val || ! isset( $old_meta_obj->val ) ) {
				// Event id.
				$event_id = false;

				// Get coupon event data.
				$coupon_data = WooCommerce_Sensor::get_coupon_event_data( $coupon );

				if ( 'discount_type' === $meta_key ) {
					// Set coupon discount type data.
					$coupon_data['OldDiscountType']  = isset( $old_meta_obj->val ) ? $old_meta_obj->val : false;
					$coupon_data['NewDiscountType']  = $meta_value;
					$coupon_data['EditorLinkCoupon'] = self::get_editor_link( $coupon )['value'];

					// Set event id.
					$event_id = 9064;
				} elseif ( 'coupon_amount' === $meta_key ) {
					// Set coupon amount data.
					$coupon_data['OldAmount'] = isset( $old_meta_obj->val ) ? $old_meta_obj->val : false;
					$coupon_data['NewAmount'] = $meta_value;

					// Set event id.
					$event_id = 9065;
				} elseif ( 'date_expires' === $meta_key ) {
					// Set coupon expiry date data.
					$coupon_data['OldDate'] = isset( $old_meta_obj->val ) && ! empty( $old_meta_obj->val ) ? gmdate( get_option( 'date_format' ), (int) $old_meta_obj->val ) : __( 'Does not expire', 'wp-security-audit-log' );
					$coupon_data['NewDate'] = ! empty( $meta_value ) ? gmdate( get_option( 'date_format' ), (int) $meta_value ) : __( 'Does not expire', 'wp-security-audit-log' );

					// Set event id.
					$event_id = 9066;
				} elseif ( in_array( $meta_key, $usage_restriction_meta, true ) && ( $meta_value || isset( $old_meta_obj->val ) ) ) {
					$event_type = 'modified';
					$event_id   = 9067;

					$meta_key = ucfirst( str_replace( '_', ' ', (string) $meta_key ) );

					$previous_value = isset( $old_meta_obj->val ) ? $old_meta_obj->val : '0';

					// Set usage restriction meta data.
					$coupon_data['MetaKey']      = $meta_key;
					$coupon_data['OldMetaValue'] = $previous_value;
					$coupon_data['NewMetaValue'] = ! empty( $meta_value ) ? $meta_value : '0';
					$coupon_data['EventType']    = $event_type;

					if ( false === self::$is_9067_logged ) {
						self::$is_9067_logged = true;
					}
				} elseif ( in_array( $meta_key, $usage_limits_meta, true ) ) {
					// Set usage limits meta data.
					$coupon_data['MetaKey']      = $meta_key;
					$coupon_data['OldMetaValue'] = isset( $old_meta_obj->val ) ? $old_meta_obj->val : false;
					$coupon_data['NewMetaValue'] = $meta_value;

					if ( false === self::$is_9068_logged ) {
						// Set event id.
						$event_id             = 9068;
						self::$is_9068_logged = true;
					}
				}

				if ( $event_id && ! empty( $coupon_data ) ) {
					// Log the event.
					Alert_Manager::trigger_event_if(
						$event_id,
						$coupon_data,
						array( __CLASS__, 'must_not_be_new_coupon' )
					);
				}
			}
			return false;
		}

		/**
		 * Check if is a new coupon or not.
		 *
		 * @return bool - If is duplicate or not.
		 *
		 * @since 4.6.0
		 */
		public static function must_not_be_new_coupon() {
			if ( Alert_Manager::will_or_has_triggered( 9063 ) ) {
				return false;
			}
			if ( Alert_Manager::will_or_has_triggered( 9124 ) ) {
				return false;
			}
			return true;
		}

		/**
		 * Check Created Events for Coupon Meta.
		 *
		 * @since 3.3.1
		 *
		 * @param bool    $log_event  - True if log event 2055 for coupon meta, false if not.
		 * @param string  $meta_key   - Meta key.
		 * @param mixed   $meta_value - Meta value.
		 * @param WP_Post $coupon     - Coupon CPT object.
		 * @return bool
		 */
		public static function log_coupon_meta_delete_event( $log_event, $meta_key, $meta_value, $coupon ) {

			$usage_restriction_meta = array( 'individual_use', 'product_ids', 'exclude_product_ids', 'product_categories', 'exclude_product_categories', 'exclude_sale_items', 'minimum_amount', 'maximum_amount', 'customer_email' );

			if ( ! empty( $meta_key ) && in_array( $meta_key, $usage_restriction_meta, true ) ) {
				$post_attributes        = array();
				$post_attributes['val'] = isset( WooCommerce_Sensor::get_old_meta_data()[ $meta_key ][0] ) ? WooCommerce_Sensor::get_old_meta_data()[ $meta_key ][0] : false;
				$post_attributes        = (object) $post_attributes;
				self::log_coupon_meta_update_events( $log_event, $meta_key, $meta_value, $post_attributes, $coupon );
				return false;
			}

			if ( ! empty( $meta_key ) && 'shop_coupon' === $coupon->post_type && in_array( $meta_key, Woocommerce_Sensor::get_coupon_meta(), true ) ) {
				return false;
			}
			return $log_event;
		}

		/**
		 * Get WC User Meta Data before updating.
		 *
		 * @since 3.4
		 *
		 * @param integer $meta_id  - Meta id.
		 * @param integer $user_id  - User id.
		 * @param string  $meta_key - Meta key.
		 */
		public static function before_wc_user_meta_update( $meta_id, $user_id, $meta_key ) {
			if ( ! Woocommerce_Helper::is_woocommerce_user_meta( $meta_key ) ) {
				return;
			}

			Woocommerce_Sensor::set_wc_user_meta(
				$meta_id,
				(object) array(
					'key'   => $meta_key,
					'value' => get_user_meta( $user_id, $meta_key, true ),
				)
			);
		}

		/**
		 * WC User Meta data updated.
		 *
		 * @since 3.4
		 *
		 * @param integer $meta_id    - Meta id.
		 * @param integer $user_id    - User id.
		 * @param string  $meta_key   - Meta key.
		 * @param mixed   $meta_value - Meta value.
		 */
		public static function wc_user_meta_updated( $meta_id, $user_id, $meta_key, $meta_value ) {
			if ( ! Woocommerce_Helper::is_woocommerce_user_meta( $meta_key ) ) {
				return;
			}

			$is_first_edit = false;

			if ( ! isset( Woocommerce_Sensor::get_wc_user_meta()[ $meta_id ] ) || ! is_object( Woocommerce_Sensor::get_wc_user_meta()[ $meta_id ] ) ) {
				Woocommerce_Sensor::get_wc_user_meta()[ $meta_id ] = (object) array(
					'key'   => $meta_key,
					'value' => 'None supplied', // Not translatable as its internal only, we use a translatable string for display later on.
				);
				$is_first_edit                                     = true;
			}

			if ( isset( Woocommerce_Sensor::get_wc_user_meta()[ $meta_id ] ) ) {
				$current_value = get_user_meta( $user_id, Woocommerce_Sensor::get_wc_user_meta()[ $meta_id ]->key, true );
			}

			if ( isset( Woocommerce_Sensor::get_wc_user_meta()[ $meta_id ] ) && Woocommerce_Sensor::get_wc_user_meta()[ $meta_id ]->value !== $current_value ) {
				if ( Woocommerce_Sensor::get_wc_user_meta()[ $meta_id ]->value !== $meta_value ) {
					// Event id.
					$event_id = false;

					if ( false !== strpos( (string) $meta_key, 'billing_' ) ) {
						$event_id = 9083;

						$billing_address_fields = array( 'billing_first_name', 'billing_last_name', 'billing_company', 'billing_country', 'billing_address_1', 'billing_address_2', 'billing_city', 'billing_state', 'billing_postcode', 'billing_phone' );

						// We will fill this is as needed below.
						$new_address_array = array();
						$old_address_array = array();

						foreach ( $billing_address_fields as $field ) {
							$field_value                 = get_user_meta( $user_id, $field, true );
							$new_address_array[ $field ] = ( $meta_key === $field ) ? $meta_value : $field_value;
							$old_address_array[ $field ] = $field_value;
						}

						// Replace old values we already have stored.
						foreach ( Woocommerce_Sensor::get_wc_user_meta() as $user_meta ) {
							$old_address_array[ $user_meta->key ] = $user_meta->value;
						}

						$new_address_array = array_filter( $new_address_array );
						$old_address_array = array_filter( $old_address_array );

						// Combine name fields to avoid weird comma.
						if ( isset( $new_address_array['billing_first_name'] ) && isset( $new_address_array['billing_last_name'] ) ) {
							$new_address_array['billing_first_name'] = $new_address_array['billing_first_name'] . ' ' . $new_address_array['billing_last_name'];
							unset( $new_address_array['billing_last_name'] );
						}
						if ( isset( $old_address_array['billing_first_name'] ) && isset( $old_address_array['billing_last_name'] ) ) {
							$old_address_array['billing_first_name'] = $old_address_array['billing_first_name'] . ' ' . $old_address_array['billing_last_name'];
							unset( $old_address_array['billing_last_name'] );
						}

						// Turn them into a nice string.
						$new_address = implode( ', ', $new_address_array );
						$old_address = implode( ', ', $old_address_array );

						if ( $event_id ) {
							$user = get_user_by( 'ID', $user_id );
							if ( 9083 === $event_id ) {
								// Add 1 to our changed fields counter.
								++self::$updated_field_count;
								Alert_Manager::trigger_event_if(
									$event_id,
									array(
										'TargetUsername' => $user ? $user->user_login : false,
										'NewValue'       => sanitize_text_field( $new_address ),
										'OldValue'       => ( $is_first_edit ) ? esc_html__( 'Not supplied', 'wp-security-audit-log' ) : sanitize_text_field( $old_address ),
										'EditUserLink'   => add_query_arg( 'user_id', $user_id, \network_admin_url( 'user-edit.php' ) ),
										'Roles'          => is_array( $user->roles ) ? implode( ', ', $user->roles ) : $user->roles,
									),
									array(
										__CLASS__,
										'must_not_repeat_billing',
									)
								);
							}
						}
					} elseif ( false !== strpos( (string) $meta_key, 'shipping_' ) ) {
						$event_id = 9084;

						$shipping_address_fields = array( 'shipping_first_name', 'shipping_last_name', 'shipping_company', 'shipping_country', 'shipping_address_1', 'shipping_address_2', 'shipping_city', 'shipping_state', 'shipping_postcode', 'shipping_phone' );

						// We will fill this is as needed below.
						$new_address_array = array();

						foreach ( $shipping_address_fields as $field ) {
							$field_value                 = get_user_meta( $user_id, $field, true );
							$new_address_array[ $field ] = ( $meta_key === $field ) ? $meta_value : $field_value;
							$old_address_array[ $field ] = $field_value;
						}

						// Replace old values we already have stored.
						foreach ( Woocommerce_Sensor::get_wc_user_meta() as $user_meta ) {
							$old_address_array[ $user_meta->key ] = $user_meta->value;
						}

						$new_address_array = array_filter( $new_address_array );
						$old_address_array = array_filter( $old_address_array );

						// Combine name fields to avoid weird comma.
						if ( isset( $new_address_array['shipping_first_name'] ) && isset( $new_address_array['shipping_last_name'] ) ) {
							$new_address_array['shipping_first_name'] = $new_address_array['shipping_first_name'] . ' ' . $new_address_array['shipping_last_name'];
							unset( $new_address_array['shipping_last_name'] );
						}
						if ( isset( $old_address_array['shipping_first_name'] ) && isset( $old_address_array['shipping_last_name'] ) ) {
							$old_address_array['shipping_first_name'] = $old_address_array['shipping_first_name'] . ' ' . $old_address_array['shipping_last_name'];
							unset( $old_address_array['shipping_last_name'] );
						}

						// Turn them into a nice string.
						$new_address = implode( ', ', $new_address_array );
						$old_address = implode( ', ', $old_address_array );

						if ( $event_id ) {
							$user = get_user_by( 'ID', $user_id );

							if ( 9084 === $event_id ) {
								++self::$updated_shipping_field_count;
								Alert_Manager::trigger_event_if(
									$event_id,
									array(
										'TargetUsername' => $user ? $user->user_login : false,
										'NewValue'       => sanitize_text_field( $new_address ),
										'OldValue'       => ( $is_first_edit ) ? esc_html__( 'Not supplied', 'wp-security-audit-log' ) : sanitize_text_field( $old_address ),
										'EditUserLink'   => add_query_arg( 'user_id', $user_id, \network_admin_url( 'user-edit.php' ) ),
										'Roles'          => is_array( $user->roles ) ? implode( ', ', $user->roles ) : $user->roles,
									),
									array(
										__CLASS__,
										'must_not_repeat_shipping',
									)
								);
							}
						}
					}
				}
			}
		}

		/**
		 * Check if is a repeated billing alert.
		 *
		 * @return bool - If was repeat or not.
		 *
		 * @since 4.6.0
		 */
		public static function must_not_repeat_billing() {
			--self::$updated_field_count;
			if ( 1 === self::$updated_field_count ) {
				return true;
			}
			return false;
		}

		/**
		 * Check if is a repeated shipping alert.
		 *
		 * @return bool - If was repeat or not.
		 *
		 * @since 4.6.0
		 */
		public static function must_not_repeat_shipping() {
			--self::$updated_shipping_field_count;
			if ( 1 === self::$updated_shipping_field_count ) {
				return true;
			}
			return false;
		}

		/**
		 * Trigger 9106 (stock level change by 3rd party).
		 *
		 * @since 3.3.1
		 *
		 * @param int    $meta_id    - ID of the metadata entry to update.
		 * @param int    $object_id  - Object ID.
		 * @param string $meta_key   - Meta key.
		 * @param mixed  $meta_value - Meta value.
		 */
		public static function detect_stock_level_change( $meta_id, $object_id, $meta_key, $meta_value ) {
			if ( '_stock' === $meta_key ) {
				$post           = get_post( $object_id );
				$editor_link    = self::get_editor_link( $post );
				$old_stock      = get_post_meta( $object_id, '_stock', true );
				$product_status = get_post_meta( $object_id, '_stock_status', true );

				$new_stock = ( empty( $meta_value ) ) ? 0 : $meta_value;
				$old_stock = ( ! empty( $old_stock ) ) ? $old_stock : 0;

				Alert_Manager::trigger_event_if(
					9106,
					array(
						'PostID'             => $post->ID,
						'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $post->ID ) ),
						'ProductTitle'       => $post->post_title,
						'ProductStatus'      => ! $product_status ? $post->post_status : $product_status,
						'PostStatus'         => ! $product_status ? $post->post_status : $product_status,
						'OldValue'           => $old_stock,
						'NewValue'           => $meta_value,
						$editor_link['name'] => $editor_link['value'],
					),
					array( __CLASS__, 'must_not_edit_or_order' )
				);
			}
		}

		/**
		 * Check WooCommerce product changes before save.
		 *
		 * Support for Admin Columns Pro plugin and its add-on.
		 *
		 * @param WC_Product $product - WooCommerce product object.
		 * @param array      $data_store - Data store array.
		 *
		 * @since 4.6.0
		 */
		public static function check_product_changes_before_save( $product, $data_store ) {
			// If we reach here without any POST data, the change is beong done directly so lets update the old item data in case anything changes.
			if ( ! isset( $_POST['action'] ) ) {
				// Update held data.
				$product_id = $product->get_id();
				WooCommerce_Sensor::set_old_product( wc_get_product( $product_id ) );
				WooCommerce_Sensor::set_old_post( get_post( $product_id ) );
				WooCommerce_Sensor::set_old_data( self::get_product_data( WooCommerce_Sensor::get_old_product() ) );
				WooCommerce_Sensor::set_old_meta_data( get_post_meta( $product_id, '', false ) );
			}

			if ( isset( $_POST['_ajax_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_ajax_nonce'] ) ), 'ac-ajax' ) || isset( $_REQUEST['rest_route'] ) && ( '/wc/v3/products/batch' === $_REQUEST['rest_route'] ) ) {
				// Get product data.
				$product_id       = $product->get_id();
				$old_product      = wc_get_product( $product_id );
				$old_product_post = get_post( $product_id );

				// Check for stock quantity changes.
				WooCommerce_Sensor_Helper::check_stock_quantity_change( $old_product_post, $old_product->get_stock_quantity(), $product->get_stock_quantity() );
			}

			if ( isset( $_REQUEST['action'] ) && ( 'woocommerce_feature_product' === $_REQUEST['action'] ) && check_admin_referer( 'woocommerce-feature-product' ) ) {
				$product_id   = $product->get_id();
				$product_post = get_post( $product_id );
				$editor_link  = self::get_editor_link( $product_post );
				Alert_Manager::trigger_event(
					9043,
					array(
						'PostID'             => esc_attr( $product->get_id() ),
						'SKU'                => esc_attr( WooCommerce_Sensor::get_product_sku( $product_id ) ),
						'ProductTitle'       => sanitize_text_field( $product_post->post_title ),
						'ProductStatus'      => sanitize_text_field( $product_post->post_status ),
						'PostStatus'         => sanitize_text_field( $product_post->post_status ),
						'EventType'          => $product->get_featured() ? 'enabled' : 'disabled',
						$editor_link['name'] => $editor_link['value'],
					)
				);
			}
		}

		/**
		 * Ensure not a repated order event.
		 *
		 * @return bool - If was repeated or not.
		 *
		 * @since 4.6.0
		 */
		public static function must_not_edit_or_order() {
			if ( Alert_Manager::will_or_has_triggered( 9019 ) ) {
				return false;
			}
			if ( Alert_Manager::will_or_has_triggered( 9018 ) ) {
				return false;
			}
			if ( Alert_Manager::will_or_has_triggered( 9105 ) ) {
				return false;
			}
			return true;
		}
	}
}

/**
// Get Order ID and Key
$order->get_id();
$order->get_order_key();

// Get Order Totals $0.00
$order->get_formatted_order_total();
$order->get_cart_tax();
$order->get_currency();
$order->get_discount_tax();
$order->get_discount_to_display();
$order->get_discount_total();
$order->get_total_fees();
$order->get_formatted_line_subtotal();
$order->get_shipping_tax();
$order->get_shipping_total();
$order->get_subtotal();
$order->get_subtotal_to_display();
$order->get_tax_location();
$order->get_tax_totals();
$order->get_taxes();
$order->get_total();
$order->get_total_discount();
$order->get_total_tax();
$order->get_total_refunded();
$order->get_total_tax_refunded();
$order->get_total_shipping_refunded();
$order->get_item_count_refunded();
$order->get_total_qty_refunded();
$order->get_qty_refunded_for_item();
$order->get_total_refunded_for_item();
$order->get_tax_refunded_for_item();
$order->get_total_tax_refunded_by_rate_id();
$order->get_remaining_refund_amount();

// Get and Loop Over Order Items
foreach ( $order->get_items() as $item_id => $item ) {
	$product_id = $item->get_product_id();
	$variation_id = $item->get_variation_id();
	$product = $item->get_product(); // see link above to get $product info
	$product_name = $item->get_name();
	$quantity = $item->get_quantity();
	$subtotal = $item->get_subtotal();
	$total = $item->get_total();
	$tax = $item->get_subtotal_tax();
	$tax_class = $item->get_tax_class();
	$tax_status = $item->get_tax_status();
	$allmeta = $item->get_meta_data();
	$somemeta = $item->get_meta( '_whatever', true );
	$item_type = $item->get_type(); // e.g. "line_item", "fee"
}

// Other Secondary Items Stuff
$order->get_items_key();
$order->get_items_tax_classes();
$order->get_item_count();
$order->get_item_total();
$order->get_downloadable_items();
$order->get_coupon_codes();

// Get Order Lines
$order->get_line_subtotal();
$order->get_line_tax();
$order->get_line_total();

// Get Order Shipping
$order->get_shipping_method();
$order->get_shipping_methods();
$order->get_shipping_to_display();

// Get Order Dates
$order->get_date_created();
$order->get_date_modified();
$order->get_date_completed();
$order->get_date_paid();

// Get Order User, Billing & Shipping Addresses
$order->get_customer_id();
$order->get_user_id();
$order->get_user();
$order->get_customer_ip_address();
$order->get_customer_user_agent();
$order->get_created_via();
$order->get_customer_note();
$order->get_address_prop();
$order->get_billing_first_name();
$order->get_billing_last_name();
$order->get_billing_company();
$order->get_billing_address_1();
$order->get_billing_address_2();
$order->get_billing_city();
$order->get_billing_state();
$order->get_billing_postcode();
$order->get_billing_country();
$order->get_billing_email();
$order->get_billing_phone();
$order->get_shipping_first_name();
$order->get_shipping_last_name();
$order->get_shipping_company();
$order->get_shipping_address_1();
$order->get_shipping_address_2();
$order->get_shipping_city();
$order->get_shipping_state();
$order->get_shipping_postcode();
$order->get_shipping_country();
$order->get_address();
$order->get_shipping_address_map_url();
$order->get_formatted_billing_full_name();
$order->get_formatted_shipping_full_name();
$order->get_formatted_billing_address();
$order->get_formatted_shipping_address();

// Get Order Payment Details
$order->get_payment_method();
$order->get_payment_method_title();
$order->get_transaction_id();

// Get Order URLs
$order->get_checkout_payment_url();
$order->get_checkout_order_received_url();
$order->get_cancel_order_url();
$order->get_cancel_order_url_raw();
$order->get_cancel_endpoint();
$order->get_view_order_url();
$order->get_edit_order_url();

// Get Order Status
$order->get_status();

// Get Thank You Page URL
$order->get_checkout_order_received_url();
*/
