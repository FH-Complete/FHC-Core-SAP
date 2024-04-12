<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

use \CI3_Events as Events;
use \FHCAPI_Controller as FHCAPI_Controller;

function event_sap_validate_update_allowed($buchungsnr)
{
	$CI =& get_instance();

	$CI->load->model('crm/Konto_model', 'KontoModel');

	$result = $CI->KontoModel->load($buchungsnr);
	if (isError($result)) {
		$CI->addError(getError($result), FHCAPI_Controller::ERROR_TYPE_DB);
		return false;
	}
	if (!hasData($result)) {
		$CI->addError(
			$CI->p_sap->t('sap', 'error_noBuchung', [
				'buchungsnr' => $buchungsnr
			]),
			FHCAPI_Controller::ERROR_TYPE_GENERAL
		);
		return false;
	}
	$data = current(getData($result));

	$CI->load->model('extensions/FHC-Core-SAP/SAPSalesOrder_model', 'SAPSalesOrderModel');

	$result = $CI->SAPSalesOrderModel->isBuchungAllowedToChange($buchungsnr, $data->buchungsnr_verweis);
	
	if (isError($result))
		$CI->addError(getError($result), FHCAPI_Controller::ERROR_TYPE_DB);
	elseif ($result->retval)
		return true;

	$CI->load->library('AuthLib');
	$CI->load->library('PermissionLib');

	return $CI->permissionlib->isBerechtigt('student/zahlungAdmin', 'suid', $data->studiengang_kz);
}

Events::on('konto_update_validation', function ($form_validation) {
	$CI =& get_instance();

	$CI->load->library('PhrasesLib', ['sap'], 'p_sap');

	$form_validation->set_rules(
		'buchungsnr',
		'Buchungsnr',
		'event_sap_validate_update_allowed',
		[
			'event_sap_validate_update_allowed' => $CI->p_sap->t('sap', 'error_buchungLocked')
		]
	);
});

Events::on('konto_query', function () {
	$CI =& get_instance();
	$dbTable = $CI->KontoModel->getDbTable();

	$CI->KontoModel->addSelect('(
		SELECT COUNT(1) 
		FROM sync.tbl_sap_salesorder so 
		WHERE so.buchungsnr=' . $dbTable . '.buchungsnr 
		OR so.buchungsnr=' . $dbTable . '.buchungsnr_verweis
		LIMIT 1
	) AS sap', false);
});

function event_sap_stv_conf_student($func)
{
	$config =& $func();
	
	$config['konto']['config']['additionalCols'][] = [
		'field' => 'sap',
		'title' => 'SAP',
		'formatter' => 'tickCross'
	];
}

Events::on('stv_conf_student', 'event_sap_stv_conf_student');

Events::on('stv_conf_students', 'event_sap_stv_conf_student');
