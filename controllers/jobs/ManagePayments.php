<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Job Queue Worker to create or update users in SAP Business by Design
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

	public function get($id)
	{
		echo print_r($this->syncpaymentslib->getPaymentById($id), true);
	}

	/**
	 * Check if a SalesOrder is already payed
	 */
	public function checkIfPaid($salesOrderId, $studentId)
	{
		$result = $this->syncpaymentslib->isSalesOrderPaid($salesOrderId,$studentId);

		if(isSuccess($result) && hasData($result))
		{
			if(getData($result) === true)
			{
				echo "SalesOrder is paid";
			}
			else
			{
				echo "SalesOrder is NOT paid";
			}
		}
		else
			echo print_r($result, true);
	}

	/**
	 * Check all open Payments and set them as paid
	 *
	 */
	public function setPaid()
	{
		$this->syncpaymentslib->setPaid();
	}

	/**
	 *
	 */
	public function createGutschrift()
	{
		$this->logInfo('Start data synchronization with SAP ByD: Gutschrift');

		// Gets the latest jobs
		$lastJobs = $this->getLastJobs(SyncPaymentsLib::SAP_PAYMENT_GUTSCHRIFT);
		if (isError($lastJobs))
		{
			$this->logError(getCode($lastJobs).': '.getError($lastJobs), SyncPaymentsLib::SAP_PAYMENT_GUTSCHRIFT);
		}
		else
		{
			// Gets all the jobs in the queue to create credit memo
			$syncResult = $this->syncpaymentslib->createGutschrift(mergeUsersPersonIdArray(getData($lastJobs)));

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
				getData($lastJobs), // Jobs to be updated
				array(JobsQueueLib::PROPERTY_STATUS, JobsQueueLib::PROPERTY_END_TIME), // Job properties to be updated
				array(JobsQueueLib::STATUS_DONE, date("Y-m-d H:i:s")) // Job properties new values
			);

			if (hasData($lastJobs)) $this->updateJobsQueue(SyncPaymentsLib::SAP_PAYMENT_GUTSCHRIFT, getData($lastJobs));
		}

		$this->logInfo('End data synchronization with SAP ByD: Gutschrift');
	}

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

	/**
	 * This method is called to synchronize Payments with SAP Business by Design
	 */
	public function create()
	{
		$jobType = 'SAPPaymentCreate';
		$this->logInfo('Start data synchronization with SAP ByD: Payments');

		// Gets the latest jobs
		$lastJobs = $this->getLastJobs($jobType);
		if (isError($lastJobs))
		{
			$this->logError('An error occurred while creating payments in SAP', getError($lastJobs));
		}
		else
		{
			$person_arr = $this->_getPersonIdArray(getData($lastJobs));
			$syncResult = $this->syncpaymentslib->create($person_arr);

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
					getData($lastJobs), // Jobs to be updated
					array(JobsQueueLib::PROPERTY_STATUS, JobsQueueLib::PROPERTY_END_TIME), // Job properties to be updated
					array(JobsQueueLib::STATUS_DONE, date("Y-m-d H:i:s")) // Job properties new values
				);

				if (hasData($lastJobs)) $this->updateJobsQueue($jobType, getData($lastJobs));
			}
		}

		$this->logInfo('End data synchronization with SAP ByD: Payments');
	}
}

