<?php
/*
 * Local default template for production installations on AWS using ECS.
 */

use \aws\auth\EcsTaskAwsCredsProvider;
use \mail\bouncing\SqsBounceHandler;
use \mail\senders\SesEmailSender;
use \writers\S3Writer;

Conf::$HTTP_TEMPLATE = Conf::HTTP_TEMPLATE_DOCKER;

Conf::$HOME = $_SERVER['APP_HOME'];
Conf::$PUB_HOME = $_SERVER['PUB_HOME'];
Conf::$ADMIN_MAIL = $_SERVER['ADMIN_MAIL'];

// MySQL connection
Conf::$SQL_PORT = $_SERVER['SQL_PORT'];
Conf::$SQL_HOST = $_SERVER['SQL_HOST'];
Conf::$SQL_USER = $_SERVER['SQL_USER'];
Conf::$SQL_PASS = $_SERVER['SQL_PASS'];
Conf::$SQL_DB   = $_SERVER['SQL_DB'];

Conf::$DB_ROOT_USER = $_SERVER['DB_ROOT_USER'];
Conf::$DB_ROOT_PASS = $_SERVER['DB_ROOT_PASS'];

Conf::$PASSWORD_SALT = $_SERVER['PASSWORD_SALT'];

// reuse
$credsProvider = new EcsTaskAwsCredsProvider();

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
