<?php
/**
 * WSAL Freemius SDK
 *
 * Freemius SDK initialization file for WSAL.
 *
 * @since 2.7.0
 * @package Wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( file_exists( dirname( __FILE__ ) . '/freemius/start.php' ) ) {

	/**
	 * Freemius SDK
	 *
	 * Create a helper function for easy SDK access.
	 *
	 * @return Freemius
	 * @throws Freemius_Exception
	 */
	function wsal_freemius() {
		global $wsal_freemius;

		if ( ! isset( $wsal_freemius ) && ! apply_filters( 'wsal_disable_freemius_sdk', false ) ) {
			define( 'WP_FS__PRODUCT_94_MULTISITE', true );

			// Include Freemius SDK.
			require_once dirname( __FILE__ ) . '/freemius/start.php';

			// Check anonymous mode.
			$freemius_state = \WSAL\Helpers\Options::get_option_value_ignore_prefix( 'wsal_freemius_state', 'anonymous' );
			$is_anonymous   = ( 'anonymous' === $freemius_state || 'skipped' === $freemius_state );
			$is_premium     = false;
			$is_anonymous   = $is_premium ? false : $is_anonymous;

			// Trial arguments.
			$trial_args = array(
				'days'               => 7,
				'is_require_payment' => false,
			);

			if ( WpSecurityAuditLog::is_mainwp_active() && ! is_multisite() ) {
				$trial_args = false;
			}

			$wsal_freemius = fs_dynamic_init(
				array(
					'id'              => '94',
					'slug'            => 'wp-security-audit-log',
					'type'            => 'plugin',
					'public_key'      => 'pk_d602740d3088272d75906045af9fa',
					'premium_suffix'  => '(Premium)',
					'is_premium'      => $is_premium,
					'has_addons'      => false,
					'has_paid_plans'  => true,
					'trial'           => $trial_args,
					'has_affiliation' => false,
					'menu'            => array(
						'slug'        => 'wsal-auditlog',
						'support'     => false,
						'affiliation' => false,
						'network'     => true,
					),
					'anonymous_mode'  => $is_anonymous,
					'live'            => true,
				)
			);
		}

		return apply_filters( 'wsal_freemius_sdk_object', $wsal_freemius );
	}

	// Init Freemius.
	wsal_freemius();

	// Signal that SDK was initiated.
	do_action( 'wsal_freemius_loaded' );
}
