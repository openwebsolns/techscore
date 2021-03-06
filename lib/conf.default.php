<?php
/**
 * Local settings. Copy this file to 'conf.local.php' and edit that
 * file with the information pertinent to the production site. While
 * this file is version controled, 'conf.local.php' is not.
 *
 */

// General settings for schema and system updates
//Conf::$DB_ROOT_USER = 'root';
//Conf::$LOG_ROOT = '/var/log/httpd';

Conf::$HTTP_TEMPLATE = Conf::HTTP_TEMPLATE_VHOST_SSL;
// Conf::$HTTP_TEMPLATE_PARAMS = array(
//   Conf::HTTP_TEMPLATE_PARAM_CERTPATH => '/etc/httpd/certs/ts2.crt',
//   Conf::HTTP_TEMPLATE_PARAM_CERTKEYPATH => '/etc/httpd/certs/ts2.key',
//   Conf::HTTP_TEMPLATE_PARAM_CERTCHAINPATH => null,
// );

//Conf::$CRON_FREQ = '*/1 * * * *';
//Conf::$CRON_SCHOOL_FREQ = '7,27,47 * * * *';
//Conf::$CRON_SEASON_FREQ = '*/5 * * * *';


Conf::$HOME = 'ts.example.com';
Conf::$PUB_HOME = 'scores.example.com';
Conf::$ADMIN_MAIL = 'admin@localhost';

// MySQL connection
Conf::$SQL_HOST = 'localhost';
Conf::$SQL_USER = 'techscore';
Conf::$SQL_PASS = '';
Conf::$SQL_DB   = 'techscore';
Conf::$SQL_PORT = null;

Conf::$PASSWORD_SALT = 'Enter password salt here. Longer is better.';

Conf::$WRITERS = array('\writers\LocalHtmlWriter.php');

// Set to the ID of the users that can log in
// Conf::$DEBUG_USERS = array('admin@localhost');

Conf::$LOCK_FILENAME = 'ts-pub.lock';

Conf::$EMAIL_SENDER = '\mail\senders\PhpMailSender';
Conf::$EMAIL_SENDER_PARAMS = array();

Conf::$ELIGIBILITY_CALCULATOR = null;
