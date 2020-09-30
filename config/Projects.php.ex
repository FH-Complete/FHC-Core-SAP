<?php

// Project id formats (required)
$config['project_id_formats'] = array(
	'admin' => 'ADM-%s',		// admin project id format
	'lehre' => 'LEHRE-%s',		// lehre project id format
	'lehrgaenge' => 'LG-%s-%s'	// lehrgaenge project id format
);

// Custom projects id format
$config['project_custom_id_format'] = '%s-%s';

// Project name formats for each study semester (required)
$config['project_name_formats'] = array(
	'admin' => 'Admin %s',		// admin project
	'lehre' => 'Lehre %s',		// lehre project
	'lehrgaenge' => 'Lehrgaenge %s %s'	// lehrgaenge project
);

// Project structure for each project (optional)
$config['project_structures'] = array(
	'admin' => array(		// structure for project admin
		'Admin - %s',
		'Betrieb - %s',
		'Design - %s',
		'Operativ - %s'
	)
);

// Project unit responsibles (required)
$config['project_unit_responsibles'] = array(
	'admin' => 'GF20',	// admin project unit responsible
	'lehre' => 'GF20',	// lehre project unit responsible
	'lehrgaenge' => 'GF20'	// lehrgaenge project unit responsible
);

// Project person responsibles (required)
$config['project_person_responsibles'] = array(
	'admin' => '9',		// admin project person responsible
	'lehre' => '9',		// lehre project person responsible
	'lehrgaenge' => '9'	// lehrgaenge project person responsible
);

// Project person responsible for custom projects (required)
$config['project_person_responsible_custom'] = '9';

// Project types (required)
$config['project_types'] = array(
	'admin' => 'Z7',	// admin project type
	'lehre' => 'Z3',	// lehre project type
	'lehrgaenge' => 'Z2'	// lehrgaenge project type
);

// Project type for custom projects (required)
$config['project_type_custom'] = 'Z3';

// Enable/disable API call ManagePurchaseOrderIn if it is the case
$config['project_manage_purchase_order_enabled'] = false;

