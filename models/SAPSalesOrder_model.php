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

	public function getOpenPayments()
	{
		$qry = "SELECT
					buchungsnr, sap_sales_order_id, sap_user_id
				FROM
					sync.tbl_sap_salesorder
					JOIN public.tbl_konto USING(buchungsnr)
					JOIN sync.tbl_sap_students USING(person_id)
				WHERE
					tbl_konto.betrag < COALESCE(
						(SELECT sum(betrag)*(-1)
						FROM public.tbl_konto
						WHERE buchungsnr_verweis=tbl_sap_salesorder.buchungsnr
					),0)
				ORDER BY sap_user_id desc, sap_sales_order_id desc
			";

		$params = array();
		return $this->execQuery($qry, $params);
	}
}
