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

		/*
		 * Get an array of directories to loop through to add custom sensors.
		 *
		 * Passed through a filter so other plugins or code can add own custom
		 * sensor class files by adding the containing directory to this array.
		 *
		 * @since 3.5.1 - Added the `wsal_custom_sensors_classes_dirs` filter.
		 */
		$paths = apply_filters( 'wsal_custom_sensors_classes_dirs', array() );
		foreach ( $paths as $inc_path ) {
			// Check directory.
			if ( is_dir( $inc_path ) && is_readable( $inc_path ) ) {
				foreach ( glob( $inc_path . '*.php' ) as $file ) {
					// Include custom sensor file.
					require_once $file;
					$file   = substr( $file, 0, -4 );
					$sensor = str_replace( $inc_path, '', $file );

					// Skip if the file is index.php for security.
					if ( 'index' === $sensor ) {
						continue;
					}

					/*
					 * @since 3.5.1 Allow loading classes where names match the
					 * filename 1:1. Prior to version 3.5.1 sensors were always
					 * assumed to be defined WITH `WSAL_Sensors_` prefix in the
					 * class name but WITHOUT it in the filename. This behavior
					 * is retained for back-compat.
					 */
					$class = ( class_exists( $sensor ) ) ? $sensor : 'WSAL_Sensors_' . $sensor;
					$this->AddFromClass( $class );
				}
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

		// Get file name.
		$filename = basename( $filepath, '.php' );

		$frontend_events = WSAL_Settings::get_frontend_events();

		// Check to see if LogInOut, and FrontendRegister sensors should load on login page.
		if ( WpSecurityAuditLog::is_login_screen() ) {
			if ( 'FrontendRegister' === $filename && ! empty( $frontend_events['register'] )
			     || 'LogInOut' === $filename ) {
				return true;
			}
			return false; // Any other sensor should not load here.
		}

		/**
		 * WSAL Filter: `wsal_load_public_sensor`
		 *
		 * Filter for the list of sensors to be loaded for visitors
		 * or public. No sensor is allowed to load on the front-end
		 * except the ones in this array.
		 *
		 * @since 3.3.1
		 *
		 * @param array $public_sensors - List of sensors to be loaded for visitors.
		 */
		$public_sensors = apply_filters( 'wsal_load_public_sensors', array( 'FrontendRegister', 'LogInOut' ) );
		if ( WpSecurityAuditLog::is_frontend() && ! is_user_logged_in() && ! in_array( $filename, $public_sensors, true ) ) {
			return false;
		}

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

		// If filename exists then continue.
		if ( $filename ) {
			// Conditional loading based on filename.
			switch ( $filename ) {
				case 'BBPress':
					// Check if BBPress plugin exists.
					if ( ! WpSecurityAuditLog::is_bbpress_active() ) {
						$load_sensor = false;
					}
					break;

				case 'YoastSEO':
					// Check if Yoast SEO (Free or Premium) plugin exists.
					if ( WpSecurityAuditLog::is_wpseo_active() ) {
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

				case 'FrontendRegister':
					if ( is_user_logged_in() || empty( $frontend_events['register'] ) ) {
						$load_sensor = false;
					}
					break;

				case 'LogInOut':
					if ( is_user_logged_in() || empty( $frontend_events['login'] ) ) {
						$load_sensor = false;
					}
					break;

				default:
					break;
			}
		}
		return $load_sensor;
	}
}
