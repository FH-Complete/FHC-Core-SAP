<?php

class SAPSalesOrder_model extends DB_Model
{
	/**
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->dbTable = 'sync.tbl_sap_salesorder';
		$this->pk = array('buchungsnr');
		$this->hasSequence = false;
	}
}
