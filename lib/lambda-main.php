<?php
use \utils\HttpRequestRouter;

/*
ALB payload:

{
    "requestContext": {
        "elb": {
            "targetGroupArn": "arn:aws:elasticloadbalancing:region:123456789012:targetgroup/my-target-group/6d0ecf831eec9f09"
        }
    },
    "httpMethod": "GET",
    "path": "/",
    "queryStringParameters": {parameters},
    "headers": {
        "accept": "text/html,application/xhtml+xml",
        "accept-language": "en-US,en;q=0.8",
        "content-type": "text/plain",
        "cookie": "cookies",
        "host": "lambda-846800462-us-east-2.elb.amazonaws.com",
        "user-agent": "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6)",
        "x-amzn-trace-id": "Root=1-5bdb40ca-556d8b0c50dc66f0511bf520",
        "x-forwarded-for": "72.21.198.66",
        "x-forwarded-port": "443",
        "x-forwarded-proto": "https"
    },
    "isBase64Encoded": false,
    "body": "request_body"
}


Response:

{
    "isBase64Encoded": false,
    "statusCode": 200,
    "statusDescription": "200 OK",
    "headers": {
        "Set-cookie": "cookies",
        "Content-Type": "application/json"
    },
    "body": "Hello from Lambda (optional)"
}
 */


/*
 * Techscore lib/scripts input:
 *
 * {
 *   "version": "TS/1.0",
 *   "scriptName": "MigrateDB",
 *   "args": ["-v"],
 *   "settings": {
 *     "noUser": false
 *   }
 * }
 *
 */

// load the layers
require_once('/opt/php-runtime/LambdaContext.inc.php');

/**
 * Entry point for application when executing in an AWS Lambda context.
 *
 * @param event ALB event
 */
function handler(array $event, LambdaContext $ctx): array {
  error_log("Request: " . json_encode($event));

  if (isset($event['version']) && $event['version'] === 'TS/1.0') {
    setupCliEnvironment($event);

    require_once(__DIR__ . '/conf.php');
    $classname = '\\scripts\\' . $event['scriptName'];
    $SCRIPT = new $classname();
    $SCRIPT->runCli(array_merge([$event['scriptName']], $event['args']));

    return [];
  }

  // Web server execution
  setupWebEnvironment($event);

  // load the application
  require_once(__DIR__ . '/conf.php');

  $response = HttpRequestRouter::routeRequest();

  $responseHeaders = array_merge(
    ["Content-Type" => "text/html; charset=UTF-8"],
    $response->headers);

  // TODO: deal with cookies!

  error_log("Response: " . json_encode([
    "statusCode" => $response->statusCode,
    "headers" => $responseHeaders,
    "bodySize" => strlen($response->body),
  ]));

  return [
    "statusCode" => $response->statusCode,
    "statusDescription" => $response->statusDescription,
    "isBase64Encoded" => false,
    "headers" => $responseHeaders,
    "body" => $response->body,
  ];
}

function setupCliEnvironment(array $event): void {
  $_SERVER['PHP_SAPI_OVERRIDE'] = 'cli';
  if (in_array('settings', $event)
      && in_array('noUser', $event['settings'])
      && $event['settings']['noUser']) {

    define('NO_USER', 1);
    error_log("Proceeding with no user");
  }
}

function setupWebEnvironment(array $event): void {
  $_SERVER['PHP_SAPI_OVERRIDE'] = 'lambda';

  $_SERVER['HTTP_ACCEPT'] = $event['headers']['accept'];
  $_SERVER['HTTP_HOST'] = $event['headers']['host'];
  $_SERVER['HTTP_REFERER'] = $event['headers']['http-referer'] ?? null;
  $_SERVER['HTTP_USER_AGENT'] = $event['headers']['user-agent'] ?? 'unknown';
  $_SERVER['HTTPS'] = 'on'; // handled by ALB/CloudFront
  // TODO: determine usage of $_SERVER['HTTP_API']

  $_SERVER['REQUEST_METHOD'] = $event['httpMethod'];
  $_SERVER['REQUEST_URI'] = $event['path'];

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $_GET = $event['queryStringParameters'] ?? [];
    $_REQUEST = $_GET;
  } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // TODO: figure out how to encode POST + FILES
  }
}
