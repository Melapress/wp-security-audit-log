<?php
/**
 * Archiving records class.
 *
 * @package wsal
 * @author Stoil Dobrev <sdobreff@gmail.com>
 */

declare(strict_types=1);

namespace WSAL\Entities\Archive;

use WSAL\Helpers\User_Helper;
use WSAL\Helpers\Settings_Helper;
use WSAL\Entities\Abstract_Entity;
use WSAL\Entities\Metadata_Entity;
use WSAL\Entities\Occurrences_Entity;
use WSAL\Helpers\External_DB_Transfer_Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Entities\Archive\Archive_Records' ) ) {

	/**
	 * Responsible for the common entity operations.
	 *
	 * @since 4.6.0
	 */
	class Archive_Records {

		/**
		 * Option name of the lock that allows one activity log transfer to or from an external database at a time.
		 *
		 * @var string
		 *
		 * @since 5.7.0
		 */
		private const EXTERNAL_DB_TRANSFER_LOCK_OPTION = 'wsal_external_db_transfer_lock';

		/**
		 * Seconds after which an external database transfer lock left by an interrupted transfer can be taken over.
		 *
		 * @var int
		 *
		 * @since 5.7.0
		 */
		private const EXTERNAL_DB_TRANSFER_LOCK_EXPIRATION = 5 * MINUTE_IN_SECONDS;

		/**
		 * Option name of the new destination IDs given to occurrences whose ID a different event already uses in the destination.
		 *
		 * @var string
		 *
		 * @since 5.7.0
		 */
		private const EXTERNAL_DB_TRANSFER_REMAPPED_IDS_OPTION = 'external-db-transfer-remapped-ids';

		/**
		 * Executes a transfer query, creating a missing table before one retry.
		 *
		 * @param \wpdb  $connection - Database connection.
		 * @param string $sql - Prepared query.
		 * @param string $entity - Entity whose missing table may be created.
		 *
		 * @return int|bool|\WP_Error - Query result or transfer error.
		 *
		 * @since 5.7.0
		 */
		private static function query( $connection, string $sql, string $entity = '' ) {
			$result = $connection->query( $sql );

			if ( false === $result && '' !== $entity && 1146 === Abstract_Entity::get_last_sql_error( $connection ) && $entity::create_table( $connection ) ) {
				$result = $connection->query( $sql );
			}

			if ( false === $result || '' !== $connection->last_error ) {
				return new \WP_Error( 'wsal_archive_database_error', \__( 'Could not transfer the activity log. Check the database connection and permissions, then try again.', 'wp-security-audit-log' ) );
			}

			return $result;
		}

		/**
		 * Returns the main site's options table, which stores the external database transfer lock for the whole network.
		 *
		 * @return string - Table name.
		 *
		 * @since 5.7.0
		 */
		private static function get_external_db_transfer_lock_table(): string {
			global $wpdb;

			/**
			 * ! We need to make an exception here for multisite, and NOT use wp_sitemeta as we normally do.
			 * ! Using wp_sitemeta would be possible, but we would add additional complexity for zero value in return.
			 *
			 * The lock must be a row that only one transfer can create. The options table has a unique key on
			 * the option name, so when two transfers insert the lock row at the same time, the database accepts
			 * only one of them. The network options table (sitemeta) has no unique key on the option name, so
			 * both inserts would succeed there and both transfers would think only they can write.
			 *
			 * On multisite, the base prefix always points to the main site's options table, whichever site is
			 * active, so every transfer on the network uses the same lock row. The row is not a setting and is
			 * only read by the lock methods. WordPress core stores its update locks in the options table too.
			 */
			return $wpdb->base_prefix . 'options';
		}

		/**
		 * Takes the external database transfer lock, or an expired lock left by an interrupted transfer.
		 *
		 * @return string|\WP_Error - Lock value with this transfer's random ID and expiry, or an error.
		 *
		 * @since 5.7.0
		 */
		private static function acquire_external_db_transfer_lock() {
			global $wpdb;

			$table         = self::get_external_db_transfer_lock_table();
			$transfer_lock = \wp_generate_uuid4() . '|' . ( time() + self::EXTERNAL_DB_TRANSFER_LOCK_EXPIRATION );

			// The unique option name lets only one transfer insert the lock row; the others insert nothing.
			$result = $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO `' . $table . "` (option_name, option_value, autoload) VALUES (%s, %s, 'off')", self::EXTERNAL_DB_TRANSFER_LOCK_OPTION, $transfer_lock ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The lock needs single-step queries the options API cannot make and must never read cached values; the table name is the database prefix plus "options", because the %i table name placeholder needs WordPress 6.2 and WSAL supports 5.5.

			if ( false === $result ) {
				return new \WP_Error( 'wsal_archive_database_error', \__( 'Could not transfer the activity log. Check the database connection and permissions, then try again.', 'wp-security-audit-log' ) );
			}

			if ( 1 === $result ) {
				return $transfer_lock;
			}

			// If we get here, $result is 0, which means a lock already exists.
			$current = $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM `' . $table . '` WHERE option_name = %s', self::EXTERNAL_DB_TRANSFER_LOCK_OPTION ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- As above.
			$parts   = explode( '|', (string) $current );
			$expires = (int) ( $parts[1] ?? 0 );

			// If a lock row was found and its expiry time is in the past.
			if ( null !== $current && $expires < time() ) {
				// Only one transfer can replace the expired value, so only one takes over.
				$result = $wpdb->query( $wpdb->prepare( 'UPDATE `' . $table . '` SET option_value = %s WHERE option_name = %s AND option_value = %s', $transfer_lock, self::EXTERNAL_DB_TRANSFER_LOCK_OPTION, $current ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- As above.

				if ( 1 === $result ) {
					return $transfer_lock;
				}
			}

			return new \WP_Error( 'wsal_external_db_transfer_running', \__( 'An activity log transfer is already running. Please try again later.', 'wp-security-audit-log' ) );
		}

		/**
		 * Confirms that this transfer is still the only one that can write, and gives it five more minutes.
		 *
		 * @param string $transfer_lock - Current lock value, replaced with the extended one.
		 *
		 * @return true|\WP_Error - True while this transfer is still the only one that can write, or an error.
		 *
		 * @since 5.7.0
		 */
		private static function extend_external_db_transfer_lock( string &$transfer_lock ) {
			global $wpdb;

			$table = self::get_external_db_transfer_lock_table();
			$parts = explode( '|', $transfer_lock );
			$value = $parts[0] . '|' . ( time() + self::EXTERNAL_DB_TRANSFER_LOCK_EXPIRATION );

			$result = $wpdb->query( $wpdb->prepare( 'UPDATE `' . $table . '` SET option_value = %s WHERE option_name = %s AND option_value = %s', $value, self::EXTERNAL_DB_TRANSFER_LOCK_OPTION, $transfer_lock ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The lock needs single-step queries the options API cannot make and must never read cached values; the table name is the database prefix plus "options", because the %i table name placeholder needs WordPress 6.2 and WSAL supports 5.5.

			// An unchanged expiry updates no rows, so the stored value is read to confirm this transfer can still write.
			if ( 1 !== $result ) {
				$stored = $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM `' . $table . '` WHERE option_name = %s', self::EXTERNAL_DB_TRANSFER_LOCK_OPTION ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- As above.

				if ( $value !== $stored ) {
					return new \WP_Error( 'wsal_external_db_transfer_lock_lost', \__( 'Another activity log transfer took over. The source records have been retained, please try again.', 'wp-security-audit-log' ) );
				}
			}

			/**
			 * Side effect: $transfer_lock is passed by reference, so this also updates the lock value held by
			 * every caller up to archive(). They need the new expiry for the next check and to release the lock.
			 */
			$transfer_lock = $value;

			return true;
		}

		/**
		 * Releases the external database transfer lock, unless another transfer can write by now.
		 *
		 * @param string $transfer_lock - Current lock value.
		 *
		 * @return void
		 *
		 * @since 5.7.0
		 */
		private static function release_external_db_transfer_lock( string $transfer_lock ): void {
			global $wpdb;

			$wpdb->query( $wpdb->prepare( 'DELETE FROM `' . self::get_external_db_transfer_lock_table() . '` WHERE option_name = %s AND option_value = %s', self::EXTERNAL_DB_TRANSFER_LOCK_OPTION, $transfer_lock ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The lock needs single-step queries the options API cannot make and must never read cached values; the table name is the database prefix plus "options", because the %i table name placeholder needs WordPress 6.2 and WSAL supports 5.5.
		}

		/**
		 * Executes a write query only while no other transfer can write.
		 *
		 * @param \wpdb  $connection - Database connection.
		 * @param string $sql - Prepared query.
		 * @param string $transfer_lock - Current lock value, replaced with the extended one.
		 * @param string $entity - Entity whose missing table may be created.
		 *
		 * @return int|bool|\WP_Error - Query result or transfer error.
		 *
		 * @since 5.7.0
		 */
		private static function write_with_external_db_transfer_lock( $connection, string $sql, string &$transfer_lock, string $entity = '' ) {
			$kept = self::extend_external_db_transfer_lock( $transfer_lock );

			if ( \is_wp_error( $kept ) ) {
				return $kept;
			}

			return self::query( $connection, $sql, $entity );
		}

		/**
		 * Copies rows only while no other transfer can write.
		 *
		 * ! Used both to copy rows to external DB, and back FROM external DB to the local one.
		 *
		 * @param \wpdb  $connection - Destination connection.
		 * @param string $entity - Destination entity.
		 * @param array  $rows - Source records.
		 * @param array  $formats - Column names and value formats.
		 * @param string $transfer_lock - Current lock value, replaced with the extended one.
		 *
		 * @return int|bool|\WP_Error - Query result or transfer error.
		 *
		 * @since 5.7.0
		 */
		private static function copy_rows( $connection, string $entity, array $rows, array $formats, string &$transfer_lock ) {
			$table = $entity::get_table_name( $connection );
			$sql   = External_DB_Transfer_Helper::get_insert_rows_sql_query_text( $connection, $table, $rows, $formats );

			return self::write_with_external_db_transfer_lock( $connection, $sql, $transfer_lock, $entity );
		}

		/**
		 * Returns the destination IDs saved for remapped occurrences of one destination, keyed by source ID.
		 *
		 * @param string $destination_key - Server, database and occurrences table of the destination.
		 *
		 * @return array - Destination IDs keyed by source occurrence ID.
		 *
		 * @since 5.7.0
		 */
		private static function get_saved_remapped_ids( string $destination_key ): array {
			$saved_remapped_ids = (array) Settings_Helper::get_option_value( self::EXTERNAL_DB_TRANSFER_REMAPPED_IDS_OPTION, array() );

			return (array) ( $saved_remapped_ids[ $destination_key ] ?? array() );
		}

		/**
		 * Saves new remapped occurrence IDs of one destination and forgets the ones that are no longer needed.
		 *
		 * @param string $destination_key - Server, database and occurrences table of the destination.
		 * @param array  $remapped_ids - Destination IDs to save, keyed by source occurrence ID.
		 * @param array  $source_ids_to_forget - Source occurrence IDs whose saved destination IDs are no longer needed.
		 *
		 * @return void
		 *
		 * @since 5.7.0
		 */
		private static function update_saved_remapped_ids( string $destination_key, array $remapped_ids, array $source_ids_to_forget ): void {
			$saved_remapped_ids = (array) Settings_Helper::get_option_value( self::EXTERNAL_DB_TRANSFER_REMAPPED_IDS_OPTION, array() );
			$destination_ids    = array_replace( (array) ( $saved_remapped_ids[ $destination_key ] ?? array() ), $remapped_ids );
			$destination_ids    = array_diff_key( $destination_ids, array_flip( $source_ids_to_forget ) );

			if ( $destination_ids ) {
				$saved_remapped_ids[ $destination_key ] = $destination_ids;
			} else {
				unset( $saved_remapped_ids[ $destination_key ] );
			}

			if ( $saved_remapped_ids ) {
				Settings_Helper::set_option_value( self::EXTERNAL_DB_TRANSFER_REMAPPED_IDS_OPTION, $saved_remapped_ids );
			} else {
				Settings_Helper::delete_option_value( self::EXTERNAL_DB_TRANSFER_REMAPPED_IDS_OPTION );
			}
		}

		/**
		 * Transfers one batch while no other transfer can write.
		 *
		 * @param array  $args - Archive connection and selection limits.
		 * @param \wpdb  $source - Source connection.
		 * @param \wpdb  $destination - Destination connection.
		 * @param string $destination_key - Server, database and occurrences table of the destination.
		 * @param string $transfer_lock - Current lock value, replaced with the extended one.
		 * @param int    $count - Number of confirmed source occurrence deletions.
		 *
		 * @return bool|\WP_Error - True for a transfer, false for no records, or an error.
		 *
		 * @since 5.7.0
		 */
		private static function transfer_batch( array $args, $source, $destination, string $destination_key, string &$transfer_lock, int &$count ) {
			/**
			 * Step 1 of 5: read the batch from the source.
			 *
			 * Selects the oldest occurrences up to the batch size (older than the cut-off date when archiving) and their metadata.
			 * Returns false when there is nothing left to transfer.
			 */
			$table = Occurrences_Entity::get_table_name( $source );
			$sql   = External_DB_Transfer_Helper::get_select_occurrences_batch_sql_query_text( $source, $table, $args );

			$result = self::query( $source, $sql, Occurrences_Entity::class );

			if ( \is_wp_error( $result ) ) {
				return $result;
			}

			$occurrences = array_map( 'get_object_vars', $source->last_result );

			if ( empty( $occurrences ) ) {
				return false;
			}

			$occurrence_ids_sql_in_list = External_DB_Transfer_Helper::get_ids_as_sql_in_list_text( array_column( $occurrences, 'id' ) );
			$sql                        = 'SELECT * FROM ' . Metadata_Entity::get_table_name( $source ) . ' WHERE occurrence_id IN (' . $occurrence_ids_sql_in_list . ') ORDER BY id';

			$result = self::query( $source, $sql, Metadata_Entity::class );

			if ( \is_wp_error( $result ) ) {
				return $result;
			}

			$metadata = array_map( 'get_object_vars', $source->last_result );

			/**
			 * Step 2 of 5: fill in missing user data.
			 *
			 * Login, logout and failed login events get any missing username or user ID filled in before they are copied.
			 */
			$occurrences = array_map( array( User_Helper::class, 'fill_missing_user_of_login_event' ), $occurrences );

			/**
			 * Step 3 of 5: decide the destination ID of each occurrence.
			 *
			 * An occurrence keeps its ID unless a different event already uses it in the destination. Then it gets a new ID,
			 * saved before copying, so a retry after an interruption finds the copy instead of copying the occurrence again.
			 */
			// Occurrence IDs of this batch in the source table.
			$source_ids = array_map( 'intval', array_column( $occurrences, 'id' ) );

			// New destination occurrence IDs saved by an interrupted earlier attempt, kept only for this batch's occurrences.
			$saved_destination_ids = array_intersect_key( self::get_saved_remapped_ids( $destination_key ), array_flip( $source_ids ) );

			// Occurrence IDs this batch may use in the destination table: its source occurrence IDs and the saved new ones.
			$candidate_ids = array_merge( $source_ids, array_map( 'intval', $saved_destination_ids ) );

			// Occurrences table of the destination database.
			$destination_table = Occurrences_Entity::get_table_name( $destination );

			// Selects the destination occurrences that already use any of these occurrence IDs.
			$sql = 'SELECT * FROM ' . $destination_table . ' WHERE id IN (' . External_DB_Transfer_Helper::get_ids_as_sql_in_list_text( $candidate_ids ) . ')';

			// Runs the query on the destination database; if the occurrences table is missing, creates it and runs the query again.
			$result = self::query( $destination, $sql, Occurrences_Entity::class );

			if ( \is_wp_error( $result ) ) {
				return $result;
			}

			// WordPress keeps the rows of the last wpdb query as objects, so we need to convert them to arrays.
			$destination_rows = array_map( 'get_object_vars', $destination->last_result );

			/**
			 * Destination occurrences keyed by their occurrence ID.
			 *
			 * Example, before:
			 * array(
			 *     0 => array( 'id' => '876', 'alert_id' => '1000', ... ),
			 *     1 => array( 'id' => '877', ... ),
			 * )
			 *
			 * After:
			 * array(
			 *     876 => array( 'id' => '876', 'alert_id' => '1000', ... ),
			 *     877 => array( 'id' => '877', ... ),
			 * )
			 */
			$destination_occurrences = array_column( $destination_rows, null, 'id' );

			// A new ID must not be used by any destination occurrence, by leftover destination metadata, or by any candidate.
			$highest_occurrence_id_sql          = 'SELECT IFNULL( MAX( id ), 0 ) FROM ' . $destination_table;
			$highest_metadata_occurrence_id_sql = 'SELECT IFNULL( MAX( occurrence_id ), 0 ) FROM ' . Metadata_Entity::get_table_name( $destination );

			// Returns the larger of the two highest occurrence IDs, in a result column named highest_id.
			$sql = 'SELECT GREATEST( ( ' . $highest_occurrence_id_sql . ' ), ( ' . $highest_metadata_occurrence_id_sql . ' ) ) AS highest_id';

			// Runs the query on the destination database; if the metadata table is missing, creates it and runs the query again.
			$result = self::query( $destination, $sql, Metadata_Entity::class );

			if ( \is_wp_error( $result ) ) {
				return $result;
			}

			// Highest occurrence ID used in the destination, read from the highest_id column of the query above.
			$highest_destination_id = (int) $destination->last_result[0]->highest_id;

			// New IDs start above the destination's highest ID and above this batch's own IDs, which may be copied unchanged.
			$next_free_id = max( $highest_destination_id, max( $candidate_ids ) ) + 1;

			// Decides each occurrence's destination ID and which occurrences still need copying, so no copy overwrites a different event.
			$destination_plan = External_DB_Transfer_Helper::decide_occurrence_destination_ids( $occurrences, $destination_occurrences, $saved_destination_ids, $next_free_id );

			// If this batch's new destination IDs differ from the ones an interrupted earlier attempt saved in the wp_options or wp_sitemeta table.
			if ( $destination_plan['remapped_ids'] !== $saved_destination_ids ) {
				// Saves the new IDs before copying: if the copy is interrupted, the next attempt finds the occurrences already copied under these IDs instead of copying them again under other IDs. Necessary to recover from interruptions without creating duplicate entries.
				self::update_saved_remapped_ids( $destination_key, $destination_plan['remapped_ids'], array() );
			}

			/**
			 * Step 4 of 5: copy the batch to the destination.
			 *
			 * Inserts the occurrences that are not in the destination yet, never replacing an existing row. Then replaces the
			 * metadata of this batch's destination IDs only, including remnants of an interrupted copy, so the metadata of
			 * other events is never touched. Every write first checks that this transfer is still the only one that can write.
			 */
			$formats = External_DB_Transfer_Helper::get_occurrence_columns_sql_formats();

			if ( $destination_plan['rows_to_insert'] ) {
				$result = self::copy_rows( $destination, Occurrences_Entity::class, $destination_plan['rows_to_insert'], $formats, $transfer_lock );

				if ( \is_wp_error( $result ) ) {
					return $result;
				}
			}

			$destination_ids_sql_in_list = External_DB_Transfer_Helper::get_ids_as_sql_in_list_text( $destination_plan['destination_ids'] );

			// Deletes the destination metadata of this batch's occurrence IDs, left there by an interrupted earlier copy, so the metadata is not inserted twice.
			$delete_batch_destination_metadata_sql = 'DELETE FROM ' . Metadata_Entity::get_table_name( $destination ) . ' WHERE occurrence_id IN (' . $destination_ids_sql_in_list . ')';

			$result = self::write_with_external_db_transfer_lock( $destination, $delete_batch_destination_metadata_sql, $transfer_lock, Metadata_Entity::class );

			if ( \is_wp_error( $result ) ) {
				return $result;
			}

			if ( $metadata ) {
				$destination_metadata = External_DB_Transfer_Helper::get_metadata_with_destination_occurrence_ids( $metadata, $destination_plan['destination_ids'] );

				$result = self::copy_rows(
					$destination,
					Metadata_Entity::class,
					$destination_metadata,
					array(
						'occurrence_id' => '%d',
						'name'          => '%s',
						'value'         => '%s',
					),
					$transfer_lock
				);

				if ( \is_wp_error( $result ) ) {
					return $result;
				}
			}

			/**
			 * Step 5 of 5: delete the batch from the source.
			 *
			 * Runs only after the whole batch was copied: occurrences first, then their metadata, so metadata
			 * stays attached to surviving events if a source deletion fails.
			 * $count records how many occurrences were deleted.
			 */
			$sql    = 'DELETE FROM ' . $table . ' WHERE id IN (' . $occurrence_ids_sql_in_list . ')';
			$result = self::write_with_external_db_transfer_lock( $source, $sql, $transfer_lock );

			if ( \is_wp_error( $result ) ) {
				// Nontransactional tables can delete some rows before reporting an error.
				$remaining = self::query( $source, 'SELECT id FROM ' . $table . ' WHERE id IN (' . $occurrence_ids_sql_in_list . ')' );

				if ( ! \is_wp_error( $remaining ) ) {
					$count = count( $occurrences ) - $remaining;
				}

				return $result;
			}

			$count = $result;

			// The batch's occurrences are copied and deleted from the source, so their saved destination IDs are no longer needed.
			if ( $saved_destination_ids || $destination_plan['remapped_ids'] ) {
				self::update_saved_remapped_ids( $destination_key, array(), $source_ids );
			}

			if ( $metadata ) {
				$metadata_ids_sql_in_list = External_DB_Transfer_Helper::get_ids_as_sql_in_list_text( array_column( $metadata, 'id' ) );
				$sql                      = 'DELETE FROM ' . Metadata_Entity::get_table_name( $source ) . ' WHERE id IN (' . $metadata_ids_sql_in_list . ')';
				$result                   = self::write_with_external_db_transfer_lock( $source, $sql, $transfer_lock );

				if ( \is_wp_error( $result ) ) {
					return $result;
				}
			}

			return true;
		}

		/**
		 * Copies events and metadata before deleting confirmed source records.
		 *
		 * @param array $args - Archive database and selection limits.
		 * @param int   $count - Number of confirmed source occurrence deletions.
		 *
		 * @return bool|\WP_Error - True for a transfer, false for no records, or an error with a count.
		 *
		 * @since 4.6.0
		 * @since 5.7.0 - Checks every transfer stage and allows only one transfer at a time.
		 */
		public static function archive( $args, &$count = 0 ) {
			$count       = 0;
			$source      = Abstract_Entity::get_connection();
			$destination = $args['archive_db'] ?? null;

			if ( ! $destination instanceof \wpdb || $source === $destination ) {
				return new \WP_Error( 'wsal_archive_invalid_connection', \__( 'A separate archive database connection is required.', 'wp-security-audit-log' ) );
			}

			$source_errors      = $source->suppress_errors( true );
			$destination_errors = $destination->suppress_errors( true );
			$transfer_lock      = '';

			try {
				// $tables[0] is the source occurrences table and $tables[1] the destination one, each as "server:port/database.table".
				$tables = array();

				foreach ( array( $source, $destination ) as $connection ) {
					$server = $connection->get_var( "SELECT CONCAT( @@hostname, ':', @@port, '/', DATABASE() )" );

					if ( ! is_string( $server ) || '' !== $connection->last_error ) {
						return new \WP_Error( 'wsal_archive_invalid_connection', \__( 'Could not connect to the activity log database.', 'wp-security-audit-log' ) );
					}

					$tables[] = $server . '.' . Occurrences_Entity::get_table_name( $connection );
				}

				// If the source and destination occurrences tables are the same table, copying the records into it and then deleting the source would remove them.
				if ( $tables[0] === $tables[1] ) {
					return new \WP_Error( 'wsal_archive_invalid_connection', \__( 'A separate archive database connection is required.', 'wp-security-audit-log' ) );
				}

				$acquired_transfer_lock = self::acquire_external_db_transfer_lock();

				if ( \is_wp_error( $acquired_transfer_lock ) ) {
					return $acquired_transfer_lock;
				}

				$transfer_lock = $acquired_transfer_lock;

				// Passes the destination occurrences table, $tables[1], as the key that keeps the saved new occurrence IDs of each destination separate.
				$result = self::transfer_batch( $args, $source, $destination, $tables[1], $transfer_lock, $count );

				if ( \is_wp_error( $result ) ) {
					$result->add_data( array( 'count' => $count ) );
				}

				return $result;
			} finally {
				if ( '' !== $transfer_lock ) {
					self::release_external_db_transfer_lock( $transfer_lock );
				}

				$source->suppress_errors( $source_errors );
				$destination->suppress_errors( $destination_errors );
			}
		}
	}
}
