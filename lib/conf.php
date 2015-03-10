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
  /**
   * @var String the hostname (sans protocol) for the scoring
   */
  public static $HOME = 'ts.collegesailing.org';
  /**
   * @var String the hostname (sans protocol) for public site
   */
  public static $PUB_HOME = 'scores.collegesailing.org';
  /**
   * @var filepath the path to the directory containing the logs
   */
  public static $LOG_ROOT = '/var/log/httpd';

  public static $ADMIN_MAIL = 'dayan@localhost';

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
   * @var Account|null the usurper
   */
  public static $USURPER = null;

  /**
   * @var Array ids of allowed users, or null to allow all
   */
  public static $DEBUG_USERS = null;

  /**
   * @var Array the list of class names of AbstractWriter's which
   * should be used when creating the public pages. These class names
   * should correspond with writers/<classname>.php files. If empty,
   * files will not be written to the filesystem.
   */
  public static $WRITERS = array('LocalHtmlWriter');

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
   * @var boolean default is true. Note: this flag is changed by the
   * application when in (unit) testing mode, as SSL is not allowed by
   * the PHP built-in webserver. Do not change this flag for normal,
   * production boxes.
   */
  public static $SECURE_COOKIE = true;

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
   * @var String the HTTP_REQUEST method for web requets: POST, GET
   */
  public static $METHOD = null;

  /**
   * Known PHP_SAPI values.
   */  
  const CLI = 'cli';
  const CLI_SERVER = 'cli-server';

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
   * Autoloads classes by name from the model subdirectory.
   *
   */
  public static function autoload($name) {
    // Check only in the 'model' folder
    $name = sprintf('%s/model/%s.php', __DIR__, str_replace('\\', '/', $name));
    if (file_exists($name)) {
      require_once($name);
    }
  }

  public static function init() {
    spl_autoload_register('Conf::autoload');

    ini_set('include_path', sprintf(".:%s", dirname(__FILE__)));

    require_once(dirname(__FILE__) . '/conf.local.php');

    // Error handler: use CLI if not online
    if (PHP_SAPI == self::CLI) {
      require_once('error/CLIHandler.php');
      CLIHandler::registerAll(E_ALL | E_STRICT);
    }
    elseif (Conf::$ERROR_HANDLER == 'mail') {
      require_once('error/MailHandler.php');
      MailHandler::registerAll(E_ALL | E_STRICT);
    }
    else {
      require_once('error/PrintHandler.php');
      PrintHandler::registerAll(E_ALL | E_STRICT | E_NOTICE);
    }

    // Database connection
    DB::setConnectionParams(Conf::$SQL_HOST, Conf::$SQL_USER, Conf::$SQL_PASS, Conf::$SQL_DB);
    DB::setLogfile(Conf::$LOG_QUERIES);

    // Start the session, if run from the web
    if (PHP_SAPI != self::CLI) {
      if (!isset($_SERVER['REQUEST_METHOD']))
        throw new RuntimeException("Script can only be run from web server.");
      Conf::$METHOD = $_SERVER['REQUEST_METHOD'];

      // Only use non-secure cookies when running as built-in PHP
      // cli-server, since SSL is not supported there.
      if (PHP_SAPI == self::CLI_SERVER) {
        Conf::$SECURE_COOKIE = false;
        Conf::$HOME = 'localhost';
      }

      require_once('WS.php');
      require_once('TSSessionHandler.php');
      require_once('xml5/HtmlLib.php');
      require_once('xml5/Session.php');
      TSSessionHandler::register();
      Session::init();
      Conf::$USER = DB::getAccount(Session::g('user'));
      if (Conf::$USER !== null && ($id = Session::g('usurped_user')) !== null) {
        $usurped = DB::getAccount($id);
        if ($usurped !== null && $usurped->status == Account::STAT_ACTIVE) {
          Conf::$USURPER = Conf::$USER;
          Conf::$USER = $usurped;
        }
      }
    }
  }
}

Conf::init();
?>
