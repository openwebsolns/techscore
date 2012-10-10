<?php
/**
 * REST API for serializing files
 *
 * @author Dayan Paez
 * @created 2012-10-10
 */

function doError($mes, $code = 400) {
  header('Content-Type: text/plain');
  header(sprintf('HTTP/1.1 %s Bad request', $code));
  echo $mes;
  exit;
}

// ------------------------------------------------------------
// Validation
// ------------------------------------------------------------
// Validate method
if (!isset($_SERVER['REQUEST_METHOD']) || !in_array($_SERVER['REQUEST_METHOD'], array('PUT', 'DELETE')))
  doError("Invalid method");

$headers = array();
foreach (apache_request_headers() as $key => $val)
  $headers[strtolower($key)] = $val;

// Validate headers
if (!isset($headers['authorization'])) doError("Missing Authorization header");
if (!isset($headers['date']))          doError("Missing Date header");
if (($date = date_create($headers['date'])) === false)
  doError("Invalid value for Date: " . $headers['date']);

// Check for skewed-ness
$now = new DateTime();
if ($now->diff($date, true) > new DateInterval('P0DT5M'))
  doError("Message too skewed.");

// Validate URI
$uri = $_SERVER['REQUEST_URI'];
if (strlen($uri) < 2)
  doError("Invalid request URI");

// Authorize based on signature
$tokens = explode(' ', $headers['authorization']);
if (count($tokens) != 2)
  doError("Invalid authorization header.", 403);
$parts = explode(':', $tokens[1]);
if (count($parts) != 2)
  doError("Malformed authorization header.");

// Extra headers for put request
$md5 = "";
$type = "";
if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
  if (!isset($headers['content-md5']))  doError("Missing Content-MD5 header");
  if (!isset($headers['content-type']))
    doError("Missing Content-Type header");
  $md5 = $headers['content-md5'];
  $type = $headers['content-type'];
}

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/lib/writers/S3LocalWriter.php');
$P = new S3LocalWriter();
$tokens = explode('.', $_SERVER['HTTP_HOST']);
$P->bucket = array_shift($tokens);
$P->host_base = implode('.', $tokens);

$signature = $P->sign($_SERVER['REQUEST_METHOD'], $md5, $type, $headers['date'], $uri);
if ($parts[0] != $P->access_key || $parts[1] != $signature)
  doError("Bad access key-signature pairing.", 403);

// Get the input, if necessary
$contents = null;
if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
  $contents = file_get_contents('php://input');
  $md5 = base64_encode(md5($contents, true));
  if ($md5 != $headers['content-md5'])
    doError("MD5 sum does not match header value");
}

// ------------------------------------------------------------
// Do the actual verb by delegating to LocalHtmlWriter
// ------------------------------------------------------------
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/lib/writers/LocalHtmlWriter.php');
$P = new LocalHtmlWriter();
try {
  if ($_SERVER['REQUEST_METHOD'] == 'PUT')
    $P->write($uri, $contents);
  else
    $P->remove($uri);
}
catch (TSWriterException $e) {
  doError($e->getMessage(), 500);
}
?>