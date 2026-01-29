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

use WSAL\Helpers\WP_Helper;
use WSAL\Controllers\Alert_Manager;
use WSAL\WP_Sensors\Helpers\WP_2FA_Helper;

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
		 * Keeps old user meta data to compare with new values.
		 *
		 * @var array
		 *
		 * @since 5.4.2
		 */
		private static $old_user_meta = array();

		/**
		 * Inits the main hooks
		 *
		 * @return void
		 *
		 * @since 5.0.0
		 */
		public static function init() {
			if ( WP_2FA_Helper::is_wp2fa_active() ) {
				\add_action( 'updated_option', array( __CLASS__, 'settings_trigger' ), 10, 3 );
				if ( WP_Helper::is_multisite() ) {
					\add_action( 'update_site_option', array( __CLASS__, 'settings_trigger' ), 10, 3 );
				}
				// \add_action( 'update_user_meta', array( __CLASS__, 'user_trigger' ), 10, 4 );
				// \add_action( 'delete_user_meta', array( __CLASS__, 'user_deletions_trigger' ), 10, 4 );

				// \add_action( 'wp_2fa_user_is_unlocked', array( __CLASS__, 'user_unlock_trigger' ) );
			}
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

				if ( \class_exists( '\WP2FA\Admin\Controllers\Settings' ) ) {
					$providers = \WP2FA\Admin\Controllers\Settings::get_providers();
					$names     = \WP2FA\Admin\Controllers\Settings::get_providers_translate_names();

					foreach ( $providers as $class => $provider ) {
						$policy_name = '';

						if ( is_string( $class ) && \class_exists( (string) $class ) ) {
							try {
								if ( constant( $class . '::POLICY_SETTINGS_NAME' ) ) {

									$policy_name = $class::POLICY_SETTINGS_NAME;
								}
							} catch ( \Error $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
								// Do nothing.
							}
						} else {
							// 2FA is still older version fallback to array key.
							$methods = array(
								'totp'         => 'enable_totp',
								'oob'          => 'enable_oob_email',
								'email'        => 'enable_email',
								'yubico'       => 'enable_yubico',
								'clickatell'   => 'enable_clickatell',
								'twilio'       => 'enable_twilio',
								'authy'        => 'enable_authy',
								'passkeys'     => 'enable_passkeys',
								'backup_codes' => 'backup_codes_enabled',
								'backup_email' => 'enable-email-backup',
							);

							$policy_name = ( isset( $methods[ $provider ] ) ) ? $methods[ $provider ] : '';
						}

						if ( ! empty( $policy_name ) ) {
							$old_val = $old_value[ $policy_name ] ?? null;
							$new_val = $new_value[ $policy_name ] ?? null;

							if ( $old_val !== $new_val ) {
								$variables = array(
									'method'    => $names[ $provider ],
									'EventType' => ! empty( $new_val ) ? 'enabled' : 'disabled',
								);

								Alert_Manager::trigger_event( 7804, $variables );
							}
						}
					}
				}

				if ( ( isset( $new_value['enable_trusted_devices'] ) && ! isset( $old_value['enable_trusted_devices'] ) ) || ( isset( $new_value['enable_trusted_devices'] ) && isset( $old_value['enable_trusted_devices'] ) && isset( $old_value['enable_trusted_devices'] ) && $old_value['enable_trusted_devices'] !== $new_value['enable_trusted_devices'] ) ) {
					$alert_code = 7805;
					$variables  = array(
						'EventType' => ! empty( $new_value['enable_trusted_devices'] ) ? 'enabled' : 'disabled',
					);
					Alert_Manager::trigger_event( $alert_code, $variables );
				}

				if ( ( isset( $new_value['trusted-devices-period'] ) && ! isset( $old_value['trusted-devices-period'] ) ) || ( isset( $new_value['trusted-devices-period'] ) && isset( $old_value['trusted-devices-period'] ) && $old_value['trusted-devices-period'] !== $new_value['trusted-devices-period'] ) ) {
					$alert_code = 7806;
					$variables  = array(
						'old_value' => $old_value['trusted-devices-period'] ?? '',
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

			if ( 'wp_2fa_enabled_methods' === $meta_key ) {
				if ( isset( self::$old_user_meta['wp_2fa_enabled_methods'] ) && ! empty( self::$old_user_meta['wp_2fa_enabled_methods'] ) ) {
					$alert_code = 7809;
					$variables  = array(
						'new_method'   => $_meta_value,
						'old_method'   => self::$old_user_meta['wp_2fa_enabled_methods'],
						'EditUserLink' => add_query_arg( 'user_id', $user_id, \network_admin_url( 'user-edit.php' ) ),
					);
					Alert_Manager::trigger_event( $alert_code, $variables );
				} else {
					$alert_code = 7808;
					$variables  = array(
						'method'       => $_meta_value,
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
		public static function user_deletions_trigger( $meta_id, $user_id, $meta_key, $_meta_value ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

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
			\add_filter(
				'wsal_event_objects',
				array( WP_2FA_Helper::class, 'add_custom_event_objects' )
			);

			if ( WP_2FA_Helper::is_wp2fa_active() ) {
				\add_action( 'update_user_metadata', array( __CLASS__, 'store_user_meta' ), 1, 4 );
				\add_action( 'add_user_metadata', array( __CLASS__, 'store_user_meta' ), 1, 4 );
				\add_action( 'updated_user_meta', array( __CLASS__, 'user_trigger' ), 10, 4 );
				\add_action( 'added_user_meta', array( __CLASS__, 'user_trigger' ), 10, 4 );
				\add_action( 'delete_user_meta', array( __CLASS__, 'user_deletions_trigger' ), 10, 4 );
			}
		}

		/**
		 * Keeps old metadata of the user for comparison.
		 *
		 * @param int    $meta_id ID of the metadata entry to update.
		 * @param int    $user_id ID of the user metadata is for.
		 * @param string $meta_key Metadata key.
		 * @param mixed  $_meta_value Metadata value. Serialized if non-scalar.
		 *
		 * @since 5.4.2
		 */
		public static function store_user_meta( $meta_id, $user_id, $meta_key, $_meta_value ) {

			if ( 'wp_2fa_enabled_methods' === $meta_key ) {
				self::$old_user_meta['wp_2fa_enabled_methods'] = \get_user_meta( $user_id, 'wp_2fa_enabled_methods', true );
			}
		}

		// /**
		// * Fires when user is unlocked.
		// *
		// * @param \WP_User $user - The user object of the user being unlocked.
		// *
		// * @return void
		// *
		// * @since 5.4.2
		// */
		// public static function user_unlock_trigger( $user ) {

		// $alert_code = 7812;
		// $variables  = array(
		// 'EditUserLink' => add_query_arg( 'user_id', $user->ID, \network_admin_url( 'user-edit.php' ) ),
		// );
		// Alert_Manager::trigger_event( $alert_code, $variables );
		// }
	}
}
