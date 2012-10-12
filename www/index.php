<?php
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
// Verify method
// ------------------------------------------------------------
if (!in_array(Conf::$METHOD, array('POST', 'GET')))
  Conf::do405();

// ------------------------------------------------------------
// Construct the URI
// ------------------------------------------------------------
$URI = WS::unlink($_SERVER['REQUEST_URI'], true);
$URI_TOKENS = explode('/', $URI);
array_shift($URI_TOKENS);

// ------------------------------------------------------------
// Not logged-in?
// ------------------------------------------------------------
if (Conf::$USER === null) {
  // Registration?
  switch ($URI_TOKENS[0]) {
  case 'register':
    if (Conf::$ALLOW_REGISTER === false)
      WS::go('/');

    // When following mail verification, simulate POST
    if (count($URI_TOKENS) > 1) {
      Conf::$METHOD = 'POST';
      $_POST['acc'] = $URI_TOKENS[1];
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
    if (Conf::$METHOD == 'POST')
      WS::go($URI);

    Session::pa(new PA("Please login to proceed.", PA::I));
    Session::s('last_page', $_SERVER['REQUEST_URI']);
    require_once('users/LoginPage.php');
    $PAGE = new LoginPage();
  }
  if (Conf::$METHOD == 'POST') {
    Session::s('POST', $PAGE->processPOST($_POST));
    WS::goBack('/');
  }
  $PAGE->getHTML($_GET);
  exit;
}

// ------------------------------------------------------------
// User registered at this point
// ------------------------------------------------------------
if ($URI_TOKENS[0] == 'license') {
  if (Conf::$METHOD == 'POST')
    WS::go($URI);
  require_once('users/EULAPane.php');
  $PAGE = new EULAPane(Conf::$USER);
  $PAGE->getHTML($_GET);
  exit;
}
if ($URI_TOKENS[0] == 'logout') {
  $_GET['dir'] = 'out';
  require_once('users/LoginPage.php');
  $PAGE = new LoginPage();
  $PAGE->getHTML(array('dir'=>'out'));
  exit;
}
DB::requireActive(Conf::$USER);

// ------------------------------------------------------------
// Process regatta requests
// ------------------------------------------------------------
if (in_array($URI_TOKENS[0], array('score', 'view', 'download'))) {
  $BASE = array_shift($URI_TOKENS);
  if (count($URI_TOKENS) == 0) {
    Session::pa(new PA("Missing regatta.", PA::I));
    WS::go('/');
  }
  $REG = DB::getRegatta(array_shift($URI_TOKENS));
  if ($REG === null) {
    Session::pa(new PA("No such regatta.", PA::I));
    WS::go('/');
  }
  if (!Conf::$USER->hasJurisdiction($REG)) {
    Session::pa(new PA("You do not have permission to edit that regatta.", PA::I));
    WS::go('/');
  }

  // User and regatta authorized, delegate to AbstractPane
  $PAGE = null;
  if ($BASE == 'score') {
    require_once('tscore/AbstractPane.php');
    $PAGE = AbstractPane::getPane($URI_TOKENS, Conf::$USER, $REG);
    if ($PAGE === null) {
      $mes = sprintf("Invalid page requested (%s)", $ARG);
      Session::pa(new PA($mes, PA::I));
      WS::go('/score/'.$REG->id);
    }
    if (!$PAGE->isActive()) {
      $title = $PAGE->getTitle();
      Session::pa(new PA("\"$title\" is not available.", PA::I));
      WS::go('/score/'.$REG->id);
    }
    // process, if so requested
    if (Conf::$METHOD == 'POST') {
      require_once('public/UpdateManager.php');
      Session::s('POST', $PAGE->processPOST($_POST));
      WS::goBack('/');
    }
  }

  // 'view' and 'download' requires GET method only
  if (Conf::$METHOD != 'GET')
    Conf::do405("Only GET method supported for dialogs and downloads.");

  if ($BASE == 'view') {
    require_once('tscore/AbstractDialog.php');
    $PAGE = AbstractDialog::getDialog($URI_TOKENS, Conf::$USER, $REG);
    if ($PAGE === null) {
      $mes = sprintf("Invalid page requested (%s)", $ARG);
      Session::pa(new PA($mes, PA::I));
      WS::go('/view/'.$REG->id);
    }
  }

  if ($BASE == 'download') {
    $st = $REG->start_time;
    $nn = $REG->nick;
    if (count($REG->getTeams()) == 0 || count($REG->getDivisions()) == 0) {
      Session::pa(new PA("First create teams and divisions before downloading.", PA::I));
      WS::go('/score/'.$REG->id);
    }

    if (count($URI_TOKENS) == 0) {
      Session::pa(new PA("Nothing to download. Please try again.", PA::I));
      WS::go('/score/'.$REG->id);
    }
    switch ($URI_TOKENS[0]) {

      // --------------- REGATTA ---------------//
      /*
        case "":
        case "regatta":
        $name = sprintf("%s-%s.tsr", $st->format("Y"), $nn);
        header("Content-type: text/xml");
        header(sprintf('Content-disposition: attachment; filename="%s"', $name));
        echo RegattaIO::toXML($REG);
        break;
      */

      // --------------- RP FORMS --------------//
    case 'rp':
    case 'rpform':
    case 'rps':
      $name = sprintf('%s-%s-rp', $st->format('Y'), $nn);
      $rp = $REG->getRpManager();
      if ($rp->isFormRecent())
        $data = $rp->getForm();
      else {
        require_once('rpwriter/RpFormWriter.php');
        $writer = new RpFormWriter($REG);
        $path = $writer->makePDF();
        $data = file_get_contents($path);
        unlink($path);
        $rp->setForm($data);
      }

      header('Content-type: application/pdf');
      header(sprintf('Content-Disposition: attachment; filename="%s.pdf"', $name));
      echo $data;
      exit;

    // --------------- default ---------------//
    default:
      $mes = sprintf("Invalid download requested (%s)", $_GET['d']);
      Session::pa(new PA("Invalid download requested.", PA::I));
      WS::go('/score/'.$REG->id);
    }
  }

  $args = $_GET;
  $post = Session::g('POST');
  if (is_array($post))
    $args = array_merge($post, $args);
  $PAGE->getHTML($args);
  exit;
}

// ------------------------------------------------------------
// Regular, non-scoring panes
// ------------------------------------------------------------
require_once('users/AbstractUserPane.php');
try {
  $PAGE = AbstractUserPane::getPane($URI_TOKENS, Conf::$USER);
}
catch (PaneException $e) {
  Session::pa(new PA($e->getMessage(), PA::E));
  WS::go('/');  
}
if (Conf::$METHOD == 'POST') {
  Session::s('POST', $PAGE->processPOST($_POST));
  WS::goBack('/');
}
$post = Session::g('POST');
$args = array_merge((is_array($post)) ? $post : array(), $_GET);
$PAGE->getHTML($args);
?>
