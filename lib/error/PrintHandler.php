<?php
namespace error;

use \Conf;
use \DB;
use \TScorePage;
use \XHeading;
use \XLi;
use \XPageTitle;
use \XP;
use \XQuickTable;
use \XUl;

/**
 * An error handler which prints the error and its backtrace directly
 * to the screen as an XHTML page.
 *
 * @author Dayan Paez
 * @version 2012-01-28
 * @package error
 */
class PrintHandler extends AbstractErrorHandler {

  public function handleExceptions($e) {
    require_once('xml5/TScorePage.php');
    $P = new TScorePage("Exception");
    $P->addContent(new XPageTitle("Exception!"));
    $P->addContent(new XUl(array(),
                           array(new XLi("Number: " . $e->getCode()),
                                 new XLi("Message: " . $e->getMessage()),
                                 new XLi("File: " . $e->getFile()),
                                 new XLi("Line: " . $e->getLine()))));
    $P->addContent($tab = new XQuickTable(array(), array("No.", "Function", "File", "Line")));
    foreach ($e->getTrace() as $i => $trace) {
      $func = (isset($trace['function'])) ? $trace['function'] : 'N/A';
      $file = (isset($trace['file'])) ? $trace['file'] : 'N/A';
      $line = (isset($trace['line'])) ? $trace['line'] : 'N/A';
      $tab->addRow(array(($i + 1), $func, $file, $line));
    }
    if (!headers_sent()) {
      http_response_code(500);
    }
    $P->printXML();
    DB::rollback();
    if (Conf::$WEBSESSION_LOG !== null) {
      Conf::$WEBSESSION_LOG->response_code = '500';
      Conf::$WEBSESSION_LOG->db_commit();
    }
    exit;
  }
}
