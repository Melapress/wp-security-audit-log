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
		 * Amount of seconds to check back for the given alert occurrence.
		 *
		 * @var integer
		 *
		 * @since 4.6.0
		 */
		private static $seconds_to_check_back = 20;

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
		 * @var WP_Post
		 *
		 * @since 4.6.0
		 */
		private static $old_post = null;

		/**
		 * Old Order.
		 *
		 * @var WC_Order
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
		 * Old Product attributes Data.
		 *
		 * @var array
		 *
		 * @since 4.6.0
		 */
		private static $old_product_attributes = null;

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
		 * Is Event 9016 Logged?
		 *
		 * @since 3.3.1
		 *
		 * @var array
		 */
		private static $last_9016_type = array();

		/**
		 * Stores $_REQUEST global variable data.
		 *
		 * @var array
		 *
		 * @since 4.6.0
		 */
		private static $request_data = array();

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
						'\WSAL\WP_Sensors\Helpers\Woocommerce_Helper',
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
						add_action( 'pre_post_update', array( __CLASS__, 'get_before_post_edit_data' ), 10, 2 );

						\add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'inline_product_changed' ), 10 );
						\add_action( 'wp_after_insert_post', array( __CLASS__, 'order_updated' ), 10, 2 );
						add_action( 'delete_post', array( __CLASS__, 'event_deleted' ), 10, 1 );
						add_action( 'woocommerce_before_delete_order', array( __CLASS__, 'event_deleted' ), 10, 1 );
						add_action( 'wp_trash_post', array( __CLASS__, 'event_trashed' ), 10, 1 );
						add_action( 'woocommerce_trash_order', array( __CLASS__, 'event_trashed' ), 10, 1 );
						add_action( 'untrash_post', array( __CLASS__, 'event_untrashed' ) );
						add_action( 'woocommerce_untrash_order', array( __CLASS__, 'event_untrashed' ) );
						add_action( 'wp_head', array( __CLASS__, 'viewing_product' ), 10 );
						add_action( 'create_product_cat', array( __CLASS__, 'event_category_creation' ), 10, 1 );
						add_filter( 'post_edit_form_tag', array( __CLASS__, 'editing_product' ), 10, 1 );
						add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'event_order_status_changed' ), 10, 4 );
						add_action( 'woocommerce_order_refunded', array( __CLASS__, 'event_order_refunded' ), 10, 2 );
						add_action( 'woocommerce_bulk_action_ids', array( __CLASS__, 'event_bulk_order_actions' ), 10, 2 );
						add_action( 'woocommerce_attribute_added', array( __CLASS__, 'event_attribute_added' ), 10, 2 );
						add_action( 'woocommerce_before_attribute_delete', array( __CLASS__, 'event_attribute_deleted' ), 10, 3 );
						add_action( 'woocommerce_attribute_updated', array( __CLASS__, 'event_attribute_updated' ), 10, 3 );
						add_action( 'wp_update_term_data', array( __CLASS__, 'event_product_cat_updated' ), 10, 4 );
						add_action( 'update_term_meta', array( __CLASS__, 'event_cat_display_updated' ), 10, 4 );
						add_action( 'delete_product_cat', array( __CLASS__, 'event_product_cat_deleted' ), 10, 4 );
						add_action( 'delete_product_tag', array( __CLASS__, 'event_product_tag_deleted' ), 10, 4 );
						add_action( 'wsal_before_post_meta_create_event', array( __CLASS__, 'log_coupon_meta_created_event' ), 10, 4 );
						add_action( 'wsal_before_post_meta_update_event', array( __CLASS__, 'log_coupon_meta_update_events' ), 10, 5 );
						add_action( 'wsal_before_post_meta_delete_event', array( __CLASS__, 'log_coupon_meta_delete_event' ), 10, 4 );
						add_action( 'update_user_meta', array( __CLASS__, 'before_wc_user_meta_update' ), 10, 3 );
						add_action( 'added_user_meta', array( __CLASS__, 'wc_user_meta_updated' ), 10, 4 );
						add_action( 'updated_user_meta', array( __CLASS__, 'wc_user_meta_updated' ), 10, 4 );
						add_action( 'woocommerce_before_product_object_save', array( __CLASS__, 'check_product_changes_before_save' ), 10, 2 );
						add_action( 'woocommerce_after_product_object_save', array( __CLASS__, 'check_product_changes_after_save' ), 10, 1 );
						add_action( 'woocommerce_product_quick_edit_save', array( __CLASS__, 'inline_product_changed' ), 10, 1 );
						add_action( 'updated_option', array( __CLASS__, 'settings_updated' ), 10, 3 );
						add_action( 'create_product_tag', array( __CLASS__, 'event_tag_creation' ), 10, 1 );
						add_action( 'update_postmeta', array( __CLASS__, 'detect_stock_level_change' ), 10, 4 );
						add_action( 'woocommerce_before_shipping_zone_object_save', array( __CLASS__, 'detect_shipping_zone_change' ), 10, 2 );
						add_action( 'woocommerce_new_webhook', array( __CLASS__, 'webhook_added' ), 10, 2 );
						add_action( 'woocommerce_webhook_deleted', array( __CLASS__, 'webhook_deleted' ), 10, 2 );
						add_action( 'woocommerce_before_shipping_zone_object_save', array( __CLASS__, 'detect_shipping_zone_change' ), 10, 2 );

						// Orders.
						add_action( 'woocommerce_new_order_item', array( __CLASS__, 'event_order_items_added' ), 10, 3 );
						add_action( 'woocommerce_before_delete_order_item', array( __CLASS__, 'event_order_items_removed' ), 10, 1 );
						add_action( 'woocommerce_before_save_order_items', array( __CLASS__, 'event_order_items_quantity_changed' ), 10, 2 );
						add_action( 'woocommerce_refund_deleted', array( __CLASS__, 'event_order_refund_removed' ), 10, 2 );
						add_action( 'admin_action_edit', array( __CLASS__, 'order_opened_for_editing' ), 10 );

						add_action( 'woocommerce_page_wc-orders', array( __CLASS__, 'orders_actions' ) );

						add_action( 'woocommerce_before_data_object_save', array( __CLASS__, 'data_store' ), 10, 2 );

						\add_action( 'woocommerce_coupon_options_save', array( __CLASS__, 'inline_product_changed' ), 10 );

						\add_action( 'woocommerce_order_note_deleted', array( __CLASS__, 'order_note_deleted' ), 10, 2 );
						// \add_action( 'woocommerce_new_customer_note', array( __CLASS__, 'new_customer_note' ), 10, 2 );
						\add_action( 'woocommerce_order_note_added', array( __CLASS__, 'order_note_added' ), 10, 2 );
					},
					10,
					2
				);
				/**
				 * Add our filters.
				 */
				add_filter(
					'wsal_event_type_data',
					array( '\WSAL\WP_Sensors\Helpers\Woocommerce_Helper', 'wsal_woocommerce_extension_add_custom_event_type' ),
					10,
					2
				);
				add_filter(
					'wsal_format_custom_meta',
					array( '\WSAL\WP_Sensors\Helpers\Woocommerce_Helper', 'wsal_woocommerce_extension_add_custom_meta_format' ),
					10,
					4
				);
				add_filter(
					'wsal_ignored_custom_post_types',
					array( '\WSAL\WP_Sensors\Helpers\Woocommerce_Helper', 'wsal_woocommerce_extension_add_custom_ignored_cpt' )
				);
				add_filter(
					'wsal_load_public_sensors',
					array( '\WSAL\WP_Sensors\Helpers\Woocommerce_Helper', 'wsal_woocommerce_extension_load_public_sensors' )
				);
				add_filter(
					'wsal_togglealerts_sub_category_events',
					array( '\WSAL\WP_Sensors\Helpers\Woocommerce_Helper', 'wsal_woocommerce_extension_togglealerts_sub_category_events' )
				);
				add_filter(
					'wsal_togglealerts_sub_category_titles',
					array( '\WSAL\WP_Sensors\Helpers\Woocommerce_Helper', 'wsal_woocommerce_extension_togglealerts_sub_category_titles' ),
					10,
					2
				);
				add_action(
					'wsal_togglealerts_append_content_to_toggle',
					array( '\WSAL\WP_Sensors\Helpers\Woocommerce_Helper', 'wsal_woocommerce_extension_append_content_to_toggle' )
				);
				add_action(
					'wsal_togglealerts_process_save_settings',
					array( '\WSAL\WP_Sensors\Helpers\Woocommerce_Helper', 'wsal_woocommerce_extension_togglealerts_process_save_settings' ),
					10,
					1
				);
				add_filter(
					'wsal_togglealerts_obsolete_events',
					array( '\WSAL\WP_Sensors\Helpers\Woocommerce_Helper', 'wsal_woocommerce_extension_togglealerts_obsolete_events' )
				);

				add_action(
					'admin_footer',
					array( '\WSAL\WP_Sensors\Helpers\Woocommerce_Helper', 'wsal_woocommerce_extension_togglealerts_js_code' )
				);

				// Special events.
				add_action(
					'woocommerce_download_product',
					array( '\WSAL\WP_Sensors\Helpers\Woocommerce_Helper', 'wsal_woocommerce_extension_detect_file_download' ),
					10,
					6
				);

			}
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
			$edit_link = self::get_editor_link( $order );
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
					$edit_link['name'] => $edit_link['value'],
					'NoteID'           => $note_id,
					'NoteType'         => ( $note->customer_note ) ? __( 'Customer note', 'wp-security-audit-log' ) : __( 'Private', 'wp-security-audit-log' ),
					'Content'          => $note->content,
					'CurrentUserID'    => ( is_a( $user, '\WP_User' ) ) ? $user->ID : 0,
					'Username'         => ( is_a( $user, '\WP_User' ) ) ? $user->user_login : 'System',
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
			$edit_link = self::get_editor_link( $order );

			Alert_Manager::trigger_event(
				9156,
				array(
					'OrderID'          => \esc_attr( $note->order_id ),
					'OrderTitle'       => \sanitize_text_field( Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $order ) ),
					'OrderStatus'      => \wc_get_order_status_name( $order->get_status() ),
					'OrderStatusSlug'  => $order->get_status(),
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
				$editor_link = self::get_editor_link( $post );

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
				$product_data = self::get_product_data( $product );
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
							self::check_price_change( self::$old_post );
						} elseif ( 'stock_status' === $lookup_key ) {
							self::check_stock_status_change( self::$old_post );
						} elseif ( 'stock' === $lookup_key ) {
							self::check_stock_quantity_change( self::$old_post );
						} elseif ( 'sku' === $lookup_key ) {
							self::check_sku_change( self::$old_post );
						} elseif ( 'weight' === $lookup_key ) {
							self::check_weight_change( self::$old_post );
						} elseif ( 'tax_status' === $lookup_key ) {
							self::check_tax_status_change( self::$old_post, self::$_old_meta_data, self::$new_data );
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
			if ( ! isset( self::$old_post ) ) {
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
			self::check_settings_change();
			self::retrieve_attribute_data();
			self::check_wc_ajax_change_events();
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
				self::$old_post                 = $post;
				self::$old_product              = 'product' === $post->post_type ? wc_get_product( $post->ID ) : null;
				self::$_old_order               = 'shop_order' === $post->post_type ? wc_get_order( $post->ID ) : null;
				self::$old_status               = $post->post_status;
				self::$_old_link                = get_post_permalink( $post_id, false, true );
				self::$_old_cats                = 'product' === $post->post_type ? self::get_product_categories( self::$old_post ) : null;
				self::$old_data                 = 'product' === $post->post_type ? self::get_product_data( self::$old_product ) : null;
				self::$old_product_attributes   = get_post_meta( $post->ID, '_product_attributes' );
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
					self::$new_data = self::get_product_data( $new_product );
					if ( empty( self::$new_data ) ) {
						return;
					}

					$changes = 0;
					$changes = self::check_categories_change( self::$_old_cats, self::get_product_categories( $post ), self::$old_post, $post )
						+ self::check_short_description_change( self::$old_post, $post )
						+ self::check_text_change( self::$old_post, $post )
						+ self::check_date_change( self::$old_post, $post )
						+ self::check_visibility_change( self::$old_post, $post )
						+ self::check_status_change( self::$old_post, $post )
						+ self::check_title_change( self::$old_post, $post )
						+ self::check_product_type_change( self::$old_post )
						+ self::check_catalog_visibility_change( self::$old_post )
						+ self::check_featured_product( self::$old_post )
						+ self::check_price_change( self::$old_post )
						+ self::check_sku_change( self::$old_post )
						+ self::check_stock_status_change( self::$old_post )
						+ self::check_stock_quantity_change( self::$old_post )
						+ self::check_type_change( self::$old_post, $post )
						+ self::check_weight_change( self::$old_post )
						+ self::check_dimensions_change( self::$old_post )
						+ self::check_downloadable_file_change( self::$old_post )
						+ self::check_backorders_setting( self::$old_post )
						+ self::check_upsells_change( self::$old_post )
						+ self::check_cross_sell_change( self::$old_post )
						+ self::check_attributes_change( self::$old_post )
						+ self::check_image_change( self::$old_post )
						+ self::check_download_limit_change( self::$_old_meta_data )
						+ self::check_tax_status_change( self::$old_post, self::$_old_meta_data, self::$new_data )
						+ self::check_low_stock_threshold_change( self::$old_post, self::$_old_meta_data, self::$new_data );

					if ( ! $changes ) {
						// Change Permalink.
						$changes = self::check_permalink_change( self::$_old_link, get_post_permalink( $post->ID, false, true ), $post );
						if ( ! $changes ) {
							// If no one of the above changes happen.
							self::check_modify_change( self::$old_post, $post );
						}
					}
				}
			} elseif ( 'shop_order' === $post->post_type ) {
				// Check order events.
				self::check_order_modify_change( $post->ID, self::$old_post, $post );
			} elseif ( 'shop_coupon' === $post->post_type ) {
				if (
					( isset( self::$old_post ) && 'auto-draft' === self::$old_post->post_status && 'draft' === $post->post_status ) // Saving draft.
						|| isset( self::$old_post ) && ( 'draft' === self::$old_post->post_status && 'publish' === $post->post_status ) // Publishing post.
						|| isset( self::$old_post ) && ( 'auto-draft' === self::$old_post->post_status && 'publish' === $post->post_status )
					) {
					self::event_creation( self::$old_post, $post );
				} else {
					// Check coupon events.
					// $changes = 0 + self::event_creation( self::$old_post, $post );

					// if ( ! $changes ) {
					self::check_short_description_change( self::$old_post, $post );
					self::check_status_change( self::$old_post, $post );
					self::check_title_change( self::$old_post, $post );
					self::check_date_change( self::$old_post, $post );
					self::check_visibility_change( self::$old_post, $post );
					// }
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
		private static function get_coupon_event_data( $coupon ) {
			if ( empty( $coupon ) || ! $coupon instanceof \WP_Post ) {
				return array();
			}

			$editor_link = self::get_editor_link( $coupon );
			return array(
				'CouponID'           => $coupon->ID,
				'CouponName'         => $coupon->post_title,
				'CouponStatus'       => $coupon->post_status,
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
				$editor_link = self::get_editor_link( $new_post );
				if ( 'publish' === $new_post->post_status ) {
					Alert_Manager::trigger_event(
						9001,
						array(
							'ProductTitle'       => sanitize_text_field( $new_post->post_title ),
							'ProductUrl'         => get_post_permalink( $new_post->ID ),
							'PostID'             => esc_attr( $new_post->ID ),
							'SKU'                => esc_attr( self::get_product_sku( $new_post->ID ) ),
							'ProductStatus'      => sanitize_text_field( $new_post->post_status ),
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
		private static function get_product_sku( $product_id ) {
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
		private static function check_categories_change( $old_cats, $new_cats, $oldpost, $newpost ) {
			if ( 'trash' === $newpost->post_status || 'trash' === $oldpost->post_status ) {
				return 0;
			}

			$old_cats = is_array( $old_cats ) ? implode( ', ', $old_cats ) : $old_cats;
			$new_cats = is_array( $new_cats ) ? implode( ', ', $new_cats ) : $new_cats;
			if ( ! empty( $old_cats ) && $old_cats !== $new_cats ) {
				$editor_link = self::get_editor_link( $newpost );
				Alert_Manager::trigger_event(
					9003,
					array(
						'ProductTitle'       => sanitize_text_field( $newpost->post_title ),
						'ProductStatus'      => sanitize_text_field( $newpost->post_status ),
						'PostID'             => esc_attr( $newpost->ID ),
						'SKU'                => esc_attr( self::get_product_sku( $newpost->ID ) ),
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
		private static function check_short_description_change( $oldpost, $newpost ) {
			if ( 'auto-draft' === $oldpost->post_status ) {
				return 0;
			}

			if ( $oldpost->post_excerpt !== $newpost->post_excerpt ) {
				if ( 'product' === $newpost->post_type ) {
					$editor_link = self::get_editor_link( $oldpost );
					Alert_Manager::trigger_event(
						9004,
						array(
							'PostID'             => esc_attr( $oldpost->ID ),
							'SKU'                => esc_attr( self::get_product_sku( $oldpost->ID ) ),
							'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
							'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
							'OldDescription'     => $oldpost->post_excerpt,
							'NewDescription'     => $newpost->post_excerpt,
							$editor_link['name'] => $editor_link['value'],
						)
					);
					return 1;
				} elseif ( 'shop_coupon' === $newpost->post_type ) {
					$coupon_data                   = self::get_coupon_event_data( $newpost );
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
		private static function check_text_change( $oldpost, $newpost ) {
			if ( 'auto-draft' === $oldpost->post_status ) {
				return 0;
			}
			if ( $oldpost->post_content !== $newpost->post_content ) {
				$editor_link = self::get_editor_link( $oldpost );
				Alert_Manager::trigger_event(
					9005,
					array(
						'PostID'             => esc_attr( $oldpost->ID ),
						'SKU'                => esc_attr( self::get_product_sku( $oldpost->ID ) ),
						'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
						'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
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
		private static function check_permalink_change( $old_link, $new_link, $post ) {
			if ( ! empty( $old_link ) && $old_link && $new_link && ( $old_link !== $new_link ) ) {
				$editor_link = self::get_editor_link( $post );
				Alert_Manager::trigger_event(
					9006,
					array(
						'PostID'             => esc_attr( $post->ID ),
						'SKU'                => esc_attr( self::get_product_sku( $post->ID ) ),
						'ProductTitle'       => sanitize_text_field( $post->post_title ),
						'ProductStatus'      => sanitize_text_field( $post->post_status ),
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
		private static function check_product_type_change( $post ) {
			$old_type = isset( self::$old_data['type'] ) ? self::$old_data['type'] : false;
			$new_type = isset( self::$new_data['type'] ) ? self::$new_data['type'] : false;

			if ( $old_type !== $new_type ) {
				$editor_link = self::get_editor_link( $post );
				Alert_Manager::trigger_event(
					9007,
					array(
						'PostID'             => esc_attr( $post->ID ),
						'SKU'                => esc_attr( self::get_product_sku( $post->ID ) ),
						'ProductTitle'       => sanitize_text_field( $post->post_title ),
						'ProductStatus'      => sanitize_text_field( $post->post_status ),
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
		private static function check_date_change( $oldpost, $newpost ) {
			if ( 'draft' === $oldpost->post_status || 'auto-draft' === $oldpost->post_status ) {
				return 0;
			}

			$from = strtotime( $oldpost->post_date );
			$to   = strtotime( $newpost->post_date );

			if ( $from !== $to ) {
				if ( 'shop_coupon' === $newpost->post_type ) {
					$editor_link = self::get_editor_link( $oldpost );
					Alert_Manager::trigger_event(
						9126,
						array(
							'CouponID'           => esc_attr( $oldpost->ID ),
							'CouponName'         => sanitize_text_field( $oldpost->post_title ),
							'CouponStatus'       => sanitize_text_field( $oldpost->post_status ),
							'OldDate'            => $oldpost->post_date,
							'NewDate'            => $newpost->post_date,
							$editor_link['name'] => $editor_link['value'],
						)
					);
					return 1;
				} else {
					$editor_link = self::get_editor_link( $oldpost );
					Alert_Manager::trigger_event(
						9008,
						array(
							'PostID'             => esc_attr( $oldpost->ID ),
							'SKU'                => esc_attr( self::get_product_sku( $oldpost->ID ) ),
							'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
							'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
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
		private static function check_visibility_change( $oldpost, $newpost ) {
			if ( 'draft' === self::$old_status || 'draft' === $newpost->post_status ) {
				return;
			}

			$old_visibility = '';
			$new_visibility = '';

			if ( $oldpost->post_password ) {
				$old_visibility = __( 'Password Protected', 'wp-security-audit-log' );
			} elseif ( 'publish' === self::$old_status ) {
				$old_visibility = __( 'Public', 'wp-security-audit-log' );
			} elseif ( 'private' === self::$old_status ) {
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
					$editor_link = self::get_editor_link( $oldpost );
					Alert_Manager::trigger_event(
						9125,
						array(
							'CouponID'           => esc_attr( $oldpost->ID ),
							'CouponCode'         => sanitize_text_field( $oldpost->post_title ),
							'CouponStatus'       => sanitize_text_field( $oldpost->post_status ),
							'OldVisibility'      => $old_visibility,
							'NewVisibility'      => $new_visibility,
							$editor_link['name'] => $editor_link['value'],
						)
					);
					return 1;
				} else {
					$editor_link = self::get_editor_link( $oldpost );
					Alert_Manager::trigger_event(
						9009,
						array(
							'PostID'             => esc_attr( $oldpost->ID ),
							'SKU'                => esc_attr( self::get_product_sku( $oldpost->ID ) ),
							'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
							'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
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
		private static function check_title_change( $oldpost, $newpost ) {
			if ( 'auto-draft' === $oldpost->post_status ) {
				return 0;
			}

			if ( 'shop_coupon' === $newpost->post_type && $oldpost->post_title !== $newpost->post_title ) {
				// Get coupon event data.
				$coupon_data = self::get_coupon_event_data( $newpost );

				// Set old and new titles.
				$coupon_data['OldName'] = $oldpost->post_title;
				$coupon_data['NewName'] = $newpost->post_title;

				// Log the event.
				Alert_Manager::trigger_event( 9071, $coupon_data );

				return 1;
			} elseif ( 'product' === $newpost->post_type && $oldpost->post_title !== $newpost->post_title ) {
				// Get editor link.
				$editor_link = self::get_editor_link( $newpost );

				// Log the event.
				Alert_Manager::trigger_event(
					9077,
					array(
						'PostID'             => esc_attr( $newpost->ID ),
						'SKU'                => esc_attr( self::get_product_sku( $newpost->ID ) ),
						'ProductStatus'      => sanitize_text_field( $newpost->post_status ),
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
		private static function check_catalog_visibility_change( $post ) {
			// Get product data.
			$old_visibility = isset( self::$old_data['catalog_visibility'] ) ? self::$old_data['catalog_visibility'] : false;
			$new_visibility = isset( self::$new_data['catalog_visibility'] ) ? self::$new_data['catalog_visibility'] : false;

			// Get WooCommerce visibility options.
			$wc_visibilities = wc_get_product_visibility_options();

			if ( ( $old_visibility && $new_visibility ) && ( $old_visibility !== $new_visibility ) ) {
				$editor_link = self::get_editor_link( $post );
				Alert_Manager::trigger_event(
					9042,
					array(
						'PostID'             => esc_attr( $post->ID ),
						'SKU'                => esc_attr( self::get_product_sku( $post->ID ) ),
						'ProductTitle'       => sanitize_text_field( $post->post_title ),
						'ProductStatus'      => sanitize_text_field( $post->post_status ),
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
		private static function check_featured_product( $post ) {
			$old_featured = isset( self::$old_data['featured'] ) ? self::$old_data['featured'] : false;
			$new_featured = isset( self::$new_data['featured'] ) ? self::$new_data['featured'] : false;

			if ( $old_featured !== $new_featured ) {
				$editor_link = self::get_editor_link( $post );
				Alert_Manager::trigger_event(
					9043,
					array(
						'PostID'             => esc_attr( $post->ID ),
						'SKU'                => esc_attr( self::get_product_sku( $post->ID ) ),
						'ProductTitle'       => sanitize_text_field( $post->post_title ),
						'ProductStatus'      => sanitize_text_field( $post->post_status ),
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
		private static function check_backorders_setting( $oldpost, $old_backorder = '', $new_backorder = '' ) {
			// Get product data.
			if ( '' === $old_backorder ) {
				$old_backorder = isset( self::$old_data['backorders'] ) ? self::$old_data['backorders'] : false;
			}
			if ( '' === $new_backorder ) {
				$new_backorder = isset( self::$new_data['backorders'] ) ? self::$new_data['backorders'] : false;
			}

			if ( $old_backorder !== $new_backorder ) {
				$editor_link = self::get_editor_link( $oldpost );
				Alert_Manager::trigger_event(
					9044,
					array(
						'PostID'             => esc_attr( $oldpost->ID ),
						'SKU'                => esc_attr( self::get_product_sku( $oldpost->ID ) ),
						'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
						'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
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
		private static function check_upsells_change( $oldpost ) {
			// Get product data.
			$old_upsell_ids = isset( self::$old_data['upsell_ids'] ) ? self::$old_data['upsell_ids'] : false;
			$new_upsell_ids = isset( self::$new_data['upsell_ids'] ) ? self::$new_data['upsell_ids'] : false;

			// Compute the difference.
			$added_upsells   = array();
			$removed_upsells = array();

			if ( is_array( $new_upsell_ids ) && is_array( $old_upsell_ids ) ) {
				$added_upsells   = array_diff( $new_upsell_ids, $old_upsell_ids );
				$removed_upsells = array_diff( $old_upsell_ids, $new_upsell_ids );
			}

			// Get editor link.
			$editor_link = self::get_editor_link( $oldpost );

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
							'SKU'                => esc_attr( self::get_product_sku( $oldpost->ID ) ),
							'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
							'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
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
		private static function check_cross_sell_change( $oldpost ) {
			// Get product data.
			$old_cross_sell_ids = isset( self::$old_data['cross_sell_ids'] ) ? self::$old_data['cross_sell_ids'] : false;
			$new_cross_sell_ids = isset( self::$new_data['cross_sell_ids'] ) ? self::$new_data['cross_sell_ids'] : false;

			// Compute the difference.
			$added_cross_sells   = array();
			$removed_cross_sells = array();
			if ( is_array( $new_cross_sell_ids ) && is_array( $old_cross_sell_ids ) ) {
				$added_cross_sells   = array_diff( $new_cross_sell_ids, $old_cross_sell_ids );
				$removed_cross_sells = array_diff( $old_cross_sell_ids, $new_cross_sell_ids );
			}

			// Get editor link.
			$editor_link = self::get_editor_link( $oldpost );

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
							'SKU'                => esc_attr( self::get_product_sku( $oldpost->ID ) ),
							'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
							'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
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
		private static function check_modify_change( $oldpost, $newpost ) {
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

			$editor_link = self::get_editor_link( $oldpost );
			Alert_Manager::trigger_event_if(
				9010,
				array(
					'PostID'             => esc_attr( $oldpost->ID ),
					'SKU'                => esc_attr( self::get_product_sku( $oldpost->ID ) ),
					'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
					'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
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
						'SKU'           => esc_attr( self::get_product_sku( $post->ID ) ),
						'ProductTitle'  => sanitize_text_field( $post->post_title ),
						'ProductStatus' => sanitize_text_field( $post->post_status ),
						'ProductUrl'    => get_post_permalink( $post->ID ),
					)
				);
			} elseif ( is_a( $post, '\Automattic\WooCommerce\Admin\Overrides\Order' ) ) {
				$editor_link = self::get_editor_link( $post );
				Alert_Manager::trigger_event(
					9037,
					array(
						'OrderID'            => esc_attr( $post->get_id() ),
						'OrderTitle'         => sanitize_text_field( Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $post->get_id() ) ),
						'OrderStatus'        => \wc_get_order_status_name( $post->get_status() ),
						'OrderStatusSlug'    => $post->get_status(),
						$editor_link['name'] => $editor_link['value'],
					)
				);
			} elseif ( 'shop_order' === $post->post_type ) {
				$editor_link = self::get_editor_link( $post );
				Alert_Manager::trigger_event(
					9037,
					array(
						'OrderID'            => esc_attr( $post->ID ),
						'OrderTitle'         => sanitize_text_field( Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $post->ID ) ),
						'OrderStatus'        => \wc_get_order_status_name( $post->post_status ),
						'OrderStatusSlug'    => sanitize_text_field( $post->post_status ),
						$editor_link['name'] => $editor_link['value'],
					)
				);
			} elseif ( 'shop_coupon' === $post->post_type ) {
				$editor_link = self::get_editor_link( $post );
				Alert_Manager::trigger_event(
					9123,
					array(
						'CouponID'           => esc_attr( $post->ID ),
						'CouponName'         => sanitize_text_field( $post->post_title ),
						'CouponStatus'       => sanitize_text_field( $post->post_status ),
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
						'SKU'          => esc_attr( self::get_product_sku( $post->ID ) ),
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
				$editor_link = self::get_editor_link( $post );
				Alert_Manager::trigger_event(
					9014,
					array(
						'PostID'             => esc_attr( $post->ID ),
						'SKU'                => esc_attr( self::get_product_sku( $post->ID ) ),
						'ProductTitle'       => sanitize_text_field( $post->post_title ),
						'ProductStatus'      => sanitize_text_field( $post->post_status ),
						$editor_link['name'] => $editor_link['value'],
					)
				);
			} elseif ( is_a( $post, '\Automattic\WooCommerce\Admin\Overrides\Order' ) ) {
				$editor_link = self::get_editor_link( $post );
				Alert_Manager::trigger_event(
					9038,
					array(
						'OrderID'            => esc_attr( $post->get_id() ),
						'OrderTitle'         => sanitize_text_field( Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $post_id ) ),
						'OrderStatus'        => \wc_get_order_status_name( $post->get_status() ),
						'OrderStatusSlug'    => $post->get_status(),
						$editor_link['name'] => $editor_link['value'],
					)
				);
			} elseif ( 'shop_order' === $post->post_type ) {
				$editor_link = self::get_editor_link( $post );
				Alert_Manager::trigger_event(
					9038,
					array(
						'OrderID'            => esc_attr( $post->ID ),
						'OrderTitle'         => sanitize_text_field( Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $post_id ) ),
						'OrderStatus'        => \wc_get_order_status_name( $post->post_status ),
						'OrderStatusSlug'    => sanitize_text_field( $post->post_status ),
						$editor_link['name'] => $editor_link['value'],
					)
				);
			} elseif ( 'shop_coupon' === $post->post_type ) {
				$editor_link = self::get_editor_link( $post );
				Alert_Manager::trigger_event(
					9127,
					array(
						'CouponID'           => esc_attr( $post->ID ),
						'CouponCode'         => sanitize_text_field( $post->post_title ),
						'CouponStatus'       => sanitize_text_field( $post->post_status ),
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
					$editor_link = self::get_editor_link( $product );
					Alert_Manager::trigger_event(
						9073,
						array(
							'PostID'             => esc_attr( $product->ID ),
							'SKU'                => esc_attr( self::get_product_sku( $product->ID ) ),
							'PostType'           => 'post', // Set to post to allow event to trigger (products are usually ignored).
							'ProductStatus'      => sanitize_text_field( $product->post_status ),
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
		private static function check_status_change( $oldpost, $newpost ) {
			if ( 'draft' === $oldpost->post_status || 'auto-draft' === $oldpost->post_status ) {
				return 0;
			}
			if ( $oldpost->post_status !== $newpost->post_status ) {
				if ( 'trash' !== $oldpost->post_status && 'trash' !== $newpost->post_status ) {
					if ( 'product' === $newpost->post_type ) {
						$editor_link = self::get_editor_link( $oldpost );
						Alert_Manager::trigger_event(
							9015,
							array(
								'PostID'             => esc_attr( $oldpost->ID ),
								'SKU'                => esc_attr( self::get_product_sku( $oldpost->ID ) ),
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
						$coupon_data = self::get_coupon_event_data( $newpost );
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
		private static function check_price_change( $post ) {
			$result         = 0;
			$old_price      = isset( self::$old_data['regular_price'] ) ? self::$old_data['regular_price'] : false;
			$old_sale_price = isset( self::$old_data['sale_price'] ) ? self::$old_data['sale_price'] : false;
			$new_price      = isset( self::$new_data['regular_price'] ) ? self::$new_data['regular_price'] : false;
			$new_sale_price = isset( self::$new_data['sale_price'] ) ? self::$new_data['sale_price'] : false;

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
		private static function event_price( $post, $type, $old_price, $new_price ) {
			$currency    = self::get_currency_symbol( self::get_config( 'currency' ) );
			$editor_link = self::get_editor_link( $post );

			if ( empty( self::$last_9016_type ) || ! in_array( $type, self::$last_9016_type, true ) ) {
				// WC does not like data being accessed directly.
				$post_id = method_exists( $post, 'get_id' ) ? $post->get_id() : $post->ID;
				Alert_Manager::trigger_event(
					9016,
					array(
						'PostID'             => $post_id,
						'SKU'                => esc_attr( self::get_product_sku( $post_id ) ),
						'ProductTitle'       => sanitize_text_field( $post->post_title ),
						'ProductStatus'      => sanitize_text_field( $post->post_status ),
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
		private static function check_sku_change( $oldpost, $old_sku = '', $new_sku = '' ) {
			if ( '' === $old_sku && '' === $new_sku ) {
				$old_sku = isset( self::$old_data['sku'] ) ? self::$old_data['sku'] : false;
				$new_sku = isset( self::$new_data['sku'] ) ? self::$new_data['sku'] : false;
			}

			if ( $new_sku && ( $old_sku !== $new_sku ) ) {
				$editor_link = self::get_editor_link( $oldpost );
				Alert_Manager::trigger_event(
					9017,
					array(
						'PostID'             => esc_attr( $oldpost->ID ),
						'SKU'                => esc_attr( self::get_product_sku( $oldpost->ID ) ),
						'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
						'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
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
		private static function check_stock_status_change( $oldpost, $old_status = '', $new_status = '' ) {
			if ( '' === $old_status && '' === $new_status ) {
				$old_status = isset( self::$old_data['stock_status'] ) ? self::$old_data['stock_status'] : false;
				$new_status = isset( self::$new_data['stock_status'] ) ? self::$new_data['stock_status'] : false;
			}

			if ( ( $old_status && $new_status ) && ( $old_status !== $new_status ) ) {
				$editor_link = self::get_editor_link( $oldpost );
				Alert_Manager::trigger_event(
					9018,
					array(
						'PostID'             => esc_attr( $oldpost->ID ),
						'SKU'                => esc_attr( self::get_product_sku( $oldpost->ID ) ),
						'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
						'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
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
		 * Trigger events 9019
		 *
		 * @param object $oldpost   - Old product object.
		 * @param mixed  $old_value - Old stock quantity.
		 * @param mixed  $new_value - New stock quantity.
		 * @return int
		 *
		 * @since 4.6.0
		 */
		private static function check_stock_quantity_change( $oldpost, $old_value = false, $new_value = false ) {
			if ( false === $old_value && false === $new_value ) {
				if ( self::$old_data['manage_stock'] ) {
					$old_value = isset( self::$old_data['stock_quantity'] ) ? self::$old_data['stock_quantity'] : false;
				} else {
					$old_value = false;
				}
				if ( self::$old_data['manage_stock'] ) {
					$new_value = isset( self::$new_data['stock_quantity'] ) ? self::$new_data['stock_quantity'] : false;
				} else {
					$new_value = false;
				}
			}

			if ( $new_value && ( $old_value !== $new_value ) ) {
				$editor_link = self::get_editor_link( $oldpost );
				// WC does not like data being accessed directly.
				$post_id = method_exists( $oldpost, 'get_id' ) ? $oldpost->get_id() : $oldpost->ID;
				Alert_Manager::trigger_event(
					9019,
					array(
						'PostID'             => $post_id,
						'SKU'                => esc_attr( self::get_product_sku( $post_id ) ),
						'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
						'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
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
		private static function check_type_change( $oldpost, $newpost = null, $virtual = false, $download = false ) {
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
				$old_virtual = isset( self::$old_data['virtual'] ) ? self::$old_data['virtual'] : false;
				$new_virtual = isset( self::$new_data['virtual'] ) ? self::$new_data['virtual'] : false;
			} elseif ( is_array( $virtual ) ) {
				$old_virtual = ( isset( $virtual['old'] ) && $virtual['old'] ) ? 'yes' : 'no';
				$new_virtual = ( isset( $virtual['new'] ) && $virtual['new'] ) ? 'yes' : 'no';
			}

			// Get simple product downloadable data.
			if ( false === $download ) {
				$old_download = isset( self::$old_data['downloadable'] ) ? self::$old_data['downloadable'] : false;
				$new_download = isset( self::$new_data['downloadable'] ) ? self::$new_data['downloadable'] : false;
			} elseif ( is_array( $download ) ) {
				$old_download = ( isset( $download['old'] ) && $download['old'] ) ? 'yes' : 'no';
				$new_download = ( isset( $download['new'] ) && $download['new'] ) ? 'yes' : 'no';
			}

			// Return variable.
			$result = 0;

			if ( $old_virtual && $new_virtual && $old_virtual !== $new_virtual ) {
				$editor_link = self::get_editor_link( $oldpost );
				// WC does not like data being accessed directly.
				$post_id = method_exists( $oldpost, 'get_id' ) ? $oldpost->get_id() : $oldpost->ID;
				Alert_Manager::trigger_event(
					9020,
					array(
						'PostID'             => $post_id,
						'SKU'                => esc_attr( self::get_product_sku( $post_id ) ),
						'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
						'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
						'OldType'            => 'yes' === $old_virtual ? 'Virtual' : 'Non-Virtual',
						'NewType'            => 'yes' === $new_virtual ? 'Virtual' : 'Non-Virtual',
						$editor_link['name'] => $editor_link['value'],
					)
				);
				$result = 1;
			}

			if ( $old_download && $new_download && $old_download !== $new_download ) {
				$editor_link = self::get_editor_link( $oldpost );
				// WC does not like data being accessed directly.
				$post_id = method_exists( $oldpost, 'get_id' ) ? $oldpost->get_id() : $oldpost->ID;
				Alert_Manager::trigger_event(
					9020,
					array(
						'PostID'             => esc_attr( $post_id ),
						'SKU'                => esc_attr( self::get_product_sku( $post_id ) ),
						'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
						'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
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
		private static function check_weight_change( $oldpost, $old_weight = '', $new_weight = '' ) {
			if ( '' === $old_weight && '' === $new_weight ) {
				$old_weight = isset( self::$old_data['weight'] ) ? self::$old_data['weight'] : false;
				$new_weight = isset( self::$new_data['weight'] ) ? self::$new_data['weight'] : false;
			}

			if ( $new_weight && ( $old_weight !== $new_weight ) ) {
				$weight_unit = self::get_config( 'weight_unit' );
				$editor_link = self::get_editor_link( $oldpost );
				// WC does not like data being accessed directly.
				$post_id = method_exists( $oldpost, 'get_id' ) ? $oldpost->get_id() : $oldpost->ID;
				Alert_Manager::trigger_event(
					9021,
					array(
						'PostID'             => $post_id,
						'SKU'                => esc_attr( self::get_product_sku( $post_id ) ),
						'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
						'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
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
		private static function check_dimensions_change( $oldpost, $length = false, $width = false, $height = false ) {
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
				$old_length = isset( self::$old_data['length'] ) ? self::$old_data['length'] : false;
				$new_length = isset( self::$new_data['length'] ) ? self::$new_data['length'] : false;
			} elseif ( is_array( $length ) ) {
				$old_length = isset( $length['old'] ) ? $length['old'] : false;
				$new_length = isset( $length['new'] ) ? $length['new'] : false;
			}

			// Width.
			if ( false === $width ) {
				$old_width = isset( self::$old_data['width'] ) ? self::$old_data['width'] : false;
				$new_width = isset( self::$new_data['width'] ) ? self::$new_data['width'] : false;
			} elseif ( is_array( $width ) ) {
				$old_width = isset( $width['old'] ) ? $width['old'] : false;
				$new_width = isset( $width['new'] ) ? $width['new'] : false;
			}

			// Height.
			if ( false === $height ) {
				$old_height = isset( self::$old_data['height'] ) ? self::$old_data['height'] : false;
				$new_height = isset( self::$new_data['height'] ) ? self::$new_data['height'] : false;
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
		private static function event_dimension( $oldpost, $type, $old_dimension, $new_dimension ) {
			$dimension_unit = self::get_config( 'dimension_unit' );
			$editor_link    = self::get_editor_link( $oldpost );
			// WC does not like data being accessed directly.
			$post_id = method_exists( $oldpost, 'get_id' ) ? $oldpost->get_id() : $oldpost->ID;
			Alert_Manager::trigger_event(
				9022,
				array(
					'PostID'             => $post_id,
					'SKU'                => esc_attr( self::get_product_sku( $post_id ) ),
					'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
					'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
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
		private static function check_downloadable_file_change( $oldpost, $file_names = false, $file_urls = false ) {
			// Get product data.
			$result         = 0;
			$is_url_changed = false;
			$editor_link    = self::get_editor_link( $oldpost );

			if ( false === $file_names ) {
				$old_file_names = isset( self::$old_data['file_names'] ) ? self::$old_data['file_names'] : array();
				$new_file_names = isset( self::$new_data['file_names'] ) ? self::$new_data['file_names'] : array();
			} else {
				$old_file_names = isset( $file_names['old'] ) ? $file_names['old'] : array();
				$new_file_names = isset( $file_names['new'] ) ? $file_names['new'] : array();
			}

			if ( false === $file_urls ) {
				$old_file_urls = isset( self::$old_data['file_urls'] ) ? self::$old_data['file_urls'] : array();
				$new_file_urls = isset( self::$new_data['file_urls'] ) ? self::$new_data['file_urls'] : array();
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
								'SKU'                => esc_attr( self::get_product_sku( $post_id ) ),
								'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
								'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
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
								'SKU'                => esc_attr( self::get_product_sku( $oldpost->ID ) ),
								'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
								'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
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
								'SKU'                => esc_attr( self::get_product_sku( $oldpost->ID ) ),
								'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
								'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
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
							'SKU'                => esc_attr( self::get_product_sku( $oldpost->ID ) ),
							'ProductTitle'       => sanitize_text_field( $oldpost->post_title ),
							'ProductStatus'      => sanitize_text_field( $oldpost->post_status ),
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
		 * @param string $option - Option name.
		 * @param string $old_value - Previous value.
		 * @param mixed  $value - New value.
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
								self::$old_location_data = $old_value . ', ' . self::get_config( 'store_address_2' ) . ', ' . self::get_config( 'store_city' ) . ', ' . WC()->countries->countries[ strtok( self::get_config( 'default_country' ), ':' ) ] . ', ' . self::get_config( 'store_postcode' );
								self::$new_location_data = sanitize_text_field( wp_unslash( $_POST['woocommerce_store_address'] ) ) . ', ' . self::get_config( 'store_address_2' ) . ', ' . self::get_config( 'store_city' ) . ', ' . WC()->countries->countries[ strtok( self::get_config( 'default_country' ), ':' ) ] . ', ' . self::get_config( 'store_postcode' );
							}
							if ( 'woocommerce_store_address_2' === $option ) {
								self::$old_location_data = self::get_config( 'store_address' ) . ', ' . $old_value . ', ' . self::get_config( 'store_city' ) . ', ' . WC()->countries->countries[ strtok( self::get_config( 'default_country' ), ':' ) ] . ', ' . self::get_config( 'store_postcode' );
								self::$new_location_data = self::get_config( 'store_address' ) . ', ' . sanitize_text_field( wp_unslash( $_POST['woocommerce_store_address_2'] ) ) . ', ' . self::get_config( 'store_city' ) . ', ' . WC()->countries->countries[ strtok( self::get_config( 'default_country' ), ':' ) ] . ', ' . self::get_config( 'store_postcode' );
							}
							if ( 'woocommerce_store_city' === $option ) {
								self::$old_location_data = self::get_config( 'store_address' ) . ', ' . self::get_config( 'store_address_2' ) . ', ' . $old_value . ', ' . self::get_config( 'default_country' ) . ', ' . self::get_config( 'store_postcode' );
								self::$new_location_data = self::get_config( 'store_address' ) . ', ' . self::get_config( 'store_address_2' ) . ', ' . sanitize_text_field( wp_unslash( $_POST['woocommerce_store_city'] ) ) . ', ' . WC()->countries->countries[ strtok( self::get_config( 'default_country' ), ':' ) ] . ', ' . self::get_config( 'store_postcode' );
							}
							if ( 'woocommerce_default_country' === $option ) {
								self::$old_location_data = self::get_config( 'store_address' ) . ', ' . self::get_config( 'store_address_2' ) . ', ' . self::get_config( 'store_address' ) . ', ' . WC()->countries->countries[ strtok( $old_value, ':' ) ] . ', ' . $old_value;
								self::$new_location_data = self::get_config( 'store_address' ) . ', ' . self::get_config( 'store_address_2' ) . ', ' . self::get_config( 'store_address' ) . ', ' . WC()->countries->countries[ strtok( sanitize_text_field( wp_unslash( $_POST['woocommerce_default_country'] ) ), ':' ) ] . ', ' . self::get_config( 'store_postcode' );
							}
							if ( 'woocommerce_store_postcode' === $option ) {
								self::$old_location_data = self::get_config( 'store_address' ) . ', ' . self::get_config( 'store_address_2' ) . ', ' . self::get_config( 'store_address' ) . ', ' . self::get_config( 'default_country' ) . ', ' . $old_value;
								self::$new_location_data = self::get_config( 'store_address' ) . ', ' . self::get_config( 'store_address_2' ) . ', ' . self::get_config( 'store_address' ) . ', ' . WC()->countries->countries[ strtok( self::get_config( 'default_country' ), ':' ) ] . ', ' . sanitize_text_field( wp_unslash( $_POST['woocommerce_store_postcode'] ) );
							}

							if ( self::$old_location_data !== self::$new_location_data ) {
								sleep( 1 );
								self::$new_location_data = sanitize_text_field( wp_unslash( $_POST['woocommerce_store_address'] ) ) . ', ' . sanitize_text_field( wp_unslash( $_POST['woocommerce_store_address_2'] ) ) . ', ' . sanitize_text_field( wp_unslash( $_POST['woocommerce_store_city'] ) ) . ', ' . WC()->countries->countries[ strtok( sanitize_text_field( wp_unslash( $_POST['woocommerce_default_country'] ) ), ':' ) ] . ', ' . sanitize_text_field( wp_unslash( $_POST['woocommerce_store_postcode'] ) );
								if ( ! Alert_Manager::was_triggered_recently( 9029 ) ) {
									Alert_Manager::trigger_event(
										9029,
										array(
											'OldLocation' => sanitize_text_field( self::$old_location_data ),
											'NewLocation' => sanitize_text_field( self::$new_location_data ),
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
							if ( empty( $old_value ) ) {
								$old_value = get_option( 'woocommerce_specific_allowed_countries' );
							}
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
							if ( empty( $old_value ) ) {
								$old_value = get_option( 'woocommerce_all_except_countries' );
							}
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
							if ( empty( $old_value ) ) {
								$old_value = get_option( 'woocommerce_specific_ship_to_countries' );
							}
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
					}
				}
			}
		}

		/**
		 * Trigger events Settings: 9027, 9028, 9029, 9030, 9031, 9032, 9033
		 *
		 * @since 4.6.0
		 */
		private static function check_settings_change() {
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
							$old_cash_on_delivery = self::get_config( 'cod_settings' );
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
							$gateway_settings   = self::get_config( $gateway . '_settings' );
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
							$gateway_settings = self::get_config( $gateway . '_settings' );
							Alert_Manager::trigger_event(
								9076,
								array(
									'GatewayID'   => sanitize_text_field( $gateway ),
									'GatewayName' => isset( $gateway_settings['title'] ) ? $gateway_settings['title'] : false,
								)
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
					$changes = $_POST['changes'];
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
		private static function get_stock_status_name( $slug ) {
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
		private static function get_product_categories( $post ) {
			return wp_get_post_terms( $post->ID, 'product_cat', array( 'fields' => 'names' ) );
		}

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
		private static function get_product_data( $product ) {
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
		private static function get_config( $option_name ) {
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
		private static function check_woo_commerce( $post ) {
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
		private static function get_editor_link( $post ) {

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

				$current_path = isset( $server_array['SCRIPT_NAME'] ) ? $server_array['SCRIPT_NAME'] . '?post=' . $product->ID : false;
				if ( ! empty( $server_array['HTTP_REFERER'] )
					&& strpos( $server_array['HTTP_REFERER'], $current_path ) !== false ) {
					// Ignore this if we were on the same page so we avoid double audit entries.
					return $product;
				}
				if ( ! empty( $product->post_title ) ) {
					$event = 9072;
					if ( ! Alert_Manager::was_triggered( $event ) && ! Alert_Manager::was_triggered( 9001 ) || ! Alert_Manager::was_triggered_recently( 9000 ) ) {
						$editor_link = self::get_editor_link( $product );
						Alert_Manager::trigger_event_if(
							$event,
							array(
								'PostID'             => esc_attr( $product->ID ),
								'SKU'                => esc_attr( self::get_product_sku( $product->ID ) ),
								'ProductStatus'      => sanitize_text_field( $product->post_status ),
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
		private static function get_currency_symbol( $currency = '' ) {
			$symbols         = array(
				'AED' => '&#x62f;.&#x625;',
				'AFN' => '&#x60b;',
				'ALL' => 'L',
				'AMD' => 'AMD',
				'ANG' => '&fnof;',
				'AOA' => 'Kz',
				'ARS' => '&#36;',
				'AUD' => '&#36;',
				'AWG' => '&fnof;',
				'AZN' => 'AZN',
				'BAM' => 'KM',
				'BBD' => '&#36;',
				'BDT' => '&#2547;&nbsp;',
				'BGN' => '&#1083;&#1074;.',
				'BHD' => '.&#x62f;.&#x628;',
				'BIF' => 'Fr',
				'BMD' => '&#36;',
				'BND' => '&#36;',
				'BOB' => 'Bs.',
				'BRL' => '&#82;&#36;',
				'BSD' => '&#36;',
				'BTC' => '&#3647;',
				'BTN' => 'Nu.',
				'BWP' => 'P',
				'BYR' => 'Br',
				'BZD' => '&#36;',
				'CAD' => '&#36;',
				'CDF' => 'Fr',
				'CHF' => '&#67;&#72;&#70;',
				'CLP' => '&#36;',
				'CNY' => '&yen;',
				'COP' => '&#36;',
				'CRC' => '&#x20a1;',
				'CUC' => '&#36;',
				'CUP' => '&#36;',
				'CVE' => '&#36;',
				'CZK' => '&#75;&#269;',
				'DJF' => 'Fr',
				'DKK' => 'DKK',
				'DOP' => 'RD&#36;',
				'DZD' => '&#x62f;.&#x62c;',
				'EGP' => 'EGP',
				'ERN' => 'Nfk',
				'ETB' => 'Br',
				'EUR' => '&euro;',
				'FJD' => '&#36;',
				'FKP' => '&pound;',
				'GBP' => '&pound;',
				'GEL' => '&#x10da;',
				'GGP' => '&pound;',
				'GHS' => '&#x20b5;',
				'GIP' => '&pound;',
				'GMD' => 'D',
				'GNF' => 'Fr',
				'GTQ' => 'Q',
				'GYD' => '&#36;',
				'HKD' => '&#36;',
				'HNL' => 'L',
				'HRK' => 'Kn',
				'HTG' => 'G',
				'HUF' => '&#70;&#116;',
				'IDR' => 'Rp',
				'ILS' => '&#8362;',
				'IMP' => '&pound;',
				'INR' => '&#8377;',
				'IQD' => '&#x639;.&#x62f;',
				'IRR' => '&#xfdfc;',
				'ISK' => 'kr.',
				'JEP' => '&pound;',
				'JMD' => '&#36;',
				'JOD' => '&#x62f;.&#x627;',
				'JPY' => '&yen;',
				'KES' => 'KSh',
				'KGS' => '&#x441;&#x43e;&#x43c;',
				'KHR' => '&#x17db;',
				'KMF' => 'Fr',
				'KPW' => '&#x20a9;',
				'KRW' => '&#8361;',
				'KWD' => '&#x62f;.&#x643;',
				'KYD' => '&#36;',
				'KZT' => 'KZT',
				'LAK' => '&#8365;',
				'LBP' => '&#x644;.&#x644;',
				'LKR' => '&#xdbb;&#xdd4;',
				'LRD' => '&#36;',
				'LSL' => 'L',
				'LYD' => '&#x644;.&#x62f;',
				'MAD' => '&#x62f;.&#x645;.',
				'MDL' => 'L',
				'MGA' => 'Ar',
				'MKD' => '&#x434;&#x435;&#x43d;',
				'MMK' => 'Ks',
				'MNT' => '&#x20ae;',
				'MOP' => 'P',
				'MRO' => 'UM',
				'MUR' => '&#x20a8;',
				'MVR' => '.&#x783;',
				'MWK' => 'MK',
				'MXN' => '&#36;',
				'MYR' => '&#82;&#77;',
				'MZN' => 'MT',
				'NAD' => '&#36;',
				'NGN' => '&#8358;',
				'NIO' => 'C&#36;',
				'NOK' => '&#107;&#114;',
				'NPR' => '&#8360;',
				'NZD' => '&#36;',
				'OMR' => '&#x631;.&#x639;.',
				'PAB' => 'B/.',
				'PEN' => 'S/.',
				'PGK' => 'K',
				'PHP' => '&#8369;',
				'PKR' => '&#8360;',
				'PLN' => '&#122;&#322;',
				'PRB' => '&#x440;.',
				'PYG' => '&#8370;',
				'QAR' => '&#x631;.&#x642;',
				'RMB' => '&yen;',
				'RON' => 'lei',
				'RSD' => '&#x434;&#x438;&#x43d;.',
				'RUB' => '&#8381;',
				'RWF' => 'Fr',
				'SAR' => '&#x631;.&#x633;',
				'SBD' => '&#36;',
				'SCR' => '&#x20a8;',
				'SDG' => '&#x62c;.&#x633;.',
				'SEK' => '&#107;&#114;',
				'SGD' => '&#36;',
				'SHP' => '&pound;',
				'SLL' => 'Le',
				'SOS' => 'Sh',
				'SRD' => '&#36;',
				'SSP' => '&pound;',
				'STD' => 'Db',
				'SYP' => '&#x644;.&#x633;',
				'SZL' => 'L',
				'THB' => '&#3647;',
				'TJS' => '&#x405;&#x41c;',
				'TMT' => 'm',
				'TND' => '&#x62f;.&#x62a;',
				'TOP' => 'T&#36;',
				'TRY' => '&#8378;',
				'TTD' => '&#36;',
				'TWD' => '&#78;&#84;&#36;',
				'TZS' => 'Sh',
				'UAH' => '&#8372;',
				'UGX' => 'UGX',
				'USD' => '&#36;',
				'UYU' => '&#36;',
				'UZS' => 'UZS',
				'VEF' => 'Bs F',
				'VND' => '&#8363;',
				'VUV' => 'Vt',
				'WST' => 'T',
				'XAF' => 'Fr',
				'XCD' => '&#36;',
				'XOF' => 'Fr',
				'XPF' => 'Fr',
				'YER' => '&#xfdfc;',
				'ZAR' => '&#82;',
				'ZMW' => 'ZK',
			);
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
			$order_post = wc_get_order( $order_id ); // Get order post object.
			$edit_link  = self::get_editor_link( $order_post );
			$event_data = array(
				'OrderID'          => esc_attr( $order_id ),
				'OrderTitle'       => sanitize_text_field( Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $order ) ),
				'OrderStatus'      => \wc_get_order_status_name( $status_to ),
				'OrderStatusSlug'  => sanitize_text_field( $status_to ),
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
						'SKU'              => self::get_product_sku( $product->get_id() ),
						'OrderStatus'      => \wc_get_order_status_name( $order_post->get_status() ),
						'OrderStatusSlug'  => $order_post->get_status(),
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

			if ( $order instanceof \WC_Order ) {
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
						'SKU'              => self::get_product_sku( $product->get_id() ),
						'OrderStatus'      => \wc_get_order_status_name( $order_post->get_status() ),
						'OrderStatusSlug'  => $order_post->get_status(),
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
								'SKU'              => self::get_product_sku( $product->get_id() ),
								'NewQuantity'      => $output[ 'order_item_qty' . $item_id ],
								'OldQuantity'      => $old_quantity,
								'ProductTitle'     => $product->get_name(),
								'OrderStatus'      => \wc_get_order_status_name( $order_post->get_status() ),
								'OrderStatusSlug'  => $order_post->get_status(),
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
						if ( intval( $output['line_total'][ $item_id ] ) !== intval( $item->get_amount() ) ) {
							$event_data = array(
								'OrderID'          => esc_attr( $order_id ),
								'OrderTitle'       => Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $order ),
								'FeeAmount'        => $output['line_total'][ $item_id ],
								'OldFeeAmount'     => $item->get_amount(),
								'OrderStatus'      => \wc_get_order_status_name( $order_post->get_status() ),
								'OrderStatusSlug'  => $order_post->get_status(),
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
			if ( Alert_Manager::will_or_has_triggered( 9133 ) || Alert_Manager::was_triggered_recently( 9133 ) ) {
				return false;
			}
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
		private static function check_order_modify_change( $order_id, $oldorder, $neworder ) {
			if ( 'trash' === $neworder->post_status ) {
				return 0;
			}

			// Dont fire if we know an item was added/removed recently.
			if ( Alert_Manager::was_triggered_recently( 9120 ) || Alert_Manager::was_triggered_recently( 9130 ) || Alert_Manager::was_triggered_recently( 9131 ) || Alert_Manager::was_triggered_recently( 9132 ) | Alert_Manager::was_triggered_recently( 9133 ) || Alert_Manager::was_triggered_recently( 9134 ) || Alert_Manager::was_triggered_recently( 9135 ) || Alert_Manager::was_triggered_recently( 9137 ) ) {
				return;
			}

			$order = wc_get_order( $order_id );
			$items = $order->get_items( array( 'fee' ) );

			self::event_order_items_quantity_changed( $order_id, $items );

			$difference = self::order_recursive_array_diff( (array) self::$_old_order, (array) $order );

			if ( ! empty( $difference ) ) {
				// Get editor link.
				$edit_link = self::get_editor_link( $oldorder );

				// Set event data.
				$event_data = array(
					'OrderID'          => esc_attr( $order_id ),
					'OrderTitle'       => sanitize_text_field( Woocommerce_Helper::wsal_woocommerce_extension_get_order_title( $order_id ) ),
					'OrderStatus'      => \wc_get_order_status_name( $neworder->post_status ),
					'OrderStatusSlug'  => sanitize_text_field( $neworder->post_status ),
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
						$rad = self::order_recursive_array_diff( $v, $new_details[ $k ] );
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
		private static function retrieve_attribute_data() {
			$save_attribute = isset( $_POST['save_attribute'] ) ? true : false;
			$post_type      = isset( $_GET['post_type'] ) ? sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) : false;
			$page           = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : false;
			$attribute_id   = isset( $_GET['edit'] ) ? absint( sanitize_text_field( wp_unslash( $_GET['edit'] ) ) ) : false;

			if ( $save_attribute && ! empty( $post_type ) && ! empty( $page ) && ! empty( $attribute_id ) && 'product' === $post_type && 'product_attributes' === $page ) {
				// Verify nonce.
				check_admin_referer( 'woocommerce-save-attribute_' . $attribute_id );

				// Get attribute data.
				self::$old_attr_data = wc_get_attribute( $attribute_id );
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
			if ( isset( $data['attribute_label'] ) && isset( self::$old_attr_data->name ) && $data['attribute_label'] !== self::$old_attr_data->name ) {
				$attr_event            = self::get_attribute_event_data( $id, $data );
				$attr_event['OldName'] = self::$old_attr_data->name;
				$attr_event['NewName'] = $data['attribute_label'];
				Alert_Manager::trigger_event( 9060, $attr_event );
			}

			// Check the attribute orderby.
			if ( isset( $data['attribute_orderby'] ) && isset( self::$old_attr_data->order_by ) && $data['attribute_orderby'] !== self::$old_attr_data->order_by ) {
				$attr_event                 = self::get_attribute_event_data( $id, $data );
				$attr_event['OldSortOrder'] = self::$old_attr_data->order_by;
				$attr_event['NewSortOrder'] = $data['attribute_orderby'];
				Alert_Manager::trigger_event( 9061, $attr_event );
			}

			// Check the attribute archives.
			if ( isset( $data['attribute_public'] ) && isset( self::$old_attr_data->has_archives ) && (int) self::$old_attr_data->has_archives !== $data['attribute_public'] ) {
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
		private static function get_attribute_event_data( $attr_id, $data ) {
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
				parse_str( $_POST['data'], $data );
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

			if ( isset( $order ) && ! empty( $order ) && ! empty( self::$_old_meta_data ) ) {

				$new_meta              = get_post_meta( $order_id, '', false );
				$compare_changed_items = array_diff_assoc(
					array_map( 'serialize', $new_meta ),
					array_map( 'serialize', self::$_old_meta_data )
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
		private static function check_attributes_change( $oldpost, $data = false ) {
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
									'SKU'                => self::get_product_sku( $product_id ),
									'ProductTitle'       => sanitize_text_field( $title ),
									'ProductStatus'      => sanitize_text_field( $status ),
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
								'SKU'                => self::get_product_sku( $product_id ),
								'ProductTitle'       => sanitize_text_field( $title ),
								'ProductStatus'      => sanitize_text_field( $status ),
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
									'SKU'                => self::get_product_sku( $product_id ),
									'ProductTitle'       => sanitize_text_field( $title ),
									'ProductStatus'      => sanitize_text_field( $status ),
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
									'SKU'                => self::get_product_sku( $product_id ),
									'ProductTitle'       => sanitize_text_field( $title ),
									'ProductStatus'      => sanitize_text_field( $status ),
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
									'SKU'                 => self::get_product_sku( $product_id ),
									'ProductTitle'        => sanitize_text_field( $title ),
									'ProductStatus'       => sanitize_text_field( $status ),
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
		private static function check_image_change( $oldpost, $data = false ) {

			if ( ! $data ) {
				$data = array_map( 'sanitize_text_field', wp_unslash( $_POST ) );
			}

			if ( ! isset( $data['_thumbnail_id'] ) ) {
				return 0;
			}

			// Setup our variables.
			$thumb_id                = get_post_thumbnail_id( $oldpost->ID );
			$old_attachment_metadata = self::$_old_attachment_metadata;
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
			$event_data['SKU']           = self::get_product_sku( $oldpost->ID );

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
				$event_data['old_path']  = ( isset( $old_attachment_metadata['file']) && ! empty( $old_attachment_metadata['file'] ) ) ? $get_upload_dir['basedir'] . DIRECTORY_SEPARATOR . $old_attachment_metadata['file'] : __( 'File is missing', 'wp-security-audit-log' );
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
		private static function check_download_limit_change( $oldpost, $data = false ) {

			if ( ! $data ) {
				$data = array_map( 'sanitize_text_field', wp_unslash( $_POST ) );
			}

			if ( ! isset( $data['_download_expiry'] ) || ! isset( $data['_download_limit'] ) ) {
				return 0;
			}

			$event_id     = false;
			$editor_link  = self::get_editor_link( self::$old_post );
			$alert_needed = false;

			// Push editor link into event data early.
			$event_data = array(
				$editor_link['name'] => $editor_link['value'],
			);

			$event_data['new_value']     = $data['_download_expiry'];
			$event_data['product_name']  = $data['post_title'];
			$event_data['ID']            = $data['post_ID'];
			$event_data['ProductStatus'] = $data['post_status'];

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
				$event_data['SKU']            = self::get_product_sku( $data['post_ID'] );
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
				$event_data['SKU']            = self::get_product_sku( $data['post_ID'] );
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
		private static function check_tax_status_change( $product, $oldpost, $post ) {
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
						'SKU'                => esc_attr( self::get_product_sku( $product->ID ) ),
						'ProductTitle'       => $product->post_title,
						'ProductStatus'      => $product->post_status,
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
						'SKU'                => esc_attr( self::get_product_sku( $product->ID ) ),
						'ProductTitle'       => $product->post_title,
						'ProductStatus'      => $product->post_status,
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
		private static function check_low_stock_threshold_change( $product, $oldpost, $post ) {
			$old_status = ( isset( $oldpost['_low_stock_amount'] ) ) ? $oldpost['_low_stock_amount'][0] : __( 'Store Default', 'wp-security-audit-log' );
			$status     = ( isset( $post['low_stock_amount'] ) && ! empty( $post['low_stock_amount'] ) ) ? $post['low_stock_amount'] : __( 'Store Default', 'wp-security-audit-log' );

			if ( $status !== $old_status ) {
				$editor_link = self::get_editor_link( $product );
				Alert_Manager::trigger_event(
					9119,
					array(
						'PostID'               => $product->ID,
						'SKU'                  => esc_attr( self::get_product_sku( $product->ID ) ),
						'ProductTitle'         => $product->post_title,
						'ProductStatus'        => $product->post_status,
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
		private static function check_variations_change( $oldpost, $data = false ) {
			if ( ! $data ) {
				$data = array_map( 'sanitize_text_field', wp_unslash( $_POST ) );
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
						$result = self::event_price( $product, 'Regular price', $old_price, $new_price );
					}

					// Check sale price.
					$old_sale_price = (int) $variation->get_sale_price();
					$new_sale_price = isset( $data['variable_sale_price'][ $key ] ) ? (int) sanitize_text_field( wp_unslash( $data['variable_sale_price'][ $key ] ) ) : false;
					if ( $old_sale_price !== $new_sale_price ) {
						$result = self::event_price( $product, 'Sale price', $old_sale_price, $new_sale_price );
					}

					// Check product SKU.
					$old_sku = $variation->get_sku();
					$new_sku = isset( $data['variable_sku'][ $key ] ) ? sanitize_text_field( wp_unslash( $data['variable_sku'][ $key ] ) ) : false;
					if ( $old_sku !== $new_sku ) {
						$result = self::check_sku_change( $product, $old_sku, $new_sku );
					}

					// Check product virtual.
					$virtual['old'] = $variation->is_virtual();
					$virtual['new'] = isset( $data['variable_is_virtual'][ $key ] ) ? true : false;
					if ( $virtual['old'] !== $virtual['new'] ) {
						$result = self::check_type_change( $product, null, $virtual );
					}

					// Check product downloadable.
					$download['old'] = $variation->is_downloadable();
					$download['new'] = isset( $data['variable_is_downloadable'][ $key ] ) ? true : false;
					if ( $download['old'] !== $download['new'] ) {
						$result = self::check_type_change( $product, null, false, $download );
					}

					// Check product stock status.
					$old_stock_status = $variation->get_stock_status();
					$new_stock_status = isset( $data['variable_stock_status'][ $key ] ) ? sanitize_text_field( wp_unslash( $data['variable_stock_status'][ $key ] ) ) : false;
					if ( $old_stock_status !== $new_stock_status ) {
						$result = self::check_stock_status_change( $product, $old_stock_status, $new_stock_status );
					}

					// Check product stock quantity.
					$old_stock = $variation->get_stock_quantity();
					$new_stock = isset( $data['variable_stock'][ $key ] ) ? (int) sanitize_text_field( wp_unslash( $data['variable_stock'][ $key ] ) ) : false;
					if ( $old_stock !== $new_stock ) {
						$result = self::check_stock_quantity_change( $product, $old_stock, $new_stock );
					}

					// Check product weight.
					$old_weight = $variation->get_weight();
					$new_weight = isset( $data['variable_weight'][ $key ] ) ? sanitize_text_field( wp_unslash( $data['variable_weight'][ $key ] ) ) : false;
					if ( $old_weight !== $new_weight ) {
						$result = self::check_weight_change( $product, $old_weight, $new_weight );
					}

					// Check product dimensions change.
					$length['old'] = $variation->get_length();
					$length['new'] = isset( $data['variable_length'][ $key ] ) ? sanitize_text_field( wp_unslash( $data['variable_length'][ $key ] ) ) : false;
					$width['old']  = $variation->get_width();
					$width['new']  = isset( $data['variable_width'][ $key ] ) ? sanitize_text_field( wp_unslash( $data['variable_width'][ $key ] ) ) : false;
					$height['old'] = $variation->get_height();
					$height['new'] = isset( $data['variable_height'][ $key ] ) ? sanitize_text_field( wp_unslash( $data['variable_height'][ $key ] ) ) : false;
					self::check_dimensions_change( $product, $length, $width, $height );

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
					self::check_downloadable_file_change( $product, $file_names, $file_urls );

					// Check backorders change.
					$old_backorder = $variation->get_backorders();
					$new_backorder = isset( $data['variable_backorders'][ $key ] ) ? sanitize_text_field( wp_unslash( $data['variable_backorders'][ $key ] ) ) : false;
					self::check_backorders_setting( $product, $old_backorder, $new_backorder );
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
		private static function get_attribute_key( $attribute_name = '' ) {
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
		private static function get_string_attribute_value( $attribute_value = '' ) {
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
		private static function get_wc_product_attributes( $product, $taxonomy ) {
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

			if ( ! empty( $meta_key ) && 'shop_coupon' === $coupon->post_type && in_array( $meta_key, self::$coupon_meta, true ) ) {
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
			if ( ! empty( $meta_key ) && ( ! in_array( $meta_key, self::$coupon_meta, true ) || 'shop_coupon' !== $coupon->post_type ) ) {
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
				$coupon_data = self::get_coupon_event_data( $coupon );

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
				$post_attributes['val'] = isset( self::$_old_meta_data ) ? self::$_old_meta_data[ $meta_key ][0] : false;
				$post_attributes        = (object) $post_attributes;
				self::log_coupon_meta_update_events( $log_event, $meta_key, $meta_value, $post_attributes, $coupon );
				return false;
			}

			if ( ! empty( $meta_key ) && 'shop_coupon' === $coupon->post_type && in_array( $meta_key, self::$coupon_meta, true ) ) {
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

			self::$wc_user_meta[ $meta_id ] = (object) array(
				'key'   => $meta_key,
				'value' => get_user_meta( $user_id, $meta_key, true ),
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

			if ( ! isset( self::$wc_user_meta[ $meta_id ] ) || ! is_object( self::$wc_user_meta[ $meta_id ] ) ) {
				self::$wc_user_meta[ $meta_id ] = (object) array(
					'key'   => $meta_key,
					'value' => 'None supplied', // Not translatable as its internal only, we use a translatable string for display later on.
				);
				$is_first_edit                  = true;
			}

			$current_value = get_user_meta( $user_id, self::$wc_user_meta[ $meta_id ]->key, true );

			if ( isset( self::$wc_user_meta[ $meta_id ] ) && $current_value !== self::$wc_user_meta[ $meta_id ]->value ) {
				if ( self::$wc_user_meta[ $meta_id ]->value !== $meta_value ) {
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
						foreach ( self::$wc_user_meta as $user_meta ) {
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
						foreach ( self::$wc_user_meta as $user_meta ) {
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
						'SKU'                => esc_attr( self::get_product_sku( $post->ID ) ),
						'ProductTitle'       => $post->post_title,
						'ProductStatus'      => ! $product_status ? $post->post_status : $product_status,
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
		 *
		 * @since 4.6.0
		 */
		public static function check_product_changes_before_save( $product, $data_store ) {
			// If we reach here without any POST data, the change is beong done directly so lets update the old item data in case anything changes.
			if ( ! isset( $_POST['action'] ) ) {
				// Update held data.
				$product_id           = $product->get_id();
				self::$old_product    = wc_get_product( $product_id );
				self::$old_post       = get_post( $product_id );
				self::$old_data       = self::get_product_data( self::$old_product );
				self::$_old_meta_data = get_post_meta( $product_id, '', false );
			}

			if ( isset( $_POST['_ajax_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_ajax_nonce'] ) ), 'ac-ajax' ) || isset( $_REQUEST['rest_route'] ) && ( '/wc/v3/products/batch' === $_REQUEST['rest_route'] ) ) {
				// Get product data.
				$product_id       = $product->get_id();
				$old_product      = wc_get_product( $product_id );
				$old_product_post = get_post( $product_id );

				// Check for stock quantity changes.
				self::check_stock_quantity_change( $old_product_post, $old_product->get_stock_quantity(), $product->get_stock_quantity() );
			}

			if ( isset( $_REQUEST['action'] ) && ( 'woocommerce_feature_product' === $_REQUEST['action'] ) && check_admin_referer( 'woocommerce-feature-product' ) ) {
				$product_id   = $product->get_id();
				$product_post = get_post( $product_id );
				$editor_link  = self::get_editor_link( $product_post );
				Alert_Manager::trigger_event(
					9043,
					array(
						'PostID'             => esc_attr( $product->get_id() ),
						'SKU'                => esc_attr( self::get_product_sku( $product_id ) ),
						'ProductTitle'       => sanitize_text_field( $product_post->post_title ),
						'ProductStatus'      => sanitize_text_field( $product_post->post_status ),
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
