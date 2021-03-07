<?php

class SAPBanks_model extends DB_Model
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'sync.tbl_sap_banks';
		$this->pk = array('sap_bank_id', 'sap_bank_swift');
		$this->hasSequence = false;
	}

	/**
	 *
	 */
	public function deleteAll()
	{
		return $this->execQuery('DELETE FROM '.$this->dbTable);
	}
}

