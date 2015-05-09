<?php
/**
 * Pane for substituting one school for another, for team racing
 *
 * @author Dayan Paez
 * @version 2009-10-04
 * @package tscore
 */

require_once('ReplaceTeamPane.php');

class TeamReplaceTeamPane extends ReplaceTeamPane {

  private $rotation;
  private $rpManager;

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct($user, $reg);
  }

  /**
   * Helper function returns list of chosen rounds
   *
   */
  private function parseRound(Array $args) {
    $round = DB::$V->reqID($args, 'round', DB::T(DB::ROUND), "Invalid round ID provided.");
    if ($round->regatta != $this->REGATTA)
      throw new SoterException("Invalid round chosen.");
    if (count($round->getSlaves()) > 0)
      throw new SoterException(sprintf("Races from %s are being carried over. Therefore, teams cannot be substituted.", $round));
    if (count($round->getMasterRounds()) > 0)
      throw new SoterException(sprintf("Some races for %s are carried over from other rounds. This is currently not allowed."));
    return $round;
  }

  protected function fillHTML(Array $args) {
    // ------------------------------------------------------------
    // Chosen round(s)?
    // ------------------------------------------------------------
    if (isset($args['round'])) {
      try {
        $round = $this->parseRound($args);

        // create options to select replacement
        $options = array("" => "");
        foreach ($this->REGATTA->getTeams() as $team)
          $options[$team->id] = $team;

        // get teams that have participated in chosen rounds
        $teams = array();
        foreach ($this->REGATTA->getRacesInRound($round, Division::A()) as $race) {
          if ($race->tr_team1 !== null) {
            $teams[$race->tr_team1->id] = $race->tr_team1;
            unset($options[$race->tr_team1->id]);
          }
          if ($race->tr_team2 !== null) {
            $teams[$race->tr_team2->id] = $race->tr_team2;
            unset($options[$race->tr_team2->id]);
          }
        }

        if (count($teams) == 0) {
          throw new SoterException(sprintf("No teams have been added to \"%s\".", $round));
        }

        if (count($options) <= 1)
          throw new SoterException("No possible teams to use as replacement.");

        $this->PAGE->addContent($p = new XPort(sprintf("Replace team in %s", $round)));
        $p->add($form = $this->createForm());
        $form->add(new XP(array(), "On the left below are all the teams that are currently participating in the chosen round. To replace a team, choose a different one from the list on the right. Leave blank for no replacement."));

        $from_rounds = $round->getMasterRounds();
        if (count($from_rounds) > 0)
          $form->add(new XWarning(
                            array(new XStrong("Warning:"),
                                  sprintf(" This round contains races carried over from %s. If the new team has races in the previous round(s), then those races will be carried over. Otherwise, new races will be created in this round in order to maintain the full round robin. This means that some scores may be lost and race numbers might be altered as a result of this operation.", implode(", ", $from_rounds)))));

        $form->add($tab = new XQuickTable(array('id'=>'tr-replace-table'), array("Current team", "Replacement")));
        $rowIndex = 0;
        foreach ($teams as $id => $team) {
          $tab->addRow(array(array($team, new XHiddenInput('team[]', $id)),
                             XSelect::fromArray('replacement[]', $options)),
                       array('class'=>'row' . ($rowIndex++ % 2)));
        }

        $form->add(new XP(array('class'=>'p-submit'),
                          array(new XA($this->link('substitute'), "← Cancel"), " ",
                                new XSubmitInput('replace-round', "Replace"),
                                new XHiddenInput('round', $round->id))));
        return;
      }
      catch (SoterException $e) {
        Session::pa(new PA($e->getMessage(), PA::E));
      }
    }

    // ------------------------------------------------------------
    // Replace team in one or more rounds
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Replace a team in a round"));
    $p->add($form = $this->createForm(XForm::GET));
    $form->add(new XP(array(), "To start, choose the round in which to replace teams."));
    $form->add(new FReqItem("Round:", $ul = new XSelect('round')));

    $hasValid = false;
    foreach ($this->REGATTA->getRounds() as $round) {
      $label = (string)$round;
      $attrs = array();

      if (count($round->getSlaves()) > 0) {
        $attrs['title'] = "Races from this round are being carried over to other rounds.";
        $attrs['disabled'] = 'disabled';
      }
      elseif (count($round->getMasterRounds()) > 0) {
        $attrs['title'] = "Races for this round are carried over from other rounds.";
        $attrs['disabled'] = 'disabled';
      }
      else {
        $hasValid = true;
      }
      $ul->add(new FOption($round->id, $label, $attrs));
    }
    $form->add(new XSubmitP('replace-round', "Choose teams →"));

    // ------------------------------------------------------------
    // Parent ports
    // ------------------------------------------------------------
    parent::fillHTML($args);
  }

  /**
   * Edit details about teams
   */
  public function process(Array $args) {
    // ------------------------------------------------------------
    // Replace in round(s)
    // ------------------------------------------------------------
    if (isset($args['replace-round'])) {
      $round = $this->parseRound($args);

      $options = array();
      foreach ($this->REGATTA->getTeams() as $team)
        $options[$team->id] = $team;

      $races_by_team1 = array();
      $races_by_team2 = array();

      $teams = array();
      foreach ($this->REGATTA->getRacesInRound($round) as $race) {
        $teams[$race->tr_team1->id] = $race->tr_team1;
        $teams[$race->tr_team2->id] = $race->tr_team2;

        if (!isset($races_by_team1[$race->tr_team1->id]))
          $races_by_team1[$race->tr_team1->id] = array();
        $races_by_team1[$race->tr_team1->id][] = $race;

        if (!isset($races_by_team2[$race->tr_team2->id]))
          $races_by_team2[$race->tr_team2->id] = array();
        $races_by_team2[$race->tr_team2->id][] = $race;

        unset($options[$race->tr_team1->id]);
        unset($options[$race->tr_team2->id]);
      }

      $list = DB::$V->reqList($args, 'team', null, "Invalid lits of teams provided.");
      if (count($list) == 0)
        throw new SoterException("No teams to replace.");
      $repl = DB::$V->reqList($args, 'replacement', count($list), "Invalid list of replacements.");

      // create list of races that should be used as carry over,
      // indexed by team, then opponent, then division
      $carried_races = array();
      foreach ($round->getMasterRounds() as $other_round) {
        foreach ($this->REGATTA->getRacesInRound($other_round) as $race) {
          $t1 = $race->tr_team1;
          $t2 = $race->tr_team2;
          if (!isset($carried_races[$t1->id]))
            $carried_races[$t1->id] = array();
          if (!isset($carried_races[$t2->id]))
            $carried_races[$t2->id] = array();

          if (!isset($carried_races[$t1->id][$t2->id]))
            $carried_races[$t1->id][$t2->id] = array();
          if (!isset($carried_races[$t2->id][$t1->id]))
            $carried_races[$t2->id][$t1->id] = array();

          if (!isset($carried_races[$t1->id][$t2->id][(string)$race->division]))
            $carried_races[$t1->id][$t2->id][(string)$race->division] = array();
          if (!isset($carried_races[$t2->id][$t1->id][(string)$race->division]))
            $carried_races[$t2->id][$t1->id][(string)$race->division] = array();

          $carried_races[$t1->id][$t2->id][(string)$race->division][] = $race;
          $carried_races[$t2->id][$t1->id][(string)$race->division][] = $race;
        }
      }

      $this->rotation = $this->REGATTA->getRotation();
      $this->rpManager = $this->REGATTA->getRpManager();

      $changed_races = array();
      $changed_finishes = array(); // update finishes as well
      $changed_sails = array();    // update rotations as well
      $deleted_rps = array();      // delete rp for removed teams

      foreach ($list as $i => $id) {
        if (!isset($teams[$id]))
          throw new SoterException("Invalid current team chosen.");

        $old_team = $teams[$id];
        $new_id = DB::$V->incString($repl, $i, 1);
        if ($new_id === null)
          continue;
        
        if (!isset($options[$new_id]))
          throw new SoterException("Invalid replacement team chosen.");

        $new_team = $options[$new_id];
        unset($options[$new_id]);

        foreach (array('tr_team1' => $races_by_team1,
                       'tr_team2' => $races_by_team2) as $prop => $races_by_team) {
          if (isset($races_by_team[$id])) {
            foreach ($races_by_team[$id] as $race) {
              $opp = $race->tr_team1;
              if ($prop == 'tr_team1')
                $opp = $race->tr_team2;

              // Check for new_team-opponent combo from carried races
              if (isset($carried_races[$new_team->id][$opp->id])) {
                $new_race = array_shift($carried_races[$new_team->id][$opp->id][(string)$race->division]);
                array_shift($carried_races[$opp->id][$new_team->id][(string)$race->division]);

                // remove the old race: is the old race from this round?
                if ($race->round == $round)
                  DB::remove($race);
              }
              else {
                $race->$prop = $new_team;
                $changed_races[$race->id] = $race;

                $finish = $this->REGATTA->getFinish($race, $old_team);
                if ($finish !== null) {
                  $finish->team = $new_team;
                  $changed_finishes[$finish->id] = $finish;
                }

                $sail = $this->rotation->getSail($race, $old_team);
                if ($sail !== null) {
                  $sail->team = $new_team;
                  $changed_sails[$sail->id] = $sail;
                }

                foreach (array(RP::SKIPPER, RP::CREW) as $role) {
                  foreach ($this->rpManager->getRpEntries($old_team, $race, $role) as $rp)
                    $deleted_rps[$rp->id] = $rp;
                }
              }
            }
          }
        }
      }

      if (count($changed_races) == 0)
        throw new SoterException("No information changed.");
      foreach ($changed_races as $race)
        DB::set($race, true);

      foreach ($changed_finishes as $finish)
        DB::set($finish, true);
      if (count($changed_finishes) > 0)
        Session::pa(new PA("Updated finishes."));

      foreach ($changed_sails as $sail)
        DB::set($sail, true);
      if (count($changed_sails) > 0)
        Session::pa(new PA("Updated rotations."));

      foreach ($deleted_rps as $rp)
        DB::remove($rp);
      if (count($deleted_rps) > 0)
        Session::pa(new PA("Removed RP info for replaced teams.", PA::I));

      Session::pa(new PA("Teams replaced."));
      $this->redirect('substitute');
    }
    return parent::process($args);
  }
}
?>