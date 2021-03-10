<?php
/**
 * Sensor: Plugins & Themes
 *
 * Plugins & Themes sensor file.
 *
 * @since 1.0.0
 * @package Wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
 * @package Wsal
 * @subpackage Sensors
 */
class WSAL_Sensors_PluginsThemes extends WSAL_AbstractSensor {

	/**
	 * List of Themes.
	 *
	 * @var array
	 */
	protected $old_themes = array();

	/**
	 * List of Plugins.
	 *
	 * @var array
	 */
	protected $old_plugins = array();

	/**
	 * Listening to events using WP hooks.
	 */
	public function HookEvents() {
		$has_permission = ( current_user_can( 'install_plugins' ) || current_user_can( 'activate_plugins' ) ||
		                    current_user_can( 'delete_plugins' ) || current_user_can( 'update_plugins' ) || current_user_can( 'install_themes' ) );

		add_action( 'admin_init', array( $this, 'EventAdminInit' ) );
		if ( $has_permission ) {
			add_action( 'shutdown', array( $this, 'EventAdminShutdown' ) );
		}
		add_action( 'switch_theme', array( $this, 'EventThemeActivated' ) );
		add_action( 'upgrader_overwrote_package', [ $this, 'OnPackageOverwrite' ], 10, 3 );
	}

	/**
	 * Triggered when a user accesses the admin area.
	 */
	public function EventAdminInit() {
		$this->old_themes  = wp_get_themes();
		$this->old_plugins = get_plugins();
	}

	/**
	 * Install, uninstall, activate, deactivate, upgrade and update.
	 */
	public function EventAdminShutdown() {
		// Filter global arrays for security.
		$post_array  = filter_input_array( INPUT_POST );
		$get_array   = filter_input_array( INPUT_GET );
		$script_name = isset( $_SERVER['SCRIPT_NAME'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) : false;

		$action = '';
		if ( isset( $get_array['action'] ) && '-1' != $get_array['action'] ) {
			$action = $get_array['action'];
		} elseif ( isset( $post_array['action'] ) && '-1' != $post_array['action'] ) {
			$action = $post_array['action'];
		}

		if ( isset( $get_array['action2'] ) && '-1' != $get_array['action2'] ) {
			$action = $get_array['action2'];
		} elseif ( isset( $post_array['action2'] ) && '-1' != $post_array['action2'] ) {
			$action = $post_array['action2'];
		}

		$actype = '';
		if ( ! empty( $script_name ) ) {
			$actype = basename( $script_name, '.php' );
		}

		$is_plugins = 'plugins' === $actype;

		// Install plugin.
		if ( in_array( $action, array( 'install-plugin', 'upload-plugin', 'run_addon_install' ) ) && current_user_can( 'install_plugins' ) ) {
			$plugin = array_values( array_diff( array_keys( get_plugins() ), array_keys( $this->old_plugins ) ) );
			if ( count( $plugin ) != 1 ) {
				$this->LogError(
					'Expected exactly one new plugin but found ' . count( $plugin ),
					array(
						'NewPlugin'  => $plugin,
						'OldPlugins' => $this->old_plugins,
						'NewPlugins' => get_plugins(),
					)
				);
				return;
			}
			$plugin_path = $plugin[0];
			$plugin      = get_plugins();
			$plugin      = $plugin[ $plugin_path ];

			// Get plugin directory name.
			$plugin_dir = WSAL_Sensors_PluginsThemes::get_plugin_dir( $plugin_path );

			$plugin_path = plugin_dir_path( WP_PLUGIN_DIR . '/' . $plugin_path[0] );
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

			$this->run_addon_check( $plugin_dir );
		}

		// Activate plugin.
		if ( $is_plugins && in_array( $action, array( 'activate', 'activate-selected' ) ) && current_user_can( 'activate_plugins' ) ) {
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
				$latest_event = $this->plugin->alerts->get_latest_events();
				$latest_event = isset( $latest_event[0] ) ? $latest_event[0] : false;
				$event_meta   = $latest_event ? $latest_event->GetMetaArray() : false;

				foreach ( $get_array['checked'] as $plugin_file ) {
					if ( $latest_event && 5001 === $latest_event->alert_id && $event_meta && isset( $event_meta['PluginFile'] ) ) {
						if ( basename( WSAL_BASE_NAME ) === basename( $event_meta['PluginFile'] ) ) {
							continue;
						}
					}

					$plugin_file = WP_PLUGIN_DIR . '/' . $plugin_file;
					$plugin_data = get_plugin_data( $plugin_file, false, true );

					$this->plugin->alerts->Trigger(
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

					$this->run_addon_check( $plugin_file );
				}
			} elseif ( isset( $post_array['checked'] ) && ! empty( $post_array['checked'] ) ) {
				foreach ( $post_array['checked'] as $plugin_file ) {
					$plugin_file = WP_PLUGIN_DIR . '/' . $plugin_file;
					$plugin_data = get_plugin_data( $plugin_file, false, true );

					$this->plugin->alerts->Trigger(
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

					$this->run_addon_check( $plugin_file );
				}
			}
		}

		// Deactivate plugin.
		if ( $is_plugins && in_array( $action, array( 'deactivate', 'deactivate-selected' ) ) && current_user_can( 'activate_plugins' ) ) {
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
					$plugin_file = WP_PLUGIN_DIR . '/' . $plugin_file;
					$plugin_data = get_plugin_data( $plugin_file, false, true );
					$this->plugin->alerts->Trigger(
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
					WSAL_Sensors_PluginsThemes::run_addon_removal_check( $plugin_file );
				}
			} elseif ( isset( $post_array['checked'] ) && ! empty( $post_array['checked'] ) ) {
				foreach ( $post_array['checked'] as $plugin_file ) {
					$plugin_file = WP_PLUGIN_DIR . '/' . $plugin_file;
					$plugin_data = get_plugin_data( $plugin_file, false, true );
					$this->plugin->alerts->Trigger(
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
					WSAL_Sensors_PluginsThemes::run_addon_removal_check( $plugin_file );
				}
			}
		}

		// Uninstall plugin.
		if ( $is_plugins && in_array( $action, array( 'delete-selected' ) ) && current_user_can( 'delete_plugins' ) ) {
			if ( ! isset( $post_array['verify-delete'] ) ) {
				// First step, before user approves deletion
				// TODO store plugin data in session here.
			} else {
				// second step, after deletion approval
				// TODO use plugin data from session.
				foreach ( $post_array['checked'] as $plugin_file ) {
					$plugin_name = basename( $plugin_file, '.php' );
					$plugin_name = str_replace( array( '_', '-', '  ' ), ' ', $plugin_name );
					$plugin_name = ucwords( $plugin_name );
					$plugin_file = WP_PLUGIN_DIR . '/' . $plugin_file;
					$plugin_data = get_plugin_data( $plugin_file, false, true );
					$this->plugin->alerts->Trigger(
						5003,
						array(
							'PluginFile' => $plugin_file,
							'PluginData' => (object) array(
								'Name'    => $plugin_name,
								'Version' => $plugin_data['Version'],
							),
						)
					);
					WSAL_Sensors_PluginsThemes::run_addon_removal_check( $plugin_file );
				}
			}
		}

		// Uninstall plugin for WordPress version 4.6.
		if ( in_array( $action, array( 'delete-plugin' ) ) && current_user_can( 'delete_plugins' ) ) {
			if ( isset( $post_array['plugin'] ) ) {
				$plugin_file = WP_PLUGIN_DIR . '/' . $post_array['plugin'];
				$plugin_name = basename( $plugin_file, '.php' );
				$plugin_name = str_replace( array( '_', '-', '  ' ), ' ', $plugin_name );
				$plugin_name = ucwords( $plugin_name );
				$plugin_data = $this->old_plugins[ $post_array['plugin'] ];
				$this->plugin->alerts->Trigger(
					5003,
					array(
						'PluginFile' => $plugin_file,
						'PluginData' => (object) array(
							'Name'    => $plugin_name,
							'Version' => $plugin_data['Version'],
						),
					)
				);

				WSAL_Sensors_PluginsThemes::run_addon_removal_check( $plugin_file );
			}
		}

		// Upgrade plugin.
		if ( in_array( $action, array( 'upgrade-plugin', 'update-plugin', 'update-selected' ) ) && current_user_can( 'update_plugins' ) ) {
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
					WSAL_Sensors_PluginsThemes::LogPluginUpdatedEvent( $plugin_file, $this->old_plugins );
				}
			}
		}

		// Update theme.
		if ( in_array( $action, array( 'upgrade-theme', 'update-theme', 'update-selected-themes' ) ) && current_user_can( 'install_themes' ) ) {
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
                    WSAL_Sensors_PluginsThemes::LogThemeUpdatedEvent( $theme_name );
                }
            }
		}

		// Install theme.
		if ( in_array( $action, array( 'install-theme', 'upload-theme' ) ) && current_user_can( 'install_themes' ) ) {
			$themes = array_diff( wp_get_themes(), $this->old_themes );
			foreach ( $themes as $name => $theme ) {
				$this->plugin->alerts->Trigger(
					5005,
					array(
						'Theme' => (object) array(
							'Name'                   => $theme->Name,
							'ThemeURI'               => $theme->ThemeURI,
							'Description'            => $theme->Description,
							'Author'                 => $theme->Author,
							'Version'                => $theme->Version,
							'get_template_directory' => $theme->get_template_directory(),
						),
					)
				);
			}
		}

		// Uninstall theme.
		if ( in_array( $action, array( 'delete-theme' ) ) && current_user_can( 'install_themes' ) ) {
			foreach ( $this->GetRemovedThemes() as $index => $theme ) {
				$this->plugin->alerts->Trigger(
					5007,
					array(
						'Theme' => (object) array(
							'Name'                   => $theme->Name,
							'ThemeURI'               => $theme->ThemeURI,
							'Description'            => $theme->Description,
							'Author'                 => $theme->Author,
							'Version'                => $theme->Version,
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
	 */
	public function EventThemeActivated( $theme_name ) {
		$theme = null;
		foreach ( wp_get_themes() as $item ) {
			if ( $theme_name == $item->Name ) {
				$theme = $item;
				break;
			}
		}
		if ( null == $theme ) {
			$this->LogError(
				'Could not locate theme named "' . $theme . '".',
				array(
					'ThemeName' => $theme_name,
					'Themes'    => wp_get_themes(),
				)
			);
			return;
		}
		$this->plugin->alerts->Trigger(
			5006,
			array(
				'Theme' => (object) array(
					'Name'                   => $theme->Name,
					'ThemeURI'               => $theme->ThemeURI,
					'Description'            => $theme->Description,
					'Author'                 => $theme->Author,
					'Version'                => $theme->Version,
					'get_template_directory' => $theme->get_template_directory(),
				),
			)
		);
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
	 * Get event code by post type.
	 *
	 * @param object $post - Post object.
	 * @param int $type_post - Code for post.
	 * @param int $type_page - Code for page.
	 * @param int $type_custom - Code for custom post type.
	 *
	 * @return false|int
	 */
	protected function GetEventTypeForPostType( $post, $type_post, $type_page, $type_custom ) {
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
				if ( $theme_name === $theme_slug|| $theme_name === $theme_obj->get('Name') ) {
					$theme = $theme_obj;
					break;
				}
			}
		}
		return $theme;
	}

	public function run_addon_check( $plugin_dir ) {
		$plugin_filename = basename( preg_replace( '/\\.[^.\\s]{3,4}$/', '', $plugin_dir ) );

		if ( is_array( $plugin_filename ) ) {
			$plugin_filename = array_map( 'strval', $plugin_filename );
		}

		// Grab list of plugins we have addons for.
		$predefined_plugins       = WSAL_PluginInstallAndActivate::get_installable_plugins();
		$predefined_plugins_addon = array_column( $predefined_plugins, 'addon_for' );
		$all_plugins              = array_keys( get_plugins() );
		foreach ( $predefined_plugins_addon as $plugin ) {

			$plugin = apply_filters( 'wsal_modify_predefined_plugin_slug', $plugin );

			// Check if plugin file starts with the same string as our addon_for, or if its equal.
			if ( $plugin_filename === $plugin ) {
				$addon_slug         = array( array_search( $plugin, array_column( $predefined_plugins, 'addon_for', 'plugin_slug' ) ) );
				$is_addon_installed = array_intersect( $all_plugins, $addon_slug );
				if ( empty( $is_addon_installed ) ) {
					$current_value   = $this->plugin->GetGlobalSetting( 'installed_plugin_addon_available' );
					$plugin_filename = array( $plugin_filename );
					if ( isset( $current_value ) && is_array( $current_value ) ) {
						$new_plugin_filenames = array_unique( array_merge( $current_value, $plugin_filename ) );
					} else {
						$new_plugin_filenames = $plugin_filename;
					}
					$this->plugin->SetGlobalSetting( 'installed_plugin_addon_available', $new_plugin_filenames );
					$this->plugin->DeleteGlobalSetting( 'addon_available_notice_dismissed' );
				}
			}
		}
	}

	public static function run_addon_removal_check( $plugin_dir ) {
		$wsal = WpSecurityAuditLog::GetInstance();
		$plugin_filename = basename( preg_replace( '/\\.[^.\\s]{3,4}$/', '', $plugin_dir ) );

		if ( is_array( $plugin_filename ) ) {
			$plugin_filename = array_map( 'strval', $plugin_filename );
		}

		// Grab list of plugins we have addons for.
		$predefined_plugins       = WSAL_PluginInstallAndActivate::get_installable_plugins();
		$predefined_plugins_addon = array_column( $predefined_plugins, 'addon_for' );
		foreach ( $predefined_plugins_addon as $plugin ) {

			$plugin = apply_filters( 'wsal_modify_predefined_plugin_slug', $plugin );

			// Check if plugin file starts with the same string as our addon_for, or if its equal.
			if ( $plugin_filename === $plugin ) {
				$current_installed = $wsal->GetGlobalSetting( 'installed_plugin_addon_available' );
				if ( isset( $current_installed ) && ! empty( $current_installed  ) ) {
					if ( ( $key = array_search( $plugin, $current_installed ) ) !== false ) {
						unset( $current_installed[$key] );
					}
				}

				$wsal->SetGlobalSetting( 'installed_plugin_addon_available', $current_installed );
			}
		}
	}

	/**
	 * Fires when the upgrader has successfully overwritten a currently installed
	 * plugin or theme with an uploaded zip package.
	 *
	 * @since 4.1.4
	 *
	 * @param string  $package          The package file.
	 * @param array   $new_plugin_data  The new plugin data.
	 * @param string  $package_type     The package type (plugin or theme).
	 */
	public function OnPackageOverwrite( $package, $new_plugin_data, $package_type ) {
		if ( 'plugin' !== $package_type ) {
			return;
		}

		if ( array_key_exists( 'Name', $new_plugin_data ) ) {
			$plugin_file = WSAL_Sensors_PluginsThemes::get_plugin_file_name( $new_plugin_data['Name'] );
			if ( ! empty( $plugin_file ) ) {
				WSAL_Sensors_PluginsThemes::LogPluginUpdatedEvent( $plugin_file );
			}
		}
	}

	/**
	 * Log plugin updated event.
	 *
	 * @param string $plugin_file Relative path to the plugin filename.
	 * @param array  $old_plugins (Optional) Array of old plugins which we can use for comparison.
	 *
	 * @since 4.1.4
	 */
	public static function LogPluginUpdatedEvent( $plugin_file, $old_plugins = '' ) {
		$plugin_file_full = WP_PLUGIN_DIR . '/' . $plugin_file;
		$plugin_data      = get_plugin_data( $plugin_file_full, false, true );

		$old_version = ( isset( $old_plugins[ $plugin_file ] ) ) ? $old_plugins[ $plugin_file ]['Version'] : false;
		$new_version = $plugin_data['Version'];

		if ( $old_version !== $new_version ) {
			$wsal = WpSecurityAuditLog::GetInstance();
			$wsal->alerts->Trigger(
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
				)
			);
		}
	}

    /**
     * Log theme updated event.
     *
     * @param string $theme_name Theme name.
     *
     * @since 4.1.5
     */
    public static function LogThemeUpdatedEvent( $theme_name ) {
        $theme = WSAL_Sensors_PluginsThemes::get_theme_by_name($theme_name);
        if ( ! $theme instanceof WP_Theme ) {
            return;
        }

        $wsal = WpSecurityAuditLog::GetInstance();
        $wsal->alerts->Trigger(
            5031,
            array(
                'Theme' => (object) array(
                    'Name'                   => $theme->Name,
                    'ThemeURI'               => $theme->ThemeURI,
                    'Description'            => $theme->Description,
                    'Author'                 => $theme->Author,
                    'Version'                => $theme->Version,
                    'get_template_directory' => $theme->get_template_directory(),
                ),
            )
        );
    }
}
