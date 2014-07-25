<?php
$phone=$_GET['phonenumber'];
if (!preg_match("/^([0-9]{1,3})\.[0-9]+x?[0-9]*$/i", $phone)){
echo "<span style='color:#DF0101'>Invalid Phone Number Format (ex. 1.4163334444)</span>";
}else{
echo "";} 
?>
