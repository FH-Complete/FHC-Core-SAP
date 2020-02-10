<?php

/**
 * Implements the SAP SOAP calls
 */
class SAPClient_model extends CI_Model
{
	// --------------------------------------------------------------------------------------------
    // Public methods

	/**
	 *
	 */
	public function findByElementsByFamilyName($familyName)
	{
		return $this->_call(
			'FindByElements',
			array(
				'SelectionByFamilyName' => $familyName
			)
		);
	}

	// --------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Generic SAP call. It checks also for specific SAP blocking and non-blocking errors
	 */
	private function _call($wsFunction, $callParametersArray)
	{
		// Loads the SAPClientLib library
		$this->load->library('extensions/FHC-Core-SAP/SAPClientLib');

		// Call the SAP webservice with the given parameters
		$wsResult = $this->sapclientlib->call($wsFunction, $callParametersArray);

		// If an error occurred
		if ($this->sapclientlib->isError()) $wsResult = error($this->sapclientlib->getError());

		$this->sapclientlib->resetToDefault();

		return $wsResult;
	}
}
