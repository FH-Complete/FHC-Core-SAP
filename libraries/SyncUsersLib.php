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

	const DEFAULT_NATION_ISO = 'AT'; // Default nation ISO

	// Genders
	const FHC_GENDER_MALE = 'm';
	const FHC_GENDER_FEMALE = 'w';
	const SAP_GENDER_MALE = 1;
	const SAP_GENDER_FEMALE = 2;
	const SAP_GENDER_UNKNOWN = 3;

	// Config entries for messaging
	const CFG_OU_RECEIVERS_PRIVATE = 'ou_receivers_private';

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

	private $_ci; // Code igniter instance

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		// Loads QueryCustomerInModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SAPCoreAPI/QueryCustomerIn_model', 'QueryCustomerInModel');
		// Loads ManageCustomerInModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SAPCoreAPI/ManageCustomerIn_model', 'ManageCustomerInModel');

		// Loads SAPStudierendeModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SAPStudierende_model', 'SAPStudierendeModel');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods
	
	/**
	 * Creates new users in SAP using the array of person ids given as parameter
	 */
	public function createUsers($users)
	{
		// If the given array of person ids is empty stop here
		if (isEmptyArray($users)) return success('No users to be created');

		// Array used to store non blocking error messages to be returned back and then logged
		$nonBlockingErrorsArray = array();

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
				$nonBlockingErrorsArray[] = 'Was not possible to find a valid email address for user: '.$userData->person_id;
				continue; // ...and continue to the next one
			}

			// Checks if the current user is already present in SAP
			$userDataSAP = $this->_userExistsByEmailSAP($userData->email);

			if (isError($userDataSAP)) return $userDataSAP;

			// If the current user is not present in SAP 
			if (!hasData($userDataSAP))
			{
				// Then create it!
				$manageCustomerResult = $this->_ci->ManageCustomerInModel->MaintainBundle_V1(
					array(
						'BasicMessageHeader' => array(
							'ID' => $this->_generateUID(self::CREATE_USER_PREFIX),
							'UUID' => $this->_generateUUID()
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
									'AddressUsageCode' => 'XXDEFAULT'
								),
								'Address' => array(
									'EmailURI' => $userData->email,
									'PreferredCommunicationMediumTypeCode' => 'INT',
									'PostalAddress' => array(
										'CountryCode' => $userData->country
									)
								)
							),
							'CommunicationArrangement' => array(
								0 => array(
									'CompoundServiceInterfaceCode' => '108',
									'EnabledIndicator' => true,
									'CommunicationMediumTypeCode' => 'INT'
								),
								1 => array(
									'CompoundServiceInterfaceCode' => '11',
									'EnabledIndicator' => true,
									'CommunicationMediumTypeCode' => 'INT'
								),   
								2 => array(
									'CompoundServiceInterfaceCode' => '27',
									'EnabledIndicator' => true,
									'CommunicationMediumTypeCode' => 'INT'
								),   
								3 => array(
									'CompoundServiceInterfaceCode' => '28',
									'EnabledIndicator' => true,
									'CommunicationMediumTypeCode' => 'INT'
								),
								4 => array(
									'CompoundServiceInterfaceCode' => '46',
									'EnabledIndicator' => true,
									'CommunicationMediumTypeCode' => 'INT'
								),
								5 => array(
									'CompoundServiceInterfaceCode' => '992',
									'EnabledIndicator' => true,
									'CommunicationMediumTypeCode' => 'INT'
								)
							)
						)
					)
				);

				// If no error occurred...
				if (!isError($manageCustomerResult))
				{
					// SAP data
					$manageCustomer = getData($manageCustomerResult);

					// If data structure is ok...
					if (isset($manageCustomer->Customer) && isset($manageCustomer->Customer->InternalID))
					{
						// Store in database the couple person_id sap_id
						$insert = $this->_ci->SAPStudierendeModel->insert(
							array(
								'person_id' => $userData->person_id,
								'sap_id' => $manageCustomer->Customer->InternalID
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
									if (isset($item->Note)) $nonBlockingErrorsArray[] = $item->Note.' for user: '.$userData->person_id;
								}
							}
							elseif ($manageCustomer->Log->Item->Note)
							{
								$nonBlockingErrorsArray[] = $manageCustomer->Log->Item->Note.' for user: '.$userData->person_id;
							}
						}
						else
						{
							// Default non blocking error
							$nonBlockingErrorsArray[] = 'SAP did not return the InterlID for user: '.$userData->person_id;
						}
						continue;
					}
				}
				else // ...otherwise return it
				{
					return $manageCustomerResult;
				}
			}
		}

		return success($nonBlockingErrorsArray);
	}

	/**
	 * Updates users data in SAP using the array of person ids given as parameter
	 */
	public function updateUsers($users)
	{
		if (isEmptyArray($users)) return success('No users to be updated');

		// Array used to store non blocking error messages to be returned back and then logged
		$nonBlockingErrorsArray = array();

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
				$nonBlockingErrorsArray[] = 'Was not possible to find a valid email address for user: '.$userData->person_id;
				continue; // ...and continue to the next one
			}

			// Gets the SAP id for the current user
			$sapIdResult = $dbModel->execReadOnlyQuery('
				SELECT s.sap_id
				  FROM sync.tbl_sap_studierende s
				WHERE s.person_id = ?
			', array($userData->person_id));

			if (isError($sapIdResult)) return $sapIdResult;
			if (!hasData($sapIdResult)) continue; // should never happen since it was checked earlier

			// Checks if the current user is already present in SAP
			$userDataSAP = $this->_userExistsByEmailSAP($userData->email);

			if (isError($userDataSAP)) return $userDataSAP;
	
			// If the current user is present in SAP 
			if (hasData($userDataSAP))
			{
				$sapCustomer = getData($userDataSAP); // get SAP customer data
				
				// Get the AddressInformation UUID
				if (isset($sapCustomer->AddressInformation)
					&& isset($sapCustomer->AddressInformation->UUID)
					&& isset($sapCustomer->AddressInformation->UUID->_))
				{
					$userData->addressInformationUUID = $sapCustomer->AddressInformation->UUID->_;
				}
				// Should never happen, just to be shure
				// In case is not possible to retrieve the AddressInformation UUID the call would fail anyway
				// better to skip to the next user
				else
				{
					$nonBlockingErrorsArray[] = 'Was no possible to retrieve the AddressInformation UUID for user '.$userData->person_id;
					continue;
				}

				// Then update it!
				$manageCustomerResult = $this->_ci->ManageCustomerInModel->MaintainBundle_V1(
					array(
						'BasicMessageHeader' => array(
							'ID' => $this->_generateUID(self::CREATE_USER_PREFIX),
							'UUID' => $this->_generateUUID()
						),
						'Customer' => array(
							'actionCode' => '02',
							'addressInformationListCompleteTransmissionIndicator' => false,
							'InternalID' => getData($sapIdResult)[0]->sap_id,
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
									'EmailURI' => $userData->email,
									'PostalAddress' => array(
										'CountryCode' => $userData->country
									)
								)
							)
						)
					)
				);

				// If no error occurred...
				if (!isError($manageCustomerResult))
				{
					// SAP data
					$manageCustomer = getData($manageCustomerResult);

					// If data structure is ok...
					if (isset($manageCustomer->Customer) && isset($manageCustomer->Customer->InternalID))
					{
						// Everything is fine!
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
									if (isset($item->Note)) $nonBlockingErrorsArray[] = $item->Note.' for user: '.$userData->person_id;
								}
							}
							elseif ($manageCustomer->Log->Item->Note)
							{
								$nonBlockingErrorsArray[] = $manageCustomer->Log->Item->Note.' for user: '.$userData->person_id;
							}
						}
						else
						{
							// Default non blocking error
							$nonBlockingErrorsArray[] = 'SAP did not return the InterlID for user: '.$userData->person_id;
						}
						continue;
					}
				}
				else // ...otherwise return it
				{
					return $manageCustomerResult;
				}
			}
		}

		return success($nonBlockingErrorsArray);
	}

	// --------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Generates a unique UUID
	 */
	private function _generateUUID()
	{
		$data = openssl_random_pseudo_bytes(16);

		$data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
		$data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}

	/**
	 * Generate a (almost) unique id to be used as id of each SAP SOAP call
	 * Using uniqid here should be fine
	 */
	private function _generateUID($prefix)
	{
		return uniqid($prefix, true);
	}

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
			  FROM sync.tbl_sap_studierende s
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
			SELECT p.person_id,
				p.nachname AS surname,
				p.vorname AS name,
				p.anrede AS title,
				s.locale AS language,
				p.geschlecht AS gender
			  FROM public.tbl_person p
		     LEFT JOIN public.tbl_sprache s USING(sprache)
			 WHERE p.person_id IN ?
		', array(getData($users)));

		if (isError($dbUsersPersonalData)) return $dbUsersPersonalData;
		if (!hasData($dbUsersPersonalData)) return error('The provided person ids are not present in database');

		// Loops through users personal data 
		foreach (getData($dbUsersPersonalData) as $userPersonalData)
		{
			// -------------------------------------------------------------------------------------------
			// Gender
			if ($userPersonalData->gender == self::FHC_GENDER_MALE)
			{
				// Male
				$userPersonalData->gender = self::SAP_GENDER_MALE;
			}
			elseif ($userPersonalData->gender == self::FHC_GENDER_FEMALE)
			{
				// Female
				$userPersonalData->gender = self::SAP_GENDER_FEMALE;
			}
			else // otherwise
			{
				$userPersonalData->gender = self::SAP_GENDER_UNKNOWN;
			}

			// -------------------------------------------------------------------------------------------
			// Language
			if ($userPersonalData->language == self::ENGLISH_LANGUAGE)
			{
				$userPersonalData->language = self::ENGLISH_LANGUAGE_ISO;
			}
			else
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
			if (hasData($addressResult)) // if a private email was found
			{
				$userAllData->country = getData($addressResult)[0]->iso3166_1_a2;
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
			if (hasData($kontaktResult)) // if a private email was found
			{
				$userAllData->email = getData($kontaktResult)[0]->kontakt;
			}

			// Get the last prestudent for the current user
			$this->_ci->load->model('crm/Prestudent_model', 'PrestudentModel');
			$resultLastPrestudent = $this->_ci->PrestudentModel->getLastPrestudent($userPersonalData->person_id);

			if (isError($resultLastPrestudent)) return $resultLastPrestudent;
			if (hasData($resultLastPrestudent))
			{
				// Get the prestudentstatus for the just obtained prestudent
				$this->_ci->load->model('crm/Prestudentstatus_model', 'PrestudentstatusModel');
				$resultLastPrestudentstatus = $this->_ci->PrestudentstatusModel->loadWhere(getData($resultLastPrestudent)[0]->prestudent_id);

				if (isError($resultLastPrestudentstatus)) return $resultLastPrestudentstatus;

				// If there are no prestudents for the current user, or the current user is not a student...
				if (!hasData($resultLastPrestudentstatus))
				{
					// ...then use the private email -> fallback
					// ...and the classification is set to fallback
				}
				else // ...otherwise...
				{
					// Set classification
					if (getData($resultLastPrestudentstatus)[0]->status_kurzbz == self::PS_STUDENT)
					{
						$userAllData->classification = self::CLASSIFICATION_STUDENT;
					}
					elseif (getData($resultLastPrestudentstatus)[0]->status_kurzbz == self::PS_BEWERBER
						|| getData($resultLastPrestudentstatus)[0]->status_kurzbz == self::PS_AUFGECOMMENER)
					{
						$userAllData->classification = self::CLASSIFICATION_BEWERBER;
					}
					elseif (getData($resultLastPrestudentstatus)[0]->status_kurzbz == self::PS_ABSOLVENT)
					{
						$userAllData->classification = self::CLASSIFICATION_ALUMNI;
					}
					elseif (getData($resultLastPrestudentstatus)[0]->status_kurzbz == self::PS_INTERESSENT)
					{
						$userAllData->prospectIndicator = true;
					}
					// else fallback

					// ...get the UID to compose the email address
					$dbUIDResult = $dbModeal->execReadOnlyQuery('
						SELECT b.uid, sg.oe_kurzbz 
						  FROM public.tbl_benutzer b
						  JOIN public.tbl_prestudent ps USING (person_id)
					  	  JOIN public.tbl_studiengang sg USING (studiengang_kz)
						 WHERE ps.prestudent_id = ?
						   AND b.aktiv = TRUE
					', array(getData($resultLastPrestudentstatus)[0]->prestudent_id));

					if (isError($dbUIDResult)) return $dbUIDResult;

					// Loads message configuration
					$this->_ci->config->load('message');

					// If data are present in database and the organisation unit is NOT in the list
					// of organisation units that sent only to private emails
					if (hasData($dbUIDResult)
						&& array_search(getData($dbUIDResult)[0]->oe_kurzbz, $this->_ci->config->item(self::CFG_OU_RECEIVERS_PRIVATE)) === false)
					{
						$userAllData->email = getData($dbUIDResult)[0]->uid.'@'.DOMAIN;
					}
					// else no data are present in database -> use the private email -> fallback
				}
			}

			// Stores all data for the current user
			$usersAllDataArray[] = $userAllData; 
		}

		return success($usersAllDataArray); // everything was fine!
	}

	/**
	 * Checks on SAP side if a user already exists with the given email address
	 * Returns a success object with a true values if the user exists on SAP side, otherwise with a false value
	 * In case of error then an error object is returned
	 */
	private function _userExistsByEmailSAP($email)
	{
		// Calls SAP to find a user with the given email
		$queryCustomerResult = $this->_ci->QueryCustomerInModel->findByCommunicationData(
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

		if (isError($queryCustomerResult)) return $queryCustomerResult;
		if (!hasData($queryCustomerResult)) return error('Something went wrong while checking if a user is present using email adress');

		// Get data from the returned object
		$queryCustomer = getData($queryCustomerResult);

		// Checks the structure of the returned object
		if (isset($queryCustomer->ProcessingConditions)
			&& isset($queryCustomer->ProcessingConditions->ReturnedQueryHitsNumberValue))
		{
			// Returns the customer object a user is present in SAP with the given email, otherwise an empty success
			if ($queryCustomer->ProcessingConditions->ReturnedQueryHitsNumberValue > 0)
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
}

