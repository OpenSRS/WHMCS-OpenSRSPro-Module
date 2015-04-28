<?php

if(isset($_REQUEST['debugmode'])){
    if($_REQUEST['debugmode']){
        $_SESSION['debugmode']=true;
    }
    else{
        $_SESSION['debugmode']=false;
    }
}
if(isset($_SESSION['debugmode']) && $_SESSION['debugmode'] && $_SESSION['adminloggedinstatus']){
    error_reporting(E_ALL);
    ini_set('display_errors',1);
}

if(function_exists('mysql_safequery') == false) {
    function mysql_safequery($query,$params=false) {
        if ($params) {
            foreach ($params as &$v) { $v = mysql_real_escape_string($v); }
            $sql_query = vsprintf( str_replace("?","'%s'",$query), $params );
            $sql_query = mysql_query($sql_query);
        } else {
            $sql_query = mysql_query($query);
        }
        return ($sql_query);
    }
}

function opensrspro_getSetting($setting){
    
    $result = mysql_safequery("SELECT value FROM tblregistrars WHERE registrar='opensrspro' AND setting=?",array($setting));
    if($row = mysql_fetch_assoc($result))
        return decrypt($row['value']);
    return false;
}

function hook_opensrspro_ActivateTemplatesChangesHeadOutput($vars){
    
    $pre_script='';
    $script = '';    
    /* Tld AU start */     
    if($vars['filename'] == "ordersadd" || $vars['filename'] == "clientsdomains")
    {
            $script.='
            <script type="text/javascript">
            //<![CDATA[
             var all_prerequisites_exist = 0;
                       var js_e_type_ids = {"Trademark Owner":[{"value":"TM","name":"Trademark"}],"Registered Business":[{"value":"OTHER","name":"Australian Registered Body Number"}],"Partnership":[{"value":"ABN","name":"Australian Business Number"}],"Sole Trader":[{"value":"ABN","name":"Australian Business Number"}],"Pending TM Owner":[{"value":"TM","name":"Trademark"}],"Company":[{"value":"ACN","name":"Australian Company Number"}],"Trade Union":[{"value":"OTHER","name":"Organisation Number"}],"Charity":[{"value":"ABN","name":"Australian Business Number"}],"Incorporated Association":[{"value":"ACT BN","name":"Australian Capital Territory Business Number"},{"value":"NSW BN","name":"New South Wales Business Number"},{"value":"NT BN","name":"Northern Territory Business Number"},{"value":"QLD BN","name":"Queensland Business Number"},{"value":"SA BN","name":"South Australia Business Number"},{"value":"TAS BN","name":"Tasmania Business Number"},{"value":"VIC BN","name":"Victoria Business Number"},{"value":"WA BN","name":"Western Australia Business Number"}],"Club":[{"value":"ABN","name":"Australian Business Number"}],"Non-profit Organisation":[{"value":"ABN","name":"Australian Business Number"},{"value":"ACT BN","name":"Australian Capital Territory Business Number"},{"value":"ACN","name":"Australian Company Number"},{"value":"OTHER","name":"Australian Registered Body Number"},{"value":"NSW BN","name":"New South Wales Business Number"},{"value":"NT BN","name":"Northern Territory Business Number"},{"value":"QLD BN","name":"Queensland Business Number"},{"value":"SA BN","name":"South Australia Business Number"},{"value":"TAS BN","name":"Tasmania Business Number"},{"value":"VIC BN","name":"Victoria Business Number"},{"value":"WA BN","name":"Western Australia Business Number"}],"Citizen/Resident":[{"value":"ABN","name":"Australian Business Number"},{"value":"ACN","name":"Australian Company Number"},{"value":"ACT BN","name":"Australian Capital Territory Business Number"},{"value":"NSW BN","name":"New South Wales Business Number"},{"value":"OTHER","name":"Other"},{"value":"NT BN","name":"Northern Territory Business Number"},{"value":"QLD BN","name":"Queensland Business Number"},{"value":"SA BN","name":"South Australia Business Number"},{"value":"TAS BN","name":"Tasmania Business Number"},{"value":"TM","name":"Trademark"},{"value":"VIC BN","name":"Victoria Business Number"},{"value":"WA BN","name":"Western Australia Business Number"}]};
                       
                        function prerequisites_exist() {
                            // Defensive.. test all ids exist before we enable the functionality
                            var ids = [ \'id_au_eligibility_type\', \'id_au_eligibility_id_type\' ];
                            for (var i = 0; i < ids.length; ++i ) {
         
                            if ( ! $(\'#"+ids[i]\') )  {
                                return false;
                            }
                            }
                            all_prerequisites_exist = 1;
                            return true;
                        }
                        function on_change_entity_type(entity_type) { 
                            if ( ( ! all_prerequisites_exist ) || entity_type == undefined || ! js_e_type_ids[entity_type] ) {
                                return false;
                            }
                            
                            var select_e_id_type = $("#id_au_eligibility_id_type");
                            select_e_id_type.empty();
            
                            for (var i = 0; i < js_e_type_ids[entity_type].length; i++) {
                                var o = document.createElement("option");
                                o.value = js_e_type_ids[entity_type][i].value;
                                o.text =  js_e_type_ids[entity_type][i].name;
                                select_e_id_type.append(o);
                            }
                            return false;
                        }
                        function update_ui_on_load(){ 
                            if ( ! prerequisites_exist() ) { return false; }
                            on_change_entity_type( $("#id_au_eligibility_type").val());
                        }
                        
                         //]]>
                </script>"
                        ';   
    }
    /* Tld AU End */
    $script.='
        <script type="text/javascript">
        //<![CDATA[
            jQuery(document).ready(function(){
    ';
    
    /* Added by BC : NG : 8-10-2014 : To display domain notes */ 
       if($vars['filename'] == "clientsdomains")
       {
            $command = 'getadmindetails';
            $adminuser = '';
            $values = '';
             
            $resultData = localAPI($command,$values,$adminuser);
            $adminDetails = mysql_fetch_assoc(mysql_query("SELECT * FROM tbladmins WHERE id='".mysql_real_escape_string($resultData['adminid'])."'"));
            $resQuery = mysql_query("SELECT permid FROM tbladminperms WHERE roleid='".$adminDetails['roleid']."'");
            $rowData = mysql_num_rows($resQuery);
            $permIds = array();
            if($rowData > 0)
            {
                while($resData=mysql_fetch_array($resQuery))
                {
                    array_push($permIds,$resData['permid']);
                }
            }
               
            if(in_array(9999,$permIds))
            {
                $script.= "
                    jQuery('table.form tr:last').append('<div id=\'dialog\' title=\'Domain Notes\' scrolling=\'auto\'></div>');
                    if(jQuery('input[value=\'View Domain Notes\']'))
                    {
                        jQuery('input[value=\'View Domain Notes\']').after('&nbsp;<div id=\'dialogloader\' style=\'display:none\'><img src=\'/whmcs/images/loadingsml.gif\' /></div>')
                        var tokenurl = jQuery('input[value=\'View Domain Notes\']').attr('onclick').split('&token=');
                        var rplurl = tokenurl[1].replace(\"'\",'');
                        var DomainId = jQuery('select[name=\'id\'] option:selected').val();
                        jQuery('input[value=\'View Domain Notes\']').attr('onclick', '');
                        jQuery('input[value=\'View Domain Notes\']').click(function(event){
                            jQuery('#dialogloader').css('display', 'inline'); 
                            var url = '/whmcs/admin/clientsdomains.php?userid=".$_REQUEST['userid']."&id='+DomainId+'&regaction=custom&ac=viewdomainnotes&token='+rplurl;
                            jQuery.ajax({url:url,success:function(result){
                              jQuery('#dialog').html(result);
                              if(result)
                              {
                                jQuery('#dialogloader').css('display', 'none');
                                jQuery('#dialog').dialog({
                                    autoOpen: true,
                                    resizable: false,
                                    width: 800,
                                    height: 500,
                                    modal: true,
                                    position: 'center', 
                                    open: function (event, ui) {
                                      jQuery('#dialog').css('overflow', 'auto'); 
                                    }
                                });
                              }
                            }});
                        });
                    }
                    

                ";
            }
       }

        /* End : To display domain notes */
        
        /* Tld AU start */     
        if($vars['filename'] == "ordersadd" || $vars['filename'] == "clientsdomains")
        {
            if($vars['filename'] == "ordersadd")
            {
               $script.='var orderFlag = 1'; 
            }
            else
            {
                if($_REQUEST['domainid'])
                {
                    $domainId = $_REQUEST['domainid'];
                }
                else
                {
                     $domainId = $_REQUEST['id'];
                }
                $resQuery = mysql_query("SELECT name,value FROM tbldomainsadditionalfields WHERE domainid='".$domainId."' AND name = 'Eligibility ID Type'");
                $rowData = mysql_fetch_assoc($resQuery);
                if($rowData > 0)
                {   
                  $eligibilityIDType = $rowData['value'];
                }
                $resQuery1 = mysql_query("SELECT name,value FROM tbldomainsadditionalfields WHERE domainid='".$domainId."' AND name = 'Eligibility Type'");
                $rowData1 = mysql_fetch_assoc($resQuery1);
                if($rowData1 > 0)
                {  
                  $eligibilityType = $rowData1['value'];
                }
                  $script.='var orderFlag = 0'; 
            }
            $script.='
            
            function autldfield()
            {   
                $(".fieldlabel").each(function(e){
                    if(this.innerHTML == "Eligibility Type")
                    {
                       if(orderFlag == 1)
                       {
                            var domain = $("#regdomain0").val();
                       }
                       else
                       {
                            var domain = $("input:text[name=domain]").val()
                       }
                       var domainArr = domain.split(".");
                       var domainTld = domainArr.splice(-2,2)
                       var tld = domainTld[0] + "." + domainTld[1]; 
                       if(tld == "com.au" || tld == "net.au")
                       {      
                           $("select[name=\'domainfield[0][5]\']").attr({id: "id_au_eligibility_id_type"});
                           $("select[name=\'domainfield[0][6]\']").attr({id: "id_au_eligibility_type", onchange : "return on_change_entity_type(this.value)"}); 
                           $("select[name=\'domainfield[5]\']").attr({id: "id_au_eligibility_id_type"});
                           $("select[name=\'domainfield[6]\']").attr({id: "id_au_eligibility_type", onchange : "return on_change_entity_type(this.value)"});
                             
                           var newOptions = {"Company": "Company",  "Partnership": "Partnership",  "Pending TM Owner": "Pending TM Owner", "Registered Business" : "Registered Business", "Sole Trader" : "Sole Trader",  "Trademark Owner" : "Trademark Owner"};

                           var $el = $("#id_au_eligibility_type");
                           $el.empty();
                           $.each(newOptions, function(value,key) {
                           $el.append($("<option></option>")
                             .attr("value", value).text(key));
                           });  
                           if(orderFlag == 0)
                           {
                             $el.val("'.$eligibilityType.'"); 
                           }
                           update_ui_on_load();
                           if(orderFlag == 0)
                           {
                             $("#id_au_eligibility_id_type").val("'.$eligibilityIDType.'");
                           }

                       }
                       if(tld == "org.au" || tld == "asn.au")
                       {      
                           $("select[name=\'domainfield[0][5]\']").attr({id: "id_au_eligibility_id_type"});
                           $("select[name=\'domainfield[0][6]\']").attr({id: "id_au_eligibility_type", onchange : "return on_change_entity_type(this.value)"}); 
                           $("select[name=\'domainfield[5]\']").attr({id: "id_au_eligibility_id_type"});
                           $("select[name=\'domainfield[6]\']").attr({id: "id_au_eligibility_type", onchange : "return on_change_entity_type(this.value)"}); 
                           var newOptions2 = {"Charity": "Charity",  "Club": "Club",  "Incorporated Association": "Incorporated Association", "Non-profit Organisation" : "Non-profit Organisation", "Trade Union" : "Trade Union"};

                           var $el = $("#id_au_eligibility_type");
                           $el.empty();
                           $.each(newOptions2, function(value,key) {
                           $el.append($("<option></option>")
                             .attr("value", value).text(key));
                           });
                           if(orderFlag == 0)
                           {
                             $el.val("'.$eligibilityType.'"); 
                           }
                           update_ui_on_load();
                           if(orderFlag == 0)
                           {
                             $("#id_au_eligibility_id_type").val("'.$eligibilityIDType.'");
                           }
                           
                       }
                       if(tld == "id.au")
                       {      
                           $("select[name=\'domainfield[0][5]\']").attr({id: "id_au_eligibility_id_type"});
                           $("select[name=\'domainfield[0][6]\']").attr({id: "id_au_eligibility_type", onchange : "return on_change_entity_type(this.value)"}); 
                           $("select[name=\'domainfield[5]\']").attr({id: "id_au_eligibility_id_type"});
                           $("select[name=\'domainfield[6]\']").attr({id: "id_au_eligibility_type", onchange : "return on_change_entity_type(this.value)"});
                           
                           var newOptions3 = {"Citizen/Resident" : "Citizen/Resident"};
                           var $el = $("#id_au_eligibility_type");
                           $el.empty();
                           $.each(newOptions3, function(value,key) {
                           $el.append($("<option></option>")
                             .attr("value", value).text(key));
                           });
                           if(orderFlag == 0)
                           {
                             $el.val("'.$eligibilityType.'");
                           }
                           update_ui_on_load();
                           if(orderFlag == 0)
                           {
                             $("#id_au_eligibility_id_type").val("'.$eligibilityIDType.'");
                           }
                       }
                    }
                });
            }
            
            
            $("#domain0").blur(function(ele){
                autldfield();
            });
            
            $("#regdomain0").blur(function(ele){
                autldfield();
            });
            
             $("#domreg0").click(function(ele){
                    setTimeout(function() {
                          autldfield();
                    }, 1000);
            });
            
            ';
            
            if($vars['filename'] == "clientsdomains")
            {
                $script.='autldfield();';
            }
            
            
        }
        /* Tld AU End */  
    
    if(($vars['filename']=='clientarea' || $vars['filename']=='register' || $vars['filename']=='cart' || $vars['filename']=='clientsdomaincontacts' || $vars['filename']=='clientscontacts' || $vars['filename']=='clientsprofile' || $vars['filename']=='clientsadd') && opensrspro_getSetting('DisableTemplatesChanges')!='on'){
        
        /*$pre_script.='
            <script type="text/javascript" src="includes/jscript/validate.js"></script>
        ';*/
        
        /* Added by BC : NG : 9-8-2014 : To Add validations for phone and email to contact forms  */ 
        $script.='
            jQuery("input[name=\'phonenumber\']").blur(function(){
                if(!this.value.match(/^(\+?[0-9]{1,3})\.[0-9]+x?[0-9]*$/))
                {
                    if(!jQuery("#msg").length)
                    {
                        jQuery("input[name=\'phonenumber\']").after("<div id=\'msg\'></div>");
                    }
                    jQuery("#msg").html("<span style=\'color:#DF0101\'>Invalid Phone Number Format (ex. +1.4163334444 or 1.4163334444)</span>");
                     jQuery(".btn-primary").prop("disabled", true);
                }
                else
                {
                    jQuery("#msg").html("");
                    jQuery(".btn-primary").prop("disabled", false);
                }
            })
        ';
        
        $script.='
            jQuery("input[name=\'email\']").blur(function(){
                if(!this.value.match(/^(("[\w-\s]+")|([\w-]+(?:\.[\w-]+)*)|("[\w-\s]+")([\w-]+(?:\.[\w-]+)*))(@((?:[\w-]+\.)*\w[\w-]{0,66})\.([a-z]{2,6}(?:\.[a-z]{2})?)$)|(@\[?((25[0-5]\.|2[0-4][0-9]\.|1[0-9]{2}\.|[0-9]{1,2}\.))((25[0-5]|2[0-4][0-9]|1[0-9]{2}|[0-9]{1,2})\.){2}(25[0-5]|2[0-4][0-9]|1[0-9]{2}|[0-9]{1,2})\]?$)/))
                {
                    if(!jQuery("#msgemail").length)
                    {
                        jQuery("input[name=\'email\']").after("<div id=\'msgemail\'></div>");
                    }
                    jQuery("#msgemail").html("<span style=\'color:#DF0101\'>Invalid Email Format (ex. johndoe@domain.com)</span>");
                     jQuery(".btn-primary").prop("disabled", true);
                }
                else
                {
                    jQuery("#msgemail").html("");
                    jQuery(".btn-primary").prop("disabled", false);
                }
            })
        '; 
        
        $script.='
            var phoneArray = ["contactdetails[Registrant][Phone]","contactdetails[Billing][Phone]","contactdetails[Admin][Phone]","contactdetails[Tech][Phone]"];
            jQuery("input[name^=\'contactdetails\']").each(function(e){    
                  if(jQuery.inArray(this.name,phoneArray) >= 0)
                  {
                      var phoneVal = "";
                      jQuery("input[name=\'"+this.name+"\']").blur(function(){
                            var divId = this.name.replace("contactdetails[","").replace("][Phone]","");
                            var phoneVal = this.value;
                            if(!phoneVal.match(/^(\+?[0-9]{1,3})\.[0-9]+x?[0-9]*$/))
                            {
                                if(!jQuery("#msg"+divId).length)
                                {
                                    jQuery("input[name=\'"+this.name+"\']").after("<div id=\'msg"+divId+"\'></div>");
                                }
                                jQuery("#msg"+divId).html("<span style=\'color:#DF0101\'>Invalid Phone Number Format (ex. +1.4163334444 or 1.4163334444)</span>");
                                jQuery(".btn-primary").prop("disabled", true);
                                jQuery("input[value=\'Save Changes\']").prop("disabled", true);
                            }
                            else
                            {
                                jQuery("#msg"+divId).html("");
                                jQuery(".btn-primary").prop("disabled", false);
                                jQuery("input[value=\'Save Changes\']").prop("disabled", false);
                            }
                      })
                     
                  }
                
            });
            
        ';
        
        $script.='
            var emailArray = ["contactdetails[Registrant][Email]","contactdetails[Billing][Email]","contactdetails[Admin][Email]","contactdetails[Tech][Email]"];
            jQuery("input[name^=\'contactdetails\']").each(function(e){    
                  if(jQuery.inArray(this.name,emailArray) >= 0)
                  {
                      var emailVal = "";
                      jQuery("input[name=\'"+this.name+"\']").blur(function(){
                            var divIdEmail = this.name.replace("contactdetails[","").replace("]","").replace("[","").replace("]","");
                            var emailVal = this.value;
                            if(!emailVal.match(/^(("[\w-\s]+")|([\w-]+(?:\.[\w-]+)*)|("[\w-\s]+")([\w-]+(?:\.[\w-]+)*))(@((?:[\w-]+\.)*\w[\w-]{0,66})\.([a-z]{2,6}(?:\.[a-z]{2})?)$)|(@\[?((25[0-5]\.|2[0-4][0-9]\.|1[0-9]{2}\.|[0-9]{1,2}\.))((25[0-5]|2[0-4][0-9]|1[0-9]{2}|[0-9]{1,2})\.){2}(25[0-5]|2[0-4][0-9]|1[0-9]{2}|[0-9]{1,2})\]?$)/))
                            {
                                if(!jQuery("#msg"+divIdEmail).length)
                                {
                                    jQuery("input[name=\'"+this.name+"\']").after("<div id=\'msg"+divIdEmail+"\'></div>");
                                }
                                jQuery("#msg"+divIdEmail).html("<span style=\'color:#DF0101\'>Invalid Email Format (ex. johndoe@domain.com)</span>");
                                jQuery(".btn-primary").prop("disabled", true);
                                jQuery("input[value=\'Save Changes\']").prop("disabled", true);
                            }
                            else
                            {
                                jQuery("#msg"+divIdEmail).html("");
                                jQuery(".btn-primary").prop("disabled", false);
                                jQuery("input[value=\'Save Changes\']").prop("disabled", false);
                            }
                      })
                     
                  }
                
            });
            
        ';
        
        $script.='
            var faxArray = ["contactdetails[Registrant][Fax]","contactdetails[Billing][Fax]","contactdetails[Admin][Fax]","contactdetails[Tech][Fax]"];
            jQuery("input[name^=\'contactdetails\']").each(function(e){    
                  if(jQuery.inArray(this.name,faxArray) >= 0)
                  {
                      var faxVal = "";
                      jQuery("input[name=\'"+this.name+"\']").blur(function(){
                            var divIdFax = this.name.replace("contactdetails[","").replace("]","").replace("[","").replace("]","");
                            var faxVal = this.value;
                            if(!faxVal.match(/^(\+?[0-9]{1,3})\.[0-9]+x?[0-9]*$/))
                            {
                                if(!jQuery("#msg"+divIdFax).length)
                                {
                                    jQuery("input[name=\'"+this.name+"\']").after("<div id=\'msg"+divIdFax+"\'></div>");
                                }
                                jQuery("#msg"+divIdFax).html("<span style=\'color:#DF0101\'>Invalid Fax Format (ex. +1.4163334444 or 1.4163334444)</span>");
                                jQuery(".btn-primary").prop("disabled", true);
                                jQuery("input[value=\'Save Changes\']").prop("disabled", true)
                            }
                            else
                            {
                                jQuery("#msg"+divIdFax).html("");
                                jQuery(".btn-primary").prop("disabled", false);
                                jQuery("input[value=\'Save Changes\']").prop("disabled", false)
                            }
                      })
                     
                  }
                
            });
            
        ';
        /* End : To Add validations for phone and email to contact forms  */ 
        
        /* Added by BC : NG : 11-9-2014 : To Add JS validation at review & checkout page for Domain Registration  */ 
        $script.='
            jQuery("input[name=\'domaincontactphonenumber\']").blur(function(){
                if(!this.value.match(/^(\+?[0-9]{1,3})\.[0-9]+x?[0-9]*$/))
                {
                    if(!jQuery("#msgdomaincontactphonenumber").length)
                    {
                        jQuery("input[name=\'domaincontactphonenumber\']").after("<div id=\'msgdomaincontactphonenumber\'></div>");
                    }
                    jQuery("#msgdomaincontactphonenumber").html("<span style=\'color:#DF0101\'>Invalid Phone Number Format (ex. +1.4163334444 or 1.4163334444)</span>");
                     jQuery(".ordernow").prop("disabled", true);
                }
                else
                {
                    jQuery("#msgdomaincontactphonenumber").html("");
                    jQuery(".ordernow").prop("disabled", false);
                }
            })
        ';
            
        $script.='
            jQuery("input[name=\'domaincontactemail\']").blur(function(){
                if(!this.value.match(/^(("[\w-\s]+")|([\w-]+(?:\.[\w-]+)*)|("[\w-\s]+")([\w-]+(?:\.[\w-]+)*))(@((?:[\w-]+\.)*\w[\w-]{0,66})\.([a-z]{2,6}(?:\.[a-z]{2})?)$)|(@\[?((25[0-5]\.|2[0-4][0-9]\.|1[0-9]{2}\.|[0-9]{1,2}\.))((25[0-5]|2[0-4][0-9]|1[0-9]{2}|[0-9]{1,2})\.){2}(25[0-5]|2[0-4][0-9]|1[0-9]{2}|[0-9]{1,2})\]?$)/))
                {
                    if(!jQuery("#msgdomaincontactemail").length)
                    {
                        jQuery("input[name=\'domaincontactemail\']").after("<div id=\'msgdomaincontactemail\'></div>");
                    }
                    jQuery("#msgdomaincontactemail").html("<span style=\'color:#DF0101\'>Invalid Email Format (ex. johndoe@domain.com)</span>");
                    jQuery(".ordernow").prop("disabled", true);
                }
                else
                {
                    jQuery("#msgdomaincontactemail").html("");
                    jQuery(".ordernow").prop("disabled", false);
                }
            })
        '; 
        /* END : To Add JS validation at review & checkout page for Domain Registration  */  
        
    }
    
    /* Added by BC : NG : 21-8-2014 : To set role perimission for hide Registrant Verification Status (Using Role Permission) */
    if($vars['filename']=='configadminroles' && opensrspro_getSetting('DisableTemplatesChanges')!='on'){
        $command = 'getadmindetails';
        $adminuser = '';
        $values = '';
         
        $results = localAPI($command,$values,$adminuser);
        
        $admin_details = mysql_fetch_assoc(mysql_query("SELECT * FROM tbladmins WHERE id='".mysql_real_escape_string($results['adminid'])."'"));
        $query = mysql_query("SELECT permid FROM tbladminperms WHERE roleid='".$admin_details['roleid']."'");
        $row = mysql_num_rows($query);
        $permId = array();
        if($row > 0)
        {
            while($res=mysql_fetch_array($query))
            {
                array_push($permId,$res['permid']);
            }
        }
        if(in_array(999,$permId))
        {
            $script.='
                  
             var firstTD = jQuery("input[name^=\'adminperms\']:first").parent();
             firstTD.append("<input id=\'adminperms999\' checked=\'checked\' type=\'checkbox\' name=\'adminperms[999]\'><label for=\'adminperms999\'>Registrant Verification Status</label><br>");
        ';
        }
        else
        {
            $script.='
                  
             var firstTD = jQuery("input[name^=\'adminperms\']:first").parent();
             firstTD.append("<input id=\'adminperms999\' type=\'checkbox\' name=\'adminperms[999]\'><label for=\'adminperms999\'>Registrant Verification Status</label><br>");
        ';
        }
        /* END : To set role perimission for hide Registrant Verification Status (Using Role Permission) */
        
        /* Added by BC : NG : 8-10-2014 : To set role perimission for hide View Domain Notes (Using Role Permission) */
        if(in_array(9999,$permId))
        {
            $script.='
             var firstTD = jQuery("input[name^=\'adminperms\']:first").parent().next();
             firstTD.append("<input id=\'adminperms9999\' checked=\'checked\' type=\'checkbox\' name=\'adminperms[9999]\'><label for=\'adminperms9999\'>&nbsp;View Domain Notes</label><br>");
             ';
        }
        else
        {
            $script.='
             var firstTD = jQuery("input[name^=\'adminperms\']:first").parent().next();
             firstTD.append("<input id=\'adminperms9999\' type=\'checkbox\' name=\'adminperms[9999]\'><label for=\'adminperms9999\'>&nbsp;View Domain Notes</label><br>");
        ';
        }
        
        /* End : To set role perimission for hide View Domain Notes (Using Role Permission) */
    }
    
    
    $script.="
        });
        //]]>
        </script>";
    return $pre_script.$script;
    
}

add_hook('ClientAreaHeadOutput',1,'hook_opensrspro_ActivateTemplatesChangesHeadOutput');
/* Added by BC : NG : 11-8-2014 : To Add hook in WHMCS admin  */ 
add_hook('AdminAreaHeadOutput',1,'hook_opensrspro_ActivateTemplatesChangesHeadOutput');
/* End : To Add hook in WHMCS admin  */ 


?>