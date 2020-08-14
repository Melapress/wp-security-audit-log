<?php
/**
 * Frontend WooCommerce sensor.
 * Although WC is handled by an extension, this is kept to avoid causing issue.
 *
 * @package wsal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Frontend WooCommerce sensor.
 */
class WSAL_Sensors_FrontendWooCommerce extends WSAL_AbstractSensor {

	/**
	 * Listening to events using WP hooks.
	 */
	public function HookEvents() {
		// Check if WooCommerce plugin exists.
		if ( WpSecurityAuditLog::is_woocommerce_active() ) {
			add_action( 'woocommerce_new_order', array( $this, 'event_new_order' ), 10, 1 );
			add_filter( 'woocommerce_order_item_quantity', array( $this, 'set_old_stock' ), 10, 3 );
			add_action( 'woocommerce_product_set_stock', array( $this, 'product_stock_changed' ), 10, 1 );
			add_action( 'woocommerce_variation_set_stock', array( $this, 'product_stock_changed' ), 10, 1 );
		}
	}

	/**
	 * New WooCommerce Order Event.
	 *
	 * @since 3.3.1
	 *
	 * @param integer $order_id – Order id.
	 */
	public function event_new_order( $order_id ) {
		if ( empty( $order_id ) ) {
			return;
		}

		// Get order object.
		$new_order = new WC_Order( $order_id );

		if ( $new_order && $new_order instanceof WC_Order ) {
			$order_post  = get_post( $order_id ); // Get order post object.
			$order_title = ( null !== $order_post && $order_post instanceof WP_Post ) ? $order_post->post_title : false;
			$editor_link = $this->get_editor_link( $order_post );
			$this->plugin->alerts->Trigger(
				9035,
				array(
					'OrderID'            => $order_id,
					'OrderTitle'         => $this->get_order_title( $new_order ),
					'OrderStatus'        => $new_order->get_status(),
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
	 * @return int - Order quantity.
	 */
	public function set_old_stock( $order_quantity, $order, $item ) {
		// Get product from order item.
		$product = $item->get_product();

		// Get product id.
		$product_id_with_stock = $product->get_stock_managed_by_id();

		// Get product with stock.
		$product_with_stock = wc_get_product( $product_id_with_stock );

		// Set stock attributes of the product.
		$this->_old_stock        = $product_with_stock->get_stock_quantity();
		$this->_old_stock_status = $product_with_stock->get_stock_status();

		// Return original stock quantity.
		return $order_quantity;
	}

	/**
	 * Triggered when stock of a product is changed.
	 *
	 * @since 3.3.1
	 *
	 * @param WC_Product $product - WooCommerce product object.
	 */
	public function product_stock_changed( $product ) {
		// Get product data.
		$product_status = false;
		if ( $product->is_type( 'variation' ) ) {
			$product_id     = $product->get_parent_id();
			$product_title  = $product->get_name(); // Get product title.
			$product_status = $product->get_status();
		} else {
			$product_id    = $product->get_id();
			$product_title = $product->get_title(); // Get product title.
		}

		// Return if current screen is edit post page.
		global $pagenow;
		if ( is_admin() && ( 'post.php' === $pagenow || defined( 'DOING_AJAX' ) ) ) {
			return;
		}

		// Get global $_POST array.
		$post_array = filter_input_array( INPUT_POST );

		// Special conditions for WooCommerce Bulk Stock Management.
		if ( 'edit.php' === $pagenow && isset( $post_array['page'] ) && 'woocommerce-bulk-stock-management' === $post_array['page'] ) {
			$old_acc_stock = isset( $post_array['current_stock_quantity'] ) ? $post_array['current_stock_quantity'] : false;
			$new_acc_stock = isset( $post_array['stock_quantity'] ) ? $post_array['stock_quantity'] : false;

			// Get old stock quantity.
			$old_stock = ! empty( $this->_old_stock ) ? $this->_old_stock : $old_acc_stock[ $product_id ];

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
			$old_stock        = $this->_old_stock; // Get old stock quantity.
			$old_stock_status = $this->_old_stock_status; // Get old stock status.
		}

		$new_stock        = $product->get_stock_quantity(); // Get new stock quantity.
		$new_stock_status = $product->get_stock_status(); // Get new stock status.

		// Set post object.
		$post = get_post( $product_id );

		// Set username.
		$username = '';
		if ( ! is_user_logged_in() ) {
			$username = 'Unregistered user';
		} else {
			$username = wp_get_current_user()->user_login;
		}

		// If stock status has changed then trigger the alert.
		if ( ( $old_stock_status && $new_stock_status ) && ( $old_stock_status !== $new_stock_status ) ) {
			$editor_link = $this->get_editor_link( $post );
			$this->plugin->alerts->Trigger(
				9018,
				array(
					'PostID'             => $post->ID,
					'ProductTitle'       => $product_title,
					'ProductStatus'      => ! $product_status ? $post->post_status : $product_status,
					'OldStatus'          => $this->get_stock_status( $old_stock_status ),
					'NewStatus'          => $this->get_stock_status( $new_stock_status ),
					'Username'           => $username,
					$editor_link['name'] => $editor_link['value'],
				)
			);
		}

		$wc_all_stock_changes = $this->plugin->GetGlobalBooleanSetting( 'wc-all-stock-changes', true );

		// If stock has changed then trigger the alert.
		if ( ( $old_stock !== $new_stock ) && ( $wc_all_stock_changes ) ) {
			$editor_link = $this->get_editor_link( $post );

			// Check if this was done via an order by looking for event 9035.
			// If so, we are going to add its data.
			$query = new WSAL_Models_OccurrenceQuery();
			$query->addOrderBy( 'created_on', true );
			$query->setLimit( 1 );
			$last_occurence = $query->getAdapter()->Execute( $query );
			if ( isset( $last_occurence[0] ) &&  9035 === $last_occurence[0]->alert_id ) {
				$latest_event = $this->plugin->alerts->get_latest_events();
				$latest_event = isset( $latest_event[0] ) ? $latest_event[0] : false;
				$event_meta   = $latest_event ? $latest_event->GetMetaArray() : false;
				$order_id     = isset( $event_meta['OrderID'] ) ? $event_meta['OrderID'] : false;
				$order_title  = isset( $event_meta['OrderTitle'] ) ? $event_meta['OrderTitle'] : false;
			} else {
				$order_id    = false;
				$order_title = false;
			}

			/**
			 * Event was changed from 9019 to 9105
			 *
			 * @since 4.0.3
			 */
			$this->plugin->alerts->Trigger(
				9105,
				array(
					'PostID'             => $post->ID,
					'ProductTitle'       => $product_title,
					'ProductStatus'      => ! $product_status ? $post->post_status : $product_status,
					'OldValue'           => ! empty( $old_stock ) ? $old_stock : 0,
					'NewValue'           => $new_stock,
					'Username'           => $username,
					'StockOrderID'       => $order_id,
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
	 * @return string
	 */
	private function get_stock_status( $slug ) {
		if ( 'instock' === $slug ) {
			return __( 'In stock', 'wp-security-audit-log' );
		} elseif ( 'outofstock' === $slug ) {
			return __( 'Out of stock', 'wp-security-audit-log' );
		} elseif ( 'onbackorder' === $slug ) {
			return __( 'On backorder', 'wp-security-audit-log' );
		}
	}

	/**
	 * Get editor link.
	 *
	 * @param WP_Post $post        - Product post object.
	 * @return array  $editor_link - Name and value link.
	 */
	private function get_editor_link( $post ) {
		// Meta value key.
		if ( 'shop_order' === $post->post_type ) {
			$name = 'EditorLinkOrder';
		} else {
			$name = 'EditorLinkProduct';
		}

		// Get editor post link URL.
		$value = get_edit_post_link( $post->ID );

		// If the URL is not empty then set values.
		if ( ! empty( $value ) ) {
			$editor_link = array(
				'name'  => $name, // Meta key.
				'value' => $value, // Meta value.
			);
		} else {
			// Get post object.
			$post = get_post( $post->ID );

			// Set URL action.
			if ( 'revision' === $post->post_type ) {
				$action = '';
			} else {
				$action = '&action=edit';
			}

			// Get and check post type object.
			$post_type_object = get_post_type_object( $post->post_type );
			if ( ! $post_type_object ) {
				return;
			}

			// Set editor link manually.
			if ( $post_type_object->_edit_link ) {
				$link = admin_url( sprintf( $post_type_object->_edit_link . $action, $post->ID ) );
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
	 * Formulate Order Title as done by WooCommerce.
	 *
	 * @param int|WC_Order $order - Order id or WC Order object.
	 * @return string
	 */
	private function get_order_title( $order ) {
		if ( ! $order ) {
			return false;
		}
		if ( is_int( $order ) ) {
			$order = new WC_Order( $order );
		}
		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		if ( $order->get_billing_first_name() || $order->get_billing_last_name() ) {
			$buyer = trim( sprintf( '%1$s %2$s', $order->get_billing_first_name(), $order->get_billing_last_name() ) );
		} elseif ( $order->get_billing_company() ) {
			$buyer = trim( $order->get_billing_company() );
		} elseif ( $order->get_customer_id() ) {
			$user  = get_user_by( 'id', $order->get_customer_id() );
			$buyer = ucwords( $user->display_name );
		}
		return '#' . $order->get_order_number() . ' ' . $buyer;
	}
}
