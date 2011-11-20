<?php
  /**
   * Defines global constants. This file should remain out of view
   * from both the web server and the development team.
   *
   * @author Dayan Paez
   * @version 2009-10-04
   */

// attempt to make files easier to find
ini_set('include_path', sprintf('.:%s:%s', dirname(__FILE__), get_include_path()));

define("TS_PATH", dirname(__FILE__));
function __autoload($name) {
  $dirs = explode(":", TS_PATH);
  foreach ($dirs as $dirname) {
    if (false !== ($result = __search_path($dirname, "$name.php"))) {
      require_once($result);
      return;
    }
  }
}

function __search_path($dirname, $name) {
  // Check this directory
  $filename = sprintf("%s/%s", $dirname, $name);
  if (file_exists($filename)) {
    return $filename;
  }

  // Recursive check
  $d = dir($dirname);
  while (false !== ($entry = $d->read())) {
    $filename = sprintf("%s/%s", $dirname, $entry);
    if (is_dir($filename) && $entry !== "." && $entry !== "..") {
      if (false !== ($result = __search_path($filename, $name))) {
	return $result;
      }
    }
  }
  return false;
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
ini_set('error_log', realpath(TS_PATH.'/../log/errors.log'));

require_once(TS_PATH . '/conf.local.php');

// LOG FILES
define("LOG_UPDATE",   realpath(TS_PATH.'/../log/update.log'));
define("LOG_SEASON",   realpath(TS_PATH.'/../log/season.log'));
define("LOG_SCHOOL",   realpath(TS_PATH.'/../log/school.log'));
define("LOG_FRONT",    realpath(TS_PATH.'/../log/front.log'));
?>
