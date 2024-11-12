<?php
/**
 * Woocommerce Sensor helper.
 *
 * @since     4.6.0
 *
 * @package   wsal
 * @subpackage sensors
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors\Helpers;

use WSAL\Controllers\Alert_Manager;
use WSAL\Helpers\WP_Helper;
use WSAL\Helpers\Settings_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\Helpers\Woocommerce_Helper' ) ) {
	/**
	 * Helper Sensor class for Woocommerce.
	 *
	 * @package    wsal
	 * @subpackage sensors-helpers
	 *
	 * @since 4.6.0
	 */
	class Woocommerce_Helper {
		/**
		 * Ensures our appended setting gets saved when updating via ToggleEvents screen.
		 *
		 * @param array $post_data - POSTed settings data.
		 *
		 * @return void
		 *
		 * @since 4.6.0
		 */
		public static function wsal_woocommerce_extension_togglealerts_process_save_settings( $post_data ) {
			Settings_Helper::set_boolean_option_value( 'wc-all-stock-changes', isset( $post_data['wc_all_stock_changes'] ) );
		}

		/**
		 * Detect file downloads within WC. This is here and not in a sensor as we currently cant seem to catch this filter
		 * using the usual init priority.
		 *
		 * @param string $download_get_user_email  User email.
		 * @param string $download_get_order_key   Order key.
		 * @param string $download_get_product_id  Item id.
		 * @param string $download_get_user_id     User ID.
		 * @param string $download_get_download_id Download ID.
		 * @param string $download_get_order_id    Order ID.
		 *
		 * @since 4.6.0
		 */
		public static function wsal_woocommerce_extension_detect_file_download( $download_get_user_email, $download_get_order_key, $download_get_product_id, $download_get_user_id, $download_get_download_id, $download_get_order_id ) {
			$product       = wc_get_product( $download_get_product_id );
			$product_title = $product->get_title();

			$sku = $product->get_sku();

			Alert_Manager::trigger_event(
				9099,
				array(
					'product_name'  => $product_title,
					'ID'            => $download_get_product_id,
					'SKU'           => ( $sku ) ? $sku : __( 'Not provided', 'wp-security-audit-log' ),
					'email_address' => $download_get_user_email,
				)
			);
		}

		/**
		 * Append some extra content below an event in the ToggleAlerts view.
		 *
		 * @param int $alert_id - WSAL alert ID.
		 *
		 * @return void
		 *
		 * @since 4.6.0
		 */
		public static function wsal_woocommerce_extension_append_content_to_toggle( $alert_id ) {
			if ( 9035 === $alert_id ) {
				$frontend_events        = Settings_Helper::get_frontend_events();
				$enable_wc_for_visitors = ( isset( $frontend_events['woocommerce'] ) && $frontend_events['woocommerce'] ) ? true : false;
				?>
				<tr class="alert-wrapper" data-alert-cat="WooCommerce" data-alert-subcat="Products" data-is-attached-to-alert="9035">
					<td></td>
					<td>
					<input name="frontend-events[woocommerce]" type="checkbox" id="frontend-events[woocommerce]" value="1" <?php checked( $enable_wc_for_visitors ); ?> />
					</td>
					<td colspan="2"><?php esc_html_e( 'Keep a log of visitor orders, stock changes and other public events?', 'wp-security-audit-log' ); ?></td>
				</tr>
				<?php
			}

			if ( 9019 === $alert_id ) {
				if ( class_exists( '\WSAL\Helpers\Settings_Helper' ) ) {
					$wc_all_stock_changes = Settings_Helper::get_boolean_option_value( 'wc-all-stock-changes', true );
				}

				?>
					<tr class="alert-wrapper" data-alert-cat="WooCommerce" data-alert-subcat="woocommerce-order" data-is-attached-to-alert="9019">
						<td></td>
						<td>
							<input name="wc_all_stock_changes" type="checkbox" id="wc_all_stock_changes" value="1" <?php checked( $wc_all_stock_changes ); ?> />
						</td>
						<td colspan="2"><?php esc_html_e( 'Log all stock changes. Disable this setting to only keep a log of stock changes done manually via the WooCommerce dashboard. Therefore automated stock changes typically done via customers placing orders or via other plugins will not be logged.', 'wp-security-audit-log' ); ?></td>
					</tr>
					<script type="text/javascript">
					jQuery(document).ready(function(){
						// Specific for alert 9019
						jQuery("input[value=9019]").on("change", function(){
							var check = jQuery("input[value=9019]").is(":checked");
							if(check) {
								jQuery("#wc_all_stock_changes").attr ( "checked" ,"checked" );
							} else {
								jQuery("#wc_all_stock_changes").removeAttr('checked');
							}
						});
					});
					</script>
				<?php
			}
		}

		/**
		 * Adds new custom event objects for our plugin.
		 *
		 * @method wsal_woocommerce_extension_add_custom_event_type
		 *
		 * @param array $types An array of default types.
		 *
		 * @return array
		 *
		 * @since 4.6.0
		 */
		public static function wsal_woocommerce_extension_add_custom_event_type( $types ) {
			$new_types = array(
				'downloaded' => __( 'Downloaded', 'wp-security-audit-log' ),
			);

			// combine the two arrays.
			$types = array_merge( $types, $new_types );

			return $types;
		}

		/**
		 * Adds new custom event objects for our plugin.
		 *
		 * @method wsal_woocommerce_extension_add_custom_event_objects
		 *
		 * @param array $objects An array of default objects.
		 *
		 * @return array
		 *
		 * @since 4.6.0
		 */
		public static function wsal_woocommerce_extension_add_custom_event_objects( $objects ) {
			$new_objects = array(
				'woocommerce-product'  => __( 'WooCommerce Product', 'wp-security-audit-log' ),
				'woocommerce-store'    => __( 'WooCommerce Store', 'wp-security-audit-log' ),
				'woocommerce-coupon'   => __( 'WooCommerce Coupon', 'wp-security-audit-log' ),
				'woocommerce-category' => __( 'WooCommerce Category', 'wp-security-audit-log' ),
				'woocommerce-tag'      => __( 'WooCommerce Tag', 'wp-security-audit-log' ),
				'woocommerce-order'    => __( 'WooCommerce Order', 'wp-security-audit-log' ),
			);

			// combine the two arrays.
			$objects = array_merge( $objects, $new_objects );

			return $objects;
		}

		/**
		 * Adds new ignored CPT for our plugin.
		 *
		 * @method wsal_woocommerce_extension_add_custom_event_object_text
		 *
		 * @param array $post_types An array of default post_types.
		 *
		 * @return array
		 *
		 * @since 4.6.0
		 */
		public static function wsal_woocommerce_extension_add_custom_ignored_cpt( $post_types ) {
			$new_post_types = self::get_custom_post_types();

			// combine the two arrays.
			$post_types = array_merge( $post_types, $new_post_types );

			return $post_types;
		}

		/**
		 * Adds new meta formatting for our plugion.
		 *
		 * @method wsal_woocommerce_extension_add_custom_meta_format
		 *
		 * @param string              $value           Meta value.
		 * @param string              $expression      Meta expression including the surrounding percentage chars.
		 * @param WSAL_AlertFormatter $alert_formatter Alert formatter class.
		 * @param int|null            $occurrence_id   Occurrence ID. Only present if the event was already written to the database. Default null.
		 *
		 * @return string
		 *
		 * @since 4.6.0
		 */
		public static function wsal_woocommerce_extension_add_custom_meta_format( $value, $expression, $alert_formatter, $occurrence_id ) {
			if ( '%StockOrderID%' === $expression ) {
				$check_value = \strip_tags( (string) $value );
				if ( ! empty( $check_value ) && 'NULL' !== $check_value ) {
					$new_order    = new \WC_Order( strip_tags( $value ) );
					$editor_title = self::wsal_woocommerce_extension_get_order_title( $new_order );

					return isset( $editor_title ) ? '<strong>' . $editor_title . '</strong>' : '';
				} else {
					return 'N/A';
				}
			}

			return $value;
		}

		/**
		 * Add our sensor to the available public sensors within WSAL.
		 *
		 * @param array $value - Current public sensors.
		 *
		 * @return array $value - Appended array of sensors.
		 *
		 * @since 4.6.0
		 */
		public static function wsal_woocommerce_extension_load_public_sensors( $value ) {
			$value[] = 'WooCommerce_Public';

			return $value;
		}

		/**
		 * Get editor link.
		 *
		 * @param WP_Post $post - Product post object.
		 *
		 * @return array $editor_link - Name and value link.
		 *
		 * @since 4.6.0
		 */
		public static function wsal_woocommerce_extension_get_editor_link( $post ) {
			// Meta value key.
			if ( isset( $post->post_type ) && 'shop_order' === $post->post_type ) {
				$name = 'EditorLinkOrder';
			} else {
				$name = 'EditorLinkProduct';
			}

			if ( ! isset( $post->ID ) ) {
				return false;
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
					$link = \network_admin_url( sprintf( $post_type_object->_edit_link . $action, $post->ID ) );
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
		 * Get order title from order object.
		 *
		 * @param int $order_id - WC Order ID.
		 *
		 * @return string - Order title.
		 *
		 * @since 4.6.0
		 */
		public static function wsal_woocommerce_extension_get_order_title( $order_id ) {
			if ( ! $order_id ) {
				return false;
			}
			if ( is_a( $order_id, 'WC_Order' ) ) {
				$order = $order_id;
			} elseif ( is_int( $order_id ) ) {
				$order = new \WC_Order( $order_id );
			} else {
				$order = wc_get_order( $order_id );
			}

			// Final check.
			if ( ! $order || ! $order instanceof \WC_Order || ! method_exists( $order, 'get_billing_first_name' ) ) {
				return false;
			}

			$buyer = '';
			if ( $order->get_billing_first_name() || $order->get_billing_last_name() ) {
				$buyer = trim( sprintf( '%1$s %2$s', $order->get_billing_first_name(), $order->get_billing_last_name() ) );
			} elseif ( $order->get_billing_company() ) {
				$buyer = trim( $order->get_billing_company() );
			} elseif ( $order->get_customer_id() ) {
				$user  = get_user_by( 'id', $order->get_customer_id() );
				$buyer = ucwords( $user->display_name );
			}

			return ( ! empty( $buyer ) ) ? '#' . $order->get_order_number() . ' ' . $buyer : '#' . $order->get_order_number();
		}

		/**
		 * Add sub category titles to ToggleView page in WSAL.
		 *
		 * @param string $title    - String on which to append our title.
		 * @param string $alert_id - WSAL alert ID.
		 *
		 * @return string - Our custom title.
		 *
		 * @since 4.6.0
		 */
		public static function wsal_woocommerce_extension_togglealerts_sub_category_titles( $title = '', $alert_id = '' ) {
			if ( 9105 === $alert_id ) {
				$title = esc_html__( 'Product stock changes:', 'wp-security-audit-log' );
			}
			if ( 9007 === $alert_id ) {
				$title = esc_html__( 'Product admin:', 'wp-security-audit-log' );
			}
			if ( 9015 === $alert_id ) {
				$title = esc_html__( 'Product changes:', 'wp-security-audit-log' );
			}
			if ( 9047 === $alert_id ) {
				$title = esc_html__( 'Product attributes:', 'wp-security-audit-log' );
			}

			return $title;
		}

		/**
		 * Add specific events so we can use them for category titles.
		 *
		 * @param array $sub_category_events - Current events with a sub-title.
		 *
		 * @return array $sub_category_events - Array with our items appended.
		 *
		 * @since 4.6.0
		 */
		public static function wsal_woocommerce_extension_togglealerts_sub_category_events( $sub_category_events ) {
			$new_events          = array( 9105, 9007, 9015, 9047 );
			$sub_category_events = array_merge( $sub_category_events, $new_events );

			return $sub_category_events;
		}

		/**
		 * In some cases depending on the version of WSAL, the input checkboxes are disabled as
		 * WSAL things is_woocommerce_active is false. This ensures that cannot happen and the user is
		 * always able to check/uncheck WC events as normal regardless of WSAL version.
		 *
		 * @since 4.6.0
		 */
		public static function wsal_woocommerce_extension_togglealerts_js_code() {
			global $current_screen;
			// Only dequeue on our admin pages.
			if ( isset( $current_screen->base ) && false !== strpos( $current_screen->base, 'wsal-togglealerts' ) ) {
				?>
				<script type="text/javascript">
				jQuery(document).ready(function(){
					jQuery( '#tab-woocommerce [type="checkbox"]' ).removeAttr( 'disabled' );
				});
				</script>
				<?php
			}
			if ( isset( $_REQUEST['page'] ) && 'wsal-togglealerts' === $_REQUEST['page'] ) {
				?>
				<style type="text/css">
					#tab-payment-gateways tr:nth-of-type(2), #tab-products tr:nth-of-type(12), #tab-coupons tr:nth-of-type(8) {
					display: none;
					}
				</style>
				<?php
			}
		}

		/**
		 * Add obsolete events to the togglealerts view.
		 *
		 * @param array $obsolete_events - Currently obsolete events.
		 *
		 * @return array $obsolete_events - Appended events.
		 *
		 * @since 4.6.0
		 */
		public static function wsal_woocommerce_extension_togglealerts_obsolete_events( $obsolete_events ) {
			$new_events      = array( 9011, 9075 );
			$obsolete_events = array_merge( $obsolete_events, $new_events );

			return $obsolete_events;
		}

		/**
		 * Creates an editor link for a given  hook_ID.
		 *
		 * @param  int $webhook_id - Hook ID.
		 * @return string $editor_link - URL to edit screen.
		 *
		 * @since 4.6.0
		 */
		public static function create_webhook_editor_link( $webhook_id ) {
			$editor_link = esc_url(
				add_query_arg(
					array(
						'tab'          => 'advanced',
						'section'      => 'webhooks',
						'edit-webhook' => $webhook_id,
					),
					admin_url( 'admin.php?page=wc-settings' )
				)
			);

			return $editor_link;
		}

		/**
		 * Builds category link.
		 *
		 * @param integer $tag_id   - Tag ID.
		 * @param string  $taxonomy - Taxonomy.
		 * @return string|null - Link.
		 *
		 * @since 4.6.0
		 */
		public static function get_taxonomy_edit_link( $tag_id, $taxonomy = 'product_cat' ) {
			$tag_args = array(
				'post_type' => 'product',
				'taxonomy'  => $taxonomy,
				'tag_ID'    => $tag_id,
			);
			return ! empty( $tag_id ) ? add_query_arg( $tag_args, \network_admin_url( 'term.php' ) ) : null;
		}

		/**
		 * Check if meta key belongs to WooCommerce user meta.
		 *
		 * @since 3.4
		 *
		 * @param string $meta_key - Meta key.
		 * @return boolean
		 *
		 * @since 4.6.0
		 */
		public static function is_woocommerce_user_meta( $meta_key ) {
			// Remove the prefix to avoid redundancy in the meta keys.
			$address_key = str_replace( array( 'shipping_', 'billing_' ), '', $meta_key );

			// WC address meta keys without prefix.
			$meta_keys = array( 'first_name', 'last_name', 'company', 'country', 'address_1', 'address_2', 'city', 'state', 'postcode', 'phone', 'email' );

			if ( in_array( $address_key, $meta_keys, true ) ) {
				return true;
			}
			return false;
		}

		/**
		 * Checks if the Woocommerce is active.
		 *
		 * @return bool
		 *
		 * @since 4.6.0
		 */
		public static function is_woocommerce_active() {
			return WP_Helper::is_plugin_active( 'woocommerce/woocommerce.php' );
		}

		/**
		 * Returns a list of custom post types associated with particular extension.
		 *
		 * @return array List of custom post types.
		 *
		 * @since 4.5.0
		 */
		public static function get_custom_post_types(): array {
			return array(
				'product',
				'shop_coupon',
				'shop_order',
				'shop_order_refund',
				'product_variation',
				'wc_product_tab',
			);
		}

		/**
		 * Further process the $_POST data upon saving events in the ToggleAlerts view.
		 *
		 * @param array  $disabled          Empty array which we will fill if needed.
		 * @param object $registered_alerts Currently registered alerts.
		 * @param array  $frontend_events   Array of currently enabled frontend events, taken from POST data.
		 * @param array  $enabled           Currently enabled events.
		 *
		 * @return array Disabled events.
		 *
		 * @since 4.6.0
		 */
		public static function save_settings_disabled_events( $disabled, $registered_alerts, $frontend_events, $enabled ) {
			// Now we check all registered events for further processing.
			foreach ( $disabled as $alert ) {
				// Disable Visitor events if the user disabled the event there are "tied to" in the UI.
				if ( 9035 === $alert ) {
					$frontend_events = array_merge( $frontend_events, array( 'woocommerce' => false ) );
					Settings_Helper::set_frontend_events( $frontend_events );
				}
			}

			return $disabled;
		}

		/**
		 * Gets the filename of the plugin this extension is targeting.
		 *
		 * @return string Filename.
		 *
		 * @since 4.5.0
		 */
		public static function get_plugin_filename(): string {
			return 'wp-activity-log-for-woocommerce/wsal-woocommerce.php';
		}
	}
}
