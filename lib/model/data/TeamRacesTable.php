<?php
namespace data;

use \FullRegatta;
use \Division;
use \Finish;

use \XTable;
use \XTHead;
use \XTBody;
use \XTR;
use \XTH;
use \XTD;
use \XA;

require_once('xml5/HtmlLib.php');

/**
 * Displays races in numerical order, along with finishes for team racing.
 *
 * @author Dayan Paez
 * @version 2015-03-22
 */
class TeamRacesTable extends XTable {

  /**
   * Create a new table for given regatta.
   *
   * @param FullRegatta $regatta the regatta.
   * @param boolean $link_schools true to produce links, as with public site.
   */
  public function __construct(FullRegatta $regatta, $link_schools = false) {
    parent::__construct(
      array('class'=>'teamscorelist', 'id'=>'rotation-table'),
      array(
        new XTHead(
          array(),
          array(
            $head = new XTR(
              array(),
              array(new XTH(array(), "#"),
                    new XTH(array('colspan'=>2), "Team 1"),
                    new XTH(array(), "Record"),
                    new XTH(array(), ""),
                    new XTH(array(), "Record"),
                    new XTH(array('colspan'=>2), "Team 2")))))));

    $divs = $regatta->getDivisions();
    $season = $regatta->getSeason();
    $races = $regatta->getRaces(Division::A());

    $prevround = null;
    foreach ($races as $race) {
      if ($race->round != $prevround) {
        $this->add(
          $body = new XTBody(
            array(),
            array(
              new XTR(
                array('class'=>'roundrow'),
                array(new XTH(array('colspan'=>8), $race->round))))));
        $prevround = $race->round;
      }

      $team1 = $race->tr_team1;
      $team2 = $race->tr_team2;
      if ($team1 === null || $team2 === null) {
        continue;
      }

      if ($link_schools !== false) {
        $team1 = array(new XA(sprintf('%s%s/', $team1->school->getURL(), $season), $team1->school), " ", $team1->getQualifiedName());
        $team2 = array(new XA(sprintf('%s%s/', $team2->school->getURL(), $season), $team2->school), " ", $team2->getQualifiedName());
      }

      $burg1 = $race->tr_team1->school->drawSmallBurgee("");
      $burg2 = $race->tr_team2->school->drawSmallBurgee("");

      $body->add(
        $row = new XTR(
          array(),
          array(
            new XTD(array(), $race->number),
            $b1 = new XTD(array('class'=>'team1'), $burg1),
            $t1 = new XTD(array('class'=>'team1'), $team1),
            $r1 = new XTD(),
            new XTD(array('class'=>'vscell'), "vs"),
            $r2 = new XTD(),
            $t2 = new XTD(array('class'=>'team2'), $team2),
            $b2 = new XTD(array('class'=>'team2'), $burg2))));

      $finishes = $regatta->getFinishes($race);
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
          foreach ($regatta->getFinishes($regatta->getRace($divs[$i], $race->number)) as $finish) {
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
}