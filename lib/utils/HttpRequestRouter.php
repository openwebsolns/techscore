<?php
namespace utils;

use \AbstractDialog;
use \AbstractPane;
use \Account;
use \Conf;
use \DB;
use \EULAPane;
use \LoginPage;
use \LogoPane;
use \PA;
use \Permission;
use \PermissionException;
use \STN;
use \Session;
use \WS;

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
 * Utility class to map HTTP request into the appropriate HttpResponse.
 *
 * This is the "entry point" to the application, which transforms a user's request
 * into the HttpResponse that should be sent back.
 *
 * @author Dayan Paez
 * @version 2026-04-10
 */
class HttpRequestRouter {

  public static function routeRequest(): HttpResponse {
    // ------------------------------------------------------------
    // HEAD method used to determine status
    // ------------------------------------------------------------
    if (Conf::$METHOD == Conf::METHOD_HEAD) {
      if (Conf::$USER === null) {
        return HttpResponse::forbidden("");
      }

      return HttpResponse::ok("");
    }

    // ------------------------------------------------------------
    // Verify method
    // ------------------------------------------------------------
    if (!in_array(Conf::$METHOD, array(Conf::METHOD_POST, Conf::METHOD_GET))) {
      return HttpResponse::methodNotAllowed(
        "Only POST and GET methods allowed",
        ['Content-Type' => 'text/plain']);
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
          return HttpResponse::seeOther('/');
        }
        $PAGE = new SearchSailor();
        break;

      case 'sailor-registration':
        $PAGE = new RegisterStudentPane();
        if (!$PAGE->isAvailable()) {
          return HttpResponse::seeOther('/');
        }
        break;

      case 'register':
        $PAGE = new RegisterPane();
        if (!$PAGE->isAvailable()) {
          return HttpResponse::seeOther('/');
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
          return HttpResponse::seeOther($URI);
        }

        Session::s('last_page', $_SERVER['REQUEST_URI']);
        require_once('users/LoginPage.php');
        $PAGE = new LoginPage();
      }

      if (Conf::$METHOD == Conf::METHOD_POST) {
        return $PAGE->processPOST($_POST);
      }

      return $PAGE->processGET($_GET);
    }

    // ------------------------------------------------------------
    // User registered at this point
    // ------------------------------------------------------------
    Conf::$WEBSESSION_LOG = WebsessionLog::record();

    if ($URI_TOKENS[0] == 'license') {
      require_once('users/EULAPane.php');
      $PAGE = new EULAPane(Conf::$USER);
      if (Conf::$METHOD == Conf::METHOD_POST) {
        return $PAGE->processPOST($_POST);
      }

      return $PAGE->processGET($_GET);
    }

    if ($URI_TOKENS[0] == 'logout') {
      $PAGE = new LogoutPage();
      return $PAGE->processGET($_GET);
    }

    // ------------------------------------------------------------
    // Ensure the logged-in user is active
    // ------------------------------------------------------------
    if (Conf::$USER->status === Account::STAT_ACCEPTED) {
      return HttpResponse::seeOther('/license');
    } elseif (Conf::$USER->status !== Account::STAT_ACTIVE) {
      return HttpResponse::seeOther('/logout');
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
            return HttpResponse::seeOther("/score/{$REG->id}");
          }

          if (!$PAGE->isActive()) {
            $title = $PAGE->getTitle();
            Session::pa(new PA("\"$title\" is not available.", PA::I));
            return HttpResponse::seeOther("/score/{$REG->id}");
          }

          // Participant?
          $PAGE->setParticipantUIMode($is_participant);

          // process, if so requested
          if (Conf::$METHOD == Conf::METHOD_POST) {
            return $PAGE->processPOST($_POST);
          }
        }

        // 'view' and 'download' requires GET method only
        if (Conf::$METHOD != Conf::METHOD_GET) {
          return HttpResponse::methodNotAllowed(
            "Only GET method supported for dialogs and downloads.",
            ['Content-Type' => 'text/plain']);
        }

        if ($BASE == 'view') {
          require_once('tscore/AbstractDialog.php');
          $PAGE = AbstractDialog::getDialog($URI_TOKENS, Conf::$USER, $REG);
          if ($PAGE === null) {
            $mes = sprintf("Invalid page requested (%s)", implode('/', $URI_TOKENS));
            Session::pa(new PA($mes, PA::I));

            return HttpResponse::seeOther("/view/{$REG->id}");
          }
        }

        $args = $_GET;
        $post = Session::g('POST');
        if (is_array($post))
          $args = array_merge($post, $args);
        return $PAGE->processGET($args);
      }
      catch (PermissionException $e) {
        Session::pa(new PA($e->getMessage(), PA::E));
        $redirectUrl = '/';
        if ($e->regatta !== null) {
          $redirectUrl = "/score/{$e->regatta->id}";
        }

        return HttpResponse::seeOther($redirectUrl);
      }
    }

    // ------------------------------------------------------------
    // Regular, non-scoring panes
    // ------------------------------------------------------------
    try {
      $PAGE = AbstractUserPane::getPane($URI_TOKENS, Conf::$USER);
      if (Conf::$METHOD == Conf::METHOD_POST) {
        return $PAGE->processPOST($_POST);
      }

      $post = Session::g('POST');
      $args = array_merge((is_array($post)) ? $post : array(), $_GET);
      $response = $PAGE->processGET($args);
      Session::d('POST');
      return $response;
    }
    catch (PaneException $e) {
      Session::pa(new PA($e->getMessage(), PA::E));
    }
    catch (PermissionException $e) {
      Session::pa(new PA($e->getMessage(), PA::E));
    }

    return HttpResponse::seeOther('/');
  }
}
