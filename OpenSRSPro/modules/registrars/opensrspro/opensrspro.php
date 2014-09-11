<?php

/**********************************************************************
* OpenSRS - Domains Pro WHMCS module
* *
*
* CREATED BY Tucows Co -> http://www.opensrs.com
* CONTACT -> help@tucows.com
* Version -> 2.0.2
* Release Date -> 07/18/14
*
*
* Copyright (C) 2014 by Tucows Co/OpenSRS.
*
* Permission is hereby granted, free of charge, to any person obtaining a copy
* of this software and associated documentation files (the "Software"), to deal
* in the Software without restriction, including without limitation the rights
* to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
* copies of the Software, and to permit persons to whom the Software is
* furnished to do so, subject to the following conditions:
*
* The above copyright notice and this permission notice shall be included in
* all copies or substantial portions of the Software.
*
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
* THE SOFTWARE.
*
**********************************************************************/

if (!class_exists('openSRS_base')) {
    require_once dirname(__FILE__).DIRECTORY_SEPARATOR."opensrs".DIRECTORY_SEPARATOR."openSRS_loader.php";
}

// Set up the global error variables and the forwarding IP constants
global $osrsError;
global $osrsLogError;

define("TEST_FWD_IP", "216.40.33.60");
define("PROD_FWD_IP", "64.99.64.37");

// Tells WHMCS what parameters to include for OpenSRS configuration
function opensrspro_getConfigArray() {

    $configarray = array(
        "TestUsername"            => array("Type" => "text", "Size" => "20", "Description" => "Enter your test reseller username here",),
        "TestAPIKey"              => array("Type" => "password", "Size" => "32", "Description" => "Enter your test API Key here",),
        "ProdUsername"            => array("Type" => "text", "Size" => "20", "Description" => "Enter your production reseller username here",),
        "ProdAPIKey"              => array("Type" => "password", "Size" => "32", "Description" => "Enter your production API Key here",),
        "HashKey"                 => array("Type" => "password", "Size" => "32", "Description" => "Enter your Hash Key here",),
        "TestMode"                => array("Type" => "yesno",),
        "ForceForwardingIp"       => array("Type" => "yesno", "Description" => "If a customer adds a URL forward should a A record to the forwarding server be created?"),
        "ChangeLockedDomains"     => array("Type" => "yesno", "Description" => "If this option is selected, locked domains will be unlocked and the change will be made. Once the changes are complete, the domain will be locked again."),
        "LockTechContact"         => array("Type" => "yesno", "Description" => "Only use default technical contact provided in OpenSRS RWI."),
        //"CookieBypass"          => array( "Type" => "yesno", "Description" => "Reseller account is set up to operate without cookie authentication.",),
        "GeneralError"            => array("Type" => "text", "Size" => "50", "Description" => "A general error to be displayed to the end user.",),
        "DisableTemplatesChanges" => array("Type" => "yesno", "Description" => "Check to disable changes in client area templates"),
        /* Added by BC : NG : 9-7-2014 : To set role perimission for hide Registrant Verification Status */  
        "RestrictedAdminIds"      => array("Type" => "text", "Size" => "100", "Description" => "Admin ID who has allowed to see 'Registrant Verification Status'",),
        /* End : To set role perimission for hide Registrant Verification Status */
    );

    return $configarray;
}

function opensrspro_IDProtectToggle($params) {

    global $osrsLogError;
    global $osrsError;

    if ($params["protectenable"]) {
        $whois_state = "enable";
    } else {
        $whois_state = "disable";
    }

    $tld = $params["tld"];
    $sld = $params["sld"];

    $callArray = array(
        'func' => 'provModify',
        'data' => array(
            'domain_name'    => $sld . "." . $tld,
            'domain'         => $sld . "." . $tld,
            'affect_domains' => "0",
            'data'           => "whois_privacy_state",
            'state'          => $whois_state,
            'bypass'         => $sld . "." . $tld,
        ),
        'connect' => generateConnectData($params)
    );

    set_error_handler("osrsError", E_USER_WARNING);
    $openSRSHandler = processOpenSRS("array", $callArray);
    restore_error_handler();

    if (strcmp($openSRSHandler->resultFullRaw["is_success"], "1") != 0) {
        $osrsError .= $openSRSHandler->resultFullRaw["response_text"] . "<br />";
        $osrsLogError .= $openSRSHandler->resultFullRaw["response_text"] . "\n";
    }

    $values["error"] = filterForResellerError($osrsError, $params["GeneralError"]);
    opensrspro_logModuleCall(__FUNCTION__, $callArray, $openSRSHandler->resultFullRaw, $values, $params);

    return $values;
}

function opensrspro_GetNameservers($params) {

    global $osrsLogError;
    global $osrsError;


    // Pull OSRS parameter needed
    $hashKey = $params["HashKey"];
    $tld = $params["tld"];
    $sld = $params["sld"];

    // Check if Cookie bypass is on, if it is then no cookie will
    // be generated for this call
    /*
      if(strcmp($params['CookieBypass'],"on")==0)
      $cookieBypass = true;
      else
      $cookieBypass = false;
     */
    $cookieBypass = true;

    // Generate cookie using the standard username and password, if
    // cookie bypass is on, cookie is set to false.  Errors will be fed
    // into error variables from the getCookie function.
    if (!$cookieBypass) {
        $domainUser = getDomainUser($tld, $sld);
        $domainPass = getDomainPass($tld, $sld, $hashKey);
        $cookie = getCookie($sld . "." . $tld, $domainUser, $domainPass, $params);
    } else
        $cookie = false;
    
    // Checks to see if there was an error grabbing the cookie or if bypass
    // is on
    if ($cookie !== false || $cookieBypass) {

        $callArray = array(
            'func' => 'lookupGetDomain',
            'data' => array(
                'domain' => $sld . "." . $tld,
                'type' => "nameservers"
            ),
                'connect' => generateConnectData($params)
        );

        // Set cookie or bypass if that is enabled instead
        if ($cookieBypass)
            $callArray['data']['bypass'] = $sld . "." . $tld;
        else
            $callArray['data']['cookie'] = $cookie;

        // Set error handler to check for any OSRS toolkit errors,
        // make the call and then reset the error handler.  Error handler
        // will add to the error variables.

        set_error_handler("osrsError", E_USER_WARNING);

        $openSRSHandler = processOpenSRS("array", $callArray);

        restore_error_handler();
        // Check for errors from the API and add to the error variables
        if (strcmp($openSRSHandler->resultFullRaw["is_success"], "1") == 0) {

            foreach ($openSRSHandler->resultFullRaw["attributes"]["nameserver_list"] as $nameserver) {
                $sortOrder = $nameserver["sortorder"];
                $values["ns" . $sortOrder] = $nameserver["name"];
            }
        } else {
            $osrsError .= $openSRSHandler->resultFullRaw["response_text"] . "<br />";
            $osrsLogError .= $openSRSHandler->resultFullRaw["response_text"] . "\n";
        }
    }

    // Log and output any error messages.
    if (!empty($osrsLogError)) {
        if (empty($osrsError))
            $osrsError = $params["GeneralError"];
    }

    $values["error"] = filterForResellerError($osrsError, $params["GeneralError"]);

    opensrspro_logModuleCall(__FUNCTION__, $callArray, $openSRSHandler->resultFullRaw, $values, $params);

    return $values;
}

function opensrspro_SaveNameservers($params) {
    $lockstate = opensrspro_GetRegistrarLock($params);

    $tempunlock = "off";
    if (strcmp($lockstate, "locked") == 0 && strcmp($params["ChangeLockedDomains"], "on") == 0) {
        $tempunlock = "on";
        opensrspro_TempUnlock($params, $tempunlock);
    }


    global $osrsLogError;
    global $osrsError;

    $osrsLogError = "";
    $osrsError = "";

    $hashKey = $params["HashKey"];

    $tld = $params["tld"];
    $sld = $params["sld"];
    /*
      if(strcmp($params['CookieBypass'],"on")==0)
      $cookieBypass = true;
      else
      $cookieBypass = false;
     */
    $cookieBypass = true;

    if (!$cookieBypass) {
        $cookie = getCookie($sld . "." . $tld, $domainUser, $domainPass, $params);
        $domainUser = getDomainUser($tld, $sld);
        $domainPass = getDomainPass($tld, $sld, $hashKey);
    } else
        $cookie = false;

    $nameserver_list = "";

    // Pulls nameservers from the Params and puts them into csv format
    // for the call to OSRS.
    for ($i = 1; $i <= 5; $i++) {
        if (array_key_exists("ns" . $i, $params)) {
            if (strcmp($params["ns" . $i], "") != 0) {
                $nameserver_list .= $params["ns" . $i];
                $nameserver_list .= ",";
            }
        }
    }
    $nameserver_list = substr($nameserver_list, 0, -1);

    if ($cookie !== false || $cookieBypass) {

        $callArray = array(
            'func' => 'nsAdvancedUpdt',
            'data' => array(
                'op_type' => 'assign',
                'assign_ns' => $nameserver_list
            ),
            'connect' => generateConnectData($params)
        );

        if ($cookieBypass)
            $callArray['data']['bypass'] = $sld . "." . $tld;
        else
            $callArray['data']['cookie'] = $cookie;

        set_error_handler("osrsError", E_USER_WARNING);

        $openSRSHandler = processOpensrs("array", $callArray);

        restore_error_handler();

        if (strcmp($openSRSHandler->resultFullRaw["is_success"], "1") != 0) {
            $osrsError .= $openSRSHandler->resultFullRaw["response_text"] . "<br />";
            $osrsLogError .= $openSRSHandler->resultFullRaw["response_text"] . "\n";
        }
    }

    if (!empty($osrsLogError)) {
        if (empty($osrsError))
            $osrsError = $params["GeneralError"];
    }

    //Put the lock back on, if it was removed temporarily
    if (strcmp($tempunlock, "on") == 0) {
        $tempunlock = "off";
        opensrspro_TempUnlock($params, $tempunlock);
    }

    $values["error"] = filterForResellerError($osrsError, $params["GeneralError"]);

    opensrspro_logModuleCall(__FUNCTION__, $callArray, $openSRSHandler->resultFullRaw, $values, $params);

    return $values;
}

function opensrspro_GetRegistrarLock($params) {


    global $osrsLogError;
    global $osrsError;

    $osrsLogError = "";
    $osrsError = "";

    $hashKey = $params["HashKey"];
    $tld = $params["tld"];
    $sld = $params["sld"];
    /*
      if(strcmp($params['CookieBypass'],"on")==0)
      $cookieBypass = true;
      else
      $cookieBypass = false;
     */
    $cookieBypass = true;

    if (!$cookieBypass) {
        $cookie = getCookie($sld . "." . $tld, $domainUser, $domainPass, $params);
        $domainUser = getDomainUser($tld, $sld);
        $domainPass = getDomainPass($tld, $sld, $hashKey);
    } else
        $cookie = false;

    // Checks to see if there was an error grabbing the cookie
    if ($cookie !== false || $cookieBypass) {

        $callArray = array(
            'func' => 'lookupGetDomain',
            'data' => array(
                'domain' => $sld . "." . $tld,
                'domain_name' => $sld . "." . $tld,
                'type' => "status"
            ),
            'connect' => generateConnectData($params)
        );

        if ($cookieBypass)
            $callArray['data']['bypass'] = $sld . "." . $tld;
        else
            $callArray['data']['cookie'] = $cookie;

        set_error_handler("osrsError", E_USER_WARNING);

        $openSRSHandler = processOpenSRS("array", $callArray);

        restore_error_handler();

        if (strcmp($openSRSHandler->resultFullRaw["is_success"], "1") == 0) {
            $lock = $openSRSHandler->resultFullRaw["attributes"]["lock_state"];
        } else {
            $osrsLogError .= $openSRSHandler->resultFullRaw["response_text"];
        }
    }

    // Sets lock status for WHMCS to understand on return
    if (strcmp($lock, "1") == 0) {
        $lockstatus = "locked";
    } else {
        $lockstatus = "unlocked";
    }

    if (!empty($osrsLogError)) {
        if (empty($osrsError))
            $osrsError = $params["GeneralError"];
    }

    opensrspro_logModuleCall(__FUNCTION__, $callArray, $openSRSHandler->resultFullRaw, $lockstatus, $params);

    return $lockstatus;
}

function opensrspro_SaveRegistrarLock($params) {

    global $osrsLogError;
    global $osrsError;

    $osrsLogError = "";
    $osrsError = "";

    $hashKey = $params["HashKey"];
    $tld = $params["tld"];
    $sld = $params["sld"];

    /*
      if(strcmp($params['CookieBypass'],"on")==0)
      $cookieBypass = true;
      else
      $cookieBypass = false;
     */
    $cookieBypass = true;

    if (strcmp($params["lockenabled"], "locked") == 0) {
        $lockstatus = "1";
    } else {
        $lockstatus = "0";
    }

    if (!$cookieBypass) {
        $domainUser = getDomainUser($tld, $sld);
        $domainPass = getDomainPass($tld, $sld, $hashKey);
        $cookie = getCookie($sld . "." . $tld, $domainUser, $domainPass, $params);
    } else
        $cookie = false;

    // Checks to see if there was an error grabbing the cookie
    if ($cookie !== false || $cookieBypass) {

        $callArray = array(
            'func' => 'provModify',
            'data' => array(
                'domain_name' => $sld . "." . $tld,
                'domain' => $sld . "." . $tld,
                'affect_domains' => "0",
                'data' => "status",
                'lock_state' => $lockstatus
            ),
                'connect' => generateConnectData($params)
        );

        if ($cookieBypass)
            $callArray['data']['bypass'] = $sld . "." . $tld;
        else
            $callArray['data']['cookie'] = $cookie;

        set_error_handler("osrsError", E_USER_WARNING);

        $openSRSHandler = processOpenSRS("array", $callArray);

        restore_error_handler();

        if (strcmp($openSRSHandler->resultFullRaw["is_success"], "1") != 0) {
            $osrsError .= $openSRSHandler->resultFullRaw['response_text'] . "<br />";
            $osrsLogError .= $openSRSHandler->resultFullRaw['response_text'] . "\n";
        }
    }

    if (!empty($osrsLogError)) {
        if (empty($osrsError))
            $osrsError = $params["GeneralError"];
    }

    $values["error"] = filterForResellerError($osrsError, $params["GeneralError"]);
    
    opensrspro_logModuleCall(__FUNCTION__, $callArray, $openSRSHandler->resultFullRaw, $values, $params);

    return $values;
}

/* Not supported via OpenSRS XCP
  function template_GetEmailForwarding($params) {
  $username = $params["Username"];
  $password = $params["Password"];
  $testmode = $params["TestMode"];
  $tld = $params["tld"];
  $sld = $params["sld"];
  # Put your code to get email forwarding here - the result should be an array of prefixes and forward to emails (max 10)
  foreach ($result AS $value) {
  $values[$counter]["prefix"] = $value["prefix"];
  $values[$counter]["forwardto"] = $value["forwardto"];
  }
  return $values;
  }

  function template_SaveEmailForwarding($params) {
  $username = $params["Username"];
  $password = $params["Password"];
  $testmode = $params["TestMode"];
  $tld = $params["tld"];
  $sld = $params["sld"];
  foreach ($params["prefix"] AS $key=>$value) {
  $forwardarray[$key]["prefix"] =  $params["prefix"][$key];
  $forwardarray[$key]["forwardto"] =  $params["forwardto"][$key];
  }
  # Put your code to save email forwarders here
  }

 */

function opensrspro_GetDNS($params) {

    global $osrsLogError;
    global $osrsError;

    $osrsLogError = "";
    $osrsError = "";

    $hashKey = $params["HashKey"];
    $tld = $params["tld"];
    $sld = $params["sld"];
    $hostrecords = array();

    // Grab the DNS records first
    $callArray = array(
        'func' => 'dnsGet',
        'data' => array(
            'domain' => $sld . "." . $tld
        ),
        'connect' => generateConnectData($params)
    );

    set_error_handler("osrsError", E_USER_WARNING);

    $openSRSHandler = processOpenSRS("array", $callArray);

    restore_error_handler();
    
    if (strcmp($openSRSHandler->resultFullRaw["is_success"], "1") == 0) {

        // Pull records from OSRS return format and shape them into the format
        // WHMC sees.  WHMCS uses: hostname type address, for all types of
        // records.
        $aRecords = $openSRSHandler->resultFullRaw["attributes"]["records"]["A"];
        foreach ($aRecords as $aRecord) {
            $hostrecords[] = array(
                "hostname" => $aRecord["subdomain"],
                "type" => "A",
                "address" => $aRecord["ip_address"]
            );
        }

        $cnameRecords = $openSRSHandler->resultFullRaw["attributes"]["records"]["CNAME"];
        foreach ($cnameRecords as $cnameRecord) {
            $hostrecords[] = array(
                "hostname" => $cnameRecord["subdomain"],
                "type" => "CNAME",
                "address" => $cnameRecord["hostname"]
            );
        }

        $mxRecords = $openSRSHandler->resultFullRaw["attributes"]["records"]["MX"];
        /* Added by BC : NG : 10-9-2014 : To sort domain based on priority which is inputted manually */  
        usort($mxRecords,'sort_by_priority');
        /* END : To sort domain based on priority which is inputted manually */
        foreach ($mxRecords as $mxRecord) {
            $hostrecords[] = array(
                "hostname" => $mxRecord["subdomain"],
                "type" => "MX",
                "address" => $mxRecord["hostname"],
                "priority" => $mxRecord["priority"]
            );
        }


        $txtRecords = $openSRSHandler->resultFullRaw["attributes"]["records"]["TXT"];
        foreach ($txtRecords as $txtRecord) {
            $hostrecords[] = array(
                "hostname" => $txtRecord["subdomain"],
                "type" => "TXT",
                "address" => $txtRecord["text"]
            );
        }
    } else {
        $values["error"] .= $openSRSHandler->resultFullRaw["response_text"] . " ";
    }

    $cookieBypass = true;

    $domainUser = getDomainUser($tld, $sld);
    $domainPass = getDomainPass($tld, $sld, $hashKey);

    if (!$cookieBypass)
        $cookie = getCookie($sld . "." . $tld, $domainUser, $domainPass, $params);
    else
        $cookie = false;

    if ($cookie !== false || $cookieBypass) {

        // Grab the forwarding records next, grabbing fwd records needs
        // a cookie or bypass.
        $callArray = array(
            'func' => 'fwdGet',
            'data' => array(
                'domain' => $sld . "." . $tld,
            ),
            'connect' => generateConnectData($params)
        );

        if ($cookieBypass)
            $callArray['data']['bypass'] = $sld . "." . $tld;
        else
            $callArray['data']['cookie'] = $cookie;

        set_error_handler("osrsError", E_USER_WARNING);

        $openSRSHandler = processOpenSRS("array", $callArray);

        restore_error_handler();
        
        if (strcmp($openSRSHandler->resultFullRaw["is_success"], "1") == 0) {

            // Pull forwarding records in WHMCS format: hostname type address
            // like with DNS.
            $fwds = $openSRSHandler->resultFullRaw["attributes"]["forwarding"];
            
            /* Added by BC : NG : 9-8-2014 : To resolve error "Domain forwarding not found for domain" */  
            $_SESSION['doamin_fwd'] = $fwds;
            /* End : To resolve error "Domain forwarding not found for domain" */

            foreach ($fwds as $fwd) {
                if (strcmp($fwd["masked"], "1") == 0) {
                    $hostrecords[] = array(
                        "hostname" => $fwd["subdomain"],
                        "type" => "FRAME",
                        "address" => $fwd["destination_url"]
                    );
                } else {
                    $hostrecords[] = array(
                        "hostname" => $fwd["subdomain"],
                        "type" => "URL",
                        "address" => $fwd["destination_url"]
                    );
                }
            }
        } else {
            /* Added by BC : NG : 9-8-2014 : To resolve error "Domain forwarding not found for domain" */  
            $_SESSION['doamin_fwd'] = "";
            /* End : To resolve error "Domain forwarding not found for domain" */  
            $osrsError .= $openSRSHandler->resultFullRaw["response_text"] . "<br />";
            $osrsLogError .= $osrsError .= $openSRSHandler->resultFullRaw["response_text"] . "\n";
        }
    }

    opensrspro_logModuleCall(__FUNCTION__, $callArray, $openSRSHandler->resultFullRaw, $hostrecords, $params);

    return $hostrecords;
}

/* Added by BC : NG : 10-9-2014 : To sort domain based on priority which is inputted manually */  
function sort_by_priority($a, $b) {
  return $a["priority"] - $b["priority"];
}
/* END : To sort domain based on priority which is inputted manually */ 

function opensrspro_SaveDNS($params) {

    global $osrsLogError;
    global $osrsError;
    global $testFwdServerIP;
    global $prodFwdServerIP;

    $osrsLogError = "";
    $osrsError = "";

    $hashKey = $params["HashKey"];

    // Set up forwarding server IP in case
    // force forwarding IP is turned on
    if (strcmp($testMode, "on") == 0)
        $fwdServerIP = TEST_FWD_IP;
    else
        $fwdServerIP = PROD_FWD_IP;

    $forceForwardingIP = false;

    if (strcmp($params["ForceForwardingIp"], "on") == 0)
        $forceForwardingIP = true;

    $tld = $params["tld"];
    $sld = $params["sld"];

    // Set up record CSV lines
    $cname_hostnames = "";
    $cname_subdomains = "";
    $a_ip_addresses = "";
    $a_subdomains = "";
    $fwd_addresses = "";
    $fwd_subdomains = "";
    $fwd_maskeds = "";

    // Set up container arrays, numMX keeps a count on each MX record hostname
    // to set priority.  WHMCS doesn't allow for a priority field, so priority is
    // set by user order in the list for each hostname.

    $values = array();
    $numMX = array();
    $fwdSubDomains = array();
    $i = 1;

    // Push records in OSRS toolkit format.  OSRS toolkit takes csv strings
    // of the different parameters.
    foreach ($params["dnsrecords"] AS $dnsKey => $dnsValue) {

        // Resets the MX record counter if it's empty for this hostname
        if (strcmp($dnsValue["type"], "MX") == 0 && $numMX[$dnsValue["hostname"]] == null)
            $numMX[$dnsValue["hostname"]] = 0;

        // If the address is blank, the record will be omitted.  This allows
        // the end user to delete records by wiping the address or just
        // providing whitespace.
        $addressBlank = strcmp(trim($dnsValue["address"]), "");

        if ($addressBlank != 0) {

            // Keeps track of the forward records for forcing the forwarding IP.
            // if a forwarding ip A record for this subdomain already exists,
            // it will skip that forwarding record.
            if (strcmp($dnsValue["type"], "A") == 0 && strcmp($dnsValue["address"], $fwdServerIP) == 0) {
                $fwdSubDomains[$i] = $dnsValue["hostname"];
                $i++;
            }

            switch ($dnsValue["type"]) {
                case "CNAME":
                    $cname_hostnames .= $dnsValue["address"] . ",";
                    $cname_subdomains .= $dnsValue["hostname"] . ",";
                    break;

                case "A":
                    $a_ip_addresses .= $dnsValue["address"] . ",";
                    $a_subdomains .= $dnsValue["hostname"] . ",";
                    break;

                case "MX":
                case "MXE":
                    $numMX[$dnsValue["hostname"]]++;
                    $mx_hostnames .= $dnsValue["address"] . ",";
                    $mx_subdomains .= $dnsValue["hostname"] . ",";
                    /* Changed by BC : NG : 10-9-2014 : To set priority which is inputted manually */  
                    /*$mx_priorities .= 10 * $numMX[$dnsValue["hostname"]] . ",";*/
                    $mx_priorities .= $dnsValue['priority'] . ",";
                    /* END : To set priority which is inputted manually */
                    break;

                case "TXT":
                    $txt_texts .= $dnsValue["address"] . ",";
                    $txt_subdomains .= $dnsValue["hostname"] . ",";
                    break;

                case "URL":
                    $fwd_addresses .= $dnsValue["address"] . ",";
                    $fwd_subdomains .= $dnsValue["hostname"] . ",";
                    $fwd_maskeds .= "0,";

                    $searchResult = array_search($dnsValue["hostname"], $fwdSubDomains);

                    if ($searchResult == false && $forceForwardingIP == true) {
                        $a_ip_addresses .= $fwdServerIP . ",";
                        $a_subdomains .= $dnsValue["hostname"] . ",";
                    }

                    break;

                case "FRAME":
                    $fwd_addresses .= $dnsValue["address"] . ",";
                    $fwd_subdomains .= $dnsValue["hostname"] . ",";
                    $fwd_maskeds .= "1,";

                    $searchResult = array_search($dnsValue["hostname"], $fwdSubDomains);

                    if ($searchResult == false && $forceForwardingIP == true) {
                        $a_ip_addresses .= $fwdServerIP . ",";
                        $a_subdomains .= $dnsValue["hostname"] . ",";
                    }
                    break;
            }
        }
    }

    // Scrub the last character in the csv, so there is no trailing comma
    $cname_hostnames = substr($cname_hostnames, 0, -1);
    $cname_subdomains = substr($cname_subdomains, 0, -1);
    $a_ip_addresses = substr($a_ip_addresses, 0, -1);
    $a_subdomains = substr($a_subdomains, 0, -1);
    $mx_hostnames = substr($mx_hostnames, 0, -1);
    $mx_subdomains = substr($mx_subdomains, 0, -1);
    $mx_priorities = substr($mx_priorities, 0, -1);
    $txt_texts = substr($txt_texts, 0, -1);
    $txt_subdomains = substr($txt_subdomains, 0, -1);
    $fwd_addresses = substr($fwd_addresses, 0, -1);
    $fwd_subdomains = substr($fwd_subdomains, 0, -1);
    $fwd_maskeds = substr($fwd_maskeds, 0, -1);

    // Create the DNS service on this domain if it doesn't already exist
    $createArray = array(
        'func' => 'dnsCreate',
        'data' => array(
            'domain' => $sld . "." . $tld
        ),
        'connect' => generateConnectData($params)
    );
    // error handler will be set for all calls in this function
    set_error_handler("osrsError", E_USER_WARNING);

    $createHandler = processOpenSRS("array", $createArray);

    if (strcmp($createHandler->resultFullRaw["is_success"], "1") != 0 && strcmp($createHandler->resultFullRaw["response_code"], "485") != 0) {
        $osrsError .= $createHandler->resultFullRaw["response_text"] . "<br />";
        $osrsLogError .= $createHandler->resultFullRaw["response_text"] . "\n";
    }

    // Call the set function to set the records in OSRS
    $callArray = array(
        'func' => 'dnsSet',
        'data' => array(
            'domain' => $sld . "." . $tld,
            'a_ip_addresses' => $a_ip_addresses,
            'a_subdomains' => $a_subdomains,
            'cname_hostnames' => $cname_hostnames,
            'cname_subdomains' => $cname_subdomains,
            'mx_hostnames' => $mx_hostnames,
            'mx_subdomains' => $mx_subdomains,
            'mx_priorities' => $mx_priorities,
            'txt_texts' => $txt_texts,
            'txt_subdomains' => $txt_subdomains
        ),
        'connect' => generateConnectData($params)
    );

    $openSRSHandler = processOpenSRS("array", $callArray);
  
    if (strcmp($openSRSHandler->resultFullRaw["is_success"], "1") != 0) {
        $osrsError .= $openSRSHandler->resultFullRaw["response_text"] . "<br />";
        $osrsLogError .= $openSRSHandler->resultFullRaw["response_text"] . "\n";
    }
    restore_error_handler();

    /* Added if..else by BC : NG : 21-7-2014 : To delete domain forwarding */
    /*if (!empty($fwd_subdomains)) {
        // Create the forwarding service for this domain name.  A cookie is
        // required, no bypass, for this function.  Using getDomainCredentials
        // to grab the username and password if bypass is on.
        $createArray = array(
            'func' => 'fwdCreate',
            'data' => array(
                'domain' => $sld . "." . $tld
            ),
            'connect' => generateConnectData($params)
        );
        /*
          if(strcmp($params['CookieBypass'],"on")==0){
          $domainCredentials = getDomainCredentials($sld . "." . $tld, $params);
          $domainUser = $domainCredentials['username'];
          $domainPass = $domainCredentials['password'];
          } else {
          $domainUser = getDomainUser($tld, $sld);
          $domainPass = getDomainPass($tld, $sld, $hashKey);
          }
         * /
        $cookieBypass = true;

        // Generate cookie using the standard username and password, if
        // cookie bypass is on, cookie is set to false.  Errors will be fed
        // into error variables from the getCookie function.
        if (!$cookieBypass) {
            $domainUser = getDomainUser($tld, $sld);
            $domainPass = getDomainPass($tld, $sld, $hashKey);
            $cookie = getCookie($sld . "." . $tld, $domainUser, $domainPass, $params);
        }
        else
            $cookie = false;

        // Checks to see if there was an error grabbing the cookie or if bypass
        // is on
        if ($cookie !== false || $cookieBypass) {
            set_error_handler("osrsError", E_USER_WARNING);
            $createHandler = processOpenSRS("array", $createArray);
            if (strcmp($createHandler->resultFullRaw["is_success"], "1") != 0 && strcmp($createHandler->resultFullRaw["response_code"], "485") != 0) {
                $osrsError .= $createHandler->resultFullRaw["response_text"] . "<br />";
                $osrsLogError .= $createHandler->resultFullRaw["response_text"] . "\n";
            }

            // Once the create function is called the forwarding set function is
            // called to set the URL forwards.
            $callArray = array(
                'func' => 'fwdSet',
                'data' => array(
                    'domain' => $sld . "." . $tld,
                    'subdomain' => $fwd_subdomains,
                    'destination_urls' => $fwd_addresses,
                    'maskeds' => $fwd_maskeds,
                    'cookie' => $cookie,
                    'bypass' => $cookieBypass,
                ),
                'connect' => generateConnectData($params)
            );


            $openSRSHandler = processOpenSRS("array", $callArray);

            // restore the error handler once all of the OSRS calls have been made
            restore_error_handler();

            if (strcmp($openSRSHandler->resultFullRaw["is_success"], "1") != 0) {
                $osrsError .= $openSRSHandler->resultFullRaw["response_text"] . "<br />";
                $osrsLogError .= $openSRSHandler->resultFullRaw["response_text"] . "\n";
            }
        }
    }*/
    
    if (empty($fwd_subdomains)) {
        /* Changed by BC : NG : 9-8-2014 : To resolve error "Domain forwarding not found for domain" */
        /*set_error_handler("osrsError", E_USER_WARNING);
        $callArray = array(
                'func' => 'fwdDelete',
                'data' => array(
                    'domain' => $sld . "." . $tld,
                ),
                'connect' => generateConnectData($params)
            );
            $openSRSHandler = processOpenSRS("array", $callArray);
            restore_error_handler();

            if (strcmp($openSRSHandler->resultFullRaw["is_success"], "1") != 0) {
                $osrsError .= $openSRSHandler->resultFullRaw["response_text"] . "<br />";
                $osrsLogError .= $openSRSHandler->resultFullRaw["response_text"] . "\n";
            }*/
          
        if(!empty($_SESSION['doamin_fwd'])){
            set_error_handler("osrsError", E_USER_WARNING);
            $callArray = array(
                    'func' => 'fwdDelete',
                    'data' => array(
                        'domain' => $sld . "." . $tld,
                    ),
                    'connect' => generateConnectData($params)
                );
                $openSRSHandler = processOpenSRS("array", $callArray);
                restore_error_handler();

                if (strcmp($openSRSHandler->resultFullRaw["is_success"], "1") != 0) {
                    $osrsError .= $openSRSHandler->resultFullRaw["response_text"] . "<br />";
                    $osrsLogError .= $openSRSHandler->resultFullRaw["response_text"] . "\n";
                }
        }
        /* End : To resolve error "Domain forwarding not found for domain" */  
    }
    else{
        // Create the forwarding service for this domain name.  A cookie is
        // required, no bypass, for this function.  Using getDomainCredentials
        // to grab the username and password if bypass is on.
        $createArray = array(
            'func' => 'fwdCreate',
            'data' => array(
                'domain' => $sld . "." . $tld
            ),
            'connect' => generateConnectData($params)
        );
        /*
          if(strcmp($params['CookieBypass'],"on")==0){
          $domainCredentials = getDomainCredentials($sld . "." . $tld, $params);
          $domainUser = $domainCredentials['username'];
          $domainPass = $domainCredentials['password'];
          } else {
          $domainUser = getDomainUser($tld, $sld);
          $domainPass = getDomainPass($tld, $sld, $hashKey);
          }
         */
        $cookieBypass = true;

        // Generate cookie using the standard username and password, if
        // cookie bypass is on, cookie is set to false.  Errors will be fed
        // into error variables from the getCookie function.
        if (!$cookieBypass) {
            $domainUser = getDomainUser($tld, $sld);
            $domainPass = getDomainPass($tld, $sld, $hashKey);
            $cookie = getCookie($sld . "." . $tld, $domainUser, $domainPass, $params);
        }
        else
            $cookie = false;

        // Checks to see if there was an error grabbing the cookie or if bypass
        // is on
        if ($cookie !== false || $cookieBypass) {
            set_error_handler("osrsError", E_USER_WARNING);
            $createHandler = processOpenSRS("array", $createArray);
            if (strcmp($createHandler->resultFullRaw["is_success"], "1") != 0 && strcmp($createHandler->resultFullRaw["response_code"], "485") != 0) {
                $osrsError .= $createHandler->resultFullRaw["response_text"] . "<br />";
                $osrsLogError .= $createHandler->resultFullRaw["response_text"] . "\n";
            }

            // Once the create function is called the forwarding set function is
            // called to set the URL forwards.
            $callArray = array(
                'func' => 'fwdSet',
                'data' => array(
                    'domain' => $sld . "." . $tld,
                    'subdomain' => $fwd_subdomains,
                    'destination_urls' => $fwd_addresses,
                    'maskeds' => $fwd_maskeds,
                    'cookie' => $cookie,
                    'bypass' => $cookieBypass,
                ),
                'connect' => generateConnectData($params)
            );


            $openSRSHandler = processOpenSRS("array", $callArray);

            // restore the error handler once all of the OSRS calls have been made
            restore_error_handler();

            if (strcmp($openSRSHandler->resultFullRaw["is_success"], "1") != 0) {
                $osrsError .= $openSRSHandler->resultFullRaw["response_text"] . "<br />";
                $osrsLogError .= $openSRSHandler->resultFullRaw["response_text"] . "\n";
            }
        }
    }
    /* End : To delete domain forwarding */

    if (!empty($osrsLogError)) {
        if (empty($osrsError))
            $osrsError = $params["GeneralError"];
    }

    $values["error"] = filterForResellerError($osrsError, $params["GeneralError"]);

    opensrspro_logModuleCall(__FUNCTION__, $callArray, $openSRSHandler->resultFullRaw, $values, $params);

    return $values;
}

function opensrspro_RegisterDomain($params) {
    global $osrsLogError;
    global $osrsError;

    $osrsLogError = "";
    $osrsError = "";

    $hashKey = $params["HashKey"];
    $lockTech = $params["LockTechContact"];

    $sld = $params["sld"];
    $tld = $params["tld"];

    $regperiod = $params["regperiod"];
    $privacy = $params ['idprotection'];

    $domainUser = getDomainUser($tld, $sld);
    $domainPass = getDomainPass($tld, $sld, $hashKey);

    $callArray = array(
        'func' => 'provSWregister',
        'data' => array(
            'domain' => $sld . "." . $tld,
            'custom_nameservers' => "1",
            'f_lock_domain' => "1",
            'reg_username' => $domainUser,
            'reg_password' => $domainPass,
            'period' => $regperiod,
            'reg_type' => "new",
            'handle' => "process"
        ),
        'connect' => generateConnectData($params)
    );

    if (strcmp($lockTech, "on") == 0)
        $callArray["data"]["custom_tech_contact"] = "0";
    else
        $callArray["data"]["custom_tech_contact"] = "1";

    if ($privacy)
        $callArray["data"]["f_whois_privacy"] = "1";
    else
        $callArray["data"]["f_whois_privacy"] = "0";


    // Pulls contact details from the params.  The phone number has to be set
    // to 1.xxxxx on the form and we just add a + at the begining to put
    // it into good format for OSRS.

    $contactType = 'personal';
    $contactSet = $params;
    $contactValueType = "";

    $callArray[$contactType]["first_name"] = $contactSet[$contactValueType . "firstname"];
    $callArray[$contactType]["last_name"] = $contactSet[$contactValueType . "lastname"];

    //check if org_name is blank
    if (empty($contactSet[$contactValueType . "companyname"]))
        $callArray[$contactType]["org_name"] = $contactSet[$contactValueType . "firstname"] . " " . $contactSet[$contactValueType . "lastname"];
    else
        $callArray[$contactType]["org_name"] = $contactSet[$contactValueType . "companyname"];

    $callArray[$contactType]["address1"] = $contactSet[$contactValueType . "address1"];
    $callArray[$contactType]["address2"] = $contactSet[$contactValueType . "address2"];
    $callArray[$contactType]["address3"] = $contactSet[$contactValueType . "address3"];
    $callArray[$contactType]["city"] = $contactSet[$contactValueType . "city"];
    $callArray[$contactType]["state"] = $contactSet[$contactValueType . "state"];
    $callArray[$contactType]["postal_code"] = $contactSet[$contactValueType . "postcode"];
    $callArray[$contactType]["country"] = $contactSet[$contactValueType . "country"];
    $callArray[$contactType]["email"] = $contactSet[$contactValueType . "email"];
    $callArray[$contactType]["phone"] = $contactSet[$contactValueType . "fullphonenumber"];
    $callArray[$contactType]["fax"] = $contactSet[$contactValueType . "fax"];
    $callArray[$contactType]["lang_pref"] = "EN";


    // Tech and Billing contacts are copies of the Admin, no seperate contacts
    // for WHMCS just yet.
    //Adding Nameservers, putting them into CSV strings for the call
    for ($i = 1; $i <= 5; $i++) {
        if (strcmp($params["ns" . $i], "") != 0) {
            $callArray['data']['name' . $i] = $params["ns" . $i];
            $callArray['data']['sortorder' . $i] = $i;
        }
    }

    set_error_handler("osrsError", E_USER_WARNING);

    // Function that grabs CCTLD fields and checks on things like
    // province format.  Returns any errors as E_USER_WARNING like the
    // toolkit does.
    $callArray = addCCTLDFields($params, $callArray);
    $openSRSHandler = processOpenSRS("array", $callArray);

    restore_error_handler();

    // Checks for errors from OSRS call, specifcally errors for the Telephone
    // number.
    if (strcmp($openSRSHandler->resultFullRaw["is_success"], "1") != 0) {

        if (strcmp($openSRSHandler->resultFullRaw["response_code"], "465") == 0) {

            if (strpos($openSRSHandler->resultFullRaw["attributes"]["error"], "phone") != false)
                str_replace("+", "", $openSRSHandler->resultFullRaw["attributes"]["error"]);
            $osrsError .= $openSRSHandler->resultFullRaw["attributes"]["error"] . "<br />";
            $osrsLogError .= $openSRSHandler->resultFullRaw["attributes"]["error"] . "\n";
        } else {
            $osrsError .= $openSRSHandler->resultFullRaw["response_text"] . "<br />";
            $osrsLogError .= $openSRSHandler->resultFullRaw["response_text"] . "\n";
        }
    }

    if (!empty($osrsLogError)) {
        if (empty($osrsError))
            $osrsError = $params["GeneralError"];
    }

    $values["error"] = filterForResellerError($osrsError, $params["GeneralError"]);

    opensrspro_logModuleCall(__FUNCTION__, $callArray, $openSRSHandler->resultFullRaw, $values, $params);

    return $values;
}

// Transfer call is pretty much the same as the registration call except

function opensrspro_TransferDomain($params) {

    global $osrsLogError;
    global $osrsError;

    $osrsLogError = "";
    $osrsError = "";

    $hashKey = $params["HashKey"];
    $lockTech = $params["LockTechContact"];

    $sld = $params["sld"];
    $tld = $params["tld"];

    $regperiod = $params["regperiod"];
    $transfersecret = $params["transfersecret"];
    $privacy = $params ['idprotection'];

    $domainUser = getDomainUser($tld, $sld);
    $domainPass = getDomainPass($tld, $sld, $hashKey);

    $callArray = array(
        'func' => 'provSWregister',
        'data' => array(
            'domain' => $sld . "." . $tld,
            'f_lock_domain' => "1",
            'custom_nameservers' => "1",
            'tns' => $nameservers,
            'reg_username' => $domainUser,
            'reg_password' => $domainPass,
            'period' => $regperiod,
            'handle' => "process",
            'reg_type' => "transfer"
        ),
        'connect' => generateConnectData($params)
    );

    if (strcmp($lockTech, "on") == 0)
        $callArray["data"]["custom_tech_contact"] = "0";
    else
        $callArray["data"]["custom_tech_contact"] = "1";

    if ($privacy)
        $callArray["data"]["f_whois_privacy"] = "1";
    else
        $callArray["data"]["f_whois_privacy"] = "0";

    //$contactTypes = array("owner", "admin", "tech", "billing");

    $contactType = 'personal';

    $contactSet = $params;
    $contactValueType = "";

//        if (strcmp($contactType, "owner") == 0)
//            $contactValueType = "";
//        else
//            $contactValueType = $contactType;

    $callArray[$contactType]["first_name"] = $contactSet[$contactValueType . "firstname"];
    $callArray[$contactType]["last_name"] = $contactSet[$contactValueType . "lastname"];

    //check if org_name is blank
    if (empty($contactSet[$contactValueType . "companyname"]))
        $callArray[$contactType]["org_name"] = $contactSet[$contactValueType . "firstname"] . " " . $contactSet[$contactValueType . "lastname"];
    else
        $callArray[$contactType]["org_name"] = $contactSet[$contactValueType . "companyname"];

    $callArray[$contactType]["address1"] = $contactSet[$contactValueType . "address1"];
    $callArray[$contactType]["address2"] = $contactSet[$contactValueType . "address2"];
    $callArray[$contactType]["address3"] = $contactSet[$contactValueType . "address3"];
    $callArray[$contactType]["city"] = $contactSet[$contactValueType . "city"];
    $callArray[$contactType]["state"] = $contactSet[$contactValueType . "state"];
    $callArray[$contactType]["postal_code"] = $contactSet[$contactValueType . "postcode"];
    $callArray[$contactType]["country"] = $contactSet[$contactValueType . "country"];
    $callArray[$contactType]["email"] = $contactSet[$contactValueType . "email"];
    $callArray[$contactType]["phone"] = $contactSet[$contactValueType . "fullphonenumber"];
    $callArray[$contactType]["fax"] = $contactSet[$contactValueType . "fax"];
    $callArray[$contactType]["lang_pref"] = "EN";


    //$callArray["data"]["auth_info"] = $transfersecret;
    // Tech and Billing contacts are copies of the Admin, no seperate contacts
    // for WHMCS just yet.
    $callArray["tech"] = $callArray["admin"];
    $callArray["billing"] = $callArray["admin"];

    //Adding Nameservers
    for ($i = 1; $i <= 5; $i++) {
        if (strcmp($params["ns" . $i], "") != 0) {
            $callArray['data']['name' . $i] = $params["ns" . $i];
            $callArray['data']['sortorder' . $i] = $i;
        }
    }

    set_error_handler("osrsError", E_USER_WARNING);

    $callArray = addCCTLDFields($params, $callArray);

    $openSRSHandler = processOpenSRS("array", $callArray);

    restore_error_handler();

    if (strcmp($openSRSHandler->resultFullRaw["is_success"], "1") != 0) {

        if (strcmp($openSRSHandler->resultFullRaw["response_code"], "465") == 0) {

            if (strpos($openSRSHandler->resultFullRaw["attributes"]["error"], "phone") != false)
                str_replace("+", "", $openSRSHandler->resultFullRaw["attributes"]["error"]);

            $osrsError .= $openSRSHandler->resultFullRaw["attributes"]["error"] . "<br />";
            $osrsLogError .= $openSRSHandler->resultFullRaw["attributes"]["error"] . "\n";
        } else {
            $osrsError .= $openSRSHandler->resultFullRaw["response_text"] . "<br />";
            $osrsLogError .= $openSRSHandler->resultFullRaw["response_text"] . "\n";
        }
    }

    if (!empty($osrsLogError)) {
        if (empty($osrsError))
            $osrsError = $params["GeneralError"];
    }

    $values["error"] = filterForResellerError($osrsError, $params["GeneralError"]);

    opensrspro_logModuleCall(__FUNCTION__, $callArray, $openSRSHandler->resultFullRaw, $values, $params);

    return $values;
}

function opensrspro_RenewDomain($params) {

    global $osrsLogError;
    global $osrsError;

    $tld = $params["tld"];
    $sld = $params["sld"];
    $regperiod = $params["regperiod"];

    // OSRS requires the expiration year with a renewal, this function
    // will grab that expration year for this domain.
    $expirationYear = getExpirationYear($sld . "." . $tld, $params);

    $currentYear = intval(date("Y"));

    // Check to make sure the renewal isn't going over the 10 year max
    // from this year.  If it is, then push an error out.
    if (intval($expirationYear) > $currentYear)
        $regMax = intval(date("Y")) + 10;
    else
        $regMax = intval($expirationYear) + 10;

    $renewedUntil = intval($expirationYear) + intval($regperiod);

    if ($regMax < $renewedUntil) {
        $osrsError .= "Domain can only be renewed to a maximum of 10 years. <br />";
        $osrsLogError .= "Renewal attempt for " . $sld . "." . $tld . " failed, renewal exceeded 10 year limit. \n";
    } else {

        $callArray = array(
            'func' => 'provRenew',
            'data' => array(
                'domain' => $sld . "." . $tld,
                'handle' => "process",
                'period' => $regperiod,
                'auto_renew' => "0",
                'currentexpirationyear' => $expirationYear
            ),
            'connect' => generateConnectData($params)
        );

        set_error_handler("osrsError", E_USER_WARNING);
        $openSRSHandler = processOpenSRS("array", $callArray);
        restore_error_handler();

        if (strcmp($openSRSHandler->resultFullRaw["is_success"], "1") != 0) {
            $osrsError .= $openSRSHandler->resultFullRaw["response_text"] . "<br />";
            $osrsLogError .= $openSRSHandler->resultFullRaw["response_text"] . "\n";
        }
    }

    if (!empty($osrsLogError)) {
        if (empty($osrsError))
            $osrsError = $params["GeneralError"];
    }

    $values["error"] = filterForResellerError($osrsError, $params["GeneralError"]);

    opensrspro_logModuleCall(__FUNCTION__, $callArray, $openSRSHandler->resultFullRaw, $values, $params);

    return $values;
}

function opensrspro_GetContactDetails($params) {
    global $osrsLogError;
    global $osrsError;

    $osrsLogError = "";
    $osrsError = "";

    $hashKey = $params["HashKey"];
    $tld = $params["tld"];
    $sld = $params["sld"];
    $lockTech = $params["LockTechContact"];
    /*
      if(strcmp($params['CookieBypass'],"on")==0)
      $cookieBypass = true;
      else
      $cookieBypass = false;
     */
    $cookieBypass = true;

    // If the technical contact is locked, it can only be set in the RWI as
    // the default.  That contact will be ignored in WHMCS
    if (strcmp($lockTech, "on") == 0)
        $contactTypes = array("owner", "admin", "billing");
    else
        $contactTypes = array("owner", "admin", "tech", "billing");

    if (!$cookieBypass) {
        $domainUser = getDomainUser($tld, $sld);
        $domainPass = getDomainPass($tld, $sld, $hashKey);
        $cookie = getCookie($sld . "." . $tld, $domainUser, $domainPass, $params);
    } else
        $cookie = false;

    // Checks to see if there was an error grabbing the cookie
    if ($cookie !== false || $cookieBypass) {

        $callArray = array(
            'func' => 'lookupGetDomain',
            'data' => array(
                'domain' => $sld . "." . $tld,
                'type' => "all_info"
            ),
            'connect' => generateConnectData($params)
        );

        if ($cookieBypass)
            $callArray['data']['bypass'] = $sld . "." . $tld;
        else
            $callArray['data']['cookie'] = $cookie;

        set_error_handler("osrsError", E_USER_WARNING);

        $openSRSHandler = processOpenSRS("array", $callArray);

        restore_error_handler();

        if (strcmp($openSRSHandler->resultFullRaw["is_success"], "1") == 0) {

            // Pulls the contact set from OSRS format to WHMCS format.  This
            // owner is changed to Registrant and the first letter of each
            // contact type is capitalized.  Labels are then changed for each
            // field.

            $contactSet = $openSRSHandler->resultFullRaw["attributes"]["contact_set"];

            // edited by Maks Aloksa
            $contactType = "owner";
            $contactValueType = "Registrant";

//                if (strcmp($contactType, "owner") == 0)
//                    $contactValueType = "Registrant";
//                //$contactValueType = "Owner";
//                else
//                    $contactValueType = ucfirst($contactType);

            $values[$contactValueType]["First Name"] = $contactSet[$contactType]["first_name"];
            $values[$contactValueType]["Last Name"] = $contactSet[$contactType]["last_name"];
            $values[$contactValueType]["Organization Name"] = $contactSet[$contactType]["org_name"];
            $values[$contactValueType]["Address 1"] = $contactSet[$contactType]["address1"];
            $values[$contactValueType]["Address 2"] = $contactSet[$contactType]["address2"];
            $values[$contactValueType]["Address3"] = $contactSet[$contactType]["address3"];
            $values[$contactValueType]["City"] = $contactSet[$contactType]["city"];
            $values[$contactValueType]["State"] = $contactSet[$contactType]["state"];
            $values[$contactValueType]["Postal Code"] = $contactSet[$contactType]["postal_code"];
            $values[$contactValueType]["Country"] = $contactSet[$contactType]["country"];
            $values[$contactValueType]["Email"] = $contactSet[$contactType]["email"];
            $values[$contactValueType]["Phone"] = $contactSet[$contactType]["phone"];
            $values[$contactValueType]["Fax"] = $contactSet[$contactType]["fax"];
            
            /* Added by BC : NG : 23-7-2014 : To resolve issue of display all contact details  */ 
            
            $contactType = "billing";
            $contactValueType = "Billing";

//                if (strcmp($contactType, "owner") == 0)
//                    $contactValueType = "Registrant";
//                //$contactValueType = "Owner";
//                else
//                    $contactValueType = ucfirst($contactType);

            $values[$contactValueType]["First Name"] = $contactSet[$contactType]["first_name"];
            $values[$contactValueType]["Last Name"] = $contactSet[$contactType]["last_name"];
            $values[$contactValueType]["Organization Name"] = $contactSet[$contactType]["org_name"];
            $values[$contactValueType]["Address 1"] = $contactSet[$contactType]["address1"];
            $values[$contactValueType]["Address 2"] = $contactSet[$contactType]["address2"];
            $values[$contactValueType]["Address3"] = $contactSet[$contactType]["address3"];
            $values[$contactValueType]["City"] = $contactSet[$contactType]["city"];
            $values[$contactValueType]["State"] = $contactSet[$contactType]["state"];
            $values[$contactValueType]["Postal Code"] = $contactSet[$contactType]["postal_code"];
            $values[$contactValueType]["Country"] = $contactSet[$contactType]["country"];
            $values[$contactValueType]["Email"] = $contactSet[$contactType]["email"];
            $values[$contactValueType]["Phone"] = $contactSet[$contactType]["phone"];
            $values[$contactValueType]["Fax"] = $contactSet[$contactType]["fax"];
            
            $contactType = "admin";
            $contactValueType = "Admin";

//                if (strcmp($contactType, "owner") == 0)
//                    $contactValueType = "Registrant";
//                //$contactValueType = "Owner";
//                else
//                    $contactValueType = ucfirst($contactType);

            $values[$contactValueType]["First Name"] = $contactSet[$contactType]["first_name"];
            $values[$contactValueType]["Last Name"] = $contactSet[$contactType]["last_name"];
            $values[$contactValueType]["Organization Name"] = $contactSet[$contactType]["org_name"];
            $values[$contactValueType]["Address 1"] = $contactSet[$contactType]["address1"];
            $values[$contactValueType]["Address 2"] = $contactSet[$contactType]["address2"];
            $values[$contactValueType]["Address3"] = $contactSet[$contactType]["address3"];
            $values[$contactValueType]["City"] = $contactSet[$contactType]["city"];
            $values[$contactValueType]["State"] = $contactSet[$contactType]["state"];
            $values[$contactValueType]["Postal Code"] = $contactSet[$contactType]["postal_code"];
            $values[$contactValueType]["Country"] = $contactSet[$contactType]["country"];
            $values[$contactValueType]["Email"] = $contactSet[$contactType]["email"];
            $values[$contactValueType]["Phone"] = $contactSet[$contactType]["phone"];
            $values[$contactValueType]["Fax"] = $contactSet[$contactType]["fax"];
            
            
            if (strcmp($lockTech, "on") != 0){
                $contactType = "tech";
                $contactValueType = "Tech";

    //                if (strcmp($contactType, "owner") == 0)
    //                    $contactValueType = "Registrant";
    //                //$contactValueType = "Owner";
    //                else
    //                    $contactValueType = ucfirst($contactType);

                $values[$contactValueType]["First Name"] = $contactSet[$contactType]["first_name"];
                $values[$contactValueType]["Last Name"] = $contactSet[$contactType]["last_name"];
                $values[$contactValueType]["Organization Name"] = $contactSet[$contactType]["org_name"];
                $values[$contactValueType]["Address 1"] = $contactSet[$contactType]["address1"];
                $values[$contactValueType]["Address 2"] = $contactSet[$contactType]["address2"];
                $values[$contactValueType]["Address3"] = $contactSet[$contactType]["address3"];
                $values[$contactValueType]["City"] = $contactSet[$contactType]["city"];
                $values[$contactValueType]["State"] = $contactSet[$contactType]["state"];
                $values[$contactValueType]["Postal Code"] = $contactSet[$contactType]["postal_code"];
                $values[$contactValueType]["Country"] = $contactSet[$contactType]["country"];
                $values[$contactValueType]["Email"] = $contactSet[$contactType]["email"];
                $values[$contactValueType]["Phone"] = $contactSet[$contactType]["phone"];
                $values[$contactValueType]["Fax"] = $contactSet[$contactType]["fax"];
            }
            
            /* End : To resolve issue of display all contact details  */ 
            
        } else {
            $osrsError .= $openSRSHandler->resultFullRaw["response_text"] . "<br />";
            $osrsLogError .= $openSRSHandler->resultFullRaw["response_text"] . "\n";
        }
    }

    if (!empty($osrsLogError)) {
        if (empty($osrsError))
            $osrsError = $params["GeneralError"];
    }


    //$values["error"] = filterForResellerError($osrsError, $params["GeneralError"]);

    opensrspro_logModuleCall(__FUNCTION__, $callArray, $openSRSHandler->resultFullRaw, $values, $params);

    return $values;
}

function opensrspro_SaveContactDetails($params) {
    //print "savecontactmethodcalled";	
    $lockstate = opensrspro_GetRegistrarLock($params);

    $tempunlock = "off";
    if (strcmp($lockstate, "locked") == 0 && strcmp($params["ChangeLockedDomains"], "on") == 0) {
        $tempunlock = "on";
        opensrspro_TempUnlock($params, $tempunlock);
    }

    global $osrsLogError;
    global $osrsError;

    $osrsLogError = "";
    $osrsError = "";

    $hashKey = $params["HashKey"];
    $lockTech = $params["LockTechContact"];
    $tld = $params["tld"];
    $sld = $params["sld"];

    // Sets the values variable to an empty array since we will be doing pushes
    // into this array later.
    $values = array();
    /*
      if(strcmp($params['CookieBypass'],"on")==0)
      $cookieBypass = true;
      else
      $cookieBypass = false;
     */
    $cookieBypass = true;

    // If tech contact is locked, it can only be set to the RWI default and
    // is ignored in WHMCS.
    if (strcmp($lockTech, "on") == 0)
        $contactTypes = array("owner", "admin", "billing");
    else
        $contactTypes = array("owner", "admin", "tech", "billing");

    $contactTypesStr = implode(",", $contactTypes);

    if (!$cookieBypass) {
        $domainUser = getDomainUser($tld, $sld);
        $domainPass = getDomainPass($tld, $sld, $hashKey);
        $cookie = getCookie($sld . "." . $tld, $domainUser, $domainPass, $params);
    } else
        $cookie = false;


    // Checks to see if there was an error grabbing the cookie
    if ($cookie !== false || $cookieBypass) {

        $callArray = array(
            'func' => 'provUpdateContacts',
            'data' => array(
                'domain' => $sld . "." . $tld,
                'types' => $contactTypesStr
            ),
            'connect' => generateConnectData($params)
        );

        if ($cookieBypass)
            $callArray['data']['bypass'] = $sld . "." . $tld;
        else
            $callArray['data']['cookie'] = $cookie;

        // Pulls the contact set from WHMCS format to OSRS format.  This
        // Registrant is changed to owner and the first letter of each
        // contact type is set to lower case.  Labels are then changed for each
        // field.
        $contactType = 'personal';

        // edited by Maks Aloksa
        $contactSet = $params["contactdetails"];
        $contactValueType = "Registrant";
//            $contactValueType = "";
//
//            if (strcmp($contactType, "owner") == 0)
//                $contactValueType = "Registrant";
//            else
//                $contactValueType = ucfirst($contactType);

        $callArray[$contactType]["first_name"] = $contactSet[$contactValueType]["First Name"];
        $callArray[$contactType]["last_name"] = $contactSet[$contactValueType]["Last Name"];
        $callArray[$contactType]["org_name"] = $contactSet[$contactValueType]["Organization Name"] ? $contactSet[$contactValueType]["Organization Name"] : $contactSet[$contactValueType]["Organisation Name"];
        $callArray[$contactType]["address1"] = $contactSet[$contactValueType]["Address 1"];
        $callArray[$contactType]["address2"] = $contactSet[$contactValueType]["Address 2"];
        $callArray[$contactType]["address3"] = $contactSet[$contactValueType]["Address3"];
        $callArray[$contactType]["city"] = $contactSet[$contactValueType]["City"];
        $callArray[$contactType]["state"] = $contactSet[$contactValueType]["State"];
        $callArray[$contactType]["postal_code"] = $contactSet[$contactValueType]["Postal Code"] ? $contactSet[$contactValueType]["Postal Code"] : $contactSet[$contactValueType]["Postcode"];
        $callArray[$contactType]["country"] = $contactSet[$contactValueType]["Country"];
        $callArray[$contactType]["email"] = $contactSet[$contactValueType]["Email"];
        $callArray[$contactType]["phone"] = $contactSet[$contactValueType]["Phone"];
        $callArray[$contactType]["fax"] = $contactSet[$contactValueType]["Fax"];
        $callArray[$contactType]["lang_pref"] = "EN";
        
        /* Added by BC : NG : 23-7-2014 : To resolve issue of save/update all contact details  */ 
        
        $contactType = 'billing';

        // edited by Maks Aloksa
        $contactSet = $params["contactdetails"];
        $contactValueType = "Billing";
//            $contactValueType = "";
//
//            if (strcmp($contactType, "owner") == 0)
//                $contactValueType = "Registrant";
//            else
//                $contactValueType = ucfirst($contactType);

        $callArray[$contactType]["first_name"] = $contactSet[$contactValueType]["First Name"];
        $callArray[$contactType]["last_name"] = $contactSet[$contactValueType]["Last Name"];
        $callArray[$contactType]["org_name"] = $contactSet[$contactValueType]["Organization Name"] ? $contactSet[$contactValueType]["Organization Name"] : $contactSet[$contactValueType]["Organisation Name"];
        $callArray[$contactType]["address1"] = $contactSet[$contactValueType]["Address 1"];
        $callArray[$contactType]["address2"] = $contactSet[$contactValueType]["Address 2"];
        $callArray[$contactType]["address3"] = $contactSet[$contactValueType]["Address3"];
        $callArray[$contactType]["city"] = $contactSet[$contactValueType]["City"];
        $callArray[$contactType]["state"] = $contactSet[$contactValueType]["State"];
        $callArray[$contactType]["postal_code"] = $contactSet[$contactValueType]["Postal Code"] ? $contactSet[$contactValueType]["Postal Code"] : $contactSet[$contactValueType]["Postcode"];
        $callArray[$contactType]["country"] = $contactSet[$contactValueType]["Country"];
        $callArray[$contactType]["email"] = $contactSet[$contactValueType]["Email"];
        $callArray[$contactType]["phone"] = $contactSet[$contactValueType]["Phone"];
        $callArray[$contactType]["fax"] = $contactSet[$contactValueType]["Fax"];
        $callArray[$contactType]["lang_pref"] = "EN";
        
        
        $contactType = 'admin';

        // edited by Maks Aloksa
        $contactSet = $params["contactdetails"];
        $contactValueType = "Admin";
//            $contactValueType = "";
//
//            if (strcmp($contactType, "owner") == 0)
//                $contactValueType = "Registrant";
//            else
//                $contactValueType = ucfirst($contactType);

        $callArray[$contactType]["first_name"] = $contactSet[$contactValueType]["First Name"];
        $callArray[$contactType]["last_name"] = $contactSet[$contactValueType]["Last Name"];
        $callArray[$contactType]["org_name"] = $contactSet[$contactValueType]["Organization Name"] ? $contactSet[$contactValueType]["Organization Name"] : $contactSet[$contactValueType]["Organisation Name"];
        $callArray[$contactType]["address1"] = $contactSet[$contactValueType]["Address 1"];
        $callArray[$contactType]["address2"] = $contactSet[$contactValueType]["Address 2"];
        $callArray[$contactType]["address3"] = $contactSet[$contactValueType]["Address3"];
        $callArray[$contactType]["city"] = $contactSet[$contactValueType]["City"];
        $callArray[$contactType]["state"] = $contactSet[$contactValueType]["State"];
        $callArray[$contactType]["postal_code"] = $contactSet[$contactValueType]["Postal Code"] ? $contactSet[$contactValueType]["Postal Code"] : $contactSet[$contactValueType]["Postcode"];
        $callArray[$contactType]["country"] = $contactSet[$contactValueType]["Country"];
        $callArray[$contactType]["email"] = $contactSet[$contactValueType]["Email"];
        $callArray[$contactType]["phone"] = $contactSet[$contactValueType]["Phone"];
        $callArray[$contactType]["fax"] = $contactSet[$contactValueType]["Fax"];
        $callArray[$contactType]["lang_pref"] = "EN";
        
        
        if (strcmp($lockTech, "on") != 0){
        $contactType = 'tech';

        // edited by Maks Aloksa
        $contactSet = $params["contactdetails"];
        $contactValueType = "Tech";
//            $contactValueType = "";
//
//            if (strcmp($contactType, "owner") == 0)
//                $contactValueType = "Registrant";
//            else
//                $contactValueType = ucfirst($contactType);

        $callArray[$contactType]["first_name"] = $contactSet[$contactValueType]["First Name"];
        $callArray[$contactType]["last_name"] = $contactSet[$contactValueType]["Last Name"];
        $callArray[$contactType]["org_name"] = $contactSet[$contactValueType]["Organization Name"] ? $contactSet[$contactValueType]["Organization Name"] : $contactSet[$contactValueType]["Organisation Name"];
        $callArray[$contactType]["address1"] = $contactSet[$contactValueType]["Address 1"];
        $callArray[$contactType]["address2"] = $contactSet[$contactValueType]["Address 2"];
        $callArray[$contactType]["address3"] = $contactSet[$contactValueType]["Address3"];
        $callArray[$contactType]["city"] = $contactSet[$contactValueType]["City"];
        $callArray[$contactType]["state"] = $contactSet[$contactValueType]["State"];
        $callArray[$contactType]["postal_code"] = $contactSet[$contactValueType]["Postal Code"] ? $contactSet[$contactValueType]["Postal Code"] : $contactSet[$contactValueType]["Postcode"];
        $callArray[$contactType]["country"] = $contactSet[$contactValueType]["Country"];
        $callArray[$contactType]["email"] = $contactSet[$contactValueType]["Email"];
        $callArray[$contactType]["phone"] = $contactSet[$contactValueType]["Phone"];
        $callArray[$contactType]["fax"] = $contactSet[$contactValueType]["Fax"];
        $callArray[$contactType]["lang_pref"] = "EN";
        
        }
        
        /* End : To resolve issue of save/update all contact details */
        
        //die('<pre>'.  print_r($callArray, true).'</pre>');
        set_error_handler("osrsError", E_USER_WARNING);

        $openSRSHandler = processOpenSRS("array", $callArray);

        restore_error_handler();

        if (strcmp($openSRSHandler->resultFullRaw["is_success"], "1") != 0) {
            $osrsError .= $openSRSHandler->resultFullRaw["response_text"] . "<br />";
            $osrsLogError .= $openSRSHandler->resultFullRaw["response_text"] . "\n";
        }
    }

    if (!empty($osrsLogError)) {
        if (empty($osrsError))
            $osrsError = $params["GeneralError"];
    }

    //Put the lock back on, if it was removed temporarily
    if (strcmp($tempunlock, "on") == 0) {
        $tempunlock = "off";
        opensrspro_TempUnlock($params, $tempunlock);
    }

    $values["error"] = filterForResellerError($osrsError, $params["GeneralError"]);

    opensrspro_logModuleCall(__FUNCTION__, $callArray, $openSRSHandler->resultFullRaw, $values, $params);

    return $values;
}

function opensrspro_GetEPPCode($params) {

    global $osrsLogError;
    global $osrsError;

    $osrsLogError = "";
    $osrsError = "";

    $hashKey = $params["HashKey"];
    $tld = $params["tld"];
    $sld = $params["sld"];

    // Generates cookie even if bypass is turned on.  EPP code requires a cookie
    // either way.  Will call getDomainCredentials to get the username and password
    // from OSRS to generate the cookie.
    /*
      if(strcmp($params['CookieBypass'],"on")==0){
      $domainCredentials = getDomainCredentials($sld . "." . $tld, $params);
      $domainUser = $domainCredentials['username'];
      $domainPass = $domainCredentials['password'];
      } else {
      $domainUser = getDomainUser($tld, $sld);
      $domainPass = getDomainPass($tld, $sld, $hashKey);
      }
     */
    $cookieBypass = true;


    if (!$cookieBypass) {
        $domainUser = getDomainUser($tld, $sld);
        $domainPass = getDomainPass($tld, $sld, $hashKey);
        $cookie = getCookie($sld . "." . $tld, $domainUser, $domainPass, $params);
    } else
        $cookie = false;

    // Checks to see if there was an error grabbing the cookie
    if ($cookie !== false || $cookieBypass) {

        $callArray = array(
            'func' => 'lookupGetDomain',
            'data' => array(
                'domain' => $sld . "." . $tld,
                'cookie' => $cookie,
                'type' => "domain_auth_info"
            ),
            'connect' => generateConnectData($params)
        );

        set_error_handler("osrsError", E_USER_WARNING);

        $openSRSHandler = processOpenSRS("array", $callArray);

        restore_error_handler();

        if (strcmp($openSRSHandler->resultFullRaw["is_success"], "1") == 0) {
            $eppcode = $openSRSHandler->resultFullRaw["attributes"]["domain_auth_info"];
        } else {

            $osrsError .= $openSRSHandler->resultFullRaw["response_text"] . "<br />";
            $osrsLogError .= $openSRSHandler->resultFullRaw["response_text"] . "\n";
        }
    }

    $values["eppcode"] = $eppcode;

    if (!empty($osrsLogError)) {
        if (empty($osrsError))
            $osrsError = $params["GeneralError"];
    }

    $values["error"] = filterForResellerError($osrsError, $params["GeneralError"]);

    opensrspro_logModuleCall(__FUNCTION__, $callArray, $openSRSHandler->resultFullRaw, $values, $params);

    return $values;
}

function opensrspro_RegisterNameserver($params) {

    global $osrsLogError;
    global $osrsError;

    $osrsLogError = "";
    $osrsError = "";

    $hashKey = $params["HashKey"];
    $values = array();

    $tld = $params["tld"];
    $sld = $params["sld"];

    /*
      if(strcmp($params['CookieBypass'],"on")==0)
      $cookieBypass = true;
      else
      $cookieBypass = false;
     */
    $cookieBypass = true;


    if (!$cookieBypass) {
        $domainUser = getDomainUser($tld, $sld);
        $domainPass = getDomainPass($tld, $sld, $hashKey);
        $cookie = getCookie($sld . "." . $tld, $domainUser, $domainPass, $params);
    } else
        $cookie = false;

    // Checks to see if there was an error grabbing the cookie
    if ($cookie !== false || $cookieBypass) {

        $callArray = array(
            'func' => 'nsCreate',
            'data' => array(
                'name' => $params["nameserver"],
                'ipaddress' => $params["ipaddress"],
                'add_to_all_registry' => "1"
            ),
            'connect' => generateConnectData($params)
        );

        if ($cookieBypass)
            $callArray['data']['bypass'] = $sld . "." . $tld;
        else
            $callArray['data']['cookie'] = $cookie;

        set_error_handler();

        $openSRSHandler = processOpenSRS("array", $callArray);

        restore_error_handler();

        if (strcmp($openSRSHandler->resultFullRaw["is_success"], "1") != 0) {
            $osrsError .= $openSRSHandler->resultFullRaw["response_text"] . "<br />";
            $osrsLogError .= $openSRSHandler->resultFullRaw["response_text"] . "\n";
        }
    }

    if (!empty($osrsLogError)) {
        if (empty($osrsError))
            $osrsError = $params["GeneralError"];
    }

    $values["error"] = filterForResellerError($osrsError, $params["GeneralError"]);

    opensrspro_logModuleCall(__FUNCTION__, $callArray, $openSRSHandler->resultFullRaw, $values, $params);

    return $values;
}

function opensrspro_ModifyNameserver($params) {
    //require_once("./opensrs/openSRS_loader.php");

    global $osrsLogError;
    global $osrsError;

    $osrsLogError = "";
    $osrsError = "";

    $username = $params["Username"];
    $key = $params["APIKey"];
    $hashKey = $params["HashKey"];
    $testMode = $params["TestMode"];
    $values = array();

    $tld = $params["tld"];
    $sld = $params["sld"];
    $domainUser = getDomainUser($tld, $sld);
    $domainPass = getDomainPass($tld, $sld, $hashKey);

    /*
      if(strcmp($params['CookieBypass'],"on")==0)
      $cookieBypass = true;
      else
      $cookieBypass = false;
     */
    $cookieBypass = true;

    if (!$cookieBypass)
        $cookie = getCookie($sld . "." . $tld, $domainUser, $domainPass, $params);
    else
        $cookie = false;

    // Checks to see if there was an error grabbing the cookie
    if ($cookie !== false || $cookieBypass) {

        $callArray = array(
            'func' => 'nsModify',
            'data' => array(
                'name' => $params["nameserver"],
                'ipaddress' => $params["newipaddress"]
            ),
            'connect' => generateConnectData($params)
        );

        if ($cookieBypass)
            $callArray['data']['bypass'] = $sld . "." . $tld;
        else
            $callArray['data']['cookie'] = $cookie;

        set_error_handler("osrsError", E_USER_WARNING);

        $openSRSHandler = processOpenSRS("array", $callArray);

        restore_error_handler();

        if (strcmp($openSRSHandler->resultFullRaw["is_success"], "1") != 0) {
            $osrsError .= $openSRSHandler->resultFullRaw["response_text"] . "<br />";
            $osrsLogError .= $openSRSHandler->resultFullRaw["response_text"] . "\n";
        }
    }

    if (!empty($osrsLogError)) {
        if (empty($osrsError))
            $osrsError = $params["GeneralError"];
    }

    $values["error"] = filterForResellerError($osrsError, $params["GeneralError"]);

    opensrspro_logModuleCall(__FUNCTION__, $callArray, $openSRSHandler->resultFullRaw, $values, $params);

    return $values;
}

function opensrspro_DeleteNameserver($params) {
    //require_once("./opensrs/openSRS_loader.php");

    global $osrsLogError;
    global $osrsError;

    $osrsLogError = "";
    $osrsError = "";

    $username = $params["Username"];
    $key = $params["APIKey"];
    $hashKey = $params["HashKey"];
    $testMode = $params["TestMode"];
    $values = array();

    $tld = $params["tld"];
    $sld = $params["sld"];

    $domainUser = getDomainUser($tld, $sld);
    $domainPass = getDomainPass($tld, $sld, $hashKey);

    /*
      if(strcmp($params['CookieBypass'],"on")==0)
      $cookieBypass = true;
      else
      $cookieBypass = false;
     */
    $cookieBypass = true;

    if (!$cookieBypass)
        $cookie = getCookie($sld . "." . $tld, $domainUser, $domainPass, $params);
    else
        $cookie = false;

    // Checks to see if there was an error grabbing the cookie
    if ($cookie !== false || $cookieBypass) {

        $callArray = array(
            'func' => 'nsDelete',
            'data' => array(
                'name' => $params["nameserver"],
            ),
            'connect' => generateConnectData($params)
        );

        if ($cookieBypass)
            $callArray['data']['bypass'] = $sld . "." . $tld;
        else
            $callArray['data']['cookie'] = $cookie;

        set_error_handler("osrsError", E_USER_WARNING);

        $openSRSHandler = processOpenSRS("array", $callArray);

        restore_error_handler();

        if (strcmp($openSRSHandler->resultFullRaw["is_success"], "1") != 0) {
            $osrsError .= $openSRSHandler->resultFullRaw["response_text"] . "<br />";
            $osrsLogError .= $openSRSHandler->resultFullRaw["response_text"] . "\n";
        }
    }

    if (!empty($osrsLogError)) {
        if (empty($osrsError))
            $osrsError = $params["GeneralError"];
    }

    $values["error"] = filterForResellerError($osrsError, $params["GeneralError"]);

    opensrspro_logModuleCall(__FUNCTION__, $callArray, $openSRSHandler->resultFullRaw, $values, $params);

    return $values;
}

function opensrspro_Sync($params) {

    // create sync table if not exists
    mysql_query("
        CREATE TABLE IF NOT EXISTS `opensrspro_sync` (
            `domain` varchar(255) NOT NULL UNIQUE,
            `last_check` varchar(40),
            `data` text,
            PRIMARY KEY (`domain`)
        ) DEFAULT CHARACTER SET utf8 ENGINE = MYISAM");

    // insert missing domains to the table
    mysql_query("
        INSERT INTO opensrspro_sync (domain)
        SELECT domain
        FROM tbldomains
        WHERE registrar='opensrspro' AND domain NOT IN ( SELECT domain FROM opensrspro_sync)
        ");

    $outdated = false;
    $result = mysql_query("SELECT * FROM `opensrspro_sync` WHERE domain='{$params['domain']}'");
    $row = mysql_fetch_assoc($result);
    $last_check = strtotime($row['last_check']);
    if (!$last_check || time() - $last_check > (23 * 60 * 60)) {
        $outdated = true;
    } else {
        $data = unserialize($row['data']);

        $sync = true;
        $syncdata = $data;
    }
    
    /* Added by BC : RA : 5-7-2014 : To set request string at logModuleCall */
    $callArray = array('Domain' => $params['domain'], 'Last Check' => $last_check, 'Previous Data' => $syncdata);
    /* End : To set request string at logModuleCall */

    if ($outdated) {
        // save all lookup data to database
        if ($transferedaway = opensrspro_getTransfersAway($params)) {
            foreach ($transferedaway as $domain => $data) {
                mysql_query("UPDATE opensrspro_sync SET last_check=NOW(),data='" . serialize($data) . "' WHERE domain='{$domain}'");
            }
        }
        if ($domains = opensrspro_getDomainsByExpiry($params)) {
            foreach ($domains as $domain => $data) {
                mysql_query("UPDATE opensrspro_sync SET last_check=NOW(),data='" . serialize($data) . "' WHERE domain='{$domain}'");
            }
        }

        $sync = true;
        $syncdata = isset($transferedaway[$params['domain']]) ? $transferedaway[$params['domain']] : $domains[$params['domain']];
    }

    if ($sync) {

        // Set domain as cancelled if transfered away
        if (isset($syncdata['status']) && $syncdata['status'] == 'completed') {
            mysql_query("UPDATE tbldomains SET status='Cancelled' WHERE domain='{$params['domain']}'");
            return true;
        }
        $values = array();
        if (strtotime($syncdata['expiredate']) < time()) {
            $values['expired'] = true;
        } else {
            $values['active'] = true;
        }
        $values['expirydate'] = date("Y-m-d", strtotime($syncdata['expiredate']));
        
        /* Added by BC : NG : 27-6-2014 : For add log in opensrspro_Sync function */
        opensrspro_logModuleCall(__FUNCTION__, $callArray, $syncdata, $values, "");
        /* End : For add log in opensrspro_Sync function */

        return $values;
    }
    /* Added by BC : RA : 5-7-2014 : To set request string at logModuleCall */ 
    $values = array('error' => 'Sync not run');
    opensrspro_logModuleCall(__FUNCTION__, $callArray, $syncdata, $values, "");
    /* End : To set request string at logModuleCall */

// previous version - single sync
    /*
      global $osrsLogError;
      global $osrsError;

      $osrsLogError = "";
      $osrsError = "";
      $expirationDate = false;

      $hashKey = $params["HashKey"];

      $tld = $params["tld"];
      $sld = $params["sld"];
      $regperiod = $params["regperiod"];

      $domainUser = getDomainUser($tld, $sld);
      $domainPass = getDomainPass($tld, $sld, $hashKey);

      # used to return the details of the sync
      $values = array();

      if(strcmp($params['CookieBypass'],"on")==0)
      $cookieBypass = true;
      else
      $cookieBypass = false;

      if(!$cookieBypass)
      $cookie = getCookie($sld . "." . $tld, $domainUser, $domainPass, $params);
      else
      $cookie = false;

      if($cookie !== false || $cookieBypass){

      $expirationCall = array(
      'func' => 'lookupGetDomain',
      'data' => array(
      'domain' => $domain,
      'type' => "all_info"
      ),
      'connect' =>generateConnectData($params)
      );

      if($cookieBypass)
      $expirationCall['data']['bypass'] = $domain;
      else
      $expirationCall['data']['cookie'] = $cookie;

      set_error_handler("osrsError", E_USER_WARNING);

      $expiryReturn = processOpenSRS("array", $expirationCall);

      restore_error_handler();

      if(strcmp($expiryReturn->resultFullRaw["is_success"], "1") == 0){

      $expirationTime = $expiryReturn->resultFullRaw["attributes"]["expiredate"];
      $expirationDateArray = explode(" ", $expirationTime);
      $expirationDate = $expirationDateArray[0];
      $values['expirydate'] = '2013-10-28'; # populate with the domains expiry date if available
      $todaysDate = date("Y-m-d");
      if(strtotime($expirationDate) > strtotime($todaysDate)){
      $values['active'] = true; # set to true if the domain is active
      }
      else {
      $values['expired'] = true; # or set to true if the domain has expired
      }

      } else {
      #$values['expired'] = true; # set to true because domain has been deleted or not found in account
      $osrsLogError .= $expiryReturn->resultFullRaw["response_text"] . "\n";
      }
      }

      return $values;
     */
}

// Supporting functions, not called by WHMCS itself

function opensrspro_getTransfersAway($params, $from = false, $to = false) {

    global $osrsLogError;
    $osrsLogError = "";

    $page = 1;
    $domains = array();

    $call = array(
        'func' => 'transGetAway',
        'data' => array(
            'limit' => '40',
        ),
        'connect' => generateConnectData($params)
    );

    if ($from)
        $call['data']['from'] = $from;
    if ($to)
        $call['data']['to'] = $to;

    do {
        $call['data']['page'] = $page;

        set_error_handler("osrsError", E_USER_WARNING);
        $return = processOpenSRS("array", $call);
        restore_error_handler();



        if (strcmp($return->resultFullRaw["is_success"], "1") != 0) {
            $osrsLogError .= $return->resultFullRaw["response_text"] . "\n";
            opensrspro_logModuleCall(__FUNCTION__, $call, $return->resultFullRaw, false, $params);
            return false;
        } else {
            if (isset($return->resultFullRaw["attributes"]['transfers']))
                $domains = array_merge($return->resultFullRaw["attributes"]['transfers'], $domains);
            $page++;
        }
    }while ($return->resultFullRaw["attributes"]['total'] > count($domains));

    $result = array();
    foreach ($domains as $dom) {
        $result[$dom['domain']] = $dom;
    }

    opensrspro_logModuleCall(__FUNCTION__, $call, $return->resultFullRaw, $result, $params);

    return $result;
}

function opensrspro_getTransfersIn($params, $from = false, $to = false) {

    global $osrsLogError;
    $osrsLogError = "";

    $page = 1;
    $domains = array();

    $call = array(
        'func' => 'transGetIn',
        'data' => array(
            'limit' => '40',
        ),
        'connect' => generateConnectData($params)
    );

    if ($from)
        $call['data']['from'] = $from;
    if ($to)
        $call['data']['to'] = $to;

    do {
        $call['data']['page'] = $page;

        set_error_handler("osrsError", E_USER_WARNING);
        $return = processOpenSRS("array", $call);
        restore_error_handler();

        if (strcmp($return->resultFullRaw["is_success"], "1") != 0) {
            $osrsLogError .= $return->resultFullRaw["response_text"] . "\n";
            opensrspro_logModuleCall(__FUNCTION__, $call, $return->resultFullRaw, false, $params);
            return false;
        } else {
            if (isset($return->resultFullRaw["attributes"]['transfers']))
                $domains = array_merge($return->resultFullRaw["attributes"]['transfers'], $domains);
            $page++;
        }
    }while ($return->resultFullRaw["attributes"]['total'] > count($domains));

    $result = array();
    foreach ($domains as $dom) {
        $result[$dom['domain']] = $dom;
    }
    opensrspro_logModuleCall(__FUNCTION__, $call, $return->resultFullRaw, $result, $params);
    return $result;
}

function opensrspro_getDomainsByExpiry($params, $from = '1970-01-01', $to = '2038-01-01') {

    global $osrsLogError;
    $osrsLogError = "";

    $page = 1;
    $domains = array();
    do {
        $call = array(
            'func' => 'lookupGetDomainsByExpiry',
            'data' => array(
                'limit' => '40',
                'page' => $page,
                'exp_from' => $from,
                'exp_to' => $to,
            ),
            'connect' => generateConnectData($params)
        );

        set_error_handler("osrsError", E_USER_WARNING);
        $return = processOpenSRS("array", $call);
        restore_error_handler();

        if (strcmp($return->resultFullRaw["is_success"], "1") != 0) {
            $osrsLogError .= $return->resultFullRaw["response_text"] . "\n";
            opensrspro_logModuleCall(__FUNCTION__, $call, $return->resultFullRaw, false, $params);
            return false;
        } else {
            if (isset($return->resultFullRaw["attributes"]['exp_domains']))
                $domains = array_merge($return->resultFullRaw["attributes"]['exp_domains'], $domains);
            $page++;
        }
    }while ($return->resultFullRaw["attributes"]['remainder']);

    $result = array();
    foreach ($domains as $dom) {
        $result[$dom['name']] = $dom;
    }
    opensrspro_logModuleCall(__FUNCTION__, $call, $return->resultFullRaw, $result, $params);
    return $result;
}

// Generates the User name based on domain name
function getDomainUser($tld, $sld) {
    /* Added by BC : RA : 5-7-2014 : To set request string at logModuleCall */
    $callArray = array('tld' => $tld, 'sld' => $sld);
    /* End : To set request string at logModuleCall */
    $domainUser = $sld . $tld;
    $domainUser = str_replace("-", "", $domainUser);
    $domainUser = str_replace(".", "", $domainUser);

    if (strlen($domainUser) > 20) {
        $domainUser = substr($domainUser, 0, 19);
    }
    
    /* Added by BC : NG : 27-6-2014 : For add log in getDomainUser function */ 
    opensrspro_logModuleCall(__FUNCTION__, $callArray, $domainUser, $domainUser, "");
    /* End : For add log in getDomainUser function */

    return $domainUser;
}

// Generates a secure password beased on the domain name and a admin
// provided hash key.
function getDomainPass($tld, $sld, $hashKey) {
    /* Added by BC : RA : 5-7-2014 : To set request string at logModuleCall */
    $callArray = array('tld' => $tld, 'sld' => $sld, 'hashKey' => $hashKey);
    /* End : To set request string at logModuleCall */
    $domainPass = sha1(sha1($tld . $sld . $hashKey) . $hashKey);
    $domainPass = substr($domainPass, 0, 19);
    
     /* Added by BC : NG : 27-6-2014 : For add log in getDomainPass function */ 
    opensrspro_logModuleCall(__FUNCTION__, $callArray, $domainPass, $domainPass, "");
    /* End : For add log in getDomainPass function */

    return $domainPass;
}

// Grabs the cookie from OSRS, sends any errors back via the global error variables
function getCookie($domain, $domainUser, $domainPass, $params) {

    global $osrsLogError;
    global $osrsError;
    $cookie = false;


    $cookieCall = array(
        'func' => 'cookieSet',
        'data' => array(
            'domain' => $domain,
            'reg_username' => $domainUser,
            'reg_password' => $domainPass
        ),
        'connect' => generateConnectData($params)
    );

    set_error_handler("osrsError", E_USER_WARNING);

    $cookieReturn = processOpenSRS("array", $cookieCall);

    restore_error_handler();

    if (strcmp($cookieReturn->resultFullRaw["is_success"], "1") == 0) {
        $cookie = $cookieReturn->resultFullRaw["attributes"]["cookie"];
    } else {
        $osrsLogError = $cookieReturn->resultFullRaw["response_text"];
    }

    opensrspro_logModuleCall(__FUNCTION__, $cookieCall, $cookieReturn->resultFullRaw, $cookie, $params);

    return $cookie;
}

// Grabs the expiration year and sends back errors via the global error variables
function getExpirationYear($domain, $params) {

    global $osrsLogError;

    $expirationYear = false;

    $expirationCall = array(
        'func' => 'lookupGetDomain',
        'data' => array(
            'domain' => $domain,
            'type' => "all_info",
            'bypass' => $domain,
        ),
        'connect' => generateConnectData($params)
    );

    set_error_handler("osrsError", E_USER_WARNING);

    $expiryReturn = processOpenSRS("array", $expirationCall);

    restore_error_handler();

    if (strcmp($expiryReturn->resultFullRaw["is_success"], "1") == 0) {
        $expirationDate = $expiryReturn->resultFullRaw["attributes"]["registry_expiredate"] ? $expiryReturn->resultFullRaw["attributes"]["registry_expiredate"] : $expiryReturn->resultFullRaw["attributes"]["expiredate"];
        $expirationDateArray = explode("-", $expirationDate);
        $expirationYear = $expirationDateArray[0];
    } else {
        $osrsLogError .= $expiryReturn->resultFullRaw["response_text"] . "\n";
    }

    opensrspro_logModuleCall(__FUNCTION__, $expirationCall, $expiryReturn->resultFullRaw, $expirationYear, $params);

    return $expirationYear;
}

// Generates the connection data needed to send an OSRS call
function generateConnectData($params) {

    if (strcmp($params["TestMode"], "on") == 0) {
        $connectData["osrs_username"] = $params["TestUsername"];
        $connectData["osrs_password"] = "placeholder";
        $connectData["osrs_key"] = $params["TestAPIKey"];
        $connectData["osrs_environment"] = "TEST";
        $connectData["osrs_host"] = "horizon.opensrs.net";
        $connectData["osrs_port"] = "55000";
        $connectData["osrs_sslPort"] = "55443";
    } else {
        $connectData["osrs_username"] = $params["ProdUsername"];
        $connectData["osrs_password"] = "placeholder";
        $connectData["osrs_key"] = $params["ProdAPIKey"];
        $connectData["osrs_environment"] = "PROD";
        $connectData["osrs_host"] = "rr-n1-tor.opensrs.net";
        $connectData["osrs_port"] = "55000";
        $connectData["osrs_sslPort"] = "55443";
    }

    $connectData["osrs_protocol"] = "XCP";
    $connectData["osrs_baseClassVersion"] = "2.8.0";
    $connectData["osrs_version"] = "XML:0.1";
    
     /* Added by BC : NG : 27-6-2014 : For add log in generateConnectData function */  
    opensrspro_logModuleCall(__FUNCTION__, $params, $connectData, $connectData, $params);
    /* End : For add log in generateConnectData function */

    return $connectData;
}

// Takes any OSRS errors and
function osrsError($errno, $errstr, $errfile, $errline) {

    global $osrsError;
    global $osrsLogError;
    
    /* Added by BC : RA : 5-7-2014 : To set request string at logModuleCall */
    $callArray = array('Error No.' => $errno, 'Error String' => $errstr, 'Error File' => $errfile, 'Error Line' => $errline);
    /* End : To set request string at logModuleCall */


    // Error to be logged, includes file and error line.
    $osrsLogError .=$errstr . " " . " File: " . $errfile . " Line: " . $errline;

    // Error to be displayed to end user, only the error string itself.
    $osrsError.= $errstr . "<br />";
    
    /* Added by BC : RA : 5-7-2014 : To set request string at logModuleCall */ 
    $responseArray = array('osrsLogError' => $osrsLogError, 'osrsError' => $osrsError);
    opensrspro_logModuleCall(__FUNCTION__, $callArray, $responseArray, $responseArray, '');
    /* End : To set request string at logModuleCall */
}

// Checks call array for specific CCTLD requirements
function addCCTLDFields($params, $callArray) {
    
    /* Added by BC : RA : 5-7-2014 : To set request string at logModuleCall */
    $call = array('Params' => $params, 'Call Array' => $callArray);
    /* End : To set request string at logModuleCall */

    $tld = $params["tld"];

    // Puts eu and be language into call
    $lang = isset($params ['additionalfields'] ['Language']) ? $params ['additionalfields'] ['Language'] : 'en';

    if ($tld == 'eu' || $tld == 'be') {
        $callArray["owner"]["lang"] = $lang;
        $callArray["tech"]["lang"] = $lang;
        $callArray["admin"]["lang"] = $lang;
        $callArray["billing"]["lang"] = $lang;
        $callArray["data"]["lang"] = $lang;
        $callArray["data"]["country"] = $callArray["owner"]["country"];
    }

    //.PRO
    if ($tld == "pro") {
        $callArray["professional_data"]["profession"] = $params["additionalfields"]["Profession"];
    }

    //.AU
    if ($tld == "com.au" || $tld == "net.au" || $tld == "org.au" || $tld == "asn.au" || $tld == "id.au") {
        $callArray["data"]["registrant_name"] = $params['additionalfields']['Registrant Name'];
        $callArray["data"]["registrant_id_type"] = $params['additionalfields']['Registrant ID Type'];
        $callArray["data"]["registrant_id"] = $params['additionalfields']['Registrant ID'];
        $callArray["data"]["eligibility_type"] = $params['additionalfields']['Eligibility Type'];
        $callArray["data"]["eligibility_name"] = $params['additionalfields']['Eligibility Name'];
        $callArray["data"]["eligibility_id_type"] = $params['additionalfields']['Eligibility ID Type'];
        $callArray["data"]["eligibility_id"] = $params['additionalfields']['Eligibility ID'];
        $callArray["data"]["eligibility_reason"] = $params['additionalfields']['Eligibility Reason'];
        $callArray["data"]["owner_confirm_address"] = $callArray["owner"]["email"];
    }

    // Pushes in owner confirm address for eu, be and de transfers
    if ($tld == "eu" || $tld == "be" || $tld == "de" || $tld == "it") {
        $callArray["data"]["owner_confirm_address"] = $callArray["owner"]["email"];
    }

    // Pushes Nexus information into call

    if ($tld == 'us') {

        $callArray["nexus"]["category"] = $params['additionalfields']['Nexus Category'];

        $usDomainPurpose = trim($params['additionalfields']['Application Purpose']);

        if (strtolower($usDomainPurpose) == strtolower('Business use for profit')) {
            $callArray["nexus"]["app_purpose"] = 'P1';
        } else if (strtolower($usDomainPurpose) == strtolower('Educational purposes')) {
            $callArray["nexus"]["app_purpose"] = 'P4';
        } else if (strtolower($usDomainPurpose) == strtolower('Personal Use')) {
            $callArray["nexus"]["app_purpose"] = 'P3';
        } else if (strtolower($usDomainPurpose) == strtolower('Government purposes')) {
            $callArray["nexus"]["app_purpose"] = 'P5';
        } else {
            $callArray["nexus"]["app_purpose"] = 'P2';
        }

        $callArray["nexus"]["validator"] = $params['additionalfields']['Nexus Country'];
    }

    //.NAME
    if ($tld == 'name') {
        $callArray["data"]["forwarding_email"] = $params['additionalfields']['Forwarding Email'];
    }

    //.DE
    if ($tld == 'de') {
        $callArray["data"]["zone_fax"] = $params['additionalfields']['Zone Contact Fax'];
    }

    //.COM.BR
    if ($tld == 'com.br') {
        $callArray["data"]["br_register_number"] = $params['additionalfields']['CPF/CNPJ number'];
    }

    // .asia
    if ($tld == 'asia') {

        $callArray["cedinfo"]["legal_entity_type"] = $params ['additionalfields'] ['Legal Type'];
        $callArray["cedinfo"]["id_type"] = $params ['additionalfields'] ['Identity Form'];
        $callArray["cedinfo"]["id_number"] = $params ['additionalfields'] ['Identity Number'];
        $callArray["cedinfo"]["contact_type"] = "owner";
        /* Changed by BC : NG : 9-9-2014 : To resolve issue of tld .asia registration error  */
        
        /*$callArray["cedinfo"]["locality_country"] = $callArray["owner"]["country"];*/
        $callArray["cedinfo"]["locality_country"] = $callArray["personal"]["country"];
        
        /* END : To resolve issue of tld .asia registration error  */
        
        //        $callArray["cedinfo"]["legal_entity_type"] = $params ['additionalfields'] ['Legal Entity Type'];
        //        $callArray["cedinfo"]["legal_entity_type_info"] = $params ['additionalfields'] ['Other legal entity type'];
        //        $callArray["cedinfo"]["id_type"] = $params ['additionalfields'] ['Identification Form'];
        //        $callArray["cedinfo"]["id_type_info"] = $params ['additionalfields'] ['Other legal entity type'];
        //        $callArray["cedinfo"]["id_number"] = $params ['additionalfields'] ['Identification Number'];
        //        $callArray["cedinfo"]["contact_type"] = $params ['additionalfields'] ['Contact Type'];
        //        $callArray["cedinfo"]["locality_country"] = $callArray[$callArray["cedinfo"]["contact_type"]]["country"];
    }

    //.IT
    if ($tld == 'it') {
        switch ($params['additionalfields'] ['Legal Type']) {
            case "Italian and foreign natural persons":
                $callArray["data"]["entity_type"] = "1";
                break;
            case "Companies/one man companies":
                $callArray["data"]["entity_type"] = "2";
                break;
            case "Freelance workers/professionals":
                $callArray["data"]["entity_type"] = "3";
                break;
            case "non-profit organizations":
                $callArray["data"]["entity_type"] = "4";
                break;
            case "public organizations":
                $callArray["data"]["entity_type"] = "5";
                break;
            case "other subjects":
                $callArray["data"]["entity_type"] = "6";
                break;
            case "non natural foreigners":
                $callArray["data"]["entity_type"] = "7";
                break;
        }
        //$callArray["data"]["nationality_code"] = $params ['additionalfields']['Nationality Code'];
        $callArray["data"]["reg_code"] = $params ['additionalfields']['Tax ID'];
    }

    //.FR

    if ($tld == 'fr') {
        $callArray["data"]["registrant_type"] = $params['additionalfields']['Registrant Type'];

        switch ($params['additionalfields']['Registrant Type']) {
            case "Individual":
                $callArray["data"]["country_of_birth"] = $params['additionalfields']['Country of Birth'];
                $callArray["data"]["date_of_birth"] = $params['additionalfields']['Date of Birth'];
                $callArray["data"]["place_of_birth"] = $params['additionalfields']['Place of Birth'];
                $callArray["data"]["postal_code_of_birth"] = $params['additionalfields']['Postal Code of Birth'];
                break;
            case "Organization":
                $callArray["data"]["registrant_vat_id"] = $params['additionalfields']['VAT Number'];
                $callArray["data"]["trademark_number"] = $params['additionalfields']['Trademark Number'];
                $callArray["data"]["siren_siret"] = $params['additionalfields']['SIREN/SIRET Number'];
                break;
            default:
                break;
        }
    }
    // Pushes in all CA required information as well as checking to make sure
    // the province is in two letter format.
    if ($tld == 'ca') {

        $callArray["data"]["lang_pref"] = $lang;

        switch ($params['additionalfields'] ['Legal Type']) {
            case "Corporation":
                $callArray["data"]["legal_type"] = "CCO";
                break;
            case "Canadian Citizen":
                $callArray["data"]["legal_type"] = "CCT";
                break;
            case "Permanent Resident of Canada":
                $callArray["data"]["legal_type"] = "RES";
                break;
            case "Government":
                $callArray["data"]["legal_type"] = "GOV";
                break;
            case "Canadian Educational Institution":
                $callArray["data"]["legal_type"] = "EDU";
                break;
            case "Canadian Unincorporated Association":
                $callArray["data"]["legal_type"] = "ASS";
                break;
            case "Canadian Hospital":
                $callArray["data"]["legal_type"] = "HOP";
                break;
            case "Partnership Registered in Canada":
                $callArray["data"]["legal_type"] = "PRT";
                break;
            case "Trade-mark registered in Canada":
                $callArray["data"]["legal_type"] = "TDM";
                break;
            case "Canadian Trade Union":
                $callArray["data"]["legal_type"] = "TRD";
                break;
            case "Canadian Political Party":
                $callArray["data"]["legal_type"] = "PLT";
                break;
            case "Canadian Library Archive or Museum":
                $callArray["data"]["legal_type"] = "LAM";
                break;
            case "Trust established in Canada":
                $callArray["data"]["legal_type"] = "TRS";
                break;
            case "Aboriginal Peoples":
                $callArray["data"]["legal_type"] = "ABO";
                break;
            case "Legal Representative of a Canadian Citizen":
                $callArray["data"]["legal_type"] = "LGR";
                break;
            case "Official mark registered in Canada":
                $callArray["data"]["legal_type"] = "OMK";
                break;
            default:
                break;
        }

        if ($params['additionalfields'] ['Registrant Name'])
            $callArray["owner"]["org_name"] = $params['additionalfields'] ['Registrant Name'];
        $trademarkNum = $params['additionalfields'] ['Trademark Number'];

        $contacts = array("owner", "admin", "tech", "billing");

        // Checking for two letter format and reformatting if recognized
        foreach ($contacts as $contact) {
            if(!isset($callArray[$contact])){
                $callArray[$contact] = $callArray['personal'];
            }
            switch (strtolower($callArray[$contact]['state'])) {
                case "ontario":
                case "ont":
                case "ont.":
                case "on":
                    $callArray[$contact]['state'] = "ON";
                    break;
                case "alberta":
                case "alta":
                case "alta.":
                case "ab":
                    $callArray[$contact]['state'] = "AB";
                    break;
                case "quebec":
                case "que.":
                case "que":
                case "p.q.":
                case "qc":
                case "pq":
                    $callArray[$contact]['state'] = "QC";
                    break;
                case "nova scotia":
                case "n.s.":
                case "ns":
                    $callArray[$contact]['state'] = "NS";
                    break;
                case "new brunswick":
                case "n.b.":
                case "nb":
                    $callArray[$contact]['state'] = "NB";
                    break;
                case "manitoba":
                case "man":
                case "man.":
                case "mb":
                    $callArray[$contact]['state'] = "MB";
                    break;
                case "british columbia":
                case "b.c.":
                case "bc":
                    $callArray[$contact]['state'] = "BC";
                    break;

                case "prince edward island":
                case "p.e.i.":
                case "pei":
                case "pe":
                    $callArray[$contact]['state'] = "PE";
                    break;

                case "saskatchewan":
                case "sask":
                case "sask.":
                case "sk":
                    $callArray[$contact]['state'] = "SK";
                    break;

                case "newfoundland and labrador":
                case "newfoundland":
                case "labrador":
                case "nfld":
                case "nfld.":
                case "lab":
                case "lab.":
                case "nl":
                    $callArray[$contact]['state'] = "NL";
                    break;

                case "northwest territories":
                case "nt":
                    $callArray[$contact]['state'] = "NT";
                    break;

                case "yukon":
                case "yt":
                    $callArray[$contact]['state'] = "YT";
                    break;

                case "nunavut":
                case "nu":
                    $callArray[$contact]['state'] = "NU";
                    break;
                default:
                    trigger_error("Unable to recognize province for " . $contact . ", please provide in two letter format.", E_USER_WARNING);
                    break;
            }
        }

        $trademarkNum = trim($trademarkNum);

        if (empty($trademarkNum))
            $callArray["data"]["isa_trademark"] = "0";
        else
            $callArray["data"]["isa_trademark"] = "1";
    }
    
    /* Added by BC : NG : 27-6-2014 : For add log in addCCTLDFields function */
    opensrspro_logModuleCall(__FUNCTION__, $call, $callArray, $callArray, $params);
    /* End : For add log in addCCTLDFields function */

    return $callArray;
}

// Grabs username and password from OSRS if reseller account is allowed to do so.
function getDomainCredentials($domain, $params) {
    $domainCredentials = array();

    $credentialsCall = array(
        'func' => 'lookupGetUserAccessInfo',
        'data' => array(
            'domain' => $domain,
        ),
        'connect' => generateConnectData($params)
    );

    set_error_handler("osrsError", E_USER_WARNING);

    $credentialsReturn = processOpenSRS("array", $credentialsCall);

    restore_error_handler();

    if (strcmp($credentialsReturn->resultFullRaw["is_success"], "1") == 0) {
        $domainCredentials = $credentialsReturn->resultFullRaw["attributes"];
    } else {
        $osrsLogError = $credentialsReturn->resultFullRaw["response_text"];
    }

    opensrspro_logModuleCall(__FUNCTION__, $credentialsCall, $credentialsReturn->resultFullRaw, $domainCredentials, $params);

    return $domainCredentials;
}

// Checks to make sure the word reseller is not in an error message.  If it is,
// it will replace it with the general error.
function filterForResellerError($error, $generalError) {
    
     /* Added by BC : RA : 5-7-2014 : To set request string at logModuleCall */
    $callArray = array('error' => $error, 'generalError' => $generalError);
    /* End : To set request string at logModuleCall */

    $newError = "";

    if (preg_match("/\sreseller[\s\.,;\-:]/", $error) == 0)
        $newError = $error;
    else
        $newError = $generalError;
        
     /* Added by BC : NG : 27-6-2014 : For add log in filterForResellerError function */   
    if($error != "" && $generalError != "")
    {
        opensrspro_logModuleCall(__FUNCTION__, $callArray, $newError, $newError, '');
    }
    /* End : For add log in filterForResellerError function */

    return $newError;
}

/* Added by BC : NG : 9-7-2014 : To get config data */   
function getConfigurationParamsData()
{
    $result = mysql_query("SELECT setting,value FROM tblregistrars WHERE registrar='opensrspro'");
    while($row = mysql_fetch_assoc($result)){
        $params[$row['setting']] = decrypt($row['value']);
    } 
    return $params;
}
/* End : To get config data */

//This function is used to temporarily unlock domains in order to make changes.

function opensrspro_TempUnlock($params, $tempunlock) {

    global $osrsLogError;
    global $osrsError;

    $osrsLogError = "";
    $osrsError = "";

    $hashKey = $params["HashKey"];
    $tld = $params["tld"];
    $sld = $params["sld"];

    /*
      if(strcmp($params['CookieBypass'],"on")==0)
      $cookieBypass = true;
      else
      $cookieBypass = false;
     */
    $cookieBypass = true;

    if (!$cookieBypass) {
        $domainUser = getDomainUser($tld, $sld);
        $domainPass = getDomainPass($tld, $sld, $hashKey);
        $cookie = getCookie($sld . "." . $tld, $domainUser, $domainPass, $params);
    }
    else
        $cookie = false;

    //Check to see if domain needs to be locked or unlocked
    if (strcmp($tempunlock, "on") == 0) {
        $lockstate = "0";
    } elseif (strcmp($tempunlock, "off") == 0) {
        $lockstate = "1";
    } else {
        $temp = opensrspro_GetRegistrarLock($params);
        if (strcmp($temp, "locked") == 0) {
            $lockstate = "1";
        } else {
            $lockstate = "0";
        }
    }

    // Checks to see if there was an error grabbing the cookie
    if ($cookie !== false || $cookieBypass) {

        $callArray = array(
            'func' => 'provModify',
            'data' => array(
                'domain_name' => $sld . "." . $tld,
                'domain' => $sld . "." . $tld,
                'affect_domains' => "0",
                'data' => "status",
                'lock_state' => $lockstate
            ),
            'connect' => generateConnectData($params)
        );

        if ($cookieBypass)
            $callArray['data']['bypass'] = $sld . "." . $tld;
        else
            $callArray['data']['cookie'] = $cookie;

        set_error_handler("osrsError", E_USER_WARNING);

        $openSRSHandler = processOpenSRS("array", $callArray);

        restore_error_handler();

        if (strcmp($openSRSHandler->resultFullRaw["is_success"], "1") != 0) {
            $osrsError .= $openSRSHandler->resultFullRaw["response_text"] . "<br />";
            $osrsLogError .= $openSRSHandler->resultFullRaw["response_text"] . "\n";
        }
    }

    if (!empty($osrsLogError)) {
        if (empty($osrsError))
            $osrsError = $params["GeneralError"];
    }

    $values["error"] = filterForResellerError($osrsError, $params["GeneralError"]);

    opensrspro_logModuleCall(__FUNCTION__, $callArray, $openSRSHandler->resultFullRaw, $values, $params);

    return $values;
}

function opensrspro_logModuleCall($function, $callArray, $resultFullRaw, $return, $params) {

    $module = 'OpenSRSPro';
    $action = substr($function, 0, 11) == 'opensrspro_' ? substr($function, 11) : $function;
    $requeststring = $callArray;
    $responsedata = $resultFullRaw;
    //$processeddata = $return;
    $replacevars = array($params["TestUsername"], $params["TestAPIKey"], $params["ProdUsername"], $params["ProdAPIKey"]);
    logModuleCall($module, $action, $requeststring, $responsedata, $return, $replacevars);
}

global $registrant_verification_status;

function opensrspro_AdminDomainsTabFields($params){
    
    $domain = $params['sld'].'.'.$params['tld'];
    $domain_details = mysql_fetch_assoc(mysql_query("SELECT * FROM tbldomains WHERE id='".mysql_real_escape_string($params['domainid'])."'"));

    if($domain_details['status'] != 'Active')
        return;
        
    /* Added by BC : NG : 9-7-2014 : To set role perimission for hide Registrant Verification Status */
    $command   = 'getadmindetails';
    $adminuser = '';
    $values    = '';
     
    $results   = localAPI($command,$values,$adminuser);
    
    /* Added by BC : NG : 21-8-2014 : To set role perimission for hide Registrant Verification Status (Using Role Permission) */
    $admin_details = mysql_fetch_assoc(mysql_query("SELECT `roleid` FROM tbladmins WHERE id='".mysql_real_escape_string($results['adminid'])."'"));
    $query         = mysql_query("SELECT `permid` FROM tbladminperms WHERE `roleid`='".$admin_details['roleid']."'");
    $row           = mysql_num_rows($query);
    $permId        = array();
    if($row > 0){
        while($res=mysql_fetch_array($query)){
            array_push($permId,$res['permid']);
        }
    }
       
    if($results['result'] == 'success' and !in_array(999,$permId)) {return;}
    /* End : To set role perimission for hide Registrant Verification Status */
    
    global $osrsError;
    global $registrant_verification_status;
    
    if(isset($registrant_verification_status[$domain])){
        $results = $registrant_verification_status[$domain];
    }
    else{
        $results = opensrspro_getRegistrantVerificationStatus($params);
        $registrant_verification_status[$domain] = $results;
    }
    
    $error = filterForResellerError($osrsError, $params["GeneralError"]);
    if($error){
        $return = '<strong style="color:#f00">'.$error.'</strong>';
    }
    elseif($results['is_success']=='0'){
        $return = '<strong style="color:#f00">'.$results['response_text'].'</strong>';
    }
    else{
        
        $info_results = array();
    
        if($results['email_bounced'] == '1')
            $results['email_bounced'] = 'Yes';
        else if($results['email_bounced'] == '0')
            $results['email_bounced'] = 'No';

        if(is_array($results)){
            foreach($results as $k => $v){
                $k = ucwords(str_replace('_',' ', $k));
                $v = ucwords($v);
                $info_results[$k] = $v;
            }
        }
        
        $return = '<table>';
        foreach($info_results as $k => $v){
            $return .= "<tr><td><strong>{$k}:</strong></td><td>{$v}</td></tr>";
        }
        $return .= '</table>';
    }

    return array('Registrant Verification Status' => $return);
    
}

function opensrspro_AdminCustomButtonArray($params) {
    
    $domain = $params['sld'].'.'.$params['tld'];
    $domain_details = mysql_fetch_assoc(mysql_query("SELECT * FROM tbldomains WHERE id='".mysql_real_escape_string($params['domainid'])."'"));

    if($domain_details['status'] != 'Active')
        return;
        
    /* Added by BC : NG : 9-7-2014 : To set role perimission for hide Registrant Verification Status */
    $command   = 'getadmindetails';
    $adminuser = '';
    $values    = '';
     
    $results   = localAPI($command,$values,$adminuser);
    
    /* Added by BC : NG : 21-8-2014 : To set role perimission for hide Registrant Verification Status (Using Role Permission) */
    $admin_details = mysql_fetch_assoc(mysql_query("SELECT `roleid` FROM tbladmins WHERE id='".mysql_real_escape_string($results['adminid'])."'"));
    $query         = mysql_query("SELECT `permid` FROM tbladminperms WHERE `roleid`='".$admin_details['roleid']."'");
    $row           = mysql_num_rows($query);
    $permId        = array();
    if($row > 0){
        while($res=mysql_fetch_array($query)){
            array_push($permId,$res['permid']);
        }
    }
       
    if($results['result'] == 'success' and !in_array(999,$permId)) {return;}
    /* End : To set role perimission for hide Registrant Verification Status */
    
    global $registrant_verification_status;
    
    if(isset($registrant_verification_status[$domain])){
        $status = $registrant_verification_status[$domain];
    }
    else{
        $status = opensrspro_getRegistrantVerificationStatus($params);
        $registrant_verification_status[$domain] = $status;
    }

    $buttonarray = array();
    
    if($status['registrant_verification_status']=='verifying'){
        $buttonarray["Resend Verification Email"] = "resendVerificationEmail";
    }
    
    return $buttonarray;
}

function opensrspro_resendVerificationEmail($params){
    
    global $osrsError;
    global $osrsLogError;

    $domain = $params['sld'].'.'.$params['tld'];
    
   /* Changed by BC : NG : 9-7-2014 : For get comfig data */ 
    
    /*$result = mysql_query("SELECT setting,value FROM tblregistrars WHERE registrar='opensrspro'");
    while($row = mysql_fetch_assoc($result)){
        $params[$row['setting']] = decrypt($row['value']);
    } */
    $params = array_merge($params,getConfigurationParamsData());    
    /* End : For get comfig data */
    
    /* Added by BC : NG : 9-7-2014 : To set role perimission for hide Registrant Verification Status */
    $command   = 'getadmindetails';
    $adminuser = '';
    $values    = '';
     
    $results   = localAPI($command,$values,$adminuser);
    
    /* Added by BC : NG : 21-8-2014 : To set role perimission for hide Registrant Verification Status (Using Role Permission) */
    $admin_details = mysql_fetch_assoc(mysql_query("SELECT `roleid` FROM tbladmins WHERE id='".mysql_real_escape_string($results['adminid'])."'"));
    $query         = mysql_query("SELECT `permid` FROM tbladminperms WHERE `roleid`='".$admin_details['roleid']."'");
    $row           = mysql_num_rows($query);
    $permId        = array();
    if($row > 0){
        while($res=mysql_fetch_array($query)){
            array_push($permId,$res['permid']);
        }
    }
       
    if($results['result'] == 'success' and !in_array(999,$permId)) {return;}
    /* End : To set role perimission for hide Registrant Verification Status */
    
    $callArray = array(
        'func' => 'sendRegistrantVerificationEmail',
        'data' => array(
                'domain' => $domain,
            ),
        'connect' => generateConnectData($params)
    );

    $results = array();
    
    set_error_handler("osrsError", E_USER_WARNING);
    $openSRSHandler = processOpenSRS("array", $callArray);
    restore_error_handler();

    // Check for errors from the API and add to the error variables
    if(strcmp($openSRSHandler->resultFullRaw["is_success"], "1")==0){
        $results['message'] = $openSRSHandler->resultFullRaw["response_text"];
    } else {
        $osrsError .= $openSRSHandler->resultFullRaw["response_text"] . "<br />";
        $osrsLogError .= $openSRSHandler->resultFullRaw["response_text"] . "\n";
        $results['error'] = $openSRSHandler->resultFullRaw["response_text"];
    }
    
    // Log and output any error messages.
    if(!empty($osrsLogError)){
        if(empty($osrsError))
            $osrsError = $params["GeneralError"];
    }
    
    opensrspro_logModuleCall(__FUNCTION__,$callArray,$openSRSHandler->resultFullRaw,$results,$params);
    return $results;
}

function opensrspro_getRegistrantVerificationStatus($params){
    
    global $osrsError;
    global $osrsLogError;

    $domain = $params['sld'].'.'.$params['tld'];
    
    /* Changed by BC : NG : 9-7-2014 : For get comfig data */ 
    
    /*$result = mysql_query("SELECT setting,value FROM tblregistrars WHERE registrar='opensrspro'");
    while($row = mysql_fetch_assoc($result)){
        $params[$row['setting']] = decrypt($row['value']);
    } */
    
    $params = array_merge($params,getConfigurationParamsData());   
    
    /* End : For get comfig data */

    $callArray = array(
        'func' => 'lookupGetRegistrantVerificationStatus',
        'data' => array(
                'domain' => $domain,
            ),
        'connect' => generateConnectData($params)
    );

    $results = array();
    
    set_error_handler("osrsError", E_USER_WARNING);
    $openSRSHandler = processOpenSRS("array", $callArray);
    restore_error_handler();
    
    if($openSRSHandler->resultFullRaw["is_success"]=='0'){
        opensrspro_logModuleCall(__FUNCTION__,$callArray,$openSRSHandler->resultFullRaw,$openSRSHandler->resultFullRaw,$params);
        return $openSRSHandler->resultFullRaw;
    }

    $results = $openSRSHandler->resultFullRaw["attributes"];
    opensrspro_logModuleCall(__FUNCTION__,$callArray,$openSRSHandler->resultFullRaw,$results,$params);
    return $results;
}

if(!class_exists('lookupGetRegistrantVerificationStatus')){
    class lookupGetRegistrantVerificationStatus extends openSRS_base{
        public function __construct ($formatString, $dataObject) {
            parent::__construct($dataObject);
            $this->_dataObject = $dataObject;
            $this->_process ();
        }
        private function _process(){

            $cmd = array(
                "protocol" => "XCP",
                "action" => "get_registrant_verification_status",
                "object" => "domain",
                "attributes" => array(
                    "domain" => $this->_dataObject->data->domain,
                )
            );

            $xmlCMD = $this->_opsHandler->encode($cmd);
            $XMLresult = $this->send_cmd($xmlCMD);
            $arrayResult = $this->_opsHandler->decode($XMLresult);

            // Results
            $this->resultFullRaw = $arrayResult;

            if (isSet($arrayResult['attributes']['lookup']['items'])){
                    $this->resultRaw = $arrayResult['attributes']['lookup']['items'];
            } else {
                    $this->resultRaw = $arrayResult;
            }

            $this->resultFullFormated = convertArray2Formatted ($this->_formatHolder, $this->resultFullRaw);
            $this->resultFormated = convertArray2Formatted ($this->_formatHolder, $this->resultRaw);
        }

    }
}

if(!class_exists('sendRegistrantVerificationEmail')){
    class sendRegistrantVerificationEmail extends openSRS_base{
        public function __construct ($formatString, $dataObject) {
            parent::__construct($dataObject);
            $this->_dataObject = $dataObject;
            $this->_process ();
        }
        private function _process(){
            $cmd = array(
                "protocol" => "XCP",
                "action" => "send_registrant_verification_email",
                "object" => "domain",
                "attributes" => array(
                    "domain" => $this->_dataObject->data->domain,
                )
            );

            $xmlCMD = $this->_opsHandler->encode($cmd);
            $XMLresult = $this->send_cmd($xmlCMD);
            $arrayResult = $this->_opsHandler->decode($XMLresult);

            // Results
            $this->resultFullRaw = $arrayResult;
            $this->resultRaw = $arrayResult;

            $this->resultFullFormated = convertArray2Formatted ($this->_formatHolder, $this->resultFullRaw);
            $this->resultFormated = convertArray2Formatted ($this->_formatHolder, $this->resultRaw);
        }

    }
}

?>
