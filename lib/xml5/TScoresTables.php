<?php
/**
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @created 2011-03-06
 * @package xml5
 */

require_once('HtmlLib.php');

/**
 * Generates the necessary tables (only once) for the given regatta
 *
 * @author Dayan Paez
 * @version 2011-03-06
 */
class TScoresTables {

  private $full_tables = array();
  private $summary_tables = array();
  private $div_tables = array();

  private $REGATTA;

  /**
   * Creates a new table maker for the given regatta
   *
   * @param Regatta $reg the regatta to summarize
   */
  public function __construct(Dt_Regatta $reg) {
    $this->REGATTA = $reg;
  }

  /**
   * Returns the full scores table for the given regatta
   *
   * @return XTable|String either XTable or a string explaining why there
   * is no table
   */
  public function getFullTables() {
    if (count($this->full_tables) > 0) return $this->full_tables;

    $num_divs  = $this->REGATTA->num_divisions;
    $bank = 'ABCD';
    $divisions = array();
    for ($i = 0; $i < $num_divs; $i++)
      $divisions[] = $bank[$i];

    // Get finished race array: div => Array<Race>, and determine
    // largest scored race number
    $largest_num = 0;
    $races = array();
    foreach ($divisions as $division) {
      $races[$division] = array();
      foreach ($this->REGATTA->getScoredRaces($division) as $race) {
	$races[$division][$race->number] = $race;
	$largest_num = max($largest_num, $race->number);
      }
    }

    $this->full_tables[] = new XTable(array('class'=>'results coordinate'),
				      array(new XTHead(array(),
						       array($r = new XTR(array(),
									  array(new XTH(array(), '#'),
										new XTH(array(), 'Team'))))),
					    $tab = new XTBody()));
    if ($num_divs > 1)
      $r->add(new XTH(array(), "Div."));
    for ($i = 1; $i <= $largest_num; $i++)
      $r->add(new XTH(array('align'=>'right'), $i));
    $r->add($penalty_th = new XTH(array('title'=>"Penalty", 'abbr'=>"Penalty"), ""));
    $r->add(new XTH(array('title'=>"Total", 'abbr'=>"Total", 'align'=>'right'), "TOT"));

    // Teams are automagically ranked already in dt_team
    $ranks = $this->REGATTA->getTeams();
    $tiebreakers = array("" => "");
    foreach ($ranks as $team) {
      if (!empty($team->rank_explanation) && !isset($tiebreakers[$team->rank_explanation])) {
	$count = count($tiebreakers);
	switch ($count) {
	case 1:
	  $tiebreakers[$team->rank_explanation] = "*";
	  break;
	case 2:
	  $tiebreakers[$team->rank_explanation] = "**";
	  break;
	default:
	  $tiebreakers[$team->rank_explanation] = chr(95 + $count);
	}
      }
    }

    $has_penalties = false;
    $order = 1;
    foreach ($ranks as $team) {
      $scoreTeam   = 0;
      $scoreRace   = ($largest_num == 0) ? array() : array_fill(0, $largest_num, 0);
      $penaltyTeam = 0;

      // For each division... and race...
      foreach ($races as $div => $raceList) {
	$scoreDiv = 0;

	$tab->add($r = new XTR(array('class'=>"div$div")));
	$rank = ($team->rank_explanation != "") ?
	  new XSpan($tiebreakers[$team->rank_explanation], array('class'=>'tiebreaker')) : "";
	if ($num_divs == 1) {
	  $r->add(new XTD(array('title'=>$team->rank_explanation, 'rowspan'=>2),
			  array($rank, $order++)));
	  $r->add(new XTD(array('class'=>'strong'),
			  array($team->name,
				new XBR(),
				new XA(sprintf('/schools/%s', $team->school->id), $team->school->nick_name))));
	}
	elseif ($div == 'A') {
	  $r->add(new XTD(array('title'=>$team->rank_explanation, 'rowspan'=>($num_divs + 1)),
			  array($rank, $order++)));
	  $r->add(new XTD(array('class'=>'strong'), $team->name));
	}
	elseif ($div == "B") {
	  $r->add(new XTD(array(), new XA(sprintf('/schools/%s', $team->school->id), $team->school->nick_name)));
	}
	else {
	  $r->add(new XTD(array('class'=>'empty')));
	}
	if ($num_divs > 1)
	  $r->add(new XTD(array("class"=>"strong"), $div));

	// ...for each race
	for ($i = 1; $i <= $largest_num; $i++) {

	  // finish and score
	  $cell = new XTD(array('axis'=>$div));
	  if (!isset($raceList[$i])) {
	    $cell->set('class', 'empty');
	    $cell->set('title', "No finish registered");
	  }
	  else {
	    $race = $raceList[$i];

	    // add score for this race to running team score
	    $finish = $this->REGATTA->getFinish($race, $team);
	    $scoreDiv        += $finish->score;
	    $scoreTeam       += $finish->score;
	    $scoreRace[$i-1] += $finish->score;

	    $cell->add($finish->place);
	    $cell->set('title', $finish->explanation);
	    $cell->set('align', 'right');
	  }
	  $r->add($cell);
	}

	// print penalty, should it exist
	$team_pen = $team->getRank($div);
	if ($team_pen !== null && $team_pen->penalty !== null) {
	  $r->add(new XTD(array('align'=>'right', 'title'=>$team_pen->comments), $team_pen->penalty));
	  $scoreDiv += 20;
	  $penaltyTeam += 20;
	  $has_penalties = true;
	}
	else {
	  $r->add(new XTD(array('class'=>'empty')));
	}

	// print total score for division
	$r->add(new XTD(array('align'=>'right'), $scoreDiv));
      }

      // write total row
      $tab->add($r = new XTR(array('class'=>'totalrow')));
      $r->add($burgee_cell = new XTD());

      $burgee_root = '../../html/inc/img/schools/';
      if (file_exists($burgee_root . $team->school->id)) {
	$url = sprintf('/inc/img/schools/%s.png', $team->school->id);
	$burgee_cell->add(new XImg($url, $team->school->id, array('height'=>30)));
      }
      if ($num_divs > 1)
	$r->add(new XTD(array('class'=>'empty')));

      for ($i = 0; $i < $largest_num; $i++) {
	$value = array_sum(array_slice($scoreRace, 0, $i + 1));
	$r->add(new XTD(array('class'=>'sum', 'align'=>'right'), $value));
      }

      // print penalty sum, if they exist
      if ($penaltyTeam == 0)
	$r->add(new XTD(array('class'=>'empty')));
      else
	$r->add(new XTD(array('title'=>'Penalty total'), '('.$penaltyTeam.')'));

      // print total
      $r->add(new XTD(array('class'=>'sum total', 'align'=>'right'), $scoreTeam + $penaltyTeam));
    }

    // Print legend, if necessary
    if (count($tiebreakers) > 1)
      $this->full_tables[] = $this->getLegend($tiebreakers);
    return $this->full_tables;
  }

  /**
   * Fetches the summary table of results
   *
   * @return Array the table element
   */
  public function getSummaryTables() {
    if (count($this->summary_tables) > 0) return $this->summary_tables;

    $num_divs  = $this->REGATTA->num_divisions;
    $bank = 'ABCD';
    $divisions = array();
    for ($i = 0; $i < $num_divs; $i++)
      $divisions[] = $bank[$i];

    $races = array();
    foreach ($divisions as $div)
      $races[$div] = $this->REGATTA->getScoredRaces($div);

    $this->summary_tables[] =
      new XTable(array('class'=>'results coordinate'),
		 array(new XTHead(array(),
				  array($r = new XTR(array(),
						     array(new XTH(array(), "#"),
							   new XTH(),
							   new XTH(array(), "School"),
							   new XTH(array(), "Team"))))),
		       $tab = new XTBody()));
    foreach ($divisions as $div) {
      $r->add(new XTH(array(), $div));
      $r->add(new XTH(array('title'=>"Penalty in division $div")));
    }
    $r->add(new XTH(array('title'=>"Total for team", 'abbr'=>"Total"), "TOT"));

    // In order to print the ranks, go through each ranked team once,
    // and collect the different tiebreaking categories, giving each
    // one a successive symbol.
    $tiebreakers = array("" => "");
    $teams = $this->REGATTA->getTeams();
    foreach ($teams as $team) {
      if (!empty($team->rank_explanation) && !isset($tiebreakers[$team->rank_explanation])) {
	$count = count($tiebreakers);
	switch ($count) {
	case 1:
	  $tiebreakers[$team->rank_explanation] = "*";
	  break;
	case 2:
	  $tiebreakers[$team->rank_explanation] = "**";
	  break;
	default:
	  $tiebreakers[$team->rank_explanation] = chr(95 + $count);
	}
      }
    }

    $row = 0;
    foreach ($teams as $order => $team) {
      $ln = new XA(sprintf('/schools/%s', $team->school->id), $team->school->name);
      $xp = ($team->rank_explanation != "") ?
	new XSpan($tiebreakers[$team->rank_explanation], array('title'=>$team->rank_explanation)) : "";
      
      $tab->add($r = new XTR(array('class'=>'row'.($row++%2)),
			     array(new XTD(array('title'=>$team->rank_explanation),
					   array($xp, $order + 1)),
				   $bc = new XTD(),
				   new XTD(array('class'=>'strong'), $ln),
				   new XTD(array('class'=>'left', 'align'=>'left'), $team->name))));
      if (realpath(sprintf('%s/../../html/inc/img/schools/%s.png', dirname(__FILE__), $team->school->id)))
	$bc->add(new XImg(sprintf('/inc/img/schools/%s.png', $team->school->id), $team->school->name,
			  array('height'=>30)));
						 
      $scoreTeam    = 0;
      // For each division
      foreach ($divisions as $div) {
	$scoreDiv = 0;
	foreach ($races[$div] as $race) {
	  $finish = $this->REGATTA->getFinish($race, $team);
	  $scoreDiv += $finish->score;
	}

	// penalty
	$img = "";
	$pen = $team->getRank($div);
	if ($pen !== null && $pen->penalty !== null) {
	  $scoreDiv += 20;
	  $img = new XImg('inc/img/error.png', 'X',
			  array('title'=>sprintf('(%s, +20 points) %s', $pen->penalty, $pen->comments)));
	}
	$r->add(new XTD(array(), $scoreDiv));
	$r->add(new XTD(array(), $img));
	$scoreTeam += $scoreDiv;
      }
      $r->add(new XTD(array('class'=>'total'), $scoreTeam));
    }

    // Print legend, if necessary
    if (count($tiebreakers) > 1)
      $this->summary_tables[] = $this->getLegend($tiebreakers);
    return $this->summary_tables;
  }

  public function getDivisionTables($division) {
    if (isset($this->div_tables[$division])) return $this->div_tables[$division];

    $this->div_tables[$division] = array();
    $this->div_tables[$division][] =
      new XTable(array('class'=>'results coordinate'),
		 array(new XTHead(array(),
				  array(new XTR(array(),
						array(new XTH(array(), "#"),
						      new XTH(),
						      new XTH(array(), "Team"),
						      new XTH(array('title'=>"Penalty for division $division")),
						      new XTH(array(), "Total"),
						      new XTH(array(), "Sailors"),
						      new XTH(array(), "Races"))))),
		       $tab = new XTBody()));

    // print each ranked team
    //  - keep track of different ranks and tiebrakers
    $tiebreakers = array("" => "");
    $teams = $this->REGATTA->getRanks($division);
    foreach ($teams as $rank) {
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
    
    $rowIndex = 0;
    $order = 1;
    foreach ($teams as $rank) {

      // get total score for this team
      $total = 0;
      $races = $this->REGATTA->getScoredRaces($division);
      foreach ($races as $race) {
	$finish = $this->REGATTA->getFinish($race, $rank->team);
	$total += $finish->score;
      }
      if ($rank->penalty != null) {
	$total += 20;
      }

      $ln = new XA(sprintf('/schools/%s/', $rank->team->school->id), $rank->team->school->name);
      $xp = ($rank->explanation != "") ?
	new XSpan($tiebreakers[$rank->explanation], array('class'=>'tiebreaker')) : "";

      $img = "";
      if (realpath(sprintf('%s/../../html/inc/img/schools/%s.png', dirname(__FILE__), $rank->team->school->id)))
	$img = new XImg(sprintf('/inc/img/schools/%s.png', $rank->team->school->id), $rank->team->school->id,
			array('height'=>40));

      $r1 = new XTR(array('class'=>'row'.$rowIndex % 2),
		    array($tie_td = new XTD(array('title'=>$rank->explanation), array($xp, $order++)),
			  $bur_td = new XTD(array(), $img),
			  new XTD(array('class'=>'strong', 'align'=>'left'), $ln),
			  $pen = new XTD(),
			  new XTD(array(), $total)));
      if ($rank->penalty !== null) {
	$pen->add($rank->penalty);
	$pen->set('title', sprintf('%s, +20 points) %s', $rank->penalty, $rank->comments));
      }

      $r2 = new XTR(array('class'=>'row'.$rowIndex % 2),
		    array(new XTD(array('align'=>'left'), $rank->team->name),
			  new XTD(array('class'=>'empty')),
			  new XTD(array('class'=>'empty'))));

      $headerRows = array($r1, $r2);

      $num_rows = 2;
      // ------------------------------------------------------------
      // Skippers and crews
      foreach (array('skipper', 'crew') as $index => $role) {
	$sailors = array();
	$rpraces = array();
	foreach ($rank->team->getRP($division, $role) as $rp) {
	  if (!isset($sailors[$rp->sailor->id])) {
	    $sailors[$rp->sailor->id] = $rp->sailor;
	    $rpraces[$rp->sailor->id] = array();
	  }
	  $rpraces[$rp->sailor->id][$rp->race->number] = $rp->race->number;
	}
	    
	$is_first = true;
	$s_rows = array();
	if (count($sailors) == 0) {
	  $headerRows[$index]->add(new XTD(array('class'=>'empty')));
	  $headerRows[$index]->add(new XTD(array('class'=>'empty')));
	}
	foreach ($sailors as $id => $s) {
	  if ($is_first) {
	    $row = $headerRows[$index];
	    $is_first = false;
	  }
	  else {
	    $row = new XTR(array('class'=>'row'.$rowIndex % 2),
			   array(new XTD(array('class'=>'empty')),
				 new XTD(array('class'=>'empty')),
				 new XTD(array('class'=>'empty')),
				 new XTD(array('class'=>'empty'))));
	    $s_rows[] = $row;
	  }

	  if (count($rpraces[$id]) == count($races))
	    $amt = "";
	  else
	    $amt = $this->makeRange($rpraces[$id]);
	  $row->add(new XTD(array('align'=>'right'), $s));
	  $row->add(new XTD(array('align'=>'left', 'class'=>'races'), $amt));
	}

	// Add rows
	$tab->add($headerRows[$index]);
	foreach ($s_rows as $r)
	  $tab->add($r);

	$num_rows += count($s_rows);
      }
      $tie_td->set('rowspan', $num_rows);
      $bur_td->set('rowspan', $num_rows);
      $rowIndex++;
    } // end of table

    // Print tiebreakers $table
    if (count($tiebreakers) > 1)
      $this->div_tables[$division][] = $this->getLegend($tiebreakers);
    return $this->div_tables[$division];
  }

  /**
   * Prepares the tiebreakers legend element (now a table) and returns it.
   *
   * @param Array $tiebreaker the associative array of symbol => explanation
   * @return GenericElement probably a table
   */
  protected function getLegend($tiebreakers) {
    $tab = new XQuickTable(array('class'=>'legend'), array("Sym.", "Explanation"));
    array_shift($tiebreakers);
    foreach ($tiebreakers as $exp => $ast)
      $tab->addRow(array($ast, $exp));
    return $tab;
  }

  protected function makeRange(Array $nums) {
    if (count($nums) == 0) return "";

    // and sorted
    sort($nums, SORT_NUMERIC);
    
    $last = array_shift($nums);
    $next = null;
    $close = null;
    $txt = $last;
    while (count($nums) > 0) {
      $next = array_shift($nums);
      if ($next == $last + 1)
	$close = $next;
      else {
	if ($close !== null)
	  $txt .= ('-'.$close);
	$txt .= (','.$next);
	$close = null;
      }
      $last = $next;
    }
    if ($close !== null)
      $txt .= ('-'.$close);
    return $txt;
  }
}

if (isset($argv) && basename(__FILE__) == basename($argv[0])) {
  $_SERVER['HTTP_HOST'] = 'cli';
  require_once('../conf.php');
  require_once('mysqli/DB.php');
  DBME::setConnection(Preferences::getConnection());

  $t = new TScoresTables(DBME::get(DBME::$REGATTA, 6));
  $r = $t->getFullTable();
  file_put_contents('/tmp/test.html', $r->toXML());

  echo memory_get_peak_usage();
}
?>