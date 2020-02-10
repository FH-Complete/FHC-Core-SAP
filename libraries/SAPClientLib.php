<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * To perform SOAP calls to SAP Business ByDesign
 */
class SAPClientLib
{
	// Blocking errors
	const ERROR = 						'ERR0001';
	const SOAP_ERROR =		 			'ERR0002';

	const ERROR_STR = '%s: %s'; //

	// Non blocking errors
	const USER_ALREADY_EXISTS_WARNING =	'WAR0001';

	// Configs parameters names
	const ACTIVE_CONNECTION = 'fhc_sap_active_connection';
	const CONNECTIONS = 'fhc_sap_connections';

	// Connection parameters names
	const WSDL = 'wsdl';
	const OPTIONS = 'options';

	private $_soapOptionsArray;		// contains the connection option parameters
	private $_wsdl;					// contains the WSDL URI

	private $_wsFunction;			// name of the SOAP call

	private $_callParametersArray;	// contains the parameters to give to the remote SOAP web service

	private $_error;				// true if an error occurred
	private $_errorMessage;			// contains the error message

	private $_hasData;				// indicates if there are data in the response or not
	private $_emptyResponse;		// indicates if the response is empty or not

	private $_ci; // Code igniter instance

    /**
     * Object initialization
     */
    public function __construct()
    {
		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->config->load('extensions/FHC-Core-SAP/SAPClient'); // Loads FHC-SAP configuration

		$this->_setPropertiesDefault(); // properties initialization

        $this->_setConnection(); // sets the connection parameters
    }

    // --------------------------------------------------------------------------------------------
    // Public methods

    /**
     * Performs a call to a remote SOAP web service
     */
    public function call($wsSoapFunction, $callParametersArray = array())
    {
		// Checks if the SOAP webservice name is provided and it is valid
		if ($wsSoapFunction != null && trim($wsSoapFunction) != '')
		{
			$this->_wsFunction = $wsSoapFunction;
		}
		else
		{
			$this->_error(self::MISSING_REQUIRED_PARAMETERS, 'Forgot something?');
		}

		// Checks that the SOAP webservice parameters are present in an array
		if (is_array($callParametersArray))
		{
			$this->_callParametersArray = $callParametersArray;
		}
		else
		{
			$this->_error(self::WRONG_WS_PARAMETERS, 'Are those parameters?');
		}

		if ($this->isError()) return null; // If an error was raised then return a null value

        return $this->_callRemoteSOAP($wsSoapFunction, $callParametersArray); // perform a remote SOAP call with the given uri
    }

	/**
	 * Returns the error message stored in property _errorMessage
	 */
	public function getError()
	{
		return $this->_errorMessage;
	}

	/**
	 * Returns true if an error occurred, otherwise false
	 */
	public function isError()
	{
		return $this->_error;
	}

	/**
	 * Returns false if an error occurred, otherwise true
	 */
	public function isSuccess()
	{
		return !$this->isError();
	}

	/**
	 * Returns true if the response contains data, otherwise false
	 */
	public function hasData()
	{
		return $this->_hasData;
	}

	/**
	 * Returns true if the response was empty, otherwise false
	 */
	public function hasEmptyResponse()
	{
		return $this->_emptyResponse;
	}

	/**
	 * Reset the library properties to default values
	 */
	public function resetToDefault()
	{
		$this->_wsFunction = null;
		$this->_callParametersArray = array();
		$this->_error = false;
		$this->_errorMessage = '';
		$this->_hasData = false;
		$this->_emptyResponse = false;
	}

    // --------------------------------------------------------------------------------------------
    // Private methods

	/**
     * Initialization of the properties of this object
     */
	private function _setPropertiesDefault()
	{
		$this->_connectionArray = null;

		$this->_wsFunction = null;

		$this->_callParametersArray = array();

		$this->_error = false;

		$this->_errorMessage = '';

		$this->_hasData = false;

		$this->_emptyResponse = false;
	}

    /**
     * Sets the connection
     */
    private function _setConnection()
    {
		$activeConnectionName = $this->_ci->config->item(self::ACTIVE_CONNECTION);
		$connectionsArray = $this->_ci->config->item(self::CONNECTIONS);

		$connectionArray = $connectionsArray[$activeConnectionName];

		$this->_wsdl = $connectionArray[self::WSDL];
		$this->_soapOptionsArray = $connectionArray[self::OPTIONS];
    }

	/**
	 * Performs a remote SOAP web service call with the given name and parameters
	 */
	private function _callRemoteSOAP($wsSoapFunction, $callParametersArray)
	{
		$response = null;

		try
		{
			$soapClient = new SoapClient($this->_wsdl, $this->_soapOptionsArray);

			$response = $soapClient->{$wsSoapFunction}($callParametersArray);

			// Checks the response of the remote SOAP web service and handles possible errors
			// Eventually here is also called a hook, so the data could have been manipulated
			$response = $this->_checkResponse($response);
		}
		catch (SoapFault $sf) // SOAP errors
		{
			$this->_error(self::SOAP_ERROR, sprintf(self::ERROR_STR, $sf->getCode(), $sf->getMessage()));
		}
		// Otherwise another error has occurred
		catch (Exception $e)
		{
			$this->_error(self::ERROR, sprintf(self::ERROR_STR, $e->getCode(), $e->getMessage()));
		}

		if ($this->isError()) return null; // If an error was raised then return a null value

		return $response;
	}

    /**
     * Checks the response from the remote web service
     */
    private function _checkResponse($response)
    {
		$checkResponse = null;

		// If NOT an empty response
        if (is_object($response) || is_array($response) || is_string($response) || is_numeric($response))
        {
			// If no data are present
            if ((is_string($response->body) && trim($response->body) == '')
				|| (is_array($response->body) && count($response->body) == 0)
                || (is_object($response->body) && count((array)$response->body) == 0))
            {
				$this->_hasData = false; // set property _hasData to false
            }
            else
            {
				$this->_hasData = true; // set property _hasData to true
            }

			$checkResponse = $response; // returns a success
        }
		else // if the response is empty
		{
			$this->_emptyResponse = true; // set property _hasData to false
		}

		return $checkResponse;
    }

	/**
	 * Sets property _error to true and stores an error message in property _errorMessage
	 */
	private function _error($code, $message = 'Generic error')
	{
		$this->_error = true;
		$this->_errorMessage = $code.': '.$message;
	}
}
