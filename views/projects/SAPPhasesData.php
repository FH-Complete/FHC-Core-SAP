<?php

// NOTE: This is a pseudo query to be able to start with an empty table.
// Table will be filled with data by user interaction (ajax call).
$qry = '
	SELECT * FROM (VALUES (1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1)) AS tmp (
	"isSynced",
	"status",
    "projects_timesheets_project",
    "projects_timesheet_id",
    "project_id",
    "start_date",
    "end_date",
    "time_recording",
    "project_task_id",
    "name",
    "projektphase_id",
    "bezeichnung",
    "deleted"
	) LIMIT 0;
';

$tableWidgetArray = array(
	'query' => $qry,
	'tableUniqueId' => 'SAPPhases',
	'requiredPermissions' => 'basis/projekt',
	'datasetRepresentation' => 'tabulator',
	'columnsAliases' => array(
		'Synced',
		'Status',
		'SyncID',
		'SAP ProjektTimesheetID',
		'SAP ProjektID',
		'Start',
		'Ende',
		'ZA-pflichtig',
		'SAP Phase-ID',
		'SAP Phase',
		'FH Phase-ID',
		'FH Phase',
		'Deleted'
	),
	'datasetRepOptions' => '{
		index: "projects_timesheet_id",
		height: "300px",
		layout: "fitColumns",
		persistantLayout: false,
		headerFilterPlaceholder: " ",
		tableWidgetHeader: false,
	    selectable: true,
        selectableRangeMode: "click",
		selectablePersistence: false,
        rowAdded:function(row){
	        resortTable(row);
	    }
	}',
	'datasetRepFieldsDefs' => '{
		isSynced: {align:"center", editor:false, formatter:"tickCross", width: 80},
		status: {
			formatter:"lookup",
			formatterParams:getSAPPhasesStatusbezeichnung
		},
		projects_timesheets_project: {visible: false},
		projects_timesheet_id: {visible: false},
		project_id: {visible:false},
		start_date: {visible: true, mutator: mut_formatStringDate},
		end_date: {visible: true, mutator: mut_formatStringDate},
		time_recording: {visible: true},
		project_task_id: {visible: true, tooltip: true},
		name: {visible: true, tooltip: true},
		projektphase_id: {visible: true, tooltip: true},
		bezeichnung: {visible: true, tooltip: true},
		deleted: {visible: false}
	}'
);

echo $this->widgetlib->widget('TableWidget', $tableWidgetArray);
