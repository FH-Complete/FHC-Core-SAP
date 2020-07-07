<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * This library contains the logic used to perform data synchronization between FHC and SAP Business by Design
 */
class SyncPriceListsLib
{
	private $_ci; // Code igniter instance

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
	 * Once the service is created the service is linked to a price list
	 */
	public function manageSalesPriceListIn($sap_service_id, $stundensatz, &$nonBlockingErrorsArray)
	{
		$manageSalesPriceListInResult = $this->_ci->ManageSalesPriceListInModel->maintainBundle(
			array(
				'BasicMessageHeader' => array(
					'ID' => generateUID(self::UPDATE_PRICE_LIST_PREFIX)
				),
				'SalesPriceList' => array(
					'actionCode' => '01',
					'ID' => 'ILV-FHTW',
					'StartDate' => date('Y-m-d'),
					'EndDate' => '2021-01-01',
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
							if (isset($item->Note)) $nonBlockingErrorsArray[] = $item->Note.' for price list: '.$sap_service_id;
						}
					}
					elseif ($manageSalesPriceListIn->Log->Item->Note)
					{
						$nonBlockingErrorsArray[] = $manageSalesPriceListIn->Log->Item->Note.' for price list: '.$sap_service_id;
					}
				}
				else
				{
					// Default non blocking error
					$nonBlockingErrorsArray[] = 'SAP did not return ID for price list: ILV-FHTW';
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

