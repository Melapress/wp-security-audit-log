<?php

/**
 * Abstract class used for the plugin extension placeholder views/admin pages.
 *
 * @see Views/*.php
 * @package Wsal
 * @since 4.1.5.2
 */
abstract class WSAL_ExtensionPlaceholderView extends WSAL_AbstractView {

	public function is_title_visible() {
		return false;
	}

	public function GetIcon() {
		return 'dashicons-external';
	}

	public function Header() {
		// Extension Page CSS.
		$extensionsFile = '/css/extensions.css';
		wp_enqueue_style(
			'extensions',
			$this->_plugin->GetBaseUrl() . $extensionsFile,
			array(),
			filemtime( $this->_plugin->GetBaseDir() . $extensionsFile )
		);

		// Simple lightbox CSS.
		$simpleLightboxFile = '/css/dist/simple-lightbox.min.css';
		wp_enqueue_style(
			'wsal-simple-lightbox-css',
			$this->_plugin->GetBaseUrl() . $simpleLightboxFile,
			array(),
			filemtime( $this->_plugin->GetBaseDir() . $simpleLightboxFile )
		);
	}

	public function Footer() {
		// jQuery.
		wp_enqueue_script( 'jquery' );

		// Simple lightbox JS.
		$simpleLightboxFile = '/js/dist/simple-lightbox.jquery.min.js';
		wp_register_script(
			'wsal-simple-lightbox-js',
			$this->_plugin->GetBaseUrl() . $simpleLightboxFile,
			array( 'jquery' ),
			filemtime( $this->_plugin->GetBaseDir() . $simpleLightboxFile ),
			false
		);
		wp_enqueue_script( 'wsal-simple-lightbox-js' );

		// Extensions JS.
		$extensionsFile = '/js/extensions.js';
		wp_register_script(
			'wsal-extensions-js',
			$this->_plugin->GetBaseUrl() . $extensionsFile,
			array( 'wsal-simple-lightbox-js' ),
			filemtime( $this->_plugin->GetBaseDir() . $extensionsFile ),
			false
		);
		wp_enqueue_script( 'wsal-extensions-js' );
	}
}
