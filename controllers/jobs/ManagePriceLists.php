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
	//

	/**
	 * Create a price list for the current month
	 */
	public function create()
	{
		$jobType = SyncPriceListsLib::SAP_PRICE_LIST_CREATE;

		$this->logInfo('Start data synchronization with SAP ByD: create');

		// Gets the latest jobs
		$lastJobs = $this->getLastJobs($jobType);
		if (isError($lastJobs))
		{
			$this->logError(getCode($lastJobs).': '.getError($lastJobs), $jobType);
		}
		elseif (hasData($lastJobs))
		{
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

				// Update jobs properties values
				updateJobs(
					getData($lastJobs), // Jobs to be updated
					array(JobsQueueLib::PROPERTY_STATUS, JobsQueueLib::PROPERTY_END_TIME), // Job properties to be updated
					array(JobsQueueLib::STATUS_DONE, date('Y-m-d H:i:s')) // Job properties new values
				);
				
				if (hasData($lastJobs)) $this->updateJobsQueue($jobType, getData($lastJobs));
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

