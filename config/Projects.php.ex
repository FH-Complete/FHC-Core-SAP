<?php

// Enable/disable warnings in projects synchronization
$config['project_warnings_enabled'] = false;

// Project id formats (required)
$config['project_id_formats'] = array(
	'admin_fhtw' => 'ADM-FHTW-%s',		// admin project id format
	'admin_gmbh' => 'ADM-GMBH-%s',		// admin project id format
	'lehre' => 'LEHRE-%s',		// lehre project id format
	'lehrgaenge' => 'LG-%s-%s'	// lehrgaenge project id format
);

// Custom projects id format
$config['project_custom_id_format'] = '%s-%s';

// GMBH Custom Projects Degree Programm ID List
$config['project_gmbh_custom_id_list'] = array(10021, 10027);

// GMBH custom projects id format
$config['project_gmbh_custom_id_format'] = '%s-%s';

// Project name formats for each study semester (required)
$config['project_name_formats'] = array(
	'admin_fhtw' => 'Admin FHTW %s',		// admin project
	'admin_gmbh' => 'Admin GMBH %s',		// admin project
	'lehre' => 'Lehre %s',		// lehre project
	'lehrgaenge' => 'Lehrgaenge %s %s'	// lehrgaenge project
);

// Project structure for each project (optional)
$config['project_structures'] = array(
	'admin_fhtw' => array(		// structure for project admin
		'Admin - %s',
		'Betrieb - %s',
		'Design - %s',
		'Operativ - %s'
	),
	'admin_gmbh' => array(		// structure for project admin
		'Admin - %s',
		'Betrieb - %s',
		'Design - %s',
		'Operativ - %s'
	)
);

// Project unit responsibles (required)
$config['project_unit_responsibles'] = array(
	'admin_fhtw' => 'GF20',	// admin project unit responsible
	'admin_gmbh' => 'GF20',	// admin project unit responsible
	'lehre' => 'GF20',	// lehre project unit responsible
	'lehrgaenge' => 'GF20'	// lehrgaenge project unit responsible
);

// Project person responsibles (required)
$config['project_person_responsibles'] = array(
	'admin_fhtw' => '9',		// admin project person responsible
	'admin_gmbh' => '9',		// admin project person responsible
	'lehre' => '9',		// lehre project person responsible
	'lehrgaenge' => '9'	// lehrgaenge project person responsible
);

// Project person responsible for custom projects (required)
$config['project_person_responsible_custom'] = '9';

// Project person responsible for gmbh custom projects (required)
$config['project_person_responsible_gmbh_custom'] = '9';

// Project types (required)
$config['project_types'] = array(
	'admin_fhtw' => 'Z7',	// admin project type
	'admin_gmbh' => 'Z7',	// admin project type
	'lehre' => 'Z3',	// lehre project type
	'lehrgaenge' => 'Z2'	// lehrgaenge project type
);

// Project type for custom projects (required)
$config['project_type_custom'] = 'Z3';

// Project type for gmbh custom projects (required)
$config['project_type_gmbh_custom'] = 'Z2';

// Enable/disable API call ManagePurchaseOrderIn if it is the case
$config['project_manage_purchase_order_enabled'] = false;

// Purchase order employee responsibles
$config['project_purchase_order_employee_responsible_fhtw'] = 'BRANDSTA';
$config['project_purchase_order_employee_responsible_gmbh'] = 'WIRTHS';

// Purchase order buyer parties
$config['project_purchase_order_buyer_party_fhtw'] = '100000';
$config['project_purchase_order_buyer_party_gmbh'] = '200000';

// Purchase order buyer parties
$config['project_purchase_order_billto_party_fhtw'] = '100000';
$config['project_purchase_order_billto_party_gmbh'] = '200000';

// Purchase order seller parties
$config['project_purchase_order_seller_party_fhtw'] = '100000';
$config['project_purchase_order_seller_party_gmbh'] = '200000';

// Purchase order ship to location
$config['project_purchase_order_shipto_location_fhtw'] = '100200';
$config['project_purchase_order_shipto_location_gmbh'] = '200000';

// Purchase order recipient party
$config['project_purchase_order_recipient_party'] = 'GABRIELE';
