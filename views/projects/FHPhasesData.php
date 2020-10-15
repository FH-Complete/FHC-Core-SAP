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
		'FH Projekt',
		'FH PhaseID',
		'FH Projektphase'
	),
	'datasetRepOptions' => '{
		index: "projektphase_id",
		layout: "fitColumns",
		persistantLayout: false,
		headerFilterPlaceholder: " ",
		tableWidgetHeader: false,
		selectable: true,     
        selectableRangeMode: "click",
		selectablePersistence: false,
		selectableCheck: function(row){
            return func_selectableCheck(row);
        },
        rowUpdated: function(row){
            resortTable(row);
        },
        rowAdded:function(row){
	        resortTable(row);
	    }
	}',
	'datasetRepFieldsDefs' => '{
		isSynced: {align:"center", editor:false, formatter:"tickCross", width: 80},
		projects_timesheets_project: {visible: false},
		projekt_id: {visible: false},
		projekt_kurzbz: {visible:false},
		projektphase_id: {visible:false},
		bezeichnung: {visible:true}
	}'
);

echo $this->widgetlib->widget('TableWidget', $tableWidgetArray);