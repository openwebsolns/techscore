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
class TeamRotationDialog extends AbstractDialog {
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
   * @param Round $round round or group member
   * @param boolean $link_schools true to link schools
   * @return Array:Xmlable
   */
  public function getTable(Round $round, $link_schools = false) {
    $rotation = $this->REGATTA->getRotation();

    $divs = $this->REGATTA->getDivisions();
    $other_divs = array();
    for ($i = 1; $i < count($divs); $i++)
      $other_divs[] = $divs[$i];

    $season = $this->REGATTA->getSeason();

    $tab = new XTable(array('class'=>'teamscorelist', 'class'=>'tr-rotation-table'),
                      array(new XTHead(array(),
                                       array($head = new XTR(array(),
                                                             array(new XTH(array(), "#"),
                                                                   new XTH(array('colspan'=>2), "Team 1"),
                                                                   new XTH(array('colspan'=>count($divs)), "Sails"),
                                                                   new XTH(array(), ""),
                                                                   new XTH(array('colspan'=>count($divs)), "Sails"),
                                                                   new XTH(array('colspan'=>2), "Team 2"))))),
                            $body = new XTBody()));

    $races = ($round->round_group === null) ?
      $this->REGATTA->getRacesInRound($round, Division::A()) :
      $this->REGATTA->getRacesInRoundGroup($round->round_group, Division::A());

    $flight = count($rotation->getCommonSails($races)) / 2;
    foreach ($races as $i => $race) {
      // spacer
      if ($i % $flight == 0) {
        $body->add(new XTR(array('class'=>'tr-flight'), array(new XTD(array('colspan' => 8 + 2 * count($divs)), sprintf("Flight %d", ($i / $flight + 1))))));
      }

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
                                               new XTD(array('class'=>'team1'), $burg1),
                                               new XTD(array('class'=>'team1'), $team1))));

      // first team
      $sail = $rotation->getSail($race, $team1);
      $row->add($s = new XTD(array('class'=>'team1 tr-sail'), $sail));
      if ($sail->color !== null)
        $s->set('style', sprintf('background:%s;', $sail->color));

      $other_races = array();
      foreach ($other_divs as $div) {
        $other_races[(string)$div] = $this->REGATTA->getRace($div, $race->number);
        $sail = $rotation->getSail($other_races[(string)$div], $team1);
        $row->add($s = new XTD(array('class'=>'team1 tr-sail'), $sail));
        if ($sail !== null && $sail->color !== null)
          $s->set('style', sprintf('background:%s;', $sail->color));
      }

      $row->add(new XTD(array('class'=>'vscell'), "vs"));

      // second team
      $sail = $rotation->getSail($race, $team2);
      $row->add($s = new XTD(array('class'=>'team2 tr-sail'), $sail));
      if ($sail->color !== null)
        $s->set('style', sprintf('background:%s;', $sail->color));

      foreach ($other_races as $race) {
        $sail = $rotation->getSail($race, $team2);
        $row->add($s = new XTD(array('class'=>'team2 tr-sail'), $sail));
        if ($sail !== null && $sail->color !== null)
          $s->set('style', sprintf('background:%s;', $sail->color));
      }

      $row->add(new XTD(array('class'=>'team2'), $team2));
      $row->add(new XTD(array('class'=>'team2'), $burg2));
    }
    return array($tab);
  }

  /**
   * Creates the tabular display
   *
   */
  public function fillHTML(Array $args) {
    $rotation = $this->REGATTA->getRotation();

    $covered = array();
    foreach ($rotation->getRounds() as $round) {
      if (!isset($covered[$round->id])) {
        $covered[$round->id] = $round;
        $label = (string)$round;
        if ($round->round_group !== null) {
          foreach ($round->round_group->getRounds() as $i => $other) {
            if ($i > 0) {
              $label .= ", " . $other;
              $covered[$other->id] = $other;
            }
          }
        }

        $this->PAGE->addContent($p = new XPort($label));
        foreach ($this->getTable($round) as $tab)
          $p->add($tab);
      }
    }
  }
}
?>