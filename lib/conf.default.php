<?php
/**
 * Local settings. Copy this file to 'conf.local.php' and edit that
 * file with the information pertinent to the production site. While
 * this file is version controled, 'conf.local.php' is not.
 *
 */

use \writers\LocalHtmlWriter;

// General settings for schema and system updates
//Conf::$DB_ROOT_USER = 'root';
//Conf::$LOG_ROOT = '/var/log/httpd';

Conf::$HTTP_TEMPLATE = Conf::HTTP_TEMPLATE_VHOST_SSL;
// Conf::$HTTP_TEMPLATE_PARAMS = array(
//   Conf::HTTP_TEMPLATE_PARAM_CERTPATH => '/etc/httpd/certs/ts2.crt',
//   Conf::HTTP_TEMPLATE_PARAM_CERTKEYPATH => '/etc/httpd/certs/ts2.key',
//   Conf::HTTP_TEMPLATE_PARAM_CERTCHAINPATH => null,
// );

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

Conf::$WRITER = '\writers\LocalHtmlWriter';
Conf::$WRITER_PARAMS = array(
    \writers\LocalHtmlWriter::PARAM_HTML_ROOT => realpath(dirname(__FILE__).'/../public-html'),
);

// Set to the ID of the users that can log in
// Conf::$DEBUG_USERS = array('admin@localhost');

Conf::$LOCK_FILENAME = 'ts-pub.lock';

Conf::$EMAIL_SENDER = '\mail\senders\PhpMailSender';
Conf::$EMAIL_SENDER_PARAMS = array();

Conf::$ELIGIBILITY_CALCULATOR = null;
