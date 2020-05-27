<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Job Queue Worker to create or update List Prices in SAP Business by Design
 */
class ManageListPrices extends JQW_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// Loads SAP common helper
                $this->load->helper('extensions/FHC-Core-SAP/hlp_sap_common');

		// Loads SyncListPricesLib
		$this->load->library('extensions/FHC-Core-SAP/SyncListPricesLib');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Method used mostly for testing or debugging, it performs a call to SAP to find a list price with the given supplier id
	 * and then returns the raw SOAP result
	 */
	public function getListPriceById($id)
	{
		var_dump($this->synclistpriceslib->getListPriceById(urldecode($id)));
	}
}

