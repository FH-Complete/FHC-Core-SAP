<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 *
 */
class JQMScheduler extends JQW_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// Loads SAP common helper
		$this->load->helper('extensions/FHC-Core-SAP/hlp_sap_common');

		// Loads JQMSchedulerLib
		$this->load->library('extensions/FHC-Core-SAP/JQMSchedulerLib');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 *
	 */
	public function newUsers($studySemester = null)
	{
		$this->logInfo('Start job queue scheduler FHC-Core-SAP->newUsers');

		// Generates the input for the new job
		$jobInputResult = $this->jqmschedulerlib->newUsers($studySemester);

		// If an error occured then log it
		if (isError($jobInputResult))
		{
			$this->logError(getError($jobInputResult));
		}
		else
		{
			// If a job input were generated
			if (hasData($jobInputResult))
			{
				// Add the new job to the jobs queue
				$addNewJobResult = $this->addNewJobsToQueue(
					JQMSchedulerLib::JOB_TYPE_SAP_NEW_USERS, // job type
					$this->generateJobs( // gnerate the structure of the new job
						JobsQueueLib::STATUS_NEW,
						getData($jobInputResult)
					)
				);

				// If error occurred return it
				if (isError($addNewJobResult)) $this->logError(getError($addNewJobResult));
			}
			else // otherwise log info
			{
				$this->logInfo('There are no jobs to generate');
			}
		}

		$this->logInfo('End job queue scheduler FHC-Core-SAP->newUsers');
	}

	/**
	 *
	 */
	public function updateUsers()
	{
		$this->logInfo('Start job queue scheduler FHC-Core-SAP->updateUsers');

		// Generates the input for the new job
		$jobInputResult = $this->jqmschedulerlib->updateUsers();

		// If an error occured then log it
		if (isError($jobInputResult))
		{
			$this->logError(getError($jobInputResult));
		}
		else
		{
			// If a job input were generated
			if (hasData($jobInputResult))
			{
				// Split array in arrays every LENGTH
				$jobInputArrays = array_chunk(getData($jobInputResult), JQMSchedulerLib::MAX_JOB_ELEMENTS);

				// Loops on arrays
				foreach ($jobInputArrays as $jobInputArray)
				{
					// Add the new job to the jobs queue
					$addNewJobResult = $this->addNewJobsToQueue(
						JQMSchedulerLib::JOB_TYPE_SAP_UPDATE_USERS, // job type
						$this->generateJobs( // generate the structure of the new job
							JobsQueueLib::STATUS_NEW,
							json_encode($jobInputArray)
						)
					);

					// If error occurred return it
					if (isError($addNewJobResult)) $this->logError(getError($addNewJobResult));
				}
			}
			else // otherwise log info
			{
				$this->logInfo('There are no jobs to generate');
			}
		}

		$this->logInfo('End job queue scheduler FHC-Core-SAP->updateUsers');
	}

	/**
	 *
	 */
	public function newServices()
	{
		$this->logInfo('Start job queue scheduler FHC-Core-SAP->newServices');

		// Generates the input for the new job
		$jobInputResult = $this->jqmschedulerlib->newServices();

		// If an error occured then log it
		if (isError($jobInputResult))
		{
			$this->logError(getError($jobInputResult));
		}
		else
		{
			// If a job input were generated
			if (hasData($jobInputResult))
			{
				// Add the new job to the jobs queue
				$addNewJobResult = $this->addNewJobsToQueue(
					JQMSchedulerLib::JOB_TYPE_SAP_NEW_SERVICES, // job type
					$this->generateJobs( // gnerate the structure of the new job
						JobsQueueLib::STATUS_NEW,
						getData($jobInputResult)
					)
				);

				// If error occurred return it
				if (isError($addNewJobResult)) $this->logError(getError($addNewJobResult));
			}
			else // otherwise log info
			{
				$this->logInfo('There are no jobs to generate');
			}
		}

		$this->logInfo('End job queue scheduler FHC-Core-SAP->newServices');
	}

	/**
	 * Gets all the active employees and store them in the job worker queue in batches
	 */
	public function updateServices()
	{
		$this->logInfo('Start job queue scheduler FHC-Core-SAP->updateServices');

		// Generates the input for the new job
		$jobInputResult = $this->jqmschedulerlib->updateServices();

		// If an error occured then log it
		if (isError($jobInputResult))
		{
			$this->logError(getError($jobInputResult));
		}
		else
		{
			// If a job input were generated
			if (hasData($jobInputResult))
			{
				// Split array in arrays every LENGTH
				$jobInputArrays = array_chunk(getData($jobInputResult), JQMSchedulerLib::MAX_JOB_ELEMENTS);

				// Loops on arrays
				foreach ($jobInputArrays as $jobInputArray)
				{
					// Add the new job to the jobs queue
					$addNewJobResult = $this->addNewJobsToQueue(
						JQMSchedulerLib::JOB_TYPE_SAP_UPDATE_SERVICES, // job type
						$this->generateJobs( // gnerate the structure of the new job
							JobsQueueLib::STATUS_NEW,
							json_encode($jobInputArray)
						)
					);

					// If error occurred return it
					if (isError($addNewJobResult)) $this->logError(getError($addNewJobResult));
				}
			}
			else // otherwise log info
			{
				$this->logInfo('There are no jobs to generate');
			}
		}

		$this->logInfo('End job queue scheduler FHC-Core-SAP->updateServices');
	}

	/**
	 *
	 */
	public function newPayments()
	{
		$this->logInfo('Start job queue scheduler FHC-Core-SAP->newPayments');

		// Generates the input for the new job
		$jobInputResult = $this->jqmschedulerlib->newPayments();

		// If an error occured then log it
		if (isError($jobInputResult))
		{
			$this->logError(getError($jobInputResult));
		}
		else
		{
			// If a job input were generated
			if (hasData($jobInputResult))
			{
				// Split array in arrays every LENGTH
				$jobInputArrays = array_chunk(getData($jobInputResult), JQMSchedulerLib::MAX_JOB_ELEMENTS);

				// Loops on arrays
				foreach ($jobInputArrays as $jobInputArray)
				{
					// Add the new job to the jobs queue
					$addNewJobResult = $this->addNewJobsToQueue(
						JQMSchedulerLib::JOB_TYPE_SAP_NEW_PAYMENTS, // job type
						$this->generateJobs( // generate the structure of the new job
							JobsQueueLib::STATUS_NEW,
							json_encode($jobInputArray)
						)
					);

					// If error occurred return it
					if (isError($addNewJobResult)) $this->logError(getError($addNewJobResult));
				}
			}
			else // otherwise log info
			{
				$this->logInfo('There are no jobs to generate');
			}
		}

		$this->logInfo('End job queue scheduler FHC-Core-SAP->newPayments');
	}

	/**
	 *
	 */
	public function creditMemo()
	{
		$this->logInfo('Start job queue scheduler FHC-Core-SAP->creditMemo');

		// Generates the input for the new job
		$jobInputResult = $this->jqmschedulerlib->creditMemo();

		// If an error occured then log it
		if (isError($jobInputResult))
		{
			$this->logError(getError($jobInputResult));
		}
		else
		{
			// If a job input were generated
			if (hasData($jobInputResult))
			{
				// Split array in arrays every LENGTH
				$jobInputArrays = array_chunk(getData($jobInputResult), JQMSchedulerLib::MAX_JOB_ELEMENTS);

				// Loops on arrays
				foreach ($jobInputArrays as $jobInputArray)
				{
					// Loops on arrays
					foreach ($jobInputArrays as $jobInputArray)
					{
						// Add the new job to the jobs queue
						$addNewJobResult = $this->addNewJobsToQueue(
							JQMSchedulerLib::JOB_TYPE_SAP_CREDIT_MEMO, // job type
							$this->generateJobs( // generate the structure of the new job
								JobsQueueLib::STATUS_NEW,
								json_encode($jobInputArray)
							)
						);

						// If error occurred return it
						if (isError($addNewJobResult)) $this->logError(getError($addNewJobResult));
					}
				}
			}
			else // otherwise log info
			{
				$this->logInfo('There are no jobs to generate');
			}
		}

		$this->logInfo('End job queue scheduler FHC-Core-SAP->creditMemo');
	}

	/**
	 *
	 */
	public function newEmployees()
	{
		$this->logInfo('Start job queue scheduler FHC-Core-SAP->newEmployees');

		// Generates the input for the new job
		$jobInputResult = $this->jqmschedulerlib->newEmployees();

		// If an error occured then log it
		if (isError($jobInputResult))
		{
			$this->logError(getError($jobInputResult));
		}
		else
		{
			// If a job input were generated
			if (hasData($jobInputResult))
			{
				// Add the new job to the jobs queue
				$addNewJobResult = $this->addNewJobsToQueue(
					JQMSchedulerLib::JOB_TYPE_SAP_NEW_EMPLOYEES, // job type
					$this->generateJobs( // gnerate the structure of the new job
						JobsQueueLib::STATUS_NEW,
						getData($jobInputResult)
					)
				);

				// If error occurred return it
				if (isError($addNewJobResult)) $this->logError(getError($addNewJobResult));
			}
			else // otherwise log info
			{
				$this->logInfo('There are no jobs to generate');
			}
		}

		$this->logInfo('End job queue scheduler FHC-Core-SAP->newEmployees');
	}

	public function updateEmployee()
	{
		$this->logInfo('Start job queue scheduler FHC-Core-SAP->updateEmployees');

		// Generates the input for the new job
		$jobInputResult = $this->jqmschedulerlib->updateEmployees();

		// If an error occured then log it
		if (isError($jobInputResult))
		{
			$this->logError(getError($jobInputResult));
		}
		else
		{
			// If a job input were generated
			if (hasData($jobInputResult))
			{
				// Split array in arrays every LENGTH
				$jobInputArrays = array_chunk(getData($jobInputResult), JQMSchedulerLib::UPDATE_LENGTH);

				// Loops on arrays
				foreach ($jobInputArrays as $jobInputArray)
				{
					// Add the new job to the jobs queue
					$addNewJobResult = $this->addNewJobsToQueue(
						JQMSchedulerLib::JOB_TYPE_SAP_UPDATE_EMPLOYEES, // job type
						$this->generateJobs( // generate the structure of the new job
							JobsQueueLib::STATUS_NEW,
							json_encode($jobInputArray)
						)
					);

					// If error occurred return it
					if (isError($addNewJobResult)) $this->logError(getError($addNewJobResult));
				}
			}
			else // otherwise log info
			{
				$this->logInfo('There are no jobs to generate');
			}
		}

		$this->logInfo('End job queue scheduler FHC-Core-SAP->updateEmployees');
	}

	public function updateEmployeeWorkAgreement()
	{
		$this->logInfo('Start job queue scheduler FHC-Core-SAP->updateEmployeesWorkAgreement');

		// Generates the input for the new job
		$jobInputResult = $this->jqmschedulerlib->updateEmployeesWorkAgreement();


		// If an error occured then log it
		if (isError($jobInputResult))
		{
			$this->logError(getError($jobInputResult));
		}
		else
		{
			// If a job input were generated
			if (hasData($jobInputResult))
			{
				// Split array in arrays every LENGTH
				$jobInputArrays = array_chunk(getData($jobInputResult), JQMSchedulerLib::UPDATE_LENGTH);

				// Loops on arrays
				foreach ($jobInputArrays as $jobInputArray)
				{
					// Add the new job to the jobs queue
					$addNewJobResult = $this->addNewJobsToQueue(
						JQMSchedulerLib::JOB_TYPE_SAP_UPDATE_EMPLOYEES_WORKAGREEMENT, // job type
						$this->generateJobs( // generate the structure of the new job
							JobsQueueLib::STATUS_NEW,
							json_encode($jobInputArray)
						)
					);

					// If error occurred return it
					if (isError($addNewJobResult)) $this->logError(getError($addNewJobResult));
				}
			}
			else // otherwise log info
			{
				$this->logInfo('There are no jobs to generate');
			}
		}

		$this->logInfo('End job queue scheduler FHC-Core-SAP->updateEmployeesWorkAgreement');
	}
	
	/**
	 * Should run only once!
	 */
	public function setEmployeeOnService()
	{
		$this->logInfo('Start job queue scheduler FHC-Core-SAP->setEmployeeOnService');
		
		// Generates the input for the new job
		$jobInputResult = $this->jqmschedulerlib->setEmployeeOnService();
		
		// If an error occured then log it
		if (isError($jobInputResult))
		{
			$this->logError(getError($jobInputResult));
		}
		else
		{
			// If a job input were generated
			if (hasData($jobInputResult))
			{
				// Split array in arrays every LENGTH
				$jobInputArrays = array_chunk(getData($jobInputResult), 50);
				
				// Loops on arrays
				foreach ($jobInputArrays as $jobInputArray)
				{
					// Add the new job to the jobs queue
					$addNewJobResult = $this->addNewJobsToQueue(
						JQMSchedulerLib::JOB_TYPE_SAP_UPDATE_EMPLOYEE_SERVICE, // job type
						$this->generateJobs( // gnerate the structure of the new job
							JobsQueueLib::STATUS_DONE,
							json_encode($jobInputArray)
						)
					);
					
					// If error occurred return it
					if (isError($addNewJobResult)) $this->logError(getError($addNewJobResult));
				}
			}
			else // otherwise log info
			{
				$this->logInfo('There are no jobs to generate');
			}
		}
		
		$this->logInfo('End job queue scheduler FHC-Core-SAP->setEmployeeOnService');
	}
	
	/**
	 * Should run only once! PV21 migration
	 * Compare last DV between FH and SAP
	 */
	public function checkEmployeesDVs()
	{
		$this->logInfo('Start job queue scheduler FHC-Core-SAP->checkEmployeesDVs');
		
		// Generates the input for the new job
		$jobInputResult = $this->jqmschedulerlib->checkEmployeesDVs();
		
		// If an error occured then log it
		if (isError($jobInputResult))
		{
			$this->logError(getError($jobInputResult));
		}
		else
		{
			// If a job input were generated
			if (hasData($jobInputResult))
			{
				// Split array in arrays every LENGTH
				$jobInputArrays = array_chunk(getData($jobInputResult), 100);
				
				// Loops on arrays
				foreach ($jobInputArrays as $jobInputArray)
				{
					// Add the new job to the jobs queue
					$addNewJobResult = $this->addNewJobsToQueue(
						JQMSchedulerLib::JOB_TYPE_SAP_CHECK_EMPLOYEE_DV, // job type
						$this->generateJobs( // gnerate the structure of the new job
							JobsQueueLib::STATUS_DONE,
							json_encode($jobInputArray)
						)
					);
					
					// If error occurred return it
					if (isError($addNewJobResult)) $this->logError(getError($addNewJobResult));
				}
			}
			else // otherwise log info
			{
				$this->logInfo('There are no jobs to generate');
			}
		}
		
		$this->logInfo('End job queue scheduler FHC-Core-SAP->setEmployeeOnService');
	}
}
