<?php

class SAPStudierende_model extends DB_Model
{
	/**
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'sync.tbl_sap_studierende';
		$this->pk = array('person_id', 'sap_id');
	}
}

