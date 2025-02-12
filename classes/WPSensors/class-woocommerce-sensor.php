<?php
/**
 * Sensor: WooCommerce
 *
 * WooCommerce sensor file.
 *
 * @package Wsal
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors;

use WC_Coupon;
use WC_Product;
use WSAL\Helpers\Settings_Helper;
use WSAL\Controllers\Alert_Manager;
use WSAL\WP_Sensors\Helpers\Woocommerce_Helper;
use WSAL\WP_Sensors_Helpers\WooCommerce_Sensor_Helper;
use WSAL\WP_Sensors_Helpers\WooCommerce_Sensor_Helper_Second;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Plugin_Sensors\WooCommerce_Sensor' ) ) {

	/**
	 * Support for WooCommerce Plugin.
	 *
	 * @package Wsal
	 */
	class WooCommerce_Sensor {

		/**
		 * WooCommerce Product Object.
		 *
		 * @var WC_Product
		 *
		 * @since 4.6.0
		 */
		private static $old_product = null;

		/**
		 * Old Post.
		 *
		 * @var \WP_Post
		 *
		 * @since 4.6.0
		 */
		private static $old_post = null;

		/**
		 * Old Order.
		 *
		 * @var \WC_Order
		 *
		 * @since 4.6.0
		 */
		private static $_old_order = null;

		/**
		 * Old Status.
		 *
		 * @var string
		 *
		 * @since 4.6.0
		 */
		private static $old_status = null;

		/**
		 * Old Post Link.
		 *
		 * @var string
		 *
		 * @since 4.6.0
		 */
		private static $_old_link = null;

		/**
		 * Old Post Categories.
		 *
		 * @var array
		 *
		 * @since 4.6.0
		 */
		private static $_old_cats = null;

		/**
		 * Old Product Data.
		 *
		 * @var array
		 *
		 * @since 4.6.0
		 */
		private static $old_data = null;

		/**
		 * New Product Data.
		 *
		 * @var array
		 *
		 * @since 4.6.0
		 */
		private static $new_data = null;

		/**
		 * Old Meta Product Data.
		 *
		 * @var array
		 *
		 * @since 4.6.0
		 */
		private static $_old_meta_data = null;

		/**
		 * Old Product attachment Data.
		 *
		 * @var array
		 *
		 * @since 4.6.0
		 */
		private static $_old_attachment_metadata = null;

		/**
		 * Old Attribute Data.
		 *
		 * @since 3.3.1
		 *
		 * @var stdClass
		 */
		private static $old_attr_data;

		/**
		 * Old store location data.
		 *
		 * @since 1.4.0
		 *
		 * @var string
		 */
		private static $old_location_data;

		/**
		 * Most recent store location data.
		 *
		 * @since 1.4.0
		 *
		 * @var string
		 */
		private static $new_location_data;

		/**
		 * Coupon Meta Data Keys.
		 *
		 * @since 3.3.1
		 *
		 * @var array
		 */
		private static $coupon_meta = array(
			'discount_type',
			'coupon_amount',
			'individual_use',
			'product_ids',
			'exclude_product_ids',
			'usage_limit',
			'usage_limit_per_user',
			'limit_usage_to_x_items',
			'usage_count',
			'date_expires',
			'expiry_date',
			'free_shipping',
			'product_categories',
			'exclude_product_categories',
			'exclude_sale_items',
			'minimum_amount',
			'maximum_amount',
			'customer_email',
		);

		/**
		 * WC User Meta.
		 *
		 * @since 3.4
		 *
		 * @var array
		 */
		private static $wc_user_meta = array();

		/**
		 * Stores $_REQUEST global variable data.
		 *
		 * @var array
		 *
		 * @since 4.6.0
		 */
		private static $request_data = array();

		/**
		 * That needs to be registered as a frontend sensor, when the admin sets the plugin to monitor the login from 3rd parties.
		 *
		 * @return boolean
		 *
		 * @since 4.5.1
		 */
		public static function is_frontend_sensor(): bool {
			$frontend_events = Settings_Helper::get_frontend_events();
			$should_load     = ( isset( $frontend_events['woocommerce'] ) && $frontend_events['woocommerce'] );

			if ( $should_load ) {
				return true;
			}

			return false;
		}

		/**
		 * Listening to events using WP hooks.
		 *
		 * @since 4.6.0
		 */
		public static function init() {
		}

		/**
		 * Loads all the plugin dependencies early.
		 *
		 * @return void
		 *
		 * @since 4.6.0
		 */
		public static function early_init() {
			add_filter(
				'wsal_event_objects',
				array( '\WSAL\WP_Sensors\Helpers\Woocommerce_Helper', 'wsal_woocommerce_extension_add_custom_event_objects' )
			);

			if ( Woocommerce_Helper::is_woocommerce_active() ) {
				add_filter(
					'wsal_save_settings_disabled_events',
					array(
						Woocommerce_Helper::class,
						'save_settings_disabled_events',
					),
					10,
					4
				);
				add_action(
					'init',
					function () {

						if ( current_user_can( 'edit_posts' ) ) {
							add_action( 'admin_init', array( __CLASS__, 'event_admin_init' ) );

						}
						\add_action( 'woocommerce_update_option', array( __CLASS__, 'woo_option_update' ) );

						\add_action( 'pre_post_update', array( __CLASS__, 'get_before_post_edit_data' ), 10, 2 );

						\add_action( 'woocommerce_before_order_object_save', array( __CLASS__, 'get_order_post_edit_data' ), 10, 2 );

						\add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'inline_product_changed' ), 10 );
						\add_action( 'woocommerce_after_order_object_save', array( __CLASS__, 'inline_product_changed' ), 10, 2 );
						add_action( 'delete_post', array( WooCommerce_Sensor_Helper::class, 'event_deleted' ), 10, 1 );
						add_action( 'woocommerce_before_delete_order', array( WooCommerce_Sensor_Helper::class, 'event_deleted' ), 10, 1 );
						add_action( 'wp_trash_post', array( WooCommerce_Sensor_Helper::class, 'event_trashed' ), 10, 1 );
						add_action( 'woocommerce_trash_order', array( WooCommerce_Sensor_Helper::class, 'event_trashed' ), 10, 1 );
						add_action( 'untrash_post', array( WooCommerce_Sensor_Helper::class, 'event_untrashed' ) );
						add_action( 'woocommerce_untrash_order', array( WooCommerce_Sensor_Helper::class, 'event_untrashed' ) );
						add_action( 'wp_head', array( WooCommerce_Sensor_Helper::class, 'viewing_product' ), 10 );
						add_action( 'create_product_cat', array( __CLASS__, 'event_category_creation' ), 10, 1 );
						add_filter( 'post_edit_form_tag', array( WooCommerce_Sensor_Helper_Second::class, 'editing_product' ), 10, 1 );
						add_action( 'woocommerce_order_status_changed', array( WooCommerce_Sensor_Helper_Second::class, 'event_order_status_changed' ), 10, 4 );
						add_action( 'woocommerce_order_refunded', array( WooCommerce_Sensor_Helper_Second::class, 'event_order_refunded' ), 10, 2 );
						add_action( 'woocommerce_bulk_action_ids', array( WooCommerce_Sensor_Helper_Second::class, 'event_bulk_order_actions' ), 10, 2 );
						add_action( 'woocommerce_attribute_added', array( WooCommerce_Sensor_Helper_Second::class, 'event_attribute_added' ), 10, 2 );
						add_action( 'woocommerce_before_attribute_delete', array( WooCommerce_Sensor_Helper_Second::class, 'event_attribute_deleted' ), 10, 3 );
						add_action( 'woocommerce_attribute_updated', array( WooCommerce_Sensor_Helper_Second::class, 'event_attribute_updated' ), 10, 3 );
						add_action( 'wp_update_term_data', array( WooCommerce_Sensor_Helper_Second::class, 'event_product_cat_updated' ), 10, 4 );
						add_action( 'update_term_meta', array( WooCommerce_Sensor_Helper_Second::class, 'event_cat_display_updated' ), 10, 4 );
						add_action( 'delete_product_cat', array( WooCommerce_Sensor_Helper_Second::class, 'event_product_cat_deleted' ), 10, 4 );
						add_action( 'delete_product_tag', array( WooCommerce_Sensor_Helper_Second::class, 'event_product_tag_deleted' ), 10, 4 );
						add_action( 'wsal_before_post_meta_create_event', array( WooCommerce_Sensor_Helper_Second::class, 'log_coupon_meta_created_event' ), 10, 4 );
						add_action( 'wsal_before_post_meta_update_event', array( WooCommerce_Sensor_Helper_Second::class, 'log_coupon_meta_update_events' ), 10, 5 );
						add_action( 'wsal_before_post_meta_delete_event', array( WooCommerce_Sensor_Helper_Second::class, 'log_coupon_meta_delete_event' ), 10, 4 );
						add_action( 'update_user_meta', array( WooCommerce_Sensor_Helper_Second::class, 'before_wc_user_meta_update' ), 10, 3 );
						add_action( 'added_user_meta', array( WooCommerce_Sensor_Helper_Second::class, 'wc_user_meta_updated' ), 10, 4 );
						add_action( 'updated_user_meta', array( WooCommerce_Sensor_Helper_Second::class, 'wc_user_meta_updated' ), 10, 4 );
						add_action( 'woocommerce_before_product_object_save', array( WooCommerce_Sensor_Helper_Second::class, 'check_product_changes_before_save' ), 10, 2 );
						add_action( 'woocommerce_after_product_object_save', array( __CLASS__, 'check_product_changes_after_save' ), 10, 1 );
						add_action( 'woocommerce_product_quick_edit_save', array( __CLASS__, 'inline_product_changed' ), 10, 1 );
						add_action( 'updated_option', array( WooCommerce_Sensor_Helper::class, 'settings_updated' ), 10, 3 );
						add_action( 'create_product_tag', array( __CLASS__, 'event_tag_creation' ), 10, 1 );
						add_action( 'update_postmeta', array( WooCommerce_Sensor_Helper_Second::class, 'detect_stock_level_change' ), 10, 4 );
						add_action( 'woocommerce_before_shipping_zone_object_save', array( __CLASS__, 'detect_shipping_zone_change' ), 10, 2 );
						add_action( 'woocommerce_new_webhook', array( __CLASS__, 'webhook_added' ), 10, 2 );
						add_action( 'woocommerce_webhook_deleted', array( __CLASS__, 'webhook_deleted' ), 10, 2 );
						add_action( 'woocommerce_before_shipping_zone_object_save', array( __CLASS__, 'detect_shipping_zone_change' ), 10, 2 );

						// Orders.
						add_action( 'woocommerce_new_order_item', array( WooCommerce_Sensor_Helper_Second::class, 'event_order_items_added' ), 10, 3 );
						add_action( 'woocommerce_before_delete_order_item', array( WooCommerce_Sensor_Helper_Second::class, 'event_order_items_removed' ), 10, 1 );
						add_action( 'woocommerce_before_save_order_items', array( WooCommerce_Sensor_Helper_Second::class, 'event_order_items_quantity_changed' ), 10, 2 );
						add_action( 'woocommerce_refund_deleted', array( WooCommerce_Sensor_Helper_Second::class, 'event_order_refund_removed' ), 10, 2 );
						add_action( 'admin_action_edit', array( __CLASS__, 'order_opened_for_editing' ), 10 );

						add_action( 'woocommerce_page_wc-orders', array( __CLASS__, 'orders_actions' ) );

						add_action( 'woocommerce_before_data_object_save', array( __CLASS__, 'data_store' ), 10, 2 );

						\add_action( 'woocommerce_coupon_options_save', array( __CLASS__, 'inline_product_changed' ), 10 );

						\add_action( 'woocommerce_order_note_deleted', array( __CLASS__, 'order_note_deleted' ), 10, 2 );
						// \add_action( 'woocommerce_new_customer_note', array( __CLASS__, 'new_customer_note' ), 10, 2 );
						\add_action( 'woocommerce_order_note_added', array( __CLASS__, 'order_note_added' ), 10, 2 );

						\add_action( 'woocommerce_product_duplicate', array( __CLASS__, 'duplicate_product_action' ), 10, 2 );
					},
					10,
					2
				);
				/**
				 * Add our filters.
				 */
				add_filter(
					'wsal_event_type_data',
					array( Woocommerce_Helper::class, 'wsal_woocommerce_extension_add_custom_event_type' ),
					10,
					2
				);
				add_filter(
					'wsal_format_custom_meta',
					array( Woocommerce_Helper::class, 'wsal_woocommerce_extension_add_custom_meta_format' ),
					10,
					4
				);
				add_filter(
					'wsal_ignored_custom_post_types',
					array( Woocommerce_Helper::class, 'wsal_woocommerce_extension_add_custom_ignored_cpt' )
				);
				add_filter(
					'wsal_load_public_sensors',
					array( Woocommerce_Helper::class, 'wsal_woocommerce_extension_load_public_sensors' )
				);
				add_filter(
					'wsal_togglealerts_sub_category_events',
					array( Woocommerce_Helper::class, 'wsal_woocommerce_extension_togglealerts_sub_category_events' )
				);
				add_filter(
					'wsal_togglealerts_sub_category_titles',
					array( Woocommerce_Helper::class, 'wsal_woocommerce_extension_togglealerts_sub_category_titles' ),
					10,
					2
				);
				add_action(
					'wsal_togglealerts_append_content_to_toggle',
					array( Woocommerce_Helper::class, 'wsal_woocommerce_extension_append_content_to_toggle' )
				);
				add_action(
					'wsal_togglealerts_process_save_settings',
					array( Woocommerce_Helper::class, 'wsal_woocommerce_extension_togglealerts_process_save_settings' ),
					10,
					1
				);
				add_filter(
					'wsal_togglealerts_obsolete_events',
					array( Woocommerce_Helper::class, 'wsal_woocommerce_extension_togglealerts_obsolete_events' )
				);

				add_action(
					'admin_footer',
					array( Woocommerce_Helper::class, 'wsal_woocommerce_extension_togglealerts_js_code' )
				);

				// Special events.
				add_action(
					'woocommerce_download_product',
					array( Woocommerce_Helper::class, 'wsal_woocommerce_extension_detect_file_download' ),
					10,
					6
				);

			}
		}

		/**
		 * Called on WOO options update. Checks if the current is a payment gateway settings update. If it does, it adds a filter to listen to changes in the gateway settings and logs them.
		 *
		 * @param array $option_array - Options to update.
		 *
		 * @return void
		 *
		 * @since 5.3.0
		 */
		public static function woo_option_update( $option_array ) {

			if ( ! is_array( $option_array ) || empty( $option_array ) || ! isset( $option_array['id'] ) ) {
				return;
			}

			$payment_gateways = \WC()->payment_gateways->payment_gateways();

			if ( ! empty( $payment_gateways ) ) {
				// Get gateway ID.
				$gateway_id = $option_array['id'];
				foreach ( $payment_gateways as $gateway ) {

					// Check if the gateway id matches any available gateway ids.
					// The ID provided is in format woocommerce_{gateway_id}_settings. So the check is made by adding the prefix and suffix.
					if ( 'woocommerce_' . $gateway->id . '_settings' !== $gateway_id ) {
						continue;
					} else {
						\add_filter( 'woocommerce_settings_api_sanitized_fields_' . $gateway->id, array( __CLASS__, 'gateway_changes' ) );
					}
				}
			}
		}

		/**
		 * That monitors gateway changes - unfortunately we don't have the gateway ID, so we are using the global name set by WOO and called $current_section.
		 *
		 * @param array $gateway_settings - The new settings for the gateway.
		 *
		 * @return array $gateway_settings - The sanitized settings for the gateway.
		 *
		 * @since 5.3.0
		 */
		public static function gateway_changes( $gateway_settings ) {
			global $current_tab, $current_section;

			// Get old gateway settings.
			$old_gateway_settings = (array) WooCommerce_Sensor_Helper_Second::get_config( $current_section . '_settings' );

			$old_gateway_status = isset( $old_gateway_settings['enabled'] ) ? $old_gateway_settings['enabled'] : false;
			$new_gateway_status = isset( $gateway_settings['enabled'] ) ? $gateway_settings['enabled'] : false;

			// Check status change.
			if ( $old_gateway_status !== $new_gateway_status ) {
				// Gateway enabled.
				Alert_Manager::trigger_event(
					9074,
					array(
						'GatewayID'   => sanitize_text_field( $current_section ),
						'GatewayName' => isset( $gateway_settings['title'] ) ? $gateway_settings['title'] : false,
						'EventType'   => 'yes' === $new_gateway_status ? 'enabled' : 'disabled',
					)
				);
			}

			return $gateway_settings;
		}

		/**
		 * Triggers when Order note (comment) is added
		 *
		 * @param int       $note_id - The note ID.
		 * @param \WC_Order $order - The order object.
		 *
		 * @return void
		 *
		 * @since 5.1.0
		 */
		public static function order_note_added( $note_id, $order ) {
			$edit_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $order );
			$note      = \wc_get_order_note( $note_id );

			$comment = \get_comment( $note_id );

			$user = \get_user_by( 'email', $comment->comment_author_email );

			Alert_Manager::trigger_event(
				9155,
				array(
					'OrderID'          => \esc_attr( $note->order_id ),
					'OrderTitle'       => \sanitize_text_field( Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $order ) ),
					'OrderStatus'      => \wc_get_order_status_name( $order->get_status() ),
					'OrderStatusSlug'  => $order->get_status(),
					'PostStatus'       => 'wc-' . $order->get_status(),
					$edit_link['name'] => $edit_link['value'],
					'NoteID'           => $note_id,
					'NoteType'         => ( $note->customer_note ) ? __( 'Customer note', 'wp-security-audit-log' ) : __( 'Private', 'wp-security-audit-log' ),
					'Content'          => $note->content,
					'CurrentUserID'    => ( is_a( $user, '\WP_User' ) ) ? $user->ID : 0,
					'Username'         => ( is_a( $user, '\WP_User' ) ) ? $user->user_login : 'WooCommerce System',
				)
			);
		}

		/**
		 * Triggers when product is duplicated.
		 *
		 * @param \WC_Product $duplicate - Duplicated product.
		 * @param \WC_Product $product - Original product.
		 *
		 * @return void
		 *
		 * @since 5.3.0
		 */
		public static function duplicate_product_action( $duplicate, $product ) {
			$editor_link = WooCommerce_Public_Sensor::get_editor_link( $duplicate );
			Alert_Manager::trigger_event(
				9138,
				array(
					'ProductTitle'          => $duplicate->get_title(),
					'ProductUrl'            => get_post_permalink( $duplicate->get_id() ),
					'ProductID'             => $duplicate->get_id(),
					'SKU'                   => $duplicate->get_sku(),
					'ProductStatus'         => $duplicate->get_status(),
					'PostStatus'            => $duplicate->get_status(),
					$editor_link['name']    => $editor_link['value'],
					'OriginalProductTitle'  => $product->get_title(),
					'OriginalProductUrl'    => get_post_permalink( $product->get_id() ),
					'OriginalProductID'     => $product->get_id(),
					'OriginalSKU'           => $product->get_sku(),
					'OriginalProductStatus' => $product->get_status(),
					'OriginalPostStatus'    => $product->get_status(),
				)
			);
		}

		/**
		 * Triggers when Order note (comment) is deleted
		 *
		 * @param int         $note_id - The note ID.
		 * @param \WP_Comment $note - The note object.
		 *
		 * @return void
		 *
		 * @since 5.1.0
		 */
		public static function order_note_deleted( $note_id, $note ) {
			$order     = \wc_get_order( $note->order_id ); // Get order post object.
			$edit_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $order );

			Alert_Manager::trigger_event(
				9156,
				array(
					'OrderID'          => \esc_attr( $note->order_id ),
					'OrderTitle'       => \sanitize_text_field( Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $order ) ),
					'OrderStatus'      => \wc_get_order_status_name( $order->get_status() ),
					'OrderStatusSlug'  => $order->get_status(),
					'PostStatus'       => 'wc-' . $order->get_status(),
					$edit_link['name'] => $edit_link['value'],
					'NoteID'           => $note_id,
					'NoteType'         => ( $note->customer_note ) ? __( 'Customer note', 'wp-security-audit-log' ) : __( 'Private', 'wp-security-audit-log' ),
					'Content'          => $note->content,
				)
			);
		}

		/**
		 * Called when Woo saves data (update)
		 *
		 * @param \WC_Webhook    $wc_data - The modified webhook.
		 * @param \WC_Data_Store $data_store - The Woo data store.
		 *
		 * @return void
		 *
		 * @since 4.6.0
		 */
		public static function data_store( $wc_data, $data_store ) {
			if ( 'WC_Webhook_Data_Store' === $data_store->get_current_class_name() ) {
				if ( $wc_data->get_id() ) {
					$changes = $wc_data->get_changes();
					if ( ! empty( $changes ) ) {

						$old_webhook = wc_get_webhook( $wc_data->get_id() );

						$editor_link = Woocommerce_Helper::create_webhook_editor_link( $wc_data->get_id() );
						Alert_Manager::trigger_event(
							9122,
							array(
								'HookName'          => $wc_data->get_name( 'edit' ),
								'OldHookName'       => $old_webhook->get_name(),
								'DeliveryURL'       => $wc_data->get_delivery_url( 'edit' ),
								'OldDeliveryURL'    => $old_webhook->get_delivery_url(),
								'Topic'             => $wc_data->get_topic( 'edit' ),
								'OldTopic'          => $old_webhook->get_topic(),
								'Status'            => $wc_data->get_status( 'edit' ),
								'OldStatus'         => $old_webhook->get_status(),
								'Secret'            => $wc_data->get_secret( 'edit' ),
								'OldSecret'         => $old_webhook->get_secret(),
								'EditorLinkWebhook' => $editor_link,
							)
						);
					}
				}
			}
		}

		/**
		 * Covers order actions
		 *
		 * @return void
		 *
		 * @since 4.6.0
		 */
		public static function orders_actions() {

			if ( isset( $_GET['action'] ) ) {
				if ( 'edit' === \sanitize_text_field( \wp_unslash( $_GET['action'] ) ) && isset( $_GET['id'] ) && 0 < (int) $_GET['id'] ) {
					// Get post.
					$post = wc_get_order( \sanitize_text_field( \wp_unslash( $_GET['id'] ) ) );

					// Log event.
					if ( $post ) {
						self::order_opened_in_editor( $post );
					}
				}
			}
		}

		/**
		 * Alert for Editing of Posts and Custom Post Types in Gutenberg.
		 *
		 * @since 3.2.4
		 */
		public static function order_opened_for_editing() {
			global $pagenow;

			if ( 'post.php' !== $pagenow ) {
				return;
			}

			$post_id = isset( $_GET['post'] ) ? (int) sanitize_text_field( wp_unslash( $_GET['post'] ) ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			// Check post id.
			if ( empty( $post_id ) ) {
				return;
			}

			if ( is_user_logged_in() && is_admin() ) {
				// Get post.
				$post = wc_get_order( $post_id );

				// Log event.
				if ( $post ) {
					self::order_opened_in_editor( $post );
				}
			}
		}

		/**
		 * Order Opened for Editing in WP Editors.
		 *
		 * @param WP_Post $post – Post object.
		 *
		 * @since 4.6.0
		 */
		private static function order_opened_in_editor( $post ) {
			if ( empty( $post ) ) {
				return;
			}

			$current_path = isset( $_SERVER['SCRIPT_NAME'] ) ? esc_url_raw( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) . '?post=' . $post->get_id() : false;
			$referrer     = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : false;

			// Check referrer URL.
			if ( ! empty( $referrer ) ) {
				// Parse the referrer.
				$parsed_url = wp_parse_url( $referrer );

				// If the referrer is post-new then we can ignore this one.
				if ( isset( $parsed_url['path'] ) && 'post-new' === basename( $parsed_url['path'], '.php' ) ) {
					return $post;
				}
			}

			if ( ! empty( $referrer ) && strpos( $referrer, $current_path ) !== false ) {
				// Ignore this if we were on the same page so we avoid double audit entries.
				return $post;
			}

			if ( ! Alert_Manager::was_triggered_recently( 9154 ) ) {
				$event       = 9154;
				$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $post );

				Alert_Manager::trigger_event(
					$event,
					array(
						'PostID'             => $post->get_id(),
						'PostTitle'          => Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $post->get_id() ),
						'PostStatus'         => \wc_get_order_status_name( $post->get_status() ),
						'PostStatusSlug'     => $post->get_status(),
						'PostUrl'            => get_permalink( $post->get_id() ),
						$editor_link['name'] => $editor_link['value'],
					)
				);
			}
		}

		/**
		 * Detect changes made directly to WC product data.
		 *
		 * @param  WC_Product $product - WC porduct object.
		 * @return void
		 *
		 * @since 4.6.0
		 */
		public static function check_product_changes_after_save( $product ) {
			// We know this hook is only fired when updating, but we need to be sure this is an automatic (not post edit etc) change.
			if ( ! isset( $_POST['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$product_data = WooCommerce_Sensor_Helper_Second::get_product_data( $product );
				if ( empty( $product_data ) ) {
					return;
				}

				self::$new_data   = $product_data;
				$old_product_data = self::$old_data;

				if ( empty( $old_product_data ) ) {
					return;
				}

				$values_to_lookup = array(
					'sku',
					'stock_status',
					'stock',
					'tax_status',
					'weight',
					'regular_price',
					'sale_price',
				);

				foreach ( $values_to_lookup as $lookup_key ) {
					if ( isset( self::$new_data[ $lookup_key ] ) && $old_product_data[ $lookup_key ] !== self::$new_data[ $lookup_key ] || isset( self::$old_data[ $lookup_key ] ) && self::$new_data[ $lookup_key ] !== $old_product_data[ $lookup_key ] ) {
						if ( 'regular_price' === $lookup_key || 'sale_price' === $lookup_key ) {
							WooCommerce_Sensor_Helper::check_price_change( self::$old_post );
						} elseif ( 'stock_status' === $lookup_key ) {
							WooCommerce_Sensor_Helper::check_stock_status_change( self::$old_post );
						} elseif ( 'stock' === $lookup_key ) {
							WooCommerce_Sensor_Helper::check_stock_quantity_change( self::$old_post );
						} elseif ( 'sku' === $lookup_key ) {
							WooCommerce_Sensor_Helper::check_sku_change( self::$old_post );
						} elseif ( 'weight' === $lookup_key ) {
							WooCommerce_Sensor_Helper::check_weight_change( self::$old_post );
						} elseif ( 'tax_status' === $lookup_key ) {
							WooCommerce_Sensor_Helper_Second::check_tax_status_change( self::$old_post, self::$_old_meta_data, self::$new_data );
						}
					}
				}
			}
		}

		/**
		 * Trigger alert when new webhook is added.
		 *
		 * @param  int    $webhook_id - WC Webhook ID.
		 * @param  object $webhook - WC Webhook data.
		 * @return int    $webhook_id
		 *
		 * @since 4.6.0
		 */
		public static function webhook_added( $webhook_id, $webhook ) {
			$editor_link = Woocommerce_Helper::create_webhook_editor_link( $webhook_id );
			Alert_Manager::trigger_event(
				9120,
				array(
					'HookName'          => sanitize_text_field( $webhook->get_name() ),
					'DeliveryURL'       => sanitize_text_field( $webhook->get_delivery_url() ),
					'Topic'             => sanitize_text_field( $webhook->get_topic() ),
					'Status'            => sanitize_text_field( $webhook->get_status() ),
					'EditorLinkWebhook' => $editor_link,
				)
			);
			return $webhook_id;
		}

		/**
		 * Trigger alert when new webhook is deleted.
		 *
		 * @param  int    $webhook_id - WC Webhook ID.
		 * @param  object $webhook    - WC Webhook data.
		 * @return int    $webhook_id
		 *
		 * @since 4.6.0
		 */
		public static function webhook_deleted( $webhook_id, $webhook ) {
			Alert_Manager::trigger_event(
				9121,
				array(
					'HookName'    => sanitize_text_field( $webhook->get_name() ),
					'DeliveryURL' => sanitize_text_field( $webhook->get_delivery_url() ),
					'Topic'       => sanitize_text_field( $webhook->get_topic() ),
					'Status'      => sanitize_text_field( $webhook->get_status() ),
				)
			);
			return $webhook_id;
		}

		/**
		 * Trigger 9082 when a shipping zone is created or modified.
		 *
		 * @param WC_Shipping_Zone                      $instance - Zone object.
		 * @param WC_Shipping_Zone_Data_Store_Interface $this_data_store - WC Data store.
		 *
		 * @return object $instance - Zone object.
		 *
		 * @since 4.6.0
		 */
		public static function detect_shipping_zone_change( $instance, $this_data_store ) {
			$zone_name = $instance->get_zone_name();
			Alert_Manager::trigger_event(
				9082,
				array(
					'EventType'        => $instance->get_id() ? 'modified' : 'created',
					'ShippingZoneName' => sanitize_text_field( $zone_name ),
				)
			);

			return $instance;
		}

		/**
		 * Trigger inline product change events.
		 *
		 * @param WC_Product $product - WooCommerce product.
		 *
		 * @since 4.6.0
		 */
		public static function inline_product_changed( $product ) {
			unset( self::$request_data['woocommerce_quick_edit'] );
			if ( ! isset( self::$old_post ) && ! isset( self::$_old_order ) ) {
				return;
			}
			if ( is_int( $product ) ) {
				$product = get_post( $product );

				if ( 'shop_coupon' === $product->post_type ) {
					$product = new WC_Coupon( $product->ID );
				} else {
					$product = new WC_Product( $product->ID );
				}
			}

			self::event_changed( $product, true );
		}

		/**
		 * Triggered when a user accesses the admin area.
		 *
		 * @since 4.6.0
		 */
		public static function event_admin_init() {
			WooCommerce_Sensor_Helper::check_settings_change();
			WooCommerce_Sensor_Helper_Second::retrieve_attribute_data();
			WooCommerce_Sensor_Helper_Second::check_wc_ajax_change_events();
		}


		/**
		 * Retrieve Old data.
		 *
		 * @param integer $order - WOO Order.
		 *
		 * @since 5.3.0
		 */
		public static function get_order_post_edit_data( $order ) {
			if ( \is_object( $order ) && ( $order instanceof \WC_Order || $order instanceof \WC_Order_Refund ) ) {
				self::$_old_order = $order;
			}
		}

		/**
		 * Retrieve Old data.
		 *
		 * @param integer $post_id - Product ID.
		 *
		 * @since 4.6.0
		 */
		public static function get_before_post_edit_data( $post_id ) {
			$post_id  = (int) $post_id; // Making sure that the post id is integer.
			$post     = get_post( $post_id ); // Get post.
			$thumb_id = get_post_thumbnail_id( $post->ID );

			if ( ! empty( $post ) && $post instanceof \WP_Post && in_array( $post->post_type, array( 'product', 'shop_order', 'shop_coupon' ), true ) ) {
				self::$old_post    = $post;
				self::$old_product = 'product' === $post->post_type ? wc_get_product( $post->ID ) : null;
				self::$_old_order  = 'shop_order' === $post->post_type ? wc_get_order( $post->ID ) : null;
				self::$old_status  = $post->post_status;
				self::$_old_link   = get_post_permalink( $post_id, false, true );
				self::$_old_cats   = 'product' === $post->post_type ? WooCommerce_Sensor_Helper::get_product_categories( self::$old_post ) : null;
				self::$old_data    = 'product' === $post->post_type ? WooCommerce_Sensor_Helper_Second::get_product_data( self::$old_product ) : null;
				// self::$old_product_attributes   = get_post_meta( $post->ID, '_product_attributes' );
				self::$_old_meta_data           = get_post_meta( $post->ID, '', false );
				self::$_old_attachment_metadata = wp_get_attachment_metadata( $thumb_id );
			}
		}

		/**
		 * Checks if the product update is inline-edit or not.
		 *
		 * @return bool
		 *
		 * @since 4.6.0
		 */
		public static function check_inline_edit() {
			if ( empty( self::$request_data ) ) {
				self::$request_data = $_REQUEST; // phpcs:ignore
			}
			return ! empty( self::$request_data['woocommerce_quick_edit'] );
		}

		/**
		 * WooCommerce Product Updated.
		 *
		 * @param WP_Post $post    - WC Product CPT object.
		 * @param integer $update  - True if product update, false if product is new.
		 *
		 * @since 4.6.0
		 */
		public static function event_changed( $post, $update ) {
			if ( ! $update ) {
				self::event_creation( self::$old_post, $post );
				return;
			}

			if ( ! isset( $post ) ) {
				return;
			}

			$object = $post;

			$post = get_post( $post->get_id() );

			if ( 'product' === $post->post_type ) {
				if (
					( isset( self::$old_post ) && 'auto-draft' === self::$old_post->post_status && 'draft' === $post->post_status ) // Saving draft.
						|| isset( self::$old_post ) && ( 'draft' === self::$old_post->post_status && 'publish' === $post->post_status ) // Publishing post.
						|| isset( self::$old_post ) && ( 'auto-draft' === self::$old_post->post_status && 'publish' === $post->post_status )
					) {
					self::event_creation( self::$old_post, $post );
				} else {

					// Get new woocommerce product object.
					$new_product    = $object;
					self::$new_data = WooCommerce_Sensor_Helper_Second::get_product_data( $new_product );
					if ( empty( self::$new_data ) ) {
						return;
					}

					$changes = 0;
					$changes = WooCommerce_Sensor_Helper::check_categories_change( self::$_old_cats, WooCommerce_Sensor_Helper::get_product_categories( $post ), self::$old_post, $post )
						+ WooCommerce_Sensor_Helper::check_short_description_change( self::$old_post, $post )
						+ WooCommerce_Sensor_Helper::check_text_change( self::$old_post, $post )
						+ WooCommerce_Sensor_Helper::check_date_change( self::$old_post, $post )
						+ WooCommerce_Sensor_Helper::check_visibility_change( self::$old_post, $post )
						+ WooCommerce_Sensor_Helper::check_status_change( self::$old_post, $post )
						+ WooCommerce_Sensor_Helper::check_title_change( self::$old_post, $post )
						+ WooCommerce_Sensor_Helper::check_product_type_change( self::$old_post )
						+ WooCommerce_Sensor_Helper::check_catalog_visibility_change( self::$old_post )
						+ WooCommerce_Sensor_Helper::check_featured_product( self::$old_post )
						+ WooCommerce_Sensor_Helper::check_price_change( self::$old_post )
						+ WooCommerce_Sensor_Helper::check_sku_change( self::$old_post )
						+ WooCommerce_Sensor_Helper::check_stock_status_change( self::$old_post )
						+ WooCommerce_Sensor_Helper::check_sold_individualy_change( self::$old_product )
						+ WooCommerce_Sensor_Helper::check_stock_quantity_change( self::$old_post )
						+ WooCommerce_Sensor_Helper::check_type_change( self::$old_post, $post )
						+ WooCommerce_Sensor_Helper::check_weight_change( self::$old_post )
						+ WooCommerce_Sensor_Helper::check_dimensions_change( self::$old_post )
						+ WooCommerce_Sensor_Helper::check_downloadable_file_change( self::$old_post )
						+ WooCommerce_Sensor_Helper::check_backorders_setting( self::$old_post )
						+ WooCommerce_Sensor_Helper::check_upsells_change( self::$old_post )
						+ WooCommerce_Sensor_Helper::check_cross_sell_change( self::$old_post )
						+ WooCommerce_Sensor_Helper_Second::check_attributes_change( self::$old_post )
						+ WooCommerce_Sensor_Helper_Second::check_image_change( self::$old_post )
						+ WooCommerce_Sensor_Helper_Second::check_download_limit_change( self::$_old_meta_data )
						+ WooCommerce_Sensor_Helper_Second::check_tax_status_change( self::$old_post, self::$_old_meta_data, self::$new_data )
						+ WooCommerce_Sensor_Helper_Second::check_low_stock_threshold_change( self::$old_post, self::$_old_meta_data, self::$new_data );

					if ( ! $changes ) {
						// Change Permalink.
						$changes = WooCommerce_Sensor_Helper::check_permalink_change( self::$_old_link, get_post_permalink( $post->ID, false, true ), $post );
						if ( ! $changes ) {
							// If no one of the above changes happen.
							WooCommerce_Sensor_Helper::check_modify_change( self::$old_post, $post );
						}
					}
				}
			} elseif ( 'shop_order' === $post->post_type ) {
				// Check order events.
				WooCommerce_Sensor_Helper_Second::check_order_modify_change( $post->ID, self::$old_post, $post );
			} elseif ( 'shop_order_placehold' === $post->post_type ) {
				// Check order events.
				WooCommerce_Sensor_Helper_Second::check_order_modify_change( $post->ID, self::$_old_order, $post );
			} elseif ( 'shop_coupon' === $post->post_type ) {
				if (
					( isset( self::$old_post ) && 'auto-draft' === self::$old_post->post_status && 'draft' === $post->post_status ) // Saving draft.
						|| isset( self::$old_post ) && ( 'draft' === self::$old_post->post_status && 'publish' === $post->post_status ) // Publishing post.
						|| isset( self::$old_post ) && ( 'auto-draft' === self::$old_post->post_status && 'publish' === $post->post_status )
					) {
					self::event_creation( self::$old_post, $post );
				} else {

					WooCommerce_Sensor_Helper::check_short_description_change( self::$old_post, $post );
					WooCommerce_Sensor_Helper::check_status_change( self::$old_post, $post );
					WooCommerce_Sensor_Helper::check_title_change( self::$old_post, $post );
					WooCommerce_Sensor_Helper::check_date_change( self::$old_post, $post );
					WooCommerce_Sensor_Helper::check_visibility_change( self::$old_post, $post );

				}
			}
		}

		/**
		 * Return Coupon Event Data.
		 *
		 * @since 3.3.1
		 *
		 * @param WP_Post $coupon - Coupon event data.
		 * @return array
		 */
		public static function get_coupon_event_data( $coupon ) {
			if ( empty( $coupon ) || ! $coupon instanceof \WP_Post ) {
				return array();
			}

			$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $coupon );
			return array(
				'CouponID'           => $coupon->ID,
				'CouponName'         => $coupon->post_title,
				'CouponStatus'       => $coupon->post_status,
				'PostStatus'         => $coupon->post_status,
				'CouponExcerpt'      => $coupon->post_excerpt,
				$editor_link['name'] => $editor_link['value'],
			);
		}

		/**
		 * WooCommerce Product/Coupon Created.
		 *
		 * Trigger events 9000, 9001, 9063.
		 *
		 * @param object $old_post - Old Post.
		 * @param object $new_post - New Post.
		 *
		 * @since 4.6.0
		 */
		private static function event_creation( $old_post, $new_post ) {
			if ( ! $old_post instanceof \WP_Post || ! $new_post instanceof \WP_Post ) {
				return;
			}

			if ( 'product' === $old_post->post_type ) {
				$editor_link = WooCommerce_Sensor_Helper_Second::get_editor_link( $new_post );
				if ( 'publish' === $new_post->post_status ) {
					Alert_Manager::trigger_event(
						9001,
						array(
							'ProductTitle'       => sanitize_text_field( $new_post->post_title ),
							'ProductUrl'         => get_post_permalink( $new_post->ID ),
							'PostID'             => esc_attr( $new_post->ID ),
							'SKU'                => esc_attr( self::get_product_sku( $new_post->ID ) ),
							'ProductStatus'      => sanitize_text_field( $new_post->post_status ),
							'PostStatus'         => sanitize_text_field( $new_post->post_status ),
							$editor_link['name'] => $editor_link['value'],
						)
					);
					return 1;
				} else {
					Alert_Manager::trigger_event(
						9000,
						array(
							'ProductTitle'       => sanitize_text_field( $new_post->post_title ),
							'PostID'             => esc_attr( $new_post->ID ),
							'ProductStatus'      => sanitize_text_field( $new_post->post_status ),
							'PostStatus'         => sanitize_text_field( $new_post->post_status ),
							'SKU'                => esc_attr( self::get_product_sku( $new_post->ID ) ),
							$editor_link['name'] => $editor_link['value'],
						)
					);
					return 1;
				}
			} elseif ( 'shop_coupon' === $old_post->post_type ) {
				$coupon_data = self::get_coupon_event_data( $new_post );
				Alert_Manager::trigger_event(
					9063,
					$coupon_data
				);
				return 1;
			}
			return 0;
		}

		/**
		 * Get a SKU for a given product ID.
		 *
		 * @param  int $product_id - Id to lookup.
		 *
		 * @return int|string - Result.
		 *
		 * @since 4.6.0
		 */
		public static function get_product_sku( $product_id ) {
			$product = wc_get_product( $product_id );
			// If this is not an object, return.
			if ( ! is_object( $product ) ) {
				return;
			}
			$sku = $product->get_sku();

			if ( isset( $_POST['variable_sku'] ) && isset( $_POST['variable_sku'][0] ) && ! empty( $_POST['variable_sku'][0] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$sku = sanitize_text_field( \wp_unslash( $_POST['variable_sku'][0] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}

			/**
			 * For some reason the above could return empty string even if there is a SKU.
			 * Happens when product is created (first time) or when product is in draft but the user want to use preview option.
			 * Usually in that case the correct SKU is in the post array, so that code tries to extract that data from there - more debugging is needed here probably?
			 */
			if ( empty( $sku ) && isset( $_POST['_sku'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$sku = sanitize_text_field( \wp_unslash( $_POST['_sku'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			} elseif ( empty( $sku ) ) {
				$sku = __( 'Not provided', 'wp-security-audit-log' );
			}

			return $sku;
		}

		/**
		 * Trigger events 9002
		 *
		 * @param int|WP_Term $term_id - Term ID.
		 *
		 * @since 4.6.0
		 */
		public static function event_category_creation( $term_id = null ) {
			$term = get_term( $term_id );
			if ( ! empty( $term ) ) {
				Alert_Manager::trigger_event(
					9002,
					array(
						'CategoryName'   => sanitize_text_field( $term->name ),
						'Slug'           => sanitize_text_field( $term->slug ),
						'ProductCatLink' => Woocommerce_Helper::get_taxonomy_edit_link( $term_id ),
					)
				);
			}
		}

		/**
		 * Trigger events 9001
		 *
		 * @param int|WP_Term $term_id - Term ID.
		 *
		 * @since 4.6.0
		 */
		public static function event_tag_creation( $term_id = null ) {
			$term = get_term( $term_id );
			if ( ! empty( $term ) ) {
				Alert_Manager::trigger_event(
					9101,
					array(
						'CategoryName'   => sanitize_text_field( $term->name ),
						'Slug'           => sanitize_text_field( $term->slug ),
						'ProductTagLink' => Woocommerce_Helper::get_taxonomy_edit_link( $term_id, 'product_tag' ),
					)
				);
			}
		}

		/**
		 * Class property getter
		 *
		 * @return array
		 *
		 * @since 5.3.0
		 */
		public static function get_old_data() {
			return self::$old_data;
		}

		/**
		 * Class property setter
		 *
		 * @param array $old_data - The array with data.
		 *
		 * @return void
		 *
		 * @since 5.3.0
		 */
		public static function set_old_data( $old_data ) {
			self::$old_data = $old_data;
		}

		/**
		 * Class property getter
		 *
		 * @return array
		 *
		 * @since 5.3.0
		 */
		public static function get_new_data() {
			return self::$new_data;
		}

		/**
		 * Class property getter
		 *
		 * @return stdClass
		 *
		 * @since 5.3.0
		 */
		public static function get_old_attr_data() {
			return self::$old_attr_data;
		}

		/**
		 * Class property setter
		 *
		 * @param array $old_attr_data - The array with data.
		 *
		 * @return void
		 *
		 * @since 5.3.0
		 */
		public static function set_old_attr_data( $old_attr_data ) {
			self::$old_attr_data = $old_attr_data;
		}

		/**
		 * Class property getter
		 *
		 * @return array
		 *
		 * @since 5.3.0
		 */
		public static function get_old_meta_data() {
			return self::$_old_meta_data;
		}

		/**
		 * Class property setter
		 *
		 * @param array $old_meta_data - The array with data.
		 *
		 * @return void
		 *
		 * @since 5.3.0
		 */
		public static function set_old_meta_data( $old_meta_data ) {
			self::$_old_meta_data = $old_meta_data;
		}

		/**
		 * Class property getter
		 *
		 * @return array
		 *
		 * @since 5.3.0
		 */
		public static function get_old_product() {
			return self::$old_product;
		}

		/**
		 * Class property setter
		 *
		 * @param \WP_Post|null $old_product - The Product with data.
		 *
		 * @return void
		 *
		 * @since 5.3.0
		 */
		public static function set_old_product( $old_product ) {
			self::$old_product = $old_product;
		}

		/**
		 * Class property getter
		 *
		 * @return \WP_Post|null
		 *
		 * @since 5.3.0
		 */
		public static function get_old_post() {
			return self::$old_post;
		}

		/**
		 * Class property setter
		 *
		 * @param \WP_POST|null $old_post - The Postrray with data.
		 *
		 * @return void
		 *
		 * @since 5.3.0
		 */
		public static function set_old_post( $old_post ) {
			self::$old_post = $old_post;
		}

		/**
		 * Class property getter
		 *
		 * @return array
		 *
		 * @since 5.3.0
		 */
		public static function get_new_location_data() {
			return self::$new_location_data;
		}

		/**
		 * Class property setter
		 *
		 * @param array $new_location_data - The array with data.
		 *
		 * @return void
		 *
		 * @since 5.3.0
		 */
		public static function set_new_location_data( $new_location_data ) {
			self::$new_location_data = $new_location_data;
		}

		/**
		 * Class property getter
		 *
		 * @return array
		 *
		 * @since 5.3.0
		 */
		public static function get_old_location_data() {
			return self::$old_location_data;
		}

		/**
		 * Class property setter
		 *
		 * @param array $old_location_data - The array with data.
		 *
		 * @return void
		 *
		 * @since 5.3.0
		 */
		public static function set_old_location_data( $old_location_data ) {
			self::$old_location_data = $old_location_data;
		}

		/**
		 * Class property getter
		 *
		 * @return string
		 *
		 * @since 5.3.0
		 */
		public static function get_old_status() {
			return self::$old_status;
		}

		/**
		 * Class property getter
		 *
		 * @return \WC_Order|null
		 *
		 * @since 5.3.0
		 */
		public static function get_old_order() {
			return self::$_old_order;
		}

		/**
		 * Class property getter
		 *
		 * @return array
		 *
		 * @since 5.3.0
		 */
		public static function get_old_attachment_metadata() {
			return self::$_old_attachment_metadata;
		}

		/**
		 * Class property getter
		 *
		 * @return array
		 *
		 * @since 5.3.0
		 */
		public static function get_coupon_meta() {
			return self::$coupon_meta;
		}

		/**
		 * Class property getter
		 *
		 * @return array
		 *
		 * @since 5.3.0
		 */
		public static function get_wc_user_meta() {
			return self::$wc_user_meta;
		}

		/**
		 * Class property setter
		 *
		 * @param string $meta_key - The array key.
		 * @param mixed  $value - The value to set.
		 *
		 * @return void
		 *
		 * @since 5.3.0
		 */
		public static function set_wc_user_meta( $meta_key, $value ) {
			self::$wc_user_meta[ $meta_key ] = $value;
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
