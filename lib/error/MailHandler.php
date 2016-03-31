<?php
namespace error;

use \Conf;
use \DB;
use \TScorePage;
use \XPageTitle;
use \XP;

/**
 * Handles errors and exceptions by sending mail and providing a
 * generic message to the user
 *
 * @author Dayan Paez
 * @version 2012-01-28
 * @package error
 */
class MailHandler extends AbstractErrorHandler {

  public static $CENSORED_POST = array('userid', 'pass');

  public function handleExceptions($e) {
    $fmt = "  - %-7s: %s\n";
    $body  = sprintf($fmt, "Time",   date('Y-m-d H:i:s'));
    $body .= sprintf($fmt, "Number", $e->getCode());
    $body .= sprintf($fmt, "String", $e->getMessage());
    $body .= sprintf($fmt, "File",   $e->getFile());
    $body .= sprintf($fmt, "Line",   $e->getLine());
    $body .= sprintf($fmt, "Request", $_SERVER['REQUEST_URI']);
    $body .= sprintf($fmt, "User", (Conf::$USER !== null) ? Conf::$USER->email : "--");
    $body .= sprintf($fmt, "Browser", (isset($_SERVER['HTTP_USER_AGENT'])) ? $_SERVER['HTTP_USER_AGENT'] : "--");
    $body .= sprintf($fmt, "Method", (isset($_SERVER['REQUEST_METHOD'])) ? $_SERVER['REQUEST_METHOD'] : "--");
    $body .= "\n--------------------\n\n";

    foreach ($e->getTrace() as $list) {
      foreach (array('file', 'line', 'class', 'function') as $index) {
        if (array_key_exists($index, $list)) {
          $body .= sprintf($fmt, ucfirst($index), $list[$index]);
        }
      }
    }

    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST') {
      $body .= "\n--------------------\n\n";
      $body .= "Post:\n";
      
      foreach ($_POST as $key => $val) {
        if (in_array($key, self::$CENSORED_POST))
          $val = str_repeat("*", mb_strlen($val));
        elseif (is_array($val))
          $val = serialize($val);
        $body .= sprintf("%30s: %s\n", $key, $val);
      }
    }
    DB::mail(Conf::$ADMIN_MAIL, sprintf("[Techscore Exception] %s", substr($e->getMessage(), 0, 100)), $body);

    // Prepare XHTML
    require_once('xml5/TScorePage.php');
    $P = new TScorePage("Server error", Conf::$USER);
    $P->addContent(new XPageTitle("Server error"));
    $P->addContent(new XP(array(), "There was an error while handling your request. Administrators have been notified of the problem and it will be addressed as soon as possible."));
    $P->addContent(new XP(array(), "Sorry for the inconvenience."));
    http_response_code(500);
    $P->printXML();
    DB::rollback();
    if (Conf::$WEBSESSION_LOG !== null) {
      Conf::$WEBSESSION_LOG->response_code = '500';
      Conf::$WEBSESSION_LOG->db_commit();
    }
    exit;
  }

}
