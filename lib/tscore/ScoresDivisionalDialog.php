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
   * @param FullRegatta $reg the regatta
   */
  public function __construct(FullRegatta $reg) {
    parent::__construct("Race results in divisions", $reg);
  }

  /**
   * Create and display the score table
   *
   */
  public function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("Team results"));
    if (!$this->REGATTA->hasFinishes()) {
      $p->add(new XP(array('class'=>'warning'), "There are no finishes for this regatta."));
      return;
    }
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
   * @param String $link_schools true to link to school's season page
   * link the schools using the school's ID
   *
   * @return Array the table element
   */
  public function getTable($link_schools = false) {
    $ELEMS = array();

    $divisions = $this->REGATTA->getDivisions();

    $t = new XTable(array('class'=>'results coordinate divisional'),
                    array(new XTHead(array(),
                                     array($r = new XTR(array(),
                                                        array(new XTH(),
                                                              new XTH(),
                                                              new XTH(),
                                                              new XTH(array(), "School"),
                                                              new XTH(array(), "Team"))))),
                          $tab = new XTBody()));
    $ELEMS[] = $t;
    $penalty_th = array();
    $division_has_penalty = array();
    foreach ($divisions as $div) {
      $r->add(new XTH(array(), $div));
      $r->add($th = new XTH(array('title'=>"Team penalty in division $div"), ""));

      $penalty_th[(string)$div] = $th;
      $division_has_penalty[(string)$div] = false;
    }
    $r->add(new XTH(array('title'=>"Total for team", 'abbr'=>"Total"), "TOT"));

    // In order to print the ranks, go through each ranked team once,
    // and collect the different tiebreaking categories, giving each
    // one a successive symbol.
    $tiebreakers = array("" => "");
    $ranks = $this->REGATTA->getRankedTeams();
    foreach ($ranks as $rank) {
      if (!empty($rank->dt_explanation) && !isset($tiebreakers[$rank->dt_explanation])) {
        $count = count($tiebreakers);
        switch ($count) {
        case 1:
          $tiebreakers[$rank->dt_explanation] = "*";
          break;
        case 2:
          $tiebreakers[$rank->dt_explanation] = "**";
          break;
        default:
          $tiebreakers[$rank->dt_explanation] = chr(95 + $count);
        }
      }
    }

    $row = 0;
    foreach ($ranks as $tID => $rank) {
      $ln = $rank->school->name;
      if ($link_schools !== false)
        $ln = new XA(sprintf('/schools/%s/%s/', $rank->school->id, $this->REGATTA->getSeason()), $ln);
      $tab->add($r = new XTR(array('class'=>'row' . ($row++ % 2)),
                             array(new XTD(array('title'=>$rank->dt_explanation, 'class'=>'tiebreaker'),
                                           $tiebreakers[$rank->dt_explanation]),
                                   new XTD(array(), $tID + 1),
                                   $bc = new XTD(array('class'=>'burgee-cell')),
                                   new XTD(array('class'=>'strong'), $ln),
                                   new XTD(array('class'=>'left'), $rank->getQualifiedName()))));
      if ($rank->school->burgee !== null) {
        $url = sprintf('/inc/img/schools/%s.png', $rank->school->id);
        $bc->add(new XImg($url, $rank->school->id, array('height'=>'30px')));
      }

      $scoreTeam    = 0;
      // For each division
      foreach ($divisions as $div) {
        $r->add($s_cell = new XTD());
        $r->add($p_cell = new XTD());

        if (($div_rank = $rank->getRank($div)) === null)
          continue;

        if ($div_rank->penalty !== null) {
          $p_cell->add($div_rank->penalty);
          $p_cell->set('title', "+20 points: " . $div_rank->comments);

          $division_has_penalty[(string)$div] = true;
        }
        $s_cell->add(new XText($div_rank->score));
        $s_cell->set("class", "total");
        $scoreTeam += $div_rank->score;
      }
      $r->add(new XTD(array('class'=>'totalcell'), $scoreTeam));
    }

    // Deal with penalty headers
    foreach ($division_has_penalty as $id => $val) {
      if ($val)
        $penalty_th[$id]->add("P");
    }

    // Print legend, if necessary
    if (count($tiebreakers) > 1)
      $ELEMS[] = $this->getLegend($tiebreakers);
    return $ELEMS;
  }
}
?>
