<?php
$qry = '
	SELECT
				CASE
				WHEN (projects_timesheets_project_id IS NOT NULL) THEN \'true\'
				ELSE \'false\'
				END AS "isSynced",
				projekt_id,
				projekt_kurzbz,
				titel
	FROM 		fue.tbl_projekt
	LEFT JOIN 	sync.tbl_projects_timesheets_project synctbl USING (projekt_id)
	WHERE 		synctbl.projektphase_id IS NULL
	-- filter active projects only
	AND 		( ende IS NULL OR ende >= ( NOW() - INTERVAL \'1 YEAR\' ) )
	ORDER BY 	projects_timesheets_project_id, projekt_kurzbz
';

$tableWidgetArray = array(
	'query' => $qry,
	'tableUniqueId' => 'FUEProjects',
	'requiredPermissions' => 'basis/projekt',
	'datasetRepresentation' => 'tabulator',
	'columnsAliases' => array(
		'Synced',
		'ProjektTimesheet-ID',
		'FH Projekt-ID',
		'FH Projekt'
	),
	'datasetRepOptions' => '{
		index: "projekt_id",
		height: "350px",
		layout: "fitColumns",
		persistantLayout: false,
		headerFilterPlaceholder: " ",
		selectable: 1,
		selectablePersistence: false,
		initialHeaderFilter:[
            {field:"isSynced", value:"false"} // set default isSynced filter to "false"
        ],
		rowClick: function(e, row){
            func_rowClick_onFHProject(e, row);
        },
		tableWidgetHeader: false,
		rowSelected: function(row){
			rowSelected_onFUEProject(row);
		},
	    rowUpdated: function(row){
            resortTable(row);
        },
        rowAdded:function(row){
	        resortTable(row);
	    },
	    renderStarted: function(){
	        renderStarted_onFUEProject(this);
	    }
	}',
	'datasetRepFieldsDefs' => '{
		isSynced: {headerFilter:"input", align:"center", editor:false, formatter:"tickCross", width: 80},
		projekt_id: {visible: false},
		projekt_id: {visible: false},
		projekt_kurzbz: {headerFilter:"input", tooltip: true},
		titel: {headerFilter:"input", tooltip: true}
	}'
);

echo $this->widgetlib->widget('TableWidget', $tableWidgetArray);
