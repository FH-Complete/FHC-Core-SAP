<?php
$qry = '
	SELECT
				CASE
				WHEN projects_timesheets_project_id IS NOT NULL THEN \'true\'
				ELSE \'false\'
				END AS "isSynced",
				projects_timesheet_id,
				project_id,
				CONCAT(name, \' (\' ,project_id ,\')\') as name,
				status,
				deleted
	FROM    	sync.tbl_sap_projects_timesheets
	LEFT JOIN 	sync.tbl_projects_timesheets_project synctbl USING (projects_timesheet_id)
	WHERE 		project_task_id IS NULL
	ORDER BY 	projects_timesheets_project_id, project_id
';

$tableWidgetArray = array(
	'query' => $qry,
	'tableUniqueId' => 'SAPProjects',
	'requiredPermissions' => 'basis/projekt',
	'datasetRepresentation' => 'tabulator',
	'columnsAliases' => array(
		'Synced',
		'ProjectTimesheetID',
		'ProjectID',
		'Projekt',
		'Status',
		'Deleted'
	),
	'datasetRepOptions' => '{
		index: "projects_timesheet_id",
		layout: "fitColumns",
		persistantLayout: false,
		headerFilterPlaceholder: " ",
		selectable: 1,
		selectablePersistence: false,
		rowClick: function(e, row){
            func_rowClick_onSAPProject(e, row);
        },
		tableWidgetHeader: false,
		rowSelected: function(row){
			rowSelected_onSAPProject(row);
		},
	    rowUpdated: function(row){
            resortTable(row);
        },
         rowAdded:function(row){
	        resortTable(row);
	    }
	}',
	'datasetRepFieldsDefs' => '{
		isSynced: {headerFilter:"input", align:"center", editor:false, formatter:"tickCross", width: 100},
		projects_timesheet_id: {visible: false},
		project_id: {visible: false},
		name: {headerFilter: "input"},
		status: {visible: false},
		deleted: {visible: false}
	}'
);

echo $this->widgetlib->widget('TableWidget', $tableWidgetArray);
