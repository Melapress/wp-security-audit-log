<?php
/**
 * Alerts file.
 *
 * Alerts are defined in this file.
 *
 * @package wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// If not included correctly...
if ( ! class_exists( 'WpSecurityAuditLog' ) ) {
	exit();
}

// Define custom / new PHP constants.
defined( 'E_CRITICAL' ) || define( 'E_CRITICAL', 'E_CRITICAL' );

/**
 * Gets an array of directories to loop through to add custom alerts.
 *
 * Passed through a filter so other plugins or code can add own custom
 * alerts files by adding the containing directory to this array.
 *
 * @param WpSecurityAuditLog $wsal - Instance of main plugin.
 *
 * @since 3.5.1 - Added the `wsal_custom_alerts_dirs` filter.
 *
 */
function wsal_load_include_custom_files( $wsal ) {
	$paths = apply_filters( 'wsal_custom_alerts_dirs', array() );
	foreach ( $paths as $inc_path ) {
		// Check directory.
		if ( is_dir( $inc_path ) && is_readable( $inc_path ) ) {
			$file = $inc_path . DIRECTORY_SEPARATOR . 'custom-alerts.php';
			if ( file_exists( $file ) ) {
				// A file exists that should contain custom alerts - require it.
				require_once $file;
				if ( ! empty( $custom_alerts ) && is_array( $custom_alerts ) ) {
					try {
						$wsal->alerts->RegisterGroup( $custom_alerts );
					} catch ( Exception $ex ) {
						$wsal->wsal_log( $ex->getMessage() );
					}
				}
			}
		}
	}
}

/**
 * Builds a configuration object of links suitable for the the events definition.
 *
 * @param string[] $link_aliases
 *
 * @return array
 */
function wsaldefaults_build_links( $link_aliases = [] ) {
	$result = [];

	if ( ! empty( $link_aliases ) ) {
		foreach ( $link_aliases as $link_alias ) {
			switch ( $link_alias ) {
				case 'CategoryLink':
				case 'cat_link':
				case 'ProductCatLink':
					$result[ __( 'View category', 'wp-security-audit-log' ) ] = '%' . $link_alias . '%';
					break;

				case 'ContactSupport':
					$result[ __( 'Contact Support', 'wp-security-audit-log' ) ] = 'https://wpactivitylog.com/contact/';
					break;

				case 'CommentLink':
					$result[ __( 'Comment', 'wp-security-audit-log' ) ] = [
						//  before 4.2.1 the CommentLink meta would contain the full HTML markup for the link, now it
						//  contains only the URL
						'url'   => '%CommentLink%',
						'label' => '%CommentDate%'
					];
					break;

				case 'EditorLinkPage':
					$result[ __( 'View page in the editor', 'wp-security-audit-log' ) ] = '%EditorLinkPage%';
					break;

				case 'EditorLinkPost':
					$result[ __( 'View the post in editor', 'wp-security-audit-log' ) ] = '%EditorLinkPost%';
					break;

				case 'EditorLinkOrder':
					//  @todo move to the WooCommerce extension
					$result[ __( 'View the order', 'wp-security-audit-log' ) ] = '%EditorLinkOrder%';
					break;

				case 'EditUserLink':
					$result[ __( 'User profile page', 'wp-security-audit-log' ) ] = '%EditUserLink%';
					break;

				case 'LinkFile':
					$result[ __( 'Open the log file', 'wp-security-audit-log' ) ] = '%LinkFile%';
					break;

				case 'LogFileLink':
					//  we don't show the link anymore
					break;

				case 'MenuUrl':
					$result[ __( 'View menu', 'wp-security-audit-log' ) ] = '%MenuUrl%';
					break;

				case 'PostUrl':
					$result[ __( 'URL', 'wp-security-audit-log' ) ] = '%PostUrl%';
					break;

				case 'PostUrlIfPlublished':
				case 'PostUrlIfPublished':
					$result[ __( 'URL', 'wp-security-audit-log' ) ] = '%PostUrlIfPlublished%';
					break;

				case 'RevisionLink':
					$result[ __( 'View the content changes', 'wp-security-audit-log' ) ] = '%RevisionLink%';
					break;

				case 'TagLink':
					$result[ __( 'View tag', 'wp-security-audit-log' ) ] = '%RevisionLink%';
					break;

				case 'LogFileText':
				case 'MetaLink':
					/*
					 * All these links are formatted using WSAL_AlertFormatter (including any label) because they
					 * contain non-trivial HTML markup that includes custom JS. We assume these will only be rendered
					 * in the log viewer in WP admin UI.
					 */
					array_push( $result, '%' . $link_alias . '%' );
					break;

				default:
					//  unsupported link alias
			}
		}
	}

	return $result;
}

/**
 * Define Default Alerts.
 *
 * Define default alerts for the plugin.
 */
function wsaldefaults_wsal_init() {
	$wsal = WpSecurityAuditLog::GetInstance();

	if ( ! isset( $wsal->constants ) ) {
		$wsal->constants = new WSAL_ConstantManager();
	}

	if ( ! isset( $wsal->alerts ) ) {
		$wsal->alerts = new WSAL_AlertManager( $wsal );
	}

	/*
	 * severity is based on monolog log levels
	 * @see https://github.com/Seldaek/monolog/blob/main/doc/01-usage.md#log-levels
	 */
	//  ALERT (550): Action must be taken immediately.
	$wsal->constants->AddConstant( 'WSAL_CRITICAL', 500, __( 'Critical severity events.', 'wp-security-audit-log' ) );
	//  ERROR (400): Runtime errors that do not require immediate action but should typically be logged and monitored.
	$wsal->constants->AddConstant( 'WSAL_HIGH', 400, __( 'High severity events.', 'wp-security-audit-log' ) );
	//  WARNING (300): Exceptional occurrences that are not errors.
	$wsal->constants->AddConstant( 'WSAL_MEDIUM', 300, __( 'Medium severity events.', 'wp-security-audit-log' ) );
	//  NOTICE (250): Normal but significant events.
	$wsal->constants->AddConstant( 'WSAL_LOW', 250, __( 'Low severity events.', 'wp-security-audit-log' ) );
	//  INFO (200): Interesting events.
	$wsal->constants->AddConstant( 'WSAL_INFORMATIONAL', 200, __( 'Informational events.', 'wp-security-audit-log' ) );

	// Create list of default alerts.
	$wsal->alerts->RegisterGroup(
		array(
			__( 'Users Logins & Sessions Events', 'wp-security-audit-log' ) => array(
				__( 'User Activity', 'wp-security-audit-log' ) => array(
					array(
						1000,
						WSAL_LOW,
						__( 'User logged in', 'wp-security-audit-log' ),
						__( 'User logged in.', 'wp-security-audit-log' ),
						[],
						[],
						'user',
						'login'
					),
					array(
						1001,
						WSAL_LOW,
						__( 'User logged out', 'wp-security-audit-log' ),
						__( 'User logged out.', 'wp-security-audit-log' ),
						[],
						[],
						'user',
						'logout'
					),
					array(
						1002,
						WSAL_MEDIUM,
						__( 'Login failed', 'wp-security-audit-log' ),
						__( '%Attempts% failed login(s).', 'wp-security-audit-log' ),
						[],
						[],
						'user',
						'failed-login'
					),
					array(
						1003,
						WSAL_LOW,
						__( 'Login failed  / non existing user', 'wp-security-audit-log' ),
						__( '%Attempts% failed login(s).', 'wp-security-audit-log' ),
						[],
						wsaldefaults_build_links( [ 'LogFileText' ] ),
						'system',
						'failed-login'
					),
					array(
						1004,
						WSAL_MEDIUM,
						__( 'Login blocked', 'wp-security-audit-log' ),
						__( 'Login blocked because other session(s) already exist for this user.', 'wp-security-audit-log' ),
						[
							__( 'IP address', 'wp-security-audit-log' ) => '%ClientIP%'
						],
						[],
						'user',
						'blocked'
					),
					array(
						1005,
						WSAL_LOW,
						__( 'User logged in with existing session(s)', 'wp-security-audit-log' ),
						__( 'User logged in however there are other session(s) already for this user.', 'wp-security-audit-log' ),
						[
							__( 'IP address(es)', 'wp-security-audit-log' ) => '%IPAddress%'
						],
						[],
						'user',
						'login'
					),
					array(
						1006,
						WSAL_MEDIUM,
						__( 'User logged out all other sessions with the same username', 'wp-security-audit-log' ),
						__( 'Logged out all other sessions with the same user.', 'wp-security-audit-log' ),
						[],
						[],
						'user',
						'logout'
					),
					array(
						1007,
						WSAL_MEDIUM,
						__( 'User session destroyed and logged out', 'wp-security-audit-log' ),
						__( 'Terminated the session of the user %TargetUserName%.', 'wp-security-audit-log' ),
						[
							__( 'Role', 'wp-security-audit-log' )       => '%TargetUserRole%',
							__( 'Session ID', 'wp-security-audit-log' ) => '%TargetSessionID%'
						],
						[],
						'user',
						'logout'
					),
					array(
						1008,
						WSAL_MEDIUM,
						__( 'Switched to another user', 'wp-security-audit-log' ),
						__( 'Switched the session to being logged in as %TargetUserName%.', 'wp-security-audit-log' ),
						[
							__( 'Role', 'wp-security-audit-log' ) => '%TargetUserRole%'
						],
						[],
						'user',
						'login'
					),
					array(
						2010,
						WSAL_MEDIUM,
						__( 'User uploaded file to the Uploads directory', 'wp-security-audit-log' ),
						__( 'Uploaded a file called %FileName%.', 'wp-security-audit-log' ),
						[
							__( 'Directory', 'wp-security-audit-log' ) => '%FilePath%'
						],
						[],
						'file',
						'uploaded'
					),
					array(
						2011,
						WSAL_LOW,
						__( 'User deleted file from Uploads directory', 'wp-security-audit-log' ),
						__( 'Deleted the file %FileName%.', 'wp-security-audit-log' ),
						[
							__( 'Directory', 'wp-security-audit-log' ) => '%FilePath%'
						],
						[],
						'file',
						'deleted'
					),
					array(
						1010,
						WSAL_INFORMATIONAL,
						__( 'User requested a password reset', 'wp-security-audit-log' ),
						__( 'User requested a password reset. This does not mean that the password was changed.', 'wp-security-audit-log' ),
						[],
						[],
						'user',
						'submitted'
					),
				),
			),

			__( 'Content & Comments', 'wp-security-audit-log' ) => array(
				__( 'Content', 'wp-security-audit-log' ) => array(
					array(
						2000,
						WSAL_INFORMATIONAL,
						__( 'User created a new post and saved it as draft', 'wp-security-audit-log' ),
						__( 'Created the post %PostTitle%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%'
						],
						wsaldefaults_build_links( [ 'EditorLinkPost', 'PostUrlIfPublished' ] ),
						'post',
						'created'
					),
					array(
						2001,
						WSAL_LOW,
						__( 'User published a post', 'wp-security-audit-log' ),
						__( 'Published the post %PostTitle%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%'
						],
						wsaldefaults_build_links( [ 'EditorLinkPost', 'PostUrlIfPublished' ] ),
						'post',
						'published'
					),
					array(
						2002,
						WSAL_LOW,
						__( 'User modified a post', 'wp-security-audit-log' ),
						__( 'Modified the post %PostTitle%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%'
						],
						wsaldefaults_build_links( [ 'EditorLinkPost', 'PostUrlIfPublished' ] ),
						'post',
						'modified'
					),
					array(
						2008,
						WSAL_MEDIUM,
						__( 'User permanently deleted a post from the trash', 'wp-security-audit-log' ),
						__( 'Permanently deleted the post %PostTitle%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%'
						],
						[],
						'post',
						'deleted'
					),
					array(
						2012,
						WSAL_MEDIUM,
						__( 'User moved a post to the trash', 'wp-security-audit-log' ),
						__( 'Moved the post %PostTitle% to trash.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%'
						],
						wsaldefaults_build_links( [ 'PostUrlIfPublished' ] ),
						'post',
						'deleted'
					),
					array(
						2014,
						WSAL_LOW,
						__( 'User restored a post from trash', 'wp-security-audit-log' ),
						__( 'Restored the post %PostTitle% from trash.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%'
						],
						wsaldefaults_build_links( [ 'EditorLinkPost', 'PostUrlIfPublished' ] ),
						'post',
						'restored'
					),
					array(
						2017,
						WSAL_INFORMATIONAL,
						__( 'User changed post URL', 'wp-security-audit-log' ),
						__( 'Changed the URL of the post %PostTitle%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )      => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )    => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' )  => '%PostStatus%',
							__( 'Previous URL', 'wp-security-audit-log' ) => '%OldUrl%',
							__( 'New URL', 'wp-security-audit-log' )      => '%NewUrl%'
						],
						wsaldefaults_build_links( [ 'EditorLinkPost' ] ),
						'post',
						'modified'
					),
					array(
						2019,
						WSAL_INFORMATIONAL,
						__( 'User changed post author', 'wp-security-audit-log' ),
						__( 'Changed the author of the post %PostTitle% to %NewAuthor%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )         => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )       => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' )     => '%PostStatus%',
							__( 'Previous author', 'wp-security-audit-log' ) => '%OldAuthor%'
						],
						wsaldefaults_build_links( [ 'PostUrlIfPublished' ] ),
						'post',
						'modified'
					),
					array(
						2021,
						WSAL_MEDIUM,
						__( 'User changed post status', 'wp-security-audit-log' ),
						__( 'Changed the status of the post %PostTitle% to %NewStatus%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )         => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )       => '%PostType%',
							__( 'Previous status', 'wp-security-audit-log' ) => '%OldStatus%'
						],
						wsaldefaults_build_links( [ 'EditorLinkPost', 'PostUrlIfPublished' ] ),
						'post',
						'modified'
					),
					array(
						2025,
						WSAL_LOW,
						__( 'User changed the visibility of a post', 'wp-security-audit-log' ),
						__( 'Changed the visibility of the post %PostTitle% to %NewVisibility%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )                    => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )                  => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' )                => '%PostStatus%',
							__( 'Previous visibility status', 'wp-security-audit-log' ) => '%OldVisibility%'
						],
						wsaldefaults_build_links( [ 'EditorLinkPost', 'PostUrlIfPublished' ] ),
						'post',
						'modified'
					),
					array(
						2027,
						WSAL_INFORMATIONAL,
						__( 'User changed the date of a post', 'wp-security-audit-log' ),
						__( 'Changed the date of the post %PostTitle% to %NewDate%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )       => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )     => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' )   => '%PostStatus%',
							__( 'Previous date', 'wp-security-audit-log' ) => '%OldDate%'
						],
						wsaldefaults_build_links( [ 'EditorLinkPost', 'PostUrlIfPublished' ] ),
						'post',
						'modified'
					),
					array(
						2047,
						WSAL_LOW,
						__( 'User changed the parent of a page', 'wp-security-audit-log' ),
						__( 'Changed the parent of the post %PostTitle% to %NewParentName%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )         => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )       => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' )     => '%PostStatus%',
							__( 'Previous parent', 'wp-security-audit-log' ) => '%OldParentName%'
						],
						wsaldefaults_build_links( [ 'EditorLinkPost', 'PostUrlIfPublished' ] ),
						'post',
						'modified'
					),
					array(
						2048,
						WSAL_LOW,
						__( 'User changed the template of a page', 'wp-security-audit-log' ),
						__( 'Changed the template of the post %PostTitle% to %NewTemplate%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )           => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )         => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' )       => '%PostStatus%',
							__( 'Previous template', 'wp-security-audit-log' ) => '%OldTemplate%'
						],
						wsaldefaults_build_links( [ 'EditorLinkPost', 'PostUrlIfPublished' ] ),
						'post',
						'modified'
					),
					array(
						2049,
						WSAL_INFORMATIONAL,
						__( 'User set a post as sticky', 'wp-security-audit-log' ),
						__( 'Set the post %PostTitle% as sticky.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%'
						],
						wsaldefaults_build_links( [ 'EditorLinkPost', 'PostUrlIfPublished' ] ),
						'post',
						'modified'
					),
					array(
						2050,
						WSAL_INFORMATIONAL,
						__( 'User removed post from sticky', 'wp-security-audit-log' ),
						__( 'Removed the post %PostTitle% from sticky.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%'
						],
						wsaldefaults_build_links( [ 'EditorLinkPost', 'PostUrlIfPublished' ] ),
						'post',
						'modified'
					),
					array(
						2065,
						WSAL_LOW,
						__( 'User modified the content of a post', 'wp-security-audit-log' ),
						__( 'Modified the content of the post %PostTitle%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%'
						],
						wsaldefaults_build_links( [ 'RevisionLink', 'EditorLinkPost', 'PostUrlIfPublished' ] ),
						'post',
						'modified'
					),
					array(
						2073,
						WSAL_INFORMATIONAL,
						__( 'User submitted a post for review', 'wp-security-audit-log' ),
						__( 'Submitted the post %PostTitle% for review.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%'
						],
						wsaldefaults_build_links( [ 'EditorLinkPost', 'PostUrlIfPublished' ] ),
						'post',
						'modified'
					),
					array(
						2074,
						WSAL_LOW,
						__( 'User scheduled a post', 'wp-security-audit-log' ),
						__( 'Scheduled the post %PostTitle% to be published on %PublishingDate%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%'
						],
						wsaldefaults_build_links( [ 'EditorLinkPost', 'PostUrlIfPublished' ] ),
						'post',
						'modified'
					),
					array(
						2086,
						WSAL_INFORMATIONAL,
						__( 'User changed title of a post', 'wp-security-audit-log' ),
						__( 'Changed the title of the post %OldTitle% to %NewTitle%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%'
						],
						wsaldefaults_build_links( [ 'EditorLinkPost', 'PostUrlIfPublished' ] ),
						'post',
						'modified'
					),
					array(
						2100,
						WSAL_INFORMATIONAL,
						__( 'User opened a post in the editor', 'wp-security-audit-log' ),
						__( 'Opened the post %PostTitle% in the editor.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%'
						],
						wsaldefaults_build_links( [ 'EditorLinkPost', 'PostUrlIfPublished' ] ),
						'post',
						'opened'
					),
					array(
						2101,
						WSAL_INFORMATIONAL,
						__( 'User viewed a post', 'wp-security-audit-log' ),
						__( 'Viewed the post %PostTitle%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%'
						],
						wsaldefaults_build_links( [ 'PostUrl', 'EditorLinkPost' ] ),
						'post',
						'viewed'
					),
					array(
						2111,
						WSAL_LOW,
						__( 'User enabled/disabled comments in a post', 'wp-security-audit-log' ),
						__( 'Comments in the post %PostTitle%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%'
						],
						wsaldefaults_build_links( [ 'EditorLinkPost', 'PostUrlIfPublished' ] ),
						'post',
						'enabled'
					),
					array(
						2112,
						WSAL_LOW,
						__( 'User enabled/disabled trackbacks and pingbacks in a post', 'wp-security-audit-log' ),
						__( 'Pingbacks and Trackbacks in the post %PostTitle%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%'
						],
						wsaldefaults_build_links( [ 'EditorLinkPost', 'PostUrlIfPublished' ] ),
						'post',
						'enabled'
					),
					array(
						2129,
						WSAL_INFORMATIONAL,
						__( 'User updated the excerpt in a post', 'wp-security-audit-log' ),
						__( 'The excerpt of the post %PostTitle%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )                => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )              => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' )            => '%PostStatus%',
							__( 'Previous excerpt entry', 'wp-security-audit-log' ) => '%old_post_excerpt%',
							__( 'New excerpt entry', 'wp-security-audit-log' )      => '%post_excerpt%'
						],
						wsaldefaults_build_links( [ 'EditorLinkPost', 'PostUrlIfPublished' ] ),
						'post',
						'modified'
					),
					array(
						2130,
						WSAL_INFORMATIONAL,
						__( 'User updated the featured image in a post', 'wp-security-audit-log' ),
						__( 'The featured image of the post %PostTitle%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )        => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )      => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' )    => '%PostStatus%',
							__( 'Previous image', 'wp-security-audit-log' ) => '%previous_image%',
							__( 'New image', 'wp-security-audit-log' )      => '%new_image%'
						],
						wsaldefaults_build_links( [ 'EditorLinkPost', 'PostUrlIfPublished' ] ),
						'post',
						'modified'
					),
				),

				__( 'Tags', 'wp-security-audit-log' ) => array(
					array(
						2119,
						WSAL_INFORMATIONAL,
						__( 'User added post tag', 'wp-security-audit-log' ),
						__( 'Added tag(s) to the post %PostTitle%.', 'wp-security-audit-log' ),
						[
							__( 'ID', 'wp-security-audit-log' )           => '%PostID%',
							__( 'Type', 'wp-security-audit-log' )         => '%PostType%',
							__( 'Status', 'wp-security-audit-log' )       => '%PostStatus%',
							__( 'Added tag(s)', 'wp-security-audit-log' ) => '%tag%'
						],
						wsaldefaults_build_links( [ 'EditorLinkPost' ] ),
						'post',
						'modified'
					),
					array(
						2120,
						WSAL_INFORMATIONAL,
						__( 'User removed post tag', 'wp-security-audit-log' ),
						__( 'Removed tag(s) from the post %PostTitle%.', 'wp-security-audit-log' ),
						[
							__( 'ID', 'wp-security-audit-log' )             => '%PostID%',
							__( 'Type', 'wp-security-audit-log' )           => '%PostType%',
							__( 'Status', 'wp-security-audit-log' )         => '%PostStatus%',
							__( 'Removed tag(s)', 'wp-security-audit-log' ) => '%tag%'
						],
						wsaldefaults_build_links( [ 'EditorLinkPost', 'PostUrlIfPublished' ] ),
						'post',
						'modified'
					),
					array(
						2121,
						WSAL_INFORMATIONAL,
						__( 'User created new tag', 'wp-security-audit-log' ),
						__( 'Created the tag %TagName%.', 'wp-security-audit-log' ),
						[
							__( 'Slug', 'wp-security-audit-log' ) => 'Slug'
						],
						wsaldefaults_build_links( [ 'TagLink' ] ),
						'tag',
						'created'
					),
					array(
						2122,
						WSAL_LOW,
						__( 'User deleted tag', 'wp-security-audit-log' ),
						__( 'Deleted the tag %TagName%.', 'wp-security-audit-log' ),
						[
							__( 'Slug', 'wp-security-audit-log' ) => 'Slug'
						],
						[],
						'tag',
						'deleted'
					),
					array(
						2123,
						WSAL_INFORMATIONAL,
						__( 'Renamed the tag %old_name% to %new_name%.', 'wp-security-audit-log' ),
						'',
						[
							__( 'Slug', 'wp-security-audit-log' ) => '%Slug%'
						],
						wsaldefaults_build_links( [ 'TagLink' ] ),
						'tag',
						'renamed'
					),
					array(
						2124,
						WSAL_INFORMATIONAL,
						__( 'User changed tag slug', 'wp-security-audit-log' ),
						__( 'Changed the slug of the tag %tag% to %new_slug%.', 'wp-security-audit-log' ),
						[
							__( 'Previous slug', 'wp-security-audit-log' ) => '%old_slug%'
						],
						wsaldefaults_build_links( [ 'TagLink' ] ),
						'tag',
						'modified'
					),
					array(
						2125,
						WSAL_INFORMATIONAL,
						__( 'User changed tag description', 'wp-security-audit-log' ),
						__( 'Changed the description of the tag %tag%.', 'wp-security-audit-log' ),
						[
							__( 'Slug', 'wp-security-audit-log' )                 => '%Slug%',
							__( 'Previous description', 'wp-security-audit-log' ) => '%old_desc%',
							__( 'New description', 'wp-security-audit-log' )      => '%new_desc%'
						],
						wsaldefaults_build_links( [ 'TagLink' ] ),
						'tag',
						'modified'
					),
				),

				__( 'Categories', 'wp-security-audit-log' ) => array(
					array(
						2016,
						WSAL_LOW,
						__( 'User changed post category', 'wp-security-audit-log' ),
						__( 'Changed the category(ies) of the post %PostTitle% to %NewCategories%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )                => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )              => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' )            => '%PostStatus%',
							__( 'Previous category(ies)', 'wp-security-audit-log' ) => '%OldCategories%'
						],
						wsaldefaults_build_links( [ 'EditorLinkPost', 'PostUrlIfPublished' ] ),
						'post',
						'modified'
					),
					array(
						2023,
						WSAL_MEDIUM,
						__( 'User created new category', 'wp-security-audit-log' ),
						__( 'Created the category %CategoryName%.', 'wp-security-audit-log' ),
						[
							__( 'Slug', 'wp-security-audit-log' ) => 'Slug'
						],
						wsaldefaults_build_links( [ 'CategoryLink' ] ),
						'category',
						'created'
					),
					array(
						2024,
						WSAL_MEDIUM,
						__( 'User deleted category', 'wp-security-audit-log' ),
						__( 'Deleted the category %CategoryName%.', 'wp-security-audit-log' ),
						[
							__( 'Slug', 'wp-security-audit-log' ) => 'Slug'
						],
						[],
						'category',
						'deleted'
					),
					array(
						2052,
						WSAL_LOW,
						__( 'Changed the parent of a category', 'wp-security-audit-log' ),
						__( 'Changed the parent of the category %CategoryName% to %NewParent%.', 'wp-security-audit-log' ),
						[
							__( 'Slug', 'wp-security-audit-log' )            => '%Slug%',
							__( 'Previous parent', 'wp-security-audit-log' ) => '%OldParent%'
						],
						wsaldefaults_build_links( [ 'CategoryLink' ] ),
						'category',
						'modified'
					),
					array(
						2127,
						WSAL_LOW,
						__( 'User changed category name', 'wp-security-audit-log' ),
						__( 'Renamed the category %old_name% to %new_name%.', 'wp-security-audit-log' ),
						[
							__( 'Slug', 'wp-security-audit-log' )          => '%slug%'
						],
						wsaldefaults_build_links( [ 'cat_link' ] ),
						'category',
						'renamed'
					),
					array(
						2128,
						WSAL_LOW,
						__( 'User changed category slug', 'wp-security-audit-log' ),
						__( 'Changed the slug of the category %CategoryName% to %new_slug%.', 'wp-security-audit-log' ),
						[
							__( 'Previous slug', 'wp-security-audit-log' ) => '%old_slug%'
						],
						wsaldefaults_build_links( [ 'cat_link' ] ),
						'category',
						'modified'
					),
				),

				__( 'Custom Fields', 'wp-security-audit-log' ) => array(
					array(
						2053,
						WSAL_LOW,
						__( 'User created a custom field for a post', 'wp-security-audit-log' ),
						__( 'Created the new custom field %MetaKey% in the post %PostTitle%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )            => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )          => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' )        => '%PostStatus%',
							__( 'Custom field value', 'wp-security-audit-log' ) => '%MetaValue%'
						],
						wsaldefaults_build_links( [ 'EditorLinkPost', 'MetaLink', 'PostUrlIfPublished' ] ),
						'post',
						'modified'
					),
					array(
						2054,
						WSAL_LOW,
						__( 'User updated a custom field value for a post', 'wp-security-audit-log' ),
						__( 'Modified the value of the custom field %MetaKey% in the post %PostTitle%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )                     => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )                   => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' )                 => '%PostStatus%',
							__( 'Previous custom field value', 'wp-security-audit-log' ) => '%MetaValueOld%',
							__( 'New custom field value', 'wp-security-audit-log' )      => '%MetaValueNew%'
						],
						wsaldefaults_build_links( [ 'EditorLinkPost', 'MetaLink', 'PostUrlIfPublished' ] ),
						'custom-field',
						'modified'
					),
					array(
						2055,
						WSAL_MEDIUM,
						__( 'User deleted a custom field from a post', 'wp-security-audit-log' ),
						__( 'Deleted the custom field %MetaKey% from the post %PostTitle%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%'
						],
						wsaldefaults_build_links( [ 'EditorLinkPost', 'PostUrlIfPublished' ] ),
						'custom-field',
						'deleted'
					),
					array(
						2062,
						WSAL_LOW,
						__( 'User updated a custom field name for a post', 'wp-security-audit-log' ),
						__( 'Renamed the custom field %MetaKeyOld% on post %PostTitle% to %MetaKeNew%.', 'wp-security-audit-log' ),
						[
							__( 'Post', 'wp-security-audit-log' )                       => '%PostTitle%',
							__( 'Post ID', 'wp-security-audit-log' )                    => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )                  => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' )                => '%PostStatus%'
						],
						wsaldefaults_build_links( [ 'EditorLinkPost', 'PostUrlIfPublished' ] ),
						'custom-field',
						'renamed'
					),
				),

				__( 'Custom Fields (ACF)', 'wp-security-audit-log' ) => array(
					array(
						2131,
						WSAL_LOW,
						__( 'User added relationship to a custom field value for a post', 'wp-security-audit-log' ),
						__( 'Added relationships to the custom field %MetaKey% in the post %PostTitle%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )           => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )         => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' )       => '%PostStatus%',
							__( 'New relationships', 'wp-security-audit-log' ) => '%Relationships%'
						],
						wsaldefaults_build_links( [ 'EditorLinkPost', 'MetaLink' ] ),
						'custom-field',
						'modified'
					),
					array(
						2132,
						WSAL_LOW,
						__( 'User removed relationship from a custom field value for a post', 'wp-security-audit-log' ),
						__( 'Removed relationships from the custom field %MetaKey% in the post %PostTitle%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )               => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )             => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' )           => '%PostStatus%',
							__( 'Removed relationships', 'wp-security-audit-log' ) => '%Relationships%'
						],
						wsaldefaults_build_links( [ 'EditorLinkPost', 'MetaLink' ] ),
						'custom-field',
						'modified'
					),
				),

				/**
				 * Alerts: Comments
				 */
				__( 'Comments', 'wp-security-audit-log' )            => array(
					array(
						2090,
						WSAL_INFORMATIONAL,
						__( 'User approved a comment', 'wp-security-audit-log' ),
						__( 'Approved the comment posted by %Author% on the post %PostTitle%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
							__( 'Comment ID', 'wp-security-audit-log' )  => '%CommentID%'
						],
						wsaldefaults_build_links( [ 'CommentLink', 'PostUrlIfPublished' ] ),
						'comment',
						'approved'
					),
					array(
						2091,
						WSAL_INFORMATIONAL,
						__( 'User unapproved a comment', 'wp-security-audit-log' ),
						__( 'Unapproved the comment posted by %Author% on the post %PostTitle%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
							__( 'Comment ID', 'wp-security-audit-log' )  => '%CommentID%'
						],
						wsaldefaults_build_links( [ 'CommentLink', 'PostUrlIfPublished' ] ),
						'comment',
						'unapproved'
					),
					array(
						2092,
						WSAL_INFORMATIONAL,
						__( 'User replied to a comment', 'wp-security-audit-log' ),
						__( 'Replied to the comment posted by %Author% on the post %PostTitle%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
							__( 'Comment ID', 'wp-security-audit-log' )  => '%CommentID%'
						],
						wsaldefaults_build_links( [ 'CommentLink', 'PostUrlIfPublished' ] ),
						'comment',
						'created'
					),
					array(
						2093,
						WSAL_LOW,
						__( 'User edited a comment', 'wp-security-audit-log' ),
						__( 'Edited the comment posted by %Author% on the post %PostTitle%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
							__( 'Comment ID', 'wp-security-audit-log' )  => '%CommentID%'
						],
						wsaldefaults_build_links( [ 'CommentLink', 'PostUrlIfPublished' ] ),
						'comment',
						'modified'
					),
					array(
						2094,
						WSAL_INFORMATIONAL,
						__( 'User marked a comment as Spam', 'wp-security-audit-log' ),
						__( 'Marked the comment posted by %Author% on the post %PostTitle% as spam.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
							__( 'Comment ID', 'wp-security-audit-log' )  => '%CommentID%'
						],
						wsaldefaults_build_links( [ 'CommentLink', 'PostUrlIfPublished' ] ),
						'comment',
						'unapproved'
					),
					array(
						2095,
						WSAL_LOW,
						__( 'User marked a comment as Not Spam', 'wp-security-audit-log' ),
						__( 'Marked the comment posted by %Author% on the post %PostTitle% as not spam.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
							__( 'Comment ID', 'wp-security-audit-log' )  => '%CommentID%'
						],
						wsaldefaults_build_links( [ 'CommentLink', 'PostUrlIfPublished' ] ),
						'comment',
						'approved'
					),
					array(
						2096,
						WSAL_LOW,
						__( 'User moved a comment to trash', 'wp-security-audit-log' ),
						__( 'Moved the comment posted by %Author% on the post %PostTitle% to trash.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
							__( 'Comment ID', 'wp-security-audit-log' )  => '%CommentID%'
						],
						wsaldefaults_build_links( [ 'CommentLink', 'PostUrlIfPublished' ] ),
						'comment',
						'deleted'
					),
					array(
						2097,
						WSAL_INFORMATIONAL,
						__( 'User restored a comment from the trash', 'wp-security-audit-log' ),
						__( 'Restored the comment posted by %Author% on the post %PostTitle% from trash.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
							__( 'Comment ID', 'wp-security-audit-log' )  => '%CommentID%'
						],
						wsaldefaults_build_links( [ 'CommentLink', 'PostUrlIfPublished' ] ),
						'comment',
						'restored'
					),
					array(
						2098,
						WSAL_LOW,
						__( 'User permanently deleted a comment', 'wp-security-audit-log' ),
						__( 'Permanently deleted the comment posted by %Author% on the post %PostTitle%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
							__( 'Comment ID', 'wp-security-audit-log' )  => '%CommentID%'
						],
						wsaldefaults_build_links( [ 'PostUrlIfPublished' ] ),
						'comment',
						'deleted'
					),
					array(
						2099,
						WSAL_INFORMATIONAL,
						__( 'User posted a comment', 'wp-security-audit-log' ),
						__( 'Posted a comment on the post %PostTitle%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
							__( 'Comment ID', 'wp-security-audit-log' )  => '%CommentID%'
						],
						wsaldefaults_build_links( [ 'CommentLink', 'PostUrlIfPublished' ] ),
						'comment',
						'created'
					),
					/**
					 * IMPORTANT: This alert is deprecated but should not be
					 * removed from the definitions for backwards compatibility.
					 */
					array(
						2126,
						WSAL_INFORMATIONAL,
						__( 'Visitor posted a comment', 'wp-security-audit-log' ),
						__( 'Posted a comment on the post %PostTitle%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
							__( 'Comment ID', 'wp-security-audit-log' )  => '%CommentID%'
						],
						wsaldefaults_build_links( [ 'CommentLink', 'PostUrlIfPublished' ] ),
						'comment',
						'created'
					),
				),

				/**
				 * Alerts: Widgets
				 */
				__( 'Widgets', 'wp-security-audit-log' )             => array(
					array(
						2042,
						WSAL_MEDIUM,
						__( 'User added a new widget', 'wp-security-audit-log' ),
						__( 'Added a new %WidgetName% widget in %Sidebar%.', 'wp-security-audit-log' ),
						[],
						[],
						'widget',
						'added'
					),
					array(
						2043,
						WSAL_HIGH,
						__( 'User modified a widget', 'wp-security-audit-log' ),
						__( 'Modified the %WidgetName% widget in %Sidebar%.', 'wp-security-audit-log' ),
						[],
						[],
						'widget',
						'modified'
					),
					array(
						2044,
						WSAL_MEDIUM,
						__( 'User deleted widget', 'wp-security-audit-log' ),
						__( 'Deleted the %WidgetName% widget from %Sidebar%.', 'wp-security-audit-log' ),
						[],
						[],
						'widget',
						'deleted'
					),
					array(
						2045,
						WSAL_LOW,
						__( 'User moved widget', 'wp-security-audit-log' ),
						__( 'Moved the %WidgetName% widget.', 'wp-security-audit-log' ),
						[
							__( 'From', 'wp-security-audit-log' ) => '%OldSidebar%',
							__( 'To', 'wp-security-audit-log' )   => '%NewSidebar%'
						],
						[],
						'widget',
						'modified'
					),
					array(
						2071,
						WSAL_LOW,
						__( 'User changed widget position', 'wp-security-audit-log' ),
						__( 'Changed the position of the %WidgetName% widget in %Sidebar%.', 'wp-security-audit-log' ),
						[],
						[],
						'widget',
						'modified'
					),
				),

				/**
				 * Alerts: Menus
				 */
				__( 'Menus', 'wp-security-audit-log' )               => array(
					array(
						2078,
						WSAL_LOW,
						__( 'User created new menu', 'wp-security-audit-log' ),
						__( 'New menu called %MenuName%.', 'wp-security-audit-log' ),
						[],
						wsaldefaults_build_links( [ 'MenuUrl' ] ),
						'menu',
						'created'
					),
					array(
						2079,
						WSAL_LOW,
						__( 'User added content to a menu', 'wp-security-audit-log' ),
						__( 'Added the item %ContentName% to the menu %MenuName%.', 'wp-security-audit-log' ),
						[
							__( 'Item type', 'wp-security-audit-log' ) => '%ContentType%'
						],
						wsaldefaults_build_links( [ 'MenuUrl' ] ),
						'menu',
						'modified'
					),
					array(
						2080,
						WSAL_LOW,
						__( 'User removed content from a menu', 'wp-security-audit-log' ),
						__( 'Removed the item %ContentName% from the menu %MenuName%.', 'wp-security-audit-log' ),
						[
							__( 'Item type', 'wp-security-audit-log' ) => '%ContentType%'
						],
						wsaldefaults_build_links( [ 'MenuUrl' ] ),
						'menu',
						'modified'
					),
					array(
						2081,
						WSAL_MEDIUM,
						__( 'User deleted menu', 'wp-security-audit-log' ),
						__( 'Deleted the menu %MenuName%.', 'wp-security-audit-log' ),
						[],
						[],
						'menu',
						'deleted'
					),
					array(
						2082,
						WSAL_LOW,
						__( 'User changed menu setting', 'wp-security-audit-log' ),
						__( 'The setting %MenuSetting% in the menu %MenuName%.', 'wp-security-audit-log' ),
						[],
						wsaldefaults_build_links( [ 'MenuUrl' ] ),
						'menu',
						'enabled'
					),
					array(
						2083,
						WSAL_LOW,
						__( 'User modified content in a menu', 'wp-security-audit-log' ),
						__( 'Modified the item %ContentName% in the menu %MenuName%.', 'wp-security-audit-log' ),
						[
							__( 'Item type', 'wp-security-audit-log' ) => '%ContentType%'
						],
						wsaldefaults_build_links( [ 'MenuUrl' ] ),
						'menu',
						'modified'
					),
					array(
						2084,
						WSAL_LOW,
						__( 'User changed name of a menu', 'wp-security-audit-log' ),
						__( 'Renamed the menu %OldMenuName% to %MenuName%.', 'wp-security-audit-log' ),
						[],
						wsaldefaults_build_links( [ 'MenuUrl' ] ),
						'menu',
						'renamed'
					),
					array(
						2085,
						WSAL_LOW,
						__( 'User changed order of the objects in a menu', 'wp-security-audit-log' ),
						__( 'Changed the order of the items in the menu %MenuName%.', 'wp-security-audit-log' ),
						[],
						wsaldefaults_build_links( [ 'MenuUrl' ] ),
						'menu',
						'modified'
					),
					array(
						2089,
						WSAL_LOW,
						__( 'User moved objects as a sub-item', 'wp-security-audit-log' ),
						__( 'Moved items as sub-items in the menu %MenuName%.', 'wp-security-audit-log' ),
						[
							__( 'Moved item', 'wp-security-audit-log' )       => '%ItemName%',
							__( 'as a sub-item of', 'wp-security-audit-log' ) => '%ParentName%'
						],
						wsaldefaults_build_links( [ 'MenuUrl' ] ),
						'menu',
						'modified'
					),
				),

				/**
				 * Alerts: Custom Post Types
				 *
				 * IMPORTANT: These alerts should not be removed from here
				 * for backwards compatibility.
				 *
				 * @deprecated 3.1.0
				 */
				__( 'Custom Post Types', 'wp-security-audit-log' )   => array(
					array(
						2003,
						E_NOTICE,
						__( 'User modified a draft blog post', 'wp-security-audit-log' ),
						__( 'Modified the draft post with the %PostTitle%. %EditorLinkPost%.', 'wp-security-audit-log' )
					),
					array(
						2029,
						E_NOTICE,
						__( 'User created a new post with custom post type and saved it as draft', 'wp-security-audit-log' ),
						__( 'Created a new custom post called %PostTitle% of type %PostType%. %EditorLinkPost%.', 'wp-security-audit-log' )
					),
					array(
						2030,
						E_NOTICE,
						__( 'User published a post with custom post type', 'wp-security-audit-log' ),
						__( 'Published a custom post %PostTitle% of type %PostType%. Post URL is %PostUrl%. %EditorLinkPost%.', 'wp-security-audit-log' )
					),
					array(
						2031,
						E_NOTICE,
						__( 'User modified a post with custom post type', 'wp-security-audit-log' ),
						__( 'Modified the custom post %PostTitle% of type %PostType%. Post URL is %PostUrl%. %EditorLinkPost%.', 'wp-security-audit-log' )
					),
					array(
						2032,
						E_NOTICE,
						__( 'User modified a draft post with custom post type', 'wp-security-audit-log' ),
						__( 'Modified the draft custom post %PostTitle% of type is %PostType%. %EditorLinkPost%.', 'wp-security-audit-log' )
					),
					array(
						2033,
						E_WARNING,
						__( 'User permanently deleted post with custom post type', 'wp-security-audit-log' ),
						__( 'Permanently Deleted the custom post %PostTitle% of type %PostType%.', 'wp-security-audit-log' )
					),
					array(
						2034,
						E_WARNING,
						__( 'User moved post with custom post type to trash', 'wp-security-audit-log' ),
						__( 'Moved the custom post %PostTitle% of type %PostType% to trash. Post URL was %PostUrl%.', 'wp-security-audit-log' )
					),
					array(
						2035,
						E_CRITICAL,
						__( 'User restored post with custom post type from trash', 'wp-security-audit-log' ),
						__( 'The custom post %PostTitle% of type %PostType% has been restored from trash. %EditorLinkPost%.', 'wp-security-audit-log' )
					),
					array(
						2036,
						E_NOTICE,
						__( 'User changed the category of a post with custom post type', 'wp-security-audit-log' ),
						__( 'Changed the category(ies) of the custom post %PostTitle% of type %PostType% from %OldCategories% to %NewCategories%. %EditorLinkPost%.', 'wp-security-audit-log' )
					),
					array(
						2037,
						E_NOTICE,
						__( 'User changed the URL of a post with custom post type', 'wp-security-audit-log' ),
						__( 'Changed the URL of the custom post %PostTitle% of type %PostType% from %OldUrl% to %NewUrl%. %EditorLinkPost%.', 'wp-security-audit-log' )
					),
					array(
						2038,
						E_NOTICE,
						__( 'User changed the author or post with custom post type', 'wp-security-audit-log' ),
						__( 'Changed the author of custom post %PostTitle% of type %PostType% from %OldAuthor% to %NewAuthor%. %EditorLinkPost%.', 'wp-security-audit-log' )
					),
					array(
						2039,
						E_NOTICE,
						__( 'User changed the status of post with custom post type', 'wp-security-audit-log' ),
						__( 'Changed the status of custom post %PostTitle% of type %PostType% from %OldStatus% to %NewStatus%. %EditorLinkPost%.', 'wp-security-audit-log' )
					),
					array(
						2040,
						E_WARNING,
						__( 'User changed the visibility of a post with custom post type', 'wp-security-audit-log' ),
						__( 'Changed the visibility of the custom post %PostTitle% of type %PostType% from %OldVisibility% to %NewVisibility%. %EditorLinkPost%.', 'wp-security-audit-log' )
					),
					array(
						2041,
						E_NOTICE,
						__( 'User changed the date of post with custom post type', 'wp-security-audit-log' ),
						__( 'Changed the date of the custom post %PostTitle% of type %PostType% from %OldDate% to %NewDate%. %EditorLinkPost%.', 'wp-security-audit-log' )
					),
					array(
						2056,
						E_CRITICAL,
						__( 'User created a custom field for a custom post type', 'wp-security-audit-log' ),
						__( 'Created a new custom field %MetaKey% with value %MetaValue% in custom post %PostTitle% of type %PostType%. %EditorLinkPost%.<br>%MetaLink%.', 'wp-security-audit-log' )
					),
					array(
						2057,
						E_CRITICAL,
						__( 'User updated a custom field for a custom post type', 'wp-security-audit-log' ),
						__( 'Modified the value of the custom field %MetaKey% from %MetaValueOld% to %MetaValueNew% in custom post %PostTitle% of type %PostType% %EditorLinkPost%.<br>%MetaLink%.', 'wp-security-audit-log' )
					),
					array(
						2058,
						E_CRITICAL,
						__( 'User deleted a custom field from a custom post type', 'wp-security-audit-log' ),
						__( 'Deleted the custom field %MetaKey% with id %MetaID% from custom post %PostTitle% of type %PostType% %EditorLinkPost%.<br>%MetaLink%.', 'wp-security-audit-log' )
					),
					array(
						2063,
						E_CRITICAL,
						__( 'User updated a custom field name for a custom post type', 'wp-security-audit-log' ),
						__( 'Changed the custom field name from %MetaKeyOld% to %MetaKeyNew% in custom post %PostTitle% of type %PostType% %EditorLinkPost%.<br>%MetaLink%.', 'wp-security-audit-log' )
					),
					array(
						2067,
						E_WARNING,
						__( 'User modified content for a published custom post type', 'wp-security-audit-log' ),
						__( 'Modified the content of the published custom post type %PostTitle%. Post URL is %PostUrl%. %EditorLinkPost%.', 'wp-security-audit-log' )
					),
					array(
						2068,
						E_NOTICE,
						__( 'User modified content for a draft post', 'wp-security-audit-log' ),
						__( 'Modified the content of the draft post %PostTitle%.%RevisionLink% %EditorLinkPost%.', 'wp-security-audit-log' )
					),
					array(
						2070,
						E_NOTICE,
						__( 'User modified content for a draft custom post type', 'wp-security-audit-log' ),
						__( 'Modified the content of the draft custom post type %PostTitle%.%EditorLinkPost%.', 'wp-security-audit-log' )
					),
					array(
						2072,
						E_NOTICE,
						__( 'User modified content of a post', 'wp-security-audit-log' ),
						__( 'Modified the content of post %PostTitle% which is submitted for review.%RevisionLink% %EditorLinkPost%.', 'wp-security-audit-log' )
					),
					array(
						2076,
						E_NOTICE,
						__( 'User scheduled a custom post type', 'wp-security-audit-log' ),
						__( 'Scheduled the custom post type %PostTitle% to be published %PublishingDate%. %EditorLinkPost%.', 'wp-security-audit-log' )
					),
					array(
						2088,
						E_NOTICE,
						__( 'User changed title of a custom post type', 'wp-security-audit-log' ),
						__( 'Changed the title of the custom post %OldTitle% to %NewTitle%. %EditorLinkPost%.', 'wp-security-audit-log' )
					),
					array(
						2104,
						E_NOTICE,
						__( 'User opened a custom post type in the editor', 'wp-security-audit-log' ),
						__( 'Opened the custom post %PostTitle% of type %PostType% in the editor. View the post: %EditorLinkPost%.', 'wp-security-audit-log' )
					),
					array(
						2105,
						E_NOTICE,
						__( 'User viewed a custom post type', 'wp-security-audit-log' ),
						__( 'Viewed the custom post %PostTitle% of type %PostType%. View the post: %PostUrl%.', 'wp-security-audit-log' )
					),
					array(
						5021,
						E_CRITICAL,
						__( 'A plugin created a custom post', 'wp-security-audit-log' ),
						__( 'A plugin automatically created the following custom post: %PostTitle%.', 'wp-security-audit-log' )
					),
					array(
						5027,
						E_CRITICAL,
						__( 'A plugin deleted a custom post', 'wp-security-audit-log' ),
						__( 'A plugin automatically deleted the following custom post: %PostTitle%.', 'wp-security-audit-log' )
					),
					array(
						2108,
						E_NOTICE,
						__( 'A plugin modified a custom post', 'wp-security-audit-log' ),
						__( 'Plugin modified the custom post %PostTitle%. View the post: %EditorLinkPost%.', 'wp-security-audit-log' )
					),
				),

				/**
				 * Alerts: Pages
				 *
				 * IMPORTANT: These alerts should not be removed from here
				 * for backwards compatibility.
				 *
				 * @deprecated 3.1.0
				 */
				__( 'Pages', 'wp-security-audit-log' )               => array(
					array(
						2004,
						E_NOTICE,
						__( 'User created a new WordPress page and saved it as draft', 'wp-security-audit-log' ),
						__( 'Created a new page called %PostTitle% and saved it as draft. %EditorLinkPage%.', 'wp-security-audit-log' )
					),
					array(
						2005,
						E_NOTICE,
						__( 'User published a WordPress page', 'wp-security-audit-log' ),
						__( 'Published a page called %PostTitle%. Page URL is %PostUrl%. %EditorLinkPage%.', 'wp-security-audit-log' )
					),
					array(
						2006,
						E_NOTICE,
						__( 'User modified a published WordPress page', 'wp-security-audit-log' ),
						__( 'Modified the published page %PostTitle%. Page URL is %PostUrl%. %EditorLinkPage%.', 'wp-security-audit-log' )
					),
					array(
						2007,
						E_NOTICE,
						__( 'User modified a draft WordPress page', 'wp-security-audit-log' ),
						__( 'Modified the draft page %PostTitle%. Page ID is %PostID%. %EditorLinkPage%.', 'wp-security-audit-log' )
					),
					array(
						2009,
						E_WARNING,
						__( 'User permanently deleted a page from the trash', 'wp-security-audit-log' ),
						__( 'Permanently deleted the page %PostTitle%.', 'wp-security-audit-log' )
					),
					array(
						2013,
						E_WARNING,
						__( 'User moved WordPress page to the trash', 'wp-security-audit-log' ),
						__( 'Moved the page %PostTitle% to trash. Page URL was %PostUrl%.', 'wp-security-audit-log' )
					),
					array(
						2015,
						E_CRITICAL,
						__( 'User restored a WordPress page from trash', 'wp-security-audit-log' ),
						__( 'Page %PostTitle% has been restored from trash. %EditorLinkPage%.', 'wp-security-audit-log' )
					),
					array(
						2018,
						E_NOTICE,
						__( 'User changed page URL', 'wp-security-audit-log' ),
						__( 'Changed the URL of the page %PostTitle% from %OldUrl% to %NewUrl%. %EditorLinkPage%.', 'wp-security-audit-log' )
					),
					array(
						2020,
						E_NOTICE,
						__( 'User changed page author', 'wp-security-audit-log' ),
						__( 'Changed the author of the page %PostTitle% from %OldAuthor% to %NewAuthor%. %EditorLinkPage%.', 'wp-security-audit-log' )
					),
					array(
						2022,
						E_NOTICE,
						__( 'User changed page status', 'wp-security-audit-log' ),
						__( 'Changed the status of the page %PostTitle% from %OldStatus% to %NewStatus%. %EditorLinkPage%.', 'wp-security-audit-log' )
					),
					array(
						2026,
						E_WARNING,
						__( 'User changed the visibility of a page post', 'wp-security-audit-log' ),
						__( 'Changed the visibility of the page %PostTitle% from %OldVisibility% to %NewVisibility%. %EditorLinkPage%.', 'wp-security-audit-log' )
					),
					array(
						2028,
						E_NOTICE,
						__( 'User changed the date of a page post', 'wp-security-audit-log' ),
						__( 'Changed the date of the page %PostTitle% from %OldDate% to %NewDate%. %EditorLinkPage%.', 'wp-security-audit-log' )
					),
					array(
						2059,
						E_CRITICAL,
						__( 'User created a custom field for a page', 'wp-security-audit-log' ),
						__( 'Created a new custom field called %MetaKey% with value %MetaValue% in the page %PostTitle% %EditorLinkPage%.<br>%MetaLink%.', 'wp-security-audit-log' )
					),
					array(
						2060,
						E_CRITICAL,
						__( 'User updated a custom field value for a page', 'wp-security-audit-log' ),
						__( 'Modified the value of the custom field %MetaKey% from %MetaValueOld% to %MetaValueNew% in the page %PostTitle% %EditorLinkPage%.<br>%MetaLink%.', 'wp-security-audit-log' )
					),
					array(
						2061,
						E_CRITICAL,
						__( 'User deleted a custom field from a page', 'wp-security-audit-log' ),
						__( 'Deleted the custom field %MetaKey% with id %MetaID% from page %PostTitle% %EditorLinkPage%<br>%MetaLink%.', 'wp-security-audit-log' )
					),
					array(
						2064,
						E_CRITICAL,
						__( 'User updated a custom field name for a page', 'wp-security-audit-log' ),
						__( 'Changed the custom field name from %MetaKeyOld% to %MetaKeyNew% in the page %PostTitle% %EditorLinkPage%.<br>%MetaLink%.', 'wp-security-audit-log' )
					),
					array(
						2066,
						E_WARNING,
						__( 'User modified content for a published page', 'wp-security-audit-log' ),
						__( 'Modified the content of the published page %PostTitle%. Page URL is %PostUrl%. %RevisionLink% %EditorLinkPage%.', 'wp-security-audit-log' )
					),
					array(
						2069,
						E_NOTICE,
						__( 'User modified content for a draft page', 'wp-security-audit-log' ),
						__( 'Modified the content of draft page %PostTitle%.%RevisionLink% %EditorLinkPage%.', 'wp-security-audit-log' )
					),
					array(
						2075,
						E_NOTICE,
						__( 'User scheduled a page', 'wp-security-audit-log' ),
						__( 'Scheduled the page %PostTitle% to be published %PublishingDate%. %EditorLinkPage%.', 'wp-security-audit-log' )
					),
					array(
						2087,
						E_NOTICE,
						__( 'User changed title of a page', 'wp-security-audit-log' ),
						__( 'Changed the title of the page %OldTitle% to %NewTitle%. %EditorLinkPage%.', 'wp-security-audit-log' )
					),
					array(
						2102,
						E_NOTICE,
						__( 'User opened a page in the editor', 'wp-security-audit-log' ),
						__( 'Opened the page %PostTitle% in the editor. View the page: %EditorLinkPage%.', 'wp-security-audit-log' )
					),
					array(
						2103,
						E_NOTICE,
						__( 'User viewed a page', 'wp-security-audit-log' ),
						__( 'Viewed the page %PostTitle%. View the page: %PostUrl%.', 'wp-security-audit-log' )
					),
					array(
						2113,
						E_NOTICE,
						__( 'User disabled Comments/Trackbacks and Pingbacks on a draft post', 'wp-security-audit-log' ),
						__( 'Disabled %Type% on the draft post %PostTitle%. View the post: %PostUrl%.', 'wp-security-audit-log' )
					),
					array(
						2114,
						E_NOTICE,
						__( 'User enabled Comments/Trackbacks and Pingbacks on a draft post', 'wp-security-audit-log' ),
						__( 'Enabled %Type% on the draft post %PostTitle%. View the post: %PostUrl%.', 'wp-security-audit-log' )
					),
					array(
						2115,
						E_NOTICE,
						__( 'User disabled Comments/Trackbacks and Pingbacks on a published page', 'wp-security-audit-log' ),
						__( 'Disabled %Type% on the published page %PostTitle%. View the page: %PostUrl%.', 'wp-security-audit-log' )
					),
					array(
						2116,
						E_NOTICE,
						__( 'User enabled Comments/Trackbacks and Pingbacks on a published page', 'wp-security-audit-log' ),
						__( 'Enabled %Type% on the published page %PostTitle%. View the page: %PostUrl%.', 'wp-security-audit-log' )
					),
					array(
						2117,
						E_NOTICE,
						__( 'User disabled Comments/Trackbacks and Pingbacks on a draft page', 'wp-security-audit-log' ),
						__( 'Disabled %Type% on the draft page %PostTitle%. View the page: %PostUrl%.', 'wp-security-audit-log' )
					),
					array(
						2118,
						E_NOTICE,
						__( 'User enabled Comments/Trackbacks and Pingbacks on a draft page', 'wp-security-audit-log' ),
						__( 'Enabled %Type% on the draft page %PostTitle%. View the page: %PostUrl%.', 'wp-security-audit-log' )
					),
					array(
						5020,
						E_CRITICAL,
						__( 'A plugin created a page', 'wp-security-audit-log' ),
						__( 'A plugin automatically created the following page: %PostTitle%.', 'wp-security-audit-log' )
					),
					array(
						5026,
						E_CRITICAL,
						__( 'A plugin deleted a page', 'wp-security-audit-log' ),
						__( 'A plugin automatically deleted the following page: %PostTitle%.', 'wp-security-audit-log' )
					),
					array(
						2107,
						E_NOTICE,
						__( 'A plugin modified a page', 'wp-security-audit-log' ),
						__( 'Plugin modified the page %PostTitle%. View the page: %EditorLinkPage%.', 'wp-security-audit-log' )
					),
				),
			),

			__( 'User Accounts', 'wp-security-audit-log' ) => array(
				__( 'User Profiles', 'wp-security-audit-log' ) => array(
					array(
						4000,
						WSAL_CRITICAL,
						__( 'New user was created on WordPress', 'wp-security-audit-log' ),
						__( 'A new user %NewUserData->Username% is created via registration.', 'wp-security-audit-log' ),
						[
							__( 'User', 'wp-security-audit-log' ) => '%NewUserData->Username%'
						],
						wsaldefaults_build_links( [ 'EditUserLink' ] ),
						'user',
						'created'
					),
					array(
						4001,
						WSAL_CRITICAL,
						__( 'User created another WordPress user', 'wp-security-audit-log' ),
						__( 'Created the new user: %NewUserData->Username%.', 'wp-security-audit-log' ),
						[
							__( 'Role', 'wp-security-audit-log' )       => '%NewUserData->Roles%',
							__( 'First name', 'wp-security-audit-log' ) => '%NewUserData->FirstName%',
							__( 'Last name', 'wp-security-audit-log' )  => '%NewUserData->LastName%'
						],
						wsaldefaults_build_links( [ 'EditUserLink' ] ),
						'user',
						'created'
					),
					array(
						4002,
						WSAL_CRITICAL,
						__( 'The role of a user was changed by another WordPress user', 'wp-security-audit-log' ),
						__( 'Changed the role of user %TargetUsername% to %NewRole%.', 'wp-security-audit-log' ),
						[
							__( 'Previous role', 'wp-security-audit-log' ) => '%OldRole%',
							__( 'First name', 'wp-security-audit-log' )    => '%FirstName%',
							__( 'Last name', 'wp-security-audit-log' )     => '%LastName%'
						],
						wsaldefaults_build_links( [ 'EditUserLink' ] ),
						'user',
						'modified'
					),
					array(
						4003,
						WSAL_HIGH,
						__( 'User has changed his or her password', 'wp-security-audit-log' ),
						__( 'Changed the password.', 'wp-security-audit-log' ),
						[
							__( 'Role', 'wp-security-audit-log' )       => '%TargetUserData->Roles%',
							__( 'First name', 'wp-security-audit-log' ) => '%TargetUserData->FirstName%',
							__( 'Last name', 'wp-security-audit-log' )  => '%TargetUserData->LastName%'
						],
						wsaldefaults_build_links( [ 'EditUserLink' ] ),
						'user',
						'modified'
					),
					array(
						4004,
						WSAL_HIGH,
						__( 'User changed another user\'s password', 'wp-security-audit-log' ),
						__( 'Changed the password of the user %TargetUserData->Username%.', 'wp-security-audit-log' ),
						[
							__( 'Role', 'wp-security-audit-log' )       => '%TargetUserData->Roles%',
							__( 'First name', 'wp-security-audit-log' ) => '%TargetUserData->FirstName%',
							__( 'Last name', 'wp-security-audit-log' )  => '%TargetUserData->LastName%'
						],
						wsaldefaults_build_links( [ 'EditUserLink' ] ),
						'user',
						'modified'
					),
					array(
						4005,
						WSAL_MEDIUM,
						__( 'User changed his or her email address', 'wp-security-audit-log' ),
						__( 'Changed the email address to %NewEmail%.', 'wp-security-audit-log' ),
						[
							__( 'Role', 'wp-security-audit-log' )                   => '%Roles%',
							__( 'First name', 'wp-security-audit-log' )             => '%FirstName%',
							__( 'Last name', 'wp-security-audit-log' )              => '%LastName%',
							__( 'Previous email address', 'wp-security-audit-log' ) => '%OldEmail%'
						],
						wsaldefaults_build_links( [ 'EditUserLink' ] ),
						'user',
						'modified'
					),
					array(
						4006,
						WSAL_MEDIUM,
						__( 'User changed another user\'s email address', 'wp-security-audit-log' ),
						__( 'Changed the email address of the user %TargetUsername% to %NewEmail%.', 'wp-security-audit-log' ),
						[
							__( 'Role', 'wp-security-audit-log' )                   => '%Roles%',
							__( 'First name', 'wp-security-audit-log' )             => '%FirstName%',
							__( 'Last name', 'wp-security-audit-log' )              => '%LastName%',
							__( 'Previous email address', 'wp-security-audit-log' ) => '%OldEmail%'
						],
						wsaldefaults_build_links( [ 'EditUserLink' ] ),
						'user',
						'modified'
					),
					array(
						4007,
						WSAL_HIGH,
						__( 'User was deleted by another user', 'wp-security-audit-log' ),
						__( 'Deleted the user %TargetUserData->Username%.', 'wp-security-audit-log' ),
						[
							__( 'Role', 'wp-security-audit-log' )       => '%TargetUserData->Roles%',
							__( 'First name', 'wp-security-audit-log' ) => '%NewUserData->FirstName%',
							__( 'Last name', 'wp-security-audit-log' )  => '%NewUserData->LastName%'
						],
						[],
						'user',
						'deleted'
					),
					array(
						4014,
						WSAL_INFORMATIONAL,
						__( 'User opened the profile page of another user', 'wp-security-audit-log' ),
						__( 'Opened the profile page of user %TargetUsername%.', 'wp-security-audit-log' ),
						[
							__( 'Role', 'wp-security-audit-log' )       => '%Roles%',
							__( 'First name', 'wp-security-audit-log' ) => '%FirstName%',
							__( 'Last name', 'wp-security-audit-log' )  => '%LastName%'
						],
						wsaldefaults_build_links( [ 'EditUserLink' ] ),
						'user',
						'opened'
					),
					array(
						4015,
						WSAL_LOW,
						__( 'User updated a custom field value for a user', 'wp-security-audit-log' ),
						__( 'Changed the value of the custom field %custom_field_name% in the user profile %TargetUsername%.', 'wp-security-audit-log' ),
						[
							__( 'Role', 'wp-security-audit-log' )           => '%Roles%',
							__( 'First name', 'wp-security-audit-log' )     => '%FirstName%',
							__( 'Last name', 'wp-security-audit-log' )      => '%LastName%',
							__( 'Previous value', 'wp-security-audit-log' ) => '%old_value%',
							__( 'New value', 'wp-security-audit-log' )      => '%new_value%'
						],
						wsaldefaults_build_links( [ 'EditUserLink', 'MetaLink' ] ),
						'user',
						'modified'
					),
					array(
						4016,
						WSAL_LOW,
						__( 'User created a custom field value for a user', 'wp-security-audit-log' ),
						__( 'Created the custom field %custom_field_name% in the user profile %TargetUsername%.', 'wp-security-audit-log' ),
						[
							__( 'Role', 'wp-security-audit-log' )               => '%Roles%',
							__( 'First name', 'wp-security-audit-log' )         => '%FirstName%',
							__( 'Last name', 'wp-security-audit-log' )          => '%LastName%',
							__( 'Custom field value', 'wp-security-audit-log' ) => '%new_value%'
						],
						wsaldefaults_build_links( [ 'EditUserLink', 'MetaLink' ] ),
						'user',
						'modified'
					),
					array(
						4017,
						WSAL_INFORMATIONAL,
						__( 'User changed first name for a user', 'wp-security-audit-log' ),
						__( 'Changed the first name of the user %TargetUsername% to %new_firstname%.', 'wp-security-audit-log' ),
						[
							__( 'Role', 'wp-security-audit-log' )          => '%Roles%',
							__( 'Previous name', 'wp-security-audit-log' ) => '%old_firstname%',
							__( 'Last name', 'wp-security-audit-log' )     => '%LastName%'
						],
						wsaldefaults_build_links( [ 'EditUserLink' ] ),
						'user',
						'modified'
					),
					array(
						4018,
						WSAL_INFORMATIONAL,
						__( 'User changed last name for a user', 'wp-security-audit-log' ),
						__( 'Changed the last name of the user %TargetUsername% to %new_lastname%.', 'wp-security-audit-log' ),
						[
							__( 'Role', 'wp-security-audit-log' )               => '%Roles%',
							__( 'First name', 'wp-security-audit-log' )         => '%FirstName%',
							__( 'Previous last name', 'wp-security-audit-log' ) => '%old_lastname%'
						],
						wsaldefaults_build_links( [ 'EditUserLink' ] ),
						'user',
						'modified'
					),
					array(
						4019,
						WSAL_INFORMATIONAL,
						__( 'User changed nickname for a user', 'wp-security-audit-log' ),
						__( 'Changed the nickname of the user %TargetUsername% to %new_nickname%.', 'wp-security-audit-log' ),
						[
							__( 'Role', 'wp-security-audit-log' )              => '%Roles%',
							__( 'First name', 'wp-security-audit-log' )        => '%FirstName%',
							__( 'Last name', 'wp-security-audit-log' )         => '%LastName%',
							__( 'Previous nickname', 'wp-security-audit-log' ) => '%old_nickname%'
						],
						wsaldefaults_build_links( [ 'EditUserLink' ] ),
						'user',
						'modified'
					),
					array(
						4020,
						WSAL_LOW,
						__( 'User changed the display name for a user', 'wp-security-audit-log' ),
						__( 'Changed the display name of the user %TargetUsername% to %new_displayname%.', 'wp-security-audit-log' ),
						[
							__( 'Role', 'wp-security-audit-log' )                  => '%Roles%',
							__( 'First name', 'wp-security-audit-log' )            => '%FirstName%',
							__( 'Last name', 'wp-security-audit-log' )             => '%LastName%',
							__( 'Previous display name', 'wp-security-audit-log' ) => '%old_displayname%'
						],
						wsaldefaults_build_links( [ 'EditUserLink' ] ),
						'user',
						'modified'
					),

					array(
						4025,
						WSAL_CRITICAL,
						__( 'User created an application password', 'wp-security-audit-log' ),
						__( 'The application password %friendly_name%.', 'wp-security-audit-log' ),
						[
							__( 'Role', 'wp-security-audit-log' )                               => '%roles%',
							__( 'First name', 'wp-security-audit-log' )                         => '%firstname%',
							__( 'Last name', 'wp-security-audit-log' )                          => '%lastname%'
						],
						[],
						'user',
						'added'
					),
					array(
						4026,
						WSAL_CRITICAL,
						__( 'User created an application password', 'wp-security-audit-log' ),
						__( 'The application password %friendly_name% for the user %login%.', 'wp-security-audit-log' ),
						[
							__( 'Role', 'wp-security-audit-log' )                               => '%roles%',
							__( 'First name', 'wp-security-audit-log' )                         => '%firstname%',
							__( 'Last name', 'wp-security-audit-log' )                          => '%lastname%'
						],
						[],
						'user',
						'added'
					),

					array(
						4027,
						WSAL_HIGH,
						__( 'User revoked all application passwords', 'wp-security-audit-log' ),
						__( 'All application passwords.', 'wp-security-audit-log' ),
						[
							__( 'Role', 'wp-security-audit-log' )       => '%roles%',
							__( 'First name', 'wp-security-audit-log' ) => '%firstname%',
							__( 'Last name', 'wp-security-audit-log' )  => '%lastname%'
						],
						[],
						'user',
						'revoked'
					),
					array(
						4028,
						WSAL_HIGH,
						__( 'User revoked all application passwords for a user', 'wp-security-audit-log' ),
						__( 'All application passwords from the user %login%.', 'wp-security-audit-log' ),
						[
							__( 'Role', 'wp-security-audit-log' )       => '%roles%',
							__( 'First name', 'wp-security-audit-log' ) => '%firstname%',
							__( 'Last name', 'wp-security-audit-log' )  => '%lastname%'
						],
						[],
						'user',
						'revoked'
					),
					array(
						4029,
						WSAL_HIGH,
						__( 'Admin sent a password reset request to a user', 'wp-security-audit-log' ),
						__( 'Sent a password reset request to the user %login%.', 'wp-security-audit-log' ),
						[
							__( 'Role', 'wp-security-audit-log' )       => '%roles%',
							__( 'First name', 'wp-security-audit-log' ) => '%firstname%',
							__( 'Last name', 'wp-security-audit-log' )  => '%lastname%'
						],
						[],
						'user',
						'submitted'
					),
				),

				__( 'Multisite User Profiles', 'wp-security-audit-log' ) => array(
					array(
						4008,
						WSAL_CRITICAL,
						__( 'User granted Super Admin privileges', 'wp-security-audit-log' ),
						__( 'Granted Super Admin privileges to the user %TargetUsername%.', 'wp-security-audit-log' ),
						[
							__( 'Role', 'wp-security-audit-log' )       => '%Roles%',
							__( 'First name', 'wp-security-audit-log' ) => '%FirstName%',
							__( 'Last name', 'wp-security-audit-log' )  => '%LastName%'
						],
						wsaldefaults_build_links( [ 'EditUserLink' ] ),
						'user',
						'modified'
					),
					array(
						4009,
						WSAL_CRITICAL,
						__( 'User revoked from Super Admin privileges', 'wp-security-audit-log' ),
						__( 'Revoked Super Admin privileges from %TargetUsername%.', 'wp-security-audit-log' ),
						[
							__( 'Role', 'wp-security-audit-log' )       => '%Roles%',
							__( 'First name', 'wp-security-audit-log' ) => '%FirstName%',
							__( 'Last name', 'wp-security-audit-log' )  => '%LastName%'
						],
						wsaldefaults_build_links( [ 'EditUserLink' ] ),
						'user',
						'modified'
					),
					array(
						4010,
						WSAL_MEDIUM,
						__( 'Existing user added to a site', 'wp-security-audit-log' ),
						__( 'Added user %TargetUsername% to the site %SiteName%.', 'wp-security-audit-log' ),
						[
							__( 'Role', 'wp-security-audit-log' )       => '%TargetUserRole%',
							__( 'First name', 'wp-security-audit-log' ) => '%FirstName%',
							__( 'Last name', 'wp-security-audit-log' )  => '%LastName%'
						],
						wsaldefaults_build_links( [ 'EditUserLink' ] ),
						'user',
						'modified'
					),
					array(
						4011,
						WSAL_MEDIUM,
						__( 'User removed from site', 'wp-security-audit-log' ),
						__( 'Removed user %TargetUsername% from the site %SiteName%', 'wp-security-audit-log' ),
						[
							__( 'Site role', 'wp-security-audit-log' )  => '%TargetUserRole%',
							__( 'First name', 'wp-security-audit-log' ) => '%FirstName%',
							__( 'Last name', 'wp-security-audit-log' )  => '%LastName%'
						],
						wsaldefaults_build_links( [ 'EditUserLink' ] ),
						'user',
						'modified'
					),
					array(
						4012,
						WSAL_CRITICAL,
						__( 'New network user created', 'wp-security-audit-log' ),
						__( 'Created the new network user %NewUserData->Username%.', 'wp-security-audit-log' ),
						[
							__( 'First name', 'wp-security-audit-log' ) => '%NewUserData->FirstName%',
							__( 'Last name', 'wp-security-audit-log' )  => '%NewUserData->LastName%'
						],
						wsaldefaults_build_links( [ 'EditUserLink' ] ),
						'user',
						'created'
					),
				),
			),

			__( 'Plugins & Themes', 'wp-security-audit-log' ) => array(
				__( 'Plugins', 'wp-security-audit-log' ) => array(
					array(
						5000,
						WSAL_CRITICAL,
						__( 'User installed a plugin', 'wp-security-audit-log' ),
						__( 'Installed the plugin %Plugin->Name%.', 'wp-security-audit-log' ),
						[
							__( 'Version', 'wp-security-audit-log' )          => '%Plugin->Version%',
							__( 'Install location', 'wp-security-audit-log' ) => '%Plugin->plugin_dir_path%'
						],
						[],
						'plugin',
						'installed'
					),
					array(
						5001,
						WSAL_HIGH,
						__( 'User activated a WordPress plugin', 'wp-security-audit-log' ),
						__( 'Activated the plugin %PluginData->Name%.', 'wp-security-audit-log' ),
						[
							__( 'Version', 'wp-security-audit-log' )          => '%PluginData->Version%',
							__( 'Install location', 'wp-security-audit-log' ) => '%PluginFile%'
						],
						[],
						'plugin',
						'activated'
					),
					array(
						5002,
						WSAL_HIGH,
						__( 'User deactivated a WordPress plugin', 'wp-security-audit-log' ),
						__( 'Deactivated the plugin %PluginData->Name%.', 'wp-security-audit-log' ),
						[
							__( 'Version', 'wp-security-audit-log' )          => '%PluginData->Version%',
							__( 'Install location', 'wp-security-audit-log' ) => '%PluginFile%'
						],
						[],
						'plugin',
						'deactivated'
					),
					array(
						5003,
						WSAL_HIGH,
						__( 'User uninstalled a plugin', 'wp-security-audit-log' ),
						__( 'Uninstalled the plugin %PluginData->Name%.', 'wp-security-audit-log' ),
						[
							__( 'Version', 'wp-security-audit-log' )          => '%PluginData->Version%',
							__( 'Install location', 'wp-security-audit-log' ) => '%PluginFile%'
						],
						[],
						'plugin',
						'uninstalled'
					),
					array(
						5004,
						WSAL_LOW,
						__( 'User upgraded a plugin', 'wp-security-audit-log' ),
						__( 'Updated the plugin %PluginData->Name%.', 'wp-security-audit-log' ),
						[
							__( 'Updated version', 'wp-security-audit-log' )  => '%PluginData->Version%',
							__( 'Install location', 'wp-security-audit-log' ) => '%PluginFile%'
						],
						[],
						'plugin',
						'updated'
					),
					array(
						5019,
						WSAL_MEDIUM,
						__( 'A plugin created a post', 'wp-security-audit-log' ),
						__( 'The plugin created the post %PostTitle%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
							__( 'Plugin', 'wp-security-audit-log' )      => '%PluginName%'
						],
						wsaldefaults_build_links( [ 'EditorLinkPage' ] ),
						'post',
						'created'
					),
					array(
						5025,
						WSAL_LOW,
						__( 'A plugin deleted a post', 'wp-security-audit-log' ),
						__( 'A plugin deleted the post %PostTitle%.', 'wp-security-audit-log' ),
						[
							__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
							__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
							__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
							__( 'Plugin', 'wp-security-audit-log' )      => '%PluginName%'
						],
						[],
						'post',
						'deleted'
					),
					array(
						2051,
						WSAL_HIGH,
						__( 'User changed a file using the plugin editor', 'wp-security-audit-log' ),
						__( 'Modified the file %File% with the plugin editor.', 'wp-security-audit-log' ),
						[],
						[],
						'file',
						'modified'
					),
				),

				__( 'Themes', 'wp-security-audit-log' ) => array(
					array(
						5005,
						WSAL_CRITICAL,
						__( 'User installed a theme', 'wp-security-audit-log' ),
						__( 'Installed the theme %Theme->Name%.', 'wp-security-audit-log' ),
						[
							__( 'Version', 'wp-security-audit-log' )          => '%Theme->Version%',
							__( 'Install location', 'wp-security-audit-log' ) => '%Theme->get_template_directory%',
						],
						'',
						'theme',
						'installed'
					),
					array(
						5006,
						WSAL_HIGH,
						__( 'User activated a theme', 'wp-security-audit-log' ),
						__( 'Activated the theme %Theme->Name%.', 'wp-security-audit-log' ),
						[
							__( 'Version', 'wp-security-audit-log' )          => '%Theme->Version%',
							__( 'Install location', 'wp-security-audit-log' ) => '%Theme->get_template_directory%'
						],
						[],
						'theme',
						'activated'
					),
					array(
						5007,
						WSAL_HIGH,
						__( 'User uninstalled a theme', 'wp-security-audit-log' ),
						__( 'Deleted the theme %Theme->Name%.', 'wp-security-audit-log' ),
						[
							__( 'Version', 'wp-security-audit-log' )          => '%Theme->Version%',
							__( 'Install location', 'wp-security-audit-log' ) => '%Theme->get_template_directory%'
						],
						[],
						'theme',
						'deleted'
					),
					array(
						5031,
						WSAL_LOW,
						__( 'User updated a theme', 'wp-security-audit-log' ),
						__( 'Updated the theme %Theme->Name%.', 'wp-security-audit-log' ),
						[
							__( 'New version', 'wp-security-audit-log' )      => '%Theme->Version%',
							__( 'Install location', 'wp-security-audit-log' ) => '%Theme->get_template_directory%'
						],
						[],
						'theme',
						'updated'
					),
					array(
						2046,
						WSAL_HIGH,
						__( 'User changed a file using the theme editor', 'wp-security-audit-log' ),
						__( 'Modified the file %Theme%/%File% with the theme editor.', 'wp-security-audit-log' ),
						[],
						[],
						'file',
						'modified'
					),
				),

				__( 'Themes on Multisite', 'wp-security-audit-log' ) => array(
					array(
						5008,
						WSAL_HIGH,
						__( 'Activated theme on network', 'wp-security-audit-log' ),
						__( 'Network activated the theme %Theme->Name%.', 'wp-security-audit-log' ),
						[
							__( 'Version', 'wp-security-audit-log' )          => '%Theme->Version%',
							__( 'Install location', 'wp-security-audit-log' ) => '%Theme->get_template_directory%'
						],
						[],
						'theme',
						'activated'
					),
					array(
						5009,
						WSAL_MEDIUM,
						__( 'Deactivated theme from network', 'wp-security-audit-log' ),
						__( 'Network deactivated the theme %Theme->Name%.', 'wp-security-audit-log' ),
						[
							__( 'Version', 'wp-security-audit-log' )          => '%Theme->Version%',
							__( 'Install location', 'wp-security-audit-log' ) => '%Theme->get_template_directory%'
						],
						[],
						'theme',
						'deactivated'
					),
				),
			),

			__( 'WordPress & System', 'wp-security-audit-log' ) => array(
				__( 'System', 'wp-security-audit-log' ) => array(
					array(
						0000,
						E_CRITICAL,
						__( 'Unknown Error', 'wp-security-audit-log' ),
						__( 'An unexpected error has occurred.', 'wp-security-audit-log' )
					),
					array(
						0001,
						E_CRITICAL,
						__( 'PHP error', 'wp-security-audit-log' ),
						__( '%Message%.', 'wp-security-audit-log' )
					),
					array(
						0002,
						E_WARNING,
						__( 'PHP warning', 'wp-security-audit-log' ),
						__( '%Message%.', 'wp-security-audit-log' )
					),
					array(
						0003,
						E_NOTICE,
						__( 'PHP notice', 'wp-security-audit-log' ),
						__( '%Message%.', 'wp-security-audit-log' )
					),
					array(
						0004,
						E_CRITICAL,
						__( 'PHP exception', 'wp-security-audit-log' ),
						__( '%Message%.', 'wp-security-audit-log' )
					),
					array(
						0005,
						E_CRITICAL,
						__( 'PHP shutdown error', 'wp-security-audit-log' ),
						__( '%Message%.', 'wp-security-audit-log' )
					),
					array(
						6004,
						WSAL_MEDIUM,
						__( 'WordPress was updated', 'wp-security-audit-log' ),
						__( 'Updated WordPress.', 'wp-security-audit-log' ),
						[
							__( 'Previous version', 'wp-security-audit-log' ) => '%OldVersion%',
							__( 'New version', 'wp-security-audit-log' )      => '%NewVersion%'
						],
						[],
						'system',
						'updated'
					),
					/**
					 * Alerts: Advertising Extensions
					 *
					 * IMPORTANT: This alert should not be removed from here for backwards compatibility.
					 *
					 * @deprecated 4.2.0
					 */
					array(
						9999,
						E_CRITICAL,
						__( 'Advertising Extensions', 'wp-security-audit-log' ),
						__( '%PromoName% %PromoMessage%', 'wp-security-audit-log' )
					),
				),

				__( 'Activity log plugin', 'wp-security-audit-log' ) => array(
					array(
						6000,
						WSAL_INFORMATIONAL,
						__( 'Events automatically pruned by system', 'wp-security-audit-log' ),
						__( 'System automatically deleted %EventCount% events from the activity log.', 'wp-security-audit-log' ),
						[],
						[],
						'wp-activity-log',
						'deleted'
					),
					array(
						6006,
						WSAL_MEDIUM,
						__( 'Reset the plugin\'s settings to default', 'wp-security-audit-log' ),
						__( 'Reset the activity log plugin\'s settings to default.', 'wp-security-audit-log' ),
						[],
						[],
						'wp-activity-log',
						'modified'
					),
					array(
						6034,
						WSAL_CRITICAL,
						__( 'Purged the activity log', 'wp-security-audit-log' ),
						__( 'Purged the activity log.', 'wp-security-audit-log' ),
						[],
						[],
						'wp-activity-log',
						'deleted'
					),

					array(
						6043,
						WSAL_HIGH,
						__( 'Some WP Activity Log plugin settings on this site were propagated and overridden from the MainWP dashboard', 'wp-security-audit-log' ),
						__( 'Some <strong>WP Activity Log</strong> plugin settings on this site were propagated and overridden from the MainWP dashboard.', 'wp-security-audit-log' ),
						[],
						[],
						'wp-activity-log',
						'modified'
					),

					array(
						6046,
						WSAL_LOW,
						__( 'Changed the status of the Login Page Notification', 'wp-security-audit-log' ),
						__( 'Changed the status of the <strong>Login Page Notification.</strong>', 'wp-security-audit-log' ),
						[],
						[],
						'wp-activity-log',
						'enabled'
					),
					array(
						6047,
						WSAL_LOW,
						__( 'Changed the text of the Login Page Notification', 'wp-security-audit-log' ),
						__( 'Changed the text of the <strong>Login Page Notification.</strong>', 'wp-security-audit-log' ),
						[],
						[],
						'wp-activity-log',
						'modified'
					),
					array(
						6048,
						WSAL_LOW,
						__( 'Changed the status of the Reverse proxy / firewall option', 'wp-security-audit-log' ),
						__( 'Changed the status of the <strong>Reverse proxy / firewall option.</strong>', 'wp-security-audit-log' ),
						[],
						[],
						'wp-activity-log',
						'enabled'
					),
					array(
						6049,
						WSAL_HIGH,
						__( 'Changed the Restrict plugin access setting', 'wp-security-audit-log' ),
						__( 'Changed the <strong>Restrict plugin access</strong> setting to %new_setting%.', 'wp-security-audit-log' ),
						[
							__( 'Previous setting', 'wp-security-audit-log' ) => '%previous_setting%',
						],
						[],
						'wp-activity-log',
						'modified'
					),
					array(
						6050,
						WSAL_HIGH,
						__( 'The user %user% to / from the list of users who can view the activity log', 'wp-security-audit-log' ),
						__( 'The user %user% to / from the list of users who can view the activity log.', 'wp-security-audit-log' ),
						[
							__( 'Previous list of users who had access to view the activity log', 'wp-security-audit-log' ) => '%previous_users%',
						],
						[],
						'wp-activity-log',
						'added'
					),
					array(
						6051,
						WSAL_MEDIUM,
						__( 'Changed the status of the Hide plugin in plugins page setting', 'wp-security-audit-log' ),
						__( 'Changed the status of the <strong>Hide plugin in plugins page</strong> setting.', 'wp-security-audit-log' ),
						[],
						[],
						'wp-activity-log',
						'enabled'
					),
					array(
						6052,
						WSAL_HIGH,
						__( 'Changed the Activity log retention setting', 'wp-security-audit-log' ),
						__( 'Changed the <strong>Activity log retention</strong> to %new_setting%.', 'wp-security-audit-log' ),
						[
							__( 'Previous setting', 'wp-security-audit-log' ) => '%previous_setting%',
						],
						[],
						'wp-activity-log',
						'modified'
					),
					array(
						6053,
						WSAL_LOW,
						__( 'A user was added to / from the list of excluded users from the activity log', 'wp-security-audit-log' ),
						__( 'The user %user% to / from the list of excluded users from the activity log.', 'wp-security-audit-log' ),
						[
							__( 'Previous list of users', 'wp-security-audit-log' ) => '%previous_users%',
						],
						[],
						'wp-activity-log',
						'added'
					),
					array(
						6054,
						WSAL_LOW,
						__( 'A user role was added to / from the list of excluded roles from the activity log', 'wp-security-audit-log' ),
						__( 'The user role %role% to / from the list of excluded roles from the activity log.', 'wp-security-audit-log' ),
						[
							__( 'Previous list of users', 'wp-security-audit-log' ) => '%previous_users%',
						],
						[],
						'wp-activity-log',
						'added'
					),
					array(
						6055,
						WSAL_LOW,
						__( 'An IP address was added to / from the list of excluded IP addresses from the activity log', 'wp-security-audit-log' ),
						__( 'The IP address %ip% to / from the list of excluded IP addresses from the activity log.', 'wp-security-audit-log' ),
						[
							__( 'Previous list of IPs', 'wp-security-audit-log' ) => '%previous_ips%',
						],
						[],
						'wp-activity-log',
						'added'
					),
					array(
						6056,
						WSAL_LOW,
						__( 'A post type was added to / from the list of excluded post types from the activity log', 'wp-security-audit-log' ),
						__( 'The post type %post_type% to / from the list of excluded post types from the activity log.', 'wp-security-audit-log' ),
						[
							__( 'Previous list of Post types', 'wp-security-audit-log' ) => '%previous_types%',
						],
						[],
						'wp-activity-log',
						'added'
					),
					array(
						6057,
						WSAL_LOW,
						__( 'A custom field was added to / from the list of excluded custom fields from the activity log', 'wp-security-audit-log' ),
						__( 'The custom field %custom_field% to / from the list of excluded custom fields from the activity log.', 'wp-security-audit-log' ),
						[
							__( 'Previous list of Custom fields', 'wp-security-audit-log' ) => '%previous_fields%',
						],
						[],
						'wp-activity-log',
						'added'
					),
					array(
						6058,
						WSAL_LOW,
						__( 'A custom field was added to / from the list of excluded user profile custom fields from the activity log', 'wp-security-audit-log' ),
						__( 'The custom field %custom_field% to / from the list of excluded user profile custom fields from the activity log.', 'wp-security-audit-log' ),
						[
							__( 'Previous list of user profile Custom fields', 'wp-security-audit-log' ) => '%previous_fields%',
						],
						[],
						'wp-activity-log',
						'added'
					),
				),

				__( 'Notifications & Integrations', 'wp-security-audit-log' ) => array(
					array(
						6310,
						WSAL_LOW,
						__( 'Changed the status of the Daily Summary of Activity Log', 'wp-security-audit-log' ),
						__( 'Changed the status of the <strong>Daily Summary of Activity Log.</strong>.', 'wp-security-audit-log' ),
						[],
						[],
						'wp-activity-log',
						'enabled'
					),
					array(
						6311,
						WSAL_LOW,
						__( 'Modified the reciepients of the Daily Summary of Activity Log.', 'wp-security-audit-log' ),
						__( 'Modified the reciepients of the <strong>Daily Summary of Activity Log</strong>.', 'wp-security-audit-log' ),
						[
							__( 'New recipient', 'wp-security-audit-log' ) => '%recipient%',
							__( 'Previous recipient', 'wp-security-audit-log' ) => '%previous_recipient%',
						],
						[],
						'wp-activity-log',
						'modified'
					),
					array(
						6312,
						WSAL_LOW,
						__( 'Changed the status of a built in notification', 'wp-security-audit-log' ),
						__( 'Changed the status of the built in notification %notification_name%.', 'wp-security-audit-log' ),
						[],
						[],
						'wp-activity-log',
						'enabled'
					),
					array(
						6313,
						WSAL_LOW,
						__( 'Modified the recipient(s) of the built a notification', 'wp-security-audit-log' ),
						__( 'Modified the recipient(s) of the built in notification %notification_name%.', 'wp-security-audit-log' ),
						[
							__( 'New recipient(s)', 'wp-security-audit-log' ) => '%recipient%',
							__( 'Previous recipient(s)', 'wp-security-audit-log' ) => '%previous_recipient%',
						],
						[],
						'wp-activity-log',
						'added'
					),
					array(
						6314,
						WSAL_LOW,
						__( 'Added a new custom notification', 'wp-security-audit-log' ),
						__( 'Added a new custom notification %notification_name%.', 'wp-security-audit-log' ),
						[
							__( 'Recipient(s)', 'wp-security-audit-log' ) => '%recipient%',
						],
						[],
						'wp-activity-log',
						'added'
					),
					array(
						6315,
						WSAL_LOW,
						__( 'Modified a custom notification', 'wp-security-audit-log' ),
						__( 'Modified the custom notification %notification_name%.', 'wp-security-audit-log' ),
						[
							__( 'Recipient(s)', 'wp-security-audit-log' ) => '%recipient%',
						],
						[],
						'wp-activity-log',
						'modified'
					),
					array(
						6316,
						WSAL_LOW,
						__( 'Changed the status of a custom notification', 'wp-security-audit-log' ),
						__( 'Changed the status of the custom notification %notification_name%.', 'wp-security-audit-log' ),
						[],
						[],
						'wp-activity-log',
						'enabled'
					),
					array(
						6317,
						WSAL_LOW,
						__( 'Deleted a custom notification', 'wp-security-audit-log' ),
						__( 'Deleted the custom notification %notification_name%.', 'wp-security-audit-log' ),
						[],
						[],
						'wp-activity-log',
						'deleted'
					),
					array(
						6318,
						WSAL_LOW,
						__( 'Modified a default notification template', 'wp-security-audit-log' ),
						__( 'Modified the default %template_name% notification template.', 'wp-security-audit-log' ),
						[],
						[],
						'wp-activity-log',
						'modified'
					),

					// Integrations.
					array(
						6320,
						WSAL_HIGH,
						__( 'Added a new integrations connection', 'wp-security-audit-log' ),
						__( 'Added a new integrations connection %name%', 'wp-security-audit-log' ),
						[
							__( 'Connection type', 'wp-security-audit-log' ) => '%type%',
						],
						[],
						'wp-activity-log',
						'added'
					),
					array(
						6321,
						WSAL_HIGH,
						__( 'Modified an integrations connection', 'wp-security-audit-log' ),
						__( 'Modified the integrations connection %name%.', 'wp-security-audit-log' ),
						[
							__( 'Connection type', 'wp-security-audit-log' ) => '%type%',
						],
						[],
						'wp-activity-log',
						'modified'
					),
					array(
						6322,
						WSAL_HIGH,
						__( 'Deleted a integrations connection', 'wp-security-audit-log' ),
						__( 'Deleted the integrations connection %name%.', 'wp-security-audit-log' ),
						[],
						[],
						'wp-activity-log',
						'deleted'
					),
					array(
						6323,
						WSAL_HIGH,
						__( 'Added a new activity log mirror', 'wp-security-audit-log' ),
						__( 'Added a new activity log mirror %name%.', 'wp-security-audit-log' ),
						[
							__( 'Connection used by this mirror', 'wp-security-audit-log' ) => '%connection%',
						],
						[],
						'wp-activity-log',
						'added'
					),
					array(
						6324,
						WSAL_HIGH,
						__( 'Modified an activity log mirror', 'wp-security-audit-log' ),
						__( 'Modified the activity log mirror %name%.', 'wp-security-audit-log' ),
						[
							__( 'Connection used by this mirror', 'wp-security-audit-log' ) => '%connection%',
						],
						[],
						'wp-activity-log',
						'modified'
					),
					array(
						6325,
						WSAL_LOW,
						__( 'Changed the status of an activity log mirror', 'wp-security-audit-log' ),
						__( 'Changed the status of the activity log mirror %name%.', 'wp-security-audit-log' ),
						[
							__( 'Connection used by this mirror', 'wp-security-audit-log' ) => '%connection%',
						],
						[],
						'wp-activity-log',
						'deleted'
					),
					array(
						6326,
						WSAL_HIGH,
						__( 'Deleted an activity log mirror', 'wp-security-audit-log' ),
						__( 'Deleted the activity log mirror %name%.', 'wp-security-audit-log' ),
						[],
						[],
						'wp-activity-log',
						'deleted'
					),
					array(
						6327,
						WSAL_HIGH,
						__( 'Changed the status of Logging of events to the database', 'wp-security-audit-log' ),
						__( 'Changed the status of <strong>Logging of events to the database</strong>.', 'wp-security-audit-log' ),
						[],
						[],
						'wp-activity-log',
						'enabled'
					),
				),

				__( 'WordPress Site Settings', 'wp-security-audit-log' ) => array(
					array(
						6001,
						WSAL_CRITICAL,
						__( 'Option Anyone Can Register in WordPress settings changed', 'wp-security-audit-log' ),
						__( 'The <strong>Membership</strong> setting <strong>Anyone can register</strong>.', 'wp-security-audit-log' ),
						[],
						[],
						'system-setting',
						'enabled'
					),
					array(
						6002,
						WSAL_CRITICAL,
						__( 'New User Default Role changed', 'wp-security-audit-log' ),
						__( 'Changed the <strong>New user default role</strong> WordPress setting.', 'wp-security-audit-log' ),
						[
							__( 'Previous role', 'wp-security-audit-log' ) => '%OldRole%',
							__( 'New role', 'wp-security-audit-log' )      => '%NewRole%'
						],
						[],
						'system-setting',
						'modified'
					),
					array(
						6003,
						WSAL_CRITICAL,
						__( 'WordPress Administrator Notification email changed', 'wp-security-audit-log' ),
						__( 'Change the <strong>Administrator email address</strong> in the WordPress settings.', 'wp-security-audit-log' ),
						[
							__( 'Previous address', 'wp-security-audit-log' ) => '%OldEmail%',
							__( 'New address', 'wp-security-audit-log' )      => '%NewEmail%'
						],
						[],
						'system-setting',
						'modified'
					),
					array(
						6005,
						WSAL_HIGH,
						__( 'User changes the WordPress Permalinks', 'wp-security-audit-log' ),
						__( 'Changed the <strong>WordPress permalinks</strong>.', 'wp-security-audit-log' ),
						[
							__( 'Previous permalinks', 'wp-security-audit-log' ) => '%OldPattern%',
							__( 'New permalinks', 'wp-security-audit-log' )      => '%NewPattern%'
						],
						[],
						'system-setting',
						'modified'
					),
					array(
						6008,
						WSAL_INFORMATIONAL,
						__( 'Enabled/Disabled the option Discourage search engines from indexing this site', 'wp-security-audit-log' ),
						__( 'Changed the status of the WordPress setting <strong>Search engine visibility</strong> (Discourage search engines from indexing this site)', 'wp-security-audit-log' ),
						[],
						[],
						'system-setting',
						'enabled'
					),
					array(
						6009,
						WSAL_MEDIUM,
						__( 'Enabled/Disabled comments on all the website', 'wp-security-audit-log' ),
						__( 'Changed the status of the WordPress setting <strong>Allow people to submit comments on new posts</strong>.', 'wp-security-audit-log' ),
						[],
						[],
						'system-setting',
						'enabled'
					),

					array(
						6010,
						WSAL_MEDIUM,
						__( 'Enabled/Disabled the option Comment author must fill out name and email', 'wp-security-audit-log' ),
						__( 'Changed the status of the WordPress setting <strong>.Comment author must fill out name and email</strong>.', 'wp-security-audit-log' ),
						[],
						[],
						'system-setting',
						'enabled'
					),
					array(
						6011,
						WSAL_MEDIUM,
						__( 'Enabled/Disabled the option Users must be logged in and registered to comment', 'wp-security-audit-log' ),
						__( 'Changed the status of the WordPress setting <strong>Users must be registered and logged in to comment</strong>.', 'wp-security-audit-log' ),
						[],
						[],
						'system-setting',
						'enabled'
					),
					array(
						6012,
						WSAL_INFORMATIONAL,
						__( 'Enabled/Disabled the option to automatically close comments', 'wp-security-audit-log' ),
						__( 'Changed the status of the WordPress setting <strong>Automatically close comments after %Value% days</strong>.', 'wp-security-audit-log' ),
						[],
						[],
						'system-setting',
						'enabled'
					),
					array(
						6013,
						WSAL_INFORMATIONAL,
						__( 'Changed the value of the option Automatically close comments', 'wp-security-audit-log' ),
						__( 'Changed the value of the WordPress setting <strong>Automatically close comments after a number of days</strong> to %NewValue%.', 'wp-security-audit-log' ),
						[
							__( 'Previous value', 'wp-security-audit-log' ) => '%OldValue%'
						],
						[],
						'system-setting',
						'modified'
					),
					array(
						6014,
						WSAL_MEDIUM,
						__( 'Enabled/Disabled the option for comments to be manually approved', 'wp-security-audit-log' ),
						__( 'Changed the value of the WordPress setting <strong>Comments must be manualy approved</strong>.', 'wp-security-audit-log' ),
						[],
						[],
						'system-setting',
						'enabled'
					),
					array(
						6015,
						WSAL_LOW,
						__( 'Enabled/Disabled the option for an author to have previously approved comments for the comments to appear', 'wp-security-audit-log' ),
						__( 'Changed the value of the WordPress setting <strong>Comment author must have a previously approved comment</strong>.', 'wp-security-audit-log' ),
						[],
						[],
						'system-setting',
						'enabled'
					),
					array(
						6016,
						WSAL_LOW,
						__( 'Changed the number of links that a comment must have to be held in the queue', 'wp-security-audit-log' ),
						__( 'Changed the value of the WordPress setting <strong>Hold a comment in the queue if it contains links</strong> to %NewValue% links.', 'wp-security-audit-log' ),
						[
							__( 'Previous value', 'wp-security-audit-log' ) => '%OldValue%'
						],
						[],
						'system-setting',
						'modified'
					),
					array(
						6017,
						WSAL_INFORMATIONAL,
						__( 'Modified the list of keywords for comments moderation', 'wp-security-audit-log' ),
						__( 'Modified the list of keywords for comments medoration in WordPress.', 'wp-security-audit-log' ),
						[],
						[],
						'system-setting',
						'modified'
					),
					array(
						6018,
						WSAL_INFORMATIONAL,
						__( 'Modified the list of keywords for comments blacklisting', 'wp-security-audit-log' ),
						__( 'Modified the list of <strong>Disallowed comment keys</strong> (keywords) for comments blacklisting in WordPress.', 'wp-security-audit-log' ),
						[],
						[],
						'system-setting',
						'modified'
					),
					array(
						6024,
						WSAL_CRITICAL,
						__( 'Option WordPress Address (URL) in WordPress settings changed', 'wp-security-audit-log' ),
						__( 'Changed the <strong>WordPress address (URL)</strong> tp %new_url%.', 'wp-security-audit-log' ),
						[
							__( 'Previous URL', 'wp-security-audit-log' ) => '%old_url%'
						],
						[],
						'system-setting',
						'modified'
					),
					array(
						6025,
						WSAL_CRITICAL,
						__( 'Option Site Address (URL) in WordPress settings changed', 'wp-security-audit-log' ),
						__( 'Changed the <strong>Site address (URL)</strong> to %new_url%.', 'wp-security-audit-log' ),
						[
							__( 'Previous URL', 'wp-security-audit-log' ) => '%old_url%'
						],
						[],
						'system-setting',
						'modified'
					),
					array(
						6035,
						WSAL_CRITICAL,
						__( 'Option Your homepage displays in WordPress settings changed', 'wp-security-audit-log' ),
						__( 'Changed the <strong>Your homepage displays</strong> WordPress setting to %new_homepage%.', 'wp-security-audit-log' ),
						[
							__( 'Previous setting', 'wp-security-audit-log' ) => '%old_homepage%'
						],
						[],
						'system-setting',
						'modified'
					),
					array(
						6036,
						WSAL_CRITICAL,
						__( 'Option homepage in WordPress settings changed', 'wp-security-audit-log' ),
						__( 'Changed the <strong>Homepage</strong> in the WordPress settings to %new_page%.', 'wp-security-audit-log' ),
						[
							__( 'Previous page', 'wp-security-audit-log' ) => '%old_page%'
						],
						[],
						'system-setting',
						'modified'
					),
					array(
						6037,
						WSAL_CRITICAL,
						__( 'Option posts page in WordPress settings changed', 'wp-security-audit-log' ),
						__( 'Changed the <strong> Posts</strong>  page in the WordPress settings to %new_page%.', 'wp-security-audit-log' ),
						[
							__( 'Previous page', 'wp-security-audit-log' ) => '%old_page%'
						],
						[],
						'system-setting',
						'modified'
					),

					array(
						6040,
						WSAL_CRITICAL,
						__( 'Option Timezone in WordPress settings changed', 'wp-security-audit-log' ),
						__( 'Changed the <strong>Timezone</strong> in the WordPress settings to %new_timezone%.', 'wp-security-audit-log' ),
						[
							__( 'Previous timezone', 'wp-security-audit-log' ) => '%old_timezone%'
						],
						[],
						'system-setting',
						'modified'
					),
					array(
						6041,
						WSAL_CRITICAL,
						__( 'Option Date format in WordPress settings changed', 'wp-security-audit-log' ),
						__( 'Changed the <strong>Date format</strong> in the WordPress settings to %new_date_format%.', 'wp-security-audit-log' ),
						[
							__( 'Previous format', 'wp-security-audit-log' ) => '%old_date_format%'
						],
						[],
						'system-setting',
						'modified'
					),
					array(
						6042,
						WSAL_CRITICAL,
						__( 'Option Time format in WordPress settings changed', 'wp-security-audit-log' ),
						__( 'Changed the <strong>Time format</strong> in the WordPress settings to %new_time_format%.', 'wp-security-audit-log' ),
						[
							__( 'Previous format', 'wp-security-audit-log' ) => '%old_time_format%'
						],
						[],
						'system-setting',
						'modified'
					),

					array(
						6044,
						WSAL_HIGH,
						__( 'Option Automatic updates setting changed', 'wp-security-audit-log' ),
						__( 'Changed the <strong>Automatic updates</strong> setting.', 'wp-security-audit-log' ),
						[
							__( 'New setting status', 'wp-security-audit-log' ) => '%updates_status%'
						],
						[],
						'system-setting',
						'modified'
					),

					array(
						6045,
						WSAL_HIGH,
						__( 'Option Site Language setting changed', 'wp-security-audit-log' ),
						__( 'Changed the <strong>Site Language</strong> to %new_value%.', 'wp-security-audit-log' ),
						[
							__( 'Previous setting', 'wp-security-audit-log' ) => '%previous_value%'
						],
						[],
						'system-setting',
						'modified'
					),
				),

				__( 'Database Events', 'wp-security-audit-log' ) => array(
					array(
						5010,
						WSAL_LOW,
						__( 'Plugin created table', 'wp-security-audit-log' ),
						__( 'The plugin %Plugin->Name% created this table in the database.', 'wp-security-audit-log' ),
						[
							__( 'Table', 'wp-security-audit-log' )  => '%TableNames%'
						],
						[],
						'database',
						'created'
					),
					array(
						5011,
						WSAL_LOW,
						__( 'Plugin modified table structure', 'wp-security-audit-log' ),
						__( 'The plugin %Plugin->Name% modified the structure of a database table.', 'wp-security-audit-log' ),
						[
							__( 'Table', 'wp-security-audit-log' )  => '%TableNames%'
						],
						[],
						'database',
						'modified'
					),
					array(
						5012,
						WSAL_MEDIUM,
						__( 'Plugin deleted table', 'wp-security-audit-log' ),
						__( 'The plugin %Plugin->Name% deleted this table from the database.', 'wp-security-audit-log' ),
						[
							__( 'Table', 'wp-security-audit-log' )  => '%TableNames%'
						],
						[],
						'database',
						'deleted'
					),
					array(
						5013,
						WSAL_LOW,
						__( 'Theme created tables', 'wp-security-audit-log' ),
						__( 'The theme %Theme->Name% created this tables in the database.', 'wp-security-audit-log' ),
						[
							__( 'Table', 'wp-security-audit-log' ) => '%TableNames%'
						],
						[],
						'database',
						'created'
					),
					array(
						5014,
						WSAL_LOW,
						__( 'Theme modified tables structure', 'wp-security-audit-log' ),
						__( 'The theme %Theme->Name% modified the structure of this database table', 'wp-security-audit-log' ),
						[
							__( 'Table', 'wp-security-audit-log' ) => '%TableNames%'
						],
						[],
						'database',
						'modified'
					),
					array(
						5015,
						WSAL_MEDIUM,
						__( 'Theme deleted tables', 'wp-security-audit-log' ),
						__( 'The theme %Theme->Name% deleted this table from the database.', 'wp-security-audit-log' ),
						[
							__( 'Tables', 'wp-security-audit-log' ) => '%TableNames%'
						],
						[],
						'database',
						'deleted'
					),
					array(
						5016,
						WSAL_HIGH,
						__( 'Unknown component created tables', 'wp-security-audit-log' ),
						__( 'An unknown component created these tables in the database.', 'wp-security-audit-log' ),
						[
							__( 'Tables', 'wp-security-audit-log' ) => '%TableNames%'
						],
						[],
						'database',
						'created'
					),
					array(
						5017,
						WSAL_HIGH,
						__( 'Unknown component modified tables structure', 'wp-security-audit-log' ),
						__( 'An unknown component modified the structure of these database tables.', 'wp-security-audit-log' ),
						[
							__( 'Tables', 'wp-security-audit-log' ) => '%TableNames%'
						],
						[],
						'database',
						'modified'
					),
					array(
						5018,
						WSAL_HIGH,
						__( 'Unknown component deleted tables', 'wp-security-audit-log' ),
						__( 'An unknown component deleted these tables from the database.', 'wp-security-audit-log' ),
						[
							__( 'Tables', 'wp-security-audit-log' ) => '%TableNames%'
						],
						[],
						'database',
						'deleted'
					),
					array(
						5022,
						WSAL_HIGH,
						__( 'WordPress created tables', 'wp-security-audit-log' ),
						__( 'WordPress has created these tables in the database.', 'wp-security-audit-log' ),
						[
							__( 'Tables', 'wp-security-audit-log' ) => '%TableNames%'
						],
						[],
						'database',
						'created'
					),
					array(
						5023,
						WSAL_HIGH,
						__( 'WordPress modified tables structure', 'wp-security-audit-log' ),
						__( 'WordPress modified the structure of these database tables.', 'wp-security-audit-log' ),
						[
							__( 'Tables', 'wp-security-audit-log' ) => '%TableNames%'
						],
						[],
						'database',
						'modified'
					),
					array(
						5024,
						WSAL_HIGH,
						__( 'WordPress deleted tables', 'wp-security-audit-log' ),
						__( 'WordPress deleted these tables from the database.', 'wp-security-audit-log' ),
						[
							__( 'Tables', 'wp-security-audit-log' ) => '%TableNames%'
						],
						[],
						'database',
						'deleted'
					),
				),
			),

			__( 'Multisite Network Sites', 'wp-security-audit-log' ) => array(
				__( 'MultiSite', 'wp-security-audit-log' ) => array(
					array(
						7000,
						WSAL_CRITICAL,
						__( 'New site added on the network', 'wp-security-audit-log' ),
						__( 'Added the new site %SiteName% to the network.', 'wp-security-audit-log' ),
						[
							__( 'URL', 'wp-security-audit-log' ) => '%BlogURL%'
						],
						[],
						'multisite-network',
						'added'
					),
					array(
						7001,
						WSAL_HIGH,
						__( 'Existing site archived', 'wp-security-audit-log' ),
						__( 'Archived the site %SiteName% on the network.', 'wp-security-audit-log' ),
						[
							__( 'URL', 'wp-security-audit-log' ) => '%BlogURL%'
						],
						[],
						'multisite-network',
						'modified'
					),
					array(
						7002,
						WSAL_HIGH,
						__( 'Archived site has been unarchived', 'wp-security-audit-log' ),
						__( 'Unarchived the site %SiteName%.', 'wp-security-audit-log' ),
						[
							__( 'URL', 'wp-security-audit-log' ) => '%BlogURL%'
						],
						[],
						'multisite-network',
						'modified'
					),
					array(
						7003,
						WSAL_HIGH,
						__( 'Deactivated site has been activated', 'wp-security-audit-log' ),
						__( 'Activated the site %SiteName% on the network.', 'wp-security-audit-log' ),
						[
							__( 'URL', 'wp-security-audit-log' ) => '%BlogURL%'
						],
						[],
						'multisite-network',
						'activated'
					),
					array(
						7004,
						WSAL_HIGH,
						__( 'Site has been deactivated', 'wp-security-audit-log' ),
						__( 'Deactiveated the site %SiteName% on the network.', 'wp-security-audit-log' ),
						[
							__( 'URL', 'wp-security-audit-log' ) => '%BlogURL%'
						],
						[],
						'multisite-network',
						'deactivated'
					),
					array(
						7005,
						WSAL_HIGH,
						__( 'Existing site deleted from network', 'wp-security-audit-log' ),
						__( 'The site: %SiteName%.', 'wp-security-audit-log' ),
						[
							__( 'URL', 'wp-security-audit-log' ) => '%BlogURL%'
						],
						[],
						'multisite-network',
						'deleted'
					),
					array(
						7007,
						WSAL_CRITICAL,
						__( 'Allow site administrators to add new users to their sites settings changed', 'wp-security-audit-log' ),
						__( 'Changed the status of the network setting <strong>Allow site administrators to add new users to their sites</strong>.', 'wp-security-audit-log' ),
						[],
						[],
						'multisite-network',
						'enabled'
					),
					array(
						7008,
						WSAL_HIGH,
						__( 'Site upload space settings changed', 'wp-security-audit-log' ),
						__( 'Changed the status of the network setting <strong>Site upload space</strong> (to limit space allocated for each site\'s upload directory).', 'wp-security-audit-log' ),
						[],
						[],
						'multisite-network',
						'enabled'
					),
					array(
						7009,
						WSAL_MEDIUM,
						__( 'Site upload space file size settings changed', 'wp-security-audit-log' ),
						__( 'Changed the file size in the <strong>Site upload space</strong> network setting to %new_value%.', 'wp-security-audit-log' ),
						[
							__( 'Previous size (MB)', 'wp-security-audit-log' ) => '%old_value%'
						],
						[],
						'multisite-network',
						'modified'
					),
					array(
						7010,
						WSAL_CRITICAL,
						__( 'Site Upload file types settings changed', 'wp-security-audit-log' ),
						__( 'Changed the network setting <strong>Upload file types (list of allowed file types)</strong>.', 'wp-security-audit-log' ),
						[
							__( 'Previous value', 'wp-security-audit-log' ) => '%old_value%',
							__( 'New value', 'wp-security-audit-log' )      => '%new_value%'
						],
						[],
						'multisite-network',
						'modified'
					),
					array(
						7011,
						WSAL_CRITICAL,
						__( 'Site Max upload file size settings changed', 'wp-security-audit-log' ),
						__( 'Changed the <strong>Max upload file size</strong> network setting to %new_value%.', 'wp-security-audit-log' ),
						[
							__( 'Previous size (KB)', 'wp-security-audit-log' ) => '%old_value%'
						],
						[],
						'multisite-network',
						'modified'
					),
					array(
						7012,
						WSAL_HIGH,
						__( 'Allow new registrations settings changed', 'wp-security-audit-log' ),
						__( 'Changed the <strong>Allow new registrations</strong> setting to %new_setting%.', 'wp-security-audit-log' ),
						[
							__( 'Previous setting', 'wp-security-audit-log' ) => '%previous_setting%'
						],
						[],
						'multisite-network',
						'modified'
					),
				),
			),
		)
	);

	// Dummy item to hold WFCM installer.
	if ( function_exists( 'is_plugin_active' ) && ! defined( 'WFCM_PLUGIN_FILE' ) ) {
		$file_changes_tab = array(
			__( 'File Changes', 'wp-security-audit-log' ) => array(
				__( 'Monitor File Changes', 'wp-security-audit-log' ) => array(
					array(
						99999,
						WSAL_HIGH,
						__( 'Dummy', 'wp-security-audit-log' ),
						'',
						[],
						[],
						'file',
						'modified'
					),
				)
			),
		);
		$wsal->alerts->RegisterGroup( $file_changes_tab );
	}

	// Load Custom alerts.
	wsal_load_include_custom_files( $wsal );
}

add_action( 'init', 'wsaldefaults_wsal_init', 5 );

if ( did_action( 'init' ) ) {
	wsaldefaults_wsal_init();
}
