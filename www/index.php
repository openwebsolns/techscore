<?php
use \model\WebsessionLog;
use \ui\HttpResponse;
use \users\AbstractUserPane;
use \users\LogoutPage;
use \users\PaneException;
use \users\PasswordRecoveryPane;
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
  if (Conf::$USER === null) {
    $response = HttpResponse::forbidden("");
  } else {
    $response = HttpResponse::ok("");
  }

  $response->sendToBrowser();
  exit(0);
}

// ------------------------------------------------------------
// Verify method
// ------------------------------------------------------------
if (!in_array(Conf::$METHOD, array(Conf::METHOD_POST, Conf::METHOD_GET))) {
  $response = HttpResponse::methodNotAllowed(
    "Only POST and GET methods allowed",
    ['Content-Type' => 'text/plain']);
  $response->sendToBrowser();
  exit;
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
    $PAGE = new RegisterPane();
    if (!$PAGE->isAvailable()) {
      WS::go('/');
    }
    break;

  case 'password-recover':
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
    $response = $PAGE->processPOST($_POST);
  } else {
    $response = $PAGE->processGET($_GET);
  }

  $response->sendToBrowser();
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
    $response = $PAGE->processPOST($_POST);
  } else {
    $response = $PAGE->processGET($_GET);
  }
  $response->sendToBrowser();
  exit;
}

if ($URI_TOKENS[0] == 'logout') {
  $PAGE = new LogoutPage();
  $response = $PAGE->processGET($_GET);
  $response->sendToBrowser();
  exit;
}

// ------------------------------------------------------------
// Ensure the logged-in user is active
// ------------------------------------------------------------
if (Conf::$USER->status === Account::STAT_ACCEPTED) {
  $response = HttpResponse::seeOther('/license');
  $response->sendToBrowser();
  exit;
} elseif (Conf::$USER->status !== Account::STAT_ACTIVE) {
  $response = HttpResponse::seeOther('/logout');
  $response->sendToBrowser();
  exit;
}

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
        $response = $PAGE->processPOST($_POST);
        $response->sendToBrowser();
        exit;
      }
    }

    // 'view' and 'download' requires GET method only
    if (Conf::$METHOD != Conf::METHOD_GET) {
      $response = HttpResponse::methodNotAllowed(
        "Only GET method supported for dialogs and downloads.",
        ['Content-Type' => 'text/plain']);
      $response->sendToBrowser();
      exit;
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
    $response = $PAGE->processGET($args);
    $response->sendToBrowser();
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
// Regular, non-scoring panes
// ------------------------------------------------------------
try {
  $PAGE = AbstractUserPane::getPane($URI_TOKENS, Conf::$USER);
  if (Conf::$METHOD == Conf::METHOD_POST) {
    $response = $PAGE->processPOST($_POST);
    $response->sendToBrowser();
    exit;
  }

  $post = Session::g('POST');
  $args = array_merge((is_array($post)) ? $post : array(), $_GET);
  $response = $PAGE->processGET($args);
  $response->sendToBrowser();
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

