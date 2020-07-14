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

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		// Loads QuerySalesPriceListInModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SOAP/QuerySalesPriceListIn_model', 'QuerySalesPriceListInModel');
		// Loads ManageSalesPriceListInModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SOAP/ManageSalesPriceListIn_model', 'ManageSalesPriceListInModel');
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
		$dateObj = DateTime::createFromFormat('!m', date('n'));
		$monthName = $dateObj->format('F');
		$priceListId = strtoupper('ILV-FHTW-'.$monthName);

		$manageSalesPriceListInResult = $this->_ci->ManageSalesPriceListInModel->maintainBundle(
			array(
				'BasicMessageHeader' => array(
					'ID' => generateUID(self::CREATE_PRICE_LIST_PREFIX)
				),
				'SalesPriceList' => array(
					'actionCode' => '01',
					'ID' => $priceListId,
					'AccountID' => '100000',
					'TypeCode' => '7PL0',
					'CurrencyCode' => 'EUR',
					'StartDate' => date('Y-m').'-01', // beginning of the current month
					'EndDate' => date('Y-m-t')
				)
			)
		);

		// If no error occurred...
		if (!isError($manageSalesPriceListInResult))
		{
			// SAP data
			$manageSalesPriceListIn = getData($manageSalesPriceListInResult);
			$nonBlockingErrorsArray = array();

			// If data structure is ok...
			if (isset($manageSalesPriceListIn->SalesPriceList)
				&& isset($manageSalesPriceListIn->SalesPriceList->ID)
				&& isset($manageSalesPriceListIn->SalesPriceList->ID->_))
			{
				// Returns the result from SAP
				return $manageSalesPriceListInResult;
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
							if (isset($item->Note)) $nonBlockingErrorsArray[] = $item->Note;
						}
					}
					elseif ($manageSalesPriceListIn->Log->Item->Note)
					{
						$nonBlockingErrorsArray[] = $manageSalesPriceListIn->Log->Item->Note;
					}
				}
				else
				{
					// Default non blocking error
					$nonBlockingErrorsArray[] = 'SAP did not return ID for price list: '.$priceListId;
				}

				// ...and return an empty success
				return success($nonBlockingErrorsArray);
			}
		}
		else // ...otherwise return it
		{
			return $manageSalesPriceListInResult;
		}
	}

	/**
	 * Add service to the current price list
	 */
	public function addServiceToCurrentPriceList($sap_service_id, $stundensatz, &$nonBlockingErrorsArray)
	{
		$dateObj = DateTime::createFromFormat('!m', date('n'));
		$monthName = $dateObj->format('F');
		$priceListId = strtoupper('ILV-FHTW-'.$monthName);

		$manageSalesPriceListInResult = $this->_ci->ManageSalesPriceListInModel->maintainBundle(
			array(
				'BasicMessageHeader' => array(
					'ID' => generateUID(self::UPDATE_PRICE_LIST_PREFIX)
				),
				'SalesPriceList' => array(
					'actionCode' => '04',
					'ID' => $priceListId,
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
				// Returns the result from SAP
				return $manageSalesPriceListInResult;
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
							if (isset($item->Note)) $nonBlockingErrorsArray[] = $item->Note;
						}
					}
					elseif ($manageSalesPriceListIn->Log->Item->Note)
					{
						$nonBlockingErrorsArray[] = $manageSalesPriceListIn->Log->Item->Note;
					}
				}
				else
				{
					// Default non blocking error
					$nonBlockingErrorsArray[] = 'SAP did not return ID for price list: '.$priceListId;
				}

				// ...and return an empty success
				return success();
			}
		}
		else // ...otherwise return it
		{
			return $manageSalesPriceListInResult;
		}
	}
}

