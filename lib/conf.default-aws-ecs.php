<?php
/*
 * Local default template for production installations on AWS using ECS.
 */

use \aws\auth\EcsTaskAwsCredsProvider;
use \mail\bouncing\SqsBounceHandler;
use \mail\senders\SesEmailSender;
use \metrics\AwsMetricPublisher;
use \writers\S3Writer;

// reuse
$credsProvider = new EcsTaskAwsCredsProvider();

Conf::$WRITER = '\writers\S3Writer';
Conf::$WRITER_PARAMS = array(
  S3Writer::PARAM_BUCKET => $_SERVER['SCORES_BUCKET'],
  S3Writer::PARAM_AWS_CREDS_PROVIDER => $credsProvider,
  S3Writer::PARAM_REGION => $_SERVER['AWS_REGION'],
  S3Writer::PARAM_STRIP_INDEX_HTML => true,
);

Conf::$EMAIL_SENDER = '\mail\senders\SesEmailSender';
Conf::$EMAIL_SENDER_PARAMS = array(
  SesEmailSender::PARAM_REGION => $_SERVER['AWS_REGION'],
  SesEmailSender::PARAM_CONFIGURATION_SET_NAME => $_SERVER['SES_CONFIGURATION_SET_NAME'],
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
