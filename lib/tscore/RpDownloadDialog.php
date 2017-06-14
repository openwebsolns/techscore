<?php
namespace tscore;

use \Session;
use \PA;

use \Account;
use \DB;
use \FullRegatta;
use \Metric;
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

  const METRIC_FROM_CACHE = 'RpDownloadDialog.cache';
  const METRIC_GENERATED = 'RpDownloadDialog.generated';
  const METRIC_NO_DATA = 'RpDownloadDialog.nodata';
  const METRIC_NO_FORM = 'RpDownloadDialog.noform';
  const METRIC_SUCCESS = 'RpDownloadDialog.success';

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
    if ($rp->isFormRecent()) {
      Metric::publish(self::METRIC_FROM_CACHE);
      $data = $rp->getForm();
    }
    else {
      Metric::publish(self::METRIC_GENERATED);
      $form = DB::getRpFormWriter($this->REGATTA);
      if ($form === null) {
        Metric::publish(self::METRIC_NO_FORM);
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
        Metric::publish(self::METRIC_NO_DATA);
        Session::pa(new PA("Downloadable PDF forms are not available for this regatta type.", PA::I));
        $this->redirect();
      }

      $rp->setForm($data);
    }

    Metric::publish(self::METRIC_SUCCESS);
    header('Content-type: application/pdf');
    header(sprintf('Content-Disposition: attachment; filename="%s.pdf"', $name));
    echo $data;
  }
}