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
 */
function mergeUsersPersonIdArray($jobs)
{
	$mergedUsersArray = array();

	if (count($jobs) == 0) return $mergedUsersArray;

	foreach ($jobs as $job)
	{
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

/**
 * Updates the specified properties of the given jobs with the given values
 */
function updateJobs($jobs, $properties, $values)
{
	// If not valid arrays of properties and values arrays are not of the same size then exit
	if (isEmptyArray($jobs) || isEmptyArray($properties) || isEmptyArray($values)) return;
	if (count($properties) != count($values)) return;

	// For each job
	foreach ($jobs as $job)
	{
		// For each propery of the job
		for ($pI = 0; $pI < count($properties); $pI++)
		{
			// If this property is present in the job object
			if (property_exists($job, $properties[$pI]))
			{
				$job->{$properties[$pI]} = $values[$pI]; // set a new value
			}
		}
	}
}

/**
 * Convert a PHP timestamp date to a SAP ODATA date
 */
function toDate($phpTimestamp)
{
	return '/Date('.$phpTimestamp.'000)/';
}

/**
 * Create an ODATA filter with the given parameters
 */
function filter($arrayValues, $searchFor, $condition, $operator, $function = '')
{
	$filter = '';
	$counter = 0;

	foreach ($arrayValues as $val)
	{
		if ($counter > 0) $filter .= ' '.$operator.' ';

		$filter .= '('.$searchFor.' '.$condition.' '.$function.'\''.$val.'\')';

		$counter++;
	}

	return $filter;
}

