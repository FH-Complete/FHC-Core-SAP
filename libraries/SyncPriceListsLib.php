<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * This library contains the logic used to perform data synchronization between FHC and SAP Business by Design
 */
class SyncPriceListsLib
{
	private $_ci; // Code igniter instance

	// Jobs types used by this lib
	const SAP_PRICE_LIST_CREATE = 'SAPPriceListCreate';

	// Prefix for SAP SOAP id calls
	const CREATE_PRICE_LIST_PREFIX = 'CPL';
	const UPDATE_PRICE_LIST_PREFIX = 'UPL';

	// Config entries names
	const PRICE_LISTS_ID_FORMATS = 'price_lists_id_formats';
	const PRICE_LISTS_ACCOUNT_IDS = 'price_lists_account_ids';
	const PRICE_LISTS_START_DATE = 'price_lists_start_date';
	const PRICE_LISTS_END_DATE = 'price_lists_end_date';

	const GMBH_CONFIG_INDX = 'gmbh';
	const FHTW_CONFIG_INDX = 'fhtw';

	// Database oe values for FHTW and GMBH
	const GMBH_OE_VALUE = 'gmbh';
	const FHTW_OE_VALUE = 'gst';

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

		// Loads QuerySalesPriceListInModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SOAP/QuerySalesPriceListIn_model', 'QuerySalesPriceListInModel');
		// Loads ManageSalesPriceListInModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SOAP/ManageSalesPriceListIn_model', 'ManageSalesPriceListInModel');

		// Loads MessageTokenModel
		$this->_ci->load->model('system/MessageToken_model', 'MessageTokenModel');

		// Loads Price Lists configuration
		$this->_ci->config->load('extensions/FHC-Core-SAP/PriceLists');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Return the raw result of SAP->QueryCustomerIn->FindByCommunicationData->SelectionByEmailURI
	 */
	public function getPriceListByCustomerId($id)
	{
		// Calls SAP to find a price list with the given customer id
		return $this->_ci->QuerySalesPriceListInModel->findByTypeCodeAndPropertyIDAndPropertyValue(
			array(
				'SalesPriceList' => array(
					'TypeCode' => '7PL0',
					'PropertyValuationPriceSpecificationElementPropertyValuation1' => array(
						'IdentifyingIndicator' => true,
						'PriceSpecificationElementPropertyReference' => array(
							'PriceSpecificationElementPropertyID' => 'CND_BUYER_ID'
						),
						'PriceSpecificationElementPropertyValue' => array(
							'ID' => $id
						)
					)
				)
			)
		);
	}

	/**
	 * Create a new price list for the current month
	 */
	public function create()
	{
		// Get price lists id formats from config
		$priceListsIdFormats = $this->_ci->config->item(self::PRICE_LISTS_ID_FORMATS);
		// Get price lists account ids from config
		$priceListsAccountIds = $this->_ci->config->item(self::PRICE_LISTS_ACCOUNT_IDS);

		// For each price list that have to be created for the current month
		foreach ($priceListsIdFormats as $companyId => $priceListsIdFormat)
		{
			// Id of the current price list
			$priceListId = strtoupper($priceListsIdFormat);

			// Create a new price list in SAP
			$manageSalesPriceListInResult = $this->_ci->ManageSalesPriceListInModel->maintainBundle(
				array(
					'BasicMessageHeader' => array(
						'ID' => generateUID(self::CREATE_PRICE_LIST_PREFIX)
					),
					'SalesPriceList' => array(
						'actionCode' => '01',
						'ID' => $priceListId,
						'AccountID' => $priceListsAccountIds[$companyId],
						'TypeCode' => '7PL0',
						'CurrencyCode' => 'EUR',
						'StartDate' => $this->_ci->config->item(self::PRICE_LISTS_START_DATE),
						'EndDate' => $this->_ci->config->item(self::PRICE_LISTS_END_DATE)
					)
				)
			);

			// If no error occurred...
			if (!isError($manageSalesPriceListInResult))
			{
				// SAP data
				$manageSalesPriceListIn = getData($manageSalesPriceListInResult);

				// If data structure is ok...
				if (isset($manageSalesPriceListIn->SalesPriceList)
					&& isset($manageSalesPriceListIn->SalesPriceList->ID)
					&& isset($manageSalesPriceListIn->SalesPriceList->ID->_))
				{
					// Everything is ok!
				}
				else // ...otherwise store a non blocking error...
				{
					// If it is present a description from SAP then use it
					if (isset($manageSalesPriceListIn->Log) && isset($manageSalesPriceListIn->Log->Item)
						&& isset($manageSalesPriceListIn->Log->Item))
					{
						if (!isEmptyArray($manageSalesPriceListIn->Log->Item))
						{
							foreach ($manageSalesPriceListIn->Log->Item as $item)
							{
								if (isset($item->Note))
								{
									$this->_ci->LogLibSAP->logWarningDB($item->Note);
								}
							}
						}
						elseif ($manageSalesPriceListIn->Log->Item->Note)
						{
							$this->_ci->LogLibSAP->logWarningDB($manageSalesPriceListIn->Log->Item->Note);
						}
					}
					else
					{
						// Default non blocking error
						$this->_ci->LogLibSAP->logWarningDB('SAP did not return ID for price list: '.$priceListId);
					}
				}
			}
			else // ...otherwise return it
			{
				return $manageSalesPriceListInResult;
			}
		}

		return success('Price lists created successfully');
	}

	/**
	 * Add a service to the current price list
	 */
	public function addServiceToPriceList($priceListId, $sap_service_id, $stundensatz)
	{
		$manageSalesPriceListInResult = $this->_ci->ManageSalesPriceListInModel->maintainBundle(
			array(
				'BasicMessageHeader' => array(
					'ID' => generateUID(self::UPDATE_PRICE_LIST_PREFIX)
				),
				'SalesPriceList' => array(
					'actionCode' => '04',
					'ID' => strtoupper($priceListId),
					'StartDate' => $this->_ci->config->item(self::PRICE_LISTS_START_DATE),
					'EndDate' => $this->_ci->config->item(self::PRICE_LISTS_END_DATE),
					'PriceSpecification' => array(
						'TypeCode' => '7PR1',
						'Amount' => array(
							'currencyCode' => 'EUR',
							'_' => $stundensatz
						),
						'BaseQuantity' => array(
							'unitCode' => 'HUR',
							'_' => 1
						),
						'BaseQuantityTypeCode' => 'HUR',
						'ProductID' => $sap_service_id
					)
				)
			)
		);

		// If an error occurred then return it
		if (isError($manageSalesPriceListInResult)) return $manageSalesPriceListInResult;

		// SAP data
		$manageSalesPriceListIn = getData($manageSalesPriceListInResult);

		// If data structure is ok...
		if (isset($manageSalesPriceListIn->SalesPriceList)
			&& isset($manageSalesPriceListIn->SalesPriceList->ID)
			&& isset($manageSalesPriceListIn->SalesPriceList->ID->_))
		{
			// Returns the result from SAP
			return $manageSalesPriceListInResult;
		}
		else // ...otherwise log a non blocking error...
		{
			// If it is present a description from SAP then use it
			if (isset($manageSalesPriceListIn->Log) && isset($manageSalesPriceListIn->Log->Item)
				&& isset($manageSalesPriceListIn->Log->Item))
			{
				if (!isEmptyArray($manageSalesPriceListIn->Log->Item))
				{
					foreach ($manageSalesPriceListIn->Log->Item as $item)
					{
						if (isset($item->Note))
						{
							$this->_ci->LogLibSAP->logWarningDB($item->Note);
						}
					}
				}
				elseif ($manageSalesPriceListIn->Log->Item->Note)
				{
					$this->_ci->LogLibSAP->logWarningDB($manageSalesPriceListIn->Log->Item->Note);
				}
			}
			else
			{
				// Default non blocking error
				$this->_ci->LogLibSAP->logWarningDB('SAP did not return ID for price list: '.$priceListId);
			}
		}

		return success('Service added successfully to the price list');
	}

	/**
	 *
	 */
	public function addServicesToPriceList()
	{
		// Get all the synchronized users from database
		$dbModel = new DB_Model();
		$dbSyncdUsers = $dbModel->execReadOnlyQuery('
			SELECT p.person_id,
				s.sap_service_id,
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
					SELECT ss.stundensatz
					FROM hr.tbl_stundensatz ss
					WHERE ss.uid = m.mitarbeiter_uid
						AND stundensatztyp = \'kalkulatorisch\'
					ORDER BY ss.gueltig_von DESC
					LIMIT 1
				) AS stundensatz
			  FROM public.tbl_person p
			  JOIN sync.tbl_sap_services s USING(person_id)
			  JOIN public.tbl_benutzer b USING(person_id)
			  JOIN public.tbl_mitarbeiter m ON(b.uid = m.mitarbeiter_uid)
			  WHERE b.aktiv
		      ORDER BY p.person_id
		');

		// If an error occurred
		if (isError($dbSyncdUsers)) $this->logError(getCode($dbSyncdUsers).': '.getError($dbSyncdUsers));

		// Loops through services data
		foreach (getData($dbSyncdUsers) as $user)
		{
			// If the stundensatz is not set for this user...
			if (isEmptyString($user->stundensatz))
			{
				$this->_ci->LogLibSAP->logWarningDB('No stundensatz set for user: '.$user->person_id);
				continue; // ...and continue to the next one
			}

			// User default root organization_unit
			$user->root_organization_unit = null;

			// Get root organization unit for this user
			$rootOUResult = $this->_ci->MessageTokenModel->getOERoot($user->organization_unit);

			// If an error occurred then return it
			if (isError($rootOUResult)) return $rootOUResult;

			// If data have been found
			if (hasData($rootOUResult))
			{
				// Store the root organization unit for this user
				$user->root_organization_unit = getData($rootOUResult)[0]->oe_kurzbz;
			}

			// Price list
			// If root_organization_unit is not null then it is possible to add this service to a price list
			if ($user->root_organization_unit != null)
			{
				// Price list
				$priceListId = '';
				// If the root organization unit is GMBH then add this service to the FHTW price list
				// NOTE: for price list the logic is inverted!
				if ($user->root_organization_unit == self::GMBH_OE_VALUE)
				{
					$priceListId = ($this->_ci->config->item(SyncPriceListsLib::PRICE_LISTS_ID_FORMATS))[self::FHTW_CONFIG_INDX];
				}
				else // otherwise to the GMBH price list
				{
					$priceListId = ($this->_ci->config->item(SyncPriceListsLib::PRICE_LISTS_ID_FORMATS))[self::GMBH_CONFIG_INDX];
				}

				// Finally add this service to a price list
				$manageSalesPriceListInResult = $this->addServiceToPriceList(
					$priceListId,
					$user->sap_service_id,
					$user->stundensatz
				);

				if (isError($manageSalesPriceListInResult)) return $manageSalesPriceListInResult; // if fatal error
			}
			else
			{
				$this->_ci->LogLibSAP->logWarningDB('Was not possible to find the root organization unit for this service: '.$user->sap_service_id);
			}
		}

		return success('Services successfully added to the current price list');
	}
}
