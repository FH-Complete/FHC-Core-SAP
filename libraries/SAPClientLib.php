<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 *
 */
class SAPClientLib
{
	const APIKEY_NAME = 'IDM-API-KEY'; // name of the api key

    const HTTP_GET_METHOD = 'GET'; // http get method name
    const HTTP_POST_METHOD = 'POST'; // http post method name
	const HTTP_PUT_METHOD = 'PUT'; // http post method name
	const URI_TEMPLATE = '%s://%s/%s/%s'; // URI format

	// Configs parameters names
	const ACTIVE_CONNECTION = 'fhc_sap_active_connection';
	const CONNECTIONS = 'fhc_sap_connections';

	const HTTP_OK = 200; // HTTP success code

	// HTTP error codes
	const HTTP_FORBIDDEN = 403;
	const HTTP_NOT_FOUND = 404;
	const HTTP_NOT_ALLOWED_METHOD = 405;
	const HTTP_RESOURCE_NOT_AVAILABLE = 409;
	const HTTP_WRONG_PARAMETERS = 422;
	const HTTP_INTERNAL_SERVER_ERROR = 500;

	// Blocking errors
	const ERROR = 						'ERR0001';
	const CONNECTION_ERROR = 			'ERR0002';
	const JSON_PARSE_ERROR = 			'ERR0003';
	const UNAUTHORIZED = 				'ERR0004';
	const MISSING_REQUIRED_PARAMETERS = 'ERR0005';
	const WRONG_WS_PARAMETERS = 		'ERR0006';
	const INVALID_WS = 					'ERR0007';
	const WS_NOT_READY =				'ERR0008';
	const HTTP_WRONG_METHOD =			'ERR0009';
	const RS_ERROR =					'ERR0010';

	// Non blocking errors
	const USER_ALREADY_EXISTS_WARNING =	'WAR0001';

	// Name of the web service to get incidents
	const WS_INCIDENT = 'incident/get';

	// Connection parameters names
	const PROTOCOL = 'protocol';
	const HOST = 'host';
	const PATH = 'path';
	const USERNAME = 'username';
	const PASSWORD = 'password';
	const APIKEY = 'apikey';

	private $_connectionArray;		// contains the connection parameters configuration array

	private $_wsFunction;			// path to the webservice

	private $_httpMethod;			// http method used to call this server
	private $_callParametersArray;	// contains the parameters to give to the remote web service

	private $_error;				// true if an error occurred
	private $_errorMessage;			// contains the error message

	private $_hasData;				// indicates if there are data in the response or not
	private $_emptyResponse;		// indicates if the response is empty or not

	private $_ci; // Code igniter instance

    /**
     * Object initialization
     */
    public function __construct($credentials = null)
    {
		$this->_ci =& get_instance(); // get code igniter instance

		$this->_ci->config->load('extensions/FHC-Core-SAP/SAPClient'); // Loads FHC-SAP configuration

		$this->_setPropertiesDefault(); // properties initialization

        $this->_setConnection($credentials); // loads the configurations
    }

    // --------------------------------------------------------------------------------------------
    // Public methods

    /**
     * Performs a call to a remote web service
     */
    public function call($wsFunction, $httpMethod = self::HTTP_GET_METHOD, $callParametersArray = array())
    {
		// Checks if the webservice name is provided and it is valid
		if ($wsFunction != null && trim($wsFunction) != '')
		{
			$this->_wsFunction = $wsFunction;
		}
		else
		{
			$this->_error(self::MISSING_REQUIRED_PARAMETERS, 'Forgot something?');
		}

		// Checks that the HTTP method required is valid
		if ($httpMethod != null
			&& ($httpMethod == self::HTTP_GET_METHOD || $httpMethod == self::HTTP_POST_METHOD || $httpMethod == self::HTTP_PUT_METHOD))
		{
			$this->_httpMethod = $httpMethod;
		}
		else
		{
			$this->_error(self::WRONG_WS_PARAMETERS, 'Have you ever herd about HTTP methods?');
		}

		// Checks that the webservice parameters are present in an array
		if (is_array($callParametersArray))
		{
			$this->_callParametersArray = $callParametersArray;
		}
		else
		{
			$this->_error(self::WRONG_WS_PARAMETERS, 'Are those parameters?');
		}

		if ($this->isError()) return null; // If an error was raised then return a null value

        return $this->_callRemoteWS($this->_generateURI()); // perform a remote ws call with the given uri
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
		$this->_httpMethod = null;
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

		$this->_httpMethod = null;

		$this->_callParametersArray = array();

		$this->_error = false;

		$this->_errorMessage = '';

		$this->_hasData = false;

		$this->_emptyResponse = false;
	}

    /**
     * Sets the connection
     */
    private function _setConnection($credentials)
    {
		$activeConnectionName = $this->_ci->config->item(self::ACTIVE_CONNECTION);
		$connectionsArray = $this->_ci->config->item(self::CONNECTIONS);

		$this->_connectionArray = $connectionsArray[$activeConnectionName];

		if (!isEmptyArray($credentials)
			&& isset($credentials[self::USERNAME]) && isset($credentials[self::PASSWORD])
			&& !isEmptyString($credentials[self::USERNAME]) && !isEmptyString($credentials[self::PASSWORD]))
		{
			$this->_connectionArray[self::USERNAME] = $credentials[self::USERNAME];
			$this->_connectionArray[self::PASSWORD] = $credentials[self::PASSWORD];
		}
    }

    /**
     * Returns true if the HTTP method used to call this server is GET
     */
    private function _isGET()
    {
        return $this->_httpMethod == self::HTTP_GET_METHOD;
    }

    /**
     * Returns true if the HTTP method used to call this server is POST
     */
    private function _isPOST()
    {
        return $this->_httpMethod == self::HTTP_POST_METHOD;
    }

	/**
     * Returns true if the HTTP method used to call this server is POST
     */
    private function _isPUT()
    {
        return $this->_httpMethod == self::HTTP_PUT_METHOD;
    }

    /**
     * Generate the URI to call the remote web service
     */
    private function _generateURI()
    {
        $uri = sprintf(
            self::URI_TEMPLATE,
            $this->_connectionArray[self::PROTOCOL],
            $this->_connectionArray[self::HOST],
            $this->_connectionArray[self::PATH],
			$this->_wsFunction
        );

		// If the call was performed using a HTTP GET then append the query string to the URI
        if ($this->_isGET())
        {
			$queryString = '';
			$firstParam = true;

			// Create the query string
			foreach ($this->_callParametersArray as $name => $value)
			{
				if (is_array($value)) // if is an array
				{
					foreach ($value as $key => $val)
					{
						$queryString .= ($firstParam == true ? '?' : '&').$name.'[]='.$val;
					}
				}
				else // otherwise
				{
					$queryString .= ($firstParam == true ? '?' : '&').$name.'='.$value;
				}

				$firstParam = false;
			}

            $uri .= $queryString;
        }

        return $uri;
    }

	/**
	 * Performs a remote web service call with the given uri and returns the result after having checked it
	 */
	private function _callRemoteWS($uri)
	{
		$response = null;

		try
		{
			if ($this->_isGET()) // if the call was performed using a HTTP GET...
			{
				$response = $this->_callGET($uri); // ...calls the remote web service with the HTTP GET method
			}
			elseif ($this->_isPOST()) // else if the call was performed using a HTTP POST...
			{
				$response = $this->_callPOST($uri); // ...calls the remote web service with the HTTP POST method
			}
			elseif ($this->_isPUT()) // else if the call was performed using a HTTP PUT...
			{
				$response = $this->_callPUT($uri); // ...calls the remote web service with the HTTP PUT method
			}

			// Checks the response of the remote web service and handles possible errors
			// Eventually here is also called a hook, so the data could have been manipulated
			$response = $this->_checkResponse($response);
		}
		catch (\Httpful\Exception\ConnectionErrorException $cee) // connection error
		{
			$this->_error(self::CONNECTION_ERROR, 'A connection error occurred while calling the remote server');
		}
		// Otherwise another error has occurred, most likely the result of the
		// remote web service is not json so a parse error is raised
		catch (Exception $e)
		{
			$this->_error(self::JSON_PARSE_ERROR, 'The remote server answerd with a not valid json');
		}

		if ($this->isError()) return null; // If an error was raised then return a null value

		return $response;
	}

    /**
     * Performs a remote call using the GET HTTP method
	 * NOTE: parameters in a HTTP GET call are placed into the URI by _generateURI
     */
    private function _callGET($uri)
    {
        return \Httpful\Request::get($uri)
            ->expectsJson() // parse from json
			->authenticateWith($this->_connectionArray[self::USERNAME], $this->_connectionArray[self::PASSWORD])
			->addHeader(self::APIKEY_NAME, $this->_connectionArray[self::APIKEY])
            ->send();
    }

    /**
     * Performs a remote call using the POST HTTP method
     */
    private function _callPOST($uri)
    {
        return \Httpful\Request::post($uri)
            ->expectsJson() // parse response as json
            ->body(http_build_query($this->_callParametersArray)) // post parameters
			->authenticateWith($this->_connectionArray[self::USERNAME], $this->_connectionArray[self::PASSWORD])
			->addHeader(self::APIKEY_NAME, $this->_connectionArray[self::APIKEY])
			->sendsType(\Httpful\Mime::FORM)
            ->send();
    }

	/**
     * Performs a remote call using the PUT HTTP method
     */
    private function _callPUT($uri)
    {
        return \Httpful\Request::put($uri)
            ->expectsJson() // parse response as json
			->authenticateWith($this->_connectionArray[self::USERNAME], $this->_connectionArray[self::PASSWORD])
			->addHeader(self::APIKEY_NAME, $this->_connectionArray[self::APIKEY])
			->body(json_encode($this->_callParametersArray)) // post parameters
            ->sendsJson() // Content-Type JSON
			->send();
    }

    /**
     * Checks the response from the remote web service
     */
    private function _checkResponse($response)
    {
		$checkResponse = null;

		// If NOT an empty response
        if (is_object($response) && isset($response->code) && isset($response->body))
        {
			// Checks the HTTP response code
            if ($response->code == self::HTTP_OK)
            {
				// Checks for errors that do not match the given HTTP code
				if ((isset($response->body->error) && $response->body->error != 0) || isset($response->body->errors))
				{
					// If the called webservice is not the one to get the incident (to avoid loops)
					// and a change id is present then...
					if ($this->_wsFunction != self::WS_INCIDENT && isset($response->body->change_id))
					{
						// ...try to retrieve the incident
						$incident = $this->call(
							self::WS_INCIDENT,
							self::HTTP_GET_METHOD,
							array(
								'change_id' => $response->body->change_id
							)
						);
						// If the incident is successfully retrieved and it contains useful data
						if (isset($incident->result) && is_object($incident->result) && isset($incident->result->success))
						{
							// Returns the given error
							$this->_error(
								self::ERROR,
								'HTTP code is success, but an error was given within the json response: '.$incident->result->success
							);
						}
						else // otherwise
						{
							$this->_error(
								self::ERROR,
								'HTTP code is success, but an error was given within the json response and it is not possible to retrieve the incident error'
							);
						}
					}
					else // otherwise
					{
						$this->_error(
							self::ERROR,
							'HTTP code is success, but an error was given within the json response: '.$response->raw_body
						);
					}
				}
				else // otherwise everything is fine
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
				}

				$checkResponse = $response->body; // returns a success
            }
			else // otherwise checks what error occurred
			{
				// Unauthorized call (wrong username, password, apikey, etc...)
				if ($response->code == self::HTTP_FORBIDDEN)
				{
					$this->_error(self::UNAUTHORIZED, 'The authentication credentials provided are not valid');
				}
				// At the called URL does not answer any webservice
				elseif ($response->code == self::HTTP_NOT_FOUND)
				{
					$this->_error(self::INVALID_WS, 'Does not exist a webservice that answer to this URL');
				}
				// Not supported HTTP method
				elseif ($response->code == self::HTTP_NOT_ALLOWED_METHOD)
				{
					$this->_error(self::HTTP_WRONG_METHOD, 'The used HTTP method is not supported by this webservice');
				}
				// This resource is not currently available
				elseif ($response->code == self::HTTP_RESOURCE_NOT_AVAILABLE)
				{
					$this->_error(self::WS_NOT_READY, 'The called resource is not currently available');
				}
				// Name, value type, quantity of one or more parameters are not valid
				elseif ($response->code == self::HTTP_WRONG_PARAMETERS)
				{
					$this->_error(
						self::WRONG_WS_PARAMETERS,
						'The parameters needed by this webservice are not provided or their value is not valid'
					);
				}
				// Internal server error
				elseif ($response->code == self::HTTP_INTERNAL_SERVER_ERROR)
				{
					$this->_error(self::RS_ERROR, 'A fatal error occurred on the remote server, contact the maintainer');
				}
				else // Every other not contemplated possible error
				{
					// If some info is present
					if (isset($this->raw_body))
					{
						$this->_error(self::ERROR, 'Generic error occurred: '.$this->raw_body);
					}
					else // Otherwise return the entire response
					{
						$this->_error(self::ERROR, 'Generic error occurred: '.json_encode($response));
					}
				}
			}
        }
		else // if the response has no body
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