<?php
/*
 * This file is part of TechScore
 *
 * @package tscore-dialog
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
    $ELEMS = $this->getTable();
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
   * @param String $link_schools if not null, the prefix to use to
   * link the schools using the school's ID
   *
   * @return Array the table element
   */
  public function getTable($PREFIX = "", $link_schools = null) {
    $ELEMS = array();

    $divisions = $this->REGATTA->getDivisions();
    $races = array();
    foreach ($divisions as $div)
      $races[(string)$div] = $this->REGATTA->getScoredRaces($div);
    $num_divs  = count($divisions);

    $tab = new Table();
    $ELEMS[] = $tab;
    $tab->addAttr("class", "results");
    $tab->addAttr("class", "coordinate");
    $tab->addHeader($r = new Row(array(Cell::th(),
				       Cell::th(),
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
    $tiebreakers = array("" => "");
    $ranks = $this->REGATTA->scorer->rank($this->REGATTA);
    foreach ($ranks as $rank) {
      if (!empty($rank->explanation) && !isset($tiebreakers[$rank->explanation])) {
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

    $row = 0;
    foreach ($ranks as $tID => $rank) {
      $ln = $rank->team->school->name;
      if ($link_schools !== null)
	$ln = new Link(sprintf('%s/%s', $link_schools, $rank->team->school->id), $ln);
      $tab->addRow($r = new Row(array(new Cell($tiebreakers[$rank->explanation],
					       array('title'=>$rank->explanation,
						     'class'=>'tiebreaker')),
				      new Cell($tID + 1),
				      $bc = new Cell(),
				      new Cell($ln, array("class"=>"strong")),
				      new Cell($rank->team->name, array("class"=>"left")))));
      $r->addAttr('class', 'row' . ($row++%2));
      $url = sprintf("%s/img/schools/%s.png", $PREFIX, $rank->team->school->id);
      $bc->addChild(new Image($url, array("height"=>"30px", "alt"=>$rank->team->school->id)));

      $scoreTeam    = 0;
      // For each division
      foreach ($divisions as $div) {
	$scoreDiv = 0;
	foreach ($races[(string)$div] as $race) {
	  $finish = $this->REGATTA->getFinish($race, $rank->team);
	  $scoreDiv += $finish->score;
	}
	$pen = $this->REGATTA->getTeamPenalty($rank->team, $div);
	$r->addCell($s_cell = new Cell());
	$r->addCell($p_cell = new Cell());
	if ($pen !== null) {
	  $scoreDiv += 20;
	  $p_cell->addChild(new Image("$PREFIX/img/error.png", array("alt" => "X")));
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
    if (count($tiebreakers) > 1)
      $ELEMS[] = $this->getLegend($tiebreakers);
    return $ELEMS;
  }
}
?>
