<?php
namespace tscore;

use \Session;
use \PA;

use \Account;
use \DB;
use \FullRegatta;
use \STN;

/**
 * Download the filled RP form for a given regatta.
 *
 * Use cached DB version, if possible.
 *
 * @author Dayan Paez
 * @version 2015-03-30
 */
class RpTemplateDownload extends AbstractDownloadDialog {

  /**
   * Create a new RP download dialog.
   *
   * @param Account $user the user.
   * @param FullRegatta $reg the regatta.
   */
  public function __construct(Account $user, FullRegatta $reg) {
    parent::__construct("Download RP Template", $user, $reg);
  }

  /**
   * Returns the RP form as binary data.
   *
   * @param Array $args (unused).
   */
  public function processGET(Array $args) {
    $st = $this->REGATTA->start_time;
    $nn = $this->REGATTA->nick;
    if (count($this->REGATTA->getTeams()) == 0 || count($this->REGATTA->getDivisions()) == 0) {
      Session::pa(new PA("First create teams and divisions before downloading.", PA::I));
      $this->redirect();
    }

    $form = DB::getRpFormWriter($this->REGATTA);
    if ($form === null || ($name = $form->getPdfName()) === null) {
      Session::pa(new PA("Empty PDF forms are not available for this regatta type.", PA::I));
      $this->redirect();
    }

    header('Content-type: application/pdf');
    header(sprintf('Content-Disposition: attachment; filename="%s"', basename($name)));
    echo file_get_contents($name);
  }
}