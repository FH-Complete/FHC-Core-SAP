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
		$this->logInfo('Start data synchronization with SAP ByD: create');

		// Create a price list on SAP side
		$syncResult = $this->syncpricelistslib->create();

		if (isError($syncResult))
		{
			$this->logError(getCode($syncResult).': '.getError($syncResult));
		}
		else
		{
			// If non blocking errors are present...
			if (hasData($syncResult))
			{
				if (!isEmptyArray(getData($syncResult)))
				{
					// ...then log them all as warnings
					foreach (getData($syncResult) as $nonBlockingError)
					{
						$this->logWarning($nonBlockingError);
					}
				}
				// Else if it a single message log it as info
				elseif (!isEmptyString(getData($syncResult)))
				{
					$this->logInfo(getData($syncResult));
				}
			}
		}

		$this->logInfo('End data synchronization with SAP ByD: create');
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

