<?php
  /**
   * Defines global constants. This file should remain out of view
   * from both the web server and the development team.
   *
   * @author Dayan Paez
   * @version 2009-10-04
   */

/**
 * Global configuration parameters. This is better than using global
 * constants, as those are static.
 *
 * @author Dayan Paez
 * @version 2012-01-06
 */
class Conf {
  public static $VERSION = '2.0';

  // Logging options
  public static $LOG_UPDATE;
  public static $LOG_SEASON;
  public static $LOG_SCHOOL;
  public static $LOG_FRONT;
  public static $LOG_MEMORY = false;
  public static $DIVERT_MAIL = 'dayan@localhost';

  // General constants
  public static $NAME = "TechScore";
  public static $HOME = "https://ts.collegesailing.info";
  public static $PUB_HOME = "http://scores.collegesailing.info";
  public static $HELP_HOME = "http://collegesailing.info/ts-help";
  public static $ADMIN_MAIL = "dayan@localhost";
  public static $TS_FROM_MAIL = "dayan@localhost";

  // MySQL connection
  public static $SQL_HOST = "localhost";
  public static $SQL_USER = "ts2";
  public static $SQL_PASS = "";
  public static $SQL_DB   = "ts2";

  /**
   * @var String|null Set to non-null path to log queries
   */
  public static $LOG_QUERIES = null;

  /**
   * @var Account|null will be set to the logged-in account, if any
   */
  public static $USER = null;
}
// LOG FILES
Conf::$LOG_UPDATE = realpath(dirname(__FILE__).'/../log/update.log');
Conf::$LOG_SEASON = realpath(dirname(__FILE__).'/../log/season.log');
Conf::$LOG_SCHOOL = realpath(dirname(__FILE__).'/../log/school.log');
Conf::$LOG_FRONT =  realpath(dirname(__FILE__).'/../log/front.log');

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

  mail("dpv140@gmail.com", "[TS2 ERROR]", $body, "From: " . Conf::$TS_FROM_MAIL);

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

  $sent = mail(Conf::$ADMIN_MAIL, "[TS2 EXCEPTION]", $body, "From: " . Conf::$TS_FROM_MAIL);
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

// Database connection
require_once('regatta/DB.php');
DB::setConnectionParams(Conf::$SQL_HOST, Conf::$SQL_USER, Conf::$SQL_PASS, Conf::$SQL_DB);

// Start the session, if run from the web
if (isset($_SERVER['HTTP_HOST'])) {
  require_once('xml5/Session.php');
  Session::init();
  Conf::$USER = DB::getAccount(Session::g('user'));
}
?>
