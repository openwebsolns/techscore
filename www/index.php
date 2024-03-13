<?php
use \model\WebsessionLog;
use \users\AbstractUserPane;
use \users\BurgeePane;
use \users\LogoutPage;
use \users\PaneException;
use \users\RegisterPane;
use \users\SearchSailor;
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
    $PAGE = new SearchSailor();
    break;

  case 'sailor-registration':
    $PAGE = new RegisterStudentPane();
    if (!$PAGE->isAvailable()) {
      WS::go('/');
    }
    break;

  case 'register':
    // For backwards compatibility, allow URL like /register/<token>
    if (count($URI_TOKENS) > 1) {
      $_GET[RegisterPane::INPUT_TOKEN] = $URI_TOKENS[1];
      $_POST[RegisterPane::INPUT_TOKEN] = $URI_TOKENS[1];
    }
    $PAGE = new RegisterPane();
    if (!$PAGE->isAvailable()) {
      WS::go('/');
    }
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
  $PAGE = new LogoutPage();
  $PAGE->processGET($_GET);
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
  $P = new BurgeePane();
  $P->processGET(array(BurgeePane::INPUT_BURGEE => $URI_TOKENS[3]));
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
  Session::d('POST');
}
catch (PaneException $e) {
  Session::pa(new PA($e->getMessage(), PA::E));
  WS::go('/');  
}
catch (PermissionException $e) {
  Session::pa(new PA($e->getMessage(), PA::E));
  WS::goBack('/');
}

