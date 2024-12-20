<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * This library contains the logic used to perform data synchronization between FHC and SAP Business by Design
 */
class SyncUsersLib
{
	// Jobs types used by this lib
	const SAP_USERS_CREATE = 'SAPUsersCreate';
	const SAP_USERS_UPDATE = 'SAPUsersUpdate';

	// Prefix for SAP SOAP id calls
	const CREATE_USER_PREFIX = 'CU';
	const UPDATE_USER_PREFIX = 'UU';

	const DEFAULT_LANGUAGE_ISO = 'DE'; // Default language ISO
	const ENGLISH_LANGUAGE = 'English'; // English language
	const ENGLISH_LANGUAGE_ISO = 'EN'; // English language ISO

	// Genders
	const FHC_GENDER_MALE = 'm';
	const FHC_GENDER_FEMALE = 'w';
	const FHC_GENDER_NON_BINARY = 'x';
	const SAP_GENDER_UNKNOWN = 0;
	const SAP_GENDER_MALE = 1;
	const SAP_GENDER_FEMALE = 2;
	const SAP_GENDER_NON_BINARY = 3;

	// Prestundet statuses
	const PS_STUDENT = 'Student';
	const PS_BEWERBER = 'Bewerber';
	const PS_AUFGENOMMENER = 'Aufgenommener';
	const PS_ABSOLVENT = 'Absolvent';
	const PS_INTERESSENT = 'Interessent';

	// SAP Classifications
	const CLASSIFICATION_ANDERE = '0';// Andere
	const CLASSIFICATION_STUDENT = 'A'; // Student
	const CLASSIFICATION_ALUMNI = 'B';// Alumni
	const CLASSIFICATION_BEWERBER = 'C';// Bewerber

	// SAP user statuses
	const USER_STATUS_PREPARATION = 1;
	const USER_STATUS_ACTIVE = 2;

	// SAP default address value
	const DEFAULT_ADDRESS = 'XXDEFAULT';

	// Config entries for messaging
	const CFG_OU_RECEIVERS_PRIVATE = 'ou_receivers_private';

	// Config entries name
	const USERS_PAYMENT_COMPANY_IDS = 'users_payment_company_ids';
	const USERS_ACCOUNT_DETERMINATION_DEBTOR_GROUP_CODE = 'users_account_determination_debtor_group_code';

	// Address info
	const STRASSE_LENGHT = 60;
	const ORT_LENGHT = 40;
	const PLZ_LENGHT = 11;

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
				'dbExecuteUser' => 'Jobs queue system',
				'requestId' => 'JQW',
				'requestDataFormatter' => function($data) {
					return json_encode($data);
				}
			),
			'LogLibSAP'
		);

		// Loads QueryCustomerInModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SOAP/QueryCustomerIn_model', 'QueryCustomerInModel');
		// Loads ManageCustomerInModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SOAP/ManageCustomerIn_model', 'ManageCustomerInModel');

		// Loads SAPStudentsModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SAPStudents_model', 'SAPStudentsModel');

		// Load users configuration
		$this->_ci->config->load('extensions/FHC-Core-SAP/Users');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

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

	/**
	 * Return the raw result of SAP->QueryCustomerIn->FindByCommunicationData->SelectionByInternalID
	 */
	public function getUserById($id)
	{
		// Calls SAP to find a user with the given id
		return $this->_ci->QueryCustomerInModel->findByCommunicationData(
			array(
				'CustomerSelectionByCommunicationData' => array(
					'SelectionByInternalID' => array(
						'LowerBoundaryInternalID' => $id,
						'InclusionExclusionCode' => 'I',
						'IntervalBoundaryTypeCode' => 1
					)
				),
				'ProcessingConditions' => array(
					'QueryHitsUnlimitedIndicator' => true
					//'QueryHitsMaximumNumberValue' => 10
				)
			)
		);
	}

	/**
	 * Creates new users in SAP using the array of person ids given as parameter
	 */
	public function create($users)
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
			if (isEmptyString($userData->email))
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

			// Checks if the current user is already present in SAP
			$userDataSAP = $this->_userExistsByEmailSAP($userData->email);

			// If an error occurred then return it
			if (isError($userDataSAP)) return $userDataSAP;

			// If the current user is not present in SAP
			if (!hasData($userDataSAP))
			{
				$data = array(
					'BasicMessageHeader' => array(
						'ID' => generateUID(self::CREATE_USER_PREFIX),
						'UUID' => generateUUID()
					),
					'Customer' => array(
						'actionCode' => '01',
						'addressInformationListCompleteTransmissionIndicator' => true,
						'communicationArrangementListCompleteTransmissionIndicator' => true,
						'CategoryCode' => 1,
						'ProspectIndicator' => $userData->prospectIndicator,
						'CustomerIndicator' => !$userData->prospectIndicator,
						'LifeCycleStatusCode' => 2,
						'Person' => array(
							'GivenName' => $userData->name,
							'FamilyName' => $userData->surname,
							'BirthName' => $userData->surname,
							'NonVerbalCommunicationLanguageCode' => $userData->language,
							'GenderCode' => $userData->gender
						),
						'VerbalCommunicationLanguageCode' => $userData->language,
						'ABCClassificationCode' => $userData->classification,
						'ContactAllowedCode' => 1,
						'LegalCompetenceIndicator' => true,
						'AddressInformation' => array(
							'actionCode' => '01',
							'addressInformationListCompleteTransmissionIndicator' => true,
							'AddressUsage' => array(
								'AddressUsageCode' => self::DEFAULT_ADDRESS
							),
							'Address' => array(
								'EmailURI' => $userData->email,
								'PreferredCommunicationMediumTypeCode' => 'INT'
							)
						),
						/*
						INT = EMail
						PRT = Drucker
						XMS = Externes System
						FAX = Fax
						*/
						'CommunicationArrangement' => array(
							0 => array( // Mahnung
								'CompoundServiceInterfaceCode' => '108',
								'EnabledIndicator' => true,
								'CommunicationMediumTypeCode' => 'INT'
							),
							1 => array( // Zahlungsavis
								'CompoundServiceInterfaceCode' => '11',
								'EnabledIndicator' => true,
								'CommunicationMediumTypeCode' => 'INT'
							),
							2 => array( // Kundenauftragsbestaetigung
								'CompoundServiceInterfaceCode' => '27',
								'EnabledIndicator' => true,
								'CommunicationMediumTypeCode' => 'INT'
							),
							3 => array( // Kundenrechnung / Gutschrift
								'CompoundServiceInterfaceCode' => '28',
								'EnabledIndicator' => true,
								'CommunicationMediumTypeCode' => 'INT'
							),
							4 => array( // Angebot
								'CompoundServiceInterfaceCode' => '46',
								'EnabledIndicator' => true,
								'CommunicationMediumTypeCode' => 'INT'
							),
							5 => array( // Kundenvertragsbestätigung
								'CompoundServiceInterfaceCode' => '992',
								'EnabledIndicator' => true,
								'CommunicationMediumTypeCode' => 'INT'
							)
						),
						'PaymentData' => $this->_getPaymentCompanyIdArray()
					)
				);

				// If bank data are present for the current user then they are added to $data
				// NOTE: here are logged warnings into the database
				$this->_setUserBankData(
					$data, $userData->person_id, $userData->name, $userData->surname, $userData->iban, $userData->swift
				);

				// Get the correct address info
				$data['Customer']['AddressInformation']['Address']['PostalAddress'] = $this->_getAddressInformations($userData);

				// Then create it!
				$manageCustomerResult = $this->_ci->ManageCustomerInModel->MaintainBundle_V1($data);

				// If an error occurred then return it
				if (isError($manageCustomerResult)) return $manageCustomerResult;

				// SAP data
				$manageCustomer = getData($manageCustomerResult);

				// If data structure is ok...
				if (isset($manageCustomer->Customer) && isset($manageCustomer->Customer->InternalID))
				{
					// Store in database the couple person_id sap_user_id
					$insert = $this->_ci->SAPStudentsModel->insert(
						array(
							'person_id' => $userData->person_id,
							'sap_user_id' => $manageCustomer->Customer->InternalID,
							'last_update' => 'NOW()'
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
			else // Add the already present user to the sync table
			{
				$sapCustomer = getData($userDataSAP); // get SAP customer data

				// Store in database the couple person_id sap_user_id
				$insert = $this->_ci->SAPStudentsModel->insert(
					array(
						'person_id' => $userData->person_id,
						'sap_user_id' => $sapCustomer->InternalID
					)
				);

				// If database error occurred then return it
				if (isError($insert)) return $insert;
			}
		}

		return success('Users data created successfully');
	}

	/**
	 * Updates users data in SAP using the array of person ids given as parameter
	 */
	public function update($users)
	{
		if (isEmptyArray($users)) return success('No users to be updated');

		// Remove the already created users
		$diffUsers = $this->_removeNotCreatedUsers($users);

		if (isError($diffUsers)) return $diffUsers;
		if (!hasData($diffUsers)) return success('No users to be created after diff');

		// Retrieves all users data
		$usersAllData = $this->_getAllUsersData($diffUsers);

		if (isError($usersAllData)) return $usersAllData;
		if (!hasData($usersAllData)) return error('No data available for the given users');

		$dbModel = new DB_Model();

		// Loops through users data
		foreach (getData($usersAllData) as $userData)
		{
			// If an email address was not found for this user...
			if (isEmptyString($userData->email))
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

			// Gets the SAP id for the current user
			$sapIdResult = $dbModel->execReadOnlyQuery('
				SELECT s.sap_user_id
				  FROM sync.tbl_sap_students s
				WHERE s.person_id = ?
			', array($userData->person_id));

			if (isError($sapIdResult)) return $sapIdResult;
			if (!hasData($sapIdResult)) continue; // should never happen since it was checked earlier

			// Checks if the current user is already present in SAP
			$userDataSAP = $this->_userExistsByIdSAP(getData($sapIdResult)[0]->sap_user_id);

			// If an error occurred then return it
			if (isError($userDataSAP)) return $userDataSAP;

			// If the current user is present in SAP
			if (hasData($userDataSAP))
			{
				$sapCustomer = getData($userDataSAP); // get SAP customer data
				$userData->addressInformationUUID = null; // default is null

				// Get the AddressInformation UUID
				if (isset($sapCustomer->AddressInformation)
					&& isset($sapCustomer->AddressInformation->UUID)
					&& isset($sapCustomer->AddressInformation->UUID->_))
				{
					$userData->addressInformationUUID = $sapCustomer->AddressInformation->UUID->_;
				}
				elseif (isset($sapCustomer->AddressInformation) && !isEmptyArray($sapCustomer->AddressInformation))
				{
					// For each address
					foreach ($sapCustomer->AddressInformation as $addressInformation)
					{
						// Get the mail address for this user
						if (isset($addressInformation->AddressUsage)
							&& isset($addressInformation->UUID)
							&& isset($addressInformation->UUID->_)
							&& isset($addressInformation->AddressUsage->AddressUsageCode)
							&& isset($addressInformation->AddressUsage->AddressUsageCode->_)
							&& $addressInformation->AddressUsage->AddressUsageCode->_ == self::DEFAULT_ADDRESS)
						{
							$userData->addressInformationUUID = $addressInformation->UUID->_;
						}
					}
				}

				// Should never happen, just to be shure
				// In case is not possible to retrieve the AddressInformation UUID the call would fail anyway
				// better to skip to the next user
				if ($userData->addressInformationUUID == null)
				{
					$this->_ci->LogLibSAP->logWarningDB('Was no possible to retrieve the AddressInformation UUID for user '.$userData->person_id);
					continue;
				}

				$data = array(
					'BasicMessageHeader' => array(
						'ID' => generateUID(self::UPDATE_USER_PREFIX),
						'UUID' => generateUUID()
					),
					'Customer' => array(
						'actionCode' => '02',
						'addressInformationListCompleteTransmissionIndicator' => false,
						'InternalID' => getData($sapIdResult)[0]->sap_user_id,
						'ProspectIndicator' => $userData->prospectIndicator,
						'CustomerIndicator' => !$userData->prospectIndicator,
						'Person' => array(
							'GivenName' => $userData->name,
							'FamilyName' => $userData->surname,
							'BirthName' => $userData->surname,
							'NonVerbalCommunicationLanguageCode' => $userData->language,
							'GenderCode' => $userData->gender
						),
						'VerbalCommunicationLanguageCode' => $userData->language,
						'ABCClassificationCode' => $userData->classification,
						'AddressInformation' => array(
							'UUID' => $userData->addressInformationUUID,
							'actionCode' => '02',
							'addressInformationListCompleteTransmissionIndicator' => false,
							'Address' => array(
								'EmailURI' => $userData->email
							)
						),
						/*
						INT = EMail
						PRT = Drucker
						XMS = Externes System
						FAX = Fax
						*/
						'CommunicationArrangement' => array(
							0 => array( // Mahnung
								'CompoundServiceInterfaceCode' => '108',
								'EnabledIndicator' => true,
								'CommunicationMediumTypeCode' => 'INT'
							),
							1 => array( // Zahlungsavis
								'CompoundServiceInterfaceCode' => '11',
								'EnabledIndicator' => true,
								'CommunicationMediumTypeCode' => 'INT'
							),
							2 => array( // Kundenauftragsbestaetigung
								'CompoundServiceInterfaceCode' => '27',
								'EnabledIndicator' => true,
								'CommunicationMediumTypeCode' => 'INT'
							),
							3 => array( // Kundenrechnung / Gutschrift
								'CompoundServiceInterfaceCode' => '28',
								'EnabledIndicator' => true,
								'CommunicationMediumTypeCode' => 'INT'
							),
							4 => array( // Angebot
								'CompoundServiceInterfaceCode' => '46',
								'EnabledIndicator' => true,
								'CommunicationMediumTypeCode' => 'INT'
							),
							5 => array( // Kundenvertragsbestätigung
								'CompoundServiceInterfaceCode' => '992',
								'EnabledIndicator' => true,
								'CommunicationMediumTypeCode' => 'INT'
							)
						),
						'PaymentData' => $this->_getPaymentCompanyIdArray()
					)
				);

				// If bank data are present for the current user then they are added to $data
				// NOTE: here are logged warnings into the database
				//$this->_setUserBankData(
				//	$data, $userData->person_id, $userData->name, $userData->surname, $userData->iban, $userData->swift
				//);

				// Get the correct address info
				$data['Customer']['AddressInformation']['Address']['PostalAddress'] = $this->_getAddressInformations($userData);

				// Then update it!
				$manageCustomerResult = $this->_ci->ManageCustomerInModel->MaintainBundle_V1($data);

				// If an error occurred then return it
				if (isError($manageCustomerResult)) return $manageCustomerResult;

				// SAP data
				$manageCustomer = getData($manageCustomerResult);

				// If data structure is ok...
				if (isset($manageCustomer->Customer) && isset($manageCustomer->Customer->InternalID))
				{
					// Store in database the date of the update
					$update = $this->_ci->SAPStudentsModel->update(
						array(
							'person_id' => $userData->person_id,
							'sap_user_id' => $manageCustomer->Customer->InternalID
						),
						array(
							'last_update' => 'NOW()'
						)
					);

					// If database error occurred then return it
					if (isError($update)) return $update;
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
			else
			{
				$this->_ci->LogLibSAP->logWarningDB(
					'The following user does not exists on SAP: person_id: '.$userData->person_id.
					' - sap_user_id: '.getData($sapIdResult)[0]->sap_user_id
				);
			}
		}

		return success('Users data updated successfully');
	}

	/**
	 * Updates the bank accounts data of the synchronized users.
	 * It makes use of the sync table sync.tbl_sap_students
	 */
	public function updateBankAccounts()
	{
		// Retrieves all bank accounts for the users that are present
		// in the sync table sync.tbl_sap_students
		$dbModel = new DB_Model();

		$usersBankData = $dbModel->execReadOnlyQuery('
			SELECT p.person_id,
				s.sap_user_id,
				p.nachname AS surname,
				p.vorname AS name,
				b.iban,
				b.bic AS swift
			  FROM sync.tbl_sap_students s
			  JOIN public.tbl_bankverbindung b USING(person_id)
			  JOIN public.tbl_person p USING(person_id)
			 WHERE b.iban IS NOT NULL
			 AND (b.insertamum>now() - \'2 days\'::interval OR b.updateamum > now() - \'2 days\'::interval)
		');

		// If an error occurred then return it
		if (isError($usersBankData)) return $usersBankData;
		// If no data are present then return a message
		if (!hasData($usersBankData)) return success('No data available from database');

		// Loops through users bank data
		foreach (getData($usersBankData) as $userBankData)
		{
			$iban = str_replace(' ', '', $userBankData->iban);
			$swift = str_replace(' ', '', $userBankData->swift);

			$sapCustomerResult = $this->_userExistsByIdSAP($userBankData->sap_user_id);

			if(!isSuccess($sapCustomerResult) || !hasData($sapCustomerResult))
			{
				$this->_ci->LogLibSAP->logErrorDB('User not found in SAP PersonID: '.$userBankData->person_id);
				continue;
			}

			// Check if IBAN is already set in SAP
			// If it is already there we dont set it because this causes an error if we do so
			$sapCustomerData = getData($sapCustomerResult);
			if(isset($sapCustomerData->BankDetails))
			{
				if(isset($sapCustomerData->BankDetails->BankAccountStandardID))
				{
					if($sapCustomerData->BankDetails->BankAccountStandardID == $iban)
					{
						$this->_ci->LogLibSAP->logInfoDB('IBAN already set for PersonID: '.$userBankData->person_id.' - no action performed');
						continue;
					}
					else
					{
						$this->_ci->LogLibSAP->logWarningDB('Different IBAN Found: '.$userBankData->person_id.' - try to set');
					}
				}
			}

			// Get the BankInternalID with the given parameters
			$bankInternalID = $this->_getBankInternalID($userBankData->person_id, $iban, $swift);

			// If a _not_ valid BankInternalID was found then continue to the next user
			// NOTE: _getBankInternalID logs warnings in case none or many BankInternalID have been found
			if (isEmptyString($bankInternalID))
			{
				$this->_ci->LogLibSAP->logWarningDB('No Bank Internal ID found for user: '.$userBankData->person_id);
				continue;
			}

			// Otherwise set the data to proceed with the bank data update on SAP side
			$data = array(
				'BasicMessageHeader' => array(
					'ID' => generateUID(self::UPDATE_USER_PREFIX),
					'UUID' => generateUUID()
				),
				'Customer' => array(
					'actionCode' => '04',
					'bankDetailsListCompleteTransmissionIndicator' => true,
					'InternalID' => $userBankData->sap_user_id,
					'BankDetails' => array(
						'actionCode' => '04',
						'BankInternalID' => $bankInternalID,
						'BankAccountHolderName' => $userBankData->name.' '.$userBankData->surname,
						'BankAccountStandardID' => $iban
					)
				)
			);

			// Then update it!
			$manageCustomerResult = $this->_ci->ManageCustomerInModel->MaintainBundle_V1($data);

			// If an error occurred then return it
			if (isError($manageCustomerResult))
			{
				$this->_ci->LogLibSAP->logErrorDB('Error add Bankdata for user: '.$userBankData->person_id);
				return $manageCustomerResult;
			}

			// SAP data
			$manageCustomer = getData($manageCustomerResult);

			// If data structure is ok...
			if (isset($manageCustomer->Customer) && isset($manageCustomer->Customer->InternalID))
			{
				// Success
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
							if (isset($item->Note)) $this->_ci->LogLibSAP->logWarningDB($item->Note.' for user: '.$userBankData->person_id);
						}
					}
					elseif ($manageCustomer->Log->Item->Note)
					{
						$this->_ci->LogLibSAP->logWarningDB($manageCustomer->Log->Item->Note.' for user: '.$userBankData->person_id);
					}
				}
				else
				{
					// Default non blocking error
					$this->_ci->LogLibSAP->logWarningDB('SAP did not return the InterlID for user: '.$userBankData->person_id);
				}
				continue;
			}
		}

		return success('Users bank accounts data updated successfully');
	}

	// --------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Remove already created users from the given array
	 * Wrapper method for _addOrRemoveUsers
	 */
	private function _removeCreatedUsers($users)
	{
		return $this->_addOrRemoveUsers($users, false);
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
	private function _addOrRemoveUsers($users, $initialFoundValue)
	{
		$diffUsersArray = array(); // array that is foing to be returned

		// Get synchronized users from database
		$dbModel = new DB_Model();
		$dbSyncdUsers = $dbModel->execReadOnlyQuery('
			SELECT s.person_id
			  FROM sync.tbl_sap_students s
			 WHERE s.person_id IN ?
		', array($users));

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
	private function _getAllUsersData($users)
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
				p.geschlecht AS gender
			  FROM public.tbl_person p
			 WHERE p.person_id IN ?
		', array(
			getData($users)
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
			// Email & Classification

			// Default fallback for classification
			$userAllData->classification = self::CLASSIFICATION_ANDERE;
			// Default fallback for prospect indicator
			$userAllData->prospectIndicator = false;

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
				$userAllData->email = getData($kontaktResult)[0]->kontakt;
			}
			else // otherwise set the email as null, it should be checked later every time before using it
			{
				$userAllData->email = null;
			}

			// Get the prestudentstatus for the just obtained prestudent
			$this->_ci->load->model('crm/Prestudentstatus_model', 'PrestudentstatusModel');
			$resultLastPrestudentstatus = $this->_ci->PrestudentstatusModel->getLastStatusPerson($userPersonalData->person_id);

			if (isError($resultLastPrestudentstatus)) return $resultLastPrestudentstatus;

			// If there are no prestudents for the current user, or the current user is not a student...
			if (hasData($resultLastPrestudentstatus))
			{
				$statuses = getData($resultLastPrestudentstatus);

				$is_student = false;
				$is_bewerber = false;
				$is_absolvent = false;
				$is_interessent = false;

				foreach($statuses as $row_status)
				{
					if ($row_status->status_kurzbz == self::PS_STUDENT)
					{
						$is_student = true;
					}

					if ($row_status->status_kurzbz == self::PS_BEWERBER
					 || $row_status->status_kurzbz == self::PS_AUFGENOMMENER)
					{
						$is_bewerber = true;
					}

					if ($row_status->status_kurzbz == self::PS_ABSOLVENT)
					{
						$is_absolvent = true;
					}
					if ($row_status->status_kurzbz == self::PS_INTERESSENT)
					{
						$is_interessent = true;
					}
				}

				// Set classification
				if ($is_student)
				{
					$userAllData->classification = self::CLASSIFICATION_STUDENT;
				}
				elseif ($is_bewerber)
				{
					$userAllData->classification = self::CLASSIFICATION_BEWERBER;
				}
				elseif ($is_absolvent)
				{
					$userAllData->classification = self::CLASSIFICATION_ALUMNI;
				}
				else
				{
					$userAllData->classification = self::CLASSIFICATION_ANDERE;
				}

				if ($is_interessent)
				{
					$userAllData->prospectIndicator = true;
				}
				// else fallback
			}

			// Try to get Company Mail if available

			// Loads message configuration
			$this->_ci->config->load('message');

			// ...get the UID to compose the email address
			$dbUIDResult = $dbModel->execReadOnlyQuery('
				SELECT
					uid, tbl_studiengang.oe_kurzbz
				FROM
					public.tbl_prestudent
					JOIN public.tbl_student USING(prestudent_id)
					JOIN public.tbl_benutzer ON(uid=student_uid)
					JOIN public.tbl_prestudentstatus USING(prestudent_id)
					JOIN public.tbl_studiengang ON(tbl_prestudent.studiengang_kz = tbl_studiengang.studiengang_kz)
				WHERE
					tbl_prestudent.person_id = ?
					AND tbl_benutzer.aktiv
					AND tbl_studiengang.oe_kurzbz NOT IN ?
				ORDER BY
					tbl_prestudentstatus.datum desc
				LIMIT 1
			', array($userPersonalData->person_id, $this->_ci->config->item(self::CFG_OU_RECEIVERS_PRIVATE)));

			if (isError($dbUIDResult)) return $dbUIDResult;

			// If data are present in database and the organisation unit is NOT in the list
			// of organisation units that sent only to private emails
			if (hasData($dbUIDResult))
			{
				$userAllData->email = getData($dbUIDResult)[0]->uid.'@'.DOMAIN;
			}
			// else no data are present in database -> use the private email -> fallback

			// -------------------------------------------------------------------------------------------
			// Bank account
			$this->_ci->load->model('person/Bankverbindung_model', 'BankverbindungModel');
			$this->_ci->BankverbindungModel->addOrder('updateamum', 'DESC');
			$this->_ci->BankverbindungModel->addOrder('insertamum', 'DESC');
			$this->_ci->BankverbindungModel->addLimit(1);
			$bankResult = $this->_ci->BankverbindungModel->loadWhere(
				array(
					'person_id' => $userPersonalData->person_id
				)
			);

			$userAllData->iban = null;
			$userAllData->swift = null;

			if (isError($bankResult)) return $bankResult;
			if (hasData($bankResult)) // if a bank account was found
			{
				$userAllData->iban = str_replace(' ', '', getData($bankResult)[0]->iban);
				$userAllData->swift = str_replace(' ', '', getData($bankResult)[0]->bic);
			}

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
	private function _userExistsByEmailSAP($email)
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
	 * Checks on SAP side if a user already exists with the given SAP id
	 * Returns a success object with the found user data, otherwise with a false value
	 * In case of error then an error object is returned
	 */
	private function _userExistsByIdSAP($id)
	{
		$queryCustomerResult = $this->getUserById($id);

		if (isError($queryCustomerResult)) return $queryCustomerResult;
		if (!hasData($queryCustomerResult)) return error('Something went wrong while checking if a user is present using SAP id');

		// Get data from then returned object
		$queryCustomer = getData($queryCustomerResult);

		// Checks the structure of then returned object
		if (isset($queryCustomer->ProcessingConditions)
			&& isset($queryCustomer->ProcessingConditions->ReturnedQueryHitsNumberValue))
		{
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
	 * Generate payment data array
	 */
	private function _getPaymentCompanyIdArray()
	{
		// Get users payment company ids
		$paymentCompanyIds = $this->_ci->config->item(self::USERS_PAYMENT_COMPANY_IDS);
		$paymentCompanyIdsArray = [];

		foreach ($paymentCompanyIds as $paymentCompanyId)
		{
			$paymentCompanyIdsArray[] = array(
				'CompanyID' => $paymentCompanyId,
				'AccountDeterminationDebtorGroupCode' => $this->_ci->config->item(self::USERS_ACCOUNT_DETERMINATION_DEBTOR_GROUP_CODE),
				'PaymentForm' => array('PaymentFormCode' => '05') // Ueberweisung
			);
		}

		return $paymentCompanyIdsArray;
	}

	/**
	 * Generate correct info address using the user data, if data do not match SAP requirements then they are shortened and
	 * a warning message is generated
	 */
	private function _getAddressInformations($userData)
	{
		$addressInformationArray = null; // in case is not possible to generate address info

		// If address info are present in database
		if (isset($userData->strasse) && isset($userData->country) && isset($userData->plz))
		{
			// If ort is not present or it is an empty string
			if (!isset($userData->ort) || (isset($userData->ort) && isEmptyString($userData->ort)))
			{
				// If also the gemeinde is not present or it is an empty string
				if (!isset($userData->gemeinde) || (isset($userData->gemeinde) && isEmptyString($userData->gemeinde)))
				{
					return null; // return a null value. Should never happen because was checked earlier
				}
				else // otherwise use the gemeinde instead of the ort
				{
					// ORT
					$ort = $userData->gemeinde;
					if (mb_strlen($ort) >= self::ORT_LENGHT)
					{
						$ort = mb_substr($userData->ort, 0, self::ORT_LENGHT);
						$this->_ci->LogLibSAP->logWarningDB('Ort is longer then '.self::ORT_LENGHT.' chars for user: '.$userData->person_id);
					}
				}
			}
			else // if present and valid then use it
			{
				// ORT
				$ort = $userData->ort;
				if (mb_strlen($userData->ort) >= self::ORT_LENGHT)
				{
					$ort = mb_substr($userData->ort, 0, self::ORT_LENGHT);
					$this->_ci->LogLibSAP->logWarningDB('Ort is longer then '.self::ORT_LENGHT.' chars for user: '.$userData->person_id);
				}
			}

			// Strasse
			$strasse = $userData->strasse;
			if (mb_strlen($userData->strasse) >= self::STRASSE_LENGHT)
			{
				$strasse = mb_substr($userData->strasse, 0, self::STRASSE_LENGHT);
				$this->_ci->LogLibSAP->logWarningDB('Strasse is longer then '.self::STRASSE_LENGHT.' chars for user: '.$userData->person_id);
			}

			// PLZ
			$plz = $userData->plz;
			if (mb_strlen($userData->plz) >= self::PLZ_LENGHT)
			{
				$plz = mb_substr($userData->plz, 0, self::PLZ_LENGHT);
				$this->_ci->LogLibSAP->logWarningDB('Plz is longer then '.self::PLZ_LENGHT.' chars for user: '.$userData->person_id);
			}

			$addressInformationArray = array(
				'CountryCode' => $userData->country,
				'CityName' => $ort,
				'StreetPostalCode' => $plz,
				'StreetName' => $strasse
			);
		}

		return $addressInformationArray;
	}

	/**
	 * Set the user bank data if possible
	 * NOTE: warnings are logged in database
	 */
	private function _setUserBankData(&$data, $person_id, $name, $surname, $iban, $swift)
	{
		// If the bank account is present
		if (!isEmptyString($iban))
		{
			// Get the bankInternalID using the given parameters
			$bankInternalID = $this->_getBankInternalID($person_id, $iban, $swift);

			// If a valid BankInternalID was found
			// NOTE: _getBankInternalID logs warnings in case none or many BankInternalID have been found
			if (!isEmptyString($bankInternalID))
			{
				$data['Customer']['bankDetailsListCompleteTransmissionIndicator'] = true;
				$data['Customer']['BankDetails'] = array(
					'actionCode' => '01',
					'BankInternalID' => $bankInternalID,
					'BankAccountHolderName' => $name.' '.$surname,
					'BankAccountStandardID' => $iban
				);
			}
		}
	}

	/**
	 * Get the SAP bank account internal id with the given parameters
	 * The first attempt is to get the BankInternalID using the national code contained in the user IBAN
	 * if this IBAN belongs to an austrian bank
	 * NOTE: logs warnings in case none or many BankInternalID have been found
	 */
	private function _getBankInternalID($person_id, $iban, $swift)
	{
		$bankInternalID = null; // if a valid BankInternalID was not found then return null

		// Retrieves the SAP bank id with the bank national code
		$dbModel = new DB_Model();

		// Checks if the IBAN belongs to an autrian bank
		if (substr($iban, 0, 2) == 'AT')
		{
			// Get the bank national code from the IBAN
			// Austrian IBAN format:
			// ATkk bbbb bccc cccc cccc
			// k: check digits
			// b: national bank code
			// c: account number
			$bankNationalCode = substr($iban, 4, 5);

			// Get the BankInternalID from the database using the bank national code
			$sapBankData = $dbModel->execReadOnlyQuery('
				SELECT b.sap_bank_id
				  FROM sync.tbl_sap_banks b
				 WHERE b.sap_bank_default_national_code = ?
				',
				array($bankNationalCode)
			);

			// If an error occurred then return it
			if (isError($sapBankData)) return $sapBankData;

			// If no data are present then log a warning in the database
			if (!hasData($sapBankData))
			{
				$this->_ci->LogLibSAP->logWarningDB(
					'Was not possible to find a valid BankInternalID for user: '.$person_id.
					' with the bank national code: '.$bankNationalCode
				);
			}
			else // if there are data
			{
				// and more then one then log a warning in the database
				if (count(getData($sapBankData)) > 1)
				{
					$this->_ci->LogLibSAP->logWarningDB(
						'Too many BankInternalID for user: '.$person_id.
						' with the bank national code: '.$bankNationalCode
					);
				}
				else // a single valid BankInternalID was found
				{
					$bankInternalID = getData($sapBankData)[0]->sap_bank_id;
				}
			}
		}
		else // _not_ an austrian iban
		{
			// Get the BankInternalID from the database using the SWIFT code
			$sapBankData = $dbModel->execReadOnlyQuery('
				SELECT b.sap_bank_id
				  FROM sync.tbl_sap_banks b
				 WHERE b.sap_bank_swift = ?
				',
				array($swift)
			);

			// If an error occurred then return it
			if (isError($sapBankData)) return $sapBankData;

			// If no data are present then log a warning in the database
			if (!hasData($sapBankData))
			{
				$this->_ci->LogLibSAP->logWarningDB(
					'Was not possible to find a valid BankInternalID for user: '.$person_id.
					' with the SWIFT code: '.$swift
				);
			}
			else // if there are data
			{
				// and more then one then log a warning in the database
				if (count(getData($sapBankData)) > 1)
				{
					$this->_ci->LogLibSAP->logWarningDB(
						'Too many BankInternalID for user: '.$person_id.
						' with the SWIFT code: '.$swift
					);
				}
				else // a single valid BankInternalID was found
				{
					$bankInternalID = getData($sapBankData)[0]->sap_bank_id;
				}
			}
		}

		return $bankInternalID;
	}
}
