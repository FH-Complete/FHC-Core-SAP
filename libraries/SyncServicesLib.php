<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * This library contains the logic used to perform data synchronization between FHC and SAP Business by Design
 */
class SyncServicesLib
{
	// Jobs types used by this lib
	const SAP_SERVICES_CREATE = 'SAPServicesCreate';
	const SAP_SERVICES_UPDATE = 'SAPServicesUpdate';

	// Prefix for SAP SOAP id calls
	const CREATE_SERVICE_PREFIX = 'CS';
	const UPDATE_SERVICE_PREFIX = 'US';

	const DEFAULT_LANGUAGE_ISO = 'DE'; // Default language ISO
	const ENGLISH_LANGUAGE_ISO = 'EN'; // English language ISO

	private $_ci; // Code igniter instance

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		// Loads QueryServiceProductValuationDataInModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SAPCoreAPI/QueryServiceProductValuationDataIn_model', 'QueryServiceProductValuationDataInModel');
		// Loads ManageServiceProductInModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SAPCoreAPI/ManageServiceProductIn_model', 'ManageServiceProductInModel');
		// Loads ManageServiceProductValuationDataInModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SAPCoreAPI/ManageServiceProductValuationDataIn_model', 'ManageServiceProductValuationDataInModel');

		// Loads SAPServicesModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SAPServices_model', 'SAPServicesModel');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Creates new services in SAP using the array of person ids given as parameter
	 */
	public function create($users)
	{
		// If the given array of person ids is empty stop here
		if (isEmptyArray($users)) return success('No services to be created');

		// Array used to store non blocking error messages to be returned back and then logged
		$nonBlockingErrorsArray = array();

		// Remove the already created services performing a diff between the given person ids and those present
		// in the sync table. If no errors and the diff array is not empty then continues, otherwise a message
		// is returned
		$diffUsers = $this->_removeCreatedUsers($users);
		if (isError($diffUsers)) return $diffUsers;
		if (!hasData($diffUsers)) return success('No services to be created after diff');

		// Retrieves all services data for the given users
		$servicesAllData = $this->_getAllServicesData($diffUsers);

		if (isError($servicesAllData)) return $servicesAllData;
		if (!hasData($servicesAllData)) return error('No services data available for the given users');

		// Loops through services data 
		foreach (getData($servicesAllData) as $serviceData)
		{
			// If the name is not set for this user...
			if (isEmptyString($serviceData->name))
			{
				$nonBlockingErrorsArray[] = 'No surname set for user: '.$serviceData->person_id;
				continue; // ...and continue to the next one
			}

			// Checks if the current service is already present in SAP
			$serviceDataSAP = $this->_serviceExistsByDescriptionSAP($serviceData->description);

			if (isError($serviceDataSAP)) return $serviceDataSAP;

			// If the current user is not present in SAP 
			if (!hasData($serviceDataSAP))
			{
				// Create service
				$createResult = $this->_manageServiceProductIn($serviceData->description, $serviceData->person_id, $nonBlockingErrorsArray);
				if (isError($createResult)) return $createResult; // if fatal error

				// Updated valuations
				// If the previous call was successful -> no blocking errors, no fatal errors
				if (hasData($createResult))
				{
					// Get the previously created service id
					// NOTE: Here is safe because was checked earlier in _manageServiceProductIn
					$serviceId = getData($createResult)->ServiceProduct->InternalID->_;

					// Activate valuation for GMBH
					$valuationResult = $this->_manageServiceProductValuationDataIn($serviceId, 'GMBH', 75, $nonBlockingErrorsArray);
					if (isError($valuationResult)) return $valuationResult; // if fatal error

					// Activate valuation for GST
					$valuationResult = $this->_manageServiceProductValuationDataIn($serviceId, 'GST', 65, $nonBlockingErrorsArray);
					if (isError($valuationResult)) return $valuationResult; // if fatal error
				}
				// otherwise non blocking error and continue with the next one 
			}
			else // if the service is already present in SAP
			{
				$nonBlockingErrorsArray[] = 'Service already present: '.$serviceData->description;
				continue;
			}
		}

		return success($nonBlockingErrorsArray);
	}

	/**
	 * Updates services data in SAP using the array of person ids given as parameter
	 */
	public function update($users)
	{
		if (isEmptyArray($users)) return success('No services to be updated');

		// Array used to store non blocking error messages to be returned back and then logged
		$nonBlockingErrorsArray = array();

		// Remove the already created services
		$diffUsers = $this->_removeNotCreatedUsers($users);

		if (isError($diffUsers)) return $diffUsers;
		if (!hasData($diffUsers)) return success('No services to be created after diff');

		// Retrieves all users data
		$servicesAllData = $this->_getAllServicesData($diffUsers);

		if (isError($servicesAllData)) return $servicesAllData;
		if (!hasData($servicesAllData)) return error('No data available for the given users');

		$dbModel = new DB_Model();

		// Loops through users data 
		foreach (getData($servicesAllData) as $serviceData)
		{
			// If the name is not set for this user...
			if (isEmptyString($serviceData->name))
			{
				$nonBlockingErrorsArray[] = 'No surname set for user: '.$serviceData->person_id;
				continue; // ...and continue to the next one
			}

			// Gets the SAP service id for the current user
			$sapIdResult = $dbModel->execReadOnlyQuery('
				SELECT s.sap_service_id
				  FROM sync.tbl_sap_services s
				WHERE s.person_id = ?
			', array($serviceData->person_id));

			if (isError($sapIdResult)) return $sapIdResult;
			if (!hasData($sapIdResult)) continue; // should never happen since it was checked earlier

			// Checks if the current service is already present in SAP
			$serviceDataSAP = $this->_serviceExistsByIdSAP(getData($sapIdResult)[0]->sap_service_id);

			if (isError($serviceDataSAP)) return $serviceDataSAP;

			// If the current service is present in SAP 
			if (hasData($serviceDataSAP))
			{
				$sapService = getData($serviceDataSAP); // get SAP service data
				
				// Then update it!
				$manageServiceResult = $this->_ci->ManageServiceProductInModel->MaintainBundle_V1(
					array(
						'BasicMessageHeader' => array(
							'ID' => generateUID(self::CREATE_SERVICE_PREFIX),
							'UUID' => generateUUID()
						),
						'ServiceProduct' => array(
							'actionCode' => '02',
							'descriptionListCompleteTransmissionIndicator' => true,
							'InternalID' => getData($sapIdResult)[0]->sap_service_id,
							'Description' => array(
								0 => array(
									'Description' => array(
										'_' => $serviceData->description,
										'languageCode' => 'DE'
									)
								),
								1 => array(
									'Description' => array(
										'_' => $serviceData->description,
										'languageCode' => 'EN'
									)
								)
							)
						)
					)
				);

				// If no error occurred...
				if (!isError($manageServiceResult))
				{
					// SAP data
					$manageService = getData($manageServiceResult);

					// If data structure is ok...
					if (isset($manageService->ServiceProduct) && isset($manageService->ServiceProduct->InternalID))
					{
						// Everything is fine!
					}
					else // ...otherwise store a non blocking error and continue with the next user
					{
						// If it is present a description from SAP then use it
						if (isset($manageService->Log) && isset($manageService->Log->Item)
							&& isset($manageService->Log->Item))
						{
							if (!isEmptyArray($manageService->Log->Item))
							{
								foreach ($manageService->Log->Item as $item)
								{
									if (isset($item->Note)) $nonBlockingErrorsArray[] = $item->Note.' for user: '.$serviceData->person_id;
								}
							}
							elseif ($manageService->Log->Item->Note)
							{
								$nonBlockingErrorsArray[] = $manageService->Log->Item->Note.' for user: '.$serviceData->person_id;
							}
						}
						else
						{
							// Default non blocking error
							$nonBlockingErrorsArray[] = 'SAP did not return the InterlID for user: '.$serviceData->person_id;
						}
						continue;
					}
				}
				else // ...otherwise return it
				{
					return $manageServiceResult;
				}
			}
			else // if the service is already present in SAP
			{
				$nonBlockingErrorsArray[] = 'Service is not present: '.$serviceData->description;
				continue;
			}
		}

		return success($nonBlockingErrorsArray);
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
			  FROM sync.tbl_sap_services s
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
	 * Retrieves all the data needed to create/update services on SAP side
	 */
	private function _getAllServicesData($users)
	{
		$servicesAllDataArray = array(); // returned array

		// Retrieves services data from database
		$dbModel = new DB_Model();

		$dbServicesData = $dbModel->execReadOnlyQuery('
			SELECT p.person_id,
				p.nachname AS surname,
				p.vorname AS name,
				m.personalnummer AS personalnumber
			  FROM public.tbl_person p
			  JOIN public.tbl_benutzer b USING(person_id)
			  JOIN public.tbl_mitarbeiter m ON(b.uid = m.mitarbeiter_uid)
			 WHERE p.person_id IN ?
		', array(getData($users)));

		if (isError($dbServicesData)) return $dbServicesData;
		if (!hasData($dbServicesData)) return error('The provided person ids are not present in database');

		// Loops through services data
		foreach (getData($dbServicesData) as $userPersonalData)
		{
			// Description
			$userPersonalData->description = sprintf(
				'%s %s %s',
				$userPersonalData->name,
				$userPersonalData->surname,
				$userPersonalData->personalnumber
			);

			// Stores all data for the current user
			$servicesAllDataArray[] = $userPersonalData; 
		}

		return success($servicesAllDataArray); // everything was fine!
	}

	/**
	 * Checks on SAP side if a service already exists with the given description
	 * Returns a success object with the found service data, otherwise with a false value
	 * In case of error then an error object is returned
	 */
	private function _serviceExistsByDescriptionSAP($description)
	{
		// Calls SAP to find a service with the given description
		$queryServiceResult = $this->_ci->QueryServiceProductValuationDataInModel->findByElements(
			array(
				'ServiceProductSelectionByElements' => array(
					'SelectionByDescription' => array(
						'InclusionExclusionCode' => 'I',
						'IntervalBoundaryTypeCode' => 1,
						'LowerBoundaryDescription' => $description
						//'UpperBoundaryDescription' => null
					)
				),
				'ProcessingConditions' => array(
					'QueryHitsUnlimitedIndicator' => true
					//'QueryHitsMaximumNumberValue' => 10
				)
			)
		);

		if (isError($queryServiceResult)) return $queryServiceResult;
		if (!hasData($queryServiceResult)) return error('Something went wrong while checking if a service is present using a description');

		// Get data from the returned object
		$queryService = getData($queryServiceResult);

		// Checks the structure of the returned object
		if (isset($queryService->ProcessingConditions)
			&& isset($queryService->ProcessingConditions->ReturnedQueryHitsNumberValue))
		{
			// Returns the service object a user is present in SAP with the given email, otherwise an empty success
			if ($queryService->ProcessingConditions->ReturnedQueryHitsNumberValue > 0)
			{
				return success($queryService->ServiceProduct);
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
	 * Checks on SAP side if a service already exists with the given id
	 * Returns a success object with the found service data, otherwise with a false value
	 * In case of error then an error object is returned
	 */
	private function _serviceExistsByIdSAP($id)
	{
		// Calls SAP to find a service with the given service id
		$queryServiceResult = $this->_ci->QueryServiceProductValuationDataInModel->findByElements(
			array(
				'ServiceProductSelectionByElements' => array(
					'SelectionByInternalID' => array(
						'InclusionExclusionCode' => 'I',
						'IntervalBoundaryTypeCode' => 1,
						'LowerBoundaryInternalID' => $id
						//'UpperBoundaryDescription' => null
					)
				),
				'ProcessingConditions' => array(
					'QueryHitsUnlimitedIndicator' => true
					//'QueryHitsMaximumNumberValue' => 10
				)
			)
		);

		if (isError($queryServiceResult)) return $queryServiceResult;
		if (!hasData($queryServiceResult)) return error('Something went wrong while checking if a service is present using an id');

		// Get data from the returned object
		$queryService = getData($queryServiceResult);

		// Checks the structure of the returned object
		if (isset($queryService->ProcessingConditions)
			&& isset($queryService->ProcessingConditions->ReturnedQueryHitsNumberValue))
		{
			// Returns the service object a user is present in SAP with the given email, otherwise an empty success
			if ($queryService->ProcessingConditions->ReturnedQueryHitsNumberValue > 0)
			{
				return success($queryService->ServiceProduct);
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
	 *
	 */
	private function _manageServiceProductIn($description, $person_id, &$nonBlockingErrorsArray)
	{
		// Then create it!
		$manageServiceResult = $this->_ci->ManageServiceProductInModel->MaintainBundle_V1(
			array(
				'BasicMessageHeader' => array(
					'ID' => generateUID(self::CREATE_SERVICE_PREFIX),
					'UUID' => generateUUID()
				),
				'ServiceProduct' => array(
					'actionCode' => '01',
					'descriptionListCompleteTransmissionIndicator' => true,
					'salesListCompleteTransmissionIndicator' => true,
					'deviantTaxClassificationListCompleteTransmissionIndicator' => true,
					'valuationListCompleteTransmissionIndicator' => true,
					'ProductCategoryID' => 'FE',
					'BaseMeasureUnitCode' => 'HUR',
					'ValuationMeasureUnitCode' => 'HUR',
					'Description' => array(
						0 => array(
							'Description' => array(
								'_' => $description,
								'languageCode' => 'DE'
							)
						),
						1 => array(
							'Description' => array(
								'_' => $description,
								'languageCode' => 'EN'
							)
						)
					),
					'Purchasing' => array(
						'purchasingNoteListCompleteTransmissionIndicator' => true,
						'LifeCycleStatusCode' => 1,
						'PurchasingMeasureUnitCode' => 'HUR'
					),
					'Sales' => array(
						0 => array(
							'SalesOrganisationID' => 'GMBH',
							'DistributionChannelCode' => array('_' => '01'),
							'LifeCycleStatusCode' => 2,
							'SalesMeasureUnitCode' => 'HUR',
							'ItemGroupCode' => 'PBTM'
						),
						1 => array(
							'SalesOrganisationID' => 'GF20',
							'DistributionChannelCode' => array('_' => '01'),
							'LifeCycleStatusCode' => 2,
							'SalesMeasureUnitCode' => 'HUR',
							'ItemGroupCode' => 'PBTM'
						),
						2 => array(
							'SalesOrganisationID' => '100003',
							'DistributionChannelCode' => array('_' => '01'),
							'LifeCycleStatusCode' => 2,
							'SalesMeasureUnitCode' => 'HUR',
							'ItemGroupCode' => 'PBTM'
						)
					),
					'DeviantTaxClassification' => array(
						'CountryCode' => 'AT',
						'RegionCode' => array(
							0 => '',
							'listID' => 'AT'
						),
						'TaxTypeCode' => array(
							'_' => 1,
							'listID' => 'AT'
						),
						'TaxRateTypeCode' => array(
							'_' => 1,
							'listID' => 'AT'
						)
					),
					'Valuation' => array(
						0 => array(
							'CompanyID' => 'GMBH',
							'LifeCycleStatusCode' => 1
						),
						1 => array(
							'CompanyID' => 'GST',
							'LifeCycleStatusCode' => 1
						)
					)
				)
			)
		);

		// If no error occurred...
		if (!isError($manageServiceResult))
		{
			// SAP data
			$manageService = getData($manageServiceResult);

			// If data structure is ok...
			if (isset($manageService->ServiceProduct) && isset($manageService->ServiceProduct->InternalID)
				&& isset($manageService->ServiceProduct->InternalID->_))
			{
				// Store in database the couple person_id sap_service_id
				$insert = $this->_ci->SAPServicesModel->insert(
					array(
						'person_id' => $person_id,
						'sap_service_id' => $manageService->ServiceProduct->InternalID->_
					)
				);

				// If database error occurred then return it
				if (isError($insert)) return $insert;
				// Returns the result from SAP
				return $manageServiceResult; 
			}
			else // ...otherwise store a non blocking error...
			{
				// If it is present a description from SAP then use it
				if (isset($manageService->Log) && isset($manageService->Log->Item)
					&& isset($manageService->Log->Item))
				{
					if (!isEmptyArray($manageService->Log->Item))
					{
						foreach ($manageService->Log->Item as $item)
						{
							if (isset($item->Note)) $nonBlockingErrorsArray[] = $item->Note.' for user: '.$person_id;
						}
					}
					elseif ($manageService->Log->Item->Note)
					{
						$nonBlockingErrorsArray[] = $manageService->Log->Item->Note.' for user: '.$person_id;
					}
				}
				else
				{
					// Default non blocking error
					$nonBlockingErrorsArray[] = 'SAP did not return the InterlID for user: '.$person_id;
				}

				// ...and return an empty success
				return success();
			}
		}
		else // ...otherwise return it
		{
			return $manageServiceResult;
		}
	}
	
	/**
	 * Once the service is created its valuations are still inactive, this method activates a single valuation
	 * specified by service id and company id
	 */
	private function _manageServiceProductValuationDataIn($sap_service_id, $company_id, $amount, &$nonBlockingErrorsArray)
	{
		$manageServiceProductValuationResult = $this->_ci->ManageServiceProductValuationDataInModel->MaintainBundle(
			array(
				'BasicMessageHeader' => array(
					'ID' => generateUID(self::CREATE_SERVICE_PREFIX),
				),
				'ServiceProductValuationData' => array(
					'actionCode' => '04',
					'ServiceProductInternalID' => $sap_service_id,
					'CompanyID' => $company_id,
					'AccountDeterminationGroupCode' => 5000,
					'CostRate' => array(
						'actionCode' => '04',
						'SetOfBooksID' => 'FH01',
						'StartDate' => date('Y-m-d'),
						'Amount' => array(
							'_' => $amount,
							'currencyCode' => 'EUR'
						),
						'Quantity' => array(
							'_' => 1,
							'unitCode' => 'HUR'
						)
					),
					'FinancialProcessInformation' => array(
						'actionCode' => '02',
						'LifeCycleStatusCode' => 2
					)
				)
			)
		);

		// If no error occurred...
		if (!isError($manageServiceProductValuationResult))
		{
			// SAP data
			$manageServiceProductValuation = getData($manageServiceProductValuationResult);

			// If data structure is ok...
			if (isset($manageServiceProductValuation->ServiceProductValuationData)
				&& isset($manageServiceProductValuation->ServiceProductValuationData->ServiceProductInternalID)
				&& isset($manageServiceProductValuation->ServiceProductValuationData->CompanyID))
			{
				// Returns the result from SAP
				return $manageServiceProductValuationResult; 
			}
			else // ...otherwise store a non blocking error...
			{
				// If it is present a description from SAP then use it
				if (isset($manageServiceProductValuation->Log) && isset($manageServiceProductValuation->Log->Item)
					&& isset($manageServiceProductValuation->Log->Item))
				{
					if (!isEmptyArray($manageServiceProductValuation->Log->Item))
					{
						foreach ($manageServiceProductValuation->Log->Item as $item)
						{
							if (isset($item->Note)) $nonBlockingErrorsArray[] = $item->Note.' for service: '.$sap_service_id;
						}
					}
					elseif ($manageServiceProductValuation->Log->Item->Note)
					{
						$nonBlockingErrorsArray[] = $manageServiceProductValuation->Log->Item->Note.' for service: '.$sap_service_id;
					}
				}
				else
				{
					// Default non blocking error
					$nonBlockingErrorsArray[] = 'SAP did not return the InterlID for user: '.$sap_service_id;
				}

				// ...and return an empty success
				return success();
			}
		}
		else // ...otherwise return it
		{
			return $manageServiceProductValuationResult;
		}
	}
}

