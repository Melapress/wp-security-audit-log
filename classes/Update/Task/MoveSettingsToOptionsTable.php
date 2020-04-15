<?php
/**
 * Task to handle moving some options from the custom table to the default
 * site options table.
 *
 * NOTE: This helps to simply setting/getting options and also benefits from
 * WPs in-built caching optimizations already applied to that table.
 *
 * @package Wsal
 * @since 4.0.3
 */

namespace WSAL\Update\Task;

/**
 * Remaps remaps old cron names to new ones and schedules them at the same
 * time as the original was scheduled.
 */
class MoveSettingsToOptionstable {

	/**
	 * Holds the main plugin instance to work on.
	 *
	 * @var \WpSecurityAuditLog
	 */
	private $wsal;

	/**
	 * The prefix to use when creating the new settings.
	 *
	 * @var string
	 */
	private $prefix = '';

	/**
	 * The version we are upgrading from.
	 *
	 * @var null|string
	 */
	private $old_version;

	/**
	 * The version we upgraded to.
	 *
	 * @var null|string
	 */
	private $new_version;

	/**
	 * Sets up the class properties.
	 *
	 * @method __construct
	 * @since  4.0.3
	 * @param  \WpSecurityAuditLog $wsal   An instance of the main plugin.
	 * @param  string              $prefix The prefix string to prepend to options.
	 */
	public function __construct( $wsal, $prefix = '' ) {
		$this->wsal   = $wsal;
		$this->prefix = $prefix;
		add_filter( 'wsal_update_move_settings', array( $this, 'settings_to_move_4_0_3' ) );
	}

	/**
	 * Passes in the version strings so we can decide what settings to move.
	 *
	 * @method pass_versions
	 * @since  4.0.3
	 * @param  string $old_version The old version string.
	 * @param  string $new_version The new version string.
	 */
	public function set_versions( $old_version = '', $new_version = '' ) {
		$this->old_version = $old_version;
		$this->new_version = $new_version;
	}

	/**
	 * Loop through an array of cron tasks and remap them if the name changed.
	 *
	 * NOTE: The expected data used in this is a single dimentional array with
	 * option names.
	 *
	 * @method run
	 * @since  4.0.2
	 */
	public function run() {
		// bail early if we don't have a main plugin object to work with.
		if ( null === $this->wsal || ! is_a( $this->wsal, 'WpSecurityAuditLog' ) ) {
			return;
		}
		// bail early if options versions were not yet set.
		if ( null === $this->old_version || null === $this->new_version ) {
			return;
		}

		// get an array of options to move.
		$settings_to_move = apply_filters( 'wsal_update_move_settings', array() );

		// Loop through array of options to move to WP options table.
		foreach ( $settings_to_move as $setting ) {
			$value = $this->wsal->options_helper->get_option_value( $setting );
			// to prevent override of already migrated data we will first check
			// if option value exists in the standard options table.
			if ( null !== $value ) {
				// skip to next item as this already exists in options table.
				continue;
			}

			// no options value existed in the default table, check in the
			// plugins custom table.
			$value = $this->wsal->GetGlobalOption( $setting );
			if ( $value ) {
				// if we got data from the custom table save it.
				$this->wsal->options_helper->set_option_value( $setting, $value );
			}
		}
	}

	/**
	 * Filters in a list of settings to move in the 4.0.3 update.
	 *
	 * NOTE: Should fire if coming from before 4.0.1 to 4.0.3 or later.
	 *
	 * @method settings_to_move_4_0_3
	 * @since  4.0.3
	 * @param  array $settings An array of settings to move.
	 * @return array
	 */
	public function settings_to_move_4_0_3( $settings ) {
		if ( \version_compare( $this->old_version, '4.0.2', '>=' ) && \version_compare( $this->new_version, '4.0.3', '<=' ) ) {
			// settings moved in this version update.
			$settings = array_merge(
				$settings,
				array(
					'pruning-date-e',
					'pruning-date',
					'pruning-unit',
					'version',
					'timezone',
					'type_username',
					'dismissed-privacy-notice',
					'columns',
					'disabled-alerts',
					'wp-backend',
				)
			);
		}
		// Return an array of all the setting we are wanting moved.
		return $settings;
	}

}
