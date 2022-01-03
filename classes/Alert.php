<?php

/**
 * WSAL_Alert Object class.
 *
 * @package wsal
 */
final class WSAL_Alert {

	/**
	 * Alert type (used when triggering an alert etc).
	 *
	 * @var integer
	 */
	public $code = 0;

	/**
	 * Alert error level (E_* constant).
	 *
	 * @var integer
	 */
	public $severity = 0;

	/**
	 * Alert category (alerts are grouped by matching categories).
	 *
	 * @var string
	 */
	public $catg = '';

	/**
	 * Alert sub category.
	 *
	 * @var string
	 */
	public $subcatg = '';

	/**
	 * Alert description (ie, describes what happens when alert is triggered).
	 *
	 * @var string
	 */
	public $desc = '';

	/**
	 * Alert message (variables between '%' are expanded to values).
	 *
	 * @var string
	 */
	public $mesg = '';

	/**
	 * Event object.
	 *
	 * @var string
	 */
	public $object = '';

	/**
	 * Event type.
	 *
	 * @var string
	 */
	public $event_type = '';

	/**
	 * List of metadata items containing metadata key and a label to be displayed.
	 *
	 * @var array
	 * @since 4.2.1
	 */
	public $metadata = [];

	/**
	 * List of metadata items containing metadata key and a label to be displayed.
	 *
	 * @var string[]
	 * @since 4.2.1
	 */
	public $links = [];

	/**
	 * Constructor.
	 *
	 * @param integer $type - Type of alert.
	 * @param integer $code - Code of alert.
	 * @param string $catg - Category of alert.
	 * @param string $subcatg - Subcategory of alert.
	 * @param string $desc - Description.
	 * @param string $mesg - Alert message.
	 * @param array $metadata - List of metadata items containing metadata key and a label to be displayed.
	 * @param string $links - This should be a list of links in form of dynamic placeholders or a metadata names.
	 * @param string $object - Event object.
	 * @param string $event_type - Event type.
	 */
	public function __construct( $type = 0, $code = 0, $catg = '', $subcatg = '', $desc = '', $mesg = '', $metadata = [], $links = '', $object = '', $event_type = '' ) {
		$this->code       = $type;
		$this->severity   = $code;
		$this->catg       = $catg;
		$this->subcatg    = $subcatg;
		$this->desc       = $desc;
		$this->mesg       = $mesg;
		$this->object     = $object;
		$this->event_type = $event_type;
		$this->metadata   = $metadata;
		$this->links      = $links;
	}

	/**
	 * Gets alert message.
	 *
	 * Note: not to be used to display any messages. Use WSAL_Models_Occurrence::GetMessage() instead.
	 *
	 * @param array $meta_data - (Optional) Meta data relevant to message.
	 * @param string|null $message - (Optional) Override message template to use.
	 * @param integer $occurrence_id - (Optional) Event occurrence ID.
	 * @param string|false $context - Context in which the message will be used/displayed.
	 *
	 * @return string Fully formatted message.
	 * @throws Freemius_Exception
	 * @see WSAL_Models_Occurrence::GetMessage()
	 */
	public function GetMessage( $meta_data = array(), $message = null, $occurrence_id = 0, $context = false ) {
		return $this->GetFormattedMesg( is_null( $message ) ? $this->mesg : $message, $meta_data, $occurrence_id, $context );
	}

	/**
	 * Expands a message with variables by replacing variables with meta data values.
	 *
	 * @param string $original_message - The original message.
	 * @param array $meta_data - (Optional) Meta data relevant to message.
	 * @param integer $occurrence_id - (Optional) Event occurrence ID.
	 * @param string|false $context - Context in which the message will be used/displayed.
	 *
	 * @return string The expanded message.
	 * @throws Freemius_Exception
	 */
	protected function GetFormattedMesg( $original_message, $meta_data = array(), $occurrence_id = 0, $context = false ) {

		$result = '';

		//  fallback on the default context
		if ( false === $context ) {
			$context = 'default';
		}

		//  get the alert formatter for given context
		$formatter = WSAL_AlertFormatterFactory::getFormatter( $context );

		// Tokenize message with regex.
		$message_parts = preg_split( '/(%.*?%)/', (string) $original_message, - 1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $message_parts ) ) {
			//  use the message as is
			$result = (string) $original_message;
		} else {
			// Handle tokenized message.
			if ( ! empty( $message_parts ) ) {
				foreach ( $message_parts as $i => $token ) {
					if ( strlen( $token ) === 0 ) {
						continue;
					}
					// Handle escaped percent sign.
					if ( '%%' == $token ) {
						$message_parts[ $i ] = '%';
					} elseif ( substr( $token, 0, 1 ) == '%' && substr( $token, - 1, 1 ) == '%' ) {
						// Handle complex expressions.
						$message_parts[ $i ] = $this->GetMetaExprValue( substr( $token, 1, - 1 ), $meta_data );
						$message_parts[ $i ] = $formatter->format_meta_expression( $token, $message_parts[ $i ], $occurrence_id );
						if ( ! empty( $message_parts[ $i ] ) ) {
							$message_parts[ $i ] = $formatter->wrap_in_hightlight_markup( $message_parts[ $i ] );
						}
					}
				}

				// Compact message
				$result = implode( '', $message_parts );
			}
		}

		//  process message to make sure it any HTML tags are handled correctly
		$result = $formatter->process_html_tags_in_message( $result );

		$end_of_line = $formatter->get_end_of_line();

		//  process metadata and links introduced as part of alert definition in version 4.2.1
		if ( $formatter->supports_metadata() ) {
			$metadata_result = $this->get_formatted_metadata( $formatter, $meta_data, $occurrence_id );
			if ( ! empty( $metadata_result ) ) {
				if ( ! empty( $result ) ) {
					$result .= $end_of_line;
				}
				$result .= $metadata_result;
			}
		}

		if ( $formatter->supports_hyperlinks() ) {
			$hyperlinks_result = $this->get_formatted_hyperlinks( $formatter, $meta_data, $occurrence_id );
			if ( ! empty( $hyperlinks_result ) ) {
				if ( ! empty( $result ) ) {
					$result .= $end_of_line;
				}
				$result .= $hyperlinks_result;
			}
		}

		return $result;
	}

	/**
	 * Retrieves a value for a particular meta variable expression.
	 *
	 * @param string $expr Expression, eg: User->Name looks for a Name property for meta named User.
	 * @param array $meta_data (Optional) Meta data relevant to expression.
	 *
	 * @return mixed The value nearest to the expression.
	 */
	protected function GetMetaExprValue( $expr, $meta_data = array() ) {
		$expr = preg_replace( '/%/', '', $expr );
		if ( 'IPAddress' == $expr ) {
			if ( array_key_exists( 'IPAddress', $meta_data ) ) {
				return implode( ', ', $meta_data['IPAddress'] );
			}

			return null;
		}

		// TODO: Handle function calls (and methods?).
		$expr = explode( '->', $expr );
		$meta = array_shift( $expr );
		$meta = isset( $meta_data[ $meta ] ) ? $meta_data[ $meta ] : null;
		foreach ( $expr as $part ) {
			if ( is_scalar( $meta ) || is_null( $meta ) ) {
				return $meta; // This isn't 100% correct.
			}
			$meta = is_array( $meta ) ? $meta[ $part ] : ( isset( $meta->$part ) ? $meta->$part : 'NULL' );
		}

		return is_scalar( $meta ) ? (string) $meta : var_export( $meta, true );
	}

	/**
	 * @param WSAL_AlertFormatter $formatter
	 * @param array $meta_data
	 * @param int $occurrence_id
	 *
	 * @return string
	 * @throws Freemius_Exception
	 * @since 4.2.1
	 */
	public function get_formatted_metadata( $formatter, $meta_data, $occurrence_id ) {
		$result            = '';
		$metadata_as_array = $this->get_metadata_as_array( $formatter, $meta_data, $occurrence_id );
		if ( ! empty( $metadata_as_array ) ) {

			$meta_result_parts = [];
			foreach ( $metadata_as_array as $meta_label => $meta_expression ) {
				if ( ! empty( $meta_expression ) ) {
					array_push( $meta_result_parts, $meta_label . ': ' . $formatter->wrap_in_hightlight_markup( $meta_expression ) );
				}
			}

			if ( ! empty( $meta_result_parts ) ) {
				$result .= implode( $formatter->get_end_of_line(), $meta_result_parts );
			}
		}
		return $result;
	}

	/**
	 * @param WSAL_AlertFormatter $formatter
	 * @param array $meta_data
	 * @param int $occurrence_id
	 *
	 * @return array
	 * @since 4.2.1
	 */
	public function get_metadata_as_array( $formatter, $meta_data, $occurrence_id ) {
		$result = [];
		if ( ! empty( $this->metadata ) ) {
			foreach ( $this->metadata as $meta_label => $meta_token ) {
				if ( strlen( $meta_token ) === 0 ) {
					continue;
				}

				//  pure alert meta lookup based on meta token
				$meta_expression = $this->GetMetaExprValue( $meta_token, $meta_data );

				//  additional alert meta processing - handles derived or decorated alert data
				$meta_expression = $formatter->format_meta_expression( $meta_token, $meta_expression, $occurrence_id );

				if ( ! empty( $meta_expression ) ) {
					$result[ $meta_label ] = $meta_expression;
				}
			}
		}

		return $result;
	}

	/**
	 * @param WSAL_AlertFormatter $formatter
	 * @param array $meta_data
	 * @param int $occurrence_id
	 *
	 * @return string
	 * @throws Freemius_Exception
	 * @since 4.2.1
	 */
	public function get_formatted_hyperlinks( $formatter, $meta_data, $occurrence_id ) {
		$result              = '';
		$hyperlinks_as_array = $this->get_hyperlinks_as_array( $formatter, $meta_data, $occurrence_id );
		if ( ! empty( $hyperlinks_as_array ) ) {
			$links_result_parts = [];
			foreach ( $hyperlinks_as_array as  $link_data ) {
				$link_label = $link_data['label'];
				$link_url = $link_data['url'];
				$needs_formatting = $link_data['needs_formatting'];
				$formatted_link = $needs_formatting ? $formatter->format_link( $link_url, $link_label ) : $link_url;
				array_push( $links_result_parts, $formatted_link );
			}

			if ( ! empty( $links_result_parts ) ) {
				$result .= implode( $formatter->get_end_of_line(), $links_result_parts );
			}
		}

		return $result;
	}

	/**
	 * @param WSAL_AlertFormatter $formatter
	 * @param array $meta_data
	 * @param int $occurrence_id
	 * @param bool $exclude_links_not_needing_formatting If true, links that don't need formatting will be excluded. For example special links that contain onclick attribute already from the meta formatter.
	 *
	 * @return string
	 * @since 4.2.1
	 */
	public function get_hyperlinks_as_array( $formatter, $meta_data, $occurrence_id, $exclude_links_not_needing_formatting = false ) {
		$result = [];
		if ( ! empty( $this->links ) ) {
			foreach ( $this->links as $link_label => $link_data ) {

				$link_title = '';
				$link_url   = '';
				if ( is_string( $link_data ) ) {
					if ( strlen( $link_data ) === 0 ) {
						continue;
					}

					$link_url   = $link_data;
					$link_title = $link_data;
				} else {
					$link_url   = $link_data['url'];
					$link_title = $link_data['label'];
				}

				//  link url can be:
				//  - an actual URL
				//  - placeholder for an existing meta data field that contains a URL (or the full HTML A tag markup)
				//  -- before 4.2.1 the CommentLink meta would contain the full HTML markup for the link, now it contains only the URL
				//  - other placeholder for a dynamic or JS infused link that will be processed by the meta formatter
				$needs_formatting = true;
				if ( ! WSAL_Utilities_RequestUtils::is_valid_url( $link_url ) ) {

					$meta_expression = $this->GetMetaExprValue( $link_url, $meta_data );
					$meta_expression = $formatter->format_meta_expression( $link_url, $meta_expression, $occurrence_id, $meta_data );
					if ( ! empty( $meta_expression ) ) {
						if ( WSAL_Utilities_RequestUtils::is_valid_url( $meta_expression ) ) {

							$link_url = $meta_expression;
						} else if ( preg_match( '/onclick=/', $meta_expression ) ) {
							$link_url         = $meta_expression;
							$needs_formatting = false;
						} else {

							preg_match( '/href=["\']https?:\/\/([^"\']+)["\']/', $meta_expression, $url_matches );
							if ( count( $url_matches ) == 2 ) {
								$link_url = $url_matches[1];
							}
						}
					} else {
						$link_url = '';
					}
				}

				if ( $exclude_links_not_needing_formatting && ! $needs_formatting ) {
					continue;
				}

				if ( ! empty( $link_url ) ) {
					$result[ $link_label ] = [
						'url'              => $link_url,
						'needs_formatting' => $needs_formatting,
						'title'            => $link_title,
						'label'            => $link_label
					];
				}
			}
		}

		return $result;
	}
}
