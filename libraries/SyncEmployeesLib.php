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
	const SAP_CHECK_EMPLOYEE_DV = 'SAPEmployeeCheckDV';

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
	//const SAP_TYPE_TEMPORARY = 2;

	const JOB_ID = 'ECINT_DUMMY_JOB';
	const JOB_ID_2 = 'ECINT_DUMMY_JOB_2';
	const FHC_CONTRACT_TYPES = 'fhc_contract_types';
	const AFTER_END = 'sap_sync_employees_x_days_after_end';
	const BEFORE_START = 'sap_sync_employees_x_days_before_start';
	const SYNC_START = 'sap_sync_start_date';
	
	const ERROR_MSG = 'Please check the logs';

	private $_ci; // Code igniter instance

	private $testlauf = false; // Testlauf

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
		$this->_ci->load->model('extensions/FHC-Core-SAP/SOAP/QueryEmployeeBankDetailsIn_model', 'QueryEmployeeBankDetailsInModel');
		$this->_ci->load->model('person/benutzerfunktion_model', 'BenutzerfunktionModel');
		$this->_ci->load->model('system/MessageToken_model', 'MessageTokenModel');
		
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
		$empsAllData = $this->_getAllEmpsData($diffUsers, true);

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
					'LeavingDate' => null,
					'Employee' => array(
						'GivenName' => $empData->name,
						'FamilyName' => $empData->surname,
						'GenderCode' => $empData->gender,
						'BirthDate' => $empData->bday
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
						'OrganisationalCentreID' => $empData->oe_kurzbz_sap,
						'JobID' => self::JOB_ID
					)

				)
			);

			if (isset($empData->country))
			{
				$address = array(
					'CountryCode' => $empData->country,
					'CityName' => $empData->city,
					'StreetPostalCode' => $empData->zip,
					'StreetName' => $empData->street
				);

				$data['PersonnelHiring']['Employee']['PrivateAddress'] = $address;
			}

			// Then create it!
			$manageEmployeeResult = $this->_ci->ManagePersonnelHiringInModel->MaintainBundle($data);

			// If an error occurred then return it
			if (isError($manageEmployeeResult)) return $manageEmployeeResult;

			// SAP data
			$manageEmployee = getData($manageEmployeeResult);

			// If data structure is ok...
			if (isset($manageEmployee->PersonnelHiring) && isset($manageEmployee->PersonnelHiring->UUID))
			{
				if (isset($manageEmployee->PersonnelHiring->EmployeeID))
				{
					$employeeID = preg_replace('/^0*/', '', $manageEmployee->PersonnelHiring->EmployeeID->_);
				}
				else
				{
					// Get the employee after creation
					$sapEmployeeResult = $this->getEmployeeAfterCreation($empData->name, $empData->surname, date('Y-m-d'));
					
					if (isError($sapEmployeeResult)) return $sapEmployeeResult;
					
					$sapEmployee = getData($sapEmployeeResult);
					$employeeID = $sapEmployee->BasicData->EmployeeID->_;
				}

				// Add payment information for the employee
				$this->addPaymentInformation($employeeID, $empData, $empData->person_id);
				// Store in database the couple person_id sap_user_id
				$insert = $this->_ci->SAPMitarbeiterModel->insert(
					array(
						'mitarbeiter_uid' => $empData->uid,
						'sap_eeid' => $employeeID,
						'last_update' => 'NOW()',
						'last_update_workagreement' => 'NOW()'
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

				if (!is_cli())
					return error(self::ERROR_MSG);
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

				if (isset($empData->country))
				{
					$address = array(
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
					);
					$employeeData['EmployeeData']['AddressInformation'] = $address;
				}

				if (isset($empData->nummer))
				{
					$telephone = array(
						'actionCode' => '04',
						'ObjectNodeSenderTechnicalID' => null,
						'TelephoneFormattedNumberDescription' => $empData->nummer
					);
					$employeeData['EmployeeData']['WorkplaceAddressInformation']['Address']['Telephone'] = $telephone;
				}

				$sapBankData = $this->getBankDetails(getData($sapIdResult)[0]->sap_eeid);
				$bankDetailsOfEmployee = null;

				if (hasData($sapBankData) && isset(getData($sapBankData)->BankDetailsOfEmployee))
				{
					$bankDetailsOfEmployee = getData($sapBankData)->BankDetailsOfEmployee;
				}

				$sapBanksIban = [];
				$newBankKeyId = '0001';

				if (isset($bankDetailsOfEmployee->BankDetailsOfEmployee))
				{
					$sapBanksData = $bankDetailsOfEmployee->BankDetailsOfEmployee;
					if (!is_array($sapBanksData))
						$sapBanksData = [$bankDetailsOfEmployee->BankDetailsOfEmployee];

					$sapBanksIban = array_column($sapBanksData, 'BankAccountStandardID');

					$maxBankKeyId = max(array_map('intval', array_column($sapBanksData, 'KeyID')));
					$newBankKeyId = str_pad($maxBankKeyId + 1, 4, 0, STR_PAD_LEFT);
				}

				if (isset($empData->iban) && !(in_array($empData->iban, $sapBanksIban)))
				{
					$payment = array(
						'PaymentInformation' => array(
							'actionCode' => '04',
							'ObjectNodeSenderTechnicalID' => null,
							'PaymentFormCode' => '05', //Bank transfer
							'BankDetails' => array(
								'actionCode' => '04',
								'ObjectNodeSenderTechnicalID' => null,
								'ID' => $newBankKeyId, //random ID
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
					if (!is_cli())
						return error(self::ERROR_MSG);
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
		if (!hasData($diffEmps)) return success('No emps to be updated after diff');

		// Holt sich alle Daten des Emps
		$empsAllData = $this->_getAllEmpsDataWorkAgreement($diffEmps);

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
				return error('Emp not found in SAP: ' . $empData->uid);

			$sapEmpData = $sapEmpData->EmployeeData->EmploymentData;

			$sapEmpData = $this->checkIfObject($sapEmpData);

			$startDates = array();
			$sapEmpType = new stdClass();
			$updated = null;
			$testReturn = array();
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
					foreach($benutzerFunctionsSAP as $benutzerFunctionSAP)
					{
						$sapEmpType->functions[$benutzerFunctionSAP->ValidityPeriod->StartDate] = new stdClass();
						$sapEmpType->functions[$benutzerFunctionSAP->ValidityPeriod->StartDate]->ValidityPeriod = isset($benutzerFunctionSAP->ValidityPeriod) ? $benutzerFunctionSAP->ValidityPeriod :'';
						$sapEmpType->functions[$benutzerFunctionSAP->ValidityPeriod->StartDate]->AgreedWorkingTimeRate = isset($benutzerFunctionSAP->AgreedWorkingTimeRate->DecimalValue) ? number_format($benutzerFunctionSAP->AgreedWorkingTimeRate->DecimalValue, 2) : '';
					}

					$benutzerOrganisationalsSAP = $this->checkIfObject($workAgreement->OrganisationalAssignment);

					/*speichern uns die Zuordnungen aus SAPByD*/
					foreach ($benutzerOrganisationalsSAP as $benutzerOrganisationalSAP)
					{
						/*es kommt vor, dass bei vorhandenen Benutzern mehrere Zurordnungen bestehen in der selben Zeit
						wir prüfen ob eine OE in der Sync Tabelle besteht
						falls ja, übernehmen wir nur die richtige Zuordnung
						falls keine in der Tabelle vorhanden ist brechen wir ab*/
						$positions = $this->checkIfObject($benutzerOrganisationalSAP->PositionAssignment);

						foreach ($positions as $position)
						{
							$organisationStart = $position->ValidityPeriod->StartDate;
							
							if (isset($position->OrganisationalCenterDetails->OrganisationalCenterID))
							{
								$sapEmpType->organisation[$organisationStart] = new stdClass();
								$sapEmpType->organisation[$organisationStart]->sap_oe = $position->OrganisationalCenterDetails->OrganisationalCenterID;
							}
							else if (isset($position->OrganisationalCenterDetails) && is_array($position->OrganisationalCenterDetails))
							{
								$sapEmpType->organisation[$organisationStart] = new stdClass();
								$sapEmpType->organisation[$organisationStart]->sap_oe = array();
								
								foreach ($position->OrganisationalCenterDetails as $orgCenterDetails)
								{
									$sapEmpType->organisation[$organisationStart]->sap_oe[] = $orgCenterDetails->OrganisationalCenterID;
								}
								sort ($sapEmpType->organisation[$organisationStart]->sap_oe);
							}
						}
					}
				}
			}
			/*holen uns das erste eingetragene Datum aus SAPByD
			damit wir uns die DV's ab dem Zeitpunkt holen*/
			ksort($startDates);
			$firstDateSap = array_keys($startDates)[0];

			$dienstverhaeltnisse = $dbModel->execReadOnlyQuery('
				SELECT dienstverhaeltnis_id AS id, von, bis, oe_kurzbz
				FROM hr.tbl_dienstverhaeltnis
				WHERE mitarbeiter_uid = ?
					AND (bis >= ? OR bis IS NULL)
					AND vertragsart_kurzbz IN ?
				ORDER BY von, bis
			', array($empData->uid, $firstDateSap, $this->_ci->config->item(self::FHC_CONTRACT_TYPES)));
			
			if (!hasData($dienstverhaeltnisse))
			{
				$this->_ci->LogLibSAP->logWarningDB('Kein Dienstverhaeltnis vorhanden: '. $empData->person_id);
				if (!is_cli())
					return error(self::ERROR_MSG);
				else
					continue;
			}

			$dienstverhaeltnisse = getData($dienstverhaeltnisse);

			$allDVEmpData = array();
			$all_bestandteile = array();
			$needSync = new stdClass();

			foreach ($dienstverhaeltnisse as $dv_key => $dienstverhaeltnis)
			{
				$bestandteile = $this->_getBestandteile($dienstverhaeltnis, $empData);
				
				foreach ($bestandteile as $bestandteil)
				{
					if ($bestandteil->vertragsbestandteiltyp_kurzbz === 'funktion')
					{
						$this->_getStunden($bestandteil, $bestandteile);
					}
					
					if ($bestandteil->vertragsbestandteiltyp_kurzbz === 'stunden')
					{
						$this->_getKostenstelle($bestandteil, $bestandteile);
					}
					
					if ((is_null($bestandteil->wochenstunden) || is_null($bestandteil->oe_kurzbz) || is_null($bestandteil->von)) &&
						$bestandteil->von >= $this->_ci->config->item(self::SYNC_START)
						)
					{
						if (is_null($bestandteil->wochenstunden))
							$this->_ci->LogLibSAP->logWarningDB('Keine Wochenstunden vorhanden: '. $empData->person_id . ' Vertragsbestandteil: ' . $bestandteil->vertragsbestandteil_id);
						else if (is_null($bestandteil->oe_kurzbz))
							$this->_ci->LogLibSAP->logWarningDB('Keine OE zugeordnet: '. $empData->person_id . ' Vertragsbestandteil: ' . $bestandteil->vertragsbestandteil_id);
						if (!is_cli())
							return error(self::ERROR_MSG);
						else
							continue 3;
					}
					
					if ($bestandteil->von >= $this->_ci->config->item(self::SYNC_START))
					{
						$kostenstelle_sap = $this->_getKostenstelleSap($bestandteil, $empData);

						if ($kostenstelle_sap === false)
						{
							if (!is_cli())
								return error(self::ERROR_MSG);
							else
								continue 2;
						}
					}

					$all_bestandteile[] = $bestandteil;
				}
			}

			foreach ($all_bestandteile as $key => $bestandteil)
			{
				if ($bestandteil->von < $this->_ci->config->item(self::SYNC_START))
					continue;
				
				if (!isset($needSync->funktion))
				{
					$needSync->funktion[$bestandteil->von] = $bestandteil;
				}
				else if (!isset($needSync->funktion[$bestandteil->von]))
				{
					if ($bestandteil->wochenstunden !== $all_bestandteile[$key - 1]->wochenstunden ||
						$bestandteil->oe_kurzbz !== $all_bestandteile[$key - 1]->oe_kurzbz)
					{
						$needSync->funktion[$bestandteil->von] = $bestandteil;
					}
				}
				else
				{
					if ($bestandteil->bis > $needSync->funktion[$bestandteil->von]->bis)
						$needSync->funktion[$bestandteil->von]->bis = $bestandteil->bis;
				}
				
				$allDVEmpData = $needSync->funktion;
			}
			
			$lastEndDate = null;
			if (!isEmptyArray($allDVEmpData))
				$lastEndDate = end($allDVEmpData)->bis;

			if (!is_null($lastEndDate))
			{
				krsort($allDVEmpData);
				$filtered = null;
				foreach ($allDVEmpData as $sync)
				{
					if ($sync->bis > $lastEndDate || is_null($sync->bis))
					{
						$filtered = $sync;
						break;
					}
				}
				
				if (!is_null($filtered))
				{
					$bestandteil = new stdClass();
					
					$new_von = new DateTime($lastEndDate);
					$new_von->modify('+1 day');
					$bestandteil->von = $new_von->format('Y-m-d');
					$bestandteil->bis = $filtered->bis;
					$bestandteil->wochenstunden = null;
					$bestandteil->oe_kurzbz = null;
					$bestandteil->oe_kurzbz_sap = null;
					$bestandteil->dv_oe_kurzbz = $filtered->dv_oe_kurzbz;

					$this->_getStunden($bestandteil, $all_bestandteile);
					$this->_getKostenstelle($bestandteil, $all_bestandteile);
					$this->_getKostenstelleSap($bestandteil, $empData);

					$allDVEmpData[$bestandteil->von] = $bestandteil;
				}
			}
			
			ksort($allDVEmpData);
			
			foreach ($allDVEmpData as $dvKey => $dbDVEmpData)
			{
				if (isset($sapEmpType->functions[$dbDVEmpData->von]))
				{
					$sapOE = null;
					if (isset($sapEmpType->organisation[$dbDVEmpData->von]->sap_oe))
					{
						if (is_array($sapEmpType->organisation[$dbDVEmpData->von]->sap_oe))
						{
							$sapOE = array_diff($sapEmpType->organisation[$dbDVEmpData->von]->sap_oe, array($dbDVEmpData->oe_kurzbz_sap));
							$sapOE = reset($sapOE);
						}
						else
							$sapOE = $sapEmpType->organisation[$dbDVEmpData->von]->sap_oe;
					}
					
					if (!is_null($sapOE) && $dbDVEmpData->oe_kurzbz_sap !== $sapOE ||
						($sapEmpType->functions[$dbDVEmpData->von]->AgreedWorkingTimeRate !== $dbDVEmpData->wochenstunden))
					{
						$updated = $this->transferEmployee($sapID, $dbDVEmpData->von, $dbDVEmpData->wochenstunden, $dbDVEmpData->oe_kurzbz_sap, $empData->person_id, true);
						if ($this->testlauf)
						{
							$testReturn[] = $updated;
						}
						else if (!$updated)
						{
							if (!is_cli())
								return error(self::ERROR_MSG);
							else
								continue 2;
						}
						
					}
				}
				else
				{
					krsort($sapEmpType->functions);
					$lastSAPFunctionDate = array_keys($sapEmpType->functions)[0];
					
					$sapEndDate = $sapEmpType->functions[$lastSAPFunctionDate]->ValidityPeriod->EndDate;
					
					if ($sapEndDate !== '9999-12-31')
					{
						if ($dbDVEmpData->von > $sapEndDate)
						{
							$updated = $this->rehireEmployee($sapID, $dbDVEmpData->von, self::SAP_TYPE_PERMANENT, $dbDVEmpData->wochenstunden, $dbDVEmpData->oe_kurzbz_sap, $empData->person_id);

							if ($this->testlauf)
							{
								$testReturn[] = $updated;
							}
							else if (!$updated)
							{
								if (!is_cli())
									return error(self::ERROR_MSG);
								else
									continue 2;
							}
						}
						else
						{
							$this->_ci->LogLibSAP->logWarningDB('Wiedereinstieg nicht moeglich, da in SAP ein hoeheres Enddatum als unser Wiedereinstiegsdatum ist: '. $empData->person_id . ' Wiedereinstieg: ' . $dbDVEmpData->von);

							if (!is_cli())
								return error(self::ERROR_MSG);
							else
								continue 2;
						}
					}
					else
					{
						$updated = $this->transferEmployee($sapID, $dbDVEmpData->von, $dbDVEmpData->wochenstunden, $dbDVEmpData->oe_kurzbz_sap, $empData->person_id, true);
						
						if ($this->testlauf)
						{
							$testReturn[] = $updated;
						}
						else if (!$updated)
						{
							if (!is_cli())
								return error(self::ERROR_MSG);
							else
								continue 2;
						}
					}
				}
			}
			
			$dienstverhaeltnisse_ende = array_column($dienstverhaeltnisse, 'bis');
			rsort($dienstverhaeltnisse_ende);
			if (!in_array(null, $dienstverhaeltnisse_ende))
			{
				$dvEnde = new DateTime($dienstverhaeltnisse_ende[0]);
				$dvEnde->modify('+'. $this->_ci->config->item(self::AFTER_END) .' days');
				$today = Date('Y-m-d');

				if ($today >= $dvEnde->format('Y-m-d'))
				{
					krsort($sapEmpType->functions);
					$lastSAPFunctionDate = array_keys($sapEmpType->functions)[0];

					$sapEndDate = $sapEmpType->functions[$lastSAPFunctionDate]->ValidityPeriod->EndDate;
					$leavingDate = $dienstverhaeltnisse_ende[0];

					if ($leavingDate != $sapEndDate)
					{
						$updated = $this->addLeavingDate($sapID, $leavingDate, $empData->person_id);
						
						if ($this->testlauf)
						{
							$testReturn[] = $updated;
						}
						else if (!$updated)
						{
							if (!is_cli())
								return error(self::ERROR_MSG);
							else
								continue;
						}
						
					}
				}
			}

			if ($this->testlauf)
			{
				return success($testReturn);
			}
			else if ($updated !== false)
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

				if (!is_cli())
					return success('Users data updated successfully');
			}
		}
		return success('Users data updated successfully');
	}

	private function _getStunden(&$bestandteil, $bestandteile)
	{
		
		$result = array_flip(array_keys(array_column($bestandteile, 'vertragsbestandteiltyp_kurzbz'), 'stunden'));
		$std_bestandteile = array_intersect_key($bestandteile, $result);
		
		foreach ($std_bestandteile as $std_bestandteil)
		{
			if (
				($bestandteil->von <= $std_bestandteil->bis || is_null($std_bestandteil->bis))
				&&
				($bestandteil->bis >= $std_bestandteil->von || is_null($bestandteil->bis))
			)
			{
				if (is_null($bestandteil->wochenstunden))
					$bestandteil->wochenstunden = $std_bestandteil->wochenstunden;
			}
		}
		
	}

	private function _getKostenstelle(&$bestandteil, $bestandteile)
	{
		$result = array_flip(array_keys(array_column($bestandteile, 'vertragsbestandteiltyp_kurzbz'), 'funktion'));
		$kst_bestandteile = array_intersect_key($bestandteile, $result);
		
		foreach ($kst_bestandteile as $kst_bestandteil)
		{
			if (
				($bestandteil->von <= $kst_bestandteil->bis || is_null($kst_bestandteil->bis))
				&&
				($bestandteil->bis >= $kst_bestandteil->von || is_null($bestandteil->bis))
			)
			{
				if (is_null($bestandteil->oe_kurzbz))
					$bestandteil->oe_kurzbz = $kst_bestandteil->oe_kurzbz;
			}
		}
	}

	private function _getKostenstelleSap(&$bestandteil, $empData)
	{
		$dbModel = new DB_Model();
		$oeResult = $dbModel->execReadOnlyQuery('
									SELECT *
									FROM sync.tbl_sap_organisationsstruktur
									WHERE oe_kurzbz = ?
								', array($bestandteil->oe_kurzbz));
		
		if (isError($oeResult)) return $oeResult;
		
		if (!hasData($oeResult))
		{
			$this->_ci->LogLibSAP->logWarningDB('Die Organisation ist in der Tabelle nicht vorhanden ' . $bestandteil->oe_kurzbz);
			return false;
		}
		else
		{
			$oe_kurzbz_sap = getData($oeResult)[0]->oe_kurzbz_sap;
			$oe_kurzbz_root = $this->_ci->MessageTokenModel->getOERoot($bestandteil->oe_kurzbz);
			
			$oe_kurzbz_root = getData($oe_kurzbz_root)[0]->oe_kurzbz;
			
			if ($bestandteil->dv_oe_kurzbz !== $oe_kurzbz_root)
			{
				$this->_ci->LogLibSAP->logWarningDB('Die Funktion OE entspricht nicht der DV OE person:'. $empData->person_id);
				return false;
			}
			
			$bestandteil->oe_kurzbz_sap = $oe_kurzbz_sap;
		}
	}

	private function _getBestandteile($dienstverhaeltnis, $empData)
	{
		$dbModel = new DB_Model();
		
		$qry = "SELECT tbl_vertragsbestandteil.von,
						tbl_vertragsbestandteil.bis,
						vertragsbestandteiltyp_kurzbz,
						tbl_vertragsbestandteil_stunden.wochenstunden,
						tbl_benutzerfunktion.oe_kurzbz,
						tbl_dienstverhaeltnis.oe_kurzbz as dv_oe_kurzbz,
						tbl_vertragsbestandteil.vertragsbestandteil_id
				FROM hr.tbl_dienstverhaeltnis
					JOIN hr.tbl_vertragsbestandteil USING(dienstverhaeltnis_id)
					LEFT JOIN hr.tbl_vertragsbestandteil_stunden ON tbl_vertragsbestandteil.vertragsbestandteil_id = tbl_vertragsbestandteil_stunden.vertragsbestandteil_id
					LEFT JOIN hr.tbl_vertragsbestandteil_funktion ON tbl_vertragsbestandteil.vertragsbestandteil_id = tbl_vertragsbestandteil_funktion.vertragsbestandteil_id
					LEFT JOIN public.tbl_benutzerfunktion ON tbl_vertragsbestandteil_funktion.benutzerfunktion_id = tbl_benutzerfunktion.benutzerfunktion_id
				WHERE dienstverhaeltnis_id = ?
					AND (
							vertragsbestandteiltyp_kurzbz IN ?
							OR
							(vertragsbestandteiltyp_kurzbz = ? AND funktion_kurzbz = ?)
					)
				ORDER BY tbl_vertragsbestandteil.von";
		
		$params = [$dienstverhaeltnis->id, array('stunden'), 'funktion', 'kstzuordnung'];
		
		$bestandteile = $dbModel->execReadOnlyQuery($qry, $params);
		
		if (!hasData($bestandteile))
		{
			$this->_ci->LogLibSAP->logWarningDB('Kein Bestandteile vorhanden: '. $empData->person_id);
			return false;
		}
		
		return getData($bestandteile);
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

	public function sync($empID, $onlyStammdaten, $testlauf)
	{
		$dbModel = new DB_Model();

		$sapIdResult = $dbModel->execReadOnlyQuery('
				SELECT s.sap_eeid
				FROM sync.tbl_sap_mitarbeiter s
				WHERE s.mitarbeiter_uid = ?
			', array($empID));

		if (isError($sapIdResult)) return $sapIdResult;

		$this->testlauf = $testlauf;

		$emp = array($empID);

		if (!hasData($sapIdResult))
			return $this->create($emp);
		else
		{
			$update = $this->update($emp);
			if (!isError($update) && !$onlyStammdaten)
				return $this->updateEmployeeWorkAgreement($emp);
			return
				$update;
		}
	}

	public function checkEmployeesDVs($emps)
	{
		if (isEmptyArray($emps)) return success('No services to be updated');
		
		$diffEmps = $this->_removeNotCreatedEmps($emps);
		
		if (isError($diffEmps)) return $diffEmps;
		if (!hasData($diffEmps)) return success('No DVs to be compared after diff');
		
		$empsAllData = $this->_getAllEmpsDataWorkAgreement($diffEmps);
		
		if (isError($empsAllData)) return $empsAllData;
		if (!hasData($empsAllData)) return error('No data available for the given emps');
		
		foreach (getData($empsAllData) as $empData)
		{
			$dbModel = new DB_Model();
			// SAP ID vom EMP holen
			$sapResult = $dbModel->execReadOnlyQuery('
				SELECT s.sap_eeid
				FROM sync.tbl_sap_mitarbeiter s
				WHERE s.mitarbeiter_uid = ?
			', array($empData->uid));
			
			if (isError($sapResult))
				return $sapResult;
			if (!hasData($sapResult))
				continue;
			
			$sapID = getData($sapResult)[0]->sap_eeid;
			
			$sapEmpData = $this->getEmployeeById($sapID);
			
			if (isError($sapEmpData))
				return $sapEmpData;
			
			if (!hasData($sapEmpData))
				continue;
			
			$sapEmpData = getData($sapEmpData);
			
			if ($sapEmpData->ProcessingConditions->ReturnedQueryHitsNumberValue === 0)
			{
				$this->_ci->LogLibSAP->logWarningDB('Emp not found in SAP: '. $empData->uid);
				continue;
			}
			
			
			$sapEmpData = $sapEmpData->EmployeeData->EmploymentData;
			
			$sapEmpData = $this->checkIfObject($sapEmpData);
			
			foreach ($sapEmpData as $sapData)
			{
				if (!isset($data[$empData->uid]))
					$data[$empData->uid] = array();
				
				$workAgreements = $this->checkIfObject($sapData->WorkAgreementData);
				foreach ($workAgreements as $workAgreement)
				{
					$data[$empData->uid] = [];
					
					$benutzerFunctionsSAP = $this->checkIfObject($workAgreement->AdditionalClauses);
					
					foreach ($benutzerFunctionsSAP as $benutzerFunctionSAP)
					{
						$data[$empData->uid]['stunden'][] = ['stunden_von' => isset($benutzerFunctionSAP->ValidityPeriod->StartDate) ? $benutzerFunctionSAP->ValidityPeriod->StartDate : '', 'stunden_bis' => isset($benutzerFunctionSAP->ValidityPeriod->EndDate) ? $benutzerFunctionSAP->ValidityPeriod->EndDate : '', 'stunden' => isset($benutzerFunctionSAP->AgreedWorkingTimeRate->DecimalValue) ? number_format($benutzerFunctionSAP->AgreedWorkingTimeRate->DecimalValue, 2) : ''];
					}
					
					$benutzerOrganisationalsSAP = $this->checkIfObject($workAgreement->OrganisationalAssignment);
					
					foreach ($benutzerOrganisationalsSAP as $benutzerOrganisationalSAP)
					{
						$positions = $this->checkIfObject($benutzerOrganisationalSAP->PositionAssignment);
						
						foreach ($positions as $position)
						{
							$organisationStart = $position->ValidityPeriod->StartDate;
							$organisationEnde = $position->ValidityPeriod->EndDate;
							$sap_oe = null;
							if (isset($position->OrganisationalCenterDetails->OrganisationalCenterID))
							{
								$sap_oe = $position->OrganisationalCenterDetails->OrganisationalCenterID;
							} else if (isset($position->OrganisationalCenterDetails) && is_array($position->OrganisationalCenterDetails))
							{
								$tmp_sap_oe = array();
								
								foreach ($position->OrganisationalCenterDetails as $orgCenterDetails)
								{
									$tmp_sap_oe[] = $orgCenterDetails->OrganisationalCenterID;
								}
								
								sort($tmp_sap_oe);
								
								$sap_oe = $tmp_sap_oe[0];
							}
							
							$data[$empData->uid]['position'][] = ['von' => $organisationStart, 'bis' => $organisationEnde, 'oe' => $sap_oe];
						}
					}
				}
				
				/**
				 * only the last SAP Work agreement needs to be checked
				 */
				krsort($data[$empData->uid]['position']);
				krsort($data[$empData->uid]['stunden']);
				$data[$empData->uid]['stunden'] = array_slice($data[$empData->uid]['stunden'], 0, 1);
				$data[$empData->uid]['position'] = array_slice($data[$empData->uid]['position'], 0, 1);
			}
		}
		ksort($data);

		$export = [];
		foreach ($data as $mitarbeiter_uid => $emp)
		{
			foreach ($emp['stunden'] as $arbeitsvertragSap)
			{
				$query = '
					SELECT *
					FROM hr.tbl_dienstverhaeltnis
						JOIN hr.tbl_vertragsbestandteil ON tbl_dienstverhaeltnis.dienstverhaeltnis_id = tbl_vertragsbestandteil.dienstverhaeltnis_id
						JOIN hr.tbl_vertragsbestandteil_stunden ON tbl_vertragsbestandteil.vertragsbestandteil_id = tbl_vertragsbestandteil_stunden.vertragsbestandteil_id
					WHERE mitarbeiter_uid = ?
						ORDER BY tbl_vertragsbestandteil.von DESC NULLS LAST
					LIMIT 1
				';

				$params = [$mitarbeiter_uid];
				
				$dbModel = new DB_Model();
				$result = $dbModel->execReadOnlyQuery($query, $params);
				if (hasData($result))
				{
					$dbStunden = getData($result)[0];
					
					if ($arbeitsvertragSap['stunden'] !== $dbStunden->wochenstunden)
					{
						$daten = array($mitarbeiter_uid, 'stunden', $arbeitsvertragSap['stunden_von'], $arbeitsvertragSap['stunden_bis'], $arbeitsvertragSap['stunden']);

						$daten['db_von'] = $dbStunden->von;
						$daten['db_bis'] = is_null($dbStunden->bis) ? 'Kein Enddatum' : $dbStunden->bis;
						$daten['db_ist'] = $dbStunden->wochenstunden;
						$export[] = $daten;
					}
					
				}
			}

			foreach ($emp['position'] as $oeZuordnung)
			{
				$query = '
					SELECT tbl_sap_organisationsstruktur.*
					FROM hr.tbl_dienstverhaeltnis
						JOIN hr.tbl_vertragsbestandteil ON tbl_dienstverhaeltnis.dienstverhaeltnis_id = tbl_vertragsbestandteil.dienstverhaeltnis_id
						JOIN hr.tbl_vertragsbestandteil_funktion ON tbl_vertragsbestandteil.vertragsbestandteil_id = tbl_vertragsbestandteil_funktion.vertragsbestandteil_id
						JOIN public.tbl_benutzerfunktion ON tbl_vertragsbestandteil_funktion.benutzerfunktion_id = tbl_benutzerfunktion.benutzerfunktion_id AND funktion_kurzbz = ?
						JOIN tbl_organisationseinheit ON tbl_benutzerfunktion.oe_kurzbz = tbl_organisationseinheit.oe_kurzbz
						JOIN sync.tbl_sap_organisationsstruktur ON tbl_organisationseinheit.oe_kurzbz = tbl_sap_organisationsstruktur.oe_kurzbz
					WHERE mitarbeiter_uid = ?
					ORDER BY tbl_vertragsbestandteil.von DESC NULLS LAST
					LIMIT 1
				';

				$params = ['kstzuordnung', $mitarbeiter_uid];

				$dbModel = new DB_Model();
				$result = $dbModel->execReadOnlyQuery($query, $params);
				if (hasData($result))
				{
					$dbOe = getData($result)[0];
					if ($dbOe->oe_kurzbz_sap !== $oeZuordnung['oe'])
					{
						$daten = array($mitarbeiter_uid, "zuordnung", $oeZuordnung['von'], $oeZuordnung['bis'], $oeZuordnung['oe']);
						$daten['db_von_oe'] = !isset($dbOe->von) ? 'Kein Startdatum' : $dbOe->von;
						$daten['db_bis_oe'] = !isset($dbOe->bis) ? 'Kein Enddatum' : $dbOe->bis;
						$daten['db_ist_oe'] =  !isset($dbOe->oe_kurzbz_sap) ? 'Keine OE' : $dbOe->oe_kurzbz_sap;
						$export[] = $daten;
					}
				}
			}
		}

		$filename = "employees.csv";
		$file = fopen($filename, 'w');

		fputcsv($file, array('Mitarbeiter','Kategorie', 'SAP_Von','SAP_Bis', 'SAP_Ist', 'FH_von', 'FH_Bis', 'FH_Ist'));

		foreach ($export as $line)
		{
			fputcsv($file, $line);
		}
		fclose($file);
		return success('csv exportiert');
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
	private function _getAllEmpsData($emps, $create = false)
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
					'person_id' => $empAllData->person_id, 'verrechnung' => true
				)
			);

			if (isError($bankResult)) return $bankResult;

			$empAllData->accNumber = null;
			$empAllData->bankNumber = null;

			if (hasData($bankResult))
			{
				$bankData = getData($bankResult)[0];
				$pattern = '/[^A-Z0-9]/';
				$iban = preg_replace($pattern, '', strtoupper($bankData->iban));

				$ibanCountry = substr($iban, 0, 2);

				$empAllData->bankCountry = $ibanCountry;

				if ($ibanCountry === 'AT')
				{
					$bankIBAN = checkIBAN($iban);
					if (!$bankIBAN)
					{
						$this->_ci->LogLibSAP->logWarningDB('Incorrect bank data available for the given user: '. $empAllData->person_id);
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
					else
					{
						$this->_ci->LogLibSAP->logWarningDB('The bank swift code is not present in database: '. $bankData->bic);
					}
				}
				else
				{
					$this->_ci->LogLibSAP->logWarningDB('No bank data available for the given user: '.$empAllData->person_id);
				}
			}
			else
				$this->_ci->LogLibSAP->logWarningDB('No bank data available for the given user: '.$empPersonalData->person_id);

			// -------------------------------------------------------------------------------------------
			// Dienstverhaeltnis
			if ($create)
			{
				$empAllData->typeCode = self::SAP_TYPE_PERMANENT;
				$dbModel = new DB_Model();
				$dienstverhaeltnis = $dbModel->execReadOnlyQuery('
					SELECT dienstverhaeltnis_id AS id,
							tbl_dienstverhaeltnis.von,
							tbl_dienstverhaeltnis.bis
					FROM hr.tbl_dienstverhaeltnis
					WHERE mitarbeiter_uid = ?
						AND (tbl_dienstverhaeltnis.von::DATE <= (NOW() + INTERVAL ?\' Days\')::DATE)
						AND (tbl_dienstverhaeltnis.bis >= NOW() OR tbl_dienstverhaeltnis.bis IS NULL)
						AND vertragsart_kurzbz IN ?
					ORDER BY tbl_dienstverhaeltnis.von
					LIMIT 1
					', array($empAllData->uid, $this->_ci->config->item(self::BEFORE_START), $this->_ci->config->item(self::FHC_CONTRACT_TYPES)));
				
				if (isError($dienstverhaeltnis)) return $dienstverhaeltnis;
				
				if (!hasData($dienstverhaeltnis))
				{
					$this->_ci->LogLibSAP->logWarningDB('Kein Dienstverhaeltnis gefunden: ' . $empAllData->person_id);
					if (!is_cli())
						return error(self::ERROR_MSG);
					else
						continue;
				}
				
				$dienstverhaeltnis = getData($dienstverhaeltnis)[0];
				
				$funktionQry = "SELECT tbl_benutzerfunktion.oe_kurzbz,
       									tbl_dienstverhaeltnis.oe_kurzbz as dv_oe_kurzbz,
       									tbl_vertragsbestandteil.von,
       									tbl_vertragsbestandteil.bis
						FROM hr.tbl_dienstverhaeltnis
						JOIN hr.tbl_vertragsbestandteil USING(dienstverhaeltnis_id)
						JOIN hr.tbl_vertragsbestandteil_funktion USING(vertragsbestandteil_id)
						JOIN public.tbl_benutzerfunktion USING(benutzerfunktion_id)
						WHERE dienstverhaeltnis_id = ? AND funktion_kurzbz = ?";
				
				$funktionParams = array($dienstverhaeltnis->id, 'kstzuordnung');
				
				if (!is_null($dienstverhaeltnis->von))
				{
					$funktionQry .=' AND (tbl_vertragsbestandteil.bis IS NULL OR tbl_vertragsbestandteil.bis >= ?)';
					$funktionParams[] = $dienstverhaeltnis->von;
				}
				
				if (!is_null($dienstverhaeltnis->bis))
				{
					$funktionQry .=' AND (tbl_vertragsbestandteil.von IS NULL OR tbl_vertragsbestandteil.von <= ?)';
					$funktionParams[] = $dienstverhaeltnis->bis;
				}
				
				$funktionQry .= "ORDER BY tbl_vertragsbestandteil.von LIMIT 1;";
				
				$funktion = $dbModel->execReadOnlyQuery($funktionQry, $funktionParams);

				if (isError($funktion)) return $funktion;
				
				if (!hasData($funktion))
				{
					$this->_ci->LogLibSAP->logWarningDB('Kein Funktion Bestandteil vorhanden: ' . $empAllData->person_id . ' DV ID: ' . $dienstverhaeltnis->id);
					if (!is_cli())
						return error(self::ERROR_MSG);
					else
						continue;
				}
				
				$funktion = getData($funktion)[0];

				$stundenQry = "SELECT *
						FROM hr.tbl_dienstverhaeltnis
						JOIN hr.tbl_vertragsbestandteil USING(dienstverhaeltnis_id)
						JOIN hr.tbl_vertragsbestandteil_stunden USING(vertragsbestandteil_id)
						WHERE dienstverhaeltnis_id = ?";

				$stundenParams = array($dienstverhaeltnis->id);

				if (!is_null($funktion->von))
				{
					$stundenQry .=' AND (tbl_vertragsbestandteil.bis IS NULL OR tbl_vertragsbestandteil.bis >= ?)';
					$stundenParams[] = $funktion->von;
				}
				
				if (!is_null($funktion->bis))
				{
					$stundenQry .=' AND (tbl_vertragsbestandteil.von IS NULL OR tbl_vertragsbestandteil.von <= ?)';
					$stundenParams[] = $funktion->bis;
				}
				
				$stundenQry .= " ORDER BY tbl_vertragsbestandteil.von LIMIT 1";
				
				$stundenBestandteile = $dbModel->execReadOnlyQuery($stundenQry, $stundenParams);
				
				if (isError($stundenBestandteile)) return $stundenBestandteile;
				
				if (!hasData($stundenBestandteile))
				{
					$this->_ci->LogLibSAP->logWarningDB('Kein Stunden Bestandteil vorhanden: ' . $empAllData->person_id . ' DV ID: ' . $dienstverhaeltnis->id);
					if (!is_cli())
						return error(self::ERROR_MSG);
					else
						continue;
				}
				$stundenBestandteile = getData($stundenBestandteile)[0];
				
				$empAllData->decimalValue = $stundenBestandteile->wochenstunden;
				$empAllData->startDate = $funktion->von;

				$oeResult = $dbModel->execReadOnlyQuery('
									SELECT *
									FROM sync.tbl_sap_organisationsstruktur
									WHERE oe_kurzbz = ?
								', array($funktion->oe_kurzbz));
				
				if (isError($oeResult)) return $oeResult;
				
				if (!hasData($oeResult))
				{
					$this->_ci->LogLibSAP->logWarningDB('Die Organisation ist in der Sync Tabelle nicht vorhanden ' . $funktion->oe_kurzbz);
					if (!is_cli())
						return error(self::ERROR_MSG);
					else
						continue;
				}
				
				$oe_kurzbz_root = $this->_ci->MessageTokenModel->getOERoot($funktion->oe_kurzbz);
				$oe_kurzbz_root = getData($oe_kurzbz_root)[0]->oe_kurzbz;
				
				if ($funktion->dv_oe_kurzbz !== $oe_kurzbz_root)
				{
					$this->_ci->LogLibSAP->logWarningDB('Die Funktion OE entspricht nicht der DV OE person:'. $empAllData->person_id);
					if (!is_cli())
						return error(self::ERROR_MSG);
					else
						continue;
				}
				
				$oe_kurzbz_sap = getData($oeResult)[0]->oe_kurzbz_sap;
				$empAllData->oe_kurzbz_sap = $oe_kurzbz_sap;
			}
			
			$this->_ci->load->model('ressource/mitarbeiter_model', 'MitarbeiterModel');
			$mitarbeiter = $this->_ci->MitarbeiterModel->loadWhere(array('mitarbeiter_uid' => $empPersonalData->uid));
			
			if (isError($mitarbeiter)) return $mitarbeiter;
			
			if (hasData($mitarbeiter))
			{
				$mitarbeiter = getData($mitarbeiter)[0];
				$this->_ci->load->model('organisation/standort_model', 'StandortModel');
				
				$this->_ci->StandortModel->addJoin('public.tbl_kontakt', 'standort_id');
				$vorwahl = $this->_ci->StandortModel->loadWhere(array(
						'public.tbl_kontakt.standort_id' => $mitarbeiter->standort_id,
						'public.tbl_kontakt.kontakttyp' => 'telefon'
					)
				);
				
				if (isError($vorwahl)) return $vorwahl;
				
				if (hasData($vorwahl) && $mitarbeiter->telefonklappe !== null)
				{
					$empAllData->nummer = getData($vorwahl)[0]->kontakt . ' ' . $mitarbeiter->telefonklappe;
				}
			}
			
			$empAllData->email = $empPersonalData->uid . '@technikum-wien.at';
			// Stores all data for the current employee
			$empsAllDataArray[] = $empAllData;
		}

		return success($empsAllDataArray); // everything was fine!
	}
	
	private function _getAllEmpsDataWorkAgreement($emps)
	{
		$empsAllDataArray = array(); // returned array

		// Retrieves users personal data from database
		$dbModel = new DB_Model();

		$dbEmpsPersonalData = $dbModel->execReadOnlyQuery('
			SELECT DISTINCT p.person_id,
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
			
			$empAllData = $empPersonalData; // Stores current employee personal data
			// Stores all data for the current employee
			$empsAllDataArray[] = $empAllData;
		}

		return success($empsAllDataArray); // everything was fine!
	}

	private function getEmployeeById($id)
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

	private function getBankDetails($id)
	{
		// Calls SAP to find employee - bankdetails with the given id
		return $this->_ci->QueryEmployeeBankDetailsInModel->findByElements(
			array (
				'BankDetailsByEmployee' => array(
					'SelectionByEmployeeId' => array(
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

	private function getEmployeeAfterCreation($name, $surname, $date)
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

	private function addPaymentInformation($emp, $empData, $person_id)
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

		if (isset($empData->nummer))
		{
			$telephone = array(
				'actionCode' => '04',
				'ObjectNodeSenderTechnicalID' => null,
				'TelephoneFormattedNumberDescription' => $empData->nummer
			);

			$employeeData['EmployeeData']['WorkplaceAddressInformation']['Address']['Telephone'] = $telephone;
		}

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

		$manageEmpResult = $this->_ci->ManageEmployeeInModel->MaintainBundle($data);

		if (isError($manageEmpResult)) return $manageEmpResult;

		$manageEmp = getData($manageEmpResult);

		if (isset($manageEmp->EmployeeData) && isset($manageEmp->EmployeeData->ChangeStateID))
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

	private function addLeavingDate($empID, $endDate, $person_id)
	{
		if ($this->testlauf === true)
		{
			return array('type' => 'leaving', 'date' => $endDate);
		}
		
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
						if (isset($item->Note)) $this->_ci->LogLibSAP->logWarningDB($item->Note . ' for user: ' . $person_id . ' leavingdate: ' . $endDate);
					}
				}
				elseif ($manageEmp->Log->Item->Note)
				{
					$this->_ci->LogLibSAP->logWarningDB($manageEmp->Log->Item->Note . ' for user: ' . $person_id . ' leavingdate: ' . $endDate);
				}
			}
			else
			{
				// Default non blocking error
				$this->_ci->LogLibSAP->logWarningDB('SAP did not add a leaving date for the user: ' . $person_id . ' leavingdate: ' . $endDate);
			}
		}
		return false;
	}

	private function rehireEmployee($empID, $beginn, $typeCode, $stunden, $oe, $person_id, $ende = null)
	{
		if ($this->testlauf === true)
		{
			return array('type' => 'rehire', 'date' => $beginn, 'hours' => $stunden, 'oe' => $oeID);
		}

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
						if (isset($item->Note)) $this->_ci->LogLibSAP->logWarningDB($item->Note.' for user: '.$person_id . 'rehiredate: ' . $beginn);
					}
				}
				elseif ($manageEmp->Log->Item->Note)
				{
					$this->_ci->LogLibSAP->logWarningDB($manageEmp->Log->Item->Note.' for user: '.$person_id . 'rehiredate: ' . $beginn);
				}
			}
			else
			{
				// Default non blocking error
				$this->_ci->LogLibSAP->logWarningDB('SAP did not rehire the user: '.$person_id . 'rehiredate: ' . $beginn);
			}
		}
		return false;
	}

	private function transferEmployee($empID, $transferDate, $hours, $oeID, $person_id, $secondTry = false, $jobID = self::JOB_ID)
	{
		if ($this->testlauf === true)
		{
			return array('type' => 'transfer', 'date' => $transferDate, 'hours' => $hours, 'oe' => $oeID);
		}

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
						if (isset($item->Note)) $this->_ci->LogLibSAP->logWarningDB($item->Note.' for user: '.$person_id. ' transferdate: ' . $transferDate);
					}
				}
				elseif ($manageEmp->Log->Item->Note)
				{
					$this->_ci->LogLibSAP->logWarningDB($manageEmp->Log->Item->Note.' for user: '.$person_id . ' transferdate: ' . $transferDate);
				}
			}
			else
			{
				// Default non blocking error
				$this->_ci->LogLibSAP->logWarningDB('SAP did not transfer the user: '.$person_id. ' transferdate: ' . $transferDate);
			}
		}
		return false;
	}

}
