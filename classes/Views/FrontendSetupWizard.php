<?php
/**
 * View: WSAL Frontend Setup
 *
 * WSAL frontend setup class file.
 *
 * @since 3.2.3
 * @package Wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class: WSAL Frontend Setup Wizard.
 *
 * WSAL setup wizard class which manages the functionality
 * related to setup.
 */
final class WSAL_Views_FrontendSetupWizard {

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
	 * Option name
	 *
	 * @var string
	 */
	private $option_name = 'frontend-events';

	/**
	 * Method: Constructor.
	 *
	 * @param WpSecurityAuditLog $wsal – Instance of main plugin.
	 */
	public function __construct( WpSecurityAuditLog $wsal ) {
		$this->wsal = $wsal;
		add_action( 'admin_menu', array( $this, 'admin_menus' ), 10 );
		add_action( 'admin_init', array( $this, 'setup_page' ), 10 );
	}

	/**
	 * Ajax handler to verify setting token.
	 */
	public function setup_check_security_token() {
		if ( ! $this->wsal->settings->CurrentUserCan( 'view' ) ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html__( 'Access Denied.', 'wp-security-audit-log' ),
				)
			);
			die();
		}

		//@codingStandardsIgnoreStart
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : false;
		//@codingStandardsIgnoreEnd

		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wsal-verify-wizard-page' ) ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html__( 'Nonce verification failed.', 'wp-security-audit-log' ),
				)
			);
			die();
		}
		die();
	}

	/**
	 * Add setup admin page.
	 */
	public function admin_menus() {
		add_dashboard_page( '', '', 'manage_options', 'wsal-front-setup', '' );
	}

	/**
	 * Setup Page Start.
	 */
	public function setup_page() {
		// Get page argument from $_GET array.
		$page = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING );
		if ( empty( $page ) || 'wsal-front-setup' !== $page ) {
			return;
		}

		/**
		 * Wizard Steps.
		 */
		$this->wizard_steps = array(
			'welcome'  => array(
				'name'    => __( 'Welcome', 'wp-security-audit-log' ),
				'content' => array( $this, 'wsal_step_welcome' ),
			),
			'register' => array(
				'name'    => __( 'User Registrations', 'wp-security-audit-log' ),
				'content' => array( $this, 'wsal_step_register' ),
				'save'    => array( $this, 'wsal_step_register_save' ),
			),
			'login'    => array(
				'name'    => __( 'Log In', 'wp-security-audit-log' ),
				'content' => array( $this, 'wsal_step_login' ),
				'save'    => array( $this, 'wsal_step_login_save' ),
			),
			'404s'     => array(
				'name'    => __( '404s', 'wp-security-audit-log' ),
				'content' => array( $this, 'wsal_step_404s' ),
				'save'    => array( $this, 'wsal_step_404s_save' ),
			),
			'finish'   => array(
				'name'    => __( 'Finish', 'wp-security-audit-log' ),
				'content' => array( $this, 'wsal_step_finish' ),
				'save'    => array( $this, 'wsal_step_finish_save' ),
			),
		);

		// Set current step.
		$current_step       = filter_input( INPUT_GET, 'current-step', FILTER_SANITIZE_STRING );
		$this->current_step = ! empty( $current_step ) ? $current_step : current( array_keys( $this->wizard_steps ) );

		/**
		 * Enqueue Styles.
		 */
		wp_enqueue_style(
			'wsal-wizard-css',
			$this->wsal->GetBaseUrl() . '/css/dist/wsal-wizard.build.css',
			array( 'dashicons', 'install', 'forms' ),
			filemtime( $this->wsal->GetBaseDir() . 'css/dist/wsal-wizard.build.css' )
		);

		// Data array.
		$data_array = array(
			'ajaxURL' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'wsal-verify-wizard-page' ),
		);
		wp_localize_script( 'wsal-wizard-js', 'wsalData', $data_array );

		/**
		 * Save Wizard Settings.
		 */
		$save_step = filter_input( INPUT_POST, 'save_step', FILTER_SANITIZE_STRING );
		if ( ! empty( $save_step ) && ! empty( $this->wizard_steps[ $this->current_step ]['save'] ) ) {
			call_user_func( $this->wizard_steps[ $this->current_step ]['save'] );
		}

		/**
		 * Close Wizard Settings.
		 */
		$exit_wizard = filter_input( INPUT_GET, 'exit-wizard', FILTER_SANITIZE_STRING );
		if ( ! empty( $exit_wizard ) ) {
			call_user_func( array( $this, 'wsal_exit_frontend_wizard' ) );
		}

		ob_start();
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
			<title><?php esc_html_e( 'WP Security Audit Log &rsaquo; Setup Wizard', 'wp-security-audit-log' ); ?></title>
			<?php wp_print_scripts( 'wsal-wizard-js' ); ?>
			<?php do_action( 'admin_print_styles' ); ?>
			<?php do_action( 'admin_head' ); ?>
		</head>
		<body class="wsal-setup wp-core-ui">
			<h1 id="wsal-logo"><a href="https://wpsecurityauditlog.com/" target="_blank"><img src="<?php echo esc_url( $this->wsal->GetBaseUrl() ); ?>/img/wsal-logo-full.png" alt="WP Security Audit Log" /></a></h1>
		<?php
	}

	/**
	 * Setup Page Footer.
	 */
	private function setup_page_footer() {
		?>
			<div class="wsal-setup-footer">
				<?php if ( 'welcome' !== $this->current_step && 'finish' !== $this->current_step ) : // Don't show the link on the first & last step. ?>
					<a href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'page'        => 'wsal-front-setup',
								'exit-wizard' => wp_create_nonce( 'wsal-exit-wizard' ),
							),
							admin_url()
						)
					);
					?>
								"><?php esc_html_e( 'Close Wizard', 'wp-security-audit-log' ); ?></a>
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
	 * Setup Page Content.
	 */
	private function setup_page_content() {
		?>
		<div class="wsal-setup-content">
			<?php
			if ( ! empty( $this->wizard_steps[ $this->current_step ]['content'] ) ) {
				call_user_func( $this->wizard_steps[ $this->current_step ]['content'] );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Step View: `Welcome`
	 */
	private function wsal_step_welcome() {
		?>
		<p><?php esc_html_e( 'In this update, we have improved the plugin\'s front-end sensors and performance. Use this quick 4-step wizard to fine tune the plugin for your website.', 'wp-security-audit-log' ); ?></p>

		<div class="wsal-setup-actions">
			<a class="button button-primary"
				href="<?php echo esc_url( $this->get_next_step() ); ?>">
				<?php esc_html_e( 'Start Configuring front-end sensors', 'wp-security-audit-log' ); ?>
			</a>
			<a class="button button-secondary"
				href="
				<?php
				echo esc_url(
					add_query_arg(
						array(
							'page'        => 'wsal-front-setup',
							'exit-wizard' => wp_create_nonce( 'wsal-exit-wizard' ),
						),
						admin_url()
					)
				);
				?>
						">
				<?php esc_html_e( 'Exit Wizard', 'wp-security-audit-log' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Step View: `front end register sensors`
	 */
	private function wsal_step_register() {
		?>
		<form method="post" class="wsal-setup-form">
			<?php wp_nonce_field( 'wsal-step-registers' ); ?>
			<h4><?php esc_html_e( 'Can visitors register for a user on your website?', 'wp-security-audit-log' ); ?></h4>
			<fieldset>
				<label for="wsal-frontend-events-register-yes">
					<input id="wsal-frontend-events-register-yes" name="wsal-front-end-register" type="radio" value="1">
					<?php esc_html_e( 'Yes', 'wp-security-audit-log' ); ?>
				</label>
				<br />
				<label for="wsal-frontend-events-register-no">
					<input id="wsal-frontend-events-register-no" name="wsal-front-end-register" type="radio" value="0" checked>
					<?php esc_html_e( 'No', 'wp-security-audit-log' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'If you are not sure about this setting, check if the Membership setting in the WordPress General settings is checked or not. If it is not checked (default) select No.', 'wp-security-audit-log' ); ?></p>
			</fieldset>
			<!-- Question -->
			<div class="wsal-setup-actions">
				<button class="button button-primary" type="submit" name="save_step" value="<?php esc_attr_e( 'Next', 'wp-security-audit-log' ); ?>"><?php esc_html_e( 'Next', 'wp-security-audit-log' ); ?></button>
			</div>
		</form>
		<?php
	}

	/**
	 * Step Save: `Log Details`
	 */
	private function wsal_step_register_save() {
		// Check nonce.
		check_admin_referer( 'wsal-step-registers' );

		if ( isset( $_POST['wsal-front-end-register'] ) ) {
			// Save frontend register sensors.
			$sensors_option               = $this->wsal::OPT_PRFX . $this->option_name;
			$frontend_sensors             = get_option( $sensors_option ); // Get the frontend sensors setting.
			$register_sensor              = sanitize_text_field( wp_unslash( $_POST['wsal-front-end-register'] ) );
			$frontend_sensors['register'] = $register_sensor;
			// Update option
			update_option( $sensors_option, $frontend_sensors );
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
			$sensors_option   = $this->wsal::OPT_PRFX . $this->option_name;
			$frontend_sensors = get_option( $sensors_option ); // Get the frontend sensors setting.
			$login_sensor     = sanitize_text_field( wp_unslash( $_POST['wsal-frontend-login'] ) );
			$login_sensor     = '0' === $login_sensor ? false : $login_sensor; // Update the sensor option.

			$frontend_sensors['login'] = $login_sensor;
			update_option( $sensors_option, $frontend_sensors );
		}

		wp_safe_redirect( esc_url_raw( $this->get_next_step() ) );
		exit();
	}

	/**
	 * Step View: `System Sensor`
	 */
	private function wsal_step_404s() {
		?>
		<form method="post" class="wsal-setup-form">
			<?php wp_nonce_field( 'wsal-step-frontend-system' ); ?>
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
			<div class="wsal-setup-actions">
				<button class="button button-primary" type="submit" name="save_step" value="<?php esc_attr_e( 'Next', 'wp-security-audit-log' ); ?>"><?php esc_html_e( 'Next', 'wp-security-audit-log' ); ?></button>
			</div>
		</form>
		<?php
	}

	/**
	 * Step Save: `System Sensor`
	 */
	private function wsal_step_404s_save() {
		// Check nonce.
		check_admin_referer( 'wsal-step-frontend-system' );

		// Update system field
		if ( isset( $_POST['wsal-frontend-system'] ) ) {
			$sensors_option   = $this->wsal::OPT_PRFX . $this->option_name;
			$frontend_sensors = get_option( $sensors_option ); // Get the frontend sensors setting.
			$system_sensor    = sanitize_text_field( wp_unslash( $_POST['wsal-frontend-system'] ) );
			$system_sensor    = '0' === $system_sensor ? false : $system_sensor; // Update the sensor option.

			$frontend_sensors['system'] = $system_sensor;
			update_option( $sensors_option, $frontend_sensors );
		}

		wp_safe_redirect( esc_url_raw( $this->get_next_step() ) );
		exit();
	}

	/**
	 * Step View: `Finish`
	 */
	private function wsal_step_finish() {
		?>
		<p><?php esc_html_e( 'All the new settings have been applied. You can change these settings from the Front-end Events in the Enable/Disable Events section.', 'wp-security-audit-log' ); ?></p>

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
		$this->wsal->SetGlobalOption( 'front-end-setup-complete', 'yes' );

		wp_safe_redirect( esc_url_raw( $this->get_next_step() ) );
		exit();
	}

	/**
	 * Exit Wizard
	 */
	private function wsal_exit_frontend_wizard() {
		if ( isset( $_GET['exit-wizard'] ) && wp_verify_nonce( $_GET['exit-wizard'], 'wsal-exit-wizard' ) ) {
			// Mark the finish of the setup.
			$this->wsal->SetGlobalOption( 'front-end-setup-complete', 'yes' );
			wp_safe_redirect( admin_url() );
			exit();
		}

	}
}
