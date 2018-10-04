<?php
/**
 * Manager: Sensor
 *
 * Sensor manager class file.
 *
 * @since 1.0.0
 * @package Wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sensor Manager.
 *
 * This class loads all the sensors and initialize them.
 *
 * @package Wsal
 */
final class WSAL_SensorManager extends WSAL_AbstractSensor {

	/**
	 * Array of sensors.
	 *
	 * @var WSAL_AbstractSensor[]
	 */
	protected $sensors = array();

	/**
	 * Method: Constructor.
	 *
	 * @param WpSecurityAuditLog $plugin - Instance of WpSecurityAuditLog.
	 */
	public function __construct( WpSecurityAuditLog $plugin ) {
		parent::__construct( $plugin );

		// Check sensors before loading for optimization.
		add_filter( 'wsal_before_sensor_load', array( $this, 'check_sensor_before_load' ), 10, 2 );

		foreach ( glob( dirname( __FILE__ ) . '/Sensors/*.php' ) as $file ) {
			$this->AddFromFile( $file );
		}

		/**
		 * Load Custom Sensor files from /wp-content/uploads/wp-security-audit-log/custom-sensors/
		 */
		$upload_dir       = wp_upload_dir();
		$uploads_dir_path = trailingslashit( $upload_dir['basedir'] ) . 'wp-security-audit-log' . DIRECTORY_SEPARATOR . 'custom-sensors' . DIRECTORY_SEPARATOR;

		// Check directory.
		if ( is_dir( $uploads_dir_path ) && is_readable( $uploads_dir_path ) ) {
			foreach ( glob( $uploads_dir_path . '*.php' ) as $file ) {
				// Include custom sensor file.
				require_once $file;
				$file   = substr( $file, 0, -4 );
				$sensor = str_replace( $uploads_dir_path, '', $file );

				// Skip if the file is index.php for security.
				if ( 'index' === $sensor ) {
					continue;
				}

				// Generate and initiate custom sensor file.
				$class = 'WSAL_Sensors_' . $sensor;
				$this->AddFromClass( $class );
			}
		}
	}

	/**
	 * Method: Hook events of the sensors.
	 */
	public function HookEvents() {
		foreach ( $this->sensors as $sensor ) {
			$sensor->HookEvents();
		}
	}

	/**
	 * Method: Get the sensors.
	 */
	public function GetSensors() {
		return $this->sensors;
	}

	/**
	 * Add new sensor from file inside autoloader path.
	 *
	 * @param string $file Path to file.
	 */
	public function AddFromFile( $file ) {
		/**
		 * Filter: `wsal_before_sensor_load`
		 *
		 * Check to see if sensor is to be initiaited or not.
		 *
		 * @param bool   $load_sensor – Set to true if sensor is to be loaded.
		 * @param string $file        – File path.
		 */
		$load_sensor = apply_filters( 'wsal_before_sensor_load', true, $file );

		// Initiate the sensor if $load_sensor is true.
		if ( $load_sensor ) {
			$this->AddFromClass( $this->plugin->GetClassFileClassName( $file ) );
		}
	}

	/**
	 * Add new sensor given class name.
	 *
	 * @param string $class Class name.
	 */
	public function AddFromClass( $class ) {
		$this->AddInstance( new $class( $this->plugin ) );
	}

	/**
	 * Add newly created sensor to list.
	 *
	 * @param WSAL_AbstractSensor $sensor The new sensor.
	 */
	public function AddInstance( WSAL_AbstractSensor $sensor ) {
		$this->sensors[] = $sensor;
	}

	/**
	 * Check sensor before loading.
	 *
	 * @param bool   $load_sensor – Whether to load sensor or not.
	 * @param string $filepath    – File path.
	 * @return bool
	 */
	public function check_sensor_before_load( $load_sensor, $filepath ) {
		global $pagenow;
		if ( ! $this->plugin->IsMultisite() ) {
			$admin_page = $pagenow;
		} else {
			/**
			 * Global $pagenow is not set in multisite while plugins are loading.
			 * So we use the wp-core code to create one for ourselves before it is
			 * set.
			 *
			 * @see wp-includes/vars.php
			 */
			$php_self = isset( $_SERVER['PHP_SELF'] ) ? sanitize_text_field( wp_unslash( $_SERVER['PHP_SELF'] ) ) : false;
			preg_match( '#/wp-admin/?(.*?)$#i', $php_self, $self_matches );
			$admin_page = isset( $self_matches[1] ) ? $self_matches[1] : false;
			$admin_page = trim( $admin_page, '/' );
			$admin_page = preg_replace( '#\?.*?$#', '', $admin_page );
		}

		// WSAL views array.
		$wsal_views = array(
			'wsal-auditlog',
			'wsal-togglealerts',
			'wsal-settings',
			'wsal-emailnotifications',
			'wsal-loginusers',
			'wsal-reports',
			'wsal-search',
			'wsal-externaldb',
			'wsal-user-management-views',
			'wsal-rep-views-main',
			'wsal-np-notifications',
			'wsal-np-addnotification',
			'wsal-np-editnotification',
			'wsal-ext-settings',
			'wsal-help',
			'wsal-auditlog-account',
			'wsal-auditlog-contact',
			'wsal-auditlog-pricing',
		);

		// Get current page query argument via $_GET array.
		$current_page = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING );

		// Check these conditions before loading sensors.
		if (
			is_admin()
			&& (
				in_array( $current_page, $wsal_views, true ) // WSAL Views.
				|| 'index.php' === $admin_page  // Dashboard.
				|| 'tools.php' === $admin_page  // Tools page.
				|| 'export.php' === $admin_page // Export page.
				|| 'import.php' === $admin_page // Import page.
			)
		) {
			return false;
		}

		// Get file name.
		$filename = basename( $filepath, '.php' );

		// If filename exists then continue.
		if ( $filename ) {
			// Conditional loading based on filename.
			switch ( $filename ) {
				case 'BBPress':
					// Check if BBPress plugin exists.
					if ( ! is_plugin_active( 'bbpress/bbpress.php' ) ) {
						$load_sensor = false;
					}
					break;

				case 'WooCommerce':
					// Check if WooCommerce plugin exists.
					if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
						$load_sensor = false;
					}
					break;

				case 'YoastSEO':
					// Check if Yoast SEO (Free or Premium) plugin exists.
					if ( is_plugin_active( 'wordpress-seo/wp-seo.php' ) || is_plugin_active( 'wordpress-seo-premium/wp-seo-premium.php' ) ) {
						$load_sensor = true;
					} else {
						$load_sensor = false;
					}
					break;

				case 'Multisite':
					// If site is not multisite then don't load it.
					if ( ! $this->plugin->IsMultisite() ) {
						$load_sensor = false;
					}
					break;

				case 'Menus':
					// If the current page is not Menus page in themes tab or customizer then don't load menu sensor.
					if ( 'nav-menus.php' === $admin_page || 'customize.php' === $admin_page ) {
						$load_sensor = true;
					} else {
						$load_sensor = false;
					}
					break;

				case 'Widgets':
					// If the current page is not Widgets page in themes tab or customizer then don't load menu sensor.
					if ( 'widgets.php' === $admin_page || 'customize.php' === $admin_page || 'admin-ajax.php' === $admin_page ) {
						$load_sensor = true;
					} else {
						$load_sensor = false;
					}
					break;

				case 'FileChanges':
					// If file changes is disabled then don't load file changes sensor.
					if ( 'enable' !== $this->plugin->GetGlobalOption( 'scan-file-changes', 'enable' ) ) {
						$load_sensor = false;

						// Clear scheduled hook if there is any hook scheduled.
						if ( wp_next_scheduled( WSAL_Sensors_FileChanges::$schedule_hook ) ) {
							wp_clear_scheduled_hook( WSAL_Sensors_FileChanges::$schedule_hook );
						}
					}
					break;

				default:
					break;
			}
		}

		return $load_sensor;
	}
}
