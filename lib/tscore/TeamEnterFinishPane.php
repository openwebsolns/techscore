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

  protected function fillHTML(Array $args) {
    // Chosen race, by number
    $race = null;
    $num = DB::$V->incString($args, 'race', 1, 1001, null);
    if ($num !== null) {
      $race = $this->REGATTA->getRace(Division::A(), $num);
      if ($race === null)
        Session::pa(new PA("Invalid race chosen.", PA::I));
      if ($race->tr_team1 === null || $race->tr_team2 === null) {
        Session::pa(new PA(sprintf("Race %d cannot be scored until both teams are known.", $race->number), PA::I));
        $race = null;
      }
    }

    $rotation = $this->REGATTA->getRotation();
    $using = DB::$V->incKey($args, 'finish_using', $this->ACTIONS, self::ROTATION);
    if (!$rotation->isAssigned($race)) {
      unset($this->ACTIONS[self::ROTATION]);
      $using = self::TEAMS;
    }

    // ------------------------------------------------------------
    // Choose race: provide either numerical input, or direct selection
    // ------------------------------------------------------------
    if ($race === null) {
      $this->PAGE->addContent($p = new XPort("Choose race"));
      $p->add($form = $this->createForm(XForm::GET));
      $form->set("id", "race_form");

      $form->add(new FReqItem("Race number:", $race_input = $this->newRaceInput('race', null)));
      $form->add(new FReqItem("Using:", XSelect::fromArray('finish_using', $this->ACTIONS, $using)));

      // Add next unscored, or last scored race
      $races = $this->REGATTA->getUnscoredRaces();
      if (count($races) > 0)
        $race_input->set('value', $races[0]);
      else
        $race_input->set('value', $this->REGATTA->getLastScoredRace());
      
      // No rotation yet
      $form->add(new XSubmitP('go', "Enter finishes →"));

      // ------------------------------------------------------------
      // Choose race: provide grid
      // ------------------------------------------------------------
      foreach ($this->REGATTA->getRounds() as $round) {
        $p->add(new XH4($round));
        $p->add($this->getRoundTable($round));
      }
      return;
    }

    // ------------------------------------------------------------
    // Enter finishes
    // ------------------------------------------------------------
    $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/finish.js'));
    $this->fillFinishesPort($race, ($using == self::ROTATION) ? $rotation : null);
  }

  /**
   * Creates grid with links to score races
   *
   */
  protected function getRoundTable(Round $round) {
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
    for ($i = 0; $i < count($round->race_order); $i++) {
      $pair = $round->getRaceOrderPair($i);

      $t1 = $pair[0] - 1;
      $t2 = $pair[1] - 1;

      if ($teams[$t1] instanceof Team && $teams[$t2] instanceof Team) {
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
?>