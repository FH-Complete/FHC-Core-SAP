<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * This library contains the logic used to perform data synchronization between FHC and SAP Business by Design
 */
class SyncEmployeesLib
{
	private $_ci; // Code igniter instance

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		// Loads the LogLib with the needed parameters to log correctly from this library
		$this->_ci->load->library(
			'LogLib',
			array(
				'classIndex' => 3,
				'functionIndex' => 3,
				'lineIndex' => 2,
				'dbLogType' => 'job', // required
				'dbExecuteUser' => 'Cronjob system',
				'requestId' => 'JOB',
				'requestDataFormatter' => function($data) {
					return json_encode($data);
				}
			)
		);

		// Loads model EmployeeModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/ODATA/Employee_model', 'EmployeeModel');

		// Loads model SAPMitarbeiterModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SAPMitarbeiter_model', 'SAPMitarbeiterModel');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 *
	 */
	public function importEmployeeIDs()
	{
		$dbModel = new DB_Model();
		
		// Get all employees form database
		$employeeResult = $dbModel->execReadOnlyQuery('
			SELECT m.mitarbeiter_uid AS uid
			  FROM public.tbl_mitarbeiter m
			 WHERE m.mitarbeiter_uid NOT IN (
				SELECT mitarbeiter_uid
				  FROM sync.tbl_sap_mitarbeiter
			)
			   AND m.personalnummer > 0
			ORDER BY m.mitarbeiter_uid
		');
		
		// If an error occurred then return it
		if (isError($employeeResult)) return $employeeResult;

		// If there are no employees to update then return a message
		if (!hasData($employeeResult)) return success('No employees to import');

		// Get all the employees from SAP
		$sapEmployeeResult = $this->_ci->EmployeeModel->getAllEmployees();

		// If an error occurred then return it
		if (isError($sapEmployeeResult)) return $sapEmployeeResult;

		// If there are no employees on SAP then return an error
		if (!hasData($sapEmployeeResult)) return error('Was not possible to retrieve employees from SAP');
		
		// Log some statistics
		$this->_ci->loglib->logInfoDB('Employees to import: '.count(getData($employeeResult)));
		$this->_ci->loglib->logInfoDB('Employees retrieved from SAP: '.count(getData($sapEmployeeResult)));

		$importedCounter = 0; // imported employees counter

		// For each employee found in database
		foreach (getData($employeeResult) as $employee)
		{
			// For each employee found in SAP
			foreach (getData($sapEmployeeResult) as $sapEmployee)
			{
				// If the employee match...
				if (strtolower($employee->uid) == strtolower($sapEmployee->C_BusinessUserId))
				{
					// ...write it into the sync table
					$employeeInsert = $this->_ci->SAPMitarbeiterModel->insert(
						array(
							// uid is the same from database therefore will not violate the foreign key
							'mitarbeiter_uid' => $employee->uid,
							// If the id on SAP is numeric then get the integer value of it, otherwise use the string value
							'sap_eeid' => is_numeric($sapEmployee->C_EeId) ? intval($sapEmployee->C_EeId) : $sapEmployee->C_EeId
						)
					);

					// If error occurred then return it
					if (isError($employeeInsert)) return $employeeInsert;

					$importedCounter++; // successfully imported!
				}
			}
		}

		// If here then everything was fine
		return success('Total employees imported: '.$importedCounter);
	}
}

