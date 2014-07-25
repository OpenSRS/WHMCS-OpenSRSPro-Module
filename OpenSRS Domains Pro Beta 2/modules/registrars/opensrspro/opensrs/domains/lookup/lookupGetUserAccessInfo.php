<?php

class lookupGetUserAccessInfo extends openSRS_base {
	private $_domain = "";
	private $_dataObject;
	private $_formatHolder = "";
	public $resultFullRaw;
	public $resultRaw;
	public $resultFullFormated;
	public $resultFormated;

	public function __construct ($formatString, $dataObject) {
		parent::__construct($dataObject);
		$this->_dataObject = $dataObject;
		$this->_formatHolder = $formatString;
		$this->_validateObject ();
	}

	public function __destruct () {
		parent::__destruct();
	}

	// Validate the object
	private function _validateObject (){
		$allPassed = true;
		$domain = "";
		$arraSelected = array ();
		$arraAll = array ();
		$arraCall = array ();

		if (isSet($this->_dataObject->data->domain)) {
			// Grab domain name
			$domain = $this->_dataObject->data->domain;
		} else {
			trigger_error ("oSRS Error - Search domain string not defined.", E_USER_WARNING);
			$allPassed = false;
		}

		// Select non empty one
		if (isSet($this->_dataObject->data->selected) && $this->_dataObject->data->selected != "") $arraSelected = explode (";", $this->_dataObject->data->selected);
		if (isSet($this->_dataObject->data->defaulttld) && $this->_dataObject->data->defaulttld != "") $arraAll = explode (";", $this->_dataObject->data->defaulttld);

		if (count($arraSelected) == 0) {
			if (count($arraAll) == 0){
				$arraCall = array (".com",".net",".org");
			} else {
				$arraCall = $arraAll;
			}
		} else {
			$arraCall = $arraSelected;
		}

		// Call function
		if (!$allPassed) {
			trigger_error ("oSRS Error - Incorrect call.", E_USER_WARNING);
		} else {
			$resObject = $this->_domainTLD ($domain, $arraCall);
		}
	}

	// Selected / all TLD options
	private function _domainTLD($domain, $request){
		$cmd = array(
			"protocol" => "XCP",
			"action" => "GET_USER_ACCESS_INFO",
			"object" => "USERINFO",
			"attributes" => array(
				"domain_name" => $domain
			)
		);


		$xmlCMD = $this->_opsHandler->encode($cmd);					// Flip Array to XML
		$XMLresult = $this->send_cmd($xmlCMD);						// Send XML
		$arrayResult = $this->_opsHandler->decode($XMLresult);		// FLip XML to Array

		// Results
		$this->resultFullRaw = $arrayResult;

		if (isSet($arrayResult['attributes']['lookup']['items'])){
			$this->resultRaw = $arrayResult['attributes']['lookup']['items'];
		} else {
			$this->resultRaw = $arrayResult;
		}

		$this->resultFullFormated = convertArray2Formated ($this->_formatHolder, $this->resultFullRaw);
		$this->resultFormated = convertArray2Formated ($this->_formatHolder, $this->resultRaw);
	}
}