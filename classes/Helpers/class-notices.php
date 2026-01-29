<?php
/**
 * Responsible for notices showing.
 *
 * @package    wsal
 * @subpackage helpers
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-security-audit-log/
 * @since 4.6.0
 */

declare(strict_types=1);

namespace WSAL\Helpers;

use WSAL\Helpers\Settings_Helper;
use WSAL\Utils\Abstract_Migration;
use WSAL\WP_Sensors\Helpers\LearnDash_Helper;

if ( ! class_exists( '\WSAL\Helpers\Notices' ) ) {

	/**
	 * Holds the notices of the plugin
	 *
	 * @since 4.6.0
	 */
	class Notices {

		/**
		 * That is a global constant used for showing the ebook notice.
		 */
		public const EBOOK_NOTICE = 'ebook-notice-show';

		/**
		 * Constant used for showing the LearnDash update notice.
		 *
		 * @since 5.6.0
		 */
		public const LEARNDASH_UPDATE_NOTICE = 'learndash-update-notice-dismissed';

		/**
		 * Holds the number of notices we have to show in the admin menu bubble.
		 *
		 * @var integer
		 *
		 * @since 5.2.2
		 */
		private static $number_of_notices = 0;

		/**
		 * Sets the class hooks.
		 *
		 * @return void
		 *
		 * @since 4.6.0
		 */
		public static function init() {
			global $current_screen;

			if ( ! isset( $current_screen ) ) {
				return;
			}

			if ( in_array( $current_screen->base, \WpSecurityAuditLog::get_plugin_screens_array(), true ) ) {

				$notice_upgrade = Settings_Helper::get_boolean_option_value( Abstract_Migration::UPGRADE_NOTICE, false );
				if ( $notice_upgrade ) {
					Settings_Helper::delete_option_value( self::EBOOK_NOTICE );
					self::display_notice_upgrade();
				}

				// if ( 'free' === \WpSecurityAuditLog::get_plugin_version() ) {
				// $ebook = Settings_Helper::get_boolean_option_value( self::EBOOK_NOTICE, false );
				// if ( ! $ebook ) {
				// self::display_ebook_notice();
				// }
				// }

				// $survey_2025 = Settings_Helper::get_boolean_option_value( 'survey-2025', false );
				// if ( ! $survey_2025 ) {
				// self::display_yearly_security_survey();
				// }

				$learndash_update_notice = Settings_Helper::get_boolean_option_value( self::LEARNDASH_UPDATE_NOTICE, false );

				if ( ! $learndash_update_notice && LearnDash_Helper::is_learndash_active() ) {
					self::display_learndash_update_notice();
				} else {
					$melapress_survey_2025 = Settings_Helper::get_boolean_option_value( 'melapress-survey-2025', false );

					if ( ! $melapress_survey_2025 && ! self::is_black_friday_campaign_active() ) {
						self::display_yearly_wsal_melapress_survey();
					}
				}

				// @free:start
				if ( self::should_show_black_friday_notice() ) {
					self::display_black_friday();
				}
				// @free:end
			}
		}

		/**
		 * Responsible for initializing the AJAX hooks for the notifications
		 *
		 * @return void
		 *
		 * @since 4.6.0
		 */
		public static function init_ajax_hooks() {

			$notice_upgrade = Settings_Helper::get_boolean_option_value( Abstract_Migration::UPGRADE_NOTICE, false );
			if ( $notice_upgrade ) {
				\add_action( 'wp_ajax_wsal_dismiss_upgrade_notice', array( __CLASS__, 'dismiss_upgrade_notice' ) );

				++self::$number_of_notices;
			}

			// ! \WpSecurityAuditLog::get_plugin_version() does not work in this hook action, do not use it.
			// if ( 'free' === \WpSecurityAuditLog::get_plugin_version() ) {
			// $ebook = Settings_Helper::get_boolean_option_value( self::EBOOK_NOTICE, false );
			// if ( ! $ebook ) {
			// ++self::$number_of_notices;
			// \add_action( 'wp_ajax_wsal_dismiss_ebook_notice', array( __CLASS__, 'dismiss_ebook_notice' ) );
			// }
			// }

			// $survey_2025 = Settings_Helper::get_boolean_option_value( 'survey-2025', false );
			// if ( ! $survey_2025 ) {
			// \add_action( 'wp_ajax_dismiss_yearly_survey', array( __CLASS__, 'dismiss_yearly_survey' ) );

			// ++self::$number_of_notices;
			// }

			$learndash_update_notice = Settings_Helper::get_boolean_option_value( self::LEARNDASH_UPDATE_NOTICE, false );

			if ( ! $learndash_update_notice && LearnDash_Helper::is_learndash_active() ) {
				\add_action( 'wp_ajax_dismiss_learndash_update_notice', array( __CLASS__, 'dismiss_learndash_update_notice' ) );

				++self::$number_of_notices;
			} else {

				$melapress_survey_2025 = Settings_Helper::get_boolean_option_value( 'melapress-survey-2025', false );

				if ( ! $melapress_survey_2025 && ! self::is_black_friday_campaign_active() ) {
					\add_action( 'wp_ajax_dismiss_melapress_survey', array( __CLASS__, 'dismiss_melapress_survey' ) );

					++self::$number_of_notices;
				}
			}

			// @free:start
			if ( self::should_show_black_friday_notice() ) {
				\add_action( 'wp_ajax_dismiss_black_friday', array( __CLASS__, 'dismiss_black_friday' ) );

				++self::$number_of_notices;
			}
			// @free:end
		}

		/**
		 * Display upgrade notice.
		 *
		 * @since 5.1.0
		 */
		public static function display_notice_upgrade() {
			include_once WSAL_BASE_DIR . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'Free' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'plugin-update-card.php';
		}

		/**
		 * Display upgrade notice.
		 *
		 * @since 5.1.1
		 */
		public static function display_ebook_notice() {
			include_once WSAL_BASE_DIR . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'Free' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'wsal-ebook-card.php';
		}

		/**
		 * Method: Ajax request handler to dismiss upgrade notice.
		 *
		 * @since 5.1.0
		 */
		public static function dismiss_upgrade_notice() {
			if ( ! Settings_Helper::current_user_can( 'edit' ) ) {
				\wp_send_json_error();
			}

			if ( ! array_key_exists( 'nonce', $_POST ) || ! wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['nonce'] ) ), 'dismiss_upgrade_notice' ) ) {
				\wp_send_json_error( \esc_html_e( 'nonce is not provided or incorrect', 'wp-security-audit-log' ) );
			}

			Settings_Helper::delete_option_value( Abstract_Migration::UPGRADE_NOTICE );
			\wp_send_json_success();
		}

		/**
		 * Method: Ajax request handler to dismiss ebook notice.
		 *
		 * @since 5.1.1
		 */
		public static function dismiss_ebook_notice() {
			if ( ! Settings_Helper::current_user_can( 'edit' ) ) {
				\wp_send_json_error();
			}

			if ( ! array_key_exists( 'nonce', $_POST ) || ! wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['nonce'] ) ), 'dismiss_ebook_notice' ) ) {
				\wp_send_json_error( \esc_html_e( 'nonce is not provided or incorrect', 'wp-security-audit-log' ) );
			}

			Settings_Helper::set_option_value( self::EBOOK_NOTICE, true );
			\wp_send_json_success();
		}

		/**
		 * Returns the number of notifications we have to show.
		 *
		 * @return int
		 *
		 * @since 5.2.2
		 */
		public static function get_number_of_notices(): int {
			return self::$number_of_notices;
		}

		/**
		 * Ajax request handler to dismiss the yearly survey notice.
		 *
		 * @return void
		 *
		 * @since 5.5.1
		 */
		public static function dismiss_yearly_survey() {
			if ( ! Settings_Helper::current_user_can( 'edit' ) || ! \current_user_can( 'manage_options' ) ) {
				\wp_send_json_error();
			}

			$nonce_check = \check_ajax_referer( 'dismiss_yearly_survey', 'nonce' );

			if ( ! $nonce_check ) {
				\wp_send_json_error( \esc_html_e( 'nonce is not provided or incorrect', 'wp-security-audit-log' ) );
			}

			$update_yr_setting = Settings_Helper::set_option_value( 'survey-2025', true );

			if ( ! $update_yr_setting ) {
				\wp_send_json_error( \esc_html__( 'Failed to dismiss the survey. Please try again.', 'wp-security-audit-log' ) );
			}

			\wp_send_json_success();
		}

		/**
		 * Ajax request handler to dismiss the melapress survey notice. Please note that this is different from the yearly survey, which is for all the version of the plugin.
		 *
		 * @return void
		 *
		 * @since 5.5.3
		 */
		public static function dismiss_melapress_survey() {
			if ( ! Settings_Helper::current_user_can( 'edit' ) || ! \current_user_can( 'manage_options' ) ) {
				\wp_send_json_error();
			}

			$nonce_check = \check_ajax_referer( 'dismiss_melapress_survey', 'nonce' );

			if ( ! $nonce_check ) {
				\wp_send_json_error( \esc_html_e( 'nonce is not provided or incorrect', 'wp-security-audit-log' ) );
			}

			$update_yr_setting = Settings_Helper::set_option_value( 'melapress-survey-2025', true );

			if ( ! $update_yr_setting ) {
				\wp_send_json_error( \esc_html__( 'Failed to dismiss the survey. Please try again.', 'wp-security-audit-log' ) );
			}

			\wp_send_json_success();
		}

		/**
		 * Ajax request handler to dismiss the LearnDash update notice.
		 *
		 * @return void
		 *
		 * @since 5.6.0
		 */
		public static function dismiss_learndash_update_notice() {
			if ( ! Settings_Helper::current_user_can( 'edit' ) || ! \current_user_can( 'manage_options' ) ) {
				\wp_send_json_error();
			}

			\check_ajax_referer( 'dismiss_learndash_update_notice', 'nonce' );

			$update_setting = Settings_Helper::set_option_value( self::LEARNDASH_UPDATE_NOTICE, true );

			if ( ! $update_setting ) {
				\wp_send_json_error( \esc_html__( 'Failed to dismiss the notice. Please try again.', 'wp-security-audit-log' ) );
			}

			\wp_send_json_success();
		}

		/**
		 * Display the 2025 MelaPress survey admin notice
		 *
		 * @since 5.5.1
		 */
		public static function display_yearly_security_survey() {

			// Show only to admins.
			if ( ! \current_user_can( 'manage_options' ) ) {
					return;
			}

			$survey_url = \esc_url(
				\add_query_arg(
					array(
						'utm_source'   => 'plugin',
						'utm_medium'   => 'wsal',
						'utm_campaign' => 'survey+promo+banner',
					),
					'https://melapress.com/wordpress-security-survey-2025/'
				)
			);

			?>
				<div style="position: relative; padding-top: 8px; padding-bottom: 8px; border-left-color: #009344;" class="wsal-notice notice notice-info" id="wsal-survey-2025-notice" data-dismiss-action="dismiss_yearly_survey" data-nonce="<?php echo \esc_attr( \wp_create_nonce( 'dismiss_yearly_survey' ) ); ?>">
					<p style="font-weight:700; margin-top: 0;"><?php \esc_html_e( 'Want to know what the state of WordPress security is in 2025?', 'wp-security-audit-log' ); ?></p>
					<p>
						<?php \esc_html_e( 'Discover the latest insights in our 2025 WordPress Security Survey Report.', 'wp-security-audit-log' ); ?>
					</p>
					<a href="<?php echo \esc_url( $survey_url ); ?>" target="_blank" rel="noopener" style="background-color: #009344;"  class="button button-primary"><?php \esc_html_e( 'Read the survey', 'wp-security-audit-log' ); ?></a>
					<button type="button" class="notice-dismiss wsal-plugin-notice-close"><span class="screen-reader-text"><?php \esc_html_e( 'Dismiss this notice.', 'wp-security-audit-log' ); ?></span></button>
				</div>
			<?php
		}

		/**
		 * Display the 2025 WSAL survey admin notice
		 *
		 * @since 5.5.3
		 */
		public static function display_yearly_wsal_melapress_survey() {
			// Show only to admins.
			if ( ! \current_user_can( 'manage_options' ) ) {
					return;
			}

			$melapress_survey_url = 'https://getformly.app/46gDbF';


			?>
				<div style="position: relative; padding-top: 8px; padding-bottom: 8px; border-left-color: #009344;" class="wsal-notice notice notice-info" id="wsal-melapress-survey-notice" data-dismiss-action="dismiss_melapress_survey" data-nonce="<?php echo \esc_attr( \wp_create_nonce( 'dismiss_melapress_survey' ) ); ?>">
					<p style="font-weight:700; margin-top: 0;"><?php \esc_html_e( 'Got 2 minutes? Help us shape the future of WP Activity Log', 'wp-security-audit-log' ); ?></p>
					<a href="<?php echo \esc_url( $melapress_survey_url ); ?>" target="_blank" rel="noopener" style="background-color: #009344;"  class="button button-primary"><?php \esc_html_e( 'Take the survey', 'wp-security-audit-log' ); ?></a>
					<button type="button" class="notice-dismiss wsal-plugin-notice-close"><span class="screen-reader-text"><?php \esc_html_e( 'Dismiss this notice.', 'wp-security-audit-log' ); ?></span></button>
				</div>
			<?php
		}

		/**
		 * Display the LearnDash update notice.
		 *
		 * @return void
		 *
		 * @since 5.6.0
		 */
		public static function display_learndash_update_notice() {
			if ( ! \current_user_can( 'manage_options' ) ) {
				return;
			}

			$review_url = WP_Helper::get_admin_url( 'admin.php?page=wsal-togglealerts#cat-LearnDash-LMS' );

			?>
			<div style="position: relative; padding-top: 8px; padding-bottom: 8px; border-left-color: #009344;" class="wsal-notice notice notice-info" id="wsal-learndash-update-notice" data-dismiss-action="dismiss_learndash_update_notice" data-nonce="<?php echo \esc_attr( \wp_create_nonce( 'dismiss_learndash_update_notice' ) ); ?>">
				<p style="font-weight:700; margin-top: 0;"><?php \esc_html_e( 'Great news! We\'ve added support for LearnDash LMS.', 'wp-security-audit-log' ); ?></p>
				<p><?php \esc_html_e( 'You can now track course creation, lesson modifications, student enrollments, quiz completions, and more.', 'wp-security-audit-log' ); ?></p>
				<p><?php \esc_html_e( 'To help keep your activity log focused and efficient, we\'ve carefully selected which events are enabled by default. You can review and customize which LearnDash events you want to track at any time.', 'wp-security-audit-log' ); ?></p>
				<a href="<?php echo \esc_url( $review_url ); ?>" style="background-color: #009344;" class="button button-primary"><?php \esc_html_e( 'Review Settings', 'wp-security-audit-log' ); ?></a>
				<button type="button" class="button wsal-plugin-notice-close" style="margin-left: 8px;"><?php \esc_html_e( 'Not Now', 'wp-security-audit-log' ); ?></button>
				<button type="button" class="notice-dismiss wsal-plugin-notice-close"><span class="screen-reader-text"><?php \esc_html_e( 'Dismiss this notice.', 'wp-security-audit-log' ); ?></span></button>
			</div>
			<?php
		}

		/**
		 * Get the current date in Y-m-d format (derived from DateTimeImmutable).
		 *
		 * @return string
		 *
		 * @since 5.5.4
		 */
		private static function get_current_date(): string {
			$current_datetime = \current_datetime();

			return $current_datetime->format( 'Y-m-d' );
		}

		/**
		 * Check if the Black Friday campaign is currently active.
		 *
		 * @return bool
		 *
		 * @since 5.5.4
		 */
		public static function is_black_friday_campaign_active(): bool {
			$current_date = self::get_current_date();

			// Campaign runs from November 21 to December 1.
			return $current_date >= '2025-11-21' && $current_date <= '2025-12-01';
		}

		/**
		 * Check if the Black Friday notice should be displayed.
		 *
		 * @return bool
		 *
		 * @since 5.5.4
		 */
		public static function should_show_black_friday_notice(): bool {
			// Check if we're within the campaign period.
			if ( ! self::is_black_friday_campaign_active() ) {
				return false;
			}

			$current_date = self::get_current_date();

			// Check dismissal status.
			$first_dismissal = Settings_Helper::get_option_value( 'black-friday-first-dismiss', '' );

			// If permanently dismissed (second time), never show again.
			if ( Settings_Helper::get_boolean_option_value( 'black-friday', false ) ) {
				return false;
			}

			// Black Friday date (November 28, 2025).
			$black_friday_date = '2025-11-28';

			// If first dismissal exists and we're before Black Friday, don't show.
			if ( ! empty( $first_dismissal ) && $current_date < $black_friday_date ) {
				return false;
			}

			// If first dismissal exists and we're on or after Black Friday, show again.
			if ( ! empty( $first_dismissal ) && $current_date >= $black_friday_date ) {
				return true;
			}

			// No dismissal yet, show the notice.
			return true;
		}

		/**
		 * Ajax request handler to dismiss the Black Friday notice.
		 *
		 * @return void
		 *
		 * @since 5.5.4
		 */
		public static function dismiss_black_friday() {
			if ( ! Settings_Helper::current_user_can( 'edit' ) || ! \current_user_can( 'manage_options' ) ) {
				\wp_send_json_error();
			}

			$nonce_check = \check_ajax_referer( 'dismiss_black_friday', 'nonce' );

			if ( ! $nonce_check ) {
				\wp_send_json_error( \esc_html__( 'nonce is not provided or incorrect', 'wp-security-audit-log' ) );
			}

			$current_date = self::get_current_date();

			// Check if this is the first or second dismissal.
			$first_dismissal = Settings_Helper::get_option_value( 'black-friday-first-dismiss', '' );

			if ( empty( $first_dismissal ) ) {
				// First dismissal - save the date.
				$update_setting = Settings_Helper::set_option_value( 'black-friday-first-dismiss', $current_date );
			} else {
				// Second dismissal (on or after Black Friday) - permanently dismiss.
				$update_setting = Settings_Helper::set_option_value( 'black-friday', true );
			}

			if ( ! $update_setting ) {
				\wp_send_json_error( \esc_html__( 'Failed to dismiss the notice. Please try again.', 'wp-security-audit-log' ) );
			}

			\wp_send_json_success();
		}

		/**
		 * Display the Black Friday admin notice.
		 *
		 * @return void
		 *
		 * @since 5.5.4
		 */
		public static function display_black_friday() {
			if ( ! \current_user_can( 'manage_options' ) ) {
				return;
			}

			$offer_url = 'https://melapress.com/black-friday-cyber-monday/?utm_source=plugin&utm_medium=wsal&utm_campaign=BFCM2025';

			?>
				<div id="wsal-black-friday-notice" class="wsal-notice wsal-black-friday-banner" data-dismiss-action="dismiss_black_friday" data-nonce="<?php echo \esc_attr( \wp_create_nonce( 'dismiss_black_friday' ) ); ?>">
					<div class="wsal-bf-icon">
						<img src="<?php echo \esc_url( WSAL_BASE_URL ); ?>img/upgrade-plugin-icon.svg" alt="Black Friday Sale" />
					</div>
					<div class="wsal-bf-content">
						<div class="wsal-bf-title">
							<?php \esc_html_e( 'Upgrade to premium', 'wp-security-audit-log' ); ?>
						</div>
					<div class="wsal-bf-subtitle">
						<span class="wsal-bf-underline"><?php \esc_html_e( 'Black Friday', 'wp-security-audit-log' ); ?></span> <?php \esc_html_e( 'sale now live!', 'wp-security-audit-log' ); ?>
					</div>
						<a href="<?php echo \esc_url( $offer_url ); ?>" target="_blank" rel="noopener" class="button button-primary wsal-bf-button" id="wsal-bf-cta"><?php \esc_html_e( 'Get offer now', 'wp-security-audit-log' ); ?></a>
					</div>
					<button type="button" class="notice-dismiss wsal-plugin-notice-close"><span class="screen-reader-text"><?php \esc_html_e( 'Dismiss this notice.', 'wp-security-audit-log' ); ?></span></button>
				</div>

			<style>
				@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap');

				#wsal-black-friday-notice.wsal-black-friday-banner {
					background-color:  #020E26;
					border-left: 4px solid #FF8977;
					position: relative;
					font-family: 'Inter', sans-serif;
					display: flex;
					gap: 52px;
					margin-left: 0;
					margin-top: 64px;
					margin-right: 10px;
					padding: 16px;
					justify-content: center;
				}

				#wsal-black-friday-notice .wsal-bf-icon {
					background-color: #B6C3F2;
					display: none;
					flex-shrink: 0;
					position: relative;
				}

				#wsal-black-friday-notice .wsal-bf-icon::after {
					content: '';
					position: absolute;
					top: 0;
					right: -6px;
					width: 80px;
					height: 100%;
					background-color: #B6C3F2;
					transform: skewX(-7deg);
					z-index: 0;
				}

				#wsal-black-friday-notice .wsal-bf-icon img {
					display: block;
					position: relative;
					padding: 12px 16px 12px 20px;
					z-index: 1;
				}
				
				#wsal-black-friday-notice .wsal-bf-content {
					color: #ffffff;
					margin-top: 13px;
					margin-bottom: 15px;
				}

				#wsal-black-friday-notice .wsal-bf-title,
				#wsal-black-friday-notice .wsal-bf-subtitle {
					font-weight: 900;
					text-transform: uppercase;
				}

				#wsal-black-friday-notice .wsal-bf-title {
					font-size: 32px;
					line-height: 1.2;
					color: #FF8977;
				}

				#wsal-black-friday-notice .wsal-bf-subtitle {
					color: #ffffff;
					font-size: 24px;
					line-height: 1.5;
					margin-bottom: 12px;
				}

				#wsal-black-friday-notice .wsal-bf-underline {
					position: relative;
					display: inline-block;
					text-decoration: none;
				}

				#wsal-black-friday-notice .wsal-bf-underline::after {
					content: '';
					position: absolute;
					left: 0;
					bottom: -2px;
					width: 100%;
					height: 2px;
					background-color: currentColor;
				}

				#wsal-black-friday-notice #wsal-bf-cta.button.button-primary {
					background-color: #D9E4FD;
					border-color: #D9E4FD;
					color: #454BF7;
					display: flex;
					align-items: center;
					justify-content: center;
					font-size: 14px;
					font-weight: 700;
					padding: 7px 10px;
					line-height: 1;
					transition: background-color 0.3s ease, border-color 0.3s ease;
				}

				#wsal-black-friday-notice #wsal-bf-cta.button.button-primary:hover,
				#wsal-black-friday-notice #wsal-bf-cta.button.button-primary:focus {
					background-color: #B6C3F2;
					border-color: #B6C3F2;
				}

				#wsal-black-friday-notice .notice-dismiss {
					top: 12px;
					right: 12px;
					padding: 0;
				}

				#wsal-black-friday-notice .notice-dismiss:before {
					color: #ffffff;
				}

				@media  (min-width: 647px) {
					#wsal-black-friday-notice.wsal-black-friday-banner {
						padding: 0;
						justify-content: flex-start;
					}
					
					#wsal-black-friday-notice #wsal-bf-cta.button.button-primary {
						display: inline-flex;
					}

					#wsal-black-friday-notice .wsal-bf-icon {
						display: block;
					}

					#wsal-black-friday-notice .wsal-bf-title,
					#wsal-black-friday-notice .wsal-bf-subtitle {
						line-height: 1;
					}
				}

				@media  (min-width: 790px) {
					#wsal-black-friday-notice.wsal-black-friday-banner {
						margin-top: 48px;
						margin-right: 20px;
					}
				}
			</style>
			<script>
				document.addEventListener('DOMContentLoaded', function() {
					const wsalBfNotice = document.getElementById('wsal-black-friday-notice');
					const wsalUpgradeNotice = document.querySelector('.wsal-plugin-update');

					const wsalShowUpgradeNotice = () => {
						if (wsalUpgradeNotice) {
							wsalUpgradeNotice.style.display = 'flex';
						}
					};

					if (!wsalBfNotice) {
						// BF not present, show upgrade
						wsalShowUpgradeNotice();
					} else {
						// BF present, listen for dismiss
						const wsalBfDismissBtn = wsalBfNotice.querySelector('.wsal-plugin-notice-close');
						if (wsalBfDismissBtn) {
							wsalBfDismissBtn.addEventListener('click', function() {
								// Delayed of 300ms, it looks slightly better as the Black Friday notice "swaps" with the upgrade notice.
								setTimeout(wsalShowUpgradeNotice, 300);
							});
						}
					}
				});
			</script>
			<?php
		}
	}
}
