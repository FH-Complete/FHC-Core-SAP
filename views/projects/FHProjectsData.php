<?php
$qry = '
	SELECT
				CASE
				WHEN (projects_timesheets_project IS NOT NULL) THEN \'true\'
				ELSE \'false\'
				END AS "isSynced",
				projekt_id, 
				projekt_kurzbz,
				titel
	FROM 		fue.tbl_projekt
	LEFT JOIN 	sync.tbl_projects_timesheets_project synctbl USING (projekt_id)
	WHERE 		synctbl.projektphase_id IS NULL
	-- filter active projects only
	AND 		(ende IS NULL OR ende >= NOW())
	ORDER BY 	projects_timesheets_project, projekt_kurzbz
';

$tableWidgetArray = array(
	'query' => $qry,
	'tableUniqueId' => 'FUEProjects',
	'requiredPermissions' => 'basis/projekt',
	'datasetRepresentation' => 'tabulator',
	'columnsAliases' => array(
		'Synced',
		'ProjektTimesheet-ID',
		'Projekt-Kurzbz',
		'Projekt'
	),
	'datasetRepOptions' => '{
		index: "projekt_id",
		layout: "fitColumns",
		persistantLayout: false,
		headerFilterPlaceholder: " ",
		selectable: 1,
		selectablePersistence: false,
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
		isSynced: {headerFilter:"input", align:"center", editor:false, formatter:"tickCross", width: 100},
		projekt_id: {visible: false},
		projekt_kurzbz: {visible: false},
		titel: {headerFilter:"input"}
	}'
);

echo $this->widgetlib->widget('TableWidget', $tableWidgetArray);