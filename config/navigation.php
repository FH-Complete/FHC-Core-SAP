<?php
// Add Menu-Entry to Main Page
$config['navigation_header']['*']['Organisation']['children']['projekte'] = array(
	'link' => site_url('extensions/FHC-Core-SAP/projects/SyncProjects'),
	'description' => 'Projekte',
	'expand' => true,
	'requiredPermissions' => 'basis/projekt:r'
);

// --------------------------------------------------------------------------------------------------------------------
// Left side menu
$config['navigation_menu']['extensions/FHC-Core-SAP/projects/SyncProjects/*'] = array(
	'dashboard' => array(
		'link' => site_url('extensions/FHC-Core-SAP/projects/SyncProjects'),
		'description' => 'Projekt Synchronisation',
		'icon' => '',
		'sort' => 1,
		'requiredPermissions' => 'basis/projekt:r'
	)
);