<?php
/**
 * Metadata migration class.
 *
 * @package    wsal
 * @subpackage upgrade
 * @since      4.4.0
 */

namespace WSAL\Migration;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WSAL\Helpers\WP_Helper;
use WSAL\Controllers\Connection;
use WSAL\Entities\Metadata_Entity;
use WSAL\Utils\Abstract_Migration;
use WSAL\Entities\Occurrences_Entity;
use WSAL_Vendor\WP_Background_Process;

/**
 * Migration class
 */
if ( ! class_exists( '\WSAL\Migration\Metadata_Migration_440' ) ) {

	/**
	 * Background process for handling the migration of selected metadata from the meta table to the occurrences table. This
	 * was part of database schema changes introduced in version 4.4.0.
	 *
	 * It handles metadata migration for 1 connection defined as part of the process information. This can be either "local"
	 * to work with the local WP database or a name of connection defined by the Integrations extension.
	 *
	 * @package    wsal
	 * @subpackage upgrade
	 * @since      4.4.0
	 */
	class Metadata_Migration_440 extends WP_Background_Process {

		/**
		 * Name of the option holding the information about ongoing metadata migration.
		 *
		 * Note: the wsal_ prefix is automatically added by plugin's settings handling functions.
		 *
		 * @var string
		 */
		const OPTION_NAME_MIGRATION_INFO = 'meta_data_migration_info_440';

		/**
		 * Action
		 *
		 * @var string
		 */
		protected $action = 'wsal_meta_data_migration_440_';

		/**
		 * Default constructor - override the action variable accordingly.
		 *
		 * @param string $action - The name of the action.
		 *
		 * @since 4.4.2.1
		 */
		public function __construct( string $action ) {
			$this->action .= $action;

			parent::__construct();
		}

		/**
		 * Displays an admin notice if a metadata migration is in progress.
		 */
		public static function maybe_display_progress_admin_notice() {
			if ( ! is_user_logged_in() ) {
				// Don't show to anonymous users (obviously).
				return;
			}

			$existing_info = WP_Helper::get_global_option( self::OPTION_NAME_MIGRATION_INFO, array() );
			if ( empty( $existing_info ) ) {
				return;
			}

			$current_user = get_userdata( get_current_user_id() );
			if ( false === $current_user ) {
				// Bail if there is a problem retrieving the current user.
				return;
			}

			$is_admin = in_array( 'administrator', $current_user->roles, true ) || ( function_exists( 'is_super_admin' ) && is_super_admin( $current_user->ID ) );
			if ( ! $is_admin ) {
				// Don't show to admin users.
				return;
			}
			?>
		<div class="notice notice-info">
			<div class="notice-content-wrapper">
				<p>
					<strong><?php esc_html_e( 'Activity log database update in progress.', 'wp-security-audit-log' ); ?></strong>
					<br />
					<?php
					echo \__( '<strong>UPGRADE notice: </strong> WP Activity Log is updating the database tables where the activity log is stored. The duration of this process varies depending on the size of the activity log. The upgrade is running in the background and won\'t affect your website. For more information please refer to this <a href="https://melapress.com/support/kb/upgrade-database-process-442/" target="_blank">knowledge base entry</a>.', 'wp-security-audit-log' );
					?>
				</p>
			</div>
		</div>
			<?php
		}

		/**
		 * {@inheritDoc}
		 *
		 * @param array{start_time: int, processed_events_count: int, batch_size: int, connection: string} $item Migration process item.
		 */
		public function task( $item ) {
			// Migrate metadata for the next batch of events.
			$items_migrated = self::process_next_batch( $item['connection'], $item['batch_size'] );
			if ( 0 === $items_migrated ) {
				// All metadata has been migrated.
				try {
					// Delete the migration job info to indicate that the migration is done.
					self::remove_migration_info( $item['connection'] );
					WP_Helper::delete_global_option( Abstract_Migration::STARTED_MIGRATION_PROCESS );

				} catch ( \Exception $exception ) {
					$this->handle_error( $exception );
				}

				return false;
			}

			// Update and save the migration info.
			$item['processed_events_count'] += $items_migrated;
			self::store_migration_info( $item );

			return $item;
		}

		/**
		 * Processes next batch of events that need to be migrated.
		 *
		 * @param string $connection Connection name.
		 * @param int    $batch_size Batch size.
		 *
		 * @return int
		 */
		public static function process_next_batch( $connection, $batch_size ) {
			$plugin = \WpSecurityAuditLog::get_instance();
			if ( 'local' !== $connection ) {
				$connection = Connection::get_connection( $connection );
				if ( false === $connection ) {
					return 0;
				}
			}

			// $connector = $plugin->get_connector( $connection, false );
			/** WSAL_Adapters_MySQL_Occurrence $occurrence_adapter */
			// $occurrence_adapter = $connector->get_adapter( 'Occurrence' );

			$occurrences_to_migrate = Occurrences_Entity::get_all_with_meta_to_migrate( $batch_size );
			if ( ! empty( $occurrences_to_migrate ) ) {
				$migrated_meta_keys           = array_keys( Occurrences_Entity::$migrated_meta );
				$lowercase_migrated_meta_keys = array_map( 'strtolower', $migrated_meta_keys );
				foreach ( $occurrences_to_migrate as &$occurrence ) {
					$all_metadata = Metadata_Entity::load_array( 'occurrence_id = %d', array( $occurrence['id'] ) );
					if ( ! empty( $all_metadata ) ) {
						foreach ( $all_metadata as $meta_model ) {
							$meta_key           = $meta_model['name'];
							$lowercase_meta_key = strtolower( $meta_key );

							// We use lowercase meta keys to make sure we handle even legacy meta keys correctly, for
							// example "username" was changed to "Username" at some point.
							if ( in_array( $lowercase_meta_key, $lowercase_migrated_meta_keys ) ) { // phpcs:ignore
								// This will store the meta in the occ table if it belongs there.
								$is_empty_string = is_string( $meta_model['value'] ) && 0 === strlen( $meta_model['value'] );
								if ( ! $is_empty_string && in_array( $meta_key, $migrated_meta_keys, true ) ) {
									// The meta is set in the occurrence object on if it is an exact match, otherwise we
									// would end up writing and deleting the same meta key endlessly.
									$occurrence[ Occurrences_Entity::$migrated_meta[ $meta_key ] ] = $meta_model['value'];
									// $occurrence->set_meta_value( $meta_key, $meta_model['value'] );
								}

								Metadata_Entity::delete( $meta_model );
							}
						}

						Occurrences_Entity::save( $occurrence );
					}
				}
				unset( $occurrence );
			}

			return count( $occurrences_to_migrate );
		}

		/**
		 * Removes migration info for a particular connection.
		 *
		 * @param string $connection_name Connection name.
		 */
		public static function remove_migration_info( $connection_name ) {
			$existing_info = WP_Helper::get_global_option( self::OPTION_NAME_MIGRATION_INFO, array() );

			if ( array_key_exists( $connection_name, $existing_info ) ) {
				unset( $existing_info[ $connection_name ] );
			}

			if ( empty( $existing_info ) ) {
				WP_Helper::delete_global_option( self::OPTION_NAME_MIGRATION_INFO );
				WP_Helper::delete_global_option( Abstract_Migration::STARTED_MIGRATION_PROCESS );
			} else {
				WP_Helper::set_global_option( self::OPTION_NAME_MIGRATION_INFO, $existing_info );
			}
		}

		/**
		 * Handles an error.
		 *
		 * @param Exception $exception Error to handle.
		 */
		private static function handle_error( $exception ) {
			// @todo handle migration error
		}

		/**
		 * Stores or updates migration info for one particular connection.
		 *
		 * @param array{start_time: int, processed_events_count: int, batch_size: int, connection: string} $info Migration info data.
		 */
		public static function store_migration_info( $info ) {
			$existing_info   = WP_Helper::get_global_option( self::OPTION_NAME_MIGRATION_INFO, array() );
			$connection_name = $info['connection'];

			$existing_info[ $connection_name ] = $info;
			WP_Helper::set_global_option( self::OPTION_NAME_MIGRATION_INFO, $existing_info );
			WP_Helper::set_global_option( Abstract_Migration::STARTED_MIGRATION_PROCESS, true );
		}
	}
}
