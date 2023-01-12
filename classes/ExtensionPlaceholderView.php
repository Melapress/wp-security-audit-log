<?php
/**
 * Abstract extension placeholder view class file.
 *
 * @package    wsal
 * @subpackage views
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract class used for the plugin extension placeholder views/admin pages.
 *
 * @see        Views/*.php
 * @package    wsal
 * @subpackage views
 * @since      4.1.5.2
 */
abstract class WSAL_ExtensionPlaceholderView extends WSAL_AbstractView {

	/**
	 * {@inheritDoc}
	 */
	public function is_title_visible() {
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon() {
		return 'dashicons-external';
	}

	/**
	 * {@inheritDoc}
	 */
	public function header() {
		// Extension Page CSS.
		$extensions_file = '/css/extensions.css';
		wp_enqueue_style(
			'extensions',
			WSAL_BASE_URL . $extensions_file,
			array(),
			WSAL_VERSION
		);

		// Simple lightbox CSS.
		$simple_lightbox_file = '/css/dist/simple-lightbox.min.css';
		wp_enqueue_style(
			'wsal-simple-lightbox-css',
			WSAL_BASE_URL . $simple_lightbox_file,
			array(),
			WSAL_VERSION
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function footer() {
		// jQuery.
		wp_enqueue_script( 'jquery' );

		// Simple lightbox JS.
		$simple_lightbox_file = '/js/dist/simple-lightbox.jquery.min.js';
		wp_register_script(
			'wsal-simple-lightbox-js',
			WSAL_BASE_URL . $simple_lightbox_file,
			array( 'jquery' ),
			WSAL_VERSION,
			false
		);
		wp_enqueue_script( 'wsal-simple-lightbox-js' );

		// Extensions JS.
		$extensions_file = '/js/extensions.js';
		wp_register_script(
			'wsal-extensions-js',
			WSAL_BASE_URL . $extensions_file,
			array( 'wsal-simple-lightbox-js' ),
			WSAL_VERSION,
			false
		);
		wp_enqueue_script( 'wsal-extensions-js' );
	}
}
