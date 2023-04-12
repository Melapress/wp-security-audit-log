<?php
/**
 * Yoast SEO extension class.
 *
 * @package    wsal
 * @subpackage add-ons
 */

declare(strict_types=1);

namespace WSAL\PluginExtensions;

if ( ! class_exists( '\WSAL\PluginExtensions\YoastSeo_Extension' ) ) {
	/**
	 * Class provides basic information about WSAL extension for Yoast SEO.
	 *
	 * @package    wsal
	 * @subpackage add-ons
	 */
	class YoastSeo_Extension {
		/**
		 * Inits the extension hooks.
		 *
		 * @return void
		 *
		 * @since 4.5.0
		 */
		public static function init() {
			add_filter( 'wsal_modify_predefined_plugin_slug', array( __CLASS__, 'modify_predefined_plugin_slug' ), 10, 1 );
		}

		/**
		 * Add our extension to the array of installable extensions.
		 *
		 * @return array
		 *
		 * @since 4.5.0
		 */
		public static function filter_installable_plugins(): array {
			$new_plugin = array(
				array(
					'addon_for'          => 'wp-seo',
					'title'              => self::get_plugin_name(),
					'image_filename'     => 'yoast.png',
					'plugin_slug'        => self::get_plugin_filename(),
					'plugin_basename'    => 'activity-log-wp-seo.php',
					'plugin_url'         => 'https://downloads.wordpress.org/plugin/activity-log-wp-seo.latest-stable.zip',
					'event_tab_id'       => '#cat-yoast-seo',
					'plugin_description' => 'Keep a log of all the changes that you and your team do in the Yoast SEO metabox, plugin settings & much more.',
				),
			);

			// combine the two arrays.
			return $new_plugin;
		}

		/**
		 * Add our extensions event IDs to the array of available events.
		 *
		 * @return array
		 *
		 * @since 4.5.0
		 */
		public static function add_event_codes(): array {
			$new_event_codes = array(
				'yoast' => array(
					'name'      => self::get_plugin_name(),
					'event_ids' => array( 8801, 8802, 8803, 8804, 8805, 8806, 8807, 8808, 8850, 8851, 8852, 8809, 8810, 8811, 8812, 8815, 8816, 8817, 8818, 8819, 8820, 8821, 8822, 8824, 8825, 8826, 8827, 8828, 8829, 8838, 8839, 8840, 8842, 8843, 8813, 8814, 8830, 8831, 8832, 8833, 8834, 8835, 8836, 8837, 8853, 8854, 8841, 8844, 8845, 8846, 8847, 8848, 8855, 8856, 8857, 8858 ),
				),
			);

			// combine the two arrays.
			return $new_event_codes;
		}

		/**
		 * Retrieves a plugin name.
		 *
		 * @return string Plugin name.
		 *
		 * @latest
		 */
		public static function get_plugin_name(): string {
			return 'Yoast SEO';
		}

		/**
		 * Retrieves a plugin name.
		 *
		 * @return string Plugin name.
		 *
		 * @since 4.5.0
		 */
		public static function get_plugin_icon_url(): string {
			return 'https://ps.w.org/activity-log-wp-seo/assets/icon-128x128.png?rev=2393849';
		}

		/**
		 * Retrieves the color to use when showing some info about the extension.
		 *
		 * @return string HEX color.
		 *
		 * @since 4.5.0
		 */
		public static function get_color(): string {
			return '#a4286a';
		}

		/**
		 * Gets the filename of the plugin this extension is targeting.
		 *
		 * @return string Filename.
		 *
		 * @since 4.5.0
		 */
		public static function get_plugin_filename(): string {
			return 'activity-log-wp-seo/activity-log-wp-seo.php';
		}

		/**
		 * Correct plugin slug depending on the context.
		 *
		 * @param string $plugin Current slug to alter.
		 *
		 * @return string         Modified slug.
		 */
		public static function modify_predefined_plugin_slug( $plugin ): string {
			// Correct yoast addon.
			if ( 'yoast' === $plugin ) {
				return 'wp-seo';
			}

			return $plugin;
		}
	}
}
