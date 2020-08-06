<?php
/**
 * View: WSAL Setup
 *
 * WSAL setup class file.
 *
 * @since 3.2.3
 * @package Wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class: WSAL Setup Wizard.
 *
 * WSAL setup wizard class which manages the functionality
 * related to setup.
 */
final class WSAL_Views_SetupWizard {

	/**
	 * Instance of WpSecurityAuditLog
	 *
	 * @var WpSecurityAuditLog
	 */
	private $wsal;

	/**
	 * Wizard Steps
	 *
	 * @var array
	 */
	private $wizard_steps;

	/**
	 * Current Step
	 *
	 * @var string
	 */
	private $current_step;

	/**
	 * List of all the valid inputs we will accept for log levels.
	 *
	 * @var array
	 */
	private $valid_log_levels = array( 'geek', 'basic' );

	/**
	 * List if all the valid inputs we will accept for prune times.
	 *
	 * @var array
	 */
	private $valid_prune_times = array( '6', '12', 'none' );

	/**
	 * Method: Constructor.
	 *
	 * @param WpSecurityAuditLog $wsal – Instance of main plugin.
	 */
	public function __construct( WpSecurityAuditLog $wsal ) {
		$this->wsal = $wsal;

		if ( current_user_can( 'manage_options' ) ) {
			add_action( 'admin_init', array( $this, 'setup_page' ), 10 );
		}
		add_action( 'admin_menu', array( $this, 'admin_menus' ), 10 );
		add_action( 'wp_ajax_setup_check_security_token', array( $this, 'setup_check_security_token' ) );

	}

	/**
	 * Ajax handler to verify setting token.
	 */
	public function setup_check_security_token() {
		if ( ! current_user_can( 'manage_options' ) ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html__( 'Access Denied.', 'wp-security-audit-log' ),
				)
			);
			die();
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : false;
		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : false;

		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wsal-verify-wizard-page' ) ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html__( 'Nonce verification failed.', 'wp-security-audit-log' ),
				)
			);
			die();
		}

		if ( empty( $token ) ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html__( 'Invalid input.', 'wp-security-audit-log' ),
				)
			);
			die();
		}

		echo wp_json_encode(
			array(
				'success'   => true,
				'token'     => $token,
				'tokenType' => esc_html( $this->get_token_type( $token ) ),
			)
		);
		die();
	}

	/**
	 * Add setup admin page.
	 */
	public function admin_menus() {
		// this is an empty title because we do not want it to display.
		add_dashboard_page( '', '', 'manage_options', 'wsal-setup', '' );
		// hide it via CSS as well so screen readers pass over it.
		add_action(
			'admin_head',
			function() {
				?>
				<style>
				.wp-submenu a[href="wsal-setup"]{
					display: none !important;
				}
				</style>
				<?php
			}
		);
	}

	/**
	 * Setup Page Start.
	 */
	public function setup_page() {
		// Get page argument from $_GET array.
		$page = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING );
		if ( empty( $page ) || 'wsal-setup' !== $page || ! ( current_user_can( 'manage_options' ) ) ) {
			return;
		}

		// Grab list of installed plugins.
		$all_plugins      = get_plugins();
		$plugin_filenames = array();
		foreach ( $all_plugins as $plugin => $info ) {
			// here we strip all of the plugin slug, leaving just the filename itself. Neat!
			$plugin_filenames[] = preg_replace( '/\\.[^.\\s]{3,4}$/', '', substr( basename( json_encode( $plugin ) ), 0, -1 ) );
		}

		// Grab list of plugins we have addons for.
		$predefined_plugins = WSAL_PluginInstallAndActivate::get_installable_plugins();
		$predefined_plugins = array_column( $predefined_plugins, 'addon_for' );

		// Loop through plugins and create an array of slugs, we will compare these agains the plugins we have addons for.
		$we_have_addon = array_intersect( $plugin_filenames, $predefined_plugins );

		// Check if we have a match, if so, lets fire up out nifty slide.
		if ( ! empty( $we_have_addon ) ) {
			add_filter( 'wsal_wizard_default_steps', array( $this, 'wsal_add_wizard_step' ) );
		}

		/**
		 * Wizard Steps.
		 */
		$wizard_steps = array(
			'welcome'        => array(
				'name'    => __( 'Welcome', 'wp-security-audit-log' ),
				'content' => array( $this, 'wsal_step_welcome' ),
			),
			'log_details'    => array(
				'name'    => __( 'Log Details', 'wp-security-audit-log' ),
				'content' => array( $this, 'wsal_step_log_details' ),
				'save'    => array( $this, 'wsal_step_log_details_save' ),
			),
			'login'          => array(
				'name'    => __( 'Log In', 'wp-security-audit-log' ),
				'content' => array( $this, 'wsal_step_login' ),
				'save'    => array( $this, 'wsal_step_login_save' ),
			),
			'404s'           => array(
				'name'    => __( '404s', 'wp-security-audit-log' ),
				'content' => array( $this, 'wsal_step_404s' ),
				'save'    => array( $this, 'wsal_step_404s_save' ),
			),
			'register'       => array(
				'name'    => __( 'User Registrations', 'wp-security-audit-log' ),
				'content' => array( $this, 'wsal_step_register' ),
				'save'    => array( $this, 'wsal_step_register_save' ),
			),
			'log_retention'  => array(
				'name'    => __( 'Log Retention', 'wp-security-audit-log' ),
				'content' => array( $this, 'wsal_step_log_retention' ),
				'save'    => array( $this, 'wsal_step_log_retention_save' ),
			),
			'finish'         => array(
				'name'    => __( 'Finish', 'wp-security-audit-log' ),
				'content' => array( $this, 'wsal_step_finish' ),
				'save'    => array( $this, 'wsal_step_finish_save' ),
			),
		);

		/**
		 * Filter: `Wizard Default Steps`
		 *
		 * WSAL filter to filter wizard steps before they are displayed.
		 *
		 * @param array $wizard_steps – Wizard Steps.
		 */
		$this->wizard_steps = apply_filters( 'wsal_wizard_default_steps', $wizard_steps );

		// Set current step.
		$current_step       = filter_input( INPUT_GET, 'current-step', FILTER_SANITIZE_STRING );
		$this->current_step = ! empty( $current_step ) ? $current_step : current( array_keys( $this->wizard_steps ) );

		// check if current step is a valid one.
		if ( ! array_key_exists( $this->current_step, $this->wizard_steps ) ) {
			$this->current_step = 'invalid-step';
		}

		/**
		 * Enqueue Styles.
		 */
		wp_enqueue_style(
			'wsal-wizard-css',
			$this->wsal->GetBaseUrl() . '/css/dist/wsal-wizard.build.css',
			array( 'dashicons', 'install', 'forms' ),
			filemtime( $this->wsal->GetBaseDir() . 'css/dist/wsal-wizard.build.css' )
		);

		/**
		 * Enqueue Scripts.
		 */
		 wp_register_script(
 			'wsal-wizard-js',
 			$this->wsal->GetBaseUrl() . '/js/dist/wsal-wizard.min.js',
 			array(  ),
 			filemtime( $this->wsal->GetBaseDir() . 'js/dist/wsal-wizard.min.js' ),
 			false
 		);

		wp_register_script(
			'wsal-common',
			$this->wsal->GetBaseUrl() . '/js/common.js',
			array( 'jquery' ),
			filemtime( $this->wsal->GetBaseDir() . '/js/common.js' ),
			true
 		);

		// Data array.
		$data_array = array(
			'ajaxURL'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => ( ( ! $this->wsal->settings()->CurrentUserCan( 'edit' ) ) && ! 'invalid-step' === $this->current_step ) ? wp_create_nonce( 'wsal-verify-wizard-page' ) : '',
			'usersError' => esc_html__( 'Specified value in not a user.', 'wp-security-audit-log' ),
			'rolesError' => esc_html__( 'Specified value in not a role.', 'wp-security-audit-log' ),
			'ipError'    => esc_html__( 'Specified value in not an IP address.', 'wp-security-audit-log' ),
		);
		wp_localize_script( 'wsal-wizard-js', 'wsalData', $data_array );

		$installer_script_data = array(
			'ajaxURL'           => admin_url( 'admin-ajax.php' ),
			'installing'        => __( 'Installing, please wait', 'wp-security-audit-log' ),
			'already_installed' => __( 'Already installed', 'wp-security-audit-log' ),
			'installed'         => __( 'Extension installed', 'wp-security-audit-log' ),
			'activated'         => __( 'Extension activated', 'wp-security-audit-log' ),
			'failed'            => __( 'Install failed', 'wp-security-audit-log' ),
		);
		wp_localize_script( 'wsal-common', 'wsalCommonData', $installer_script_data );


		/**
		 * Save Wizard Settings.
		 */
		$save_step = filter_input( INPUT_POST, 'save_step', FILTER_SANITIZE_STRING );
		if ( ! empty( $save_step ) && ! empty( $this->wizard_steps[ $this->current_step ]['save'] ) ) {
			call_user_func( $this->wizard_steps[ $this->current_step ]['save'] );
		}

		$this->setup_page_header();
		$this->setup_page_steps();
		$this->setup_page_content();
		$this->setup_page_footer();
		exit;
	}

	/**
	 * Setup Page Header.
	 */
	private function setup_page_header() {
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta name="viewport" content="width=device-width" />
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
			<title><?php esc_html_e( 'WP Activity Log &rsaquo; Setup Wizard', 'wp-security-audit-log' ); ?></title>
			<?php wp_print_scripts( 'wsal-wizard-js' ); ?>
			<?php wp_print_scripts( 'wsal-common' ); ?>

			<?php do_action( 'admin_print_styles' ); ?>
		</head>
		<body class="wsal-setup wp-core-ui">
			<h1 id="wsal-logo"><a href="https://wpactivitylog.com/?utm_source=plugin&utm_medium=referral&utm_campaign=WSAL&utm_content=wizard+configuration" target="_blank"><img src="<?php echo esc_url( $this->wsal->GetBaseUrl() ); ?>/img/wsal-logo-full.png" alt="WP Activity Log" /></a></h1>
		<?php
	}

	/**
	 * Setup Page Footer.
	 */
	private function setup_page_footer() {
		?>
			<div class="wsal-setup-footer">
				<?php if ( 'welcome' !== $this->current_step && 'finish' !== $this->current_step ) : // Don't show the link on the first & last step. ?>
					<a href="<?php echo esc_url( admin_url() ); ?>"><?php esc_html_e( 'Close Wizard', 'wp-security-audit-log' ); ?></a>
				<?php endif; ?>
			</div>
			</body>
		</html>
		<?php
	}

	/**
	 * Setup Page Steps.
	 */
	private function setup_page_steps() {
		?>
		<ul class="steps">
			<?php
			foreach ( $this->wizard_steps as $key => $step ) :
				if ( $key === $this->current_step ) :
					?>
					<li class="is-active"><?php echo esc_html( $step['name'] ); ?></li>
				<?php else : ?>
					<li></li>
					<?php
				endif;
			endforeach;
			?>
		</ul>
		<?php
	}

	/**
	 * Get Next Step URL.
	 *
	 * @return string
	 */
	private function get_next_step() {
		// Get current step.
		$current_step = $this->current_step;

		// Array of step keys.
		$keys = array_keys( $this->wizard_steps );
		if ( end( $keys ) === $current_step ) { // If last step is active then return WP Admin URL.
			return admin_url();
		}

		// Search for step index in step keys.
		$step_index = array_search( $current_step, $keys, true );
		if ( false === $step_index ) { // If index is not found then return empty string.
			return '';
		}

		// Return next step.
		return add_query_arg( 'current-step', $keys[ $step_index + 1 ] );
	}

	/**
	 * Gets a link to the first wizard step.
	 *
	 * @method get_welcome_step
	 * @since  4.0.2
	 * @return string
	 */
	private function get_welcome_step() {
		return remove_query_arg( 'current-step' );
	}

	/**
	 * Setup Page Content.
	 */
	private function setup_page_content() {
		?>
		<div class="wsal-setup-content">
			<?php
			if ( isset( $this->wizard_steps[ $this->current_step ]['content'] ) && ! empty( $this->wizard_steps[ $this->current_step ]['content'] && is_callable( $this->wizard_steps[ $this->current_step ]['content'] ) ) ) {
				call_user_func( $this->wizard_steps[ $this->current_step ]['content'] );
			} else {
				$this->render_invalid_step();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render method for any invalid steps a user happens to land on.
	 *
	 * @method render_invalid_step
	 * @since  4.0.2
	 */
	private function render_invalid_step() {
		?>
		<p><?php
		printf(
			/* translators: 1 - an opening link tag, 2 - a closing link tag. */
			esc_html__( 'You have reached an invaild step - %1$sreturn to the start of the wizard%2$s.', 'wp-security-audit-log' ),
			'<a href="' . esc_url( $this->get_welcome_step() ) . '">',
			'</a>'
		);
		?></p>
		<?php
	}

	/**
	 * Step View: `Welcome`
	 */
	private function wsal_step_welcome() {
		// Dismiss the setup modal on audit log.
		if ( $this->wsal->GetGlobalBooleanSetting( 'setup-modal-dismissed', false ) ) {
			$this->wsal->SetGlobalBooleanSetting( 'setup-modal-dismissed', true );
		}
		?>
		<p><?php esc_html_e( 'This wizard helps you configure the basic plugin settings. All these settings can be changed at a later stage from the plugin settings.', 'wp-security-audit-log' ); ?></p>

		<div class="wsal-setup-actions">
			<a class="button button-primary"
				href="<?php echo esc_url( $this->get_next_step() ); ?>">
				<?php esc_html_e( 'Start Configuring the Plugin', 'wp-security-audit-log' ); ?>
			</a>
			<a class="button button-secondary"
				href="<?php echo esc_url( admin_url() ); ?>">
				<?php esc_html_e( 'Exit Wizard', 'wp-security-audit-log' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Step View: `Log Details`
	 */
	private function wsal_step_log_details() {
		?>
		<form method="post" class="wsal-setup-form">
			<?php wp_nonce_field( 'wsal-step-log-details' ); ?>
			<h4><?php esc_html_e( 'Please select the level of detail for your WordPress activity logs:', 'wp-security-audit-log' ); ?></h4>
			<fieldset>
				<label for="basic">
					<input id="basic" name="wsal-details-level" type="radio" value="basic">
					<?php esc_html_e( 'Basic (I want a high level overview and I am not interested in the detail)', 'wp-security-audit-log' ); ?>
				</label>
				<br />
				<label for="geek">
					<input id="geek" name="wsal-details-level" type="radio" value="geek" checked>
					<?php esc_html_e( 'Geek (I want to know everything that is happening on my WordPress)', 'wp-security-audit-log' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Note: You can change the WordPress logging level from the plugin’s settings anytime.', 'wp-security-audit-log' ); ?></p>
			</fieldset>
			<div class="wsal-setup-actions">
				<button class="button button-primary" type="submit" name="save_step" value="<?php esc_attr_e( 'Next', 'wp-security-audit-log' ); ?>"><?php esc_html_e( 'Next', 'wp-security-audit-log' ); ?></button>
			</div>
		</form>
		<?php
	}

	/**
	 * Step Save: `Log Details`
	 */
	private function wsal_step_log_details_save() {
		// Check nonce.
		check_admin_referer( 'wsal-step-log-details' );

		// Get Log Details Step setting.
		$log_details = isset( $_POST['wsal-details-level'] ) ? sanitize_text_field( wp_unslash( $_POST['wsal-details-level'] ) ) : false;

		// Validate we have a level that is allowed.
		if ( ! in_array( $log_details, $this->valid_log_levels, true ) ) {
			// if we have an unexpected log level then use default: 'geek'.
			$log_details = $this->valid_log_levels[0];
		}

		// Save log details option.
		$this->wsal->SetGlobalSetting( 'details-level', $log_details );
		if ( ! empty( $log_details ) && 'basic' === $log_details ) {
			$this->wsal->settings()->set_basic_mode();
		} elseif ( ! empty( $log_details ) && 'geek' === $log_details ) {
			$this->wsal->settings()->set_geek_mode();
		}

		wp_safe_redirect( esc_url_raw( $this->get_next_step() ) );
		exit();
	}

	/**
	 * Step View: `Login Sensor`
	 */
	private function wsal_step_login() {
		?>
		<form method="post" class="wsal-setup-form">
			<?php wp_nonce_field( 'wsal-step-login' ); ?>
			<h4><?php esc_html_e( 'Do you or your users use other pages to log in to WordPress other than the default login page ( /wp-admin/ )?', 'wp-security-audit-log' ); ?></h4>
			<fieldset>
				<label for="wsal-frontend-events-login-yes">
					<input id="wsal-frontend-events-login-yes" name="wsal-frontend-login" type="radio" value="1">
					<?php esc_html_e( 'Yes, we use other pages to login to WordPress.', 'wp-security-audit-log' ); ?>
				</label>
				<br />
				<label for="wsal-frontend-events-login-no">
					<input id="wsal-frontend-events-login-no" name="wsal-frontend-login" type="radio" value="0" checked>
					<?php esc_html_e( 'No, we only use the default WordPress login page.', 'wp-security-audit-log' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'If your website is a membership or ecommerce website most probably you have more than one area from where the users can login. If you are not sure, select Yes.', 'wp-security-audit-log' ); ?></p>
			</fieldset>
			<!-- Question -->
			<p class="description"><?php esc_html_e( 'Note: You can change the WordPress activity log retention settings at any time from the plugin settings later on.', 'wp-security-audit-log' ); ?></p>
			<div class="wsal-setup-actions">
				<button class="button button-primary" type="submit" name="save_step" value="<?php esc_attr_e( 'Next', 'wp-security-audit-log' ); ?>"><?php esc_html_e( 'Next', 'wp-security-audit-log' ); ?></button>
			</div>
		</form>
		<?php
	}

	/**
	 * Step Save: `Login Sensor`
	 */
	private function wsal_step_login_save() {
		// Check nonce.
		check_admin_referer( 'wsal-step-login' );

		if ( isset( $_POST['wsal-frontend-login'] ) ) {
			$frontend_sensors = WSAL_Settings::get_frontend_events(); // Get the frontend sensors setting.
			$login_sensor     = sanitize_text_field( wp_unslash( $_POST['wsal-frontend-login'] ) );
			$login_sensor     = '1' === $login_sensor ? true : false; // Update the sensor option.

			$frontend_sensors['login'] = $login_sensor;
			$this->wsal->settings()->set_frontend_events( $frontend_sensors );
		}

		wp_safe_redirect( esc_url_raw( $this->get_next_step() ) );
		exit();
	}

	/**
	 * Step View: `404s Sensor`
	 */
	private function wsal_step_404s() {
		?>
		<form method="post" class="wsal-setup-form">
			<?php wp_nonce_field( 'wsal-step-404s' ); ?>
			<h4><?php esc_html_e( 'Do you want to keep a log of (non-logged in) visitors’ requests to non-existing URLs which generate a HTTP 404 error response?', 'wp-security-audit-log' ); ?></h4>
			<fieldset>
				<label for="wsal-frontend-events-system-yes">
					<input id="wsal-frontend-events-system-yes" name="wsal-frontend-system" type="radio" value="1">
					<?php esc_html_e( 'Yes', 'wp-security-audit-log' ); ?>
				</label>
				<br />
				<label for="wsal-frontend-events-system-no">
					<input id="wsal-frontend-events-system-no" name="wsal-frontend-system" type="radio" value="0" checked>
					<?php esc_html_e( 'No', 'wp-security-audit-log' ); ?>
				</label>
			</fieldset>
			<!-- Question -->
			<p class="description"><?php esc_html_e( 'Note: You can change the WordPress activity log retention settings at any time from the plugin settings later on.', 'wp-security-audit-log' ); ?></p>
			<div class="wsal-setup-actions">
				<button class="button button-primary" type="submit" name="save_step" value="<?php esc_attr_e( 'Next', 'wp-security-audit-log' ); ?>"><?php esc_html_e( 'Next', 'wp-security-audit-log' ); ?></button>
			</div>
		</form>
		<?php
	}

	/**
	 * Step Save: `404s Sensor`
	 */
	private function wsal_step_404s_save() {
		// Check nonce.
		check_admin_referer( 'wsal-step-404s' );

		if ( isset( $_POST['wsal-frontend-system'] ) ) {
			$frontend_sensors = WSAL_Settings::get_frontend_events(); // Get the frontend sensors setting.
			$system_sensor    = sanitize_text_field( wp_unslash( $_POST['wsal-frontend-system'] ) );
			$system_sensor    = '1' === $system_sensor ? true : false; // Update the sensor option.

			$frontend_sensors['system'] = $system_sensor;
			$this->wsal->settings()->set_frontend_events( $frontend_sensors );
		}

		wp_safe_redirect( esc_url_raw( $this->get_next_step() ) );
		exit();
	}

	/**
	 * Step View: `Register Sensor`
	 */
	private function wsal_step_register() {
		?>
		<form method="post" class="wsal-setup-form">
			<?php wp_nonce_field( 'wsal-step-frontend-register' ); ?>
			<h4><?php esc_html_e( 'Can visitors register for a user on your website?', 'wp-security-audit-log' ); ?></h4>
			<fieldset>
				<label for="wsal-frontend-events-register-yes">
					<input id="wsal-frontend-events-register-yes" name="wsal-frontend-register" type="radio" value="1">
					<?php esc_html_e( 'Yes', 'wp-security-audit-log' ); ?>
				</label>
				<br />
				<label for="wsal-frontend-events-register-no">
					<input id="wsal-frontend-events-register-no" name="wsal-frontend-register" type="radio" value="0" checked>
					<?php esc_html_e( 'No', 'wp-security-audit-log' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'If you are not sure about this setting, check if the Membership setting in the WordPress General settings is checked or not. If it is not checked (default) select No.', 'wp-security-audit-log' ); ?></p>
			</fieldset>
			<!-- Question -->
			<p class="description"><?php esc_html_e( 'Note: You can change the WordPress activity log retention settings at any time from the plugin settings later on.', 'wp-security-audit-log' ); ?></p>
			<div class="wsal-setup-actions">
				<button class="button button-primary" type="submit" name="save_step" value="<?php esc_attr_e( 'Next', 'wp-security-audit-log' ); ?>"><?php esc_html_e( 'Next', 'wp-security-audit-log' ); ?></button>
			</div>
		</form>
		<?php
	}

	/**
	 * Step Save: `Register Sensor`
	 */
	private function wsal_step_register_save() {
		// Check nonce.
		check_admin_referer( 'wsal-step-frontend-register' );

		if ( isset( $_POST['wsal-frontend-register'] ) ) {
			$frontend_sensors = WSAL_Settings::get_frontend_events(); // Get the frontend sensors setting.
			$register_sensor  = sanitize_text_field( wp_unslash( $_POST['wsal-frontend-register'] ) );
			$register_sensor  = '1' === $register_sensor ? true : false; // Update the sensor option.

			$frontend_sensors['register'] = $register_sensor;
			$this->wsal->settings()->set_frontend_events( $frontend_sensors );
		}

		wp_safe_redirect( esc_url_raw( $this->get_next_step() ) );
		exit();
	}

	/**
	 * Step View: `Log Retention`
	 */
	private function wsal_step_log_retention() {
		?>
		<form method="post" class="wsal-setup-form">
			<?php wp_nonce_field( 'wsal-step-log-retention' ); ?>
			<h4>
				<?php esc_html_e( 'How long do you want to keep the data in the WordPress activity Log?', 'wp-security-audit-log' ); ?>
			</h4>
			<fieldset>
				<label for="6">
					<input id="6" name="wsal-pruning-limit" type="radio" value="6" checked />
					<?php esc_html_e( '6 months (data older than 6 months will be deleted)', 'wp-security-audit-log' ); ?>
				</label>
				<br />
				<label for="12">
					<input id="12" name="wsal-pruning-limit" type="radio" value="12" />
					<?php esc_html_e( '12 months (data older than 12 months will be deleted)', 'wp-security-audit-log' ); ?>
				</label>
				<br />
				<label for="none">
					<input id="none" name="wsal-pruning-limit" type="radio" value="none" />
					<?php esc_html_e( 'Keep all data.', 'wp-security-audit-log' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Note: You can change the WordPress activity log retention settings at any time from the plugin settings later on.', 'wp-security-audit-log' ); ?>
				</p>
			</fieldset>

			<div class="wsal-setup-actions">
				<button class="button button-primary"
					type="submit"
					name="save_step"
					value="<?php esc_attr_e( 'Next', 'wp-security-audit-log' ); ?>">
					<?php esc_html_e( 'Next', 'wp-security-audit-log' ); ?>
				</button>
			</div>
		</form>

		<p class="description">
			<em>
				<?php
				// Step help text.
				$step_help = __( 'The plugin stores the data in the WordPress database in a very efficient way, though the more data you keep the more hard disk space it will consume. If you need need to retain a lot of data we would recommend you to <a href="https://wpactivitylog.com/features/?utm_source=plugin&utm_medium=referral&utm_campaign=WSAL&utm_content=wizard+configuration" target="_blank">upgrade to Premium</a> and use the Database tools to store the WordPress activity log in an external database.', 'wp-security-audit-log' );

				echo wp_kses( $step_help, $this->wsal->allowed_html_tags );
				?>
			</em>
		</p>
		<?php
	}

	/**
	 * Step Save: `Log Retention`
	 */
	private function wsal_step_log_retention_save() {
		// Verify nonce.
		check_admin_referer( 'wsal-step-log-retention' );

		// Get log retention time setting.
		$pruning_limit = isset( $_POST['wsal-pruning-limit'] ) ? sanitize_text_field( wp_unslash( $_POST['wsal-pruning-limit'] ) ) : false;

		// validate the prune time.
		if ( ! in_array( (string) $pruning_limit, $this->valid_prune_times, true ) ) {
			// if $pruning_limit is not valid value then use default - 6.
			$pruning_limit = $this->valid_prune_times[0];
		}

		// Save log retention setting.
		if ( ! empty( $pruning_limit ) ) {
			switch ( $pruning_limit ) {
				case '6':
					// 6 months.
					$this->wsal->SetGlobalBooleanSetting( 'pruning-date-e', true );
					$this->wsal->SetGlobalSetting( 'pruning-date', $pruning_limit . ' months' );
					break;

				case '12':
					// 12 months.
					$this->wsal->SetGlobalBooleanSetting( 'pruning-date-e', true );
					$this->wsal->SetGlobalSetting( 'pruning-date', $pruning_limit . ' months' );
					break;

				case 'none':
					// None.
					$this->wsal->SetGlobalBooleanSetting( 'pruning-date-e', false );
					break;

				default:
					break;
			}
		}

		wp_safe_redirect( esc_url_raw( $this->get_next_step() ) );
		exit();
	}

	/**
	 * Get Token Type.
	 *
	 * @param string $token - Token type.
	 */
	protected function get_token_type( $token ) {
		return $this->wsal->settings()->get_token_type( $token );
	}

	/**
	 * Step View: `Finish`
	 */
	private function wsal_step_finish() {
		?>
		<p><?php esc_html_e( 'Your plugin is all set and it is ready to start keeping a record of everything that is happening on your WordPress in a WordPress activity log.', 'wp-security-audit-log' ); ?></p>
		<p><?php esc_html_e( 'Below are a few useful links you might need to refer to:', 'wp-security-audit-log' ); ?></p>

		<ul>
			<li>
				<a href="https://wpactivitylog.com/support/kb/getting-started-wp-activity-log/?utm_source=plugin&utm_medium=referral&utm_campaign=WSAL&utm_content=wizard+configuration" target="_blank">
					<?php esc_html_e( 'Getting started with the WP Activity Log plugin', 'wp-security-audit-log' ); ?>
				</a>
			</li>
			<li>
				<a href="https://wpactivitylog.com/support/kb/?utm_source=plugin&utm_medium=referral&utm_campaign=WSAL&utm_content=wizard+configuration" target="_blank">
					<?php esc_html_e( 'Knowledge Base & Support Documents', 'wp-security-audit-log' ); ?>
				</a>
			</li>
			<li>
				<a href="https://wpactivitylog.com/benefits-wordpress-activity-log/?utm_source=plugin&utm_medium=referral&utm_campaign=WSAL&utm_content=wizard+configuration" target="_blank">
					<?php esc_html_e( 'Benefits of keeping a WordPress activity log', 'wp-security-audit-log' ); ?>
				</a>
			</li>
		</ul>

		<p><?php echo wp_kses( __( 'We trust this plugin meets all your activity log requirements. Should you encounter any problems, have feature requests or would like to share some feedback, <a href="https://wpactivitylog.com/contact/?utm_source=plugin&utm_medium=referral&utm_campaign=WSAL&utm_content=wizard+configuration" target="_blank">please get in touch!</a>', 'wp-security-audit-log' ), $this->wsal->allowed_html_tags ); ?></p>

		<form method="post" class="wsal-setup-form">
			<?php wp_nonce_field( 'wsal-step-finish' ); ?>
			<div class="wsal-setup-actions">
				<button class="button button-primary"
					type="submit"
					name="save_step"
					value="<?php esc_attr_e( 'Finish', 'wp-security-audit-log' ); ?>">
					<?php esc_html_e( 'Finish', 'wp-security-audit-log' ); ?>
				</button>
			</div>
		</form>
		<?php
	}

	/**
	 * Step Save: `Finish`
	 */
	private function wsal_step_finish_save() {
		// Verify nonce.
		check_admin_referer( 'wsal-step-finish' );

		// Mark the finish of the setup.
		$this->wsal->SetGlobalBooleanSetting( 'setup-complete', true );

		wp_safe_redirect( esc_url_raw( $this->get_next_step() ) );
		exit();
	}

	/**
	 * 3rd Party plugins
	 */
	function wsal_add_wizard_step( $wizard_steps ) {
		$new_wizard_steps = array(
			'test'        => array(
				'name'    => __( 'Third Party Extensions', 'wp-security-audit-log' ),
				'content' => array( $this, 'addons_step' ),
				'save'    => array( $this, 'addons_step_save' ),
			),
		);

		// Count number of items in the array.
		$number_of_steps = count( $wizard_steps );
		// Subtract 1, as we want to insert our step one before the last item.
		$number_of_steps = $number_of_steps - 1;

		// Slice the steps up, so we have 2 parts we can insert our slide between.
		$first_part = array_slice( $wizard_steps, 0, $number_of_steps, true );
		$last_part = array_slice( $wizard_steps, -1, 1, true  );

		// combine the two arrays.
		$wizard_steps = $first_part + $new_wizard_steps + $last_part;

		return $wizard_steps;
	}

	private function addons_step() {
		$our_plugins = WSAL_PluginInstallAndActivate::get_installable_plugins();

		// Grab list of instaleld plugins.
		$all_plugins      = get_plugins();
		$plugin_filenames = array();
		foreach ( $all_plugins as $plugin => $info ) {
			// here we strip all of the plugin slug, leaving just the filename itself. Neat!
			$plugin_filenames[] = preg_replace( '/\\.[^.\\s]{3,4}$/', '', substr( basename( json_encode( $plugin ) ), 0, -1 ) );
		}

		// Grab list of plugins we have addons for.
		$predefined_plugins = array_column( $our_plugins, 'addon_for' );

		// Loop through plugins and create an array of slugs, we will compare these agains the plugins we have addons for.
		$we_have_addon = array_intersect( $plugin_filenames, $predefined_plugins );

		?>
		<form method="post" class="wsal-setup-form">
			<?php wp_nonce_field( 'wsal-step-addon' ); ?>
			<h4><?php esc_html_e( 'Monitoring changes done in third party plugins', 'wp-security-audit-log' ); ?></h4>
			<p><?php esc_html_e( 'We noticed that the below plugins are installed on this website. You can install our extensions to also keep a log of changes users do on these plugins.', 'wp-security-audit-log' ); ?></p>
			<?php
			// Create a nonce to pass through via data attr.
			$nonce      = wp_create_nonce( 'wsal-install-addon' );
			$skip_addon = false;
			// Loop through plugins and output.
			foreach ( $our_plugins as $details ) {
				$disable_button = '';
				if ( is_plugin_active( $details['plugin_slug'] ) || 'wsal-wpforms.php' === basename( $details['plugin_slug'] ) && function_exists( 'wsal_wpforms_init_actions' ) || 'wsal-bbpress.php' === basename( $details['plugin_slug'] ) && function_exists( 'wsal_bbpress_init_actions' ) ) {
					$disable_button = 'disabled';
				}
				if ( ! in_array( $details['addon_for'], $we_have_addon ) ) {
					continue;
				}
				// Check if this is actually an addon for something, otherwise bail.
				if ( ! isset( $details['addon_for'] ) || ! isset( $details['image_filename'] ) ) {
					break;
				}
				?>

				<div class="addon-wrapper">
					<img src="<?php echo esc_url( trailingslashit( WSAL_BASE_URL ) . 'img/addons/' . $details['image_filename'] ); ?>">
					<div class="addon-content">
						<h5><?php esc_html_e( 'Extension for ', 'wp-security-audit-log' ); ?><?php echo esc_html( $details['title'] ); ?></h5>
						<p><?php echo sanitize_text_field( $details['plugin_description'] ); ?></p>
						<p><button class="install-addon button button-primary <?php echo esc_attr( $disable_button ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-plugin-slug="<?php echo esc_attr( $details['plugin_slug'] ); ?>" data-plugin-download-url="<?php echo esc_url( $details['plugin_url'] ); ?>" data-plugin-event-tab-id="<?php echo esc_attr( $details['event_tab_id'] ); ?>">
							<?php
							if ( WSAL_PluginInstallAndActivate::is_plugin_installed( $details['plugin_slug'] ) && ! is_plugin_active( $details['plugin_slug'] ) ) {
								esc_html_e( 'Extension installed, activate now?', 'wp-security-audit-log' );
							} elseif ( WSAL_PluginInstallAndActivate::is_plugin_installed( $details['plugin_slug'] ) && is_plugin_active( $details['plugin_slug'] ) || 'wsal-wpforms.php' === basename( $details['plugin_slug'] ) && function_exists( 'wsal_wpforms_init_actions' ) || 'wsal-bbpress.php' === basename( $details['plugin_slug'] ) && function_exists( 'wsal_bbpress_init_actions' ) ) {
								esc_html_e( 'Extension installed', 'wp-security-audit-log' );
							} else {
									esc_html_e( 'Install Extension', 'wp-security-audit-log' );
							}
							?>
						</button><span class="spinner" style="display: none; visibility: visible; float: none; margin: 0 0 0 8px;"></span></p>
					</div>
				</div>
				<?php
				}
				?>
				<div class="wsal-setup-actions">
					<button class="button button-primary"
						type="submit"
						name="save_step"
						value="<?php esc_attr_e( 'Next', 'wp-security-audit-log' ); ?>">
						<?php esc_html_e( 'Next', 'wp-security-audit-log' ); ?>
					</button>
				</div>
			</form>
		<?php
	}

	/**
	 * Step Save: `Addons`
	 */
	private function addons_step_save() {
		// Check nonce.
		check_admin_referer( 'wsal-step-addon' );

		wp_safe_redirect( esc_url_raw( $this->get_next_step() ) );
		exit();
	}

}
