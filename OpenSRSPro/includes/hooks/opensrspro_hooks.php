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
    
    if(($vars['filename']=='clientarea' || $vars['filename']=='register' || $vars['filename']=='cart') && opensrspro_getSetting('DisableTemplatesChanges')!='on'){
        
        $pre_script.='
            <script type="text/javascript" src="includes/jscript/validate.js"></script>
        ';
        $script.='
            jQuery("input[name=\'phonenumber\']").keyup(function(){
                if(!this.value.match(/^([0-9]{1,3})\.[0-9]+x?[0-9]*$/))
                {
                    if(!jQuery("#msg").length)
                    {
                        jQuery("input[name=\'phonenumber\']").after("<div id=\'msg\'></div>");
                    }
                    jQuery("#msg").html("<span style=\'color:#DF0101\'>Invalid Phone Number Format (ex. 1.4163334444)</span>");
                     jQuery(".btn-primary").prop("disabled", true);
                }
                else
                {
                    jQuery("#msg").html("");
                    jQuery(".btn-primary").prop("disabled", false);
                }
            })
        ';
        
        /* Added by BC : NG : 9-8-2014 : To Add validations for phone and email to contact forms  */ 
        
        $script.='
           /*jQuery("input[name=\'contactdetails[Registrant][Phone]\']").keyup(function(){
                if(!this.value.match("/^([0-9]{1,3})\.[0-9]+x?[0-9]*$/"))
                {
                    if(!jQuery("#msgRegistrant").length)
                    {
                        jQuery("input[name=\'contactdetails[Registrant][Phone]\']").after("<div id=\'msgRegistrant\'></div>");
                    }
                    jQuery("#msgRegistrant").html("<span style=\'color:#DF0101\'>Invalid Phone Number Format (ex. 1.4163334444)</span>");
                    jQuery(".btn-primary").prop("disabled", true);
                }
                else
                {
                    jQuery("#msgRegistrant").html("");
                    jQuery(".btn-primary").prop("disabled", false);
                }
            })
            
            jQuery("input[name=\'contactdetails[Billing][Phone]\']").keyup(function(){
                if(!this.value.match("^([0-9]{1,3})\.[0-9]+x?[0-9]*$"))
                {
                    if(!jQuery("#msgBilling").length)
                    {
                        jQuery("input[name=\'contactdetails[Billing][Phone]\']").after("<div id=\'msgBilling\'></div>");
                    }
                    jQuery("#msgBilling").html("<span style=\'color:#DF0101\'>Invalid Phone Number Format (ex. 1.4163334444)</span>");
                    jQuery(".btn-primary").prop("disabled", true);
                }
                else
                {
                    jQuery("#msgBilling").html("");
                    jQuery(".btn-primary").prop("disabled", false);
                }
            })
            
            jQuery("input[name=\'contactdetails[Admin][Phone]\']").keyup(function(){
                if(!this.value.match("^([0-9]{1,3})\.[0-9]+x?[0-9]*$"))
                {
                    if(!jQuery("#msgAdmin").length)
                    {
                        jQuery("input[name=\'contactdetails[Admin][Phone]\']").after("<div id=\'msgAdmin\'></div>");
                    }
                    jQuery("#msgAdmin").html("<span style=\'color:#DF0101\'>Invalid Phone Number Format (ex. 1.4163334444)</span>");
                    jQuery(".btn-primary").prop("disabled", true);
                }
                else
                {
                    jQuery("#msgAdmin").html("");
                    jQuery(".btn-primary").prop("disabled", false);
                }
            })

            jQuery("input[name=\'contactdetails[Tech][Phone]\']").keyup(function(){
                if(!this.value.match(^([0-9]{1,3})\.[0-9]+x?[0-9]*$"))
                {
                    if(!jQuery("#msgTech").length)
                    {
                        jQuery("input[name=\'contactdetails[Tech][Phone]\']").after("<div id=\'msgTech\'></div>");
                    }
                    jQuery("#msgTech").html("<span style=\'color:#DF0101\'>Invalid Phone Number Format (ex. 1.4163334444)</span>");
                    jQuery(".btn-primary").prop("disabled", true);
                }
                else
                {
                    jQuery("#msgTech").html("");
                    jQuery(".btn-primary").prop("disabled", false);
                }
            })*/
            
            var phoneArray = ["contactdetails[Registrant][Phone]","contactdetails[Billing][Phone]","contactdetails[Admin][Phone]","contactdetails[Tech][Phone]"];
            jQuery("input[name^=\'contactdetails\']").each(function(e){    
                  if(jQuery.inArray(this.name,phoneArray) >= 0)
                  {
                      var phoneVal = "";
                      jQuery("input[name=\'"+this.name+"\']").keyup(function(){
                            var divId = this.name.replace("contactdetails[","").replace("][Phone]","");
                            var phoneVal = this.value;
                            if(!phoneVal.match(/^([0-9]{1,3})\.[0-9]+x?[0-9]*$/))
                            {
                                if(!jQuery("#msg"+divId).length)
                                {
                                    jQuery("input[name=\'"+this.name+"\']").after("<div id=\'msg"+divId+"\'></div>");
                                }
                                jQuery("#msg"+divId).html("<span style=\'color:#DF0101\'>Invalid Phone Number Format (ex. 1.4163334444)</span>");
                                jQuery(".btn-primary").prop("disabled", true);
                            }
                            else
                            {
                                jQuery("#msg"+divId).html("");
                                jQuery(".btn-primary").prop("disabled", false);
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



?>