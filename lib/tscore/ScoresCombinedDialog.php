<?php
/**
 * This file is part of TechScore
 *
 * @package tscore-dialog
 */

require_once('tscore/AbstractScoresDialog.php');

/**
 * Displays the scores table à la Division dialog, but tailored for
 * combined division score (uses ICSASpecialCombinedRanker).
 *
 * @author Dayan Paez
 * @version 2010-02-01
 */
class ScoresCombinedDialog extends AbstractScoresDialog {

  /**
   * Create a new rotation dialog for the given regatta
   *
   * @param Regatta $reg the regatta
   * @throws InvalidArgumentException if not combined division
   */
  public function __construct(Regatta $reg) {
    parent::__construct("Race results", $reg);
    if ($reg->scoring != Regatta::SCORING_COMBINED)
      throw new InvalidArgumentException("Dialog only available to combined division scoring.");
  }

  /**
   * Create and display the score table
   *
   */
  public function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("Division results: combined"));
    if (!$this->REGATTA->hasFinishes()) {
      $p->add(new XP(array('class'=>'warning'), "There are no finishes for this regatta."));
      return;
    }
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
   * @param String $link_schools true to include link to school's season
   * link from the school's name using the school's ID
   *
   * @return Array the table element
   */
  public function getTable($link_schools = false) {
    $rpManager = $this->REGATTA->getRpManager();
    $ELEMS = array(new XTable(array('class'=>'results coordinate division all'),
                              array(new XTHead(array(),
                                               array(new XTR(array(),
                                                             array(new XTH(), // superscript
                                                                   new XTH(), // rank
                                                                   new XTH(),
                                                                   new XTH(array('class'=>'teamname'), "Team"),
                                                                   new XTH(array(), "Div."),
                                                                   $penalty_th = new XTH(),
                                                                   new XTH(array(), "Total"),
                                                                   new XTH(array(), "Sailors"),
                                                                   new XTH(array(), ""))))),
                                    $tab = new XTBody())));
    $has_penalties = false;

    // print each ranked team
    //  - keep track of different ranks and tiebrakers
    $tiebreakers = array("" => "");
    $ranks = $this->REGATTA->getRanks();
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
    $total_races = count($this->REGATTA->getRaces(Division::A()));
    foreach ($ranks as $rank) {

      $ln = $rank->team->school->name;
      if ($link_schools !== false)
        $ln = new XA(sprintf('/schools/%s/%s/', $rank->team->school->id, $this->REGATTA->getSeason()), $ln);

      // deal with explanations
      $sym = $tiebreakers[$rank->explanation];

      // fill the two header rows up until the sailor names column
      $img = ($rank->team->school->burgee == null) ? '' :
        new XImg(sprintf('/inc/img/schools/%s.png', $rank->team->school->id), $rank->team->school->id,
                 array('height'=>'30px'));
      $r1 = new XTR(array('class'=>'topborder row' . $rowIndex % 2, 'align' => 'left'),
                    array($r1c1 = new XTD(array('title'=>$rank->explanation, 'class'=>'tiebreaker'), $sym),
                          $r1c2 = new XTD(array(), $order++),
                          $r1c3 = new XTD(array('class'=>'burgee-cell'), $img),
                          $r1c4 = new XTD(array('class'=>'schoolname'), $ln),
                          $r1cDiv = new XTD(array('class'=>'division'), $rank->division),
                          $r1c5 = new XTD(),
                          $r1c6 = new XTD(array('class'=>'totalcell'), $rank->score)));
      // get total score for this team
      if ($rank->penalty != null) {
        $r1c5->add($rank->penalty);
        $r1c5->set('title', sprintf("%s (+20 points)", $rank->comments));
        $has_penalties = true;
      }

      $r2 = new XTR(array('class'=>'row'.($rowIndex % 2), 'align'=>'left'),
                    array($r2c4 = new XTD(array('class'=>'teamname'), $rank->team->name)));

      $headerRows = array($r1, $r2);
      $num_sailors = 2;
      $team = $this->REGATTA->getTeam($rank->team->id);
      // ------------------------------------------------------------
      // Skippers and crews
      foreach (array(RP::SKIPPER, RP::CREW) as $index => $role) {
        $sailors  = $rpManager->getRP($team, new Division($rank->division), $role);

        $is_first = true;
        $s_rows = array();
        if (count($sailors) == 0) {
          $headerRows[$index]->add(new XTD()); // name
          $headerRows[$index]->add(new XTD()); // races
        }
        foreach ($sailors as $s) {
          if ($is_first) {
            $row = $headerRows[$index];
            $is_first = false;
          }
          else {
            $row = new XTR(array('class'=>'row'.($rowIndex % 2)));
            $s_rows[] = $row;
	    $num_sailors++;
          }

          if (count($s->races_nums) == $total_races)
            $amt = "";
          else
            $amt = DB::makeRange($s->races_nums);
          $row->add($s_cell = new XTD(array('class'=>'sailor-name'), $s->sailor));
          $row->add($r_cell = new XTD(array('class'=>'races'), $amt));
        }

        // Add rows
        $tab->add($headerRows[$index]);
	if ($role == RP::SKIPPER)
	  $r1c4->set('rowspan', (count($s_rows) + 1));
	else
	  $r2c4->set('rowspan', (count($s_rows) + 1));
        foreach ($s_rows as $r)
          $tab->add($r);
      }
      $r1c1->set('rowspan', $num_sailors);
      $r1c2->set('rowspan', $num_sailors);
      $r1c3->set('rowspan', $num_sailors);
      $r1c5->set('rowspan', $num_sailors);
      $r1c6->set('rowspan', $num_sailors);
      $r1cDiv->set('rowspan', $num_sailors);
      $rowIndex++;
    } // end of table
    if ($has_penalties) {
      $penalty_th->add("P");
    }

    // Print tiebreakers $table
    if (count($tiebreakers) > 1)
      $ELEMS[] = $this->getLegend($tiebreakers);
    return $ELEMS;
  }
}
