<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * This library contains the logic used to perform data synchronization between FHC and SAP Business by Design
 */
class SyncEmployeesLib
{
	const SAP_EMPLOYEES_WARNINGS_ENABLED = 'sap_employees_warnings_enabled';

	// Jobs types used by this lib
	const SAP_EMPLOYEES_CREATE = 'SAPEmployeesCreate';
	const SAP_EMPLOYEES_UPDATE = 'SAPEmployeesUpdate';
	const SAP_EMPLOYEES_WORK_AGREEMENT_UPDATE = 'SAPEmployeesWorkAgreementUpdate';

	const CREATE_EMP_PREFIX = 'CE';
	const UPDATE_EMP_PREFIX = 'UU';

	// Genders
	const FHC_GENDER_MALE = 'm';
	const FHC_GENDER_FEMALE = 'w';
	const FHC_GENDER_NON_BINARY = 'x';
	const SAP_GENDER_UNKNOWN = 0;
	const SAP_GENDER_MALE = 1;
	const SAP_GENDER_FEMALE = 2;
	const SAP_GENDER_NON_BINARY = 3;

	const SAP_TYPE_PERMANENT = 1;
	const SAP_TYPE_TEMPORARY = 2;

	const JOB_ID = 'ECINT_DUMMY_JOB';
	const JOB_ID_2 = 'ECINT_DUMMY_JOB_2';
	const FHC_CONTRACT_TYPES = 'fhc_contract_types';

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
		$this->_ci->load->model('extensions/FHC-Core-SAP/SOAP/ManageEmployeeIn2_model', 'ManageEmployeeInModel');
		$this->_ci->load->model('extensions/FHC-Core-SAP/SOAP/ManagePersonnelLeavingIn_model', 'ManagePersonnelLeavingInModel');
		$this->_ci->load->model('extensions/FHC-Core-SAP/SOAP/ManagePersonnelTransferIn_model', 'ManagePersonnelTransferInModel');
		$this->_ci->load->model('extensions/FHC-Core-SAP/SOAP/ManagePersonnelRehireIn_model', 'ManagePersonnelRehireInModel');
		$this->_ci->load->model('extensions/FHC-Core-SAP/SOAP/QueryEmployeeIn_model', 'QueryEmployeeInModel');
		$this->_ci->load->model('person/benutzerfunktion_model', 'BenutzerfunktionModel');
		$this->_ci->load->model('codex/bisverwendung_model', 'BisverwendungModel');

		// Loads Projects configuration
		$this->_ci->config->load('extensions/FHC-Core-SAP/Employees');
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
	public function create($emps, $job = true)
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
		$empsAllData = $this->_getAllEmpsData($diffUsers, $job, true);

		if (isError($empsAllData)) return $empsAllData;
		if (!hasData($empsAllData)) return error('No data available for the given users');

		$error = false;
		// Loops through users data
		foreach (getData($empsAllData) as $empData)
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
						'AdministrativeCategoryCode' => 2, //bezahlter Angestellter
						'AgreedWorkingHoursRate' => array(
							'DecimalValue' => $empData->decimalValue,
							'BaseMeasureUnitCode' => 'WEE'
						),
						'OrganisationalCentreID' => $empData->kstZuordnungen->oe_kurzbz_sap,
						'JobID' => self::JOB_ID
					)

				)
			);

			// Then create it!
			$manageEmployeeResult = $this->_ci->ManagePersonnelHiringInModel->MaintainBundle($data);

			// If an error occurred then return it
			if (isError($manageEmployeeResult)) return $manageEmployeeResult;

			// SAP data
			$manageEmployee = getData($manageEmployeeResult);

			// If data structure is ok...
			if (isset($manageEmployee->PersonnelHiring) && isset($manageEmployee->PersonnelHiring->UUID))
			{
				// Get the employee after creation
				$sapEmployeeResult = $this->getEmployeeAfterCreation($empData->name, $empData->surname, date('Y-m-d'));

				if (isError($sapEmployeeResult)) return $sapEmployeeResult;

				$sapEmployee = getData($sapEmployeeResult);

				// Add payment information for the employee
				$this->addPaymentInformation($sapEmployee->BasicData->EmployeeID, $empData);
				// Store in database the couple person_id sap_user_id
				$insert = $this->_ci->SAPMitarbeiterModel->insert(
					array(
						'mitarbeiter_uid' => $empData->uid,
						'sap_eeid' => $sapEmployee->BasicData->EmployeeID->_
					)
				);

				// If database error occurred then return it
				if (isError($insert)) return $insert;
			}
			else // ...otherwise store a non blocking error and continue with the next employee
			{
				// If it is present a description from SAP then use it
				if (isset($manageEmployee->Log) && isset($manageEmployee->Log->Item)
					&& isset($manageEmployee->Log->Item))
				{
					if (!isEmptyArray($manageEmployee->Log->Item))
					{
						foreach ($manageEmployee->Log->Item as $item)
						{
							if (isset($item->Note)) $this->_ci->LogLibSAP->logWarningDB($item->Note . ' for employee: ' . $empData->person_id);
						}
					}
					elseif ($manageEmployee->Log->Item->Note)
					{
						$this->_ci->LogLibSAP->logWarningDB($manageEmployee->Log->Item->Note . ' for employee: ' . $empData->person_id);
					}
				}
				else
				{
					// Default non blocking error
					$this->_ci->LogLibSAP->logWarningDB('SAP did not return the InterlID for employee: ' . $empData->person_id);
				}
				$error = true;
				continue;
			}
		}

		if ($job === false && $error)
			return error('Please check the logs');

		return success('Users data created successfully');
	}

	public function update($emps, $job = true)
	{
		if (isEmptyArray($emps)) return success('No emps to be updated');

		// Remove the already created users
		$diffEmps = $this->_removeNotCreatedEmps($emps);

		if (isError($diffEmps)) return $diffEmps;
		if (!hasData($diffEmps)) return success('No emps to be created after diff');

		// Retrieves all users data
		$empsAllData = $this->_getAllEmpsData($diffEmps, $job);

		if (isError($empsAllData)) return $empsAllData;
		if (!hasData($empsAllData)) return error('No data available for the given emps');

		$error = false;
		$dbModel = new DB_Model();

		// Loops through users data
		foreach (getData($empsAllData) as $empData)
		{
			// Gets the SAP id for the current emp
			$sapIdResult = $dbModel->execReadOnlyQuery('
				SELECT s.sap_eeid
				FROM sync.tbl_sap_mitarbeiter s
				WHERE s.mitarbeiter_uid = ?
			', array($empData->uid));

			if (isError($sapIdResult)) return $sapIdResult;
			if (!hasData($sapIdResult)) continue;

			// Checks if the current user is already present in SAP
			$empDataSAP = $this->getEmployeeById(getData($sapIdResult)[0]->sap_eeid);

			// If an error occurred then return it
			if (isError($empDataSAP)) return $empDataSAP;

			// If the current user is present in SAP
			if (hasData($empDataSAP))
			{
				$basicHeader = array(
					'BasicMessageHeader' => array(
						'ID' => generateUID(self::UPDATE_EMP_PREFIX),
						'UUID' => generateUUID()
					)
				);

				$employeeData = array(
					'EmployeeData' => array(
						'ObjectNodeSenderTechnicalID' => null,
						'ChangeStateID' => null,
						'UUID' => generateUUID(),
						'actionCode' => '04',
						'addressInformationListCompleteTransmissionIndicator' => true,
						'workplaceAddressInformationListCompleteTransmissionIndicator' => true,
						'identificationListCompleteTransmissionIndicator' => true,
						'Identification' => array(
							'actionCode' => '06',
							'ObjectNodeSenderTechnicalID' => 'Identity',
							'PartyIdentifierTypeCode' => 'HCM001',
							'BusinessPartnerID' => '',
							'EmployeeID' => getData($sapIdResult)[0]->sap_eeid,
						),
						'Common' => array(
							'actionCode' => '04',
							'Person' => array(
								'Name'  => array(
									'GivenName' => $empData->name,
									'FamilyName' => $empData->surname,
								),
								'GenderCode' => $empData->gender,
								'BirthDate' => $empData->bday,
							),
							'ValidityPeriod' => array(
								'StartDate' => '0001-01-01',
								'EndDate' => '9999-12-31'
							)
						),
						'AddressInformation' => array(
							'actionCode' => '04',
							'ValidityPeriod' => array(
								'StartDate' => '0001-01-01',
								'EndDate' => '9999-12-31'
							),
							'Address' => array(
								'actionCode' => '04',
								'PostalAddress' => array(
									'actionCode' => '04',
									'CountryCode' => $empData->country,
									'CityName' => $empData->city,
									'StreetPostalCode' => $empData->zip,
									'StreetName' => $empData->street
								),
							),
						),
						'WorkplaceAddressInformation' => array(
							'actionCode' => '04',
							'Address' => array(
								'actionCode' => '04',
								'Email' => array(
									'actionCode' => '04',
									'ObjectNodeSenderTechnicalID' => null,
									'URI' => $empData->email
								)
							)
						),
					)
				);

				if (isset($empData->iban))
				{
					$payment = array(
						'PaymentInformation' => array(
							'actionCode' => '04',
							'ObjectNodeSenderTechnicalID' => null,
							'PaymentFormCode' => '05', //Bank transfer
							'BankDetails' => array(
								'actionCode' => '04',
								'ObjectNodeSenderTechnicalID' => null,
								'ID' => '0001', //random ID
								'BankAccountID' => $empData->accNumber,
								'BankAccountTypeCode' => '03', //Checking Account
								'BankAccountHolderName' => $empData->name . ' ' . $empData->surname,
								'BankAccountStandardID' => $empData->iban,
								'BankRoutingID' => $empData->bankNumber,
								'BankRoutingIDTypeCode' => $empData->bankCountry,
								'MainBankIndicator' => 'true',
								'ValidityPeriod' => array(
									'StartDate' => '0001-01-01',
									'EndDate' => '9999-12-31'
								),
							)
						)
					);
					if (isset($empData->bankInternalID))
					{
						$payment['PaymentInformation']['BankDetails'] = array_merge($payment['PaymentInformation']['BankDetails'], ['BankInternalID' => $empData->bankInternalID]);
					}
					$employeeData['EmployeeData'] = array_merge($employeeData['EmployeeData'], $payment);
				}
				$data = array_merge($basicHeader, $employeeData);
				// Then update it!
				$manageEmpResult = $this->_ci->ManageEmployeeInModel->MaintainBundle($data);

				// If an error occurred then return it
				if (isError($manageEmpResult)) return $manageEmpResult;

				// SAP data
				$manageEmp = getData($manageEmpResult);

				// If data structure is ok...
				if (isset($manageEmp->EmployeeData) && isset($manageEmp->EmployeeData->ChangeStateID))
				{
					// Store in database the date of the update
					$update = $this->_ci->SAPMitarbeiterModel->update(
						array(
							'mitarbeiter_uid' => $empData->uid
						),
						array(
							'last_update' => 'NOW()'
						)
					);

					// If database error occurred then return it
					if (isError($update)) return $update;
				}
				else if ($this->_ci->config->item(self::SAP_EMPLOYEES_WARNINGS_ENABLED) === true)// ...otherwise store a non blocking error and continue with the next user
				{
					// If it is present a description from SAP then use it
					if (isset($manageEmp->Log) && isset($manageEmp->Log->Item))
					{
						if (!isEmptyArray($manageEmp->Log->Item))
						{
							foreach ($manageEmp->Log->Item as $item)
							{
								if (isset($item->Note)) $this->_ci->LogLibSAP->logWarningDB($item->Note.' for user: '.$empData->person_id);
							}
						}
						elseif ($manageEmp->Log->Item->Note)
						{
							$this->_ci->LogLibSAP->logWarningDB($manageEmp->Log->Item->Note.' for user: '.$empData->person_id);
						}
					}
					else
					{
						// Default non blocking error
						$this->_ci->LogLibSAP->logWarningDB('SAP did not return the EmpData for user: '.$empData->person_id);
					}
					$error = true;
					continue;
				}
			}
		}

		if ($job === false && $error)
			return error('Please check the logs');

		return success('Users data updated successfully');
	}

	public function updateEmployeeWorkAgreement($emps, $job = true)
	{
		if (isEmptyArray($emps)) return success('No emps to be updated');

		// Löscht alle nicht erstellten Emps
		$diffEmps = $this->_removeNotCreatedEmps($emps);

		if (isError($diffEmps)) return $diffEmps;
		if (!hasData($diffEmps)) return success('No emps to be updated after diff');

		// Holt sich alle Daten des Emps
		$empsAllData = $this->_getAllEmpsData($diffEmps, $job);

		if (isError($empsAllData)) return $empsAllData;
		if (!hasData($empsAllData)) return error('No data available for the given emps');

		$error = false;
		$dbModel = new DB_Model();

		foreach (getData($empsAllData) as $empData)
		{
			// SAP ID vom EMP holen
			$sapResult = $dbModel->execReadOnlyQuery('
				SELECT s.sap_eeid
				FROM sync.tbl_sap_mitarbeiter s
				WHERE s.mitarbeiter_uid = ?
			', array($empData->uid));

			if (isError($sapResult)) return $sapResult;
			if (!hasData($sapResult)) continue;

			$sapID = getData($sapResult)[0]->sap_eeid;

			$sapEmpData = $this->getEmployeeById($sapID);

			if (isError($sapEmpData)) return $sapEmpData;

			if (!hasData($sapEmpData)) continue;

			$sapEmpData = getData($sapEmpData);

			if ($sapEmpData->ProcessingConditions->ReturnedQueryHitsNumberValue === 0)
				return error('Emp not found in SAP');

			$sapEmpData = $sapEmpData->EmployeeData->EmploymentData;

			$sapEmpData = $this->checkIfObject($sapEmpData);

			foreach ($sapEmpData as $sapData)
			{
				$workAgreements =  $this->checkIfObject($sapData->WorkAgreementData);
				/*Gehen alle Verträge der Person durch*/
				foreach ($workAgreements as $workAgreement)
				{
					/*Start des Arbeitsvertrages*/
					$workAgreementStart = $workAgreement->ValidityPeriod->StartDate;

					/*Klauseln aus dem SAPByD*/
					$benutzerFunctionsSAP = $this->checkIfObject($workAgreement->AdditionalClauses);

					$startDates[$workAgreementStart] = true;

					/*speichern uns die Benutzer Funktionen aus SAPByD*/
					foreach($benutzerFunctionsSAP as $key => $benutzerFunctionSAP)
					{
						$functionStart = $benutzerFunctionSAP->ValidityPeriod->StartDate;
						$sapEmpType['functions'][$functionStart] = $benutzerFunctionSAP;
					}

					$benutzerOrganisationalsSAP = $this->checkIfObject($workAgreement->OrganisationalAssignment);

					/*speichern uns die Zuordnungen aus SAPByD*/
					foreach ($benutzerOrganisationalsSAP as $key => $benutzerOrganisationalSAP)
					{
						/*es kommt vor, dass bei vorhandenen Benutzern mehrere Zurordnungen bestehen in der selben Zeit
						wir prüfen ob eine OE in der Sync Tabelle besteht
						falls ja, übernehmen wir nur die richtige Zuordnung
						falls keine in der Tabelle vorhanden ist brechen wir ab*/
						$positions = $this->checkIfObject($benutzerOrganisationalSAP->PositionAssignment);

						$exists = false;

						foreach ($positions as $position)
						{
							$organisationStart = $position->ValidityPeriod->StartDate;
							$sapEmpType['organisation'][$organisationStart] = $benutzerOrganisationalSAP;

							/*kam einmalig vor, dass die Zurodnung leer war
							SAPByD zeigt es in der Mitarbeiterliste richtig an, aber nicht in den Informationen von dem Benutzer, Datum ist eingetragen aber keine Stelle bzw Abteilungs*/
							if (isset($position->OrganisationalCenterDetails->OrganisationalCenterID))
							{
								$oeResult = $dbModel->execReadOnlyQuery('
									SELECT *
									FROM sync.tbl_sap_organisationsstruktur
									WHERE oe_kurzbz_sap = ?
								', array($position->OrganisationalCenterDetails->OrganisationalCenterID));

								if(hasData($oeResult))
								{
									$sapEmpType['organisation'][$organisationStart] = $position;
									break;
								}

								/*wenn die OE nicht in der Sync Tabelle eingetragen ist, wird abgebrochen*/
								return error("Eine Organisation ist im SAPByD nicht richtig hinterlegt");
							}
						}
					}
				}
			}

			/*holen uns das erste eingetragene Datum aus SAPByD
			damit wir uns die Bisverwendungen ab dem Zeitpunkt holen*/
			ksort($startDates);
			$firstDateSap = array_keys($startDates)[0];
			$bisResult = $this->_ci->BisverwendungModel->getVerwendungen($empData->uid, $firstDateSap);

			if (!hasData($bisResult))
				return error("Keine bis Verwendung vorhanden");

			$bisResult = getData($bisResult);

			$updated = true;

			foreach($bisResult as $bisKey => $currentBis)
			{
				$oldBisKey = $bisKey;
				do
				{
					$oldBisKey--;
					$oldBis = isset($bisResult[$oldBisKey]) ? $bisResult[$oldBisKey] : false;
				} while($oldBis && !in_array($oldBis->ba1code, $this->_ci->config->item(self::FHC_CONTRACT_TYPES)));

				$newBis = isset($bisResult[$bisKey + 1]) ? $bisResult[$bisKey + 1] : false;

				$functionResult = $this->_ci->BenutzerfunktionModel->getBenutzerFunktionByUid($empData->uid, 'kstzuordnung', $currentBis->beginn, $currentBis->ende);

				if (!hasData($functionResult))
					return error("Fehler beim laden der Benutzerfunktionen");

				$functionResult = getData($functionResult);

				if ($currentBis->vertragsstunden === '0.00' || is_null($currentBis->vertragsstunden))
					$currentBis->vertragsstunden = '0.10';

				/*Prüfen ob die Bisverwendung keine Fixanstellung ist */

				if (!in_array($currentBis->ba1code, $this->_ci->config->item(self::FHC_CONTRACT_TYPES)) && $newBis === false)
				{
					if ($oldBis)
					{
						/*
						Prüfen zuerst welcher Eintrag später beginnt, ob es die Bisverwendung oder die Benutzerfunktion ist und übernehmen das Startdatum von dem jeweiligen
						Da es in SapByD keine Unterscheidung gibt, ob es eine Bisverwendung oder Benutzerfunktion ist
						*/
						krsort($sapEmpType['functions']);
						$lastSAPFunctionDate = array_keys($sapEmpType['functions'])[0];
						$sapEndDate = $sapEmpType['functions'][$lastSAPFunctionDate]->ValidityPeriod->EndDate;

						$leavingDate = $oldBis->ende;

						if (is_null($leavingDate))
						{
							$previousDay = strtotime("-1 day", strtotime($currentBis->beginn));
							$leavingDate = date("Y-m-d", $previousDay);
						}

						if ($leavingDate != $sapEndDate)
						{
							$updated = $this->addLeavingDate($sapID, $leavingDate, $empData->person_id);
							$sapEmpType['functions'][$lastSAPFunctionDate]->ValidityPeriod->EndDate = $leavingDate;
							if (!$updated)
								break;
						}
					}
					continue;
				}

				/*Prüfen ob dis Bisverwendung bereits mit dem Startdatum in SAPByD exestiert und ob der ba1code 103/109 ist*/
				if ((isset($startDates[$currentBis->beginn]) ||
					(!isset($startDates[$currentBis->beginn]) && isset($sapEmpType['functions'][$currentBis->beginn]))
					) &&
					(in_array($currentBis->ba1code, $this->_ci->config->item(self::FHC_CONTRACT_TYPES))))
				{
					/*Gehen dann alle Funktionen von der Person durch*/
					foreach ($functionResult as $functionKey => $currentFunction)
					{
						$oeResult = $dbModel->execReadOnlyQuery('
									SELECT *
									FROM sync.tbl_sap_organisationsstruktur
									WHERE oe_kurzbz = ?
								', array($currentFunction->oe_kurzbz));

						if (!hasData($oeResult))
							return error("Keine Organisation in SAP gefunden");

						$currentOE = getData($oeResult)[0]->oe_kurzbz_sap;

						/*Falls eine Organisatorische Zuordnung im SAPByD mit dem Datum der Benutzerfunktion bereits besteht*/
						if (isset($sapEmpType['functions'][$currentFunction->datum_von]))
						{
							/*Prüfen ob die OE ID in SapByD eingetragen ist
							Kam bei einem Test als Null zurück, ist eigentlich sonst immer eingetragen*/
							if (isset($sapEmpType['organisation'][$currentFunction->datum_von]->OrganisationalCenterDetails))
							{
								$sapOE = $sapEmpType['organisation'][$currentFunction->datum_von]->OrganisationalCenterDetails->OrganisationalCenterID;
							}
							else if (isset($sapEmpType['organisation'][$currentFunction->datum_von]) &&
									is_array($sapEmpType['organisation'][$currentFunction->datum_von]->PositionAssignment->OrganisationalCenterDetails))
							{
								/*Benutzer haben zum gleichen Zeitpunkt 2 Zuteilungen
								Holen uns alle Zuteilungen und vergleichen sie dann mit der jetzigen OE
								*/
								$sapOE = array();

								foreach ($sapEmpType['organisation'][$currentFunction->datum_von]->PositionAssignment->OrganisationalCenterDetails as $orgCenterDetails)
								{
									$sapOE[] = $orgCenterDetails->OrganisationalCenterID;
								}

								sort($sapOE);

								$sapOE = array_diff($sapOE, array($currentOE));
								$sapOE = reset($sapOE);
							}

							/*Ist die OE aus der Benutzerfunktion nicht die gleiche wie die, die in SAPByD eingetragen ist findet ein Transfer zur der richtigen OE statt*/
							if (((isset($sapOE) && $currentOE !== $sapOE) ||
								($sapEmpType['functions'][$currentFunction->datum_von]->AgreedWorkingTimeRate->DecimalValue . '0' !== $currentBis->vertragsstunden)) &&
								$currentBis->beginn <= $currentFunction->datum_von)
							{
								$updated = $this->transferEmployee($sapID, $currentFunction->datum_von, $currentBis->vertragsstunden, $currentOE, $empData->person_id, true);

								if (!$updated)
									break 2;
							}
							else if ((isset($sapOE) && $currentOE !== $sapOE) ||
									(isset($sapEmpType['functions'][$currentBis->beginn]) &&
									($sapEmpType['functions'][$currentBis->beginn]->AgreedWorkingTimeRate->DecimalValue . '0' !== $currentBis->vertragsstunden) &&
									$currentBis->beginn > $currentFunction->datum_von))
							{
								$updated = $this->transferEmployee($sapID, $currentBis->beginn, $currentBis->vertragsstunden, $currentOE, $empData->person_id, true);

								if (!$updated)
									break 2;
							}

						}
						else
						{
							/*Prüfen ob es eine alte Benutzerunktion gibt, falls nicht machen wir weiter, mit der nächsten Funktion
							Da die erste Funktion eigentlich immer im SapByD eingetragen sein müsste, da man sonst eine Person ohne Vertrag in SapByD hätte*/
							$oldFunction = isset($functionResult[$functionKey - 1]) ? $functionResult[$functionKey - 1] : false;

							if ($oldFunction === false)
								continue;

							$typeCode = self::SAP_TYPE_PERMANENT;
							$rehireLeaving = null;
							$newEndDate = '9999-12-31';

							//Holen uns das EndDate von SapByD, ist immer ein Datum gesetzt
							//Falls Austritt Datum "unbegrenzt" ist, ist es 9999-12-31
							if ($oldFunction->datum_von >= $currentBis->beginn && isset($sapEmpType['functions'][$oldFunction->datum_von]))
							{
								$oldSAPEndDate = $sapEmpType['functions'][$oldFunction->datum_von]->ValidityPeriod->EndDate;
							}
							elseif ($currentBis->beginn >= $oldFunction->datum_von && isset($sapEmpType['functions'][$currentBis->beginn]))
							{
								$oldSAPEndDate = $sapEmpType['functions'][$currentBis->beginn]->ValidityPeriod->EndDate;
							}
							else
							{
								$this->_ci->LogLibSAP->logWarningDB('No SAP EndDate: '. $empData->person_id);
								break 2;
							}

							$oldFASEndDate = null;
							if (!is_null($oldFunction->datum_bis))
								$oldFASEndDate = $oldFunction->datum_bis;

							/*Prüfen ob die alte OE der aktuellen entspricht
							Wenn ja muss die Person zuerst gekündigt werden, falls dass nicht bereits der Fall ist und neueingestellt werden
							Ansonsten findet nur ein Transfer statt*/
							if ($currentBis->beginn <= $currentFunction->datum_von && $oldSAPEndDate === '9999-12-31')
							{
								$updated = $this->transferEmployee($sapID, $currentFunction->datum_von, $currentBis->vertragsstunden, $currentOE, $empData->person_id);
								$sapEmpType['functions'][$currentFunction->datum_von] = (object) ['ValidityPeriod' => (object) ['EndDate' => $newEndDate]];
								if (!$updated)
									break 2;
							}
							else if($currentBis->beginn > $currentFunction->datum_von && $oldSAPEndDate === '9999-12-31')
							{
								$updated = $this->transferEmployee($sapID, $currentBis->beginn, $currentBis->vertragsstunden, $currentOE, $empData->person_id, true);
								$sapEmpType['functions'][$currentBis->beginn] = (object) ['ValidityPeriod' => (object) ['EndDate' => $newEndDate]];
								if (!$updated)
									break 2;
							}
							else
							{
								if (!is_null($oldFASEndDate) && $oldSAPEndDate !== $oldFASEndDate)
								{
									$updated = $this->addLeavingDate($sapID, $oldFASEndDate, $empData->person_id);
									if (!$updated)
										break 2;
								}
								$updated = $this->rehireEmployee($sapID, $currentFunction->datum_von, $typeCode, $currentBis->vertragsstunden, $currentOE, $empData->person_id, $rehireLeaving);
								$sapEmpType['functions'][$currentFunction->datum_von] = (object) ['ValidityPeriod' => (object) ['EndDate' => $newEndDate]];
								if (!$updated)
									break 2;
							}
						}
					}
					continue;
				}
				/* Falls die Bisverwendung noch nicht im SapByD exestiert und es sich um eine Fixanstellung handelt*/
				else if (in_array($currentBis->ba1code, $this->_ci->config->item(self::FHC_CONTRACT_TYPES)))
				{
					/*Falls keine alte Bisverwendung besteht, machen wir weiter
					Da die erste Bisverwendung eigentlich immer in SapByD eingetragen sein muss*/
					if ($oldBis === false)
						continue;

					krsort($sapEmpType['functions']);
					$lastSAPFunctionDate = array_keys($sapEmpType['functions'])[0];
					$oldSAPEndDate = $sapEmpType['functions'][$lastSAPFunctionDate]->ValidityPeriod->EndDate;

					if (!is_null($oldBis->ende))
					{
						$currBisStartDate = new DateTime($currentBis->beginn);
						$oldBisEndDate = new DateTime($oldBis->ende);

						$dateDiff = date_diff($currBisStartDate, $oldBisEndDate)->days;

						if ($dateDiff !== 1 || ($oldSAPEndDate !== '9999-12-31'))
						{
							$newEndDate = $oldBis->ende;
						}
						else
						{
							$newEndDate = '9999-12-31';
						}
					}
					else
					{
						$this->_ci->LogLibSAP->logWarningDB('Bisverwendung has no Enddate for the given user: '. $empData->person_id);
						break;
					}

					if ($oldSAPEndDate !== $newEndDate)
					{
						$updated = $this->addLeavingDate($sapID, $newEndDate, $empData->person_id);
						$sapEmpType['functions'][$lastSAPFunctionDate]->ValidityPeriod->EndDate = $newEndDate;

						if (!$updated)
							break 2;
					}

					/*Holen uns die letzte Benutzerfunktion von der letzten Bisverwendung um uns dann die letzte OE zu holen*/
					$oldFunctionResult = $this->_ci->BenutzerfunktionModel->getBenutzerFunktionByUid($empData->uid, 'kstzuordnung', $oldBis->beginn, $oldBis->ende);

					if (!hasData($oldFunctionResult))
						return error("Fehler beim laden der Benutzerfunktionen");

					$oldFunction = array_reverse(getData($oldFunctionResult))[0];
					$oldOeResult = $dbModel->execReadOnlyQuery('
									SELECT *
									FROM sync.tbl_sap_organisationsstruktur
									WHERE oe_kurzbz = ?
								', array($oldFunction->oe_kurzbz));

					/*Gehen die Benutzerfunktionen der aktuellen Bisverwendung durch*/
					foreach ($functionResult as $functionKey => $currentFunction)
					{
						if ($oldFunction === false)
							continue;

						if (!hasData($oldOeResult))
							return error("Keine Organisation in SAP gefunden");

						/*Holen uns die aktuelle OE Zuordnung*/
						$oeResult = $dbModel->execReadOnlyQuery('
									SELECT *
									FROM sync.tbl_sap_organisationsstruktur
									WHERE oe_kurzbz = ?
								', array($currentFunction->oe_kurzbz));

						if (!hasData($oeResult))
							return error("Keine Organisation in SAP gefunden");

						$currentOE = getData($oeResult)[0]->oe_kurzbz_sap;

						krsort($sapEmpType['functions']);
						$lastSAPFunctionDate = array_keys($sapEmpType['functions'])[0];
						$oldSAPEndDate = $sapEmpType['functions'][$lastSAPFunctionDate]->ValidityPeriod->EndDate;

						$typeCode = self::SAP_TYPE_PERMANENT;
						$rehireLeaving = null;
						$newEndDate = '9999-12-31';

						if ($currentBis->beginn >= $currentFunction->datum_von)
						{
							$startDate = $currentBis->beginn;
						}
						else
						{
							$startDate = $currentFunction->datum_von;
						}
						if ($oldSAPEndDate === "9999-12-31")
						{
							$updated = $this->transferEmployee($sapID, $startDate, $currentBis->vertragsstunden, $currentOE, $empData->person_id, true);
							$sapEmpType['functions'][$startDate] = (object) ['ValidityPeriod' => (object) ['EndDate' => $newEndDate]];
						}
						else
						{
							$updated = $this->rehireEmployee($sapID, $startDate, $typeCode, $currentBis->vertragsstunden, $currentOE, $empData->person_id, $rehireLeaving);
						}

						if (!$updated)
							break 2;
					}
				}

				//Wenn keine weitere Bisverwendung mehr vorhanden ist und ein Enddatum eingetragen ist wird ein Kündigungsdatum eingetragen
				if ($newBis === false && (in_array($currentBis->ba1code, $this->_ci->config->item(self::FHC_CONTRACT_TYPES))) && !is_null($currentBis->ende))
				{
					$currentBisEndDate = date($currentBis->ende, strtotime("+1 day"));
					$today = Date('Y-m-d');

					if ($today === $currentBisEndDate)
					{
						krsort($sapEmpType['functions']);
						$lastSAPFunctionDate = array_keys($sapEmpType['functions'])[0];
						$sapEndDate = $sapEmpType['functions'][$lastSAPFunctionDate]->ValidityPeriod->EndDate;

						$leavingDate = $currentBis->ende;

						if ($leavingDate != $sapEndDate)
						{
							$updated = $this->addLeavingDate($sapID, $leavingDate, $empData->person_id);

							if (isError($updated))
								break;
						}
					}
				}
			}

			if ($updated)
			{
				$update = $this->_ci->SAPMitarbeiterModel->update(
					array(
						'mitarbeiter_uid' => $empData->uid
					),
					array(
						'last_update_workagreement' => 'NOW()'
					)
				);

				// If database error occurred then return it
				if (isError($update)) return $update;
			}
			else
			{
				$error = true;
				continue;
			}
		}

		if ($job === false && $error)
			return error('Please check the logs');

		return success('Users data updated successfully');
	}

	private function checkIfObject($object)
	{
		if (is_object($object))
		{
			$infos = array();
			array_push($infos, $object);
			return $infos;
		}
		else
		{
			return $object;
		}
	}

	public function sync($empID)
	{
		$dbModel = new DB_Model();

		$sapIdResult = $dbModel->execReadOnlyQuery('
				SELECT s.sap_eeid
				FROM sync.tbl_sap_mitarbeiter s
				WHERE s.mitarbeiter_uid = ?
			', array($empID));

		if (isError($sapIdResult)) return $sapIdResult;

		$emp = array($empID);

		if (!hasData($sapIdResult))
			return $this->create($emp, false);
		else
		{
			$update = $this->update($emp, false);
			if (!isError($update))
				return $this->updateEmployeeWorkAgreement($emp, false);
			else
				return $update;
		}
	}

	public function getCSVEmployees()
	{
		$data = [];
		$emps = $this->getAllEmps();

		foreach ($emps as $emp)
		{
			if (!isset($emp->EmploymentData))
				continue;

			$additionalClauses['emp'] = $emp->EmployeeID->_;
			$additionalClauses['workingAgreement'] = [];

			if (is_array($emp->EmploymentData))
			{
				foreach($emp->EmploymentData as $empData)
				{
					if (isset($empData->WorkAgreementData))
						$additionalClauses['workingAgreement'] = $this->getWorkAgreementData($empData->WorkAgreementData, $additionalClauses['workingAgreement']);
				}
			}
			else if(isset($emp->EmploymentData->WorkAgreementData))
			{
				$additionalClauses['workingAgreement'] = $this->getWorkAgreementData($emp->EmploymentData->WorkAgreementData, $additionalClauses['workingAgreement']);
			}
			else
				continue;

			array_push($data, $additionalClauses);
		}

		sort($data);

		return $data;
	}

	public function getWorkAgreementData($workAgreement, $additionalClauses)
	{
		if (isset($workAgreement->AdditionalClauses))
		{
			if (is_array($workAgreement->AdditionalClauses))
			{
				foreach ($workAgreement->AdditionalClauses as $additionalClause)
				{
					$additionalClauses[] = $this->addAdditionalClause($additionalClause);
				}
			}
			else
			{
				$additionalClauses[] =  $this->addAdditionalClause($workAgreement->AdditionalClauses);
			}
		}
		return $additionalClauses;
	}

	public function addAdditionalClause($additionalClause)
	{
		$startDate = (isset($additionalClause->ValidityPeriod->StartDate)) ? $additionalClause->ValidityPeriod->StartDate : '';
		$decimal = (isset($additionalClause->AgreedWorkingTimeRate->DecimalValue)) ? $additionalClause->AgreedWorkingTimeRate->DecimalValue : '';
		$category = (isset($additionalClause->WorkAgreementAdministrativeCategoryCode->_)) ? $additionalClause->WorkAgreementAdministrativeCategoryCode->_ : '';
		return array('startDate' => $startDate, 'timeRate' => $decimal, 'category' => $category);
	}

	public function getAllEmps()
	{
		$objID = null;
		$emps = [];

		do {
			$empsData = $this->_ci->QueryEmployeeInModel->findByIdentification(
				array(
					'PROCESSING_CONDITIONS' => array(
						'QueryHitsMaximumNumberValue' => 40,
						'QueryHitsUnlimitedIndicator' => false,
						'LastReturnedObjectID' => $objID
					)
				)
			);

			if (!isset(getData($empsData)->EmployeeData))
				break;

			$objID = getData($empsData)->ProcessingConditions->LastReturnedObjectID->_;

			$emps[] = (array)(getData($empsData)->EmployeeData);

		} while (hasData($empsData));

		$emps = call_user_func_array('array_merge', $emps);

		return $emps;
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
	private function _removeNotCreatedEmps($emps)
	{
		return $this->_addOrRemoveUsers($emps, true);
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
	private function _getAllEmpsData($emps, $job = true, $create = false)
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

			if (isError($bankResult)) return $bankResult;

			$empAllData->accNumber = null;
			$empAllData->bankNumber = null;

			if (hasData($bankResult))
			{
				$bankData = getData($bankResult)[0];

				$iban = strtoupper(str_replace(' ','', $bankData->iban));
				$ibanCountry = substr($iban, 0, 2);

				$empAllData->bankCountry = $ibanCountry;

				if ($ibanCountry === 'AT')
				{
					$bankIBAN = checkIBAN($iban);
					if (!$bankIBAN)
					{
						$this->_ci->LogLibSAP->logWarningDB('Incorrect bank data available for the given user: '.$empPersonalData->person_id);
					}
					else
					{
						$empAllData->iban = $bankIBAN['iban'];
						$empAllData->accNumber = $bankIBAN['accNumber'];
						$empAllData->bankNumber = $bankIBAN['bankNumber'];
					}
				}
				else if (!is_null($bankData->bic))
				{
					$this->_ci->load->model('extensions/FHC-Core-SAP/SAPBanks_model', 'SAPBanksModel');
					$sapBankData = $this->_ci->SAPBanksModel->loadWhere(array('sap_bank_swift' => strtoupper(str_replace(' ', '', $bankData->bic))));

					if (hasData($sapBankData))
					{
						$sapBankData = getData($sapBankData)[0];
						$empAllData->bankInternalID = $sapBankData->sap_bank_id;
						$empAllData->iban = $iban;
					}
				}
			}
			else
				$this->_ci->LogLibSAP->logWarningDB('No bank data available for the given user: '.$empPersonalData->person_id);

			// -------------------------------------------------------------------------------------------
			// Bisverwendung
			if ($create)
			{
				$this->_ci->load->model('codex/bisverwendung_model', 'BisverwendungModel');
				$bisResult = $this->_ci->BisverwendungModel->getLast($empPersonalData->uid, false);
				if (isError($bisResult))
					return $bisResult;
				if (hasData($bisResult))
				{
					if (!in_array(getData($bisResult)[0]->ba1code, $this->_ci->config->item(self::FHC_CONTRACT_TYPES)))
					{
						if ($job === true)
						{
							$this->_ci->LogLibSAP->logWarningDB('Wrong Bisverwendung for the given user: ' . $empPersonalData->person_id);
							continue;
						} else
							return error('Wrong Bisverwendung for the given user');
					}
					$empAllData->startDate = getData($bisResult)[0]->beginn;
					$empAllData->endDate = getData($bisResult)[0]->ende;
					$vertragsstunden = getData($bisResult)[0]->vertragsstunden;
					if ($vertragsstunden === '0.00' || is_null($vertragsstunden))
						$empAllData->decimalValue = '0.10';
					else
						$empAllData->decimalValue = $vertragsstunden;
				} else
				{
					if ($job === true)
					{
						$this->_ci->LogLibSAP->logWarningDB('No Bisverwendung available for the given user: ' . $empPersonalData->person_id);
						continue;
					} else
						return error('No Bisverwendung available for the given user');
				}


				if (is_null($empAllData->endDate))
				{
					$empAllData->typeCode = self::SAP_TYPE_PERMANENT;
				} else
				{
					$empAllData->typeCode = self::SAP_TYPE_TEMPORARY;
				}

				$this->_ci->load->model('person/benutzerfunktion_model', 'BenutzerfunktionModel');

				$this->_ci->BenutzerfunktionModel->addJoin('sync.tbl_sap_organisationsstruktur', 'public.tbl_benutzerfunktion.oe_kurzbz = sync.tbl_sap_organisationsstruktur.oe_kurzbz');

				$this->_ci->BenutzerfunktionModel->addOrder('datum_von', 'DESC');
				$this->_ci->BenutzerfunktionModel->addLimit(1);

				$kstZuordnungen = $this->_ci->BenutzerfunktionModel->loadWhere(array('funktion_kurzbz' => 'kstzuordnung', 'uid' => $empPersonalData->uid));

				if (isError($kstZuordnungen))
					return $kstZuordnungen;

				if (hasData($kstZuordnungen))
				{
					$empAllData->kstZuordnungen = getData($kstZuordnungen)[0];
				} else
				{
					if ($job === true)
					{
						$this->_ci->LogLibSAP->logWarningDB('No Kstzuordnung available for the given user: ' . $empPersonalData->person_id);
						continue;
					} else
						return error('No Kstzuordnung available for the given user');
				}
			}

			$empAllData->email = $empPersonalData->uid . '@technikum-wien.at';
			// Stores all data for the current employee
			$empsAllDataArray[] = $empAllData;
		}

		return success($empsAllDataArray); // everything was fine!
	}

	public function getEmployeeById($id)
	{
		// Calls SAP to find a employee with the given id
		return $this->_ci->QueryEmployeeInModel->findByIdentification(
			array(
				'EmployeeDataSelectionByIdentification' => array(
					'SelectionByEmployeeID' => array(
						'LowerBoundaryEmployeeID' => $id,
						'InclusionExclusionCode' => 'I',
						'IntervalBoundaryTypeCode' => 1
					)
				),
				'ProcessingConditions' => array(
					'QueryHitsMaximumNumberValue' => 1,
					'QueryHitsUnlimitedIndicator' => false
				)
			)
		);
	}

	public function getEmployeeAfterCreation($name, $surname, $date)
	{
		return $this->_ci->QueryEmployeeInModel->FindBasicDataByIdentification(
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

	public function addPaymentInformation($emp, $empData)
	{
		$basicHeader = array(
			'BasicMessageHeader' => array(
				'ID' => generateUID(self::CREATE_EMP_PREFIX),
				'UUID' => null
			)
		);

		$employeeData = array(
			'EmployeeData' => array(
				'workplaceAddressInformationListCompleteTransmissionIndicator' => true,
				'actionCode' => '04',
				'ObjectNodeSenderTechnicalID' => null,
				'ChangeStateID' => null,
				'UUID' => null,
				'Identification' => array(
					'actionCode' => '06',
					'ObjectNodeSenderTechnicalID' => 'Identity',
					'PartyIdentifierTypeCode' => 'HCM001',
					'BusinessPartnerID' => '',
					'EmployeeID' => $emp
				),
				'WorkplaceAddressInformation' => array(
					'actionCode' => '04',
					'Address' => array(
						'actionCode' => '04',
						'Email' => array(
							'actionCode' => '04',
							'ObjectNodeSenderTechnicalID' => null,
							'URI' => $empData->email
						)
					)
				)
			)
		);

		if (isset($empData->iban))
		{
			$payment = array(
				'PaymentInformation' => array(
					'PaymentFormCode' => '05', //Bank Transfer
					'BankDetails' => array(
						'actionCode' => '04',
						'ID' => '0001', //random ID
						'BankAccountID' => $empData->accNumber,
						'BankAccountTypeCode' => '03', //Checking Account
						'BankAccountHolderName' => $empData->name . ' ' . $empData->surname,
						'BankAccountStandardID' => $empData->iban,
						'BankRoutingID' => $empData->bankNumber,
						'BankRoutingIDTypeCode' => $empData->bankCountry,
						'MainBankIndicator' => 'true',
						'ValidityPeriod' => array(
							'StartDate' => '1901-01-01',
							'EndDate' => '9999-12-31'
						),
					),
				),
			);
			if (isset($empData->bankInternalID))
			{
				$payment['PaymentInformation']['BankDetails'] = array_merge($payment['PaymentInformation']['BankDetails'], ['BankInternalID' => $empData->bankInternalID]);
			}
			$employeeData['EmployeeData'] = array_merge($employeeData['EmployeeData'], $payment);
		}

		$data = array_merge($basicHeader, $employeeData);

		return $this->_ci->ManageEmployeeInModel->MaintainBundle($data);
	}

	private function addLeavingDate($empID, $endDate, $person_id)
	{
		$manageEmpResult = $this->_ci->ManagePersonnelLeavingInModel->MaintainBundle(
			array(
				'BasicMessageHeader' => array(
					'ID' => generateUID(self::CREATE_EMP_PREFIX),
					'UUID' => generateUUID()
				),
				'PersonnelLeaving' => array(
					'actionCode' => '01',
					'ObjectNodeSenderTechnicalID' => null,
					'LeavingDate' => $endDate,
					'EmployeeID' => $empID,
					'PersonnelEventTypeCode' => 4,
					'PersonnelEventReasonCode' => 4,
					'WithoutNoticeIndicator' => true
				)
			)
		);

		if (isError($manageEmpResult)) return $manageEmpResult;

		$manageEmp = getData($manageEmpResult);

		if (isset($manageEmp->PersonnelLeaving) && isset($manageEmp->PersonnelLeaving->ChangeStateID))
		{
			return true;
		}
		else if ($this->_ci->config->item(self::SAP_EMPLOYEES_WARNINGS_ENABLED) === true)// ...otherwise store a non blocking error and continue
		{
			// If it is present a description from SAP then use it
			if (isset($manageEmp->Log) && isset($manageEmp->Log->Item))
			{
				if (!isEmptyArray($manageEmp->Log->Item))
				{
					foreach ($manageEmp->Log->Item as $item)
					{
						if (isset($item->Note)) $this->_ci->LogLibSAP->logWarningDB($item->Note . ' for user: ' . $person_id);
					}
				}
				elseif ($manageEmp->Log->Item->Note)
				{
					$this->_ci->LogLibSAP->logWarningDB($manageEmp->Log->Item->Note . ' for user: ' . $person_id);
				}
			}
			else
			{
				// Default non blocking error
				$this->_ci->LogLibSAP->logWarningDB('SAP did not add a leaving date for the user: ' . $person_id);
			}
		}
		return false;
	}

	private function rehireEmployee($empID, $beginn, $typeCode, $stunden, $oe, $person_id, $ende = null)
	{
		$array = array(
			'BasicMessageHeader' => array(
				'ID' => generateUID(self::CREATE_EMP_PREFIX),
				'UUID' => generateUUID()
			),
			'PersonnelRehire' => array(
				'actionCode' => '01',
				'ObjectNodeSenderTechnicalID' => null,
				'ChangeStateID' => null,
				'UUID' => null,
				'RehireDate' => $beginn,
				'Employee' => array(
					'ID' => $empID
				),
				'Employment' => array(
					'CountryCode' => 'AT'
				),
				'WorkAgreement' => array(
					'TypeCode' => $typeCode,
					'AdministrativeCategoryCode' => 2,
					'AgreedWorkingHoursRate' => array(
						'DecimalValue' => $stunden,
						'BaseMeasureUnitCode' => 'WEE'
					),
					'OrganisationalCentreID' => $oe,
					'JobID' => self::JOB_ID
				)
			)
		);

		if (!is_null($ende))
			$array['PersonnelRehire']['LeavingDate'] = $ende;

		$manageEmpResult = $this->_ci->ManagePersonnelRehireInModel->MaintainBundle(
			$array
		);

		if (isError($manageEmpResult)) return $manageEmpResult;

		$manageEmp = getData($manageEmpResult);
		if (isset($manageEmp->PersonnelRehire) && isset($manageEmp->PersonnelRehire->ChangeStateID))
		{
			return true;
		}
		else if ($this->_ci->config->item(self::SAP_EMPLOYEES_WARNINGS_ENABLED) === true) // ...otherwise store a non blocking error and continue with the next user
		{
			// If it is present a description from SAP then use it
			if (isset($manageEmp->Log) && isset($manageEmp->Log->Item))
			{
				if (!isEmptyArray($manageEmp->Log->Item))
				{
					foreach ($manageEmp->Log->Item as $item)
					{
						if (isset($item->Note)) $this->_ci->LogLibSAP->logWarningDB($item->Note.' for user: '.$person_id);
					}
				}
				elseif ($manageEmp->Log->Item->Note)
				{
					$this->_ci->LogLibSAP->logWarningDB($manageEmp->Log->Item->Note.' for user: '.$person_id);
				}
			}
			else
			{
				// Default non blocking error
				$this->_ci->LogLibSAP->logWarningDB('SAP did not transfer the user: '.$person_id);
			}
		}
		return false;
	}

	private function transferEmployee($empID, $transferDate, $hours, $oeID, $person_id, $secondTry = false, $jobID = self::JOB_ID)
	{
		$manageEmpResult = $this->_ci->ManagePersonnelTransferInModel->MaintainBundle(
			array(
				'BasicMessageHeader' => array(
					'ID' => generateUID(self::CREATE_EMP_PREFIX),
					'UUID' => generateUUID()
				),
				'PersonnelTransfer' => array(
					'actionCode' => '01',
					'TransferDate' => $transferDate,
					'EmployeeID' => $empID,
					'AgreedWorkingHoursRate' => array(
						'DecimalValue' => $hours,
						'BaseMeasureUnitCode' => 'WEE'
					),
					'OrganisationalCentreID' => $oeID,
					'JobID' => $jobID
				)
			)
		);

		if (isError($manageEmpResult)) return $manageEmpResult;

		$manageEmp = getData($manageEmpResult);

		if (isset($manageEmp->PersonnelTransfer) && isset($manageEmp->PersonnelTransfer->ChangeStateID))
		{
			return true;
		}
		else if ($secondTry === true)
		{
			return $this->transferEmployee($empID, $transferDate, $hours, $oeID, $person_id, false, self::JOB_ID_2);
		}
		else if ($this->_ci->config->item(self::SAP_EMPLOYEES_WARNINGS_ENABLED) === true) // ...otherwise store a non blocking error and continue with the next user
		{
			// If it is present a description from SAP then use it
			if (isset($manageEmp->Log) && isset($manageEmp->Log->Item))
			{
				if (!isEmptyArray($manageEmp->Log->Item))
				{
					foreach ($manageEmp->Log->Item as $item)
					{
						if (isset($item->Note)) $this->_ci->LogLibSAP->logWarningDB($item->Note.' for user: '.$person_id);
					}
				}
				elseif ($manageEmp->Log->Item->Note)
				{
					$this->_ci->LogLibSAP->logWarningDB($manageEmp->Log->Item->Note.' for user: '.$person_id);
				}
			}
			else
			{
				// Default non blocking error
				$this->_ci->LogLibSAP->logWarningDB('SAP did not transfer the user: '.$person_id);
			}
		}
		return false;
	}
}

