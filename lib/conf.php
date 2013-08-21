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
  public static $COPYRIGHT = '© OpenWeb Solutions, LLC 2008-2012';

  // Logging options
  public static $DIVERT_MAIL = 'dayan@localhost';

  // General constants
  /**
   * @var String the name of the program
   */
  public static $NAME = 'TechScore';
  /**
   * @var String the hostname (sans protocol) for the scoring
   */
  public static $HOME = 'ts.collegesailing.org';
  /**
   * @var String the hostname (sans protocol) for public site
   */
  public static $PUB_HOME = 'scores.collegesailing.org';
  /**
   * @var String the hostname (sans protocol) for the help pages
   */
  public static $HELP_HOME = 'collegesailing.org/ts-help';
  /**
   * @var String the URL for ICSA home
   */
  public static $ICSA_HOME = 'http://www.collegesailing.org';
  /**
   * @var filepath the path to the directory containing the logs
   */
  public static $LOG_ROOT = '/var/log/httpd';

  public static $ADMIN_MAIL = 'dayan@localhost';
  public static $TS_FROM_MAIL = 'dayan@localhost';

  // MySQL connection
  public static $SQL_HOST = 'localhost';
  public static $SQL_USER = 'ts2';
  public static $SQL_PASS = '';
  public static $SQL_DB   = 'ts2';

  /**
   * @var String the salt to use for storing passwords in the database
   */
  public static $PASSWORD_SALT = '';

  // Error handler
  public static $ERROR_HANDLER = 'mail'; // 'mail' or 'print'

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
   * @var Array the list of scoring NOT to allow. This will prevent
   * the creation or changing of a regatta scoring to one of these
   * values. It will NOT prevent the display or editing of those
   * regattas.
   */
  public static $REGATTA_SCORING_BLACKLIST = array();

  /**
   * @var Array the list of class names of AbstractWriter's which
   * should be used when creating the public pages. These class names
   * should correspond with writers/<classname>.php files. If empty,
   * files will not be written to the filesystem.
   */
  public static $WRITERS = array('LocalHtmlWriter');

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

  // ------------------------------------------------------------
  // Environment setup
  // ------------------------------------------------------------

  /**
   * @var String the SQL user allowed to make schema changes
   */
  public static $DB_ROOT_USER = 'root';
  /**
   * @var filepath the path to the *.crt file (TS must be run over HTTPS)
   */
  public static $HTTP_CERTPATH = '/etc/httpd/certs/ts2.crt';
  /**
   * @var filepath the path to the *.key file (TS must be run over HTTPS)
   */
  public static $HTTP_CERTKEYPATH = '/etc/httpd/certs/ts2.key';
  /**
   * @var filepath the path to the bundle file (if any)
   */
  public static $HTTP_CERTCHAINPATH = null;
  /**
   * @var String the Google Custom Search ID. If non-null, the Google
   * Custom Search script will be added to the public pages
   */
  public static $GCSE_ID = null;
  // ------------------------------------------------------------
  // Cron settings
  // ------------------------------------------------------------

  /**
   * @var cronline the (full) update schedule for regatta-level updates
   */
  public static $CRON_FREQ = '* * * * *';
  /**
   * @var cronline the (full update schedule for season-level updates
   */
  public static $CRON_SEASON_FREQ = '*/5 * * * *';
  /**
   * @var cronline the (full) update schedule for school-level updates
   */
  public static $CRON_SCHOOL_FREQ = '7,27,47 * * * *';

  /**
   * @var String the filename to use for the lock file (in system temp)
   */
  public static $LOCK_FILENAME = 'ts-pub.lock';

  // ------------------------------------------------------------
  // Runtime parameters and functions
  // ------------------------------------------------------------

  /**
   * Issues a 405 HTTP error with the message provided
   *
   * @param String $mes the explanation to issue for the 405 error
   */
  public static function do405($mes = "Only POST and GET methods allowed.") {
    header('HTTP/1.1 405 Method not allowed');
    header('Content-type: text/plain');
    echo $mes;
    exit;
  }

  /**
   * @var String the HTTP_REQUEST method for web requets: POST, GET
   */
  public static $METHOD = null;
}

function __autoload($name) {
  // Check only in the 'regatta' folder
  require_once("regatta/$name.php");
}

ini_set('include_path', sprintf(".:%s", dirname(__FILE__)));

require_once(dirname(__FILE__) . '/conf.local.php');

// Error handler: use CLI if not online
if (!isset($_SERVER['HTTP_HOST'])) {
  require_once('error/CLIHandler.php');
  CLIHandler::registerErrors(E_ALL | E_STRICT);
  CLIHandler::registerExceptions();
}
elseif (Conf::$ERROR_HANDLER == 'mail') {
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
DB::setLogfile(Conf::$LOG_QUERIES);

// Start the session, if run from the web
if (isset($_SERVER['HTTP_HOST'])) {
  if (!isset($_SERVER['REQUEST_METHOD']))
    throw new RuntimeException("Script can only be run from web server.");
  Conf::$METHOD = $_SERVER['REQUEST_METHOD'];

  require_once('WS.php');
  require_once('xml5/HtmlLib.php');
  require_once('xml5/Session.php');
  Session::init();
  Conf::$USER = DB::getAccount(Session::g('user'));
}
?>
