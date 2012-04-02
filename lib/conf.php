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
  public static $VERSION = '3.0-beta';

  // Logging options
  public static $LOG_UPDATE = '/dev/null';
  public static $LOG_SEASON = '/dev/null';
  public static $LOG_SCHOOL = '/dev/null';
  public static $LOG_FRONT = '/dev/null';
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

  // Error handler
  public static $ERROR_HANDLER = "mail"; // "mail" or "print"

  /**
   * @var String|null Set to non-null path to log queries
   */
  public static $LOG_QUERIES = null;

  /**
   * @var Account|null will be set to the logged-in account, if any
   */
  public static $USER = null;

  /**
   * @var Array ids of allowed users, or null to allow all
   */
  public static $DEBUG_USERS = null;

  /**
   * @var boolean allow registrations? (default yes)
   */
  public static $ALLOW_REGISTER = true;

  /**
   * @var Array the list of types NOT to allow. This will prevent the
   * creation or changing of a regatta into one of these types. It
   * will NOT prevent the display of these regattas.
   */
  public static $REGATTA_TYPE_BLACKLIST = array();

  /**
   * @var Array the list of scoring NOT to allow. This will prevent
   * the creation or changing of a regatta scoring to one of these
   * values. It will NOT prevent the display or editing of those
   * regattas.
   */
  public static $REGATTA_SCORING_BLACKLIST = array();

  /**
   * @var String the URL of sailor information
   */
  public static $SAILOR_API_URL = 'http://www.collegesailing.org/directory/individual/sailorapi.asp';
  /**
   * @var String the URL of coach information
   */
  public static $COACH_API_URL = 'http://www.collegesailing.org/directory/individual/coachapi.asp';
  /**
   * @var String the URL of school information
   */
  public static $SCHOOL_API_URL = 'http://www.collegesailing.org/directory/individual/schoolapi.asp';
}

function __autoload($name) {
  // Check only in the 'regatta' folder
  require_once("regatta/$name.php");
}

ini_set('error_log', realpath(dirname(__FILE__).'/../log/errors.log'));
ini_set('include_path', sprintf(".:%s", dirname(__FILE__)));

require_once(dirname(__FILE__) . '/conf.local.php');

// Error handler
if (Conf::$ERROR_HANDLER == 'mail') {
  require_once('error/MailHandler.php');
  MailHandler::registerErrors(E_ALL | E_STRICT);
  MailHandler::registerExceptions();
}
else {
  require_once('error/PrintHandler.php');
  PrintHandler::registerErrors(E_ALL | E_STRICT | E_NOTICE);
  PrintHandler::registerExceptions();
}

// Database connection
require_once('regatta/DB.php');
DB::setConnectionParams(Conf::$SQL_HOST, Conf::$SQL_USER, Conf::$SQL_PASS, Conf::$SQL_DB);
// DB::setLogfile(Conf::$LOG_QUERIES);

// Start the session, if run from the web
if (isset($_SERVER['HTTP_HOST'])) {
  require_once('xml5/Session.php');
  Session::init();
  Conf::$USER = DB::getAccount(Session::g('user'));
}
?>
