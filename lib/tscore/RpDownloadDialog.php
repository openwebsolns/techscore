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
class RpDownloadDialog extends AbstractDownloadDialog {

  /**
   * Create a new RP download dialog.
   *
   * @param Account $user the user.
   * @param FullRegatta $reg the regatta.
   */
  public function __construct(Account $user, FullRegatta $reg) {
    parent::__construct("Download RP", $user, $reg);
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

    $name = sprintf('%s-%s-rp', $st->format('Y'), $nn);
    $rp = $this->REGATTA->getRpManager();
    if ($rp->isFormRecent())
      $data = $rp->getForm();
    else {
      $form = DB::getRpFormWriter($this->REGATTA);
      if ($form === null) {
        Session::pa(new PA("Downloadable PDF forms are not available for this regatta type.", PA::I));
        $this->redirect();
      }

      $sock = DB::g(STN::PDFLATEX_SOCKET);
      if ($sock === null) {
        $data = $form->makePdf($this->REGATTA);
      }
      else {
        $data = $form->socketPdf($this->REGATTA, $sock);
      }

      if ($data === null) {
        Session::pa(new PA("Downloadable PDF forms are not available for this regatta type.", PA::I));
        $this->redirect();
      }

      $rp->setForm($data);
    }

    header('Content-type: application/pdf');
    header(sprintf('Content-Disposition: attachment; filename="%s.pdf"', $name));
    echo $data;
  }
}