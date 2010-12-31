<?php
/**
 * This file is part of TechScore
 *
 * @package tscore
 */
require_once('conf.php');

/**
 * Displays the divisional score table, which summarizes the scores
 * for each team by displaying each division's total.
 *
 * @author Dayan Paez
 * @version 2010-09-06
 */
class ScoresDivisionalDialog extends AbstractScoresDialog {

  /**
   * Create a new rotation dialog for the given regatta
   *
   * @param Regatta $reg the regatta
   */
  public function __construct(Regatta $reg) {
    parent::__construct("Race results in divisions", $reg);
  }

  /**
   * Create and display the score table
   *
   */
  public function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new Port("Team results"));
    $ELEMS = $this->getTable(HOME);
    $p->addChild(array_shift($ELEMS));
    if (count($ELEMS) > 0) {
      $this->PAGE->addContent($p = new Port("Legend"));
      $p->addChild($ELEMS[0]);
    }
  }

  /**
   * Fetches just the table of results
   *
   * @param String $PREFIX the prefix to add to the image URL
   * @return Array the table element
   */
  public function getTable($PREFIX = "") {
    $ELEMS = array();

    $divisions = $this->REGATTA->getDivisions();
    $races = array();
    foreach ($divisions as $div)
      $races[(string)$div] = $this->REGATTA->getScoredRaces($div);
    $num_divs  = count($divisions);

    /*
    // Get finished race array: div => Array<Race>, and determine
    // largest scored race number
    $largest_num = 0;
    $races = array();
    foreach ($divisions as $division) {
      $races[(string)$division] = $this->REGATTA->getScoredRaces($division);
      foreach ($races[(string)$division] as $race)
	$largest_num = max($largest_num, $race->number);
    }
    */

    $tab = new Table();
    $ELEMS[] = $tab;
    $tab->addAttr("id", "div-results");
    $tab->addHeader($r = new Row(array(Cell::th(),
				       Cell::th(),
				       Cell::th("School"),
				       Cell::th("Team"))));
    foreach ($divisions as $div)
      $r->addCell(Cell::th($div), $penalty_th = Cell::th(""));
    $r->addCell(Cell::th("TOT"));

    $has_penalties = false;
    // In order to print the ranks, go through each ranked team once,
    // and collect the different tiebreaking categories, giving each
    // one a successive symbol.
    $tiebreakers = array("Natural order" => "");
    $ranks = $this->REGATTA->scorer->rank($this->REGATTA);
    foreach ($ranks as $rank) {
      if ($rank->explanation != "Natural order" && !isset($tiebreakers[$rank->explanation])) {
	$count = count($tiebreakers);
	switch ($count) {
	case 1:
	  $tiebreakers[$rank->explanation] = "*";
	  break;
	case 2:
	  $tiebreakers[$rank->explanation] = "**";
	  break;
	default:
	  $tiebreakers[$rank->explanation] = chr(95 + $count);
	}
      }
    }

    foreach ($ranks as $tID => $rank) {
      $tab->addRow($r = new Row(array(new Cell($tID + 1),
				      $bc = new Cell(),
				      new Cell($rank->team->school->name, array("class"=>array("strong"))),
				      new Cell($rank->team->name))));
      $url = sprintf("%s/img/schools/%s.png", $PREFIX, $rank->team->school->id);
      $bc->addChild(new Image($url));

      $scoreTeam    = 0;
      // For each division
      foreach ($divisions as $div) {
	$scoreDiv = 0;
	foreach ($races[(string)$div] as $race) {
	  $finish = $this->REGATTA->getFinish($race, $rank->team);
	  $scoreDiv += $finish->score->score;
	}
	$pen = $this->REGATTA->getTeamPenalty($rank->team, $div);
	$r->addCell($s_cell = new Cell());
	$r->addCell($p_cell = new Cell());
	if ($pen !== null) {
	  $scoreDiv += 20;
	  $p_cell->addChild(new Image("img/error.png", array("alt" => "X")));
	  $p_cell->addAttr("title", sprintf("%s (+20 points)", $pen->type));
	}
	$s_cell->addChild(new Text($scoreDiv));
	$s_cell->addAttr("class", "total");
	$scoreTeam += $scoreDiv;
      }
      $r->addCell(new Cell($scoreTeam, array("class"=>array("total"))));
    }
    if ($has_penalties)
      $penalty_th->addText("P");

    // Print legend, if necessary
    if (count($tiebreakers) > 1) {
      $list = new GenericElement("dl");
      $ELEMS[] = $list;
      array_shift($tiebreakers);
      foreach ($tiebreakers as $exp => $ast) {
	$list->addChild(new GenericElement("dt", array(new Text($ast))));
	$list->addChild(new GenericElement("dd", array(new Text($exp))));
      }
    }

    return $ELEMS;
  }
}
?>