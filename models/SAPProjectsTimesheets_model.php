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
}

