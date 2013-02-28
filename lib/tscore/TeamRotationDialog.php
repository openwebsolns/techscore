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
    parent::__construct("Rotations", $reg);
  }

  /**
   * Fetches the list of tables that comprise this display
   *
   * @param boolean $link_schools true to link schools
   * @return Array:Xmlable
   */
  public function getTable($link_schools = false) {
    $divs = $this->REGATTA->getDivisions();
    $rounds = $this->REGATTA->getRounds();
    $season = $this->REGATTA->getSeason();

    $tab = new XTable(array('class'=>'teamscorelist', 'id'=>'rotation-table'),
                      array(new XTHead(array(),
                                       array($head = new XTR(array(),
                                                             array(new XTH(array(), "#"),
                                                                   new XTH(array('colspan'=>2), "Team 1"),
                                                                   new XTH(array(), "Record"),
                                                                   new XTH(array(), ""),
                                                                   new XTH(array(), "Record"),
                                                                   new XTH(array('colspan'=>2), "Team 2")))))));
    foreach ($rounds as $round) {
      $tab->add($body = new XTBody(array(), array(new XTR(array('class'=>'roundrow'),
                                                          array(new XTH(array('colspan'=>8), $round))))));
      foreach ($this->REGATTA->getRacesInRound($round, Division::A()) as $race) {
        $team1 = $race->tr_team1;
        $team2 = $race->tr_team2;
        if ($link_schools !== false) {
          $team1 = array(new XA(sprintf('/schools/%s/%s/', $team1->school->id, $season), $team1->school), " ", $team1->getQualifiedName());
          $team2 = array(new XA(sprintf('/schools/%s/%s/', $team2->school->id, $season), $team2->school), " ", $team2->getQualifiedName());
        }

        $burg1 = "";
        if ($race->tr_team1->school->burgee !== null) {
          $url = sprintf('/inc/img/schools/%s.png', $race->tr_team1->school->id);
          $burg1 = new XImg($url, $race->tr_team1->school->id, array('height'=>'20px'));
        }
        $burg2 = "";
        if ($race->tr_team2->school->burgee !== null) {
          $url = sprintf('/inc/img/schools/%s.png', $race->tr_team2->school->id);
          $burg2 = new XImg($url, $race->tr_team2->school->id, array('height'=>'20px'));
        }

        $body->add($row = new XTR(array(), array(new XTD(array(), $race->number),
                                                 $b1 = new XTD(array('class'=>'team1'), $burg1),
                                                 $t1 = new XTD(array('class'=>'team1'), $team1),
                                                 $r1 = new XTD(),
                                                 new XTD(array('class'=>'vscell'), "vs"),
                                                 $r2 = new XTD(),
                                                 $t2 = new XTD(array('class'=>'team2'), $team2),
                                                 $b2 = new XTD(array('class'=>'team2'), $burg2))));
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
          $r1->add(Finish::displayPlaces($places1));
          $r2->add(Finish::displayPlaces($places2));

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
    return array($tab);
  }

  /**
   * Creates the tabular display
   *
   */
  public function fillHTML(Array $args) {
    foreach ($this->getTable() as $tab)
      $this->PAGE->addContent($tab);
  }
}
?>