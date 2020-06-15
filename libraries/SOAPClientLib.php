<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * This library is used to call SAP Business by Design SOAP web services
 */
class SOAPClientLib
{
	// Blocking errors
	const ERROR = 				'ERR0001';
	const SOAP_ERROR =			'ERR0002';
	const MISSING_REQUIRED_PARAMETERS = 	'ERR0003';
	const WRONG_WS_PARAMETERS = 		'ERR0004';

	const ERROR_STR = '%s: %s'; // Error message format

	const WSDL_FULL_NAME = APPPATH.'config/extensions/FHC-Core-SAP/WSDLs/%s/%s.wsdl'; // Full name to the WSDL

	// Configs parameters names
	const ACTIVE_CONNECTION = 'soap_active_connection';
	const CONNECTIONS = 'soap_connections';

	private $_connectionsArray;		// connections array

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

		$this->_ci->config->load('extensions/FHC-Core-SAP/SOAPClient'); // Loads FHC-SAP configuration

		$this->_setPropertiesDefault(); // properties initialization

		$this->_setConnection(); // sets the connection parameters
	}

	// --------------------------------------------------------------------------------------------
	// Public methods
	
	/**
	 * Performs a call to a remote SOAP web service
	 */
	public function call($apiSetName, $serviceName, $soapFunction, $callParametersArray = array())
	{
		// Checks if the api set name is valid
		if ($apiSetName == null || trim($apiSetName) == '') $this->_error(self::MISSING_REQUIRED_PARAMETERS, 'Forgot API set name?');
		
		// Checks if the service name is valid
		if ($serviceName == null || trim($serviceName) == '') $this->_error(self::MISSING_REQUIRED_PARAMETERS, 'Forgot SAP service name?');
		
		// Checks if the SOAP function name is valid
		if ($soapFunction == null || trim($soapFunction) == '') $this->_error(self::MISSING_REQUIRED_PARAMETERS, 'Forgot SOAP function name?');
		
		// Checks that the SOAP webservice parameters are present in an array
		if (!is_array($callParametersArray)) $this->_error(self::WRONG_WS_PARAMETERS, 'Are those parameters?');
		
		if ($this->isError()) return null; // If an error was raised then return a null value
		
		return $this->_callRemoteSOAP($apiSetName, $serviceName, $soapFunction, $callParametersArray); // perform a remote SOAP call with the given uri
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
		$this->_connectionsArray = null;
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
		
		$this->_connectionsArray = $connectionsArray[$activeConnectionName];
	}

	/**
	 * Performs a remote SOAP web service call with the given name and parameters
	 */
	private function _callRemoteSOAP($apiSetName, $serviceName, $soapFunction, $callParametersArray)
	{
		$response = null;

		try
		{
			// Call the SoapClient giving the path in the file system to the WSDL file and the options needed to connect
			$soapClient = new SoapClient(
				sprintf(self::WSDL_FULL_NAME, $apiSetName, $serviceName),
				$this->_connectionsArray[$apiSetName]
			);

			$response = $soapClient->{$soapFunction}($callParametersArray);

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
		if (is_object($response))
		{
			$this->_hasData = true; // set property _hasData to true
			// If no data are present
			if (count((array)$response) == 0) $this->_hasData = false; // set property _hasData to false
		
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

