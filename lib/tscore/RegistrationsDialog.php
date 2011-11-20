<?php
/*
 * This file is part of TechScore
 *
 * @package tscore-dialog
 */

require_once("conf.php");

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

    $this->PAGE->addContent($p = new Port("Registrations"));
    $p->addChild($tab = new Table());
    $tab->addAttr("class", "ordinate");
    $tab->addHeader(new Row(array(Cell::th("Team"),
				  Cell::th("Div."),
				  Cell::th("Skipper"),
				  Cell::th("Crew"))));

    $races_in_div = array();
    foreach ($divisions as $div)
      $races_in_div[(string)$div] = count($this->REGATTA->getRaces($div));
    $row_index = 0;
    foreach ($teams as $team) {
      $row_index++;

      $is_first = true;
      // For each division
      foreach ($divisions as $div) {
	$tab->addRow($row = new Row());
	$row->addAttr("class", "row" . $row_index%2);

	if ($is_first) {
	  $is_first = false;
          // Removed burgee printing to be fixed later TODO
	  $row->addCell($c = new Cell(""));
          /*
          new Image($team->school->burgee,
						array("alt"   =>$team->school->nick_name,
						      "height"=>"30px")),
				      array("class"=>array("vertical", "strong"))));
          */
	  $c->addChild(new Text(sprintf("<br/>%s", $team)));
	}
	else {
	  $row->addCell(new Cell(""));
	}
    
	$row->addCell(new Cell($div));

	// Get skipper and crew
	$skips = $rpManager->getRP($team, $div, RP::SKIPPER);
	$crews = $rpManager->getRP($team, $div, RP::CREW);

	// Skippers
	$list = array();
	foreach ($skips as $s) {
	  if (count($s->races_nums) == $races_in_div[(string)$div])
	    $races = "";
	  else
	    $races = Utilities::makeRange($s->races_nums);
	  $list[] = sprintf('%s <span class="races">%s</span>',
			    $s->sailor, $races);
	}
	$row->addCell(new Cell(implode("<br/>", $list)));

	// Crews
	$list = array();
	foreach ($crews as $c) {
	  if (count($c->races_nums) == $races_in_div[(string)$div])
	    $races = "";
	  else
	    $races = Utilities::makeRange($c->races_nums);
	  $list[] = sprintf('%s <span class="races">%s</span>',
			    $c->sailor, $races);
	}
	$row->addCell(new Cell(implode("<br/>", $list)));
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
