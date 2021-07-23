<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * This library contains the logic used to perform data synchronization between FHC and SAP Business by Design
 */
class SyncEmployeesLib
{
	// Jobs types used by this lib
	const SAP_EMPLOYEES_CREATE = 'SAPEmployeesCreate';
	const SAP_EMPLOYEES_UPDATE = 'SAPEmployeesUpdate';

	const CREATE_EMP_PREFIX = 'CE';

	// Genders
	const FHC_GENDER_MALE = 'm';
	const FHC_GENDER_FEMALE = 'w';
	const FHC_GENDER_NON_BINARY = 'x';
	const SAP_GENDER_UNKNOWN = 0;
	const SAP_GENDER_MALE = 1;
	const SAP_GENDER_FEMALE = 2;
	const SAP_GENDER_NON_BINARY = 3;

	const DEFAULT_LANGUAGE_ISO = 'DE'; // Default language ISO
	const ENGLISH_LANGUAGE = 'English'; // English language
	const ENGLISH_LANGUAGE_ISO = 'EN'; // English language ISO

	const SAP_TYPE_PERMANENT = 1;
	const SAP_TYPE_TEMPORARY = 2;

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
			),
			'LogLibSAP'
		);

		// Loads model EmployeeModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/ODATA/Employee_model', 'EmployeeModel');

		// Loads model SAPMitarbeiterModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SAPMitarbeiter_model', 'SAPMitarbeiterModel');

		// Loads model ManagePersonnelHiringInModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SOAP/ManagePersonnelHiringIn_model', 'ManagePersonnelHiringInModel');

		$this->_ci->load->model('extensions/FHC-Core-SAP/SOAP/QueryEmployeeIn_model', 'QueryEmployeeInModel');
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
		$this->_ci->LogLibSAP->logInfoDB('Employees to import: '.count(getData($employeeResult)));
		$this->_ci->LogLibSAP->logInfoDB('Employees retrieved from SAP: '.count(getData($sapEmployeeResult)));

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

	/**
	 * 
	 */
	public function create($emps)
	{
		// If the given array of person ids is empty stop here
		if (isEmptyArray($emps)) return success('No employees to be created');

		// Remove the already created users performing a diff between the given person ids and those present
		// in the sync table. If no errors and the diff array is not empty then continues, otherwise a message
		// is returned
		$diffUsers = $this->_removeCreatedUsers($emps);
		if (isError($diffUsers)) return $diffUsers;
		if (!hasData($diffUsers)) return success('No users to be created after diff');

		// Retrieves all users data
		$empsAllData = $this->_getAllUsersData($diffUsers);

		if (isError($empsAllData)) return $empsAllData;
		if (!hasData($empsAllData)) return error('No data available for the given users');

		// Loops through users data
		foreach (getData($empsAllData) as $empData)
		{
			// If an email address was not found for this employee...
			/*if (isEmptyString($empData->email))
			{
				$this->_ci->LogLibSAP->logWarningDB('Was not possible to find a valid email address for employee: '.$empData->person_id);
				continue; // ...and continue to the next one
			}

			// If not a valid ort or gemeinde is present
			// If ort is not present or it is an empty string
			if (!isset($empData->ort) || (isset($empData->ort) && isEmptyString($empData->ort)))
			{
				// If also the gemeinde is not present or it is an empty string
				if (!isset($empData->gemeinde) || (isset($empData->gemeinde) && isEmptyString($empData->gemeinde)))
				{
					$this->_ci->LogLibSAP->logWarningDB('Was not possible to find a valid ort or gemeinde for employee: '.$empData->person_id);
					continue; // ...and continue to the next one
				}
			}

			// Checks if the current employee is already present in SAP
			$empDataSAP = $this->_employeesExistsByEmailSAP($empData->email);

			// If an error occurred then return it
			if (isError($empDataSAP)) return $empDataSAP;*/

			$empDataSAP = null;
			// If the current employee is not present in SAP
			if (!hasData($empDataSAP))
			{
				$data = array(
					'BasicMessageHeader' => array(
						'ID' => generateUID(self::CREATE_EMP_PREFIX),
						'UUID' => generateUUID()
					),
					'PersonnelHiring' => array(
						'actionCode' => '01',
						'HireDate' => $empData->startDate,
						'LeavingDate' => $empData->endDate,
						'Employee' => array(
							'GivenName' => $empData->name,
							'FamilyName' => $empData->surname,
							'GenderCode' => $empData->gender,
							'BirthDate' => $empData->bday,
							'PrivateAddress' => array(
								'CountryCode' => $empData->country,
								'CityName' => $empData->city,
								'StreetPostalCode' => $empData->zip,
								'StreetName' => $empData->street
							)
						),
						'Employment' => array(
							'CountryCode' => 'AT'
						),
						'WorkAgreement' => array(
							'TypeCode' => $empData->typeCode,
							'AdministrativeCategoryCode' => 2,
							'AgreedWorkingHoursRate' => array(
								'DecimalValue' => $empData->decimalValue,
								'BaseMeasureUnitCode' => 'WEE'
							),
							'OrganisationalCentreID' => '100021',
							'JobID' => 'MAIST'
						)

					)
				);

				// Then create it!
				$manageCustomerResult = $this->_ci->ManagePersonnelHiringInModel->MaintainBundle($data);


				// If an error occurred then return it
				if (isError($manageCustomerResult)) return $manageCustomerResult;



				// SAP data
				$manageCustomer = getData($manageCustomerResult);

				// If data structure is ok...
				if (isset($manageCustomer->PersonnelHiring) && isset($manageCustomer->PersonnelHiring->UUID))
				{

					$sapEmployeeResult = $this->getEmployeeAfterCreation($empData->name, $empData->surname, date('Y-m-d'));

					if (isError($sapEmployeeResult)) return $sapEmployeeResult;

					$sapEmployee = getData($sapEmployeeResult);

					/*$this->updateEmployee($sapEmployee->BasicData->EmployeeID, $empData);
					// Store in database the couple person_id sap_user_id
					$insert = $this->_ci->SAPMitarbeiterModel->insert(
						array(
							'miterbeiter_id' => $empData->person_id,
							'sap_ee_id' => $sapEmployee->BasicData->EmployeeID
						)
					);

					// If database error occurred then return it
					if (isError($insert)) return $insert;*/
				}
				else // ...otherwise store a non blocking error and continue with the next employee
				{
					// If it is present a description from SAP then use it
					if (isset($manageCustomer->Log) && isset($manageCustomer->Log->Item)
						&& isset($manageCustomer->Log->Item))
					{
						if (!isEmptyArray($manageCustomer->Log->Item))
						{
							foreach ($manageCustomer->Log->Item as $item)
							{
								if (isset($item->Note)) $this->_ci->LogLibSAP->logWarningDB($item->Note.' for employee: '.$empData->person_id);
							}
						}
						elseif ($manageCustomer->Log->Item->Note)
						{
							$this->_ci->LogLibSAP->logWarningDB($manageCustomer->Log->Item->Note.' for employee: '.$empData->person_id);
						}
					}
					else
					{
						// Default non blocking error
						$this->_ci->LogLibSAP->logWarningDB('SAP did not return the InterlID for employee: '.$empData->person_id);
					}
					continue;
				}
			}
			else // Add the already existing employee to the sync table
			{
				$sapCustomer = getData($empDataSAP); // get SAP customer data

				// Store in database the couple person_id sap_user_id
				$insert = $this->_ci->SAPStudentsModel->insert(
					array(
						'uid_id' => $empData->uid_id,
						'sap_eeid' => $sapCustomer->EeID
					)
				);

				// If database error occurred then return it
				if (isError($insert)) return $insert;
			}
		}

		return success('Users data created successfully');
	}

	// --------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Remove already created users from the given array
	 * Wrapper method for _addOrRemoveUsers
	 */
	private function _removeCreatedUsers($emps)
	{
		return $this->_addOrRemoveUsers($emps, false);
	}

	/**
	 * Remove still not created users from the given array
	 * Wrapper method for _addOrRemoveUsers
	 */
	private function _removeNotCreatedUsers($users)
	{
		return $this->_addOrRemoveUsers($users, true);
	}

	/**
	 * Used to remove created or not created users from the given array
	 * initialFoundValue is a toggle
	 */
	private function _addOrRemoveUsers($emps, $initialFoundValue)
	{
		$diffUsersArray = array(); // array that is foing to be returned

		// Get synchronized users from database
		$dbModel = new DB_Model();
		$dbSyncdUsers = $dbModel->execReadOnlyQuery('
			SELECT s.mitarbeiter_uid
			  FROM sync.tbl_sap_mitarbeiter s
			 WHERE s.mitarbeiter_uid IN ?
		', array($emps));

		// If error then return it
		if (isError($dbSyncdUsers)) return $dbSyncdUsers;

		// Loops through the given users and depending on the value of the parameter initialFoundValue
		// removes created (initialFoundValue == false) or not created (initialFoundValue == true) users
		// from the users parameter
		for ($i = 0; $i < count($emps); $i++)
		{
			$found = $initialFoundValue; // initial value is the same as initialFoundValue

			if (hasData($dbSyncdUsers)) // only if data are present in database
			{
				foreach (getData($dbSyncdUsers) as $dbSyncUser) // for each synced employee
				{
					if ($emps[$i] == $dbSyncUser->mitarbeiter_uid)
					{
						$found = !$initialFoundValue; // opposite value of initialFoundValue
						break;
					}
				}
			}

			if (!$found) $diffUsersArray[] = $emps[$i]; // if not found then add to diffUsersArray array
		}

		return success($diffUsersArray);
	}

	/**
	 * Retrieves all the data needed to create/update a employee on SAP side
	 */
	private function _getAllUsersData($emps)
	{
		$empsAllDataArray = array(); // returned array

		// Retrieves users personal data from database
		$dbModel = new DB_Model();

		$dbEmpsPersonalData = $dbModel->execReadOnlyQuery('
			SELECT DISTINCT p.person_id,
				p.nachname AS surname,
				p.vorname AS name,
				p.anrede AS title,
				p.sprache AS language,
				p.geschlecht AS gender,
				p.gebdatum AS bday,
				b.uid AS uid
			  FROM public.tbl_person p
			  JOIN public.tbl_benutzer b USING(person_id)
			 WHERE b.uid IN ?
		', array(
			getData($emps)
		));

		if (isError($dbEmpsPersonalData)) return $dbEmpsPersonalData;
		if (!hasData($dbEmpsPersonalData)) return error('The provided person ids are not present in database');

		// Loops through users personal data
		foreach (getData($dbEmpsPersonalData) as $empPersonalData)
		{
			// -------------------------------------------------------------------------------------------
			// Gender
			//
			// Male
			if ($empPersonalData->gender == self::FHC_GENDER_MALE)
			{
				$empPersonalData->gender = self::SAP_GENDER_MALE;
			}
			// Female
			elseif ($empPersonalData->gender == self::FHC_GENDER_FEMALE)
			{
				$empPersonalData->gender = self::SAP_GENDER_FEMALE;
			}
			// Non binary
			elseif ($empPersonalData->gender == self::FHC_GENDER_NON_BINARY)
			{
				$empPersonalData->gender = self::SAP_GENDER_NON_BINARY;
			}
			// Unknown
			else
			{
				$empPersonalData->gender = self::SAP_GENDER_UNKNOWN;
			}

			// -------------------------------------------------------------------------------------------
			// Language

			// If the language is english then store the iso code
			if ($empPersonalData->language == self::ENGLISH_LANGUAGE)
			{
				$empPersonalData->language = self::ENGLISH_LANGUAGE_ISO;
			}
			else // otherwise for any other language use the default iso code
			{
				$empPersonalData->language = self::DEFAULT_LANGUAGE_ISO;
			}

			$empAllData = $empPersonalData; // Stores current employee personal data

			// -------------------------------------------------------------------------------------------
			// Address
			$this->_ci->load->model('person/Adresse_model', 'AdresseModel');
			$this->_ci->AdresseModel->addJoin('bis.tbl_nation', 'public.tbl_adresse.nation = bis.tbl_nation.nation_code');
			$this->_ci->AdresseModel->addOrder('updateamum', 'DESC');
			$this->_ci->AdresseModel->addOrder('insertamum', 'DESC');
			$this->_ci->AdresseModel->addLimit(1);
			$addressResult = $this->_ci->AdresseModel->loadWhere(
				array(
					'person_id' => $empPersonalData->person_id, 'zustelladresse' => true
				)
			);

			if (isError($addressResult)) return $addressResult;
			if (hasData($addressResult)) // if an address was found
			{
				$empAllData->country = getData($addressResult)[0]->iso3166_1_a2;
				$empAllData->street = getData($addressResult)[0]->strasse;
				$empAllData->zip = getData($addressResult)[0]->plz;
				$empAllData->city = getData($addressResult)[0]->ort;
			}


			// -------------------------------------------------------------------------------------------
			// Email
/*
			// Fallback on the private email
			$this->_ci->load->model('person/Kontakt_model', 'KontaktModel');
			$this->_ci->KontaktModel->addOrder('updateamum', 'DESC');
			$this->_ci->KontaktModel->addOrder('insertamum', 'DESC');
			$this->_ci->KontaktModel->addLimit(1);
			$kontaktResult = $this->_ci->KontaktModel->loadWhere(
				array(
					'person_id' => $empPersonalData->person_id, 'kontakttyp' => 'email', 'zustellung' => true
				)
			);

			if (isError($kontaktResult)) return $kontaktResult;
			if (hasData($kontaktResult)) // if an email was found
			{
				$empAllData->privateEmail = getData($kontaktResult)[0]->kontakt;
			}
			else // otherwise set the email as null, it should be checked later every time before using it
			{
				$empAllData->privateEmail = null;
			}*/
/*
			// Company Mail
			$empAllData->email = $empPersonalData->uid.'@'.DOMAIN;

			// -------------------------------------------------------------------------------------------
			// Bank
			$this->_ci->load->model('person/Bankverbindung_model', 'BankverbindungModel');
			$this->_ci->BankverbindungModel->addOrder('updateamum', 'DESC');
			$this->_ci->BankverbindungModel->addOrder('insertamum', 'DESC');
			$this->_ci->BankverbindungModel->addLimit(1);
			$bankResult = $this->_ci->BankverbindungModel->loadWhere(
				array(
					'person_id' => $empPersonalData->person_id, 'verrechnung' => true
				)
			);
			$empAllData->bank = getData($bankResult)[0];

			if (isError($bankResult)) return $bankResult;
			if (hasData($bankResult)) // if an email was found
			{
				$empAllData->email = getData($bankResult)[0]->kontakt;
			}
			else // otherwise set the email as null, it should be checked later every time before using it
			{
				$userAllData->email = null;
			}*/


			// -------------------------------------------------------------------------------------------
			// Bisverwendung

			$this->_ci->load->model('codex/bisverwendung_model', 'BisverwendungModel');
			$bisResult = $this->_ci->BisverwendungModel->getLast($empPersonalData->uid);


			if (isError($bisResult)) return $bisResult;
			if (hasData($bisResult)) // if an email was found
			{
				$empAllData->startDate = getData($bisResult)[0]->beginn;
				$empAllData->endDate = getData($bisResult)[0]->ende;
				$empAllData->decimalValue = getData($bisResult)[0]->vertragsstunden;
			}

			if (is_null($empAllData->endDate))
			{
				$empAllData->typeCode = self::SAP_TYPE_PERMANENT;
			}
			else
			{
				$empAllData->typeCode = self::SAP_TYPE_TEMPORARY;
			}
			// Stores all data for the current employee
			$empsAllDataArray[] = $empAllData;

		}

		return success($empsAllDataArray); // everything was fine!
	}

	public function getEmployeeAfterCreation($name, $surname, $date)
	{
		return $this->_ci->QueryEmployeeInModel->findByIdentification(
			array(
				'EmployeeBasicDataSelectionByIdentification' => array(
					'SelectionByEmployeeGivenName' => array(
						'LowerBoundaryEmployeeGivenName' => $name,
						'InclusionExclusionCode' => 'I',
						'IntervalBoundaryTypeCode' => 1
					),
					'SelectionByEmployeeFamilyName' => array(
						'LowerBoundaryEmployeeFamilyName' => $surname,
						'InclusionExclusionCode' => 'I',
						'IntervalBoundaryTypeCode' => 1
					),
					'SelectionByCreatedSinceDate' => array(
						'LowerBoundaryEmployeeCreatedSinceDate' => $date,
						'InclusionExclusionCode' => 'I',
						'IntervalBoundaryTypeCode' => 1
					)

				),
				'ProcessingConditions' => array(
					'QueryHitsUnlimitedIndicator' => false,
					'QueryHitsMaximumNumberValue' => 1,
				)
			)
		);
	}
}

