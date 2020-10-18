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

	// Config entry names
	const SERVICES_VALUATION_COMPANY_IDS = 'services_valuation_company_ids';
	const ACCOUNT_DETERMINATION_GROUP_CODE = 'services_account_determination_group_code';
	const SET_OF_BOOKS_ID = 'services_set_of_books_id';
	const SALES_ORGANISATION_ID = 'services_sales_organization_id';
	const ITEM_GROUP_CODE = 'services_item_group_code';
	const CATEGORY_GMBH = 'services_category_gmbh';
	const CATEGORY_NOT_GMBH = 'services_category_not_gmbh';
	const START_DATE = 'services_start_date';

	const GMBH_CONFIG_INDX = 'gmbh';
	const FHTW_CONFIG_INDX = 'fhtw';

	// Database oe values for FHTW and GMBH
	const GMBH_OE_VALUE = 'gmbh';
	const FHTW_OE_VALUE = 'gst';

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
			)
		);

		// Loads QueryServiceProductIn
		$this->_ci->load->model('extensions/FHC-Core-SAP/SOAP/QueryServiceProductIn_model', 'QueryServiceProductInModel');

		// Loads ManageServiceProductInModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SOAP/ManageServiceProductIn_model', 'ManageServiceProductInModel');
		// Loads ManageServiceProductValuationDataInModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SOAP/ManageServiceProductValuationDataIn_model', 'ManageServiceProductValuationDataInModel');

		// Loads SAPServicesModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SAPServices_model', 'SAPServicesModel');

		// Loads MessageTokenModel
		$this->_ci->load->model('system/MessageToken_model', 'MessageTokenModel');

		// Loads SyncPriceListsLib
		$this->_ci->load->library('extensions/FHC-Core-SAP/SyncPriceListsLib');
		// Loads SyncListPricesLib
		$this->_ci->load->library('extensions/FHC-Core-SAP/SyncListPricesLib');

		// Loads services configuration
		$this->_ci->config->load('extensions/FHC-Core-SAP/Services');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Return the raw result of SAP->QueryServiceProductIn->SelectionByDescription->LowerBoundaryDescription
	 */
	public function getServiceByDescription($description)
	{
		// Calls SAP to find a service with the given description
		return $this->_ci->QueryServiceProductInModel->findByElements(
			array(
				'ServiceProductSelectionByElements' => array(
					'SelectionByDescription' => array(
						'InclusionExclusionCode' => 'I',
						'IntervalBoundaryTypeCode' => 1,
						'LowerBoundaryDescription' => $description
					)
				),
				'ProcessingConditions' => array(
					'QueryHitsUnlimitedIndicator' => true
				)
			)
		);
	}

	/**
	 * Return the raw result of SAP->QueryServiceProductIn->SelectionByDescription->LowerBoundaryInternalID
	 */
	public function getServiceById($id)
	{
		// Calls SAP to find a service with the given service id
		return $this->_ci->QueryServiceProductInModel->findByElements(
			array(
				'ServiceProductSelectionByElements' => array(
					'SelectionByInternalID' => array(
						'InclusionExclusionCode' => 'I',
						'IntervalBoundaryTypeCode' => 1,
						'LowerBoundaryInternalID' => $id
					)
				),
				'ProcessingConditions' => array(
					'QueryHitsUnlimitedIndicator' => true
				)
			)
		);
	}

	/**
	 * Creates new services in SAP using the array of person ids given as parameter
	 */
	public function create($users)
	{
		// If the given array of person ids is empty stop here
		if (isEmptyArray($users)) return success('No services to be created');

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
			// If the stundensatz is not set for this user...
			$stundensatz = $serviceData->stundensatz;
			if (isEmptyString($stundensatz))
			{
				$this->_ci->loglib->logWarningDB('No stundensatz set for user: '.$serviceData->person_id);
				continue; // ...and continue to the next one
			}

			// If the name is not set for this user...
			if (isEmptyString($serviceData->name))
			{
				$this->_ci->loglib->logWarningDB('No surname set for user: '.$serviceData->person_id);
				continue; // ...and continue to the next one
			}

			// If the organization unit is null then skip this user...
			if (isEmptyString($serviceData->organization_unit))
			{
				$this->_ci->loglib->logWarningDB('No organization unit set for user: '.$serviceData->person_id);
				continue; // ...and continue to the next one
			}

			// Checks if the current service is already present in SAP
			$serviceDataSAP = $this->_serviceExistsByDescriptionSAP($serviceData->description);

			if (isError($serviceDataSAP)) return $serviceDataSAP;

			// If the current user is not present in SAP
			if (!hasData($serviceDataSAP))
			{
				// Create service
				$createResult = $this->_manageServiceProductIn(
					$serviceData->description,
					$serviceData->person_id,
					$serviceData->category,
					$serviceData->root_organization_unit
				);

				if (isError($createResult)) return $createResult; // if fatal error

				// Updated valuations
				// If the previous call was successful -> no blocking errors, no fatal errors
				if (hasData($createResult))
				{
					// Get the previously created service id
					// NOTE: Here is safe because was checked earlier in _manageServiceProductIn
					$serviceId = getData($createResult)->ServiceProduct->InternalID->_;

					// Get all company ids
					$companyIdsArray = $this->_ci->config->item(self::SERVICES_VALUATION_COMPANY_IDS);
					// Activate valuation for each company
					foreach ($companyIdsArray as $companyId)
					{
						// Activate valuation
						$valuationResult = $this->_manageServiceProductValuationDataIn(
							$serviceId,
							$companyId,
							$stundensatz
						);

						if (isError($valuationResult)) return $valuationResult; // if fatal error
					}

					// Price list & list price
					// If root_organization_unit is not null then it is possible to add this service to a price list and to a list price
					if ($serviceData->root_organization_unit != null)
					{
						// Price list
						$priceListId = '';
						// If the root organization unit is GMBH then add this service to the FHTW price list
						// NOTE: for price list the logic is inverted!
						if ($serviceData->root_organization_unit == self::GMBH_OE_VALUE)
						{
							$priceListId = ($this->_ci->config->item(SyncPriceListsLib::PRICE_LISTS_ID_FORMATS))[self::FHTW_CONFIG_INDX];
						}
						else // otherwise to the GMBH price list
						{
							$priceListId = ($this->_ci->config->item(SyncPriceListsLib::PRICE_LISTS_ID_FORMATS))[self::GMBH_CONFIG_INDX];
						}

						// Finally add this service to a price list
						$manageSalesPriceListInResult = $this->_ci->syncpricelistslib->addServiceToPriceList(
							$priceListId,
							$serviceId,
							$stundensatz
						);

						if (isError($manageSalesPriceListInResult)) return $manageSalesPriceListInResult; // if fatal error

						// List price
						$companyId = '';
						// If the root organization unit is GMBH then add this service to the GMBH list price
						if ($serviceData->root_organization_unit == self::GMBH_OE_VALUE)
						{
							$companyId = ($this->_ci->config->item(self::SERVICES_VALUATION_COMPANY_IDS))[self::GMBH_CONFIG_INDX];
						}
						else // otherwise to the FHTW list price
						{
							$companyId = ($this->_ci->config->item(self::SERVICES_VALUATION_COMPANY_IDS))[self::FHTW_CONFIG_INDX];
						}

						// Add a new list price that links this service to a list price
						$manageSalesListPriceInResult = $this->_ci->synclistpriceslib->manageProcurementPriceSpecificationIn(
							$companyId,
							$serviceId,
							$stundensatz
						);

						if (isError($manageSalesListPriceInResult)) return $manageSalesListPriceInResult; // if fatal error
					}
					else
					{
						$this->_ci->loglib->logWarningDB('Was not possible to find the root organization unit for this service: '.$serviceId);
					}
				}
				// otherwise non blocking error and continue with the next one
			}
			else // if the service is already present in SAP
			{
				$this->_ci->loglib->logWarningDB('Service already present: '.$serviceData->description);
				continue;
			}
		}

		return success('Services successfully created');
	}

	/**
	 * Updates services data in SAP using the array of person ids given as parameter
	 */
	public function update($users)
	{
		if (isEmptyArray($users)) return success('No services to be updated');

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
			// If the stundensatz is not set for this user...
			$stundensatz = $serviceData->stundensatz;
			if (isEmptyString($stundensatz))
			{
				$this->_ci->loglib->logWarningDB('No stundensatz set for user: '.$serviceData->person_id);
				continue; // ...and continue to the next one
			}

			// If the name is not set for this user...
			if (isEmptyString($serviceData->name))
			{
				$this->_ci->loglib->logWarningDB('No surname set for user: '.$serviceData->person_id);
				continue; // ...and continue to the next one
			}

			// If the organization unit is null then skip this user...
			if (isEmptyString($serviceData->organization_unit))
			{
				$this->_ci->loglib->logWarningDB('No organization unit set for user: '.$serviceData->person_id);
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

			// If an error occurred then return it
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

				// If an error occurred then return it
				if (isError($manageServiceResult)) return $manageServiceResult;

				// SAP data
				$manageService = getData($manageServiceResult);

				// If data structure is ok...
				if (isset($manageService->ServiceProduct)
					&& isset($manageService->ServiceProduct->InternalID)
					&& isset($manageService->ServiceProduct->InternalID->_))
				{
					// Get the previously created service id
					$serviceId = $manageService->ServiceProduct->InternalID->_;

					// Get all company ids
					$companyIdsArray = $this->_ci->config->item(self::SERVICES_VALUATION_COMPANY_IDS);
					// Activate valuation for each company
					foreach ($companyIdsArray as $companyId)
					{
						// Activate valuation
						$valuationResult = $this->_manageServiceProductValuationDataIn(
							$serviceId,
							$companyId,
							$stundensatz
						);

						if (isError($valuationResult)) return $valuationResult; // if fatal error
					}

					// Price list & list price
					// If root_organization_unit is not null then it is possible to add this service to a price list and to a list price
					if ($serviceData->root_organization_unit != null)
					{
						// Price list
						$priceListId = '';
						// If the root organization unit is GMBH then add this service to the FHTW price list
						// NOTE: for price list the logic is inverted!
						if ($serviceData->root_organization_unit == self::GMBH_OE_VALUE)
						{
							$priceListId = ($this->_ci->config->item(SyncPriceListsLib::PRICE_LISTS_ID_FORMATS))[self::FHTW_CONFIG_INDX];
						}
						else // otherwise to the GMBH price list
						{
							$priceListId = ($this->_ci->config->item(SyncPriceListsLib::PRICE_LISTS_ID_FORMATS))[self::GMBH_CONFIG_INDX];
						}

						// Finally add this service to a price list
						$manageSalesPriceListInResult = $this->_ci->syncpricelistslib->addServiceToPriceList(
							$priceListId,
							$serviceId,
							$stundensatz
						);

						if (isError($manageSalesPriceListInResult)) return $manageSalesPriceListInResult; // if fatal error

						// List price
						$companyId = '';
						// If the root organization unit is GMBH then add this service to the GMBH list price
						if ($serviceData->root_organization_unit == self::GMBH_OE_VALUE)
						{
							$companyId = ($this->_ci->config->item(self::SERVICES_VALUATION_COMPANY_IDS))[self::GMBH_CONFIG_INDX];
						}
						else // otherwise to the FHTW list price
						{
							$companyId = ($this->_ci->config->item(self::SERVICES_VALUATION_COMPANY_IDS))[self::FHTW_CONFIG_INDX];
						}

						// Add a new list price that links this service to a list price
						$manageSalesListPriceInResult = $this->_ci->synclistpriceslib->manageProcurementPriceSpecificationIn(
							$companyId,
							$serviceId,
							$stundensatz
						);

						if (isError($manageSalesListPriceInResult)) return $manageSalesListPriceInResult; // if fatal error
					}
					else
					{
						$this->_ci->loglib->logWarningDB(
							'Was not possible to find the root organization unit for this service: '.$serviceId
						);
					}
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
								if (isset($item->Note))
								{
									$this->_ci->loglib->logWarningDB($item->Note.' for user: '.$serviceData->person_id);
								}
							}
						}
						elseif ($manageService->Log->Item->Note)
						{
							$this->_ci->loglib->logWarningDB($manageService->Log->Item->Note.' for user: '.$serviceData->person_id);
						}
					}
					else
					{
						// Default non blocking error
						$this->_ci->loglib->logWarningDB('SAP did not return the InterlID for user: '.$serviceData->person_id);
					}
					continue;
				}
			}
			else // if the service is already present in SAP
			{
				$this->_ci->loglib->logWarningDB('Service is not present: '.$serviceData->description);
				continue;
			}
		}

		return success('Services successfully updated');
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
				m.personalnummer AS personalnumber,
				(
					SELECT bf.oe_kurzbz
				 	  FROM public.tbl_benutzerfunktion bf
					 WHERE bf.uid = b.uid
					   AND bf.funktion_kurzbz = \'oezuordnung\'
					   AND (bf.datum_von IS NULL OR bf.datum_von <= NOW())
					   AND (bf.datum_bis IS NULL OR bf.datum_bis >= NOW())
				      ORDER BY bf.insertamum DESC
					 LIMIT 1
				) AS organization_unit,
				(
					SELECT s.sap_kalkulatorischer_stundensatz
					  FROM sync.tbl_sap_stundensatz s
					 WHERE s.mitarbeiter_uid = m.mitarbeiter_uid
				      ORDER BY s.insertamum DESC
					 LIMIT 1
				) AS stundensatz
			  FROM public.tbl_person p
			  JOIN public.tbl_benutzer b USING(person_id)
			  JOIN public.tbl_mitarbeiter m ON(b.uid = m.mitarbeiter_uid)
			 WHERE p.person_id IN ?
			   AND m.personalnummer > 0
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

			// Set category and root_organization_unit properties for this user
			// Get root organization unit for this user
			$rootOUResult = $this->_ci->MessageTokenModel->getOERoot($userPersonalData->organization_unit);

			if (isError($rootOUResult)) return $rootOUResult;

			// Default category is GMBH
			$userPersonalData->category = $this->_ci->config->item(self::CATEGORY_GMBH);
			if (hasData($rootOUResult))
			{
				// Store the root organization unit for this user
				$userPersonalData->root_organization_unit = getData($rootOUResult)[0]->oe_kurzbz;

				// If the root organization unit of this user is not GMBH then set category as not gmbh
				if ($userPersonalData->root_organization_unit != self::GMBH_OE_VALUE)
				{
					$userPersonalData->category = $this->_ci->config->item(self::CATEGORY_NOT_GMBH);
				}
			}
			else // otherwise set root_organization_unit as null
			{
				$userPersonalData->root_organization_unit = null;
			}

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
		$queryServiceResult = $this->getServiceByDescription($description);

		if (isError($queryServiceResult)) return $queryServiceResult;
		if (!hasData($queryServiceResult)) return error('Something went wrong while checking if a service is present using a description');

		// Get data from then returned object
		$queryService = getData($queryServiceResult);

		// Checks the structure of then returned object
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
		$queryServiceResult = $this->getServiceById($id);

		if (isError($queryServiceResult)) return $queryServiceResult;
		if (!hasData($queryServiceResult)) return error('Something went wrong while checking if a service is present using an id');

		// Get data from then returned object
		$queryService = getData($queryServiceResult);

		// Checks the structure of then returned object
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
	private function _manageServiceProductIn($description, $person_id, $category, $rootOU)
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
					'ProductCategoryID' => $category,
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
						'LifeCycleStatusCode' => 2,
						'PurchasingMeasureUnitCode' => 'HUR'
					),
					'DeviantTaxClassification' => $this->_getTaxesArray($rootOU),
					'Sales' => $this->_getSalesArray($rootOU),
					'Valuation' => $this->_getValuationArray()
				)
			)
		);

		// If an error occurred then return it
		if (isError($manageServiceResult)) return $manageServiceResult;

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
						if (isset($item->Note))
						{
							$this->_ci->loglib->logWarningDB($item->Note.' for user: '.$person_id);
						}
					}
				}
				elseif ($manageService->Log->Item->Note)
				{
					$this->_ci->loglib->logWarningDB($manageService->Log->Item->Note.' for user: '.$person_id);
				}
			}
			else
			{
				// Default non blocking error
				$this->_ci->loglib->logWarningDB('SAP did not return the InterlID for user: '.$person_id);
			}
		}

		return success('Service successfully created');
	}

	/**
	 * Once the service is created its valuations are still inactive, this method activates a single valuation
	 * specified by service id and company id
	 */
	private function _manageServiceProductValuationDataIn($sap_service_id, $company_id, $amount)
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
					'AccountDeterminationGroupCode' => $this->_ci->config->item(self::ACCOUNT_DETERMINATION_GROUP_CODE),
					'CostRate' => array(
						'actionCode' => '04',
						'SetOfBooksID' => $this->_ci->config->item(self::SET_OF_BOOKS_ID),
						'StartDate' => $this->_ci->config->item(self::START_DATE),
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

		// If an error occurred return it
		if (isError($manageServiceProductValuationResult)) return $manageServiceProductValuationResult;

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
						if (isset($item->Note))
						{
							$this->_ci->loglib->logWarningDB($item->Note.' for service: '.$sap_service_id);
						}
					}
				}
				elseif ($manageServiceProductValuation->Log->Item->Note)
				{
					$this->_ci->loglib->logWarningDB($manageServiceProductValuation->Log->Item->Note.' for service: '.$sap_service_id);
				}
			}
			else
			{
				// Default non blocking error
				$this->_ci->loglib->logWarningDB('SAP did not return the InterlID for user: '.$sap_service_id);
			}
		}

		return success('Service valuation successfully activated');
	}

	/**
	 * Generate valuation array
	 */
	private function _getValuationArray()
	{
		$valuationArray = [];

		// Get all company ids
		$companyIdsArray = $this->_ci->config->item(self::SERVICES_VALUATION_COMPANY_IDS);

		// Activate valuation for each company
		foreach ($companyIdsArray as $companyId)
		{
			$valuationArray[] = array(
				'CompanyID' => $companyId,
				'LifeCycleStatusCode' => 1
			);
		}

		return $valuationArray;
	}

	/**
	 * Generate sales array
	 */
	private function _getSalesArray($rootOU)
	{
		$salesArray = [];

		// Get all company ids
		$companyIdsArray = $this->_ci->config->item(self::SALES_ORGANISATION_ID);

		// If the organization unit is GMBH then return the GMBH sales
		if ($rootOU == self::GMBH_OE_VALUE && isset($companyIdsArray[self::GMBH_CONFIG_INDX]))
		{
			$salesArray[] = array(
				'SalesOrganisationID' => $companyIdsArray[self::GMBH_CONFIG_INDX],
				'DistributionChannelCode' => array('_' => '01'),
				'LifeCycleStatusCode' => 2,
				'SalesMeasureUnitCode' => 'HUR',
				'ItemGroupCode' => $this->_ci->config->item(self::ITEM_GROUP_CODE)
			);
		}

		// If the organization unit is FHTW then return the FHTW sales
		if ($rootOU == self::FHTW_OE_VALUE && isset($companyIdsArray[self::FHTW_CONFIG_INDX]))
		{
			$salesArray[] = array(
				'SalesOrganisationID' => $companyIdsArray[self::FHTW_CONFIG_INDX],
				'DistributionChannelCode' => array('_' => '01'),
				'LifeCycleStatusCode' => 2,
				'SalesMeasureUnitCode' => 'HUR',
				'ItemGroupCode' => $this->_ci->config->item(self::ITEM_GROUP_CODE)
			);
		}

		return $salesArray;
	}

	/**
	 * Generate taxes array
	 */
	private function _getTaxesArray($rootOU)
	{
		$taxesArray = [];

		// If the organization unit is FHTW then return the FHTW taxes
		if ($rootOU == self::FHTW_OE_VALUE)
		{
			$taxesArray = array(
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
				),
				'TaxExemptionReasonCode' => array(
					'_' => 1,
					'listID' => 'AT'
				)
			);
		}

		return $taxesArray;
	}
}

