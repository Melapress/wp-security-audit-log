<?php
/**
 * Custom Alerts for WooCommerce plugin.
 *
 * Class file for alert manager.
 *
 * @since   1.0.0
 *
 * @package wsal
 * @subpackage wsal-woocommerce-forms
 */

// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.WP.I18n.UnorderedPlaceholdersText

declare(strict_types=1);

namespace WSAL\WP_Sensors\Alerts;

use WSAL\MainWP\MainWP_Addon;
use WSAL\WP_Sensors\Helpers\Woocommerce_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\Alerts\WooCommerce_Custom_Alerts' ) ) {
	/**
	 * Custom sensor for Yoast plugin.
	 *
	 * @since 4.6.0
	 */
	class WooCommerce_Custom_Alerts {
		/**
		 * Returns the structure of the alerts for extension.
		 *
		 * @since 4.6.0
		 */
		public static function get_custom_alerts(): array {
			// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
			if ( Woocommerce_Helper::is_woocommerce_active() || MainWP_Addon::check_mainwp_plugin_active() ) {
				return array(
					esc_html__( 'WooCommerce', 'wp-security-audit-log' ) => array(
						esc_html__( 'Products', 'wp-security-audit-log' ) => self::get_products_array(),

						esc_html__( 'Store', 'wp-security-audit-log' ) => self::get_store_array(),

						esc_html__( 'Payment Gateways', 'wp-security-audit-log' ) => self::get_payment_getaways_array(),

						esc_html__( 'Tax Settings', 'wp-security-audit-log' ) => self::get_tax_settings_array(),

						esc_html__( 'WC Categories', 'wp-security-audit-log' ) => self::get_wc_categories_array(),

						esc_html__( 'WC Tags', 'wp-security-audit-log' ) => self::get_wc_tags_array(),

						esc_html__( 'Attributes', 'wp-security-audit-log' ) => self::get_attributes_array(),

						esc_html__( 'Coupons', 'wp-security-audit-log' ) => self::get_coupons_array(),

						esc_html__( 'Orders', 'wp-security-audit-log' ) => self::get_orders_array(),

						esc_html__( 'User Profile', 'wp-security-audit-log' ) => self::get_user_profile_array(),
					),
				);
			}

			return array();
		}

		/**
		 * Returns array with all the events attached to the sensor (if there are different types of events, that method will merge them into one array - the events ids will be uses as keys).
		 *
		 * @since 4.6.0
		 */
		public static function get_alerts_array(): array {
			return self::get_products_array() +
			self::get_store_array() +
			self::get_payment_getaways_array() +
			self::get_tax_settings_array() +
			self::get_wc_categories_array() +
			self::get_wc_tags_array() +
			self::get_attributes_array() +
			self::get_coupons_array() +
			self::get_orders_array() +
			self::get_user_profile_array();
		}

		/**
		 * Returns the array with products alerts.
		 *
		 * @since 4.6.0
		 */
		private static function get_products_array(): array {
			return array(
				9000 => array(
					9000,
					WSAL_LOW,
					esc_html__( 'User created a new product', 'wp-security-audit-log' ),
					esc_html__( 'Created a new product called %ProductTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'created',
				),
				9001 => array(
					9001,
					WSAL_MEDIUM,
					esc_html__( 'User published a product', 'wp-security-audit-log' ),
					esc_html__( 'Published the product called %ProductTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'published',
				),
				9003 => array(
					9003,
					WSAL_LOW,
					esc_html__( 'User changed the category of a product', 'wp-security-audit-log' ),
					esc_html__( 'Changed the category(ies) of the product %ProductTitle% to %NewCategories%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
						esc_html__( 'Previous categories', 'wp-security-audit-log' ) => '%OldCategories%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'modified',
				),
				9004 => array(
					9004,
					WSAL_INFORMATIONAL,
					esc_html__( 'User modified the short description of a product', 'wp-security-audit-log' ),
					esc_html__( 'Changed the short description of the product %ProductTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'modified',
				),
				9005 => array(
					9005,
					WSAL_LOW,
					esc_html__( 'User modified the text of a product', 'wp-security-audit-log' ),
					esc_html__( 'Changed the text of the product %ProductTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'modified',
				),
				9006 => array(
					9006,
					WSAL_LOW,
					esc_html__( 'User changed the URL of a product', 'wp-security-audit-log' ),
					esc_html__( 'Changed the URL of the product %ProductTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
						esc_html__( 'Previous URL', 'wp-security-audit-log' ) => '%OldUrl%',
						esc_html__( 'New URL', 'wp-security-audit-log' ) => '%NewUrl%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'modified',
				),
				9007 => array(
					9007,
					WSAL_MEDIUM,
					esc_html__( 'User changed the Product Data of a product', 'wp-security-audit-log' ),
					esc_html__( 'Changed the type of the product %ProductTitle% to %NewType%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
						esc_html__( 'Previous type', 'wp-security-audit-log' ) => '%OldType%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'modified',
				),
				9008 => array(
					9008,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed the date of a product', 'wp-security-audit-log' ),
					esc_html__( 'Changed the date of the product %ProductTitle% to %NewDate%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
						esc_html__( 'Previous date', 'wp-security-audit-log' ) => '%OldDate%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'modified',
				),
				9009 => array(
					9009,
					WSAL_MEDIUM,
					esc_html__( 'User changed the visibility of a product', 'wp-security-audit-log' ),
					esc_html__( 'Changed the visibility of the product %ProductTitle% to %NewVisibility%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
						esc_html__( 'Previous visibility', 'wp-security-audit-log' ) => '%OldVisibility%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'modified',
				),
				9010 => array(
					9010,
					WSAL_MEDIUM,
					esc_html__( 'User modified the product', 'wp-security-audit-log' ),
					esc_html__( 'Modified the product %ProductTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'modified',
				),
				9011 => array(
					9011,
					E_NOTICE,
					esc_html__( 'User modified the draft product', 'wp-security-audit-log' ),
					esc_html__( 'Modified the draft product %ProductTitle%.', 'wp-security-audit-log' ),
					array(),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'',
				),
				9012 => array(
					9012,
					WSAL_HIGH,
					esc_html__( 'User moved a product to trash', 'wp-security-audit-log' ),
					esc_html__( 'Moved the product %ProductTitle% to trash.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
					),
					array(),
					'woocommerce-product',
					'deleted',
				),
				9013 => array(
					9013,
					WSAL_MEDIUM,
					esc_html__( 'User permanently deleted a product', 'wp-security-audit-log' ),
					esc_html__( 'Permanently deleted the product %ProductTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
					),
					array(),
					'woocommerce-product',
					'deleted',
				),
				9014 => array(
					9014,
					WSAL_HIGH,
					esc_html__( 'User restored a product from the trash', 'wp-security-audit-log' ),
					esc_html__( 'Restored the product %ProductTitle% from trash.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'restored',
				),
				9015 => array(
					9015,
					WSAL_MEDIUM,
					esc_html__( 'User changed status of a product', 'wp-security-audit-log' ),
					esc_html__( 'Changed the status of the product %ProductTitle% to %NewStatus%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Previous status', 'wp-security-audit-log' ) => '%OldStatus%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'modified',
				),
				9072 => array(
					9072,
					WSAL_INFORMATIONAL,
					esc_html__( 'User opened a product in the editor', 'wp-security-audit-log' ),
					esc_html__( 'Opened the product %ProductTitle% in the editor.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'opened',
				),
				9073 => array(
					9073,
					WSAL_INFORMATIONAL,
					esc_html__( 'User viewed a product', 'wp-security-audit-log' ),
					esc_html__( 'Viewed the product %ProductTitle% page.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'viewed',
				),
				9077 => array(
					9077,
					WSAL_MEDIUM,
					esc_html__( 'User renamed a product', 'wp-security-audit-log' ),
					esc_html__( 'Renamed the product %OldTitle% to %NewTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'renamed',
				),

				9016 => array(
					9016,
					WSAL_MEDIUM,
					esc_html__( 'User changed type of a price', 'wp-security-audit-log' ),
					esc_html__( 'Changed the %PriceType% price of the product %ProductTitle% to %NewPrice%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
						esc_html__( 'Previous price', 'wp-security-audit-log' ) => '%OldPrice%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'modified',
				),
				9017 => array(
					9017,
					WSAL_MEDIUM,
					esc_html__( 'User changed the SKU of a product', 'wp-security-audit-log' ),
					esc_html__( 'Changed the SKU of the product %ProductTitle% to %NewSku%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
						esc_html__( 'Previous SKU', 'wp-security-audit-log' ) => '%OldSku%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'modified',
				),
				9018 => array(
					9018,
					WSAL_LOW,
					esc_html__( 'User changed the stock status of a product', 'wp-security-audit-log' ),
					esc_html__( 'Changed the stock status of the product %ProductTitle% to %NewStatus%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
						esc_html__( 'Previous stock status', 'wp-security-audit-log' ) => '%OldStatus%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'modified',
				),
				9020 => array(
					9020,
					WSAL_MEDIUM,
					esc_html__( 'User set a product type', 'wp-security-audit-log' ),
					esc_html__( 'Changed the type of the product %ProductTitle% to %NewType%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
						esc_html__( 'Previous type', 'wp-security-audit-log' ) => '%OldType%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'modified',
				),
				9021 => array(
					9021,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed the weight of a product', 'wp-security-audit-log' ),
					esc_html__( 'Changed the weight of the product %ProductTitle% to %NewWeight%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
						esc_html__( 'Previous weight', 'wp-security-audit-log' ) => '%OldWeight%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'modified',
				),
				9022 => array(
					9022,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed the dimensions of a product', 'wp-security-audit-log' ),
					esc_html__( 'Changed the %DimensionType% dimensions of the product %ProductTitle% to %NewDimension%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
						esc_html__( 'Previous value', 'wp-security-audit-log' ) => '%OldDimension%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'modified',
				),
				9023 => array(
					9023,
					WSAL_MEDIUM,
					esc_html__( 'User added the Downloadable File to a product', 'wp-security-audit-log' ),
					esc_html__( 'Added the downloadable file %FileName% to the product %ProductTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
						esc_html__( 'File URL', 'wp-security-audit-log' ) => '%FileUrl%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'modified',
				),
				9024 => array(
					9024,
					WSAL_MEDIUM,
					esc_html__( 'User Removed the Downloadable File from a product', 'wp-security-audit-log' ),
					esc_html__( 'Removed the downloadable file %FileName% from the product %ProductTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
						esc_html__( 'File URL', 'wp-security-audit-log' ) => '%FileUrl%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'modified',
				),
				9025 => array(
					9025,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed the name of a Downloadable File in a product', 'wp-security-audit-log' ),
					esc_html__( 'Changed the name of the downloadable file %OldName% to %NewName% in the product %ProductTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
						esc_html__( 'Previous file name', 'wp-security-audit-log' ) => '%OldName%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'modified',
				),
				9026 => array(
					9026,
					WSAL_MEDIUM,
					esc_html__( 'User changed the URL of the Downloadable File in a product', 'wp-security-audit-log' ),
					esc_html__( 'Changed the URL of the downloadable file to the product %ProductTitle%', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
						esc_html__( 'File name', 'wp-security-audit-log' ) => '%FileName%',
						esc_html__( 'Previous URL', 'wp-security-audit-log' ) => '%OldUrl%',
						esc_html__( 'New URL', 'wp-security-audit-log' ) => '%NewUrl%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'modified',
				),
				9042 => array(
					9042,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed the catalog visibility of a product', 'wp-security-audit-log' ),
					esc_html__( 'Changed the product visibility of the product %ProductTitle% to %NewVisibility%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
						esc_html__( 'Previous visibility setting', 'wp-security-audit-log' ) => '%OldVisibility%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'modified',
				),
				9043 => array(
					9043,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed the setting Featured Product of a product', 'wp-security-audit-log' ),
					__( 'Changed the status of the setting <strong>Featured Product</strong> for the product %ProductTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'enabled',
				),
				9044 => array(
					9044,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed the Allow Backorders setting of a product', 'wp-security-audit-log' ),
					esc_html__( 'Changed the Allow Backorders setting for the product %ProductTitle% to %NewStatus%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
						esc_html__( 'Previous status', 'wp-security-audit-log' ) => '%OldStatus%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'modified',
				),
				9045 => array(
					9045,
					WSAL_MEDIUM,
					esc_html__( 'User added/removed products to upsell of a product', 'wp-security-audit-log' ),
					esc_html__( 'Products to the Upsell setting in product %ProductTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
						esc_html__( 'New product in Upsells', 'wp-security-audit-log' ) => '%UpsellTitle%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'added',
				),
				9046 => array(
					9046,
					WSAL_MEDIUM,
					esc_html__( 'User added/removed products to cross-sells of a product', 'wp-security-audit-log' ),
					esc_html__( 'Products to the Cross-sell setting in product %ProductTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
						esc_html__( 'New product in Cross-sells', 'wp-security-audit-log' ) => '%CrossSellTitle%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'added',
				),

				9095 => array(
					9095,
					WSAL_LOW,
					esc_html__( 'Added or deleted a product image', 'wp-security-audit-log' ),
					esc_html__( 'The product image of the product %ProductTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
						esc_html__( 'Image name', 'wp-security-audit-log' ) => '%name%',
						esc_html__( 'Image path', 'wp-security-audit-log' ) => '%path%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'added',
				),
				9096 => array(
					9096,
					WSAL_LOW,
					esc_html__( 'Modified a product image', 'wp-security-audit-log' ),
					esc_html__( 'Changed the product image of the product %ProductTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
						esc_html__( 'Previous image name', 'wp-security-audit-log' ) => '%old_name%',
						esc_html__( 'Previous image path', 'wp-security-audit-log' ) => '%old_path%',
						esc_html__( 'New image name', 'wp-security-audit-log' ) => '%name%',
						esc_html__( 'New image path', 'wp-security-audit-log' ) => '%path%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'modified',
				),
				9097 => array(
					9097,
					WSAL_LOW,
					esc_html__( 'Modified the download limit of the product', 'wp-security-audit-log' ),
					esc_html__( 'Changed the download limit of the product %product_name% to %new_value%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%ID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
						esc_html__( 'Previous value', 'wp-security-audit-log' ) => '%previous_value%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'modified',
				),
				9098 => array(
					9098,
					WSAL_LOW,
					esc_html__( 'Modified the download expiry setting of the product', 'wp-security-audit-log' ),
					esc_html__( 'Changed the download expire setting of the product %product_name% to %new_value%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%ID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
						esc_html__( 'Previous value', 'wp-security-audit-log' ) => '%previous_value%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'modified',
				),
				9099 => array(
					9099,
					WSAL_LOW,
					esc_html__( 'A product was downloaded', 'wp-security-audit-log' ),
					esc_html__( 'Downloaded the product %product_name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%ID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'User email', 'wp-security-audit-log' ) => '%email_address%',
					),
					array(),
					'woocommerce-product',
					'modified',
				),

				9105 => array(
					9105,
					WSAL_LOW,
					esc_html__( 'System changed the stock quantity of a product', 'wp-security-audit-log' ),
					esc_html__( 'The stock quantity of the product %ProductTitle% was changed to %NewValue% due to a purchase.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'User name', 'wp-security-audit-log' ) => '%Username%',
						esc_html__( 'Order name', 'wp-security-audit-log' ) => '%StockOrderID%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
						esc_html__( 'Previous quantity', 'wp-security-audit-log' ) => '%OldValue%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'modified',
				),
				9106 => array(
					9106,
					WSAL_LOW,
					esc_html__( 'Third-party plugin changed the stock quantity of a product', 'wp-security-audit-log' ),
					esc_html__( 'The stock quantity of the product %ProductTitle% was changed to %NewValue% via third party system.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
						esc_html__( 'Previous quantity', 'wp-security-audit-log' ) => '%OldValue%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'modified',
				),
				9113 => array(
					9113,
					WSAL_MEDIUM,
					esc_html__( 'The Tax status of a product has been modified', 'wp-security-audit-log' ),
					esc_html__( 'Changed the Tax status of the product %ProductTitle% to %new_tax_status%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
						esc_html__( 'Previous tax status', 'wp-security-audit-log' ) => '%old_tax_status%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'modified',
				),
				9114 => array(
					9114,
					WSAL_MEDIUM,
					esc_html__( 'The Tax class of a product has been modified', 'wp-security-audit-log' ),
					esc_html__( 'Changed the Tax class of the product %ProductTitle% to %new_tax_class%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
						esc_html__( 'Previous tax class', 'wp-security-audit-log' ) => '%old_tax_class%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'modified',
				),

				9119 => array(
					9119,
					WSAL_HIGH,
					esc_html__( 'User changed the low stock threshold of a product', 'wp-security-audit-log' ),
					esc_html__( 'Changed the Low stock threshold of the product %ProductTitle% to %new_low_stock_amount%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
						esc_html__( 'Previous Low stock threshold value', 'wp-security-audit-log' ) => '%old_low_stock_amount%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'modified',
				),

				9120 => array(
					9120,
					WSAL_HIGH,
					esc_html__( 'User added a webhook', 'wp-security-audit-log' ),
					esc_html__( 'The webhook %HookName% was %EventType%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Topic', 'wp-security-audit-log' ) => '%Topic%',
						esc_html__( 'Delivery URL', 'wp-security-audit-log' ) => '%DeliveryURL%',
						esc_html__( 'Status', 'wp-security-audit-log' ) => '%Status%',
					),
					array(
						esc_html__( 'View details', 'wp-security-audit-log' ) => '%EditorLinkWebhook%',
					),
					'woocommerce-product',
					'added',
				),

				9121 => array(
					9121,
					WSAL_HIGH,
					esc_html__( 'User removed a webhook', 'wp-security-audit-log' ),
					esc_html__( 'The webhook %HookName% was deleted.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Topic', 'wp-security-audit-log' ) => '%Topic%',
						esc_html__( 'Delivery URL', 'wp-security-audit-log' ) => '%DeliveryURL%',
						esc_html__( 'Status', 'wp-security-audit-log' ) => '%Status%',
					),
					array(),
					'woocommerce-product',
					'deleted',
				),

				9122 => array(
					9122,
					WSAL_HIGH,
					esc_html__( 'User modified a webhook', 'wp-security-audit-log' ),
					esc_html__( 'The webhook %HookName% was modified.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous Name', 'wp-security-audit-log' ) => '%OldHookName',
						esc_html__( 'Topic', 'wp-security-audit-log' ) => '%Topic%',
						esc_html__( 'Previous Topic', 'wp-security-audit-log' ) => '%OldTopic%',
						esc_html__( 'Delivery URL', 'wp-security-audit-log' ) => '%DeliveryURL%',
						esc_html__( 'Previous Delivery URL', 'wp-security-audit-log' ) => '%OldDeliveryURL%',
						esc_html__( 'Status', 'wp-security-audit-log' ) => '%Status%',
						esc_html__( 'Previous Status', 'wp-security-audit-log' ) => '%OldStatus%',
						esc_html__( 'Secret', 'wp-security-audit-log' ) => '%Secret%',
						esc_html__( 'Previous Secret', 'wp-security-audit-log' ) => '%OldSecret%',
					),
					array(
						esc_html__( 'View details', 'wp-security-audit-log' ) => '%EditorLinkWebhook%',
					),
					'woocommerce-product',
					'modified',
				),

				9019 => array(
					9019,
					WSAL_LOW,
					esc_html__( 'User changed the stock quantity', 'wp-security-audit-log' ),
					esc_html__( 'Changed the stock quantity of the product %ProductTitle% to %NewValue%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
						esc_html__( 'Previous quantity', 'wp-security-audit-log' ) => '%OldValue%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'modified',
				),

				9047 => array(
					9047,
					WSAL_LOW,
					esc_html__( 'Added a new attribute of a product', 'wp-security-audit-log' ),
					esc_html__( 'Added a new attribute called %AttributeName% to the product %ProductTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%ProductID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
						esc_html__( 'Attribute value', 'wp-security-audit-log' ) => '%AttributeValue%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'added',
				),
				9048 => array(
					9048,
					WSAL_LOW,
					esc_html__( 'Modified the value of an attribute of a product', 'wp-security-audit-log' ),
					esc_html__( 'Modified the value of the attribute %AttributeName% in the product %ProductTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%ProductID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
						esc_html__( 'Previous attribute value', 'wp-security-audit-log' ) => '%OldValue%',
						esc_html__( 'New attribute value', 'wp-security-audit-log' ) => '%NewValue%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'modified',
				),
				9049 => array(
					9049,
					WSAL_LOW,
					esc_html__( 'Changed the name of an attribute of a product', 'wp-security-audit-log' ),
					esc_html__( 'Renamed the attribute %OldValue% to %NewValue% in the product %ProductTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%ProductID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'renamed',
				),
				9050 => array(
					9050,
					WSAL_LOW,
					esc_html__( 'Deleted an attribute of a product', 'wp-security-audit-log' ),
					esc_html__( 'Deleted the attribute %AttributeName% from the product %ProductTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%ProductID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
						esc_html__( 'Attribute value', 'wp-security-audit-log' ) => '%AttributeValue%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'deleted',
				),
				9051 => array(
					9051,
					WSAL_LOW,
					esc_html__( 'Set the attribute visibility of a product', 'wp-security-audit-log' ),
					esc_html__( 'Changed the visibility of the attribute %AttributeName% to %AttributeVisiblilty% in the the product %ProductTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%ProductID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
						esc_html__( 'Product status', 'wp-security-audit-log' ) => '%ProductStatus%',
						esc_html__( 'Previous visibility', 'wp-security-audit-log' ) => '%OldAttributeVisiblilty%',
					),
					array(
						esc_html__( 'View product in editor', 'wp-security-audit-log' ) => '%EditorLinkProduct%',
					),
					'woocommerce-product',
					'modified',
				),
			);
		}

		/**
		 * Returns the array with store alerts.
		 *
		 * @since 4.6.0
		 */
		private static function get_store_array(): array {
			return array(
				9027 => array(
					9027,
					WSAL_HIGH,
					esc_html__( 'User changed the Weight Unit', 'wp-security-audit-log' ),
					__( 'Changed the <strong>weight unit</strong> of the store to %NewUnit%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous weight unit', 'wp-security-audit-log' ) => '%OldUnit%',
					),
					array(),
					'woocommerce-store',
					'modified',
				),
				9028 => array(
					9028,
					WSAL_HIGH,
					esc_html__( 'User changed the Dimensions Unit', 'wp-security-audit-log' ),
					__( 'Changed the <strong>dimensions unit</strong> of the store to %NewUnit%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous dimensions unit', 'wp-security-audit-log' ) => '%OldUnit%',
					),
					array(),
					'woocommerce-store',
					'modified',
				),
				9029 => array(
					9029,
					WSAL_HIGH,
					esc_html__( 'User changed the Base Location', 'wp-security-audit-log' ),
					__( 'Changed the <strong>base location</strong> of the store to %NewLocation%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous address', 'wp-security-audit-log' ) => '%OldLocation%',
					),
					array(),
					'woocommerce-store',
					'modified',
				),
				9030 => array(
					9030,
					WSAL_HIGH,
					esc_html__( 'User enabled/disabled taxes', 'wp-security-audit-log' ),
					__( 'Changed the status of the <strong>Taxes</strong> store setting.', 'wp-security-audit-log' ),
					array(),
					array(),
					'woocommerce-store',
					'enabled',
				),
				9031 => array(
					9031,
					WSAL_HIGH,
					esc_html__( 'User changed the currency', 'wp-security-audit-log' ),
					__( 'Changed the <strong>currency</strong> of the store to %NewCurrency%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous currency', 'wp-security-audit-log' ) => '%OldCurrency%',
					),
					array(),
					'woocommerce-store',
					'modified',
				),
				9032 => array(
					9032,
					WSAL_HIGH,
					esc_html__( 'User enabled/disabled the use of coupons during checkout', 'wp-security-audit-log' ),
					__( 'Changed the status of the store setting <strong>use of coupons during checkout</strong>.', 'wp-security-audit-log' ),
					array(),
					array(),
					'woocommerce-store',
					'enabled',
				),
				9033 => array(
					9033,
					WSAL_MEDIUM,
					esc_html__( 'User enabled/disabled guest checkout', 'wp-security-audit-log' ),
					__( 'Changed the status of the store setting <strong>Guest checkout</strong>.', 'wp-security-audit-log' ),
					array(),
					array(),
					'woocommerce-store',
					'enabled',
				),
				9034 => array(
					9034,
					WSAL_HIGH,
					esc_html__( 'User enabled/disabled Cash on delivery', 'wp-security-audit-log' ),
					__( 'Changed the status of the store setting <strong>cash on delivery</strong>', 'wp-security-audit-log' ),
					array(),
					array(),
					'woocommerce-store',
					'enabled',
				),
				9144 => array(
					9144,
					WSAL_MEDIUM,
					esc_html__( 'User enabled/disabled the store setting Allow customers to log into an existing account during checkout', 'wp-security-audit-log' ),
					__( 'Changed the status of the store setting <strong>Allow customers to log into an existing account during checkout.</strong>', 'wp-security-audit-log' ),
					array(),
					array(),
					'woocommerce-store',
					'enabled',
				),
				9145 => array(
					9145,
					WSAL_MEDIUM,
					esc_html__( 'User enabled/disabled the store setting Allow customers to create an account during checkout', 'wp-security-audit-log' ),
					__( 'Changed the status of the store setting <strong>Changed the status of the store setting Allow customers to create an account during checkout.</strong>', 'wp-security-audit-log' ),
					array(),
					array(),
					'woocommerce-store',
					'enabled',
				),
				9146 => array(
					9146,
					WSAL_MEDIUM,
					esc_html__( 'User enabled/disabled the store setting Allow customers to create an account on the "My account" page', 'wp-security-audit-log' ),
					__( 'Changed the status of the store setting <strong>Allow customers to create an account on the "My account" page.</strong>', 'wp-security-audit-log' ),
					array(),
					array(),
					'woocommerce-store',
					'enabled',
				),
				9147 => array(
					9147,
					WSAL_MEDIUM,
					esc_html__( 'User enabled/disabled the store setting When creating an account, automatically generate an account username for the customer based on their name, surname or email', 'wp-security-audit-log' ),
					__( 'Changed the status of the store setting <strong>When creating an account, automatically generate an account username for the customer based on their name, surname or email.</strong>', 'wp-security-audit-log' ),
					array(),
					array(),
					'woocommerce-store',
					'enabled',
				),
				9148 => array(
					9148,
					WSAL_MEDIUM,
					esc_html__( 'User enabled/disabled the store setting When creating an account, send the new user a link to set their password', 'wp-security-audit-log' ),
					__( 'Changed the status of the store setting <strong>When creating an account, send the new user a link to set their password.</strong>', 'wp-security-audit-log' ),
					array(),
					array(),
					'woocommerce-store',
					'enabled',
				),
				9149 => array(
					9149,
					WSAL_MEDIUM,
					esc_html__( 'User enabled/disabled the store setting Remove personal data from orders on request', 'wp-security-audit-log' ),
					__( 'Changed the status of the store setting <strong>Remove personal data from orders on request.</strong>', 'wp-security-audit-log' ),
					array(),
					array(),
					'woocommerce-store',
					'enabled',
				),
				9150 => array(
					9150,
					WSAL_MEDIUM,
					esc_html__( 'User enabled/disabled the store setting Remove access to downloads on request', 'wp-security-audit-log' ),
					__( 'Changed the status of the store setting <strong>Remove access to downloads on request.</strong>', 'wp-security-audit-log' ),
					array(),
					array(),
					'woocommerce-store',
					'enabled',
				),
				9151 => array(
					9151,
					WSAL_MEDIUM,
					esc_html__( 'User enabled/disabled the store setting Allow personal data to be removed in bulk from orders', 'wp-security-audit-log' ),
					__( 'Changed the status of the store setting <strong>Allow personal data to be removed in bulk from orders.</strong>', 'wp-security-audit-log' ),
					array(),
					array(),
					'woocommerce-store',
					'enabled',
				),
				9152 => array(
					9152,
					WSAL_MEDIUM,
					esc_html__( 'Changed the setting Registration privacy policy message', 'wp-security-audit-log' ),
					__( 'Changed the store <strong>Registration privacy policy</strong> message to %new_setting%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous message', 'wp-security-audit-log' ) => '%old_setting%',
					),
					array(),
					'woocommerce-store',
					'modified',
				),
				9153 => array(
					9153,
					WSAL_MEDIUM,
					esc_html__( 'Changed the setting Checkout privacy policy message', 'wp-security-audit-log' ),
					__( 'Changed the store <strong>Checkout privacy policy</strong> message to %new_setting%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous message', 'wp-security-audit-log' ) => '%old_setting%',
					),
					array(),
					'woocommerce-store',
					'modified',
				),

				9085 => array(
					9085,
					WSAL_HIGH,
					esc_html__( 'User modified selling location(s)', 'wp-security-audit-log' ),
					__( 'Changed the store setting <strong>Selling location(s)</strong> to %new%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous setting', 'wp-security-audit-log' ) => '%old%',
					),
					array(),
					'woocommerce-store',
					'modified',
				),
				9086 => array(
					9086,
					WSAL_HIGH,
					esc_html__( 'User modified excluded selling location(s)', 'wp-security-audit-log' ),
					__( 'Changed the <strong>list of excluded countries to sell to</strong> setting in the store.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous list of countries', 'wp-security-audit-log' ) => '%old%',
						esc_html__( 'New list of countries', 'wp-security-audit-log' ) => '%new%',
					),
					array(),
					'woocommerce-store',
					'modified',
				),
				9087 => array(
					9087,
					WSAL_HIGH,
					esc_html__( 'User modified exclusive selling location(s)', 'wp-security-audit-log' ),
					__( 'Changed the <strong>list of countries to sell to</strong> setting in the store.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous list of countries', 'wp-security-audit-log' ) => '%old%',
						esc_html__( 'New list of countries', 'wp-security-audit-log' ) => '%new%',
					),
					array(),
					'woocommerce-store',
					'modified',
				),
				9088 => array(
					9088,
					WSAL_HIGH,
					esc_html__( 'User modified shipping location(s)', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Shipping location(s)</strong> setting in the store.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous setting', 'wp-security-audit-log' ) => '%old%',
						esc_html__( 'New Setting', 'wp-security-audit-log' ) => '%new%',
					),
					array(),
					'woocommerce-store',
					'modified',
				),
				9089 => array(
					9089,
					WSAL_HIGH,
					esc_html__( 'User modified exclusive shipping location(s)', 'wp-security-audit-log' ),
					__( 'Changed the <strong>List of specific countries to</strong> ship to setting in the store.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous list of countries', 'wp-security-audit-log' ) => '%old%',
						esc_html__( 'New list of countries', 'wp-security-audit-log' ) => '%new%',
					),
					array(),
					'woocommerce-store',
					'modified',
				),
				9090 => array(
					9090,
					WSAL_HIGH,
					esc_html__( 'User modified default customer location', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Default customer location</strong> setting to %new%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous location', 'wp-security-audit-log' ) => '%old%',
					),
					array(),
					'woocommerce-store',
					'modified',
				),
				9091 => array(
					9091,
					WSAL_HIGH,
					esc_html__( 'User modified the cart page', 'wp-security-audit-log' ),
					__( 'Changed the store\'s <strong>Cart page</strong> to %new%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous page', 'wp-security-audit-log' ) => '%old%',
					),
					array(),
					'woocommerce-store',
					'modified',
				),
				9092 => array(
					9092,
					WSAL_HIGH,
					esc_html__( 'User modified the checkout page', 'wp-security-audit-log' ),
					__( 'Changed the store\'s <strong>Checkout page</strong> to %new%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous page', 'wp-security-audit-log' ) => '%old%',
					),
					array(),
					'woocommerce-store',
					'modified',
				),
				9093 => array(
					9093,
					WSAL_HIGH,
					esc_html__( 'User modified the my account page', 'wp-security-audit-log' ),
					__( 'Changed the store\'s <strong>My account page</strong> to %new%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous page', 'wp-security-audit-log' ) => '%old%',
					),
					array(),
					'woocommerce-store',
					'modified',
				),
				9094 => array(
					9094,
					WSAL_HIGH,
					esc_html__( 'User modified the terms and conditions page', 'wp-security-audit-log' ),
					__( 'Changed the store\'s <strong>Terms and conditions page</strong> to %new%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous page', 'wp-security-audit-log' ) => '%old%',
					),
					array(),
					'woocommerce-store',
					'modified',
				),
				9100 => array(
					9100,
					WSAL_MEDIUM,
					esc_html__( 'Changed the status of the Enable product reviews setting in the store', 'wp-security-audit-log' ),
					__( 'Changed the status of the <strong>Enable product reviews</strong> setting in the store.', 'wp-security-audit-log' ),
					array(),
					array(),
					'woocommerce-store',
					'modified',
				),
				9107 => array(
					9107,
					WSAL_LOW,
					esc_html__( 'Changed the status of the Show "verified owner" label on customer reviews setting in the store', 'wp-security-audit-log' ),
					__( 'Changed the status of the <strong>Show "verified owner" label on customer reviews</strong> setting in the store.', 'wp-security-audit-log' ),
					array(),
					array(),
					'woocommerce-store',
					'modified',
				),
				9108 => array(
					9108,
					WSAL_LOW,
					esc_html__( 'Changed the status of the Reviews can only be left by "verified owners" setting in the store', 'wp-security-audit-log' ),
					__( 'Changed the status of the <strong>Reviews can only be left by "verified owners"</strong> setting in the store.', 'wp-security-audit-log' ),
					array(),
					array(),
					'woocommerce-store',
					'modified',
				),
				9109 => array(
					9109,
					WSAL_MEDIUM,
					esc_html__( 'Changed the status of the Star rating on reviews setting in the store', 'wp-security-audit-log' ),
					__( 'Changed the status of the <strong>Star rating on reviews</strong> setting in the store.', 'wp-security-audit-log' ),
					array(),
					array(),
					'woocommerce-store',
					'modified',
				),
				9110 => array(
					9110,
					WSAL_MEDIUM,
					esc_html__( 'Changed the status of the Star ratings should be required setting in the store', 'wp-security-audit-log' ),
					__( 'Changed the status of the <strong>Star ratings should be required</strong> setting in the store.', 'wp-security-audit-log' ),
					array(),
					array(),
					'woocommerce-store',
					'modified',
				),
				9111 => array(
					9111,
					WSAL_MEDIUM,
					esc_html__( 'A Checkout endpoint has been modified', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Checkout endpoint</strong> %endpoint_name% to %new_value%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous endpoint value', 'wp-security-audit-log' ) => '%old%',
					),
					array(),
					'woocommerce-store',
					'modified',
				),
				9112 => array(
					9112,
					WSAL_MEDIUM,
					esc_html__( 'An Account endpoint has been modified', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Account endpoint</strong> %endpoint_name% to %new_value%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous endpoint value', 'wp-security-audit-log' ) => '%old%',
					),
					array(),
					'woocommerce-store',
					'modified',
				),
				9115 => array(
					9115,
					WSAL_MEDIUM,
					esc_html__( 'The Currency position setting has been modified', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Currency position</strong> setting to %new_setting%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous setting', 'wp-security-audit-log' ) => '%%old_setting%',
					),
					array(),
					'woocommerce-store',
					'modified',
				),
				9116 => array(
					9116,
					WSAL_MEDIUM,
					esc_html__( 'The Thousand separator setting has been modified', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Thousand separator</strong> setting in the Currency options to %new_setting%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous setting', 'wp-security-audit-log' ) => '%%old_setting%',
					),
					array(),
					'woocommerce-store',
					'modified',
				),
				9117 => array(
					9117,
					WSAL_MEDIUM,
					esc_html__( 'The Decimal separator setting has been modified', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Decimal separator</strong> setting in the Currency options to %new_setting%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous setting', 'wp-security-audit-log' ) => '%%old_setting%',
					),
					array(),
					'woocommerce-store',
					'modified',
				),
				9118 => array(
					9118,
					WSAL_MEDIUM,
					esc_html__( 'The Number of decimals position setting has been modified', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Number of decimals</strong> setting in the Currency options to %new_setting%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous setting', 'wp-security-audit-log' ) => '%%old_setting%',
					),
					array(),
					'woocommerce-store',
					'modified',
				),

				9140 => array(
					9140,
					WSAL_MEDIUM,
					esc_html__( 'Changed the status of the Shipping options: Enable the shipping calculator on the cart page setting in the store.', 'wp-security-audit-log' ),
					__( 'Changed the status of the <strong>Shipping options: Enable the shipping calculator on the cart page</strong> setting in the store.', 'wp-security-audit-log' ),
					array(),
					array(),
					'woocommerce-store',
					'enabled',
				),

				9141 => array(
					9141,
					WSAL_MEDIUM,
					esc_html__( 'Changed the status of the Shipping option: Hide shipping costs until an address is entered setting in the store.', 'wp-security-audit-log' ),
					__( 'Changed the status of the <strong>Shipping option: Hide shipping costs until an address is entered</strong> setting in the store.', 'wp-security-audit-log' ),
					array(),
					array(),
					'woocommerce-store',
					'enabled',
				),

				9142 => array(
					9142,
					WSAL_MEDIUM,
					esc_html__( 'Changed the setting Shipping option: Shipping destination', 'wp-security-audit-log' ),
					__( 'Changed the setting Shipping option: Shipping destination to %new_setting%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous setting', 'wp-security-audit-log' ) => '%old_setting%',
					),
					array(),
					'woocommerce-store',
					'modified',
				),

				9143 => array(
					9143,
					WSAL_MEDIUM,
					esc_html__( 'Changed the status of the Debug mode setting in the store.', 'wp-security-audit-log' ),
					__( 'Changed the status of the <strong>Debug mode</strong> setting in the store.', 'wp-security-audit-log' ),
					array(),
					array(),
					'woocommerce-store',
					'enabled',
				),
			);
		}

		/**
		 * Returns the array with payment getaways alerts.
		 *
		 * @since 4.6.0
		 */
		private static function get_payment_getaways_array(): array {
			return array(
				9074 => array(
					9074,
					WSAL_HIGH,
					esc_html__( 'User enabled/disabled a payment gateway', 'wp-security-audit-log' ),
					esc_html__( 'Changed the status of the payment gateway %GatewayName%.', 'wp-security-audit-log' ),
					array(),
					array(),
					'woocommerce-store',
					'enabled',
				),
				9075 => array(
					9075,
					E_CRITICAL,
					esc_html__( 'User disabled a payment gateway', 'wp-security-audit-log' ),
					esc_html__( 'User disabled a payment gateway', 'wp-security-audit-log' ),
					array(
						esc_html__( 'The payment gateway', 'wp-security-audit-log' ) => '%GatewayName%',
					),
					array(),
					'woocommerce-store',
					'',
				),
				9076 => array(
					9076,
					WSAL_HIGH,
					esc_html__( 'User modified a payment gateway', 'wp-security-audit-log' ),
					esc_html__( 'Changed the settings of the %GatewayName% payment gateway.', 'wp-security-audit-log' ),
					array(),
					array(),
					'woocommerce-store',
					'modified',
				),
			);
		}

		/**
		 * Returns the array with payment getaways alerts.
		 *
		 * @since 4.6.0
		 */
		private static function get_tax_settings_array(): array {
			return array(
				9078 => array(
					9078,
					WSAL_LOW,
					esc_html__( 'User modified prices with tax option', 'wp-security-audit-log' ),
					__( 'Changed the store setting <strong>Prices entered with tax</strong> to %TaxStatus% taxes.', 'wp-security-audit-log' ),
					array(),
					array(),
					'woocommerce-store',
					'modified',
				),
				9079 => array(
					9079,
					WSAL_LOW,
					esc_html__( 'User modified tax calculation base', 'wp-security-audit-log' ),
					__( 'Changed the store setting <strong>Calculate tax based on</strong> to %Setting%', 'wp-security-audit-log' ),
					array(),
					array(),
					'woocommerce-store',
					'modified',
				),
				9080 => array(
					9080,
					WSAL_MEDIUM,
					esc_html__( 'User modified shipping tax class', 'wp-security-audit-log' ),
					__( 'Changed the store setting <strong>Shipping tax class</strong> to %Setting%', 'wp-security-audit-log' ),
					array(),
					array(),
					'woocommerce-store',
					'modified',
				),
				9081 => array(
					9081,
					WSAL_MEDIUM,
					esc_html__( 'User enabled/disabled rounding of tax', 'wp-security-audit-log' ),
					__( 'Changed the status of the store tax setting <strong>Rounding</strong> of tax at subtotal level.', 'wp-security-audit-log' ),
					array(),
					array(),
					'woocommerce-store',
					'enabled',
				),
				9082 => array(
					9082,
					WSAL_MEDIUM,
					esc_html__( 'User modified a shipping zone', 'wp-security-audit-log' ),
					esc_html__( 'The shipping zone %ShippingZoneName% on the WooCommerce store', 'wp-security-audit-log' ),
					array(),
					array(),
					'woocommerce-store',
					'created',
				),
			);
		}

		/**
		 * Returns the array with wc categories alerts.
		 *
		 * @since 4.6.0
		 */
		private static function get_wc_categories_array(): array {
			return array(
				9002 => array(
					9002,
					WSAL_INFORMATIONAL,
					esc_html__( 'User created a new product category', 'wp-security-audit-log' ),
					esc_html__( 'Created new product category called %CategoryName%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Category slug', 'wp-security-audit-log' ) => '%Slug%',
					),
					array(
						esc_html__( 'View category', 'wp-security-audit-log' ) => '%ProductCatLink%',
					),
					'woocommerce-category',
					'created',
				),
				9052 => array(
					9052,
					WSAL_MEDIUM,
					esc_html__( 'User deleted a product category', 'wp-security-audit-log' ),
					esc_html__( 'Deleted the product category called %CategoryName%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Category slug', 'wp-security-audit-log' ) => '%CategorySlug%',
					),
					array(),
					'woocommerce-category',
					'deleted',
				),
				9053 => array(
					9053,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed the slug of a product category', 'wp-security-audit-log' ),
					esc_html__( 'Changed the slug of the product category called %CategoryName%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous category slug', 'wp-security-audit-log' ) => '%OldSlug%',
						esc_html__( 'New category slug', 'wp-security-audit-log' ) => '%NewSlug%',
					),
					array(
						esc_html__( 'View category', 'wp-security-audit-log' ) => '%ProductCatLink%',
					),
					'woocommerce-category',
					'modified',
				),
				9054 => array(
					9054,
					WSAL_MEDIUM,
					esc_html__( 'User changed the parent category of a product category', 'wp-security-audit-log' ),
					esc_html__( 'Changed the parent of the product category %CategoryName% to %NewParentCat%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Category slug', 'wp-security-audit-log' ) => '%CategorySlug%',
						esc_html__( 'Previous parent', 'wp-security-audit-log' ) => '%OldParentCat%',
					),
					array(
						esc_html__( 'View category', 'wp-security-audit-log' ) => '%ProductCatLink%',
					),
					'woocommerce-category',
					'modified',
				),
				9055 => array(
					9055,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed the display type of a product category', 'wp-security-audit-log' ),
					esc_html__( 'Changed the display type of the product category %name% to %NewDisplayType%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Category slug', 'wp-security-audit-log' ) => '%CategorySlug%',
						esc_html__( 'Previous display type', 'wp-security-audit-log' ) => '%OldDisplayType%',
					),
					array(
						esc_html__( 'View category', 'wp-security-audit-log' ) => '%ProductCatLink%',
					),
					'woocommerce-category',
					'modified',
				),
				9056 => array(
					9056,
					WSAL_LOW,
					esc_html__( 'User changed the name of a product category', 'wp-security-audit-log' ),
					esc_html__( 'Renamed the product category %OldName% to %NewName%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous category name', 'wp-security-audit-log' ) => '%OldName%',
						esc_html__( 'Category slug', 'wp-security-audit-log' ) => '%CategorySlug%',
					),
					array(
						esc_html__( 'View category', 'wp-security-audit-log' ) => '%ProductCatLink%',
					),
					'woocommerce-category',
					'renamed',
				),
			);
		}

		/**
		 * Returns the array with wc tags alerts.
		 *
		 * @since 4.6.0
		 */
		private static function get_wc_tags_array(): array {
			return array(
				9101 => array(
					9101,
					WSAL_INFORMATIONAL,
					esc_html__( 'User created a new product tag', 'wp-security-audit-log' ),
					esc_html__( 'Created the tag %CategoryName%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Slug', 'wp-security-audit-log' ) => '%Slug%',
					),
					array(
						esc_html__( 'View product tag', 'wp-security-audit-log' ) => '%ProductTagLink%',
					),
					'woocommerce-tag',
					'created',
				),
				9102 => array(
					9102,
					WSAL_INFORMATIONAL,
					esc_html__( 'User deleted a product tag', 'wp-security-audit-log' ),
					esc_html__( 'Deleted the tag %Name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Slug', 'wp-security-audit-log' ) => '%Slug%',
					),
					array(),
					'woocommerce-tag',
					'deleted',
				),
				9103 => array(
					9103,
					WSAL_INFORMATIONAL,
					esc_html__( 'User renamed product tag', 'wp-security-audit-log' ),
					esc_html__( 'Renamed the tag %OldName% to %NewName%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Slug', 'wp-security-audit-log' ) => '%Slug%',
					),
					array(
						esc_html__( 'View product tag', 'wp-security-audit-log' ) => '%ProductTagLink%',
					),
					'woocommerce-tag',
					'renamed',
				),
				9104 => array(
					9104,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed product tag slug', 'wp-security-audit-log' ),
					esc_html__( 'Changed the slug of the tag %TagName% to %NewSlug%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous slug', 'wp-security-audit-log' ) => '%OldSlug%',
					),
					array(
						esc_html__( 'View product tag', 'wp-security-audit-log' ) => '%ProductTagLink%',
					),
					'woocommerce-tag',
					'modified',
				),
			);
		}

		/**
		 * Returns the array with attributes alerts.
		 *
		 * @since 4.6.0
		 */
		private static function get_attributes_array(): array {
			return array(
				9057 => array(
					9057,
					WSAL_MEDIUM,
					esc_html__( 'User created a new attribute', 'wp-security-audit-log' ),
					esc_html__( 'Created a new attribute in WooCommerce called %AttributeName%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Attribute slug', 'wp-security-audit-log' ) => '%AttributeSlug%',
					),
					array(),
					'woocommerce-store',
					'created',
				),
				9058 => array(
					9058,
					WSAL_LOW,
					esc_html__( 'User deleted an attribute', 'wp-security-audit-log' ),
					esc_html__( 'Deleted the WooCommerce attribute %AttributeName%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Attribute slug', 'wp-security-audit-log' ) => '%AttributeSlug%',
					),
					array(),
					'woocommerce-store',
					'deleted',
				),
				9059 => array(
					9059,
					WSAL_LOW,
					esc_html__( 'User changed the slug of an attribute', 'wp-security-audit-log' ),
					esc_html__( 'Changed the slug of the WooCommerce attribute %AttributeName% to %NewSlug%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous slug', 'wp-security-audit-log' ) => '%OldSlug%',
					),
					array(),
					'woocommerce-store',
					'modified',
				),
				9060 => array(
					9060,
					WSAL_LOW,
					esc_html__( 'User changed the name of an attribute', 'wp-security-audit-log' ),
					esc_html__( 'Renamed the WooCommerce attribute %AttributeName% to %NewName%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Attribute slug', 'wp-security-audit-log' ) => '%AttributeSlug%',
						esc_html__( 'Previous name', 'wp-security-audit-log' ) => '%OldName%',
					),
					array(),
					'woocommerce-store',
					'modified',
				),
				9061 => array(
					9061,
					WSAL_LOW,
					esc_html__( 'User changed the default sort order of an attribute', 'wp-security-audit-log' ),
					esc_html__( 'Changed the Default Sorting Order of the attribute %AttributeName% in WooCommerce to %NewSortOrder%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Attribute slug', 'wp-security-audit-log' ) => '%AttributeSlug%',
						esc_html__( 'Previous sorting order', 'wp-security-audit-log' ) => '%OldSortOrder%',
					),
					array(),
					'woocommerce-store',
					'modified',
				),
				9062 => array(
					9062,
					WSAL_LOW,
					esc_html__( 'User enabled/disabled the option Enable Archives of an attribute', 'wp-security-audit-log' ),
					esc_html__( 'Changed the status of the option Enable Archives in WooCommerce attribute %AttributeName%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Attribute slug', 'wp-security-audit-log' ) => '%Slug%',
					),
					array(),
					'woocommerce-store',
					'enabled',
				),
			);
		}

		/**
		 * Returns the array with coupons alerts.
		 *
		 * @since 4.6.0
		 */
		private static function get_coupons_array(): array {
			return array(
				9063 => array(
					9063,
					WSAL_LOW,
					esc_html__( 'User published a new coupon', 'wp-security-audit-log' ),
					esc_html__( 'Created the WooCommerce coupon: %CouponName%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Coupon ID', 'wp-security-audit-log' ) => '%CouponID%',
						esc_html__( 'Coupon status', 'wp-security-audit-log' ) => '%CouponStatus%',
					),
					array(
						esc_html__( 'View coupon in editor', 'wp-security-audit-log' ) => '%EditorLinkCoupon%',
					),
					'woocommerce-coupon',
					'published',
				),
				9064 => array(
					9064,
					WSAL_LOW,
					esc_html__( 'User changed the discount type of a coupon', 'wp-security-audit-log' ),
					esc_html__( 'Changed the Discount Type in the coupon %CouponName% to %NewDiscountType%.%', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Coupon ID', 'wp-security-audit-log' ) => '%CouponID%',
						esc_html__( 'Coupon status', 'wp-security-audit-log' ) => '%CouponStatus%',
						esc_html__( 'Previous discount type', 'wp-security-audit-log' ) => '%OldDiscountType%',
					),
					array(
						'%EditorLinkCoupon%',
					),
					'woocommerce-coupon',
					'modified',
				),
				9065 => array(
					9065,
					WSAL_LOW,
					esc_html__( 'User changed the coupon amount of a coupon', 'wp-security-audit-log' ),
					esc_html__( 'Changed the Coupon amount in the coupon %CouponName% to %NewAmount%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Coupon ID', 'wp-security-audit-log' ) => '%CouponID%',
						esc_html__( 'Coupon status', 'wp-security-audit-log' ) => '%CouponStatus%',
						esc_html__( 'Previous amount', 'wp-security-audit-log' ) => '%OldAmount%',
					),
					array(
						esc_html__( 'View coupon in editor', 'wp-security-audit-log' ) => '%EditorLinkCoupon%',
					),
					'woocommerce-coupon',
					'modified',
				),
				9066 => array(
					9066,
					WSAL_LOW,
					esc_html__( 'User changed the coupon expire date of a coupon', 'wp-security-audit-log' ),
					esc_html__( 'Changed the expire date of the coupon %CouponName% to %NewDate%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Coupon ID', 'wp-security-audit-log' ) => '%CouponID%',
						esc_html__( 'Coupon status', 'wp-security-audit-log' ) => '%CouponStatus%',
						esc_html__( 'Previous date', 'wp-security-audit-log' ) => '%OldDate%',
					),
					array(
						esc_html__( 'View coupon in editor', 'wp-security-audit-log' ) => '%EditorLinkCoupon%',
					),
					'woocommerce-coupon',
					'modified',
				),
				9067 => array(
					9067,
					WSAL_LOW,
					esc_html__( 'User changed the usage restriction settings of a coupon', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Usage restriction</strong> of the coupon %CouponName% to %NewMetaValue%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Coupon ID', 'wp-security-audit-log' ) => '%CouponID%',
						esc_html__( 'Coupon status', 'wp-security-audit-log' ) => '%CouponStatus%',
						esc_html__( 'Usage restriction parameter', 'wp-security-audit-log' ) => '%MetaKey%',
						esc_html__( 'Previous value', 'wp-security-audit-log' ) => '%OldMetaValue%',
					),
					array(
						esc_html__( 'View coupon in editor', 'wp-security-audit-log' ) => '%EditorLinkCoupon%',
					),
					'woocommerce-coupon',
					'modified',
				),
				9068 => array(
					9068,
					WSAL_LOW,
					esc_html__( 'User changed the usage limits settings of a coupon', 'wp-security-audit-log' ),
					__( 'Change the <strong>Usage limits</strong> of the coupon %CouponName% to %NewMetaValue%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Coupon ID', 'wp-security-audit-log' ) => '%CouponID%',
						esc_html__( 'Coupon status', 'wp-security-audit-log' ) => '%CouponStatus%',
						esc_html__( 'Previous usage limits', 'wp-security-audit-log' ) => '%OldMetaValue%',
					),
					array(
						esc_html__( 'View coupon in editor', 'wp-security-audit-log' ) => '%EditorLinkCoupon%',
					),
					'woocommerce-store',
					'modified',
				),
				9069 => array(
					9069,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed the description of a coupon', 'wp-security-audit-log' ),
					esc_html__( 'Changed the description of the coupon %CouponName%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Coupon ID', 'wp-security-audit-log' ) => '%CouponID%',
						esc_html__( 'Coupon status', 'wp-security-audit-log' ) => '%CouponStatus%',
						esc_html__( 'Previous description', 'wp-security-audit-log' ) => '%OldDescription%',
						esc_html__( 'New description', 'wp-security-audit-log' ) => '%NewDescription%',
					),
					array(
						esc_html__( 'View coupon in editor', 'wp-security-audit-log' ) => '%EditorLinkCoupon%',
					),
					'woocommerce-coupon',
					'modified',
				),
				9070 => array(
					9070,
					WSAL_MEDIUM,
					esc_html__( 'User changed the status of a coupon', 'wp-security-audit-log' ),
					esc_html__( 'Changed the status of the coupon %CouponName%', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Old status', 'wp-security-audit-log' ) => '%OldStatus%',
						esc_html__( 'New status', 'wp-security-audit-log' ) => '%NewStatus%',
					),
					array(),
					'woocommerce-coupon',
					'modified',
				),
				9071 => array(
					9071,
					WSAL_INFORMATIONAL,
					esc_html__( 'User renamed a WooCommerce coupon', 'wp-security-audit-log' ),
					esc_html__( 'Renamed the coupon %OldName% to %NewName%.', 'wp-security-audit-log' ),
					array(),
					array(
						esc_html__( 'View coupon in editor', 'wp-security-audit-log' ) => '%EditorLinkCoupon%',
					),
					'woocommerce-coupon',
					'renamed',
				),
				9123 => array(
					9123,
					WSAL_HIGH,
					esc_html__( 'Moved a coupon to trash.', 'wp-security-audit-log' ),
					esc_html__( 'Moved coupon to trash: %CouponName%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Coupon ID', 'wp-security-audit-log' ) => '%CouponID%',
						esc_html__( 'Coupon status', 'wp-security-audit-log' ) => '%CouponStatus%',
					),
					array(),
					'woocommerce-coupon',
					'deleted',
				),
				9124 => array(
					9124,
					WSAL_HIGH,
					esc_html__( 'Permanently deleted the coupon', 'wp-security-audit-log' ),
					esc_html__( 'Permanently deleted the coupon: %CouponCode%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Coupon ID', 'wp-security-audit-log' ) => '%CouponID%',
					),
					array(),
					'woocommerce-coupon',
					'deleted',
				),
				9125 => array(
					9125,
					WSAL_MEDIUM,
					esc_html__( 'User changed the visibility of a coupon', 'wp-security-audit-log' ),
					esc_html__( 'Changed the visibility of the coupon %CouponCode% to %NewVisibility%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Coupon ID', 'wp-security-audit-log' ) => '%CouponID%',
						esc_html__( 'Coupon status', 'wp-security-audit-log' ) => '%CouponStatus%',
						esc_html__( 'Previous visibility', 'wp-security-audit-log' ) => '%OldVisibility%',
					),
					array(
						esc_html__( 'View coupon in editor', 'wp-security-audit-log' ) => '%EditorLinkCoupon%',
					),
					'woocommerce-coupon',
					'modified',
				),
				9126 => array(
					9126,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed the date of a coupon', 'wp-security-audit-log' ),
					esc_html__( 'Changed the published date of the coupon %CouponName% to %NewDate%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Coupon ID', 'wp-security-audit-log' ) => '%CouponID%',
						esc_html__( 'Coupon status', 'wp-security-audit-log' ) => '%CouponStatus%',
						esc_html__( 'Previous date', 'wp-security-audit-log' ) => '%OldDate%',
					),
					array(
						esc_html__( 'View coupon in editor', 'wp-security-audit-log' ) => '%EditorLinkCoupon%',
					),
					'woocommerce-coupon',
					'modified',
				),
				9127 => array(
					9127,
					WSAL_LOW,
					esc_html__( 'Restored a coupon from trash', 'wp-security-audit-log' ),
					esc_html__( 'Restored the coupon %CouponCode% out of the trash.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Coupon status', 'wp-security-audit-log' ) => '%CouponStatus%',
					),
					array(
						esc_html__( 'View coupon', 'wp-security-audit-log' ) => '%EditorLinkCoupon%',
					),
					'woocommerce-coupon',
					'restored',
				),
			);
		}

		/**
		 * Returns the array with orders alerts.
		 *
		 * @since 4.6.0
		 */
		private static function get_orders_array(): array {
			return array(
				9035 => array(
					9035,
					WSAL_LOW,
					esc_html__( 'A WooCommerce order has been placed', 'wp-security-audit-log' ),
					esc_html__( 'A new order has been placed.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Order name', 'wp-security-audit-log' ) => '%OrderTitle%',
					),
					array(
						esc_html__( 'View order', 'wp-security-audit-log' ) => '%EditorLinkOrder%',
					),
					'woocommerce-order',
					'created',
				),
				9036 => array(
					9036,
					WSAL_INFORMATIONAL,
					esc_html__( 'WooCommerce order status changed', 'wp-security-audit-log' ),
					esc_html__( 'Marked the order %OrderTitle% as %OrderStatus%.', 'wp-security-audit-log' ),
					array(),
					array(
						esc_html__( 'View order', 'wp-security-audit-log' ) => '%EditorLinkOrder%',
					),
					'woocommerce-order',
					'modified',
				),
				9037 => array(
					9037,
					WSAL_MEDIUM,
					esc_html__( 'User moved a WooCommerce order to trash', 'wp-security-audit-log' ),
					esc_html__( 'Moved the order %OrderTitle% to trash', 'wp-security-audit-log' ),
					array(),
					array(),
					'woocommerce-order',
					'deleted',
				),
				9038 => array(
					9038,
					WSAL_LOW,
					esc_html__( 'User moved a WooCommerce order out of trash', 'wp-security-audit-log' ),
					esc_html__( 'Restored the order %OrderTitle% out of the trash.', 'wp-security-audit-log' ),
					array(),
					array(
						esc_html__( 'View order', 'wp-security-audit-log' ) => '%EditorLinkOrder%',
					),
					'woocommerce-order',
					'restored',
				),
				9039 => array(
					9039,
					WSAL_LOW,
					esc_html__( 'User permanently deleted a WooCommerce order', 'wp-security-audit-log' ),
					esc_html__( 'Permanently deleted the order %OrderTitle%.', 'wp-security-audit-log' ),
					array(),
					array(),
					'woocommerce-order',
					'deleted',
				),
				9040 => array(
					9040,
					WSAL_MEDIUM,
					esc_html__( 'User edited a WooCommerce order', 'wp-security-audit-log' ),
					esc_html__( 'Changed the details in order %OrderTitle%.', 'wp-security-audit-log' ),
					array(),
					array(
						esc_html__( 'View order', 'wp-security-audit-log' ) => '%EditorLinkOrder%',
					),
					'woocommerce-order',
					'modified',
				),
				9041 => array(
					9041,
					WSAL_HIGH,
					esc_html__( 'User refunded a WooCommerce order', 'wp-security-audit-log' ),
					esc_html__( 'Refunded the order %OrderTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Customer', 'wp-security-audit-log' ) => '%CustomerUser%',
						esc_html__( 'Order date', 'wp-security-audit-log' ) => '%OrderDate%',
						esc_html__( 'Refund amount', 'wp-security-audit-log' ) => '%RefundedAmount%',
						esc_html__( 'Refund reason', 'wp-security-audit-log' ) => '%Reason%',
					),
					array(
						esc_html__( 'View order', 'wp-security-audit-log' ) => '%EditorLinkOrder%',
					),
					'woocommerce-order',
					'modified',
				),
				9130 => array(
					9130,
					WSAL_HIGH,
					esc_html__( 'User added/removed a product from an order', 'wp-security-audit-log' ),
					esc_html__( 'The product %ProductTitle% to/from the order %OrderTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%ProductID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
					),
					array(
						esc_html__( 'View order', 'wp-security-audit-log' ) => '%EditorLinkOrder%',
					),
					'woocommerce-order',
					'modified',
				),
				9131 => array(
					9131,
					WSAL_HIGH,
					esc_html__( 'User modified a quantity in an order', 'wp-security-audit-log' ),
					esc_html__( 'The quantity of the %ProductTitle% was modified in order %OrderTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'New quantity', 'wp-security-audit-log' ) => '%NewQuantity%',
						esc_html__( 'Previous quantity', 'wp-security-audit-log' ) => '%OldQuantity%',
						esc_html__( 'Product ID', 'wp-security-audit-log' ) => '%ProductID%',
						esc_html__( 'Product SKU', 'wp-security-audit-log' ) => '%SKU%',
					),
					array(
						esc_html__( 'View order', 'wp-security-audit-log' ) => '%EditorLinkOrder%',
					),
					'woocommerce-order',
					'modified',
				),
				9132 => array(
					9132,
					WSAL_HIGH,
					esc_html__( 'User added/removed a fee from an order', 'wp-security-audit-log' ),
					esc_html__( 'Added/Removed a fee in order %OrderTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Fee amount', 'wp-security-audit-log' ) => '%FeeAmount%',
					),
					array(
						esc_html__( 'View order', 'wp-security-audit-log' ) => '%EditorLinkOrder%',
					),
					'woocommerce-order',
					'added',
				),
				9133 => array(
					9133,
					WSAL_HIGH,
					esc_html__( 'User modified a fee from an order', 'wp-security-audit-log' ),
					esc_html__( 'User modified a fee in order %OrderTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'New fee amount', 'wp-security-audit-log' ) => '%FeeAmount%',
						esc_html__( 'Previous fee amount', 'wp-security-audit-log' ) => '%OldFeeAmount%',
					),
					array(
						esc_html__( 'View order', 'wp-security-audit-log' ) => '%EditorLinkOrder%',
					),
					'woocommerce-order',
					'modified',
				),
				9134 => array(
					9134,
					WSAL_HIGH,
					esc_html__( 'User added/removed a coupon from an order', 'wp-security-audit-log' ),
					esc_html__( 'Added/Removed a coupon %CouponName% in order %OrderTitle%.', 'wp-security-audit-log' ),
					array(),
					array(
						esc_html__( 'View order', 'wp-security-audit-log' ) => '%EditorLinkOrder%',
					),
					'woocommerce-order',
					'added',
				),

				9135 => array(
					9135,
					WSAL_HIGH,
					esc_html__( 'User added/removed a tax from an order', 'wp-security-audit-log' ),
					esc_html__( 'Added/Removed the tax %TaxName% in order %OrderTitle%.', 'wp-security-audit-log' ),
					array(),
					array(
						esc_html__( 'View order', 'wp-security-audit-log' ) => '%EditorLinkOrder%',
					),
					'woocommerce-order',
					'added',
				),

				9136 => array(
					9136,
					WSAL_HIGH,
					esc_html__( 'User removed a refund from an order', 'wp-security-audit-log' ),
					esc_html__( 'A refund was reversed in order %OrderTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Order ID', 'wp-security-audit-log' ) => '%OrderID%',
					),
					array(
						esc_html__( 'View order', 'wp-security-audit-log' ) => '%EditorLinkOrder%',
					),
					'woocommerce-order',
					'removed',
				),

				9137 => array(
					9137,
					WSAL_HIGH,
					esc_html__( 'User added/removed shipping from an order', 'wp-security-audit-log' ),
					esc_html__( 'Added/Removed shipping in order %OrderTitle%.', 'wp-security-audit-log' ),
					array(),
					array(
						esc_html__( 'View order', 'wp-security-audit-log' ) => '%EditorLinkOrder%',
					),
					'woocommerce-order',
					'added',
				),
				9154 => array(
					9154,
					WSAL_MEDIUM,
					esc_html__( 'User opened an order in the editor', 'wp-security-audit-log' ),
					esc_html__( 'Opened the order %PostTitle% in the editor.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Order ID', 'wp-security-audit-log' ) => '%PostID%',
						esc_html__( 'Order status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					array(
						esc_html__( 'View order', 'wp-security-audit-log' ) => '%EditorLinkOrder%',
					),
					'woocommerce-order',
					'opened',
				),
				9155 => array(
					9155,
					WSAL_LOW,
					esc_html__( 'Order note is added', 'wp-security-audit-log' ),
					esc_html__( 'Added a comment in order %OrderTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Note Type', 'wp-security-audit-log' ) => '%NoteType%',
					),
					array(
						esc_html__( 'View order', 'wp-security-audit-log' ) => '%EditorLinkOrder%',
					),
					'woocommerce-order',
					'added',
				),
				9156 => array(
					9156,
					WSAL_LOW,
					esc_html__( 'Order note is deleted', 'wp-security-audit-log' ),
					esc_html__( 'Removed a note in order %OrderTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Note Type', 'wp-security-audit-log' ) => '%NoteType%',
					),
					array(
						esc_html__( 'View order', 'wp-security-audit-log' ) => '%EditorLinkOrder%',
					),
					'woocommerce-order',
					'removed',
				),
			);
		}

		/**
		 * Returns the array with users profile alerts.
		 *
		 * @since 4.6.0
		 */
		private static function get_user_profile_array(): array {
			return array(
				9083 => array(
					9083,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed the billing address details', 'wp-security-audit-log' ),
					__( 'Changed <strong>billing address</strong> details of the user %TargetUsername%', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Role', 'wp-security-audit-log' ) => '%Roles%',
						esc_html__( 'Previous billing address', 'wp-security-audit-log' ) => '%OldValue%',
						esc_html__( 'New Billing address', 'wp-security-audit-log' ) => '%NewValue%',
					),
					array(
						esc_html__( 'User profile page', 'wp-security-audit-log' ) => '%EditUserLink%',
					),
					'user',
					'modified',
				),
				9084 => array(
					9084,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed the shipping address details', 'wp-security-audit-log' ),
					__( 'Changed the <strong>shipping address</strong> details of the user %TargetUsername%', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Role', 'wp-security-audit-log' ) => '%Roles%',
						esc_html__( 'Previous shipping address', 'wp-security-audit-log' ) => '%OldValue%',
						esc_html__( 'New Shipping address', 'wp-security-audit-log' ) => '%NewValue%',
					),
					array(
						esc_html__( 'User profile page', 'wp-security-audit-log' ) => '%EditUserLink%',
					),
					'user',
					'modified',
				),
			);
		}
	}
}
