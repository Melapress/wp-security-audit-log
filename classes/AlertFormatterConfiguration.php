<?php
/**
 * Alert formatter configuration class.
 *
 * @package wsal
 * @since   4.3.0
 */

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
 * @since   4.3.0
 */
class WSAL_AlertFormatterConfiguration {

	/**
	 * List of tags compatible with function strip_tags before PHP 7.4 (must be string, not an array).
	 *
	 * @var string
	 */
	protected $tags_allowed_in_message = '';

	/**
	 * True if JS is allowed in the links.
	 *
	 * @var bool
	 */
	protected $is_js_in_links_allowed = false;

	/**
	 * Ending tag of highlighted section.
	 *
	 * @var string
	 */
	protected $highlight_end_tag = '';

	/**
	 * True if formatter supports hyperlinks.
	 *
	 * @var bool
	 */
	protected $supports_hyperlinks = false;

	/**
	 * True if formatter supports metadata.
	 *
	 * @var bool
	 */
	protected $supports_metadata = false;

	/**
	 * Maximum length of metadata value to display.
	 *
	 * @var int
	 */
	protected $max_meta_value_length = 50;

	/**
	 * Starting tag of emphasised section.
	 *
	 * @var string
	 */
	protected $emphasis_start_tag = '';

	/**
	 * End of line character sequence.
	 *
	 * @var string
	 */
	protected $end_of_line = ' ';

	/**
	 * Starting tag of highlighted section.
	 *
	 * @var string
	 */
	protected $highlight_start_tag = '';

	/**
	 * Ending tag of emphasised section.
	 *
	 * @var string
	 */
	protected $emphasis_end_tag = '';

	/**
	 * Ellipses character sequence.
	 *
	 * @var string
	 */
	protected $ellipses_sequence = '...';

	/**
	 * True if formatter uses HTML markup for links.
	 *
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
	public static function build_plain_text_configuration() {
		return new WSAL_AlertFormatterConfiguration();
	}

	/**
	 * Builds alert formatter configuration with full HTML features. Intended for the audit log UI in WordPress admin.
	 *
	 * @return WSAL_AlertFormatterConfiguration
	 */
	public static function build_html_configuration() {
		return ( new WSAL_AlertFormatterConfiguration() )
			->set_tags_allowed_in_message( '<strong><br><a>' )
			->set_is_js_in_links_allowed( true )
			->set_supports_metadata( true )
			->set_supports_hyperlinks( true )
			->set_emphasis_start_tag( '<i>' )
			->set_emphasis_end_tag( '</i>' )
			->set_highlight_start_tag( '<strong>' )
			->set_highlight_end_tag( '</strong>' )
			->set_end_of_line( '<br />' )
			->set_ellipses_sequence( '&hellip;' );
	}

	/**
	 * Sets the JS allowed in links settings.
	 *
	 * @param bool $is_js_in_links_allowed True if JS is supposed to allowed in the links.
	 *
	 * @return WSAL_AlertFormatterConfiguration
	 */
	public function set_is_js_in_links_allowed( $is_js_in_links_allowed ) {
		$this->is_js_in_links_allowed = $is_js_in_links_allowed;

		return $this;
	}

	/**
	 * Checks if HTML markup can be used for links.
	 *
	 * @return bool True if HTML markup can be used for links.
	 * @since 4.3.2
	 */
	public function can_use_html_markup_for_links(): bool {
		return $this->use_html_markup_for_links;
	}

	/**
	 * Sets the "HTML markup can be used for links" option.
	 *
	 * @param bool $use_html_markup_for_links If true, HTML markup can be used for links.
	 *
	 * @return WSAL_AlertFormatterConfiguration
	 * @since 4.3.2
	 */
	public function set_use_html_markup_for_links( bool $use_html_markup_for_links ) {
		$this->use_html_markup_for_links = $use_html_markup_for_links;

		return $this;
	}

	/**
	 * Returns a list of tags that are allowed in the message in format digestible by function strip_tags.
	 *
	 * @return string List of tags that are allowed in the message.
	 */
	public function get_tags_allowed_in_message() {
		return $this->tags_allowed_in_message;
	}

	/**
	 * Sets the tags allowed in the message in format digestible by function strip_tags.
	 *
	 * @param string $tags_allowed_in_message List of tags that are allowed in the message.
	 *
	 * @return WSAL_AlertFormatterConfiguration
	 */
	public function set_tags_allowed_in_message( $tags_allowed_in_message ) {
		$this->tags_allowed_in_message = $tags_allowed_in_message;

		return $this;
	}

	/**
	 * Sets the "JS is allowed in the links" option.
	 *
	 * @return bool True if JS is supposed to be allowed in the links.
	 */
	public function is_js_in_links_allowed() {
		return $this->is_js_in_links_allowed;
	}

	/**
	 * Gets the ending tag of highlighted section.
	 *
	 * @return string Ending tag of highlighted section.
	 */
	public function get_highlight_end_tag() {
		return $this->highlight_end_tag;
	}

	/**
	 *
	 * Sets the ending tag of highlighted section.
	 *
	 * @param string $highlight_end_tag Ending tag of highlighted section.
	 *
	 * @return WSAL_AlertFormatterConfiguration
	 */
	public function set_highlight_end_tag( $highlight_end_tag ) {
		$this->highlight_end_tag = $highlight_end_tag;

		return $this;
	}

	/**
	 * Checks if formatter supports hyperlinks.
	 *
	 * @return bool True if formatter supports hyperlinks.
	 */
	public function is_supports_hyperlinks() {
		return $this->supports_hyperlinks;
	}

	/**
	 * Sets the "formatter supports hyperlinks" setting.
	 *
	 * @param bool $supports_hyperlinks True if formatter should support hyperlinks.
	 *
	 * @return WSAL_AlertFormatterConfiguration
	 */
	public function set_supports_hyperlinks( $supports_hyperlinks ) {
		$this->supports_hyperlinks = $supports_hyperlinks;

		return $this;
	}

	/**
	 * Checks if the formatter supports metadata.
	 *
	 * @return bool True if formatter supports metadata.
	 */
	public function is_supports_metadata() {
		return $this->supports_metadata;
	}

	/**
	 * Updates the "formatter supports metadata" setting.
	 *
	 * @param bool $supports_metadata True if formatter should support metadata.
	 *
	 * @return WSAL_AlertFormatterConfiguration
	 */
	public function set_supports_metadata( $supports_metadata ) {
		$this->supports_metadata = $supports_metadata;

		return $this;
	}

	/**
	 * Gets the maximum length of metadata value to display.
	 *
	 * @return int Maximum length of metadata value to display.
	 */
	public function get_max_meta_value_length() {
		return $this->max_meta_value_length;
	}

	/**
	 * Sets the maximum length of metadata value to display.
	 *
	 * @param int $max_meta_value_length Maximum length of metadata value to display.
	 *
	 * @return WSAL_AlertFormatterConfiguration
	 */
	public function set_max_meta_value_length( $max_meta_value_length ) {
		$this->max_meta_value_length = $max_meta_value_length;

		return $this;
	}

	/**
	 * Gets the starting tag of emphasised section.
	 *
	 * @return string Starting tag of emphasised section.
	 */
	public function get_emphasis_start_tag() {
		return $this->emphasis_start_tag;
	}

	/**
	 * Updates the starting tag of emphasised section.
	 *
	 * @param string $emphasis_start_tag Starting tag of emphasised section.
	 *
	 * @return WSAL_AlertFormatterConfiguration
	 */
	public function set_emphasis_start_tag( $emphasis_start_tag ) {
		$this->emphasis_start_tag = $emphasis_start_tag;

		return $this;
	}

	/**
	 * Gets the end of line character sequence.
	 *
	 * @return string End of line character sequence.
	 */
	public function get_end_of_line() {
		return $this->end_of_line;
	}

	/**
	 * Updates the end of line character sequence.
	 *
	 * @param string $end_of_line End of line character sequence.
	 *
	 * @return WSAL_AlertFormatterConfiguration
	 */
	public function set_end_of_line( $end_of_line ) {
		$this->end_of_line = $end_of_line;

		return $this;
	}

	/**
	 * Gets the starting tag of highlighted section.
	 *
	 * @return string Starting tag of highlighted section.
	 */
	public function get_highlight_start_tag() {
		return $this->highlight_start_tag;
	}

	/**
	 * Sets the starting tag of highlighted section.
	 *
	 * @param string $highlight_start_tag Starting tag of highlighted section.
	 *
	 * @return WSAL_AlertFormatterConfiguration
	 */
	public function set_highlight_start_tag( $highlight_start_tag ) {
		$this->highlight_start_tag = $highlight_start_tag;

		return $this;
	}

	/**
	 * Ending tag of emphasised section.
	 *
	 * @return string Ending tag of emphasised section.
	 */
	public function get_emphasis_end_tag() {
		return $this->emphasis_end_tag;
	}

	/**
	 * Sets the ending tag of emphasised section.
	 *
	 * @param string $emphasis_end_tag Ending tag of emphasised section.
	 *
	 * @return WSAL_AlertFormatterConfiguration
	 */
	public function set_emphasis_end_tag( $emphasis_end_tag ) {
		$this->emphasis_end_tag = $emphasis_end_tag;

		return $this;
	}

	/**
	 * Gets the ellipses character sequence.
	 *
	 * @return string Ellipses character sequence.
	 */
	public function get_ellipses_sequence() {
		return $this->ellipses_sequence;
	}

	/**
	 * Sets the ellipses character sequence.
	 *
	 * @param string $ellipses_sequence Ellipses character sequence.
	 *
	 * @return WSAL_AlertFormatterConfiguration
	 */
	public function set_ellipses_sequence( $ellipses_sequence ) {
		$this->ellipses_sequence = $ellipses_sequence;

		return $this;
	}
}
