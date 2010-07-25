<?php
/**
 * Local settings. Copy this file to 'conf.local.php' and edit that
 * file with the information pertinent to the production site. While
 * this file is version controled, 'conf.local.php' is not.
 *
 */

// The following constants are used to identify the TechScore program
// and the web environment
define("VERSION", "2.0");
define("NAME",    "TechScore");
define("ROOT",    sprintf("http://%s", $_SERVER['HTTP_HOST']));
define("HOME",    ROOT);
define("ADMIN_MAIL", "admin@" . $_SERVER['HTTP_HOST']);
define("TS_FROM_MAIL", ADMIN_MAIL);

// MySQL connection
define('SQL_HOST', "localhost");
define('SQL_USER', "user");
define('SQL_PASS', "password");
define('SQL_DB',   "ts2");

// Timezone setting: set this to the timezone of the server (for the
// purpose of DateTime objects)
date_default_timezone_set("America/New_York");

// ERROR and EXCEPTION handlers. Mail error and exception handlers are
// provided. In order to use them, uncomment the following lines
//
// set_exception_handler("__mail_exception_handler");
// set_error_handler("__mail_error_handler", (E_ERROR | E_WARNING | E_PARSE |
// 					E_CORE_ERROR | E_CORE_WARNING |
//					E_COMPILE_ERROR | E_COMPILE_WARNING |
// 					E_USER_ERROR | E_USER_WARNING |
// 					E_STRICT | E_RECOVERBLE_ERROR));
?>