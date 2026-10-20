<?php
/**
 * Licensing factory for WP Activity Log.
 *
 * @package    wsal
 * @subpackage licensing
 *
 * @since 5.6.8
 */

declare(strict_types=1);

namespace WSAL\Licensing;

use WSAL\Helpers\Uninstall_Helper;
use WSAL\Licensing\Providers\License_Page;
use WSAL\Licensing\Providers\License_Notices;
use WSAL\Licensing\Providers\EDD\EDD_Provider;
use WSAL\Licensing\Providers\Freemius\Freemius_Provider;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Licensing\Licensing_Factory' ) ) {

	/**
	 * Provides a provider-neutral entry point for licensing operations.
	 *
	 * @since 5.6.8
	 */
	class Licensing_Factory {

		/**
		 * Plan names from lowest to highest.
		 *
		 * @var string[]
		 *
		 * @since 5.6.8
		 */
		public const PLANS = array( 'starter', 'professional', 'business', 'enterprise', 'premium', 'enterprisesupport' );

		/**
		 * EDD product IDs and the plan from self::PLANS that each product grants.
		 *
		 * Each product maps to the Freemius plan sold under the same name, so EDD and Freemius
		 * customers get the same features.
		 *
		 * @var array
		 *
		 * @since 5.6.8
		 */
		public const EDD_PRODUCT_PLANS = array(
			281 => 'premium',
			284 => 'enterprise',
			286 => 'enterprisesupport',
		);

		/**
		 * Site option storing the selected provider name.
		 *
		 * @var string
		 *
		 * @since 5.6.8
		 */
		public const PROVIDER_OPTION = 'wsal_licensing_provider';

		/**
		 * Prefix of Freemius license keys.
		 *
		 * @var string
		 *
		 * @since 5.6.8
		 */
		public const FREEMIUS_KEY_PREFIX = 'sk_';

		/**
		 * Prefix of EDD license keys.
		 *
		 * @var string
		 *
		 * @since 5.6.8
		 */
		public const EDD_KEY_PREFIX = 'edd_';

		/**
		 * Freemius provider name.
		 *
		 * @var string
		 *
		 * @since 5.6.8
		 */
		public const FREEMIUS_PROVIDER_NAME = 'freemius';

		/**
		 * EDD provider name.
		 *
		 * @var string
		 *
		 * @since 5.6.8
		 */
		public const EDD_PROVIDER_NAME = 'edd';

		/**
		 * Registered provider classes keyed by provider name, in fallback order.
		 *
		 * @var array
		 *
		 * @since 5.6.8
		 */
		private const PROVIDERS = array(
			self::FREEMIUS_PROVIDER_NAME => Freemius_Provider::class,
			self::EDD_PROVIDER_NAME      => EDD_Provider::class,
		);

		/**
		 * Site option Freemius stores the cached license state in. Its presence means Freemius was used on this site.
		 *
		 * @var string
		 *
		 * @since 5.6.8
		 */
		public const FREEMIUS_LICENSE_OPTION = 'fs_wsalp';

		/**
		 * Prefix of the site options the EDD provider stores the license in.
		 *
		 * @var string
		 *
		 * @since 5.6.8
		 */
		public const EDD_OPTION_PREFIX = 'wsal_edd_';

		/**
		 * Active provider class name.
		 *
		 * @var class-string<Licensing_Provider>|null
		 *
		 * @since 5.6.8
		 */
		private static $provider = null;

		/**
		 * Whether the active provider has been selected during this request.
		 *
		 * @var bool
		 *
		 * @since 5.6.8
		 */
		private static $is_provider_resolved = false;

		/**
		 * Whether this site is connected to Freemius, checked once per request.
		 *
		 * @var bool|null
		 *
		 * @since 5.6.8
		 */
		private static $is_freemius_connected = null;

		/**
		 * Returns a registered provider class that can be used in this build.
		 *
		 * @param string $provider_name - Provider name.
		 *
		 * @return string|null $provider - Provider class name, or null when the provider is unknown or unavailable.
		 *
		 * @since 5.6.8
		 */
		private static function get_available_provider( string $provider_name ) {
			$provider = self::PROVIDERS[ $provider_name ] ?? null;

			// The Free build ships without the provider classes.
			if ( null === $provider || ! class_exists( $provider ) || ! $provider::is_available() ) {
				return null;
			}

			return $provider;
		}

		/**
		 * Checks whether this site is connected to Freemius.
		 *
		 * The Free build ships without the Freemius provider, so it is never connected there. The answer
		 * is kept until the end of the request, because the Freemius records are read without the option cache.
		 *
		 * @return bool $is_connected - True in the Premium build when Freemius can manage this site's license.
		 *
		 * @since 5.6.8
		 */
		public static function is_freemius_connected(): bool {
			if ( null === self::$is_freemius_connected ) {
				self::$is_freemius_connected = self::is_premium_build() && Freemius_Provider::is_connected();
			}

			return self::$is_freemius_connected;
		}

		/**
		 * Selects the provider for this site.
		 *
		 * The stored provider choice wins. Without one, Freemius is used when it was used on this
		 * site before. A site with no license yet gets no provider, so it shows the license form
		 * instead of a provider's own connection screen.
		 *
		 * Freemius only counts while the site is still connected to it: its stored choice and cached
		 * license option survive a disconnect, and loading the SDK without a connection shows its
		 * registration screen, where an EDD key can't be entered.
		 *
		 * The Free build ships without providers, so it never gets one.
		 *
		 * @return string|null $provider - Provider class name, or null when no provider is available.
		 *
		 * @since 5.6.8
		 */
		private static function resolve_provider() {
			$stored_provider_name = self::get_stored_provider_name();

			// If the saved provider is Freemius but the site is no longer connected to Freemius.
			if ( self::FREEMIUS_PROVIDER_NAME === $stored_provider_name && ! self::is_freemius_connected() ) {
				$stored_provider_name = '';
			}

			if ( '' !== $stored_provider_name ) {
				$provider = self::get_available_provider( $stored_provider_name );

				if ( null !== $provider ) {
					return $provider;
				}
			}


			// If the site was licensed with Freemius before the provider choice was saved, and is still connected to Freemius.
			if ( self::has_freemius_license_data() && self::is_freemius_connected() ) {
				return self::get_available_provider( self::FREEMIUS_PROVIDER_NAME );
			}

			return null;
		}

		/**
		 * Returns the stored provider name.
		 *
		 * @return string $provider_name - Stored provider name, or an empty string when none is stored.
		 *
		 * @since 5.6.8
		 */
		public static function get_stored_provider_name(): string {
			$provider_name = \get_site_option( self::PROVIDER_OPTION, '' );

			return is_string( $provider_name ) ? $provider_name : '';
		}

		/**
		 * Checks whether Freemius was used on this site, based on its cached license option.
		 *
		 * @return bool $has_data - True when the Freemius license option exists.
		 *
		 * @since 5.6.8
		 */
		public static function has_freemius_license_data(): bool {
			return null !== \get_site_option( self::FREEMIUS_LICENSE_OPTION, null );
		}

		/**
		 * Checks whether an option holds license data, regardless of the option name's letter case.
		 *
		 * @param string $option_name - Option name.
		 *
		 * @return bool $is_license_option - True for the stored provider choice and the EDD license options.
		 *
		 * @since 5.6.8
		 */
		public static function is_license_option( string $option_name ): bool {
			// WordPress option names use a case-insensitive database collation by default.
			$option_name = strtolower( $option_name );

			if ( self::PROVIDER_OPTION === $option_name ) {
				return true;
			}

			// True only when the option name starts with the EDD prefix.
			return 0 === strpos( $option_name, self::EDD_OPTION_PREFIX );
		}

		/**
		 * Checks whether the Premium build has no licensing provider, so a license has to be activated first.
		 *
		 * @return bool $needs_activation - True when the license form must be shown instead of the plugin pages.
		 *
		 * @since 5.6.8
		 */
		public static function needs_license_activation(): bool {
			return self::is_premium_build() && null === self::get_provider();
		}

		/**
		 * Returns the name of the active provider.
		 *
		 * @return string $provider_name - Provider name, or an empty string when no provider is active.
		 *
		 * @since 5.6.8
		 */
		public static function get_provider_name(): string {
			$provider = self::get_provider();

			return null !== $provider ? $provider::get_provider_name() : '';
		}

		/**
		 * Registers the uninstall callback with WordPress, unless Freemius handles the uninstall.
		 *
		 * WordPress keeps one uninstall callback per plugin. Freemius registers its own and runs the
		 * plugin's cleanup through its after_uninstall action, so it must not be replaced while the site
		 * is connected to Freemius. The stored options are read directly, because this runs before the
		 * migrations and must not cache the provider for the request.
		 *
		 * @return void
		 *
		 * @since 5.6.8
		 */
		public static function maybe_register_uninstall_hook() {
			$stored_provider_name = self::get_stored_provider_name();
			$uses_freemius        = false;

			if ( self::FREEMIUS_PROVIDER_NAME === $stored_provider_name ) {
				$uses_freemius = true;
			}

			// Sites licensed with Freemius before the provider choice was stored.
			if ( '' === $stored_provider_name && self::has_freemius_license_data() ) {
				$uses_freemius = true;
			}

			if ( $uses_freemius && self::is_freemius_connected() ) {
				return;
			}

			\register_uninstall_hook( WSAL_BASE_NAME, array( Uninstall_Helper::class, 'uninstall' ) );
		}

		/**
		 * Frees the license activations and removes the license data when the plugin is uninstalled.
		 *
		 * A deleted plugin must not keep using activations or leave its license key behind. Only EDD
		 * licenses need it, and the Free build ships without the EDD provider.
		 *
		 * @return void
		 *
		 * @since 5.6.8
		 */
		public static function remove_license_on_uninstall() {
			if ( ! self::is_premium_build() ) {
				return;
			}

			// The stored choice is read, so the license is also removed while EDD is disabled with the wsal_edd_provider_available filter.
			if ( self::EDD_PROVIDER_NAME !== self::get_stored_provider_name() ) {
				return;
			}

			EDD_Provider::uninstall_cleanup();

			\delete_site_option( self::PROVIDER_OPTION );
		}

		/**
		 * Returns the active provider class.
		 *
		 * @param bool $force_refresh - Whether to select the provider again, checking the Freemius connection again too.
		 *
		 * @return string|null $provider - Provider class name, or null when no provider is available.
		 *
		 * @since 5.6.8
		 */
		public static function get_provider( bool $force_refresh = false ) {
			if ( $force_refresh ) {
				self::$is_provider_resolved  = false;
				self::$is_freemius_connected = null;
			}

			if ( ! self::$is_provider_resolved ) {
				self::$provider             = self::resolve_provider();
				self::$is_provider_resolved = true;
			}

			return self::$provider;
		}

		/**
		 * Registers the licensing hooks that must run on every request, front end included.
		 *
		 * The EDD provider activates the license of each new subsite, and WordPress can create subsites
		 * outside the admin, where the provider itself is not initialized.
		 *
		 * @return void
		 *
		 * @since 5.6.8
		 */
		public static function init_network_site_hooks() {
			// The Free build never has a provider, so it always returns here.
			if ( self::EDD_PROVIDER_NAME !== self::get_provider_name() ) {
				return;
			}

			EDD_Provider::init_network_site_hooks();
		}

		/**
		 * Initializes the active licensing provider.
		 *
		 * @return void
		 *
		 * @since 5.6.8
		 */
		public static function init() {
			$provider = self::get_provider();

			if ( null !== $provider ) {
				$provider::init();
			}

			// The license page ships in the Premium build only.
			if ( self::is_premium_build() ) {
				License_Page::init();
			}
		}

		/**
		 * Returns the provider name a license key belongs to, based on its prefix.
		 *
		 * Freemius keys start with "sk_" and EDD keys start with "edd_".
		 *
		 * @param string $license_key - License key.
		 *
		 * @return string $provider_name - Provider name, or an empty string when the key format is not recognised.
		 *
		 * @since 5.6.8
		 */
		public static function detect_provider_name( string $license_key ): string {
			$license_key = trim( $license_key );

			if ( 0 === strpos( $license_key, self::FREEMIUS_KEY_PREFIX ) ) {
				return self::FREEMIUS_PROVIDER_NAME;
			}

			if ( 0 === strpos( $license_key, self::EDD_KEY_PREFIX ) ) {
				return self::EDD_PROVIDER_NAME;
			}

			return '';
		}

		/**
		 * Checks whether this is the Premium build of the plugin.
		 *
		 * This is a property of the build, not of the license, so it does not depend on the provider.
		 *
		 * @return bool $is_premium_build - True in the Premium build.
		 *
		 * @since 5.6.8
		 */
		public static function is_premium_build(): bool {
			$is_premium_build = false;


			return $is_premium_build;
		}

		/**
		 * Checks whether the active license is valid.
		 *
		 * @return bool $is_valid - True when the active license is valid.
		 *
		 * @since 5.6.8
		 */
		public static function has_active_valid_license(): bool {
			$provider = self::get_provider();

			return null !== $provider && $provider::has_active_valid_license();
		}

		/**
		 * Checks the cached license state without contacting the provider.
		 *
		 * @return bool $is_valid - True when the cached license state is valid.
		 *
		 * @since 5.6.8
		 */
		public static function has_cached_valid_license(): bool {
			$provider = self::get_provider();

			return null !== $provider && $provider::has_cached_valid_license();
		}

		/**
		 * Checks whether premium code may be used, including during a trial.
		 *
		 * @return bool $can_use - True when premium code may be used.
		 *
		 * @since 5.6.8
		 */
		public static function can_use_premium_code(): bool {
			$provider = self::get_provider();

			return null !== $provider && $provider::can_use_premium_code();
		}

		/**
		 * Checks whether premium settings are read-only while premium features keep running.
		 *
		 * @return bool $is_frozen - True when premium settings must not be edited.
		 *
		 * @since 5.6.8
		 */
		public static function is_premium_frozen(): bool {
			$provider = self::get_provider();

			return null !== $provider && $provider::is_premium_frozen();
		}

		/**
		 * Prints the notice that replaces premium settings while they are read-only.
		 *
		 * @return bool $is_rendered - True when premium settings are read-only and the notice was printed.
		 *
		 * @since 5.6.8
		 */
		public static function maybe_render_frozen_notice(): bool {
			$provider = self::get_provider();

			if ( null === $provider ) {
				return false;
			}

			$license_state = $provider::get_frozen_license_state();

			if ( empty( $license_state ) ) {
				return false;
			}

			License_Notices::render_frozen_notice( $license_state, true );

			return true;
		}

		/**
		 * Checks whether the site has no paid, features-enabled license.
		 *
		 * @return bool $is_free - True when the site uses the free plan or no provider is available.
		 *
		 * @since 5.6.8
		 */
		public static function is_free_plan(): bool {
			$provider = self::get_provider();

			return null === $provider || $provider::is_free_plan();
		}

		/**
		 * Checks whether a plan satisfies a required plan, using the order in self::PLANS.
		 *
		 * @param string $current_plan  - Plan reported by the provider.
		 * @param string $required_plan - Plan required by the caller.
		 * @param bool   $exact         - Whether only the required plan itself matches.
		 *
		 * @return bool $matches - True when the current plan is the required plan, or a higher plan when $exact is false.
		 *
		 * @since 5.6.8
		 */
		private static function plan_matches( string $current_plan, string $required_plan, bool $exact ): bool {
			if ( '' === $current_plan ) {
				return false;
			}

			$required_plan = strtolower( $required_plan );

			if ( $current_plan === $required_plan ) {
				return true;
			}

			if ( $exact ) {
				return false;
			}

			$current_order  = array_search( $current_plan, self::PLANS, true );
			$required_order = array_search( $required_plan, self::PLANS, true );

			if ( false === $current_order || false === $required_order ) {
				return false;
			}

			return $current_order > $required_order;
		}

		/**
		 * Checks whether the active paid plan matches a plan.
		 *
		 * @param string $plan  - Plan name from self::PLANS.
		 * @param bool   $exact - Whether only the plan itself matches, not higher plans.
		 *
		 * @return bool $matches - True when the plan matches.
		 *
		 * @since 5.6.8
		 */
		public static function is_plan( string $plan, bool $exact = false ): bool {
			$provider = self::get_provider();

			if ( null === $provider ) {
				return false;
			}

			return self::plan_matches( $provider::get_plan_name(), $plan, $exact );
		}

		/**
		 * Checks whether the active paid plan or trial plan matches a plan.
		 *
		 * @param string $plan  - Plan name from self::PLANS.
		 * @param bool   $exact - Whether only the plan itself matches, not higher plans.
		 *
		 * @return bool $matches - True when the plan or trial plan matches.
		 *
		 * @since 5.6.8
		 */
		public static function is_plan_or_trial( string $plan, bool $exact = false ): bool {
			if ( self::is_plan( $plan, $exact ) ) {
				return true;
			}

			$provider = self::get_provider();

			if ( null === $provider ) {
				return false;
			}

			return self::plan_matches( $provider::get_trial_plan_name(), $plan, $exact );
		}

		/**
		 * Refreshes the cached license state from the active provider.
		 *
		 * @return bool $is_synchronized - True when synchronization completes.
		 *
		 * @since 5.6.8
		 */
		public static function sync_license(): bool {
			$provider = self::get_provider();

			return null !== $provider && $provider::sync_license();
		}

		/**
		 * Deletes locally cached license data and the stored provider choice.
		 *
		 * @return void
		 *
		 * @since 5.6.8
		 */
		public static function delete_local_license_data() {
			$provider = self::get_provider();

			if ( null !== $provider ) {
				$provider::delete_local_license_data();
			}

			\delete_site_option( self::PROVIDER_OPTION );

			self::$is_provider_resolved = false;
		}

		/**
		 * Activates a license key with the provider matching the key format.
		 *
		 * The provider choice is stored only after a successful activation, so a rejected key
		 * never changes the selected provider.
		 *
		 * @param string $license_key - License key to activate.
		 *
		 * @return bool|\WP_Error $result - True when the license is activated, or the reason it failed.
		 *
		 * @since 5.6.8
		 */
		public static function activate_license( string $license_key ) {
			$license_key = trim( $license_key );

			if ( '' === $license_key ) {
				return new \WP_Error( 'wsal_license_key_missing', \__( 'Please enter a license key.', 'wp-security-audit-log' ) );
			}

			$provider_name = self::detect_provider_name( $license_key );

			if ( '' === $provider_name ) {
				return new \WP_Error( 'wsal_license_key_invalid', \__( 'This license key is not valid. Please check it and try again.', 'wp-security-audit-log' ) );
			}

			$provider = self::get_available_provider( $provider_name );

			if ( null === $provider ) {
				return new \WP_Error( 'wsal_license_provider_unavailable', \__( 'This type of license key is not supported by this version of WP Activity Log.', 'wp-security-audit-log' ) );
			}

			$result = $provider::activate_license( $license_key );

			if ( \is_wp_error( $result ) ) {
				return $result;
			}

			if ( true !== $result ) {
				return new \WP_Error( 'wsal_license_activation_failed', \__( 'The license could not be activated.', 'wp-security-audit-log' ) );
			}

			\update_site_option( self::PROVIDER_OPTION, $provider_name );

			self::$is_provider_resolved = false;

			return true;
		}

		/**
		 * Deactivates the license through the active provider.
		 *
		 * @return bool $is_deactivated - True when deactivation completes.
		 *
		 * @since 5.6.8
		 */
		public static function deactivate_license(): bool {
			$provider = self::get_provider();

			return null !== $provider && $provider::deactivate_license();
		}

		/**
		 * Registers the callback that removes plugin data when the plugin is uninstalled.
		 *
		 * @param callable $callback - Uninstall callback.
		 *
		 * @return void
		 *
		 * @since 5.6.8
		 */
		public static function register_uninstall_handler( callable $callback ) {
			$provider = self::get_provider();

			if ( null !== $provider ) {
				$provider::register_uninstall_handler( $callback );
			}
		}

		/**
		 * Returns the provider support contact form markup.
		 *
		 * @return string $form - Contact form markup, or an empty string when unavailable.
		 *
		 * @since 5.6.8
		 */
		public static function get_support_contact_form(): string {
			$provider = self::get_provider();

			return null !== $provider ? $provider::get_support_contact_form() : '';
		}

		/**
		 * Applies provider-specific setup for MainWP stealth mode.
		 *
		 * @return void
		 *
		 * @since 5.6.8
		 */
		public static function prepare_mainwp_stealth_mode() {
			$provider = self::get_provider();

			if ( null !== $provider ) {
				$provider::prepare_mainwp_stealth_mode();
			}
		}
	}
}
