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

	/**
	 * Get project by project_id.
	 * @param $project_id
	 * @return mixed
	 */
	public function getProject($project_id)
	{
		return $this->loadWhere(array(
			'project_id' => $project_id,
			'project_task_id' => NULL
		));
	}

	/**
	 * Renames the project id
	 */
	public function setDeletedTrue()
	{
		return $this->execQuery(
			'UPDATE sync.tbl_sap_projects_timesheets SET deleted = TRUE'
		);
	}
	
	
	/**
	 * Get all phases from SAP project.
	 * @param $project_id
	 * @return mixed
	 */
	public function getAllPhasesFromProject($project_id)
	{
		return $this->loadWhere(
			'project_id = '. $this->db->escape($project_id). ' AND project_task_id IS NOT NULL'
		);
	}
}

