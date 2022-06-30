<?php

/**
 * Core class for importing/exporting of WordPress options.
 */
class SettingsImportExport {

	/**
	 * Path to the library base folder relative to the WP plugins folder.
	 *
	 * @var string
	 */
	private $base_dir_relative;

	/**
	 * Absolute URL to the library base folder.
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * Instance key. Used to prefix nonce actions, field names, AJAX actions etc.
	 *
	 * @var string
	 */
	private $key;

	/**
	 * Callback to retrieve a list of options to export.
	 *
	 * @var callable
	 */
	private $option_list_callback;

	/**
	 * Callback to get type of option used during the pre-import check.
	 *
	 * @var callable
	 */
	private $option_type_check_callback;

	/**
	 * Callback to run an extra pre-import check of option that doesn't have a specific type. It should return WP_Error
	 * if the option cannot be imported for some reason.
	 *
	 * @var callable
	 */
	private $pre_import_option_check_callback;

	/**
	 * Callback to run to see if scripts and styles can be enqueued.
	 *
	 * @var callable
	 */
	private $can_enqueue_scripts_callback;

	/**
	 * URL to the help page.
	 *
	 * @var string
	 */
	private $help_page_url;

	/**
	 * Constructor.
	 *
	 * @param string   $base_dir_relative                Path to the library base folder relative to the WP plugins
	 *                                                   folder.
	 * @param string   $base_url                         Absolute URL to the library base folder.
	 * @param string   $key                              Instance key.
	 * @param callable $option_list_callback             Callback to retrieve a list of options to export.
	 * @param callable $option_type_check_callback       Callback to get type of option used during the pre-import
	 *                                                   check.
	 * @param callable $pre_import_option_check_callback Callback to run an extra pre-import check of option that
	 *                                                   doesn't have a specific type. It should return WP_Error if the
	 *                                                   option cannot be imported for some reason.
	 * @param callable $can_enqueue_scripts_callback     Callback to run to see if scripts and styles can be enqueued.
	 * @param string   $help_page_url                    URL to the help page.
	 */
	public function __construct( $base_dir_relative, $base_url, $key, $option_list_callback, $option_type_check_callback, $pre_import_option_check_callback, $can_enqueue_scripts_callback, $help_page_url = '' ) {

		$this->base_dir_relative                = trailingslashit( $base_dir_relative );
		$this->base_url                         = trailingslashit( $base_url );
		$this->key                              = $key;
		$this->option_list_callback             = $option_list_callback;
		$this->option_type_check_callback       = $option_type_check_callback;
		$this->pre_import_option_check_callback = $pre_import_option_check_callback;
		$this->can_enqueue_scripts_callback     = $can_enqueue_scripts_callback;
		$this->help_page_url                    = $help_page_url;

		add_filter( 'wp_ajax_' . $key . '_export_settings', array( $this, 'export_settings' ), 10, 1 );
		add_filter( 'wp_ajax_' . $key . '_check_setting_pre_import', array( $this, 'check_setting_pre_import' ), 10, 1 );
		add_filter( 'wp_ajax_' . $key . '_process_import', array( $this, 'process_import' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		$this->load_textdomain();
	}

	/**
	 * Load library's text domain.
	 *
	 * @since  1.0.0
	 */
	public function load_textdomain() {
		if ( ! is_admin() ) {
			return;
		}

		load_plugin_textdomain(
			'import-export-settings',
			false,
			$this->base_dir_relative . 'languages/'
		);
	}

	/**
	 * Renders the UI for importing/exporting settings.
	 *
	 * @param boolean $disabled If true, all the form controls are disabled.
	 */
	public function render( $disabled ) {
		$nonce = wp_create_nonce( $this->key . '-export-settings' );
		?>
		<table class="form-table logs-management-settings" data-key="<?php echo esc_attr( $this->key ); ?>">
			<tr>
				<th><label><?php esc_html_e( 'Export settings', 'import-export-settings' ); ?></label></th>
				<td>
					<fieldset <?php echo esc_attr( $disabled ); ?>>
						<input type="button" id="export-settings" class="button-primary"
								value="<?php esc_html_e( 'Export', 'import-export-settings' ); ?>"
								data-export-wpws-settings data-nonce="<?php echo esc_attr( $nonce ); ?>">
						<p class="description">
							<?php esc_html_e( 'Once the settings are exported a download will automatically start. The settings are exported to a JSON file.', 'import-export-settings' ); ?>
						</p>
					</fieldset>
				</td>
			</tr>

			<tr>
				<th><label><?php esc_html_e( 'Import settings', 'import-export-settings' ); ?></label></th>
				<td>
					<fieldset <?php echo esc_attr( $disabled ); ?>>

						<input type="file" id="wpws-settings-file" name="filename"><br>
						<input style="margin-top: 7px;" type="submit" id="import-settings" class="button-primary"
								data-import-wpws-settings data-nonce="<?php echo esc_attr( $nonce ); ?>"
								value="<?php esc_html_e( 'Validate & Import', 'import-export-settings' ); ?>">
						<p class="description">
							<?php esc_html_e( 'Once you choose a JSON settings file, it will be checked prior to being imported to alert you of any issues, if there are any.', 'import-export-settings' ); ?>
						</p>
						<div id="import-settings-modal">
							<div class="modal-content">
								<h3 id="wpws-modal-title"></h3>
								<span class="import-settings-modal-close">&times;</span>
								<span><ul id="wpws-settings-file-output"></ul></span>
							</div>
						</div>
					</fieldset>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Creates a JSON file containing settings.
	 */
	public function export_settings() {
		// Grab POSTed data.
		$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_STRING );

		// Check nonce.
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, $this->key . '-export-settings' ) ) {
			wp_send_json_error( esc_html__( 'Nonce Verification Failed.', 'import-export-settings' ) );
		}

		$results = array();
		if ( is_callable( $this->option_list_callback ) ) {
			$results = call_user_func( $this->option_list_callback );
		}

		wp_send_json_success( json_encode( $results ) ); // phpcs:ignore
	}

	/**
	 * Checks settings before importing.
	 */
	public function check_setting_pre_import() {

		// Grab POSTed data.
		$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_STRING );

		// Check nonce.
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, $this->key . '-export-settings' ) ) {
			wp_send_json_error( esc_html__( 'Nonce Verification Failed.', 'import-export-settings' ) );
		}

		$setting_name   = filter_input( INPUT_POST, 'setting_name', FILTER_SANITIZE_STRING );
		$process_import = filter_input( INPUT_POST, 'process_import', FILTER_SANITIZE_STRING );
		$setting_value  = filter_input( INPUT_POST, 'setting_value', FILTER_DEFAULT, FILTER_FORCE_ARRAY );
		$setting_value  = $setting_value[0];

		$message = array(
			'setting_checked' => $setting_name,
		);

		$failed = false;

		// Check if relevant data is present for setting to be operable before import.
		if ( ! empty( $setting_value ) ) {

			// Try to figure out an option type using a callback.
			$type = null;
			if ( is_callable( $this->option_type_check_callback ) ) {
				$type = call_user_func( $this->option_type_check_callback, $setting_name );
			}

			switch ( $type ) {
				case 'user':
					$setting_value_to_test = $this->trim_and_explode( $setting_value );
					foreach ( $setting_value_to_test as $user_login ) {
						if ( ! get_user_by( 'login', $user_login ) ) {
							$message['failure_reason']      = esc_html__( 'User not found: ', 'import-export-settings' ) . $user_login;
							$message['failure_reason_type'] = 'not_found';
							$failed                         = true;
						}
					}
					break;
				case 'role':
					$setting_value_to_test = $this->trim_and_explode( $setting_value );
					foreach ( $setting_value_to_test as $role ) {
						if ( ! in_array( $role, array_keys( get_editable_roles() ), true ) ) {
							$message['failure_reason']      = esc_html__( 'Role not found: ', 'import-export-settings' ) . $role;
							$message['failure_reason_type'] = 'not_found';
							$failed                         = true;
						}
					}
					break;
				case 'post_type':
					$setting_value_to_test = $this->trim_and_explode( $setting_value );
					foreach ( $setting_value_to_test as $post_type ) {
						if ( ! in_array( $post_type, $this->get_all_post_types(), true ) ) {
							$message['failure_reason']      = esc_html__( 'Post type not found: ', 'import-export-settings' ) . $post_type;
							$message['failure_reason_type'] = 'not_found';
							$failed                         = true;
						}
					}
					break;

				default:
					if ( is_callable( $this->pre_import_option_check_callback ) ) {
						$check = call_user_func( $this->pre_import_option_check_callback, $setting_name, $setting_value );
						if ( $check instanceof WP_Error ) {
							$message['failure_reason']      = $check->get_error_message();
							$message['failure_reason_type'] = $check->get_error_code();
							$failed                         = true;
						}
					}
			}

			if ( 'true' !== $process_import && $failed ) {
				wp_send_json_error( $message );
			}
		}

		// If set to import the data once checked, then do so.
		if ( 'true' === $process_import && ! isset( $message['failure_reason'] ) ) {
			$updated                        = ( ! update_option( $setting_name, maybe_unserialize( $setting_value ) ) ) ? esc_html__( 'Setting updated', 'import-export-settings' ) : esc_html__( 'Setting created', 'import-export-settings' );
			$message['import_confirmation'] = $updated;
			wp_send_json_success( $message );
		}

		wp_send_json_success( $message );
		exit;
	}

	/**
	 * Gets value ready for checking when needed.
	 *
	 * @param mixed $value Value.
	 */
	public function trim_and_explode( $value ) {
		if ( is_array( $value ) ) {
			return explode( ',', $value[0] );
		} else {
			$setting_value = trim( $value, '"' );

			return str_replace( '""', '"', explode( ',', $setting_value ) );
		}
	}

	/**
	 * Simpler helper to get all available post types.
	 *
	 * @return array
	 */
	public function get_all_post_types() {
		global $wp_post_types;

		return array_keys( $wp_post_types );
	}

	/**
	 * Add scripts and styles for this extension.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {

		if ( ! is_callable( $this->can_enqueue_scripts_callback ) ) {
			return;
		}

		if ( true === call_user_func( $this->can_enqueue_scripts_callback ) ) {

			wp_enqueue_script(
				'settings-export-import',
				$this->base_url . 'js/settings-export.js',
				array(),
				'1.0.0',
				true
			);

			wp_localize_script(
				'settings-export-import',
				'wpws_import_data',
				array(
					'wp_nonce'              => wp_create_nonce( $this->key . '-export-settings' ),
					'checkingMessage'       => esc_html__( 'Checking import contents', 'import-export-settings' ),
					'checksPassedMessage'   => esc_html__( 'Ready to import', 'import-export-settings' ),
					'checksFailedMessage'   => esc_html__( 'Issues found', 'import-export-settings' ),
					'importingMessage'      => esc_html__( 'Importing settings', 'import-export-settings' ),
					'importedMessage'       => esc_html__( 'Settings imported', 'import-export-settings' ),
					'helpMessage'           => esc_html__( 'Help', 'import-export-settings' ),
					'notFoundMessage'       => esc_html__( 'The role, user or post type contained in your settings are not currently found in this website. Importing such settings could lead to abnormal behavour. For more information and / or if you require assistance, please', 'import-export-settings' ),
					'notSupportedMessage'   => esc_html__( 'Currently this data is not supported by our export/import wizard.', 'import-export-settings' ),
					'restrictAccessMessage' => esc_html__( 'To avoid accidental lock-out, this setting is not imported.', 'import-export-settings' ),
					'wrongFormat'           => esc_html__( 'Please upload a valid JSON file.', 'import-export-settings' ),
					'cancelMessage'         => esc_html__( 'Cancel', 'import-export-settings' ),
					'readyMessage'          => esc_html__( 'The settings file has been tested and the configuration is ready to be imported. Would you like to proceed?', 'import-export-settings' ),
					'proceedMessage'        => esc_html__( 'The configuration has been successfully imported. Click OK to close this window', 'import-export-settings' ),
					'proceed'               => esc_html__( 'Proceed', 'import-export-settings' ),
					'ok'                    => esc_html__( 'OK', 'import-export-settings' ),
					'helpPage'              => $this->help_page_url,
					'helpLinkText'          => esc_html__( 'Contact Us', 'import-export-settings' ),
				)
			);
			wp_enqueue_style( 'settings-export-import', $this->base_url . 'css/style.css', array(), '1.0.0' );
		}
	}
}