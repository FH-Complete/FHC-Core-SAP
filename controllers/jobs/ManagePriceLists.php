<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Job to create or get Price Lists in SAP Business by Design
 */
class ManagePriceLists extends JOB_Controller
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
	//

	/**
	 * Create a price list for the current month
	 */
	public function create()
	{
		$this->logInfo('Start price list create');

		// Create a price list on SAP side
		$syncResult = $this->syncpricelistslib->create();

		// Log result
		if (isError($syncResult))
		{
			$this->logError(getCode($syncResult).': '.getError($syncResult));
		}
		else
		{
			$this->logInfo(getData($syncResult));
		}

		$this->logInfo('End price list create');
	}

	/**
	 * Adds all the active services (employees) to the current price list
	 */
	public function addServicesToPriceList()
	{
		$this->logInfo('Start price list linking');

		// Add the user/service to the current price list
		$syncResult = $this->syncpricelistslib->addServicesToPriceList();

		// Log result
		if (isError($syncResult))
		{
			$this->logError(getCode($syncResult).': '.getError($syncResult));
		}
		else
		{
			$this->logInfo(getData($syncResult));
		}

		$this->logInfo('End price list linking');
	}

	/**
	 * Method used mostly for testing or debugging, it performs a call to SAP to find a price list with the given customer id
	 * and then returns the raw SOAP result
	 */
	public function getPriceListByCustomerId($id)
	{
		var_dump($this->syncpricelistslib->getPriceListByCustomerId(urldecode($id)));
	}
}

