<?php
/**
 * View: WSAL Setup
 *
 * WSAL setup class file.
 *
 * @since      3.2.3
 * @package    wsal
 * @subpackage views
 */

declare(strict_types=1);

namespace WSAL\Views;

use WSAL\Helpers\WP_Helper;
use WSAL\Helpers\View_Manager;
use WSAL\Helpers\Plugins_Helper;
use WSAL\Helpers\Settings_Helper;
use WSAL\Helpers\Plugin_Settings_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( '\WSAL\Views\Setup_Wizard' ) ) {
	/**
	 * Class: WSAL Setup Wizard.
	 *
	 * WSAL setup wizard class which manages the functionality
	 * related to setup.
	 *
	 * @package    wsal
	 * @subpackage views
	 *
	 * @since 5.0.0
	 */
	final class Setup_Wizard {

		/**
		 * Wizard Steps
		 *
		 * @var array
		 *
		 * @since 5.0.0
		 */
		private static $wizard_steps;

		/**
		 * Current Step
		 *
		 * @var string
		 *
		 * @since 5.0.0
		 */
		private static $current_step;

		/**
		 * List of all the valid inputs we will accept for log levels.
		 *
		 * @var array
		 *
		 * @since 5.0.0
		 */
		private static $valid_log_levels = array( 'geek', 'basic' );

		/**
		 * List if all the valid inputs we will accept for prune times.
		 *
		 * @var array
		 *
		 * @since 5.0.0
		 */
		private static $valid_prune_times = array( '3', '6', '12', 'none' );

		/**
		 * Method: init.
		 *
		 * @since 5.0.0
		 */
		public static function init() {

			if ( Settings_Helper::current_user_can( 'edit' ) ) {
				\add_action( 'admin_init', array( __CLASS__, 'setup_page' ), 10 );
				\add_action( 'admin_menu', array( __CLASS__, 'admin_menus' ), 10 );
				\add_action( 'network_admin_menu', array( __CLASS__, 'admin_menus' ), 10 );
				\add_action( 'wp_ajax_setup_check_security_token', array( __CLASS__, 'setup_check_security_token' ) );
			}
		}

		/**
		 * Ajax handler to verify setting token.
		 *
		 * @since 5.0.0
		 */
		public static function setup_check_security_token() {
			if ( ! Settings_Helper::current_user_can( 'edit' ) ) {
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

			if ( empty( $nonce ) || ! \wp_verify_nonce( $nonce, 'wsal-verify-wizard-page' ) ) {
				echo \wp_json_encode(
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

			echo \wp_json_encode(
				array(
					'success'   => true,
					'token'     => $token,
					'tokenType' => esc_html( Plugin_Settings_Helper::get_token_type( $token ) ),
				)
			);
			die();
		}

		/**
		 * Add setup admin page.
		 *
		 * @since 5.0.0
		 */
		public static function admin_menus() {
			// this is an empty title because we do not want it to display.
			add_dashboard_page( '', '', 'manage_options', 'wsal-setup', '' );
			// hide it via CSS as well so screen readers pass over it.
			add_action(
				'admin_head',
				function () {
					?>
				<style>
				.wp-submenu a[href="wsal-setup"]{
					display: none !important;
				}
				.wsal_upgrade_icon {
					position: relative;
					display: block;
				}

				.wsal_upgrade_icon:after {
					content: "";
					display: block;
						background: url( "<?php echo WSAL_BASE_URL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>/img/add-icon.png") no-repeat;
					width: 34px;
					height: 34px;
					float: right;
					top: 8px;
					position: absolute;
					right: -10px;
					background-size: 14px;
					-webkit-filter: invert(100%);
					filter: invert(100%);
					opacity: 0.8;
				}
				</style>
					<?php
				}
			);
		}

		/**
		 * Setup Page Start.
		 *
		 * @since 5.0.0
		 */
		public static function setup_page() {
			// Get page argument from $_GET array.

			$page = ( isset( $_GET['page'] ) ) ? \sanitize_text_field( \wp_unslash( $_GET['page'] ) ) : '';
			if ( empty( $page ) || 'wsal-setup' !== $page || ! ( current_user_can( 'manage_options' ) ) ) {
				return;
			}

			/**
			 * Wizard Steps.
			 */
			$wizard_steps = array(
				'welcome'       => array(
					'name'    => __( 'Welcome', 'wp-security-audit-log' ),
					'content' => array( __CLASS__, 'wsal_step_welcome' ),
				),
				'log_details'   => array(
					'name'    => __( 'Log Details', 'wp-security-audit-log' ),
					'content' => array( __CLASS__, 'wsal_step_log_details' ),
					'save'    => array( __CLASS__, 'wsal_step_log_details_save' ),
				),
				'login'         => array(
					'name'    => __( 'Log In', 'wp-security-audit-log' ),
					'content' => array( __CLASS__, 'wsal_step_login' ),
					'save'    => array( __CLASS__, 'wsal_step_login_save' ),
				),
				'register'      => array(
					'name'    => __( 'User Registrations', 'wp-security-audit-log' ),
					'content' => array( __CLASS__, 'wsal_step_register' ),
					'save'    => array( __CLASS__, 'wsal_step_register_save' ),
				),
				'log_retention' => array(
					'name'    => __( 'Log Retention', 'wp-security-audit-log' ),
					'content' => array( __CLASS__, 'wsal_step_log_retention' ),
					'save'    => array( __CLASS__, 'wsal_step_log_retention_save' ),
				),
				'finish'        => array(
					'name'    => __( 'Finish', 'wp-security-audit-log' ),
					'content' => array( __CLASS__, 'wsal_step_finish' ),
					'save'    => array( __CLASS__, 'wsal_step_finish_save' ),
				),
			);

			/**
			 * Filter: `Wizard Default Steps`
			 *
			 * WSAL filter to filter wizard steps before they are displayed.
			 *
			 * @param array $wizard_steps – Wizard Steps.
			 */
			self::$wizard_steps = apply_filters( 'wsal_wizard_default_steps', $wizard_steps );

			// Set current step.
			$current_step       = ( isset( $_GET['current-step'] ) ) ? \sanitize_text_field( \wp_unslash( $_GET['current-step'] ) ) : null;
			self::$current_step = ! empty( $current_step ) ? $current_step : current( array_keys( self::$wizard_steps ) );

			// check if current step is a valid one.
			if ( ! array_key_exists( self::$current_step, self::$wizard_steps ) ) {
				self::$current_step = 'invalid-step';
			}

			/**
			 * Enqueue Styles.
			 */
			$wizard_css = View_Manager::get_asset_path( 'css/dist/', 'wsal-wizard', 'css', false );
			wp_enqueue_style(
				'wsal-wizard-css',
				WSAL_BASE_URL . '/' . $wizard_css,
				array( 'dashicons', 'install', 'forms' ),
				WSAL_VERSION
			);

			/**
			 * Enqueue Scripts.
			 */
			$wizard_js = View_Manager::get_asset_path( 'js/dist/', 'wsal-wizard', 'js', false );
			wp_register_script(
				'wsal-wizard-js',
				WSAL_BASE_URL . '/' . $wizard_js,
				array( 'jquery' ),
				WSAL_VERSION,
				false
			);

			$common_js = '/js/common.js';
			wp_register_script(
				'wsal-common',
				WSAL_BASE_URL . $common_js,
				array( 'jquery' ),
				WSAL_VERSION,
				true
			);

			// Data array.
			$data_array = array(
				'ajaxURL'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => ( ( ! Settings_Helper::current_user_can( 'edit' ) ) && ! 'invalid-step' === self::$current_step ) ? wp_create_nonce( 'wsal-verify-wizard-page' ) : '',
				'usersError' => esc_html__( 'Specified value in not a user.', 'wp-security-audit-log' ),
				'rolesError' => esc_html__( 'Specified value in not a role.', 'wp-security-audit-log' ),
				'ipError'    => esc_html__( 'Specified value in not an IP address.', 'wp-security-audit-log' ),
			);
			wp_localize_script( 'wsal-wizard-js', 'wsalData', $data_array );

			$installer_script_data = array(
				'ajaxURL'           => admin_url( 'admin-ajax.php' ),
				'installing'        => esc_html__( 'Installing, please wait', 'wp-security-audit-log' ),
				'already_installed' => esc_html__( 'Already installed', 'wp-security-audit-log' ),
				'installed'         => esc_html__( 'Extension installed', 'wp-security-audit-log' ),
				'activated'         => esc_html__( 'Extension activated', 'wp-security-audit-log' ),
				'failed'            => esc_html__( 'Install failed', 'wp-security-audit-log' ),
				'reloading_page'    => esc_html__( 'Reloading page', 'wp-security-audit-log' ),
			);
			wp_localize_script( 'wsal-common', 'wsalCommonData', $installer_script_data );

			/**
			 * Save Wizard Settings.
			 */
			$save_step = ( isset( $_POST['save_step'] ) ) ? \sanitize_text_field( \wp_unslash( $_POST['save_step'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! empty( $save_step ) && ! empty( self::$wizard_steps[ self::$current_step ]['save'] ) ) {
				call_user_func( self::$wizard_steps[ self::$current_step ]['save'] );
			}

			self::setup_page_header();
			self::setup_page_steps();
			self::setup_page_content();
			self::setup_page_footer();
			exit;
		}

		/**
		 * Setup Page Header.
		 *
		 * @since 5.0.0
		 */
		private static function setup_page_header() {
			?>
			<!DOCTYPE html>
			<html <?php language_attributes(); ?>>
			<head>
				<meta name="viewport" content="width=device-width" />
				<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
				<title><?php esc_html_e( 'WP Activity Log &rsaquo; Setup Wizard', 'wp-security-audit-log' ); ?></title>
				<?php wp_print_scripts( 'wsal-wizard-js' ); ?>
				<?php wp_print_scripts( 'wsal-common' ); ?>
				<?php remove_action( 'admin_print_styles', 'print_emoji_styles' ); ?>
				<?php remove_action( 'admin_head', 'wp_admin_bar_header' ); ?>
				<?php do_action( 'admin_print_styles' ); ?>
			</head>
			<body class="wsal-setup wp-core-ui">
				<h1 id="wsal-logo"><a href="https://melapress.com/?utm_source=plugin&utm_medium=referral&utm_campaign=wsal&utm_content=wizard+configuration" rel="noopener noreferrer" target="_blank"><img src="<?php echo esc_url( WSAL_BASE_URL ); ?>img/wp-activity-log-logo-full-colour-horiz-rgb.svg" alt="WP Activity Log" /></a></h1>
			<?php
		}

		/**
		 * Setup Page Footer.
		 *
		 * @since 5.0.0
		 */
		private static function setup_page_footer() {
			?>
				<div class="wsal-setup-footer">
					<?php if ( 'welcome' !== self::$current_step && 'finish' !== self::$current_step ) : // Don't show the link on the first & last step. ?>
						<a href="<?php echo esc_url( \WpSecurityAuditLog::get_plugin_admin_url_page() ); ?>"><?php esc_html_e( 'Close Wizard', 'wp-security-audit-log' ); ?></a>
					<?php endif; ?>
				</div>
				</body>
			</html>
			<?php
		}

		/**
		 * Setup Page Steps.
		 *
		 * @since 5.0.0
		 */
		private static function setup_page_steps() {
			?>
			<ul class="steps">
				<?php
				foreach ( self::$wizard_steps as $key => $step ) :
					if ( $key === self::$current_step ) :
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
		 *
		 * @since 5.0.0
		 */
		private static function get_next_step() {
			// Get current step.
			$current_step = self::$current_step;

			// Array of step keys.
			$keys = array_keys( self::$wizard_steps );
			if ( end( $keys ) === $current_step ) { // If last step is active then return WP Admin URL.
				return \WpSecurityAuditLog::get_plugin_admin_url_page();
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
		 *
		 * @return string
		 *
		 * @since 5.0.0
		 */
		private static function get_welcome_step() {
			return remove_query_arg( 'current-step' );
		}

		/**
		 * Setup Page Content.
		 *
		 * @since 5.0.0
		 */
		private static function setup_page_content() {
			?>
			<div class="wsal-setup-content">
				<?php
				if ( isset( self::$wizard_steps[ self::$current_step ]['content'] ) && ! empty( self::$wizard_steps[ self::$current_step ]['content'] && is_callable( self::$wizard_steps[ self::$current_step ]['content'] ) ) ) {
					call_user_func( self::$wizard_steps[ self::$current_step ]['content'] );
				} else {
					self::render_invalid_step();
				}
				?>
			</div>
			<?php
		}

		/**
		 * Render method for any invalid steps a user happens to land on.
		 *
		 * @method render_invalid_step
		 *
		 * @since 5.0.0
		 */
		private static function render_invalid_step() {
			echo '<p>';
			printf(
			/* translators: 1 - an opening link tag, 2 - a closing link tag. */
				esc_html__( 'You have reached an invaild step - %1$sreturn to the start of the wizard%2$s.', 'wp-security-audit-log' ),
				'<a href="' . esc_url( self::get_welcome_step() ) . '">',
				'</a>'
			);
			echo '</p>';
		}

		/**
		 * Step View: `Welcome`
		 *
		 * @since 5.0.0
		 */
		private static function wsal_step_welcome() {
			// Dismiss the setup modal in case if not already done.
			if ( ! Settings_Helper::get_boolean_option_value( 'setup-modal-dismissed', false ) ) {
				Settings_Helper::set_boolean_option_value( 'setup-modal-dismissed', true, true );
			}
			?>
			<p><?php esc_html_e( 'This wizard helps you configure the basic plugin settings. All these settings can be changed at a later stage from the plugin settings.', 'wp-security-audit-log' ); ?></p>

			<div class="wsal-setup-actions">
				<a class="button button-primary"
					href="<?php echo esc_url( self::get_next_step() ); ?>">
					<?php esc_html_e( 'Start Configuring the Plugin', 'wp-security-audit-log' ); ?>
				</a>
				<a class="button button-secondary"
					href="<?php echo esc_url( \WpSecurityAuditLog::get_plugin_admin_url_page() ); ?>">
					<?php esc_html_e( 'Exit Wizard', 'wp-security-audit-log' ); ?>
				</a>
			</div>
			<?php
		}

		/**
		 * Step View: `Log Details`
		 *
		 * @since 5.0.0
		 */
		private static function wsal_step_log_details() {
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
		 *
		 * @since 5.0.0
		 */
		private static function wsal_step_log_details_save() {
			// Check nonce.
			check_admin_referer( 'wsal-step-log-details' );

			// Get Log Details Step setting.
			$log_details = isset( $_POST['wsal-details-level'] ) ? sanitize_text_field( wp_unslash( $_POST['wsal-details-level'] ) ) : false;

			// Validate we have a level that is allowed.
			if ( ! in_array( $log_details, self::$valid_log_levels, true ) ) {
				// if we have an unexpected log level then use default: 'geek'.
				$log_details = self::$valid_log_levels[0];
			}

			// Save log details option.
			Settings_Helper::set_option_value( 'details-level', $log_details );
			if ( ! empty( $log_details ) && 'basic' === $log_details ) {
				Plugin_Settings_Helper::set_basic_mode();
			} elseif ( ! empty( $log_details ) && 'geek' === $log_details ) {
				Plugin_Settings_Helper::set_geek_mode();
			}

			wp_safe_redirect( esc_url_raw( self::get_next_step() ) );
			exit();
		}

		/**
		 * Step View: `Login Sensor`
		 *
		 * @since 5.0.0
		 */
		private static function wsal_step_login() {
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
		 *
		 * @since 5.0.0
		 */
		private static function wsal_step_login_save() {
			// Check nonce.
			check_admin_referer( 'wsal-step-login' );

			if ( isset( $_POST['wsal-frontend-login'] ) ) {
				$frontend_sensors = Settings_Helper::get_frontend_events(); // Get the frontend sensors setting.
				$login_sensor     = sanitize_text_field( wp_unslash( $_POST['wsal-frontend-login'] ) );
				$login_sensor     = '1' === $login_sensor; // Update the sensor option.

				$frontend_sensors['login'] = $login_sensor;
				Settings_Helper::set_frontend_events( $frontend_sensors );
			}

			wp_safe_redirect( esc_url_raw( self::get_next_step() ) );
			exit();
		}

		/**
		 * Step View: `Register Sensor`
		 *
		 * @since 5.0.0
		 */
		private static function wsal_step_register() {
			?>
			<form method="post" class="wsal-setup-form">
				<?php wp_nonce_field( 'wsal-step-frontend-register' ); ?>
				<h4><?php esc_html_e( 'Can visitors register as a user on your website?', 'wp-security-audit-log' ); ?></h4>
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
		 *
		 * @since 5.0.0
		 */
		private static function wsal_step_register_save() {
			// Check nonce.
			\check_admin_referer( 'wsal-step-frontend-register' );

			if ( isset( $_POST['wsal-frontend-register'] ) ) {
				$frontend_sensors = Settings_Helper::get_frontend_events(); // Get the frontend sensors setting.
				$register_sensor  = sanitize_text_field( wp_unslash( $_POST['wsal-frontend-register'] ) );
				$register_sensor  = '1' === $register_sensor; // Update the sensor option.

				$frontend_sensors['register'] = $register_sensor;
				Settings_Helper::set_frontend_events( $frontend_sensors );
			}

			\wp_safe_redirect( esc_url_raw( self::get_next_step() ) );
			exit();
		}

		/**
		 * Step View: `Log Retention`
		 *
		 * @since 5.0.0
		 */
		private static function wsal_step_log_retention() {
			?>
			<form method="post" class="wsal-setup-form">
				<?php wp_nonce_field( 'wsal-step-log-retention' ); ?>
				<h4>
					<?php esc_html_e( 'How long do you want to keep the data in the WordPress activity Log?', 'wp-security-audit-log' ); ?>
				</h4>
				<fieldset>
					<label for="3">
						<input id="3" name="wsal-pruning-limit" type="radio" value="3" checked />
						<?php esc_html_e( '3 months (data older than 3 months will be deleted)', 'wp-security-audit-log' ); ?>
					</label>
					<br />
					<label for="6">
						<input id="6" name="wsal-pruning-limit" type="radio" value="6" />
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
					$step_help = __( 'The plugin stores the data in the WordPress database in a very efficient way, though the more data you keep the more hard disk space it will consume. If you need need to retain a lot of data we would recommend you to <a href="https://melapress.com/features/?utm_source=plugin&utm_medium=referral&utm_campaign=wsal&utm_content=wizard+configuration" target="_blank">upgrade to Premium</a> and use the Database tools to store the WordPress activity log in an external database.', 'wp-security-audit-log' );

					echo wp_kses( $step_help, Plugin_Settings_Helper::get_allowed_html_tags() );
					?>
				</em>
			</p>
			<?php
		}

		/**
		 * Step Save: `Log Retention`
		 *
		 * @since 5.0.0
		 */
		private static function wsal_step_log_retention_save() {
			// Verify nonce.
			\check_admin_referer( 'wsal-step-log-retention' );

			// Get log retention time setting.
			$pruning_limit = isset( $_POST['wsal-pruning-limit'] ) ? sanitize_text_field( wp_unslash( $_POST['wsal-pruning-limit'] ) ) : false;

			// validate the prune time.
			if ( ! in_array( (string) $pruning_limit, self::$valid_prune_times, true ) ) {
				// if $pruning_limit is not valid value then use default - 6.
				$pruning_limit = self::$valid_prune_times[0];
			}

			// Save log retention setting.
			if ( ! empty( $pruning_limit ) ) {
				switch ( $pruning_limit ) {
					case '3':
					case '6':
					case '12':
						// 6 or 12 months.
						Settings_Helper::set_boolean_option_value( 'pruning-date-e', true );
						Settings_Helper::set_option_value( 'pruning-date', $pruning_limit . ' months' );
						break;

					case 'none':
						// None.
						Settings_Helper::set_boolean_option_value( 'pruning-date-e', false );
						break;

					default:
						break;
				}
			}

			\wp_safe_redirect( esc_url_raw( self::get_next_step() ) );
			exit();
		}

		/**
		 * Step View: `Finish`
		 *
		 * @since 5.0.0
		 */
		private static function wsal_step_finish() {
			?>
			<p><?php esc_html_e( 'Your plugin is all set and it is ready to start keeping a record of everything that is happening on your WordPress in a WordPress activity log.', 'wp-security-audit-log' ); ?></p>
			<p><?php esc_html_e( 'Below are a few useful links you might need to refer to:', 'wp-security-audit-log' ); ?></p>

			<ul>
				<li>
					<a href="https://melapress.com/support/kb/wp-activity-log-getting-started/?utm_source=plugin&utm_source=plugin&utm_medium=link&utm_campaign=wsal" rel="noopener noreferrer" target="_blank">
						<?php esc_html_e( 'Getting started with the WP Activity Log plugin', 'wp-security-audit-log' ); ?>
					</a>
				</li>
				<li>
					<a href="https://melapress.com/support/kb/?utm_source=plugin&utm_source=plugin&utm_medium=link&utm_campaign=wsal" rel="noopener noreferrer" target="_blank">
						<?php esc_html_e( 'Knowledge Base & Support Documents', 'wp-security-audit-log' ); ?>
					</a>
				</li>
				<li>
					<a href="https://melapress.com/wordpress-activity-log/?utm_source=plugin&utm_source=plugin&utm_medium=link&utm_campaign=wsal" rel="noopener noreferrer" target="_blank">
						<?php esc_html_e( 'WordPress activity logs: the definitive guide to understanding & using them', 'wp-security-audit-log' ); ?>
					</a>
				</li>
			</ul>

			<?php
			// Link to contact form.
			$help_page = 'https://melapress.com/contact/?utm_source=plugin&utm_medium=link&utm_campaign=wsal';
			?>

			<p><?php echo wp_kses( __( 'We trust this plugin meets all your activity log requirements. Should you encounter any problems, have feature requests or would like to share some feedback', 'wp-security-audit-log' ), Plugin_Settings_Helper::get_allowed_html_tags() ); ?>  <a href="<?php echo esc_url( $help_page ); ?>" rel="noopener noreferrer" target="_blank"><?php esc_html_e( 'please get in touch!', 'wp-security-audit-log' ); ?></a></p>

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
		 *
		 * @since 5.0.0
		 */
		private static function wsal_step_finish_save() {
			// Verify nonce.
			\check_admin_referer( 'wsal-step-finish' );

			// Mark the finish of the setup.
			Settings_Helper::set_boolean_option_value( 'setup-complete', true );

			\wp_safe_redirect( \esc_url_raw( self::get_next_step() ) );
			exit();
		}
	}
}
