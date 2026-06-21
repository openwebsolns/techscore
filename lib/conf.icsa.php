<?php
use \aws\auth\InstanceMetadataAwsCredsProvider;
use \mail\bouncing\SqsBounceHandler;
use \mail\senders\SesEmailSender;

/**
 * Local settings. Copy this file to 'conf.local.php' and edit that
 * file with the information pertinent to the production site. While
 * this file is version controled, 'conf.local.php' is not.
 *
 */

// General settings for schema and system updates
Conf::$DB_ROOT_USER = 'root';
Conf::$LOG_ROOT = '/var/log/httpd';
Conf::$HTTP_TEMPLATE = Conf::HTTP_TEMPLATE_CODEDEPLOY;
Conf::$HTTP_TEMPLATE_PARAMS = array(
  Conf::HTTP_TEMPLATE_PARAM_DIRECTORY => '/srv/techscore',
  Conf::HTTP_TEMPLATE_PARAM_LOGROOT => '/var/log/httpd',
);
Conf::$CRON_FREQ = '*/1 * * * *';
Conf::$CRON_SEASON_FREQ = '*/5 * * * *';
 
// The following constants are used to identify the TechScore program
// and the web environment. Take a look at the class Conf in conf.php
Conf::$HOME = "ts.collegesailing.org";
Conf::$PUB_HOME = "scores.collegesailing.org";
Conf::$ADMIN_MAIL = "bugs@openweb-solutions.net";

// MySQL connection
Conf::$SQL_HOST = 'dd1iqxjwfp0ow7v.cpapenimzwxf.us-west-2.rds.amazonaws.com';
Conf::$SQL_USER = 'techscore';
Conf::$SQL_PASS = 'csail04sailor2';
Conf::$SQL_DB   = 'techscore';

Conf::$PASSWORD_SALT = '05bf0d6a6153bf0910ec9c5d0d3d7096a68efe6febd4c912691d97d510def6d13ea06a7a37ac774019bc63ac4b335e926fedfd21991673f26f714450a81c0fee';

Conf::$ERROR_HANDLER = '\error\PrintHandler';
//Conf::$LOG_QUERIES = '/tmp/queries.log';

// Define any other settings that are local to the production site
// such as default date_timezone, etc.

//Conf::$DEBUG_USERS = array('dayan@openweb-solutions.net');

Conf::$WRITERS = array('\writers\S3WriterViaCli');

Conf::$EMAIL_SENDER = '\mail\senders\SesEmailSender';
Conf::$EMAIL_SENDER_PARAMS = array(
  SesEmailSender::PARAM_REGION => 'us-west-2',
  SesEmailSender::PARAM_AWS_CREDS_PROVIDER => new InstanceMetadataAwsCredsProvider('app-WebServerRole-J1CL8FO0LQ4N'),
);

Conf::$EMAIL_BOUNCE_HANDLER = new SqsBounceHandler(array(
  SqsBounceHandler::PARAM_REGION => 'us-west-2',
  SqsBounceHandler::PARAM_QUEUE_URL => 'https://sqs.us-west-2.amazonaws.com/701917750080/app-EmailNotificationsQueue-Ln182szjHqVR',
  SqsBounceHandler::PARAM_AWS_CREDS_PROVIDER => new InstanceMetadataAwsCredsProvider('app-WebServerRole-J1CL8FO0LQ4N'),
));


Conf::$ELIGIBILITY_CALCULATOR = '\eligibility\IcsaEligibilityCalculator';
