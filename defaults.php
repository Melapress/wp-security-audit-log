<?php

// if not included correctly...
if ( !class_exists( 'WpSecurityAuditLog' ) ) exit();

WpSecurityAuditLog::GetInstance()
	->constants->UseConstants(array(
		array('name' => 'E_ERROR', 'description' => 'Fatal run-time error.'),
		array('name' => 'E_WARNING', 'description' => 'Run-time warning (non-fatal error).'),
		array('name' => 'E_PARSE', 'description' => 'Compile-time parse error.'),
		array('name' => 'E_NOTICE', 'description' => 'Run-time notice.'),
		array('name' => 'E_CORE_ERROR', 'description' => 'Fatal error that occurred during startup.'),
		array('name' => 'E_CORE_WARNING', 'description' => 'Warnings that occurred during startup.'),
		array('name' => 'E_COMPILE_ERROR', 'description' => 'Fatal compile-time error.'),
		array('name' => 'E_COMPILE_WARNING', 'description' => 'Compile-time warning.'),
		array('name' => 'E_USER_ERROR', 'description' => 'User-generated error message.'),
		array('name' => 'E_USER_WARNING', 'description' => 'User-generated warning message.'),
		array('name' => 'E_USER_NOTICE', 'description' => 'User-generated notice message. '),
		array('name' => 'E_STRICT', 'description' => 'Non-standard/optimal code warning.'),
		array('name' => 'E_RECOVERABLE_ERROR', 'description' => 'Catchable fatal error.'),
		array('name' => 'E_DEPRECATED', 'description' => 'Run-time deprecation notices.'),
		array('name' => 'E_USER_DEPRECATED', 'description' => 'Run-time user deprecation notices.'),
	));

WpSecurityAuditLog::GetInstance()
	->alerts->Register(
		array(0001, E_ERROR, 'PHP Error', '%Message%.'),
		array(0002, E_WARNING, 'PHP Warning', '%Message%.'),
		array(0003, E_NOTICE, 'PHP Notice', '%Message%.'),
		
		array(1000, E_NOTICE, 'User logs in', 'Successfully logged in.'),
		array(1001, E_NOTICE, 'User logs out', 'Successfully logged out.'),
		array(1002, E_WARNING, 'Login failed', 'Failed login detected using "%Username%" as username.'),
		array(2010, E_NOTICE, 'User uploaded file from Uploads directory', ''),
		array(2011, E_WARNING, 'User deleted file from Uploads directory', '')
	);