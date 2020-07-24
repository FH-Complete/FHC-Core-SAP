<?php
class SAPMitarbeiter_model extends DB_Model
{

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'sync.tbl_sap_mitarbeiter';
		$this->pk = 'mitarbeiter_uid';
		$this->hasSequence = false;
	}

}
