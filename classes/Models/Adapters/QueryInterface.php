<?php

interface WSAL_Adapters_QueryInterface {
	
	public function Execute();
	public function count();
	public function Delete();
	public function Where($cond, $args);
}
