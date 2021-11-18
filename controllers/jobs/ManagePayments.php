<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Job Queue Worker to create or update payments in SAP Business by Design
 */
class ManagePayments extends JQW_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// Loads SAP common helper
		$this->load->helper('extensions/FHC-Core-SAP/hlp_sap_common');

		// Loads SyncPaymentsLib
		$this->load->library('extensions/FHC-Core-SAP/SyncPaymentsLib');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 *
	 */
	public function getPaymentById($id)
	{
		var_dump($this->syncpaymentslib->getPaymentById($id));
	}

	/**
	 * Check if a SalesOrder is already payed
	 */
	public function checkIfPaid($salesOrderId, $studentId)
	{
		$result = $this->syncpaymentslib->isSalesOrderPaid($salesOrderId, $studentId);

		if (isSuccess($result))
		{
			if (hasData($result))
			{
				if (getData($result) === true)
				{
					var_dump("SalesOrder is paid");
				}
				else
				{
					var_dump("SalesOrder is NOT paid");
				}
			}
			else
			{
				var_dump("Sales order not found on SAP");
			}
		}
		else
			var_dump(getError($result));
	}

	/**
	 * Check all open Payments and set them as paid
	 *
	 */
	public function setPaid()
	{
		$this->logInfo('Start data synchronization with SAP ByD: setPaid');

		$this->syncpaymentslib->setPaid();

		$this->logInfo('End data synchronization with SAP ByD: setPaid');
	}

	/**
	 *
	 */
	public function createGutschrift()
	{
		$this->logInfo('Start data synchronization with SAP ByD: Gutschrift');

		// Gets the latest jobs
		$oldestJob = $this->getOldestJob(SyncPaymentsLib::SAP_PAYMENT_GUTSCHRIFT);
		if (isError($oldestJob))
		{
			$this->logError(getCode($oldestJob).': '.getError($oldestJob), SyncPaymentsLib::SAP_PAYMENT_GUTSCHRIFT);
		}
		elseif (hasData($oldestJob)) // if there are jobs to work
		{
			// Update jobs start time
			$this->updateJobs(
				getData($oldestJob), // Jobs to be updated
				array(JobsQueueLib::PROPERTY_START_TIME), // Job properties to be updated
				array(date("Y-m-d H:i:s")) // Job properties new values
			);
			$updateResult = $this->updateJobsQueue(SyncPaymentsLib::SAP_PAYMENT_GUTSCHRIFT, getData($oldestJob));

			// If there were an error then log it
			if (isError($updateResult))
			{
				$this->logError(getError($updateResult));
			}
			else // work the jobs
			{
				// Gets the oldest job in the queue to create credit memos
				$syncResult = $this->syncpaymentslib->createGutschrift(mergeUsersPersonIdArray(getData($oldestJob)));

				// Log the result
				if (isError($syncResult))
				{
					// Save all the errors
					$errors = getError($syncResult);

					// If it is NOT an array...
					if (isEmptyArray($errors))
					{
						// ...then convert it to an array
						$errors = array($errors);
					}
					// otherwise it is already an array

					// For each error found
					foreach ($errors as $error)
					{
						$this->logError(getCode($syncResult).': '.$error);
					}
				}
				else
				{
					$this->logInfo(getData($syncResult));
				}

				// Update jobs properties values
				$this->updateJobs(
					getData($oldestJob), // Jobs to be updated
					array(JobsQueueLib::PROPERTY_STATUS, JobsQueueLib::PROPERTY_END_TIME), // Job properties to be updated
					array(JobsQueueLib::STATUS_DONE, date("Y-m-d H:i:s")) // Job properties new values
				);
				$this->updateJobsQueue(SyncPaymentsLib::SAP_PAYMENT_GUTSCHRIFT, getData($oldestJob));
			}
		}

		$this->logInfo('End data synchronization with SAP ByD: Gutschrift');
	}

	/**
	 * This method is called to synchronize Payments with SAP Business by Design
	 */
	public function create()
	{
		$this->logInfo('Start data synchronization with SAP ByD: Payments');

		// Gets the latest jobs
		$oldestJob = $this->getOldestJob(SyncPaymentsLib::SAP_PAYMENT_CREATE);
		if (isError($oldestJob))
		{
			$this->logError('An error occurred while creating payments in SAP', getError($oldestJob));
		}
		elseif (hasData($oldestJob)) // if there are jobs to work
		{
			// Update jobs start time
			$this->updateJobs(
				getData($oldestJob), // Jobs to be updated
				array(JobsQueueLib::PROPERTY_START_TIME), // Job properties to be updated
				array(date("Y-m-d H:i:s")) // Job properties new values
			);
			$updateResult = $this->updateJobsQueue(SyncPaymentsLib::SAP_PAYMENT_CREATE, getData($oldestJob));

			// If there were an error then log it
			if (isError($updateResult))
			{
				$this->logError(getError($updateResult));
			}
			else // work the jobs
			{
				// Gets the oldest job to create payments
				$syncResult = $this->syncpaymentslib->create($this->_getPersonIdArray(getData($oldestJob)));

				// Logs the error
				if (isError($syncResult))
				{
					$this->logError('An error occurred while creating payments in SAP', getError($syncResult));
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
					$this->updateJobs(
						getData($oldestJob), // Jobs to be updated
						array(JobsQueueLib::PROPERTY_STATUS, JobsQueueLib::PROPERTY_END_TIME), // Job properties to be updated
						array(JobsQueueLib::STATUS_DONE, date("Y-m-d H:i:s")) // Job properties new values
					);
					$this->updateJobsQueue(SyncPaymentsLib::SAP_PAYMENT_CREATE, getData($oldestJob));
				}
			}
		}

		$this->logInfo('End data synchronization with SAP ByD: Payments');
	}

	/**
	 *
	 */
	private function _getPersonIdArray($jobs)
	{
		$mergedUsersArray = array();

		if (count($jobs) == 0) return $mergedUsersArray;

		foreach ($jobs as $job)
		{
			$decodedInput = json_decode($job->input);
			if ($decodedInput != null)
			{
				foreach ($decodedInput as $el)
				{
					$mergedUsersArray[] = $el->person_id;
				}
			}
		}
		return $mergedUsersArray;
	}
}

