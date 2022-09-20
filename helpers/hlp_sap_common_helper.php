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
 *
 */
function mergeUidArray($jobs, $jobsAmount = 99999)
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
				$mergedUsersArray[] = $el->uid; //
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

/**
 * Removes duplicated elements from the the given array
 * Array elements: {person_id => int}
 */
function uniquePersonIdArray($personIdArray)
{
	$uniquePersonIdArray = array(); // returned array

	// For each element of the given array
	foreach ($personIdArray as $pia)
	{
		$found = false; // found flag

		// For each element of the array that will be returned
		foreach ($uniquePersonIdArray as $upia)
		{
			// If the same element is found in the array that will be returned
			if ($pia->person_id == $upia->person_id)
			{
				$found = true; // set the flag as true
				break; // stop looping
			}
		}

		// If the element was not found in the array that will be returned then store it in this array
		if (!$found) $uniquePersonIdArray[] = $pia;
	}

	return $uniquePersonIdArray; // return the new array with unique elements
}

/**
 * Gets a list of jobs as parameter and returns a merged array of purchase orders
 */
function mergePurchaseOrdersIdArray($jobs)
{
	$mergedPOsArray = array();

	// If no jobs then return an empty array
	if (count($jobs) == 0) return $mergedPOsArray;

	// For each job
	foreach ($jobs as $job)
	{
		// Decode the json input
		$decodedInput = json_decode($job->input);

		// If decoding was fine
		if ($decodedInput != null)
		{
			$mergedPOsArray[] = $decodedInput->purchase_order_id;
		}
	}

	return $mergedPOsArray;
}

/**
 * Removes duplicated elements from the the given array
 * Array elements: {mitarbeiter_uid => string}
 */
function uniqudMitarbeiterUidArray($mitarbeiterUidArray)
{
	$uniqueMitarbeiterUidArray = array(); // returned array

	// For each element of the given array
	foreach ($mitarbeiterUidArray as $muid)
	{
		$found = false; // found flag

		// For each element of the array that will be returned
		foreach ($uniqueMitarbeiterUidArray as $umuid)
		{
			// If the same element is found in the array that will be returned
			if ($muid->uid == $umuid->uid)
			{
				$found = true; // set the flag as true
				break; // stop looping
			}
		}

		// If the element was not found in the array that will be returned then store it in this array
		if (!$found) $uniqueMitarbeiterUidArray[] = $muid;
	}

	return $uniqueMitarbeiterUidArray; // return the new array with unique elements
}

// alle Informationen aus dem IBAN rausholen um die SAP richtig zu übermitteln
function checkIBAN($iban)
{
	if (isEmptyString($iban))
		return false;

	$ibanCountry = substr($iban, 0, 2);

	$ibanArr = array(
		'AT' => array('accnumber' => 11, 'bankcode' => 5, 'length' => 20)
	);

	if (strlen($iban) !== $ibanArr[$ibanCountry]['length'])
		return false;

	$accNumber = substr($iban, 4 + $ibanArr[$ibanCountry]['bankcode'], $ibanArr[$ibanCountry]['accnumber']);
	$bankNumber = substr($iban, 4, $ibanArr[$ibanCountry]['bankcode']);

	return array('country' => $ibanCountry, 'iban' => $iban, 'accNumber' => $accNumber, 'bankNumber' => $bankNumber);
}

