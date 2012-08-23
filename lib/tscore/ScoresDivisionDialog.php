<?php
/**
 * This file is part of TechScore
 *
 * @package tscore-dialog
 */

require_once('tscore/AbstractScoresDialog.php');

/**
 * Displays the scores table for a given regatta's division
 *
 * @author Dayan Paez
 * @version 2010-02-01
 */
class ScoresDivisionDialog extends AbstractScoresDialog {

  private $division;

  /**
   * Create a new rotation dialog for the given regatta
   *
   * @param Regatta $reg the regatta
   * @param Division $div the division
   * @throws InvalidArgumentException if the division is not in the
   * regatta
   */
  public function __construct(Regatta $reg, Division $div) {
    parent::__construct("Race results", $reg);
    if (!in_array($div, $reg->getDivisions())) {
      throw new InvalidArgumentException("No such division ($div) in this regatta.");
    }
    $this->division = $div;
  }

  /**
   * Create and display the score table
   *
   */
  public function fillHTML(Array $args) {
    $division  = $this->division;

    $this->PAGE->addContent($p = new XPort("Division $division results"));
    $elems = $this->getTable();
    $p->add(array_shift($elems));
    if (count($elems) > 0) {
      $p->add(new XHeading("Tiebreaker legend"));
      $p->add($elems[0]);
    }
  }

  /**
   * Fetches just the table of results
   *
   * @param String $PREFIX the prefix to add to image resource URLs
   * @param String $link_schools if not null, the prefix to add to a
   * link from the school's name using the school's ID
   *
   * @return Array the table element
   */
  public function getTable($PREFIX = "", $link_schools = null) {
    $rpManager = $this->REGATTA->getRpManager();
    $division = $this->division;

    $ELEM = array(new XTable(array('class'=>'results coordinate narrow'),
			     array(new XTHead(array(),
					      array(new XTR(array(),
							    array(new XTH(), // superscript
								  new XTH(), // rank
								  new XTH(),
								  new XTH(array(), "Team"),
								  $penalty_th = new XTH(),
								  new XTH(array(), "Total"),
								  new XTH(array(), "Sailors"),
								  new XTH(array(), ""))))),
				   $tab = new XTBody())));
    $has_penalties = false;

    // print each ranked team
    //  - keep track of different ranks and tiebrakers
    $tiebreakers = array("" => "");
    $ranks = $this->REGATTA->scorer->rank($this->REGATTA, $division);
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
    
    $rowIndex = 0;
    $order = 1;
    foreach ($ranks as $rank) {

      // get total score for this team
      $total = 0;
      $races = $this->REGATTA->getScoredRaces($division);
      foreach ($races as $race) {
	$finish = $this->REGATTA->getFinish($race, $rank->team);
	$total += $finish->score;
      }
      $penalty = $this->REGATTA->getTeamPenalty($rank->team, $division);
      if ($penalty != null) {
	$total += 20;
      }

      $ln = $rank->team->school->name;
      if ($link_schools !== null)
	$ln = new XA(sprintf('%s/%s', $link_schools, $rank->team->school->id), $ln);

      // deal with explanations
      $sym = sprintf("<sup>%s</sup>", $tiebreakers[$rank->explanation]);

      // fill the two header rows up until the sailor names column
      $img = ($rank->team->school->burgee == null) ? '' :
	new XImg(sprintf('%s/inc/img/schools/%s.png', $PREFIX, $rank->team->school->id), $rank->team->school->id,
		 array('height'=>'30px'));
      $r1 = new XTR(array('class'=>'topborder row' . $rowIndex % 2, 'align' => 'left'),
		    array(new XTD(array('title'=>$rank->explanation, 'class'=>'tiebreaker'), new XRawText($sym)),
			  $ord = new XTD(array(), $order++),
			  new XTD(array(), $img),
			  $sch = new XTD(array('class'=>'schoolname'), $ln),
			  $pen = new XTD(),
			  new XTD(array('class'=>'totalcell'), $total)));
      if ($penalty != null) {
	$pen->add($penalty->type);
	$pen->set("title", sprintf("%s (+20 points)", $penalty->comments));
      }

      $r2 = new XTR(array('class'=>'row'.($rowIndex % 2), 'align'=>'left'),
		    array(new XTD(),
			  new XTD(),
			  new XTD(),
			  $sch = new XTD(array('class'=>'teamname'), $rank->team->name),
			  new XTD(),
			  new XTD(array('class'=>'totalcell'))));
      
      $headerRows = array($r1, $r2);

      // ------------------------------------------------------------
      // Skippers and crews
      foreach (array(RP::SKIPPER, RP::CREW) as $index => $role) {
	$sailors  = $rpManager->getRP($rank->team, $division, $role);
	    
	$is_first = true;
	$s_rows = array();
	if (count($sailors) == 0) {
	  $headerRows[$index]->add(new XTD());
	  $headerRows[$index]->add(new XTD());
	}
	foreach ($sailors as $s) {
	  if ($is_first) {
	    $row = $headerRows[$index];
	    $is_first = false;
	  }
	  else {
	    $row = new XTR(array('class'=>'row'.($rowIndex % 2)),
			   array(new XTD(),
				 new XTD(),
				 new XTD(),
				 new XTD(),
				 new XTD(),
				 new XTD(array('class'=>'totalcell'))));
	    $s_rows[] = $row;
	  }

	  if (count($s->races_nums) == count($races))
	    $amt = "";
	  else
	    $amt = DB::makeRange($s->races_nums);
	  $row->add($s_cell = new XTD(array('align'=>'right'), $s->sailor));
	  $row->add($r_cell = new XTD(array('align'=>'left', 'class'=>'races'), $amt));
	}

	// Add rows
	$tab->add($headerRows[$index]);
	foreach ($s_rows as $r)
	  $tab->add($r);
      }
      $rowIndex++;
    } // end of table

    // Print tiebreakers $table
    if (count($tiebreakers) > 1)
      $ELEMS[] = $this->getLegend($tiebreakers);
    return $ELEM;
  }
}
