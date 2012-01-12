<?php
/*
 * This file is part of TechScore
 *
 * @package tscore-dialog
 */

require_once('tscore/AbstractDialog.php');

/**
 * Displays a list of all the registered sailors.
 *
 * @author Dayan Paez
 * @version 2010-01-23
 */
class RegistrationsDialog extends AbstractDialog {

  /**
   * Creates a new registrations dialog
   *
   */
  public function __construct(Regatta $reg) {
    parent::__construct("Record of Participation", $reg);
  }

  protected function fillHTML(Array $args) {
    $divisions = $this->REGATTA->getDivisions();
    $teams     = $this->REGATTA->getTeams();
    $rpManager = $this->REGATTA->getRpManager();

    $this->PAGE->addContent($p = new XPort("Registrations"));
    $p->add($tab = new XQuickTable(array('class'=>'ordinate'), array("Team", "Div.", "Skipper", "Crew")));

    $races_in_div = array();
    foreach ($divisions as $div)
      $races_in_div[(string)$div] = count($this->REGATTA->getRaces($div));
    $row_index = 0;
    foreach ($teams as $team) {
      $row_index++;

      $is_first = true;
      // For each division
      foreach ($divisions as $div) {
	$row = array();
	if ($is_first) {
	  $is_first = false;
          // Removed burgee printing to be fixed later TODO
	  $row[] = $team;
	}
	else {
	  $row[] = "";
	}
	$row[] = $div;

	// Get skipper and crew
	$skips = $rpManager->getRP($team, $div, RP::SKIPPER);
	$crews = $rpManager->getRP($team, $div, RP::CREW);

	// Skippers
	$list = array();
	foreach ($skips as $s) {
	  $li = new XLi($sailor);
	  if (count($s->races_nums) != $races_in_div[(string)$div])
	    $li->add(new XSpan(DB::makeRange($s->races_nums), array('class'=>'races')));
	  $list[] = $li;
	}
	$row[] = (count($list) > 0) ? new XUl(array(), $list) : "";

	// Crews
	$list = array();
	foreach ($crews as $c) {
	  $li = new XLi($sailor);
	  if (count($c->races_nums) != $races_in_div[(string)$div])
	    $li->add(new XSpan(DB::makeRange($c->races_nums), array('class'=>'races')));
	  $list[] = $li;
	}
	$row[] = (count($list) > 0) ? new XUl(array(), $list) : "";
	$tab->addRow($row, array('class'=>'row' . ($row_index % 2)));
      }
    }
  }

  public function isActive() {
    return count($this->REGATTA->getTeams()) > 0;
  }

  public function process(Array $args) {
    return $args;
  }
}
?>
