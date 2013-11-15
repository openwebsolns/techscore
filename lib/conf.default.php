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
//Conf::$HTTP_CERTPATH = '/etc/httpd/certs/ts2.crt';
//Conf::$HTTP_CERTKEYPATH = '/etc/httpd/certs/ts2.key';
//Conf::$HTTP_CERTCHAINPATH = null;
//Conf::$CRON_FREQ = '*/1 * * * *';
//Conf::$CRON_SCHOOL_FREQ = '7,27,47 * * * *';
//Conf::$CRON_SEASON_FREQ = '*/5 * * * *';


Conf::$HOME = 'ts.collegesailing.org';
Conf::$PUB_HOME = 'scores.collegesailing.org';
// Conf::$HELP_HOME = 'www.collegesailing.org/ts-help';
Conf::$ADMIN_MAIL = 'admin@localhost';
Conf::$TS_FROM_MAIL = Conf::$ADMIN_MAIL;

// MySQL connection
Conf::$SQL_HOST = 'localhost';
Conf::$SQL_USER = 'ts2';
conf::$SQL_PASS = '';
conf::$SQL_DB   = 'ts2';

Conf::$PASSWORD_SALT = 'Enter password salt here. Longer is better.';

Conf::$WRITERS = array('LocalHtmlWriter.php');

// API updates
// Conf::$SAILOR_API_URL = '';
// Conf::$COACH_API_URL = '';
// Conf::$SCHOOL_API_URL = '';

// Set to the ID of the users that can log in
// Conf::$DEBUG_USERS = array('admin@localhost');

// Set the allowed regatta types and scoring options
//Conf::$REGATTA_SCORING_BLACKLIST = array('standard', 'combined');

Conf::$LOCK_FILENAME = 'ts-pub.lock';
?>
