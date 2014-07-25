function ValidatePhone(phonenumber)
{
        var httpxml;
        try
        {
                // Firefox, Opera 8.0+, Safari
                httpxml=new XMLHttpRequest();
        }
        catch (e)
        {
                // Internet Explorer
                try
                {
                        httpxml=new ActiveXObject("Msxml2.XMLHTTP");
                }
                catch (e)
                {
                        try
                        {
                                httpxml=new ActiveXObject("Microsoft.XMLHTTP");
                        }
                        catch (e)
                        {
                                alert("Your browser does not support AJAX!");
                                return false;
                        }
                }
        }
        function stateck()
        {
                if(httpxml.readyState==4)
                {
                        document.getElementById("msg").innerHTML=httpxml.responseText;
			buttonCheck(httpxml.responseText);
                }
        }

        var url="includes/phoneCheck.php";
        url=url+"?phonenumber="+phonenumber;
        url=url+"&sid="+Math.random();
        httpxml.onreadystatechange=stateck;
        httpxml.open("GET",url,true);
        httpxml.send(null);
}

function buttonCheck(check)
{
	if (check!="") {
		document.getElementById("btnSubmit").disabled =true;
	} else {
		document.getElementById("btnSubmit").disabled =false;
	}
}
