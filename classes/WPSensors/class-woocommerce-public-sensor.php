<?php
/**
 * Sensor: Public WooCommerce Activity.
 *
 * Public/Visitor activity sensor class file.
 *
 * @since 1.2
 *
 * @package Wsal
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors;

use WSAL\Helpers\User_Helper;
use WSAL\Helpers\Settings_Helper;
use WSAL\Controllers\Alert_Manager;
use WSAL\Entities\Occurrences_Entity;
use WSAL\WP_Sensors\Helpers\Woocommerce_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Plugin_Sensors\WooCommerce_Public_Sensor' ) ) {
	/**
	 * Public/Visitor WooCommerce Sensor.
	 *
	 * @package Wsal
	 */
	class WooCommerce_Public_Sensor {

		/**
		 * Old Product Stock Quantity.
		 *
		 * @var int
		 */
		private static $old_stock = null;

		/**
		 * Old Product Stock Status.
		 *
		 * @var string
		 */
		private static $old_stock_status = null;

		/**
		 * WC User Meta.
		 *
		 * @since 3.4
		 *
		 * @var array
		 */
		private static $wc_user_meta = array();

		/**
		 * Is that a login sensor or not?
		 * Sensors doesn't need to have this property, except where they explicitly have to set that value.
		 *
		 * @var boolean
		 *
		 * @since 4.5.0
		 */
		private static $frontend_sensor = true;

		/**
		 * Is that a front end sensor? The sensors doesn't need to have that method implemented, except if they want to specifically set that value.
		 *
		 * @return boolean
		 *
		 * @since 4.5.0
		 */
		public static function is_frontend_sensor() {
			return self::$frontend_sensor;
		}

		/**
		 * Listening to events using WP hooks.
		 */
		public static function init() {
			if ( Woocommerce_Helper::is_woocommerce_active() ) {
				$frontend_events = Settings_Helper::get_frontend_events();
				\add_action( 'woocommerce_new_order', array( __CLASS__, 'event_new_order' ), 10, 1 );
				\add_filter( 'woocommerce_order_item_quantity', array( __CLASS__, 'set_old_stock' ), 10, 3 );
				\add_filter( 'woocommerce_update_product_stock_query', array( __CLASS__, 'set_old_stock_for_orders' ), 10, 3 );
				\add_action( 'woocommerce_product_set_stock', array( __CLASS__, 'product_stock_changed' ), 10, 1 );
				\add_action( 'woocommerce_variation_set_stock', array( __CLASS__, 'product_stock_changed' ), 10, 1 );
				if ( \is_user_logged_in() || ! \is_user_logged_in() && ( isset( $frontend_events['woocommerce'] ) && $frontend_events['woocommerce'] ) ) {
					\add_action( 'update_user_meta', array( __CLASS__, 'before_wc_user_meta_update' ), 10, 3 );
					\add_action( 'added_user_meta', array( __CLASS__, 'before_wc_user_meta_update' ), 10, 3 );
				}
			}
		}

		/**
		 * Get WC User Meta Data before updating.
		 *
		 * @since 3.4
		 *
		 * @param int    $meta_id  - Meta id.
		 * @param int    $user_id  - User id.
		 * @param string $meta_key - Meta key.
		 */
		public static function before_wc_user_meta_update( $meta_id, $user_id, $meta_key ) {
			if ( ! self::is_woocommerce_user_meta( $meta_key ) ) {
				return;
			}

			self::$wc_user_meta[ $meta_id ] = (object) array(
				'key'   => $meta_key,
				'value' => get_user_meta( $user_id, $meta_key, true ),
			);
		}

		/**
		 * Check if meta key belongs to WooCommerce user meta.
		 *
		 * @since 3.4
		 *
		 * @param string $meta_key - Meta key.
		 *
		 * @return bool
		 */
		private static function is_woocommerce_user_meta( $meta_key ) {
			// Remove the prefix to avoid redundancy in the meta keys.
			$address_key = str_replace( array( 'shipping_', 'billing_' ), '', (string) $meta_key );

			// WC address meta keys without prefix.
			$meta_keys = array( 'first_name', 'last_name', 'company', 'country', 'address_1', 'address_2', 'city', 'state', 'postcode', 'phone', 'email' );

			if ( in_array( $address_key, $meta_keys, true ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Get editor link.
		 *
		 * @since 3.3.1
		 *
		 * @param WP_Post $post - Product post object.
		 *
		 * @return array $editor_link - Name and value link.
		 */
		private static function get_editor_link( $post ) {

			$post_type = '';

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
		 * New WooCommerce Order Event.
		 *
		 * @since 3.3.1
		 *
		 * @param int $order_id – Order id.
		 */
		public static function event_new_order( $order_id ) {
			if ( empty( $order_id ) ) {
				return;
			}

			// Get order object.
			$new_order = new \WC_Order( $order_id );

			if ( $new_order && $new_order instanceof \WC_Order ) {
				$order_post  = \wc_get_order( $order_id ); // Get order post object.
				$editor_link = self::get_editor_link( $order_post );
				Alert_Manager::trigger_event(
					9035,
					array(
						'OrderID'            => $order_id,
						'OrderTitle'         => Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $order_id ),
						'OrderStatus'        => \wc_get_order_status_name( $new_order->get_status() ),
						'OrderStatusSlug'    => $new_order->get_status(),
						$editor_link['name'] => $editor_link['value'],
					)
				);
			}
		}

		/**
		 * Triggered before updating stock quantity on customer order.
		 *
		 * @since 3.3.1
		 *
		 * @param int           $order_quantity - Order quantity.
		 * @param WC_Order      $order          - Order object.
		 * @param WC_Order_Item $item           - Order item object.
		 *
		 * @return int - Order quantity.
		 */
		public static function set_old_stock( $order_quantity, $order, $item ) {
			// Get product from order item.
			$product = $item->get_product();

			// Get product id.
			$product_id_with_stock = $product->get_stock_managed_by_id();

			// Get product with stock.
			$product_with_stock = wc_get_product( $product_id_with_stock );

			// Set stock attributes of the product.
			self::$old_stock        = $product_with_stock->get_stock_quantity();
			self::$old_stock_status = $product_with_stock->get_stock_status();

			// Return original stock quantity.
			return $order_quantity;
		}

		/**
		 * Triggered before updating stock quantity on customer order from admin panel.
		 *
		 * @param string $sql        - Stock update SQL query.
		 * @param int    $product_id - Product id.
		 *
		 * @return string
		 */
		public static function set_old_stock_for_orders( $sql, $product_id ) {
			$old_product = wc_get_product( $product_id );

			// Set stock attributes of the product.
			self::$old_stock        = $old_product->get_stock_quantity();
			self::$old_stock_status = $old_product->get_stock_status();

			// Return the original sql.
			return $sql;
		}

		/**
		 * Triggered when stock of a product is changed.
		 *
		 * @since 3.3.1
		 *
		 * @param WC_Product $product - WooCommerce product object.
		 */
		public static function product_stock_changed( $product ) {
			if ( is_null( self::$old_stock ) && is_null( self::$old_stock_status ) ) {
				return;
			}

			// Get product data.
			$product_status = false;

			if ( $product->is_type( 'variation' ) ) {
				$product_id     = $product->get_parent_id();
				$product_title  = $product->get_name(); // Get product title.
				$product_status = $product->get_status();
				$product_sku    = $product->get_sku();
			} else {
				$product_id    = $product->get_id();
				$product_title = $product->get_title(); // Get product title.
			}

			// Return if current screen is edit post page.
			global $pagenow;

			// Get global $_POST array.
			$post_array = filter_input_array( INPUT_POST );

			// Special conditions for WooCommerce Bulk Stock Management.
			if ( 'edit.php' === $pagenow && isset( $post_array['page'] ) && 'woocommerce-bulk-stock-management' === $post_array['page'] ) {
				$old_acc_stock = isset( $post_array['current_stock_quantity'] ) ? \sanitize_text_field( \wp_unslash( $post_array['current_stock_quantity'] ) ) : false;
				$new_acc_stock = isset( $post_array['stock_quantity'] ) ? \sanitize_text_field( \wp_unslash( $post_array['stock_quantity'] ) ) : false;

				// Get old stock quantity.
				$old_stock = ! empty( self::$old_stock ) ? self::$old_stock : $old_acc_stock[ $product_id ];

				// Following cases handle the stock status.
				if ( '0' === $old_acc_stock[ $product_id ] && '0' !== $new_acc_stock[ $product_id ] ) {
					$old_stock_status = 'outofstock';
				} elseif ( '0' !== $old_acc_stock[ $product_id ] && '0' === $new_acc_stock[ $product_id ] ) {
					$old_stock_status = 'instock';
				} elseif ( '0' === $old_acc_stock[ $product_id ] && '0' === $new_acc_stock[ $product_id ] ) {
					$old_stock_status = 'outofstock';
				} elseif ( '0' !== $old_acc_stock[ $product_id ] && '0' !== $new_acc_stock[ $product_id ] ) {
					$old_stock_status = 'instock';
				} else {
					$old_stock_status = '';
				}
			} else {
				$old_stock        = self::$old_stock; // Get old stock quantity.
				$old_stock_status = self::$old_stock_status; // Get old stock status.
			}

			$new_stock        = $product->get_stock_quantity(); // Get new stock quantity.
			$new_stock_status = $product->get_stock_status(); // Get new stock status.

			// Set post object.
			$post = get_post( $product_id );

			// Set username.
			$username = '';
			if ( ! is_user_logged_in() ) {
				$username = 'Unregistered user';
				$user_id  = 0;
			} else {
				$username = User_Helper::get_current_user()->user_login;
				$user_id  = User_Helper::get_current_user()->ID;
			}

			// If stock status has changed then trigger the alert.
			if ( ( $old_stock_status && $new_stock_status ) && ( $old_stock_status !== $new_stock_status ) ) {
				$editor_link = self::get_editor_link( $post );
				Alert_Manager::trigger_event(
					9018,
					array(
						'PostID'             => $post->ID,
						'ProductTitle'       => $product_title,
						'ProductStatus'      => ! $product_status ? $post->post_status : $product_status,
						'OldStatus'          => self::get_stock_status( $old_stock_status ),
						'NewStatus'          => self::get_stock_status( $new_stock_status ),
						'Username'           => $username,
						'CurrentUserID'      => $user_id,
						$editor_link['name'] => $editor_link['value'],
					)
				);
			}

			$wc_all_stock_changes = \WSAL\Helpers\Settings_Helper::get_boolean_option_value( 'wc-all-stock-changes', true );

			// If stock has changed then trigger the alert.
			if ( ( $old_stock !== $new_stock ) && ( $wc_all_stock_changes ) ) {
				$editor_link = self::get_editor_link( $post );

				// Check if this was done via an order by looking for event 9035.
				// If so, we are going to add its data.
				$last_occurence = Alert_Manager::get_latest_events();

				$last_occurence_id = ( is_array( $last_occurence[0] ) ) ? $last_occurence[0]['alert_id'] : $last_occurence[0]->alert_id;
				if ( isset( $last_occurence[0] ) && ( 9035 === (int) $last_occurence_id || 9155 === (int) $last_occurence_id || 9105 === (int) $last_occurence_id || 9130 === (int) $last_occurence_id || 9154 === (int) $last_occurence_id ) ) {

					$event_meta  = Occurrences_Entity::get_meta_array( (int) $last_occurence[0]['id'] );
					$order_id    = isset( $event_meta['OrderID'] ) ? $event_meta['OrderID'] : false;
					$order_title = isset( $event_meta['OrderTitle'] ) ? $event_meta['OrderTitle'] : false;

					// If we still dont have an id, check if the last event held something we can use.
					if ( ! $order_id && isset( $event_meta['StockOrderID'] ) ) {
						$order_id = $event_meta['StockOrderID'];
					}
				} else {
					$order_id    = false;
					$order_title = false;
				}

				/*
				 * Event was changed from 9019 to 9105
				 *
				 * @since 4.0.3
				 */
				Alert_MAnager::trigger_event(
					9105,
					array(
						'PostID'             => $post->ID,
						'SKU'                => isset( $product_sku ) ? $product_sku : esc_attr( self::get_product_sku( $post->ID ) ),
						'ProductTitle'       => $product_title,
						'ProductStatus'      => ! $product_status ? $post->post_status : $product_status,
						'OldValue'           => ! empty( $old_stock ) ? $old_stock : 0,
						'NewValue'           => $new_stock,
						'Username'           => $username,
						'CurrentUserID'      => $user_id,
						'StockOrderID'       => $order_id,
						'OrderTitle'         => $order_title,
						$editor_link['name'] => $editor_link['value'],
					)
				);
			}
		}

		/**
		 * Get Stock Status Name.
		 *
		 * @since 3.3.1
		 *
		 * @param string $slug - Stock slug.
		 *
		 * @return string
		 */
		private static function get_stock_status( $slug ) {
			if ( 'instock' === $slug ) {
				return esc_html__( 'In stock', 'wp-security-audit-log' );
			} elseif ( 'outofstock' === $slug ) {
				return esc_html__( 'Out of stock', 'wp-security-audit-log' );
			} elseif ( 'onbackorder' === $slug ) {
				return esc_html__( 'On backorder', 'wp-security-audit-log' );
			}
		}

		/**
		 * Get a SKU for a given product ID.
		 *
		 * @param int $product_id - Id to lookup.
		 *
		 * @return int|string - Result.
		 */
		private static function get_product_sku( $product_id ) {
			$product = wc_get_product( $product_id );
			// If this is not an object, return.
			if ( ! is_object( $product ) ) {
				return;
			}
			$sku = $product->get_sku();

			return ( $sku ) ? $sku : esc_html__( 'Not provided', 'wp-security-audit-log' );
		}
	}
}
