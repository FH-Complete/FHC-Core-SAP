<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Job Queue Worker to create or update services in SAP Business by Design
 */
class ManageServices extends JQW_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// Loads SAP common helper
                $this->load->helper('extensions/FHC-Core-SAP/hlp_sap_common');

		// Loads SyncServicesLib
		$this->load->library('extensions/FHC-Core-SAP/SyncServicesLib');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * This method is called to synchronize new servicess with SAP Business by Design
	 * Wrapper method for _manageServices
	 */
	public function create()
	{
		$this->_manageServices(SyncServicesLib::SAP_SERVICES_CREATE, 'create');
	}

	/**
	 * This method is called to synchronize updated services data with SAP Business by Design
	 * Wrapper method for _manageServices
	 */
	public function update()
	{
		$this->_manageServices(SyncServicesLib::SAP_SERVICES_UPDATE, 'update');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Performs data synchronization with SAP, depending on the first parameter can create or update services
	 */
	private function _manageServices($jobType, $operation)
	{
		$this->logInfo('Start data synchronization with SAP ByD: '.$operation);

		// Gets the latest jobs
		$lastJobs = $this->getLastJobs($jobType);
		if (isError($lastJobs))
		{
			$this->logError('An error occurred while '.$operation.'ing services in SAP', getError($lastJobs));
		}
		else
		{
			// Create/update users on SAP side
			if ($jobType == SyncServicesLib::SAP_SERVICES_CREATE)
			{
				$syncResult = $this->syncserviceslib->create(mergeUsersPersonIdArray(getData($lastJobs)));
			}
			else
			{
				$syncResult = $this->syncserviceslib->update(mergeUsersPersonIdArray(getData($lastJobs)));
			}

			if (isError($syncResult))
			{
				$this->logError('An error occurred while '.$operation.'ing services in SAP', getError($syncResult));
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
					array(JobsQueueLib::STATUS_DONE, date("Y-m-d H:i:s")) // Job properties new values
				);
				
				if (hasData($lastJobs)) $this->updateJobsQueue($jobType, getData($lastJobs));
			}
		}

		$this->logInfo('End data synchronization with SAP ByD: '.$operation);
	}
}

