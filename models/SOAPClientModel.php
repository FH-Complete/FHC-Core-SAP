<?php

/**
 * Implements the SAP SOAP client basic funcitonalities
 */
abstract class SOAPClientModel extends CI_Model
{
	protected $_apiSetName; // to store the name of the api set name
	protected $_serviceName; // to store the service name

	// --------------------------------------------------------------------------------------------
	// Protected methods

	/**
	 * Generic SAP call. It checks also for specific SAP blocking and non-blocking errors
	 */
	protected function _call($soapFunction, $callParametersArray)
	{
		// Loads the SAPClientLib library
		$this->load->library('extensions/FHC-Core-SAP/SOAPClientLib');

		// Checks if the property _apiSetName is valid
		if ($this->_apiSetName == null || trim($this->_apiSetName) == '')
		{
			$this->soapclientlib->resetToDefault();

			return error('API set name not valid');
		}

		// Checks if the property _serviceName is valid
		if ($this->_serviceName == null || trim($this->_serviceName) == '')
		{
			$this->soapclientlib->resetToDefault();

			return error('Service name not valid');
		}

		// Call the SAP webservice with the given parameters
		$wsResult = success(
			$this->soapclientlib->call(
				$this->_apiSetName,
				$this->_serviceName,
				$soapFunction,
				$callParametersArray
			)
		);

		// If an error occurred
		if ($this->soapclientlib->isError()) $wsResult = error($this->soapclientlib->getError());

		$this->soapclientlib->resetToDefault(); // reset to the default values

		return $wsResult;
	}
}

