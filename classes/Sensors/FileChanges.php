<?php
/**
 * Sensor: File Changes Detection
 *
 * Sensor file for detecting file changes.
 *
 * @since 3.2
 * @package Wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class: File Change Detection Sensor
 *
 * @package Wsal
 */
class WSAL_Sensors_FileChanges extends WSAL_AbstractSensor {

	/**
	 * WP Root Path.
	 *
	 * @var string
	 */
	private $root_path = '';

	/**
	 * Paths to exclude during scan.
	 *
	 * @var array
	 */
	private $excludes = array();

	/**
	 * View settings.
	 *
	 * @var array
	 */
	public $scan_settings = array();

	/**
	 * Frequency daily hour
	 * For testing change hour here [01 to 23]
	 *
	 * @var array
	 */
	private static $daily_hour = array( '04' );

	/**
	 * Frequency weekly date
	 * For testing change date here [1 (for Monday) through 7 (for Sunday)]
	 *
	 * @var string
	 */
	private static $weekly_day = '1';

	/**
	 * Frequency montly date
	 * For testing change date here [01 to 31]
	 *
	 * @var string
	 */
	private static $monthly_day = '01';

	/**
	 * Schedule hook name
	 * For testing change the name
	 *
	 * @var string
	 */
	public static $schedule_hook = 'wsal_detect_file_changes';

	/**
	 * Scan files counter during a scan.
	 *
	 * @var int
	 */
	private $scan_file_count = 0;

	/**
	 * Scan files limit reached.
	 *
	 * @var bool
	 */
	private $scan_limit_file = false;

	/**
	 * Class constants.
	 */
	const SCAN_DAILY      = 'daily';
	const SCAN_WEEKLY     = 'weekly';
	const SCAN_MONTHLY    = 'monthly';
	const SCAN_FILE_LIMIT = 1000000;

	/**
	 * Method: Constructor.
	 *
	 * @param WpSecurityAuditLog $plugin - Instance of WpSecurityAuditLog.
	 */
	public function __construct( WpSecurityAuditLog $plugin ) {
		// Call to parent constructor.
		parent::__construct( $plugin );

		// Set root path.
		$this->root_path = trailingslashit( ABSPATH );

		if ( empty( $this->scan_settings ) ) {
			$this->load_file_change_settings();
		}

		add_action( 'wsal_init', array( $this, 'schedule_file_changes' ) );
	}

	/**
	 * Listening to events using WP hooks.
	 */
	public function HookEvents() {
		// Disable the sensor if file changes is disabled.
		if ( isset( $this->scan_settings['scan_file_changes'] ) && 'enable' !== $this->scan_settings['scan_file_changes'] ) {
			return;
		}

		// Filter stored and scanned files to balance scan file exclusion.
		add_filter( 'wsal_file_scan_stored_files', array( $this, 'filter_scan_files' ), 10, 2 );
		add_filter( 'wsal_file_scan_scanned_files', array( $this, 'filter_scan_files' ), 10, 2 );

		// Empty skip file alerts array.
		add_action( 'wsal_after_file_scan', array( $this, 'empty_skip_file_alerts' ), 10, 1 );

		// Reset skip core updates flag to normal.
		add_action( 'wsal_last_scanned_directory', array( $this, 'reset_core_updates_flag' ), 10, 1 );
	}

	/**
	 * Method: Reset file and directory counter for scan.
	 */
	public function reset_scan_counter() {
		$this->scan_file_count = 0;
		$this->scan_limit_file = false;
	}

	/**
	 * Method: Load file detection settings.
	 */
	public function load_file_change_settings() {
		if ( ! is_multisite() ) {
			$default_scan_dirs = array( 'root', 'wp-admin', 'wp-includes', 'wp-content', 'wp-content/themes', 'wp-content/plugins', 'wp-content/uploads' );
		} else {
			$default_scan_dirs = array( 'root', 'wp-admin', 'wp-includes', 'wp-content', 'wp-content/themes', 'wp-content/plugins', 'wp-content/uploads', 'wp-content/uploads/sites' );
		}

		// Load file detection settings.
		$this->scan_settings = array(
			'scan_file_changes'   => $this->plugin->GetGlobalOption( 'scan-file-changes', 'enable' ),
			'scan_frequency'      => $this->plugin->GetGlobalOption( 'scan-frequency', 'weekly' ),
			'scan_hour'           => $this->plugin->GetGlobalOption( 'scan-hour', '04' ),
			'scan_day'            => $this->plugin->GetGlobalOption( 'scan-day', '1' ),
			'scan_date'           => $this->plugin->GetGlobalOption( 'scan-date', '10' ),
			'scan_directories'    => $this->plugin->GetGlobalOption( 'scan-directories', $default_scan_dirs ),
			'excluded_dirs'       => $this->plugin->GetGlobalOption( 'scan-excluded-directories', array( trailingslashit( WP_CONTENT_DIR ) . 'cache' ) ),
			'excluded_extensions' => $this->plugin->GetGlobalOption( 'scan-excluded-extensions', array( 'jpg', 'jpeg', 'png', 'bmp', 'pdf', 'txt', 'log', 'mo', 'po', 'mp3', 'wav', 'gif', 'ico', 'jpe', 'psd', 'raw', 'svg', 'tif', 'tiff', 'aif', 'flac', 'm4a', 'oga', 'ogg', 'ra', 'wma', 'asf', 'avi', 'mkv', 'mov', 'mp4', 'mpe', 'mpeg', 'mpg', 'ogv', 'qt', 'rm', 'vob', 'webm', 'wm', 'wmv' ) ),
			'excluded_files'      => $this->plugin->GetGlobalOption( 'scan_excluded_files', array() ),
			'last_scanned'        => $this->plugin->GetGlobalOption( 'last-scanned', false ),
			'file_size_limit'     => $this->plugin->GetGlobalOption( 'scan-file-size-limit', 5 ),
		);

		// Set the scan hours.
		if ( ! empty( $this->scan_settings['scan_hour'] ) ) {
			$saved_hour = (int) $this->scan_settings['scan_hour'];
			$next_hour  = $saved_hour + 1;
			$hours      = array( $saved_hour, $next_hour );
			foreach ( $hours as $hour ) {
				$daily_hour[] = str_pad( $hour, 2, '0', STR_PAD_LEFT );
			}
			self::$daily_hour = $daily_hour;
		}

		// Set weekly day.
		if ( ! empty( $this->scan_settings['scan_day'] ) ) {
			self::$weekly_day = $this->scan_settings['scan_day'];
		}

		// Set monthly date.
		if ( ! empty( $this->scan_settings['scan_date'] ) ) {
			self::$monthly_day = $this->scan_settings['scan_date'];
		}
	}

	/**
	 * Method: Schedule file changes.
	 */
	public function schedule_file_changes() {
		// Schedule file changes if the feature is enabled.
		if ( is_multisite() && ! is_main_site() ) {
			// Clear the scheduled hook if feature is disabled.
			wp_clear_scheduled_hook( self::$schedule_hook );
		} elseif ( 'enable' === $this->scan_settings['scan_file_changes'] ) {
			// Hook scheduled method.
			add_action( self::$schedule_hook, array( $this, 'detect_file_changes' ) );

			// Schedule event if there isn't any already.
			if ( ! wp_next_scheduled( self::$schedule_hook ) ) {
				wp_schedule_event(
					time(), // Timestamp.
					'tenminutes', // Frequency.
					self::$schedule_hook // Scheduled event.
				);
			}
		} else {
			// Clear the scheduled hook if feature is disabled.
			wp_clear_scheduled_hook( self::$schedule_hook );
		}
	}

	/**
	 * Method: Detect file changes.
	 *
	 * @param bool $manual - Set to true for manual scan.
	 * @param int  $last_scanned - Last scanned directory index of server directories. Helpful in performing manual scan.
	 */
	public function detect_file_changes( $manual = false, $last_scanned = null ) {
		// Check scan time frequency & last scanned directory list.
		if ( ! $manual && ! $this->check_start_scan( $this->scan_settings['scan_frequency'] ) ) {
			return;
		}

		// Check if a scan is already in progress.
		if ( $this->plugin->GetGlobalOption( 'scan-in-progress', false ) ) {
			return;
		}

		// Set the scan in progress to true because the scan has started.
		$this->plugin->SetGlobalOption( 'scan-in-progress', true );

		// Check last scanned for manual scan.
		if ( ! $manual && is_null( $last_scanned ) ) {
			// Replace the last scanned value with the setting value
			// if the scan is not manual and last scan value is null.
			$last_scanned = $this->scan_settings['last_scanned'];
		}

		// Get directories to be scanned.
		$directories = $this->scan_settings['scan_directories'];

		// Set the next directory to scan.
		if ( ! $manual ) {
			if ( false === $last_scanned || $last_scanned > 5 ) {
				$next_to_scan = 0;
			} elseif ( 'root' === $last_scanned ) {
				$next_to_scan = 1;
			} else {
				$next_to_scan = $last_scanned + 1;
			}
		} else {
			$next_to_scan = $last_scanned;
		}

		// Set the options name for file list.
		$file_list = "local_files_$next_to_scan";

		// Prepare directories array.
		// @todo Store this in transient to cache the value. We don't need to load it every time.
		$uploads_dir = wp_upload_dir();

		// Server directories.
		$server_dirs = array(
			'', // Root directory.
			'wp-admin', // WordPress Admin.
			WPINC, // wp-includes.
			WP_CONTENT_DIR, // wp-content.
			WP_CONTENT_DIR . '/themes', // Themes.
			WP_PLUGIN_DIR, // Plugins.
			$uploads_dir['basedir'], // Uploads.
		);

		// Prepare directories path.
		foreach ( $server_dirs as $index => $server_dir ) {
			$server_dir            = untrailingslashit( $server_dir );
			$server_dirs[ $index ] = preg_replace( '/^' . preg_quote( ABSPATH, '/' ) . '/', '', $server_dir );
		}

		// Get directory path to scan.
		$path_to_scan = $server_dirs[ $next_to_scan ];

		if ( ( empty( $path_to_scan ) && in_array( 'root', $directories, true ) )
		|| ( ! empty( $path_to_scan ) && in_array( $path_to_scan, $directories, true ) ) ) {
			// Exclude everything else.
			unset( $server_dirs[ $next_to_scan ] );
			$this->excludes = $server_dirs;

			// Get list of files to scan from DB.
			$stored_files = $this->plugin->GetGlobalOption( $file_list, array() );

			/**
			 * `Filter`: Stored files filter.
			 *
			 * @param array  $stored_files – Files array already saved in DB from last scan.
			 * @param string $path_to_scan – Path currently being scanned.
			 */
			$filtered_stored_files = apply_filters( 'wsal_file_scan_stored_files', $stored_files, $path_to_scan );

			// Get array of already directories scanned from DB.
			$scanned_dirs = $this->plugin->GetGlobalOption( 'scanned_dirs', array() );

			// If already scanned directories don't exist then it marks the start of a scan.
			if ( ! $manual && empty( $scanned_dirs ) ) {
				$this->plugin->SetGlobalOption( 'last_scan_start', time() );
			}

			/**
			 * Before file scan action hook.
			 *
			 * @param string $path_to_scan - Directory path to scan.
			 */
			do_action( 'wsal_before_file_scan', $path_to_scan );

			// Reset scan counter.
			$this->reset_scan_counter();

			// Scan the path.
			$scanned_files = $this->scan_path( $path_to_scan );

			/**
			 * `Filter`: Scanned files filter.
			 *
			 * @param array  $scanned_files – Files array already saved in DB from last scan.
			 * @param string $path_to_scan – Path currently being scanned.
			 */
			$filtered_scanned_files = apply_filters( 'wsal_file_scan_scanned_files', $scanned_files, $path_to_scan );

			// Add the currently scanned path to scanned directories.
			$scanned_dirs[] = $path_to_scan;

			/**
			 * After file scan action hook.
			 *
			 * @param string $path_to_scan - Directory path to scan.
			 */
			do_action( 'wsal_after_file_scan', $path_to_scan );

			// Get initial scan setting.
			$initial_scan = $this->plugin->GetGlobalOption( "is_initial_scan_$next_to_scan", 'yes' );

			// If the scan is not initial then.
			if ( 'yes' !== $initial_scan ) {
				// Compare the results to find out about file added and removed.
				$files_added   = array_diff_key( $filtered_scanned_files, $filtered_stored_files );
				$files_removed = array_diff_key( $filtered_stored_files, $filtered_scanned_files );

				/**
				 * File changes.
				 *
				 * To scan the files with changes, we need to
				 *
				 *  1. Remove the newly added files from scanned files – no need to add them to changed files array.
				 *  2. Remove the deleted files from already logged files – no need to compare them since they are removed.
				 *  3. Then start scanning for differences – check the difference in hash.
				 */
				$scanned_files_minus_added  = array_diff_key( $filtered_scanned_files, $files_added );
				$stored_files_minus_deleted = array_diff_key( $filtered_stored_files, $files_removed );

				// Changed files array.
				$files_changed = array();

				// Go through each newly scanned file.
				foreach ( $scanned_files_minus_added as $file => $file_hash ) {
					// Check if it exists in already stored array of files, ignore if the key does not exists.
					if ( array_key_exists( $file, $stored_files_minus_deleted ) ) {
						// If key exists, then check if the file hash is set and compare it to already stored hash.
						if (
							! empty( $file_hash ) && ! empty( $stored_files_minus_deleted[ $file ] )
							&& 0 !== strcmp( $file_hash, $stored_files_minus_deleted[ $file ] )
						) {
							// If the file hashes don't match then store the file in changed files array.
							$files_changed[ $file ] = $file_hash;
						}
					}
				}

				// Files added alert.
				if ( count( $files_added ) > 0 ) {
					// Get excluded site content.
					$site_content = $this->plugin->GetGlobalOption( 'site_content' );

					// Log the alert.
					foreach ( $files_added as $file => $file_hash ) {
						// Get directory name.
						$directory_name = dirname( $file );

						// Check if the directory is in excluded directories list.
						if (
							! empty( $site_content->skip_directories )
							&& in_array( $directory_name, $site_content->skip_directories, true )
						) {
							continue; // If true, then skip the loop.
						}

						// Get filename from file path.
						$filename = basename( $file );

						// Check if the filename is in excluded files list.
						if (
							! empty( $site_content->skip_files )
							&& in_array( $filename, $site_content->skip_files, true )
						) {
							continue; // If true, then skip the loop.
						}

						// Check for allowed extensions.
						if (
							! empty( $site_content->skip_extensions )
							&& in_array( pathinfo( $filename, PATHINFO_EXTENSION ), $site_content->skip_extensions, true )
						) {
							continue; // If true, then skip the loop.
						}

						// Created file event.
						$this->plugin->alerts->Trigger( 6029, array(
							'FileLocation'  => $file,
							'FileHash'      => $file_hash,
							'CurrentUserID' => '0',
						) );
					}
				}

				// Files removed alert.
				if ( count( $files_removed ) > 0 ) {
					// Log the alert.
					foreach ( $files_removed as $file => $file_hash ) {
						// Get directory name.
						$directory_name = dirname( $file );

						// Check if directory is in excluded directories list.
						if ( in_array( $directory_name, $this->scan_settings['excluded_dirs'], true ) ) {
							continue; // If true, then skip the loop.
						}

						// Get filename from file path.
						$filename = basename( $file );

						// Check if the filename is in excluded files list.
						if ( in_array( $filename, $this->scan_settings['excluded_files'], true ) ) {
							continue; // If true, then skip the loop.
						}

						// Check for allowed extensions.
						if ( in_array( pathinfo( $filename, PATHINFO_EXTENSION ), $this->scan_settings['excluded_extensions'], true ) ) {
							continue; // If true, then skip the loop.
						}

						// Removed file event.
						$this->plugin->alerts->Trigger( 6030, array(
							'FileLocation'  => $file,
							'FileHash'      => $file_hash,
							'CurrentUserID' => '0',
						) );
					}
				}

				// Files edited alert.
				if ( count( $files_changed ) > 0 ) {
					// Log the alert.
					foreach ( $files_changed as $file => $file_hash ) {
						$this->plugin->alerts->Trigger( 6028, array(
							'FileLocation'  => $file,
							'FileHash'      => $file_hash,
							'CurrentUserID' => '0',
						) );
					}
				}

				// Check for files limit alert.
				if ( $this->scan_limit_file ) {
					$this->plugin->alerts->Trigger( 6032, array(
						'CurrentUserID' => '0',
					) );
				}

				/**
				 * `Action`: Last scanned directory.
				 *
				 * @param int $next_to_scan – Last scanned directory.
				 */
				do_action( 'wsal_last_scanned_directory', $next_to_scan );
			} else {
				$this->plugin->SetGlobalOption( "is_initial_scan_$next_to_scan", 'no' ); // Initial scan check set to false.
			}

			// Store scanned files list.
			$this->plugin->SetGlobalOption( $file_list, $scanned_files );

			if ( ! $manual ) {
				$this->plugin->SetGlobalOption( 'scanned_dirs', $scanned_dirs );
			}
		}

		/**
		 * Update last scanned directory.
		 *
		 * IMPORTANT: This option is saved outside start scan check
		 * so that if the scan is skipped, then the increment of
		 * next to scan is not disturbed.
		 */
		if ( ! $manual ) {
			if ( 0 === $next_to_scan ) {
				$this->plugin->SetGlobalOption( 'last-scanned', 'root' );

				// Scan started alert.
				$this->plugin->alerts->Trigger( 6033, array(
					'CurrentUserID' => '0',
					'ScanStatus'    => 'started',
				) );
			} elseif ( 6 === $next_to_scan ) {
				$this->plugin->SetGlobalOption( 'last-scanned', $next_to_scan );

				// Scan stopped.
				$this->plugin->alerts->Trigger( 6033, array(
					'CurrentUserID' => '0',
					'ScanStatus'    => 'stopped',
				) );
			} else {
				$this->plugin->SetGlobalOption( 'last-scanned', $next_to_scan );
			}
		}

		// Set the scan in progress to false because scan is complete.
		$this->plugin->SetGlobalOption( 'scan-in-progress', false );
	}

	/**
	 * Method: Scan path for files.
	 *
	 * @param string $path - Directory path to scan.
	 * @return array - Array of files present in $path.
	 */
	private function scan_path( $path = '' ) {
		// Check excluded paths.
		if ( in_array( $path, $this->excludes ) ) {
			return array();
		}

		// Set the directory path.
		$dir_path = $this->root_path . $path;
		$files    = array(); // Array of files to return.

		// Open directory.
		$dir_handle = @opendir( $dir_path );
		if ( false === $dir_handle ) {
			return $files; // Return if directory fails to open.
		}

		$is_multisite    = is_multisite(); // Multsite checks.
		$directories     = $this->scan_settings['scan_directories']; // Get directories to be scanned.
		$file_size_limit = $this->scan_settings['file_size_limit']; // Get file size limit.
		$file_size_limit = $file_size_limit * 1048576; // Calculate file size limit in bytes; 1MB = 1048576 bytes.

		// Scan the directory for files.
		while ( false !== ( $item = @readdir( $dir_handle ) ) ) {
			// Ignore `.` and `..` from directory.
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			// Filter valid filename.
			if ( preg_match( '/[^A-Za-z0-9 _ .-]/', $item ) > 0 ) {
				continue;
			}

			// If we're on root then ignore `wp-admin`, `wp-content` & `wp-includes`.
			if (
				empty( $path )
				&& (
					false !== strpos( $item, 'wp-admin' )
					|| false !== strpos( $item, 'wp-content' )
					|| false !== strpos( $item, 'wp-includes' )
				)
			) {
				continue;
			}

			// Ignore `.git`, `.svn`, & `node_modules` from scan.
			if ( false !== strpos( $item, '.git' ) || false !== strpos( $item, '.svn' ) || false !== strpos( $item, 'node_modules' ) ) {
				continue;
			}

			// Set item paths.
			if ( ! empty( $path ) ) {
				$relative_name = $path . '/' . $item; // Relative file path w.r.t. the location in major 7 folders.
				$absolute_name = $dir_path . '/' . $item; // Complete file path w.r.t. ABSPATH.
			} else {
				// If path is empty then it is root.
				$relative_name = $path . $item; // Relative file path w.r.t. the location in major 7 folders.
				$absolute_name = $dir_path . $item; // Complete file path w.r.t. ABSPATH.
			}

			// Check for directory.
			if ( is_dir( $absolute_name ) ) {
				/**
				 * `Filter`: Directory name filter before opening it for scan.
				 *
				 * @param string $item – Directory name.
				 */
				$item = apply_filters( 'wsal_directory_before_file_scan', $item );
				if ( ! $item ) {
					continue;
				}

				// Check if the directory is in excluded directories list.
				if ( in_array( $absolute_name, $this->scan_settings['excluded_dirs'], true ) ) {
					continue; // Skip the directory.
				}

				// If not multisite then simply scan.
				if ( ! $is_multisite ) {
					$files = array_merge( $files, $this->scan_path( $relative_name ) );
				} else {
					/**
					 * Check if `wp-content/uploads/sites` is present in the
					 * relative name of the directory & it is allowed to scan.
					 */
					if (
						false !== strpos( $relative_name, 'wp-content/uploads/sites' )
						&& in_array( 'wp-content/uploads/sites', $directories, true )
					) {
						$files = array_merge( $files, $this->scan_path( $relative_name ) );
					} elseif (
						false !== strpos( $relative_name, 'wp-content/uploads/sites' )
						&& ! in_array( 'wp-content/uploads/sites', $directories, true )
					) {
						// If `wp-content/uploads/sites` is not allowed to scan then skip the loop.
						continue;
					} else {
						$files = array_merge( $files, $this->scan_path( $relative_name ) );
					}
				}
			} else {
				/**
				 * `Filter`: File name filter before scan.
				 *
				 * @param string $item – File name.
				 */
				$item = apply_filters( 'wsal_filename_before_file_scan', $item );
				if ( ! $item ) {
					continue;
				}

				// Check if the item is in excluded files list.
				if ( in_array( $item, $this->scan_settings['excluded_files'], true ) ) {
					continue; // If true, then skip the loop.
				}

				// Check for allowed extensions.
				if ( in_array( pathinfo( $item, PATHINFO_EXTENSION ), $this->scan_settings['excluded_extensions'], true ) ) {
					continue; // If true, then skip the loop.
				}

				// Check files count.
				if ( $this->scan_file_count > self::SCAN_FILE_LIMIT ) { // If file limit is reached.
					$this->scan_limit_file = true; // Then set the limit flag.
					break; // And break the loop.
				}

				// Check file size limit.
				if ( filesize( $absolute_name ) < $file_size_limit ) {
					$this->scan_file_count = $this->scan_file_count + 1;
					// File data.
					$files[ $absolute_name ] = @md5_file( $absolute_name ); // File hash.
				} else {
					// File size is more than the limit.
					$this->plugin->alerts->Trigger( 6031, array(
						'FileLocation'  => $absolute_name,
						'CurrentUserID' => '0',
					) );

					// File data.
					$files[ $absolute_name ] = '';
				}
			}
		}

		// Close the directory.
		@closedir( $dir_handle );

		// Return files data.
		return $files;
	}

	/**
	 * Method: Filter scan files before file changes comparison. This
	 * function filters both stored & scanned files.
	 *
	 * Filters:
	 *     1. wp-content/plugins (Plugins).
	 *     2. wp-content/themes (Themes).
	 *     3. wp-admin (WP Core).
	 *     4. wp-includes (WP Core).
	 *
	 * Hooks using this function:
	 *     1. wsal_file_scan_stored_files.
	 *     2. wsal_file_scan_scanned_files.
	 *
	 * @param array  $scan_files – Scan files array.
	 * @param string $path_to_scan – Path currently being scanned.
	 * @return array
	 */
	public function filter_scan_files( $scan_files, $path_to_scan ) {
		// If the path to scan is of plugins.
		if (
			false !== strpos( $path_to_scan, 'wp-content/plugins' )
		) {
			// Filter plugin files.
			$scan_files = $this->filter_excluded_scan_files( $scan_files, 'plugins' );
		} elseif (
			false !== strpos( $path_to_scan, 'wp-content/themes' ) // And if the path to scan is of themes then.
		) {
			// Filter theme files.
			$scan_files = $this->filter_excluded_scan_files( $scan_files, 'themes' );
		} elseif (
			false !== strpos( $path_to_scan, 'wp-admin' ) // If the path is wp-admin or
			|| false !== strpos( $path_to_scan, 'wp-includes' ) // wp-includes then check it for core updates skip.
		) {
			// Get `site_content` option.
			$site_content = $this->plugin->GetGlobalOption( 'site_content', false );

			// If the `skip_core` is set and its value is equal to true then.
			if ( isset( $site_content->skip_core ) && true === $site_content->skip_core ) {
				// Empty the scan files.
				$scan_files = array();
			}
		}

		// Return the filtered scan files.
		return $scan_files;
	}

	/**
	 * Method: Filter different types of content from scan files.
	 *
	 * Excluded types:
	 *  1. Plugins.
	 *  2. Themes.
	 *
	 * @param array  $scan_files - Array of scan files.
	 * @param string $excluded_type - Type to be excluded.
	 * @return array
	 */
	public function filter_excluded_scan_files( $scan_files, $excluded_type ) {
		// Check if any one of the two parameters are empty.
		if ( empty( $scan_files ) || empty( $excluded_type ) ) {
			return $scan_files;
		}

		// Get list of excluded plugins/themes.
		$excluded_contents = $this->plugin->GetGlobalOption( 'site_content', false );

		// If excluded files exists then.
		if ( ! empty( $excluded_contents ) ) {
			// Get an array of scan files.
			$files = array_keys( $scan_files );

			// An array of files to exclude from scan files array.
			$files_to_exclude = array();

			if (
				'plugins' === $excluded_type
				&& isset( $excluded_contents->skip_plugins ) // Skip plugins array exists.
				&& is_array( $excluded_contents->skip_plugins ) // Skip plugins is array.
				&& ! empty( $excluded_contents->skip_plugins ) // And if it is not empty.
			) {
				// Go through each plugin to be skipped.
				foreach ( $excluded_contents->skip_plugins as $plugin ) {
					// Path of plugin to search in stored files.
					$search_path = '/plugins/' . $plugin;

					// Get array of files to exclude of plugins from scan files array.
					foreach ( $files as $file ) {
						if ( false !== strpos( $file, $search_path ) ) {
							$files_to_exclude[] = $file;
						}
					}
				}
			} elseif (
				'themes' === $excluded_type
				&& isset( $excluded_contents->skip_themes ) // Skip themes array exists.
				&& is_array( $excluded_contents->skip_themes ) // Skip themes is array.
				&& ! empty( $excluded_contents->skip_themes ) // And if it is not empty.
			) {
				// Go through each theme to be skipped.
				foreach ( $excluded_contents->skip_themes as $theme ) {
					// Path of theme to search in stored files.
					$search_path = '/themes/' . $theme;

					// Get array of files to exclude of themes from scan files array.
					$files_to_exclude = array();
					foreach ( $files as $file ) {
						if ( false !== strpos( $file, $search_path ) ) {
							$files_to_exclude[] = $file;
						}
					}
				}
			}

			// If there are files to be excluded then.
			if ( ! empty( $files_to_exclude ) ) {
				// Go through each file to be excluded and unset it from scan files array.
				foreach ( $files_to_exclude as $file_to_exclude ) {
					if ( array_key_exists( $file_to_exclude, $scan_files ) ) {
						unset( $scan_files[ $file_to_exclude ] );
					}
				}
			}
		}
		return $scan_files;
	}

	/**
	 * Method: Empty skip file alerts array after the scan of plugins path.
	 *
	 * @param string $path_to_scan – Path currently being scanned.
	 * @return void
	 */
	public function empty_skip_file_alerts( $path_to_scan ) {
		// Check path to scan is not empty.
		if ( empty( $path_to_scan ) ) {
			return;
		}

		// If path to scan is of plugins then empty the skip plugins array.
		if ( false !== strpos( $path_to_scan, 'wp-content/plugins' ) ) {
			// Get contents list.
			$site_content = $this->plugin->GetGlobalOption( 'site_content', false );

			// Empty skip plugins array.
			$site_content->skip_plugins = array();

			// Save it.
			$this->plugin->SetGlobalOption( 'site_content', $site_content );

			// If path to scan is of themes then empty the skip themes array.
		} elseif ( false !== strpos( $path_to_scan, 'wp-content/themes' ) ) {
			// Get contents list.
			$site_content = $this->plugin->GetGlobalOption( 'site_content', false );

			// Empty skip themes array.
			$site_content->skip_themes = array();

			// Save it.
			$this->plugin->SetGlobalOption( 'site_content', $site_content );
		}
	}

	/**
	 * Method: Check scan frequency.
	 *
	 * Scan start checks:
	 *   1. Check frequency is not empty.
	 *   2. Check if there is any directory left to scan.
	 *     2a. If there is a directory left, then proceed to check frequency.
	 *     2b. Else check if 24 hrs limit is passed or not.
	 *   3. Check frequency of the scan set by user and decide to start the scan or not.
	 *
	 * @param string $frequency - Frequency of the scan.
	 * @return bool True if scan is a go, false if not.
	 */
	public function check_start_scan( $frequency ) {
		// If empty then return false.
		if ( empty( $frequency ) ) {
			return false;
		}

		/**
		 * When there are no directories left to scan then:
		 *
		 * 1. Get the last scan start time.
		 * 2. Check for 24 hrs limit.
		 * 3a. If the limit has passed then remove options related to last scan.
		 * 3b. Else return false.
		 */
		if ( ! $this->dir_left_to_scan( $this->scan_settings['scan_directories'] ) ) {
			// Get last scan time.
			$last_scan_start = $this->plugin->GetGlobalOption( 'last_scan_start', false );

			if ( ! empty( $last_scan_start ) ) {
				// Check for minimum 24 hours.
				$scan_hrs = $this->hours_since_last_scan( $last_scan_start );

				// If scan hours difference has passed 24 hrs limit then remove the options.
				if ( $scan_hrs > 23 ) {
					$this->plugin->DeleteByName( 'wsal-scanned_dirs' ); // Delete already scanned directories option.
					$this->plugin->DeleteByName( 'wsal-last_scan_start' ); // Delete last scan complete timestamp option.
				} else {
					// Else if they have not passed their limit, then return false.
					return false;
				}
			}
		}

		// Scan check.
		$scan = false;

		// Frequency set by user on the settings page.
		switch ( $frequency ) {
			case self::SCAN_DAILY: // Daily scan.
				if ( in_array( $this->calculate_daily_hour(), self::$daily_hour, true ) ) {
					$scan = true;
				}
				break;
			case self::SCAN_WEEKLY: // Weekly scan.
				$weekly_day = $this->calculate_weekly_day();
				$scan       = ( self::$weekly_day === $weekly_day ) ? true : false;
				break;
			case self::SCAN_MONTHLY: // Monthly scan.
				$str_date = $this->calculate_monthly_day();
				if ( ! empty( $str_date ) ) {
					$scan = ( date( 'Y-m-d' ) == $str_date ) ? true : false;
				}
				break;
		}
		return $scan;
	}

	/**
	 * Method: Calculate and return hour of the day
	 * based on WordPress timezone.
	 *
	 * @return string - Hour of the day.
	 */
	private function calculate_daily_hour() {
		return date( 'H', time() + ( get_option( 'gmt_offset' ) * ( 60 * 60 ) ) );
	}

	/**
	 * Method: Calculate and return day of the week
	 * based on WordPress timezone.
	 *
	 * @return string|bool - Day of the week or false.
	 */
	private function calculate_weekly_day() {
		if ( in_array( $this->calculate_daily_hour(), self::$daily_hour, true ) ) {
			return date( 'w' );
		}
		return false;
	}

	/**
	 * Method: Calculate and return day of the month
	 * based on WordPress timezone.
	 *
	 * @return string|bool - Day of the week or false.
	 */
	private function calculate_monthly_day() {
		if ( in_array( $this->calculate_daily_hour(), self::$daily_hour, true ) ) {
			return date( 'Y-m-' ) . self::$monthly_day;
		}
		return false;
	}

	/**
	 * Method: Check to determine if there is any directory left to scan.
	 *
	 * @param array $scan_directories - Array of directories to scan set by user.
	 * @return bool
	 */
	public function dir_left_to_scan( $scan_directories ) {
		// Return false if $scan_directories is empty.
		if ( empty( $scan_directories ) ) {
			return false;
		}

		// If multisite then remove all the subsites uploads of multisite from scan directories.
		if ( is_multisite() ) {
			foreach ( $scan_directories as $index => $dir ) {
				if ( false !== strpos( $dir, 'wp-content/uploads/sites' ) ) {
					unset( $scan_directories[ $index ] );
				}
			}
		}

		// Get array of already directories scanned from DB.
		$already_scanned_dirs = $this->plugin->GetGlobalOption( 'scanned_dirs', array() );

		// Check if already scanned directories has `root` directory.
		if ( in_array( '', $already_scanned_dirs, true ) ) {
			// If found then search for `root` in the directories to be scanned.
			$key = array_search( 'root', $scan_directories, true );
			if ( false !== $key ) {
				// If key is found then remove it from directories to be scanned array.
				unset( $scan_directories[ $key ] );
			}
		}

		// Check the difference in directories.
		$diff = array_diff( $scan_directories, $already_scanned_dirs );

		// If the diff array has 1 or more value then scan needs to run.
		if ( is_array( $diff ) && count( $diff ) > 0 ) {
			return true;
		} elseif ( empty( $diff ) ) {
			return false;
		}
		return false;
	}

	/**
	 * Method: Get number of hours since last file changes scan.
	 *
	 * @param float $created_on – Timestamp of last scan.
	 * @return bool|int – False if $created_on is empty | Number of hours otherwise.
	 */
	public function hours_since_last_scan( $created_on ) {
		// If $created_on is empty, then return.
		if ( empty( $created_on ) ) {
			return false;
		}

		// Last alert date.
		$created_date = new DateTime( date( 'Y-m-d H:i:s', $created_on ) );

		// Current date.
		$current_date = new DateTime( 'NOW' );

		// Calculate time difference.
		$time_diff = $current_date->diff( $created_date );
		$diff_days = $time_diff->d; // Difference in number of days.
		$diff_hrs  = $time_diff->h; // Difference in number of hours.
		$total_hrs = ( $diff_days * 24 ) + $diff_hrs; // Total number of hours.

		// Return difference in hours.
		return $total_hrs;
	}

	/**
	 * Method: Reset core file changes flag.
	 *
	 * @param int $last_scanned_dir – Last scanned directory.
	 */
	public function reset_core_updates_flag( $last_scanned_dir ) {
		// Check if last scanned directory exists and it is at last directory.
		if ( ! empty( $last_scanned_dir ) && 6 === $last_scanned_dir ) {
			// Get `site_content` option.
			$site_content = $this->plugin->GetGlobalOption( 'site_content', false );

			// Check if the option is instance of stdClass.
			if ( false !== $site_content && $site_content instanceof stdClass ) {
				$site_content->skip_core        = false;   // Reset skip core after the scan is complete.
				$site_content->skip_files       = array(); // Empty the skip files at the end of the scan.
				$site_content->skip_extensions  = array(); // Empty the skip extensions at the end of the scan.
				$site_content->skip_directories = array(); // Empty the skip directories at the end of the scan.
				$this->plugin->SetGlobalOption( 'site_content', $site_content ); // Save the option.
			}
		}
	}
}
