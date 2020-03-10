<?php

$config['fhc_sap_active_connection'] = 'DEVELOPMENT'; // the used configuration set of the chosen connection

// Example of a configuration set. All parameters are required!
$config['fhc_sap_connections'] = array(
	'DEVELOPMENT' => array(
		'SAPCoreAPI' => array(
			'login' => 'SAPCoreAPIUsername',
			'password' => 'SAPCoreAPIPassword'
		),
		'API set 2 NAME' => array(
			'login' => 'LOGIN2', // basic HTTP authentication username
			'password' => 'PASSWORD2' // basic HTTP authentication password
		)
	),
	'PRODUCTION' => array(
		'SAPCoreAPI' => array(
			'login' => 'SAPCoreAPIUsername', // basic HTTP authentication username
			'password' => 'SAPCoreAPIPassword' // basic HTTP authentication password
		)
	)
);
