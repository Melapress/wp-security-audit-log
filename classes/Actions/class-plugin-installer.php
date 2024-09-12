<?php
/**
 * Plugin installer action
 *
 * NOTE: Currently this class is used only for deactivating the legacy extension plugins.
 *
 * Class file for installing plugins from the repo.
 *
 * @since 4.0.1
 * @package wsal
 */

declare(strict_types=1);

namespace WSAL\Actions;

use WSAL\Helpers\WP_Helper;
use WSAL\Helpers\Plugins_Helper;

if ( ! class_exists( '\WSAL\Actions\Plugin_Installer' ) ) {

	/**
	 * Class to handle the installation and activation of plugins.
	 *
	 * @since 4.0.1
	 */
	class Plugin_Installer {

		/**
		 * Register the ajax action.
		 *
		 * @method register
		 *
		 * @since  4.0.1
		 */
		public static function init() {
			\add_action( 'wp_ajax_wsal_run_addon_install', array( __CLASS__, 'run_addon_install' ) );
		}

		/**
		 * Run the installer.
		 *
		 * @method run_addon_install
		 *
		 * @since  4.0.1
		 */
		public static function run_addon_install() {
			\check_ajax_referer( 'wsal-install-addon' );

			$predefined_plugins = Plugins_Helper::get_installable_plugins();

			// Setup empties to avoid errors.
			$plugin_zip  = '';
			$plugin_slug = '';

			if ( ! ( isset( $_POST['addon_for'] ) && is_array( $predefined_plugins ) ) ) {
				// no 'addon_for' passed, check for a zip and slug.
				$plugin_zip  = ( isset( $_POST['plugin_url'] ) ) ? esc_url_raw( wp_unslash( $_POST['plugin_url'] ) ) : '';
				$plugin_slug = ( isset( $_POST['plugin_slug'] ) ) ? sanitize_textarea_field( wp_unslash( $_POST['plugin_slug'] ) ) : '';
			} else {
				/*
				 * Key POSTed as an 'addon_for', try get the zip and slug.
				 *
				 * @since 4.0.2
				 */
				$addon = sanitize_text_field( wp_unslash( $_POST['addon_for'] ) );
				$addon = apply_filters( 'wsal_modify_predefined_plugin_slug', $addon );

				foreach ( $predefined_plugins as $plugin ) {
					if ( strtolower( $plugin['addon_for'] ) === $addon ) {
						$plugin_zip  = $plugin['plugin_url'];
						$plugin_slug = $plugin['plugin_slug'];
					}
				}
			}

			// validate that the plugin is in the allowed list, or it is our helper plugin with external libraries.
			$valid                      = false;
			$helper_plugin_installation = 'wsal-external-libraries/wsal-external-libraries.php' === $plugin_slug;
			if ( $helper_plugin_installation ) {
				$valid = true;
			} else {
				foreach ( $predefined_plugins as $plugin ) {
					// if we have a valid plugin then break.
					if ( $valid ) {
						break;
					}

					$valid = $plugin_zip === $plugin['plugin_url'] && $plugin_slug === $plugin['plugin_slug'];
				}
			}

			// bail early if we didn't get a valid url and slug to install.
			if ( ! $valid ) {
				\wp_send_json_error(
					array(
						'message' => esc_html__( 'Tried to install a zip or slug that was not in the allowed list', 'wp-security-audit-log' ),
					)
				);
			}

			// Check if the plugin is installed.
			if ( self::is_plugin_installed( $plugin_slug ) ) {
				// If plugin is installed but not active, activate it.
				if ( ! WP_Helper::is_plugin_active( $plugin_zip ) ) {
					self::run_activate( $plugin_slug );
					self::activate( $plugin_zip );
					$result = 'activated';
				} else {
					$result = 'already_installed';
				}
			} else {
				// No plugin found or plugin not present to be activated, so lets install it.
				self::install_plugin( $plugin_zip );
				self::run_activate( $plugin_slug );
				self::activate( $plugin_zip );
				$result = 'success';
			}

			// If we're installing our helper plugin, we also need to delete the nudge to install the helper plugin.
			if ( $helper_plugin_installation ) {
				\WSAL\Helpers\Settings_Helper::delete_option_value( 'show-helper-plugin-needed-nudge' );
			}

			\wp_send_json( $result );
		}

		/**
		 * Install a plugin given a slug.
		 *
		 * @param  string $plugin_zip URL to the direct zip file.
		 *
		 * @since  4.0.1
		 */
		public static function install_plugin( $plugin_zip = '' ) {
			// bail early if we don't have a slug to work with.
			if ( empty( $plugin_zip ) ) {
				return;
			}
			// get the core plugin upgrader if not already in the runtime.
			if ( ! class_exists( 'Plugin_Upgrader' ) ) {
				include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			}
			// clear the cache so we're using fresh data.
			\wp_cache_flush();
			$upgrader       = new \Plugin_Upgrader();
			$install_result = $upgrader->install( $plugin_zip );
			if ( ! $install_result || \is_wp_error( $install_result ) ) {
				if ( \is_wp_error( $install_result ) ) {
					return $install_result->get_error_message();
				}
				die();
			}
		}

		/**
		 * Activates a plugin that is available on the site.
		 *
		 * @param  string $plugin_zip URL to the direct zip file.
		 *
		 * @return void
		 *
		 * @since  4.0.1
		 */
		public static function activate( $plugin_zip = '' ) {
			// bail early if we don't have a slug to work with.
			if ( empty( $plugin_zip ) ) {
				return;
			}

			if ( WP_Helper::is_multisite() ) {
				return;
			}

			// get core plugin functions if they are not already in runtime.
			if ( ! function_exists( 'activate_plugin' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			if ( ! WP_Helper::is_plugin_active( $plugin_zip ) ) {
				\activate_plugin( $plugin_zip );
			}
		}

		/**
		 * Deactivate plugin by given slug
		 *
		 * @param string $plugin - The slug of the plugin to deactivate.
		 *
		 * @return boolean
		 *
		 * @since 4.6.0
		 */
		public static function deactivate_plugin( string $plugin ): bool {
			if ( ! function_exists( 'deactivate_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			if ( WP_Helper::is_plugin_active( $plugin ) ) {
				$network_wide = false; // Set to true if you want to deactivate the plugin network-wide (for multisite).
				if ( WP_Helper::is_multisite() ) {
					$network_wide = true;
				}

				$result = \deactivate_plugins( $plugin, false, $network_wide );

				// Check if the plugin was deactivated.
				if ( \is_wp_error( $result ) ) {
					return false;
				}
			}

			return true;
		}

		/**
		 * Activates a plugin that is available on the site.
		 *
		 * @method run_activate
		 * @since  4.0.1
		 * @param  string $plugin_slug slug for plugin.
		 */
		public static function run_activate( $plugin_slug = '' ) {
			// bail early if we don't have a slug to work with.
			if ( empty( $plugin_slug ) ) {
				return;
			}

			if ( WP_Helper::is_multisite() ) {
				$current = \get_site_option( 'active_sitewide_plugins' );
			} else {
				$current = \get_option( 'active_plugins' );
			}

			if ( ! in_array( $plugin_slug, $current, true ) ) {
				if ( WP_Helper::is_multisite() ) {
					$current[] = $plugin_slug;
					\activate_plugin( $plugin_slug, '', true );
				} else {
					$current[] = $plugin_slug;
					\activate_plugin( $plugin_slug );
				}
			}
			return null;
		}

		/**
		 * Check if a plugin is installed.
		 *
		 * @param  string $plugin_slug slug for plugin.
		 *
		 * @since  4.0.1
		 */
		public static function is_plugin_installed( $plugin_slug = '' ) {
			// bail early if we don't have a slug to work with.
			if ( empty( $plugin_slug ) ) {
				return;
			}

			// get core plugin functions if not already in the runtime.
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$all_plugins = \get_plugins();

			// true if plugin is already installed or false if not.
			if ( ! empty( $all_plugins[ $plugin_slug ] ) ) {
				return true;
			} else {
				return false;
			}
		}
	}
}
