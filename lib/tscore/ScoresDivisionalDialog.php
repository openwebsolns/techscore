<?php
/*
 * This file is part of TechScore
 *
 * @package tscore-dialog
 */

require_once('tscore/AbstractScoresDialog.php');

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
    $this->PAGE->addContent($p = new XPort("Team results"));
    $ELEMS = $this->getTable();
    $p->add(array_shift($ELEMS));
    if (count($ELEMS) > 0) {
      $this->PAGE->addContent($p = new XPort("Legend"));
      $p->add($ELEMS[0]);
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

    $t = new XTable(array('class'=>'results coordinate'),
		    array(new XTHead(array(),
				     array($r = new XTR(array(),
							array(new XTH(),
							      new XTH(),
							      new XTH(),
							      new XTH(array(), "School"),
							      new XTH(array(), "Team"))))),
			  $tab = new XTBody()));
    $ELEMS[] = $t;
    foreach ($divisions as $div) {
      $r->add(new XTH(array(), $div));
      $r->add(new XTH(array('title'=>'Team penalty'), "P"));
    }
    $r->add(new XTH(array(), "TOT"));

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
	$ln = new XA(sprintf('%s/%s', $link_schools, $rank->team->school->id), $ln);
      $tab->add($r = new XTR(array('class'=>'row' . ($row++ % 2)),
			     array(new XTD(array('title'=>$rank->explanation, 'class'=>'tiebreaker'),
					   $tiebreakers[$rank->explanation]),
				   new XTD(array(), $tID + 1),
				   $bc = new XTD(),
				   new XTD(array("class"=>"strong"), $ln),
				   new XTD(array("class"=>"left"), $rank->team->name))));
      $url = sprintf("%s/inc/img/schools/%s.png", $PREFIX, $rank->team->school->id);
      $bc->add(new XImg($url, $rank->team->school->id, array("height"=>"30px")));

      $scoreTeam    = 0;
      // For each division
      foreach ($divisions as $div) {
	$scoreDiv = 0;
	foreach ($races[(string)$div] as $race) {
	  $finish = $this->REGATTA->getFinish($race, $rank->team);
	  $scoreDiv += $finish->score;
	}
	$pen = $this->REGATTA->getTeamPenalty($rank->team, $div);
	$r->add($s_cell = new XTD());
	$r->add($p_cell = new XTD());
	if ($pen !== null) {
	  $scoreDiv += 20;
	  $p_cell->add(new XImg("$PREFIX/inc/img/e.png", "X"));
	  $p_cell->set("title", sprintf("%s (+20 points)", $pen->type));
	}
	$s_cell->add(new XText($scoreDiv));
	$s_cell->set("class", "total");
	$scoreTeam += $scoreDiv;
      }
      $r->add(new XTD(array('class'=>'total'), $scoreTeam));
    }

    // Print legend, if necessary
    if (count($tiebreakers) > 1)
      $ELEMS[] = $this->getLegend($tiebreakers);
    return $ELEMS;
  }
}
?>
