<?php

final class WSAL_Alert {
	
	/**
	 * Alert type (used when triggering an alert etc).
	 * @var integer
	 */
	public $type = 0;
	
	/**
	 * Alert error level (E_* constant).
	 * @var integer
	 */
	public $code = 0;
	
	/**
	 * Alert category (alerts are grouped by matching categories).
	 * @var string
	 */
	public $catg = '';
	
	/**
	 * Alert description (ie, describes what happens when alert is triggered).
	 * @var string
	 */
	public $desc = '';
	
	/**
	 * Alert message (variables between '%' are expanded to values).
	 * @var string
	 */
	public $mesg = '';
	
	public function __construct($type = 0, $code = 0, $catg = '', $desc = '', $mesg = '') {
		$this->type = $type;
		$this->code = $code;
		$this->catg = $catg;
		$this->desc = $desc;
		$this->mesg = $mesg;
	}
	
}
