<?php
/**
 * Abstract migration class.
 *
 * @package    wsal
 * @subpackage utils
 * @copyright  2024 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link       https://wordpress.org/plugins/wp-2fa/
 */

namespace WSAL\Utils;

use WSAL\Helpers\WP_Helper;
use WSAL\Helpers\Settings_Helper;
use WSAL\Helpers\Plugin_Settings_Helper;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Abstract Migration class
 */
if ( ! class_exists( '\WSAL\Utils\Abstract_Migration' ) ) {

	/**
	 * Utility class to ease the migration process.
	 *
	 * Every migration must go in its own method
	 * The naming convention is migrateUpTo_XXX where XXX is the number of the version,
	 * format is numbers only.
	 * Example: migration for version upto 1.4 must be in migrateUpTo_14 method
	 *
	 * The numbers in the names of the methods must have exact numbers count as in the selected
	 * version in use, even if there are silent numbers for some of the major versions as 1, 2, 3 etc. (the .0.0 is skipped / silent)
	 * Example:
	 *  - if X.X.X is selected for version number, then for version 1.1 method must have "...migrateUpTo_110..." in its name
	 *  - if X.X is selected for version number, then for version 1, method must have "...migrateUpTo_10..." in its name
	 *
	 * Note: you can add prefix to the migration method, if that is necessary, but "migrateUpTo_" is a must -
	 * the name must contain that @see getAllMigrationMethodsAsNumbers of that class.
	 * For version extraction the number following the last '_' will be used
	 * TODO: the mandatory part of the method name can be a setting in the class, but is that a good idea?
	 *
	 * Note: order of the methods is not preserved - version numbers will be used for ordering
	 *
	 * @package WP2FA\Utils
	 *
	 * @since 4.4.0
	 */
	class Abstract_Migration {

		/**
		 * That is a global constant used for marking the migration process as in progress.
		 */
		public const STARTED_MIGRATION_PROCESS = 'migration-process-started';

		/**
		 * That is a global constant used for showing the upgrade notice.
		 */
		public const UPGRADE_NOTICE = 'upgrade-notice-show';

		/**
		 * Extracted version from the DB (WP option)
		 *
		 * @var string
		 *
		 * @since 4.4.0
		 */
		protected static $stored_version = '';

		/**
		 * The name of the option from which we should extract version
		 * Note: version is expected in version format - 1.0.0; 1; 1.0; 1.0.0.0
		 * Note: only numbers will be processed
		 *
		 * @var string
		 *
		 * @since 4.4.0
		 */
		protected static $version_option_name = '';

		/**
		 * The constant name where the plugin version is stored
		 * Note: version is expected in version format - 1.0.0; 1; 1.0; 1.0.0.0
		 * Note: only numbers will be processed
		 *
		 * @var string
		 *
		 * @since 4.4.2.1
		 */
		protected static $const_name_of_plugin_version = '';

		/**
		 * Used for adding proper pads for the missing numbers
		 * Version number format used here depends on selection for how many numbers will be used for representing version
		 *
		 * For X.X     use 2;
		 * For X.X.X   use 3;
		 * For X.X.X.X use 4;
		 *
		 * etc.
		 *
		 * Example: if selected version format is X.X.X that means that 3 digits are used for versioning.
		 * And current version is stored as 2 (no suffix 0.0) that means that it will be normalized as 200.
		 *
		 * @var integer
		 *
		 * @since 4.4.0
		 */
		protected static $pad_length = 4;

		/**
		 * Collects all the migration methods which needs to be executed in order and executes them
		 *
		 * @return void
		 *
		 * @since 4.4.0
		 */
		public static function migrate() {

			// Check if that process is not started already.
			$migration_started = WP_Helper::get_global_option( self::STARTED_MIGRATION_PROCESS, false );

			if ( version_compare( static::get_stored_version(), \constant( static::$const_name_of_plugin_version ), '<' ) ) {

				$stored_version_as_number = static::normalize_version( static::get_stored_version() );
				$target_version_as_number = static::normalize_version( \constant( static::$const_name_of_plugin_version ) );

				if ( '0000' === $stored_version_as_number && ! WP_Helper::is_plugin_installed() ) {
					// That is first install of the plugin, store the version and leave.
					self::store_updated_version();

					$disabled_alerts = WP_Helper::get_global_option( 'disabled-alerts', false );

					if ( ! \is_array( $disabled_alerts ) ) {
						$disabled_alerts = \explode( ',', $disabled_alerts );

						\array_walk( $disabled_alerts, 'trim' );
					}

					$always_disabled_alerts = Settings_Helper::get_default_always_disabled_alerts();

					if ( ! \is_array( $always_disabled_alerts ) ) {
						$always_disabled_alerts = \explode( ',', $always_disabled_alerts );

						\array_walk( $always_disabled_alerts, 'trim' );
					}

					$disabled_alerts = \array_merge( $disabled_alerts, $always_disabled_alerts );

					Settings_Helper::set_boolean_option_value( 'pruning-limit-e', true );
					$pruning_date = '3';
					$pruning_unit = 'months';
					Settings_Helper::set_pruning_date_settings( true, $pruning_date . ' ' . $pruning_unit, $pruning_unit );
					Plugin_Settings_Helper::set_pruning_limit_enabled( false );

					/**
					 * That is split only for clarity
					 */
					if ( false === $disabled_alerts ) {
						WP_Helper::set_global_option( 'disabled-alerts', $always_disabled_alerts );
					} elseif ( $disabled_alerts !== $always_disabled_alerts ) {
						WP_Helper::update_global_option( 'disabled-alerts', $disabled_alerts );
					}
				} elseif ( false === $migration_started ) {

					WP_Helper::set_global_option( self::STARTED_MIGRATION_PROCESS, true );
					try {
						// set transient for the updating status - would that help ?!?
						$method_as_version_numbers = static::get_all_migration_methods_as_numbers();

						$migrate_methods = array_filter(
							$method_as_version_numbers,
							function ( $method, $key ) use ( &$stored_version_as_number, &$target_version_as_number ) {

								if ( ( ( (int) $target_version_as_number ) / 1000 ) > ( ( (int) $stored_version_as_number ) / 1000 ) ) {
									return ( in_array( $key, range( $stored_version_as_number + 1, $target_version_as_number ), true ) );
								}

								return false;
							},
							ARRAY_FILTER_USE_BOTH
						);

						if ( ! empty( $migrate_methods ) ) {
							\ksort( $migrate_methods );
							foreach ( $migrate_methods as $method ) {
								static::{$method}();
							}
						}

						self::store_updated_version();
					} finally {
						\WSAL\Helpers\WP_Helper::delete_global_option( self::STARTED_MIGRATION_PROCESS );
					}
				}
			}

			/**
			 * Downgrading the plugin? Set the version number.
			 * Leave the rest as is.
			 *
			 * @return void
			 *
			 * @since 4.4.2.1
			 */
			if ( false === $migration_started && version_compare( static::get_stored_version(), \constant( static::$const_name_of_plugin_version ), '>' ) ) {
				self::store_updated_version();
			}
		}

		/**
		 * Removes redundant notices
		 *
		 * @param string $notice - Notice to be removed.
		 *
		 * @return void
		 *
		 * @since 4.4.2.1
		 */
		public static function remove_notice( string $notice ) {

			global $wpdb;
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->usermeta} WHERE meta_key = '%s';", // phpcs:ignore
					$notice
				)
			);
		}

		/**
		 * Extracts currently stored version from the DB
		 *
		 * @return string
		 *
		 * @since 4.4.0
		 */
		private static function get_stored_version() {

			if ( '' === trim( static::$stored_version ) ) {
				static::$stored_version = WP_Helper::get_global_option( static::$version_option_name, '0.0.0' );
			}

			return static::$stored_version;
		}

		/**
		 * Stores the version to which we migrated
		 *
		 * @return void
		 *
		 * @since 4.4.0
		 */
		private static function store_updated_version() {
			if ( version_compare( static::get_stored_version(), \constant( static::$const_name_of_plugin_version ), '<' ) ) {
				WP_Helper::update_global_option( static::$version_option_name, \constant( static::$const_name_of_plugin_version ) );

				if ( '0.0.0' !== (string) static::$stored_version ) {
					WP_Helper::set_global_option( self::UPGRADE_NOTICE, true );
				}
			}
		}

		/**
		 * Normalized the version numbers to numbers
		 *
		 * Version format is expected to be as follows:
		 * X.X.X
		 *
		 * All non numeric values will be removed from the version string
		 *
		 * Note: version is expected in version format - 1.0.0; 1; 1.0; 1.0.0.0
		 * Note: only numbers will be processed
		 *
		 * @param string $version - The version string we have to use.
		 *
		 * @return int
		 *
		 * @since 4.4.0
		 */
		private static function normalize_version( string $version ): string {
			$version_as_number = (string) filter_var( $version, FILTER_SANITIZE_NUMBER_INT );

			if ( self::$pad_length > strlen( $version_as_number ) ) {
				$version_as_number = str_pad( $version_as_number, static::$pad_length, '0', STR_PAD_RIGHT );
			}

			return $version_as_number;
		}

		/**
		 * Collects all the migration methods from the class and stores them in the array
		 * Array is in following format:
		 * key - number of the version
		 * value - name of the method
		 *
		 * @return array
		 *
		 * @since 4.4.0
		 */
		private static function get_all_migration_methods_as_numbers() {
			$class_methods = \get_class_methods( get_called_class() );

			$method_as_version_numbers = array();
			foreach ( $class_methods as $method ) {
				if ( false !== \strpos( $method, 'migrate_up_to_' ) ) {
					$ver                               = \substr( $method, \strrpos( $method, '_' ) + 1, \strlen( $method ) );
					$method_as_version_numbers[ $ver ] = $method;
				}
			}

			return $method_as_version_numbers;
		}
	}
}
