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
		$this->pk = array('projects_timesheet_id', 'projekt_id', 'projektphase_id')
	}
}

