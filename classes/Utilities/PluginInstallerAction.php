<?php
/**
 * Plugin installer action
 *
 * Class file for installing plugins from the repo.
 *
 * @since 4.0.1
 * @package wsal
 */

if ( ! class_exists( 'WSAL_PluginInstallerAction' ) ) {

	/**
	 * Class to handle the installation and activation of plugins.
	 *
	 * @since 4.0.1
	 */
	class WSAL_PluginInstallerAction {

		/**
		 * Register the ajax action.
		 *
		 * @method register
		 * @since  4.0.1
		 */
		public function register() {
			add_action( 'wp_ajax_wsal_run_addon_install', array( $this, 'run_addon_install' ) );
		}

		/**
		 * Run the installer.
		 *
		 * @method run_addon_install
		 * @since  4.0.1
		 */
		public function run_addon_install() {
			check_ajax_referer( 'wsal-install-addon' );

			$predefined_plugins = WSAL_PluginInstallAndActivate::get_installable_plugins();

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
				wp_send_json_error(
					array(
						'message' => esc_html__( 'Tried to install a zip or slug that was not in the allowed list', 'wp-security-audit-log' ),
					)
				);
			}

			// Check if the plugin is installed.
			if ( $this->is_plugin_installed( $plugin_slug ) ) {
				// If plugin is installed but not active, activate it.
				if ( ! WpSecurityAuditLog::is_plugin_active( $plugin_zip ) ) {
					$this->run_activate( $plugin_slug );
					$this->activate( $plugin_zip );
					$result = 'activated';
				} else {
					$result = 'already_installed';
				}
			} else {
				// No plugin found or plugin not present to be activated, so lets install it.
				$this->install_plugin( $plugin_zip );
				$this->run_activate( $plugin_slug );
				$this->activate( $plugin_zip );
				$result = 'success';
			}

			// If we're installing our helper plugin, we also need to delete the nudge to install the helper plugin.
			if ( $helper_plugin_installation ) {
				WpSecurityAuditLog::get_instance()->delete_global_setting( 'show-helper-plugin-needed-nudge' );
			}

			wp_send_json( $result );
		}

		/**
		 * Install a plugin given a slug.
		 *
		 * @method install
		 * @since  4.0.1
		 * @param  string $plugin_zip URL to the direct zip file.
		 */
		public function install_plugin( $plugin_zip = '' ) {
			// bail early if we don't have a slug to work with.
			if ( empty( $plugin_zip ) ) {
				return;
			}
			// get the core plugin upgrader if not already in the runtime.
			if ( ! class_exists( 'Plugin_Upgrader' ) ) {
				include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			}
			// clear the cache so we're using fresh data.
			wp_cache_flush();
			$upgrader       = new Plugin_Upgrader();
			$install_result = $upgrader->install( $plugin_zip );
			if ( ! $install_result || is_wp_error( $install_result ) ) {
				if ( is_wp_error( $install_result ) ) {
					return $install_result->get_error_message();
				}
				die();
			}
		}

		/**
		 * Activates a plugin that is available on the site.
		 *
		 * @method activate
		 * @since  4.0.1
		 * @param  string $plugin_zip URL to the direct zip file.
		 * @return void
		 */
		public function activate( $plugin_zip = '' ) {
			// bail early if we don't have a slug to work with.
			if ( empty( $plugin_zip ) ) {
				return;
			}

			if ( function_exists( 'is_multisite' ) && is_multisite() ) {
				return;
			}

			// get core plugin functions if they are not already in runtime.
			if ( ! function_exists( 'activate_plugin' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			if ( ! WpSecurityAuditLog::is_plugin_active( $plugin_zip ) ) {
				activate_plugin( $plugin_zip );
			}
		}

		/**
		 * Activates a plugin that is available on the site.
		 *
		 * @method run_activate
		 * @since  4.0.1
		 * @param  string $plugin_slug slug for plugin.
		 */
		public function run_activate( $plugin_slug = '' ) {
			// bail early if we don't have a slug to work with.
			if ( empty( $plugin_slug ) ) {
				return;
			}

			if ( function_exists( 'is_multisite' ) && is_multisite() ) {
				$current = get_site_option( 'active_sitewide_plugins' );
			} else {
				$current = get_option( 'active_plugins' );
			}

			if ( ! in_array( $plugin_slug, $current, true ) ) {
				if ( function_exists( 'is_multisite' ) && is_multisite() ) {
					$current[] = $plugin_slug;
					activate_plugin( $plugin_slug, '', true );
				} else {
					$current[] = $plugin_slug;
					activate_plugin( $plugin_slug );
				}
			}
			return null;
		}

		/**
		 * Check if a plugin is installed.
		 *
		 * @method is_plugin_installed
		 * @since  4.0.1
		 * @param  string $plugin_slug slug for plugin.
		 */
		public function is_plugin_installed( $plugin_slug = '' ) {
			// bail early if we don't have a slug to work with.
			if ( empty( $plugin_slug ) ) {
				return;
			}

			// get core plugin functions if not already in the runtime.
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$all_plugins = get_plugins();

			// true if plugin is already installed or false if not.
			if ( ! empty( $all_plugins[ $plugin_slug ] ) ) {
				return true;
			} else {
				return false;
			}
		}
	}
}
