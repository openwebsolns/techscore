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

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct($user, $reg);
  }

  /**
   * Helper function returns list of chosen rounds
   *
   */
  private function parseRounds(Array $args) {
    $rounds = array();
    if (is_array($args['round'])) {
      foreach ($args['round'] as $id) {
        $round = DB::get(DB::$ROUND, $id);
        if ($round === null || $round->regatta != $this->REGATTA)
          throw new SoterException("Invalid round provided: $id.");
        $rounds[] = $round;
      }
      if (count($rounds) == 0)
        throw new SoterException("No round(s) chosen.");
    }
    else {
      $round = DB::$V->reqID($args, 'round', DB::$ROUND, "Invalid round ID provided.");
      if ($round->regatta != $this->REGATTA)
        throw new SoterException("Invalid round chosen.");
      $rounds[] = $round;
    }
    return $rounds;
  }

  protected function fillHTML(Array $args) {
    // ------------------------------------------------------------
    // Chosen round(s)?
    // ------------------------------------------------------------
    if (isset($args['round'])) {
      try {
        $rounds = $this->parseRounds($args);

        // create options to select replacement
        $options = array("" => "");
        foreach ($this->REGATTA->getTeams() as $team)
          $options[$team->id] = $team;

        // get teams that have participated in chosen rounds
        $teams = array();
        foreach ($rounds as $round) {
          foreach ($this->REGATTA->getRacesInRound($round, Division::A(), false) as $race) {
            $teams[$race->tr_team1->id] = $race->tr_team1;
            $teams[$race->tr_team2->id] = $race->tr_team2;

            unset($options[$race->tr_team1->id]);
            unset($options[$race->tr_team2->id]);
          }
        }

        if (count($options) == 1) {
          $mes = "No possible teams to use as replacement.";
          if (count($rounds) > 1)
            $mes .= " Try replacing one round at a time.";
          throw new SoterException($mes);
        }

        $this->PAGE->addContent($p = new XPort(sprintf("Replace team in %s", implode(", ", $rounds))));
        $p->add($form = $this->createForm());
        $form->add(new XP(array(), "On the left below are all the teams that are currently participating in the chosen round(s). To replace a team, choose a different one from the list on the right. Leave blank for no replacement."));
        $form->add($tab = new XQuickTable(array('id'=>'tr-replace-table'), array("Current team", "Replacement")));

        $rowIndex = 0;
        foreach ($teams as $id => $team) {
          $tab->addRow(array(array($team, new XHiddenInput('team[]', $id)),
                             XSelect::fromArray('replacement[]', $options)),
                       array('class'=>'row' . ($rowIndex++ % 2)));
        }

        $form->add($p = new XP(array('class'=>'p-submit'),
                               array(new XA($this->link('substitute'), "← Cancel"), " ",
                                     new XSubmitInput('replace-round', "Replace"))));
        foreach ($rounds as $round)
          $p->add(new XHiddenInput('round[]', $round->id));
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
    $form->add(new XP(array(), "To start, choose the round or rounds in which to replace teams."));
    $form->add(new FItem("Rounds:", $ul = new XUl(array('class'=>'inline-list'))));

    foreach ($this->REGATTA->getRounds() as $round) {
      $id = 'chk-' . $round->id;
      $ul->add(new XLi(array(new XCheckboxInput('round[]', $round->id, array('id'=>$id)),
                             new XLabel($id, $round))));
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
      $rounds = $this->parseRounds($args);

      $options = array();
      foreach ($this->REGATTA->getTeams() as $team)
        $options[$team->id] = $team;

      $races_by_team1 = array();
      $races_by_team2 = array();

      $teams = array();
      foreach ($rounds as $round) {
        foreach ($this->REGATTA->getRacesInRound($round, null, false) as $race) {
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
      }

      $list = DB::$V->reqList($args, 'team', null, "Invalid lits of teams provided.");
      if (count($list) == 0)
        throw new SoterException("No teams to replace.");
      $repl = DB::$V->reqList($args, 'replacement', count($list), "Invalid list of replacements.");

      $rotation = $this->REGATTA->getRotation();
      $rpManager = $this->REGATTA->getRpManager();

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
              $race->$prop = $new_team;
              $changed_races[$race->id] = $race;

              $finish = $this->REGATTA->getFinish($race, $old_team);
              if ($finish !== null) {
                $finish->team = $new_team;
                $changed_finishes[$finish->id] = $finish;
              }

              $sail = $rotation->getSail($race, $old_team);
              if ($sail !== null) {
                $sail->team = $new_team;
                $changed_sails[$sail->id] = $sail;
              }

              foreach (array(RP::SKIPPER, RP::CREW) as $role) {
                foreach ($rpManager->getRpEntries($old_team, $race, $role) as $rp)
                  $deleted_rps[$rp->id] = $rp;
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