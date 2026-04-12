<?php
/*
 * Local default template for production installations on AWS using ECS.
 */

use \aws\auth\BasicAwsCredsProvider;
use \mail\bouncing\SqsBounceHandler;
use \mail\senders\SesEmailSender;
use \metrics\AwsMetricPublisher;
use \writers\S3Writer;

function getSecretFromLambdaExtension($value, $nestedKey = null) {
    if (strpos($value, 'aws-secret:') !== 0) {
        return $value;
    }

    $secretName = substr($value, strlen('aws-secret:'));

    // https://docs.aws.amazon.com/lambda/latest/dg/with-secrets-manager.html#lambda-secrets-manager-extension-approach
    $opts = array('http' => array('method' => 'GET', 'header' => sprintf("X-Aws-Parameters-Secrets-Token: %s", $_SERVER['AWS_SESSION_TOKEN'])));
    $context = stream_context_create($opts);
    $url = "http://localhost:2773/secretsmanager/get?secretId=${secretName}";
    $response = file_get_contents($url, false, $context);
    $decoded = json_decode($response, true /* =associative array */);
    if ($decoded === null) {
        throw new InvalidArgumentException("Unable to parse response from SecretsManager extension");
    }

    $secretValue = $decoded['SecretString'];
    if ($nestedKey === null) {
        // Expected plain text; return value as-is
        return $secretValue;
    }

    $decoded = json_decode($secretValue, true);
    if ($decoded === null) {
        throw new InvalidArgumentException("Unable to parse secret value as a JSON");
    }

    return $decoded[$nestedKey];
}

// Unused in Lambda
Conf::$HTTP_TEMPLATE = Conf::HTTP_TEMPLATE_DOCKER;

Conf::$HOME = $_SERVER['APP_HOME'];
Conf::$PUB_HOME = $_SERVER['PUB_HOME'];
Conf::$ADMIN_MAIL = $_SERVER['ADMIN_MAIL'];

// MySQL connection
Conf::$SQL_PORT = $_SERVER['SQL_PORT'];
Conf::$SQL_HOST = $_SERVER['SQL_HOST'];
Conf::$SQL_USER = $_SERVER['SQL_USER'];
Conf::$SQL_PASS = getSecretFromLambdaExtension($_SERVER['SQL_PASS'], 'password');
Conf::$SQL_DB   = $_SERVER['SQL_DB'];

// Reuse single user
Conf::$DB_ROOT_USER = Conf::$SQL_USER;
Conf::$DB_ROOT_PASS = Conf::$SQL_PASS;

Conf::$PASSWORD_SALT = getSecretFromLambdaExtension($_SERVER['PASSWORD_SALT']);

// reuse
$credsProvider = new BasicAwsCredsProvider(
  $_SERVER['AWS_ACCESS_KEY_ID'],
  $_SERVER['AWS_SECRET_ACCESS_KEY'],
  $_SERVER['AWS_SESSION_TOKEN']
);

Conf::$WRITER = '\writers\S3Writer';
Conf::$WRITER_PARAMS = array(
  S3Writer::PARAM_BUCKET => $_SERVER['SCORES_BUCKET'],
  S3Writer::PARAM_AWS_CREDS_PROVIDER => $credsProvider,
);

Conf::$EMAIL_SENDER = '\mail\senders\SesEmailSender';
Conf::$EMAIL_SENDER_PARAMS = array(
  SesEmailSender::PARAM_REGION => $_SERVER['AWS_REGION'],
  SesEmailSender::PARAM_AWS_CREDS_PROVIDER => $credsProvider,
);

if (in_array('SQS_BOUNCE_QUEUE_URL', $_SERVER)) {
  Conf::$EMAIL_BOUNCE_HANDLER = new SqsBounceHandler(array(
    SqsBounceHandler::PARAM_REGION => $_SERVER['AWS_REGION'],
    SqsBounceHandler::PARAM_QUEUE_URL => $_SERVER['SQS_BOUNCE_QUEUE_URL'],
    SqsBounceHandler::PARAM_AWS_CREDS_PROVIDER => $credsProvider,
  ));
}

Conf::$METRIC_PUBLISHER = '\metrics\AwsMetricPublisher';
Conf::$METRIC_PUBLISHER_PARAMS = array(
  AwsMetricPublisher::PARAM_REGION => $_SERVER['AWS_REGION'],
  AwsMetricPublisher::PARAM_AWS_CREDS_PROVIDER => $credsProvider,
);

// TODO: create Lambda-compatible error handler wrapper
Conf::$ERROR_HANDLER = '\error\CLIHandler';
