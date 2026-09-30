<?php

class SAPStudentsGutschriften_model extends DB_Model
{
	/**
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'sync.tbl_sap_students_gutschriften';
		$this->pk = array('id');
		$this->hasSequence = true;
	}
}

