<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Job Queue Worker to create or update Price Lists in SAP Business by Design
 */
class ManagePriceLists extends JQW_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// Loads SAP common helper
                $this->load->helper('extensions/FHC-Core-SAP/hlp_sap_common');

		// Loads SyncPriceListsLib
		$this->load->library('extensions/FHC-Core-SAP/SyncPriceListsLib');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Method used mostly for testing or debugging, it performs a call to SAP to find a price list with the given customer id
	 * and then returns the raw SOAP result
	 */
	public function getPriceListByCustomerId($id)
	{
		var_dump($this->syncpricelistslib->getPriceListByCustomerId(urldecode($id)));
	}
}

