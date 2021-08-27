<?php
/**
 * Manager: Constant Manager Class
 *
 * CLass file for constant manager.
 *
 * @since 1.0.0
 * @package wsal
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class used for Constants.
 *
 * E_NOTICE, E_WARNING, E_CRITICAL, etc.
 *
 * @package wsal
 */
class WSAL_ConstantManager {


	/**
	 * Constants array.
	 *
	 * @var array
	 */
	protected $_constants = array();

	/**
	 * Constants Cache.
	 *
	 * @var array
	 */
	protected $constants_cache = array();

	/**
	 * Use an existing PHP constant.
	 *
	 * @param string $name        - Constant name.
	 * @param string $description - Constant description.
	 */
	public function UseConstant( $name, $description = '' ) {
		$this->_constants[] = (object) array(
			'name'        => $name,
			'value'       => constant( $name ),
			'description' => $description,
		);
	}

	/**
	 * Add new PHP constant.
	 *
	 * @param string         $name        - Constant name.
	 * @param integer|string $value       - Constant value.
	 * @param string         $description - Constant description.
	 *
	 * @throws Exception - Error if a constant is already defined.
	 */
	public function AddConstant( $name, $value, $description = '' ) {
		// Check for constant conflict and define new one if required.
		if ( defined( $name ) && constant( $name ) !== $value ) {
			throw new Exception( 'Constant already defined with a different value.' );
		} else {
			define( $name, $value );
		}
		// Add constant to da list.
		$this->UseConstant( $name, $description );
	}

	/**
	 * Add multiple constants in one go.
	 *
	 * @param array $items - Array of arrays with name, value, description pairs.
	 */
	public function AddConstants( $items ) {
		foreach ( $items as $item ) {
			$this->AddConstant( $item['name'], $item['value'], $item['description'] );
		}
	}

	/**
	 * Use multiple constants in one go.
	 *
	 * @param array $items - Array of arrays with name, description pairs.
	 */
	public function UseConstants( $items ) {
		foreach ( $items as $item ) {
			$this->UseConstant( $item['name'], $item['description'] );
		}
	}

	/**
	 * Get constant details by a particular detail.
	 *
	 * @param string $what    - The type of detail: 'name', 'value'.
	 * @param mixed  $value   - The detail expected value.
	 * @param mixed  $default - Default value of constant.
	 *
	 * @throws Exception - Error if detail type is unexpected.
	 *
	 * @return mixed Either constant details (props: name, value, description) or $default if not found.
	 */
	public function GetConstantBy( $what, $value, $default = null ) {
		// Make sure we do have some constants.
		if ( ! empty( $this->_constants ) ) {
			// Make sure that constants do have a $what property.
			if ( ! isset( $this->_constants[0]->$what ) ) {
				throw new Exception( 'Unexpected detail type "' . $what . '".' );
			}

			// Check cache.
			if ( isset( $this->constants_cache[ $value ] ) ) {
				return $this->constants_cache[ $value ];
			}

			$possible_matches = array();
			// Return constant match the property value.
			foreach ( $this->_constants as $constant ) {
				if ( $value == $constant->$what ) {
					$this->constants_cache[ $value ] = $constant;
					$possible_matches[]              = $constant;
				}
			}

			// If we got matches then get the last one in the array,
			if ( ! empty( $possible_matches ) ) {
				return end( $possible_matches );
			}
		}
		return $default;
	}

	/**
	 * Get constant object for displaying.
	 *
	 * @param integer $code - Value of the constant.
	 * @return stdClass
	 */
	public function get_constant_to_display( $code ) {
		$const = (object) array(
			'name'        => 'E_UNKNOWN',
			'value'       => 0,
			'description' => __( 'Unknown error code.', 'wp-security-audit-log' ),
		);

		$const = $this->GetConstantBy( 'value', $code, $const );

		//  CSS property was added in 4.3.0 as part of severity levels refactoring to be able to print language
		//  independent CSS class not based on the constant value
		if ( ! property_exists($const, 'css')) {
			$const->css = strtolower( $const->name );
		}

		if ( 'E_CRITICAL' === $const->name ) {
			$const->name = __( 'Critical', 'wp-security-audit-log' );
		} elseif ( 'E_WARNING' === $const->name ) {
			$const->name = __( 'Warning', 'wp-security-audit-log' );
		} elseif ( 'E_NOTICE' === $const->name ) {
			$const->name = __( 'Notification', 'wp-security-audit-log' );
		} elseif ( 'WSAL_CRITICAL' === $const->name ) {
			$const->name = __( 'Critical', 'wp-security-audit-log' );
		} elseif ( 'WSAL_HIGH' === $const->name ) {
			$const->name = __( 'High', 'wp-security-audit-log' );
		} elseif ( 'WSAL_MEDIUM' === $const->name ) {
			$const->name = __( 'Medium', 'wp-security-audit-log' );
		} elseif ( 'WSAL_LOW' === $const->name ) {
			$const->name = __( 'Low', 'wp-security-audit-log' );
		} elseif ( 'WSAL_INFORMATIONAL' === $const->name ) {
			$const->name = __( 'Informational', 'wp-security-audit-log' );
		}

		return $const;
	}
}
