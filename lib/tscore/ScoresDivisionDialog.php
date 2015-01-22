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
   * @param FullRegatta $reg the regatta
   * @param Division $div the division
   * @throws InvalidArgumentException if the division is not in the
   * regatta
   */
  public function __construct(FullRegatta $reg, Division $div) {
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
    $this->PAGE->addContent($p = new XPort(sprintf("Division %s results", $this->division)));
    $races = $this->REGATTA->getScoredRaces($this->division);
    if (count($races) == 0) {
      $p->add(new XP(array('class'=>'warning'), sprintf("There are no finishes for division %s.", $this->division)));
      return;
    }
    $elems = $this->getTable();
    $p->add(array_shift($elems));
    if (count($elems) > 0) {
      $p->add(new XHeading("Tiebreaker legend"));
      $p->add($elems[0]);
    }

    // Also add a chart
    if (count($races) > 1) {
      $this->PAGE->set('xmlns:svg', 'http://www.w3.org/2000/svg');
      $this->PAGE->addContent($p = new XPort(sprintf("Rank history for division %s", $this->division)));
      $p->add(new XP(array(), "The first place team as of a given race will always be at the top of the chart. The spacing from one team to the next shows relative gains/losses made from one race to the next. You may hover over the data points to display the total score as of that race."));

      require_once('xml5/SVGLib.php');
      SVGAbstractElem::$namespace = 'svg';

      require_once('charts/RaceProgressChart.php');
      $maker = new RaceProgressChart($this->REGATTA);
      $chart = $maker->getChart($races, sprintf("Rank history for division %s of %s", $this->division, $this->REGATTA->name));
      $chart->setIncludeHeaders(false);
      $p->add($chart);
    }
  }

  /**
   * Fetches just the table of results
   *
   * @param boolean public_mode true to create links to public resources
   * @return Array the table element
   */
  public function getTable($public_mode = false) {
    $rpManager = $this->REGATTA->getRpManager();
    $division = $this->division;

    $ELEMS = array(new XTable(array('class'=>'results coordinate division ' . $division),
                              array(new XTHead(array(),
                                               array(new XTR(array(),
                                                             array(new XTH(), // superscript
                                                                   new XTH(), // rank
                                                                   new XTH(),
                                                                   new XTH(array('class'=>'teamname'), "Team"),
                                                                   $penalty_th = new XTH(),
                                                                   new XTH(array(), "Total"),
                                                                   new XTH(array(), "Sailors"),
                                                                   new XTH(array(), ""),
                                                                   new XTH(array(), ""))))),
                                    $tab = new XTBody())));
    $has_penalties = false;

    // print each ranked team
    //  - keep track of different ranks and tiebrakers
    $tiebreakers = array("" => "");
    $ranks = $this->REGATTA->getRanks($division);

    $outside_sailors = array();

    if (count($ranks) == 0)
      return array();

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
    $total_races = count($this->REGATTA->getRaces($division));
    foreach ($ranks as $rank) {

      $ln = $rank->team->school->name;
      if ($public_mode !== false)
        $ln = new XA(sprintf('%s%s/', $rank->team->school->getURL(), $this->REGATTA->getSeason()), $ln);

      // deal with explanations
      $sym = $tiebreakers[$rank->explanation];

      // fill the two header rows up until the sailor names column
      $img = $rank->team->school->drawSmallBurgee('');
      $r1 = new XTR(array('class'=>'topborder left row' . $rowIndex % 2),
                    array($r1c1 = new XTD(array('title'=>$rank->explanation, 'class'=>'tiebreaker'), $sym),
                          $r1c2 = new XTD(array(), $order++),
                          $r1c3 = new XTD(array('class'=>'burgee-cell'), $img),
                          $r1c4 = new XTD(array('class'=>'schoolname'), $ln),
                          $r1c5 = new XTD(),
                          $r1c6 = new XTD(array('class'=>'totalcell'), $rank->score)));
      if ($rank->penalty != null) {
        $r1c5->add($rank->penalty);
        $r1c5->set('title', sprintf("%s (+20 points)", $rank->comments));
        $has_penalties = true;
      }

      // We use the default team name instead of the qualified name
      // because we are specifying the sailor names explicitly
      $r2 = new XTR(array('class'=>'left row'.($rowIndex % 2)),
                    array($r2c4 = new XTD(array('class'=>'teamname'), $rank->team->name)));

      $headerRows = array($r1, $r2);
      $num_sailors = 2;
      // ------------------------------------------------------------
      // Skippers and crews
      foreach (array(RP::SKIPPER, RP::CREW) as $index => $role) {
        $sailors  = $rpManager->getRP($rank->team, $division, $role);

        $is_first = true;
        $s_rows = array();
        if (count($sailors) == 0) {
          $headerRows[$index]->add(new XTD()); // name
          $headerRows[$index]->add(new XTD()); // races
          $headerRows[$index]->add(new XTD()); // outside sailors
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

          $sup = "";
          if ($s->sailor !== null && $s->sailor->school != $rank->team->school) {
            if (!isset($outside_sailors[$s->sailor->school->nick_name]))
              $outside_sailors[$s->sailor->school->nick_name] = count($outside_sailors) + 1;
            $sup = $outside_sailors[$s->sailor->school->nick_name];
          }
          $row->add(new XTD(array('class'=>'sailor-name ' . $role), $s->getSailor(true, $public_mode)));
          $row->add(new XTD(array('class'=>'races'), $amt));
          $row->add(new XTD(array('class'=>'superscript'), $sup));
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
      $rowIndex++;
    } // end of table
    if ($has_penalties) {
      $penalty_th->add("P");
    }

    // Print tiebreakers $table
    if (count($tiebreakers) > 1 || count($outside_sailors) > 0)
      $ELEMS[] = $this->getLegend($tiebreakers, $outside_sailors);
    return $ELEMS;
  }
}
