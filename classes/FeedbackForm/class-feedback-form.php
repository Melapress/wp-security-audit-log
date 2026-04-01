<?php
/**
 * Deactivation feedback form.
 *
 * Renders a popover on the plugins page to collect feedback when the user
 * deactivates the plugin, and sends it to the remote server.
 *
 * @package wsal
 *
 * @since 5.6.2
 */

declare(strict_types=1);

namespace WSAL\FeedbackForm;

use WSAL\Helpers\WP_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\FeedbackForm\Feedback_Form' ) ) {

	/**
	 * Deactivation feedback form.
	 */
	class Feedback_Form {

		/**
		 * The remote API URL to send the deactivation reason to.
		 *
		 * @var string
		 */
		const REMOTE_URL = 'https://proxytron.wpwhitesecurity.com';

		/**
		 * Initialize hooks.
		 *
		 * @return void
		 *
		 * @since 5.6.2
		 */
		public static function init() {
			\add_action( 'admin_footer', array( __CLASS__, 'maybe_render' ) );
		}

		/**
		 * Get the plugin slug suffix for the premium version.
		 *
		 * @return string
		 *
		 * @since 5.6.2
		 */
		private static function get_plugin_variant() {
			if ( WP_Helper::is_plugin_active( 'wp-security-audit-log-premium/wp-security-audit-log.php' ) ) {
				return '-premium';
			}

			return '';
		}

		/**
		 * Render the popover and enqueue assets on the plugins page.
		 *
		 * @return void
		 *
		 * @since 5.6.2
		 */
		public static function maybe_render() {
			if ( ! \function_exists( 'get_current_screen' ) || ! \get_current_screen() ) {
				return;
			}

			$screen_id = \get_current_screen()->id;

			if ( 'plugins' !== $screen_id && 'plugins-network' !== $screen_id ) {
				return;
			}

			self::enqueue_assets();
			self::render_popover();
		}

		/**
		 * Enqueue the JS and CSS assets and pass data to the script.
		 *
		 * @return void
		 *
		 * @since 5.6.2
		 */
		private static function enqueue_assets() {
			$base_url = WSAL_BASE_URL . 'classes/FeedbackForm/';

			\wp_enqueue_style(
				'wsal-feedback-form-styles',
				$base_url . 'feedback-form.css',
				array(),
				WSAL_VERSION
			);

			\wp_enqueue_script(
				'wsal-feedback-form-scripts',
				$base_url . 'feedback-form.js',
				array( 'wp-i18n' ),
				WSAL_VERSION,
				true
			);

			\wp_localize_script(
				'wsal-feedback-form-scripts',
				'wsalFeedbackForm',
				array(
					'remoteUrl' => self::REMOTE_URL,
					'plugin'    => 'wp-security-audit-log' . self::get_plugin_variant(),
					'siteUrl'   => \get_site_url(),
				)
			);
		}

		/**
		 * Render the feedback popover HTML.
		 *
		 * @return void
		 *
		 * @since 5.6.2
		 */
		private static function render_popover() {
			$reasons = array(
				array(
					'id'                   => 'unexpected-behavior',
					'label'                => \__( 'The plugin isn\'t working, caused issues, or has a bug', 'wp-security-audit-log' ),
					'feedback_placeholder' => \__( 'Can you briefly describe the issue?', 'wp-security-audit-log' ),
					'feedback_type'        => 'textarea',
				),
				array(
					'id'                   => 'found-better-plugin',
					'label'                => \__( 'I found a better alternative', 'wp-security-audit-log' ),
					'feedback_placeholder' => \__( 'Which plugin did you switch to?', 'wp-security-audit-log' ),
					'feedback_type'        => 'text',
				),
				array(
					'id'                   => 'missing-feature',
					'label'                => \__( 'The plugin is missing a specific feature', 'wp-security-audit-log' ),
					'feedback_placeholder' => \__( 'What feature were you looking for?', 'wp-security-audit-log' ),
					'feedback_type'        => 'textarea',
				),
				array(
					'id'                   => 'hard-to-understand',
					'label'                => \__( 'The plugin is too hard to set up or understand', 'wp-security-audit-log' ),
					'feedback_placeholder' => \__( 'Can you tell us a bit more about this?', 'wp-security-audit-log' ),
					'feedback_type'        => 'text',
				),
				array(
					'id'                   => 'temporary-deactivation',
					'label'                => \__( 'This is a temporary deactivation', 'wp-security-audit-log' ),
					'feedback_type'        => false,
					'feedback_placeholder' => false,
				),
			);

			shuffle( $reasons );

			$reasons[] = array(
				'id'                   => 'other',
				'label'                => \__( 'Other', 'wp-security-audit-log' ),
				'feedback_placeholder' => false,
				'feedback_type'        => false,
			);

			$logo_url = WSAL_BASE_URL . 'img/wp-activity-log-logo-full-colour-horiz-rgb.svg';
			?>

		<div id="wp-security-audit-log-popover" popover>
			<button type="button" class="wsal-close-button" aria-label="<?php \esc_attr_e( 'Close', 'wp-security-audit-log' ); ?>">&times;</button>
			<div class="wsal-logo-wrapper">
				<img src="<?php echo \esc_url( $logo_url ); ?>" alt="<?php \esc_attr_e( 'WP Activity Log', 'wp-security-audit-log' ); ?>">
			</div>
			<h1><?php \esc_html_e( "We're sorry to see you go", 'wp-security-audit-log' ); ?></h1>
			<p><?php \esc_html_e( 'If you have a moment, please let us know why you are deactivating this plugin:', 'wp-security-audit-log' ); ?></p>
			<form>
				<?php foreach ( $reasons as $reason ) : ?>
					<div class="wsal-reason-wrapper" data-reason="<?php echo \esc_attr( $reason['id'] ); ?>">
						<span class="wsal-radio-wrapper">
							<input
								id="deactivate-plugin-reason-<?php echo \esc_attr( $reason['id'] ); ?>"
								type="radio"
								name="reason"
								value="<?php echo \esc_attr( $reason['id'] ); ?>"
							>
							<label for="deactivate-plugin-reason-<?php echo \esc_attr( $reason['id'] ); ?>">
								<?php echo \esc_html( $reason['label'] ); ?>
							</label>
						</span>
						<?php if ( $reason['feedback_type'] ) : ?>
							<div class="wsal-feedback-wrapper">
								<?php if ( 'textarea' === $reason['feedback_type'] ) : ?>
									<textarea
										id="deactivate-plugin-reason-<?php echo \esc_attr( $reason['id'] ); ?>-feedback"
										name="feedback"
										placeholder="<?php echo \esc_attr( $reason['feedback_placeholder'] ); ?>"
									></textarea>
								<?php else : ?>
									<input
										id="deactivate-plugin-reason-<?php echo \esc_attr( $reason['id'] ); ?>-feedback"
										type="text"
										name="feedback"
										placeholder="<?php echo \esc_attr( $reason['feedback_placeholder'] ); ?>"
									>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>

				<div class="wsal-actions">
					<button type="button" class="wsal-submit"><?php \esc_html_e( 'Submit & Deactivate', 'wp-security-audit-log' ); ?></button>
					<button type="button" class="wsal-dismiss"><?php \esc_html_e( 'Skip & Deactivate', 'wp-security-audit-log' ); ?></button>
				</div>
			</form>
		</div>
			<?php
		}
	}
}
