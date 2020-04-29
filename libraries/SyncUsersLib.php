<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * This library contains the logic used to perform data synchronization between FHC and SAP Business by Design
 */
class SyncUsersLib
{
	const SAP_USERS_CREATE = 'SAPUsersCreate';
	const SAP_USERS_UPDATE = 'SAPUsersUpdate';

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
	 * 
	 */
	public function createUsers($users)
	{
		if (isEmptyArray($users)) return success('No users to be created');

		// Remove the already created users
		$diffUsers = $this->_removeCreatedUsers($users);

		if (isError($diffUsers)) return $diffUsers;
		if (!hasData($diffUsers)) return success('No users to be created after diff');

		// Retrieves users data from database
		$dbModel = new DB_Model();

		$dbUsersData = $dbModel->execReadOnlyQuery('
			SELECT p.person_id,
				p.nachname AS surname,
				p.vorname AS name,
				k.kontakt AS email
			  FROM public.tbl_person p
			  JOIN public.tbl_kontakt k USING(person_id)
			WHERE k.kontakttyp = \'email\'
			  AND k.zustellung = true
			  AND k.person_id IN ?
		', array(getData($diffUsers)));

		if (isError($dbUsersData)) return $dbUsersData;
		if (!hasData($dbUsersData)) return error('The provided person ids are not present in database');

		// Loops through users data 
		foreach (getData($dbUsersData) as $userData)
		{
			// Checks if the current user is already present in SAP
			$userExists = $this->_userExistsByEmail($userData->email);

			if (isError($userExists)) return $userExists;
	
			// If the current user is not present in SAP 
			if (getData($userExists) == 0)
			{
				// Then create it!
				$manageCustomerResult = $this->_ci->ManageCustomerInModel->MaintainBundle_V1(
					array(
						'BasicMessageHeader' => array(
							'ID' => $userData->person_id,
							'UUID' => $this->_generateUUID()
						),
						'Customer' => array(
							'actionCode' => '01',
							'addressInformationListCompleteTransmissionIndicator' => true,
							'CategoryCode' => 1,
							'ProspectIndicator' => false,
							'CustomerIndicator' => true,
							'LifeCycleStatusCode' => 2,
							'Person' => array(
								'GivenName' => $userData->name,
								'FamilyName' => $userData->surname,
								'BirthName' => $userData->surname
							),
							'VerbalCommunicationLanguageCode' => 'EN',
							'ContactAllowedCode' => 3,
							'LegalCompetenceIndicator' => true,
							'ABCClassificationCode' => 'A',
							'AddressInformation' => array(
								'actionCode' => '01',
								'addressInformationListCompleteTransmissionIndicator' => true,
								'ObjectNodeSenderTechnicalID' => '002',
								'AddressUsage' => array(
									'ObjectNodeSenderTechnicalID' => '003',
									'AddressUsageCode' => 'XXDEFAULT',
									'DefaultIndicator' => false
								),
								'Address' => array(
									'EmailURI' => $userData->email,
									'PreferredCommunicationMediumTypeCod' => 'LET',
									'PostalAddress' => array(
										'CountryCode' => 'AT'
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
					else // ...otherwise return the error
					{
						return error('SAP did not return the InterlID');
					}
				}
				else // ...otherwise return it
				{
					return $manageCustomerResult;
				}
			}
		}

		return success('The procedure ended with no errors');
	}

	/**
	 * 
	 */
	public function updateUsers($users)
	{
		if (isEmptyArray($users)) return success('No users to be updated');

		// Remove the already created users
		$diffUsers = $this->_removeNotCreatedUsers($users);

		if (isError($diffUsers)) return $diffUsers;
		if (!hasData($diffUsers)) return success('No users to be created after diff');

		$dbModel = new DB_Model();

		// Retrieves users data from database
		$dbUsersData = $dbModel->execReadOnlyQuery('
			SELECT p.person_id,
				p.nachname AS surname,
				p.vorname AS name,
				k.kontakt AS email,
				s.sap_id
			  FROM public.tbl_person p
			  JOIN public.tbl_kontakt k USING(person_id)
			  JOIN sync.tbl_sap_studierende s USING(person_id)
			WHERE k.kontakttyp = \'email\'
			  AND k.zustellung = true
			  AND k.person_id IN ?
		', array(getData($diffUsers)));

		if (isError($dbUsersData)) return $dbUsersData;
		if (!hasData($dbUsersData)) return error('The provided person ids are not present in database');

		// Loops through users data 
		foreach (getData($dbUsersData) as $userData)
		{
			// Checks if the current user is already present in SAP
			$userExists = $this->_userExistsByEmail($userData->email);

			if (isError($userExists)) return $userExists;
	
			// If the current user is present in SAP 
			if (getData($userExists) != 0)
			{
				$manageCustomerResult = $this->_ci->ManageCustomerInModel->MaintainBundle_V1(
					array(
						'BasicMessageHeader' => array(
							'ID' => $userData->sap_id
						),
						'Customer' => array(
							'actionCode' => '02',
							'addressInformationListCompleteTransmissionIndicator' => false,
							'ObjectNodeSenderTechnicalID' => '001',
							'CategoryCode' => 1,
							'ProspectIndicator' => false,
							'CustomerIndicator' => true,
							'LifeCycleStatusCode' => 2,
							'ContactPerson' => array(
								'actionCode' => '02',
								'workplaceTelephoneListCompleteTransmissionIndicator' => true,
								'addressInformationListCompleteTransmissionIndicator' => false,
								'ObjectNodeSenderTechnicalID' => '208',
								'GivenName' => $userData->name,
								'FamilyName' => $userData->surname,
								'BirthName' => $userData->surname
							)
						)
					)
				);

				var_dump($manageCustomerResult);
				
				// If no error occurred...
				if (isError($manageCustomerResult)) return $manageCustomerResult;
			}
		}

		return success('The procedure ended with no errors');
	}

	// --------------------------------------------------------------------------------------------
	// Private methods

	/**
	 *
	 */
	private function _generateUUID()
	{
		$data = openssl_random_pseudo_bytes(16);

		$data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
		$data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}

	/**
	 *
	 */
	private function _removeCreatedUsers($users)
	{
		return $this->_addOrRemoveUsers($users, false);
	}

	/**
	 *
	 */
	private function _removeNotCreatedUsers($users)
	{
		return $this->_addOrRemoveUsers($users, true);
	}

	/**
	 *
	 */
	private function _addOrRemoveUsers($users, $initialFoundValue)
	{
		$diffUsersArray = array();

		$dbModel = new DB_Model();

		$dbSyncdUsers = $dbModel->execReadOnlyQuery('
			SELECT s.person_id
			  FROM sync.tbl_sap_studierende s
			WHERE s.person_id IN ?
		', array($users));

		if (isError($dbSyncdUsers)) return $dbSyncdUsers;

		for ($i = 0; $i < count($users); $i++)
		{
			$found = $initialFoundValue;

			if (hasData($dbSyncdUsers))
			{
				foreach (getData($dbSyncdUsers) as $dbSyncUser)
				{
					if ($users[$i] == $dbSyncUser->person_id)
					{
						$found = !$initialFoundValue;
						break;
					}
				}
			}

			if (!$found) $diffUsersArray[] = $users[$i];
		}

		return success($diffUsersArray);
	}

	/**
	 *
	 */
	private function _userExistsByEmail($email)
	{
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

		$queryCustomer = getData($queryCustomerResult);

		if (isset($queryCustomer->ProcessingConditions)
			&& isset($queryCustomer->ProcessingConditions->ReturnedQueryHitsNumberValue))
		{
			return success($queryCustomer->ProcessingConditions->ReturnedQueryHitsNumberValue);
		}
		else
		{
			return error('The returned SAP object is not correctly structured');
		}
	}
}

