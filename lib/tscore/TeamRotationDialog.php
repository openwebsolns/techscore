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

  private function getTeams(Round $round) {
    $names = array();
    $masters = $round->getMasters();

    if (count($masters) == 0) {
      for ($i = 0; $i < $round->num_teams; $i++) {
        $names[] = new XEm(sprintf("Team %d", ($i + 1)), array('class'=>'no-team'));
      }
    }
    else {
      $j = 0;
      foreach ($masters as $master) {
        for ($i = 0; $i < $master->num_teams; $i++) {
          $names[] = new XEm(sprintf("Team %d", ($j + 1)), array('class'=>'no-team'));
          $j++;
        }
      }
    }
    foreach ($round->getSeeds() as $seed) {
      $names[$seed->seed - 1] = $seed->team;
    }
    return $names;
  }

  /**
   * Fetches the list of tables that comprise this display
   *
   * @param Round $round round or group member
   * @param boolean $link_schools true to link schools
   * @return Array:Xmlable
   */
  public function getTable(Round $round, $link_schools = false) {
    $divisions = $this->REGATTA->getDivisions();

    // multiple boats?
    $boats = array();

    // flightsizes per round?
    $flightsize = array();

    // Group teams and sails by round
    $teams = array($round->id => $this->getTeams($round));
    $sails = array($round->id => array());
    if ($round->hasRotation())
      $sails[$round->id] = $round->assignSails($teams[$round->id], $divisions);

    $race_index = array($round->id => 0);
    if ($round->round_group !== null) {
      foreach ($round->round_group->getRounds() as $r) {
        if (!isset($teams[$r->id])) {
          $teams[$r->id] = $this->getTeams($r);
          $sails[$r->id] = array();
          if ($r->hasRotation())
            $sails[$r->id] = $r->assignSails($teams[$r->id], $divisions);
          $race_index[$r->id] = 0;
        }
	foreach ($r->getBoats() as $boat)
	  $boats[$boat->id] = $boat;
	$flightsize[$r->id] = $r->num_boats / (2 * count($divisions));
      }
    }

    // calculate appropriate race number
    $prevround = null;
    $races = null;
    if ($round->round_group === null) {
      $races = $this->REGATTA->getRacesInRound($round, Division::A());
      $flightsize[$round->id] = $round->num_boats / (2 * count($divisions));
      $boats = $round->getBoats();
    }
    else {
      $races = $this->REGATTA->getRacesInRoundGroup($round->round_group, Division::A());
    }

    $season = $this->REGATTA->getSeason();
    $header = array(new XTH(array(), "#"));
    if (count($boats) > 1)
      $header[] = new XTH(array(), "Boat");
    $header[] = new XTH(array('colspan'=>2), "Team 1");
    $header[] = new XTH(array('colspan'=>count($divisions)), "Sails");
    $header[] = new XTH(array(), "");
    $header[] = new XTH(array('colspan'=>count($divisions)), "Sails");
    $header[] = new XTH(array('colspan'=>2), "Team 2");
    $tab = new XTable(array('class'=>'tr-rotation-table'),
                      array(new XTHead(array(), array(new XTR(array(), $header))),
                            $body = new XTBody()));

    $flight = 0;
    $numcols = count($header) + 2 * count($divisions);
    $num_in_round = 0;
    foreach ($races as $i => $race) {
      $round = $race->round;
      $race_i = $race_index[$round->id];
      $num_in_round++;

      // spacer
      if ($prevround != $round) {
        $flight++;
        $body->add(new XTR(array('class'=>'tr-flight'), array(new XTD(array('colspan' => $numcols), sprintf("Flight %d", $flight)))));
        $prevround = $round;
	$num_in_round = 0;
      }
      elseif ($num_in_round % $flightsize[$round->id] == 0) {
	$flight++;
	$body->add(new XTR(array('class'=>'tr-flight'), array(new XTD(array('colspan' => $numcols), sprintf("Flight %d", $flight)))));
      }

      $rowattrs = array();

      $pair = $round->getRaceOrderPair($race_i);
      $team1 = $teams[$round->id][$pair[0] - 1];
      $team2 = $teams[$round->id][$pair[1] - 1];

      $burg1 = "";
      if ($team1 instanceof Team) {
        $burg1 = $team1->school->drawSmallBurgee("");
        if ($link_schools !== false)
          $team1 = array(new XA(sprintf('/schools/%s/%s/', $team1->school->id, $season), $team1->school), " ", $team1->getQualifiedName());
      }
      else {
        $rowattrs['class'] = 'tr-incomplete';
      }

      $burg2 = "";
      if ($team2 instanceof Team) {
        $burg2 = $team2->school->drawSmallBurgee("");
        if ($link_schools !== false)
          $team2 = array(new XA(sprintf('/schools/%s/%s/', $team2->school->id, $season), $team2->school), " ", $team2->getQualifiedName());
      }
      else {
        $rowattrs['class'] = 'tr-incomplete';
      }

      $body->add($row = new XTR($rowattrs, array(new XTD(array(), $race->number))));
      if (count($boats) > 1) {
	$row->add(new XTD(array('class'=>'boat-cell'), new XSpan($race->boat, array('class'=>'boat'))));
      }
      $row->add(new XTD(array('class'=>'team1'), $burg1));
      $row->add(new XTD(array('class'=>'team1'), $team1));

      // first team
      foreach ($divisions as $div) {
        $sail = null;
        if (isset($sails[$round->id][$race_i]))
          $sail = $sails[$round->id][$race_i][$pair[0]][(string)$div];
        $row->add($s = new XTD(array('class'=>'team1 tr-sail'), $sail));
        if ($sail !== null && $sail->color !== null)
          $s->set('style', sprintf('background:%s;', $sail->color));
      }

      $row->add(new XTD(array('class'=>'vscell'), "vs"));

      // second team
      foreach ($divisions as $div) {
        $sail = null;
        if (isset($sails[$round->id][$race_i]))
          $sail = $sails[$round->id][$race_i][$pair[1]][(string)$div];
        $row->add($s = new XTD(array('class'=>'team2 tr-sail'), $sail));
        if ($sail !== null && $sail->color !== null)
          $s->set('style', sprintf('background:%s;', $sail->color));
      }

      $row->add(new XTD(array('class'=>'team2'), $team2));
      $row->add(new XTD(array('class'=>'team2'), $burg2));

      $race_index[$round->id]++;
    }
    return array($tab);
  }

  /**
   * Creates the tabular display
   *
   */
  public function fillHTML(Array $args) {
    $this->PAGE->body->set('class', 'tr-rotation-page');
    $this->PAGE->addContent(new XP(array('class'=>'warning nonprint'),
                                   array(new XStrong("Hint:"), " to print the sail colors, enable \"Print background colors\" in your printer dialog.")));

    $covered = array();
    foreach ($this->REGATTA->getRounds() as $round) {
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