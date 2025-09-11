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

use WSAL\Controllers\Alert_Manager;
use WSAL\WP_Sensors\Helpers\WP_Plugins_Themes_Helper;

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
	 * 5030 Plugin failed to update
	 * 5031 User updated a theme
	 * 5034 Updated translations for a plugin
	 * 5035 Updated translations for a theme
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
		private static $old_themes = null;

		/**
		 * List of Plugins.
		 *
		 * @var array
		 *
		 * @since 4.5.0
		 */
		private static $old_plugins = null;

		/**
		 * Litst of all plugins
		 *
		 * @var array
		 *
		 * @since 5.3.4.1
		 */
		private static $plugins_available = null;

		/**
		 * Stores the plugins data for the currently processed plugin
		 *
		 * @var array
		 *
		 * @since 5.3.0
		 */
		private static $plugins_data = array();

		/**
		 * Stores the plugins data for the currently processed plugin
		 *
		 * @var array
		 *
		 * @since 5.4.0
		 */
		private static $reported_plugins = array();


		/**
		 * Inits the main hooks
		 *
		 * @return void
		 *
		 * @since 4.5.0
		 */
		public static function init() {
			$has_permission = ( current_user_can( 'install_plugins' ) || current_user_can( 'activate_plugins' ) ||
				\current_user_can( 'delete_plugins' ) || current_user_can( 'update_plugins' ) || current_user_can( 'install_themes' ) );

			self::event_init();

			\add_action( 'switch_theme', array( __CLASS__, 'event_theme_activated' ) );

			\add_action( 'deleted_theme', array( __CLASS__, 'on_deleted_theme' ), 10, 2 );

			\add_action( 'wp_insert_post', array( __CLASS__, 'plugin_created_post' ), 10, 2 );

			// Log plugin deletions, i.e. when a user click "Delete" in the plugins listing
			// or choose plugin(s) and select Bulk actions -> Delete.
			// Since WordPress 4.4 filters exists that are fired before and after plugin deletion.
			\add_action( 'delete_plugin', array( __CLASS__, 'on_action_delete_plugin' ), 10, 1 );
			\add_action( 'deleted_plugin', array( __CLASS__, 'on_action_deleted_plugin' ), 10, 2 );

			/**
			 * At least the plugin bulk upgrades fires this action before upgrade
			 * We use it to fetch the current version of all plugins, before they are upgraded
			 */
			// \add_filter( 'upgrader_pre_install', array( __CLASS__, 'save_versions_before_update' ), 10, 2 );

			// Fires after the upgrades has done it's thing.
			// Check hook extra for upgrader initiator.
			\add_action( 'upgrader_process_complete', array( __CLASS__, 'on_upgrader_process_complete' ), 10, 2 );
			\add_action( 'upgrader_overwrote_package', array( __CLASS__, 'on_package_overwrite' ), 10, 3 );

			\add_action( 'activated_plugin', array( __CLASS__, 'on_activated_plugin' ), 10, 2 );
			\add_action( 'deactivated_plugin', array( __CLASS__, 'on_deactivated_plugin' ), 10, 1 );

			\add_action( 'update_option_active_plugins', array( __CLASS__, 'on_active_plugins_update' ), 10, 2 );

			\add_action( 'upgrader_process_complete', array( __CLASS__, 'on_plugin_or_theme_update' ), 10, 2 );

			\add_filter( 'upgrader_post_install', array( __CLASS__, 'on_upgrader_post_install' ), 10, 3 );
		}

		/**
		 * Maybe add thickbox class to the links, this is used by WordPress to trigger the view more information modal.
		 *
		 * @param string $url - the URL of the link to check.
		 *
		 * @return array $extra_css_classes - An array of extra CSS classes to be added to the link.
		 *
		 * @since 5.5.0
		 */
		public static function maybe_add_thickbox_class( $url ) {

			$extra_css_classes = array();

			$is_wp_repo_url = strpos( $url, 'plugin-install.php' );

			if ( $is_wp_repo_url ) {
				// Add thickbox class to the links, this is used by WordPress to trigger the view more information modal.
				$extra_css_classes[] = 'thickbox';
			}

			return $extra_css_classes;
		}

		/**
		 * Build the WordPress repository URL for a plugin.
		 *
		 * @param string $plugin_slug - The plugin slug which is also the directory name of the plugin.
		 *
		 * @return string - The URL to the plugin's page in the WordPress repository.
		 *
		 * @since 5.5.0
		 */
		public static function get_plugin_wp_repo_url( $plugin_slug ) {
			// Get correct url, network_admin_url also checks if we're in a multisite or not.
			return network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $plugin_slug . '&TB_iframe=true&width=640&height=600' );
		}

		/**
		 * Plugin is deactivated
		 * plugin_name is like admin-menu-tree-page-view/index.php
		 *
		 * @param string $plugin_name Plugin name.
		 *
		 * @since 5.4.0
		 */
		public static function on_deactivated_plugin( $plugin_name ) {
			if ( ! in_array( $plugin_name, self::$reported_plugins, true ) ) {
				$plugin_data = \get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_name, true, false );
				$plugin_slug = dirname( $plugin_name );

				Alert_Manager::trigger_event(
					5002,
					array(
						'PluginFile' => WP_PLUGIN_DIR . '/' . $plugin_name,
						'PluginData' => (object) array(
							'Name'          => $plugin_data['Name'],
							'PluginURI'     => $plugin_data['PluginURI'],
							'PluginRepoUrl' => self::get_plugin_wp_repo_url( $plugin_slug ),
							'Version'       => $plugin_data['Version'],
							'Author'        => $plugin_data['Author'],
							'Network'       => $plugin_data['Network'] ? 'True' : 'False',
						),
					)
				);

				self::$reported_plugins[ $plugin_name ] = $plugin_name;
			}
		}

		/**
		 * Plugin is activated
		 * plugin_name is like admin-menu-tree-page-view/index.php
		 *
		 * @param string $plugin_name Plugin name.
		 * @param bool   $network_wide Network wide.
		 */
		public static function on_activated_plugin( $plugin_name, $network_wide = null ) {
			if ( ! in_array( $plugin_name, self::$reported_plugins, true ) ) {
				$plugin_data = \get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_name, true, false );

				$plugin_slug = dirname( $plugin_name );

				Alert_Manager::trigger_event(
					5001,
					array(
						'PluginFile' => WP_PLUGIN_DIR . '/' . $plugin_name,
						'PluginData' => (object) array(
							'Name'          => $plugin_data['Name'],
							'PluginRepoUrl' => self::get_plugin_wp_repo_url( $plugin_slug ),
							'PluginURI'     => $plugin_data['PluginURI'],
							'Version'       => $plugin_data['Version'],
							'Author'        => $plugin_data['Author'],
							'Network'       => $plugin_data['Network'] ? 'True' : 'False',
						),
					)
				);

				self::$reported_plugins[ $plugin_name ] = $plugin_name;
			}
		}

		/**
		 * Called when plugins is updated or installed
		 * Called from class-wp-upgrader.php
		 *
		 * @param \Plugin_Upgrader $plugin_upgrader_instance Plugin_Upgrader instance. In other contexts, $this, might
		 *                                                  be a Theme_Upgrader or Core_Upgrade instance.
		 * @param array            $arr_data                 Array of bulk item update data.
		 *
		 * @return void
		 *
		 * @since 5.3.4.1
		 */
		public static function on_upgrader_process_complete( $plugin_upgrader_instance, $arr_data ): void {
			if ( empty( $plugin_upgrader_instance ) || empty( $arr_data ) ) {
				return;
			}

			// Prevent events 5000 and 5005 from being triggered multiple times, triggered in this class on on_upgrader_post_install() before this method.
			if ( Alert_Manager::has_triggered( 5000 ) || Alert_Manager::has_triggered( 5005 ) ) {
				return;
			}

			// Check that required data is set.
			if ( empty( $arr_data['type'] ) || empty( $arr_data['action'] ) ) {
				return;
			}

			if ( isset( $arr_data['type'] ) && 'plugin' === $arr_data['type'] ) {
				self::single_plugin_install( $plugin_upgrader_instance, $arr_data );
				self::single_plugin_update( $plugin_upgrader_instance, $arr_data );
				self::bulk_plugin_update( $plugin_upgrader_instance, $arr_data );
			}

			if ( isset( $arr_data['type'] ) && 'theme' === $arr_data['type'] ) {
				self::theme_install( $plugin_upgrader_instance, $arr_data );
				self::theme_upgrade( $plugin_upgrader_instance, $arr_data );
			}
		}

		/**
		 * Capture event when single plugin is installed
		 *
		 * @param \Plugin_Upgrader $upgrader_instance Plugin_Upgrader instance. In other contexts, $this, might
		 *                                                  be a Theme_Upgrader or Core_Upgrade instance.
		 * @param array            $arr_data                 Array of bulk item update data.
		 *
		 * @return void
		 *
		 * @since 5.3.4.1
		 */
		public static function theme_install( $upgrader_instance, $arr_data ): void {

			// Must be type 'theme' and action 'install'.
			if ( 'theme' !== $arr_data['type'] || 'install' !== $arr_data['action'] ) {
				return;
			}

			if ( empty( $upgrader_instance->new_theme_data ) ) {
				return;
			}

			// Install theme.
			$destination_name = $upgrader_instance->result['destination_name'] ?? '';

			if ( empty( $destination_name ) ) {
				return;
			}

			$theme = \wp_get_theme( $destination_name );

			if ( ! $theme->exists() ) {
				return;
			}

			$new_theme_data = $upgrader_instance->new_theme_data;

			Alert_Manager::trigger_event(
				5005,
				array(
					'Theme' => (object) array(
						'Name'                   => $new_theme_data['Name'],
						'ThemeURI'               => $theme->ThemeURI, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						'Description'            => $theme->get( 'Description' ),
						'Author'                 => $new_theme_data['Author'],
						'Version'                => $new_theme_data['Version'],
						'get_template_directory' => $theme->get_template_directory(),
					),
				)
			);
		}

		/**
		 * Capture event when single plugin is installed
		 *
		 * @param \Plugin_Upgrader $upgrader_instance Plugin_Upgrader instance. In other contexts, $this, might
		 *                                                  be a Theme_Upgrader or Core_Upgrade instance.
		 * @param array            $arr_data                 Array of bulk item update data.
		 *
		 * @return void
		 *
		 * @since 5.3.4.1
		 */
		public static function theme_upgrade( $upgrader_instance, $arr_data ): void {

			// Must be type 'theme' and action 'update'.
			if ( 'theme' !== $arr_data['type'] || 'update' !== $arr_data['action'] ) {
				return;
			}

			// If single install make an array so it look like bulk and we can use same code.
			if ( isset( $arr_data['bulk'] ) && $arr_data['bulk'] && isset( $arr_data['themes'] ) ) {
				$arr_themes = (array) $arr_data['themes'];
			} else {
				$arr_themes = array(
					$arr_data['theme'],
				);
			}

			foreach ( $arr_themes as $one_updated_theme ) {
				$theme = \wp_get_theme( $one_updated_theme );

				if ( ! is_a( $theme, 'WP_Theme' ) ) {
					continue;
				}

				$theme_name    = $theme->get( 'Name' );
				$theme_version = $theme->get( 'Version' );

				if ( ! $theme_name || ! $theme_version ) {
					continue;
				}

				Alert_Manager::trigger_event(
					5031,
					array(
						'Theme' => (object) array(
							'Name'                   => $theme_name,
							'ThemeURI'               => $theme->ThemeURI, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
							'Description'            => $theme->Description, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
							'Author'                 => $theme->Author, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
							'Version'                => $theme_version,
							'get_template_directory' => $theme->get_template_directory(),
						),
					)
				);
			}
		}

		/**
		 * Capture event when single plugin is installed
		 *
		 * @param \Plugin_Upgrader $plugin_upgrader_instance Plugin_Upgrader instance. In other contexts, $this, might
		 *                                                  be a Theme_Upgrader or Core_Upgrade instance.
		 * @param array            $arr_data                 Array of bulk item update data.
		 *
		 * @return void
		 *
		 * @since 5.3.4.1
		 */
		public static function single_plugin_install( $plugin_upgrader_instance, $arr_data ): void {
			// Bail if not single plugin install data.
			if ( ! isset( $arr_data['action'] ) || 'install' !== $arr_data['action'] || $plugin_upgrader_instance->bulk ) {
				return;
			}

			$upgrader_skin_options = isset( $plugin_upgrader_instance->skin->options ) && is_array( $plugin_upgrader_instance->skin->options ) ? $plugin_upgrader_instance->skin->options : array();
			$upgrader_skin_result  = isset( $plugin_upgrader_instance->skin->result ) && is_array( $plugin_upgrader_instance->skin->result ) ? $plugin_upgrader_instance->skin->result : array();
			$new_plugin_data       = $plugin_upgrader_instance->new_plugin_data ?? array();
			$plugin_slug           = $upgrader_skin_result['destination_name'] ?? '';

			$plugin = $plugin_upgrader_instance->plugin_info();

			$context = array(
				'plugin_slug'         => $plugin_slug,
				'plugin_name'         => $new_plugin_data['Name'] ?? '',
				'plugin_title'        => $new_plugin_data['Title'] ?? '',
				'plugin_url'          => $new_plugin_data['PluginURI'] ?? '',
				'plugin_version'      => $new_plugin_data['Version'] ?? '',
				'plugin_author'       => $new_plugin_data['Author'] ?? '',
				'plugin_requires_wp'  => $new_plugin_data['RequiresWP'] ?? '',
				'plugin_requires_php' => $new_plugin_data['RequiresPHP'] ?? '',
				// Prevent warning Undefined array key "Network" in an edge case when an invalid plugin.zip is uploaded to the site.
				'plugin_network'      => ( $plugin_data['Network'] ?? false ) ? 'True' : 'False',
				'plugin_path'         => WP_PLUGIN_DIR . \DIRECTORY_SEPARATOR . $plugin,
			);

			if ( isset( $new_plugin_data['UpdateURI'] ) ) {
				$context['plugin_update_uri'] = $new_plugin_data['UpdateURI'];
			}

			// Check for premium version being installed / updated.
			if ( in_array( $plugin, array( \WpSecurityAuditLog::PREMIUM_VERSION_WHOLE_PLUGIN_NAME, \WpSecurityAuditLog::FREE_VERSION_WHOLE_PLUGIN_NAME, \WpSecurityAuditLog::NOFS_VERSION_WHOLE_PLUGIN_NAME ), true ) ) {

				return;
			}

			$install_source = 'web';
			if ( isset( $upgrader_skin_options['type'] ) ) {
				$install_source = \strtolower( (string) $upgrader_skin_options['type'] );
			}

			$context['plugin_install_source'] = $install_source;

			// If uploaded plugin store name of ZIP.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( 'upload' === $install_source && isset( $_FILES['pluginzip']['name'] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$plugin_upload_name            = sanitize_text_field( $_FILES['pluginzip']['name'] );
				$context['plugin_upload_name'] = $plugin_upload_name;
			}

			if ( ! is_a( $plugin_upgrader_instance->skin->result, 'WP_Error' ) ) {

				// Check if the plugin is already installed and we are here because of an update via upload.
				$old_plugins = self::get_old_plugins();

				$plugin_slug = dirname( $plugin );

				if ( isset( $old_plugins[ $plugin ] ) ) {
					return;
				} else {
					// No - it is a real update - no need to fire post activate event.
					\remove_action( 'upgrader_overwrote_package', array( __CLASS__, 'on_package_overwrite' ), 10, 3 );
				}

				Alert_Manager::trigger_event(
					5000,
					array(
						'PluginData' => (object) array(
							'Name'            => $context['plugin_name'],
							'PluginURI'       => $context['plugin_url'],
							'PluginRepoUrl'   => self::get_plugin_wp_repo_url( $plugin_slug ),
							'Version'         => $context['plugin_version'],
							'Author'          => $context['plugin_author'],
							'Network'         => $context['plugin_network'],
							'Slug'            => $context['plugin_slug'],
							'Title'           => $context['plugin_title'],
							'plugin_dir_path' => $context['plugin_path'],
						),
					)
				);
			}
		}

		/**
		 * Capture event when single plugin is updated
		 *
		 * @param \Plugin_Upgrader $plugin_upgrader_instance Plugin_Upgrader instance. In other contexts, $this, might
		 *                                                  be a Theme_Upgrader or Core_Upgrade instance.
		 * @param array            $arr_data                 Array of bulk item update data.
		 *
		 * @return void
		 *
		 * @since 5.3.4.1
		 */
		public static function single_plugin_update( $plugin_upgrader_instance, $arr_data ): void {
			if ( ! isset( $arr_data['action'] ) || 'update' !== $arr_data['action'] || $plugin_upgrader_instance->bulk ) {
				return;
			}

			if ( is_array( $arr_data['plugin'] ) && isset( $arr_data['plugin'] ) && 'wp-security-audit-log.php' === $arr_data['plugin'] ) {

				return;
			}

			// No plugin info in instance, so get it ourself.
			$plugin_data = array();
			if ( file_exists( WP_PLUGIN_DIR . '/' . $arr_data['plugin'] ) ) {
				$plugin_data = \get_plugin_data( WP_PLUGIN_DIR . '/' . $arr_data['plugin'], true, false );
			}

			$plugin_slug = dirname( $arr_data['plugin'] );

			$context = array(
				'plugin_slug'        => $plugin_slug,
				'plugin_name'        => $plugin_data['Name'] ?? '',
				'plugin_title'       => $plugin_data['Title'] ?? '',
				'plugin_description' => $plugin_data['Description'] ?? '',
				'plugin_author'      => $plugin_data['Author'] ?? '',
				'plugin_version'     => $plugin_data['Version'] ?? '',
				'plugin_url'         => $plugin_data['PluginURI'] ?? '',
				'plugin_network'     => ( $plugin_data['Network'] ) ? 'True' : 'False',
				'plugin_path'        => \WP_PLUGIN_DIR . \DIRECTORY_SEPARATOR . $arr_data['plugin'],
			);

			// Add Update URI if it is set. Available since WP 5.8.
			if ( isset( $plugin_data['UpdateURI'] ) ) {
				$context['plugin_update_uri'] = $plugin_data['UpdateURI'];
			}

			if ( ! \is_wp_error( \validate_plugin( $arr_data['plugin'] ) ) ) {

				$old_plugins = self::get_old_plugins();

				$old_version = ( isset( $old_plugins[ $arr_data['plugin'] ] ) ) ? $old_plugins[ $arr_data['plugin'] ]['Version'] : false;

				if ( $old_version !== $context['plugin_version'] ) {
					Alert_Manager::trigger_event(
						5004,
						array(
							'PluginFile' => $arr_data['plugin'],
							'PluginData' => (object) array(
								'Name'            => $context['plugin_name'],
								'PluginURI'       => $context['plugin_url'],
								'PluginRepoUrl'   => self::get_plugin_wp_repo_url( $plugin_slug ),
								'Version'         => $context['plugin_version'],
								'Author'          => $context['plugin_author'],
								'Network'         => $context['plugin_network'],
								'Slug'            => $context['plugin_slug'],
								'Title'           => $context['plugin_title'],
								'plugin_dir_path' => $context['plugin_path'],
							),
							'OldVersion' => $old_version,
						)
					);
				}
			}
		}

		/**
		 * Capture event when single plugin is updated
		 *
		 * @param \Plugin_Upgrader $plugin_upgrader_instance Plugin_Upgrader instance. In other contexts, $this, might
		 *                                                  be a Theme_Upgrader or Core_Upgrade instance.
		 * @param array            $arr_data                 Array of bulk item update data.
		 *
		 * @return void
		 *
		 * @since 5.3.4.1
		 */
		public static function bulk_plugin_update( $plugin_upgrader_instance, $arr_data ): void {
			// Bail if not bulk plugin update.
			if ( ! isset( $arr_data['bulk'] ) || ! $arr_data['bulk'] || ! isset( $arr_data['action'] ) || 'update' !== $arr_data['action'] ) {
				return;
			}

			$plugins_updated = isset( $arr_data['plugins'] ) ? (array) $arr_data['plugins'] : array();

			foreach ( $plugins_updated as $plugin ) {

				$arr_data['plugin'] = $plugin;

				if ( is_array( $arr_data['plugin'] ) && isset( $arr_data['plugin'] ) && 'wp-security-audit-log.php' === $arr_data['plugin'] ) {

					return;
				}

				// No plugin info in instance, so get it ourself.
				$plugin_data = array();
				if ( file_exists( WP_PLUGIN_DIR . '/' . $arr_data['plugin'] ) ) {
					$plugin_data = \get_plugin_data( WP_PLUGIN_DIR . '/' . $arr_data['plugin'], true, false );
				}

				$plugin_slug = dirname( $arr_data['plugin'] );

				$context = array(
					'plugin_slug'        => $plugin_slug,
					'plugin_name'        => $plugin_data['Name'] ?? '',
					'plugin_title'       => $plugin_data['Title'] ?? '',
					'plugin_description' => $plugin_data['Description'] ?? '',
					'plugin_author'      => $plugin_data['Author'] ?? '',
					'plugin_version'     => $plugin_data['Version'] ?? '',
					'plugin_url'         => $plugin_data['PluginURI'] ?? '',
					'plugin_network'     => ( $plugin_data['Network'] ) ? 'True' : 'False',
					'plugin_path'        => \WP_PLUGIN_DIR . \DIRECTORY_SEPARATOR . $arr_data['plugin'],
				);

				// Add Update URI if it is set. Available since WP 5.8.
				if ( isset( $plugin_data['UpdateURI'] ) ) {
					$context['plugin_update_uri'] = $plugin_data['UpdateURI'];
				}

				if ( ! \is_wp_error( \validate_plugin( $arr_data['plugin'], ) ) ) {

					$old_plugins = self::get_old_plugins();

					$old_version = ( isset( $old_plugins[ $arr_data['plugin'] ] ) ) ? $old_plugins[ $arr_data['plugin'] ]['Version'] : false;

					if ( $old_version !== $context['plugin_version'] ) {
						Alert_Manager::trigger_event(
							5004,
							array(
								'PluginFile' => $arr_data['plugin'],
								'PluginData' => (object) array(
									'Name'            => $context['plugin_name'],
									'PluginURI'       => $context['plugin_url'],
									'PluginRepoUrl'   => self::get_plugin_wp_repo_url( $plugin_slug ),
									'Version'         => $context['plugin_version'],
									'Author'          => $context['plugin_author'],
									'Network'         => $context['plugin_network'],
									'Slug'            => $context['plugin_slug'],
									'Title'           => $context['plugin_title'],
									'plugin_dir_path' => $context['plugin_path'],
								),
								'OldVersion' => $old_version,
							)
						);
					}
				}
			}
		}

		/**
		 * Fires when the upgrader has successfully overwritten a currently installed
		 * plugin or theme with an uploaded zip package.
		 *
		 * @param string $package          The package file.
		 * @param array  $plugin_data  The new plugin data.
		 * @param string $package_type     The package type (plugin or theme).
		 *
		 * @since 4.5.0
		 */
		public static function on_package_overwrite( $package, $plugin_data, $package_type ) {
			if ( 'plugin' !== $package_type ) {
				return;
			}

			if ( is_array( $plugin_data ) && isset( $plugin_data['TextDomain'] ) && 'wp-security-audit-log' === $plugin_data['TextDomain'] ) {

				return;
			}

			$plugin_slug = self::extract_plugin_filename( $plugin_data['TextDomain'] );

			$context = array(
				'plugin_slug'        => $plugin_slug,
				'plugin_name'        => $plugin_data['Name'],
				'plugin_title'       => $plugin_data['Title'],
				'plugin_description' => $plugin_data['Description'],
				'plugin_author'      => $plugin_data['Author'],
				'plugin_version'     => $plugin_data['Version'],
				'plugin_url'         => $plugin_data['PluginURI'],
				'plugin_network'     => ( $plugin_data['Network'] ) ? 'True' : 'False',
				'plugin_path'        => \WP_PLUGIN_DIR . \DIRECTORY_SEPARATOR . $plugin_slug,
			);

			if ( ! \is_wp_error( \validate_plugin( $plugin_slug ) ) ) {

				$old_plugins = self::get_old_plugins();

				$old_version = ( isset( $old_plugins[ $plugin_slug ] ) ) ? $old_plugins[ $plugin_slug ]['Version'] : false;

				if ( $old_version !== $context['plugin_version'] ) {
					Alert_Manager::trigger_event(
						5004,
						array(
							'PluginFile' => $plugin_slug,
							'PluginData' => (object) array(
								'Name'            => $context['plugin_name'],
								'PluginURI'       => $context['plugin_url'],
								'PluginRepoUrl'   => self::get_plugin_wp_repo_url( $plugin_slug ),
								'Version'         => $context['plugin_version'],
								'Author'          => $context['plugin_author'],
								'Network'         => $context['plugin_network'],
								'Slug'            => $context['plugin_slug'],
								'Title'           => $context['plugin_title'],
								'plugin_dir_path' => $context['plugin_path'],
							),
							'OldVersion' => $old_version,
						)
					);
				}
			}
		}

		/**
		 * Extracts plugin filename from the plugin text domain.
		 *
		 * @param string $plugin_textdomain - The text domain of the plugin.
		 *
		 * @return string
		 *
		 * @since 5.3.4.1
		 */
		public static function extract_plugin_filename( $plugin_textdomain ): string {
			foreach ( self::get_all_plugins() as $plugin_file => $plugin ) {
				if ( $plugin['TextDomain'] === $plugin_textdomain ) {
					return $plugin_file;
				}
			}

			return '';
		}

		/**
		 * Store information about a plugin before it gets deleted.
		 * Called from action `deleted_plugin` that is fired just before the plugin will be deleted.
		 *
		 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
		 *
		 * @return void
		 *
		 * @since 5.3.0
		 */
		public static function on_action_delete_plugin( $plugin_file ) {
			self::$plugins_data[ $plugin_file ] = \get_plugin_data( \WP_PLUGIN_DIR . '/' . $plugin_file, true, false );
		}


		/**
		 * Log plugin deletion.
		 * Called from action `deleted_plugin` that is fired just after a plugin has been deleted.
		 *
		 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
		 * @param bool   $deleted     Whether the plugin deletion was successful.
		 *
		 * @return void
		 *
		 * @since 5.3.0
		 */
		public static function on_action_deleted_plugin( $plugin_file, $deleted ) {
			if ( ! $deleted ) {
				return;
			}

			if ( empty( self::$plugins_data[ $plugin_file ] ) ) {
				return;
			}

			$plugin_file = $plugin_file;
			$plugin_name = \strip_tags( self::$plugins_data[ $plugin_file ]['Title'] );
			$plugin_data = self::$plugins_data[ $plugin_file ];

			$plugin_slug = dirname( $plugin_file );

			Alert_Manager::trigger_event(
				5003,
				array(
					'PluginFile' => $plugin_file,
					'PluginData' => (object) array(
						'Name'          => $plugin_name,
						'Version'       => $plugin_data['Version'],
						'PluginRepoUrl' => self::get_plugin_wp_repo_url( $plugin_slug ),
					),
				)
			);
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

			if ( ! array_key_exists( $stylesheet, self::get_old_themes() ) ) {
				return;
			}

			$theme = self::get_old_themes()[ $stylesheet ];
			Alert_Manager::trigger_event(
				5007,
				array(
					'Theme' => (object) array(
						'Name'                   => $theme->Name, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						'ThemeURI'               => $theme->ThemeURI, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						'Description'            => $theme->Description, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						'Author'                 => $theme->Author, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						'Version'                => $theme->Version, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
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
		public static function event_init() {
			self::get_old_themes();
			self::get_old_plugins();
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
				if ( $theme_name === $item->Name ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					$theme = $item;
					break;
				}
			}
			if ( null === $theme ) {
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
						'Name'                   => $theme->Name, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						'ThemeURI'               => $theme->ThemeURI, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						'Description'            => $theme->Description, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						'Author'                 => $theme->Author, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						'Version'                => $theme->Version, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
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
							$plugin_slug = dirname( $plugin_name );

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
									'PluginData'         => (object) array(
										'PluginRepoUrl' => self::get_plugin_wp_repo_url( $plugin_slug ),
									),
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

							$plugin_slug = dirname( $plugin_name );

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
									'PluginData'         => (object) array(
										'PluginRepoUrl' => self::get_plugin_wp_repo_url( $plugin_slug ),
									),
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

		/**
		 * Get old themes - That must be done BEFORE themes changes apply globally. It stores "image" the themes before changes.
		 *
		 * @return array
		 *
		 * @since 5.3.4.1
		 */
		private static function get_old_themes(): array {
			if ( null === self::$old_themes ) {
				self::$old_themes = \wp_get_themes();
			}

			return self::$old_themes;
		}

		/**
		 * Get old plugins. That must apply before plugins changes apply globally. It stores "image" the plugins before changes.
		 *
		 * @return array
		 *
		 * @since 5.3.4.1
		 */
		private static function get_old_plugins(): array {
			if ( null === self::$old_plugins ) {
				self::$old_plugins = \get_plugins();
			}

			return self::$old_plugins;
		}

		/**
		 * To prevent $silent flag set to true in the WP core we are using this method.
		 *
		 * @param array $old_value - Old value with active plugins.
		 * @param array $new_value - New value with active plugins.
		 *
		 * @return void
		 *
		 * @since 5.4.0
		 */
		public static function on_active_plugins_update( $old_value, $new_value ) {
			if ( \is_array( $new_value ) && \is_array( $old_value ) ) {
				foreach ( $new_value as $plugin ) {
					if ( ! in_array( $plugin, self::$reported_plugins, true ) ) {
						// Plugin is not reported neither as active nor as deactivated.
						// Check if that plugin exists in the old value. If it does, it means that the plugin was deactivated.
						if ( ! in_array( $plugin, $old_value, true ) ) {
							// Plugin was activated.
							self::on_activated_plugin( $plugin );

							self::$reported_plugins[ $plugin ] = $plugin;
						}
					}
				}

				foreach ( $old_value as $plugin ) {
					if ( ! in_array( $plugin, self::$reported_plugins, true ) ) {
						// Plugin is not reported neither as active nor as deactivated.
						// Check if that plugin exists in the old value. If it does, it means that the plugin was deactivated.
						if ( ! in_array( $plugin, $new_value, true ) ) {
							// Plugin was deactivated.
							self::on_deactivated_plugin( $plugin );
							self::$reported_plugins[ $plugin ] = $plugin;
						}
					}
				}
			}
		}

		/**
		 * Get all plugins.
		 *
		 * @return array
		 *
		 * @since 5.3.4.1
		 */
		private static function get_all_plugins(): array {
			if ( null === self::$plugins_available ) {
				self::$plugins_available = \get_plugins();
			}

			return self::$plugins_available;
		}

		/**
		 * Notify when a plugin translation is updated.
		 *
		 * @param \WP_Upgrader|\Language_Pack_Upgrader $upgrader \WP_Upgrader instance. In other contexts this might be a Theme_Upgrader, Plugin_Upgrader, Core_Upgrade, or Language_Pack_Upgrader instance.
		 * @param array                                $options  Array of bulk item update data.
		 *
		 * @since 5.5.0
		 */
		public static function on_plugin_or_theme_update( $upgrader, $options ) {

			if ( ! isset( $options['type'] ) || ! isset( $options['action'] ) ) {
				return;
			}

			if ( 'translation' !== $options['type'] || 'update' !== $options['action'] ) {
				return;
			}

			$translations = $options['translations'];

			foreach ( $translations as $translation ) {

				// Only proceed if the translation is for a plugin or a theme.
				if ( 'plugin' !== $translation['type'] && 'theme' !== $translation['type'] ) {
					continue;
				}

				/**
				 * Example of $translation for a plugin:
				 * (
				 *     [language] => es_ES
				 *     [type] => plugin
				 *     [slug] => wp-statistics
				 *     [version] => 14.15.3
				 * )
				 */

				/**
				 * Example of $translation for a theme:
				 * (
				 *     [language] => es_ES
				 *     [type] => theme
				 *     [slug] => generatepress
				 *     [version] => 3.6.0
				 * )
				 */

				$name = '';

				if ( method_exists( $upgrader, 'get_name_for_update' ) ) {

					// Name - e.g. "WordPress".
					$name = $upgrader->get_name_for_update( (object) $translation );
				}

				// If name is empty, let's use the slug as a fallback.
				if ( empty( $name ) && ! empty( $translation['slug'] ) ) {
					$name = $translation['slug'];
				}

				if ( 'plugin' === $translation['type'] ) {
						Alert_Manager::trigger_event(
							5034,
							array(
								'plugin_name' => $name,
								'language'    => $translation['language'],
							)
						);
				}

				if ( 'theme' === $translation['type'] ) {
					Alert_Manager::trigger_event(
						5035,
						array(
							'theme_name' => $name,
							'language'   => $translation['language'],
						)
					);
				}
			}
		}

		/**
		 * Fallback method in case events 5000 and 5005 were not triggered.
		 *
		 * This method has been added to handle the case when a plugin or theme is installed via ManageWP,
		 * which is not reported by the on_upgrader_process_complete method.
		 *
		 * @param bool  $response - Installation response.
		 * @param array $hook_extra - Extra arguments passed to hooked filters.
		 * @param array $result - Installation result data.
		 *
		 * @return bool $response - The installation response after the installation has finished
		 *
		 * @since 5.5.0
		 */
		public static function on_upgrader_post_install( $response, $hook_extra, $result ) {

			/**
			 * If $hook_extra['action'] is not set or not equal to 'install', return early.
			 * This works both for themes and plugins.
			 */
			if ( ! isset( $hook_extra['action'] ) || 'install' !== $hook_extra['action'] ) {
				return $response;
			}

			if ( isset( $result['destination_name'] ) ) {
				$folder_name = WP_Plugins_Themes_Helper::trim_folder_name( $result['destination_name'] );

				$is_plugin = WP_Plugins_Themes_Helper::does_dir_or_file_exist( $folder_name, 'plugin' );

				if ( $is_plugin ) {
					$event_plugin_data = WP_Plugins_Themes_Helper::get_plugin_event_info_from_folder( $folder_name );

					if ( is_array( $event_plugin_data ) ) {
						Alert_Manager::trigger_event(
							5000,
							array(
								'PluginData' => (object) $event_plugin_data,
							)
						);
					}
				}

				$is_theme = WP_Plugins_Themes_Helper::does_dir_or_file_exist( $folder_name, 'theme' );
				if ( $is_theme ) {
					$theme_event_data = WP_Plugins_Themes_Helper::get_theme_event_info_from_folder( $folder_name );

					if ( is_array( $theme_event_data ) ) {
						Alert_Manager::trigger_event(
							5005,
							array(
								'Theme' => (object) $theme_event_data,
							)
						);
					}
				}
			}

			return $response;
		}
	}
}
