<?php
/**
 * Deleting records class.
 *
 * @package wsal
 * @author Stoil Dobrev <sdobreff@gmail.com>
 */

declare(strict_types=1);

namespace WSAL\Entities\Archive;

use WSAL\Entities\Abstract_Entity;
use WSAL\Entities\Metadata_Entity;
use WSAL\Entities\Occurrences_Entity;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Entities\Archive\Delete_Records' ) ) {

	/**
	 * Responsible for the common entity operations.
	 *
	 * @since 4.6.0
	 */
	class Delete_Records {

		/**
		 * Deletes records from the Occurrences amd Metadata tables.
		 *
		 * @param array   $order - The order clause [ field -> order direction ].
		 * @param integer $limit - The limit for the query.
		 * @param array   $condition - Condition to be used in the query.
		 * @param \WPDB   $connection - \WPDB connection to be used (if different from the currently default one).
		 *
		 * @return array - Number of deleted records (in Occurrences only) and the executed SQL as string.
		 *
		 * @since 4.6.0
		 */
		public static function delete( $order = array(), $limit = 0, $condition = array(), $connection = null ) {
			if ( null !== $connection ) {
				if ( $connection instanceof \WPDB ) {
					$_wpdb = $connection;

				}
			} else {
				$_wpdb = Abstract_Entity::get_connection();
			}

			$sql = 'SELECT max(id) FROM ' . Occurrences_Entity::get_table_name( $_wpdb ) . ' WHERE 1 ';

			if ( ! empty( $condition ) ) {
				$sql .= ' AND ' . \array_key_first( $condition );
			}

			/**
			 * Multi order is not supported currently.
			 */
			if ( ! empty( $order ) ) {
				$sql .= ' ORDER BY ' . \array_key_first( $order ) . ' ' . reset( $order );
			}

			if ( ! empty( $limit ) ) {
				$sql .= ' LIMIT ' . $order;
			}

			$_wpdb->suppress_errors( true );
			$biggest_id = (int) $_wpdb->get_var( $_wpdb->prepare( $sql, reset( $condition ) ) );
			if ( '' !== $_wpdb->last_error ) {
				if ( 1146 === Occurrences_Entity::get_last_sql_error( $_wpdb ) ) {
					if ( Occurrences_Entity::create_table( $_wpdb ) ) {
						$biggest_id = (int) $_wpdb->get_var( $sql );
					}
				}
			}
			$_wpdb->suppress_errors( false );

			if ( $biggest_id > 0 ) {
				$delete_meta = 'DELETE FROM ' . Metadata_Entity::get_table_name( $_wpdb ) . ' WHERE occurrence_id <= %s';

				Metadata_Entity::delete_query( $delete_meta, array( $biggest_id ), $_wpdb );

				$delete_occurrence = 'DELETE FROM ' . Occurrences_Entity::get_table_name( $_wpdb ) . ' WHERE 1 ';

				if ( ! empty( $condition ) ) {
					$delete_occurrence .= ' AND ' . \array_key_first( $condition );
				}

				/**
				 * Multi order is not supported currently.
				 */
				if ( ! empty( $order ) ) {
					$delete_occurrence .= ' ORDER BY ' . \array_key_first( $order ) . ' ' . reset( $order );
				}

				if ( ! empty( $limit ) ) {
					$delete_occurrence .= ' LIMIT ' . $order;
				}

				return array(
					Occurrences_Entity::delete_query(
						$delete_occurrence,
						array( reset( $condition ) ),
						$_wpdb
					),
					$delete_occurrence,
				);
			}
		}
	}
}
