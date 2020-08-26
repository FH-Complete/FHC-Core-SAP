<?php

class SAPProjectsTimesheets_model extends DB_Model
{
	/**
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'sync.tbl_sap_projects_timesheets';
		$this->pk = 'projects_timesheet_id';
	}

	/**
	 * Renames the project id
	 */
	public function renameProjectId($oldProjectId, $newProjectId)
	{
		return $this->execQuery(
			'UPDATE sync.tbl_sap_projects_timesheets SET project_id = ?, updateamum = NOW() WHERE project_id = ?',
			array(
				$newProjectId,
				$oldProjectId
			)
		);
	}
}

