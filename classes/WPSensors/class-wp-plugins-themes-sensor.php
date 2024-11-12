<?php
/**
 * Sensor: Plugins and Themes
 *
 * Plugins and Themes sensor class file.
 *
 * @since      4.5.0
 * @package    wsal
 * @subpackage sensors
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors;

use WSAL\Helpers\Plugins_Helper;
use WSAL\Helpers\Settings_Helper;
use WSAL\Controllers\Alert_Manager;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\WP_Plugins_Themes_Sensor' ) ) {
	/**
	 * Plugins & Themes sensor.
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
	 * @since 4.5.0
	 */
	class WP_Plugins_Themes_Sensor {

		/**
		 * List of Themes.
		 *
		 * @var array
		 *
		 * @since 4.5.0
		 */
		private static $old_themes = array();

		/**
		 * List of Plugins.
		 *
		 * @var array
		 *
		 * @since 4.5.0
		 */
		private static $old_plugins = array();

		/**
		 * Inits the main hooks
		 *
		 * @return void
		 *
		 * @since 4.5.0
		 */
		public static function init() {
			$has_permission = ( current_user_can( 'install_plugins' ) || current_user_can( 'activate_plugins' ) ||
				current_user_can( 'delete_plugins' ) || current_user_can( 'update_plugins' ) || current_user_can( 'install_themes' ) );

			add_action( 'admin_init', array( __CLASS__, 'event_admin_init' ) );
			if ( $has_permission ) {
				add_action( 'shutdown', array( __CLASS__, 'event_admin_shutdown' ) );
			}
			add_action( 'switch_theme', array( __CLASS__, 'event_theme_activated' ) );
			add_action( 'upgrader_overwrote_package', array( __CLASS__, 'on_package_overwrite' ), 10, 3 );

			add_action( 'deleted_theme', array( __CLASS__, 'on_deleted_theme' ), 10, 2 );
			add_action( 'upgrader_process_complete', array( __CLASS__, 'detect_upgrade_completed' ), 10, 2 );

			add_action( 'wp_insert_post', array( __CLASS__, 'plugin_created_post' ), 10, 2 );
		}

		/**
		 * Trigger event once an automatic theme or plugin update has occured
		 *
		 * @param WP_Upgrader $upgrader_object - WP Upgrader object.
		 * @param array       $hook_extra - Update details.
		 * @return void
		 *
		 * @since 4.5.0
		 */
		public static function detect_upgrade_completed( $upgrader_object, $hook_extra ) {

			if ( is_array( $hook_extra ) && isset( $hook_extra['plugin'] ) && 'wp-security-audit-log.php' === $hook_extra['plugin'] ) {
				/**
				 * Our own plugin gets updated, unfortunately we have no idea (and most probably that is the old version of our plugin) what is the state of the plugin and what has been changed in the new version - check code reference here - https://developer.wordpress.org/reference/hooks/upgrader_process_complete/ especially the part that stays:
				 * Use with caution: When you use the upgrader_process_complete action hook in your plugin and your plugin is the one which under upgrade, then this action will run the old version of your plugin.
				 */

				return;
			}

			if ( isset( $hook_extra['plugin'] ) ) {
				self::log_plugin_updated_event( $hook_extra['plugin'] );
			} elseif ( isset( $hook_extra['theme'] ) ) {
				self::log_theme_updated_event( $hook_extra['theme'] );
			}
		}

		/**
		 * Handles a theme deletion attempt.
		 *
		 * @param string $stylesheet Stylesheet of the theme to delete.
		 * @param bool   $deleted    Whether the theme deletion was successful.
		 *
		 * @since 4.5.0
		 */
		public static function on_deleted_theme( $stylesheet, $deleted ) {
			if ( ! $deleted ) {
				return;
			}

			if ( ! array_key_exists( $stylesheet, self::$old_themes ) ) {
				return;
			}

			$theme = self::$old_themes[ $stylesheet ];
			Alert_Manager::trigger_event(
				5007,
				array(
					'Theme' => (object) array(
						'Name'                   => $theme->Name, // phpcs:ignore
						'ThemeURI'               => $theme->ThemeURI, // phpcs:ignore
						'Description'            => $theme->Description, // phpcs:ignore
						'Author'                 => $theme->Author, // phpcs:ignore
						'Version'                => $theme->Version, // phpcs:ignore
						'get_template_directory' => $theme->get_template_directory(),
					),
				)
			);
		}

		/**
		 * Triggered when a user accesses the admin area.
		 *
		 * @since 4.5.0
		 */
		public static function event_admin_init() {
			self::$old_themes  = wp_get_themes();
			self::$old_plugins = get_plugins();
		}

		/**
		 * Install, uninstall, activate, deactivate, upgrade and update.
		 *
		 * @since 4.5.0
		 */
		public static function event_admin_shutdown() {
			// Filter global arrays for security.
			$post_array  = filter_input_array( INPUT_POST );
			$get_array   = filter_input_array( INPUT_GET );
			$script_name = isset( $_SERVER['SCRIPT_NAME'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) : false;

			$action = '';
			if ( isset( $get_array['action'] ) && '-1' !== $get_array['action'] ) {
				$action = $get_array['action'];
			} elseif ( isset( $post_array['action'] ) && '-1' !== $post_array['action'] ) {
				$action = $post_array['action'];
			}

			if ( isset( $get_array['action2'] ) && '-1' !== $get_array['action2'] ) {
				$action = $get_array['action2'];
			} elseif ( isset( $post_array['action2'] ) && '-1' !== $post_array['action2'] ) {
				$action = $post_array['action2'];
			}

			$actype = '';
			if ( ! empty( $script_name ) ) {
				$actype = basename( $script_name, '.php' );
			}

			$is_plugins = 'plugins' === $actype;

			// Install plugin.
			if ( in_array( $action, array( 'install-plugin', 'upload-plugin', 'wsal_run_addon_install' ), true ) && current_user_can( 'install_plugins' ) ) {
				$plugin = array_merge( array_diff( array_keys( get_plugins() ), array_keys( self::$old_plugins ) ), array_diff( array_keys( self::$old_plugins ), array_keys( get_plugins() ) ) );

				// Check for premium version being installed / updated.
				if ( in_array( \WpSecurityAuditLog::PREMIUM_VERSION_WHOLE_PLUGIN_NAME, $plugin, true ) ) {
					/**
					 * It looks like our own plugin is installed / updated. That means that we have no idea if there is a version on server or the plugin is in memory only (if it is he don't know which parts of it are there), that could lead to PHP errors which will prevent plugin install / update, better approach is to do nothing in terms of logging.
					 *
					 * TODO: the plugin name (see comparison in if clause above) could be whatever, we must introduce constant for that probably
					 */
					return;
				}
				// Check for free version being installed / updated.
				if ( in_array( \WpSecurityAuditLog::FREE_VERSION_WHOLE_PLUGIN_NAME, $plugin, true ) ) {
					/**
					 * It looks like our own plugin is installed / updated. That means that we have no idea if there is a version on server or the plugin is in memory only (if it is he don't know which parts of it are there), that could lead to PHP errors which will prevent plugin install / update, better approach is to do nothing in terms of logging.
					 *
					 * TODO: the plugin name (see comparison in if clause above) could be whatever, we must introduce constant for that probably
					 */
					return;
				}
				// Check for nofs version being installed / updated.
				if ( in_array( \WpSecurityAuditLog::NOFS_VERSION_WHOLE_PLUGIN_NAME, $plugin, true ) ) {
					/**
					 * It looks like our own plugin is installed / updated. That means that we have no idea if there is a version on server or the plugin is in memory only (if it is he don't know which parts of it are there), that could lead to PHP errors which will prevent plugin install / update, better approach is to do nothing in terms of logging.
					 *
					 * TODO: the plugin name (see comparison in if clause above) could be whatever, we must introduce constant for that probably
					 */
					return;
				}

				if ( ! count( $plugin ) ) {
					/**
					 * No changed plugins - there is nothing we suppose to log.
					 */
					return;
				}

				if ( count( $plugin ) > 1 ) {
					Alert_Manager::log_error(
						'Expected exactly one new plugin but found ' . count( $plugin ),
						array(
							'NewPlugin'  => $plugin,
							'OldPlugins' => self::$old_plugins,
							'NewPlugins' => get_plugins(),
						)
					);
					return;
				}
				$plugin_path = $plugin[0];
				$plugin      = get_plugins();
				$plugin      = $plugin[ $plugin_path ];

				// Get plugin directory name.
				$plugin_dir = self::get_plugin_dir( $plugin_path );

				$plugin_path = plugin_dir_path( WP_PLUGIN_DIR . '/' . $plugin_path[0] );
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

				// self::run_addon_check( $plugin_dir );
			}

			// Activate plugin.
			if ( $is_plugins && in_array( $action, array( 'activate', 'activate-selected' ), true ) && current_user_can( 'activate_plugins' ) ) {
				// Check $_GET array case.
				if ( isset( $get_array['plugin'] ) ) {
					if ( ! isset( $get_array['checked'] ) ) {
						$get_array['checked'] = array();
					}
					$get_array['checked'][] = $get_array['plugin'];
				}

				// Check $_POST array case.
				if ( isset( $post_array['plugin'] ) ) {
					if ( ! isset( $post_array['checked'] ) ) {
						$post_array['checked'] = array();
					}
					$post_array['checked'][] = $post_array['plugin'];
				}

				if ( isset( $get_array['checked'] ) && ! empty( $get_array['checked'] ) ) {
					$latest_event = Alert_Manager::get_latest_events( 1, true );

					if ( false !== $latest_event && \is_array( $latest_event ) ) {
						$latest_event = reset( $latest_event );
					}
					$event_meta = $latest_event ? $latest_event['meta_values'] : false;

					foreach ( $get_array['checked'] as $plugin_file ) {
						if ( ! \is_wp_error( \validate_plugin( $plugin_file ) ) ) {
							if ( $latest_event && 5001 === (int) $latest_event['alert_id'] && $event_meta && isset( $event_meta['PluginFile'] ) ) {
								if ( basename( WSAL_BASE_NAME ) === basename( $event_meta['PluginFile'] ) ) {
									continue;
								}
							}
							$plugin_file = WP_PLUGIN_DIR . '/' . $plugin_file;
							$plugin_data = get_plugin_data( $plugin_file, false, true );

							Alert_Manager::trigger_event(
								5001,
								array(
									'PluginFile' => $plugin_file,
									'PluginData' => (object) array(
										'Name'      => $plugin_data['Name'],
										'PluginURI' => $plugin_data['PluginURI'],
										'Version'   => $plugin_data['Version'],
										'Author'    => $plugin_data['Author'],
										'Network'   => $plugin_data['Network'] ? 'True' : 'False',
									),
								)
							);

							// self::run_addon_check( $plugin_file );
						}
					}
				} elseif ( isset( $post_array['checked'] ) && ! empty( $post_array['checked'] ) ) {
					foreach ( $post_array['checked'] as $plugin_file ) {

						if ( ! \is_wp_error( \validate_plugin( $plugin_file ) ) ) {
							$plugin_file = WP_PLUGIN_DIR . '/' . $plugin_file;
							$plugin_data = get_plugin_data( $plugin_file, false, true );
							Alert_Manager::trigger_event(
								5001,
								array(
									'PluginFile' => $plugin_file,
									'PluginData' => (object) array(
										'Name'      => $plugin_data['Name'],
										'PluginURI' => $plugin_data['PluginURI'],
										'Version'   => $plugin_data['Version'],
										'Author'    => $plugin_data['Author'],
										'Network'   => $plugin_data['Network'] ? 'True' : 'False',
									),
								)
							);

							// self::run_addon_check( $plugin_file );
						}
					}
				}
			}

			// Deactivate plugin.
			if ( $is_plugins && in_array( $action, array( 'deactivate', 'deactivate-selected' ), true ) && current_user_can( 'activate_plugins' ) ) {
				// Check $_GET array case.
				if ( isset( $get_array['plugin'] ) ) {
					if ( ! isset( $get_array['checked'] ) ) {
						$get_array['checked'] = array();
					}
					$get_array['checked'][] = $get_array['plugin'];
				}

				// Check $_POST array case.
				if ( isset( $post_array['plugin'] ) ) {
					if ( ! isset( $post_array['checked'] ) ) {
						$post_array['checked'] = array();
					}
					$post_array['checked'][] = $post_array['plugin'];
				}

				if ( isset( $get_array['checked'] ) && ! empty( $get_array['checked'] ) ) {
					foreach ( $get_array['checked'] as $plugin_file ) {
						if ( ! \is_wp_error( \validate_plugin( $plugin_file ) ) ) {
							$plugin_file = WP_PLUGIN_DIR . '/' . $plugin_file;
							$plugin_data = get_plugin_data( $plugin_file, false, true );
							Alert_Manager::trigger_event(
								5002,
								array(
									'PluginFile' => $plugin_file,
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
						// self::run_addon_removal_check( $plugin_file );
					}
				} elseif ( isset( $post_array['checked'] ) && ! empty( $post_array['checked'] ) ) {
					foreach ( $post_array['checked'] as $plugin_file ) {
						if ( ! \is_wp_error( \validate_plugin( $plugin_file ) ) ) {
							$plugin_file = WP_PLUGIN_DIR . '/' . $plugin_file;
							$plugin_data = get_plugin_data( $plugin_file, false, true );
							Alert_Manager::trigger_event(
								5002,
								array(
									'PluginFile' => $plugin_file,
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
				}
			}

			// Uninstall plugin.
			if ( $is_plugins && in_array( $action, array( 'delete-selected' ), true ) && current_user_can( 'delete_plugins' ) ) {
				if ( ! isset( $post_array['verify-delete'] ) ) { // phpcs:ignore
					// First step, before user approves deletion
					// TODO store plugin data in session here.
				} else {
					// second step, after deletion approval
					// TODO use plugin data from session.
					foreach ( $post_array['checked'] as $plugin_file ) {
						if ( ! \is_wp_error( \validate_plugin( $plugin_file ) ) ) {
							$plugin_name = basename( $plugin_file, '.php' );
							$plugin_name = str_replace( array( '_', '-', '  ' ), ' ', $plugin_name );
							$plugin_name = ucwords( $plugin_name );
							$plugin_file = WP_PLUGIN_DIR . '/' . $plugin_file;
							$plugin_data = get_plugin_data( $plugin_file, false, true );
							Alert_Manager::trigger_event(
								5003,
								array(
									'PluginFile' => $plugin_file,
									'PluginData' => (object) array(
										'Name'    => $plugin_name,
										'Version' => $plugin_data['Version'],
									),
								)
							);
						}
					}
				}
			}

			// Uninstall plugin for WordPress version 4.6.
			if ( in_array( $action, array( 'delete-plugin' ), true ) && current_user_can( 'delete_plugins' ) ) {
				if ( isset( $post_array['plugin'] ) ) {
					if ( ! \is_wp_error( \validate_plugin( $post_array['plugin'] ) ) ) {
						$plugin_file = WP_PLUGIN_DIR . '/' . $post_array['plugin'];
						$plugin_name = basename( $plugin_file, '.php' );
						$plugin_name = str_replace( array( '_', '-', '  ' ), ' ', $plugin_name );
						$plugin_name = ucwords( $plugin_name );
						$plugin_data = self::$old_plugins[ $post_array['plugin'] ];
						Alert_Manager::trigger_event(
							5003,
							array(
								'PluginFile' => $plugin_file,
								'PluginData' => (object) array(
									'Name'    => $plugin_name,
									'Version' => $plugin_data['Version'],
								),
							)
						);
					}

					// self::run_addon_removal_check( $plugin_file );
				}
			}

			// Upgrade plugin.
			if ( in_array( $action, array( 'upgrade-plugin', 'update-plugin', 'update-selected' ), true ) && current_user_can( 'update_plugins' ) ) {
				$plugins = array();

				// Check $_GET array cases.
				if ( isset( $get_array['plugins'] ) ) {
					$plugins = explode( ',', $get_array['plugins'] );
				} elseif ( isset( $get_array['plugin'] ) ) {
					$plugins[] = $get_array['plugin'];
				}

				// Check $_POST array cases.
				if ( isset( $post_array['plugins'] ) ) {
					$plugins = explode( ',', $post_array['plugins'] );
				} elseif ( isset( $post_array['plugin'] ) ) {
					$plugins[] = $post_array['plugin'];
				}
				if ( isset( $plugins ) ) {
					foreach ( $plugins as $plugin_file ) {
						if ( ! \is_wp_error( \validate_plugin( $plugin_file ) ) ) {
							self::log_plugin_updated_event( $plugin_file, self::$old_plugins );
						}
					}
				}
			}

			// Update theme.
			if ( in_array( $action, array( 'upgrade-theme', 'update-theme', 'update-selected-themes' ), true ) && current_user_can( 'install_themes' ) ) {
				// Themes.
				$themes = array();

				// Check $_GET array cases.
				if ( isset( $get_array['slug'] ) || isset( $get_array['theme'] ) ) {
					$themes[] = isset( $get_array['slug'] ) ? $get_array['slug'] : $get_array['theme'];
				} elseif ( isset( $get_array['themes'] ) ) {
					$themes = explode( ',', $get_array['themes'] );
				}

				// Check $_POST array cases.
				if ( isset( $post_array['slug'] ) || isset( $post_array['theme'] ) ) {
					$themes[] = isset( $post_array['slug'] ) ? $post_array['slug'] : $post_array['theme'];
				} elseif ( isset( $post_array['themes'] ) ) {
					$themes = explode( ',', $post_array['themes'] );
				}
				if ( isset( $themes ) ) {
					foreach ( $themes as $theme_name ) {
						self::log_theme_updated_event( $theme_name );
					}
				}
			}

			// Install theme.
			if ( in_array( $action, array( 'install-theme', 'upload-theme' ), true ) && current_user_can( 'install_themes' ) ) {
				$themes = array_diff( wp_get_themes(), self::$old_themes );
				foreach ( $themes as $theme ) {
					Alert_Manager::trigger_event(
						5005,
						array(
							'Theme' => (object) array(
								'Name'                   => $theme->Name, // phpcs:ignore
								'ThemeURI'               => $theme->ThemeURI, // phpcs:ignore
								'Description'            => $theme->Description, // phpcs:ignore
								'Author'                 => $theme->Author, // phpcs:ignore
								'Version'                => $theme->Version, // phpcs:ignore
								'get_template_directory' => $theme->get_template_directory(),
							),
						)
					);
				}
			}
		}

		/**
		 * Activated a theme.
		 *
		 * @param string $theme_name - Theme name.
		 *
		 * @since 4.5.0
		 */
		public static function event_theme_activated( $theme_name ) {
			$theme = null;
			foreach ( wp_get_themes() as $item ) {
				if ( $theme_name === $item->Name ) { // phpcs:ignore
					$theme = $item;
					break;
				}
			}
			if ( null == $theme ) { // phpcs:ignore
				Alert_Manager::log_error(
					'Could not locate theme named "' . $theme . '".',
					array(
						'ThemeName' => $theme_name,
						'Themes'    => wp_get_themes(),
					)
				);
				return;
			}
			Alert_Manager::trigger_event(
				5006,
				array(
					'Theme' => (object) array(
						'Name'                   => $theme->Name, // phpcs:ignore
						'ThemeURI'               => $theme->ThemeURI, // phpcs:ignore
						'Description'            => $theme->Description, // phpcs:ignore
						'Author'                 => $theme->Author, // phpcs:ignore
						'Version'                => $theme->Version, // phpcs:ignore
						'get_template_directory' => $theme->get_template_directory(),
					),
				)
			);
		}

		/**
		 * Get event code by post type.
		 *
		 * @param object $post        - Post object.
		 * @param int    $type_post   - Code for post.
		 * @param int    $type_page   - Code for page.
		 * @param int    $type_custom - Code for custom post type.
		 *
		 * @return false|int
		 *
		 * @since 4.5.0
		 */
		protected static function get_event_type_for_post_type( $post, $type_post, $type_page, $type_custom ) {
			if ( empty( $post ) || ! isset( $post->post_type ) ) {
				return false;
			}

			switch ( $post->post_type ) {
				case 'page':
					return $type_page;
				case 'post':
					return $type_post;
				default:
					return $type_custom;
			}
		}

		/**
		 * Method: Remove the PHP file after `/` in the plugin
		 * directory name.
		 *
		 * For example, it will remove `/akismet.php` from
		 * `akismet/akismet.php`.
		 *
		 * @param string $plugin – Plugin name.
		 *
		 * @return string
		 *
		 * @since 4.5.0
		 */
		public static function get_plugin_dir( $plugin ) {
			$position = strpos( $plugin, '/' );
			if ( false !== $position ) {
				$plugin = substr_replace( $plugin, '', $position );
			}
			return $plugin;
		}

		/**
		 * Method: Return plugin file name.
		 *
		 * @param string $plugin_name - Plugin name.
		 *
		 * @return string
		 *
		 * @since 4.5.0
		 */
		public static function get_plugin_file_name( $plugin_name ) {
			// Verify parameter.
			if ( empty( $plugin_name ) ) {
				return '';
			}

			// Get all plugins.
			$all_plugins = get_plugins();

			$plugin_filename = '';
			if ( ! empty( $all_plugins ) && is_array( $all_plugins ) ) {
				foreach ( $all_plugins as $plugin_file => $plugin_data ) {
					if ( $plugin_name === $plugin_data['Name'] ) {
						$plugin_filename = $plugin_file;
					}
				}
			}

			return $plugin_filename;
		}

		/**
		 * Method: Search and return theme object by name.
		 *
		 * @param string $theme_name - Theme name.
		 *
		 * @return WP_Theme|null
		 *
		 * @since 4.5.0
		 */
		public static function get_theme_by_name( $theme_name ) {
			// Check if $theme_name is empty.
			if ( empty( $theme_name ) ) {
				return null;
			}

			// Get all themes.
			$all_themes = wp_get_themes();

			$theme = null;
			if ( ! empty( $all_themes ) ) {
				foreach ( $all_themes as $theme_slug => $theme_obj ) {
					if ( $theme_name === $theme_slug || $theme_name === $theme_obj->get( 'Name' ) ) {
						$theme = $theme_obj;
						break;
					}
				}
			}
			return $theme;
		}

		/**
		 * Runs an add-on check.
		 *
		 * @param string $plugin_dir Plugin directory.
		 *
		 * @since 4.5.0
		 */
		public static function run_addon_check( $plugin_dir ) {

			$path_parts      = pathinfo( $plugin_dir );
			$plugin_filename = $path_parts['filename'];

			// Grab list of plugins we have addons for.
			$predefined_plugins       = Plugins_Helper::get_installable_plugins();
			$predefined_plugins_addon = array_column( $predefined_plugins, 'addon_for' );
			$all_plugins              = array_keys( get_plugins() );
			foreach ( $predefined_plugins_addon as $plugin ) {

				$plugin = apply_filters( 'wsal_modify_predefined_plugin_slug', $plugin );

				// Check if plugin file starts with the same string as our addon_for, or if its equal.
				if ( $plugin_filename === $plugin ) {
					$addon_slug         = array( array_search( $plugin, array_column( $predefined_plugins, 'addon_for', 'plugin_slug' ) ) ); // phpcs:ignore
					$is_addon_installed = array_intersect( $all_plugins, $addon_slug );
					if ( empty( $is_addon_installed ) ) {
						$current_value   = Settings_Helper::get_option_value( 'installed_plugin_addon_available' );
						$plugin_filename = array( $plugin_filename );
						if ( isset( $current_value ) && is_array( $current_value ) ) {
							$new_plugin_filenames = array_unique( array_merge( $current_value, $plugin_filename ) );
						} else {
							$new_plugin_filenames = $plugin_filename;
						}
						Settings_Helper::set_option_value( 'installed_plugin_addon_available', $new_plugin_filenames );
						Settings_Helper::delete_option_value( 'addon_available_notice_dismissed' );
					}
				}
			}
		}

		/**
		 * Checks for an add-on removal.
		 *
		 * @param string $plugin_dir Plugin directory.
		 *
		 * @since 4.5.0
		 */
		public static function run_addon_removal_check( $plugin_dir ) {

			$path_parts      = pathinfo( $plugin_dir );
			$plugin_filename = $path_parts['filename'];

			// Grab list of plugins we have addons for.
			$predefined_plugins       = Plugins_Helper::get_installable_plugins();
			$predefined_plugins_addon = array_column( $predefined_plugins, 'addon_for' );
			foreach ( $predefined_plugins_addon as $plugin ) {

				$plugin = apply_filters( 'wsal_modify_predefined_plugin_slug', $plugin );

				// Check if plugin file starts with the same string as our addon_for, or if its equal.
				if ( $plugin_filename === $plugin ) {
					$current_installed = Settings_Helper::get_option_value( 'installed_plugin_addon_available' );
					if ( isset( $current_installed ) && ! empty( $current_installed ) ) {
						$key = array_search( $plugin, $current_installed ); // phpcs:ignore
						if ( false !== $key ) {
							unset( $current_installed[ $key ] );
						}
					}

					Settings_Helper::set_option_value( 'installed_plugin_addon_available', $current_installed );
				}
			}
		}

		/**
		 * Fires when the upgrader has successfully overwritten a currently installed
		 * plugin or theme with an uploaded zip package.
		 *
		 * @param string $package          The package file.
		 * @param array  $new_plugin_data  The new plugin data.
		 * @param string $package_type     The package type (plugin or theme).
		 *
		 * @since 4.5.0
		 */
		public static function on_package_overwrite( $package, $new_plugin_data, $package_type ) {
			if ( 'plugin' !== $package_type ) {
				return;
			}

			if ( is_array( $new_plugin_data ) && isset( $new_plugin_data['TextDomain'] ) && 'wp-security-audit-log' === $new_plugin_data['TextDomain'] ) {
				/**
				 * Out own plugin gets updated, unfortunately we have no idea (and most probably that is the old version of our plugin  ) what is the state of the plugin and what has been changed in the new version - check code reference here - https://developer.wordpress.org/reference/hooks/upgrader_process_complete/ especially the part that stays:
				 * Use with caution: When you use the upgrader_process_complete action hook in your plugin and your plugin is the one which under upgrade, then this action will run the old version of your plugin.
				 * Yes - that is not upgrader_overwrote_package but same applies to it im afraid
				 */

				return;
			}

			if ( array_key_exists( 'Name', $new_plugin_data ) ) {
				$plugin_file = self::get_plugin_file_name( $new_plugin_data['Name'] );
				if ( ! empty( $plugin_file ) ) {
					if ( ! \is_wp_error( \validate_plugin( $plugin_file ) ) ) {
						self::log_plugin_updated_event( $plugin_file );
					}
				}
			}
		}

		/**
		 * Log plugin updated event.
		 *
		 * @param string $plugin_file Relative path to the plugin filename.
		 * @param array  $old_plugins (Optional) Array of old plugins which we can use for comparison.
		 *
		 * @since 4.5.0
		 */
		public static function log_plugin_updated_event( $plugin_file, $old_plugins = '' ) {
			if ( ! \is_wp_error( \validate_plugin( $plugin_file ) ) ) {
				$plugin_file_full = WP_PLUGIN_DIR . '/' . $plugin_file;
				$plugin_data      = get_plugin_data( $plugin_file_full, false, true );

				$old_version = ( isset( $old_plugins[ $plugin_file ] ) ) ? $old_plugins[ $plugin_file ]['Version'] : false;
				$new_version = $plugin_data['Version'];

				if ( $old_version !== $new_version ) {
					Alert_Manager::trigger_event(
						5004,
						array(
							'PluginFile' => $plugin_file,
							'PluginData' => (object) array(
								'Name'      => $plugin_data['Name'],
								'PluginURI' => $plugin_data['PluginURI'],
								'Version'   => $new_version,
								'Author'    => $plugin_data['Author'],
								'Network'   => $plugin_data['Network'] ? 'True' : 'False',
							),
							'OldVersion' => $old_version,
						)
					);
				}
			}
		}

		/**
		 * Log theme updated event.
		 *
		 * @param string $theme_name Theme name.
		 *
		 * @since 4.5.0
		 */
		public static function log_theme_updated_event( $theme_name ) {
			$theme = self::get_theme_by_name( $theme_name );
			if ( ! $theme instanceof \WP_Theme ) {
				return;
			}

			Alert_Manager::trigger_event(
				5031,
				array(
					'Theme' => (object) array(
						'Name'                   => $theme->Name, // phpcs:ignore
						'ThemeURI'               => $theme->ThemeURI, // phpcs:ignore
						'Description'            => $theme->Description, // phpcs:ignore
						'Author'                 => $theme->Author, // phpcs:ignore
						'Version'                => $theme->Version, // phpcs:ignore
						'get_template_directory' => $theme->get_template_directory(),
					),
				)
			);
		}

		/**
		 * Plugin creates/modifies posts.
		 *
		 * @param int    $post_id - Post ID.
		 * @param object $post - Post object.
		 *
		 * @since 4.5.0
		 */
		public static function plugin_created_post( $post_id, $post ) {
			if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
				return;
			}

			// Ignore if the request is coming from post editor.
			if ( isset( $_REQUEST['_wp_http_referer'] ) ) {
				$referrer   = esc_url_raw( wp_unslash( $_REQUEST['_wp_http_referer'] ) );
				$parsed_url = wp_parse_url( $referrer );

				if ( isset( $parsed_url['path'] ) && 'post' === basename( $parsed_url['path'], '.php' ) ) {
					return;
				}
			}

			// Filter $_REQUEST array for security.
			$get_array  = filter_input_array( INPUT_GET );
			$post_array = filter_input_array( INPUT_POST );

			$wp_actions = array( 'editpost', 'heartbeat', 'inline-save', 'trash', 'untrash', 'vc_save' );
			if ( isset( $get_array['action'] ) && ! in_array( $get_array['action'], $wp_actions, true ) ) {
				if (
				! in_array( $post->post_type, Alert_Manager::get_ignored_post_types(), true )
				&& ! empty( $post->post_title )
				) {
					// Get post editor link.
					$editor_link = self::get_editor_link( $post );

					// If the plugin modify the post.
					if ( false !== strpos( $get_array['action'], 'edit' ) ) {
						Alert_Manager::trigger_event(
							2106,
							array(
								'PostID'             => $post->ID,
								'PostType'           => $post->post_type,
								'PostTitle'          => $post->post_title,
								'PostStatus'         => $post->post_status,
								'PostUrl'            => get_permalink( $post->ID ),
								$editor_link['name'] => $editor_link['value'],
							)
						);
					} else {
						$plugin_name = isset( $get_array['plugin'] ) ? $get_array['plugin'] : false;
						if ( ! \is_wp_error( \validate_plugin( $plugin_name ) ) ) {

							$plugin_data = $plugin_name ? get_plugin_data( trailingslashit( WP_PLUGIN_DIR ) . $plugin_name ) : false;

							Alert_Manager::trigger_event(
								5019,
								array(
									'PluginName'         => ( $plugin_data && isset( $plugin_data['Name'] ) ) ? $plugin_data['Name'] : false,
									'PostID'             => $post->ID,
									'PostType'           => $post->post_type,
									'PostTitle'          => $post->post_title,
									'PostStatus'         => $post->post_status,
									'Username'           => 'Plugins',
									$editor_link['name'] => $editor_link['value'],
								)
							);
						}
					}
				}
			}

			if ( isset( $post_array['action'] ) && ! in_array( $post_array['action'], $wp_actions, true ) ) {
				if (
				! in_array( $post->post_type, Alert_Manager::get_ignored_post_types(), true )
				&& ! empty( $post->post_title )
				) {
					// If the plugin modify the post.
					if ( false !== strpos( $post_array['action'], 'edit' ) ) {
						$editor_link = self::get_editor_link( $post );
						Alert_Manager::trigger_event(
							2106,
							array(
								'PostID'             => $post->ID,
								'PostType'           => $post->post_type,
								'PostTitle'          => $post->post_title,
								$editor_link['name'] => $editor_link['value'],
							)
						);
					} elseif (
					( isset( $post_array['page'] ) && 'woocommerce-bulk-stock-management' === $post_array['page'] ) // If page index is set in post array then ignore.
					|| (
						isset( $post_array['mainwpsignature'] )
						&& ( 'restore' === $post_array['action'] || 'unpublish' === $post_array['action'] || 'publish' === $post_array['action'] )
					) // OR If the request is coming from MainWP then ignore.
					) {
						// Ignore WooCommerce Bulk Stock Management page.
						// OR MainWP plugin requests.
					} else {
						$plugin_name = isset( $get_array['plugin'] ) ? $get_array['plugin'] : false;
						if ( ! \is_wp_error( \validate_plugin( $plugin_name ) ) ) {
							$plugin_data = $plugin_name ? get_plugin_data( trailingslashit( WP_PLUGIN_DIR ) . $plugin_name ) : false;
							$editor_link = self::get_editor_link( $post );
							Alert_Manager::trigger_event(
								5019,
								array(
									'PluginName'         => ( $plugin_data && isset( $plugin_data['Name'] ) ) ? $plugin_data['Name'] : false,
									'PostID'             => $post->ID,
									'PostType'           => $post->post_type,
									'PostTitle'          => $post->post_title,
									'PostStatus'         => $post->post_status,
									'Username'           => 'Plugins',
									$editor_link['name'] => $editor_link['value'],
								)
							);
						}
					}
				}
			}
		}

		/**
		 * Get editor link.
		 *
		 * @param object $post - The post object.
		 * @return array $editor_link name and value link.
		 *
		 * @since 4.5.0
		 */
		private static function get_editor_link( $post ) {
			$name        = 'EditorLink';
			$name       .= ( 'page' === $post->post_type ) ? 'Page' : 'Post';
			$value       = get_edit_post_link( $post->ID );
			$editor_link = array(
				'name'  => $name,
				'value' => $value,
			);

			return $editor_link;
		}
	}
}
