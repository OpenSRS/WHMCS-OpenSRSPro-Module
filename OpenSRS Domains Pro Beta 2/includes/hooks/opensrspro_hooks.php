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
            jQuery(document).ready(function(){
    ';
    
    if(($vars['filename']=='clientarea' || $vars['filename']=='register' || $vars['filename']=='cart') && opensrspro_getSetting('DisableTemplatesChanges')!='on'){
        
        $pre_script.='
            <script type="text/javascript" src="includes/jscript/validate.js"></script>
        ';
        $script.='
            jQuery("input[name=\'phonenumber\']").keyup(function(){
                ValidatePhone(this.value);
            })
            .after("<div id=\'msg\'></div>")
            .closest("form").find("input[type=\'submit\']").attr("id","btnSubmit");
        ';

    }
    $script.="
        });
        </script>";
    return $pre_script.$script;
    
}

add_hook('ClientAreaHeadOutput',1,'hook_opensrspro_ActivateTemplatesChangesHeadOutput');



?>