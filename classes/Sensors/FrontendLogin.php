<?php
/**
 * Frontend user login sensor.
 *
 * @package wsal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Frontend user login sensor.
 */
class WSAL_Sensors_FrontendLogin extends WSAL_AbstractSensor {

	/**
	 * Listening to events using WP hooks.
	 */
	public function HookEvents() {
		add_action( 'wp_login', array( $this, 'event_login' ), 10, 2 );

		if ( WpSecurityAuditLog::is_twofactor_active() ) {
			add_action( 'login_redirect', array( $this, 'event_2fa_login' ), 10, 1 );
		}
	}

	/**
	 * Event Login.
	 *
	 * @param string $user_login - Username.
	 * @param object $user - WP_User object.
	 */
	public function event_login( $user_login, $user ) {
		if ( empty( $user ) ) {
			$user = get_user_by( 'login', $user_login );
		}

		$user_roles = $this->plugin->settings->GetCurrentUserRoles( $user->roles );

		if ( $this->plugin->settings->IsLoginSuperAdmin( $user_login ) ) {
			$user_roles[] = 'superadmin';
		}

		$this->plugin->alerts->Trigger(
			1000,
			array(
				'Username'         => $user_login,
				'CurrentUserRoles' => $user_roles,
			),
			true
		);
	}

	/**
	 * Login Event for Two-Factor plugin.
	 *
	 * @since 3.3
	 *
	 * @param string $redirect_url – Redirect URL.
	 * @return string
	 */
	public function event_2fa_login( $redirect_url ) {
		// @codingStandardsIgnoreStart
		$provider = isset( $_POST['provider'] ) ? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : '';
		$user_id  = isset( $_POST['wp-auth-id'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['wp-auth-id'] ) ) : '';
		// @codingStandardsIgnoreEnd

		// Default Two-Factor options.
		$providers_2fa = array( 'Two_Factor_Email', 'Two_Factor_Totp', 'Two_Factor_FIDO_U2F', 'Two_Factor_Backup_Codes', 'Two_Factor_Dummy' );

		// Get users.
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return $redirect_url;
		}

		// If provider and user are set and provider is known then log the event.
		if ( $provider && $user && in_array( $provider, $providers_2fa, true ) ) {
			// Get user roles.
			$user_roles = $this->plugin->settings->GetCurrentUserRoles( $user->roles );

			if ( $this->plugin->settings->IsLoginSuperAdmin( $user->user_login ) ) {
				$user_roles[] = 'superadmin';
			}

			$this->plugin->alerts->Trigger(
				1000,
				array(
					'Username'         => $user->user_login,
					'CurrentUserRoles' => $user_roles,
				),
				true
			);
		}

		return $redirect_url;
	}
}
