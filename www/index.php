<?php
use \utils\HttpRequestRouter;

/**
 * Gateway to the program TechScore. Manage all session information
 * and direct traffic.
 *
 * @author Dayan Paez
 * @version 2.0
 * @created 2009-10-16
 */

require_once('conf.php');

$response = HttpRequestRouter::routeRequest();

header("HTTP/1.1 {$response->statusCode} {$response->statusDescription}");
foreach ($response->headers as $headerKey => $headerValue) {
  header("${headerKey}: ${headerValue}");
}

echo $response->body;
