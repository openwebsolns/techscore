<?php
/**
 * Local settings. Copy this file to 'conf.local.php' and edit that
 * file with the information pertinent to the production site. While
 * this file is version controled, 'conf.local.php' is not.
 *
 */

// The following constants are used to identify the TechScore program
// and the web environment. Take a look at the class Conf in conf.php
Conf::$HOME = sprintf("http://%s", $_SERVER['HTTP_HOST']);
Conf::$PUB_HOME = sprintf("http://scores.%s", $_SERVER['HTTP_HOST']);
Conf::$HELP_HOME = sprintf("http://%s/ts-help", $_SERVER['HTTP_HOST']);
Conf::$ADMIN_MAIL = "admin@" . $_SERVER['HTTP_HOST'];
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