<?php
/**
 * Sensor: Widgets
 *
 * Widgets sensor class file.
 *
 * @since     4.6.0
 * @package   wsal
 * @subpackage sensors
 */

declare(strict_types=1);

namespace WSAL\WP_Sensors;

use WSAL\Controllers\Alert_Manager;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\WP_Sensors\WP_2FA_Sensor' ) ) {

	/**
	 * Sensor for WP 2FA related events.
	 */
	class WP_2FA_Sensor {

		/**
		 * Inits the main hooks
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function init() {
			add_action( 'updated_option', array( __CLASS__, 'settings_trigger' ), 10, 3 );
			add_action( 'update_user_meta', array( __CLASS__, 'user_trigger' ), 10, 4 );
			add_action( 'delete_user_meta', array( __CLASS__, 'user_deletions_trigger' ), 10, 4 );
		}

		/**
		 * Monitor and alert for 2FA policy changes.
		 *
		 * @param string $option - Option name being altered.
		 * @param array  $old_value - Previous value.
		 * @param array  $new_value - Incoming value.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function settings_trigger( $option, $old_value, $new_value ) {
			if ( 'wp_2fa_policy' === $option ) {
				// Overall policy.
				if ( $new_value['enforcement-policy'] !== $old_value['enforcement-policy'] ) {
					if ( 'do-not-enforce' === $new_value['enforcement-policy'] ) {
						$alert_code = 7801;
						$variables  = array(
							'new_policy' => $new_value['enforcement-policy'],
						);
					} else {
						$alert_code = 7800;
						$variables  = array(
							'new_policy' => ( 'all-users' === $new_value['enforcement-policy'] ) ? esc_html__( 'Enforce on all users', 'wp-security-audit-log' ) : esc_html__( 'Only enforce on specific users & roles', 'wp-security-audit-log' ),
						);
					}
					Alert_Manager::trigger_event( $alert_code, $variables );
				}

				if ( $new_value['enforced_roles'] !== $old_value['enforced_roles'] ) {
					$alert_code = 7802;
					$variables  = array(
						'changed_list' => esc_html__( 'Enforced roles', 'wp-security-audit-log' ),
						'old_list'     => ( ! empty( $old_value['enforced_roles'] ) ) ? implode( ', ', $old_value['enforced_roles'] ) : esc_html__( 'None provided', 'wp-security-audit-log' ),
						'new_list'     => ( ! empty( $new_value['enforced_roles'] ) ) ? implode( ', ', $new_value['enforced_roles'] ) : esc_html__( 'None provided', 'wp-security-audit-log' ),
					);
					Alert_Manager::trigger_event( $alert_code, $variables );
				}

				if ( $new_value['enforced_users'] !== $old_value['enforced_users'] ) {
					$alert_code = 7802;
					$variables  = array(
						'changed_list' => esc_html__( 'Enforced users', 'wp-security-audit-log' ),
						'old_list'     => ( ! empty( $old_value['enforced_users'] ) ) ? implode( ', ', $old_value['enforced_users'] ) : esc_html__( 'None provided', 'wp-security-audit-log' ),
						'new_list'     => ( ! empty( $new_value['enforced_users'] ) ) ? implode( ', ', $new_value['enforced_users'] ) : esc_html__( 'None provided', 'wp-security-audit-log' ),
					);
					Alert_Manager::trigger_event( $alert_code, $variables );
				}

				if ( $new_value['excluded_roles'] !== $old_value['excluded_roles'] ) {
					$alert_code = 7803;
					$variables  = array(
						'changed_list' => esc_html__( 'Excluded roles', 'wp-security-audit-log' ),
						'old_list'     => ( ! empty( $old_value['excluded_roles'] ) ) ? implode( ', ', $old_value['excluded_roles'] ) : esc_html__( 'None provided', 'wp-security-audit-log' ),
						'new_list'     => ( ! empty( $new_value['excluded_roles'] ) ) ? implode( ', ', $new_value['excluded_roles'] ) : esc_html__( 'None provided', 'wp-security-audit-log' ),
					);
					Alert_Manager::trigger_event( $alert_code, $variables );
				}

				if ( $new_value['excluded_users'] !== $old_value['excluded_users'] ) {
					$alert_code = 7803;
					$variables  = array(
						'changed_list' => esc_html__( 'Excluded users', 'wp-security-audit-log' ),
						'old_list'     => ( ! empty( $old_value['excluded_users'] ) ) ? implode( ', ', $old_value['excluded_users'] ) : esc_html__( 'None provided', 'wp-security-audit-log' ),
						'new_list'     => ( ! empty( $new_value['excluded_users'] ) ) ? implode( ', ', $new_value['excluded_users'] ) : esc_html__( 'None provided', 'wp-security-audit-log' ),
					);
					Alert_Manager::trigger_event( $alert_code, $variables );
				}

				if ( $old_value['enable_email'] !== $new_value['enable_email'] ) {
					$alert_code = 7804;
					$variables  = array(
						'method'    => 'One-time code via email (HOTP)',
						'EventType' => ! empty( $new_value['enable_email'] ) ? 'enabled' : 'disabled',
					);
					Alert_Manager::trigger_event( $alert_code, $variables );
				}

				if ( $old_value['enable_totp'] !== $new_value['enable_totp'] ) {
					$alert_code = 7804;
					$variables  = array(
						'method'    => 'One-time code via 2FA App (TOTP)',
						'EventType' => ! empty( $new_value['enable_totp'] ) ? 'enabled' : 'disabled',
					);
					Alert_Manager::trigger_event( $alert_code, $variables );
				}

				if ( isset( $new_value['enable_oob_email'] ) && isset( $old_value['enable_oob_email'] ) && $old_value['enable_oob_email'] !== $new_value['enable_oob_email'] ) {
					$alert_code = 7804;
					$variables  = array(
						'method'    => 'Link via email',
						'EventType' => ! empty( $new_value['enable_oob_email'] ) ? 'enabled' : 'disabled',
					);
					Alert_Manager::trigger_event( $alert_code, $variables );
				}

				// User changes.
				if ( $old_value['backup_codes_enabled'] !== $new_value['backup_codes_enabled'] ) {
					$alert_code = 7804;
					$variables  = array(
						'method'    => 'Backup Codes',
						'EventType' => ! empty( $new_value['backup_codes_enabled'] ) ? 'enabled' : 'disabled',
					);
					Alert_Manager::trigger_event( $alert_code, $variables );
				}

				if ( isset( $new_value['enable_trusted_devices'] ) && isset( $old_value['enable_trusted_devices'] ) && $old_value['enable_trusted_devices'] !== $new_value['enable_trusted_devices'] ) {
					$alert_code = 7805;
					$variables  = array(
						'EventType' => ! empty( $new_value['enable_trusted_devices'] ) ? 'enabled' : 'disabled',
					);
					Alert_Manager::trigger_event( $alert_code, $variables );
				}

				if ( isset( $new_value['trusted-devices-period'] ) && isset( $old_value['trusted-devices-period'] ) && $old_value['trusted-devices-period'] !== $new_value['trusted-devices-period'] ) {
					$alert_code = 7806;
					$variables  = array(
						'old_value' => $old_value['trusted-devices-period'],
						'new_value' => $new_value['trusted-devices-period'],
					);
					Alert_Manager::trigger_event( $alert_code, $variables );
				}

				if ( isset( $new_value['password-reset-2fa-show'] ) && isset( $old_value['password-reset-2fa-show'] ) && $old_value['password-reset-2fa-show'] !== $new_value['password-reset-2fa-show'] ) {
					$alert_code = 7807;
					$variables  = array(
						'EventType' => ! empty( $new_value['password-reset-2fa-show'] ) ? 'enabled' : 'disabled',
					);
					Alert_Manager::trigger_event( $alert_code, $variables );
				}
			}
		}

		/**
		 * Captures 2FA related changes.
		 *
		 * @param int    $meta_id ID of the metadata entry to update.
		 * @param int    $user_id ID of the user metadata is for.
		 * @param string $meta_key Metadata key.
		 * @param mixed  $_meta_value Metadata value. Serialized if non-scalar.
		 *
		 * @since 5.0.0
		 */
		public static function user_trigger( $meta_id, $user_id, $meta_key, $_meta_value ) {

			// Filter global arrays for security.
			$server_array = filter_input_array( INPUT_SERVER );
			if ( ! isset( $server_array['HTTP_REFERER'] ) || ! isset( $server_array['REQUEST_URI'] ) ) {
				return;
			}

			// Check the page which is performing this change.
			$referer_check = pathinfo( $server_array['HTTP_REFERER'] );
			$referer_check = $referer_check['filename'];
			$referer_check = ( strpos( $referer_check, '.' ) !== false ) ? strstr( $referer_check, '.', true ) : $referer_check;

			$is_correct_referer_and_action = false;

			if ( 'profile' === $referer_check || 'user-edit' === $referer_check ) {
				$is_correct_referer_and_action = true;
			}

			if ( 'wp_2fa_enabled_methods' === $meta_key ) {
				if ( ! get_user_meta( $user_id, 'wp_2fa_enabled_methods', true ) || empty( get_user_meta( $user_id, 'wp_2fa_enabled_methods', true ) ) ) {
					$alert_code = 7808;
					$variables  = array(
						'method'       => get_user_meta( $user_id, 'wp_2fa_enabled_methods', true ),
						'EditUserLink' => add_query_arg( 'user_id', $user_id, \network_admin_url( 'user-edit.php' ) ),
					);
					Alert_Manager::trigger_event( $alert_code, $variables );
				} else {
					$alert_code = 7809;
					$variables  = array(
						'new_method'   => $_meta_value,
						'old_method'   => get_user_meta( $user_id, 'wp_2fa_enabled_methods', true ),
						'EditUserLink' => add_query_arg( 'user_id', $user_id, \network_admin_url( 'user-edit.php' ) ),
					);
					Alert_Manager::trigger_event( $alert_code, $variables );
				}
			}
			if ( 'wp_2fa_is_locked' === $meta_key ) {
				$alert_code = 7811;
				$variables  = array(
					'EditUserLink' => add_query_arg( 'user_id', $user_id, \network_admin_url( 'user-edit.php' ) ),
				);
				Alert_Manager::trigger_event( $alert_code, $variables );
			}
		}

		/**
		 * Captures 2FA related changes.
		 *
		 * @param int    $meta_id ID of the metadata entry to update.
		 * @param int    $user_id ID of the user metadata is for.
		 * @param string $meta_key Metadata key.
		 * @param mixed  $_meta_value Metadata value. Serialized if non-scalar.
		 *
		 * @since 5.0.0
		 */
		public static function user_deletions_trigger( $meta_id, $user_id, $meta_key, $_meta_value ) {

			// Filter global arrays for security.
			$server_array = filter_input_array( INPUT_SERVER );
			if ( ! isset( $server_array['HTTP_REFERER'] ) || ! isset( $server_array['REQUEST_URI'] ) ) {
				return;
			}

			// Check the page which is performing this change.
			$referer_check = pathinfo( $server_array['HTTP_REFERER'] );
			$referer_check = $referer_check['filename'];
			$referer_check = ( strpos( $referer_check, '.' ) !== false ) ? strstr( $referer_check, '.', true ) : $referer_check;

			$is_correct_referer_and_action = false;

			if ( 'profile' === $referer_check || 'user-edit' === $referer_check ) {
				$is_correct_referer_and_action = true;
			}

			if ( 'wp_2fa_2fa_status' === $meta_key ) {
				$alert_code = 7810;
				$variables  = array(
					'EditUserLink' => add_query_arg( 'user_id', $user_id, \network_admin_url( 'user-edit.php' ) ),
					'old_method'   => get_user_meta( $user_id, 'wp_2fa_enabled_methods', true ),
				);
				Alert_Manager::trigger_event( $alert_code, $variables );
			}

			if ( 'wp_2fa_is_locked' === $meta_key ) {
				$alert_code = 7812;
				$variables  = array(
					'EditUserLink' => add_query_arg( 'user_id', $user_id, \network_admin_url( 'user-edit.php' ) ),
				);
				Alert_Manager::trigger_event( $alert_code, $variables );
			}
		}

		/**
		 * Loads all the plugin dependencies early.
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function early_init() {
			add_filter(
				'wsal_event_objects',
				array( '\WSAL\WP_Sensors\Helpers\WP_2FA_Helper', 'add_custom_event_objects' )
			);
		}
	}
}
