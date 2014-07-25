=================================================
|             OpenSRS Domains Pro WHMCS Module  |
|      	     Date:  June 12, 2014 				|
|	  Version:  2.0 Beta 2								|
|         Website:  www.opensrs.com				|
|	  support:  help@opensrs.com				|
=================================================

The Module Requires:

	* WHMCS 5.2.6+ and 5.3.x
	* PHP 5.2+
	* PEAR - http://pear.php.net/
	* mcrypt - http://www.php.net/manual/en/book.mcrypt.php
        * getmypid() enabled

	** 'TCP Out' ports 51000, 55000 and 55443 have to be open on the server for lookups and http(s) connections to OpenSRS API

####################################
1. Installation of Registrar Module:
####################################

1) Unpack the module on your computer

2) Copy the respective files to your WHMCS installation using the included file tree structure
	*lang file - If you've made changes to your english.php lang file, you can copy the code in the append_to_lang.txt file to the bottom of your existing english.php file.
	*additionaldomainfields.php - Copy the additional lines from append_to_additionaldomainfields.php file to the /includes/additionaldomainfields.php file if you'd like to sell EU, BR and BE.

3) Log into your admin interface and visit Setup->Product/Services->Domain Registrars and activate OpenSRS Pro. See the next section to configure the module.



############################
2. Configuring OpenSRS Pro:
############################

TestUsername: <Your test environment username> 
TestAPIKey: <Your test environment API Key> (different from your live key) 
ProdUsername: <Your live environment username> 
ProdAPIKey: <Your live environment API Key> 
Hashkey: <enter a random string of characters. This is used to create strong passwords when provisioning domains and should never be changed once it is set as it is used to access previously registered domains.> *This field is optional 
TestMode: <If checked, your test environment login details will be used>

The rest of the options are optional and can be activated as needed.

ForceForwardingIP: <Leave Unchecked> 
ChangeLockedDomains: <Allow WHMCS to make changes to a domain automatically by unlocking and then locking the domain again> 
LockTechContact: <Use the tech contact provided in RWI instead of what is entered into WHMCS> 
GeneralError: <Enter a general error message users will see when there is an error.>
DisableTemplateChanges: <Enabling this will turn off changes to the template files>

########################
3. Upgrade Instructions:
########################

******************************
Upgrading from OpenSRScustom
******************************


1) This WHMCS module changes the format of the username and password for domains compared to the way it is done by the default OpenSRS module provided with WHMCS.  
To migrate from the default OpenSRS WHMCS module to this one, enable the 'Cookie Bypass' option we've added in the 'Domain Registrars' settings for OpenSRS Pro.

****
Please note usernames/passwords are set for domains by OpenSRS to provide end-users the ability to change their domain information using an end-user Manage Web Interface (MWI).
With WHMCS, there is a client area that end users use to manage their domains.  The module is linked with OpenSRS to make these changes from one place, therefore eliminating the need to use the MWI. If your clients experience issues making changes, please enable the 'Cookie Bypass' option to fix this issue.
****

2) If you are upgrading from our previous version of this module, called opensrscustom, you will need to update the registrar for each domain. You can run this SQL command in phpMyAdmin (please backup your database first) and it will update all your domains. Make sure to configure and install opensrspro before running this.

UPDATE `tbldomains` SET `registrar`="opensrspro" WHERE `tbldomains`.`registrar`="opensrscustom"


************************************************
Upgrading from opensrspro to the latest release
************************************************

If you are using beta 6, we have simplified the installation process and removed the domainlookup.php file. Please follow these instructions to install beta 7 or later. If you are already using 1.0+ release, please see the modified files in the change log at the bottom:

1. Remove the file /whmcs/domainlookup.php
2. Remove the template file /whmcs/templates/default/domainlookup.tpl (replace default with your theme name)
3. Replace the file /whmcs/templates/default/domainchecker.tpl with from a fresh install of WHMCS
4. Replace the file /whmcs/includes/hooks/opensrspro_hooks.php with the new version included in this package



**********************************************
Upgrading from OpenSRS built in WHMCS module
**********************************************

1) If you are upgrading from the WHMCS built in module, called opensrs, you will need to update the registrar for each domain. You can run this SQL command in phpMyAdmin (please backup your database first) and it will update all your domains. Make sure to configure and install opensrspro before running this.

UPDATE `tbldomains` SET `registrar`="opensrspro" WHERE `tbldomains`.`registrar`="opensrs"



#########################################
4. SUPPORTED TLD's
#########################################

gTLD
.com
.net
.org
.info
.biz
.mobi
.asia
.tel
.name

ccTLD
.at
.au [.com.au | .net.au | .org.au | .asn.au | .id.au]
.be
.br
.bz
.ca
.cc
.ch
.co
.de
.dk
.es
.eu
.in
.it
.li
.me
.mx
.nl
.pw
.tv
.uk [.co.uk | .me.uk | .org.uk]
.us
.ws

Other
.pro
.xxx

New TLDs:

HOLDINGS
VENTURES
SINGLES
CLOTHING
GURU
BIKE
PLUMBING
CAMERA
LIGHTING 
EQUIPMENT
ESTATE
GRAPHICS
GALLERY
PHOTOGRAPHY
LAND
TODAY
TECHNOLOGY
CONTRACTORS
DIRECTORY
KITCHEN
CONSTRUCTION
DIAMONDS
ENTERPRISES
TIPS
VOYAGE
CAREERS
PHOTOS
RECIPES
SHOES
LIMO
DOMAINS
CAB
COMPANY
COMPUTER
SYSTEMS
ACADEMY
MANAGEMENT
CENTER
BUILDERS
EMAIL
SOLUTIONS
SUPPORT
TRAINING
CAMP
EDUCATION
GLASS
INSTITUTE
REPAIR
COFFEE
HOUSE
FLORIST
INTERNATIONAL
SOLAR
HOLIDAY
MARKETING

When setting up domain pricing for each TLD - WHMCS offers DNS Management, Email Forwarding, ID Protection and EPP Code options.
Please ensure the TLD you're setting up offers the service before enabling it.

#######################
5. TLD SPECIFIC NOTES:
#######################


ES Domains:
The whois server listed for .es is missing the 's' after 'http' in the whoisservers.php file that comes with WHMCS 5.1.2.  This causes .es domain lookups to return "Domain taken" for all .es domains.

Fix:
in the includes directory, open the file "whoisservers.php" and add a 's' after the 'http' as shown below:
change:
.es|http://www.realtimeregister.com/page/whois_check.php?domein=|HTTPREQUEST-es available
to:
.es|https://www.realtimeregister.com/page/whois_check.php?domein=|HTTPREQUEST-es available


---

UK Domains:
You can register .co.uk, .me.uk and .org.uk domains.
New orders are not supported for .net.uk, .plc.uk and .ltd.uk not supported.

---

.DE DOMAINS:

DENIC enforces a strict nameserver policy for all .de domains. The nameservers need to be on different subnets (at least one of the first 3 octets of the IP addresses must be different). Nameservers need to be fully functional AND the domain zone file needs to be created on the nameservers prior to or up to 30 days after the registration.

---


########################
6. Change Log
########################

Release 2.0
- Rewritten and updated the PHP Tool Kit
- Replace all files to upgrade to 2.0 except additionaldomainfields.php and english.php
- Known Issues - Locking/Unlocking does not work

Release 1.3.1
- Removed extra quote from language file
- Fixed dependency issues
- Replace entire folder contents in /modules/registrars/opensrspro/opensrs/
- Replace english language file if you are receiving syntax errors

Release 1.3
- Cleaned up the read me file
- Cleaned up PHP library files (removed files noted below)
- Added full support for registrant verification status and resending of emails (modified: /includes/hooks/opensrspro_customadminfields.php)
- Added support for additional new gTLDs (listed above) (modified append_to_whoisservers.php and append_to_additionaldomainfields.txt)
- To allow for availability checks for new TLDs, copy the contents of 'append_to_whoisservers.php' and place at the end of /includes/whoisservers.php file
- Modified Files: /includes/hooks/opensrspro_customadminfields.php, /modules/registrars/opensrspro/opensrspro.php, /modules/registrars/opensrspro/opensrs/openSRS_config.php, /modules/registrars/opensrspro/opensrs/openSRS_loader.php
- Removed: /modules/registrars/opensrspro/opensrs/mail/*, /modules/registrars/opensrspro/opensrs/plugins/*, /modules/registrars/opensrspro/opensrs/openSRS_mail.php



Release 1.2
- Fixed additional quote syntax error in language file
- Fixed read me file clarification issue
- Fixed a bug that caused expired domains to fail when renewing
- Removed ability to manage/order Contact Privacy from client side. Can only be added via built in WHMCS functionality manually.
- Custom template files/code has been removed. Please replace these files with the original WHMCS versions or remove the custom code that you have added for contact privacy.
- Modified files: Replace all files and remove template files


Release 1.1
- Fixed a bug that prevented admins from updating the contact information on domains
- Modified files: opensrspro.php 

Release 1.0
- Updated read me file


Beta 12
- Fixed a bug that prevented both Domains Pro and the default OpenSRS module from running simultaneously (opensrspro.php)
- Added new language requirements for 5.2.x (english.php)

Beta 11 
- Internal Release

Beta 10 - Jun 25th/13
- Removed ShowSuggest settings option
- Fixed adding of 5th name server not being recognized


Beta 9 - Jun 24th/13
- Fixes authentication issues experienced by some domains (files changed: opensrspro.php)
- Debug logging now displays in WHMCS debug area and not in a text file

Beta 8 - May 2nd/13 - Internal release
- Fixes a .ca contact update bug (files changed: opensrspro.php)

Beta 7 - Apr 22/13
- Removed domainlookup.php and our own whois servers. We are now using the default whmcs whois servers to simplify installation
- Added support for .pw
- You can now use our module alongside additional registrar modules
- Fixed a bug that prevented .CA names from being registered properly

Beta 6 - Feb 25/13
- Fixes dns management false error notice
- Premium domain support has been officially removed
- If client purchases whois privacy, service isn't activated until invoice is paid
- If client purchases whois privacy, invoice opens in new window instead of same window
- Removing privacy feature from domain is now possible so client is billed during next billing cycle


Beta 5 - Feb 25/13:
- Simplifies installation
- Adds support for Whois Privacy purchasing from client area if domain doesn't have it enabled
- Adds .BR support
- Adds support for WHMCS domain sync script
- Various bug fixes

Beta 4
- Internal release

Beta 3:
- Bug fixes




Please visit the OpenSRS Reseller Resource center (http://opensrs.com/site/resources) for complete details and requirements for each tld.