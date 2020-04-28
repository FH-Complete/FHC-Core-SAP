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

		$dbModel = new DB_Model();

		$dbSyncdUsers = $dbModel->execReadOnlyQuery('
			SELECT s.person_id
			  FROM sync.tbl_sap_studierende s
			WHERE s.person_id IN ?
		', $users);
		if (isError($dbSyncdUsers)) return $dbSyncdUsers;

		$diffUsersArray = array();

		for ($i = 0; $i < count($users); $i++)
		{
			$found = false;

			foreach ($dbSyncdUsers as $dbSyncUser)
			{
				if ($user[$i] == $dbSyncUser->person_id)
				{
					$found = true;
					break;
				}
			}

			if (!$found) $diffUsersArray[] = $users[$i];
		}

		$dbUsersData = $dbModel->execReadOnlyQuery('
			SELECT p.person_id,
				p.nachname AS surname,
				p.vorname AS name,
				k.kontakt AS email
			  FROM public.tbl_person p
			  JOIN public.tbl_kontakt k USING(person_id)
			WHERE k.kontaktyp = \'email\'
			  AND k.zustellung = true
			  AND k.person_id IN ?
		', $diffUsersArray);
		if (isError($dbUsersData)) return $dbUsersData;

		foreach (getData($dbUsersData) as $userData)
		{
			$queryCustomerResult = $this->QueryCustomerInModel->findByCommunicationData(
				array(
					'CustomerSelectionByCommunicationData' => array(
						'SelectionByEmailURI' => array(
							'LowerBoundaryEmailURI' => $userData->kontakt,
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

			if (!hasData($queryCustomerResult))
			{
				$manageCustomerResult = $this->ManageCustomerInModel->MaintainBundle_V1(
					array(
						'BasicMessageHeader' => array(
							'ID' => $userData->person_id,
							'UUID' => $userData->person_id
						),
						'Customer' => array(
							'InternalID' => 'CPSENT',
							'CategoryCode' => 1,
							'ProspectIndicator' => false,
							'CustomerIndicator' => true,
							'LifeCycleStatusCode' => 2
						),
						'Person' => array(
							'GivenName' => $userData->name,
							'FamilyName' => $userData->surname
						),
						'VerbalCommunicationLanguageCode' => 'EN',
						'ContactAllowedCode' => 3,
						'LegalCompetenceIndicator' => true,
						'ABCClassificationCode' => 'A',
						'AddressInformation' => array(
							'ObjectNodeSenderTechnicalID' => '002',
							'AddressUsage' => array(
								'ObjectNodeSenderTechnicalID' => '003',
								'AddressUsageCode' => 'XXDEFAULT',
								'DefaultIndicator' => false
							),
							'Address' => array(
								'EmailURI' => $userData->email,
								'PreferredCommunicationMediumTypeCod' => 'LET'
								'PostalAddress' => array(
									'CountryCode' => 'AT'
								)
							)
						)
					)
				);
				
				if (!isError($manageCustomerResult)) $this->_ci->SAPStudierendeModel->insert($userData->person_id, $userData->person_id);
			}
		}
	}

	/**
	 * 
	 */
	public function updateUsers($users)
	{
		if (isEmptyArray($users)) return success('No users to be updated');

		$dbModel = new DB_Model();

		$dbSyncdUsers = $dbModel->execReadOnlyQuery('
			SELECT s.person_id
			  FROM sync.tbl_sap_studierende s
			WHERE s.person_id IN ?
		', $users);
		if (isError($dbSyncdUsers)) return $dbSyncdUsers;

		$diffUsersArray = array();

		for ($i = 0; $i < count($users); $i++)
		{
			$found = false;

			foreach ($dbSyncdUsers as $dbSyncUser)
			{
				if ($user[$i] == $dbSyncUser->person_id)
				{
					$found = true;
					break;
				}
			}

			if ($found) $diffUsersArray[] = $users[$i];
		}

		$dbUsersData = $dbModel->execReadOnlyQuery('
			SELECT p.person_id,
				p.nachname AS surname,
				p.vorname AS name,
				k.kontakt AS email
			  FROM public.tbl_person p
			  JOIN public.tbl_kontakt k USING(person_id)
			WHERE k.kontaktyp = \'email\'
			  AND k.zustellung = true
			  AND k.person_id IN ?
		', $diffUsersArray);
		if (isError($dbUsersData)) return $dbUsersData;

		foreach (getData($dbUsersData) as $userData)
		{
			$queryCustomerResult = $this->QueryCustomerInModel->findByCommunicationData(
				array(
					'CustomerSelectionByCommunicationData' => array(
						'SelectionByEmailURI' => array(
							'LowerBoundaryEmailURI' => $userData->kontakt,
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

			if (hasData($queryCustomerResult))
			{
				$manageCustomerResult = $this->ManageCustomerInModel->MaintainBundle_V1(
					array(
						'BasicMessageHeader' => array(
							'ID' => $userData->person_id
						),
						'Customer' => array(
							'ObjectNodeSenderTechnicalID' => '001',
							'InternalID' => 'CPSENT',
							'CategoryCode' => 1,
							'ProspectIndicator' => false,
							'CustomerIndicator' => true,
							'LifeCycleStatusCode' => 2
						),
						'ContactPerson' => array(
							'GivenName' => $userData->name,
							'FamilyName' => $userData->surname
						)
					)
				);
				
				if (!isError($manageCustomerResult)) $this->_ci->SAPStudierendeModel->insert($userData->person_id, $userData->person_id);
			}
		}
	}
}

