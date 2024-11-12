<?php
/**
 * Sensor: Main WP
 *
 * Main WP sensor class file.
 *
 * @since     4.6.0
 * @package   wsal
 * @subpackage sensors
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors;

use WSAL\Helpers\Settings_Helper;
use WSAL\Controllers\Alert_Manager;
use WSAL\Helpers\Plugin_Settings_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\Main_WP_Sensor' ) ) {
	/**
	 * MainWP Plugins & Themes sensor.
	 *
	 * 5000 User installed a plugin
	 * 5001 User activated a WordPress plugin
	 * 5002 User deactivated a WordPress plugin
	 * 5003 User uninstalled a plugin
	 * 5004 User upgraded a plugin
	 * 5005 User installed a theme
	 * 5006 User activated a theme
	 * 5007 User uninstalled a theme
	 * 5031 User updated a theme
	 *
	 * @package    wsal
	 * @subpackage sensors
	 */
	class Main_WP_Sensor {
		/**
		 * List of Themes.
		 *
		 * @var array
		 *
		 * @since 4.5.0
		 */
		private static $old_themes = array();

		/**
		 * Inits the main hooks
		 *
		 * @return void
		 *
		 * @since 4.5.0
		 */
		public static function init() {
			\add_action( 'admin_init', array( __CLASS__, 'event_admin_init' ) );

			// Check if MainWP Child Plugin exists.
			if ( \WpSecurityAuditLog::is_mainwp_active() ) {
				self::mainwp_child_init();

				// Handle plugin/theme installation event via MainWP dashboard.
				\add_action( 'mainwp_child_installPluginTheme', array( __CLASS__, 'mainwp_child_install_assets' ), 10, 1 );

				// Activate/Deactivate plugin event.
				\add_action( 'activated_plugin', array( __CLASS__, 'mainwp_child_plugin_events' ), 10, 1 );
				\add_action( 'deactivated_plugin', array( __CLASS__, 'mainwp_child_plugin_events' ), 10, 1 );

				// Uninstall plugin from MainWP dashboard.
				\add_action( 'mainwp_child_plugin_action', array( __CLASS__, 'mainwp_child_uninstall_plugin' ), 10, 1 );

				// Uninstall theme from MainWP dashboard.
				\add_action( 'mainwp_child_theme_action', array( __CLASS__, 'mainwp_child_uninstall_theme' ), 10, 1 );

				// Update theme/plugin from MainWP dashboard.
				\add_action( 'upgrader_process_complete', array( __CLASS__, 'mainwp_child_update_assets' ), 10, 2 );
			}
			\add_action( 'deactivated_plugin', array( __CLASS__, 'reset_stealth_mode' ), 10, 1 );
		}

		/**
		 *
		 * Reset Stealth Mode on MainWP Child plugin deactivation.
		 *
		 * @param string $plugin — Plugin.
		 *
		 * @since 5.1.0
		 */
		public static function reset_stealth_mode( $plugin ) {
			if ( 'mainwp-child/mainwp-child.php' !== $plugin ) {
				return;
			}

			if ( Settings_Helper::get_boolean_option_value( 'mwp-child-stealth-mode', false ) ) {
				Plugin_Settings_Helper::deactivate_mainwp_child_stealth_mode();
			}
		}

		/**
		 * Triggered when a user accesses the admin area.
		 *
		 * @since 4.5.0
		 */
		public static function event_admin_init() {
			self::$old_themes = wp_get_themes();
		}

		/**
		 * Method: Check and initialize class members for MainWP.
		 *
		 * @since 4.5.0
		 */
		public static function mainwp_child_init() {
			if ( isset( $_POST['mainwpsignature'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$function        = ( isset( $_POST['function'] ) ) ? \sanitize_text_field( \wp_unslash( $_POST['function'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$action          = ( isset( $_POST['action'] ) ) ? \sanitize_text_field( \wp_unslash( $_POST['action'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$theme           = ( isset( $_POST['theme'] ) ) ? \sanitize_text_field( \wp_unslash( $_POST['theme'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$mainwpsignature = ( isset( $_POST['mainwpsignature'] ) ) ? \sanitize_text_field( \wp_unslash( $_POST['mainwpsignature'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing

				if (
				isset( $function ) && 'theme_action' === $function
				&& isset( $action ) && 'delete' === $action
				&& isset( $theme ) && ! empty( $theme )
				&& isset( $mainwpsignature ) && ! empty( $mainwpsignature )
				) {
					if ( empty( self::$old_themes ) ) {
						self::$old_themes = wp_get_themes();
					}
				}
			}
		}

		/**
		 * Get removed themes.
		 *
		 * @return WP_Theme[] List of WP_Theme objects.
		 *
		 * @since 4.5.0
		 */
		protected static function get_removed_themes() {
			$result = self::$old_themes;
			foreach ( $result as $i => $theme ) {
				if ( file_exists( $theme->get_template_directory() ) ) {
					unset( $result[ $i ] );
				}
			}

			return array_values( $result );
		}

		/**
		 * Method: Handle plugin/theme install event
		 * from MainWP dashboard on child site.
		 *
		 * @param array $args - Array of arguments related to asset installed.
		 *
		 * @since 4.5.0
		 */
		public static function mainwp_child_install_assets( $args ) {
			if ( empty( $args ) || ! is_array( $args ) ) {
				return;
			}

			// Verify the action from MainWP.
			if (
			isset( $args['action'] ) && 'install' === $args['action']
			&& isset( $args['success'] ) && ! empty( $args['success'] )
			) {
				if ( isset( $args['type'] ) && 'theme' === $args['type'] ) { // Installing theme.
					// Get theme name & object.
					$theme_slug = isset( $args['slug'] ) ? $args['slug'] : false;
					$theme_obj  = wp_get_theme( $theme_slug );

					// Check if theme exists.
					if ( $theme_obj->exists() ) {
						Alert_Manager::trigger_event(
							5005,
							array(
								'Theme' => (object) array(
									'Name'        => $theme_obj->Name, // phpcs:ignore
									'ThemeURI'    => $theme_obj->ThemeURI, // phpcs:ignore
									'Description' => $theme_obj->Description, // phpcs:ignore
									'Author'      => $theme_obj->Author, // phpcs:ignore
									'Version'     => $theme_obj->Version, // phpcs:ignore
									'get_template_directory' => $theme_obj->get_template_directory(),
								),
							)
						);
					}
				} elseif ( isset( $args['type'] ) && 'plugin' === $args['type'] ) {
					// Get plugin slug.
					$plugin_slug = isset( $args['slug'] ) ? $args['slug'] : false;

					$plugins = get_plugins(); // Get all plugins.
					$plugin  = $plugins[ $plugin_slug ]; // Take out the plugin being installed.

					$plugin_path = plugin_dir_path( WP_PLUGIN_DIR . '/' . $plugin_slug );
					Alert_Manager::trigger_event(
						5000,
						array(
							'Plugin' => (object) array(
								'Name'            => $plugin['Name'],
								'PluginURI'       => $plugin['PluginURI'],
								'Version'         => $plugin['Version'],
								'Author'          => $plugin['Author'],
								'Network'         => $plugin['Network'] ? 'True' : 'False',
								'plugin_dir_path' => $plugin_path,
							),
						)
					);
				}
			}
		}

		/**
		 * Method: Handle plugin uninstall event
		 * from MainWP dashboard on child site.
		 *
		 * @param array $args - Array of arguments related to asset uninstalled.
		 *
		 * @since 4.5.0
		 */
		public static function mainwp_child_uninstall_plugin( $args ) {
			if ( empty( $args ) || ! is_array( $args ) ) {
				return;
			}

			// Get MainWP post data.
			$post_array = filter_input_array( INPUT_POST );

			// Get plugins from MainWP.
			if ( isset( $post_array['plugin'] ) && ! empty( $post_array['plugin'] ) ) {
				$wp_plugins = explode( '||', $post_array['plugin'] );
			}

			// Verify actions from MainWP.
			if (
			isset( $args['action'] ) && 'delete' === $args['action']
			&& isset( $args['Name'] ) && ! empty( $args['Name'] )
			&& isset( $post_array['mainwpsignature'] ) && ! empty( $post_array['mainwpsignature'] )
			) {
				// Get plugin name.
				$plugin_name = $args['Name'];

				// Get plugin filename.
				$plugin_filename = WP_Plugins_Themes_Sensor::get_plugin_file_name( $plugin_name );

				if ( ! empty( $plugin_filename ) && in_array( $plugin_filename, $wp_plugins, true ) ) {
					Alert_Manager::trigger_event(
						5003,
						array(
							'PluginFile' => $plugin_filename,
							'PluginData' => (object) array(
								'Name' => $plugin_name,
							),
						)
					);
				}
			}
		}

		/**
		 * Method: Handle theme uninstall event
		 * from MainWP dashboard on child site.
		 *
		 * @param array $args - Array of arguments related to asset uninstalled.
		 *
		 * @since 4.5.0
		 */
		public static function mainwp_child_uninstall_theme( $args ) {
			if ( empty( $args ) || ! is_array( $args ) ) {
				return;
			}

			// Get MainWP post data.
			$post_array = filter_input_array( INPUT_POST );

			// Get themes from MainWP.
			if ( isset( $post_array['theme'] ) && ! empty( $post_array['theme'] ) ) {
				$wp_themes = explode( '||', \sanitize_text_field( \wp_unslash( $post_array['theme'] ) ) );
			}

			// Verify actions from MainWP.
			if (
			isset( $args['action'] ) && 'delete' === $args['action']
			&& isset( $args['Name'] ) && ! empty( $args['Name'] )
			&& isset( $post_array['mainwpsignature'] ) && ! empty( $post_array['mainwpsignature'] )
			) {
				// Get theme object.
				$themes = self::get_removed_themes();

				if ( ! empty( $themes ) ) {
					foreach ( $themes as $index => $theme ) {
						if ( ! empty( $theme ) && $theme instanceof \WP_Theme && in_array( $theme->Name, $wp_themes, true ) ) { // phpcs:ignore
							Alert_Manager::trigger_event(
								5007,
								array(
									'Theme' => (object) array(
										'Name'        => $theme->Name, // phpcs:ignore
										'ThemeURI'    => $theme->ThemeURI, // phpcs:ignore
										'Description' => $theme->Description, // phpcs:ignore
										'Author'      => $theme->Author, // phpcs:ignore
										'Version'     => $theme->Version, // phpcs:ignore
										'get_template_directory' => $theme->get_template_directory(),
									),
								)
							);
						}
					}
				}
			}
		}

		/**
		 * Method: Handle plugin activation event
		 * from MainWP dashboard on a child site.
		 *
		 * @param string $plugin - Plugin slug.
		 *
		 * @since 4.5.0
		 */
		public static function mainwp_child_plugin_events( $plugin ) {
			// Check parameter.
			if ( empty( $plugin ) && ! \is_wp_error( \validate_plugin( $plugin ) ) ) {
				return;
			}

			// Get MainWP post data.
			$post_array = filter_input_array( INPUT_POST );

			// Get plugins from MainWP.
			if ( isset( $post_array['plugin'] ) && ! empty( $post_array['plugin'] ) ) {
				$wp_plugins = explode( '||', \sanitize_text_field( \wp_unslash( $post_array['plugin'] ) ) );
			}

			if (
			isset( $post_array['mainwpsignature'] ) // Check MainWP signature.
			&& isset( $post_array['action'] ) // Check if action is set.
			&& isset( $post_array['function'] ) // Check if function is set.
			&& 'plugin_action' === $post_array['function']
			&& in_array( $plugin, $wp_plugins, true ) // Check if plugin being activate/deactivate is in the list of plugins from MainWP.
			) {
				if ( 'activate' === $post_array['action'] ) {
					$event = 5001;
				} elseif ( 'deactivate' === $post_array['action'] ) {
					$event = 5002;
				}

				$plugin      = WP_PLUGIN_DIR . '/' . $plugin;
				$plugin_data = get_plugin_data( $plugin, false, true );
				Alert_Manager::trigger_event(
					$event,
					array(
						'PluginFile' => $plugin,
						'PluginData' => (object) array(
							'Name'      => $plugin_data['Name'],
							'PluginURI' => $plugin_data['PluginURI'],
							'Version'   => $plugin_data['Version'],
							'Author'    => $plugin_data['Author'],
							'Network'   => $plugin_data['Network'] ? 'True' : 'False',
						),
					)
				);
			} elseif (
			isset( $post_array['mainwpsignature'] ) // Check MainWP signature.
			&& isset( $post_array['function'] ) // Check if function is set.
			&& 'installplugintheme' === $post_array['function']
			&& isset( $post_array['type'] ) // Check if type is set.
			&& 'plugin' === $post_array['type']
			) {
				$plugin      = WP_PLUGIN_DIR . '/' . $plugin;
				$plugin_data = get_plugin_data( $plugin, false, true );
				Alert_Manager::trigger_event(
					5001,
					array(
						'PluginFile' => $plugin,
						'PluginData' => (object) array(
							'Name'      => $plugin_data['Name'],
							'PluginURI' => $plugin_data['PluginURI'],
							'Version'   => $plugin_data['Version'],
							'Author'    => $plugin_data['Author'],
							'Network'   => $plugin_data['Network'] ? 'True' : 'False',
						),
					)
				);
			}
		}

		/**
		 * Method: Handle plugin/theme update event from MainWP dashboard on child site.
		 *
		 * @param WP_Upgrader $upgrader WordPress upgrader.
		 * @param array       $args     - Array of arguments related to asset updated.
		 *
		 * @since 4.5.0
		 */
		public static function mainwp_child_update_assets( $upgrader, $args ) {
			if ( empty( $args ) || ! is_array( $args ) ) {
				return;
			}

			// Get MainWP post data.
			$post_array = filter_input_array( INPUT_POST );

			// Check type.
			if (
			isset( $post_array['function'] ) && 'upgradeplugintheme' === $post_array['function']
			&& isset( $post_array['mainwpsignature'] ) && ! empty( $post_array['mainwpsignature'] )
			&& isset( $post_array['list'] ) && ! empty( $post_array['list'] )
			&& isset( $args['action'] ) && 'update' === $args['action']
			&& isset( $args['type'] ) && ! empty( $args['type'] )
			) {
				if ( 'theme' === $args['type'] ) {
					// Site themes updated.
					$site_themes = array_key_exists( 'themes', $args ) ? $args['themes'] : explode( ',', \sanitize_text_field( \wp_unslash( $post_array['list'] ) ) );

					if ( empty( $site_themes ) ) {
						// No themes in any of the lists.
						return;
					}

					foreach ( $site_themes as $theme_name ) {
						WP_Plugins_Themes_Sensor::log_theme_updated_event( $theme_name );
					}
				} elseif ( 'plugin' === $args['type'] ) {
					// Site plugins updated.
					if ( ! array_key_exists( 'plugins', $args ) || empty( $args['plugins'] ) ) {
						// No plugins in the list.
						return;
					}

					$plugins = $args['plugins'];
					foreach ( $plugins as $plugin_file ) {
						WP_Plugins_Themes_Sensor::log_plugin_updated_event( $plugin_file );
					}
				}
			}
		}
	}
}
