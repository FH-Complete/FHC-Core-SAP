<?php

/**
 * Implements the SAP ODATA webservice calls
 */
abstract class ODATAClientModel extends CI_Model
{
	protected $_apiSetName; // to store the name of the api set name

	/**
	 *
	 */
	public function __construct()
	{
		// Loads the ODATAClientLib library
		$this->load->library('extensions/FHC-Core-SAP/ODATAClientLib');
	}

	// --------------------------------------------------------------------------------------------
	// Protected methods

	/**
	 * Generic SAP ODATA call
	 */
	protected function _call($wsFunction, $httpMethod, $callParametersArray = array())
	{
		// Checks if the property _apiSetName is valid
		if ($this->_apiSetName == null || trim($this->_apiSetName) == '')
		{
			$this->odataclientlib->resetToDefault();

			return error('API set name not valid');
		}

		// Call the SAP ODATA webservice with the given parameters
		$wsResult = $this->odataclientlib->call($this->_apiSetName, $wsFunction, $httpMethod, $callParametersArray);

		// If an error occurred return it
		if ($this->odataclientlib->isError())
		{
			$wsResult = error($this->odataclientlib->getError());
		}
		else // otherwise return a success
		{
			$wsResult = success($wsResult);
		}

		// Reset the odataclientlib parameters
		$this->odataclientlib->resetToDefault();

		// Return a success object that contains the web service result
		return $wsResult;
	}
}

