<?php
/*
 * This file is part of TechScore
 *
 * @package tscore-dialog
 */

require_once('tscore/AbstractScoresDialog.php');

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
    // $this->PAGE->set('xmlns:ts', 'http://collegesailing.info');
    $this->PAGE->addContent($p = new Port("Team results"));
    $ELEMS = $this->getTable();
    $p->add(array_shift($ELEMS));
    if (count($ELEMS) > 0) {
      $this->PAGE->addContent($p = new Port("Legend"));
      $p->add($ELEMS[0]);
    }
  }

  /**
   * Fetches just the table of results
   *
   * @param String $PREFIX the prefix to add to image resource URLs
   * @param String $link_schools if not null, the prefix for linking schools
   * @return Array the table element
   */
  public function getTable($PREFIX = "", $link_schools = null) {
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
    $tab->set("class", "results");
    $tab->set("class", "coordinate");
    $tab->addHeader($r = new Row(array(Cell::th(),
				       Cell::th(),
				       Cell::th("Team"))));
    if ($num_divs > 1)
      $r->addCell(Cell::th("Div."));
    for ($i = 1; $i <= $largest_num; $i++) {
      $r->addCell($c = Cell::th($i));
      $c->set("align", "right");
    }
    $r->addCell($penalty_th = Cell::th(""),
		new Cell("TOT", array("align"=>"right"), 1));

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
	$r->set("class", "div" . $div);

	if ($num_divs == 1) {
	  $ln = $rank->team->name . '<br/>' . $rank->team->school->nick_name;
	  if ($link_schools !== null)
	    $ln = new XSpan(array(new XRawText($rank->team->name), new XBr(),
				  new XA(sprintf('%s/%s', $link_schools, $rank->team->school->id),
					   $rank->team->school->nick_name)));
	  $r->addCell(new Cell($tiebreakers[$rank->explanation], array("title" => $rank->explanation,
								       "class" => "tiebreaker")),
		      new Cell($order++),
		      new Cell($ln, array("class"=>"strong")));
	}
	elseif ($div == "A") {
	  $r->addCell(new Cell($tiebreakers[$rank->explanation], array("title" => $rank->explanation)),
		      new Cell($order++),
		      new Cell($rank->team->name,
			       array("class"=>"strong")));
	}
	elseif ($div == "B") {
	  $ln = $rank->team->school->nick_name;
	  if ($link_schools !== null)
	    $ln = new XA(sprintf('%s/%s', $link_schools, $rank->team->school->id), $ln);
	  $r->addCell(new Cell(),
		      new Cell(),
		      new Cell($ln));
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
	    $scoreDiv        += $finish->score;
	    $scoreTeam       += $finish->score;
	    $scoreRace[$i-1] += $finish->score;

	    $cell->add(new XText($finish->place));
	    $cell->set("title", $finish->explanation);
	    $cell->set("align", "right");
	    $cell->set("ts:score", $finish->score);
	  }
	  $r->addCell($cell);
	}

	// print penalty, should it exist
	$team_pen = $this->REGATTA->getTeamPenalty($rank->team, new Division($div));
	if ($team_pen !== null) {
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
	$burgee_cell->add(new XImg($url, $rank->team->school->id, array("height"=>"30px")));
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
    if (count($tiebreakers) > 1)
      $ELEMS[] = $this->getLegend($tiebreakers);
    return $ELEMS;
  }
}
?>
