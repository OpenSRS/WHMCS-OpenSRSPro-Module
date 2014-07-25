<?php

// Paths
// Setting default path to the same directory this file is in
if(!defined('DS')) define('DS', DIRECTORY_SEPARATOR);
if(!defined('PS')) define('PS', PATH_SEPARATOR);
if(!defined('CRLF')) define('CRLF', "\r\n");


//define('OPENSRSURI', dirname(__FILE__));

// Application core configurations.
//define("OPENSRSCONFINGS", OPENSRSURI . DS . "configurations");
/**
 * OpenSRS Domain service directories include provisioning, lookup, and dns
 */
//define('OPENSRSDOMAINS', OPENSRSURI . DS . 'domains');

/**
 * OpenSRS publishing service directory
 */
//define('OPENSRSPUBLISHING', OPENSRSURI . DS . 'publishing');

/**
 * OpenSRS email service (APP) directory
 */
//define('OPENSRSMAIL', OPENSRSURI . DS . 'mail');


//define('OPENSRSFASTLOOKUP', OPENSRSURI . DS . 'fastlookup');


// load connection data 

//if (function_exists('mysql_safequery') == false) {
//
//    function mysql_safequery($query, $params = false) {
//        if ($params) {
//            foreach ($params as &$v) {
//                $v = mysql_real_escape_string($v);
//            }
//            $sql_query = vsprintf(str_replace("?", "'%s'", $query), $params);
//            $sql_query = mysql_query($sql_query);
//        } else {
//            $sql_query = mysql_query($query);
//        }
//        return ($sql_query);
//    }
//
//}
//$params = array();
//$result = mysql_query("SELECT setting,value FROM tblregistrars WHERE registrar='opensrspro'");
//while ($row = mysql_fetch_assoc($result)) {
//    $params[$row['setting']] = decrypt($row['value']);
//}
//
//// OpenSRS reseller username
//if($params['TestMode'] == 'on')
//    define('OSRS_USERNAME', $params['TestUsername']);
//else
//    define('OSRS_USERNAME', $params['ProdUsername']);
//
//// OpenSRS reseller private Key. Please generate a key if you do not already have one.
//if($params['TestMode'] == 'on')
//    define('OSRS_KEY', $params['TestAPIKey']);
//else  
//    define('OSRS_KEY', $params['ProdAPIKey']);
//
////OpenSRS domain service API url.
////LIVE => rr-n1-tor.opensrs.net, TEST => horizon.opensrs.net
//if($params['TestMode'] == 'on')
//    define('OSRS_HOST', 'horizon.opensrs.net');
//else 
//    define('OSRS_HOST', 'rr-n1-tor.opensrs.net');
///**
// * OpenSRS default encryption type => ssl, sslv2, sslv3, tls
// */
//define('CRYPT_TYPE', 'ssl');
///**
// * OpenSRS API SSL port
// */
//define('OSRS_SSL_PORT', '55443');
///**
// * OpenSRS protocol. XCP or TPP.
// */
//define('OSRS_PROTOCOL', 'XCP');
///**
// * OpenSRS version
// */
//define('OSRS_VERSION', 'XML:0.1');
///**
// * OpenSRS domain service debug flag
// */
//define('OSRS_DEBUG', 0);
///**
// * OpenSRS API fastlookup port`
// */
//define('OSRS_FASTLOOKUP_PORT', '51000');
