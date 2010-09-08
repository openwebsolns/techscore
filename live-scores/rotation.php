<?php
/**
 * TEST ONLY, do NOT include in LIVE VERSION
 *
 */

require_once('conf.php');

if (empty($_GET['d'])) exit;
try {  $R = new Regatta((int)$_GET['d']); }
catch (Exception $e) { exit; }

$P = new ReportMaker($R);
echo $P->getRotationPage();
?>
