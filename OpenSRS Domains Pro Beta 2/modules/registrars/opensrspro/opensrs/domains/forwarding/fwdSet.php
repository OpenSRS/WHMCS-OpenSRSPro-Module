<?php
/*
 *  Required object values:
 *  data - 
 */
 
class fwdSet extends openSRS_base {
	private $_dataObject;
	private $_formatHolder = "";
	public $resultFullRaw;
	public $resultRaw;
	public $resultFullFormatted;
	public $resultFormatted;

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
		
		// Command required values
		if ((!isSet($this->_dataObject->data->cookie) || $this->_dataObject->data->cookie == "") && (!isSet($this->_dataObject->data->bypass) || $this->_dataObject->data->bypass == "")) {
			trigger_error ("oSRS Error - cookie / bypass is not defined.", E_USER_WARNING);
			$allPassed = false;
		}
		if ( $this->_dataObject->data->cookie != "" && $this->_dataObject->data->bypass != "" ) {
			trigger_error ("oSRS Error - Both cookie and bypass cannot be set in one call.", E_USER_WARNING);
			$allPassed = false;
		}
		
		if (!isSet($this->_dataObject->data->domain) || $this->_dataObject->data->domain == "") {
			trigger_error ("oSRS Error - domain is not defined.", E_USER_WARNING);
			$allPassed = false;
		}
		if (!isSet($this->_dataObject->data->subdomain) || $this->_dataObject->data->subdomain == "") {
			trigger_error ("oSRS Error - subdomain is not defined.", E_USER_WARNING);
			$allPassed = false;
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
			'action' => 'set_domain_forwarding',
			'object' => 'domain',
//			'cookie' => $this->_dataObject->data->cookie,
			'attributes' => array (
				'domain' => $this->_dataObject->data->domain, 
				'forwarding' => array (
					array (
					'subdomain' => $this->_dataObject->data->subdomain
					)
					
				)
			)
		);
		
		// Cookie / bypass
		if (isSet($this->_dataObject->data->cookie) && $this->_dataObject->data->cookie != "") $cmd['cookie'] = $this->_dataObject->data->cookie;
		if (isSet($this->_dataObject->data->bypass) && $this->_dataObject->data->bypass != "") $cmd['domain'] = $this->_dataObject->data->bypass;

		// Command optional values
		if (isSet($this->_dataObject->data->destination_urls) && $this->_dataObject->data->destination_urls != "") $destination_urls = $this->_dataObject->data->destination_urls;
                if (isSet($this->_dataObject->data->subdomains) && $this->_dataObject->data->subdomains != "") $subdomains = $this->_dataObject->data->subdomains;
                if (isSet($this->_dataObject->data->subdomain) && $this->_dataObject->data->subdomain != "") $subdomains = $this->_dataObject->data->subdomain;
                if (isSet($this->_dataObject->data->descriptions) && $this->_dataObject->data->descriptions != "") $descriptions = $this->_dataObject->data->descriptions;
		if (isSet($this->_dataObject->data->enableds) && $this->_dataObject->data->enableds != "") $enables = $this->_dataObject->data->enableds;
		if (isSet($this->_dataObject->data->keywords) && $this->_dataObject->data->keywords != "") $keywords = $this->_dataObject->data->keywords;
		if (isSet($this->_dataObject->data->maskeds) && $this->_dataObject->data->maskeds != "") $maskeds = $this->_dataObject->data->maskeds;
		if (isSet($this->_dataObject->data->titles) && $this->_dataObject->data->titles != "") $titles = $this->_dataObject->data->titles;

                if ($destination_urls != "" ){
			$destinations_array = explode(",", $destination_urls);
                        $subdomains_array = explode(",", $subdomains);
                        $maskeds_array = explode(",", $maskeds);
                        
                        if( count($destinations_array) == count($subdomains_array) &&
                            count($destinations_array) == count($maskeds_array) ){

                            $i = 0;

                            
                            
                            
                            foreach($subdomains_array as $subdomain){
                                $cmd['attributes']['forwarding'][$i] = array(
                                        'subdomain' => $subdomain,
                                        'destination_url' => $destinations_array[$i],
                                        'masked' => $maskeds_array[$i],
                                        'enabled' => "1"
                                );

                                $i++;
                            }
                        }
		}
                
		$xmlCMD = $this->_opsHandler->encode($cmd);					// Flip Array to XML
		$XMLresult = $this->send_cmd($xmlCMD);						// Send XML
		$arrayResult = $this->_opsHandler->decode($XMLresult);		// Flip XML to Array

		// Results
		$this->resultFullRaw = $arrayResult;
		$this->resultRaw = $arrayResult;
		$this->resultFullFormatted = convertArray2Formatted ($this->_formatHolder, $this->resultFullRaw);
		$this->resultFormatted = convertArray2Formatted ($this->_formatHolder, $this->resultRaw);
	}
}
