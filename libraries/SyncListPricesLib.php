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
		$this->_ci->load->model('extensions/FHC-Core-SAP/SAPCoreAPI/ManageProcurementPriceSpecificationIn_model', 'ManageProcurementPriceSpecificationInModel');
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
}

