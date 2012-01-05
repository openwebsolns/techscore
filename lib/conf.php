<?php
  /**
   * Defines global constants. This file should remain out of view
   * from both the web server and the development team.
   *
   * @author Dayan Paez
   * @version 2009-10-04
   */

function __autoload($name) {
  // Check only in the 'regatta' folder
  require_once("regatta/$name.php");
}

/**
 * Error reporting function: send mail
 *
 */
function __mail_error_handler($errno, $errstr, $errfile, $errline, $context) {
  $fmt = "%6s: %s\n";
  $body  = sprintf($fmt, "Time",   date('Y-m-d H:i:s'));
  $body .= sprintf($fmt, "Number", $errno);
  $body .= sprintf($fmt, "String", $errstr);
  $body .= sprintf($fmt, "File",   $errfile);
  $body .= sprintf($fmt, "Line",   $errline);
  $body .= @sprintf($fmt, "Request", $_SERVER['REQUEST_URI']);
  foreach (debug_backtrace() as $list) {
    $body .= "--------------------\n";
    foreach (array('file', 'line', 'class', 'function') as $index) {
      if (isset($list[$index]))
	$body .= sprintf($fmt, ucfirst($index), $list[$index]);
    }
  }

  mail("dpv140@gmail.com", "[TS2 ERROR]", $body, "From: " . TS_FROM_MAIL);

  print <<<END
There was an error while handling your request. Administrators
have been notified of the problem and it will be addressed as
soon as possible.

Sorry for the inconvenience.
END;
  die();
}

/**
 * Exception reporting: send mail
 *
 */
function __mail_exception_handler(Exception $e) {
  $fmt = "%6s: %s\n";
  $body  = sprintf($fmt, "Time",   date('Y-m-d H:i:s'));
  $body .= sprintf($fmt, "Number", $e->getCode());
  $body .= sprintf($fmt, "String", $e->getMessage());
  $body .= sprintf($fmt, "File",   $e->getFile());
  $body .= sprintf($fmt, "Line",   $e->getLine());
  $body .= sprintf($fmt, "Request", $_SERVER['REQUEST_URI']);
  $body .= "--------------------\n";
  $body .= sprintf($fmt, "Trace",  $e->getTraceAsString());
  $body .= "====================\n";

  $sent = mail(ADMIN_MAIL, "[TS2 EXCEPTION]", $body, "From: " . TS_FROM_MAIL);
  if ($sent) {
    print <<<END
There was an error while handling your request. Administrators
have been notified of the problem and it will be addressed as
soon as possible.

Sorry for the inconvenience.
END;
  }
  else {
    print <<<END
There was an error while handling your request. Please excuse the inconvenience.
END;
  }
  die();
}
ini_set('error_log', realpath(dirname(__FILE__).'/../log/errors.log'));

require_once(dirname(__FILE__) . '/conf.local.php');

// LOG FILES
define("LOG_UPDATE",   realpath(dirname(__FILE__).'/../log/update.log'));
define("LOG_SEASON",   realpath(dirname(__FILE__).'/../log/season.log'));
define("LOG_SCHOOL",   realpath(dirname(__FILE__).'/../log/school.log'));
define("LOG_FRONT",    realpath(dirname(__FILE__).'/../log/front.log'));

// Start the session, if run from the web
if (isset($_SERVER['HTTP_HOST'])) {
  require_once('xml5/Session.php');
  require_once('xml/Announcement.php');
  Session::init();
}
?>
