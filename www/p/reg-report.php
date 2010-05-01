<?php
  // View reports, and such
$whitelist = array("18.251.3.92",
		   "18.181.1.168");
if (!in_array($_SERVER['REMOTE_ADDR'], $whitelist))
  exit(1);

if (!isset($_REQUEST['reg']) ||
    !is_numeric($_REQUEST['reg']))
  exit(1);

require_once "/var/local/ts/_bg.php";
require_once "/var/local/ts/_elements.php";

session_start();
makeDBConnection();
$id = mysql_real_escape_string((int)($_REQUEST['reg']));
if (!updateRegattaSession($id))
  exit(2);
if ($_SESSION['REG']['type'] == "personal")
  exit(4);

$current = getReportsDict();
if (count($current) > 0)
  $chosen_report = array_shift(array_keys($current));

// View existing reports
if (count($current) == 0) {
  $p = new Portlet("No reports yet");
  $para = 'No reports have been created for this regatta.';
  $p->addChild(new Para($para));
}
else {
  $p = getReportDiv($chosen_report);
}

echo $p->toHTML(6);

?>