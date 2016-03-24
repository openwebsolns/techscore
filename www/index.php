<?php
use \model\WebsessionLog;
use \tscore\AbstractDownloadDialog;
use \users\AbstractUserPane;
use \users\PaneException;
use \users\membership\RegisterStudentPane;

/**
 * Gateway to the program TechScore. Manage all session information
 * and direct traffic.
 *
 * @author Dayan Paez
 * @version 2.0
 * @created 2009-10-16
 */

require_once('conf.php');

// ------------------------------------------------------------
// HEAD method used to determine status
// ------------------------------------------------------------
if (Conf::$METHOD == Conf::METHOD_HEAD) {
  if (Conf::$USER === null)
    header('HTTP/1.1 403 Permission denied');
  exit(0);
}

// ------------------------------------------------------------
// Verify method
// ------------------------------------------------------------
if (!in_array(Conf::$METHOD, array(Conf::METHOD_POST, Conf::METHOD_GET))) {
  Conf::do405();
}

// ------------------------------------------------------------
// Construct the URI
// ------------------------------------------------------------
$URI = WS::unlink($_SERVER['REQUEST_URI'], true);
$URI_TOKENS = array();
foreach (explode('/', $URI) as $arg) {
  if (strlen($arg) > 0)
    $URI_TOKENS[] = $arg;
}
if (count($URI_TOKENS) == 0)
  $URI_TOKENS = array('home');

// ------------------------------------------------------------
// Not logged-in?
// ------------------------------------------------------------
if (Conf::$USER === null) {

  switch ($URI_TOKENS[0]) {
  case 'logo.png':
    require_once('users/LogoPane.php');
    $PAGE = new LogoPane();
    break;

  case 'search':
    if (DB::g(STN::EXPOSE_SAILOR_SEARCH) === null) {
      WS::go('/');
    }
    require_once('users/SearchSailor.php');
    $PAGE = new SearchSailor();
    break;

  case 'sailor-registration':
    if (DB::g(STN::ALLOW_SAILOR_REGISTRATION) === null) {
      WS::go('/');
    }
    $PAGE = new RegisterStudentPane();
    break;

  case 'register':
    // Registration?
    if (DB::g(STN::ALLOW_REGISTER) === null)
      WS::go('/');

    // When following mail verification, simulate POST
    if (count($URI_TOKENS) > 1) {
      Conf::$METHOD = Conf::METHOD_POST;
      $_POST['acc'] = $URI_TOKENS[1];
      $_POST['csrf_token'] = Session::getCsrfToken();
    }
    require_once('users/RegisterPane.php');
    $PAGE = new RegisterPane();
    break;

  case 'password-recover':
    require_once('users/PasswordRecoveryPane.php');
    $PAGE = new PasswordRecoveryPane();
    break;

  case 'login':
    require_once('users/LoginPage.php');
    $PAGE = new LoginPage();
    break;

  default:
    if (Conf::$METHOD == Conf::METHOD_POST) {
      WS::go($URI);
    }

    Session::s('last_page', $_SERVER['REQUEST_URI']);
    require_once('users/LoginPage.php');
    $PAGE = new LoginPage();
  }
  if (Conf::$METHOD == Conf::METHOD_POST) {
    Session::s('POST', $PAGE->processPOST($_POST));
    WS::goBack('/');
  }
  $PAGE->processGET($_GET);
  exit;
}

// ------------------------------------------------------------
// User registered at this point
// ------------------------------------------------------------
Conf::$WEBSESSION_LOG = WebsessionLog::record();

if ($URI_TOKENS[0] == 'license') {
  require_once('users/EULAPane.php');
  $PAGE = new EULAPane(Conf::$USER);
  if (Conf::$METHOD == Conf::METHOD_POST) {
    $PAGE->processPOST($_POST);
    WS::go('/');
  }
  $PAGE->processGET($_GET);
  exit;
}
if ($URI_TOKENS[0] == 'logout') {
  $_GET['dir'] = 'out';
  require_once('users/LoginPage.php');
  $PAGE = new LoginPage();
  $PAGE->processGET(array('dir'=>'out'));
  exit;
}
DB::requireActive(Conf::$USER);

// ------------------------------------------------------------
// Process regatta requests
// ------------------------------------------------------------
if (in_array($URI_TOKENS[0], array('score', 'view', 'download'))) {
  try {
    if (!Conf::$USER->canAny(array(Permission::EDIT_REGATTA, Permission::PARTICIPATE_IN_REGATTA)))
      throw new PermissionException("No permission to edit regattas.");

    $BASE = array_shift($URI_TOKENS);
    if (count($URI_TOKENS) == 0) {
      throw new PermissionException("Missing regatta.");
    }
    $REG = DB::getRegatta(array_shift($URI_TOKENS));
    if ($REG === null) {
      throw new PermissionException("No such regatta.");
    }

    $is_participant = false;
    if (!Conf::$USER->hasJurisdiction($REG)) {
      if ($REG->private === null && Conf::$USER->isParticipantIn($REG)) {
        $is_participant = true;
      }
      else {
        throw new PermissionException("You do not have permission to edit that regatta.");
      }
    }

    // User and regatta authorized, delegate to AbstractPane
    $PAGE = null;
    if ($BASE == 'score') {
      require_once('tscore/AbstractPane.php');
      $PAGE = AbstractPane::getPane($URI_TOKENS, Conf::$USER, $REG);
      if ($PAGE === null) {
        $mes = sprintf("Invalid page requested (%s)", implode('/', $URI_TOKENS));
        Session::pa(new PA($mes, PA::I));
        WS::go('/score/'.$REG->id);
      }
      if (!$PAGE->isActive()) {
        $title = $PAGE->getTitle();
        Session::pa(new PA("\"$title\" is not available.", PA::I));
        WS::go('/score/'.$REG->id);
      }
      // Participant?
      if ($is_participant) {
        $PAGE->setParticipantUIMode($is_participant);
      }

      // process, if so requested
      if (Conf::$METHOD == Conf::METHOD_POST) {
        Session::s('POST', $PAGE->processPOST($_POST));
        WS::goBack('/');
      }
    }

    // 'view' and 'download' requires GET method only
    if (Conf::$METHOD != Conf::METHOD_GET) {
      Conf::do405("Only GET method supported for dialogs and downloads.");
    }

    if ($BASE == 'view') {
      require_once('tscore/AbstractDialog.php');
      $PAGE = AbstractDialog::getDialog($URI_TOKENS, Conf::$USER, $REG);
      if ($PAGE === null) {
        $mes = sprintf("Invalid page requested (%s)", implode('/', $URI_TOKENS));
        Session::pa(new PA($mes, PA::I));
        WS::go('/view/'.$REG->id);
      }
    }

    if ($BASE == 'download') {
      require_once('tscore/AbstractDialog.php');
      $PAGE = AbstractDownloadDialog::getDownloadDialog($URI_TOKENS, Conf::$USER, $REG);

      if ($PAGE === null) {
        $mes = sprintf("Invalid download requested (%s)", implode('/', $URI_TOKENS));
        Session::pa(new PA($mes, PA::I));
        WS::go('/score/'.$REG->id);
      }
    }

    $args = $_GET;
    $post = Session::g('POST');
    if (is_array($post))
      $args = array_merge($post, $args);
    $PAGE->processGET($args);
    exit;
  }
  catch (PermissionException $e) {
    Session::pa(new PA($e->getMessage(), PA::E));
    if ($e->regatta !== null)
      WS::go('/score/' . $e->regatta->id);
    WS::go('/');
  }
}

// ------------------------------------------------------------
// School burgee stash
// ------------------------------------------------------------
if ($URI_TOKENS[0] == 'inc') {
  if (count($URI_TOKENS) != 4 ||
      $URI_TOKENS[1] != 'img' ||
      $URI_TOKENS[2] != 'schools') {
    http_response_code(404);
    exit;
  }
  $name = basename($URI_TOKENS[3], '.png');
  $id = $name;
  $prop = 'burgee';
  if (substr($name, -3) == '-40') {
    $id = substr($name, 0, strlen($name) - 3);
    $prop = 'burgee_small';
  }
  if (($school = DB::getSchool($id)) === null ||
      $school->$prop === null) {
    http_response_code(404);
    exit;
  }

  // Cache headings
  header("Cache-Control: public");
  header("Pragma: public");
  header("Content-Type: image/png");
  header("Expires: Sun, 21 Jul 2030 14:08:53 -0400");
  header(sprintf("Last-Modified: %s", $school->$prop->last_updated->format('r')));
  echo base64_decode($school->$prop->filedata);
  exit;
}

// ------------------------------------------------------------
// Regular, non-scoring panes
// ------------------------------------------------------------
try {
  $PAGE = AbstractUserPane::getPane($URI_TOKENS, Conf::$USER);
  if (Conf::$METHOD == Conf::METHOD_POST) {
    Session::s('POST', $PAGE->processPOST($_POST));
    WS::goBack('/');
  }
  $post = Session::g('POST');
  $args = array_merge((is_array($post)) ? $post : array(), $_GET);
  $PAGE->processGET($args);
}
catch (PaneException $e) {
  Session::pa(new PA($e->getMessage(), PA::E));
  WS::go('/');  
}
catch (PermissionException $e) {
  Session::pa(new PA($e->getMessage(), PA::E));
  WS::goBack('/');
}

