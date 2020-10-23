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

		// Loads ManageProcurementPriceSpecificationInModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/SOAP/ManageProcurementPriceSpecificationIn_model', 'ManageProcurementPriceSpecificationInModel');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Return the raw result of SAP->ManageProcurementPriceSpecificationIn->Read
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
	public function manageProcurementPriceSpecificationIn($companyId, $sap_service_id, $stundensatz)
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
								'ID' => $companyId,
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

		// If an error occurred then return it
		if (isError($manageProcurementPriceSpecificationInResult)) return $manageProcurementPriceSpecificationInResult;

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
						if (isset($item->Note))
						{
							$this->_ci->LogLibSAP->logWarningDB($item->Note.' for list price: '.$sap_service_id);
						}
					}
				}
				elseif ($manageProcurementPriceSpecificationIn->Log->Item->Note)
				{
					$this->_ci->LogLibSAP->logWarningDB(
						$manageProcurementPriceSpecificationIn->Log->Item->Note.' for list price: '.$sap_service_id
					);
				}
			}
			else
			{
				// Default non blocking error
				$this->_ci->LogLibSAP->logWarningDB('SAP did not return ID for list price: ILV-GMBH');
			}

			// ...and return a success
			return success('Service successfully linked to the list price');
		}
	}
}

