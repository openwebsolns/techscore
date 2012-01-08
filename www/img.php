<?php
/**
 * Fetches the burgees
 *
 * @author Dayan Paez
 * @version 2010-07-14
 */

require_once('conf.php');

/**
 * Issues the page 404 not found error and exits
 *
 */
function header_404() {
  header("HTTP/1.0 404 Not Found");
  exit;
}

if (empty($_GET['school'])) {
  header_404();
}

$school = DB::getSchool(addslashes($_GET['school']));
if ($school == null || $school->burgee === null)
  header_404();

// Cache headings
header("Cache-Control: public");
header("Pragma: public");
header("Content-Type: image/png");
header("Expires: Sun, 21 Jul 2030 14:08:53 -0400");
header(sprintf("Last-Modified: %s", $school->burgee->last_updated->format('r')));

error_log(sprintf("Request: %s\t%s\t%s\t%s\n", $school->id, $_SERVER['REMOTE_ADDR'], date('r'),
		  $school->burgee->last_updated->format('r')),
	  3, "/tmp/burgee_requests.txt");

echo base64_decode($school->burgee->filedata);
?>