<?php
/*
 *  Required object values:
 *  data -
 */

class resellerCreate extends openSRS_base {
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

		$reqData = array ("admin_email","address1","city","country","email","first_name","last_name","postal_code","state","password","username");
		for ($i = 0; $i < count($reqData); $i++){
			if ($this->_dataObject->data->$reqData[$i] == "") {
				trigger_error ("oSRS Error - ". $reqData[$i] ." is not defined.", E_USER_WARNING);
				$allPassed = false;
			}
		}

		// Run the command
		if ($allPassed) {
			// Execute the command
			$this->_processRequest ();
		} else {
			trigger_error ("oSRS Error - Incorrect call.", E_USER_WARNING);
		}
	}




	// Post validation functions
	private function _processRequest (){
		$cmd = array(
			'protocol' => 'XCP',
			'action' => 'create',
			'object' => 'reseller',
			'attributes' => array (
				'admin_email' => $this->_dataObject->data->admin_email,
				'contact_set' =>  array (
					'address1' => $this->_dataObject->data->address1,
					'city' => $this->_dataObject->data->city,
					'country' => $this->_dataObject->data->country,
					'email' => $this->_dataObject->data->email,
					'first_name' => $this->_dataObject->data->first_name,
					'last_name' => $this->_dataObject->data->last_name,
					'postal_code' => $this->_dataObject->data->postal_code,
					'state' => $this->_dataObject->data->state,
					'phone' => $this->_dataObject->data->phone,
					'org_name' => $this->_dataObject->data->org_name,
				),
				// 'nameservers' => array (),
				'password' => $this->_dataObject->data->password,
				'username' => $this->_dataObject->data->username
			)
		);

		// Command optional values
		if (isSet($this->_dataObject->data->address2) && $this->_dataObject->data->address2 != "") $cmd['attributes']['address2'] = $this->_dataObject->data->address2;
		if (isSet($this->_dataObject->data->address3) && $this->_dataObject->data->address3 != "") $cmd['attributes']['address3'] = $this->_dataObject->data->address3;
		if (isSet($this->_dataObject->data->fax) && $this->_dataObject->data->fax != "") $cmd['attributes']['fax'] = $this->_dataObject->data->fax;
		if (isSet($this->_dataObject->data->phone) && $this->_dataObject->data->phone != "") $cmd['attributes']['phone'] = $this->_dataObject->data->phone;
		if (isSet($this->_dataObject->data->org_name) && $this->_dataObject->data->org_name != "") $cmd['attributes']['org_name'] = $this->_dataObject->data->org_name;

		if (isSet($this->_dataObject->data->fqdn1) && $this->_dataObject->data->fqdn1 != "") $cmd['nameservers']['fqdn1'] = $this->_dataObject->data->fqdn1;
		if (isSet($this->_dataObject->data->fqdn2) && $this->_dataObject->data->fqdn2 != "") $cmd['nameservers']['fqdn2'] = $this->_dataObject->data->fqdn2;
		if (isSet($this->_dataObject->data->fqdn3) && $this->_dataObject->data->fqdn3 != "") $cmd['nameservers']['fqdn3'] = $this->_dataObject->data->fqdn3;

/* Loop for Nameserver information
		if (isSet($this->_dataObject->data->nameservers) && $this->_dataObject->data->nameservers != "") {
			// 'fqdn1' => 'parking1.mdnsservice.com'
			$tmpArray = explode (",", $this->_dataObject->data->nameservers);
			for ($i=0; $i<count($tmpArray); $i++){
				$cmd['attributes']['nameservers']['fqdn'. ($i+1)] = $tmpArray[$i];
			}
		}
*/

		$xmlCMD = $this->_opsHandler->encode($cmd);					// Flip Array to XML
		$XMLresult = $this->send_cmd($xmlCMD);						// Send XML
		$arrayResult = $this->_opsHandler->decode($XMLresult);		// Flip XML to Array

		// Results
		$this->resultFullRaw = $arrayResult;
		$this->resultRaw = $arrayResult;
		$this->resultFullFormated = convertArray2Formated ($this->_formatHolder, $this->resultFullRaw);
		$this->resultFormated = convertArray2Formated ($this->_formatHolder, $this->resultRaw);
	}
}
