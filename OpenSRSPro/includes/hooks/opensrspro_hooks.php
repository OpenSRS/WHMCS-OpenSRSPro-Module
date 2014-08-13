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
    $script='
        <script type="text/javascript">
        //<![CDATA[
            jQuery(document).ready(function(){
    ';
    
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