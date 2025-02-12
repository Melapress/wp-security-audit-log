<?php
/**
 * Extension: Base fields.
 *
 * @since 5.1.0
 *
 * @package   wsal
 * @subpackage entities
 */

declare(strict_types=1);

namespace WSAL\Entities;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WSAL\Entities\Query_Builder_Parser' ) ) {

	/**
	 * Takes QueryBuilder array and converts it into PHP compatible query which can be used in simple IF statement.
	 *
	 * @since 5.2.1
	 */
	class Query_Builder_Parser {

		/**
		 * Operators which required array values
		 *
		 * @var array
		 *
		 * @since 5.2.1
		 */
		protected static $needs_array = array(
			'IN',
			'NOT IN',
			'BETWEEN',
			'NOT BETWEEN',
		);

		/**
		 * SQL conditions to PHP conditions mapper
		 *
		 * @var array
		 *
		 * @since 5.2.1
		 */
		public static $conditions = array(
			'and' => 'AND',
			'or'  => 'OR',
		);

		/**
		 * Prepared PHP query string
		 *
		 * @var string
		 *
		 * @since 5.2.1
		 */
		private static $query = '';

		/**
		 * Operators and modifiers they support.
		 *
		 * @var array
		 *
		 * @since 5.2.1
		 */
		protected static $operators = array(
			'equal'            => array(
				'accept_values' => true,
				'apply_to'      => array( 'string', 'number', 'datetime' ),
			),
			'not_equal'        => array(
				'accept_values' => true,
				'apply_to'      => array( 'string', 'number', 'datetime' ),
			),
			'in'               => array(
				'accept_values' => true,
				'apply_to'      => array( 'string', 'number', 'datetime' ),
			),
			'not_in'           => array(
				'accept_values' => true,
				'apply_to'      => array( 'string', 'number', 'datetime' ),
			),
			'less'             => array(
				'accept_values' => true,
				'apply_to'      => array( 'number', 'datetime' ),
			),
			'less_or_equal'    => array(
				'accept_values' => true,
				'apply_to'      => array( 'number', 'datetime' ),
			),
			'greater'          => array(
				'accept_values' => true,
				'apply_to'      => array( 'number', 'datetime' ),
			),
			'greater_or_equal' => array(
				'accept_values' => true,
				'apply_to'      => array( 'number', 'datetime' ),
			),
			'between'          => array(
				'accept_values' => true,
				'apply_to'      => array( 'number', 'datetime' ),
			),
			'not_between'      => array(
				'accept_values' => true,
				'apply_to'      => array( 'number', 'datetime' ),
			),
			'begins_with'      => array(
				'accept_values' => true,
				'apply_to'      => array( 'string' ),
			),
			'not_begins_with'  => array(
				'accept_values' => true,
				'apply_to'      => array( 'string' ),
			),
			'contains'         => array(
				'accept_values' => true,
				'apply_to'      => array( 'string' ),
			),
			'not_contains'     => array(
				'accept_values' => true,
				'apply_to'      => array( 'string' ),
			),
			'ends_with'        => array(
				'accept_values' => true,
				'apply_to'      => array( 'string' ),
			),
			'not_ends_with'    => array(
				'accept_values' => true,
				'apply_to'      => array( 'string' ),
			),
			'is_empty'         => array(
				'accept_values' => false,
				'apply_to'      => array( 'string' ),
			),
			'is_not_empty'     => array(
				'accept_values' => false,
				'apply_to'      => array( 'string' ),
			),
			'is_null'          => array(
				'accept_values' => false,
				'apply_to'      => array( 'string', 'number', 'datetime' ),
			),
			'is_not_null'      => array(
				'accept_values' => false,
				'apply_to'      => array( 'string', 'number', 'datetime' ),
			),
		);

		/**
		 * SQL operators mapper (equal is converted to PHP)
		 *
		 * @var array
		 *
		 * @since 5.2.1
		 */
		protected static $operator_sql = array(
			'equal'            => array( 'operator' => '=' ),
			'not_equal'        => array( 'operator' => '!=' ),
			'in'               => array( 'operator' => 'IN' ),
			'not_in'           => array( 'operator' => 'NOT IN' ),
			'less'             => array( 'operator' => '<' ),
			'less_or_equal'    => array( 'operator' => '<=' ),
			'greater'          => array( 'operator' => '>' ),
			'greater_or_equal' => array( 'operator' => '>=' ),
			'between'          => array( 'operator' => 'BETWEEN' ),
			'not_between'      => array( 'operator' => 'NOT BETWEEN' ),
			'begins_with'      => array(
				'operator' => 'LIKE',
				'prepend'  => '%',
			),
			'not_begins_with'  => array(
				'operator' => 'NOT LIKE',
				'prepend'  => '%',
			),
			'contains'         => array(
				'operator' => 'LIKE',
				'append'   => '%',
				'prepend'  => '%',
			),
			'not_contains'     => array(
				'operator' => 'NOT LIKE',
				'append'   => '%',
				'prepend'  => '%',
			),
			'ends_with'        => array(
				'operator' => 'LIKE',
				'append'   => '%',
			),
			'not_ends_with'    => array(
				'operator' => 'NOT LIKE',
				'append'   => '%',
			),
			'is_empty'         => array( 'operator' => '=' ),
			'is_not_empty'     => array( 'operator' => '!=' ),
			'is_null'          => array( 'operator' => 'NULL' ),
			'is_not_null'      => array( 'operator' => 'NOT NULL' ),
		);

		/**
		 * Query_Builder_Parser's parse function!
		 *
		 * Build a query based on JSON that has been passed into the function, onto the builder passed into the function.
		 *
		 * @param string $json - The JSON string to be parsed.
		 *
		 * @throws \Exception - If something bad happens during parsing.
		 *
		 * @return string
		 */
		public static function parse( $json ) {

			self::$query = '';

			// do a JSON decode (throws exceptions if there is a JSON error...) .
			$query = self::decode_json( $json );

			// This can happen if the querybuilder had no rules...
			if ( ! isset( $query['rules'] ) || ! is_array( $query['rules'] ) ) {
				return '';
			}

			if ( empty( $query['rules'] ) ) {
				return '';
			}

			// This shouldn't ever cause an issue, but may as well not go through the rules.
			if ( count( $query['rules'] ) < 1 ) {
				return '';
			}

			self::loop_through_rules( $query['rules'], $query['condition'] );

			return self::strip_last_condition( self::$query, $query['condition'] );
		}

		/**
		 * Returns the sql operators array.
		 *
		 * @return array
		 *
		 * @since 5.3.0
		 */
		public static function get_operator_sql(): array {
			return self::$operator_sql;
		}

		/**
		 * Called by parse, loops through all the rules to find out if nested or not.
		 *
		 * @param array  $rules - Rules to be parsed.
		 * @param string $query_condition - The SQL condition to be applied.
		 *
		 * @throws \Exception - If parsing fails.
		 */
		protected static function loop_through_rules( array $rules, $query_condition = 'AND' ) {
			foreach ( $rules as $rule ) {
				/*
				 * If make_query does not see the correct fields, it will return the QueryBuilder without modifications
				 */
				self::$query .= self::make_query( $rule, $query_condition );

				if ( self::is_nested( $rule ) ) {
					self::$query .= '( ' . self::strip_last_condition( self::create_nested_query( $rule, $query_condition ), $query_condition ) . ' ) ' . $rule['condition'] . ' ';
				}
			}

			if ( ! isset( $rule ) || ! isset( $rule['condition'] ) ) {
				return;
			}

			self::$query = self::strip_last_condition( self::$query, $rule['condition'] );
		}

		/**
		 * If that is the final string - lets strip the last condition from it - it is not needed.
		 *
		 * @param string $query - The query string.
		 * @param string $query_condition - The condition string.
		 *
		 * @return string
		 *
		 * @since 5.2.1
		 */
		private static function strip_last_condition( string $query, string $query_condition ): string {
			$condition = self::$conditions[ strtolower( $query_condition ) ];

			return \rtrim( $query, $condition . ' ' );
		}

		/**
		 * Determine if a particular rule is actually a group of other rules.
		 *
		 * @param mixed $rule - Array of rules.
		 *
		 * @return bool
		 */
		protected static function is_nested( $rule ) {
			if ( isset( $rule['rules'] ) && is_array( $rule['rules'] ) && count( $rule['rules'] ) > 0 ) {
				return true;
			}

			return false;
		}

		/**
		 * Create nested queries
		 *
		 * When a rule is actually a group of rules, we want to build a nested query with the specified condition (AND/OR)
		 *
		 * @param array       $rule - The rule to build query from.
		 * @param string|null $condition - The SQL condition to be applied to the query.
		 *
		 * @return string
		 */
		protected static function create_nested_query( array $rule, $condition = null ) {
			if ( null === $condition ) {
				$condition = $rule['condition'];
			}
			$query_builder = '';
			$condition     = self::validate_condition( $condition );

			foreach ( $rule['rules'] as $loop_rule ) {
				$function = 'make_query';

				if ( self::is_nested( $loop_rule ) ) {
					$function = 'create_nested_query';
				}

				if ( method_exists( __CLASS__, $function ) ) {
					$query_builder .= call_user_func_array( array( __CLASS__, $function ), array( $loop_rule, $rule['condition'] ) );
				}
			}

			return self::strip_last_condition( $query_builder, $rule['condition'] );
		}

		/**
		 * Check if a given rule is correct.
		 *
		 * Just before making a query for a rule, we want to make sure that the field, operator and value are set
		 *
		 * @param array $rule - The rule to check.
		 *
		 * @return bool true if values are correct.
		 */
		protected static function check_rule_correct( array $rule ) {
			if ( ! isset( $rule['operator'], $rule['id'], $rule['field'], $rule['type'] ) ) {
				return false;
			}

			if ( ! isset( self::$operators[ $rule['operator'] ] ) ) {
				return false;
			}

			return true;
		}

		/**
		 * Give back the correct value when we don't accept one.
		 *
		 * @param array $rule - The rule to check against.
		 *
		 * @return null|string
		 */
		protected static function operator_value_when_not_accepting_one( array $rule ) {
			if ( 'is_empty' == $rule['operator'] || 'is_not_empty' == $rule['operator'] ) {
				return '';
			}

			return null;
		}

		/**
		 * Ensure that the value for a field is correct.
		 *
		 * Append/Prepend values for SQL statements, etc.
		 *
		 * @param string $operator - The operator to check.
		 * @param array  $rule - The rule to check.
		 * @param mixed  $value - The value to check.
		 *
		 * @throws \Exception - If something goes wrong.
		 *
		 * @return string
		 */
		public static function get_correct_value( string $operator, array $rule, $value ) {
			$field         = $rule['field'];
			$sql_operator  = self::$operator_sql[ $rule['operator'] ];
			$require_array = self::operator_requires_array( $operator );

			$value = self::enforce_array_or_string( $require_array, $value, $field );

			$value = self::append_operator_if_required( $require_array, $value, $sql_operator );

			if ( 'string' === $rule['type'] || 'date' === $rule['type'] ) {
				$value = "'" . $value . "'";
			}

			/*
			*  Turn datetime into Carbon object so that it works with "between" operators etc.
			*/
			// if ( 'date' == $rule['type'] ) {
				// $value = $this->convertDatetimeToCarbon( $value );
			// }

			return $value;
		}

		/**
		 * The query maker!
		 *
		 * Take a particular rule and make build something that the QueryBuilder would be proud of.
		 *
		 * Make sure that all the correct fields are in the rule object then add the expression to
		 * the query that was given by the user to the QueryBuilder.
		 *
		 * @param array  $rule - The rule array to check.
		 * @param string $query_condition  - String with the query condition and/or...
		 *
		 * @throws \Exception - If there is something wrong.
		 *
		 * @return string
		 */
		protected static function make_query( array $rule, $query_condition = 'AND' ) {
			/*
			 * Ensure that the value is correct for the rule, return query on exception
			 */
			self::validate_condition( $query_condition );
			try {
				$value = self::get_value_for_query_from_rule( $rule );
			} catch ( \Exception $e ) {
				return '';
			}

			return self::convert_incoming_qbto_query( $rule, $value, $query_condition );
		}

		/**
		 * Convert an incoming rule from jQuery QueryBuilder to the Eloquent Querybuilder
		 *
		 * (This used to be part of make_query, where the name made sense, but I pulled it
		 * out to reduce some duplicated code inside JoinSupportingQueryBuilder)
		 *
		 * @param array  $rule - Array with rules.
		 * @param mixed  $value - The value that needs to be queried in the database.
		 * @param string $query_condition  - Condition - and/or...
		 *
		 * @return string
		 */
		protected static function convert_incoming_qbto_query( array $rule, $value, $query_condition = 'AND' ) {
			/*
			 * Convert the Operator (LIKE/NOT LIKE/GREATER THAN) given to us by QueryBuilder
			 * into on one that we can use inside the SQL query
			 */
			$sql_operator = self::$operator_sql[ $rule['operator'] ];
			$operator     = $sql_operator['operator'];
			$condition    = self::$conditions[ strtolower( $query_condition ) ];

			if ( self::operator_requires_array( $operator ) ) {
				return self::make_query_when_array( $rule, $sql_operator, $value, $condition );
			} elseif ( self::operator_is_null( $operator ) ) {
				return self::make_query_when_null( $rule, $sql_operator, $condition );
			}

			return $rule['field'] . ' ' . $sql_operator['operator'] . ' ' . $value . ' ' . $condition . ' ';
		}

		/**
		 * Ensure that the value is correct for the rule, try and set it if it's not.
		 *
		 * @param array $rule - The array with rules.
		 *
		 * @return mixed
		 */
		protected static function get_value_for_query_from_rule( array $rule ) {

			$value = self::get_rule_value( $rule );

			/*
			 * If the SQL Operator is set not to have a value, make sure that we set the value to null.
			 */
			if ( false === self::$operators[ $rule['operator'] ]['accept_values'] ) {
				return self::operator_value_when_not_accepting_one( $rule );
			}

			/*
			 * Convert the Operator (LIKE/NOT LIKE/GREATER THAN) given to us by QueryBuilder
			 * into on one that we can use inside the SQL query
			 */
			$sql_operator = self::$operator_sql[ $rule['operator'] ];
			$operator     = $sql_operator['operator'];

			/*
			 * \o/ Ensure that the value is an array only if it should be.
			 */
			$value = self::get_correct_value( $operator, $rule, $value );

			return $value;
		}

		/**
		 * Checks if given operator requires array of values.
		 *
		 * @param string $operator - The SQL operator to be applied.
		 *
		 * @return bool
		 *
		 * @since 5.2.1
		 */
		protected static function operator_requires_array( string $operator ): bool {
			return in_array( $operator, self::$needs_array );
		}

		/**
		 * Determine if an operator is NULL/NOT NULL
		 *
		 * @param string $operator - The operator to check for.
		 *
		 * @return bool
		 */
		protected static function operator_is_null( $operator ): bool {
			return ( 'NULL' == $operator || 'NOT NULL' == $operator ) ? true : false;
		}

		/**
		 * Make sure that a condition is either 'or' or 'and'.
		 *
		 * @param  string $condition  - The condition to validate.
		 *
		 * @return string
		 *
		 * @throws \Exception - If provided is not the condition we expect.
		 */
		protected static function validate_condition( $condition ) {
			if ( is_null( $condition ) ) {
				return $condition;
			}

			$condition = trim( strtolower( $condition ) );

			if ( 'and' !== $condition && 'or' !== $condition ) {
				throw new \Exception( "Condition can only be one of: 'and', 'or'." );
			}

			return $condition;
		}

		/**
		 * Enforce whether the value for a given field is the correct type
		 *
		 * @param bool   $require_array - Value must be an array.
		 * @param mixed  $value  - The value we are checking against.
		 * @param string $field - The field that we are enforcing.
		 *
		 * @return mixed value after enforcement
		 *
		 * @throws \Exception - Value is not a correct type.
		 */
		protected static function enforce_array_or_string( $require_array, $value, $field ) {
			self::check_field_is_an_array( $require_array, $value, $field );

			if ( ! $require_array && is_array( $value ) ) {
				return self::convert_array_to_flat_value( $field, $value );
			}

			return $value;
		}

		/**
		 * Ensure that a given field is an array if required.
		 *
		 * @see enforce_array_or_string
		 *
		 * @param boolean $require_array - Is array type required.
		 * @param mixed   $value - The value to be checked.
		 * @param string  $field - The name of the field to check.
		 *
		 * @throws \Exception - If field is array - that is not correct here.
		 */
		protected static function check_field_is_an_array( $require_array, $value, $field ) {
			if ( $require_array && ! is_array( $value ) ) {
				throw new \Exception( "Field ($field) should be an array, but it isn't." );
			}
		}

		/**
		 * Convert an array with just one item to a string.
		 *
		 * In some instances, and array may be given when we want a string.
		 *
		 * @see enforce_array_or_string
		 *
		 * @param string $field - The name of the field to show in the error.
		 * @param mixed  $value - The value to check and return first one if it is array.
		 *
		 * @return mixed
		 *
		 * @throws \Exception - Throws exception if field is not an array.
		 */
		protected static function convert_array_to_flat_value( $field, $value ) {
			if ( count( $value ) !== 1 ) {
				throw new \Exception( "Field ($field) should not be an array, but it is." );
			}

			return $value[0];
		}

		/**
		 * Append or prepend a string to the query if required.
		 *
		 * @param bool  $require_array value must be an array.
		 * @param mixed $value the value we are checking against.
		 * @param mixed $sql_operator - The SQL operator.
		 *
		 * @return mixed $value
		 */
		protected static function append_operator_if_required( $require_array, $value, $sql_operator ) {
			if ( ! $require_array ) {
				if ( isset( $sql_operator['append'] ) ) {
					$value = $sql_operator['append'] . $value;
				}

				if ( isset( $sql_operator['prepend'] ) ) {
					$value = $value . $sql_operator['prepend'];
				}
			}

			return $value;
		}

		/**
		 * Decode the given JSON
		 *
		 * @param string $json - The JSON string to decode.
		 *
		 * @throws \Exception - If the JSON string is invalid.
		 *
		 * @return array
		 */
		private static function decode_json( $json ) {
			if ( null == $json || 'null' == $json ) {
				return array();
			}

			$query = json_decode( $json, true );

			if ( json_last_error() ) {
				throw new \Exception( 'JSON parsing threw an error: ' . json_last_error_msg() );
			}

			if ( ! \is_array( $query ) ) {
				throw new \Exception( 'The query is not valid JSON' );
			}

			return $query;
		}

		/**
		 * Get a value for a given rule.
		 *
		 * @param array $rule - The array with rules.
		 *
		 * @throws \Exception - Throws an exception if the rule is not correct.
		 */
		private static function get_rule_value( array $rule ) {
			if ( ! self::check_rule_correct( $rule ) ) {
				throw new \Exception();
			}

			return $rule['value'];
		}

		/**
		 * That method takes care of the arrays.
		 *
		 * Some types of SQL Operators (ie, those that deal with lists/arrays) have specific requirements.
		 * This function enforces those requirements.
		 *
		 * @param array  $rule - The array with the rules.
		 * @param array  $sql_operator - The SQL Operator.
		 * @param array  $value - The value to be checked.
		 * @param string $condition - The SQL condition to be applied.
		 *
		 * @return string
		 *
		 * @throws \Exception - If there is no value to be returned.
		 */
		protected static function make_query_when_array( array $rule, array $sql_operator, array $value, $condition ) {
			if ( 'IN' == $sql_operator['operator'] || 'NOT IN' == $sql_operator['operator'] ) {
				return self::make_array_query_in( $rule, $sql_operator['operator'], $value, $condition );
			} elseif ( 'BETWEEN' == $sql_operator['operator'] || 'NOT BETWEEN' == $sql_operator['operator'] ) {
				return self::make_array_query_between( $rule, $sql_operator['operator'], $value, $condition );
			}

			throw new \Exception( 'make_query_when_array could not return a value' );
		}

		/**
		 * Create a 'null' query when required.
		 *
		 * @param array  $rule - Array of rules.
		 * @param array  $sql_operator - The SQL operator.
		 * @param string $condition - The condition.
		 *
		 * @return String
		 *
		 * @throws \Exception - When SQL operator is !null.
		 */
		protected static function make_query_when_null( array $rule, array $sql_operator, $condition ) {
			if ( 'NULL' == $sql_operator['operator'] ) {
				return 'null == \$' . $rule['field'] . ' ' . $condition . ' ';
			} elseif ( 'NOT NULL' == $sql_operator['operator'] ) {
				return 'null != \$' . $rule['field'] . ' ' . $condition . ' ';
			}

			throw new \Exception( 'make_query_when_null was called on an SQL operator that is not null' );
		}

		/**
		 * Method to apply when the query is an IN or NOT IN...
		 *
		 * @see make_query_when_array
		 *
		 * @param array  $rule - Array with rules.
		 * @param string $operator - The SQL operator to be used.
		 * @param array  $value - The value to be checked.
		 * @param string $condition - The condition.
		 *
		 * @return string
		 */
		private static function make_array_query_in( array $rule, $operator, array $value, $condition ) {
			if ( 'NOT IN' == $operator ) {
				return '! in_array( \$' . $rule['field'] . ', array( ' . \implode( ', ', $value ) . ' ) ) ' . $condition . ' ';
			}

			return 'in_array( \$' . $rule['field'] . ', array( ' . \implode( ', ', $value ) . ' ) ) ' . $condition . ' ';
		}

		/**
		 * Method for cases, when the query is a BETWEEN or NOT BETWEEN...
		 *
		 * @see make_query_when_array
		 *
		 * @param array  $rule - The array of rules.
		 * @param string $operator - The SQL operator used. [BETWEEN|NOT BETWEEN].
		 * @param array  $value - Array of values to be matched.
		 * @param string $condition - The SQL condition.
		 *
		 * @throws \Exception When more then two items given for the between.
		 *
		 * @return string
		 */
		private static function make_array_query_between( array $rule, $operator, array $value, $condition ) {
			if ( count( $value ) !== 2 ) {
				throw new \Exception( "{$rule['field']} should be an array with only two items." );
			}

			if ( 'NOT BETWEEN' == $operator ) {
				return '!( \$' . $rule['field'] . ' >= ' . $value[0] . ' && \$' . $rule['field'] . ' <= ' . $value[1] . ') ' . $condition . ' ';
			}

			return '( \$' . $rule['field'] . ' >= ' . $value[0] . ' && \$' . $rule['field'] . ' <= ' . $value[1] . ') ' . $condition . ' ';
		}
	}
}
