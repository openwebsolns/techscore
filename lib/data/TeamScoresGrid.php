<?php
namespace data;

use \Finish;
use \FullRegatta;
use \Round;

use \InvalidArgumentException;

use \XTable;
use \XTHead;
use \XTBody;
use \XTR;
use \XTH;
use \XTD;

require_once('xml5/HtmlLib.php');

/**
 * A grid indicating the winners of head to head races in a round.
 *
 * It is possible for a pair of teams to meet more than once in a
 * round (double round-robins come to mind). This method will
 * account for such occurrences by displaying two records, if
 * appropriate.
 *
 * @author Dayan Paez
 * @version 2015-03-23
 */
class TeamScoresGrid extends XTable {

  /**
   * Create a new team racing round-robin score grid.
   *
   * @param FullRegatta $regatta the regatta.
   * @param Round $round the specific round (creates a grid)
   * @throws InvalidArgumentException if bad data supplied.
   */
  public function __construct(FullRegatta $regatta, Round $round) {
    parent::__construct(array('class'=>'teamscores'), array($tbody = new XTBody()));

    $races = $regatta->getRacesInRound($round);
    if (count($races) == 0)
      throw new InvalidArgumentException("No such round $round in this regatta.");

    // Map all the teams in this round to every other team in the
    // round. For each such pairing, track the list of races in which
    // they are set to meet, and if applicable, the score each team
    // got in each race.
    //
    // Since $races contains the Race objects from each division,
    // track the list of races by race number (not ID).
    //
    // The structure is, then, in pseudo-JSON format:
    //
    // {Team1ID : {Team2ID : {Race# : [Ascore, Bscore,...] } } }
    //
    // Also track corresponding team objects
    $scores = array();
    $teams = array();
    foreach ($round->getSeeds() as $seed) {
      $teams[$seed->team->id] = $seed->team;
      $scores[$seed->team->id] = array();
    }

    $carried = array();
    foreach ($races as $race) {
      $ts = $regatta->getRaceTeams($race);
      if (count($ts) < 2)
        continue;

      foreach ($ts as $t) {
        if (!isset($scores[$t->id]))
          $scores[$t->id] = array();
      }

      $t0 = $ts[0];
      $t1 = $ts[1];
      if (!isset($scores[$t0->id][$t1->id]))
        $scores[$t0->id][$t1->id] = array();
      if (!isset($scores[$t1->id][$t0->id]))
        $scores[$t1->id][$t0->id] = array();

      if (!isset($scores[$t0->id][$t1->id][$race->number]))
        $scores[$t0->id][$t1->id][$race->number] = array();
      if (!isset($scores[$t1->id][$t0->id][$race->number]))
        $scores[$t1->id][$t0->id][$race->number] = array();

      $s0 = $regatta->getFinish($race, $t0);
      $s1 = $regatta->getFinish($race, $t1);
      if ($s0 !== null && $s1 !== null) {
        $scores[$t0->id][$t1->id][$race->number][] = $s0;
        $scores[$t1->id][$t0->id][$race->number][] = $s1;
      }
    }

    // fill in with masters
    foreach ($round->getMasters() as $master) {
      foreach ($regatta->getRacesInRound($master->master) as $race) {
        $t0 = $race->tr_team1;
        $t1 = $race->tr_team2;

        if ($t0 !== null && $race->tr_team2 !== null &&
            isset($teams[$t0->id]) && isset($teams[$race->tr_team2->id])) {
          $carried[$race->number] = $race;

          if (!isset($scores[$t0->id][$t1->id]))
            $scores[$t0->id][$t1->id] = array();
          if (!isset($scores[$t1->id][$t0->id]))
            $scores[$t1->id][$t0->id] = array();

          if (!isset($scores[$t0->id][$t1->id][$race->number]))
            $scores[$t0->id][$t1->id][$race->number] = array();
          if (!isset($scores[$t1->id][$t0->id][$race->number]))
            $scores[$t1->id][$t0->id][$race->number] = array();
      
          $s0 = $regatta->getFinish($race, $t0);
          $s1 = $regatta->getFinish($race, $t1);
          if ($s0 !== null && $s1 !== null) {
            $scores[$t0->id][$t1->id][$race->number][] = $s0;
            $scores[$t1->id][$t0->id][$race->number][] = $s1;
          }
        }
      }
    }

    // Create table
    $tbody->add($header = new XTR(array('class'=>'tr-cols')));
    $header->add(new XTD(array('class'=>'tr-pivot'), "↓ vs →"));
    $header->add(new XTH(array('class'=>'tr-rec-th'), "Record"));
    // Header
    foreach ($teams as $id => $team) {
      $header->add(new XTH(array('class'=>'tr-vert-label'), $team->school->nick_name));
      $row = new XTR(array('class'=>sprintf('tr-row team-%s', $team->id)),
                     array(new XTH(array('class'=>'tr-horiz-label'), $team),
                           $rec = new XTD(array('class'=>'tr-record'), "")));
      $win = 0;
      $los = 0;
      $tie = 0;
      foreach ($teams as $id2 => $other) {
        if (!isset($scores[$id][$id2]))
          $row->add(new XTD(array('class'=>'tr-ns'), "X"));
        else {
          // foreach race
          if (count($scores[$id][$id2]) == 0) {
            $row->add(new XTD(array('class'=>'tr-ns')));
            continue;
          }

          $subtab = null;
          if (count($scores[$id][$id2]) > 1)
            $row->add(new XTD(array('class'=>'tr-mult'), $subtab = new XTable(array('class'=>'tr-multtable'))));
            
          foreach ($scores[$id][$id2] as $race_num => $places) {
            $extra_class = '';
            if (isset($carried[$race_num]))
              $extra_class = ' tr-carried';

            $subrow = $row;
            if ($subtab !== null)
              $subtab->add($subrow = new XTR());

            usort($places, "Finish::compareEarned");
            $total1 = $this->sumPlaces($places);
            $total2 = $this->sumPlaces($scores[$id2][$id][$race_num]);

            // Calculate display
            $cont = Finish::displayPlaces($places);
            if ($total1 < $total2) {
              $cont = sprintf('W (%s)', $cont);
              $subrow->add(new XTD(array('class'=>'tr-win' . $extra_class), $cont));
              $win++;
            }
            elseif ($total1 > $total2) {
              $cont = sprintf('L (%s)', $cont);
              $subrow->add(new XTD(array('class'=>'tr-lose' . $extra_class), $cont));
              $los++;
            }
            elseif ($total1 != 0) {
              $cont = sprintf('T (%s)', $cont);
              $subrow->add(new XTD(array('class'=>'tr-tie' . $extra_class), $cont));
              $tie++;
            }
            else
              $subrow->add(new XTD(array(), $cont));
          }
        }
      }
      $tbody->add($row);
      $mes = $win . '-' . $los;
      if ($tie > 0)
        $mes .= '-' . $tie;
      $rec->add($mes);
    }
  }

  /**
   * Utility function to get net score from list of finishes.
   *
   * @param Array:Finish the list of finishes.
   * @return int the total score.
   */
  private function sumPlaces(Array $places = array()) {
    $total = 0;
    foreach ($places as $finish) {
      $total += $finish->score;
    }
    return $total;
  }
}