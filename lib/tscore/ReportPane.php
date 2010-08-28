<?php
/**
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once("conf.php");

/**
 * Edit content and download text version of report
 *
 * @author Dayan Paez
 * @version 2010-08-25
 */
class ReportPane extends AbstractPane {

  public function __construct(User $user, Regatta $reg) {
    parent::__construct("Reports", $user, $reg);
  }

  public function fillHTML(Array $args) {
    $reports = $this->REGATTA->getReports();
    $chosen_report = null;
    if (count($reports) > 0)
      $chosen_report = $reports[0];
    if (isset($args['report']))
      $chosen_report = $this->REGATTA->getReport($args['report']);

    if ($chosen_report !== null) {
      // Download the current one
      $this->PAGE->addContent($p = new Port("Text file (*.txt)"));
      $p->addChild($para = new Para(""));
      $para->addChild(new Link(sprintf("%s/report.txt", $this->REGATTA->id()), "Download"));
      $para->addChild(new Text(" a text version of the report."));
    }
  }

  public function process(Array $args) {
    return $args;
  }

  public function isActive() {
    return (count($this->REGATTA->getRaces()) > 0 &&
	    count($this->REGATTA->getTeams()) > 1);
  }
}
?>