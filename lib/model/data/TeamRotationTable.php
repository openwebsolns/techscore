<?php
namespace data;

use \Division;
use \FullRegatta;
use \Round;
use \Team;

use \XEm;
use \XTable;
use \XTHead;
use \XTBody;
use \XTR;
use \XTH;
use \XTD;
use \XA;
use \XSpan;
use \SailTD;

require_once('xml5/HtmlLib.php');
require_once('xml5/TS.php');

/**
 * A team racing rotation table.
 *
 * @author Dayan Paez
 * @version 2015-03-22
 */
class TeamRotationTable extends XTable {

  /**
   * Generates an HTML table for the given regatta and round.
   *
   * @param Regatta $regatta the regatta.
   * @param Round $round the round.
   * @param boolean $link_schools true to create link to school's summary.
   */
  public function __construct(FullRegatta $regatta, Round $round, $link_schools = false) {
    parent::__construct(
      array('class'=>'tr-rotation-table'),
      array(
        new XTHead(array(), array($head = new XTR())),
        $body = new XTBody(),
      )
    );

    $divisions = $regatta->getDivisions();

    // multiple boats?
    $boats = array();

    // flightsizes per round?
    $flightsize = array();

    // Group teams and sails by round
    $teams = array($round->id => $this->getTeams($round));
    $sails = array($round->id => array());
    if ($round->hasRotation())
      $sails[$round->id] = $round->assignSails($teams[$round->id], $divisions);

    // Grouped rounds?
    // TODO: merge with below?
    $race_index = array($round->id => 0);
    if ($round->round_group !== null) {
      foreach ($round->round_group->getRounds() as $r) {
        if (!array_key_exists($r->id, $teams)) {
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
      $races = $regatta->getRacesInRound($round, Division::A());
      $flightsize[$round->id] = $round->num_boats / (2 * count($divisions));
      $boats = $round->getBoats();
    }
    else {
      $races = $regatta->getRacesInRoundGroup($round->round_group, Division::A());
    }

    // multiple rounds?
    $prevround = null;

    $season = $regatta->getSeason();
    $numcols = 6;
    $head->add(new XTH(array(), "#"));
    if (count($boats) > 1) {
      $head->add(new XTH(array(), "Boat"));
      $numcols++;
    }
    $head->add(new XTH(array('colspan'=>2), "Team 1"));
    $head->add(new XTH(array('colspan'=>count($divisions)), "Sails"));
    $head->add(new XTH(array(), ""));
    $head->add(new XTH(array('colspan'=>count($divisions)), "Sails"));
    $head->add(new XTH(array('colspan'=>2), "Team 2"));

    // create the body
    $flight = 0;
    $numcols += 2 * count($divisions);
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
          $team1 = array(new XA(sprintf('%s%s/', $team1->school->getURL(), $season), $team1->school), " ", $team1->getQualifiedName());
      }
      else {
        $rowattrs['class'] = 'tr-incomplete';
      }

      $burg2 = "";
      if ($team2 instanceof Team) {
        $burg2 = $team2->school->drawSmallBurgee("");
        if ($link_schools !== false)
          $team2 = array(new XA(sprintf('%s%s/', $team2->school->getURL(), $season), $team2->school), " ", $team2->getQualifiedName());
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
        $row->add(new SailTD($sail, array('class'=>'team1')));
      }

      $row->add(new XTD(array('class'=>'vscell'), "vs"));

      // second team
      foreach ($divisions as $div) {
        $sail = null;
        if (isset($sails[$round->id][$race_i]))
          $sail = $sails[$round->id][$race_i][$pair[1]][(string)$div];
        $row->add(new SailTD($sail, array('class'=>'team2')));
      }

      $row->add(new XTD(array('class'=>'team2'), $team2));
      $row->add(new XTD(array('class'=>'team2'), $burg2));

      $race_index[$round->id]++;
    }
  }

  /**
   * Gets a displayable Xmlable for each of teams in round.
   *
   * @param Round $round the round whose teams to fetch.
   * @return Array:Xmlable list of teams.
   */
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
}