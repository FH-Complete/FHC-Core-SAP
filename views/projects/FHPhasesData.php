<?php

// NOTE: This is a pseudo query to be able to start with an empty table.
// Table will be filled with data by user interaction (ajax call).
$qry = 'SELECT * FROM (VALUES (1, 1, 1, 1, 1, 1)) AS tmp (
			"isSynced",
		    "projects_timesheets_project",
		    "projekt_id",
		    "projekt_kurzbz",
		    "projektphase_id",
		    "bezeichnung"
	) LIMIT 0;';

$tableWidgetArray = array(
	'query' => $qry,
	'tableUniqueId' => 'FUEPhases',
	'requiredPermissions' => 'basis/projekt',
	'datasetRepresentation' => 'tabulator',
	'columnsAliases' => array(
		'Synced',
		'SyncID',
		'FH ProjektTimesheetID',
		'FH Projekt-Nr',
		'FH Phase-ID',
		'FH Phasenbz'
	),
	'datasetRepOptions' => '{
		index: "projektphase_id",
		height: "300px",
		layout: "fitColumns",
		persistantLayout: false,
		headerFilterPlaceholder: " ",
		tableWidgetHeader: false,
		selectable: 1,
		selectablePersistence: false,
	    rowUpdated: function(row){
	        row.deselect();
        }
	}',
	'datasetRepFieldsDefs' => '{
		isSynced: {align:"center", editor:false, formatter:"tickCross", width: 80},
		projects_timesheets_project: {visible: false},
		projekt_id: {visible: false},
		projekt_kurzbz: {visible:false},
		projektphase_id: {tooltip: true},
		bezeichnung: {tooltip: true}
	}'
);

echo $this->widgetlib->widget('TableWidget', $tableWidgetArray);