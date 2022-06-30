<?php
/**
 * Sensor: Files
 *
 * Files sensors class file.
 *
 * @since      1.0.0
 * @package    wsal
 * @subpackage sensors
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Files sensor.
 *
 * 2010 User uploaded file in Uploads directory
 * 2011 User deleted file from Uploads directory
 * 2046 User changed a file using the theme editor
 * 2051 User changed a file using the plugin editor
 *
 * @package    wsal
 * @subpackage sensors
 */
class WSAL_Sensors_Files extends WSAL_AbstractSensor {

	/**
	 * File uploaded.
	 *
	 * @var boolean
	 */
	protected $is_file_uploaded = false;

	/**
	 * {@inheritDoc}
	 */
	public function hook_events() {
		add_action( 'add_attachment', array( $this, 'event_file_uploaded' ) );
		add_action( 'delete_attachment', array( $this, 'event_file_uploaded_deleted' ) );
		add_action( 'admin_init', array( $this, 'event_admin_init' ) );
	}

	/**
	 * File uploaded.
	 *
	 * @param integer $attachment_id - Attachment ID.
	 */
	public function event_file_uploaded( $attachment_id ) {
		// Filter $_POST array for security.
		$post_array = filter_input_array( INPUT_POST );

		$action = isset( $post_array['action'] ) ? $post_array['action'] : '';
		if ( 'upload-theme' !== $action && 'upload-plugin' !== $action ) {
			$file = get_attached_file( $attachment_id );
			$this->plugin->alerts->trigger_event(
				2010,
				array(
					'AttachmentID'  => $attachment_id,
					'FileName'      => basename( $file ),
					'FilePath'      => dirname( $file ),
					'AttachmentUrl' => get_attachment_link( $attachment_id ),
				)
			);
		}
		$this->is_file_uploaded = true;
	}

	/**
	 * Deleted file from uploads directory.
	 *
	 * @param integer $attachment_id - Attachment ID.
	 */
	public function event_file_uploaded_deleted( $attachment_id ) {
		if ( $this->is_file_uploaded ) {
			return;
		}
		$file = get_attached_file( $attachment_id );

		if ( false !== strpos( $file, 'wp-security-audit-log' ) ) {
			/**
			 * This fires when our plugin is get updated - unfortunately that most probably makes calls to the old version of the plugin and that new plugin we have no idea what changes it could have, on the other hand that call is made from the old version / memory, so most probably there are code changes which could lead to PHP errors. Lets silence this if it comes to our plugin
			 */
			return;
		}

		$this->plugin->alerts->trigger_event(
			2011,
			array(
				'AttachmentID' => $attachment_id,
				'FileName'     => basename( $file ),
				'FilePath'     => dirname( $file ),
			)
		);
	}

	/**
	 * File Changes Event.
	 *
	 * Detect file changes in plugins/themes using plugin/theme editor.
	 */
	public function event_admin_init() {
		// @codingStandardsIgnoreStart
		$nonce   = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : false;
		$file    = isset( $_POST['file'] ) ? sanitize_text_field( wp_unslash( $_POST['file'] ) ) : false;
		$action  = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : false;
		$referer = isset( $_POST['_wp_http_referer'] ) ? sanitize_text_field( wp_unslash( $_POST['_wp_http_referer'] ) ) : false;
		$referer = remove_query_arg( array( 'file', 'theme', 'plugin' ), $referer );
		$referer = basename( $referer, '.php' );
		// @codingStandardsIgnoreEnd

		if ( 'edit-theme-plugin-file' === $action ) {
			if ( 'plugin-editor' === $referer && wp_verify_nonce( $nonce, 'edit-plugin_' . $file ) ) {
				$plugin = isset( $_POST['plugin'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin'] ) ) : false;
				$this->plugin->alerts->trigger_event(
					2051,
					array(
						'File'   => $file,
						'Plugin' => $plugin,
					)
				);
			} elseif ( 'theme-editor' === $referer ) {
				$stylesheet = isset( $_POST['theme'] ) ? sanitize_text_field( wp_unslash( $_POST['theme'] ) ) : false;

				if ( ! wp_verify_nonce( $nonce, 'edit-theme_' . $stylesheet . '_' . $file ) ) {
					return;
				}

				$this->plugin->alerts->trigger_event(
					2046,
					array(
						'File'  => $file,
						'Theme' => trailingslashit( get_theme_root() ) . $stylesheet,
					)
				);
			}
		}
	}
}
