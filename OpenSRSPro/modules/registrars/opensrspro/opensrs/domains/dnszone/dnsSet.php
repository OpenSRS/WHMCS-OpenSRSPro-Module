<?php

/**
* dnsSet class file
*
* @category OpenSRS
* @package  OpenSRS
* @author   Keiji Suzuki <ksuzuki@tucows.com>
* @license  MIT License (http://www.opensource.org/licenses/mit-license.php)
* @link
*/
 
class DnsSet extends openSRS_base
{
    private $_dataObject;
    private $_formatHolder = "";
    public $resultFullRaw;
    public $resultRaw;
    public $resultFullFormatted;
    public $resultFormatted;

    /**
    * __construct
    *
    * @param  string  $formatString  	Format type
    * @param  hash    $dataObject 	Containing domain key/value pairs
    */

    public function __construct ($formatString, $dataObject) {
    	parent::__construct($dataObject);
    	$this->_dataObject = $dataObject;
    	$this->_formatHolder = $formatString;
    	$this->_validateObject ();
    }

    public function __destruct () {
    	parent::__destruct();
    }

    private function _validateObject (){
        $allPassed = true;

        // Command required values
        if (!isSet($this->_dataObject->data->domain) || $this->_dataObject->data->domain == "") {
			trigger_error ("oSRS Error - domain is not defined.", E_USER_WARNING);
			$allPassed = false;
		}
				
		if ($allPassed) {
			$this->_processRequest ();
		} else {
			trigger_error ("oSRS Error - Incorrect call.", E_USER_WARNING);
		}
	}

	// Post validation functions
	private function _processRequest (){

		$cmd = array(
			'protocol' => 'XCP',
			'action' => 'set_dns_zone',
			'object' => 'domain',
			'attributes' => array (
				'domain' => $this->_dataObject->data->domain,
			)
		);


		// Command optional values
		if (isSet($this->_dataObject->data->dns_template) && $this->_dataObject->data->dns_template != "") $cmd['attributes']['dns_template'] = $this->_dataObject->data->dns_template;

		// records - A
		$a_ip_addresses = "";
		$a_subdomains = "";
		if (isSet($this->_dataObject->data->a_ip_addresses) && $this->_dataObject->data->a_ip_addresses != "") $a_ip_addresses = $this->_dataObject->data->a_ip_addresses;
		if (isSet($this->_dataObject->data->a_subdomains) && $this->_dataObject->data->a_subdomains != "") $a_subdomains = $this->_dataObject->data->a_subdomains;
		if ($a_ip_addresses != ""){
                        $a_ip_addresses_array = explode(",", $a_ip_addresses);
                        $a_subdomains_array = explode(",", $a_subdomains);

                        $i = 0;
                        if(count($a_ip_addresses_array) == count($a_subdomains_array)){
                            foreach($a_ip_addresses_array as $a_ip_address){
                                $cmd['attributes']['records']['A'][$i] = array(
                                        'ip_address' => $a_ip_address,
                                        'subdomain' => $a_subdomains_array[$i]
                                );
                                $i++;
                            }
                        }
                }

		// records - AAAA
		$aaaa_ipv6_address = "";
		$aaaa_subdomain = "";
		if (isSet($this->_dataObject->data->aaaa_ipv6_address) && $this->_dataObject->data->aaaa_ipv6_address != "") $aaaa_ipv6_address = $this->_dataObject->data->aaaa_ipv6_address;
		if (isSet($this->_dataObject->data->aaaa_subdomain) && $this->_dataObject->data->aaaa_subdomain != "") $aaaa_subdomain = $this->_dataObject->data->aaaa_subdomain;
		if ($aaaa_ipv6_address != "" && $aaaa_subdomain != ""){
			$cmd['attributes']['records']['AAAA'][0] = array(
				'ipv6_address' => $aaaa_ipv6_address,
				'subdomain' =>$aaaa_subdomain
			);
		}

		// records - CNAME
		$cname_hostnames = "";
		$cname_subdomains = "";
		if (isSet($this->_dataObject->data->cname_hostnames) && $this->_dataObject->data->cname_hostnames != "") $cname_hostnames = $this->_dataObject->data->cname_hostnames;
		if (isSet($this->_dataObject->data->cname_subdomains) && $this->_dataObject->data->cname_subdomains != "") $cname_subdomains = $this->_dataObject->data->cname_subdomains;
		if ($cname_hostnames != ""){
                        $cname_hostnames_array = explode(",", $cname_hostnames);
                        $cname_subdomains_array = explode(",", $cname_subdomains);

                        if(count($cname_hostnames_array) == count($cname_subdomains_array)){
                            $i = 0;

                            foreach($cname_hostnames_array as $cname_hostname){
                                $cmd['attributes']['records']['CNAME'][$i] = array(
                                        'hostname' => $cname_hostname,
                                        'subdomain' => $cname_subdomains_array[$i]
                                );

                                $i++;
                            }
                        }
		}

		// records - MX
		$mx_priorities = "";
		$mx_subdomains = "";
		$mx_hostnames = "";
		if (isSet($this->_dataObject->data->mx_priorities) && $this->_dataObject->data->mx_priorities != "") $mx_priorities = $this->_dataObject->data->mx_priorities;
		if (isSet($this->_dataObject->data->mx_subdomains) && $this->_dataObject->data->mx_subdomains != "") $mx_subdomains = $this->_dataObject->data->mx_subdomains;
		if (isSet($this->_dataObject->data->mx_hostnames) && $this->_dataObject->data->mx_hostnames != "") $mx_hostnames = $this->_dataObject->data->mx_hostnames;
		if ($mx_priorities != "" && $mx_hostnames != ""){
			$mx_hostnames_array = explode(",", $mx_hostnames);
                        $mx_subdomains_array = explode(",", $mx_subdomains);
                        $mx_priorities_array = explode(",", $mx_priorities);

                        if( count($mx_hostnames_array) == count($mx_subdomains_array) &&
                            count($mx_hostnames_array) == count($mx_priorities_array) ){

                            $i = 0;

                            foreach($mx_hostnames_array as $mx_hostname){
                                $cmd['attributes']['records']['MX'][$i] = array(
                                        'hostname' => $mx_hostname,
                                        'subdomain' => $mx_subdomains_array[$i],
                                        'priority' => $mx_priorities_array[$i]
                                );

                                $i++;
                            }
                        }
		}

		// records - SRV
		$srv_priority = "";
		$srv_weight = "";
		$srv_subdomain = "";
		$srv_hostname = "";
		$srv_port = "";
		if (isSet($this->_dataObject->data->srv_priority) && $this->_dataObject->data->srv_priority != "") $srv_priority = $this->_dataObject->data->srv_priority;
		if (isSet($this->_dataObject->data->srv_weight) && $this->_dataObject->data->srv_weight != "") $srv_weight = $this->_dataObject->data->srv_weight;
		if (isSet($this->_dataObject->data->srv_subdomain) && $this->_dataObject->data->srv_subdomain != "") $srv_subdomain = $this->_dataObject->data->srv_subdomain;
		if (isSet($this->_dataObject->data->srv_hostname) && $this->_dataObject->data->srv_hostname != "") $srv_hostname = $this->_dataObject->data->srv_hostname;
		if (isSet($this->_dataObject->data->srv_port) && $this->_dataObject->data->srv_port != "") $srv_port = $this->_dataObject->data->srv_port;
		if ($srv_priority != "" && $srv_weight != "" && $srv_subdomain != "" && $srv_hostname != "" && $srv_port != ""){
			$cmd['attributes']['records']['SRV'][0] = array(
				'priority' => $srv_priority,
				'weight' => $srv_weight,
				'subdomain' => $srv_subdomain,
				'hostname' => $srv_hostname,
				'port' => $srv_port
			);
		}

		// records - TXT
		$txt_subdomain = "";
		$txt_text = "";
		if (isSet($this->_dataObject->data->txt_subdomains) && $this->_dataObject->data->txt_subdomains != "") $txt_subdomains = $this->_dataObject->data->txt_subdomains;
		if (isSet($this->_dataObject->data->txt_texts) && $this->_dataObject->data->txt_texts != "") $txt_texts = $this->_dataObject->data->txt_texts;
		if ($txt_texts != ""){
			$txt_texts_array = explode(",", $txt_texts);
                        $txt_subdomains_array = explode(",", $txt_subdomains);

                        if(count($txt_texts_array) == count($txt_subdomains_array)){
                            $i = 0;

                            foreach($txt_texts_array as $txt_text){
                                $cmd['attributes']['records']['TXT'][$i] = array(
                                        'text' => $txt_text,
                                        'subdomain' => $txt_subdomains_array[$i]
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