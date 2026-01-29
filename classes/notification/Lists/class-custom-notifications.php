<?php
/**
 * Responsible for the Showing the list of the events collected.
 *
 * @package    WSAL
 * @subpackage helpers
 *
 * @since 5.2.1
 *
 * @copyright  2026 Melapress
 * @license    https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 *
 * @see       https://wordpress.org/plugins/wp-2fa/
 */

declare(strict_types=1);

namespace WSAL\Extensions\Notifications;

use WSAL\Helpers\Settings_Helper;
use WSAL\Views\Notifications;
use WSAL\Helpers\DateTime_Formatter_Helper;
use WSAL\Entities\Custom_Notifications_Entity;
use WSAL\Extensions\Helpers\Notification_Helper;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/template.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table-compat.php';
	require_once ABSPATH . 'wp-admin/includes/list-table.php';
}

/*
 * Base list table class
 */
if ( ! class_exists( '\WSAL\Extensions\Notifications\Custom_Notifications' ) ) {
	/**
	 * Responsible for rendering base table for manipulation.
	 *
	 * @since 5.2.1
	 */
	class Custom_Notifications extends \WP_List_Table {
// phpcs:disable
// phpcs:enable
	}
}
