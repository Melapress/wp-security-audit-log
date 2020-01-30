<?php
/**
 * Alerts file.
 *
 * Alerts are defined in this file.
 *
 * @package Wsal
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
defined( 'E_DEBUG' ) || define( 'E_DEBUG', 'E_DEBUG' );
defined( 'E_RECOVERABLE_ERROR' ) || define( 'E_RECOVERABLE_ERROR', 'E_RECOVERABLE_ERROR' );
defined( 'E_DEPRECATED' ) || define( 'E_DEPRECATED', 'E_DEPRECATED' );
defined( 'E_USER_DEPRECATED' ) || define( 'E_USER_DEPRECATED', 'E_USER_DEPRECATED' );

/**
 * Load Custom Alerts from uploads/wp-security-audit-log/custom-alerts.php if exists
 *
 * @param WpSecurityAuditLog $wsal - Instance of main plugin.
 */
function load_include_custom_file( $wsal ) {
	// Custom alerts can be added via a special file inside the file and dir
	// wp-uploads/wp-security-audit-log/custom-alerts.php.
	$upload_dir       = wp_upload_dir();
	$uploads_dir_path = trailingslashit( $upload_dir['basedir'] ) . 'wp-security-audit-log';

	/*
	 * Get an array of directories to loop through to add custom alerts.
	 *
	 * Passed through a filter so other plugins or code can add own custom
	 * alerts files by adding the containing directory to this array.
	 *
	 * @since 3.5.1 - Added the `wsal_custom_alerts_dirs` filter.
	 */
	$paths = apply_filters( 'wsal_custom_alerts_dirs', array( $uploads_dir_path ) );
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

	$wsal->constants->UseConstants(
		array(
			// Default PHP constants.
			array(
				'name'        => 'E_ERROR',
				'description' => __( 'Fatal run-time error.', 'wp-security-audit-log' ),
			),
			array(
				'name'        => 'E_WARNING',
				'description' => __( 'Run-time warning (non-fatal error).', 'wp-security-audit-log' ),
			),
			array(
				'name'        => 'E_PARSE',
				'description' => __( 'Compile-time parse error.', 'wp-security-audit-log' ),
			),
			array(
				'name'        => 'E_NOTICE',
				'description' => __( 'Run-time notice.', 'wp-security-audit-log' ),
			),
			array(
				'name'        => 'E_CORE_ERROR',
				'description' => __( 'Fatal error that occurred during startup.', 'wp-security-audit-log' ),
			),
			array(
				'name'        => 'E_CORE_WARNING',
				'description' => __( 'Warnings that occurred during startup.', 'wp-security-audit-log' ),
			),
			array(
				'name'        => 'E_COMPILE_ERROR',
				'description' => __( 'Fatal compile-time error.', 'wp-security-audit-log' ),
			),
			array(
				'name'        => 'E_COMPILE_WARNING',
				'description' => __( 'Compile-time warning.', 'wp-security-audit-log' ),
			),
			array(
				'name'        => 'E_USER_ERROR',
				'description' => __( 'User-generated error message.', 'wp-security-audit-log' ),
			),
			array(
				'name'        => 'E_USER_WARNING',
				'description' => __( 'User-generated warning message.', 'wp-security-audit-log' ),
			),
			array(
				'name'        => 'E_USER_NOTICE',
				'description' => __( 'User-generated notice message.', 'wp-security-audit-log' ),
			),
			array(
				'name'        => 'E_STRICT',
				'description' => __( 'Non-standard/optimal code warning.', 'wp-security-audit-log' ),
			),
			array(
				'name'        => 'E_RECOVERABLE_ERROR',
				'description' => __( 'Catchable fatal error.', 'wp-security-audit-log' ),
			),
			array(
				'name'        => 'E_DEPRECATED',
				'description' => __( 'Run-time deprecation notices.', 'wp-security-audit-log' ),
			),
			array(
				'name'        => 'E_USER_DEPRECATED',
				'description' => __( 'Run-time user deprecation notices.', 'wp-security-audit-log' ),
			),
			// Custom constants.
			array(
				'name'        => 'E_CRITICAL',
				'description' => __( 'Critical, high-impact messages.', 'wp-security-audit-log' ),
			),
			array(
				'name'        => 'E_DEBUG',
				'description' => __( 'Debug informational messages.', 'wp-security-audit-log' ),
			),
		)
	);

	$wsal->constants->AddConstant( 'WSAL_CRITICAL', 1, __( 'Critical severity events.', 'wp-security-audit-log' ) );
	$wsal->constants->AddConstant( 'WSAL_HIGH', 6, __( 'High severity events.', 'wp-security-audit-log' ) );
	$wsal->constants->AddConstant( 'WSAL_MEDIUM', 10, __( 'Medium severity events.', 'wp-security-audit-log' ) );
	$wsal->constants->AddConstant( 'WSAL_LOW', 15, __( 'Low severity events.', 'wp-security-audit-log' ) );
	$wsal->constants->AddConstant( 'WSAL_INFORMATIONAL', 20, __( 'Informational events.', 'wp-security-audit-log' ) );

	// Create list of default alerts.
	$wsal->alerts->RegisterGroup(
		array(
			__( 'Users Logins & Sessions Events', 'wp-security-audit-log' ) => array(
				__( 'User Activity', 'wp-security-audit-log' ) => array(
					array( 1000, WSAL_LOW, __( 'User logged in', 'wp-security-audit-log' ), '', 'user', 'login' ),
					array( 1001, WSAL_LOW, __( 'User logged out', 'wp-security-audit-log' ), '', 'user', 'logout' ),
					array( 1002, WSAL_MEDIUM, __( 'Login failed', 'wp-security-audit-log' ), '', 'user', 'failed-login' ),
					array( 1003, WSAL_LOW, __( 'Login failed  / non existing user', 'wp-security-audit-log' ), __( '%Attempts% failed login(s) %LineBreak% %LogFileText%', 'wp-security-audit-log' ), 'system', 'failed-login' ),
					array( 1004, WSAL_MEDIUM, __( 'Login blocked', 'wp-security-audit-log' ), __( 'Login blocked because other session(s) already exist for this user. %LineBreak% IP address: %ClientIP%', 'wp-security-audit-log' ), 'user', 'blocked' ),
					array( 1005, WSAL_LOW, __( 'User logged in with existing session(s)', 'wp-security-audit-log' ), __( 'User logged in however there are other session(s) already exist for this user. %LineBreak% IP address: %IPAddress%', 'wp-security-audit-log' ), 'user', 'login' ),
					array( 1006, WSAL_MEDIUM, __( 'User logged out all other sessions with the same username', 'wp-security-audit-log' ), __( 'Logged out all other sessions with the same user.', 'wp-security-audit-log' ), 'user', 'logout' ),
					array( 1007, WSAL_MEDIUM, __( 'User session destroyed and logged out', 'wp-security-audit-log' ), __( 'Terminated the session of another user. %LineBreak% User: %TargetUserName% %LineBreak% Session ID: %TargetSessionID%', 'wp-security-audit-log' ), 'user', 'logout' ),
					array( 1008, WSAL_MEDIUM, __( 'Switched to another user', 'wp-security-audit-log' ), __( 'Switched to another user. %LineBreak% User: %TargetUserName% %LineBreak% Role: %TargetUserRole%', 'wp-security-audit-log' ), 'user', 'login' ),
					array( 2010, WSAL_MEDIUM, __( 'User uploaded file from Uploads directory', 'wp-security-audit-log' ), __( 'Filename: %FileName% %LineBreak% Directory: %FilePath%', 'wp-security-audit-log' ), 'file', 'uploaded' ),
					array( 2011, WSAL_LOW, __( 'User deleted file from Uploads directory', 'wp-security-audit-log' ), __( 'Filename: %FileName% %LineBreak% Directory: %FilePath%', 'wp-security-audit-log' ), 'file', 'deleted' ),
				),
			),

			__( 'Content & Comments', 'wp-security-audit-log' ) => array(
				__( 'Content', 'wp-security-audit-log' ) => array(
					array( 2000, WSAL_INFORMATIONAL, __( 'User created a new post and saved it as draft', 'wp-security-audit-log' ), __( 'Created the post %PostTitle% %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status: %PostStatus% %PostUrlIfPlublished% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'post', 'created' ),
					array( 2001, WSAL_LOW, __( 'User published a post', 'wp-security-audit-log' ), __( 'Published the post %PostTitle% %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status: %PostStatus% %PostUrlIfPlublished% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'post', 'published' ),
					array( 2002, WSAL_LOW, __( 'User modified a post', 'wp-security-audit-log' ), __( 'Modified the post %PostTitle% %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %PostUrlIfPlublished% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'post', 'modified' ),
					array( 2008, WSAL_MEDIUM, __( 'User permanently deleted a post from the trash', 'wp-security-audit-log' ), __( 'Permanently deleted the post %PostTitle% %LineBreak% ID: %PostID% %LineBreak% Type: %PostType%', 'wp-security-audit-log' ), 'post', 'deleted' ),
					array( 2012, WSAL_MEDIUM, __( 'User moved a post to the trash', 'wp-security-audit-log' ), __( 'Moved the post %PostTitle% to trash %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status: %PostStatus% %PostUrlIfPlublished% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'post', 'deleted' ),
					array( 2014, WSAL_LOW, __( 'User restored a post from trash', 'wp-security-audit-log' ), __( 'Restored the post %PostTitle% from trash %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status: %PostStatus% %PostUrlIfPlublished% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'post', 'restored' ),
					array( 2017, WSAL_INFORMATIONAL, __( 'User changed post URL', 'wp-security-audit-log' ), __( 'Changed the URL of the post %PostTitle% %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status: %PostStatus% %LineBreak% Previous URL: %OldUrl% %LineBreak% New URL: %NewUrl% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'post', 'modified' ),
					array( 2019, WSAL_INFORMATIONAL, __( 'User changed post author', 'wp-security-audit-log' ), __( 'Changed the author of the post %PostTitle% %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status: %PostStatus% %LineBreak% Previous author: %OldAuthor% %LineBreak% New author: %NewAuthor% %PostUrlIfPlublished% %LineBreak% %EditorLinkPost%.', 'wp-security-audit-log' ), 'post', 'modified' ),
					array( 2021, WSAL_MEDIUM, __( 'User changed post status', 'wp-security-audit-log' ), __( 'Changed the status of the post %PostTitle% %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status was: %OldStatus% %LineBreak% New status: %NewStatus% %PostUrlIfPlublished% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'post', 'modified' ),
					array( 2025, WSAL_LOW, __( 'User changed the visibility of a post', 'wp-security-audit-log' ), __( 'Changed the visibility of the post %PostTitle% %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status: %PostStatus% %LineBreak% Visibility was: %OldVisibility% %LineBreak% Visibility is: %NewVisibility% %PostUrlIfPlublished% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'post', 'modified' ),
					array( 2027, WSAL_INFORMATIONAL, __( 'User changed the date of a post', 'wp-security-audit-log' ), __( 'Changed the date of the post %PostTitle% %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status: %PostStatus% %LineBreak% Previous date: %OldDate% %LineBreak% New date: %NewDate% %PostUrlIfPlublished% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'post', 'modified' ),
					array( 2047, WSAL_LOW, __( 'User changed the parent of a page', 'wp-security-audit-log' ), __( 'Changed the parent of the post %PostTitle% %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status: %PostStatus% %LineBreak% Previous parent: %OldParentName% %LineBreak% New parent: %NewParentName% %PostUrlIfPlublished% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'post', 'modified' ),
					array( 2048, WSAL_LOW, __( 'User changed the template of a page', 'wp-security-audit-log' ), __( 'Changed the template of the post %PostTitle% %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status: %PostStatus% %LineBreak% Previous template: %OldTemplate% %LineBreak% New template: %NewTemplate% %PostUrlIfPlublished% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'post', 'modified' ),
					array( 2049, WSAL_INFORMATIONAL, __( 'User set a post as sticky', 'wp-security-audit-log' ), __( 'Set the post %PostTitle% as sticky %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status: %PostStatus% %PostUrlIfPlublished% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'post', 'modified' ),
					array( 2050, WSAL_INFORMATIONAL, __( 'User removed post from sticky', 'wp-security-audit-log' ), __( 'Removed the post %PostTitle% from sticky %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status: %PostStatus% %PostUrlIfPlublished% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'post', 'modified' ),
					array( 2065, WSAL_LOW, __( 'User modified the content of a post', 'wp-security-audit-log' ), __( 'Modified the content of the post %PostTitle% %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status: %PostStatus% %LineBreak% %RevisionLink% %PostUrlIfPlublished% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'post', 'modified' ),
					array( 2073, WSAL_INFORMATIONAL, __( 'User submitted a post for review', 'wp-security-audit-log' ), __( 'Submitted the post %PostTitle% for review %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status: %PostStatus% %PostUrlIfPlublished% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'post', 'modified' ),
					array( 2074, WSAL_LOW, __( 'User scheduled a post', 'wp-security-audit-log' ), __( 'Scheduled the post %PostTitle% to be published on %PublishingDate% %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status: %PostStatus% %LineBreak% %PostUrlIfPlublished% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'post', 'modified' ),
					array( 2086, WSAL_INFORMATIONAL, __( 'User changed title of a post', 'wp-security-audit-log' ), __( 'Changed the title of the post %OldTitle% %LineBreak% New title: %NewTitle% %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status: %PostStatus% %PostUrlIfPlublished% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'post', 'modified' ),
					array( 2100, WSAL_INFORMATIONAL, __( 'User opened a post in the editor', 'wp-security-audit-log' ), __( 'Opened the post %PostTitle% in the editor %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status: %PostStatus% %PostUrlIfPlublished% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'post', 'opened' ),
					array( 2101, WSAL_INFORMATIONAL, __( 'User viewed a post', 'wp-security-audit-log' ), __( 'Viewed the post %PostTitle% %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status: %PostStatus% %LineBreak% URL: %PostUrl% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'post', 'viewed' ),
					array( 2106, WSAL_MEDIUM, __( 'A plugin modified a post', 'wp-security-audit-log' ), __( 'Plugin modified the post %PostTitle% %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status: %PostStatus% %PostUrlIfPlublished% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'post', 'modified' ),
					array( 2111, WSAL_LOW, __( 'User enabled/disabled comments in a post', 'wp-security-audit-log' ), __( 'The comments in the post %PostTitle% %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status: %PostStatus% %PostUrlIfPlublished% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'post', 'enabled' ),
					array( 2112, WSAL_LOW, __( 'User enabled/disabled trackbacks and pingbacks in a post', 'wp-security-audit-log' ), __( 'Pingbacks and Trackbacks in the post %PostTitle% %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status: %PostStatus% %PostUrlIfPlublished% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'post', 'enabled' ),
				),

				__( 'Tags', 'wp-security-audit-log' ) => array(
					array( 2119, WSAL_INFORMATIONAL, __( 'User added post tag', 'wp-security-audit-log' ), __( 'Added tag(s) to the post %PostTitle% %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status: %PostStatus% %LineBreak% Added tag(s): %tag% %PostUrlIfPlublished% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'post', 'modified' ),
					array( 2120, WSAL_INFORMATIONAL, __( 'User removed post tag', 'wp-security-audit-log' ), __( 'Removed tag(s) from the post %PostTitle% %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status: %PostStatus% %LineBreak% Removed tag(s): %tag% %PostUrlIfPlublished% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'post', 'modified' ),
					array( 2121, WSAL_INFORMATIONAL, __( 'User created new tag', 'wp-security-audit-log' ), __( 'Created the tag %TagName% %LineBreak% Slug: %Slug% %LineBreak% %TagLink%', 'wp-security-audit-log' ), 'tag', 'created' ),
					array( 2122, WSAL_LOW, __( 'User deleted tag', 'wp-security-audit-log' ), __( 'Deleted the tag %TagName% %LineBreak% Slug: %Slug%', 'wp-security-audit-log' ), 'tag', 'deleted' ),
					array( 2123, WSAL_INFORMATIONAL, __( 'User renamed tag', 'wp-security-audit-log' ), __( 'Old name: %old_name% %LineBreak% New name: %new_name% %LineBreak% Slug: %Slug% %LineBreak% %TagLink%', 'wp-security-audit-log' ), 'tag', 'renamed' ),
					array( 2124, WSAL_INFORMATIONAL, __( 'User changed tag slug', 'wp-security-audit-log' ), __( 'Changed the slug of the tag %tag% %LineBreak% Previous slug: %old_slug% %LineBreak% New slug: %new_slug% %LineBreak% %TagLink%', 'wp-security-audit-log' ), 'tag', 'modified' ),
					array( 2125, WSAL_INFORMATIONAL, __( 'User changed tag description', 'wp-security-audit-log' ), __( 'Changed the description of the tag %tag% %LineBreak% Slug: %Slug% %LineBreak% Previous description: %old_desc% %LineBreak% New description: %new_desc% %LineBreak% %TagLink%', 'wp-security-audit-log' ), 'tag', 'modified' ),
				),

				__( 'Categories', 'wp-security-audit-log' ) => array(
					array( 2016, WSAL_LOW, __( 'User changed post category', 'wp-security-audit-log' ), __( 'Changed the category of the post %PostTitle% %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status: %PostStatus% %LineBreak% Previous category(ies): %OldCategories% %LineBreak% New category(ies): %NewCategories% %PostUrlIfPlublished% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'post', 'modified' ),
					array( 2023, WSAL_MEDIUM, __( 'User created new category', 'wp-security-audit-log' ), __( 'Created the category %CategoryName% %LineBreak% Slug: %Slug% %LineBreak% %CategoryLink%', 'wp-security-audit-log' ), 'category', 'created' ),
					array( 2024, WSAL_MEDIUM, __( 'User deleted category', 'wp-security-audit-log' ), __( 'Deleted the category %CategoryName% %LineBreak% Slug: %Slug%', 'wp-security-audit-log' ), 'category', 'deleted' ),
					array( 2052, WSAL_LOW, __( 'Changed the parent of a category', 'wp-security-audit-log' ), __( 'Changed the parent of the category %CategoryName% %LineBreak% Slug: %Slug% %LineBreak% Previous parent: %OldParent% %LineBreak% New parent: %NewParent% %LineBreak% %CategoryLink%', 'wp-security-audit-log' ), 'category', 'modified' ),
					array( 2127, WSAL_LOW, __( 'User changed category name', 'wp-security-audit-log' ), __( 'Changed the name of the category: %new_name% %LineBreak% Slug: %Slug% %LineBreak% Previous name: %old_name% %LineBreak% %cat_link%', 'wp-security-audit-log' ), 'category', 'modified' ),
					array( 2128, WSAL_LOW, __( 'User changed category slug', 'wp-security-audit-log' ), __( 'Changed the slug of the category: %CategoryName% %LineBreak% Previous slug: %old_slug% %LineBreak% New slug: %new_slug% %LineBreak% %cat_link%', 'wp-security-audit-log' ), 'category', 'modified' ),
				),

				__( 'Custom Fields', 'wp-security-audit-log' ) => array(
					array( 2053, WSAL_LOW, __( 'User created a custom field for a post', 'wp-security-audit-log' ), __( 'Created a new custom field called %MetaKey% in the post %PostTitle% %LineBreak% Post ID: %PostID% %LineBreak% Post Type: %PostType% %LineBreak% Post Status: %PostStatus% %LineBreak% Custom field value: %MetaValue% %PostUrlIfPlublished% %LineBreak% %EditorLinkPost% %LineBreak% %MetaLink%', 'wp-security-audit-log' ), 'post', 'modified' ),
					array( 2054, WSAL_LOW, __( 'User updated a custom field value for a post', 'wp-security-audit-log' ), __( 'Modified the value of the custom field %MetaKey% in the post %PostTitle% %LineBreak% Post ID: %PostID% %LineBreak% Post Type: %PostType% %LineBreak% Post Status: %PostStatus% %LineBreak% Previous custom field value: %MetaValueOld% %LineBreak% New custom field value: %MetaValueNew% %PostUrlIfPlublished% %LineBreak% %EditorLinkPost% %LineBreak% %MetaLink%.', 'wp-security-audit-log' ), 'custom-field', 'modified' ),
					array( 2055, WSAL_MEDIUM, __( 'User deleted a custom field from a post', 'wp-security-audit-log' ), __( 'Deleted the custom field %MetaKey% from the post %PostTitle% %LineBreak% Post ID: %PostID% %LineBreak% Post Type: %PostType% %LineBreak% Post Status: %PostStatus% %PostUrlIfPlublished% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'custom-field', 'deleted' ),
					array( 2062, WSAL_LOW, __( 'User updated a custom field name for a post', 'wp-security-audit-log' ), __( 'Old custom field name: %MetaKeyOld% %LineBreak% New custom field name: %MetaKeyNew% %LineBreak% Post: %PostTitle% %LineBreak% Post ID: %PostID% %LineBreak% Post Type:%PostType% %LineBreak% Post Status: %PostStatus% %PostUrlIfPlublished% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'custom-field', 'renamed' ),
				),

				/**
				 * Alerts: Comments
				 */
				__( 'Comments', 'wp-security-audit-log' ) => array(
					array( 2090, WSAL_INFORMATIONAL, __( 'User approved a comment', 'wp-security-audit-log' ), __( 'Approved the comment posted by %Author% on the post %PostTitle% %LineBreak% Post ID: %PostID% %LineBreak% Post Type: %PostType% %LineBreak% Post Status: %PostStatus% %LineBreak% Comment ID: %CommentID% %PostUrlIfPlublished%  %LineBreak% %CommentLink%', 'wp-security-audit-log' ), 'comment', 'approved' ),
					array( 2091, WSAL_INFORMATIONAL, __( 'User unapproved a comment', 'wp-security-audit-log' ), __( 'Unapproved the comment posted by %Author% on the post %PostTitle% %LineBreak% Post ID: %PostID% %LineBreak% Post Type: %PostType% %LineBreak% Post Status: %PostStatus% %LineBreak% Comment ID: %CommentID% %PostUrlIfPlublished%  %LineBreak% %CommentLink%', 'wp-security-audit-log' ), 'comment', 'unapproved' ),
					array( 2092, WSAL_INFORMATIONAL, __( 'User replied to a comment', 'wp-security-audit-log' ), __( 'Replied to the comment posted by %Author% on the post %PostTitle% %LineBreak% Post ID: %PostID% %LineBreak% Post Type: %PostType% %LineBreak% Post Status: %PostStatus% %LineBreak% Comment ID: %CommentID% %PostUrlIfPlublished%  %LineBreak% %CommentLink%', 'wp-security-audit-log' ), 'comment', 'created' ),
					array( 2093, WSAL_LOW, __( 'User edited a comment', 'wp-security-audit-log' ), __( 'Edited the comment posted by %Author% on the post %PostTitle% %LineBreak% Post ID: %PostID% %LineBreak% Post Type: %PostType% %LineBreak% Post Status: %PostStatus% %LineBreak% Comment ID: %CommentID% %PostUrlIfPlublished%  %LineBreak% %CommentLink%', 'wp-security-audit-log' ), 'comment', 'modified' ),
					array( 2094, WSAL_INFORMATIONAL, __( 'User marked a comment as Spam', 'wp-security-audit-log' ), __( 'Marked the comment posted by %Author% on the post %PostTitle% as spam %LineBreak% Post ID: %PostID% %LineBreak% Post Type: %PostType% %LineBreak% Post Status: %PostStatus% %LineBreak% Comment ID: %CommentID% %PostUrlIfPlublished%  %LineBreak% %CommentLink%', 'wp-security-audit-log' ), 'comment', 'unapproved' ),
					array( 2095, WSAL_LOW, __( 'User marked a comment as Not Spam', 'wp-security-audit-log' ), __( 'Marked the comment posted by %Author% on the post %PostTitle% as not spam %LineBreak% Post ID: %PostID% %LineBreak% Post Type: %PostType% %LineBreak% Post Status: %PostStatus% %LineBreak% Comment ID: %CommentID% %PostUrlIfPlublished%  %LineBreak% %CommentLink%', 'wp-security-audit-log' ), 'comment', 'approved' ),
					array( 2096, WSAL_LOW, __( 'User moved a comment to trash', 'wp-security-audit-log' ), __( 'Moved the comment posted by %Author% on the post %PostTitle% to trash %LineBreak% Post ID: %PostID% %LineBreak% Post Type: %PostType% %LineBreak% Post Status: %PostStatus% %LineBreak% Comment ID: %CommentID% %PostUrlIfPlublished%  %LineBreak% %CommentLink%', 'wp-security-audit-log' ), 'comment', 'deleted' ),
					array( 2097, WSAL_INFORMATIONAL, __( 'User restored a comment from the trash', 'wp-security-audit-log' ), __( 'Restored the comment posted by %Author% on the post %PostTitle% from trash %LineBreak% Post ID: %PostID% %LineBreak% Post Type: %PostType% %LineBreak% Post Status: %PostStatus% %LineBreak% Comment ID: %CommentID% %PostUrlIfPlublished%  %LineBreak% %CommentLink%', 'wp-security-audit-log' ), 'comment', 'restored' ),
					array( 2098, WSAL_LOW, __( 'User permanently deleted a comment', 'wp-security-audit-log' ), __( 'Permanently deleted the comment posted by %Author% on the post %PostTitle% %LineBreak% Post ID: %PostID% %LineBreak% Post Type: %PostType% %LineBreak% Post Status: %PostStatus% %PostUrlIfPlublished% %LineBreak% Comment ID: %CommentID%', 'wp-security-audit-log' ), 'comment', 'deleted' ),
					array( 2099, WSAL_INFORMATIONAL, __( 'User posted a comment', 'wp-security-audit-log' ), __( 'Posted a comment on the post %PostTitle% %LineBreak% Post ID: %PostID% %LineBreak% Post Type: %PostType% %LineBreak% Post Status: %PostStatus% %LineBreak% Comment ID: %CommentID% %PostUrlIfPlublished%  %LineBreak% %CommentLink%', 'wp-security-audit-log' ), 'comment', 'created' ),
					/**
					 * IMPORTANT: This alert is depricated but should not be
					 * removed from the definitions for backwards compatibilty.
					 */
					array( 2126, WSAL_INFORMATIONAL, __( 'Visitor posted a comment', 'wp-security-audit-log' ), __( 'Posted a comment on the post %PostTitle% %LineBreak% Post ID: %PostID% %LineBreak% Post Type: %PostType% %LineBreak% Post Status: %PostStatus% %LineBreak% Comment ID: %CommentID% %PostUrlIfPlublished%  %LineBreak% %CommentLink%', 'wp-security-audit-log' ), 'comment', 'created' ),
				),

				/**
				 * Alerts: Widgets
				 */
				__( 'Widgets', 'wp-security-audit-log' ) => array(
					array( 2042, WSAL_MEDIUM, __( 'User added a new widget', 'wp-security-audit-log' ), __( 'Added a new %WidgetName% widget in  %Sidebar%.', 'wp-security-audit-log' ), 'widget', 'added' ),
					array( 2043, WSAL_HIGH, __( 'User modified a widget', 'wp-security-audit-log' ), __( 'Modified the %WidgetName% widget in %Sidebar%.', 'wp-security-audit-log' ), 'widget', 'modified' ),
					array( 2044, WSAL_MEDIUM, __( 'User deleted widget', 'wp-security-audit-log' ), __( 'Deleted the %WidgetName% widget from %Sidebar%.', 'wp-security-audit-log' ), 'widget', 'deleted' ),
					array( 2045, WSAL_LOW, __( 'User moved widget', 'wp-security-audit-log' ), __( 'Moved the %WidgetName% widget %LineBreak% From: %OldSidebar% %LineBreak% To: %NewSidebar%', 'wp-security-audit-log' ), 'widget', 'modified' ),
					array( 2071, WSAL_LOW, __( 'User changed widget position', 'wp-security-audit-log' ), __( 'Changed the position of the %WidgetName% widget in %Sidebar%.', 'wp-security-audit-log' ), 'widget', 'modified' ),
				),

				/**
				 * Alerts: Menus
				 */
				__( 'Menus', 'wp-security-audit-log' ) => array(
					array( 2078, WSAL_LOW, __( 'User created new menu', 'wp-security-audit-log' ), __( 'New menu called %MenuName%.', 'wp-security-audit-log' ), 'menu', 'created' ),
					array( 2079, WSAL_LOW, __( 'User added content to a menu', 'wp-security-audit-log' ), __( 'Added new item to the menu %MenuName% %LineBreak% Item type: %ContentType% %LineBreak% Item name: %ContentName% ', 'wp-security-audit-log' ), 'menu', 'modified' ),
					array( 2080, WSAL_LOW, __( 'User removed content from a menu', 'wp-security-audit-log' ), __( 'Removed item from the menu %MenuName% %LineBreak% Item type: %ContentType% %LineBreak% Item name: %ContentName%', 'wp-security-audit-log' ), 'menu', 'modified' ),
					array( 2081, WSAL_MEDIUM, __( 'User deleted menu', 'wp-security-audit-log' ), __( 'Deleted the menu %MenuName%', 'wp-security-audit-log' ), 'menu', 'deleted' ),
					array( 2082, WSAL_LOW, __( 'User changed menu setting', 'wp-security-audit-log' ), __( 'The setting in the %MenuName% %LineBreak% Setting: %MenuSetting%', 'wp-security-audit-log' ), 'menu', 'enabled' ),
					array( 2083, WSAL_LOW, __( 'User modified content in a menu', 'wp-security-audit-log' ), __( 'Modified an item in the menu %MenuName% %LineBreak% Item type: %ContentType% %LineBreak% Item name: %ContentName%', 'wp-security-audit-log' ), 'menu', 'modified' ),
					array( 2084, WSAL_LOW, __( 'User changed name of a menu', 'wp-security-audit-log' ), __( 'Old name: %OldMenuName% %LineBreak% New name: %NewMenuName%', 'wp-security-audit-log' ), 'menu', 'renamed' ),
					array( 2085, WSAL_LOW, __( 'User changed order of the objects in a menu', 'wp-security-audit-log' ), __( 'Changed the order of the items in the menu %MenuName%', 'wp-security-audit-log' ), 'menu', 'modified' ),
					array( 2089, WSAL_LOW, __( 'User moved objects as a sub-item', 'wp-security-audit-log' ), __( 'Menu name: %MenuName% %LineBreak% Moved item %ItemName% as a sub-item of %ParentName%', 'wp-security-audit-log' ), 'menu', 'modified' ),
				),

				/**
				 * Alerts: Custom Post Types
				 *
				 * IMPORTANT: These alerts should not be removed from here
				 * for backwards compatibilty.
				 *
				 * @deprecated 3.1.0
				 */
				__( 'Custom Post Types', 'wp-security-audit-log' ) => array(
					array( 2003, E_NOTICE, __( 'User modified a draft blog post', 'wp-security-audit-log' ), __( 'Modified the draft post with the %PostTitle%. %EditorLinkPost%.', 'wp-security-audit-log' ) ),
					array( 2029, E_NOTICE, __( 'User created a new post with custom post type and saved it as draft', 'wp-security-audit-log' ), __( 'Created a new custom post called %PostTitle% of type %PostType%. %EditorLinkPost%.', 'wp-security-audit-log' ) ),
					array( 2030, E_NOTICE, __( 'User published a post with custom post type', 'wp-security-audit-log' ), __( 'Published a custom post %PostTitle% of type %PostType%. Post URL is %PostUrl%. %EditorLinkPost%.', 'wp-security-audit-log' ) ),
					array( 2031, E_NOTICE, __( 'User modified a post with custom post type', 'wp-security-audit-log' ), __( 'Modified the custom post %PostTitle% of type %PostType%. Post URL is %PostUrl%. %EditorLinkPost%.', 'wp-security-audit-log' ) ),
					array( 2032, E_NOTICE, __( 'User modified a draft post with custom post type', 'wp-security-audit-log' ), __( 'Modified the draft custom post %PostTitle% of type is %PostType%. %EditorLinkPost%.', 'wp-security-audit-log' ) ),
					array( 2033, E_WARNING, __( 'User permanently deleted post with custom post type', 'wp-security-audit-log' ), __( 'Permanently Deleted the custom post %PostTitle% of type %PostType%.', 'wp-security-audit-log' ) ),
					array( 2034, E_WARNING, __( 'User moved post with custom post type to trash', 'wp-security-audit-log' ), __( 'Moved the custom post %PostTitle% of type %PostType% to trash. Post URL was %PostUrl%.', 'wp-security-audit-log' ) ),
					array( 2035, E_CRITICAL, __( 'User restored post with custom post type from trash', 'wp-security-audit-log' ), __( 'The custom post %PostTitle% of type %PostType% has been restored from trash. %EditorLinkPost%.', 'wp-security-audit-log' ) ),
					array( 2036, E_NOTICE, __( 'User changed the category of a post with custom post type', 'wp-security-audit-log' ), __( 'Changed the category(ies) of the custom post %PostTitle% of type %PostType% from %OldCategories% to %NewCategories%. %EditorLinkPost%.', 'wp-security-audit-log' ) ),
					array( 2037, E_NOTICE, __( 'User changed the URL of a post with custom post type', 'wp-security-audit-log' ), __( 'Changed the URL of the custom post %PostTitle% of type %PostType% from %OldUrl% to %NewUrl%. %EditorLinkPost%.', 'wp-security-audit-log' ) ),
					array( 2038, E_NOTICE, __( 'User changed the author or post with custom post type', 'wp-security-audit-log' ), __( 'Changed the author of custom post %PostTitle% of type %PostType% from %OldAuthor% to %NewAuthor%. %EditorLinkPost%.', 'wp-security-audit-log' ) ),
					array( 2039, E_NOTICE, __( 'User changed the status of post with custom post type', 'wp-security-audit-log' ), __( 'Changed the status of custom post %PostTitle% of type %PostType% from %OldStatus% to %NewStatus%. %EditorLinkPost%.', 'wp-security-audit-log' ) ),
					array( 2040, E_WARNING, __( 'User changed the visibility of a post with custom post type', 'wp-security-audit-log' ), __( 'Changed the visibility of the custom post %PostTitle% of type %PostType% from %OldVisibility% to %NewVisibility%. %EditorLinkPost%.', 'wp-security-audit-log' ) ),
					array( 2041, E_NOTICE, __( 'User changed the date of post with custom post type', 'wp-security-audit-log' ), __( 'Changed the date of the custom post %PostTitle% of type %PostType% from %OldDate% to %NewDate%. %EditorLinkPost%.', 'wp-security-audit-log' ) ),
					array( 2056, E_CRITICAL, __( 'User created a custom field for a custom post type', 'wp-security-audit-log' ), __( 'Created a new custom field %MetaKey% with value %MetaValue% in custom post %PostTitle% of type %PostType%.' . ' %EditorLinkPost%.' . '<br>%MetaLink%.', 'wp-security-audit-log' ) ),
					array( 2057, E_CRITICAL, __( 'User updated a custom field for a custom post type', 'wp-security-audit-log' ), __( 'Modified the value of the custom field %MetaKey% from %MetaValueOld% to %MetaValueNew% in custom post %PostTitle% of type %PostType%' . ' %EditorLinkPost%.' . '<br>%MetaLink%.', 'wp-security-audit-log' ) ),
					array( 2058, E_CRITICAL, __( 'User deleted a custom field from a custom post type', 'wp-security-audit-log' ), __( 'Deleted the custom field %MetaKey% with id %MetaID% from custom post %PostTitle% of type %PostType%' . ' %EditorLinkPost%.' . '<br>%MetaLink%.', 'wp-security-audit-log' ) ),
					array( 2063, E_CRITICAL, __( 'User updated a custom field name for a custom post type', 'wp-security-audit-log' ), __( 'Changed the custom field name from %MetaKeyOld% to %MetaKeyNew% in custom post %PostTitle% of type %PostType%' . ' %EditorLinkPost%.' . '<br>%MetaLink%.', 'wp-security-audit-log' ) ),
					array( 2067, E_WARNING, __( 'User modified content for a published custom post type', 'wp-security-audit-log' ), __( 'Modified the content of the published custom post type %PostTitle%. Post URL is %PostUrl%.' . '%EditorLinkPost%.', 'wp-security-audit-log' ) ),
					array( 2068, E_NOTICE, __( 'User modified content for a draft post', 'wp-security-audit-log' ), __( 'Modified the content of the draft post %PostTitle%.' . '%RevisionLink%' . ' %EditorLinkPost%.', 'wp-security-audit-log' ) ),
					array( 2070, E_NOTICE, __( 'User modified content for a draft custom post type', 'wp-security-audit-log' ), __( 'Modified the content of the draft custom post type %PostTitle%.' . '%EditorLinkPost%.', 'wp-security-audit-log' ) ),
					array( 2072, E_NOTICE, __( 'User modified content of a post', 'wp-security-audit-log' ), __( 'Modified the content of post %PostTitle% which is submitted for review.' . '%RevisionLink%' . ' %EditorLinkPost%.', 'wp-security-audit-log' ) ),
					array( 2076, E_NOTICE, __( 'User scheduled a custom post type', 'wp-security-audit-log' ), __( 'Scheduled the custom post type %PostTitle% to be published %PublishingDate%. %EditorLinkPost%.', 'wp-security-audit-log' ) ),
					array( 2088, E_NOTICE, __( 'User changed title of a custom post type', 'wp-security-audit-log' ), __( 'Changed the title of the custom post %OldTitle% to %NewTitle%. %EditorLinkPost%.', 'wp-security-audit-log' ) ),
					array( 2104, E_NOTICE, __( 'User opened a custom post type in the editor', 'wp-security-audit-log' ), __( 'Opened the custom post %PostTitle% of type %PostType% in the editor. View the post: %EditorLinkPost%.', 'wp-security-audit-log' ) ),
					array( 2105, E_NOTICE, __( 'User viewed a custom post type', 'wp-security-audit-log' ), __( 'Viewed the custom post %PostTitle% of type %PostType%. View the post: %PostUrl%.', 'wp-security-audit-log' ) ),
					array( 5021, E_CRITICAL, __( 'A plugin created a custom post', 'wp-security-audit-log' ), __( 'A plugin automatically created the following custom post: %PostTitle%.', 'wp-security-audit-log' ) ),
					array( 5027, E_CRITICAL, __( 'A plugin deleted a custom post', 'wp-security-audit-log' ), __( 'A plugin automatically deleted the following custom post: %PostTitle%.', 'wp-security-audit-log' ) ),
					array( 2108, E_NOTICE, __( 'A plugin modified a custom post', 'wp-security-audit-log' ), __( 'Plugin modified the custom post %PostTitle%. View the post: %EditorLinkPost%.', 'wp-security-audit-log' ) ),
				),

				/**
				 * Alerts: Pages
				 *
				 * IMPORTANT: These alerts should not be removed from here
				 * for backwards compatibilty.
				 *
				 * @deprecated 3.1.0
				 */
				__( 'Pages', 'wp-security-audit-log' ) => array(
					array( 2004, E_NOTICE, __( 'User created a new WordPress page and saved it as draft', 'wp-security-audit-log' ), __( 'Created a new page called %PostTitle% and saved it as draft. %EditorLinkPage%.', 'wp-security-audit-log' ) ),
					array( 2005, E_NOTICE, __( 'User published a WordPress page', 'wp-security-audit-log' ), __( 'Published a page called %PostTitle%. Page URL is %PostUrl%. %EditorLinkPage%.', 'wp-security-audit-log' ) ),
					array( 2006, E_NOTICE, __( 'User modified a published WordPress page', 'wp-security-audit-log' ), __( 'Modified the published page %PostTitle%. Page URL is %PostUrl%. %EditorLinkPage%.', 'wp-security-audit-log' ) ),
					array( 2007, E_NOTICE, __( 'User modified a draft WordPress page', 'wp-security-audit-log' ), __( 'Modified the draft page %PostTitle%. Page ID is %PostID%. %EditorLinkPage%.', 'wp-security-audit-log' ) ),
					array( 2009, E_WARNING, __( 'User permanently deleted a page from the trash', 'wp-security-audit-log' ), __( 'Permanently deleted the page %PostTitle%.', 'wp-security-audit-log' ) ),
					array( 2013, E_WARNING, __( 'User moved WordPress page to the trash', 'wp-security-audit-log' ), __( 'Moved the page %PostTitle% to trash. Page URL was %PostUrl%.', 'wp-security-audit-log' ) ),
					array( 2015, E_CRITICAL, __( 'User restored a WordPress page from trash', 'wp-security-audit-log' ), __( 'Page %PostTitle% has been restored from trash. %EditorLinkPage%.', 'wp-security-audit-log' ) ),
					array( 2018, E_NOTICE, __( 'User changed page URL', 'wp-security-audit-log' ), __( 'Changed the URL of the page %PostTitle% from %OldUrl% to %NewUrl%. %EditorLinkPage%.', 'wp-security-audit-log' ) ),
					array( 2020, E_NOTICE, __( 'User changed page author', 'wp-security-audit-log' ), __( 'Changed the author of the page %PostTitle% from %OldAuthor% to %NewAuthor%. %EditorLinkPage%.', 'wp-security-audit-log' ) ),
					array( 2022, E_NOTICE, __( 'User changed page status', 'wp-security-audit-log' ), __( 'Changed the status of the page %PostTitle% from %OldStatus% to %NewStatus%. %EditorLinkPage%.', 'wp-security-audit-log' ) ),
					array( 2026, E_WARNING, __( 'User changed the visibility of a page post', 'wp-security-audit-log' ), __( 'Changed the visibility of the page %PostTitle% from %OldVisibility% to %NewVisibility%. %EditorLinkPage%.', 'wp-security-audit-log' ) ),
					array( 2028, E_NOTICE, __( 'User changed the date of a page post', 'wp-security-audit-log' ), __( 'Changed the date of the page %PostTitle% from %OldDate% to %NewDate%. %EditorLinkPage%.', 'wp-security-audit-log' ) ),
					array( 2059, E_CRITICAL, __( 'User created a custom field for a page', 'wp-security-audit-log' ), __( 'Created a new custom field called %MetaKey% with value %MetaValue% in the page %PostTitle%' . ' %EditorLinkPage%.' . '<br>%MetaLink%.', 'wp-security-audit-log' ) ),
					array( 2060, E_CRITICAL, __( 'User updated a custom field value for a page', 'wp-security-audit-log' ), __( 'Modified the value of the custom field %MetaKey% from %MetaValueOld% to %MetaValueNew% in the page %PostTitle%' . ' %EditorLinkPage%.' . '<br>%MetaLink%.', 'wp-security-audit-log' ) ),
					array( 2061, E_CRITICAL, __( 'User deleted a custom field from a page', 'wp-security-audit-log' ), __( 'Deleted the custom field %MetaKey% with id %MetaID% from page %PostTitle%' . ' %EditorLinkPage%.' . '<br>%MetaLink%.', 'wp-security-audit-log' ) ),
					array( 2064, E_CRITICAL, __( 'User updated a custom field name for a page', 'wp-security-audit-log' ), __( 'Changed the custom field name from %MetaKeyOld% to %MetaKeyNew% in the page %PostTitle%' . ' %EditorLinkPage%.' . '<br>%MetaLink%.', 'wp-security-audit-log' ) ),
					array( 2066, E_WARNING, __( 'User modified content for a published page', 'wp-security-audit-log' ), __( 'Modified the content of the published page %PostTitle%. Page URL is %PostUrl%. %RevisionLink% %EditorLinkPage%.', 'wp-security-audit-log' ) ),
					array( 2069, E_NOTICE, __( 'User modified content for a draft page', 'wp-security-audit-log' ), __( 'Modified the content of draft page %PostTitle%.' . '%RevisionLink%' . ' %EditorLinkPage%.', 'wp-security-audit-log' ) ),
					array( 2075, E_NOTICE, __( 'User scheduled a page', 'wp-security-audit-log' ), __( 'Scheduled the page %PostTitle% to be published %PublishingDate%.' . ' %EditorLinkPage%.', 'wp-security-audit-log' ) ),
					array( 2087, E_NOTICE, __( 'User changed title of a page', 'wp-security-audit-log' ), __( 'Changed the title of the page %OldTitle% to %NewTitle%.' . ' %EditorLinkPage%.', 'wp-security-audit-log' ) ),
					array( 2102, E_NOTICE, __( 'User opened a page in the editor', 'wp-security-audit-log' ), __( 'Opened the page %PostTitle% in the editor. View the page: %EditorLinkPage%.', 'wp-security-audit-log' ) ),
					array( 2103, E_NOTICE, __( 'User viewed a page', 'wp-security-audit-log' ), __( 'Viewed the page %PostTitle%. View the page: %PostUrl%.', 'wp-security-audit-log' ) ),
					array( 2113, E_NOTICE, __( 'User disabled Comments/Trackbacks and Pingbacks on a draft post', 'wp-security-audit-log' ), __( 'Disabled %Type% on the draft post %PostTitle%. View the post: %PostUrl%.', 'wp-security-audit-log' ) ),
					array( 2114, E_NOTICE, __( 'User enabled Comments/Trackbacks and Pingbacks on a draft post', 'wp-security-audit-log' ), __( 'Enabled %Type% on the draft post %PostTitle%. View the post: %PostUrl%.', 'wp-security-audit-log' ) ),
					array( 2115, E_NOTICE, __( 'User disabled Comments/Trackbacks and Pingbacks on a published page', 'wp-security-audit-log' ), __( 'Disabled %Type% on the published page %PostTitle%. View the page: %PostUrl%.', 'wp-security-audit-log' ) ),
					array( 2116, E_NOTICE, __( 'User enabled Comments/Trackbacks and Pingbacks on a published page', 'wp-security-audit-log' ), __( 'Enabled %Type% on the published page %PostTitle%. View the page: %PostUrl%.', 'wp-security-audit-log' ) ),
					array( 2117, E_NOTICE, __( 'User disabled Comments/Trackbacks and Pingbacks on a draft page', 'wp-security-audit-log' ), __( 'Disabled %Type% on the draft page %PostTitle%. View the page: %PostUrl%.', 'wp-security-audit-log' ) ),
					array( 2118, E_NOTICE, __( 'User enabled Comments/Trackbacks and Pingbacks on a draft page', 'wp-security-audit-log' ), __( 'Enabled %Type% on the draft page %PostTitle%. View the page: %PostUrl%.', 'wp-security-audit-log' ) ),
					array( 5020, E_CRITICAL, __( 'A plugin created a page', 'wp-security-audit-log' ), __( 'A plugin automatically created the following page: %PostTitle%.', 'wp-security-audit-log' ) ),
					array( 5026, E_CRITICAL, __( 'A plugin deleted a page', 'wp-security-audit-log' ), __( 'A plugin automatically deleted the following page: %PostTitle%.', 'wp-security-audit-log' ) ),
					array( 2107, E_NOTICE, __( 'A plugin modified a page', 'wp-security-audit-log' ), __( 'Plugin modified the page %PostTitle%. View the page: %EditorLinkPage%.', 'wp-security-audit-log' ) ),
				),
			),

			__( 'User Accounts', 'wp-security-audit-log' ) => array(
				__( 'User Profiles', 'wp-security-audit-log' ) => array(
					array( 4000, WSAL_CRITICAL, __( 'New user was created on WordPress', 'wp-security-audit-log' ), __( 'New user: %NewUserData->Username% %LineBreak% Role: %NewUserData->Roles% %LineBreak% First name: %NewUserData->FirstName% %LineBreak% Last name: %NewUserData->LastName% %LineBreak% %EditUserLink%', 'wp-security-audit-log' ), 'user', 'created' ),
					array( 4001, WSAL_CRITICAL, __( 'User created another WordPress user', 'wp-security-audit-log' ), __( 'New user: %NewUserData->Username% %LineBreak% Role: %NewUserData->Roles% %LineBreak% First name: %NewUserData->FirstName% %LineBreak% Last name: %NewUserData->LastName% %LineBreak% %EditUserLink%', 'wp-security-audit-log' ), 'user', 'created' ),
					array( 4002, WSAL_CRITICAL, __( 'The role of a user was changed by another WordPress user', 'wp-security-audit-log' ), __( 'Changed the role of the user %TargetUsername% %LineBreak% New role: %NewRole% %LineBreak% Previous role: %OldRole% %LineBreak% First name: %FirstName% %LineBreak% Last name: %LastName% %LineBreak% %EditUserLink%', 'wp-security-audit-log' ), 'user', 'modified' ),
					array( 4003, WSAL_HIGH, __( 'User has changed his or her password', 'wp-security-audit-log' ), __( 'Changed the password %LineBreak% First name: %TargetUserData->FirstName% %LineBreak% Last name: %TargetUserData->LastName% %LineBreak% %EditUserLink%', 'wp-security-audit-log' ), 'user', 'modified' ),
					array( 4004, WSAL_HIGH, __( 'User changed another user\'s password', 'wp-security-audit-log' ), __( 'Changed the password of the user %TargetUserData->Username% %LineBreak% Role: %TargetUserData->Roles% %LineBreak% First name: %TargetUserData->FirstName% %LineBreak% Last name: %TargetUserData->LastName% %LineBreak% %EditUserLink%', 'wp-security-audit-log' ), 'user', 'modified' ),
					array( 4005, WSAL_MEDIUM, __( 'User changed his or her email address', 'wp-security-audit-log' ), __( 'Changed the email address to %NewEmail% %LineBreak% Role: %Roles% %LineBreak% First name: %FirstName% %LineBreak% Last name: %LastName% %LineBreak% %EditUserLink%', 'wp-security-audit-log' ), 'user', 'modified' ),
					array( 4006, WSAL_MEDIUM, __( 'User changed another user\'s email address', 'wp-security-audit-log' ), __( 'Changed the email address of the user %TargetUsername% %LineBreak% New email address: %NewEmail% %LineBreak% Previous email address: %OldEmail% %LineBreak% Role: %Roles% %LineBreak% First name: %FirstName% %LineBreak% Last name: %LastName% %LineBreak% %EditUserLink%', 'wp-security-audit-log' ), 'user', 'modified' ),
					array( 4007, WSAL_HIGH, __( 'User was deleted by another user', 'wp-security-audit-log' ), __( 'User: %TargetUserData->Username% %LineBreak% Role: %TargetUserData->Roles% %LineBreak% First name: %NewUserData->FirstName% %LineBreak% Last name: %NewUserData->LastName%', 'wp-security-audit-log' ), 'user', 'deleted' ),
					array( 4014, WSAL_INFORMATIONAL, __( 'User opened the profile page of another user', 'wp-security-audit-log' ), __( 'The profile page of the user %TargetUsername% %LineBreak% Role: %Roles% %LineBreak% First name: %FirstName% %LineBreak% Last name: %LastName% %LineBreak% %EditUserLink%', 'wp-security-audit-log' ), 'user', 'opened' ),
					array( 4015, WSAL_LOW, __( 'User updated a custom field value for a user', 'wp-security-audit-log' ), __( 'Changed the value of a custom field in the user profile %TargetUsername% %LineBreak% Custom field: %custom_field_name% %LineBreak% Previous value: %old_value% %LineBreak% New value: %new_value% %LineBreak% Role: %Roles% %LineBreak% First name: %FirstName% %LineBreak% Last name: %LastName% %LineBreak% %EditUserLink%', 'wp-security-audit-log' ), 'user', 'modified' ),
					array( 4016, WSAL_LOW, __( 'User created a custom field value for a user', 'wp-security-audit-log' ), __( 'Created a new custom field in the user profile %TargetUsername% %LineBreak% Custom field: %custom_field_name% %LineBreak% Custom field value: %new_value% %LineBreak% Role: %Roles% %LineBreak% First name: %FirstName% %LineBreak% Last name: %LastName% %LineBreak% %EditUserLink%', 'wp-security-audit-log' ), 'user', 'modified' ),
					array( 4017, WSAL_INFORMATIONAL, __( 'User changed first name for a user', 'wp-security-audit-log' ), __( 'Changed the first name of the user %TargetUsername% %LineBreak% Previous name: %old_firstname% %LineBreak% New name: %new_firstname% %LineBreak% Role: %Roles% %LineBreak% Last name: %LastName% %LineBreak% %EditUserLink%', 'wp-security-audit-log' ), 'user', 'modified' ),
					array( 4018, WSAL_INFORMATIONAL, __( 'User changed last name for a user', 'wp-security-audit-log' ), __( 'Changed the last name of the user %TargetUsername% %LineBreak% Previous last name: %old_lastname% %LineBreak% New last name: %new_lastname% %LineBreak% Role: %Roles% %LineBreak% First name: %FirstName% %LineBreak% %EditUserLink%', 'wp-security-audit-log' ), 'user', 'modified' ),
					array( 4019, WSAL_INFORMATIONAL, __( 'User changed nickname for a user', 'wp-security-audit-log' ), __( 'Changed the nickname of the user %TargetUsername% %LineBreak% Previous nickname: %old_nickname% New nickname: %new_nickname% %LineBreak% Role: %Roles% %LineBreak% First name: %FirstName% %LineBreak% Last name: %LastName% %LineBreak% %EditUserLink%', 'wp-security-audit-log' ), 'user', 'modified' ),
					array( 4020, WSAL_LOW, __( 'User changed the display name for a user', 'wp-security-audit-log' ), __( 'Changed the display name of the user %TargetUsername% %LineBreak% Previous display name: %old_displayname% New display name: %new_displayname% %LineBreak% Role: %Roles% %LineBreak% First name: %FirstName% %LineBreak% Last name: %LastName% %LineBreak% %EditUserLink%', 'wp-security-audit-log' ), 'user', 'modified' ),
				),

				__( 'Multisite User Profiles', 'wp-security-audit-log' ) => array(
					array( 4008, WSAL_CRITICAL, __( 'User granted Super Admin privileges', 'wp-security-audit-log' ), __( 'Granted Super Admin privileges to %TargetUsername% %LineBreak% First name: %FirstName% %LineBreak% Last name: %LastName% %LineBreak% %EditUserLink%', 'wp-security-audit-log' ), 'user', 'modified' ),
					array( 4009, WSAL_CRITICAL, __( 'User revoked from Super Admin privileges', 'wp-security-audit-log' ), __( 'Revoked Super Admin privileges from %TargetUsername% %LineBreak% First name: %FirstName% %LineBreak% Last name: %LastName% %LineBreak% %EditUserLink%', 'wp-security-audit-log' ), 'user', 'modified' ),
					array( 4010, WSAL_MEDIUM, __( 'Existing user added to a site', 'wp-security-audit-log' ), __( 'Added user %TargetUsername% to site: %SiteName% %LineBreak% Role: %TargetUserRole% %LineBreak% First name: %FirstName% %LineBreak% Last name: %LastName% %LineBreak% %EditUserLink%', 'wp-security-audit-log' ), 'user', 'modified' ),
					array( 4011, WSAL_MEDIUM, __( 'User removed from site', 'wp-security-audit-log' ), __( 'Removed user %TargetUsername% from site: %SiteName% %LineBreak% Previous role: %TargetUserRole% %LineBreak% First name: %FirstName% %LineBreak% Last name: %LastName% %LineBreak% %EditUserLink%', 'wp-security-audit-log' ), 'user', 'modified' ),
					array( 4012, WSAL_CRITICAL, __( 'New network user created', 'wp-security-audit-log' ), __( 'Created a new network user %NewUserData->Username% %LineBreak% First name: %NewUserData->FirstName% %LineBreak% Last name: %NewUserData->LastName% %LineBreak% %EditUserLink%', 'wp-security-audit-log' ), 'user', 'created' ),
				),

				__( 'bbPress User Profiles', 'wp-security-audit-log' ) => array(
					array( 4013, WSAL_LOW, __( 'The forum role of a user was changed by another WordPress user', 'wp-security-audit-log' ), __( 'Change the forum role of user %TargetUsername% %LineBreak% Previous role: %OldRole% %LineBreak% New role: %NewRole% %LineBreak% First name: %FirstName% %LineBreak% Last name: %LastName% %LineBreak% %EditUserLink%', 'wp-security-audit-log' ), 'user', 'modified' ),
				),
			),

			__( 'Plugins & Themes', 'wp-security-audit-log' ) => array(
				__( 'Plugins', 'wp-security-audit-log' ) => array(
					array( 5000, WSAL_CRITICAL, __( 'User installed a plugin', 'wp-security-audit-log' ), __( 'Name: %Plugin->Name% %LineBreak% Install location: %Plugin->plugin_dir_path%', 'wp-security-audit-log' ), 'plugin', 'installed' ),
					array( 5001, WSAL_HIGH, __( 'User activated a WordPress plugin', 'wp-security-audit-log' ), __( 'Name: %PluginData->Name% %LineBreak% Install location: %PluginFile%', 'wp-security-audit-log' ), 'plugin', 'activated' ),
					array( 5002, WSAL_HIGH, __( 'User deactivated a WordPress plugin', 'wp-security-audit-log' ), __( 'Name: %PluginData->Name% %LineBreak% Install location: %PluginFile%', 'wp-security-audit-log' ), 'plugin', 'deactivated' ),
					array( 5003, WSAL_HIGH, __( 'User uninstalled a plugin', 'wp-security-audit-log' ), __( 'Name: %PluginData->Name% %LineBreak% Install location: %PluginFile%', 'wp-security-audit-log' ), 'plugin', 'uninstalled' ),
					array( 5004, WSAL_LOW, __( 'User upgraded a plugin', 'wp-security-audit-log' ), __( 'Name: %PluginData->Name% %LineBreak% Install location: %PluginFile%', 'wp-security-audit-log' ), 'plugin', 'updated' ),
					array( 5010, WSAL_LOW, __( 'Plugin created tables', 'wp-security-audit-log' ), __( 'Plugin created these tables in the database %LineBreak% Plugin: %Plugin->Name% %LineBreak% Tables: %TableNames%', 'wp-security-audit-log' ), 'database', 'created' ),
					array( 5011, WSAL_LOW, __( 'Plugin modified tables structure', 'wp-security-audit-log' ), __( 'Plugin modified the structure of these database tables %LineBreak% Plugin: %Plugin->Name% %LineBreak% Tables: %TableNames%', 'wp-security-audit-log' ), 'database', 'modified' ),
					array( 5012, WSAL_MEDIUM, __( 'Plugin deleted tables', 'wp-security-audit-log' ), __( 'Plugin deleted these tables from the database %LineBreak% Plugin: %Plugin->Name% %LineBreak% Tables: %TableNames%', 'wp-security-audit-log' ), 'database', 'deleted' ),
					array( 5019, WSAL_MEDIUM, __( 'A plugin created a post', 'wp-security-audit-log' ), __( 'Plugin %PluginName% created the post %PostTitle% %LineBreak% Post ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status: %PostStatus% %LineBreak% %EditorLinkPage%', 'wp-security-audit-log' ), 'post', 'created' ),
					array( 5025, WSAL_LOW, __( 'A plugin deleted a post', 'wp-security-audit-log' ), __( 'Plugin %PluginName% deleted the post %PostTitle% %LineBreak% Post ID: %PostID% %LineBreak% Type: %PostType%', 'wp-security-audit-log' ), 'post', 'deleted' ),
					array( 2051, WSAL_HIGH, __( 'User changed a file using the plugin editor', 'wp-security-audit-log' ), __( 'Modified a file with the plugin editor %LineBreak% File: %File%', 'wp-security-audit-log' ), 'file', 'modified' ),
				),

				__( 'Themes', 'wp-security-audit-log' ) => array(
					array( 5005, WSAL_CRITICAL, __( 'User installed a theme', 'wp-security-audit-log' ), __( 'Theme: "%Theme->Name%" %LineBreak% Install location: %Theme->get_template_directory%', 'wp-security-audit-log' ), 'theme', 'installed' ),
					array( 5006, WSAL_HIGH, __( 'User activated a theme', 'wp-security-audit-log' ), __( 'Theme "%Theme->Name%" %LineBreak% Install location: %Theme->get_template_directory%', 'wp-security-audit-log' ), 'theme', 'activated' ),
					array( 5007, WSAL_HIGH, __( 'User uninstalled a theme', 'wp-security-audit-log' ), __( 'Theme "%Theme->Name%" %LineBreak% Install location: %Theme->get_template_directory%', 'wp-security-audit-log' ), 'theme', 'deleted' ),
					array( 5013, WSAL_LOW, __( 'Theme created tables', 'wp-security-audit-log' ), __( 'Theme created these tables in the database %LineBreak% Theme: %Theme->Name% %LineBreak% Tables: %TableNames%', 'wp-security-audit-log' ), 'database', 'created' ),
					array( 5014, WSAL_LOW, __( 'Theme modified tables structure', 'wp-security-audit-log' ), __( 'Theme modified the structure of these database tables %LineBreak% Theme: %Theme->Name% %LineBreak% Tables: %TableNames%', 'wp-security-audit-log' ), 'database', 'modified' ),
					array( 5015, WSAL_MEDIUM, __( 'Theme deleted tables', 'wp-security-audit-log' ), __( 'Theme deleted these tables from the database %LineBreak% Theme: %Theme->Name% %LineBreak% Tables: %TableNames%', 'wp-security-audit-log' ), 'database', 'deleted' ),
					array( 5031, WSAL_LOW, __( 'User updated a theme', 'wp-security-audit-log' ), __( 'Name: %Theme->Name% %LineBreak% Install location: %Theme->get_template_directory%', 'wp-security-audit-log' ), 'theme', 'updated' ),
					array( 2046, WSAL_HIGH, __( 'User changed a file using the theme editor', 'wp-security-audit-log' ), __( 'Modified a file with the theme editor %LineBreak% File: %Theme%/%File%', 'wp-security-audit-log' ), 'file', 'modified' ),
				),

				__( 'Themes on Multisite', 'wp-security-audit-log' ) => array(
					array( 5008, WSAL_HIGH, __( 'Activated theme on network', 'wp-security-audit-log' ), __( 'Network activated the theme %Theme->Name% %LineBreak% Install location: %Theme->get_template_directory%', 'wp-security-audit-log' ), 'theme', 'activated' ),
					array( 5009, WSAL_MEDIUM, __( 'Deactivated theme from network', 'wp-security-audit-log' ), __( 'Network deactivated the theme %Theme->Name% %LineBreak% Install location: %Theme->get_template_directory%', 'wp-security-audit-log' ), 'theme', 'deactivated' ),
				),

				__( 'Database Events', 'wp-security-audit-log' ) => array(
					array( 5016, WSAL_HIGH, __( 'Unknown component created tables', 'wp-security-audit-log' ), __( 'An unknown component created these tables in the database %LineBreak% Tables: %TableNames%', 'wp-security-audit-log' ), 'database', 'created' ),
					array( 5017, WSAL_HIGH, __( 'Unknown component modified tables structure', 'wp-security-audit-log' ), __( 'An unknown component modified the structure of these database tables %LineBreak% Tables: %TableNames%', 'wp-security-audit-log' ), 'database', 'modified' ),
					array( 5018, WSAL_HIGH, __( 'Unknown component deleted tables', 'wp-security-audit-log' ), __( 'An unknown component deleted these tables from the database %LineBreak% Tables: %TableNames%', 'wp-security-audit-log' ), 'database', 'deleted' ),
				),
			),

			__( 'WordPress & System', 'wp-security-audit-log' ) => array(
				__( 'System', 'wp-security-audit-log' ) => array(
					array( 0000, E_CRITICAL, __( 'Unknown Error', 'wp-security-audit-log' ), __( 'An unexpected error has occurred .', 'wp-security-audit-log' ) ),
					array( 0001, E_CRITICAL, __( 'PHP error', 'wp-security-audit-log' ), __( '%Message%.', 'wp-security-audit-log' ) ),
					array( 0002, E_WARNING, __( 'PHP warning', 'wp-security-audit-log' ), __( '%Message%.', 'wp-security-audit-log' ) ),
					array( 0003, E_NOTICE, __( 'PHP notice', 'wp-security-audit-log' ), __( '%Message%.', 'wp-security-audit-log' ) ),
					array( 0004, E_CRITICAL, __( 'PHP exception', 'wp-security-audit-log' ), __( '%Message%.', 'wp-security-audit-log' ) ),
					array( 0005, E_CRITICAL, __( 'PHP shutdown error', 'wp-security-audit-log' ), __( '%Message%.', 'wp-security-audit-log' ) ),
					array( 6004, WSAL_MEDIUM, __( 'WordPress was updated', 'wp-security-audit-log' ), __( 'Updated WordPress %LineBreak% Previous version: %OldVersion% %LineBreak% New version: %NewVersion%', 'wp-security-audit-log' ), 'system', 'updated' ),
					array( 9999, E_CRITICAL, __( 'Advertising Add-ons', 'wp-security-audit-log' ), __( '%PromoName% %PromoMessage%', 'wp-security-audit-log' ) ),
				),

				__( 'Activity log plugin', 'wp-security-audit-log' ) => array(
					array( 6000, WSAL_INFORMATIONAL, __( 'Events automatically pruned by system', 'wp-security-audit-log' ), __( 'System automatically deleted %EventCount% event(s)', 'wp-security-audit-log' ), 'activity-logs', 'deleted' ),
					array( 6006, WSAL_MEDIUM, __( 'Reset plugin\'s settings to default', 'wp-security-audit-log' ), __( 'Reset the WP Security Audit Log plugin settings to default', 'wp-security-audit-log' ), 'plugin', 'modified' ),
					array( 6034, WSAL_CRITICAL, __( 'Purged the activity log', 'wp-security-audit-log' ), __( 'Purged the activity log', 'wp-security-audit-log' ), 'activity-logs', 'deleted' ),
				),

				__( 'File Changes', 'wp-security-audit-log' ) => array(
					array( 6028, WSAL_HIGH, __( 'File content has been modified', 'wp-security-audit-log' ), __( 'Content of the file on site modified %LineBreak% File: %File% %LineBreak% Location: %FileLocation%', 'wp-security-audit-log' ), 'file', 'modified' ),
					array( 6029, WSAL_CRITICAL, __( 'File added to the site', 'wp-security-audit-log' ), __( 'File added to site %LineBreak% File: %File% %LineBreak% Location: %FileLocation%', 'wp-security-audit-log' ), 'file', 'added' ),
					array( 6030, WSAL_MEDIUM, __( 'File deleted from the site', 'wp-security-audit-log' ), __( 'File deleted from site %LineBreak% File: %File% %LineBreak% Location: %FileLocation%', 'wp-security-audit-log' ), 'file', 'deleted' ),
					array( 6031, WSAL_INFORMATIONAL, __( 'File not scanned because it is bigger than the maximum file size limit', 'wp-security-audit-log' ), __( 'File not scanned because it is bigger than the maximum file size limit %LineBreak% File: %File% %LineBreak% Location: %FileLocation% %LineBreak% %FileSettings%', 'wp-security-audit-log' ), 'system', 'blocked' ),
					array( 6032, WSAL_INFORMATIONAL, __( 'File integrity scan stopped due to the limit of 1 million files', 'wp-security-audit-log' ), __( 'Your website has more than 1 million files so the file integrity scanner cannot scan them all. Contact support for more information. %LineBreak% %ContactSupport%', 'wp-security-audit-log' ), 'system', 'blocked' ),
					array( 6033, WSAL_INFORMATIONAL, __( 'File integrity scan started/stopped', 'wp-security-audit-log' ), __( 'The file integrity scan has %ScanStatus%.', 'wp-security-audit-log' ), 'system', 'started' ),
				),

				__( 'User/Visitor Actions', 'wp-security-audit-log' ) => array(
					array( 6007, WSAL_INFORMATIONAL, __( 'User requests non-existing pages (404 Error Pages)', 'wp-security-audit-log' ), __( 'Has requested a non existing page (404 error) %LineBreak% Number of times: %Attempts%', 'wp-security-audit-log' ), 'system', 'opened' ),
					array( 6023, WSAL_INFORMATIONAL, __( 'Website Visitor User requests non-existing pages (404 Error Pages)', 'wp-security-audit-log' ), __( 'Website visitor has requested a non existing page (404 error) %LineBreak% Number of times: %Attempts%', 'wp-security-audit-log' ), 'system', 'opened' ),
				),

				__( 'WordPress Site Settings', 'wp-security-audit-log' ) => array(
					array( 6001, WSAL_CRITICAL, __( 'Option Anyone Can Register in WordPress settings changed', 'wp-security-audit-log' ), __( 'The option Anyone can register', 'wp-security-audit-log' ), 'system-setting', 'enabled' ),
					array( 6002, WSAL_CRITICAL, __( 'New User Default Role changed', 'wp-security-audit-log' ), __( 'Changed the new user default role %LineBreak% Previous role: %OldRole% %LineBreak% New role: %NewRole%', 'wp-security-audit-log' ), 'system-setting', 'modified' ),
					array( 6003, WSAL_CRITICAL, __( 'WordPress Administrator Notification email changed', 'wp-security-audit-log' ), __( 'Changed the WordPress administrator notification email address %LineBreak% Previous address %OldEmail% %LineBreak% New address: %NewEmail%', 'wp-security-audit-log' ), 'system-setting', 'modified' ),
					array( 6005, WSAL_HIGH, __( 'User changes the WordPress Permalinks', 'wp-security-audit-log' ), __( 'Changed the WordPress permalinks %LineBreak% Previous permalinks: %OldPattern% %LineBreak% New permalinks: %NewPattern%', 'wp-security-audit-log' ), 'system-setting', 'modified' ),
					array( 6008, WSAL_INFORMATIONAL, __( 'Enabled/Disabled the option Discourage search engines from indexing this site', 'wp-security-audit-log' ), __( 'Discourage search engines from indexing this site.', 'wp-security-audit-log' ), 'system-setting', 'enabled' ),
					array( 6009, WSAL_MEDIUM, __( 'Enabled/Disabled comments on all the website', 'wp-security-audit-log' ), __( 'Comments on the website', 'wp-security-audit-log' ), 'system-setting', 'enabled' ),
					array( 6010, WSAL_MEDIUM, __( 'Enabled/Disabled the option Comment author must fill out name and email', 'wp-security-audit-log' ), __( 'The option Comment author must fill out name and email', 'wp-security-audit-log' ), 'system-setting', 'enabled' ),
					array( 6011, WSAL_MEDIUM, __( 'Enabled/Disabled the option Users must be logged in and registered to comment', 'wp-security-audit-log' ), __( 'The option Users must be logged in and registered to comment', 'wp-security-audit-log' ), 'system-setting', 'enabled' ),
					array( 6012, WSAL_INFORMATIONAL, __( 'Enabled/Disabled the option to automatically close comments', 'wp-security-audit-log' ), __( 'The option to Automatically close comments after %Value% days', 'wp-security-audit-log' ), 'system-setting', 'enabled' ),
					array( 6013, WSAL_INFORMATIONAL, __( 'Changed the value of the option Automatically close comments', 'wp-security-audit-log' ), __( 'Changed the value of the option to Automatically close comments after a number of days %LineBreak% Previous value: %OldValue% %LineBreak% New value: %NewValue%', 'wp-security-audit-log' ), 'system-setting', 'modified' ),
					array( 6014, WSAL_MEDIUM, __( 'Enabled/Disabled the option for comments to be manually approved', 'wp-security-audit-log' ), __( 'The option for comments to be manually approved', 'wp-security-audit-log' ), 'system-setting', 'enabled' ),
					array( 6015, WSAL_LOW, __( 'Enabled/Disabled the option for an author to have previously approved comments for the comments to appear', 'wp-security-audit-log' ), __( 'The option for an author to have previously approved comments for the comments to appear', 'wp-security-audit-log' ), 'system-setting', 'enabled' ),
					array( 6016, WSAL_LOW, __( 'Changed the number of links that a comment must have to be held in the queue', 'wp-security-audit-log' ), __( 'Changed the minimum number of links a comment must have to be held in the queue %LineBreak% Previous value: %OldValue% %LineBreak% New value: %NewValue%', 'wp-security-audit-log' ), 'system-setting', 'modified' ),
					array( 6017, WSAL_INFORMATIONAL, __( 'Modified the list of keywords for comments moderation', 'wp-security-audit-log' ), __( 'Modified the list of keywords for comments medoration', 'wp-security-audit-log' ), 'system-setting', 'modified' ),
					array( 6018, WSAL_INFORMATIONAL, __( 'Modified the list of keywords for comments blacklisting', 'wp-security-audit-log' ), __( 'Modified the list of keywords for comments blacklisting', 'wp-security-audit-log' ), 'system-setting', 'modified' ),
					array( 6024, WSAL_CRITICAL, __( 'Option WordPress Address (URL) in WordPress settings changed', 'wp-security-audit-log' ), __( 'Changed the WordPress address (URL) %LineBreak% Previous URL: %old_url% %LineBreak% New URL: %new_url%', 'wp-security-audit-log' ), 'system-setting', 'modified' ),
					array( 6025, WSAL_CRITICAL, __( 'Option Site Address (URL) in WordPress settings changed', 'wp-security-audit-log' ), __( 'Changed the site address (URL) %LineBreak% Previous URL: %old_url% %LineBreak% New URL: %new_url%', 'wp-security-audit-log' ), 'system-setting', 'modified' ),
				),
			),

			__( 'bbPress Forums', 'wp-security-audit-log' ) => array(
				__( 'Forums', 'wp-security-audit-log' ) => array(
					array( 8000, WSAL_INFORMATIONAL, __( 'User created new forum', 'wp-security-audit-log' ), __( 'New forum called %ForumName% %LineBreak% %EditorLinkForum%', 'wp-security-audit-log' ), 'bbpress-forum', 'created' ),
					array( 8001, WSAL_MEDIUM, __( 'User changed status of a forum', 'wp-security-audit-log' ), __( 'Changed the status of the forum %ForumName% %LineBreak% Previous Status: %OldStatus% %LineBreak% New Status: %NewStatus% %LineBreak% %EditorLinkForum%', 'wp-security-audit-log' ), 'bbpress-forum', 'modified' ),
					array( 8002, WSAL_MEDIUM, __( 'User changed visibility of a forum', 'wp-security-audit-log' ), __( 'Changed the visibility of the forum %ForumName% %LineBreak% Previous visibility: %OldVisibility% %LineBreak% New visibility: %NewVisibility% %LineBreak% %EditorLinkForum%', 'wp-security-audit-log' ), 'bbpress-forum', 'modified' ),
					array( 8003, WSAL_LOW, __( 'User changed the URL of a forum', 'wp-security-audit-log' ), __( 'Changed the URL of the forum %ForumName% %LineBreak% Previous URL: %OldUrl% %LineBreak% New URL: %NewUrl% %LineBreak% %EditorLinkForum%', 'wp-security-audit-log' ), 'bbpress-forum', 'modified' ),
					array( 8004, WSAL_INFORMATIONAL, __( 'User changed order of a forum', 'wp-security-audit-log' ), __( 'Changed the sorting order of the forum %ForumName% %LineBreak% Previous sorting order: %OldOrder% %LineBreak% New sorting order: %NewOrder% %LineBreak% %EditorLinkForum%', 'wp-security-audit-log' ), 'bbpress-forum', 'modified' ),
					array( 8005, WSAL_HIGH, __( 'User moved forum to trash', 'wp-security-audit-log' ), __( 'Moved the forum %ForumName% to trash', 'wp-security-audit-log' ), 'bbpress-forum', 'deleted' ),
					array( 8006, WSAL_HIGH, __( 'User permanently deleted forum', 'wp-security-audit-log' ), __( 'Permanently deleted the forum %ForumName%', 'wp-security-audit-log' ), 'bbpress-forum', 'deleted' ),
					array( 8007, WSAL_HIGH, __( 'User restored forum from trash', 'wp-security-audit-log' ), __( 'Restored the forum %ForumName% from trash', 'wp-security-audit-log' ), 'bbpress-forum', 'restored' ),
					array( 8008, WSAL_LOW, __( 'User changed the parent of a forum', 'wp-security-audit-log' ), __( 'Changed the parent of the forum %ForumName% %LineBreak% Previous parent: %OldParent% %LineBreak% New parent: %NewParent% %LineBreak% %EditorLinkForum%', 'wp-security-audit-log' ), 'bbpress-forum', 'modified' ),
					array( 8011, WSAL_LOW, __( 'User changed type of a forum', 'wp-security-audit-log' ), __( 'Changed the type of the forum %ForumName% %LineBreak% Previous type: %OldType% %LineBreak% New type: %NewType% %LineBreak% %EditorLinkForum%', 'wp-security-audit-log' ), 'bbpress-forum', 'modified' ),
				),

				__( 'bbPress Forum Topics', 'wp-security-audit-log' ) => array(
					array( 8014, WSAL_INFORMATIONAL, __( 'User created new topic', 'wp-security-audit-log' ), __( 'New topic called %TopicName% %LineBreak% %EditorLinkTopic%', 'wp-security-audit-log' ), 'bbpress-forum', 'created' ),
					array( 8015, WSAL_INFORMATIONAL, __( 'User changed status of a topic', 'wp-security-audit-log' ), __( 'Changed the status of the topic %TopicName% %LineBreak% Previous status: %OldStatus% %LineBreak% New status: %NewStatus% %LineBreak% %EditorLinkTopic%', 'wp-security-audit-log' ), 'bbpress-forum', 'modified' ),
					array( 8016, WSAL_INFORMATIONAL, __( 'User changed type of a topic', 'wp-security-audit-log' ), __( 'Changed the type of the topic %TopicName% %LineBreak% Previous type: %OldType% %LineBreak% New type: %NewType% %LineBreak% %EditorLinkTopic%', 'wp-security-audit-log' ), 'bbpress-forum', 'modified' ),
					array( 8017, WSAL_INFORMATIONAL, __( 'User changed URL of a topic', 'wp-security-audit-log' ), __( 'Changed the URL of the topic %TopicName% %LineBreak% Previous URL: %OldUrl% %LineBreak% New URL: %NewUrl% %LineBreak% %EditorLinkTopic%', 'wp-security-audit-log' ), 'bbpress-forum', 'modified' ),
					array( 8018, WSAL_INFORMATIONAL, __( 'User changed the forum of a topic', 'wp-security-audit-log' ), __( 'Changed the forum of the topic %TopicName% %LineBreak% Previous forum: %OldForum% %LineBreak% New forum: %NewForum% %LineBreak% %EditorLinkTopic%', 'wp-security-audit-log' ), 'bbpress-forum', 'modified' ),
					array( 8019, WSAL_MEDIUM, __( 'User moved topic to trash', 'wp-security-audit-log' ), __( 'Moved the %TopicName% to trash', 'wp-security-audit-log' ), 'bbpress-forum', 'deleted' ),
					array( 8020, WSAL_MEDIUM, __( 'User permanently deleted topic', 'wp-security-audit-log' ), __( 'Permanently deleted the topic %TopicName%', 'wp-security-audit-log' ), 'bbpress-forum', 'deleted' ),
					array( 8021, WSAL_INFORMATIONAL, __( 'User restored topic from trash', 'wp-security-audit-log' ), __( 'Restored the topic %TopicName% from trash', 'wp-security-audit-log' ), 'bbpress-forum', 'restored' ),
					array( 8022, WSAL_LOW, __( 'User changed visibility of a topic', 'wp-security-audit-log' ), __( 'Changed the visibility of the topic %TopicName% %LineBreak% Previous visibility: %OldVisibility% %LineBreak% New visibility: %NewVisibility% %LineBreak% %EditorLinkTopic%', 'wp-security-audit-log' ), 'bbpress-forum', 'modified' ),
				),

				__( 'bbPress Settings', 'wp-security-audit-log' ) => array(
					array( 8009, WSAL_HIGH, __( 'User changed forum\'s role', 'wp-security-audit-log' ), __( 'Changed the forum user\'s auto role %LineBreak% Previous role: %OldRole% %LineBreak% New role: %NewRole%', 'wp-security-audit-log' ), 'bbpress', 'modified' ),
					array( 8010, WSAL_CRITICAL, __( 'User changed option of a forum', 'wp-security-audit-log' ), __( 'The option for anonymous posting on the forums', 'wp-security-audit-log' ), 'bbpress', 'enabled' ),
					array( 8012, WSAL_MEDIUM, __( 'User changed time to disallow post editing', 'wp-security-audit-log' ), __( 'Changed the time to disallow post editing in the forums %LineBreak% Previous time: %OldTime% %LineBreak% New time: %NewTime%', 'wp-security-audit-log' ), 'bbpress', 'modified' ),
					array( 8013, WSAL_HIGH, __( 'User changed the forum setting posting throttle time', 'wp-security-audit-log' ), __( 'Changed the posting throttle time in the forums %LineBreak% Previous time: %OldTime% %LineBreak% New time: %NewTime%', 'wp-security-audit-log' ), 'bbpress', 'modified' ),
				),
			),

			__( 'Multisite Network Sites', 'wp-security-audit-log' ) => array(
				__( 'MultiSite', 'wp-security-audit-log' ) => array(
					array( 7000, WSAL_CRITICAL, __( 'New site added on the network', 'wp-security-audit-log' ), __( 'New site on the network: %SiteName% %LineBreak% URL: %BlogURL%', 'wp-security-audit-log' ), 'multisite-network', 'added' ),
					array( 7001, WSAL_HIGH, __( 'Existing site archived', 'wp-security-audit-log' ), __( 'Archived the site: %SiteName% %LineBreak% URL: %BlogURL%', 'wp-security-audit-log' ), 'multisite-network', 'modified' ),
					array( 7002, WSAL_HIGH, __( 'Archived site has been unarchived', 'wp-security-audit-log' ), __( 'Unarchived the site: %SiteName% %LineBreak% URL: %BlogURL%', 'wp-security-audit-log' ), 'multisite-network', 'modified' ),
					array( 7003, WSAL_HIGH, __( 'Deactivated site has been activated', 'wp-security-audit-log' ), __( 'Activated the site: %SiteName% %LineBreak% URL: %BlogURL%', 'wp-security-audit-log' ), 'multisite-network', 'activated' ),
					array( 7004, WSAL_HIGH, __( 'Site has been deactivated', 'wp-security-audit-log' ), __( 'Deactivated the site: %SiteName% %LineBreak% URL: %BlogURL%', 'wp-security-audit-log' ), 'multisite-network', 'deactivated' ),
					array( 7005, WSAL_HIGH, __( 'Existing site deleted from network', 'wp-security-audit-log' ), __( 'The site: %SiteName% %LineBreak% URL: %BlogURL%', 'wp-security-audit-log' ), 'multisite-network', 'deleted' ),
				),
			),

			__( 'WooCommerce', 'wp-security-audit-log' ) => array(
				__( 'Products', 'wp-security-audit-log' ) => array(
					array( 9000, WSAL_LOW, __( 'User created a new product', 'wp-security-audit-log' ), __( 'Created a new product called %ProductTitle% %LineBreak% ID: %PostID% %LineBreak% Status: %ProductStatus% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'created' ),
					array( 9001, WSAL_MEDIUM, __( 'User published a product', 'wp-security-audit-log' ), __( 'Published the product called %ProductTitle% %LineBreak% ID: %PostID% %LineBreak% Status: %ProductStatus% %LineBreak% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'published' ),
					array( 9003, WSAL_LOW, __( 'User changed the category of a product', 'wp-security-audit-log' ), __( 'Changed the category of the product %ProductTitle% %LineBreak% ID: %PostID% %LineBreak% Status: %ProductStatus% %LineBreak% Previous categories: %OldCategories% %LineBreak% New categories: %NewCategories% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'modified' ),
					array( 9004, WSAL_INFORMATIONAL, __( 'User modified the short description of a product', 'wp-security-audit-log' ), __( 'Changed the short description of the product %ProductTitle% %LineBreak% ID: %PostID% %LineBreak% Status: %ProductStatus% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'modified' ),
					array( 9005, WSAL_LOW, __( 'User modified the text of a product', 'wp-security-audit-log' ), __( 'Changed the text of the product %ProductTitle% %LineBreak% ID: %PostID% %LineBreak% Status: %ProductStatus% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'modified' ),
					array( 9006, WSAL_LOW, __( 'User changed the URL of a product', 'wp-security-audit-log' ), __( 'Changed the URL of the product %ProductTitle% %LineBreak% ID: %PostID% %LineBreak% Status: %ProductStatus% %LineBreak% Previous URL: %OldUrl% %LineBreak% New URL: %NewUrl% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'modified' ),
					array( 9007, WSAL_MEDIUM, __( 'User changed the Product Data of a product', 'wp-security-audit-log' ), __( 'Changed the type of the product %ProductTitle% %LineBreak% ID: %PostID% %LineBreak% Status: %ProductStatus% %LineBreak% Previous type: %OldType% %LineBreak% New type: %NewType% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'modified' ),
					array( 9008, WSAL_INFORMATIONAL, __( 'User changed the date of a product', 'wp-security-audit-log' ), __( 'Changed the date of the product %ProductTitle% %LineBreak% ID: %PostID% %LineBreak% Status: %ProductStatus% %LineBreak% Previous date: %OldDate% %LineBreak% New date: %NewDate% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'modified' ),
					array( 9009, WSAL_MEDIUM, __( 'User changed the visibility of a product', 'wp-security-audit-log' ), __( 'Changed the visibility of the product %ProductTitle% %LineBreak% ID: %PostID% %LineBreak% Status: %ProductStatus% %LineBreak% Previous visibility: %OldVisibility% %LineBreak% New visibility: %NewVisibility% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'modified' ),
					array( 9010, WSAL_MEDIUM, __( 'User modified the product', 'wp-security-audit-log' ), __( 'Modified the product %ProductTitle% %LineBreak% ID: %PostID% %LineBreak% Status: %ProductStatus% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'modified' ),
					array( 9011, E_NOTICE, __( 'User modified the draft product', 'wp-security-audit-log' ), __( 'Modified the draft product %ProductTitle%. View the product: %EditorLinkProduct%.', 'wp-security-audit-log' ), 'woocommerce-product' ),
					array( 9012, WSAL_HIGH, __( 'User moved a product to trash', 'wp-security-audit-log' ), __( 'Moved the product %ProductTitle% to trash %LineBreak% ID: %PostID% %LineBreak% Status: %ProductStatus%', 'wp-security-audit-log' ), 'woocommerce-product', 'deleted' ),
					array( 9013, WSAL_MEDIUM, __( 'User permanently deleted a product', 'wp-security-audit-log' ), __( 'Permanently deleted the product %ProductTitle% %LineBreak% ID: %PostID%', 'wp-security-audit-log' ), 'woocommerce-product', 'deleted' ),
					array( 9014, WSAL_HIGH, __( 'User restored a product from the trash', 'wp-security-audit-log' ), __( 'Restored the product %ProductTitle% from trash %LineBreak% ID: %PostID% %LineBreak% Status: %ProductStatus% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'restored' ),
					array( 9015, WSAL_MEDIUM, __( 'User changed status of a product', 'wp-security-audit-log' ), __( 'Changed the status of the product %ProductTitle% %LineBreak% ID: %PostID% %LineBreak% Previous status: %OldStatus% %LineBreak% New status: %NewStatus% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'modified' ),
					array( 9072, WSAL_INFORMATIONAL, __( 'User opened a product in the editor', 'wp-security-audit-log' ), __( 'Opened the product %ProductTitle% in the editor %LineBreak% ID: %PostID% %LineBreak% Status: %ProductStatus% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'opened' ),
					array( 9073, WSAL_INFORMATIONAL, __( 'User viewed a product', 'wp-security-audit-log' ), __( 'Viewed the product %ProductTitle% page %LineBreak% ID: %PostID% %LineBreak% Status: %ProductStatus% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'viewed' ),
					array( 9077, WSAL_MEDIUM, __( 'User renamed a product', 'wp-security-audit-log' ), __( 'Old name: %OldTitle% %LineBreak% New name: %NewTitle% %LineBreak% ID: %PostID% %LineBreak% Status: %ProductStatus% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'renamed' ),
					array( 9016, WSAL_MEDIUM, __( 'User changed type of a price', 'wp-security-audit-log' ), __( 'Changed the %PriceType% of the product %ProductTitle% %LineBreak% ID: %PostID% %LineBreak% Status: %ProductStatus% %LineBreak% Previous price: %OldPrice% %LineBreak% New price: %NewPrice% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'modified' ),
					array( 9017, WSAL_MEDIUM, __( 'User changed the SKU of a product', 'wp-security-audit-log' ), __( 'Changed the SKU of the product %ProductTitle% %LineBreak% ID: %PostID% %LineBreak% Status: %ProductStatus% %LineBreak% Previous SKU: %OldSku% %LineBreak% New SKU: %NewSku% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'modified' ),
					array( 9018, WSAL_LOW, __( 'User changed the stock status of a product', 'wp-security-audit-log' ), __( 'Changed the stock status of the product %ProductTitle% %LineBreak% ID: %PostID% %LineBreak% Status: %ProductStatus% %LineBreak% Previous stock status: %OldStatus% %LineBreak% New stock status: %NewStatus% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'modified' ),
					array( 9019, WSAL_LOW, __( 'User changed the stock quantity', 'wp-security-audit-log' ), __( 'Changed the stock quantity of the product %ProductTitle% %LineBreak% ID: %PostID% %LineBreak% Status: %ProductStatus% %LineBreak% Previous quantity: %OldValue% %LineBreak% New quantity: %NewValue% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'modified' ),
					array( 9020, WSAL_MEDIUM, __( 'User set a product type', 'wp-security-audit-log' ), __( 'Changed the type of the %NewType% product %ProductTitle% %LineBreak% ID: %PostID% %LineBreak% Status: %ProductStatus% %LineBreak% Previous type: %OldType% %LineBreak% New type: %NewType% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'modified' ),
					array( 9021, WSAL_INFORMATIONAL, __( 'User changed the weight of a product', 'wp-security-audit-log' ), __( 'Changed the weight of the product %ProductTitle% %LineBreak% ID: %PostID% %LineBreak% Status: %ProductStatus% %LineBreak% Previous weight: %OldWeight% %LineBreak% New weight: %NewWeight% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'modified' ),
					array( 9022, WSAL_INFORMATIONAL, __( 'User changed the dimensions of a product', 'wp-security-audit-log' ), __( 'Changed the %DimensionType% dimensions of the product %ProductTitle% %LineBreak% ID: %PostID% %LineBreak% Status: %ProductStatus% %LineBreak% Previous value: %OldDimension% %LineBreak% New value: %NewDimension% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'modified' ),
					array( 9023, WSAL_MEDIUM, __( 'User added the Downloadable File to a product', 'wp-security-audit-log' ), __( 'Added a downloadable file to the product %ProductTitle% %LineBreak% ID: %PostID% %LineBreak% Status: %ProductStatus% %LineBreak% File name: %FileName% %LineBreak% File URL: %FileUrl% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'modified' ),
					array( 9024, WSAL_MEDIUM, __( 'User Removed the Downloadable File from a product', 'wp-security-audit-log' ), __( 'Removed the downloadable file from the product %ProductTitle% %LineBreak% ID: %PostID% %LineBreak% Status: %ProductStatus% %LineBreak% File name: %FileName% %LineBreak% File URL: %FileUrl% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'modified' ),
					array( 9025, WSAL_INFORMATIONAL, __( 'User changed the name of a Downloadable File in a product', 'wp-security-audit-log' ), __( 'Changed the name of the downloadable file to the product %ProductTitle% %LineBreak% ID: %PostID% %LineBreak% Status: %ProductStatus% %LineBreak% Previous file name: %OldName% %LineBreak% New file name: %NewName% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'modified' ),
					array( 9026, WSAL_MEDIUM, __( 'User changed the URL of the Downloadable File in a product', 'wp-security-audit-log' ), __( 'Changed the URL of the downloadable file to the product %ProductTitle% %LineBreak% ID: %PostID% %LineBreak% Status: %ProductStatus% %LineBreak% File name: %FileName% %LineBreak% Previous URL: %OldUrl% %LineBreak% New URL: %NewUrl% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'modified' ),
					array( 9042, WSAL_INFORMATIONAL, __( 'User changed the catalog visibility of a product', 'wp-security-audit-log' ), __( 'Changed the product visibility of the product %ProductTitle% %LineBreak% ID: %PostID% %LineBreak% Status: %ProductStatus% %LineBreak% Previous setting: %OldVisibility% %LineBreak% New setting: %NewVisibility% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'modified' ),
					array( 9043, WSAL_INFORMATIONAL, __( 'User changed the setting Featured Product of a product', 'wp-security-audit-log' ), __( 'The setting Featured Product for the product %ProductTitle% %LineBreak% ID: %PostID% %LineBreak% Status: %ProductStatus% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'enabled' ),
					array( 9044, WSAL_INFORMATIONAL, __( 'User changed the Allow Backorders setting of a product', 'wp-security-audit-log' ), __( 'Changed the Allow Backorders setting for the product %ProductTitle% %LineBreak% ID: %PostID% %LineBreak% Status: %ProductStatus% %LineBreak% Previous status: %OldStatus% %LineBreak% New status: %NewStatus% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'modified' ),
					array( 9045, WSAL_MEDIUM, __( 'User added/removed products to upsell of a product', 'wp-security-audit-log' ), __( 'Products to Upsell in the product %ProductTitle% %LineBreak% ID: %PostID% %LineBreak% Status: %ProductStatus% %LineBreak% Product: %UpsellTitle% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'added' ),
					array( 9046, WSAL_MEDIUM, __( 'User added/removed products to cross-sells of a product', 'wp-security-audit-log' ), __( 'Product to Cross-sell in the product %ProductTitle% %LineBreak% ID: %PostID% %LineBreak% Status: %ProductStatus% %LineBreak% Product: %CrossSellTitle% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'added' ),
					array( 9047, WSAL_LOW, __( 'Added a new attribute of a product', 'wp-security-audit-log' ), __( 'A new attribute to the product %ProductTitle% %LineBreak% ID: %ProductID% %LineBreak% Status: %ProductStatus% %LineBreak% Attribute name: %AttributeName% %LineBreak% Attribute value: %AttributeValue% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'added' ),
					array( 9048, WSAL_LOW, __( 'Modified the value of an attribute of a product', 'wp-security-audit-log' ), __( 'Modified the value of an attribute in the product %ProductTitle% %LineBreak% ID: %ProductID% %LineBreak% Status: %ProductStatus% %LineBreak% Attribute name: %AttributeName% %LineBreak% Previous attribute value: %OldValue% %LineBreak% New attribute value: %NewValue% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'modified' ),
					array( 9049, WSAL_LOW, __( 'Changed the name of an attribute of a product', 'wp-security-audit-log' ), __( 'Changed the name of an attribute in the product %ProductTitle% %LineBreak% ID: %ProductID% %LineBreak% Status: %ProductStatus% %LineBreak% Previous attribute name: %OldValue% %LineBreak% New attribute name: %NewValue% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'modified' ),
					array( 9050, WSAL_LOW, __( 'Deleted an attribute of a product', 'wp-security-audit-log' ), __( 'An attribute from the product %ProductTitle% %LineBreak% ID: %ProductID% %LineBreak% Status: %ProductStatus% %LineBreak% Attribute name: %AttributeName% %LineBreak% Attribute value: %AttributeValue% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'deleted' ),
					array( 9051, WSAL_LOW, __( 'Set the attribute visibility of a product', 'wp-security-audit-log' ), __( 'Changed the visibility of an attribute in the product %ProductTitle% %LineBreak% ID: %ProductID% %LineBreak% Status: %ProductStatus% %LineBreak% Attribute name: %AttributeName% %LineBreak% Attribute visibility: %AttributeVisiblilty% %LineBreak% %EditorLinkProduct%', 'wp-security-audit-log' ), 'woocommerce-product', 'modified' ),
				),

				__( 'Store', 'wp-security-audit-log' ) => array(
					array( 9027, WSAL_HIGH, __( 'User changed the Weight Unit', 'wp-security-audit-log' ), __( 'Changed the weight unit of the store %LineBreak% Previous weight unit: %OldUnit% %LineBreak% New weight unit: %NewUnit%', 'wp-security-audit-log' ), 'woocommerce-store', 'modified' ),
					array( 9028, WSAL_HIGH, __( 'User changed the Dimensions Unit', 'wp-security-audit-log' ), __( 'Changed the dimensions unit of the store %LineBreak% Previous dimensions unit: %OldUnit% %LineBreak% New dimensions unit: %NewUnit%', 'wp-security-audit-log' ), 'woocommerce-store', 'modified' ),
					array( 9029, WSAL_HIGH, __( 'User changed the Base Location', 'wp-security-audit-log' ), __( 'Changed the base location %LineBreak% Previous location: %OldLocation% %LineBreak% New location: %NewLocation%', 'wp-security-audit-log' ), 'woocommerce-store', 'modified' ),
					array( 9030, WSAL_HIGH, __( 'User enabled/disabled taxes', 'wp-security-audit-log' ), __( 'Taxes in the WooCommerce store', 'wp-security-audit-log' ), 'woocommerce-store', 'enabled' ),
					array( 9031, WSAL_HIGH, __( 'User changed the currency', 'wp-security-audit-log' ), __( 'Changed the currency of the store %LineBreak% Previous currency: %OldCurrency% %LineBreak% New currency: %NewCurrency%', 'wp-security-audit-log' ), 'woocommerce-store', 'modified' ),
					array( 9032, WSAL_HIGH, __( 'User enabled/disabled the use of coupons during checkout', 'wp-security-audit-log' ), __( 'The use of coupons during checkout', 'wp-security-audit-log' ), 'woocommerce-store', 'enabled' ),
					array( 9033, WSAL_MEDIUM, __( 'User enabled/disabled guest checkout', 'wp-security-audit-log' ), __( 'Guest checkout in the store', 'wp-security-audit-log' ), 'woocommerce-store', 'enabled' ),
					array( 9034, WSAL_HIGH, __( 'User enabled/disabled cash on delivery', 'wp-security-audit-log' ), __( 'The option cash on delivery', 'wp-security-audit-log' ), 'woocommerce-store', 'enabled' ),
				),

				__( 'Payment Gateways', 'wp-security-audit-log' ) => array(
					array( 9074, WSAL_HIGH, __( 'User enabled/disabled a payment gateway', 'wp-security-audit-log' ), __( 'The payment gateway %GatewayName%', 'wp-security-audit-log' ), 'woocommerce-store', 'enabled' ),
					array( 9075, E_CRITICAL, __( 'User disabled a payment gateway', 'wp-security-audit-log' ), __( 'Disabled the payment gateway %GatewayName%', 'wp-security-audit-log' ), 'woocommerce-store' ),
					array( 9076, WSAL_HIGH, __( 'User modified a payment gateway', 'wp-security-audit-log' ), __( 'The payment gateway %GatewayName%', 'wp-security-audit-log' ), 'woocommerce-store', 'modified' ),
				),

				__( 'Tax Settings', 'wp-security-audit-log' ) => array(
					array( 9078, WSAL_LOW, __( 'User modified prices with tax option', 'wp-security-audit-log' ), __( 'Set the option that prices are %TaxStatus% of tax', 'wp-security-audit-log' ), 'woocommerce-store', 'modified' ),
					array( 9079, WSAL_LOW, __( 'User modified tax calculation base', 'wp-security-audit-log' ), __( 'Set the setting Calculate tax based on to %Setting%', 'wp-security-audit-log' ), 'woocommerce-store', 'modified' ),
					array( 9080, WSAL_MEDIUM, __( 'User modified shipping tax class', 'wp-security-audit-log' ), __( 'Set the Shipping tax class to %Setting%', 'wp-security-audit-log' ), 'woocommerce-store', 'modified' ),
					array( 9081, WSAL_MEDIUM, __( 'User enabled/disabled rounding of tax', 'wp-security-audit-log' ), __( 'Rounding of tax at subtotal level', 'wp-security-audit-log' ), 'woocommerce-store', 'enabled' ),
					array( 9082, WSAL_MEDIUM, __( 'User modified a shipping zone', 'wp-security-audit-log' ), __( 'The shipping zone %ShippingZoneName%', 'wp-security-audit-log' ), 'woocommerce-store', 'created' ),
				),

				__( 'WC Categories', 'wp-security-audit-log' ) => array(
					array( 9002, WSAL_INFORMATIONAL, __( 'User created a new product category', 'wp-security-audit-log' ), __( 'A new product category called %CategoryName% %LineBreak% Category slug is %Slug% %LineBreak% %ProductCatLink%', 'wp-security-audit-log' ), 'woocommerce-store', 'created' ),
					array( 9052, WSAL_MEDIUM, __( 'User deleted a product category', 'wp-security-audit-log' ), __( 'The product category called %CategoryName% %LineBreak% Category slug: %CategorySlug%', 'wp-security-audit-log' ), 'woocommerce-store', 'deleted' ),
					array( 9053, WSAL_INFORMATIONAL, __( 'User changed the slug of a product category', 'wp-security-audit-log' ), __( 'Changed the slug of the product category called %CategoryName% %LineBreak% Previous category slug: %OldSlug% %LineBreak% New category slug: %NewSlug% %LineBreak% %ProductCatLink%', 'wp-security-audit-log' ), 'woocommerce-store', 'modified' ),
					array( 9054, WSAL_MEDIUM, __( 'User changed the parent category of a product category', 'wp-security-audit-log' ), __( 'Changed the parent of the product category %CategoryName% %LineBreak% Category slug: %CategorySlug% Previous parent: %OldParentCat% %LineBreak% New parent: %NewParentCat% %LineBreak% %ProductCatLink%', 'wp-security-audit-log' ), 'woocommerce-store', 'modified' ),
					array( 9055, WSAL_INFORMATIONAL, __( 'User changed the display type of a product category', 'wp-security-audit-log' ), __( 'Changed the display type of the product category %CategoryName% %LineBreak% %CategorySlug% %LineBreak% Previous display type: %OldDisplayType% %LineBreak% New display type: %NewDisplayType% %LineBreak% %ProductCatLink%', 'wp-security-audit-log' ), 'woocommerce-store', 'modified' ),
					array( 9056, WSAL_LOW, __( 'User changed the name of a product category', 'wp-security-audit-log' ), __( 'Changed the name of the product category %CategoryName% %LineBreak% %CategorySlug% %LineBreak% Previous name: %OldName% %LineBreak% New name: %NewName% %LineBreak% %ProductCatLink%', 'wp-security-audit-log' ), 'woocommerce-store', 'modified' ),
				),

				__( 'Attributes', 'wp-security-audit-log' ) => array(
					array( 9057, WSAL_MEDIUM, __( 'User created a new attribute', 'wp-security-audit-log' ), __( 'A new attribute in WooCommerce called %AttributeName% %LineBreak% Attribute slug: %AttributeSlug%', 'wp-security-audit-log' ), 'woocommerce-store', 'created' ),
					array( 9058, WSAL_LOW, __( 'User deleted an attribute', 'wp-security-audit-log' ), __( 'The WooCommerce attribute called %AttributeName% %LineBreak% Attribute slug: %AttributeSlug%', 'wp-security-audit-log' ), 'woocommerce-store', 'deleted' ),
					array( 9059, WSAL_LOW, __( 'User changed the slug of an attribute', 'wp-security-audit-log' ), __( 'Changed the slug of the WooCommerce attribute %AttributeName% %LineBreak% Previous slug: %OldSlug% %LineBreak% New slug: %NewSlug%', 'wp-security-audit-log' ), 'woocommerce-store', 'modified' ),
					array( 9060, WSAL_LOW, __( 'User changed the name of an attribute', 'wp-security-audit-log' ), __( 'Changed the name of the WooCommerce attribute %AttributeName% %LineBreak% Attribute slug: %AttributeSlug% %LineBreak% Previous name: %OldName% %LineBreak% New name: %NewName%', 'wp-security-audit-log' ), 'woocommerce-store', 'modified' ),
					array( 9061, WSAL_LOW, __( 'User changed the default sort order of an attribute', 'wp-security-audit-log' ), __( 'Changed the Default Sort Order of the attribute %AttributeName% in WooCommerce %LineBreak% Attribute slug: %AttributeSlug% %LineBreak% Previous sorting order: %OldSortOrder% %LineBreak% New sorting order: %NewSortOrder%', 'wp-security-audit-log' ), 'woocommerce-store', 'modified' ),
					array( 9062, WSAL_LOW, __( 'User enabled/disabled the option Enable Archives of an attribute', 'wp-security-audit-log' ), __( 'The option Enable Archives in WooCommerce attribute %AttributeName%', 'wp-security-audit-log' ), 'woocommerce-store', 'enabled' ),
				),

				__( 'Coupons', 'wp-security-audit-log' ) => array(
					array( 9063, WSAL_LOW, __( 'User published a new coupon', 'wp-security-audit-log' ), __( 'Published a new coupon called %CouponName% %LineBreak% %EditorLinkCoupon%', 'wp-security-audit-log' ), 'woocommerce-store', 'published' ),
					array( 9064, WSAL_LOW, __( 'User changed the discount type of a coupon', 'wp-security-audit-log' ), __( 'Changed the Discount Type in coupon %CouponName% %LineBreak% Previous discount type: %OldDiscountType% %LineBreak% New discount type: %NewDiscountType% %LineBreak% %EditorLinkCoupon%', 'wp-security-audit-log' ), 'woocommerce-store', 'modified' ),
					array( 9065, WSAL_LOW, __( 'User changed the coupon amount of a coupon', 'wp-security-audit-log' ), __( 'Changed the Coupon amount in coupon %CouponName% %LineBreak% Previous amount: %OldAmount% %LineBreak% New amount: %NewAmount% %LineBreak% %EditorLinkCoupon%', 'wp-security-audit-log' ), 'woocommerce-store', 'modified' ),
					array( 9066, WSAL_LOW, __( 'User changed the coupon expire date of a coupon', 'wp-security-audit-log' ), __( 'Changed the expire date of the coupon %CouponName% %LineBreak% Previous date: %OldDate% %LineBreak% New date: %NewDate% %LineBreak% %EditorLinkCoupon%', 'wp-security-audit-log' ), 'woocommerce-store', 'modified' ),
					array( 9067, WSAL_LOW, __( 'User changed the usage restriction settings of a coupon', 'wp-security-audit-log' ), __( 'Changed the Usage Restriction of the coupon %CouponName% %LineBreak% Previous usage restriction: %OldMetaValue% %LineBreak% New usage restriction: %NewMetaValue% %LineBreak% %EditorLinkCoupon%', 'wp-security-audit-log' ), 'woocommerce-store', 'modified' ),
					array( 9068, WSAL_LOW, __( 'User changed the usage limits settings of a coupon', 'wp-security-audit-log' ), __( 'Changed the Usage Limits of the coupon %CouponName% %LineBreak% Previous usage limits: %OldMetaValue% %LineBreak% New usage limits: %NewMetaValue% %LineBreak% %EditorLinkCoupon%', 'wp-security-audit-log' ), 'woocommerce-store', 'modified' ),
					array( 9069, WSAL_INFORMATIONAL, __( 'User changed the description of a coupon', 'wp-security-audit-log' ), __( 'Changed the description of the coupon %CouponName% %LineBreak% Previous description: %OldDescription% %LineBreak% New description: %NewDescription% %LineBreak% %EditorLinkCoupon%', 'wp-security-audit-log' ), 'woocommerce-store', 'modified' ),
					array( 9070, E_WARNING, __( 'User changed the status of a coupon', 'wp-security-audit-log' ), __( 'Changed the Status of the WooCommerce coupon %CouponName% from %OldStatus% to %NewStatus%.', 'wp-security-audit-log' ), 'woocommerce-store' ),
					array( 9071, WSAL_INFORMATIONAL, __( 'User renamed a WooCommerce coupon', 'wp-security-audit-log' ), __( 'Old coupon name: %OldName% %LineBreak% New coupon name: %NewName% %LineBreak% %EditorLinkCoupon%', 'wp-security-audit-log' ), 'woocommerce-store', 'renamed' ),
				),

				__( 'Orders', 'wp-security-audit-log' ) => array(
					array( 9035, WSAL_LOW, __( 'A WooCommerce order has been placed', 'wp-security-audit-log' ), __( 'A new order has been placed %LineBreak% Order name: %OrderTitle% %LineBreak% %EditorLinkOrder%', 'wp-security-audit-log' ), 'woocommerce-store', 'created' ),
					array( 9036, WSAL_INFORMATIONAL, __( 'WooCommerce order status changed', 'wp-security-audit-log' ), __( 'Marked an order %OrderTitle% as %OrderStatus% %LineBreak% %EditorLinkOrder%', 'wp-security-audit-log' ), 'woocommerce-store', 'modified' ),
					array( 9037, WSAL_MEDIUM, __( 'User moved a WooCommerce order to trash', 'wp-security-audit-log' ), __( 'Moved the order %OrderTitle% to trash', 'wp-security-audit-log' ), 'woocommerce-store', 'deleted' ),
					array( 9038, WSAL_LOW, __( 'User moved a WooCommerce order out of trash', 'wp-security-audit-log' ), __( 'Restored the order %OrderTitle% from the trash %LineBreak% %EditorLinkOrder%', 'wp-security-audit-log' ), 'woocommerce-store', 'restored' ),
					array( 9039, WSAL_LOW, __( 'User permanently deleted a WooCommerce order', 'wp-security-audit-log' ), __( 'Permanently deleted the order %OrderTitle%', 'wp-security-audit-log' ), 'woocommerce-store', 'deleted' ),
					array( 9040, WSAL_MEDIUM, __( 'User edited a WooCommerce order', 'wp-security-audit-log' ), __( 'Edited the details in order %OrderTitle% %LineBreak% %EditorLinkOrder%', 'wp-security-audit-log' ), 'woocommerce-store', 'modified' ),
					array( 9041, WSAL_HIGH, __( 'User refunded a WooCommerce order', 'wp-security-audit-log' ), __( 'Refunded the order %OrderTitle% %LineBreak% %EditorLinkOrder%', 'wp-security-audit-log' ), 'woocommerce-store', 'modified' ),
				),

				__( 'User Profile', 'wp-security-audit-log' ) => array(
					array( 9083, WSAL_INFORMATIONAL, __( 'User changed the billing address details', 'wp-security-audit-log' ), __( 'Changed the billing address details of the user %TargetUsername% %LineBreak% Role: %Roles% %LineBreak% Billing address field: %AddressField% %LineBreak% Previous value: %OldValue% %LineBreak% New value: %NewValue% %LineBreak% %EditUserLink%', 'wp-security-audit-log' ), 'user', 'modified' ),
					array( 9084, WSAL_INFORMATIONAL, __( 'User changed the shipping address details', 'wp-security-audit-log' ), __( 'Changed the shipping address details of the user %TargetUsername% %LineBreak% Role: %Roles% %LineBreak% Shipping address field: %AddressField% %LineBreak% Previous value: %OldValue% %LineBreak% New value: %NewValue% %LineBreak% %EditUserLink%', 'wp-security-audit-log' ), 'user', 'modified' ),
				),
			),

			__( 'Yoast SEO', 'wp-security-audit-log' ) => array(
				__( 'Post Changes', 'wp-security-audit-log' ) => array(
					array( 8801, WSAL_INFORMATIONAL, __( 'User changed title of a SEO post', 'wp-security-audit-log' ), __( 'Changed the Meta title of the post %PostTitle% %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status: %PostStatus% %LineBreak% Previous title: %OldSEOTitle% %LineBreak% New title: %NewSEOTitle% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'post', 'modified' ),
					array( 8802, WSAL_INFORMATIONAL, __( 'User changed the meta description of a SEO post', 'wp-security-audit-log' ), __( 'Changed the Meta Description of the post %PostTitle% %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status: %PostStatus% %LineBreak% Previous description: %old_desc% %LineBreak% New description: %new_desc% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'post', 'modified' ),
					array( 8803, WSAL_INFORMATIONAL, __( 'User changed setting to allow search engines to show post in search results of a SEO post', 'wp-security-audit-log' ), __( 'Changed the setting to allow search engines to show post in search results for the post %PostTitle% %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status: %PostStatus% %LineBreak% Previous setting: %OldStatus% %LineBreak% New setting: %NewStatus% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'post', 'modified' ),
					array( 8804, WSAL_INFORMATIONAL, __( 'User Enabled/Disabled the option for search engine to follow links of a SEO post', 'wp-security-audit-log' ), __( 'The option for search engine to follow links in post %PostTitle% %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status: %PostStatus% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'post', 'enabled' ),
					array( 8805, WSAL_LOW, __( 'User set the meta robots advanced setting of a SEO post', 'wp-security-audit-log' ), __( 'Changed the Meta Robots Advanced setting for the post %PostTitle% %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status: %PostStatus% %LineBreak% Previous setting: %OldStatus% %LineBreak% New setting: %NewStatus% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'post', 'modified' ),
					array( 8806, WSAL_INFORMATIONAL, __( 'User changed the canonical URL of a SEO post', 'wp-security-audit-log' ), __( 'Changed the Canonical URL of the post %PostTitle% %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status: %PostStatus% %LineBreak% Previous URL: %OldCanonicalUrl% %LineBreak% New URL: %NewCanonicalUrl% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'post', 'modified' ),
					array( 8807, WSAL_INFORMATIONAL, __( 'User changed the focus keyword of a SEO post', 'wp-security-audit-log' ), __( 'Changed the focus keyword for the post %PostTitle% %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status: %PostStatus% %LineBreak% Previous keyword: %old_keywords% %LineBreak% New keyword: %new_keywords% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'post', 'modified' ),
					array( 8808, WSAL_INFORMATIONAL, __( 'User Enabled/Disabled the option Cornerston Content of a SEO post', 'wp-security-audit-log' ), __( 'The option Cornerstone Content in the post %PostTitle% %LineBreak% ID: %PostID% %LineBreak% Type: %PostType% %LineBreak% Status: %PostStatus% %LineBreak% %EditorLinkPost%', 'wp-security-audit-log' ), 'post', 'enabled' ),
				),

				__( 'Website Changes', 'wp-security-audit-log' ) => array(
					array( 8809, WSAL_INFORMATIONAL, __( 'User changed the Title Separator setting', 'wp-security-audit-log' ), __( 'Changed the default title separator %LineBreak% Previous separator: %old% %LineBreak% New separator: %new%', 'wp-security-audit-log' ), 'yoast-seo', 'modified' ),
					array( 8810, WSAL_MEDIUM, __( 'User changed the Homepage Title setting', 'wp-security-audit-log' ), __( 'Changed the homepage Meta title %LineBreak% Previous title: %old% %LineBreak% New title: %new%', 'wp-security-audit-log' ), 'yoast-seo', 'modified' ),
					array( 8811, WSAL_MEDIUM, __( 'User changed the Homepage Meta description setting', 'wp-security-audit-log' ), __( 'Changed the homepage Meta description %LineBreak% Previous description: %old% %LineBreak% New description: %new%', 'wp-security-audit-log' ), 'yoast-seo', 'modified' ),
					array( 8812, WSAL_INFORMATIONAL, __( 'User changed the Company or Person setting', 'wp-security-audit-log' ), __( 'Changed the Company or Person setting %LineBreak% Previous setting: %old% %LineBreak% New setting: %new%', 'wp-security-audit-log' ), 'yoast-seo', 'modified' ),
				),

				__( 'Plugin Settings Changes', 'wp-security-audit-log' ) => array(
					array( 8813, WSAL_MEDIUM, __( 'User Enabled/Disabled the option Show Posts/Pages in Search Results in the Yoast SEO plugin settings', 'wp-security-audit-log' ), __( 'The option to show %SEOPostType% in search results', 'wp-security-audit-log' ), 'yoast-seo', 'enabled' ),
					array( 8814, WSAL_INFORMATIONAL, __( 'User changed the Posts/Pages title template in the Yoast SEO plugin settings', 'wp-security-audit-log' ), __( 'Changed the %SEOPostType% Meta (SEO) title template %LineBreak% Previous template: %old% %LineBreak% New template: %new%', 'wp-security-audit-log' ), 'yoast-seo', 'modified' ),
					array( 8815, WSAL_MEDIUM, __( 'User Enabled/Disabled SEO analysis in the Yoast SEO plugin settings', 'wp-security-audit-log' ), __( 'The SEO Analysis feature', 'wp-security-audit-log' ), 'yoast-seo', 'enabled' ),
					array( 8816, WSAL_MEDIUM, __( 'User Enabled/Disabled readability analysis in the Yoast SEO plugin settings', 'wp-security-audit-log' ), __( 'The Readability Analysis feature', 'wp-security-audit-log' ), 'yoast-seo', 'enabled' ),
					array( 8817, WSAL_MEDIUM, __( 'User Enabled/Disabled cornerstone content in the Yoast SEO plugin settings', 'wp-security-audit-log' ), __( 'The Cornerstone content feature', 'wp-security-audit-log' ), 'yoast-seo', 'enabled' ),
					array( 8818, WSAL_MEDIUM, __( 'User Enabled/Disabled the text link counter in the Yoast SEO plugin settings', 'wp-security-audit-log' ), __( 'The Text link counter feature', 'wp-security-audit-log' ), 'yoast-seo', 'enabled' ),
					array( 8819, WSAL_MEDIUM, __( 'User Enabled/Disabled XML sitemaps in the Yoast SEO plugin settings', 'wp-security-audit-log' ), __( 'The XML sitemap feature', 'wp-security-audit-log' ), 'yoast-seo', 'enabled' ),
					array( 8820, WSAL_MEDIUM, __( 'User Enabled/Disabled ryte integration in the Yoast SEO plugin settings', 'wp-security-audit-log' ), __( 'The Ryte integration feature', 'wp-security-audit-log' ), 'yoast-seo', 'enabled' ),
					array( 8821, WSAL_MEDIUM, __( 'User Enabled/Disabled the admin bar menu in the Yoast SEO plugin settings', 'wp-security-audit-log' ), __( 'The Admin bar menu feature', 'wp-security-audit-log' ), 'yoast-seo', 'enabled' ),
					array( 8822, WSAL_INFORMATIONAL, __( 'User changed the Posts/Pages meta description template in the Yoast SEO plugin settings', 'wp-security-audit-log' ), __( 'Changed the %SEOPostType% Meta description template %LineBreak% Previous template: %old% New template: %new%', 'wp-security-audit-log' ), 'yoast-seo', 'modified' ),
					array( 8823, WSAL_LOW, __( 'User set the option Date in Snippet Preview for Posts/Pages in the Yoast SEO plugin settings', 'wp-security-audit-log' ), __( 'The option Date in Snippet Preview for %SEOPostType%', 'wp-security-audit-log' ), 'yoast-seo', 'enabled' ),
					array( 8824, WSAL_LOW, __( 'User set the option Yoast SEO Meta Box for Posts/Pages in the Yoast SEO plugin settings', 'wp-security-audit-log' ), __( 'The option Yoast SEO Meta Box for %SEOPostType%', 'wp-security-audit-log' ), 'yoast-seo', 'enabled' ),
					array( 8825, WSAL_LOW, __( 'User Enabled/Disabled the advanced settings for authors in the Yoast SEO plugin settings', 'wp-security-audit-log' ), __( 'The Security: no advanced settings for authors feature', 'wp-security-audit-log' ), 'yoast-seo', 'enabled' ),
				),
			),
		)
	);

	// Load Custom alerts.
	load_include_custom_file( $wsal );
}

add_action( 'init', 'wsaldefaults_wsal_init', 5 );

if ( did_action( 'init' ) ) {
	wsaldefaults_wsal_init();
}
