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
   * @param boolean $link_schools true to link schools
   * @return Array:Xmlable
   */
  public function getTable($link_schools = false) {
    $rotation = $this->REGATTA->getRotation();

    $divs = $this->REGATTA->getDivisions();
    $other_divs = array();
    for ($i = 1; $i < count($divs); $i++)
      $other_divs[] = $divs[$i];

    $rounds = array();
    foreach ($rotation->getRounds() as $round)
      $rounds[$round->id] = $round;

    $groups = array();
    $unmoved = $rounds;
    foreach ($rounds as $id => $round) {
      if (isset($unmoved[$id])) {
        $groups[] = $round;
        unset($unmoved[$id]);
        if ($round->round_group !== null) {
          foreach ($round->round_group->getRounds() as $other)
            unset($unmoved[$other->id]);
        }
      }
    }

    $season = $this->REGATTA->getSeason();

    $tab = new XTable(array('class'=>'teamscorelist', 'id'=>'rotation-table'),
                      array(new XTHead(array(),
                                       array($head = new XTR(array(),
                                                             array(new XTH(array(), "#"),
                                                                   new XTH(array('colspan'=>2), "Team 1"),
                                                                   new XTH(array('colspan'=>count($divs)), "Sails"),
                                                                   new XTH(array(), ""),
                                                                   new XTH(array('colspan'=>count($divs)), "Sails"),
                                                                   new XTH(array('colspan'=>2), "Team 2")))))));
    foreach ($groups as $round) {
      $tab->add($body = new XTBody(array(), array(new XTR(array('class'=>'roundrow'),
                                                          array(new XTH(array('colspan'=>8 + 2 * count($divs)), $round))))));
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
          if ($sail->color !== null)
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
          if ($sail->color !== null)
            $s->set('style', sprintf('background:%s;', $sail->color));
        }

        $row->add(new XTD(array('class'=>'team2'), $team2));
        $row->add(new XTD(array('class'=>'team2'), $burg2));
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