<?php
/*
 * This file is part of TechScore
 *
 * @package tscore-dialog
 */

require_once('tscore/AbstractScoresDialog.php');

/**
 * Displays all the races in numberical order, and the finishes
 *
 * The columns are:
 *
 *   - race number
 *   - first team to finish
 *   - second team to finish
 *     ...
 *
 * @author Dayan Paez
 * @version 2013-02-18
 */
class TeamRotationDialog extends AbstractScoresDialog {
  /**
   * Create a new dialog
   *
   * @param FullRegatta $reg the regatta
   */
  public function __construct(FullRegatta $reg) {
    parent::__construct("Results as list", $reg);
  }

  /**
   * Creates the tabular display
   *
   */
  public function fillHTML(Array $args) {
    $divs = $this->REGATTA->getDivisions();
    $rounds = $this->REGATTA->getRounds();

    $tab = new XTable(array('class'=>'teamscorelist'),
                      array(new XTHead(array(),
                                       array($head = new XTR(array(),
                                                             array(new XTH(array(), "#"),
                                                                   new XTH(array(), "Team 1"),
                                                                   new XTH(array(), "Record"),
                                                                   new XTH(array(), ""),
                                                                   new XTH(array(), "Record"),
                                                                   new XTH(array(), "Team 2")))))));
    foreach ($rounds as $round) {
      $tab->add($body = new XTBody(array(), array(new XTR(array('class'=>'roundrow'),
                                                          array(new XTH(array('colspan'=>6), $round))))));
      foreach ($this->REGATTA->getRacesInRound($round, Division::A()) as $race) {
        $body->add($row = new XTR(array(), array(new XTD(array(), $race->number),
                                                 $t1 = new XTD(array('class'=>'team1'), $race->tr_team1),
                                                 $r1 = new XTD(),
                                                 new XTD(array('class'=>'vscell'), "vs"),
                                                 $r2 = new XTD(),
                                                 $t2 = new XTD(array('class'=>'team2'), $race->tr_team2))));
        $finishes = $this->REGATTA->getFinishes($race);
        if (count($finishes) > 0) {
          $places1 = array();
          $places2 = array();
          $score1 = 0;
          $score2 = 0;
          foreach ($finishes as $finish) {
            if ($finish->team == $race->tr_team1) {
              $places1[] = $finish;
              $score1 += $finish->score;
            }
            elseif ($finish->team == $race->tr_team2) {
              $places2[] = $finish;
              $score2 += $finish->score;
            }
          }
          // repeat with remaining divisions
          for ($i = 1; $i < count($divs); $i++) {
            foreach ($this->REGATTA->getFinishes($this->REGATTA->getRace($divs[$i], $race->number)) as $finish) {
              if ($finish->team == $race->tr_team1) {
                $places1[] = $finish;
                $score1 += $finish->score;
              }
              elseif ($finish->team == $race->tr_team2) {
                $places2[] = $finish;
                $score2 += $finish->score;
              }
            }
          }
          $r1->add($this->displayPlaces($places1));
          $r2->add($this->displayPlaces($places2));

          if ($score1 < $score2) {
            $t1->set('class', 'tr-win team1');
            $r1->set('class', 'tr-win team1');
            $t2->set('class', 'tr-lose team2');
            $r2->set('class', 'tr-lose team2');
          }
          elseif ($score1 > $score2) {
            $t1->set('class', 'tr-lose team1');
            $r1->set('class', 'tr-lose team1');
            $t2->set('class', 'tr-win team2');
            $r2->set('class', 'tr-win team2');
          }
          else {
            $t1->set('class', 'tr-tie team1');
            $r1->set('class', 'tr-tie team1');
            $t2->set('class', 'tr-tie team2');
            $r2->set('class', 'tr-tie team2');
          }
        }
      }
    }
    $this->PAGE->addContent($tab);
  }
}
?>