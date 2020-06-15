<?php

/**
 * Implements the SAP ODATA webservice calls
 */
abstract class ODATAClientModel extends CI_Model
{
	/**
	 * Object initialization
	 */
	public function __construct()
	{
		parent::__construct();

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
		// Call the SAP ODATA webservice with the given parameters
		$wsResult = $this->odataclientlib->call($wsFunction, $httpMethod, $callParametersArray);

		// If an error occurred
		if ($this->odataclientlib->isError()) $wsResult = error($this->odataclientlib->getError());

		$this->odataclientlib->resetToDefault();

		return $wsResult;
	}
}

