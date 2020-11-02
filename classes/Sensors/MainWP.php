<?php
/**
 * Sensor: MainWP Plugins & Themes
 *
 * MainWP Plugins & Themes sensor file.
 *
 * @since 4.1.4
 * @package Wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
 * @package Wsal
 * @subpackage Sensors
 */
class WSAL_Sensors_MainWP extends WSAL_AbstractSensor {

	/**
	 * List of Themes.
	 *
	 * @var array
	 */
	protected $old_themes = array();

	/**
	 * Listening to events using WP hooks.as
	 */
	public function HookEvents() {

		add_action( 'admin_init', array( $this, 'EventAdminInit' ) );

		// Check if MainWP Child Plugin exists.
		if ( WpSecurityAuditLog::is_mainwp_active() ) {

			$this->mainwp_child_init();

			// Handle plugin/theme installation event via MainWP dashboard.
			add_action( 'mainwp_child_installPluginTheme', array( $this, 'mainwp_child_install_assets' ), 10, 1 );

			// Activate/Deactivate plugin event.
			add_action( 'activated_plugin', array( $this, 'mainwp_child_plugin_events' ), 10, 1 );
			add_action( 'deactivated_plugin', array( $this, 'mainwp_child_plugin_events' ), 10, 1 );

			// Uninstall plugin from MainWP dashboard.
			add_action( 'mainwp_child_plugin_action', array( $this, 'mainwp_child_uninstall_plugin' ), 10, 1 );

			// Uninstall theme from MainWP dashboard.
			add_action( 'mainwp_child_theme_action', array( $this, 'mainwp_child_uninstall_theme' ), 10, 1 );

			// Update theme/plugin from MainWP dashboard.
            add_action( 'upgrader_process_complete', array( $this, 'mainwp_child_update_assets' ), 10, 2 );

		}
	}

	/**
	 * Triggered when a user accesses the admin area.
	 */
	public function EventAdminInit() {
		$this->old_themes  = wp_get_themes();
		$this->old_plugins = get_plugins();
	}

	/**
	 * Method: Check and initialize class members for MainWP.
	 */
	public function mainwp_child_init() {
		// $_POST array arguments.
		$post_array_args = array(
			'function'        => FILTER_SANITIZE_STRING,
			'action'          => FILTER_SANITIZE_STRING,
			'theme'           => FILTER_SANITIZE_STRING,
			'mainwpsignature' => FILTER_SANITIZE_STRING,
		);

		// Get $_POST array.
		$post_array = filter_input_array( INPUT_POST, $post_array_args );

		if (
			isset( $post_array['function'] ) && 'theme_action' === $post_array['function']
			&& isset( $post_array['action'] ) && 'delete' === $post_array['action']
			&& isset( $post_array['theme'] ) && ! empty( $post_array['theme'] )
			&& isset( $post_array['mainwpsignature'] ) && ! empty( $post_array['mainwpsignature'] )
		) {
			if ( empty( $this->old_themes ) ) {
				$this->old_themes = wp_get_themes();
			}
		}
	}

	/**
	 * Get removed themes.
	 *
	 * @return array of WP_Theme objects
	 */
	protected function GetRemovedThemes() {
		$result = $this->old_themes;
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
	 * @since 3.2.2
	 */
	public function mainwp_child_install_assets( $args ) {
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
					$this->plugin->alerts->Trigger(
						5005,
						array(
							'Theme' => (object) array(
								'Name'                   => $theme_obj->Name,
								'ThemeURI'               => $theme_obj->ThemeURI,
								'Description'            => $theme_obj->Description,
								'Author'                 => $theme_obj->Author,
								'Version'                => $theme_obj->Version,
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
				$this->plugin->alerts->Trigger(
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
	 * @since 3.2.2
	 */
	public function mainwp_child_uninstall_plugin( $args ) {
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
			$plugin_filename = WSAL_Sensors_PluginsThemes::get_plugin_file_name( $plugin_name );

			if ( ! empty( $plugin_filename ) && in_array( $plugin_filename, $wp_plugins, true ) ) {
				$this->plugin->alerts->Trigger(
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
	 * @since 3.2.2
	 */
	public function mainwp_child_uninstall_theme( $args ) {
		if ( empty( $args ) || ! is_array( $args ) ) {
			return;
		}

		// Get MainWP post data.
		$post_array = filter_input_array( INPUT_POST );

		// Get themes from MainWP.
		if ( isset( $post_array['theme'] ) && ! empty( $post_array['theme'] ) ) {
			$wp_themes = explode( '||', $post_array['theme'] );
		}

		// Verify actions from MainWP.
		if (
			isset( $args['action'] ) && 'delete' === $args['action']
			&& isset( $args['Name'] ) && ! empty( $args['Name'] )
			&& isset( $post_array['mainwpsignature'] ) && ! empty( $post_array['mainwpsignature'] )
		) {
			// Get theme object.
			$themes = $this->GetRemovedThemes();

			if ( ! empty( $themes ) ) {
				foreach ( $themes as $index => $theme ) {
					if ( ! empty( $theme ) && $theme instanceof WP_Theme && in_array( $theme->Name, $wp_themes, true ) ) {
						$this->plugin->alerts->Trigger(
							5007,
							array(
								'Theme' => (object) array(
									'Name'        => $theme->Name,
									'ThemeURI'    => $theme->ThemeURI,
									'Description' => $theme->Description,
									'Author'      => $theme->Author,
									'Version'     => $theme->Version,
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
	 * @since 3.2.2
	 */
	public function mainwp_child_plugin_events( $plugin ) {
		// Check parameter.
		if ( empty( $plugin ) ) {
			return;
		}

		// Get MainWP post data.
		$post_array = filter_input_array( INPUT_POST );

		// Get plugins from MainWP.
		if ( isset( $post_array['plugin'] ) && ! empty( $post_array['plugin'] ) ) {
			$wp_plugins = explode( '||', $post_array['plugin'] );
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
			$this->plugin->alerts->Trigger(
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
			$this->plugin->alerts->Trigger(
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
     * Method: Handle plugin/theme update event
     * from MainWP dashboard on child site.
     *
     * @param WP_Upgrader $upgrader
     * @param array $args - Array of arguments related to asset updated.
     * @since 3.2.2
     */
	public function mainwp_child_update_assets( $upgrader, $args ) {
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
				$site_themes = array_key_exists( 'themes', $args ) ? $args['themes'] : explode( ',', $post_array['list'] );

				if ( empty( $site_themes ) ) {
				    //  no themes in any of the lists
                    return;
                }

				foreach ( $site_themes as $theme_name ) {
                    WSAL_Sensors_PluginsThemes::LogThemeUpdatedEvent( $theme_name );
                }
			} elseif ( 'plugin' === $args['type'] ) {
                // Site plugins updated.
                if ( ! array_key_exists( 'plugins', $args ) || empty( $args['plugins'] ) ) {
                    //  no plugins in the list
                    return;
                }

                $plugins = $args['plugins'];
                foreach ( $plugins as $plugin_file ) {
                    WSAL_Sensors_PluginsThemes::LogPluginUpdatedEvent( $plugin_file );
                }
			}
		}
	}
}
