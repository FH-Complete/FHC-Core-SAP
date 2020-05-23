<?php

if (! defined('BASEPATH')) exit('No direct script access allowed');

// ------------------------------------------------------------------------
// Collection of utility functions for general purpose
// ------------------------------------------------------------------------

/**
 * Generates a unique UUID
 */
function generateUUID()
{
	$data = openssl_random_pseudo_bytes(16);

	$data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
	$data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

	return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Generate a (almost) unique id to be used as id of each SAP SOAP call
 * Using uniqid here should be fine
 */
function generateUID($prefix)
{
	return uniqid($prefix, true);
}

/**
 * Gets a list of jobs as parameter and returns a merged array of person ids
 * Sets all jobs status to done
 */
function mergeUsersPersonIdArray($jobs)
{
	$mergedUsersArray = array();

	if (count($jobs) == 0) return $mergedUsersArray;

	foreach ($jobs as $job)
	{
		$job->status = JobsQueueLib::STATUS_DONE; // set all jobs as done

		$decodedInput = json_decode($job->input);
		if ($decodedInput != null)
		{
			foreach ($decodedInput as $el)
			{
				$mergedUsersArray[] = $el->person_id;
			}
		}
	}
	return $mergedUsersArray;
}

