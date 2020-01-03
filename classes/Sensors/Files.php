<?php
/**
 * Sensor: Files
 *
 * Files sensors class file.
 *
 * @since 1.0.0
 * @package Wsal
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
 * @package Wsal
 * @subpackage Sensors
 */
class WSAL_Sensors_Files extends WSAL_AbstractSensor {

	/**
	 * File uploaded.
	 *
	 * @var boolean
	 */
	protected $is_file_uploaded = false;

	/**
	 * Listening to events using WP hooks.
	 */
	public function HookEvents() {
		add_action( 'add_attachment', array( $this, 'EventFileUploaded' ) );
		add_action( 'delete_attachment', array( $this, 'EventFileUploadedDeleted' ) );
		add_action( 'admin_init', array( $this, 'EventAdminInit' ) );
	}

	/**
	 * File uploaded.
	 *
	 * @param integer $attachment_id - Attachment ID.
	 */
	public function EventFileUploaded( $attachment_id ) {
		// Filter $_POST array for security.
		$post_array = filter_input_array( INPUT_POST );

		$action = isset( $post_array['action'] ) ? $post_array['action'] : '';
		if ( 'upload-theme' !== $action && 'upload-plugin' !== $action ) {
			$file = get_attached_file( $attachment_id );
			$this->plugin->alerts->Trigger(
				2010, array(
					'AttachmentID' => $attachment_id,
					'FileName'     => basename( $file ),
					'FilePath'     => dirname( $file ),
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
	public function EventFileUploadedDeleted( $attachment_id ) {
		if ( $this->is_file_uploaded ) {
			return;
		}
		$file = get_attached_file( $attachment_id );
		$this->plugin->alerts->Trigger(
			2011, array(
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
	public function EventAdminInit() {
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
				$this->plugin->alerts->Trigger(
					2051, array(
						'File'   => $file,
						'Plugin' => $plugin,
					)
				);
			} elseif ( 'theme-editor' === $referer ) {
				$stylesheet = isset( $_POST['theme'] ) ? sanitize_text_field( wp_unslash( $_POST['theme'] ) ) : false;

				if ( ! wp_verify_nonce( $nonce, 'edit-theme_' . $stylesheet . '_' . $file ) ) {
					return;
				}

				$this->plugin->alerts->Trigger(
					2046, array(
						'File'  => $file,
						'Theme' => trailingslashit( get_theme_root() ) . $stylesheet,
					)
				);
			}
		}
	}
}
