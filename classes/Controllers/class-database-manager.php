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
			$query_occ = Occurrences_Entity::truncate_table();

			$query_meta = Metadata_Entity::truncate_table();

			// If both queries are successful, then return true.
			return $query_occ && $query_meta;
		}
	}
}
