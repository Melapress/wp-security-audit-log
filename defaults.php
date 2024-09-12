<?php
/**
 * Alerts file.
 *
 * Alerts are defined in this file.
 *
 * @package wsal
 */

// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.WP.I18n.UnorderedPlaceholdersText

use WSAL\Helpers\Classes_Helper;
use WSAL\Controllers\Alert_Manager;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// If not included correctly...
if ( ! class_exists( 'WpSecurityAuditLog' ) ) {
	exit();
}

/**
 * Gets an array of directories to loop through to add custom alerts.
 *
 * Passed through a filter so other plugins or code can add own custom
 * alerts files by adding the containing directory to this array.
 *
 * @since 3.5.1 - Added the `wsal_custom_alerts_dirs` filter.
 */
function wsal_load_include_custom_files() {
	do_action( 'wsal_custom_alerts_register', array() );

	$extension_alerts = Classes_Helper::get_classes_by_namespace( '\WSAL\Custom_Alerts' );
	$sensors_alerts   = Classes_Helper::get_classes_by_namespace( '\WSAL\WP_Sensors\Alerts' );

	$extension_alerts = array_merge( $extension_alerts, $sensors_alerts );

	foreach ( $extension_alerts as $alerts ) {
		if ( method_exists( $alerts, 'get_custom_alerts' ) ) {
			Alert_Manager::register_group( call_user_func_array( array( $alerts, 'get_custom_alerts' ), array() ) );
		}
	}
}

/**
 * Builds a configuration object of links suitable for the events definition.
 *
 * @param string[] $link_aliases Link aliases.
 *
 * @return array
 */
function wsaldefaults_build_links( $link_aliases = array() ) {
	$result = array();

	if ( ! empty( $link_aliases ) ) {
		foreach ( $link_aliases as $link_alias ) {
			switch ( $link_alias ) {
				case 'CategoryLink':
				case 'cat_link':
				case 'ProductCatLink':
					$result[ esc_html__( 'View category', 'wp-security-audit-log' ) ] = '%' . $link_alias . '%';
					break;

				case 'ContactSupport':
					$result[ esc_html__( 'Contact Support', 'wp-security-audit-log' ) ] = 'https://melapress.com/contact/';
					break;

				case 'CommentLink':
					$result[ esc_html__( 'Comment', 'wp-security-audit-log' ) ] = array(
						// Before 4.2.1 the CommentLink meta would contain the full HTML markup for the link, now it
						// contains only the URL.
						'url'   => '%CommentLink%',
						'label' => '%CommentDate%',
					);
					break;

				case 'EditorLinkPage':
					$result[ esc_html__( 'View page in the editor', 'wp-security-audit-log' ) ] = '%EditorLinkPage%';
					break;

				case 'EditorLinkPost':
					$result[ esc_html__( 'View the post in editor', 'wp-security-audit-log' ) ] = '%EditorLinkPost%';
					break;

				case 'EditorLinkOrder':
					// @todo move to the WooCommerce extension
					$result[ esc_html__( 'View the order', 'wp-security-audit-log' ) ] = '%EditorLinkOrder%';
					break;

				case 'EditUserLink':
					$result[ esc_html__( 'User profile page', 'wp-security-audit-log' ) ] = '%EditUserLink%';
					break;

				case 'LinkFile':
					$result[ esc_html__( 'Open the log file', 'wp-security-audit-log' ) ] = '%LinkFile%';
					break;

				case 'LogFileLink':
					// We don't show the link anymore.
					break;

				case 'MenuUrl':
					$result[ esc_html__( 'View menu', 'wp-security-audit-log' ) ] = '%MenuUrl%';
					break;

				case 'PostUrl':
					$result[ esc_html__( 'URL', 'wp-security-audit-log' ) ] = '%PostUrl%';
					break;

				case 'AttachmentUrl':
					$result[ esc_html__( 'View attachment page', 'wp-security-audit-log' ) ] = '%AttachmentUrl%';
					break;

				case 'PostUrlIfPlublished':
				case 'PostUrlIfPublished':
					$result[ esc_html__( 'URL', 'wp-security-audit-log' ) ] = '%PostUrlIfPlublished%';
					break;

				case 'RevisionLink':
					$result[ esc_html__( 'View the content changes', 'wp-security-audit-log' ) ] = '%RevisionLink%';
					break;

				case 'TagLink':
					$result[ esc_html__( 'View tag', 'wp-security-audit-log' ) ] = '%RevisionLink%';
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
					// Unsupported link alias.
			}
		}
	}

	return $result;
}

/**
 * Loads all the events for the core and extentions
 *
 * @return void
 *
 * @since 4.5.0
 */
function set_wsal_alerts() {

	$wsal_default_events = array(
		esc_html__( 'Users Logins & Sessions Events', 'wp-security-audit-log' ) => array(
			esc_html__( 'User Activity', 'wp-security-audit-log' ) => array(
				array(
					1000,
					WSAL_LOW,
					esc_html__( 'User logged in', 'wp-security-audit-log' ),
					esc_html__( 'User logged in.', 'wp-security-audit-log' ),
					array(),
					array(),
					'user',
					'login',
				),
				array(
					1001,
					WSAL_LOW,
					esc_html__( 'User logged out', 'wp-security-audit-log' ),
					esc_html__( 'User logged out.', 'wp-security-audit-log' ),
					array(),
					array(),
					'user',
					'logout',
				),
				array(
					1002,
					WSAL_MEDIUM,
					esc_html__( 'Failed login attempt', 'wp-security-audit-log' ),
					esc_html__( '%Attempts% failed login(s).', 'wp-security-audit-log' ),
					array(),
					array(),
					'user',
					'failed-login',
				),
				array(
					1003,
					WSAL_LOW,
					esc_html__( 'Failed login attempt with a non-existing user', 'wp-security-audit-log' ),
					esc_html__( 'Failed login attempt with username %Users%.', 'wp-security-audit-log' ),
					array(),
					array(),
					// wsaldefaults_build_links( array( 'LogFileText' ) ),
					'system',
					'failed-login',
				),
				array(
					1004,
					WSAL_MEDIUM,
					esc_html__( 'Login blocked', 'wp-security-audit-log' ),
					esc_html__( 'Login blocked because other session(s) already exist for this user.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'IP address', 'wp-security-audit-log' ) => '%ClientIP%',
					),
					array(),
					'user',
					'blocked',
				),
				array(
					1005,
					WSAL_LOW,
					esc_html__( 'User logged in with existing session(s)', 'wp-security-audit-log' ),
					esc_html__( 'User logged in however there are other session(s) already for this user.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'IP address(es)', 'wp-security-audit-log' ) => '%IPAddress%',
					),
					array(),
					'user',
					'login',
				),
				array(
					1006,
					WSAL_MEDIUM,
					esc_html__( 'User logged out all other sessions with the same username', 'wp-security-audit-log' ),
					esc_html__( 'Logged out all other sessions with the same user.', 'wp-security-audit-log' ),
					array(),
					array(),
					'user',
					'logout',
				),
				array(
					1007,
					WSAL_MEDIUM,
					esc_html__( 'User session destroyed and logged out', 'wp-security-audit-log' ),
					esc_html__( 'Terminated the session of the user %TargetUserName%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Role', 'wp-security-audit-log' )       => '%TargetUserRole%',
						esc_html__( 'Session ID', 'wp-security-audit-log' ) => '%TargetSessionID%',
					),
					array(),
					'user',
					'logout',
				),
				array(
					1008,
					WSAL_MEDIUM,
					esc_html__( 'Switched to another user', 'wp-security-audit-log' ),
					esc_html__( 'Switched the session to being logged in as %TargetUserName%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Role', 'wp-security-audit-log' ) => '%TargetUserRole%',
					),
					array(),
					'user',
					'login',
				),
				array(
					1009,
					WSAL_LOW,
					esc_html__( 'The plugin terminated an idle session for a user', 'wp-security-audit-log' ),
					esc_html__( 'The plugin terminated an idle session for the user %username%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Role', 'wp-security-audit-log' )       => '%TargetUserRole%',
						esc_html__( 'Session ID', 'wp-security-audit-log' ) => '%SessionID%',
					),
					array(),
					'user',
					'logout',
				),
				array(
					1010,
					WSAL_INFORMATIONAL,
					esc_html__( 'User requested a password reset', 'wp-security-audit-log' ),
					esc_html__( 'User requested a password reset. This does not mean that the password was changed.', 'wp-security-audit-log' ),
					array(),
					array(),
					'user',
					'submitted',
				),
			),
		),

		esc_html__( 'Content & Comments', 'wp-security-audit-log' ) => array(
			esc_html__( 'Content', 'wp-security-audit-log' ) => array(
				array(
					2000,
					WSAL_INFORMATIONAL,
					esc_html__( 'User created a new post and saved it as draft', 'wp-security-audit-log' ),
					esc_html__( 'Created the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					wsaldefaults_build_links( array( 'EditorLinkPost', 'PostUrlIfPublished' ) ),
					'post',
					'created',
				),
				array(
					2001,
					WSAL_LOW,
					esc_html__( 'User published a post', 'wp-security-audit-log' ),
					esc_html__( 'Published the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					wsaldefaults_build_links( array( 'EditorLinkPost', 'PostUrlIfPublished' ) ),
					'post',
					'published',
				),
				array(
					2002,
					WSAL_LOW,
					esc_html__( 'User modified a post', 'wp-security-audit-log' ),
					esc_html__( 'Modified the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					wsaldefaults_build_links( array( 'EditorLinkPost', 'PostUrlIfPublished' ) ),
					'post',
					'modified',
				),
				array(
					2008,
					WSAL_MEDIUM,
					esc_html__( 'User permanently deleted a post from the trash', 'wp-security-audit-log' ),
					esc_html__( 'Permanently deleted the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					array(),
					'post',
					'deleted',
				),
				array(
					2010,
					WSAL_MEDIUM,
					esc_html__( 'User uploaded file to the Uploads directory', 'wp-security-audit-log' ),
					esc_html__( 'Uploaded a file called %FileName%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Directory', 'wp-security-audit-log' ) => '%FilePath%',
					),
					wsaldefaults_build_links( array( 'AttachmentUrl' ) ),
					'file',
					'uploaded',
				),
				array(
					2011,
					WSAL_LOW,
					esc_html__( 'User deleted file from Uploads directory', 'wp-security-audit-log' ),
					esc_html__( 'Deleted the file %FileName%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Directory', 'wp-security-audit-log' ) => '%FilePath%',
					),
					array(),
					'file',
					'deleted',
				),
				array(
					2012,
					WSAL_MEDIUM,
					esc_html__( 'User moved a post to the trash', 'wp-security-audit-log' ),
					esc_html__( 'Moved the post %PostTitle% to trash.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					wsaldefaults_build_links( array( 'PostUrlIfPublished' ) ),
					'post',
					'deleted',
				),
				array(
					2014,
					WSAL_LOW,
					esc_html__( 'User restored a post from trash', 'wp-security-audit-log' ),
					esc_html__( 'Restored the post %PostTitle% from trash.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					wsaldefaults_build_links( array( 'EditorLinkPost', 'PostUrlIfPublished' ) ),
					'post',
					'restored',
				),
				array(
					2017,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed post URL', 'wp-security-audit-log' ),
					esc_html__( 'Changed the URL of the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )      => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )    => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' )  => '%PostStatus%',
						esc_html__( 'Previous URL', 'wp-security-audit-log' ) => '%OldUrl%',
						esc_html__( 'New URL', 'wp-security-audit-log' )      => '%NewUrl%',
					),
					wsaldefaults_build_links( array( 'EditorLinkPost' ) ),
					'post',
					'modified',
				),
				array(
					2019,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed post author', 'wp-security-audit-log' ),
					esc_html__( 'Changed the author of the post %PostTitle% to %NewAuthor%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )         => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )       => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' )     => '%PostStatus%',
						esc_html__( 'Previous author', 'wp-security-audit-log' ) => '%OldAuthor%',
					),
					wsaldefaults_build_links( array( 'PostUrlIfPublished' ) ),
					'post',
					'modified',
				),
				array(
					2021,
					WSAL_MEDIUM,
					esc_html__( 'User changed post status', 'wp-security-audit-log' ),
					esc_html__( 'Changed the status of the post %PostTitle% to %NewStatus%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )         => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )       => '%PostType%',
						esc_html__( 'Previous status', 'wp-security-audit-log' ) => '%OldStatus%',
					),
					wsaldefaults_build_links( array( 'EditorLinkPost', 'PostUrlIfPublished' ) ),
					'post',
					'modified',
				),
				array(
					2025,
					WSAL_LOW,
					esc_html__( 'User changed the visibility of a post', 'wp-security-audit-log' ),
					esc_html__( 'Changed the visibility of the post %PostTitle% to %NewVisibility%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )                    => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )                  => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' )                => '%PostStatus%',
						esc_html__( 'Previous visibility status', 'wp-security-audit-log' ) => '%OldVisibility%',
					),
					wsaldefaults_build_links( array( 'EditorLinkPost', 'PostUrlIfPublished' ) ),
					'post',
					'modified',
				),
				array(
					2027,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed the date of a post', 'wp-security-audit-log' ),
					esc_html__( 'Changed the date of the post %PostTitle% to %NewDate%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )       => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )     => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' )   => '%PostStatus%',
						esc_html__( 'Previous date', 'wp-security-audit-log' ) => '%OldDate%',
					),
					wsaldefaults_build_links( array( 'EditorLinkPost', 'PostUrlIfPublished' ) ),
					'post',
					'modified',
				),
				array(
					2047,
					WSAL_LOW,
					esc_html__( 'User changed the parent of a page', 'wp-security-audit-log' ),
					esc_html__( 'Changed the parent of the post %PostTitle% to %NewParentName%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )         => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )       => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' )     => '%PostStatus%',
						esc_html__( 'Previous parent', 'wp-security-audit-log' ) => '%OldParentName%',
					),
					wsaldefaults_build_links( array( 'EditorLinkPost', 'PostUrlIfPublished' ) ),
					'post',
					'modified',
				),
				array(
					2048,
					WSAL_LOW,
					esc_html__( 'User changed the template of a page', 'wp-security-audit-log' ),
					esc_html__( 'Changed the template of the post %PostTitle% to %NewTemplate%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )           => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )         => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' )       => '%PostStatus%',
						esc_html__( 'Previous template', 'wp-security-audit-log' ) => '%OldTemplate%',
					),
					wsaldefaults_build_links( array( 'EditorLinkPost', 'PostUrlIfPublished' ) ),
					'post',
					'modified',
				),
				array(
					2049,
					WSAL_INFORMATIONAL,
					esc_html__( 'User set a post as sticky', 'wp-security-audit-log' ),
					esc_html__( 'Set the post %PostTitle% as sticky.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					wsaldefaults_build_links( array( 'EditorLinkPost', 'PostUrlIfPublished' ) ),
					'post',
					'modified',
				),
				array(
					2050,
					WSAL_INFORMATIONAL,
					esc_html__( 'User removed post from sticky', 'wp-security-audit-log' ),
					esc_html__( 'Removed the post %PostTitle% from sticky.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					wsaldefaults_build_links( array( 'EditorLinkPost', 'PostUrlIfPublished' ) ),
					'post',
					'modified',
				),
				array(
					2065,
					WSAL_LOW,
					esc_html__( 'User modified the content of a post', 'wp-security-audit-log' ),
					esc_html__( 'Modified the content of the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					wsaldefaults_build_links( array( 'RevisionLink', 'EditorLinkPost', 'PostUrlIfPublished' ) ),
					'post',
					'modified',
				),
				array(
					2073,
					WSAL_INFORMATIONAL,
					esc_html__( 'User submitted a post for review', 'wp-security-audit-log' ),
					esc_html__( 'Submitted the post %PostTitle% for review.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					wsaldefaults_build_links( array( 'EditorLinkPost', 'PostUrlIfPublished' ) ),
					'post',
					'modified',
				),
				array(
					2074,
					WSAL_LOW,
					esc_html__( 'User scheduled a post', 'wp-security-audit-log' ),
					esc_html__( 'Scheduled the post %PostTitle% to be published on %PublishingDate%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					wsaldefaults_build_links( array( 'EditorLinkPost', 'PostUrlIfPublished' ) ),
					'post',
					'modified',
				),
				array(
					2086,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed title of a post', 'wp-security-audit-log' ),
					esc_html__( 'Changed the title of the post %OldTitle% to %NewTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					wsaldefaults_build_links( array( 'EditorLinkPost', 'PostUrlIfPublished' ) ),
					'post',
					'modified',
				),
				array(
					2100,
					WSAL_INFORMATIONAL,
					esc_html__( 'User opened a post in the editor', 'wp-security-audit-log' ),
					esc_html__( 'Opened the post %PostTitle% in the editor.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					wsaldefaults_build_links( array( 'EditorLinkPost', 'PostUrlIfPublished' ) ),
					'post',
					'opened',
				),
				array(
					2101,
					WSAL_INFORMATIONAL,
					esc_html__( 'User viewed a post', 'wp-security-audit-log' ),
					esc_html__( 'Viewed the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					wsaldefaults_build_links( array( 'PostUrl', 'EditorLinkPost' ) ),
					'post',
					'viewed',
				),
				array(
					2111,
					WSAL_LOW,
					esc_html__( 'User enabled/disabled comments in a post', 'wp-security-audit-log' ),
					esc_html__( 'Comments in the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					wsaldefaults_build_links( array( 'EditorLinkPost', 'PostUrlIfPublished' ) ),
					'post',
					'enabled',
				),
				array(
					2112,
					WSAL_LOW,
					esc_html__( 'User enabled/disabled trackbacks and pingbacks in a post', 'wp-security-audit-log' ),
					esc_html__( 'Pingbacks and Trackbacks in the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					wsaldefaults_build_links( array( 'EditorLinkPost', 'PostUrlIfPublished' ) ),
					'post',
					'enabled',
				),
				array(
					2129,
					WSAL_INFORMATIONAL,
					esc_html__( 'User updated the excerpt in a post', 'wp-security-audit-log' ),
					esc_html__( 'The excerpt of the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )                => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )              => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' )            => '%PostStatus%',
						esc_html__( 'Previous excerpt entry', 'wp-security-audit-log' ) => '%old_post_excerpt%',
						esc_html__( 'New excerpt entry', 'wp-security-audit-log' )      => '%post_excerpt%',
					),
					wsaldefaults_build_links( array( 'EditorLinkPost', 'PostUrlIfPublished' ) ),
					'post',
					'modified',
				),
				array(
					2130,
					WSAL_INFORMATIONAL,
					esc_html__( 'User updated the featured image in a post', 'wp-security-audit-log' ),
					esc_html__( 'The featured image of the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )        => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )      => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' )    => '%PostStatus%',
						esc_html__( 'Previous image', 'wp-security-audit-log' ) => '%previous_image%',
						esc_html__( 'New image', 'wp-security-audit-log' )      => '%new_image%',
					),
					wsaldefaults_build_links( array( 'EditorLinkPost', 'PostUrlIfPublished' ) ),
					'post',
					'modified',
				),
				array(
					2133,
					WSAL_INFORMATIONAL,
					esc_html__( 'Ownership of the post has changed', 'wp-security-audit-log' ),
					esc_html__( 'Has taken over the post %PostTitle% from %user%', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )        => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )      => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' )    => '%PostStatus%',
					),
					wsaldefaults_build_links( array( 'EditorLinkPost', 'PostUrlIfPublished' ) ),
					'post',
					'modified',
				),
			),

			esc_html__( 'Tags', 'wp-security-audit-log' )  => array(
				array(
					2119,
					WSAL_INFORMATIONAL,
					esc_html__( 'User added post tag', 'wp-security-audit-log' ),
					esc_html__( 'Added tag(s) to the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'ID', 'wp-security-audit-log' )           => '%PostID%',
						esc_html__( 'Type', 'wp-security-audit-log' )         => '%PostType%',
						esc_html__( 'Status', 'wp-security-audit-log' )       => '%PostStatus%',
						esc_html__( 'Added tag(s)', 'wp-security-audit-log' ) => '%tag%',
					),
					wsaldefaults_build_links( array( 'EditorLinkPost' ) ),
					'post',
					'modified',
				),
				array(
					2120,
					WSAL_INFORMATIONAL,
					esc_html__( 'User removed post tag', 'wp-security-audit-log' ),
					esc_html__( 'Removed tag(s) from the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'ID', 'wp-security-audit-log' )             => '%PostID%',
						esc_html__( 'Type', 'wp-security-audit-log' )           => '%PostType%',
						esc_html__( 'Status', 'wp-security-audit-log' )         => '%PostStatus%',
						esc_html__( 'Removed tag(s)', 'wp-security-audit-log' ) => '%tag%',
					),
					wsaldefaults_build_links( array( 'EditorLinkPost', 'PostUrlIfPublished' ) ),
					'post',
					'modified',
				),
				array(
					2121,
					WSAL_INFORMATIONAL,
					esc_html__( 'User created new tag', 'wp-security-audit-log' ),
					esc_html__( 'Created the tag %TagName%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Slug', 'wp-security-audit-log' ) => 'Slug',
					),
					wsaldefaults_build_links( array( 'TagLink' ) ),
					'tag',
					'created',
				),
				array(
					2122,
					WSAL_LOW,
					esc_html__( 'User deleted tag', 'wp-security-audit-log' ),
					esc_html__( 'Deleted the tag %TagName%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Slug', 'wp-security-audit-log' ) => 'Slug',
					),
					array(),
					'tag',
					'deleted',
				),
				array(
					2123,
					WSAL_INFORMATIONAL,
					esc_html__( 'Renamed the tag %old_name% to %new_name%.', 'wp-security-audit-log' ),
					'',
					array(
						esc_html__( 'Slug', 'wp-security-audit-log' ) => '%Slug%',
					),
					wsaldefaults_build_links( array( 'TagLink' ) ),
					'tag',
					'renamed',
				),
				array(
					2124,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed tag slug', 'wp-security-audit-log' ),
					esc_html__( 'Changed the slug of the tag %tag% to %new_slug%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous slug', 'wp-security-audit-log' ) => '%old_slug%',
					),
					wsaldefaults_build_links( array( 'TagLink' ) ),
					'tag',
					'modified',
				),
				array(
					2125,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed tag description', 'wp-security-audit-log' ),
					esc_html__( 'Changed the description of the tag %tag%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Slug', 'wp-security-audit-log' )                 => '%Slug%',
						esc_html__( 'Previous description', 'wp-security-audit-log' ) => '%old_desc%',
						esc_html__( 'New description', 'wp-security-audit-log' )      => '%new_desc%',
					),
					wsaldefaults_build_links( array( 'TagLink' ) ),
					'tag',
					'modified',
				),
			),

			esc_html__( 'Categories', 'wp-security-audit-log' ) => array(
				array(
					2016,
					WSAL_LOW,
					esc_html__( 'User changed post category', 'wp-security-audit-log' ),
					esc_html__( 'Changed the category(ies) of the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )                => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )              => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' )            => '%PostStatus%',
						esc_html__( 'New category(ies)', 'wp-security-audit-log' )      => '%NewCategories%',
						esc_html__( 'Previous category(ies)', 'wp-security-audit-log' ) => '%OldCategories%',
					),
					wsaldefaults_build_links( array( 'EditorLinkPost', 'PostUrlIfPublished' ) ),
					'post',
					'modified',
				),
				array(
					2023,
					WSAL_MEDIUM,
					esc_html__( 'User created new category', 'wp-security-audit-log' ),
					esc_html__( 'Created the category %CategoryName%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Slug', 'wp-security-audit-log' ) => 'Slug',
					),
					wsaldefaults_build_links( array( 'CategoryLink' ) ),
					'category',
					'created',
				),
				array(
					2024,
					WSAL_MEDIUM,
					esc_html__( 'User deleted category', 'wp-security-audit-log' ),
					esc_html__( 'Deleted the category %CategoryName%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Slug', 'wp-security-audit-log' ) => 'Slug',
					),
					array(),
					'category',
					'deleted',
				),
				array(
					2052,
					WSAL_LOW,
					esc_html__( 'Changed the parent of a category', 'wp-security-audit-log' ),
					esc_html__( 'Changed the parent of the category %CategoryName% to %NewParent%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Slug', 'wp-security-audit-log' )            => '%Slug%',
						esc_html__( 'Previous parent', 'wp-security-audit-log' ) => '%OldParent%',
					),
					wsaldefaults_build_links( array( 'CategoryLink' ) ),
					'category',
					'modified',
				),
				array(
					2127,
					WSAL_LOW,
					esc_html__( 'User changed category name', 'wp-security-audit-log' ),
					esc_html__( 'Renamed the category %old_name% to %new_name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Slug', 'wp-security-audit-log' )          => '%slug%',
					),
					wsaldefaults_build_links( array( 'cat_link' ) ),
					'category',
					'renamed',
				),
				array(
					2128,
					WSAL_LOW,
					esc_html__( 'User changed category slug', 'wp-security-audit-log' ),
					esc_html__( 'Changed the slug of the category %CategoryName% to %new_slug%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous slug', 'wp-security-audit-log' ) => '%old_slug%',
					),
					wsaldefaults_build_links( array( 'cat_link' ) ),
					'category',
					'modified',
				),
			),

			esc_html__( 'Custom Fields', 'wp-security-audit-log' ) => array(
				array(
					2053,
					WSAL_LOW,
					esc_html__( 'User created a custom field for a post', 'wp-security-audit-log' ),
					esc_html__( 'Created the new custom field %MetaKey% in the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )            => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )          => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' )        => '%PostStatus%',
						esc_html__( 'Custom field value', 'wp-security-audit-log' ) => '%MetaValue%',
					),
					wsaldefaults_build_links( array( 'EditorLinkPost', 'MetaLink', 'PostUrlIfPublished' ) ),
					'post',
					'modified',
				),
				array(
					2054,
					WSAL_LOW,
					esc_html__( 'User updated a custom field value for a post', 'wp-security-audit-log' ),
					esc_html__( 'Modified the value of the custom field %MetaKey% in the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )                     => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )                   => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' )                 => '%PostStatus%',
						esc_html__( 'Previous custom field value', 'wp-security-audit-log' ) => '%MetaValueOld%',
						esc_html__( 'New custom field value', 'wp-security-audit-log' )      => '%MetaValueNew%',
					),
					wsaldefaults_build_links( array( 'EditorLinkPost', 'MetaLink', 'PostUrlIfPublished' ) ),
					'custom-field',
					'modified',
				),
				array(
					2055,
					WSAL_MEDIUM,
					esc_html__( 'User deleted a custom field from a post', 'wp-security-audit-log' ),
					esc_html__( 'Deleted the custom field %MetaKey% from the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
					),
					wsaldefaults_build_links( array( 'EditorLinkPost', 'PostUrlIfPublished' ) ),
					'custom-field',
					'deleted',
				),
				array(
					2062,
					WSAL_LOW,
					esc_html__( 'User updated a custom field name for a post', 'wp-security-audit-log' ),
					esc_html__( 'Renamed the custom field %MetaKeyOld% on post %PostTitle% to %MetaKeyNew%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post', 'wp-security-audit-log' )                       => '%PostTitle%',
						esc_html__( 'Post ID', 'wp-security-audit-log' )                    => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )                  => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' )                => '%PostStatus%',
					),
					wsaldefaults_build_links( array( 'EditorLinkPost', 'PostUrlIfPublished' ) ),
					'custom-field',
					'renamed',
				),
			),

			esc_html__( 'Custom Fields (ACF)', 'wp-security-audit-log' ) => array(
				array(
					2131,
					WSAL_LOW,
					esc_html__( 'User added relationship to a custom field value for a post', 'wp-security-audit-log' ),
					esc_html__( 'Added relationships to the custom field %MetaKey% in the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )           => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )         => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' )       => '%PostStatus%',
						esc_html__( 'New relationships', 'wp-security-audit-log' ) => '%Relationships%',
					),
					wsaldefaults_build_links( array( 'EditorLinkPost', 'MetaLink' ) ),
					'custom-field',
					'modified',
				),
				array(
					2132,
					WSAL_LOW,
					esc_html__( 'User removed relationship from a custom field value for a post', 'wp-security-audit-log' ),
					esc_html__( 'Removed relationships from the custom field %MetaKey% in the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )               => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )             => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' )           => '%PostStatus%',
						esc_html__( 'Removed relationships', 'wp-security-audit-log' ) => '%Relationships%',
					),
					wsaldefaults_build_links( array( 'EditorLinkPost', 'MetaLink' ) ),
					'custom-field',
					'modified',
				),
			),

			/**
			 * Alerts: Comments
			 */
			esc_html__( 'Comments', 'wp-security-audit-log' ) => array(
				array(
					2090,
					WSAL_INFORMATIONAL,
					esc_html__( 'User approved a comment', 'wp-security-audit-log' ),
					esc_html__( 'Approved the comment posted by %Author% on the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
						esc_html__( 'Comment ID', 'wp-security-audit-log' )  => '%CommentID%',
					),
					wsaldefaults_build_links( array( 'CommentLink', 'PostUrlIfPublished' ) ),
					'comment',
					'approved',
				),
				array(
					2091,
					WSAL_INFORMATIONAL,
					esc_html__( 'User unapproved a comment', 'wp-security-audit-log' ),
					esc_html__( 'Unapproved the comment posted by %Author% on the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
						esc_html__( 'Comment ID', 'wp-security-audit-log' )  => '%CommentID%',
					),
					wsaldefaults_build_links( array( 'CommentLink', 'PostUrlIfPublished' ) ),
					'comment',
					'unapproved',
				),
				array(
					2092,
					WSAL_INFORMATIONAL,
					esc_html__( 'User replied to a comment', 'wp-security-audit-log' ),
					esc_html__( 'Replied to the comment posted by %Author% on the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
						esc_html__( 'Comment ID', 'wp-security-audit-log' )  => '%CommentID%',
					),
					wsaldefaults_build_links( array( 'CommentLink', 'PostUrlIfPublished' ) ),
					'comment',
					'created',
				),
				array(
					2093,
					WSAL_LOW,
					esc_html__( 'User edited a comment', 'wp-security-audit-log' ),
					esc_html__( 'Edited the comment posted by %Author% on the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
						esc_html__( 'Comment ID', 'wp-security-audit-log' )  => '%CommentID%',
					),
					wsaldefaults_build_links( array( 'CommentLink', 'PostUrlIfPublished' ) ),
					'comment',
					'modified',
				),
				array(
					2094,
					WSAL_INFORMATIONAL,
					esc_html__( 'User marked a comment as Spam', 'wp-security-audit-log' ),
					esc_html__( 'Marked the comment posted by %Author% on the post %PostTitle% as spam.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
						esc_html__( 'Comment ID', 'wp-security-audit-log' )  => '%CommentID%',
					),
					wsaldefaults_build_links( array( 'CommentLink', 'PostUrlIfPublished' ) ),
					'comment',
					'unapproved',
				),
				array(
					2095,
					WSAL_LOW,
					esc_html__( 'User marked a comment as Not Spam', 'wp-security-audit-log' ),
					esc_html__( 'Marked the comment posted by %Author% on the post %PostTitle% as not spam.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
						esc_html__( 'Comment ID', 'wp-security-audit-log' )  => '%CommentID%',
					),
					wsaldefaults_build_links( array( 'CommentLink', 'PostUrlIfPublished' ) ),
					'comment',
					'approved',
				),
				array(
					2096,
					WSAL_LOW,
					esc_html__( 'User moved a comment to trash', 'wp-security-audit-log' ),
					esc_html__( 'Moved the comment posted by %Author% on the post %PostTitle% to trash.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
						esc_html__( 'Comment ID', 'wp-security-audit-log' )  => '%CommentID%',
					),
					wsaldefaults_build_links( array( 'CommentLink', 'PostUrlIfPublished' ) ),
					'comment',
					'deleted',
				),
				array(
					2097,
					WSAL_INFORMATIONAL,
					esc_html__( 'User restored a comment from the trash', 'wp-security-audit-log' ),
					esc_html__( 'Restored the comment posted by %Author% on the post %PostTitle% from trash.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
						esc_html__( 'Comment ID', 'wp-security-audit-log' )  => '%CommentID%',
					),
					wsaldefaults_build_links( array( 'CommentLink', 'PostUrlIfPublished' ) ),
					'comment',
					'restored',
				),
				array(
					2098,
					WSAL_LOW,
					esc_html__( 'User permanently deleted a comment', 'wp-security-audit-log' ),
					esc_html__( 'Permanently deleted the comment posted by %Author% on the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
						esc_html__( 'Comment ID', 'wp-security-audit-log' )  => '%CommentID%',
					),
					wsaldefaults_build_links( array( 'PostUrlIfPublished' ) ),
					'comment',
					'deleted',
				),
				array(
					2099,
					WSAL_INFORMATIONAL,
					esc_html__( 'User posted a comment', 'wp-security-audit-log' ),
					esc_html__( 'Posted a comment on the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
						esc_html__( 'Comment ID', 'wp-security-audit-log' )  => '%CommentID%',
					),
					wsaldefaults_build_links( array( 'CommentLink', 'PostUrlIfPublished' ) ),
					'comment',
					'created',
				),
				/**
				 * IMPORTANT: This alert is deprecated but should not be
				 * removed from the definitions for backwards compatibility.
				 */
				array(
					2126,
					WSAL_INFORMATIONAL,
					esc_html__( 'Visitor posted a comment', 'wp-security-audit-log' ),
					esc_html__( 'Posted a comment on the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
						esc_html__( 'Comment ID', 'wp-security-audit-log' )  => '%CommentID%',
					),
					wsaldefaults_build_links( array( 'CommentLink', 'PostUrlIfPublished' ) ),
					'comment',
					'created',
				),
			),

			/**
			 * Alerts: Widgets
			 */
			esc_html__( 'Widgets', 'wp-security-audit-log' ) => array(
				array(
					2042,
					WSAL_MEDIUM,
					esc_html__( 'User added a new widget', 'wp-security-audit-log' ),
					esc_html__( 'Added a new %WidgetName% widget in %Sidebar%.', 'wp-security-audit-log' ),
					array(),
					array(),
					'widget',
					'added',
				),
				array(
					2043,
					WSAL_HIGH,
					esc_html__( 'User modified a widget', 'wp-security-audit-log' ),
					esc_html__( 'Modified the %WidgetName% widget in %Sidebar%.', 'wp-security-audit-log' ),
					array(),
					array(),
					'widget',
					'modified',
				),
				array(
					2044,
					WSAL_MEDIUM,
					esc_html__( 'User deleted widget', 'wp-security-audit-log' ),
					esc_html__( 'Deleted the %WidgetName% widget from %Sidebar%.', 'wp-security-audit-log' ),
					array(),
					array(),
					'widget',
					'deleted',
				),
				array(
					2045,
					WSAL_LOW,
					esc_html__( 'User moved widget', 'wp-security-audit-log' ),
					esc_html__( 'Moved the %WidgetName% widget.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'From', 'wp-security-audit-log' ) => '%OldSidebar%',
						esc_html__( 'To', 'wp-security-audit-log' )   => '%NewSidebar%',
					),
					array(),
					'widget',
					'modified',
				),
				array(
					2071,
					WSAL_LOW,
					esc_html__( 'User changed widget position', 'wp-security-audit-log' ),
					esc_html__( 'Changed the position of the %WidgetName% widget in %Sidebar%.', 'wp-security-audit-log' ),
					array(),
					array(),
					'widget',
					'modified',
				),
			),

			/**
			 * Alerts: Menus
			 */
			esc_html__( 'Menus', 'wp-security-audit-log' ) => array(
				array(
					2078,
					WSAL_LOW,
					esc_html__( 'User created new menu', 'wp-security-audit-log' ),
					esc_html__( 'New menu called %MenuName%.', 'wp-security-audit-log' ),
					array(),
					wsaldefaults_build_links( array( 'MenuUrl' ) ),
					'menu',
					'created',
				),
				array(
					2079,
					WSAL_LOW,
					esc_html__( 'User added content to a menu', 'wp-security-audit-log' ),
					esc_html__( 'Added the item %ContentName% to the menu %MenuName%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Item type', 'wp-security-audit-log' ) => '%ContentType%',
					),
					wsaldefaults_build_links( array( 'MenuUrl' ) ),
					'menu',
					'modified',
				),
				array(
					2080,
					WSAL_LOW,
					esc_html__( 'User removed content from a menu', 'wp-security-audit-log' ),
					esc_html__( 'Removed the item %ContentName% from the menu %MenuName%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Item type', 'wp-security-audit-log' ) => '%ContentType%',
					),
					wsaldefaults_build_links( array( 'MenuUrl' ) ),
					'menu',
					'modified',
				),
				array(
					2081,
					WSAL_MEDIUM,
					esc_html__( 'User deleted menu', 'wp-security-audit-log' ),
					esc_html__( 'Deleted the menu %MenuName%.', 'wp-security-audit-log' ),
					array(),
					array(),
					'menu',
					'deleted',
				),
				array(
					2082,
					WSAL_LOW,
					esc_html__( 'User changed menu setting', 'wp-security-audit-log' ),
					esc_html__( 'The setting %MenuSetting% in the menu %MenuName%.', 'wp-security-audit-log' ),
					array(),
					wsaldefaults_build_links( array( 'MenuUrl' ) ),
					'menu',
					'enabled',
				),
				array(
					2083,
					WSAL_LOW,
					esc_html__( 'User modified content in a menu', 'wp-security-audit-log' ),
					esc_html__( 'Modified the item %ContentName% in the menu %MenuName%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Item type', 'wp-security-audit-log' ) => '%ContentType%',
					),
					wsaldefaults_build_links( array( 'MenuUrl' ) ),
					'menu',
					'modified',
				),
				array(
					2084,
					WSAL_LOW,
					esc_html__( 'User changed name of a menu', 'wp-security-audit-log' ),
					esc_html__( 'Renamed the menu %OldMenuName% to %MenuName%.', 'wp-security-audit-log' ),
					array(),
					wsaldefaults_build_links( array( 'MenuUrl' ) ),
					'menu',
					'renamed',
				),
				array(
					2085,
					WSAL_LOW,
					esc_html__( 'User changed order of the objects in a menu', 'wp-security-audit-log' ),
					esc_html__( 'Changed the order of the items in the menu %MenuName%.', 'wp-security-audit-log' ),
					array(),
					wsaldefaults_build_links( array( 'MenuUrl' ) ),
					'menu',
					'modified',
				),
				array(
					2089,
					WSAL_LOW,
					esc_html__( 'User moved objects as a sub-item', 'wp-security-audit-log' ),
					esc_html__( 'Moved items as sub-items in the menu %MenuName%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Moved item', 'wp-security-audit-log' )       => '%ItemName%',
						esc_html__( 'as a sub-item of', 'wp-security-audit-log' ) => '%ParentName%',
					),
					wsaldefaults_build_links( array( 'MenuUrl' ) ),
					'menu',
					'modified',
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
			esc_html__( 'Custom Post Types', 'wp-security-audit-log' ) => array(
				array(
					2003,
					E_NOTICE,
					esc_html__( 'User modified a draft blog post', 'wp-security-audit-log' ),
					esc_html__( 'Modified the draft post with the %PostTitle%. %EditorLinkPost%.', 'wp-security-audit-log' ),
				),
				array(
					2029,
					E_NOTICE,
					esc_html__( 'User created a new post with custom post type and saved it as draft', 'wp-security-audit-log' ),
					esc_html__( 'Created a new custom post called %PostTitle% of type %PostType%. %EditorLinkPost%.', 'wp-security-audit-log' ),
				),
				array(
					2030,
					E_NOTICE,
					esc_html__( 'User published a post with custom post type', 'wp-security-audit-log' ),
					esc_html__( 'Published a custom post %PostTitle% of type %PostType%. Post URL is %PostUrl%. %EditorLinkPost%.', 'wp-security-audit-log' ),
				),
				array(
					2031,
					E_NOTICE,
					esc_html__( 'User modified a post with custom post type', 'wp-security-audit-log' ),
					esc_html__( 'Modified the custom post %PostTitle% of type %PostType%. Post URL is %PostUrl%. %EditorLinkPost%.', 'wp-security-audit-log' ),
				),
				array(
					2032,
					E_NOTICE,
					esc_html__( 'User modified a draft post with custom post type', 'wp-security-audit-log' ),
					esc_html__( 'Modified the draft custom post %PostTitle% of type is %PostType%. %EditorLinkPost%.', 'wp-security-audit-log' ),
				),
				array(
					2033,
					E_WARNING,
					esc_html__( 'User permanently deleted post with custom post type', 'wp-security-audit-log' ),
					esc_html__( 'Permanently Deleted the custom post %PostTitle% of type %PostType%.', 'wp-security-audit-log' ),
				),
				array(
					2034,
					E_WARNING,
					esc_html__( 'User moved post with custom post type to trash', 'wp-security-audit-log' ),
					esc_html__( 'Moved the custom post %PostTitle% of type %PostType% to trash. Post URL was %PostUrl%.', 'wp-security-audit-log' ),
				),
				array(
					2035,
					E_CRITICAL,
					esc_html__( 'User restored post with custom post type from trash', 'wp-security-audit-log' ),
					esc_html__( 'The custom post %PostTitle% of type %PostType% has been restored from trash. %EditorLinkPost%.', 'wp-security-audit-log' ),
				),
				array(
					2036,
					E_NOTICE,
					esc_html__( 'User changed the category of a post with custom post type', 'wp-security-audit-log' ),
					esc_html__( 'Changed the category(ies) of the custom post %PostTitle% of type %PostType% from %OldCategories% to %NewCategories%. %EditorLinkPost%.', 'wp-security-audit-log' ),
				),
				array(
					2037,
					E_NOTICE,
					esc_html__( 'User changed the URL of a post with custom post type', 'wp-security-audit-log' ),
					esc_html__( 'Changed the URL of the custom post %PostTitle% of type %PostType% from %OldUrl% to %NewUrl%. %EditorLinkPost%.', 'wp-security-audit-log' ),
				),
				array(
					2038,
					E_NOTICE,
					esc_html__( 'User changed the author or post with custom post type', 'wp-security-audit-log' ),
					esc_html__( 'Changed the author of custom post %PostTitle% of type %PostType% from %OldAuthor% to %NewAuthor%. %EditorLinkPost%.', 'wp-security-audit-log' ),
				),
				array(
					2039,
					E_NOTICE,
					esc_html__( 'User changed the status of post with custom post type', 'wp-security-audit-log' ),
					esc_html__( 'Changed the status of custom post %PostTitle% of type %PostType% from %OldStatus% to %NewStatus%. %EditorLinkPost%.', 'wp-security-audit-log' ),
				),
				array(
					2040,
					E_WARNING,
					esc_html__( 'User changed the visibility of a post with custom post type', 'wp-security-audit-log' ),
					esc_html__( 'Changed the visibility of the custom post %PostTitle% of type %PostType% from %OldVisibility% to %NewVisibility%. %EditorLinkPost%.', 'wp-security-audit-log' ),
				),
				array(
					2041,
					E_NOTICE,
					esc_html__( 'User changed the date of post with custom post type', 'wp-security-audit-log' ),
					esc_html__( 'Changed the date of the custom post %PostTitle% of type %PostType% from %OldDate% to %NewDate%. %EditorLinkPost%.', 'wp-security-audit-log' ),
				),
				array(
					2056,
					E_CRITICAL,
					esc_html__( 'User created a custom field for a custom post type', 'wp-security-audit-log' ),
					esc_html__( 'Created a new custom field %MetaKey% with value %MetaValue% in custom post %PostTitle% of type %PostType%. %EditorLinkPost%.<br>%MetaLink%.', 'wp-security-audit-log' ),
				),
				array(
					2057,
					E_CRITICAL,
					esc_html__( 'User updated a custom field for a custom post type', 'wp-security-audit-log' ),
					esc_html__( 'Modified the value of the custom field %MetaKey% from %MetaValueOld% to %MetaValueNew% in custom post %PostTitle% of type %PostType% %EditorLinkPost%.<br>%MetaLink%.', 'wp-security-audit-log' ),
				),
				array(
					2058,
					E_CRITICAL,
					esc_html__( 'User deleted a custom field from a custom post type', 'wp-security-audit-log' ),
					esc_html__( 'Deleted the custom field %MetaKey% with id %MetaID% from custom post %PostTitle% of type %PostType% %EditorLinkPost%.<br>%MetaLink%.', 'wp-security-audit-log' ),
				),
				array(
					2063,
					E_CRITICAL,
					esc_html__( 'User updated a custom field name for a custom post type', 'wp-security-audit-log' ),
					esc_html__( 'Changed the custom field name from %MetaKeyOld% to %MetaKeyNew% in custom post %PostTitle% of type %PostType% %EditorLinkPost%.<br>%MetaLink%.', 'wp-security-audit-log' ),
				),
				array(
					2067,
					E_WARNING,
					esc_html__( 'User modified content for a published custom post type', 'wp-security-audit-log' ),
					esc_html__( 'Modified the content of the published custom post type %PostTitle%. Post URL is %PostUrl%. %EditorLinkPost%.', 'wp-security-audit-log' ),
				),
				array(
					2068,
					E_NOTICE,
					esc_html__( 'User modified content for a draft post', 'wp-security-audit-log' ),
					esc_html__( 'Modified the content of the draft post %PostTitle%.%RevisionLink% %EditorLinkPost%.', 'wp-security-audit-log' ),
				),
				array(
					2070,
					E_NOTICE,
					esc_html__( 'User modified content for a draft custom post type', 'wp-security-audit-log' ),
					esc_html__( 'Modified the content of the draft custom post type %PostTitle%.%EditorLinkPost%.', 'wp-security-audit-log' ),
				),
				array(
					2072,
					E_NOTICE,
					esc_html__( 'User modified content of a post', 'wp-security-audit-log' ),
					esc_html__( 'Modified the content of post %PostTitle% which is submitted for review.%RevisionLink% %EditorLinkPost%.', 'wp-security-audit-log' ),
				),
				array(
					2076,
					E_NOTICE,
					esc_html__( 'User scheduled a custom post type', 'wp-security-audit-log' ),
					esc_html__( 'Scheduled the custom post type %PostTitle% to be published %PublishingDate%. %EditorLinkPost%.', 'wp-security-audit-log' ),
				),
				array(
					2088,
					E_NOTICE,
					esc_html__( 'User changed title of a custom post type', 'wp-security-audit-log' ),
					esc_html__( 'Changed the title of the custom post %OldTitle% to %NewTitle%. %EditorLinkPost%.', 'wp-security-audit-log' ),
				),
				array(
					2104,
					E_NOTICE,
					esc_html__( 'User opened a custom post type in the editor', 'wp-security-audit-log' ),
					esc_html__( 'Opened the custom post %PostTitle% of type %PostType% in the editor. View the post: %EditorLinkPost%.', 'wp-security-audit-log' ),
				),
				array(
					2105,
					E_NOTICE,
					esc_html__( 'User viewed a custom post type', 'wp-security-audit-log' ),
					esc_html__( 'Viewed the custom post %PostTitle% of type %PostType%. View the post: %PostUrl%.', 'wp-security-audit-log' ),
				),
				array(
					5021,
					E_CRITICAL,
					esc_html__( 'A plugin created a custom post', 'wp-security-audit-log' ),
					esc_html__( 'A plugin automatically created the following custom post: %PostTitle%.', 'wp-security-audit-log' ),
				),
				array(
					5027,
					E_CRITICAL,
					esc_html__( 'A plugin deleted a custom post', 'wp-security-audit-log' ),
					esc_html__( 'A plugin automatically deleted the following custom post: %PostTitle%.', 'wp-security-audit-log' ),
				),
				array(
					2108,
					E_NOTICE,
					esc_html__( 'A plugin modified a custom post', 'wp-security-audit-log' ),
					esc_html__( 'Plugin modified the custom post %PostTitle%. View the post: %EditorLinkPost%.', 'wp-security-audit-log' ),
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
			esc_html__( 'Pages', 'wp-security-audit-log' ) => array(
				array(
					2004,
					E_NOTICE,
					esc_html__( 'User created a new WordPress page and saved it as draft', 'wp-security-audit-log' ),
					esc_html__( 'Created a new page called %PostTitle% and saved it as draft. %EditorLinkPage%.', 'wp-security-audit-log' ),
				),
				array(
					2005,
					E_NOTICE,
					esc_html__( 'User published a WordPress page', 'wp-security-audit-log' ),
					esc_html__( 'Published a page called %PostTitle%. Page URL is %PostUrl%. %EditorLinkPage%.', 'wp-security-audit-log' ),
				),
				array(
					2006,
					E_NOTICE,
					esc_html__( 'User modified a published WordPress page', 'wp-security-audit-log' ),
					esc_html__( 'Modified the published page %PostTitle%. Page URL is %PostUrl%. %EditorLinkPage%.', 'wp-security-audit-log' ),
				),
				array(
					2007,
					E_NOTICE,
					esc_html__( 'User modified a draft WordPress page', 'wp-security-audit-log' ),
					esc_html__( 'Modified the draft page %PostTitle%. Page ID is %PostID%. %EditorLinkPage%.', 'wp-security-audit-log' ),
				),
				array(
					2009,
					E_WARNING,
					esc_html__( 'User permanently deleted a page from the trash', 'wp-security-audit-log' ),
					esc_html__( 'Permanently deleted the page %PostTitle%.', 'wp-security-audit-log' ),
				),
				array(
					2013,
					E_WARNING,
					esc_html__( 'User moved WordPress page to the trash', 'wp-security-audit-log' ),
					esc_html__( 'Moved the page %PostTitle% to trash. Page URL was %PostUrl%.', 'wp-security-audit-log' ),
				),
				array(
					2015,
					E_CRITICAL,
					esc_html__( 'User restored a WordPress page from trash', 'wp-security-audit-log' ),
					esc_html__( 'Page %PostTitle% has been restored from trash. %EditorLinkPage%.', 'wp-security-audit-log' ),
				),
				array(
					2018,
					E_NOTICE,
					esc_html__( 'User changed page URL', 'wp-security-audit-log' ),
					esc_html__( 'Changed the URL of the page %PostTitle% from %OldUrl% to %NewUrl%. %EditorLinkPage%.', 'wp-security-audit-log' ),
				),
				array(
					2020,
					E_NOTICE,
					esc_html__( 'User changed page author', 'wp-security-audit-log' ),
					esc_html__( 'Changed the author of the page %PostTitle% from %OldAuthor% to %NewAuthor%. %EditorLinkPage%.', 'wp-security-audit-log' ),
				),
				array(
					2022,
					E_NOTICE,
					esc_html__( 'User changed page status', 'wp-security-audit-log' ),
					esc_html__( 'Changed the status of the page %PostTitle% from %OldStatus% to %NewStatus%. %EditorLinkPage%.', 'wp-security-audit-log' ),
				),
				array(
					2026,
					E_WARNING,
					esc_html__( 'User changed the visibility of a page post', 'wp-security-audit-log' ),
					esc_html__( 'Changed the visibility of the page %PostTitle% from %OldVisibility% to %NewVisibility%. %EditorLinkPage%.', 'wp-security-audit-log' ),
				),
				array(
					2028,
					E_NOTICE,
					esc_html__( 'User changed the date of a page post', 'wp-security-audit-log' ),
					esc_html__( 'Changed the date of the page %PostTitle% from %OldDate% to %NewDate%. %EditorLinkPage%.', 'wp-security-audit-log' ),
				),
				array(
					2059,
					E_CRITICAL,
					esc_html__( 'User created a custom field for a page', 'wp-security-audit-log' ),
					esc_html__( 'Created a new custom field called %MetaKey% with value %MetaValue% in the page %PostTitle% %EditorLinkPage%.<br>%MetaLink%.', 'wp-security-audit-log' ),
				),
				array(
					2060,
					E_CRITICAL,
					esc_html__( 'User updated a custom field value for a page', 'wp-security-audit-log' ),
					esc_html__( 'Modified the value of the custom field %MetaKey% from %MetaValueOld% to %MetaValueNew% in the page %PostTitle% %EditorLinkPage%.<br>%MetaLink%.', 'wp-security-audit-log' ),
				),
				array(
					2061,
					E_CRITICAL,
					esc_html__( 'User deleted a custom field from a page', 'wp-security-audit-log' ),
					esc_html__( 'Deleted the custom field %MetaKey% with id %MetaID% from page %PostTitle% %EditorLinkPage%<br>%MetaLink%.', 'wp-security-audit-log' ),
				),
				array(
					2064,
					E_CRITICAL,
					esc_html__( 'User updated a custom field name for a page', 'wp-security-audit-log' ),
					esc_html__( 'Changed the custom field name from %MetaKeyOld% to %MetaKeyNew% in the page %PostTitle% %EditorLinkPage%.<br>%MetaLink%.', 'wp-security-audit-log' ),
				),
				array(
					2066,
					E_WARNING,
					esc_html__( 'User modified content for a published page', 'wp-security-audit-log' ),
					esc_html__( 'Modified the content of the published page %PostTitle%. Page URL is %PostUrl%. %RevisionLink% %EditorLinkPage%.', 'wp-security-audit-log' ),
				),
				array(
					2069,
					E_NOTICE,
					esc_html__( 'User modified content for a draft page', 'wp-security-audit-log' ),
					esc_html__( 'Modified the content of draft page %PostTitle%.%RevisionLink% %EditorLinkPage%.', 'wp-security-audit-log' ),
				),
				array(
					2075,
					E_NOTICE,
					esc_html__( 'User scheduled a page', 'wp-security-audit-log' ),
					esc_html__( 'Scheduled the page %PostTitle% to be published %PublishingDate%. %EditorLinkPage%.', 'wp-security-audit-log' ),
				),
				array(
					2087,
					E_NOTICE,
					esc_html__( 'User changed title of a page', 'wp-security-audit-log' ),
					esc_html__( 'Changed the title of the page %OldTitle% to %NewTitle%. %EditorLinkPage%.', 'wp-security-audit-log' ),
				),
				array(
					2102,
					E_NOTICE,
					esc_html__( 'User opened a page in the editor', 'wp-security-audit-log' ),
					esc_html__( 'Opened the page %PostTitle% in the editor. View the page: %EditorLinkPage%.', 'wp-security-audit-log' ),
				),
				array(
					2103,
					E_NOTICE,
					esc_html__( 'User viewed a page', 'wp-security-audit-log' ),
					esc_html__( 'Viewed the page %PostTitle%. View the page: %PostUrl%.', 'wp-security-audit-log' ),
				),
				array(
					2113,
					E_NOTICE,
					esc_html__( 'User disabled Comments/Trackbacks and Pingbacks on a draft post', 'wp-security-audit-log' ),
					esc_html__( 'Disabled %Type% on the draft post %PostTitle%. View the post: %PostUrl%.', 'wp-security-audit-log' ),
				),
				array(
					2114,
					E_NOTICE,
					esc_html__( 'User enabled Comments/Trackbacks and Pingbacks on a draft post', 'wp-security-audit-log' ),
					esc_html__( 'Enabled %Type% on the draft post %PostTitle%. View the post: %PostUrl%.', 'wp-security-audit-log' ),
				),
				array(
					2115,
					E_NOTICE,
					esc_html__( 'User disabled Comments/Trackbacks and Pingbacks on a published page', 'wp-security-audit-log' ),
					esc_html__( 'Disabled %Type% on the published page %PostTitle%. View the page: %PostUrl%.', 'wp-security-audit-log' ),
				),
				array(
					2116,
					E_NOTICE,
					esc_html__( 'User enabled Comments/Trackbacks and Pingbacks on a published page', 'wp-security-audit-log' ),
					esc_html__( 'Enabled %Type% on the published page %PostTitle%. View the page: %PostUrl%.', 'wp-security-audit-log' ),
				),
				array(
					2117,
					E_NOTICE,
					esc_html__( 'User disabled Comments/Trackbacks and Pingbacks on a draft page', 'wp-security-audit-log' ),
					esc_html__( 'Disabled %Type% on the draft page %PostTitle%. View the page: %PostUrl%.', 'wp-security-audit-log' ),
				),
				array(
					2118,
					E_NOTICE,
					esc_html__( 'User enabled Comments/Trackbacks and Pingbacks on a draft page', 'wp-security-audit-log' ),
					esc_html__( 'Enabled %Type% on the draft page %PostTitle%. View the page: %PostUrl%.', 'wp-security-audit-log' ),
				),
				array(
					5020,
					E_CRITICAL,
					esc_html__( 'A plugin created a page', 'wp-security-audit-log' ),
					esc_html__( 'A plugin automatically created the following page: %PostTitle%.', 'wp-security-audit-log' ),
				),
				array(
					5026,
					E_CRITICAL,
					esc_html__( 'A plugin deleted a page', 'wp-security-audit-log' ),
					esc_html__( 'A plugin automatically deleted the following page: %PostTitle%.', 'wp-security-audit-log' ),
				),
				array(
					2107,
					E_NOTICE,
					esc_html__( 'A plugin modified a page', 'wp-security-audit-log' ),
					esc_html__( 'Plugin modified the page %PostTitle%. View the page: %EditorLinkPage%.', 'wp-security-audit-log' ),
				),
			),
		),

		esc_html__( 'User Accounts', 'wp-security-audit-log' ) => array(
			esc_html__( 'User Profiles', 'wp-security-audit-log' ) => array(
				array(
					4000,
					WSAL_CRITICAL,
					esc_html__( 'New user was created on WordPress', 'wp-security-audit-log' ),
					__( 'A new user %NewUserData->Username% is created via registration.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'User', 'wp-security-audit-log' ) => '%NewUserData->Username%',
					),
					wsaldefaults_build_links( array( 'EditUserLink' ) ),
					'user',
					'created',
				),
				array(
					4001,
					WSAL_CRITICAL,
					esc_html__( 'User created another WordPress user', 'wp-security-audit-log' ),
					__( 'Created the new user: %NewUserData->Username%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Role', 'wp-security-audit-log' )       => '%NewUserData->Roles%',
						esc_html__( 'First name', 'wp-security-audit-log' ) => '%NewUserData->FirstName%',
						esc_html__( 'Last name', 'wp-security-audit-log' )  => '%NewUserData->LastName%',
					),
					wsaldefaults_build_links( array( 'EditUserLink' ) ),
					'user',
					'created',
				),
				array(
					4002,
					WSAL_CRITICAL,
					esc_html__( 'The role of a user was changed by another WordPress user', 'wp-security-audit-log' ),
					esc_html__( 'Changed the role of user %TargetUsername% to %NewRole%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous role', 'wp-security-audit-log' ) => '%OldRole%',
						esc_html__( 'First name', 'wp-security-audit-log' )    => '%FirstName%',
						esc_html__( 'Last name', 'wp-security-audit-log' )     => '%LastName%',
					),
					wsaldefaults_build_links( array( 'EditUserLink' ) ),
					'user',
					'modified',
				),
				array(
					4003,
					WSAL_HIGH,
					esc_html__( 'User has changed his or her password', 'wp-security-audit-log' ),
					esc_html__( 'Changed the password.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Role', 'wp-security-audit-log' )       => '%TargetUserData->Roles%',
						esc_html__( 'First name', 'wp-security-audit-log' ) => '%TargetUserData->FirstName%',
						esc_html__( 'Last name', 'wp-security-audit-log' )  => '%TargetUserData->LastName%',
					),
					wsaldefaults_build_links( array( 'EditUserLink' ) ),
					'user',
					'modified',
				),
				array(
					4004,
					WSAL_HIGH,
					esc_html__( 'User changed another user\'s password', 'wp-security-audit-log' ),
					__( 'Changed the password of the user %TargetUserData->Username%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Role', 'wp-security-audit-log' )       => '%TargetUserData->Roles%',
						esc_html__( 'First name', 'wp-security-audit-log' ) => '%TargetUserData->FirstName%',
						esc_html__( 'Last name', 'wp-security-audit-log' )  => '%TargetUserData->LastName%',
					),
					wsaldefaults_build_links( array( 'EditUserLink' ) ),
					'user',
					'modified',
				),
				array(
					4005,
					WSAL_MEDIUM,
					esc_html__( 'User changed his or her email address', 'wp-security-audit-log' ),
					esc_html__( 'Changed the email address to %NewEmail%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Role', 'wp-security-audit-log' )                   => '%Roles%',
						esc_html__( 'First name', 'wp-security-audit-log' )             => '%FirstName%',
						esc_html__( 'Last name', 'wp-security-audit-log' )              => '%LastName%',
						esc_html__( 'Previous email address', 'wp-security-audit-log' ) => '%OldEmail%',
					),
					wsaldefaults_build_links( array( 'EditUserLink' ) ),
					'user',
					'modified',
				),
				array(
					4006,
					WSAL_MEDIUM,
					esc_html__( 'User changed another user\'s email address', 'wp-security-audit-log' ),
					esc_html__( 'Changed the email address of the user %TargetUsername% to %NewEmail%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Role', 'wp-security-audit-log' )                   => '%Roles%',
						esc_html__( 'First name', 'wp-security-audit-log' )             => '%FirstName%',
						esc_html__( 'Last name', 'wp-security-audit-log' )              => '%LastName%',
						esc_html__( 'Previous email address', 'wp-security-audit-log' ) => '%OldEmail%',
					),
					wsaldefaults_build_links( array( 'EditUserLink' ) ),
					'user',
					'modified',
				),
				array(
					4007,
					WSAL_HIGH,
					esc_html__( 'User was deleted by another user', 'wp-security-audit-log' ),
					__( 'Deleted the user %TargetUserData->Username%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Role', 'wp-security-audit-log' )       => '%TargetUserData->Roles%',
						esc_html__( 'First name', 'wp-security-audit-log' ) => '%NewUserData->FirstName%',
						esc_html__( 'Last name', 'wp-security-audit-log' )  => '%NewUserData->LastName%',
					),
					array(),
					'user',
					'deleted',
				),
				array(
					4014,
					WSAL_INFORMATIONAL,
					esc_html__( 'User opened the profile page of another user', 'wp-security-audit-log' ),
					esc_html__( 'Opened the profile page of user %TargetUsername%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Role', 'wp-security-audit-log' )       => '%Roles%',
						esc_html__( 'First name', 'wp-security-audit-log' ) => '%FirstName%',
						esc_html__( 'Last name', 'wp-security-audit-log' )  => '%LastName%',
					),
					wsaldefaults_build_links( array( 'EditUserLink' ) ),
					'user',
					'opened',
				),
				array(
					4015,
					WSAL_LOW,
					esc_html__( 'User updated a custom field value for a user', 'wp-security-audit-log' ),
					esc_html__( 'Changed the value of the custom field %custom_field_name% in the user profile %TargetUsername%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Role', 'wp-security-audit-log' )           => '%Roles%',
						esc_html__( 'First name', 'wp-security-audit-log' )     => '%FirstName%',
						esc_html__( 'Last name', 'wp-security-audit-log' )      => '%LastName%',
						esc_html__( 'Previous value', 'wp-security-audit-log' ) => '%old_value%',
						esc_html__( 'New value', 'wp-security-audit-log' )      => '%new_value%',
					),
					wsaldefaults_build_links( array( 'EditUserLink', 'MetaLink' ) ),
					'user',
					'modified',
				),
				array(
					4016,
					WSAL_LOW,
					esc_html__( 'User created a custom field value for a user', 'wp-security-audit-log' ),
					esc_html__( 'Created the custom field %custom_field_name% in the user profile %TargetUsername%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Role', 'wp-security-audit-log' )               => '%Roles%',
						esc_html__( 'First name', 'wp-security-audit-log' )         => '%FirstName%',
						esc_html__( 'Last name', 'wp-security-audit-log' )          => '%LastName%',
						esc_html__( 'Custom field value', 'wp-security-audit-log' ) => '%new_value%',
					),
					wsaldefaults_build_links( array( 'EditUserLink', 'MetaLink' ) ),
					'user',
					'modified',
				),
				array(
					4017,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed first name for a user', 'wp-security-audit-log' ),
					esc_html__( 'Changed the first name of the user %TargetUsername% to %new_firstname%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Role', 'wp-security-audit-log' )          => '%Roles%',
						esc_html__( 'Previous name', 'wp-security-audit-log' ) => '%old_firstname%',
						esc_html__( 'Last name', 'wp-security-audit-log' )     => '%LastName%',
					),
					wsaldefaults_build_links( array( 'EditUserLink' ) ),
					'user',
					'modified',
				),
				array(
					4018,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed last name for a user', 'wp-security-audit-log' ),
					esc_html__( 'Changed the last name of the user %TargetUsername% to %new_lastname%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Role', 'wp-security-audit-log' )               => '%Roles%',
						esc_html__( 'First name', 'wp-security-audit-log' )         => '%FirstName%',
						esc_html__( 'Previous last name', 'wp-security-audit-log' ) => '%old_lastname%',
					),
					wsaldefaults_build_links( array( 'EditUserLink' ) ),
					'user',
					'modified',
				),
				array(
					4019,
					WSAL_INFORMATIONAL,
					esc_html__( 'User changed nickname for a user', 'wp-security-audit-log' ),
					esc_html__( 'Changed the nickname of the user %TargetUsername% to %new_nickname%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Role', 'wp-security-audit-log' )              => '%Roles%',
						esc_html__( 'First name', 'wp-security-audit-log' )        => '%FirstName%',
						esc_html__( 'Last name', 'wp-security-audit-log' )         => '%LastName%',
						esc_html__( 'Previous nickname', 'wp-security-audit-log' ) => '%old_nickname%',
					),
					wsaldefaults_build_links( array( 'EditUserLink' ) ),
					'user',
					'modified',
				),
				array(
					4020,
					WSAL_LOW,
					esc_html__( 'User changed the display name for a user', 'wp-security-audit-log' ),
					esc_html__( 'Changed the display name of the user %TargetUsername% to %new_displayname%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Role', 'wp-security-audit-log' )                  => '%Roles%',
						esc_html__( 'First name', 'wp-security-audit-log' )            => '%FirstName%',
						esc_html__( 'Last name', 'wp-security-audit-log' )             => '%LastName%',
						esc_html__( 'Previous display name', 'wp-security-audit-log' ) => '%old_displayname%',
					),
					wsaldefaults_build_links( array( 'EditUserLink' ) ),
					'user',
					'modified',
				),
				array(
					4021,
					WSAL_MEDIUM,
					esc_html__( 'User\'s website URL was modified', 'wp-security-audit-log' ),
					esc_html__( 'Changed the website URL of the user %TargetUsername% to %new_url%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Role', 'wp-security-audit-log' )                  => '%Roles%',
						esc_html__( 'First name', 'wp-security-audit-log' )            => '%FirstName%',
						esc_html__( 'Last name', 'wp-security-audit-log' )             => '%LastName%',
						esc_html__( 'Previous website URL', 'wp-security-audit-log' )             => '%old_url%',

					),
					wsaldefaults_build_links( array( 'EditUserLink' ) ),
					'user',
					'modified',
				),

				array(
					4025,
					WSAL_CRITICAL,
					esc_html__( 'User created an application password', 'wp-security-audit-log' ),
					esc_html__( 'The application password %friendly_name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Role', 'wp-security-audit-log' )                               => '%roles%',
						esc_html__( 'First name', 'wp-security-audit-log' )                         => '%firstname%',
						esc_html__( 'Last name', 'wp-security-audit-log' )                          => '%lastname%',
					),
					array(),
					'user',
					'added',
				),
				array(
					4026,
					WSAL_CRITICAL,
					esc_html__( 'User created an application password', 'wp-security-audit-log' ),
					esc_html__( 'The application password %friendly_name% for the user %login%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Role', 'wp-security-audit-log' )                               => '%roles%',
						esc_html__( 'First name', 'wp-security-audit-log' )                         => '%firstname%',
						esc_html__( 'Last name', 'wp-security-audit-log' )                          => '%lastname%',
					),
					array(),
					'user',
					'added',
				),

				array(
					4027,
					WSAL_HIGH,
					esc_html__( 'User revoked all application passwords', 'wp-security-audit-log' ),
					esc_html__( 'All application passwords.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Role', 'wp-security-audit-log' )       => '%roles%',
						esc_html__( 'First name', 'wp-security-audit-log' ) => '%firstname%',
						esc_html__( 'Last name', 'wp-security-audit-log' )  => '%lastname%',
					),
					array(),
					'user',
					'revoked',
				),
				array(
					4028,
					WSAL_HIGH,
					esc_html__( 'User revoked all application passwords for a user', 'wp-security-audit-log' ),
					esc_html__( 'All application passwords from the user %login%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Role', 'wp-security-audit-log' )       => '%roles%',
						esc_html__( 'First name', 'wp-security-audit-log' ) => '%firstname%',
						esc_html__( 'Last name', 'wp-security-audit-log' )  => '%lastname%',
					),
					array(),
					'user',
					'revoked',
				),
				array(
					4029,
					WSAL_HIGH,
					esc_html__( 'Admin sent a password reset request to a user', 'wp-security-audit-log' ),
					esc_html__( 'Sent a password reset request to the user %login%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Role', 'wp-security-audit-log' )       => '%roles%',
						esc_html__( 'First name', 'wp-security-audit-log' ) => '%firstname%',
						esc_html__( 'Last name', 'wp-security-audit-log' )  => '%lastname%',
					),
					array(),
					'user',
					'submitted',
				),
			),

			esc_html__( 'Multisite User Profiles', 'wp-security-audit-log' ) => array(
				array(
					4008,
					WSAL_CRITICAL,
					esc_html__( 'User granted Super Admin privileges', 'wp-security-audit-log' ),
					esc_html__( 'Granted Super Admin privileges to the user %TargetUsername%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Role', 'wp-security-audit-log' )       => '%Roles%',
						esc_html__( 'First name', 'wp-security-audit-log' ) => '%FirstName%',
						esc_html__( 'Last name', 'wp-security-audit-log' )  => '%LastName%',
					),
					wsaldefaults_build_links( array( 'EditUserLink' ) ),
					'user',
					'modified',
				),
				array(
					4009,
					WSAL_CRITICAL,
					esc_html__( 'User revoked from Super Admin privileges', 'wp-security-audit-log' ),
					esc_html__( 'Revoked Super Admin privileges from %TargetUsername%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Role', 'wp-security-audit-log' )       => '%Roles%',
						esc_html__( 'First name', 'wp-security-audit-log' ) => '%FirstName%',
						esc_html__( 'Last name', 'wp-security-audit-log' )  => '%LastName%',
					),
					wsaldefaults_build_links( array( 'EditUserLink' ) ),
					'user',
					'modified',
				),
				array(
					4010,
					WSAL_MEDIUM,
					esc_html__( 'Existing user added to a site', 'wp-security-audit-log' ),
					esc_html__( 'Added user %TargetUsername% to the site %SiteName%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Role', 'wp-security-audit-log' )       => '%TargetUserRole%',
						esc_html__( 'First name', 'wp-security-audit-log' ) => '%FirstName%',
						esc_html__( 'Last name', 'wp-security-audit-log' )  => '%LastName%',
					),
					wsaldefaults_build_links( array( 'EditUserLink' ) ),
					'user',
					'modified',
				),
				array(
					4011,
					WSAL_MEDIUM,
					esc_html__( 'User removed from site', 'wp-security-audit-log' ),
					esc_html__( 'Removed user %TargetUsername% from the site %SiteName%', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Site role', 'wp-security-audit-log' )  => '%TargetUserRole%',
						esc_html__( 'First name', 'wp-security-audit-log' ) => '%FirstName%',
						esc_html__( 'Last name', 'wp-security-audit-log' )  => '%LastName%',
					),
					wsaldefaults_build_links( array( 'EditUserLink' ) ),
					'user',
					'modified',
				),
				array(
					4012,
					WSAL_CRITICAL,
					esc_html__( 'New network user created', 'wp-security-audit-log' ),
					__( 'Created the new network user %NewUserData->Username%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'First name', 'wp-security-audit-log' ) => '%NewUserData->FirstName%',
						esc_html__( 'Last name', 'wp-security-audit-log' )  => '%NewUserData->LastName%',
					),
					wsaldefaults_build_links( array( 'EditUserLink' ) ),
					'user',
					'created',
				),
				array(
					4013,
					WSAL_HIGH,
					esc_html__( 'Network user has been activated', 'wp-security-audit-log' ),
					__( 'User %NewUserData->Username% has been activated.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Role', 'wp-security-audit-log' )       => '%NewUserData->Roles%',
						esc_html__( 'First name', 'wp-security-audit-log' ) => '%NewUserData->FirstName%',
						esc_html__( 'Last name', 'wp-security-audit-log' )  => '%NewUserData->LastName%',
					),
					wsaldefaults_build_links( array( 'EditUserLink' ) ),
					'user',
					'activated',
				),
				array(
					4024,
					WSAL_LOW,
					esc_html__( 'Network user has signed-up', 'wp-security-audit-log' ),
					esc_html__( 'User with the email address %email_address% has signed up to the network.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Username', 'wp-security-audit-log' )  => '%username%',
					),
					array(),
					'user',
					'created',
				),
			),
		),

		esc_html__( 'Plugins & Themes', 'wp-security-audit-log' ) => array(
			esc_html__( 'Plugins', 'wp-security-audit-log' ) => array(
				array(
					5000,
					WSAL_CRITICAL,
					esc_html__( 'User installed a plugin', 'wp-security-audit-log' ),
					__( 'Installed the plugin %Plugin->Name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Version', 'wp-security-audit-log' )          => '%Plugin->Version%',
						esc_html__( 'Install location', 'wp-security-audit-log' ) => '%Plugin->plugin_dir_path%',
					),
					array(),
					'plugin',
					'installed',
				),
				array(
					5001,
					WSAL_HIGH,
					esc_html__( 'User activated a WordPress plugin', 'wp-security-audit-log' ),
					__( 'Activated the plugin %PluginData->Name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Version', 'wp-security-audit-log' )          => '%PluginData->Version%',
						esc_html__( 'Install location', 'wp-security-audit-log' ) => '%PluginFile%',
					),
					array(),
					'plugin',
					'activated',
				),
				array(
					5002,
					WSAL_HIGH,
					esc_html__( 'User deactivated a WordPress plugin', 'wp-security-audit-log' ),
					__( 'Deactivated the plugin %PluginData->Name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Version', 'wp-security-audit-log' )          => '%PluginData->Version%',
						esc_html__( 'Install location', 'wp-security-audit-log' ) => '%PluginFile%',
					),
					array(),
					'plugin',
					'deactivated',
				),
				array(
					5003,
					WSAL_HIGH,
					esc_html__( 'User uninstalled a plugin', 'wp-security-audit-log' ),
					__( 'Uninstalled the plugin %PluginData->Name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Version', 'wp-security-audit-log' )          => '%PluginData->Version%',
						esc_html__( 'Install location', 'wp-security-audit-log' ) => '%PluginFile%',
					),
					array(),
					'plugin',
					'uninstalled',
				),
				array(
					5004,
					WSAL_LOW,
					esc_html__( 'User upgraded a plugin', 'wp-security-audit-log' ),
					__( 'Updated the plugin %PluginData->Name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Updated version', 'wp-security-audit-log' )  => '%PluginData->Version%',
						esc_html__( 'Install location', 'wp-security-audit-log' ) => '%PluginFile%',
						esc_html__( 'Previous version', 'wp-security-audit-log' ) => '%OldVersion%',
					),
					array(),
					'plugin',
					'updated',
				),
				array(
					5019,
					WSAL_MEDIUM,
					esc_html__( 'A plugin created a post', 'wp-security-audit-log' ),
					esc_html__( 'The plugin created the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
						esc_html__( 'Plugin', 'wp-security-audit-log' )      => '%PluginName%',
					),
					wsaldefaults_build_links( array( 'EditorLinkPage' ) ),
					'post',
					'created',
				),
				array(
					5025,
					WSAL_LOW,
					esc_html__( 'A plugin deleted a post', 'wp-security-audit-log' ),
					esc_html__( 'A plugin deleted the post %PostTitle%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Post ID', 'wp-security-audit-log' )     => '%PostID%',
						esc_html__( 'Post type', 'wp-security-audit-log' )   => '%PostType%',
						esc_html__( 'Post status', 'wp-security-audit-log' ) => '%PostStatus%',
						esc_html__( 'Plugin', 'wp-security-audit-log' )      => '%PluginName%',
					),
					array(),
					'post',
					'deleted',
				),

				array(
					5028,
					WSAL_MEDIUM,
					esc_html__( 'Changed the Automatic updates setting for a plugin.', 'wp-security-audit-log' ),
					esc_html__( 'Changed the Automatic updates setting for the plugin %name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Install location', 'wp-security-audit-log' )     => '%install_directory%',
					),
					array(),
					'plugin',
					'enabled',
				),

				array(
					5029,
					WSAL_MEDIUM,
					esc_html__( 'Changed the Automatic updates setting for a theme.', 'wp-security-audit-log' ),
					esc_html__( 'Changed the Automatic updates setting for the theme %name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Install location', 'wp-security-audit-log' )     => '%install_directory%',
					),
					array(),
					'theme',
					'enabled',
				),

				array(
					2051,
					WSAL_HIGH,
					esc_html__( 'User changed a file using the plugin editor', 'wp-security-audit-log' ),
					esc_html__( 'Modified the file %File% with the plugin editor.', 'wp-security-audit-log' ),
					array(),
					array(),
					'file',
					'modified',
				),
			),

			esc_html__( 'Themes', 'wp-security-audit-log' ) => array(
				array(
					5005,
					WSAL_CRITICAL,
					esc_html__( 'User installed a theme', 'wp-security-audit-log' ),
					__( 'Installed the theme %Theme->Name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Version', 'wp-security-audit-log' )          => '%Theme->Version%',
						esc_html__( 'Install location', 'wp-security-audit-log' ) => '%Theme->get_template_directory%',
					),
					'',
					'theme',
					'installed',
				),
				array(
					5006,
					WSAL_HIGH,
					esc_html__( 'User activated a theme', 'wp-security-audit-log' ),
					__( 'Activated the theme %Theme->Name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Version', 'wp-security-audit-log' )          => '%Theme->Version%',
						esc_html__( 'Install location', 'wp-security-audit-log' ) => '%Theme->get_template_directory%',
					),
					array(),
					'theme',
					'activated',
				),
				array(
					5007,
					WSAL_HIGH,
					esc_html__( 'User uninstalled a theme', 'wp-security-audit-log' ),
					__( 'Deleted the theme %Theme->Name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Version', 'wp-security-audit-log' )          => '%Theme->Version%',
						esc_html__( 'Install location', 'wp-security-audit-log' ) => '%Theme->get_template_directory%',
					),
					array(),
					'theme',
					'deleted',
				),
				array(
					5031,
					WSAL_LOW,
					esc_html__( 'User updated a theme', 'wp-security-audit-log' ),
					__( 'Updated the theme %Theme->Name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'New version', 'wp-security-audit-log' )      => '%Theme->Version%',
						esc_html__( 'Install location', 'wp-security-audit-log' ) => '%Theme->get_template_directory%',
					),
					array(),
					'theme',
					'updated',
				),
				array(
					2046,
					WSAL_HIGH,
					esc_html__( 'User changed a file using the theme editor', 'wp-security-audit-log' ),
					esc_html__( 'Modified the file %Theme%/%File% with the theme editor.', 'wp-security-audit-log' ),
					array(),
					array(),
					'file',
					'modified',
				),
			),

			esc_html__( 'Themes on Multisite', 'wp-security-audit-log' ) => array(
				array(
					5008,
					WSAL_HIGH,
					esc_html__( 'Activated theme on network', 'wp-security-audit-log' ),
					__( 'Network activated the theme %Theme->Name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Version', 'wp-security-audit-log' )          => '%Theme->Version%',
						esc_html__( 'Install location', 'wp-security-audit-log' ) => '%Theme->get_template_directory%',
					),
					array(),
					'theme',
					'activated',
				),
				array(
					5009,
					WSAL_MEDIUM,
					esc_html__( 'Deactivated theme from network', 'wp-security-audit-log' ),
					__( 'Network deactivated the theme %Theme->Name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Version', 'wp-security-audit-log' )          => '%Theme->Version%',
						esc_html__( 'Install location', 'wp-security-audit-log' ) => '%Theme->get_template_directory%',
					),
					array(),
					'theme',
					'deactivated',
				),
			),
		),

		esc_html__( 'WordPress & System', 'wp-security-audit-log' ) => array(
			esc_html__( 'System', 'wp-security-audit-log' ) => array(
				array(
					0000,
					E_CRITICAL,
					esc_html__( 'Unknown Error', 'wp-security-audit-log' ),
					esc_html__( 'An unexpected error has occurred.', 'wp-security-audit-log' ),
				),
				array(
					0001,
					E_CRITICAL,
					esc_html__( 'PHP error', 'wp-security-audit-log' ),
					esc_html__( '%Message%.', 'wp-security-audit-log' ),
				),
				array(
					0002,
					E_WARNING,
					esc_html__( 'PHP warning', 'wp-security-audit-log' ),
					esc_html__( '%Message%.', 'wp-security-audit-log' ),
				),
				array(
					0003,
					E_NOTICE,
					esc_html__( 'PHP notice', 'wp-security-audit-log' ),
					esc_html__( '%Message%.', 'wp-security-audit-log' ),
				),
				array(
					0004,
					E_CRITICAL,
					esc_html__( 'PHP exception', 'wp-security-audit-log' ),
					esc_html__( '%Message%.', 'wp-security-audit-log' ),
				),
				array(
					0005,
					E_CRITICAL,
					esc_html__( 'PHP shutdown error', 'wp-security-audit-log' ),
					esc_html__( '%Message%.', 'wp-security-audit-log' ),
				),
				array(
					6004,
					WSAL_MEDIUM,
					esc_html__( 'WordPress was updated', 'wp-security-audit-log' ),
					esc_html__( 'Updated WordPress.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous version', 'wp-security-audit-log' ) => '%OldVersion%',
						esc_html__( 'New version', 'wp-security-audit-log' )      => '%NewVersion%',
					),
					array(),
					'system',
					'updated',
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
					esc_html__( 'Advertising Extensions', 'wp-security-audit-log' ),
					esc_html__( '%PromoName% %PromoMessage%', 'wp-security-audit-log' ),
				),
			),

			esc_html__( 'Activity log plugin', 'wp-security-audit-log' ) => array(
				array(
					6000,
					WSAL_INFORMATIONAL,
					esc_html__( 'Events automatically pruned by system', 'wp-security-audit-log' ),
					esc_html__( 'System automatically deleted %EventCount% events from the activity log.', 'wp-security-audit-log' ),
					array(),
					array(),
					'wp-activity-log',
					'deleted',
				),
				array(
					6006,
					WSAL_MEDIUM,
					esc_html__( 'Reset the plugin\'s settings to default', 'wp-security-audit-log' ),
					esc_html__( 'Reset the activity log plugin\'s settings to default.', 'wp-security-audit-log' ),
					array(),
					array(),
					'wp-activity-log',
					'modified',
				),
				array(
					6034,
					WSAL_CRITICAL,
					esc_html__( 'Purged the activity log', 'wp-security-audit-log' ),
					esc_html__( 'Purged the activity log.', 'wp-security-audit-log' ),
					array(),
					array(),
					'wp-activity-log',
					'deleted',
				),

				array(
					6038,
					WSAL_CRITICAL,
					esc_html__( 'Deleted all the data about a user from the activity log.', 'wp-security-audit-log' ),
					__( 'Deleted all the data about the user <strong>%user%</strong> from the activity log.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Role', 'wp-security-audit-log' ) => '%Role%',
						esc_html__( 'First name', 'wp-security-audit-log' )    => '%FirstName%',
						esc_html__( 'Last name', 'wp-security-audit-log' )     => '%LastName%',
					),
					array(),
					'wp-activity-log',
					'deleted',
				),
				array(
					6039,
					WSAL_CRITICAL,
					esc_html__( 'Deleted all the data of a specific type from the activity log.', 'wp-security-audit-log' ),
					esc_html__( 'Deleted all the data about the %deleted_data_type% %deleted_data% from the activity log.', 'wp-security-audit-log' ),
					array(),
					array(),
					'wp-activity-log',
					'deleted',
				),

				array(
					6043,
					WSAL_HIGH,
					esc_html__( 'Some WP Activity Log plugin settings on this site were propagated and overridden from the MainWP dashboard', 'wp-security-audit-log' ),
					__( 'Some <strong>WP Activity Log</strong> plugin settings on this site were propagated and overridden from the MainWP dashboard.', 'wp-security-audit-log' ),
					array(),
					array(),
					'wp-activity-log',
					'modified',
				),

				array(
					6046,
					WSAL_LOW,
					esc_html__( 'Changed the status of the Login Page Notification', 'wp-security-audit-log' ),
					__( 'Changed the status of the <strong>Login Page Notification.</strong>', 'wp-security-audit-log' ),
					array(),
					array(),
					'wp-activity-log',
					'enabled',
				),
				array(
					6047,
					WSAL_LOW,
					esc_html__( 'Changed the text of the Login Page Notification', 'wp-security-audit-log' ),
					__( 'Changed the text of the <strong>Login Page Notification.</strong>', 'wp-security-audit-log' ),
					array(),
					array(),
					'wp-activity-log',
					'modified',
				),
				array(
					6048,
					WSAL_LOW,
					esc_html__( 'Changed the status of the Reverse proxy / firewall option', 'wp-security-audit-log' ),
					__( 'Changed the status of the <strong>Reverse proxy / firewall option.</strong>', 'wp-security-audit-log' ),
					array(),
					array(),
					'wp-activity-log',
					'enabled',
				),
				array(
					6049,
					WSAL_HIGH,
					esc_html__( 'Changed the Restrict plugin access setting', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Restrict plugin access</strong> setting to %new_setting%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous setting', 'wp-security-audit-log' ) => '%previous_setting%',
					),
					array(),
					'wp-activity-log',
					'modified',
				),
				array(
					6050,
					WSAL_HIGH,
					esc_html__( 'The user %user% to / from the list of users who can view the activity log', 'wp-security-audit-log' ),
					esc_html__( 'The user %user% to / from the list of users who can view the activity log.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous list of users who had access to view the activity log', 'wp-security-audit-log' ) => '%previous_users%',
					),
					array(),
					'wp-activity-log',
					'added',
				),
				array(
					6051,
					WSAL_MEDIUM,
					esc_html__( 'Changed the status of the Hide plugin in plugins page setting', 'wp-security-audit-log' ),
					__( 'Changed the status of the <strong>Hide plugin in plugins page</strong> setting.', 'wp-security-audit-log' ),
					array(),
					array(),
					'wp-activity-log',
					'enabled',
				),
				array(
					6052,
					WSAL_HIGH,
					esc_html__( 'Changed the Activity log retention setting', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Activity log retention</strong> to %new_setting%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous setting', 'wp-security-audit-log' ) => '%previous_setting%',
					),
					array(),
					'wp-activity-log',
					'modified',
				),
				array(
					6053,
					WSAL_LOW,
					esc_html__( 'A user was added to / from the list of excluded users from the activity log', 'wp-security-audit-log' ),
					esc_html__( 'The user %user% to / from the list of excluded users from the activity log.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous list of users', 'wp-security-audit-log' ) => '%previous_users%',
					),
					array(),
					'wp-activity-log',
					'added',
				),
				array(
					6054,
					WSAL_LOW,
					esc_html__( 'A user role was added to / from the list of excluded roles from the activity log', 'wp-security-audit-log' ),
					esc_html__( 'The user role %role% to / from the list of excluded roles from the activity log.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous list of users', 'wp-security-audit-log' ) => '%previous_users%',
					),
					array(),
					'wp-activity-log',
					'added',
				),
				array(
					6055,
					WSAL_LOW,
					esc_html__( 'An IP address was added to / from the list of excluded IP addresses from the activity log', 'wp-security-audit-log' ),
					esc_html__( 'The IP address %ip% to / from the list of excluded IP addresses from the activity log.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous list of IPs', 'wp-security-audit-log' ) => '%previous_ips%',
					),
					array(),
					'wp-activity-log',
					'added',
				),
				array(
					6056,
					WSAL_LOW,
					esc_html__( 'A post type was added to / from the list of excluded post types from the activity log', 'wp-security-audit-log' ),
					esc_html__( 'The post type %post_type% to / from the list of excluded post types from the activity log.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous list of Post types', 'wp-security-audit-log' ) => '%previous_types%',
					),
					array(),
					'wp-activity-log',
					'added',
				),
				array(
					6062,
					WSAL_LOW,
					esc_html__( 'A post status was added to / from the list of excluded post statuses from the activity log', 'wp-security-audit-log' ),
					esc_html__( 'The post status %post_status% to / from the list of excluded post statuses fields from the activity log.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous list of user profile Custom fields', 'wp-security-audit-log' ) => '%previous_status%',
					),
					array(),
					'wp-activity-log',
					'added',
				),
				array(
					6057,
					WSAL_LOW,
					esc_html__( 'A custom field was added to / from the list of excluded custom fields from the activity log', 'wp-security-audit-log' ),
					esc_html__( 'The custom field %custom_field% to / from the list of excluded custom fields from the activity log.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous list of Custom fields', 'wp-security-audit-log' ) => '%previous_fields%',
					),
					array(),
					'wp-activity-log',
					'added',
				),
				array(
					6058,
					WSAL_LOW,
					esc_html__( 'A custom field was added to / from the list of excluded user profile custom fields from the activity log', 'wp-security-audit-log' ),
					esc_html__( 'The custom field %custom_field% to / from the list of excluded user profile custom fields from the activity log.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous list of user profile Custom fields', 'wp-security-audit-log' ) => '%previous_fields%',
					),
					array(),
					'wp-activity-log',
					'added',
				),
				array(
					6060,
					WSAL_CRITICAL,
					esc_html__( 'An event was enabled / disabled', 'wp-security-audit-log' ),
					esc_html__( 'Changed the status of the event ID %ID%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Event ID description', 'wp-security-audit-log' ) => '%description%',
					),
					array(),
					'wp-activity-log',
					'enabled',
				),
			),

			esc_html__( 'Notifications & Integrations', 'wp-security-audit-log' ) => array(
				array(
					6310,
					WSAL_LOW,
					esc_html__( 'Changed the status of the Daily Summary of Activity Log', 'wp-security-audit-log' ),
					__( 'Changed the status of the <strong>Daily Summary of Activity Log.</strong>.', 'wp-security-audit-log' ),
					array(),
					array(),
					'wp-activity-log',
					'enabled',
				),
				array(
					6311,
					WSAL_LOW,
					esc_html__( 'Modified the recipients of the Daily Summary of Activity Log.', 'wp-security-audit-log' ),
					__( 'Modified the recipients of the <strong>Daily Summary of Activity Log</strong>.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'New recipient', 'wp-security-audit-log' ) => '%recipient%',
						esc_html__( 'Previous recipient', 'wp-security-audit-log' ) => '%previous_recipient%',
					),
					array(),
					'wp-activity-log',
					'modified',
				),
				array(
					6312,
					WSAL_LOW,
					esc_html__( 'Changed the status of a built in notification', 'wp-security-audit-log' ),
					esc_html__( 'Changed the status of the built in notification %notification_name%.', 'wp-security-audit-log' ),
					array(),
					array(),
					'wp-activity-log',
					'enabled',
				),
				array(
					6313,
					WSAL_LOW,
					esc_html__( 'Modified the recipient(s) of the built a notification', 'wp-security-audit-log' ),
					esc_html__( 'Modified the recipient(s) of the built in notification %notification_name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'New recipient(s)', 'wp-security-audit-log' ) => '%recipient%',
						esc_html__( 'Previous recipient(s)', 'wp-security-audit-log' ) => '%previous_recipient%',
					),
					array(),
					'wp-activity-log',
					'added',
				),
				array(
					6314,
					WSAL_LOW,
					esc_html__( 'Added a new custom notification', 'wp-security-audit-log' ),
					esc_html__( 'Added a new custom notification %notification_name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Recipient(s)', 'wp-security-audit-log' ) => '%recipient%',
					),
					array(),
					'wp-activity-log',
					'added',
				),
				array(
					6315,
					WSAL_LOW,
					esc_html__( 'Modified a custom notification', 'wp-security-audit-log' ),
					esc_html__( 'Modified the custom notification %notification_name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Recipient(s)', 'wp-security-audit-log' ) => '%recipient%',
					),
					array(),
					'wp-activity-log',
					'modified',
				),
				array(
					6316,
					WSAL_LOW,
					esc_html__( 'Changed the status of a custom notification', 'wp-security-audit-log' ),
					esc_html__( 'Changed the status of the custom notification %notification_name%.', 'wp-security-audit-log' ),
					array(),
					array(),
					'wp-activity-log',
					'enabled',
				),
				array(
					6317,
					WSAL_LOW,
					esc_html__( 'Deleted a custom notification', 'wp-security-audit-log' ),
					esc_html__( 'Deleted the custom notification %notification_name%.', 'wp-security-audit-log' ),
					array(),
					array(),
					'wp-activity-log',
					'deleted',
				),
				array(
					6318,
					WSAL_LOW,
					esc_html__( 'Modified a default notification template', 'wp-security-audit-log' ),
					esc_html__( 'Modified the default %template_name% notification template.', 'wp-security-audit-log' ),
					array(),
					array(),
					'wp-activity-log',
					'modified',
				),

				// Integrations.
				array(
					6320,
					WSAL_HIGH,
					esc_html__( 'Added a new integrations connection', 'wp-security-audit-log' ),
					esc_html__( 'Added / removed the integrations connection %name%', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Connection type', 'wp-security-audit-log' ) => '%type%',
					),
					array(),
					'wp-activity-log',
					'added',
				),
				array(
					6321,
					WSAL_HIGH,
					esc_html__( 'Modified an integrations connection', 'wp-security-audit-log' ),
					esc_html__( 'Modified the integrations connection %name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Connection type', 'wp-security-audit-log' ) => '%type%',
					),
					array(),
					'wp-activity-log',
					'modified',
				),
				array(
					6322,
					WSAL_HIGH,
					esc_html__( 'Deleted a integrations connection', 'wp-security-audit-log' ),
					esc_html__( 'Deleted the integrations connection %name%.', 'wp-security-audit-log' ),
					array(),
					array(),
					'wp-activity-log',
					'deleted',
				),
				array(
					6323,
					WSAL_HIGH,
					esc_html__( 'Added a new activity log mirror', 'wp-security-audit-log' ),
					esc_html__( 'Added a new activity log mirror %name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Connection used by this mirror', 'wp-security-audit-log' ) => '%connection%',
					),
					array(),
					'wp-activity-log',
					'added',
				),
				array(
					6324,
					WSAL_HIGH,
					esc_html__( 'Modified an activity log mirror', 'wp-security-audit-log' ),
					esc_html__( 'Modified the activity log mirror %name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Connection used by this mirror', 'wp-security-audit-log' ) => '%connection%',
					),
					array(),
					'wp-activity-log',
					'modified',
				),
				array(
					6325,
					WSAL_LOW,
					esc_html__( 'Changed the status of an activity log mirror', 'wp-security-audit-log' ),
					esc_html__( 'Changed the status of the activity log mirror %name%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Connection used by this mirror', 'wp-security-audit-log' ) => '%connection%',
					),
					array(),
					'wp-activity-log',
					'deleted',
				),
				array(
					6326,
					WSAL_HIGH,
					esc_html__( 'Deleted an activity log mirror', 'wp-security-audit-log' ),
					esc_html__( 'Deleted the activity log mirror %name%.', 'wp-security-audit-log' ),
					array(),
					array(),
					'wp-activity-log',
					'deleted',
				),
				array(
					6327,
					WSAL_HIGH,
					esc_html__( 'Changed the status of Logging of events to the database', 'wp-security-audit-log' ),
					__( 'Changed the status of <strong>Logging of events to the database</strong>.', 'wp-security-audit-log' ),
					array(),
					array(),
					'wp-activity-log',
					'enabled',
				),
			),

			esc_html__( 'WordPress Site Settings', 'wp-security-audit-log' ) => array(
				array(
					6001,
					WSAL_CRITICAL,
					esc_html__( 'Option Anyone Can Register in WordPress settings changed', 'wp-security-audit-log' ),
					__( 'The <strong>Membership</strong> setting <strong>Anyone can register</strong>.', 'wp-security-audit-log' ),
					array(),
					array(),
					'system-setting',
					'enabled',
				),
				array(
					6002,
					WSAL_CRITICAL,
					esc_html__( 'New User Default Role changed', 'wp-security-audit-log' ),
					__( 'Changed the <strong>New user default role</strong> WordPress setting.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous role', 'wp-security-audit-log' ) => '%OldRole%',
						esc_html__( 'New role', 'wp-security-audit-log' )      => '%NewRole%',
					),
					array(),
					'system-setting',
					'modified',
				),
				array(
					6003,
					WSAL_CRITICAL,
					esc_html__( 'WordPress Administrator Notification email changed', 'wp-security-audit-log' ),
					__( 'Change the <strong>Administrator email address</strong> in the WordPress settings.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous address', 'wp-security-audit-log' ) => '%OldEmail%',
						esc_html__( 'New address', 'wp-security-audit-log' )      => '%NewEmail%',
					),
					array(),
					'system-setting',
					'modified',
				),
				array(
					6005,
					WSAL_HIGH,
					esc_html__( 'User changes the WordPress Permalinks', 'wp-security-audit-log' ),
					__( 'Changed the <strong>WordPress permalinks</strong>.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous permalinks', 'wp-security-audit-log' ) => '%OldPattern%',
						esc_html__( 'New permalinks', 'wp-security-audit-log' )      => '%NewPattern%',
					),
					array(),
					'system-setting',
					'modified',
				),
				array(
					6008,
					WSAL_INFORMATIONAL,
					esc_html__( 'Enabled/Disabled the option Discourage search engines from indexing this site', 'wp-security-audit-log' ),
					__( 'Changed the status of the WordPress setting <strong>Search engine visibility</strong> (Discourage search engines from indexing this site)', 'wp-security-audit-log' ),
					array(),
					array(),
					'system-setting',
					'enabled',
				),
				array(
					6009,
					WSAL_MEDIUM,
					esc_html__( 'Enabled/Disabled comments on all the website', 'wp-security-audit-log' ),
					__( 'Changed the status of the WordPress setting <strong>Allow people to submit comments on new posts</strong>.', 'wp-security-audit-log' ),
					array(),
					array(),
					'system-setting',
					'enabled',
				),

				array(
					6010,
					WSAL_MEDIUM,
					esc_html__( 'Enabled/Disabled the option Comment author must fill out name and email', 'wp-security-audit-log' ),
					__( 'Changed the status of the WordPress setting <strong>.Comment author must fill out name and email</strong>.', 'wp-security-audit-log' ),
					array(),
					array(),
					'system-setting',
					'enabled',
				),
				array(
					6011,
					WSAL_MEDIUM,
					esc_html__( 'Enabled/Disabled the option Users must be logged in and registered to comment', 'wp-security-audit-log' ),
					__( 'Changed the status of the WordPress setting <strong>Users must be registered and logged in to comment</strong>.', 'wp-security-audit-log' ),
					array(),
					array(),
					'system-setting',
					'enabled',
				),
				array(
					6012,
					WSAL_INFORMATIONAL,
					esc_html__( 'Enabled/Disabled the option to automatically close comments', 'wp-security-audit-log' ),
					__( 'Changed the status of the WordPress setting <strong>Automatically close comments after %Value% days</strong>.', 'wp-security-audit-log' ),
					array(),
					array(),
					'system-setting',
					'enabled',
				),
				array(
					6013,
					WSAL_INFORMATIONAL,
					esc_html__( 'Changed the value of the option Automatically close comments', 'wp-security-audit-log' ),
					__( 'Changed the value of the WordPress setting <strong>Automatically close comments after a number of days</strong> to %NewValue%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous value', 'wp-security-audit-log' ) => '%OldValue%',
					),
					array(),
					'system-setting',
					'modified',
				),
				array(
					6014,
					WSAL_MEDIUM,
					esc_html__( 'Enabled/Disabled the option for comments to be manually approved', 'wp-security-audit-log' ),
					__( 'Changed the value of the WordPress setting <strong>Comments must be manualy approved</strong>.', 'wp-security-audit-log' ),
					array(),
					array(),
					'system-setting',
					'enabled',
				),
				array(
					6015,
					WSAL_LOW,
					esc_html__( 'Enabled/Disabled the option for an author to have previously approved comments for the comments to appear', 'wp-security-audit-log' ),
					__( 'Changed the value of the WordPress setting <strong>Comment author must have a previously approved comment</strong>.', 'wp-security-audit-log' ),
					array(),
					array(),
					'system-setting',
					'enabled',
				),
				array(
					6016,
					WSAL_LOW,
					esc_html__( 'Changed the number of links that a comment must have to be held in the queue', 'wp-security-audit-log' ),
					__( 'Changed the value of the WordPress setting <strong>Hold a comment in the queue if it contains links</strong> to %NewValue% links.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous value', 'wp-security-audit-log' ) => '%OldValue%',
					),
					array(),
					'system-setting',
					'modified',
				),
				array(
					6017,
					WSAL_INFORMATIONAL,
					esc_html__( 'Modified the list of keywords for comments moderation', 'wp-security-audit-log' ),
					esc_html__( 'Modified the list of keywords for comments moderation in WordPress.', 'wp-security-audit-log' ),
					array(),
					array(),
					'system-setting',
					'modified',
				),
				array(
					6018,
					WSAL_INFORMATIONAL,
					esc_html__( 'Modified the list of keywords for comments blacklisting', 'wp-security-audit-log' ),
					__( 'Modified the list of <strong>Disallowed comment keys</strong> (keywords) for comments blacklisting in WordPress.', 'wp-security-audit-log' ),
					array(),
					array(),
					'system-setting',
					'modified',
				),
				array(
					6024,
					WSAL_CRITICAL,
					esc_html__( 'Option WordPress Address (URL) in WordPress settings changed', 'wp-security-audit-log' ),
					__( 'Changed the <strong>WordPress address (URL)</strong> tp %new_url%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous URL', 'wp-security-audit-log' ) => '%old_url%',
					),
					array(),
					'system-setting',
					'modified',
				),
				array(
					6025,
					WSAL_CRITICAL,
					esc_html__( 'Option Site Address (URL) in WordPress settings changed', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Site address (URL)</strong> to %new_url%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous URL', 'wp-security-audit-log' ) => '%old_url%',
					),
					array(),
					'system-setting',
					'modified',
				),
				array(
					6035,
					WSAL_CRITICAL,
					esc_html__( 'Option Your homepage displays in WordPress settings changed', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Your homepage displays</strong> WordPress setting to %new_homepage%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous setting', 'wp-security-audit-log' ) => '%old_homepage%',
					),
					array(),
					'system-setting',
					'modified',
				),
				array(
					6036,
					WSAL_CRITICAL,
					esc_html__( 'Option homepage in WordPress settings changed', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Homepage</strong> in the WordPress settings to %new_page%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous page', 'wp-security-audit-log' ) => '%old_page%',
					),
					array(),
					'system-setting',
					'modified',
				),
				array(
					6037,
					WSAL_CRITICAL,
					esc_html__( 'Option posts page in WordPress settings changed', 'wp-security-audit-log' ),
					__( 'Changed the <strong> Posts</strong>  page in the WordPress settings to %new_page%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous page', 'wp-security-audit-log' ) => '%old_page%',
					),
					array(),
					'system-setting',
					'modified',
				),

				array(
					6040,
					WSAL_CRITICAL,
					esc_html__( 'Option Timezone in WordPress settings changed', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Timezone</strong> in the WordPress settings to %new_timezone%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous timezone', 'wp-security-audit-log' ) => '%old_timezone%',
					),
					array(),
					'system-setting',
					'modified',
				),
				array(
					6041,
					WSAL_CRITICAL,
					esc_html__( 'Option Date format in WordPress settings changed', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Date format</strong> in the WordPress settings to %new_date_format%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous format', 'wp-security-audit-log' ) => '%old_date_format%',
					),
					array(),
					'system-setting',
					'modified',
				),
				array(
					6042,
					WSAL_CRITICAL,
					esc_html__( 'Option Time format in WordPress settings changed', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Time format</strong> in the WordPress settings to %new_time_format%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous format', 'wp-security-audit-log' ) => '%old_time_format%',
					),
					array(),
					'system-setting',
					'modified',
				),

				array(
					6044,
					WSAL_HIGH,
					esc_html__( 'Option Automatic updates setting changed', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Automatic updates</strong> setting.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'New setting status', 'wp-security-audit-log' ) => '%updates_status%',
					),
					array(),
					'system-setting',
					'modified',
				),

				array(
					6045,
					WSAL_HIGH,
					esc_html__( 'Option Site Language setting changed', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Site Language</strong> to %new_value%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous setting', 'wp-security-audit-log' ) => '%previous_value%',
					),
					array(),
					'system-setting',
					'modified',
				),

				array(
					6059,
					WSAL_HIGH,
					esc_html__( 'Option Site Title changed', 'wp-security-audit-log' ),
					__( 'Changed the <strong>Site Title</strong> to %new_value%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Previous setting', 'wp-security-audit-log' ) => '%previous_value%',
					),
					array(),
					'system-setting',
					'modified',
				),

				array(
					6063,
					WSAL_INFORMATIONAL,
					esc_html__( 'Added Site Icon', 'wp-security-audit-log' ),
					__( 'Added a new website Site Icon %filename%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'New directory', 'wp-security-audit-log' ) => '%new_path%',
					),
					array(),
					'system-setting',
					'added',
				),

				array(
					6064,
					WSAL_INFORMATIONAL,
					esc_html__( 'Changed the Site Icon', 'wp-security-audit-log' ),
					__( 'Changed the Site Icon from %old_filename% to %filename%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Old directory', 'wp-security-audit-log' ) => '%old_path%',
						esc_html__( 'New directory', 'wp-security-audit-log' ) => '%new_path%',
					),
					array(),
					'system-setting',
					'modified',
				),

				array(
					6065,
					WSAL_INFORMATIONAL,
					esc_html__( 'Removed the Site Icon', 'wp-security-audit-log' ),
					__( 'Removed site icon %old_filename%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Old directory', 'wp-security-audit-log' ) => '%old_path%',
					),
					array(),
					'system-setting',
					'deleted',
				),
				array(
					6066,
					WSAL_INFORMATIONAL,
					esc_html__( 'New one time task (cron job) created', 'wp-security-audit-log' ),
					__( 'A new one-time task called %task_name% has been scheduled.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'The task is scheduled to run on', 'wp-security-audit-log' ) => '%timestamp%',
					),
					array(),
					'cron-job',
					'created',
				),
				array(
					6067,
					WSAL_INFORMATIONAL,
					esc_html__( 'New recurring task (cron job) created', 'wp-security-audit-log' ),
					__( 'A new recurring task (cron job) called %task_name% has been created.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Task\'s first run: ', 'wp-security-audit-log' ) => '%timestamp%',
						esc_html__( 'Task\'s interval: ', 'wp-security-audit-log' ) => '%display_name%',
					),
					array(),
					'cron-job',
					'created',
				),
				array(
					6068,
					WSAL_LOW,
					esc_html__( 'Recurring task (cron job) modified', 'wp-security-audit-log' ),
					__( 'The schedule of recurring task (cron job) called %task_name% has changed.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Task\'s old schedule: ', 'wp-security-audit-log' ) => '%old_display_name%',
						esc_html__( 'Task\'s new schedule: ', 'wp-security-audit-log' ) => '%new_display_name%',
					),
					array(),
					'cron-job',
					'modified',
				),
				array(
					6069,
					WSAL_INFORMATIONAL,
					esc_html__( 'One time task (cron job) executed', 'wp-security-audit-log' ),
					__( 'The one-time task called %task_name% has been executed.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Task\'s schedule was: ', 'wp-security-audit-log' ) => '%timestamp%',
					),
					array(),
					'cron-job',
					'executed',
				),
				array(
					6070,
					WSAL_INFORMATIONAL,
					esc_html__( 'Recurring task (cron job) executed', 'wp-security-audit-log' ),
					__( ' The recurring task (cron job) called %task_name% has been executed.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Task\'s schedule was: ', 'wp-security-audit-log' ) => '%display_name%',
					),
					array(),
					'cron-job',
					'executed',
				),
				array(
					6071,
					WSAL_MEDIUM,
					esc_html__( 'Deleted one-time task (cron job)', 'wp-security-audit-log' ),
					__( 'The one-time task  (cron job) called %task_name% has been deleted.', 'wp-security-audit-log' ),
					array(),
					array(),
					'cron-job',
					'deleted',
				),
				array(
					6072,
					WSAL_MEDIUM,
					esc_html__( 'Deleted recurring task (cron job)', 'wp-security-audit-log' ),
					__( 'The recurring task (cron job) called %task_name% has been deleted.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Task\'s schedule was: ', 'wp-security-audit-log' ) => '%display_name%',
					),
					array(),
					'cron-job',
					'deleted',
				),
			),

			esc_html__( 'Email Events', 'wp-security-audit-log' ) => array(
				array(
					6061,
					WSAL_LOW,
					esc_html__( 'Email was sent', 'wp-security-audit-log' ),
					__( 'Email was sent to %EmailAddress%.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Subject', 'wp-security-audit-log' ) => '%EmailSubject%',
					),
					array(),
					'system',
					'sent',
				),
			),

			esc_html__( 'Database Events', 'wp-security-audit-log' ) => array(
				array(
					5010,
					WSAL_LOW,
					esc_html__( 'Plugin created table', 'wp-security-audit-log' ),
					__( 'The plugin %Plugin->Name% created this table in the database.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Table', 'wp-security-audit-log' )  => '%TableNames%',
					),
					array(),
					'database',
					'created',
				),
				array(
					5011,
					WSAL_LOW,
					esc_html__( 'Plugin modified table structure', 'wp-security-audit-log' ),
					__( 'The plugin %Plugin->Name% modified the structure of a database table.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Table', 'wp-security-audit-log' )  => '%TableNames%',
					),
					array(),
					'database',
					'modified',
				),
				array(
					5012,
					WSAL_MEDIUM,
					esc_html__( 'Plugin deleted table', 'wp-security-audit-log' ),
					__( 'The plugin %Plugin->Name% deleted this table from the database.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Table', 'wp-security-audit-log' )  => '%TableNames%',
					),
					array(),
					'database',
					'deleted',
				),
				array(
					5013,
					WSAL_LOW,
					esc_html__( 'Theme created tables', 'wp-security-audit-log' ),
					__( 'The theme %Theme->Name% created this tables in the database.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Table', 'wp-security-audit-log' ) => '%TableNames%',
					),
					array(),
					'database',
					'created',
				),
				array(
					5014,
					WSAL_LOW,
					esc_html__( 'Theme modified tables structure', 'wp-security-audit-log' ),
					__( 'The theme %Theme->Name% modified the structure of this database table', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Table', 'wp-security-audit-log' ) => '%TableNames%',
					),
					array(),
					'database',
					'modified',
				),
				array(
					5015,
					WSAL_MEDIUM,
					esc_html__( 'Theme deleted tables', 'wp-security-audit-log' ),
					__( 'The theme %Theme->Name% deleted this table from the database.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Tables', 'wp-security-audit-log' ) => '%TableNames%',
					),
					array(),
					'database',
					'deleted',
				),
				array(
					5016,
					WSAL_HIGH,
					esc_html__( 'Unknown component created tables', 'wp-security-audit-log' ),
					esc_html__( 'An unknown component created these tables in the database.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Tables', 'wp-security-audit-log' ) => '%TableNames%',
					),
					array(),
					'database',
					'created',
				),
				array(
					5017,
					WSAL_HIGH,
					esc_html__( 'Unknown component modified tables structure', 'wp-security-audit-log' ),
					esc_html__( 'An unknown component modified the structure of these database tables.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Tables', 'wp-security-audit-log' ) => '%TableNames%',
					),
					array(),
					'database',
					'modified',
				),
				array(
					5018,
					WSAL_HIGH,
					esc_html__( 'Unknown component deleted tables', 'wp-security-audit-log' ),
					esc_html__( 'An unknown component deleted these tables from the database.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Tables', 'wp-security-audit-log' ) => '%TableNames%',
					),
					array(),
					'database',
					'deleted',
				),
				array(
					5022,
					WSAL_HIGH,
					esc_html__( 'WordPress created tables', 'wp-security-audit-log' ),
					esc_html__( 'WordPress has created these tables in the database.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Tables', 'wp-security-audit-log' ) => '%TableNames%',
					),
					array(),
					'database',
					'created',
				),
				array(
					5023,
					WSAL_HIGH,
					esc_html__( 'WordPress modified tables structure', 'wp-security-audit-log' ),
					esc_html__( 'WordPress modified the structure of these database tables.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Tables', 'wp-security-audit-log' ) => '%TableNames%',
					),
					array(),
					'database',
					'modified',
				),
				array(
					5024,
					WSAL_HIGH,
					esc_html__( 'WordPress deleted tables', 'wp-security-audit-log' ),
					esc_html__( 'WordPress deleted these tables from the database.', 'wp-security-audit-log' ),
					array(
						esc_html__( 'Tables', 'wp-security-audit-log' ) => '%TableNames%',
					),
					array(),
					'database',
					'deleted',
				),
			),
		),
	);

	// Create list of default alerts.
	Alert_Manager::register_group(
		$wsal_default_events
	);

	// Dummy item to hold WFCM installer.
	if ( function_exists( 'is_plugin_active' ) && ! defined( 'WFCM_PLUGIN_FILE' ) ) {
		$file_changes_tab = array(
			esc_html__( 'File Changes', 'wp-security-audit-log' ) => array(
				esc_html__( 'Monitor File Changes', 'wp-security-audit-log' ) => array(
					array(
						99999,
						WSAL_HIGH,
						esc_html__( 'Dummy', 'wp-security-audit-log' ),
						'',
						array(),
						array(),
						'file',
						'modified',
					),
				),
			),
		);
		Alert_Manager::register_group( $file_changes_tab );
	}

	// Load Custom alerts.
	wsal_load_include_custom_files();
}
