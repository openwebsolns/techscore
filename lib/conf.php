<?php
  /**
   * Defines global constants. This file should remain out of view
   * from both the web server and the development team.
   *
   * @author Dayan Paez
   * @created 2009-10-04
   */

define('TS_PATH', dirname(__FILE__));
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
  $body .= "--------------------\n";
  $body .= print_r($context, true);
  $body .= "====================\n";

  mail("dpv140@gmail.com", "[TS2 ERROR]", $body, "From: ts-admin@techscore.mit.edu");

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
  $body .= "--------------------\n";
  $body .= sprintf($fmt, "Trace",  $e->getTraceAsString());
  $body .= "====================\n";

  $sent = mail(ADMIN_MAIL, "[TS2 EXCEPTION]", $body, "From: ts-admin@techscore.mit.edu");
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

require_once(TS_PATH . '/conf.local.php');
?>
