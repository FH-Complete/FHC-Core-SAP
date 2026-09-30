<?php

require_once 'CoreAPIModel.php';

/**
 * This implements all the calls for:
 * API set name SAPCoreAPI
 * Service name ManageSonstigeRechnungenGutschriften
 */
class Y95KEPJZY_WS_CRPE_ManageRecPayEntry_model extends CoreAPIModel
{
	/**
	 * Set the properties to perform SOAP calls
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_serviceName = 'Y95KEPJZY_WS_CRPE_ManageRecPayEntry'; // service name
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * SOAP function:
	 */
	public function create($parameters)
	{
		return $this->_call('Create', $parameters);
	}
	
	public function createPayReceivablesEntry($parameters)
	{
		return $this->_call('createPayReceivablesEntry', $parameters);
	}
	
	public function read($parameters)
	{
		return $this->_call('Read', $parameters);
	}
}

