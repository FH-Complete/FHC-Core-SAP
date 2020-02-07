<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 *
 */
class SAP_Users extends JOB_Controller
{
	/**
	 * Controller initialization
	 */
	public function __construct()
	{
		parent::__construct();

		// Loads SAPClientModel
		$this->load->model('extensions/FHC-Core-SAP/SAPClient_model', 'SAPClientModel');
	}

	//------------------------------------------------------------------------------------------------------------------
	// Public methods

	/**
	 * Synchronize groups in LDAP
	 */
	public function syncAll()
	{
		$this->logInfo('Groups synchronization started');

		// Loads VWGruppen_model
		$this->load->model('extensions/FHC-Core-SAP/VWGruppen_model', 'VWGruppenModel');

		$dbGroups = $this->VWGruppenModel->getGroups();

		// If groups are present
		if (hasData($dbGroups))
		{
			$prevGroup = '';

			$groups = array();

			$group = new stdClass();
			$group->memberuids = array();

			// For each group from DB
			foreach (getData($dbGroups) as $dbGroup)
			{
				// New group
				if ($prevGroup != $dbGroup->gruppe_kurzbz)
				{
					$prevGroup = $dbGroup->gruppe_kurzbz; // Copy current group name

					if (count($group->memberuids) > 0) $groups[] = $group; // assign group as new element of groups

					// Reset group object
					$group = new stdClass();
					$group->memberuids = array($dbGroup->uid); // first user
					$group->cn = $dbGroup->gruppe_kurzbz; // group name
				}
				else
				{
					$group->memberuids[] = $dbGroup->uid; // Add user to this group
				}
			}

			// Perform the call to synchronize groups
			$callGroupSync = $this->SAPClientModel->sync_groups($groups);

			// If an error occurred
			if (isError($callGroupSync))
			{
				$this->logError($callGroupSync->retval, $groups);
			}
		}
		else
		{
			$this->logInfo('No groups were found!?');
		}

		$this->logInfo('Groups synchronization ended');
	}

	/**
	 * Synchronize SAMBA groups in LDAP
	 */
	public function syncAllSamba()
	{
		$this->logInfo('Samba groups synchronization started');

		// Loads VWGruppen_model
		$this->load->model('extensions/FHC-Core-SAP/VWGruppen_model', 'VWGruppenModel');

		$dbGroups = $this->VWGruppenModel->getSambaGroups();

		// If groups are present
		if (hasData($dbGroups))
		{
			$prevGroup = '';

			$groups = array();

			$group = new stdClass();
			$group->memberuids = array();

			// For each group from DB
			foreach (getData($dbGroups) as $dbGroup)
			{
				// New group
				if ($prevGroup != $dbGroup->gruppe_kurzbz)
				{
					$prevGroup = $dbGroup->gruppe_kurzbz; // Copy current group name

					if (count($group->memberuids) > 0) $groups[] = $group; // assign group as new element of groups

					// Reset group object
					$group = new stdClass();
					$group->memberuids = array($dbGroup->uid); // first user
					$group->cn = $dbGroup->gruppe_kurzbz; // group name
				}
				else
				{
					$group->memberuids[] = $dbGroup->uid; // Add user to this group
				}
			}

			// Perform the call to synchronize groups
			$callGroupSync = $this->SAPClientModel->sync_ad_groups($groups);

			// If an error occurred
			if (isError($callGroupSync))
			{
				$this->logError($callGroupSync->retval, $groups);
			}
		}
		else
		{
			$this->logInfo('No SAMBA groups were found!?');
		}

		$this->logInfo('Samba groups synchronization ended');
	}
}
