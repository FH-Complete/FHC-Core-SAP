<?php

class SAPProjectsCostcenters_model extends DB_Model
{
	/**
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'sync.tbl_sap_projects_costcenters';
		$this->pk = array('project_id', 'project_object_id', 'project_task_id', 'project_task_object_id', 'studiensemester_kurzbz', 'oe_kurzbz_sap');
		$this->hasSequence = false;
	}
}

