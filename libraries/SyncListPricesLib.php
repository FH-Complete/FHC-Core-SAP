<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * This library contains the logic used to perform data synchronization between FHC and SAP Business by Design
 */
class SyncListPricesLib
{
	private $_ci; // Code igniter instance

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		// Loads ManageProcurementPriceSpecificationInModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SOAP/ManageProcurementPriceSpecificationIn_model', 'ManageProcurementPriceSpecificationInModel');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Return the raw result of SAP->ManageProcurementPriceSpecificationIn->Read
	 * TODO: fix it!!!
	 */
	public function getListPriceById($id)
	{
		// Calls SAP to find a price list with the given supplier id
		return $this->_ci->ManageProcurementPriceSpecificationInModel->read(
			array(
				'ProcurementPriceSpecification' => array(
					'UUID' => generateUUID(),
					'PropertyValuation' => array(
						'IdentifyingIndicator' => true,
						'PriceSpecificationElementPropertyReference' => array(
							'PriceSpecificationElementPropertyID' => 'CND_SUPPL_ID'
						),
						'PriceSpecificationElementPropertyValue' => array(
							'ID' => $id,
							'IntegerValue' => 0
						)
					)
				)
			)
		);
	}

	/**
	 * Once the service is created the service is linked to a list price
	 */
	public function manageProcurementPriceSpecificationIn($sap_service_id, $stundensatz, &$nonBlockingErrorsArray)
	{
		// Calls SAP to find a price list with the given supplier id
		$manageProcurementPriceSpecificationInResult = $this->_ci->ManageProcurementPriceSpecificationInModel->maintainBundle(
			array(
				'BasicMessageHeader' => array(
					'UUID' => generateUUID()
				),
				'ProcurementPriceSpecification' => array(
					'actionCode' => '01',
					'UUID' => generateUUID(),
					'ValidityPeriod' => array(
						'IntervalBoundaryTypeCode' => '',
						'StartTimePoint' => array(
							'TypeCode' => 1
						),
						'EndTimePoint' => array(
							'TypeCode' => 1
						)
					),
					'Rate' => array(
						'DecimalValue' => $stundensatz,
						'CurrencyCode' => 'EUR',
						'BaseDecimalValue' => 1,
						'BaseMeasureUnitCode' => 'HUR'
					),
					'PropertyValuation' => array(
						0 => array(
							'IdentifyingIndicator' => true,
							'PriceSpecificationElementPropertyReference' => array(
								'PriceSpecificationElementPropertyID' => 'CND_SUPPL_ID'
							),
							'PriceSpecificationElementPropertyValue' => array(
								'ID' => 'GMBH',
								'IntegerValue' => 0
							)
						),
						1 => array(
							'IdentifyingIndicator' => true,
							'PriceSpecificationElementPropertyReference' => array(
								'PriceSpecificationElementPropertyID' => 'CND_PRODUCT_ID'
							),
							'PriceSpecificationElementPropertyValue' => array(
								'ID' => $sap_service_id,
								'IntegerValue' => 0
							)
						)
					)
				)
			)
		);

		// If no error occurred...
		if (!isError($manageProcurementPriceSpecificationInResult))
		{
			// SAP data
			$manageProcurementPriceSpecificationIn = getData($manageProcurementPriceSpecificationInResult);

			// If data structure is ok...
			if (isset($manageProcurementPriceSpecificationIn->ProcurementPriceSpecification)
				&& isset($manageProcurementPriceSpecificationIn->ProcurementPriceSpecification->UUID)
				&& isset($manageProcurementPriceSpecificationIn->ProcurementPriceSpecification->UUID->_))
			{
				// Returns the result from SAP
				return $manageProcurementPriceSpecificationInResult;
			}
			else // ...otherwise store a non blocking error...
			{
				// If it is present a description from SAP then use it
				if (isset($manageProcurementPriceSpecificationIn->Log) && isset($manageProcurementPriceSpecificationIn->Log->Item)
					&& isset($manageProcurementPriceSpecificationIn->Log->Item))
				{
					if (!isEmptyArray($manageProcurementPriceSpecificationIn->Log->Item))
					{
						foreach ($manageProcurementPriceSpecificationIn->Log->Item as $item)
						{
							if (isset($item->Note)) $nonBlockingErrorsArray[] = $item->Note.' for list price: '.$sap_service_id;
						}
					}
					elseif ($manageProcurementPriceSpecificationIn->Log->Item->Note)
					{
						$nonBlockingErrorsArray[] = $manageProcurementPriceSpecificationIn->Log->Item->Note.' for list price: '.$sap_service_id;
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
			return $manageProcurementPriceSpecificationInResult;
		}
	}
}
