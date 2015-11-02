<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */
require_once('tscore/EnterFinishPane.php');

/**
 * Enter finishes for team racing regattas
 *
 * 2013-07-15: Divide it into a two-step process: the first is where
 * the race is chosen (do not collapse the port). Unlike in fleet
 * racing, recommend the next race automatically.
 *
 * @author Dayan Paez
 * @created 2012-12-11 
 */
class TeamEnterFinishPane extends EnterFinishPane {

  /**
   * @var Map penalty options available when entering finishes
   */
  protected $pen_opts = array("" => "", Penalty::DNF => "DNF (6)", Penalty::DNS => "DNS (6)");

  protected function fillChooseRace(Array $args) {
    parent::fillChooseRace($args);

    // Provide grid
    foreach ($this->REGATTA->getRounds() as $round) {
      if ($this->isRoundFullyScored($round)) {
        $p = new XCollapsiblePort($round);
      }
      else {
        $p = new XPort($round);
      }
      $this->PAGE->addContent($p);
      $p->add($this->getRoundTable($round));
    }    
  }

  protected function getSessionMessage(Race $race) {
    // separate into team1 and team2 finishes
    $team1 = array();
    $team2 = array();
    $divisions = $this->REGATTA->getDivisions();
    foreach ($divisions as $division) {
      $therace = $race;
      if ($race->division != $division) {
        $therace = $this->REGATTA->getRace($division, $race->number);
      }
      foreach ($this->REGATTA->getFinishes($therace) as $finish) {
        if ($finish->team == $race->tr_team1) {
          $team1[] = $finish;
        }
        else {
          $team2[] = $finish;
        }
      }
    }
    return array(
      sprintf("Finishes entered for race %s: ", $race),
      new XStrong(sprintf("%s %s", $race->tr_team1, Finish::displayPlaces($team1))),
      " vs. ",
      new XStrong(sprintf("%s %s", Finish::displayPlaces($team2), $race->tr_team2)),
      "."
    );
  }

  private function isRoundFullyScored(Round $round) {
    $scored = count($this->REGATTA->getScoredRacesInRound($round));
    $present = count($this->REGATTA->getRacesInRound($round));
    return $scored >= $present;
  }

  /**
   * Creates grid with links to score races
   *
   */
  private function getRoundTable(Round $round) {
    $teams = array();
    $table = array();
    for ($i = 0; $i < $round->num_teams; $i++) {
      $teams[] = new XEm(sprintf("Team %d", ($i + 1)), array('class'=>'no-team'));

      $row = array();
      for ($j = 0; $j < $round->num_teams; $j++)
        $row[] = new XTD();
      $table[] = $row;
    }
    foreach ($round->getSeeds() as $seed) {
      $teams[$seed->seed - 1] = $seed->team;
    }

    $races = $this->REGATTA->getRacesInRound($round, Division::A());
    for ($i = 0; $i < $round->getRaceOrderCount(); $i++) {
      $pair = $round->getRaceOrderPair($i);

      $t1 = $pair[0] - 1;
      $t2 = $pair[1] - 1;

      if ($i >= count($races)) {
        $cont = new XEm("N/A", array('class'=>'no-team', 'title'=>"Race does not exist"));
        $table[$t1][$t2]->set('class', 'no-teams');
        $table[$t2][$t1]->set('class', 'no-teams');
        $table[$t1][$t2]->add($cont);
        $table[$t2][$t1]->add($cont);
      }
      elseif ($teams[$t1] instanceof Team && $teams[$t2] instanceof Team) {
        // Scorable race
        $race = $races[$i];
        $cont = new XA($this->link('finishes', array('race' => $race->number)), "Score");

        $finishes = $this->REGATTA->getFinishes($race);
        if (count($finishes) > 0) {
          $cont = new XA($this->link('finishes', array('race' => $race->number)), "Re-score");
          $table[$t1][$t2]->set('class', 're-score');
          $table[$t2][$t1]->set('class', 're-score');
        }
        $table[$t1][$t2]->add($cont);
        $table[$t2][$t1]->add($cont);
      }
      else {
        // Unscorable race
        $cont = new XEm("N/A", array('class'=>'no-team', 'title'=>"Both teams must be present."));
        $table[$t1][$t2]->set('class', 'no-teams');
        $table[$t2][$t1]->set('class', 'no-teams');
        $table[$t1][$t2]->add($cont);
        $table[$t2][$t1]->add($cont);
      }
    }

    $tab = new XTable(array('class'=>'teamscores'), array($bod = new XTBody()));
    $bod->add($header = new XTR(array('class'=>'tr-cols')));
    $header->add(new XTD(array('class'=>'tr-pivot'), "↓ vs →"));
    $rows = array();
    foreach ($teams as $i => $team) {
      $disp = $team;
      if ($team instanceof Team)
        $disp = $team->school->nick_name;
      $header->add(new XTH(array('class'=>'tr-vert-label'), $disp));
      $bod->add($row = new XTR(array('class'=>sprintf('tr-row team-%s', $i)),
                               array(new XTH(array('class'=>'tr-horiz-label'), $team))));
      foreach ($table[$i] as $j => $cont) {
        $row->add($cont);
        if ($i == $j)
          $cont->set('class', 'tr-ns');
      }
    }

    return $tab;
  }
}
