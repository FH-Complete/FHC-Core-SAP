<?php

// Max number of cost centers to create. Used for debuggig, by default -1 => disabled
$config['project_max_number_cost_centers'] = 3;

// Project id formats (required)
$config['project_id_formats'] = array(
	'admin' => 'ADM-%s',		// admin project id format
	'lehre' => 'LEH-%s',		// lehre project id format
	'lehrgaenge' => 'LEG-%s'	// lehrgaenge project id format
);

// Project name formats for each study semester (required)
$config['project_name_formats'] = array(
	'admin' => 'Admin - %s',		// admin project
	'lehre' => 'Lehre - %s',		// lehre project
	'lehrgaenge' => 'Lehrgaenge - %s'	// lehrgaenge project
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

// Project types (required)
$config['project_types'] = array(
	'admin' => '20',	// admin project type
	'lehre' => 'Z3',	// lehre project type
	'lehrgaenge' => 'Z2'	// lehrgaenge project type
);

