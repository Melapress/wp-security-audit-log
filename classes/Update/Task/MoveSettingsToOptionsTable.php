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
class MoveSettingsToOptionsTable {

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
	 * @param \WpSecurityAuditLog $wsal An instance of the main plugin.
	 * @param string $prefix The prefix string to prepend to options.
	 *
	 * @since  4.0.3
	 */
	public function __construct( $wsal, $prefix = '' ) {
		$this->wsal   = $wsal;
		$this->prefix = $prefix;
		add_filter( 'wsal_update_move_settings', array( $this, 'settings_to_move_4_0_3' ) );
		add_filter( 'wsal_update_move_settings', array( $this, 'settings_to_move_4_0_1' ) );
		add_filter( 'wsal_update_move_settings', array( $this, 'settings_to_move_4_1_3' ) );
	}

	/**
	 * Passes in the version strings so we can decide what settings to move.
	 *
	 * @method pass_versions
	 * @param string $old_version The old version string.
	 * @param string $new_version The new version string.
	 *
	 * @since  4.0.3
	 */
	public function set_versions( $old_version = '', $new_version = '' ) {
		$this->old_version = $old_version;
		$this->new_version = $new_version;
	}

	/**
	 * Loop through an array of cron tasks and remap them if the name changed.
	 *
	 * NOTE: The expected data used in this is a single dimensional array with
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
			// the wsal- prefix needs stripped from this option.
			if ( false !== ( 'wsal-' === substr( $setting, 0, 5 ) ) ) {
				$setting = str_replace( 'wsal-', '', $setting );
			}
			$value = $this->wsal->GetGlobalSetting( $setting );
			// to prevent override of already migrated data we will first check
			// if option value exists in the standard options table.
			if ( null === $value || false === $value ) {
				// only continue with the current item if it doesn't exist already in the WP options table

				// no options value existed in the default table, check in the
				// plugins custom table.
				$value = $this->wsal->GetGlobalOption( $setting );
				if ( $value ) {
					// if we got data from the custom table save it.
					$this->wsal->SetGlobalSetting( $setting, $value );
				}
			}

			//  delete the option from the custom table
			$this->wsal->DeleteByName( $setting );
			$this->wsal->DeleteByName( 'wsal-' . $setting );
			$this->wsal->DeleteByName( 'wsal_' . $setting );
		}
	}

	/**
	 * Filters in a list of settings to move in the 4.0.3 update.
	 *
	 * NOTE: Should fire if coming from before 4.0.1 to 4.0.3 or later.
	 *
	 * @method settings_to_move_4_0_3
	 * @param array $settings An array of settings to move.
	 *
	 * @return array
	 * @since  4.0.3
	 */
	public function settings_to_move_4_0_3( $settings ) {
		if ( \version_compare( $this->old_version, '4.0.2', '>=' ) && \version_compare( $this->old_version, '4.0.3', '<' ) ) {
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

	/**
	 * Filters in a list of settings to move in the 4.0.3 update.
	 *
	 * NOTE: Should fire if coming from before 4.0.1 to 4.0.3 or later.
	 *
	 * @method settings_to_move_4_0_3
	 * @param array $settings An array of settings to move.
	 *
	 * @return array
	 * @since  4.1.0
	 */
	public function settings_to_move_4_0_1( $settings ) {
		if ( \version_compare( $this->old_version, '4.0.2', '>' ) && \version_compare( $this->old_version, '4.1.3', '<=' ) ) {
			// settings moved in this version update.
			$settings = array_merge(
				$settings,
				array(
					'log-404',
					'log-404-limit',
					'log-404-referrer',
					'purge-404-log',
					'wsal-setup-modal-dismissed',
					'log-visitor-failed-login-limit',
					'log-failed-login-limit',
					'log-visitor-404-limit',
					'log-visitor-404-referrer',
					'purge-visitor-404-log',
					'log-visitor-404',
				)
			);
		}

		// Return an array of all the setting we are wanting moved.
		return $settings;
	}

	/**
	 * Filters in a list of settings to move in the 4.1.3 update.
	 *
	 * @param array $settings An array of settings to move.
	 *
	 * @return array
	 * @since  4.1.3
	 */
	public function settings_to_move_4_1_3( $settings ) {
		if ( \version_compare( $this->old_version, '4.1.3', '<=' ) ) {
			$this->wsal->settings();
			$options = new \WSAL_Models_Option();
			$adapter = $options->getAdapter();
			$all_options = $adapter->LoadArray('1=%d', array(1));
			if (!empty($all_options)) {
				/** @var \WSAL_Models_Option $option */
				foreach ($all_options as $option) {
					array_push($settings, preg_replace('/^wsal[\-_]/', '', $option->option_name));
				}
			}
		}

		// Return an array of all the setting we are wanting moved.
		return array_unique($settings);
	}

}
