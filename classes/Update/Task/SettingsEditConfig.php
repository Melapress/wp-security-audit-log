<?php
/**
 * Task to handle updating the settings that allow different users permission
 * to edit the plugin settings.
 *
 * @package Wsal
 * @since 4.0.2
 */

namespace WSAL\Update\Task;

/**
 *
 */
class SettingsEditConfig {

	/**
	 * Holds the main plugin instance to work on.
	 *
	 * @var \WpSecurityAuditLog
	 */
	private $wsal;

	/**
	 * Setups up the class properties.
	 *
	 * @method __construct
	 * @since  4.02
	 * @param  \WpSecurityAuditLog $wsal An instance of the main plugin.
	 */
	public function __construct( $wsal ) {
		$this->wsal = $wsal;
	}
	/**
	 * Loop through an array of cron tasks and remap them if the name changed.
	 *
	 * @method run
	 * @since  4.0.2
	 */
	public function run() {
		$restrict_setting_edit_type = $this->wsal->GetGlobalOption( 'restrict-plugin-settings' );
		// If it's anything other than 'only_me' then we just delete the keys
		// and revert to default settings.
		if ( 'only_me' !== $restrict_setting_edit_type ) {
			$this->wsal->DeleteByName( 'wsal-restrict-plugin-settings' );
			$this->wsal->DeleteByName( 'wsal-plugin-editors' );
			$this->wsal->DeleteByName( 'wsal-restrict-admins' );
		}
	}

}
