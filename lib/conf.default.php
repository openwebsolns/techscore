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
//Conf::$CRON_FREQ = '*/5';
//Conf::$CRON_BUP = '3 5 1,8,15,22 * *';
//Conf::$DB_BUP_USER = 'backup';

// The following constants are used to identify the TechScore program
// and the web environment. Take a look at the class Conf in conf.php
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

// LOG FILES
Conf::$LOG_UPDATE = realpath(dirname(__FILE__).'/../log/update.log');
Conf::$LOG_SEASON = realpath(dirname(__FILE__).'/../log/season.log');
Conf::$LOG_SCHOOL = realpath(dirname(__FILE__).'/../log/school.log');
Conf::$LOG_FRONT =  realpath(dirname(__FILE__).'/../log/front.log');

// API updates
// Conf::$SAILOR_API_URL = '';
// Conf::$COACH_API_URL = '';
// Conf::$SCHOOL_API_URL = '';

// Set to the ID of the users that can log in
// Conf::$DEBUG_USERS = array('admin@localhost');

// Define any other settings that are local to the production site
// such as default date_timezone, etc.

// Uncomment below to disallow registering for accounts
//Conf::$ALLOW_REGISTER = false;

// Set the allowed regatta types and scoring options
//Conf::$REGATTA_TYPE_BLACKLIST = array('conference');
//Conf::$REGATTA_SCORING_BLACKLIST = array('standard', 'combined');
?>