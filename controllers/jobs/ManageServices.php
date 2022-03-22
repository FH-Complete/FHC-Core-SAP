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
		$this->logInfo('Start data synchronization with SAP ByD: create');

		// Gets the latest jobs
		$lastJobs = $this->getLastJobs(SyncServicesLib::SAP_SERVICES_CREATE);
		if (isError($lastJobs))
		{
			$this->logError(getCode($lastJobs).': '.getError($lastJobs), SyncServicesLib::SAP_SERVICES_CREATE);
		}
		elseif (hasData($lastJobs)) // if there jobs to work
		{
			// Update jobs properties s
			$this->updateJobs(
				getData($lastJobs), // Jobs to be updated
				array(JobsQueueLib::PROPERTY_START_TIME), // Job properties to be updated
				array(date('Y-m-d H:i:s')) // Job properties new values
			);
			$updateResult = $this->updateJobsQueue(SyncServicesLib::SAP_SERVICES_CREATE, getData($lastJobs));

			// If an error occurred then log it
			if (isError($updateResult))
			{
				$this->logError(getError($updateResult));
			}
			else // works the jobs
			{
				// Create/update users on SAP side
				$syncResult = $this->syncserviceslib->create(mergeUsersPersonIdArray(getData($lastJobs)));

				// Log result
				if (isError($syncResult))
				{
					$this->logError(getCode($syncResult).': '.getError($syncResult));
				}
				else
				{
					$this->logInfo(getData($syncResult));
				}

				// Update jobs properties values
				$this->updateJobs(
					getData($lastJobs), // Jobs to be updated
					array(JobsQueueLib::PROPERTY_STATUS, JobsQueueLib::PROPERTY_END_TIME), // Job properties to be updated
					array(JobsQueueLib::STATUS_DONE, date('Y-m-d H:i:s')) // Job properties new values
				);
				$this->updateJobsQueue(SyncServicesLib::SAP_SERVICES_CREATE, getData($lastJobs));
			}
		}

		$this->logInfo('End data synchronization with SAP ByD: create');
	}

	/**
	 * This method is called to synchronize updated services data with SAP Business by Design
	 */
	public function update()
	{
		$this->logInfo('Start data synchronization with SAP ByD: update');

		// Gets the oldest job
		$oldestJob = $this->getOldestJob(SyncServicesLib::SAP_SERVICES_UPDATE);
		if (isError($oldestJob))
		{
			$this->logError(getCode($oldestJob).': '.getError($oldestJob), SyncServicesLib::SAP_SERVICES_UPDATE);
		}
		elseif (hasData($oldestJob)) // if there jobs to work
		{
			// Update jobs properties
			$this->updateJobs(
				getData($oldestJob), // Jobs to be updated
				array(JobsQueueLib::PROPERTY_START_TIME), // Job properties to be updated
				array(date('Y-m-d H:i:s')) // Job properties new values
			);
			$updateResult = $this->updateJobsQueue(SyncServicesLib::SAP_SERVICES_UPDATE, getData($oldestJob));

			// If an error occurred then log it
			if (isError($updateResult))
			{
				$this->logError(getError($updateResult));
			}
			else // works the jobs
			{
				// Create/update users on SAP side
				$syncResult = $this->syncserviceslib->update(mergeUsersPersonIdArray(getData($oldestJob)));

				// Log result
				if (isError($syncResult))
				{
					$this->logError(getCode($syncResult).': '.getError($syncResult));
				}
				else
				{
					$this->logInfo(getData($syncResult));
				}

				// Update jobs properties values
				$this->updateJobs(
					getData($oldestJob), // Jobs to be updated
					array(JobsQueueLib::PROPERTY_STATUS, JobsQueueLib::PROPERTY_END_TIME), // Job properties to be updated
					array(JobsQueueLib::STATUS_DONE, date('Y-m-d H:i:s')) // Job properties new values
				);
				$this->updateJobsQueue(SyncServicesLib::SAP_SERVICES_UPDATE, getData($oldestJob));
			}
		}

		$this->logInfo('End data synchronization with SAP ByD: update');
	}

	/**
	 * Method used mostly for testing or debugging, it performs a call to SAP to find a service with the given description
	 * and then returns the raw SOAP result
	 */
	public function getServiceByDescription($description)
	{
		var_dump($this->syncserviceslib->getServiceByDescription(urldecode($description)));
	}

	/**
	 * Method used mostly for testing or debugging, it performs a call to SAP to find a service with the given id
	 * and then returns the raw SOAP result
	 */
	public function getServiceById($id)
	{
		var_dump($this->syncserviceslib->getServiceById(urldecode($id)));
	}
}

