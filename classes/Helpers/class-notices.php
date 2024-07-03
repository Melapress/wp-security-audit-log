<?php
/**
 * Responsible for notices showing.
 *
 * @package    wsal
 * @subpackage helpers
 * @copyright  2024 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-security-audit-log/
 * @since 4.6.0
 */

declare(strict_types=1);

namespace WSAL\Helpers;

use WSAL\Helpers\Settings_Helper;
use WSAL\Utils\Abstract_Migration;

if ( ! class_exists( '\WSAL\Helpers\Notices' ) ) {

	/**
	 * Holds the notices of the plugin
	 *
	 * @since 4.6.0
	 */
	class Notices {

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

			if ( in_array( $current_screen->base, array( 'toplevel_page_wsal-auditlog', 'wp-activity-log_page_wsal-usersessions-views', 'wp-activity-log_page_wsal-np-notifications', 'wp-activity-log_page_wsal-rep-views-main', 'toplevel_page_wsal-auditlog-network', 'wp-activity-log_page_wsal-usersessions-views-network', 'wp-activity-log_page_wsal-np-notifications-network', 'wp-activity-log_page_wsal-rep-views-main-network' ), true ) ) {
				$notice_46_extensions_merged = Settings_Helper::get_boolean_option_value( 'extensions-merged-notice' );

				if ( $notice_46_extensions_merged ) {
					self::display_46_extensions_merged();
				}

				$notice_upgrade = Settings_Helper::get_boolean_option_value( Abstract_Migration::UPGRADE_NOTICE, false );
				if ( $notice_upgrade ) {
					self::display_notice_upgrade();
				}
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
			$notice_46_extensions_merged = Settings_Helper::get_boolean_option_value( 'extensions-merged-notice' );
			if ( $notice_46_extensions_merged ) {
				\add_action( 'wp_ajax_wsal_dismiss_extensions_merged', array( __CLASS__, 'dismiss_extensions_merged' ) );
			}

			$notice_upgrade = Settings_Helper::get_boolean_option_value( Abstract_Migration::UPGRADE_NOTICE, false );
			if ( $notice_upgrade ) {
				\add_action( 'wp_ajax_wsal_dismiss_upgrade_notice', array( __CLASS__, 'dismiss_upgrade_notice' ) );
			}
		}

		/**
		 * Display admin notice that as of 4.6.0 the extension plugins are part of the core.
		 *
		 * @since 4.6.0
		 */
		public static function display_46_extensions_merged() {
			?>
			<div class="updated notice notice-success is-dismissible" data-dismiss-action="wsal_dismiss_extensions_merged" data-nonce="<?php echo \esc_attr( \wp_create_nonce( 'dismiss_extensions_merged' ) ); ?>">
				<div class="notice-content-wrapper">
					<p>
						<strong><?php esc_html_e( 'Important', 'wp-security-audit-log' ); ?>: </strong>
						<?php esc_html_e( 'In WP Activity Log 4.6 we have merged the third party plugins activity log extensions in the plugin\'s core. If you have any of these installed, the plugin has already deactivated them during the update. You can now delete / uninstall these extensions if you have them installed. Below is the list of current extensions:', 'wp-security-audit-log' ); ?>
					</p>
					<ul style="list-style:circle;padding:5px 20px;">
						<li>WP Activity Log for WooCommerce</li>
						<li>WP Activity Log for WPForms</li>
						<li>WP Activity Log for bbPress</li>
						<li>WP Activity Log for Yoast SEO</li>
						<li>WP Activity Log for Gravity Forms</li>
						<li>WP Activity Log for TablePress</li>
						<li>Activity Log for MemberPress</li>
					</ul>
				</div>
			</div>
			<?php
		}

		/**
		 * Display upgrade notice.
		 *
		 * @since 5.1.0
		 */
		public static function display_notice_upgrade() {
			?>
			<div class="updated notice notice-success is-dismissible wsal-upgraded-notice" data-dismiss-action="wsal_dismiss_upgrade_notice" data-nonce="<?php echo \esc_attr( \wp_create_nonce( 'dismiss_upgrade_notice' ) ); ?>">
				<div class="notice-content-wrapper">
					<img src="<?php echo esc_url( WSAL_BASE_URL ); ?>img/wsal-logo.png">
					<p>
						<?php
						printf(
							/* Translators: 1: html that opens a link, 2: html that closes a link. */
							esc_html__( 'Thank you for upgrading to WP Activity Log version %1$s. Spare a minute and read the release notes to read about all the exciting new features and improvements we have included in this update.', 'wp-security-audit-log' ),
							\esc_attr( WSAL_VERSION )
						);
						?>
					</p>
					<p>
						<?php
						printf(
						/* Translators: 1: html that opens a link, 2: html that closes a link. */
							esc_html__( '%1$sRead the release notes%2$s.', 'wp-security-audit-log' ),
							'<a href="https://melapress.com/wordpress-activity-log/releases/?utm_source=plugin&utm_medium=banner&utm_campaign=wsal&utm_content=update+notices" rel="noopener noreferrer" target="_blank" class="button button-primary">',
							'</a>'
						);
						?>
					</p>
				</div>
			</div>
			<style>
				.wsal-upgraded-notice {
					border: 2px solid #bdd63a !important;
    				padding: 10px 15px !important;
				}
				.wsal-upgraded-notice img {
					float: left;
					max-width: 70px;
					margin: 5px 12px 10px 0;
				}
				.wsal-upgraded-notice .button-primary {
					background: #009344;
					border-color: #009344;
				}
			</style>
			<?php
		}

		/**
		 * Method: Ajax request handler to dismiss extensions are merged in the core plugin notification.
		 *
		 * @since 4.6.0
		 */
		public static function dismiss_extensions_merged() {
			if ( ! Settings_Helper::current_user_can( 'edit' ) ) {
				\wp_send_json_error();
			}

			if ( ! array_key_exists( 'nonce', $_POST ) || ! wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['nonce'] ) ), 'dismiss_extensions_merged' ) ) {
				\wp_send_json_error( \esc_html_e( 'nonce is not provided or incorrect', 'wp-security-audit-log' ) );
			}

			Settings_Helper::delete_option_value( 'extensions-merged-notice' );
			\wp_send_json_success();
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
	}
}
