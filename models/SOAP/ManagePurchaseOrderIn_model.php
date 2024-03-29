<?php

require_once 'CoreAPIModel.php';

/**
 * This implements all the calls for:
 * API set name SAPCoreAPI
 * Service name ManagePurchaseOrderIn
 */
class ManagePurchaseOrderIn_model extends CoreAPIModel
{
	/**
	 * Set the properties to perform SOAP calls
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_serviceName = 'ManagePurchaseOrderIn'; // service name
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * SOAP function:
	 */
	public function purchaseOrderMaintainBundle($parameters)
	{
		return $this->_call('ManagePurchaseOrderInMaintainBundle', $parameters);
	}

	/**
	 * SOAP function:
	 */
	public function purchaseOrderCheckBundle($parameters)
	{
		return $this->_call('ManagePurchaseOrderInCheckMaintainBundle', $parameters);
	}
}

