<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Background process for handling the migration of selected metadata from the meta table to the occurrences table. This
 * was part of database schema changes introduced in version 4.4.0.
 *
 * It handles metadata migration for 1 connection defined as part of the process information. This can be either "local"
 * to work with the local WP database or a name of connection defined by the Integrations extension.
 *
 * @package wsal
 * @subpackage upgrade
 * @since 4.4.0
 */
class WSAL_Upgrade_MetadataMigration extends WSAL_Vendor\WP_Background_Process {

	/**
	 * Name of the option holding the information about ongoing metadata migration.
	 *
	 * Note: the wsal_ prefix is automatically added by plugin's settings handling functions.
	 *
	 * @var string
	 */
	const OPTION_NAME_MIGRATION_INFO = 'meta_data_migration_info_440';

	/**
	 * @inheritDoc
	 */
	protected $action = 'wsal_meta_data_migration_440';

	/**
	 * Displays an admin notice if a metadata migration is in progress.
	 */
	public static function maybe_display_progress_admin_notice() {
		if ( ! is_user_logged_in() ) {
			// Don't show to anonymous users (obviously).
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

		$plugin        = WpSecurityAuditLog::GetInstance();
		$existing_info = $plugin->GetGlobalSetting( self::OPTION_NAME_MIGRATION_INFO, array() );
		if ( empty( $existing_info ) ) {
			return;
		}
		?>
		<div class="notice notice-info">
			<div class="notice-content-wrapper">
				<p>
					<strong><?php esc_html_e( 'Activity log database update in progress.', 'wp-security-audit-log' ); ?></strong>
					<br />
					<?php esc_html_e( 'WP Activity Log is updating the database tables where it stores the activity log. This is needed to upgrade the activity log tables to the new database schema, so the logs can be stored and read more efficiently. The duration of this process varies, depending on the number of events in the activity log. This process runs in the background and won\'t affect your website. During the upgrade, you might notice some "null" values in the activity log. This is temporary until the process is complete.', 'wp-security-audit-log' ); ?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * @inheritDoc
	 *
	 * @param array{start_time: int, processed_events_count: int, batch_size: int, connection: string} $item
	 */
	protected function task( $item ) {
		//  migrate metadata for the next batch of events
		$items_migrated = $this->process_next_batch( $item['connection'], $item['batch_size'] );
		if ( 0 === $items_migrated ) {
			//  all metadata has been migrated
			try {
				//  delete the migration job info to indicate that the migration is done
				self::remove_migration_info( $item['connection'] );

			} catch ( Exception $exception ) {
				$this->handle_error( $exception );
			}

			return false;
		}

		//  update and save the migration info
		$item['processed_events_count'] += $items_migrated;
		self::store_migration_info( $item );

		return $item;
	}

	/**
	 * @param string $connection
	 * @param int $batch_size
	 *
	 * @return int
	 */
	private function process_next_batch( $connection, $batch_size ) {
		$plugin = WpSecurityAuditLog::GetInstance();
		if ( 'local' !== $connection && ! is_null( $plugin->external_db_util ) ) {
			$connection = $plugin->external_db_util->get_connection( $connection );
			if ( false === $connection ) {
				return 0;
			}
		}

		$connector = $plugin->getConnector( $connection );
		/** @var WSAL_Adapters_MySQL_Occurrence $occurrence_adapter */
		$occurrence_adapter = $connector->getAdapter( 'Occurrence' );

		$occurrences_to_migrate = $occurrence_adapter->get_all_with_meta_to_migrate( $batch_size );
		if ( ! empty( $occurrences_to_migrate ) ) {
			$migrated_meta_keys =  array_keys( WSAL_Models_Occurrence::$migrated_meta );
			$lowercase_migrated_meta_keys = array_map( 'strtolower', $migrated_meta_keys );
			foreach ( $occurrences_to_migrate as $occurrence ) {
				$all_metadata = $occurrence_adapter->GetMultiMeta( $occurrence );
				if ( ! empty( $all_metadata ) ) {
					foreach ( $all_metadata as $meta_model ) {
						$meta_key = $meta_model->name;
						$lowercase_meta_key = strtolower($meta_key);

						// We use lowercase meta keys to make sure we handle even legacy meta keys correctly, for
						// example "username" was changed to "Username" at some point.
						if ( in_array( $lowercase_meta_key , $lowercase_migrated_meta_keys ) ) {
							//  this will store the meta in the occ table if it belongs there
							$is_empty_string = is_string( $meta_model->value ) && 0 === strlen( $meta_model->value );
							if ( ! $is_empty_string && in_array( $meta_key, $migrated_meta_keys )) {
								// The meta is set in the occurrence object on if it is an exact match, otherwise we
								// would end up writing and deleting the same meta key endlessly.
								$occurrence->SetMetaValue( $meta_key, $meta_model->value );
							}

							$meta_model->Delete();
						}
					}

					$occurrence->Save();
				}
			}
		}

		return count( $occurrences_to_migrate );
	}

	/**
	 * Removes migration info for a particular connection.
	 *
	 * @param string $connection_name Connection name.
	 */
	public static function remove_migration_info( $connection_name ) {
		$plugin        = WpSecurityAuditLog::GetInstance();
		$existing_info = $plugin->GetGlobalSetting( self::OPTION_NAME_MIGRATION_INFO, [] );

		if ( array_key_exists( $connection_name, $existing_info ) ) {
			unset( $existing_info[ $connection_name ] );
		}

		if ( empty( $existing_info ) ) {
			$plugin->DeleteGlobalSetting( self::OPTION_NAME_MIGRATION_INFO );
		} else {
			$plugin->SetGlobalSetting( self::OPTION_NAME_MIGRATION_INFO, $existing_info );
		}
	}

	/**
	 * @param Exception $exception
	 */
	private function handle_error( $exception ) {
		//  @todo handle migration error
	}

	/**
	 * Stores or updates migration info for one particular connection.
	 *
	 * @param array{start_time: int, processed_events_count: int, batch_size: int, connection: string} $info
	 */
	public static function store_migration_info( $info ) {
		$plugin          = WpSecurityAuditLog::GetInstance();
		$existing_info   = $plugin->GetGlobalSetting( self::OPTION_NAME_MIGRATION_INFO, [] );
		$connection_name = $info['connection'];

		$existing_info[ $connection_name ] = $info;
		$plugin->SetGlobalSetting( self::OPTION_NAME_MIGRATION_INFO, $existing_info );
	}
}