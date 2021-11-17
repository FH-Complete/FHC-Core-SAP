<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Jobs and queue workers to synchronize projects between FHC and SAP Business by Design
 */
class ManageProjects extends JQW_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// Loads SAP common helper
		$this->load->helper('extensions/FHC-Core-SAP/hlp_sap_common');

		// Loads SyncProjectsLib
		$this->load->library('extensions/FHC-Core-SAP/SyncProjectsLib');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Method used mostly for testing or debugging, it performs a call to SAP to get all projects
	 */
	public function getProjects()
	{
		var_dump($this->syncprojectslib->getProjects());
	}

	/**
	 * Method used mostly for testing or debugging, it performs a call to SAP to get all projects and their tasks
	 */
	public function getProjectsAndTasks()
	{
		var_dump($this->syncprojectslib->getProjectsAndTasks());
	}

	/**
	 * Method used mostly for testing or debugging, it performs a call to SAP to get a project with the given ID
	 */
	public function getProjectById($id)
	{
		var_dump($this->syncprojectslib->getProjectById($id));
	}

	/**
	 * Method used mostly for testing or debugging, it performs a call to SAP to get all tasks for a project with the given ID
	 */
	public function getProjectTasks($id)
	{
		var_dump($this->syncprojectslib->getProjectTasks($id));
	}

	/**
	 * Method used mostly for testing or debugging, it performs a call to SAP to get all tasks for a project with the given ID
	 */
	public function getProjectTaskService($id)
	{
		var_dump($this->syncprojectslib->getProjectTaskService($id));
	}

	/**
	 * Updates and creates projects on SAP side
	 */
	public function syncAll($studySemester = null)
	{
		$this->logInfo('Start projects synchronization with SAP ByD');

		// Synchronize projects!
		$syncResult = $this->syncprojectslib->sync(SyncProjectsLib::ALL, $studySemester);

		// Log result
		if (isError($syncResult))
		{
			$this->logError(getCode($syncResult).': '.getError($syncResult));
		}
		else // otherwise
		{
			$this->logInfo(getData($syncResult));
		}

		$this->logInfo('End projects synchronization with SAP ByD');
	}

	/**
	 * Updates and creates projects on SAP side only admin
	 */
	public function syncAdminFhtw($studySemester = null)
	{
		$this->logInfo('Start projects admin FHTW synchronization with SAP ByD');

		// Synchronize projects!
		$syncResult = $this->syncprojectslib->sync(SyncProjectsLib::ADMIN_FHTW, $studySemester);

		// Log result
		if (isError($syncResult))
		{
			$this->logError(getCode($syncResult).': '.getError($syncResult));
		}
		else // otherwise
		{
			$this->logInfo(getData($syncResult));
		}

		$this->logInfo('End projects admin FHTW synchronization with SAP ByD');
	}

	/**
	 * Updates and creates projects on SAP side only admin
	 */
	public function syncAdminGmbh($studySemester = null)
	{
		$this->logInfo('Start projects admin GMBH synchronization with SAP ByD');

		// Synchronize projects!
		$syncResult = $this->syncprojectslib->sync(SyncProjectsLib::ADMIN_GMBH, $studySemester);

		// Log result
		if (isError($syncResult))
		{
			$this->logError(getCode($syncResult).': '.getError($syncResult));
		}
		else // otherwise
		{
			$this->logInfo(getData($syncResult));
		}

		$this->logInfo('End projects admin GMBH synchronization with SAP ByD');
	}

	/**
	 * Updates and creates projects on SAP side only teachers
	 */
	public function syncLehre($studySemester = null)
	{
		$this->logInfo('Start projects lehre synchronization with SAP ByD');

		// Synchronize projects!
		$syncResult = $this->syncprojectslib->sync(SyncProjectsLib::LEHRE, $studySemester);

		// Log result
		if (isError($syncResult))
		{
			$this->logError(getCode($syncResult).': '.getError($syncResult));
		}
		else // otherwise
		{
			$this->logInfo(getData($syncResult));
		}

		$this->logInfo('End projects lehre synchronization with SAP ByD');
	}

	/**
	 * Updates and creates projects on SAP side only courses
	 */
	public function syncLehrgaenge($studySemester = null)
	{
		$this->logInfo('Start projects lehrgaenge synchronization with SAP ByD');

		// Synchronize projects!
		$syncResult = $this->syncprojectslib->sync(SyncProjectsLib::LEHRGAENGE, $studySemester);

		// Log result
		if (isError($syncResult))
		{
			$this->logError(getCode($syncResult).': '.getError($syncResult));
		}
		else // otherwise
		{
			$this->logInfo(getData($syncResult));
		}

		$this->logInfo('End projects lehrgaenge synchronization with SAP ByD');
	}

	/**
	 * Updates and creates projects on SAP side only customs
	 */
	public function syncCustoms($studySemester = null)
	{
		$this->logInfo('Start projects customs synchronization with SAP ByD');

		// Synchronize projects!
		$syncResult = $this->syncprojectslib->sync(SyncProjectsLib::CUSTOM, $studySemester);

		// Log result
		if (isError($syncResult))
		{
			$this->logError(getCode($syncResult).': '.getError($syncResult));
		}
		else // otherwise
		{
			$this->logInfo(getData($syncResult));
		}

		$this->logInfo('End projects customs synchronization with SAP ByD');
	}

	/**
	 * Updates and creates projects on SAP side only customs
	 */
	public function syncGmbhCustoms($studySemester = null)
	{
		$this->logInfo('Start projects custom custom synchronization with SAP ByD');

		// Synchronize projects!
		$syncResult = $this->syncprojectslib->sync(SyncProjectsLib::GMBH_CUSTOM, $studySemester);

		// Log result
		if (isError($syncResult))
		{
			$this->logError(getCode($syncResult).': '.getError($syncResult));
		}
		else // otherwise
		{
			$this->logInfo(getData($syncResult));
		}

		$this->logInfo('End projects gmbh custom synchronization with SAP ByD');
	}

	/**
	 *
	 */
	public function updateFUEProjects()
	{
		$this->logInfo('Start projects dates synchronization with SAP ByD');

		// Import SAP projects ids
		$importResult = $this->syncprojectslib->updateFUEProjects();

		// If an error occurred then log it
		if (isError($importResult))
		{
			$this->logError(getCode($importResult).': '.getError($importResult));
		}
		else // otherwise
		{
			$this->logInfo(getData($importResult));
		}

		$this->logInfo('End projects dates synchronization with SAP ByD');
	}

	/**
	 *
	 */
	public function updateProjectDates($projectName, $startDate, $endDate)
	{
		$this->logInfo('Start project '.$projectName.' dates update on SAP ByD');

		// Import SAP projects ids
		$updateResult = $this->syncprojectslib->updateProjectDates($projectName, $startDate, $endDate);

		// If an error occurred then log it
		if (isError($updateResult))
		{
			$this->logError(getCode($updateResult).': '.getError($updateResult));
		}
		else // otherwise
		{
			$this->logInfo(getData($updateResult));
		}

		$this->logInfo('End project '.$projectName.' dates update on SAP ByD');
	}

	/**
	 * Activate purchase orders in SAPByD
	 */
	public function activatePurchaseOrders()
	{
		$this->logInfo('Start purchase orders activation on SAP ByD');

		// Gets the SAP_PO_ACTIV_NUMBER oldest jobs
		$oldestJobs = $this->getOldestJobs(SyncProjectsLib::SAP_PURCHASE_ORDERS_ACTIVATION, SyncProjectsLib::SAP_PO_ACTIV_NUMBER);
		if (isError($oldestJobs))
		{
			$this->logError(getCode($oldestJobs).': '.getError($oldestJobs), SyncProjectsLib::SAP_PURCHASE_ORDERS_ACTIVATION);
		}
		else
		{
			// Get SAP_PO_ACTIV_NUMBER jobs from the queue
			$syncResult = $this->syncprojectslib->activatePurchaseOrders(
				mergePurchaseOrdersIdArray( // and merge the purchase order ids in one single array
					getData($oldestJobs)
				)
			);

			// If an error occurred then log it
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
				getData($oldestJobs), // Jobs to be updated
				array(JobsQueueLib::PROPERTY_STATUS, JobsQueueLib::PROPERTY_END_TIME), // Job properties to be updated
				array(JobsQueueLib::STATUS_DONE, date('Y-m-d H:i:s')) // Job properties new values
			);
			
			if (hasData($oldestJobs)) $this->updateJobsQueue(
				SyncProjectsLib::SAP_PURCHASE_ORDERS_ACTIVATION,
				getData($oldestJobs)
			);
		}

		$this->logInfo('End purchase orders activation on SAP ByD');
	}
}

