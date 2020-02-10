<?php

$config['fhc_sap_active_connection'] = 'DEFAULT'; // the used configuration set of the chosen connection

// Example of a configuration set. All parameters are required!
$config['fhc_sap_connections'] = array(
	'DEFAULT' => array(
		'wsdl' => 'https://my350522.sapbydesign.com/sap/bc/srt/wsdl/srvc_00163E9CE8031EEA92C19602BE6B1093/wsdl11/allinone/standard/document', // URI to the WSDL
		'options' => array(
			'login' => '_FHCOMPLETE', // basic HTTP authentication username
			'password' => 'RcaJpMD69PaYNomESwFi2WF2VPKRD' // basic HTTP authentication password
		)
	)
);
