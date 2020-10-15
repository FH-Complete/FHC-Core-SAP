<?php

// NOTE: This is a pseudo query to be able to start with an empty table.
// Table will be filled with data by user interaction (ajax call).
$qry = '
	SELECT * FROM (VALUES (1, 1, 1, 1, 1)) AS tmp (
	"isSynced",
    "projects_timesheets_project",
    "projects_timesheet_id",
    "project_id",
    "project_task_id"
	) LIMIT 0;
';

$tableWidgetArray = array(
	'query' => $qry,
	'tableUniqueId' => 'SAPPhases',
	'requiredPermissions' => 'basis/projekt',
	'datasetRepresentation' => 'tabulator',
	'columnsAliases' => array(
		'Synced',
		'SyncID',
		'SAP ProjektTimesheetID',
		'SAP Projekt',
		'SAP Projektphase'
	),
	'datasetRepOptions' => '{
		index: "projects_timesheet_id",
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
		projects_timesheet_id: {visible: false},
		project_id: {visible:false},
		project_task_id: {visible:true}
	}'
);

echo $this->widgetlib->widget('TableWidget', $tableWidgetArray);
