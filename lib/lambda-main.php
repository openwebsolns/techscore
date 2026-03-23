<?php
/*
Lambda Function URL Event:

{
  "version": "2.0",
  "routeKey": "$default",
  "rawPath": "/my/path",
  "rawQueryString": "parameter1=value1&parameter1=value2&parameter2=value",
  "cookies": [
    "cookie1",
    "cookie2"
  ],
  "headers": {
    "header1": "value1",
    "header2": "value1,value2"
  },
  "queryStringParameters": {
    "parameter1": "value1,value2",
    "parameter2": "value"
  },
  "requestContext": {
    "accountId": "123456789012",
    "apiId": "<urlid>",
    "authentication": null,
    "authorizer": {
        "iam": {
                "accessKey": "AKIA...",
                "accountId": "111122223333",
                "callerId": "AIDA...",
                "cognitoIdentity": null,
                "principalOrgId": null,
                "userArn": "arn:aws:iam::111122223333:user/example-user",
                "userId": "AIDA..."
        }
    },
    "domainName": "<url-id>.lambda-url.us-west-2.on.aws",
    "domainPrefix": "<url-id>",
    "http": {
      "method": "POST",
      "path": "/my/path",
      "protocol": "HTTP/1.1",
      "sourceIp": "123.123.123.123",
      "userAgent": "agent"
    },
    "requestId": "id",
    "routeKey": "$default",
    "stage": "$default",
    "time": "12/Mar/2020:19:03:58 +0000",
    "timeEpoch": 1583348638390
  },
  "body": "Hello from client!",
  "pathParameters": null,
  "isBase64Encoded": false,
  "stageVariables": null
}


Response:

{
   "statusCode": 201,
    "headers": {
        "Content-Type": "application/json",
        "My-Custom-Header": "Custom Value"
    },
    "body": "{ \"message\": \"Hello, world!\" }",
    "cookies": [
        "Cookie_1=Value1; Expires=21 Oct 2021 07:48 GMT",
        "Cookie_2=Value2; Max-Age=78000"
    ],
    "isBase64Encoded": false
}
 */

// load the layers
require_once('/opt/php-runtime/LambdaContext.inc.php');

/**
 * Entry point for application when executing in an AWS Lambda context.
 *
 * @param event ALB event
 */
function handler(array $event, LambdaContext $ctx): array {
    // Setup environment based on request
    setupEnvironment($event);

    // load the application
    require_once(__DIR__ . '/conf.php');

    // FIXME: actual routing, eventually
    require_once('users/LoginPage.php');
    $PAGE = new LoginPage();
    $body = $PAGE->createPage($_GET)->toXML();

    return [
        "statusCode" => 200,
        "statusDescription" => "200 OK",
        "isBase64Encoded" => false,
        "headers" => [
            "Content-Type" => "text/html",
        ],
        "body" => $body,
    ];
}

function setupEnvironment(array $event): void {
    $_SERVER['PHP_SAPI_OVERRIDE'] = 'lambda';

    $_SERVER['HTTP_ACCEPT'] = $event['headers']['accept'];
    $_SERVER['HTTP_HOST'] = $event['headers']['host'];
    $_SERVER['HTTP_REFERER'] = $event['headers']['http-referer'] ?? null;
    $_SERVER['HTTP_USER_AGENT'] = $event['headers']['user-agent'];
    $_SERVER['HTTPS'] = 'on'; // handled by ALB/CloudFront
    // TODO: determine usage of $_SERVER['HTTP_API']

    $_SERVER['REQUEST_METHOD'] = $event['requestContext']['http']['method'];
    $_SERVER['REQUEST_URI'] = $event['requestContext']['http']['path'];

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $_GET = $event['queryStringParameters'];
        $_REQUEST = $_GET;
    } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // TODO: figure out how to encode POST + FILES
    }

}
