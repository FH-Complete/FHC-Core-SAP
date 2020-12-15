<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Job to create or update projects in SAP Business by Design
 */
class ManageProjects extends JOB_Controller
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
	 *
	 */
	public function importProjectsDates()
	{
		$this->logInfo('Start projects dates synchronization with SAP ByD');

		// Import SAP projects ids
		$importResult = $this->syncprojectslib->importProjectsDates();

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
	public function mpo()
	{
		$mpo = $this->syncprojectslib->mpo();

		var_dump($mpo);
	}
}

