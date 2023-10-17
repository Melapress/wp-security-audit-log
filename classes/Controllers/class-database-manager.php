<?php
/**
 * Controller: Database Manager.
 *
 * Alert manager class file.
 *
 * @since     4.6
 *
 * @package   wsal
 * @subpackage controllers
 */

declare(strict_types=1);

namespace WSAL\Controllers;

use WSAL\Controllers\Connection;
use WSAL\Entities\Metadata_Entity;
use WSAL\Entities\Occurrences_Entity;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Controllers\Database_Manager' ) ) {
	/**
	 * Provides some DB operations.
	 *
	 * @since 4.6.0
	 */
	class Database_Manager {

		/**
		 * Purge plugin occurrence & meta tables.
		 *
		 * @return bool
		 *
		 * @since 4.6.0
		 */
		public static function purge_activity() {
			// Get connection.
			$wpdb = Connection::get_connection();

			$query_occ = true;
			if ( Occurrences_Entity::check_table_exists() ) {
				// Get occurrence model.
				$sql       = 'TRUNCATE ' . Occurrences_Entity::get_table_name();
				$query_occ = $wpdb->query( $sql ); // phpcs:ignore
			}

			$query_meta = true;
			if ( Metadata_Entity::check_table_exists() ) {
				// Get meta model.
				$sql        = 'TRUNCATE ' . Metadata_Entity::get_table_name();
				$query_meta = $wpdb->query( $sql ); // phpcs:ignore
			}

			// If both queries are successful, then return true.
			return $query_occ && $query_meta;
		}
	}
}
