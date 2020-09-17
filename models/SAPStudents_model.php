<?php

class SAPStudents_model extends DB_Model
{
	/**
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'sync.tbl_sap_students';
		$this->pk = array('person_id', 'sap_user_id');
		$this->hasSequence = false;
	}
}

