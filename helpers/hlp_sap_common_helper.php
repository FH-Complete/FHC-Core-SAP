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
function mergeUsersPersonIdArray($jobs, $jobsAmount = 99999)
{
	$jobsCounter = 0;
	$mergedUsersArray = array();

	// If no jobs then return an empty array
	if (count($jobs) == 0) return $mergedUsersArray;

	// For each job
	foreach ($jobs as $job)
	{
		// Decode the json input
		$decodedInput = json_decode($job->input);

		// If decoding was fine
		if ($decodedInput != null)
		{
			// For each element in the array
			foreach ($decodedInput as $el)
			{
				$mergedUsersArray[] = $el->person_id; //
			}
		}

		$jobsCounter++; // jobs counter

		if ($jobsCounter >= $jobsAmount) break; // if the required amount is reached then exit
	}

	return $mergedUsersArray;
}

/**
 * Convert a PHP timestamp date to a SAP ODATA date
 */
function toDate($phpTimestamp)
{
	if (isEmptyString($phpTimestamp)) return null;

	return '/Date('.$phpTimestamp.'000)/';
}

/**
 * Convert a SAP ODATA date to a PHP timestamp date
 */
function toTimestamp($sapTimestamp)
{
	if ($sapTimestamp == null) return null;

	$phpTimestamp = str_replace('/Date(', '', $sapTimestamp);
	$phpTimestamp = str_replace(')/', '', $phpTimestamp);

	return substr($phpTimestamp, 0, 10);
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

