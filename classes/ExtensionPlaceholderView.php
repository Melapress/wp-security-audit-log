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
			$this->plugin->get_base_url() . $extensions_file,
			array(),
			filemtime( $this->plugin->get_base_dir() . $extensions_file )
		);

		// Simple lightbox CSS.
		$simple_lightbox_file = '/css/dist/simple-lightbox.min.css';
		wp_enqueue_style(
			'wsal-simple-lightbox-css',
			$this->plugin->get_base_url() . $simple_lightbox_file,
			array(),
			filemtime( $this->plugin->get_base_dir() . $simple_lightbox_file )
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
			$this->plugin->get_base_url() . $simple_lightbox_file,
			array( 'jquery' ),
			filemtime( $this->plugin->get_base_dir() . $simple_lightbox_file ),
			false
		);
		wp_enqueue_script( 'wsal-simple-lightbox-js' );

		// Extensions JS.
		$extensions_file = '/js/extensions.js';
		wp_register_script(
			'wsal-extensions-js',
			$this->plugin->get_base_url() . $extensions_file,
			array( 'wsal-simple-lightbox-js' ),
			filemtime( $this->plugin->get_base_dir() . $extensions_file ),
			false
		);
		wp_enqueue_script( 'wsal-extensions-js' );
	}
}
