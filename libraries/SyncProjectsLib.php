<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * This library contains the logic used to perform data synchronization between FHC and SAP Business by Design
 */
class SyncProjectsLib
{
	// Jobs types used by this lib
	const SAP_PROJECTS_CREATE = 'SAPProjectsCreate';
	const SAP_PROJECTS_UPDATE = 'SAPProjectsUpdate';

	private $_ci; // Code igniter instance

	/**
	 * Object initialization
	 */
	public function __construct()
	{
		$this->_ci =& get_instance(); // get code igniter instance

		// Loads model ProjectsModel
		$this->_ci->load->model('extensions/FHC-Core-SAP/ODATA/Projects_model', 'ProjectsModel');
	}

	// --------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Create a new project using the given project id
	 */
	public function create($projectId)
	{
		return $this->_ci->ProjectsModel->create($projectId);
	}
	
	/**
	 * Updates an existing project using the given project id
	 */
	public function update($projectId, $name)
	{
		return $this->_ci->ProjectsModel->update($projectId, $name);
	}

	/**
	 * Return the raw result of projekt/ProjectCollection
	 */
	public function getProjects()
	{
		return $this->_ci->ProjectsModel->getProjects();
	}
	
	/**
	 * Return the raw result of projekt/ProjectCollection('$id')
	 */
	public function getProjectById($id)
	{
		return $this->_ci->ProjectsModel->getProjectById($id);
	}
	
	/**
	 * Return the raw result of projekt/ProjectCollection('$id')/ProjectTask
	 */
	public function getProjectTasks($id)
	{
		return $this->_ci->ProjectsModel->getProjectTasks($id);
	}
}

