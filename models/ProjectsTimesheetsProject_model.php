<?php

class ProjectsTimesheetsProject_model extends DB_Model
{
	/**
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'sync.tbl_projects_timesheets_project';
		$this->pk = 'projects_timesheets_project_id';
	}

	/**
	 * Get SAP phases of a given SAP project and if they are synchronized with FUE phases.
	 * @param string $project_id
	 * @return mixed
	 */
	public function getSAPPhases_withSyncStatus($project_id)
	{
		$qry = '
			WITH
				sap_projectphases AS
				(
					SELECT  *
					FROM    sync.tbl_sap_projects_timesheets
					WHERE   project_id = ?
	                AND     project_task_id IS NOT NULL
	                AND     deleted = FALSE
				)

			SELECT
	            CASE
					WHEN projects_timesheets_project_id IS NOT NULL THEN \'true\'
					ELSE \'false\'
		        END AS "isSynced",
		        status,
	            projects_timesheets_project_id,
	            projects_timesheet_id,
	            project_id,
	            start_date::date,
	            end_date::date,
	            project_task_id,
	            name,
	            tbl_projektphase.projektphase_id,
				tbl_projektphase.bezeichnung,
				time_recording
			FROM        sap_projectphases
	        LEFT JOIN   sync.tbl_projects_timesheets_project USING (projects_timesheet_id)
            LEFT JOIN fue.tbl_projektphase ON (tbl_projektphase.projektphase_id = tbl_projects_timesheets_project.projektphase_id)
	        ORDER BY    projects_timesheets_project_id, project_task_id;
		';

		return $this->execQuery($qry, array($project_id));
	}

	/**
	 * 	Get FUE phases of a given FUE project and if they are synchronized with SAP phases.
	 * @param string $projekt_kurzbz
	 * @return mixed
	 */
	public function getFUEPhases_withSyncStatus($projekt_kurzbz)
	{
		$qry = '
			WITH
				fue_projectphases AS
				(
					SELECT  *
					FROM    fue.tbl_projekt
					JOIN    fue.tbl_projektphase USING (projekt_kurzbz)
					WHERE   projekt_kurzbz = ?
				)

			SELECT
	            CASE
					WHEN projects_timesheets_project_id IS NOT NULL THEN \'true\'
					ELSE \'false\'
		        END AS "isSynced",
	            projects_timesheets_project_id,
	            fue_projectphases.projekt_id,
	            projekt_kurzbz,
	            projektphase_id,
	            bezeichnung
			FROM        fue_projectphases
            LEFT JOIN   sync.tbl_projects_timesheets_project USING (projektphase_id)
            ORDER BY    projects_timesheets_project_id, bezeichnung;
		';

		return $this->execQuery($qry, array($projekt_kurzbz));
	}

	/**
	 * Insert SAP and FH project into the sync-table.
	 * @param int $projects_timesheet_id
	 * @param int $projekt_id
	 * @param int $projektphase_id
	 * @return mixed
	 */
	public function syncProjectphases($projects_timesheet_id, $projekt_id, $projektphase_id)
	{
		return $this->insert(array(
				'projects_timesheet_id' => $projects_timesheet_id,
				'projekt_id' => $projekt_id,
				'projektphase_id' => $projektphase_id
			)
		);
	}

	/**
	 * Check, if SAP project is synchronized.
	 * @param int $projects_timesheet_id of the SAP project
	 * @return boolean
	 */
	public function isSynced_SAPProject($projects_timesheet_id)
	{
		if (!is_numeric($projects_timesheet_id))
		{
			return error('projects_timesheet_id muss eine Nummer sein.');
		}

		$result = $this->loadWhere(array(
			'projects_timesheet_id' => $projects_timesheet_id,
			'projektphase_id' => NULL
		));

		return hasData($result);
	}

	/**
	 * Check, if SAP projectphase is synchronized.
	 * @param int $projects_timesheet_id of the SAP projectphase
	 * @return boolean
	 */
	public function isSynced_SAPProjectphase($projects_timesheet_id)
	{
		if (!is_numeric($projects_timesheet_id))
		{
			return error('projects_timesheet_id muss eine Nummer sein.');
		}

		$result = $this->loadWhere('
			projects_timesheet_id = '. $projects_timesheet_id. ' AND
			projektphase_id IS NOT NULL
		');

		return hasData($result);
	}

	/**
	 * Check, if FUE project is synchronized.
	 * @param int $projekt_id
	 * @return boolean
	 */
	public function isSynced_FUEProject($projekt_id)
	{
		if (!is_numeric($projekt_id))
		{
			return error('projekt_id muss eine Nummer sein.');
		}

		$result = $this->loadWhere(array(
			'projekt_id' => $projekt_id,
			'projektphase_id' => NULL
		));

		return hasData($result);

	}

	/**
	 * Check, if FUE projectphase is synchronized.
	 * @param int $projektphase_id
	 * @return boolean
	 */
	public function isSynced_FUEProjectphase($projektphase_id)
	{
		if (!is_numeric($projektphase_id))
		{
			return error('projektphase_id muss eine Nummer sein.');
		}

		$result = $this->loadWhere(array(
			'projektphase_id' => $projektphase_id
		));

		return hasData($result);
	}
}
