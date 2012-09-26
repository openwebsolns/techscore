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
   * @param String $link_schools true to include link to schools' season
   * @return Array the table element
   */
  public function getTable($link_schools = false) {
    $ELEMS = array();

    $divisions = $this->REGATTA->getDivisions();
    $num_divs  = count($divisions);

    // Get finished race array: div => Array<Race>, and determine
    // largest scored race number
    $largest_num = 0;
    $races = array();
    foreach ($divisions as $division) {
      $div = (string)$division;
      $races[$div] = array();
      foreach ($this->REGATTA->getScoredRaces($division) as $race) {
        $races[$div][$race->number] = $race;
        $largest_num = max($largest_num, $race->number);
      }
    }

    $ELEMS[] = new XTable(array('class'=>'results coordinate'),
                          array(new XTHead(array(),
                                           array($r = new XTR(array(), array(new XTH(), new XTH(), new XTH(array(), "Team"))))),
                                $tab = new XTBody()));
    if ($num_divs > 1)
      $r->add(new XTH(array(), "Div."));
    for ($i = 1; $i <= $largest_num; $i++) {
      $r->add(new XTH(array('align'=>'right'), $i));
    }
    $r->add($penalty_th = new XTH());
    $r->add(new XTH(array("align"=>"right"), "TOT"));

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

        $tab->add($r = new XTR(array('class'=>"div$div")));

        if ($num_divs == 1) {
          $ln = array($rank->team->name, new XBr(), $rank->team->school->nick_name);
          if ($link_schools !== false)
            $ln = array($rank->team->name, new XBr(),
                        new XA(sprintf('/schools/%s/%s/', $rank->team->school->id, $this->REGATTA->getSeason()),
                               $rank->team->school->nick_name));
          $r->add(new XTD(array("title" => $rank->explanation, "class" => "tiebreaker"), $tiebreakers[$rank->explanation]));
          $r->add(new XTD(array(), $order++));
          $r->add(new XTD(array("class"=>"strong"), $ln));
        }
        elseif ($div == "A") {
          $r->add(new XTD(array("title" => $rank->explanation), $tiebreakers[$rank->explanation]));
          $r->add(new XTD(array(), $order++));
          $r->add(new XTD(array('class'=>'strong'), $rank->team->name));
        }
        elseif ($div == "B") {
          $ln = $rank->team->school->nick_name;
          if ($link_schools !== false)
            $ln = new XA(sprintf('/schools/%s/%s/', $rank->team->school->id, $this->REGATTA->getSeason()), $ln);
          $r->add(new XTD());
          $r->add(new XTD());
          $r->add(new XTD(array(), $ln));
        }
        else {
          $r->add(new XTD());
          $r->add(new XTD());
          $r->add(new XTD());
        }
        if ($num_divs > 1)
          $r->add(new XTD(array("class"=>"strong"), $div));

        // ...for each race
        for ($i = 1; $i <= $largest_num; $i++) {

          // finish and score
          $r->add($cell = new XTD());
          if (isset($raceList[$i])) {
            $race = $raceList[$i];

            // add score for this race to running team score
            $finish = $this->REGATTA->getFinish($race, $rank->team);
            $scoreDiv        += $finish->score;
            $scoreTeam       += $finish->score;
            $scoreRace[$i-1] += $finish->score;

            $cell->add($finish->getPlace());
            $cell->set("title", $finish->explanation);
            $cell->set("align", "right");
          }
        }

        // print penalty, should it exist
        $team_pen = $this->REGATTA->getTeamPenalty($rank->team, new Division($div));
        if ($team_pen !== null) {
          $r->add(new XTD(array('title'=>$team_pen->comments, 'align'=>'right'), $team_pen->type));
          $scoreDiv += 20;
          $penaltyTeam += 20;
          $has_penalties = true;
        }
        else {
          $r->add(new XTD());
        }

        // print total score for division
        $r->add(new XTD(array('align'=>'right'), ($scoreDiv == 0) ? "" : $scoreDiv));
      }

      // write total row
      $tab->add($r = new XTR(array("class"=>"totalrow"), array(new XTD(), new XTD(), $burgee_cell = new XTD())));
      if ($rank->team->school->burgee !== null) {
        $url = sprintf("/inc/img/schools/%s.png", $rank->team->school->id);
        $burgee_cell->add(new XImg($url, $rank->team->school->id, array("height"=>"30px")));
      }
      if ($num_divs > 1)
        $r->add(new XTD());

      for ($i = 0; $i < $largest_num; $i++) {
        $value = array_sum(array_slice($scoreRace, 0, $i + 1));
        $r->add(new XTD(array('class'=>'sum', 'align'=>'right'), $value));
      }

      // print penalty sum, if they exist
      if ($penaltyTeam == 0)
        $r->add(new XTD());
      else
        $r->add(new XTD(array('title' => "Penalty total"), "($penaltyTeam)"));

      // print total
      $r->add(new XTD(array('class'=>'sum total', 'align'=>'right'), $scoreTeam + $penaltyTeam));
    }

    // Print legend, if necessary
    if (count($tiebreakers) > 1)
      $ELEMS[] = $this->getLegend($tiebreakers);
    return $ELEMS;
  }
}
?>
