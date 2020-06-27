<?php

class SAPProjectsCourses_model extends DB_Model
{
	/**
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'sync.tbl_sap_projects_courses';
		$this->pk = array('project_id', 'project_object_id', 'studiensemester_kurzbz', 'studiengang_kz');
	}
}

