<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Alert formatter configuration class.
 *
 * Defines the configuration of specific alert formatter.
 *
 * Default configuration is meant for plain text output with not metadata nor hyperlinks.
 *
 * All setter methods are fluent to allow for method chaining.
 *
 * @package wsal
 * @since 4.3.0
 */
class WSAL_AlertFormatterConfiguration {

	/**
	 * List of tags compatible with function strip_tags before PHP 7.4 (must be string, not an array).
	 *
	 * @var string
	 */
	protected $tags_allowed_in_message = '';

	/**
	 * @var bool
	 */
	protected $is_js_in_links_allowed = false;

	/**
	 * @var string
	 */
	protected $highlight_end_tag = '';

	/**
	 * @var bool
	 */
	protected $supports_hyperlinks = false;

	/**
	 * @var bool
	 */
	protected $supports_metadata = false;

	/**
	 * @var int
	 */
	protected $max_meta_value_length = 50;

	/**
	 * @var string
	 */
	protected $emphasis_start_tag = '';

	/**
	 * @var string
	 */
	protected $end_of_line = ' ';

	/**
	 * @var string
	 */
	protected $highlight_start_tag = '';

	/**
	 * @var string
	 */
	protected $emphasis_end_tag = '';

	/**
	 * @var string
	 */
	protected $ellipses_sequence = '...';

	/**
	 * @var bool
	 * @since 4.3.2
	 */
	protected $use_html_markup_for_links = true;

	/**
	 * Private empty constructor used for method chaining. Builder methods should be used to retrieve implementations
	 * of this configuration.
	 */
	private function __construct() {
		return $this;
	}

	/**
	 * Builds default plain text alert formatter configuration. Intended for plain text logging services (syslog,
	 * PaperTrail etc.)
	 *
	 * @return WSAL_AlertFormatterConfiguration
	 */
	public static function buildPlainTextConfiguration() {
		return new WSAL_AlertFormatterConfiguration();

	}

	/**
	 * Builds alert formatter configuration with full HTML features. Intended for the audit log UI in WordPress admin.
	 *
	 * @return WSAL_AlertFormatterConfiguration
	 */
	public static function buildHtmlConfiguration() {
		return ( new WSAL_AlertFormatterConfiguration() )
			->setTagsAllowedInMessage( '<strong><br><a>' )
			->setIsJsInLinksAllowed( true )
			->setSupportsMetadata( true )
			->setSupportsHyperlinks( true )
			->setEmphasisStartTag( '<i>' )
			->setEmphasisEndTag( '</i>' )
			->setHighlightStartTag( '<strong>' )
			->setHighlightEndTag( '</strong>' )
			->setEndOfLine( '<br />' )
			->setEllipsesSequence( '&hellip;' );
	}

	/**
	 * @param bool $is_js_in_links_allowed
	 *
	 * @return WSAL_AlertFormatterConfiguration
	 */
	public function setIsJsInLinksAllowed( $is_js_in_links_allowed ) {
		$this->is_js_in_links_allowed = $is_js_in_links_allowed;

		return $this;
	}

	/**
	 * @return bool
	 * @since 4.3.2
	 */
	public function canUseHtmlMarkupForLinks(): bool {
		return $this->use_html_markup_for_links;
	}

	/**
	 * @param bool $use_html_markup_for_links
	 *
	 * @return WSAL_AlertFormatterConfiguration
	 * @since 4.3.2
	 *
	 */
	public function setUseHtmlMarkupForLinks( bool $use_html_markup_for_links ): WSAL_AlertFormatterConfiguration {
		$this->use_html_markup_for_links = $use_html_markup_for_links;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getTagsAllowedInMessage() {
		return $this->tags_allowed_in_message;
	}

	/**
	 * @param string $tags_allowed_in_message
	 *
	 * @return WSAL_AlertFormatterConfiguration
	 */
	public function setTagsAllowedInMessage( $tags_allowed_in_message ) {
		$this->tags_allowed_in_message = $tags_allowed_in_message;

		return $this;
	}

	/**
	 * @return bool
	 */
	public function isJsInLinksAllowed() {
		return $this->is_js_in_links_allowed;
	}

	/**
	 * @return string
	 */
	public function getHighlightEndTag() {
		return $this->highlight_end_tag;
	}

	/**
	 * @param string $highlight_end_tag
	 *
	 * @return WSAL_AlertFormatterConfiguration
	 */
	public function setHighlightEndTag( $highlight_end_tag ) {
		$this->highlight_end_tag = $highlight_end_tag;

		return $this;
	}

	/**
	 * @return bool
	 */
	public function isSupportsHyperlinks() {
		return $this->supports_hyperlinks;
	}

	/**
	 * @param bool $supports_hyperlinks
	 *
	 * @return WSAL_AlertFormatterConfiguration
	 */
	public function setSupportsHyperlinks( $supports_hyperlinks ) {
		$this->supports_hyperlinks = $supports_hyperlinks;

		return $this;
	}

	/**
	 * @return bool
	 */
	public function isSupportsMetadata() {
		return $this->supports_metadata;
	}

	/**
	 * @param bool $supports_metadata
	 *
	 * @return WSAL_AlertFormatterConfiguration
	 */
	public function setSupportsMetadata( $supports_metadata ) {
		$this->supports_metadata = $supports_metadata;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getMaxMetaValueLength() {
		return $this->max_meta_value_length;
	}

	/**
	 * @param int $max_meta_value_length
	 *
	 * @return WSAL_AlertFormatterConfiguration
	 */
	public function setMaxMetaValueLength( $max_meta_value_length ) {
		$this->max_meta_value_length = $max_meta_value_length;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getEmphasisStartTag() {
		return $this->emphasis_start_tag;
	}

	/**
	 * @param string $emphasis_start_tag
	 *
	 * @return WSAL_AlertFormatterConfiguration
	 */
	public function setEmphasisStartTag( $emphasis_start_tag ) {
		$this->emphasis_start_tag = $emphasis_start_tag;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getEndOfLine() {
		return $this->end_of_line;
	}

	/**
	 * @param string $end_of_line
	 *
	 * @return WSAL_AlertFormatterConfiguration
	 */
	public function setEndOfLine( $end_of_line ) {
		$this->end_of_line = $end_of_line;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getHighlightStartTag() {
		return $this->highlight_start_tag;
	}

	/**
	 * @param string $highlight_start_tag
	 *
	 * @return WSAL_AlertFormatterConfiguration
	 */
	public function setHighlightStartTag( $highlight_start_tag ) {
		$this->highlight_start_tag = $highlight_start_tag;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getEmphasisEndTag() {
		return $this->emphasis_end_tag;
	}

	/**
	 * @param string $emphasis_end_tag
	 *
	 * @return WSAL_AlertFormatterConfiguration
	 */
	public function setEmphasisEndTag( $emphasis_end_tag ) {
		$this->emphasis_end_tag = $emphasis_end_tag;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getEllipsesSequence() {
		return $this->ellipses_sequence;
	}

	/**
	 * @param string $ellipses_sequence
	 *
	 * @return WSAL_AlertFormatterConfiguration
	 */
	public function setEllipsesSequence( $ellipses_sequence ) {
		$this->ellipses_sequence = $ellipses_sequence;

		return $this;
	}
}
