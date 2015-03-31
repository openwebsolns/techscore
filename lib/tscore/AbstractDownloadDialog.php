<?php
namespace tscore;

use \AbstractDialog;

use \Account;
use \FullRegatta;


require_once('AbstractDialog.php');

/**
 * Abstract parent of all panes used for downloading.
 *
 * @author Dayan Paez
 * @version 2015-03-30
 */
abstract class AbstractDownloadDialog extends AbstractDialog {

  /**
   * Not used directly. Instead, child classes should override
   * processGET directly.
   *
   */
  public function fillHTML(Array $args) {
    // Empty, due to overridden processGET.
  }

  /**
   * Overrides parent to do nothing, by default.
   *
   */
  public function processGET(Array $args) {
    // By default, do nothing
  }

  //
  // ROUTING
  //

  /**
   * Returns a new instance of a dialog for the given download URL.
   *
   * Assumption: full URL is of form /download/<reg>/<args/to/this/method>.
   *
   * @see getDialog
   */
  public static function getDownloadDialog(Array $uri, Account $u, FullRegatta $r) {
    if (count($uri) == 0) {
      return null;
    }

    switch ($uri[0]) {
      // --------------- RP FORMS --------------//
    case 'rp':
    case 'rpform':
    case 'rps':
      return new RpDownloadDialog($u, $r);


      // --------------- default ----------------//
    default:
      return null;
    }
  }
}