<?php
/*
 * Local template for executing with Docker Compose.
 */

// Print errors to screen instead of e-mailing
Conf::$ERROR_HANDLER = '\error\PrintHandler';

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

Conf::$WRITER = '\writers\LocalHtmlWriter';
Conf::$WRITER_PARAMS = array(
    \writers\LocalHtmlWriter::PARAM_HTML_ROOT => realpath(dirname(__FILE__).'/../public-html'),
);
