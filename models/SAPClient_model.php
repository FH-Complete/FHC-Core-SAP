<?php

/**
 * Implements the FHC-SAP webservice calls
 */
class SAPClient_model extends CI_Model
{
	// LDAP answer if a user already exists
	const USER_ALREADY_EXISTS = '\'description\': \'entryAlreadyExists\'';

	const HTTP_GET_METHOD = 'GET'; // http get method name
    const HTTP_POST_METHOD = 'POST'; // http post method name
	const HTTP_PUT_METHOD = 'PUT'; // http post method name

	// --------------------------------------------------------------------------------------------
    // Public methods

	/**
	 *
	 */
	public function ()
	{
		return $this->_call(
			'',
			self::HTTP_GET_METHOD,
			array(
				'' => $
			)
		);
	}

	// --------------------------------------------------------------------------------------------
	// Private methods

	/**
	 * Generic SAP call. It checks also for specific SAP blocking and non-blocking errors
	 */
	private function _call($wsFunction, $httpMethod, $callParametersArray)
	{
		// Loads the SAPClientLib library
		$this->load->library('extensions/FHC-Core-SAP/SAPClientLib');

		// Call the SAP webservice with the given parameters
		$wsResult = $this->sapclientlib->call($wsFunction, $httpMethod, $callParametersArray);

		// If an error occurred
		if ($this->sapclientlib->isError())
		{
			// Checks if it is a warning...
			if (strpos($this->sapclientlib->getError(), self::USER_ALREADY_EXISTS))
			{
				$wsResult = success('User already exists in LDAP: '.$callParametersArray['uid'], SAPClientLib::USER_ALREADY_EXISTS_WARNING);
			}
			else // ...or a blocking error
			{
				$wsResult = error($this->sapclientlib->getError());
			}
		}

		// NOTE:
		$this->sapclientlib->resetToDefault();

		return $wsResult;
	}
}
