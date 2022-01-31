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
		$this->_ci->load->model('extensions/FHC-Core-SAPSF/fhcomplete/SAPOrganisationsstruktur_model', 'OrganisationsstrukturModel');
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
		$empsAllData = $this->_getAllEmpsData($diffUsers);

		if (isError($empsAllData)) return $empsAllData;
		if (!hasData($empsAllData)) return error('No data available for the given users');

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
						'AdministrativeCategoryCode' => 2,
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
					&& isset($manageEmployee->Log->Item)) {
					if (!isEmptyArray($manageEmployee->Log->Item)) {
						foreach ($manageEmployee->Log->Item as $item) {
							if (isset($item->Note)) $this->_ci->LogLibSAP->logWarningDB($item->Note . ' for employee: ' . $empData->person_id);
						}
					} elseif ($manageEmployee->Log->Item->Note) {
						$this->_ci->LogLibSAP->logWarningDB($manageEmployee->Log->Item->Note . ' for employee: ' . $empData->person_id);
					}
				} else {
					// Default non blocking error
					$this->_ci->LogLibSAP->logWarningDB('SAP did not return the InterlID for employee: ' . $empData->person_id);
				}
				continue;
			}
		}
		return success('Users data created successfully');
	}

	public function update($emps)
	{
		if (isEmptyArray($emps)) return success('No emps to be updated');

		// Remove the already created users
		$diffEmps = $this->_removeNotCreatedEmps($emps);

		if (isError($diffEmps)) return $diffEmps;
		if (!hasData($diffEmps)) return success('No emps to be created after diff');

		// Retrieves all users data
		$empsAllData = $this->_getAllEmpsData($diffEmps);

		if (isError($empsAllData)) return $empsAllData;
		if (!hasData($empsAllData)) return error('No data available for the given emps');

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
				$data = array(
					'BasicMessageHeader' => array(
						'ID' => generateUID(self::UPDATE_EMP_PREFIX),
						'UUID' => generateUUID()
					),
					'EmployeeData' => array(
						'ObjectNodeSenderTechnicalID' => null,
						'ChangeStateID' => null,
						'UUID' => generateUUID(),
						'actionCode' => '04',
						'addressInformationListCompleteTransmissionIndicator' => true,
						'Identification' => array(
							'actionCode' => '06',
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
						'PaymentInformation' => array(
							'actionCode' => '04',
							'ObjectNodeSenderTechnicalID' => null,
							'PaymentFormCode' => '05',
							'BankDetails' => array(
								'ObjectNodeSenderTechnicalID' => null,
								'ID' => '0000', //random ID
								'BankAccountID' => $empData->accNumber,
								'BankAccountTypeCode' => '03', //Checking Account
								'BankAccountHolderName' => $empData->name . ' ' . $empData->surname,
								'BankAccountStandardID' => $empData->iban,
								'BankRoutingID' => $empData->bankNumber,
								'BankRoutingIDTypeCode' => $empData->bankCountry,
								'MainBankIndicator' => 'true',
							)
						)

					)
				);

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
					continue;
				}
			}
		}

		return success('Users data updated successfully');
	}

	public function updateEmployeeWorkAgreement($emps)
	{
		if (isEmptyArray($emps)) return success('No emps to be updated');

		// Löscht alle nicht erstellten Emps
		$diffEmps = $this->_removeNotCreatedEmps($emps);

		if (isError($diffEmps)) return $diffEmps;
		if (!hasData($diffEmps)) return success('No emps to be created after diff');

		// Holt sich alle Daten des Emps
		$empsAllData = $this->_getAllEmpsData($diffEmps);

		if (isError($empsAllData)) return $empsAllData;
		if (!hasData($empsAllData)) return error('No data available for the given emps');

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
								$oeResult = $this->_ci->OrganisationsstrukturModel->loadWhere(array('oe_kurzbz_sap' => $position->OrganisationalCenterDetails->OrganisationalCenterID));

								if(hasData($oeResult))
								{
									$sapEmpType['organisation'][$organisationStart] = $position;
									break;
								}

								/*wenn die OE nicht in der Sync Tabelle eingetragen ist, wird abgebrochen*/
								if ($exists === false)
								{
									return error("Eine Organisation ist im SAPByD richtig hinterlegt");
								}
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
				$oldBis = isset($bisResult[$bisKey - 1]) ? $bisResult[$bisKey - 1] : false;
				$newBis = isset($bisResult[$bisKey + 1]) ? $bisResult[$bisKey + 1] : false;

				$functionResult = $this->_ci->BenutzerfunktionModel->getBenutzerFunktionByUid($empData->uid, 'kstzuordnung', $currentBis->beginn, $currentBis->ende);

				if (!hasData($functionResult))
					return error("Fehler beim laden der Benutzerfunktionen");

				$functionResult = getData($functionResult);
				/*Prüfen ob die Bisverwendung keine Fixanstellung ist */
				if ($currentBis->ba1code !== '103')
				{
					if ($oldBis)
					{
						/*
						Prüfen zuerst welcher Eintrag später beginnt, ob es die Bisverwendung oder die Benutzerfunktion ist und übernehmen das Startdatum von dem jeweiligen
						Da es in SapByD keine Unterscheidung gibt, ob es eine Bisverwendung oder Benutzerfunktion ist
						*/
						if ($functionResult[0]->datum_von >= $oldBis->beginn)
							$datum = $functionResult[0]->datum_von;
						else
							$datum = $oldBis->beginn;

						$sapEndDate = $sapEmpType['functions'][$datum]->ValidityPeriod->EndDate;

						$leavingDate = $oldBis->ende;

						if (is_null($leavingDate))
						{
							$previousDay = strtotime("-1 day", strtotime($currentBis->beginn));
							$leavingDate = date("Y-m-d", $previousDay);
						}

						if ($sapEndDate === '9999-12-31' && $leavingDate != $sapEndDate)
						{
							$updated = $this->addLeavingDate($sapID, $leavingDate, $empData->person_id);

							if (!$updated)
								break;
						}
					}
					continue;
				}

				/*Prüfen ob dis Bisverwendung bereits mit dem Startdatum in SAPByD exestiert und ob der ba1code 103 (Fixanstellung) ist*/
				if (isset($startDates[$currentBis->beginn]) && $currentBis->ba1code === '103')
				{
					/*Gehen dann alle Funktionen von der Person durch*/
					foreach ($functionResult as $functionKey => $currentFunction)
					{
						$oeResult = $this->_ci->OrganisationsstrukturModel->loadWhere(array('oe_kurzbz' => $currentFunction->oe_kurzbz));

						if (!hasData($oeResult))
							return error("Keine Organisation in SAP gefunden");

						$currentOE = getData($oeResult)[0]->oe_kurzbz_sap;

						/*Falls eine Organisatorische Zuordnung im SAPByD mit dem Datum der Benutzerfunktion bereits besteht*/
						if (isset($sapEmpType['functions'][$currentFunction->datum_von]))
						{
							/*Prüfen ob die OE ID in SapByD eingetragen ist
							Kam bei einem Test als Null zurück, ist eigentlich sonst immer eingetragen*/
							if (isset($sapEmpType['organisation'][$currentFunction->datum_von]->OrganisationalCenterDetails))
								$sapOE = $sapEmpType['organisation'][$currentFunction->datum_von]->OrganisationalCenterDetails->OrganisationalCenterID;

							/*Ist die OE aus der Benutzerfunktion nicht die gleiche wie die, die in SAPByD eingetragen ist findet ein Transfer zur der richtigen OE statt*/
							if (isset($sapOE) && $currentOE !== $sapOE)
							{
								$updated = $this->transferEmployee($sapID, $currentFunction->datum_von, $currentBis->vertragsstunden, $currentOE, $empData->person_id);

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

							$oldOeResult = $this->_ci->OrganisationsstrukturModel->loadWhere(array('oe_kurzbz' => $oldFunction->oe_kurzbz));

							if (!hasData($oldOeResult))
								return error("Keine Organisation in SAP gefunden");

							$oldOE = getData($oldOeResult)[0]->oe_kurzbz_sap;

							/*Prüfen ob die alte Funktion bereits ein Endedatum eingetragen hat, und nehmen das als Kündigungsdatum*/
							if (isset($oldFunction->datum_bis) && !is_null($oldFunction->datum_bis))
							{
								$leavingDate = $oldFunction->datum_bis;
							}
							else
							{
								/*ansonsten ziehen wir von dem Startdatum ein Tag ab und nehmen das als Kündigungsdatum*/
								$previousDay = strtotime("-1 day", strtotime($currentFunction->datum_von));
								$leavingDate = date("Y-m-d", $previousDay);
							}

							/*Prüfen ob in der aktuellen Benutzerfunktion bereits ein Enddatum eingetragen ist
							Falls nicht ist es ein unbefristeter Vertrag mit keinem Kündigungsdatum*/
							if (is_null($currentFunction->datum_bis))
							{
								$typeCode = self::SAP_TYPE_PERMANENT;
								$rehireLeaving = null;
								$newEndDate = '9999-12-31';
							}
							else
							{
								/*ansonsten ist es ein befristeter Vertrag mit einem Enddatum*/
								$typeCode = self::SAP_TYPE_TEMPORARY;
								$rehireLeaving = $currentFunction->datum_bis;
								$newEndDate = $currentFunction->datum_bis;
							}

							//Holen uns das EndDate von SapByD, ist immer ein Datum gesetzt
							//Falls Austritt Datum "unbegrenzt" ist, ist es 9999-12-31
							$oldFunctionEndDate = $sapEmpType['functions'][$oldFunction->datum_von]->ValidityPeriod->EndDate;

							/*Prüfen ob die alte OE der aktuellen entspricht
							Wenn ja muss die Person zuerst gekündigt werden, falls dass nicht bereits der Fall ist und neueingestellt werden
							Ansonsten findet nur ein Transfer statt*/
							if ($oldOE !== $currentOE)
							{
								$updated = $this->transferEmployee($sapID, $currentFunction->datum_von, $currentBis->vertragsstunden, $currentOE, $empData->person_id);

								if (!$updated)
									break 2;
								$sapEmpType['functions'][$currentFunction->datum_von] = (object) ['ValidityPeriod' => (object) ['EndDate' => $newEndDate]];
							}
							else
							{
								if ($oldFunctionEndDate === "9999-12-31" && $leavingDate !== $oldFunctionEndDate)
								{
									$updated = $this->addLeavingDate($sapID, $leavingDate, $empData->person_id);

									if (!$updated)
										break 2;
								}

								$updated = $this->rehireEmployee($sapID, $currentFunction->datum_von, $typeCode, $currentBis->vertragsstunden, $currentOE, $empData->person_id, $rehireLeaving);

								if (!$updated)
									break 2;
							}
						}
					}
				}
				/* Falls die Bisverwendung noch nicht im SapByD exestiert und es sich um eine Fixanstellung handelt*/
				else if ($currentBis->ba1code === '103')
				{
					/*Falls keine alte Bisverwendung besteht, machen wir weiter
					Da die erste Bisverwendung eigentlich immer in SapByD eingetragen sein muss*/
					if ($oldBis === false)
						continue;

					/*Holen uns die letzte Benutzerfunktion von der letzten Bisverwendung um uns dann die letzte OE zu holen*/
					$oldFunctionResult = $this->_ci->BenutzerfunktionModel->getBenutzerFunktionByUid($empData->uid, 'kstzuordnung', $oldBis->beginn, $oldBis->ende);

					if (!hasData($oldFunctionResult))
						return error("Fehler beim laden der Benutzerfunktionen");

					$oldFunction = array_reverse(getData($oldFunctionResult))[0];
					$oldOeResult = $this->_ci->OrganisationsstrukturModel->loadWhere(array('oe_kurzbz' => $oldFunction->oe_kurzbz));

					/*Gehen die Benutzerfunktionen der aktuellen Bisverwendung durch*/
					foreach ($functionResult as $functionKey => $currentFunction)
					{
						if ($oldFunction === false)
							continue;

						//Beim ersten Durchlauf einer neuen Bisverwendung, nehmen wir die älteste Benutzerfunktion der alten Bisverwendung
						//Ansonsten nehmen wir ältere Benutzerfunktion
						if ($functionKey > 0)
						{
							$oldFunction = isset($functionResult[$functionKey - 1]) ? $functionResult[$functionKey - 1] : false;
							$oldOeResult = $this->_ci->OrganisationsstrukturModel->loadWhere(array('oe_kurzbz' => $oldFunction->oe_kurzbz));
						}

						//Hilft uns wenn mehrere Benutzerfunktionene für die Zukunft gleichzeitig eingetragen werden
						//Damit nicht immer ein Enddatum eingetragen wird
						$newFunction = isset($functionResult[$functionKey + 1]) ? $functionResult[$functionKey + 1] : false;

						if (!hasData($oldOeResult))
							return error("Keine Organisation in SAP gefunden");

						$oldOE = getData($oldOeResult)[0]->oe_kurzbz_sap;

						/*Holen uns die aktuelle OE Zuordnung*/
						$oeResult = $this->_ci->OrganisationsstrukturModel->loadWhere(array('oe_kurzbz' => $currentFunction->oe_kurzbz));

						if (!hasData($oeResult))
							return error("Keine Organisation in SAP gefunden");

						$currentOE = getData($oeResult)[0]->oe_kurzbz_sap;

						/*Prüfen ob in der alten Benutzerfunktion bereits ein Enddatum eingetragen ist
						Falls ja wird das als Kündigungsdatum genommen*/
						if (is_null($currentFunction->datum_bis))
						{
							$typeCode = self::SAP_TYPE_PERMANENT;
							$rehireLeaving = null;
							$newEndDate = '9999-12-31';
						}
						else
						{
							$typeCode = self::SAP_TYPE_TEMPORARY;
							$rehireLeaving = $currentFunction->datum_bis;
							$newEndDate = $currentFunction->datum_bis;
						}

						//Holen uns das Enddatum der alten Benutzerfunktion

						$oldFunctionEndDate = $sapEmpType['functions'][$oldFunction->datum_von]->ValidityPeriod->EndDate;

						//Prüfen hier ab ob es noch eine neue Benutzerfunktion gibt, falls ja setzen wir Kündigungsdatum
						if ($newFunction !== false)
						{
							$rehireLeaving = null;
							$typeCode = self::SAP_TYPE_PERMANENT;
						}

						//wenn die OE nicht die gleiche ist, wird der Mitarbeiter transferiert
						if ($oldOE !== $currentOE)
						{
							//Führt ein Rehire nur dann durch, wenn es sich um die Erste neue Benutzerfunktion in der neuen Bisverwendung handelt,
							//da es sonst immer ein Transfer sein muss, da es sich nicht um die gleichen OEs handelt
							if ($oldFunctionEndDate !== "9999-12-31" && $functionKey === 0)
							{
								$updated = $this->rehireEmployee($sapID, $currentFunction->datum_von, $typeCode, $currentBis->vertragsstunden, $currentOE, $empData->person_id, $rehireLeaving);

								if (!$updated)
									break 2;
							}
							else
							{
								$updated = $this->transferEmployee($sapID, $currentFunction->datum_von, $currentBis->vertragsstunden, $currentOE, $empData->person_id);

								if (!$updated)
									break 2;
							}
						}
						else
						{
							//Wenn die alte OE die gleiche wie die jetzige ist, muss zuerst die Person gekündigt werden und dann neu eingestellt werden
							//Da sonst SAPByD eine Rückmeldung gibt, dass die job ID oder OE ID eine andere sein muss
							if ($oldFunctionEndDate === "9999-12-31" && $oldBis->ende !== $oldFunctionEndDate)
							{
								$updated = $this->addLeavingDate($sapID, $oldBis->ende, $empData->person_id);

								if (!$updated)
									break 2;
							}

							$updated = $this->rehireEmployee($sapID, $currentBis->beginn, $typeCode, $currentBis->vertragsstunden, $currentOE, $empData->person_id, $rehireLeaving);

							if (!$updated)
								break 2;
						}

						//Erweitern das Array, mit den neuen Informationen
						$sapEmpType['functions'][$currentFunction->datum_von] = (object) ['ValidityPeriod' => (object) ['EndDate' => $newEndDate]];
					}
				}

				//Wenn keine weitere Bisverwendung mehr vorhanden ist und ein Enddatum eingetragen ist wird ein Kündigungsdatum eingetragen
				if ($newBis === false && $currentBis->ba1code === '103' && !is_null($currentBis->ende))
				{
					/*Holen uns nochmal alle Funktionen, von der jetzigen Bisverwendung*/
					$functionResult = $this->_ci->BenutzerfunktionModel->getBenutzerFunktionByUid($empData->uid, 'kstzuordnung', $currentBis->beginn, $currentBis->ende);

					if (!hasData($functionResult))
						return error("Fehler beim laden der Benutzerfunktionen");

					$functionResult = getData($functionResult);

					$functionResult = array_reverse($functionResult);

					/*Nehmen das spätere Startdatum von der Bisverwendung bzw Benutzerfunktion*/
					if ($functionResult[0]->datum_von >= $oldBis->beginn)
						$datum = $functionResult[0]->datum_von;
					else
						$datum = $currentBis->beginn;

					$sapEndDate = $sapEmpType['functions'][$datum]->ValidityPeriod->EndDate;

					$leavingDate = $currentBis->ende;

					if (!is_null($leavingDate) && $sapEndDate === '9999-12-31' && $leavingDate != $sapEndDate)
					{
						$updated = $this->addLeavingDate($sapID, $leavingDate, $empData->person_id);

						if (isError($updated))
							break;
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
				//ist ein Error damit man beim Manuellen Syncen sieht, dass es fehlschlägt
				//ansonsten wäre es ein Continue;
				return error();
			}
		}
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
			return $this->create($emp);
		else
		{
			$update = $this->update($emp);
			if (!isError($update))
				return $this->updateEmployeeWorkAgreement($emp);
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
			if (!isset($emp->EmploymentData) || !isset($emp->EmploymentData->WorkAgreementData))
				continue;

			$additionalClauses['emp'] = $emp->EmployeeID->_;
			$additionalClauses['workingAgreement'] = [];

			$workAgreement = $emp->EmploymentData->WorkAgreementData;
			if (isset($workAgreement->AdditionalClauses))
			{
				if (is_array($workAgreement->AdditionalClauses))
				{
					foreach ($workAgreement->AdditionalClauses as $additionalClause)
					{
						$startDate = (isset($additionalClause->ValidityPeriod->StartDate)) ? $additionalClause->ValidityPeriod->StartDate : '';
						$decimal = (isset($additionalClause->AgreedWorkingTimeRate->DecimalValue)) ? $additionalClause->AgreedWorkingTimeRate->DecimalValue : '';
						$category = (isset($additionalClause->WorkAgreementAdministrativeCategoryCode->_)) ? $additionalClause->WorkAgreementAdministrativeCategoryCode->_ : '';
						array_push($additionalClauses['workingAgreement'], array('startDate' => $startDate, 'timeRate' => $decimal, 'category' => $category));
					}
				}
				else
				{
					$startDate = (isset($workAgreement->AdditionalClauses->ValidityPeriod->StartDate)) ? $workAgreement->AdditionalClauses->ValidityPeriod->StartDate : '';
					$decimal = (isset($workAgreement->AdditionalClauses->AgreedWorkingTimeRate->DecimalValue)) ? $workAgreement->AdditionalClauses->AgreedWorkingTimeRate->DecimalValue : '';
					$category = (isset($workAgreement->AdditionalClauses->WorkAgreementAdministrativeCategoryCode->_)) ? $workAgreement->AdditionalClauses->WorkAgreementAdministrativeCategoryCode->_: '';
					array_push($additionalClauses['workingAgreement'], array('startDate' => $startDate, 'timeRate' => $decimal, 'category' => $category));
				}
			}

			array_push($data, $additionalClauses);
		}

		$out = fopen("test.csv", 'w');
		fputcsv($out, array('Mitarbeiter', 'Startdatum', 'Stunden', 'Verwaltungskategorie'));

		foreach ($data as $additionalClause){
			foreach ($additionalClause['workingAgreement'] as $workAgreement){
				$line = [$additionalClause['emp'], date_format(date_create($workAgreement['startDate']), "m/d/Y"), $workAgreement['timeRate'], $workAgreement['category']];
				fputcsv($out, $line);
			}
		}
		fclose($out);
	}

	public function getAllEmps()
	{
		$objID = null;
		$emps = [];

		do {
			$empsData = $this->_ci->QueryEmployeeInModel->findByIdentification(
				array(
					'PROCESSING_CONDITIONS' => array(
						'QueryHitsMaximumNumberValue' => 10,
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
	private function _getAllEmpsData($emps)
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

			if (hasData($bankResult))
			{
				$bankData = checkIBAN(getData($bankResult)[0]->iban);
				if (!$bankData)
					return error('No bank data available for the given user');
				$empAllData->iban = $bankData['iban'];
				$empAllData->accNumber = $bankData['accNumber'];
				$empAllData->bankNumber = $bankData['bankNumber'];
				$empAllData->bankCountry = $bankData['country'];
			}

			// -------------------------------------------------------------------------------------------
			// Bisverwendung

			$this->_ci->load->model('codex/bisverwendung_model', 'BisverwendungModel');
			$bisResult = $this->_ci->BisverwendungModel->getLast($empPersonalData->uid, false);
			if (isError($bisResult)) return $bisResult;
			if (hasData($bisResult))
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

			$this->_ci->load->model('person/benutzerfunktion_model', 'BenutzerfunktionModel');

			$this->_ci->BenutzerfunktionModel->addJoin('sync.tbl_sap_organisationsstruktur', 'public.tbl_benutzerfunktion.oe_kurzbz = sync.tbl_sap_organisationsstruktur.oe_kurzbz');

			$this->_ci->BenutzerfunktionModel->addOrder('datum_von', 'DESC');
			$this->_ci->BenutzerfunktionModel->addLimit(1);

			$kstZuordnungen = $this->_ci->BenutzerfunktionModel->loadWhere(
				array(
					'funktion_kurzbz' => 'kstzuordnung',
					'uid' => $empPersonalData->uid
				)
			);

			if (isError($kstZuordnungen)) return $kstZuordnungen;

			if (hasData($kstZuordnungen))
			{
				$empAllData->kstZuordnungen = getData($kstZuordnungen)[0];
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
		return $this->_ci->ManageEmployeeInModel->MaintainBundle(
			array(
				'BasicMessageHeader' => array(
					'ID' => generateUID(self::CREATE_EMP_PREFIX),
					'UUID' => null
				),
				'EmployeeData' => array(
					'workplaceAddressInformationListCompleteTransmissionIndicator' => true,
					'actionCode' => '04',
					'ObjectNodeSenderTechnicalID' => null,
					'ChangeStateID' => null,
					'UUID' => null,
					'Identification' => array(
						'actionCode' => '04',
						'EmployeeID' => $emp
					),
					'PaymentInformation' => array(
						'PaymentFormCode' => '05', //Bank Transfer
						'BankDetails' => array(
							'actionCode' => '04',
							'ID' => '0000', //random ID
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
			)
		);
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

	private function transferEmployee($empID, $transferDate, $hours, $oeID, $person_id)
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
					'JobID' => self::JOB_ID
				)
			)
		);

		if (isError($manageEmpResult)) return $manageEmpResult;

		$manageEmp = getData($manageEmpResult);

		if (isset($manageEmp->PersonnelTransfer) && isset($manageEmp->PersonnelTransfer->ChangeStateID))
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
}

