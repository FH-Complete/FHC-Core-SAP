<?php
$qry = '
	SELECT
				CASE
				WHEN projects_timesheets_project_id IS NOT NULL THEN \'true\'
				ELSE \'false\'
				END AS "isSynced",
				CASE
					WHEN deleted = true THEN 99
					ELSE status
		        END AS "status",
				projects_timesheet_id,
				start_date::date,
				end_date::date,
				oe.oe_kurzbz AS "oe_kurzbz",
				oe.bezeichnung AS "oe_bezeichnung",
				project_id,
				name,
				projekt_id,
				projekt_kurzbz,
				titel,
				deleted
	FROM    	sync.tbl_sap_projects_timesheets
	LEFT JOIN 	sync.tbl_projects_timesheets_project synctbl USING (projects_timesheet_id)
	LEFT JOIN 	fue.tbl_projekt USING (projekt_id)
	LEFT JOIN   sync.tbl_sap_organisationsstruktur sap_oe ON (responsible_unit = sap_oe.oe_kurzbz_sap)
	LEFT JOIN   public.tbl_organisationseinheit oe ON (oe.oe_kurzbz = sap_oe.oe_kurzbz)
	-- Filter out phases
	WHERE 		project_task_id IS NULL
	-- Filter out deleted projects or leave them, if they are still synched (synched ones should stay to be able to desynch them)
	AND         ((deleted = FALSE) OR (deleted = TRUE AND projects_timesheets_project_id IS NOT NULL))
	-- Filter out Intercompany (ICP) and Objektverwendung (OV) projects
	AND         (project_id NOT LIKE \'ICP%\' AND project_id NOT LIKE \'OV%\')
	ORDER BY 	projects_timesheets_project_id, project_id
';

$tableWidgetArray = array(
	'query' => $qry,
	'tableUniqueId' => 'SAPProjects',
	'requiredPermissions' => 'basis/projekt',
	'datasetRepresentation' => 'tabulator',
	'columnsAliases' => array(
		'Synced',
		'Status',
		'projects_timesheet_id',
		'Start',
		'Ende',
		'oe_kurzbz',
		'Projekt-OE',
		'SAP Projekt-Nr',
		'SAP Projektname',
		'projekt_ID',
		'FH Projekt-Kurzbz',
		'FH Projekttitel',
		'Deleted'
	),
	'datasetRepOptions' => '{
		index: "projects_timesheet_id",
		height: "350px",
		layout: "fitColumns",
		persistantLayout: false,
		selectable: 1,
		selectablePersistence: false,
		 initialHeaderFilter:[
            {field:"status", value:"3"} // set default status filter to "Released"
        ],
        initialSort:[
		    {column:"isSynced", dir:"desc"} // start with false
	    ],
		tableWidgetHeader: false,
		columnDefaults:{
			headerFilterPlaceholder: " ",
		}
	}',
	'datasetRepFieldsDefs' => '{
		isSynced: {headerFilter:"input", hozAlign:"center", editor:false, formatter:"tickCross", width: 80},
		status: {
			headerFilter:"list",
			headerFilterParams:{ values:getSAPProjectStatusbezeichnung()},
			formatter:"lookup",
            formatterParams: getSAPProjectStatusbezeichnung
		},
		projects_timesheet_id: {visible: false},
		start_date: {headerFilter: "input", mutator: mut_formatStringDate},
		end_date: {headerFilter: "input", mutator: mut_formatStringDate},
		oe_kurzbz: {visible: false},
		oe_bezeichnung: {headerFilter: "input", tooltip: true},
		project_id: {headerFilter: "input", tooltip: true},
		name: {headerFilter: "input", tooltip: true},
		projekt_id: {visible: false},
		projekt_kurzbz: {headerFilter: "input", tooltip: true},
		titel: {headerFilter: "input", tooltip: true},
		deleted: {visible: false}
	}'
);

echo $this->widgetlib->widget('TableWidget', $tableWidgetArray);
