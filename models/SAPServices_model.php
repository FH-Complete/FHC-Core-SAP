<?php

class SAPServices_model extends DB_Model
{
	/**
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'sync.tbl_sap_services';
		$this->pk = array('person_id', 'sap_service_id');
	}
}

