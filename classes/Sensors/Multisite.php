<?php
/**
 * Sensor: Multisite
 *
 * Multisite sensor file.
 *
 * @since 1.0.0
 * @package Wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Multisite Sensor.
 *
 * 4010 Existing user added to a site
 * 4011 User removed from site
 * 4012 New network user created
 * 5008 Activated theme on network
 * 5009 Deactivated theme from network
 * 7000 New site added on the network
 * 7001 Existing site archived
 * 7002 Archived site has been unarchived
 * 7003 Deactivated site has been activated
 * 7004 Site has been deactivated
 * 7005 Existing site deleted from network
 * 7012 Network registration option updated
 *
 * @package Wsal
 * @subpackage Sensors
 */
class WSAL_Sensors_Multisite extends WSAL_AbstractSensor {

	/**
	 * Allowed Themes.
	 *
	 * @var array
	 */
	protected $old_allowedthemes = null;

	/**
	 * Listening to events using WP hooks.
	 */
	public function HookEvents() {
		add_action( 'admin_init', array( $this, 'EventAdminInit' ) );
		if ( current_user_can( 'switch_themes' ) ) {
			add_action( 'shutdown', array( $this, 'EventAdminShutdown' ) );
		}
		add_action( 'wp_insert_site', array( $this, 'EventNewBlog' ), 10, 1 );
		add_action( 'archive_blog', array( $this, 'EventArchiveBlog' ) );
		add_action( 'unarchive_blog', array( $this, 'EventUnarchiveBlog' ) );
		add_action( 'activate_blog', array( $this, 'EventActivateBlog' ) );
		add_action( 'deactivate_blog', array( $this, 'EventDeactivateBlog' ) );
		add_action( 'wp_uninitialize_site', array( $this, 'EventDeleteBlog' ) );
		add_action( 'add_user_to_blog', array( $this, 'EventUserAddedToBlog' ), 10, 3 );
		add_action( 'remove_user_from_blog', array( $this, 'EventUserRemovedFromBlog' ), 10, 2 );

		add_action( 'update_site_option', array( $this, 'on_network_option_change'), 10, 4 );
	}

	/**
	 * Fires when the network registration option is updated.
	 *
	 * @since 4.1.5
	 *
	 * @param string $option     Name of the network option.
	 * @param mixed  $value      Current value of the network option.
	 * @param mixed  $old_value  Old value of the network option.
	 * @param int    $network_id ID of the network.
	 */
	public function on_network_option_change( $option, $value, $old_value, $network_id ) {

		switch ( $option ) {
			case 'registration':
				// Array of potential values.
				$possible_values = array(
					'none' => __( 'disabled', 'wp-security-audit-log' ),
					'user' => __( 'user accounts only', 'wp-security-audit-log' ),
					'blog' => __( 'users can register new sites', 'wp-security-audit-log' ),
					'all'  => __( 'sites & users can be registered', 'wp-security-audit-log' ),
				);
				$this->plugin->alerts->Trigger(
					7012,
					array(
						'previous_setting' => ( isset( $possible_values[ $old_value ] ) ) ? $possible_values[ $old_value ] : $old_value,
						'new_setting'      => ( isset( $possible_values[ $value ] ) ) ? $possible_values[ $value ] : $value,
					)
				);
				break;

			case 'add_new_users':
				$this->plugin->alerts->Trigger(
					7007,
					array(
						'EventType' => ( ! $value ) ? 'disabled' : 'enabled',
					)
				);
				break;

			case 'upload_space_check_disabled':
				$this->plugin->alerts->Trigger(
					7008,
					array(
						'EventType' => ( $value ) ? 'disabled' : 'enabled',
					)
				);
				break;

			case 'blog_upload_space':
				$this->plugin->alerts->Trigger(
					7009,
					array(
						'old_value' => sanitize_text_field( $old_value ),
						'new_value' => sanitize_text_field( $value ),
					)
				);
				break;

			case 'upload_filetypes':
				$this->plugin->alerts->Trigger(
					7010,
					array(
						'old_value' => sanitize_text_field( $old_value ),
						'new_value' => sanitize_text_field( $value ),
					)
				);
				break;

			case 'fileupload_maxk':
				$this->plugin->alerts->Trigger(
					7009,
					array(
						'old_value' => sanitize_text_field( $old_value ),
						'new_value' => sanitize_text_field( $value ),
					)
				);
				break;
		}

	}

	/**
	 * Triggered when a user accesses the admin area.
	 */
	public function EventAdminInit() {
		$this->old_allowedthemes = array_keys( (array) get_site_option( 'allowedthemes' ) );
	}

	/**
	 * Activated/Deactivated theme on network.
	 */
	public function EventAdminShutdown() {
		if ( is_null( $this->old_allowedthemes ) ) {
			return;
		}
		$new_allowedthemes = array_keys( (array) get_site_option( 'allowedthemes' ) );

		// Check for enabled themes.
		foreach ( $new_allowedthemes as $theme ) {
			if ( ! in_array( $theme, (array) $this->old_allowedthemes ) ) {
				$theme = wp_get_theme( $theme );
				$this->plugin->alerts->Trigger(
					5008,
					array(
						'Theme' => (object) array(
							'Name'                   => $theme->Name,
							'ThemeURI'               => $theme->ThemeURI,
							'Description'            => $theme->Description,
							'Author'                 => $theme->Author,
							'Version'                => $theme->Version,
							'get_template_directory' => $theme->get_template_directory(),
						),
					)
				);
			}
		}

		// Check for disabled themes.
		foreach ( (array) $this->old_allowedthemes as $theme ) {
			if ( ! in_array( $theme, $new_allowedthemes ) ) {
				$theme = wp_get_theme( $theme );
				$this->plugin->alerts->Trigger(
					5009,
					array(
						'Theme' => (object) array(
							'Name'                   => $theme->Name,
							'ThemeURI'               => $theme->ThemeURI,
							'Description'            => $theme->Description,
							'Author'                 => $theme->Author,
							'Version'                => $theme->Version,
							'get_template_directory' => $theme->get_template_directory(),
						),
					)
				);
			}
		}
	}

	/**
	 * New site added on the network.
	 *
	 * @param WP_Site $new_blog - New site object.
	 */
	public function EventNewBlog( $new_blog ) {
		$blog_id = $new_blog->blog_id;

		//  site meta data nor options are not setup at this points so get_blog_option and get_home_url are not
		//  returning anything for the new blog

		//  the following code to work out the correct URL for the new site was taken from ms-site.php (WP 5.7)
		//  @see https://github.com/WordPress/WordPress/blob/5.7/wp-includes/ms-site.php#L673
		$network = get_network( $new_blog->network_id );
		if ( ! $network ) {
			$network = get_network();
		}

		$home_scheme = 'http';
		if ( ! is_subdomain_install() ) {
			if ( 'https' === parse_url( get_home_url( $network->site_id ), PHP_URL_SCHEME ) ) {
				$home_scheme = 'https';
			}
		}

		$blog_title = strip_tags( $_POST['blog']['title'] );
		$blog_url   = untrailingslashit( $home_scheme . '://' . $new_blog->domain . $new_blog->path );

		$this->plugin->alerts->Trigger(
			7000,
			array(
				'BlogID'   => $blog_id,
				'SiteName' => $blog_title,
				'BlogURL'  => $blog_url
			)
		);
	}

	/**
	 * Existing site archived.
	 *
	 * @param int $blog_id - Blog ID.
	 */
	public function EventArchiveBlog( $blog_id ) {
		$this->plugin->alerts->Trigger(
			7001,
			array(
				'BlogID'   => $blog_id,
				'SiteName' => get_blog_option( $blog_id, 'blogname' ),
				'BlogURL'  => get_home_url( $blog_id ),
			)
		);
	}

	/**
	 * Archived site has been unarchived.
	 *
	 * @param int $blog_id - Blog ID.
	 */
	public function EventUnarchiveBlog( $blog_id ) {
		$this->plugin->alerts->Trigger(
			7002,
			array(
				'BlogID'   => $blog_id,
				'SiteName' => get_blog_option( $blog_id, 'blogname' ),
				'BlogURL'  => get_home_url( $blog_id ),
			)
		);
	}

	/**
	 * Deactivated site has been activated.
	 *
	 * @param int $blog_id - Blog ID.
	 */
	public function EventActivateBlog( $blog_id ) {
		$this->plugin->alerts->Trigger(
			7003,
			array(
				'BlogID'   => $blog_id,
				'SiteName' => get_blog_option( $blog_id, 'blogname' ),
				'BlogURL'  => get_home_url( $blog_id ),
			)
		);
	}

	/**
	 * Site has been deactivated.
	 *
	 * @param int $blog_id - Blog ID.
	 */
	public function EventDeactivateBlog( $blog_id ) {
		$this->plugin->alerts->Trigger(
			7004,
			array(
				'BlogID'   => $blog_id,
				'SiteName' => get_blog_option( $blog_id, 'blogname' ),
				'BlogURL'  => get_home_url( $blog_id ),
			)
		);
	}

	/**
	 * Existing site deleted from network.
	 *
	 * @param WP_Site $deleted_blog - Deleted blog object.
	 */
	public function EventDeleteBlog( $deleted_blog ) {
		$blog_id = $deleted_blog->blog_id;
		$this->plugin->alerts->Trigger(
			7005,
			array(
				'BlogID'   => $blog_id,
				'SiteName' => get_blog_option( $blog_id, 'blogname' ),
				'BlogURL'  => get_home_url( $blog_id ),
			)
		);
	}

	/**
	 * Existing user added to a site.
	 *
	 * @param int    $user_id - User ID.
	 * @param string $role - User role.
	 * @param int    $blog_id - Blog ID.
	 */
	public function EventUserAddedToBlog( $user_id, $role, $blog_id ) {
		$user = get_userdata( $user_id );
		$this->plugin->alerts->TriggerIf(
			4010,
			array(
				'TargetUserID'   => $user_id,
				'TargetUsername' => $user ? $user->user_login : false,
				'TargetUserRole' => $role,
				'BlogID'         => $blog_id,
				'SiteName'       => get_blog_option( $blog_id, 'blogname' ),
				'FirstName'      => $user ? $user->user_firstname : false,
				'LastName'       => $user ? $user->user_lastname : false,
				'EditUserLink'   => add_query_arg( 'user_id', $user_id, admin_url( 'user-edit.php' ) ),
			),
			array( $this, 'MustNotContainCreateUser' )
		);
	}

	/**
	 * User removed from site.
	 *
	 * @param int $user_id - User ID.
	 * @param int $blog_id - Blog ID.
	 */
	public function EventUserRemovedFromBlog( $user_id, $blog_id ) {
		$user = get_userdata( $user_id );
		$this->plugin->alerts->TriggerIf(
			4011,
			array(
				'TargetUserID'   => $user_id,
				'TargetUsername' => $user->user_login,
				'TargetUserRole' => is_array( $user->roles ) ? implode( ', ', $user->roles ) : $user->roles,
				'BlogID'         => $blog_id,
				'SiteName'       => get_blog_option( $blog_id, 'blogname' ),
				'FirstName'      => $user ? $user->user_firstname : false,
				'LastName'       => $user ? $user->user_lastname : false,
				'EditUserLink'   => add_query_arg( 'user_id', $user_id, admin_url( 'user-edit.php' ) ),
			),
			array( $this, 'MustNotContainCreateUser' )
		);
	}

	/**
	 * New network user created.
	 *
	 * @param WSAL_AlertManager $mgr - Instance of Alert Manager.
	 */
	public function MustNotContainCreateUser( WSAL_AlertManager $mgr ) {
		return ! $mgr->WillTrigger( 4012 );
	}
}
