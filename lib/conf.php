<?php
use \xml5\SessionParams;

/**
 * Global configuration parameters. This is better than using global
 * constants, as those are static.
 *
 * @author Dayan Paez
 * @version 2012-01-06
 */
class Conf {
  /**
   * Techscore version.
   */
  const APP_VERSION = '3.8.1';

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
  public static $SQL_PORT = null;

  /**
   * @var String the salt to use for storing passwords in the database
   */
  public static $PASSWORD_SALT = '';

  /**
   * @var String classname to the error handler. For backwards
   * compatibility, the special value 'mail' may be used to use the
   * MailHandler.
   */
  public static $ERROR_HANDLER = '\error\MailHandler'; // 'mail' or 'print'

  /**
   * @var String the full classname of the mail strategy to use. Must
   * subclass \mail\senders\EmailSender.
   */
  public static $EMAIL_SENDER = '\mail\senders\PhpMailSender';

  /**
   * @var Array map of options passed to constructor of email sender
   * specified above.
   */
  public static $EMAIL_SENDER_PARAMS = array();

  /**
   * @var fully instantiated singleton of email bounce handler, or null to skip.
   */
  public static $EMAIL_BOUNCE_HANDLER = null;

  /**
   * @var String the full classname of the instance of \eligibility\EligibilityCalculator.
   *    Null suppresses automatic rollover functionality.
   */
  public static $ELIGIBILITY_CALCULATOR = null;

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
   *
   * @deprecated only a single entry is allowed. See $WRITER
   */
  public static $WRITERS = array('\writers\LocalHtmlWriter');

  /**
   * @var \writers\AbstractWriter the writer for public pages
   */
  public static $WRITER = null;

  /**
   * @var Array the constructor parameters for the specific writer implementation
   */
  public static $WRITER_PARAMS = array();


  /**
   * @var String full name of \metrics\MetricPublisher to use
   */
  public static $METRIC_PUBLISHER = '\metrics\DBMetricPublisher';

  /**
   * @var Array the constructor parameters for specified metric publisher
   */
  public static $METRIC_PUBLISHER_PARAMS = array();

  // ------------------------------------------------------------
  // Environment setup
  // ------------------------------------------------------------

  /**
   * @var String the SQL user allowed to make schema changes
   */
  public static $DB_ROOT_USER = 'root';
  /**
   * @var String optional password root user
   */
  public static $DB_ROOT_PASS = null;

  const HTTP_TEMPLATE_VHOST_SSL = 'apache.conf.default';
  const HTTP_TEMPLATE_VHOST_LB = 'apache.conf.default-loadbalanced';
  const HTTP_TEMPLATE_CODEDEPLOY = 'apache.conf.default-codedeploy';
  const HTTP_TEMPLATE_DOCKER = 'apache.conf.default-docker';

  public static $HTTP_TEMPLATE = self::HTTP_TEMPLATE_VHOST_SSL;

  const HTTP_TEMPLATE_PARAM_CERTPATH = '{HTTP_CERTPATH}';
  const HTTP_TEMPLATE_PARAM_CERTKEYPATH = '{HTTP_CERTKEYPATH}';
  const HTTP_TEMPLATE_PARAM_CERTCHAINPATH = '{HTTP_CERTCHAINPATH}';
  /**
   * @var filepath the installation root of the repository. This value
   * used when generating apache.conf if the final destination differs
   * from the installed one.
   */
  const HTTP_TEMPLATE_PARAM_DIRECTORY = '{HTTP_DIRECTORY}';
  /**
   * @var where Apache installs logs
   */
  const HTTP_TEMPLATE_PARAM_LOGROOT = '{HTTP_LOGROOT}';

  public static $HTTP_TEMPLATE_PARAMS = array();

  /**
   * @deprecated Use HTTP_TEMPLATE = HTTP_TEMPLATE_VHOST_LB
   *
   * @var boolean default behavior (false) is to have this Apache installation
   * serve as a front-end to the application running over SSL on port 443. In
   * that case, HTTP_CERT* parameters must also be set. This will generate two
   * virtualhosts, one over port 80 that redirects to the other over 443.
   *
   * If set to true, Apache will only listen on port 80, but will redirect any
   * traffic that originally came over port 80 to port 443. This is applicable
   * when running the application behind a load balancer which takes care of the
   * SSL negotation, while talking to the backend over port 80, thus saving the
   * individual hosts from SSL overhead nad cert management.
   */
  public static $HTTP_BEHIND_PORT_80_LOAD_BALANCER = false;
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
   * @deprecated should be removed
   */
  public static $CRON_FREQ = '* * * * *';
  /**
   * @var cronline the (full update schedule for season-level updates
   * @deprecated should be removed
   */
  public static $CRON_SEASON_FREQ = '*/5 * * * *';
  /**
   * @var cronline the (full) update schedule for school-level updates
   * @deprecated should be removed
   */
  public static $CRON_SCHOOL_FREQ = '7,27,47 * * * *';

  /**
   * @var String the filename to use for the lock file (in system temp)
   * @deprecated should be removed
   */
  public static $LOCK_FILENAME = 'ts-pub.lock';

  // ------------------------------------------------------------
  // Runtime parameters and functions
  // ------------------------------------------------------------

  /**
   * The current websession, if any.
   */
  public static $WEBSESSION_LOG = null;

  const METHOD_GET = 'GET';
  const METHOD_HEAD = 'HEAD';
  const METHOD_POST = 'POST';

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
    // Check via namespace. Also check in 'model' directory.
    // Check only in the 'model' folder
    $translation = str_replace('\\', '/', $name);
    $name = sprintf('%s/%s.php', __DIR__, $translation);
    if (file_exists($name)) {
      require_once($name);
    }
    else {
      $name = sprintf('%s/model/%s.php', __DIR__, $translation);
      if (file_exists($name)) {
        require_once($name);
      }
    }
  }

  public static function init() {
    spl_autoload_register('Conf::autoload');

    ini_set('include_path', sprintf(".:%s", dirname(__FILE__)));
    ini_set('expose_php', 'Off');
    ini_set('track_errors', 'Off');
    ini_set('upload_max_filesize', '16M');
    ini_set('date.timezone', 'America/New_York');
    ini_set('mail.add_x_header', 'Off');
    ini_set('session.name', 'Techscore');
    ini_set('session.cookie_httponly', 1);

    $confFile = isset($_SERVER['CONF_LOCAL_FILE']) ? $_SERVER['CONF_LOCAL_FILE'] : 'conf.local.php';
    require_once(dirname(__FILE__) . '/' . $confFile);

    // Error handler: use CLI if not online
    if (Conf::$ERROR_HANDLER == 'mail') {
      Conf::$ERROR_HANDLER = '\error\MailHandler';
    }
    if (PHP_SAPI == self::CLI) {
      Conf::$ERROR_HANDLER = '\error\CLIHandler';
    }
    $classname = Conf::$ERROR_HANDLER;
    (new $classname())->registerAll(E_ALL | E_STRICT | E_NOTICE);

    // Database connection
    DB::setConnectionParams(Conf::$SQL_HOST, Conf::$SQL_USER, Conf::$SQL_PASS, Conf::$SQL_DB, Conf::$SQL_PORT);
    DB::setLogfile(Conf::$LOG_QUERIES);

    // Fix up HTTPS setting when there is no 80 > 443 redirection
    if (Conf::$HTTP_TEMPLATE === Conf::HTTP_TEMPLATE_CODEDEPLOY) {
      $_SERVER['HTTPS'] = 'on';
    }

    if (!defined('NO_USER')) {
      self::initUser();
    }
  }

  private static function initUser() {
    // Start the session, if run from the web
    if (PHP_SAPI == self::CLI) {
      Conf::$USER = DB::getRootAccount();
    }
    else {
      if (!isset($_SERVER['REQUEST_METHOD'])) {
        throw new RuntimeException("Script can only be run from web server.");
      }
      Conf::$METHOD = $_SERVER['REQUEST_METHOD'];
      if (Conf::$METHOD == Conf::METHOD_POST) {
        $_POST = array_merge($_POST, $_FILES);
      }

      // Only use non-secure cookies when running as built-in PHP
      // cli-server, since SSL is not supported there.
      if (PHP_SAPI == self::CLI_SERVER) {
        Conf::$SECURE_COOKIE = false;
        Conf::$HOME = 'localhost';
      }

      // If behind ALB, or AWS, then do not force secure cookies
      if (in_array(Conf::$HTTP_TEMPLATE, array(self::HTTP_TEMPLATE_VHOST_LB, self::HTTP_TEMPLATE_CODEDEPLOY, self::HTTP_TEMPLATE_DOCKER))) {
        Conf::$SECURE_COOKIE = false;
      }

      require_once('WS.php');
      require_once('TSSessionHandler.php');
      require_once('xml5/HtmlLib.php');
      require_once('xml5/Session.php');
      TSSessionHandler::register();
      Session::init();
      Conf::$USER = DB::getAccount(Session::g(SessionParams::USER));
      if (Conf::$USER !== null && ($id = Session::g(SessionParams::USURPED_USER)) !== null) {
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
