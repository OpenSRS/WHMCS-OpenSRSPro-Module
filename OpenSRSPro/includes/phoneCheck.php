<?
$phone=$_GET['phonenumber'];
if (!eregi("^([0-9]{1,3})\.[0-9]+x?[0-9]*$", $phone)){
echo "<font color=red>Invalid Phone Number Format (ex. 1.4163334444)</font>";
}else{
echo "";} 
?>
