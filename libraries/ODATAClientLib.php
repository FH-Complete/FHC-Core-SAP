<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Manages ODATA API calls
 */
class ODATAClientLib
{
	const HTTP_GET_METHOD = 'GET'; // http get method name
	const HTTP_POST_METHOD = 'POST'; // http post method name
	const HTTP_MERGE_METHOD = 'MERGE'; // http merge method name
	const URI_TEMPLATE = '%s://%s/%s/%s'; // URI format
	const TOKEN_HEADER_FETCH_NAME = 'x-csrf-token'; // token header name when fetching
	const TOKEN_HEADER_FETCH_VALUE = 'fetch'; // token header value name when fetching
	const COOKIE_HEADER_FETCH_NAME = 'set-cookie'; // cookie header name when fetching
	const MERGE_HEADER_NAME = 'X-HTTP-Method'; // merge header name
	const MERGE_HEADER_VALUE = 'MERGE'; // merge header value
	const TOKEN_HEADER_SEND_NAME = 'X-CSRF-Token'; // token header name when posting
	const COOKIE_HEADER_SEND_NAME = 'Cookie'; // cookie header name when posting
	const ACCEPT_HEADER_NAME = 'Accept'; // accept header name
	const ACCEPT_HEADER_VALUE = 'application/json'; // accept header value

	// Configs parameters names
	const ACTIVE_CONNECTION = 'odata_active_connection';
	const CONNECTIONS = 'odata_connections';

	const HTTP_OK = 200; // HTTP success code
	const HTTP_CREATED = 201; // HTTP success code created
	const HTTP_NO_CONTENT = 204; // HTTP success code no content (aka successfully updated)

	// HTTP error codes
	const HTTP_UNAUTHORIZED = 401;
	const HTTP_FORBIDDEN = 403;
	const HTTP_NOT_FOUND = 404;
	const HTTP_NOT_ALLOWED_METHOD = 405;
	const HTTP_RESOURCE_NOT_AVAILABLE = 409;
	const HTTP_WRONG_PARAMETERS = 422;
	const HTTP_INTERNAL_SERVER_ERROR = 500;

	// Blocking errors
	const ERROR =				'ERR0001';
	const CONNECTION_ERROR = 		'ERR0002';
	const JSON_PARSE_ERROR = 		'ERR0003';
	const UNAUTHORIZED = 			'ERR0004';
	const MISSING_REQUIRED_PARAMETERS =	'ERR0005';
	const WRONG_WS_PARAMETERS = 		'ERR0006';
	const INVALID_WS = 			'ERR0007';
	const WS_NOT_READY =			'ERR0008';
	const HTTP_WRONG_METHOD =		'ERR0009';
	const RS_ERROR =			'ERR0010';

	// Connection parameters names
	const PROTOCOL = 'protocol';
	const HOST = 'host';
	const PATH = 'path';
	const USERNAME = 'username';
	const PASSWORD = 'password';

	private $_connectionArray;	// contains the connection parameters configuration array

	private $_wsFunction;		// path to the webservice

	private $_httpMethod;		// http method used to call this server
	private $_callParametersArray;	// contains the parameters to give to the remote web service

	private $_error;		// true if an error occurred
	private $_errorMessage;		// contains the error message

	private $_hasData;		// indicates if there are data in the response or not
	private $_emptyResponse;	// indicates if the response is empty or not

	private $_ci;			// Code igniter instance

	/**
	 * Object initialization
	 */
	public function __construct($credentials = null)
	{
		$this->_ci =& get_instance(); // get code igniter instance
		
		$this->_ci->config->load('extensions/FHC-Core-SAP/ODATAClient'); // Loads FHC-IDAM configuration
		
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
	    		&& ($httpMethod == self::HTTP_GET_METHOD || $httpMethod == self::HTTP_POST_METHOD || $httpMethod == self::HTTP_MERGE_METHOD))
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
	 * Returns true if the HTTP method used to call this server is MERGE
	 */
	private function _isMERGE()
	{
	    return $this->_httpMethod == self::HTTP_MERGE_METHOD;
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
			elseif ($this->_isMERGE()) // else if the call was performed using a HTTP MERGE...
			{
				$response = $this->_callMERGE($uri); // ...calls the remote web service with the HTTP MERGE method
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
	 * NOTE: parameters in a HTTP GET call are appended to the URI by _generateURI
	 */
	private function _callGET($uri)
	{
		return \Httpful\Request::get($uri)
			->expectsJson() // dangerous expectations
			->addHeader(self::ACCEPT_HEADER_NAME, self::ACCEPT_HEADER_VALUE) // not so common but required
			->authenticateWith($this->_connectionArray[self::USERNAME], $this->_connectionArray[self::PASSWORD])
			->send();
	}

	/**
	 * Get token and session cookie values from the header of a HTTP GET request to the same URI
	 * that later would be used to perform a POST GET
	 * Such GET call is performed without any parameter
	 */
	private function _getHeader($uri)
	{
		$header = array();

		// HTTP GET call without any parameter
		$response = \Httpful\Request::get($uri)
			->expectsJson() // dangerous expectations
			->addHeader(self::TOKEN_HEADER_FETCH_NAME, self::TOKEN_HEADER_FETCH_VALUE) // token with option to fetch
			->addHeader(self::ACCEPT_HEADER_NAME, self::ACCEPT_HEADER_VALUE) // not so common but required
			->authenticateWith($this->_connectionArray[self::USERNAME], $this->_connectionArray[self::PASSWORD])
			->send();

		// Checks if the header is present and the needed data are present
		if (isset($response->headers) && is_object($response->headers) && method_exists($response->headers, 'toArray'))
		{
			$headerArray = $response->headers->toArray();

			// Retrieves the token
			if (isset($headerArray[self::TOKEN_HEADER_FETCH_NAME]))
			{
				$header[self::TOKEN_HEADER_FETCH_NAME] = $headerArray[self::TOKEN_HEADER_FETCH_NAME];
			}

			// Retrieves the session cookie
			if (isset($headerArray[self::COOKIE_HEADER_FETCH_NAME]))
			{
				$header[self::COOKIE_HEADER_FETCH_NAME] = $headerArray[self::COOKIE_HEADER_FETCH_NAME];
			}
		}

		// If was not possible to retrieve all the data then return null
		if (count($header) != 2) return null;

		return $header;
	}

	/**
	 * Performs a remote call using the POST HTTP method
	 */
	private function _callPOST($uri)
	{
		// If the header data are present then perform the call...
		if (($header = $this->_getHeader($uri)) != null)
		{
			return \Httpful\Request::post($uri)
				->expectsJson() // dangerous expectations
				->addHeader(self::TOKEN_HEADER_SEND_NAME, $header[self::TOKEN_HEADER_FETCH_NAME]) // token
				->addHeader(self::COOKIE_HEADER_SEND_NAME, $header[self::COOKIE_HEADER_FETCH_NAME]) // session cookie value
				->addHeader(self::ACCEPT_HEADER_NAME, self::ACCEPT_HEADER_VALUE) // not so common but required
				->body($this->_callParametersArray) // post parameters
				->authenticateWith($this->_connectionArray[self::USERNAME], $this->_connectionArray[self::PASSWORD])
				->sendsJson() // content type json
				->send();
		}
		else // ...otherwise return a null value
		{
			return null;
		}
	}

	/**
	 * Performs a remote call using the MERGE HTTP method
	 */
	private function _callMERGE($uri)
	{
		// If the header data are present then perform the call...
		if (($header = $this->_getHeader($uri)) != null)
		{
			return \Httpful\Request::post($uri)
				->expectsJson() // dangerous expectations
				->addHeader(self::MERGE_HEADER_NAME, self::MERGE_HEADER_VALUE) // merge option
				->addHeader(self::TOKEN_HEADER_SEND_NAME, $header[self::TOKEN_HEADER_FETCH_NAME]) // token
				->addHeader(self::COOKIE_HEADER_SEND_NAME, $header[self::COOKIE_HEADER_FETCH_NAME]) // session cookie value
				->addHeader(self::ACCEPT_HEADER_NAME, self::ACCEPT_HEADER_VALUE) // not so common but required
				->body($this->_callParametersArray) // post parameters
				->authenticateWith($this->_connectionArray[self::USERNAME], $this->_connectionArray[self::PASSWORD])
				->sendsJson() // content type json
				->send();
		}
		else // ...otherwise return a null value
		{
			return null;
		}
	}

	/**
	 * Checks the response from the remote web service
	 */
	private function _checkResponse($response)
	{
		$checkResponse = null;
	
		// If NOT an empty response
		if (is_object($response) && isset($response->code))
		{
			// Checks the HTTP response code
			// If it is a success
			if ($response->code == self::HTTP_OK || $response->code == self::HTTP_CREATED || $response->code == self::HTTP_NO_CONTENT)
			{
				// If body is not empty
				if (isset($response->body))
				{
					// Checks for logic errors
					if (isset($response->body->error))
					{
						// Try to get an error code from the response if present and valid
						$errorCode = self::ERROR;
						if (isset($response->body->error->code) && !isEmptyString($response->body->error->code))
						{
							$errorCode = $response->body->error->code;
						}

						// Try to get an error message from the response if present and valid
						$errorMessage = 'Generic error: '.$response->raw_body;
						if (isset($response->body->error->message) && isset($response->body->error->message->value)
							&& !isEmptyString($response->body->error->message->value))
						{
							$errorMessage = $response->body->error->message->value;
						}

						$this->_error($errorCode, $errorMessage);
					}
					else // otherwise everything is fine
					{
						// If data are present in the body of the response
						if (isset($response->body->d) && isset($response->body->d->results))
						{
							$checkResponse = $response->body->d->results; // returns a success

							// Set property _hasData
			    				$this->_hasData = !isEmptyArray($response->body->d->results);
						}
			    		}
				}
				else // ...if body empty
				{
					$this->_hasData = false;

					// If the response body is empty and an update was previously performed then return the request payload
					// alias: data sent within the request
					if ($response->code == self::HTTP_NO_CONTENT && isset($response->request) && isset($response->request->payload))
					{
						$checkResponse = $response->request->payload;
					}
				}
			}
			else // otherwise checks what error occurred
			{
				$errorCode = self::RS_ERROR; // generic error code by default
				$errorMessage = 'A fatal error occurred on the remote server, contact the maintainer'; // default error message

				// Checks if the remote system answered with an error message
				if (isset($response->body) && isset($response->body->error) && isset($response->body->error->message)
					&& isset($response->body->error->message->value))
				{
					$errorMessage = $response->body->error->message->value;
				}

				// Unauthorized call (wrong username, password...)
				if ($response->code == self::HTTP_UNAUTHORIZED || $response->code == self::HTTP_FORBIDDEN)
				{
					$errorCode = self::UNAUTHORIZED;
					$errorMessage = 'The authentication credentials provided are not valid';
				}
				// At the called URL does not answer any webservice
				elseif ($response->code == self::HTTP_NOT_FOUND)
				{
					$errorCode = self::INVALID_WS;
					$errorMessage = 'Does not exist a webservice that answer to this URL, malformed URL or data not found';
				}
				// Not supported HTTP method
				elseif ($response->code == self::HTTP_NOT_ALLOWED_METHOD)
				{
					$errorCode = self::HTTP_WRONG_METHOD;
					$errorMessage = 'The used HTTP method is not supported by this webservice';
				}
				// This resource is not currently available
				elseif ($response->code == self::HTTP_RESOURCE_NOT_AVAILABLE)
				{
					$errorCode = self::WS_NOT_READY;
					$errorMessage = 'The called resource is not currently available';
				}
				// Name, value type, quantity of one or more parameters are not valid
				elseif ($response->code == self::HTTP_WRONG_PARAMETERS)
				{
					$errorCode = self::WRONG_WS_PARAMETERS;
					$errorMessage = 'The parameters needed by this webservice are not provided or their value is not valid';
				}
				// Internal server error
				elseif ($response->code == self::HTTP_INTERNAL_SERVER_ERROR)
				{
					// defaults previously set
				}
				else // Every other not contemplated possible error
				{
					// If some info is present
					if (isset($response->raw_body))
					{
						$errorCode = self::ERROR;
						$errorMessage = 'Generic error occurred: '.$response->raw_body;
					}
					else // Otherwise return the entire JSON encoded response
					{
						$errorCode = self::ERROR;
						$errorMessage = 'Generic error occurred: '.json_encode($response);
					}
				}

				// Finally set the error!
				$this->_error($errorCode, $errorMessage);
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

