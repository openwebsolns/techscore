<?php
/**
 * This file is part of TechScore
 *
 * @package tscore
 */
require_once('conf.php');

/**
 * Displays the full scores table for a given regatta. When there's
 * only one division, omits the division column.
 *
 */
class ScoresFullDialog extends AbstractScoresDialog {

  /**
   * Create a new rotation dialog for the given regatta
   *
   * @param Regatta $reg the regatta
   */
  public function __construct(Regatta $reg) {
    parent::__construct("Race results", $reg);
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
   * @param String $PREFIX the prefix to add to image resource URLs
   * @return Array the table element
   */
  public function getTable($PREFIX = "") {
    $ELEMS = array();

    $divisions = $this->REGATTA->getDivisions();
    $num_divs  = count($divisions);

    // Get finished race array: div => Array<Race>, and determine
    // largest scored race number
    $largest_num = 0;
    $races = array();
    foreach ($divisions as $division) {
      $races[(string)$division] = $this->REGATTA->getScoredRaces($division);
      foreach ($races[(string)$division] as $race)
	$largest_num = max($largest_num, $race->number);
    }

    $tab = new Table();
    $ELEMS[] = $tab;
    $tab->addAttr("class", "results");
    $tab->addAttr("class", "coordinate");
    $tab->addHeader($r = new Row(array(Cell::th(),
				       Cell::th(),
				       Cell::th("Team"))));
    if ($num_divs > 1)
      $r->addCell(Cell::th("Div."));
    for ($i = 1; $i <= $largest_num; $i++) {
      $r->addCell($c = Cell::th($i));
      $c->addAttr("align", "right");
    }
    $r->addCell($penalty_th = Cell::th(""),
		new Cell("TOT", array("align"=>"right"), 1));

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

    $has_penalties = false;
    $order = 1;
    foreach ($ranks as $rank) {
      $scoreTeam   = 0;
      $scoreRace   = ($largest_num == 0) ? array() : array_fill(0, $largest_num, 0);
      $penaltyTeam = 0;

      // For each division... and race...
      foreach ($races as $div => $raceList) {
	$scoreDiv = 0;

	$tab->addRow($r = new Row());
	$r->addAttr("class", "div" . $div);

	if ($num_divs == 1) {
	  $r->addCell(new Cell(sprintf('<sub>%s</sub>', $tiebreakers[$rank->explanation])),
		      new Cell($order++, array("title" => $rank->explanation)),
		      new Cell($rank->team->name . '<br/>' . $rank->team->school->nick_name,
			       array("class"=>"strong")));
	}
	elseif ($div == "A") {
	  $r->addCell(new Cell(sprintf('<sub>%s</sub>', $tiebreakers[$rank->explanation])),
		      new Cell($order++, array("title" => $rank->explanation)),
		      new Cell($rank->team->name,
			       array("class"=>"strong")));
	}
	elseif ($div == "B") {
	  $r->addCell(new Cell(),
		      new Cell(),
		      new Cell($rank->team->school->nick_name));
	}
	else {
	  $r->addCell(new Cell(), new Cell(), new Cell());
	}
	if ($num_divs > 1)
	  $r->addCell(new Cell($div, array("class"=>"strong")));

	// ...for each race
	for ($i = 1; $i <= $largest_num; $i++) {

	  // finish and score
	  $race = Preferences::getObjectWithProperty($raceList, "number", $i);
	  $cell = new Cell();
	  if ($race != null) {

	    // add score for this race to running team score
	    $finish = $this->REGATTA->getFinish($race, $rank->team);
	    $scoreDiv        += $finish->score->score;
	    $scoreTeam       += $finish->score->score;
	    $scoreRace[$i-1] += $finish->score->score;

	    $cell->addChild(new Text($finish->score->place));
	    $cell->addAttr("title", $finish->score->explanation);
	    $cell->addAttr("align", "right");
	  }
	  $r->addCell($cell);
	}

	// print penalty, should it exist
	$team_pen = $this->REGATTA->getTeamPenalties($rank->team, new Division($div));
	if (count($team_pen) > 0) {
	  $team_pen = array_shift($team_pen);
	  $r->addCell(new Cell($team_pen->type,
			       array("title"=>$team_pen->comments,
				     "align"=>"right")));
	  $scoreDiv += 20;
	  $penaltyTeam += 20;
	  $has_penalties = true;
	}
	else {
	  $r->addCell(new Cell());
	}

	// print total score for division
	$r->addCell(new Cell($scoreDiv, array("align"=>"right")));
      }

      // write total row
      $tab->addRow($r = new Row(array(), array("class"=>"totalrow")));
      $r->addCell(new Cell());
      $r->addCell(new Cell());
      $r->addCell($burgee_cell = new Cell());

      if ($rank->team->school->burgee !== null) {
	$url = sprintf("%s/img/schools/%s.png", $PREFIX, $rank->team->school->id);
	$burgee_cell->addChild(new Image($url, array("alt"=>$rank->team->school->id, "height"=>"30px")));
      }
      if ($num_divs > 1)
	$r->addCell(new Cell());

      for ($i = 0; $i < $largest_num; $i++) {
	$value = array_sum(array_slice($scoreRace, 0, $i + 1));
	$r->addCell(new Cell($value,
			     array("class"=>"sum",
				   "align"=>"right")));
      }

      // print penalty sum, if they exist
      if ($penaltyTeam == 0)
	$r->addCell(new Cell());
      else
	$r->addCell(new Cell(sprintf("(%s)", $penaltyTeam),
			     array("title" => "Penalty total")));

      // print total
      $r->addCell(new Cell($scoreTeam + $penaltyTeam,
			   array("class"=>"sum total",
				 "align"=>"right")));
    }

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