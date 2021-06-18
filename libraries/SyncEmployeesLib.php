<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * This library contains the logic used to perform data synchronization between FHC and SAP Business by Design
 */
class SyncEmployeesLib
{
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
		if (isEmptyArray($users)) return success('No users to be created');

		// Remove the already created users performing a diff between the given person ids and those present
		// in the sync table. If no errors and the diff array is not empty then continues, otherwise a message
		// is returned
		$diffUsers = $this->_removeCreatedUsers($users);
		if (isError($diffUsers)) return $diffUsers;
		if (!hasData($diffUsers)) return success('No users to be created after diff');

		// Retrieves all users data
		$usersAllData = $this->_getAllUsersData($diffUsers);

		if (isError($usersAllData)) return $usersAllData;
		if (!hasData($usersAllData)) return error('No data available for the given users');

		// Loops through users data
		foreach (getData($usersAllData) as $userData)
		{
			// If an email address was not found for this user...
			if (isEmptyString($userData-><something mandatory>))
			{
				$this->_ci->LogLibSAP->logWarningDB('Was not possible to find a valid email address for user: '.$userData->person_id);
				continue; // ...and continue to the next one
			}

			// If not a valid ort or gemeinde is present
			// If ort is not present or it is an empty string
			if (!isset($userData->ort) || (isset($userData->ort) && isEmptyString($userData->ort)))
			{
				// If also the gemeinde is not present or it is an empty string
				if (!isset($userData->gemeinde) || (isset($userData->gemeinde) && isEmptyString($userData->gemeinde)))
				{
					$this->_ci->LogLibSAP->logWarningDB('Was not possible to find a valid ort or gemeinde for user: '.$userData->person_id);
					continue; // ...and continue to the next one
				}
			}

			// Checks if the current employee is already present in SAP
			$userDataSAP = $this->_employeesExistsBySomething($userData-><criteria>);

			// If an error occurred then return it
			if (isError($userDataSAP)) return $userDataSAP;

			// If the current user is not present in SAP
			if (!hasData($userDataSAP))
			{
				$data = array(
					'BasicMessageHeader' => array(
						'ID' => generateUID(self::CREATE_EMP_PREFIX),
						'UUID' => generateUUID()
					),
					'PersonnelHiring' => array(
						'actionCode' => '01',
						'ObjectNodeSenderTechnicalID' => '',
						'Employee' => array(
							'GivenName' => $userData->name,
							'FamilyName' => $userData->surname,
							'BirthName' => $userData->surname,
							'NonVerbalCommunicationLanguageCode' => $userData->language,
							'GenderCode' => $userData->gender
						),
						...
						...
						...
					)
				);

				// Then create it!
				$manageCustomerResult = $this->_ci->ManagePersonnelHiringInModel->maintainBundle($data);

				// If an error occurred then return it
				if (isError($manageCustomerResult)) return $manageCustomerResult;

				var_dump($manageCustomer);exit;

				// SAP data
				$manageCustomer = getData($manageCustomerResult);

				// If data structure is ok...
				if (isset($manageCustomer->PersonnelHiring) && isset($manageCustomer->PersonnelHiring->InternalID))
				{
					// Store in database the couple person_id sap_user_id
					$insert = $this->_ci->SAPMitarbeiterModel->insert(
						array(
							'miterbeiter_id' => $userData->person_id,
							'sap_ee_id' => $manageCustomer->PersonnelHiring->InternalID
						)
					);

					// If database error occurred then return it
					if (isError($insert)) return $insert;
				}
				else // ...otherwise store a non blocking error and continue with the next user
				{
					// If it is present a description from SAP then use it
					if (isset($manageCustomer->Log) && isset($manageCustomer->Log->Item)
						&& isset($manageCustomer->Log->Item))
					{
						if (!isEmptyArray($manageCustomer->Log->Item))
						{
							foreach ($manageCustomer->Log->Item as $item)
							{
								if (isset($item->Note)) $this->_ci->LogLibSAP->logWarningDB($item->Note.' for user: '.$userData->person_id);
							}
						}
						elseif ($manageCustomer->Log->Item->Note)
						{
							$this->_ci->LogLibSAP->logWarningDB($manageCustomer->Log->Item->Note.' for user: '.$userData->person_id);
						}
					}
					else
					{
						// Default non blocking error
						$this->_ci->LogLibSAP->logWarningDB('SAP did not return the InterlID for user: '.$userData->person_id);
					}
					continue;
				}
			}
			else // Add the already existing employee to the sync table
			{
				$sapCustomer = getData($userDataSAP); // get SAP customer data

				// Store in database the couple person_id sap_user_id
				$insert = $this->_ci->SAPStudentsModel->insert(
					array(
						'uid_id' => $userData->uid_id,
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
		for ($i = 0; $i < count($users); $i++)
		{
			$found = $initialFoundValue; // initial value is the same as initialFoundValue

			if (hasData($dbSyncdUsers)) // only if data are present in database
			{
				foreach (getData($dbSyncdUsers) as $dbSyncUser) // for each synced user
				{
					if ($users[$i] == $dbSyncUser->person_id)
					{
						$found = !$initialFoundValue; // opposite value of initialFoundValue
						break;
					}
				}
			}

			if (!$found) $diffUsersArray[] = $users[$i]; // if not found then add to diffUsersArray array
		}

		return success($diffUsersArray);
	}

	/**
	 * Retrieves all the data needed to create/update a user on SAP side
	 */
	private function _getAllUsersData($emps)
	{
		$usersAllDataArray = array(); // returned array

		// Retrieves users personal data from database
		$dbModel = new DB_Model();

		$dbUsersPersonalData = $dbModel->execReadOnlyQuery('
			SELECT DISTINCT p.person_id,
				p.nachname AS surname,
				p.vorname AS name,
				p.anrede AS title,
				p.sprache AS language,
				p.geschlecht AS gender,
				b.uid AS uid
			  FROM public.tbl_person p
			  JOIN public.tbl_benutzer b USING(person_id)
			 WHERE b.uid IN ?
		', array(
			getData($emps)
		));

		if (isError($dbUsersPersonalData)) return $dbUsersPersonalData;
		if (!hasData($dbUsersPersonalData)) return error('The provided person ids are not present in database');

		// Loops through users personal data
		foreach (getData($dbUsersPersonalData) as $userPersonalData)
		{
			// -------------------------------------------------------------------------------------------
			// Gender
			//
			// Male
			if ($userPersonalData->gender == self::FHC_GENDER_MALE)
			{
				$userPersonalData->gender = self::SAP_GENDER_MALE;
			}
			// Female
			elseif ($userPersonalData->gender == self::FHC_GENDER_FEMALE)
			{
				$userPersonalData->gender = self::SAP_GENDER_FEMALE;
			}
			// Non binary
			elseif ($userPersonalData->gender == self::FHC_GENDER_NON_BINARY)
			{
				$userPersonalData->gender = self::SAP_GENDER_NON_BINARY;
			}
			// Unknown
			else
			{
				$userPersonalData->gender = self::SAP_GENDER_UNKNOWN;
			}

			// -------------------------------------------------------------------------------------------
			// Language

			// If the language is english then store the iso code
			if ($userPersonalData->language == self::ENGLISH_LANGUAGE)
			{
				$userPersonalData->language = self::ENGLISH_LANGUAGE_ISO;
			}
			else // otherwise for any other language use the default iso code
			{
				$userPersonalData->language = self::DEFAULT_LANGUAGE_ISO;
			}

			$userAllData = $userPersonalData; // Stores current user personal data

			// -------------------------------------------------------------------------------------------
			// Address
			$this->_ci->load->model('person/Adresse_model', 'AdresseModel');
			$this->_ci->AdresseModel->addJoin('bis.tbl_nation', 'public.tbl_adresse.nation = bis.tbl_nation.nation_code');
			$this->_ci->AdresseModel->addOrder('updateamum', 'DESC');
			$this->_ci->AdresseModel->addOrder('insertamum', 'DESC');
			$this->_ci->AdresseModel->addLimit(1);
			$addressResult = $this->_ci->AdresseModel->loadWhere(
				array(
					'person_id' => $userPersonalData->person_id, 'zustelladresse' => true
				)
			);

			$userAllData->country = '';

			if (isError($addressResult)) return $addressResult;
			if (hasData($addressResult)) // if an address was found
			{
				$userAllData->country = getData($addressResult)[0]->iso3166_1_a2;
				$userAllData->strasse = getData($addressResult)[0]->strasse;
				$userAllData->plz = getData($addressResult)[0]->plz;
				$userAllData->ort = getData($addressResult)[0]->ort;
			}

			// -------------------------------------------------------------------------------------------
			// Email

			// Fallback on the private email
			$this->_ci->load->model('person/Kontakt_model', 'KontaktModel');
			$this->_ci->KontaktModel->addOrder('updateamum', 'DESC');
			$this->_ci->KontaktModel->addOrder('insertamum', 'DESC');
			$this->_ci->KontaktModel->addLimit(1);
			$kontaktResult = $this->_ci->KontaktModel->loadWhere(
				array(
					'person_id' => $userPersonalData->person_id, 'kontakttyp' => 'email', 'zustellung' => true
				)
			);

			if (isError($kontaktResult)) return $kontaktResult;
			if (hasData($kontaktResult)) // if an email was found
			{
				$userAllData->privateEmail = getData($kontaktResult)[0]->kontakt;
			}
			else // otherwise set the email as null, it should be checked later every time before using it
			{
				$userAllData->privateEmail = null;
			}

			// Get Company Mail if available
			$userAllData->email = userPersonalData->uid.'@'.DOMAIN;

			// Stores all data for the current user
			$usersAllDataArray[] = $userAllData;
		}

		return success($usersAllDataArray); // everything was fine!
	}

	/**
	 * Checks on SAP side if a user already exists with the given email address
	 * Returns a success object with the found user data, otherwise with a false value
	 * In case of error then an error object is returned
	 */
	private function _employeesExistsBySomething($<criteria>)
	{
		$queryCustomerResult = $this->getUserByEmail($email);

		if (isError($queryCustomerResult)) return $queryCustomerResult;
		if (!hasData($queryCustomerResult)) return error('Something went wrong while checking if a user is present using email adress');

		// Get data from then returned object
		$queryCustomer = getData($queryCustomerResult);

		// Checks the structure of then returned object
		if (isset($queryCustomer->ProcessingConditions)
			&& isset($queryCustomer->ProcessingConditions->ReturnedQueryHitsNumberValue))
		{
			// Returns the customer object a user is present in SAP with the given email, otherwise an empty success
			if ($queryCustomer->ProcessingConditions->ReturnedQueryHitsNumberValue > 0
				&& ($queryCustomer->Customer->LifeCycleStatusCode == self::USER_STATUS_PREPARATION
				|| $queryCustomer->Customer->LifeCycleStatusCode == self::USER_STATUS_ACTIVE))
			{
				return success($queryCustomer->Customer);
			}
			else
			{
				return success();
			}
		}
		else
		{
			return error('The returned SAP object is not correctly structured');
		}
	}

	/**
	 * Return the raw result of SAP->QueryCustomerIn->FindByCommunicationData->SelectionByEmailURI
	 */
	public function getUserByEmail($email)
	{
		// Calls SAP to find a user with the given email
		return $this->_ci->QueryCustomerInModel->findByCommunicationData(
			array(
				'CustomerSelectionByCommunicationData' => array(
					'SelectionByEmailURI' => array(
						'LowerBoundaryEmailURI' => $email,
						'InclusionExclusionCode' => 'I',
						'IntervalBoundaryTypeCode' => 1
					)
				),
				'ProcessingConditions' => array(
					'QueryHitsUnlimitedIndicator' => true
				)
			)
		);
	}
}

