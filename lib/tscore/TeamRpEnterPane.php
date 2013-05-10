<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

/**
 * Controls the entry of RP information
 *
 * @author Dayan Paez
 * @version 2010-01-21
 */
class TeamRpEnterPane extends AbstractPane {

  public function __construct(Account $user, Regatta $reg, $title = "Enter RP") {
    parent::__construct($title, $user, $reg);
  }

  protected function fillHTML(Array $args) {
    $teams = array();
    $team_races = array();
    $chosen_team = null;
    foreach ($this->REGATTA->getTeams() as $team) {
      $races = $this->REGATTA->getRacesForTeam(Division::A(), $team);
      if (count($races) > 0) {
        $teams[$team->id] = $team;
        $team_races[$team->id] = $races;
        if ($chosen_team === null)
          $chosen_team = $team;
      }
    }

    if (count($teams) == 0) {
      $this->PAGE->addContent($p = new XPort("No teams registered or sailing."));
      $p->add(new XP(array(), array("No races involving any of the teams exist.")));
      return;
    }

    if (isset($args['chosen_team'])) {
      if (!isset($teams[$args['chosen_team']]))
        Session::pa(new PA("Invalid chosen team. Please try again.", PA::I));
      else
        $chosen_team = $teams[$args['chosen_team']];
    }

    $rpManager = $this->REGATTA->getRpManager();
    $divisions = $this->REGATTA->getDivisions();

    $this->PAGE->addContent($p = new XPort("Choose a team"));
    $p->add(new XP(array(),
                   array("Use the form below to enter RP information. If a sailor does not appear in the selection box, it means they are not in the ICSA database, and they have to be manually added to a temporary list in the ",
                         new XA(sprintf('/score/%s/unregistered', $this->REGATTA->id), "Unregistered form"),
                         ".")));

    // ------------------------------------------------------------
    // Change team
    // ------------------------------------------------------------
    $p->add($form = $this->createForm(XForm::GET));
    $form->add(new FItem("Team:", $sel = XSelect::fromArray('chosen_team', $teams, $chosen_team->id)));
    $sel->set('onchange', 'submit(this);');
    $form->add(new XSubmitAccessible("change_team", "Get form"));

    // ------------------------------------------------------------
    // What's missing
    // ------------------------------------------------------------
    if ($this->REGATTA->hasFinishes()) {
      $this->PAGE->addContent($p = new XPort(sprintf("What's missing from %s", $chosen_team)));
      $p->add(new XP(array(), "This port shows what information is missing for this team. Note that only scored races are considered."));

      $this->fillMissing($p, $chosen_team);
    }

    // ------------------------------------------------------------
    // RP Form
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort(sprintf("Fill out form for %s", $chosen_team)));
    // ------------------------------------------------------------
    // - Create option lists
    //   If the regatta is in the current season, then only choose
    //   from 'active' sailors
    $active = 'all';
    $cur_season = Season::forDate(DB::$NOW);
    if ((string)$cur_season ==  (string)$this->REGATTA->getSeason())
      $active = true;
    $gender = ($this->REGATTA->participant == Regatta::PARTICIPANT_WOMEN) ?
      Sailor::FEMALE : null;
    $sailors = $chosen_team->school->getSailors($gender, $active);
    $un_slrs = $chosen_team->school->getUnregisteredSailors($gender);

    $sailor_options = array("" => "",
                            "Sailors" => array(),
                            "Non-ICSA" => array());
    // Representative
    $rep = $rpManager->getRepresentative($chosen_team);
    $p->add($form = $this->createForm());
    $form->add(new XP(array(), "Fill out the form using one set of sailors at a time. A \"set\" refers to the group of sailors out on the water at the same time. Submit more than once for each different configuration of sailors. Invalid configurations will be ignored."));
    $form->add(new XP(array(), "Remember to choose the races that apply to the specified \"set\" by selecting from the list of races that appear below."));
    $form->add(new XP(array('class'=>'warning'),
                      array(new XStrong("Note:"), " To clear an RP entry for a given race, leave the sailor list blank, while selecting the race. ",
                            new XStrong("Hint:"),
                            " Use the ",
                            new XA(WS::link(sprintf('/view/%s/sailors', $this->REGATTA->id)), "Sailors dialog",
                                   array('onclick'=>'this.target="sailors"')),
                            " to see current registrations.")));
    $form->add(new XHiddenInput("chosen_team", $chosen_team->id));
    $form->add($fi = new FItem("Representative:", new XTextInput('rep', $rep)));
    $fi->add(new XMessage("For contact purposes only."));

    foreach ($sailors as $s)
      $sailor_options["Sailors"][$s->id] = (string)$s;
    foreach ($un_slrs as $s)
      $sailor_options["Non-ICSA"][$s->id] = (string)$s;

    // ------------------------------------------------------------
    // Configuration: need to accommodate the biggest boat for this
    // team
    $max_crews = 0;
    foreach ($team_races[$chosen_team->id] as $race) {
      if ($race->boat->max_crews > $max_crews)
        $max_crews = $race->boat->max_crews;
    }

    $header = array("Skipper");
    for ($i = 0; $i < $max_crews; $i++) {
      $mes = "Crew";
      if ($max_crews > 1)
        $mes .= ' ' . ($i + 1);
      $header[] = $mes;
    }
    $form->add($tab = new XQuickTable(array('class'=>'tr-rp-set'), $header));
    foreach ($divisions as $div) {
      $row = array(XSelect::fromArray(sprintf('sk%s', $div), $sailor_options));
      for ($i = 0; $i < $max_crews; $i++) {
        $row[] = XSelect::fromArray(sprintf('cr%s%d', $div, $i), $sailor_options);
      }
      $tab->addRow($row);
    }

    // ------------------------------------------------------------
    // Separate the races into rounds
    // ------------------------------------------------------------
    $rounds = array();
    $round_races = array();
    foreach ($team_races[$chosen_team->id] as $race) {
      if (!isset($rounds[$race->round->id])) {
        $rounds[$race->round->id] = $race->round;
        $round_races[$race->round->id] = array();
      }
      $round_races[$race->round->id][] = $race;
    }

    // create round listings
    foreach ($rounds as $id => $round) {
      $form->add(new XH4($round));
      $form->add(new XTable(array('class'=>'tr-rp-roundtable'),
                            array(new XTBody(array(), array($bod = new XTR())))));
      foreach ($round_races[$id] as $race) {
        $opp = $race->tr_team1;
        if ($opp->id == $chosen_team->id)
          $opp = $race->tr_team2;
        
        // To save space, only print the opponent
        $id = 'chk-race-' . $race->id;
        $bod->add(new XTD(array(),
                          array(new XCheckboxInput('race[]', $race->number, array('id'=>$id)),
                                $label = new XLabel($id, new XSpan($race->number, array('class'=>'message'))))));
        $label->add(new XBr());
        $label->add($opp);
      }
    }
    
    // ------------------------------------------------------------
    // - Add submit
    $form->add(new XP(array(),
                      array(new XReset("reset", "Reset"),
                            new XSubmitInput("rpform", "Submit form",
                                             array("id"=>"rpsubmit")))));
    $p->add(new XScript('text/javascript', null, "check()"));
  }


  public function process(Array $args) {

    // ------------------------------------------------------------
    // Change teams
    // ------------------------------------------------------------
    $team = DB::$V->reqTeam($args, 'chosen_team', $this->REGATTA, "Missing team choice.");

    // ------------------------------------------------------------
    // RP data
    // ------------------------------------------------------------
    if (isset($args['rpform'])) {

      $divisions = $this->REGATTA->getDivisions();
      $rpManager = $this->REGATTA->getRpManager();

      $cur_season = Season::forDate(DB::$NOW);
      $active = 'all';
      if ((string)$cur_season ==  (string)$this->REGATTA->getSeason())
        $active = true;
      $gender = ($this->REGATTA->participant == Regatta::PARTICIPANT_WOMEN) ?
        Sailor::FEMALE : null;
      $sailors = array();
      foreach ($team->school->getSailors($gender, $active) as $sailor)
        $sailors[$sailor->id] = $sailor;
      foreach ($team->school->getUnregisteredSailors($gender) as $sailor)
        $sailors[$sailor->id] = $sailor;

      // Insert representative
      $rpManager->setRepresentative($team, DB::$V->incString($args, 'rep', 1, 256, null));

      // Race numbers
      $races = array();
      $max_crews = 0;
      foreach (DB::$V->reqList($args, 'race', null, "Missing list of races.") as $num) {
        $race = $this->REGATTA->getRace(Division::A(), $num);
        if ($race === null)
          throw new SoterException("Invalid race number provided: $num.");
        if ($race->tr_team1 != $team && $race->tr_team2 != $team)
          throw new SoterException(sprintf("%s did not participate in race %s.", $team, $race));
        $races[$race->number] = $race;
        if ($race->boat->max_crews > $max_crews)
          $max_crews = $race->boat->max_crews;
      }

      // Check configuration: this should be one skipper for each
      // division, and at most as many crews as $max_crews
      $config = array();
      $chosen_sailors = array();
      foreach ($divisions as $div) {
        $sailor = null;
        $id = DB::$V->incKey($args, sprintf('sk%s', $div), $sailors);
        if ($id !== null) {
          if (isset($chosen_sailors[$id]))
            throw new SoterException(sprintf("%s cannot be involved in more than one role or boat at a time.", $sailors[$id]));
          $sailor = $sailors[$id];
          $chosen_sailors[$id] = $sailor;
        }

        $config[(string)$div] = array(RP::SKIPPER => $sailor,
                                       RP::CREW => array());

        for ($i = 0; $i < $max_crews; $i++) {
          $id = DB::$V->incKey($args, sprintf('cr%s%d', $div, $i), $sailors);
          if ($id !== null) {
            if (isset($chosen_sailors[$id]))
              throw new SoterException(sprintf("%s cannot be involved in more than one role or boat at a time.", $sailors[$id]));
            $sailor = $sailors[$id];
            $chosen_sailors[$id] = $sailor;
            $config[(string)$div][RP::CREW][] = $sailor;
          }
        }
      }

      // NOTE: With no sailors, resets the entries for the given races

      // Place skippers first
      foreach ($divisions as $div) {
        $skipper = $config[(string)$div][RP::SKIPPER];
        $crews = $config[(string)$div][RP::CREW];

        foreach ($races as $race) {
          $r = $this->REGATTA->getRace($div, $race->number);
          $rpManager->setRpEntries($team, $r, RP::SKIPPER, array($skipper));
          $myCrews = array();
          for ($i = 0; $i < $r->boat->max_crews && $i < count($crews); $i++)
            $myCrews[] = $crews[$i];
          $rpManager->setRpEntries($team, $r, RP::CREW, $myCrews);
        }
      }
      $rpManager->updateLog();

      if (count($chosen_sailors) == 0)
        Session::pa(new PA("Removed RP entries for selected races.", PA::I));
      else
        Session::pa(new PA("Updated RP entries for selected races."));
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_RP, $team->school->id);
    }
    return $args;
  }

  /**
   * Return the number of the races in this division organized by
   * number of occupants in the boats. The result associative array
   * has keys which are the ranges of occupants (1-3) and values which
   * are a comma separated list of the race numbers in the division
   * with that many occupants
   *
   * @param Division $div the division
   * @param Team $team the team whose races to fetch
   * @return Array<int, Array<int>> a set of race number lists
   * @see Regatta::getRacesForTeam
   */
  public function getOccupantsRaces(Division $div, Team $team) {
    $races = $this->REGATTA->getRacesForTeam($div, $team);
    $list = array();
    foreach ($races as $race) {
      $occ = $race->boat->getNumCrews();
      if (!isset($list[$occ]))
        $list[$occ] = array();
      $list[$occ][] = $race->number;
    }
    return $list;
  }

  protected function fillMissing(XPort $p, Team $chosen_team) {
    $divisions = $this->REGATTA->getDivisions();
    $rpManager = $this->REGATTA->getRpManager();

    $races = array();
    $rounds = array();
    $opponents = array();
    foreach ($divisions as $divNumber => $div) {
      foreach ($this->REGATTA->getScoredRacesForTeam($div, $chosen_team) as $race) {
        if (!isset($races[$race->number])) {
          $races[$race->number] = array(RP::SKIPPER => 0, RP::CREW => 0);
          $rounds[$race->number] = $race->round;
          $opponents[$race->number] = ($race->tr_team1 == $chosen_team) ? $race->tr_team2 : $race->tr_team1;
        }
        if (count($rpManager->getRpEntries($chosen_team, $race, RP::SKIPPER)) == 0)
          $races[$race->number][RP::SKIPPER]++;
        $races[$race->number][RP::CREW] += $race->boat->min_crews - count($rpManager->getRpEntries($chosen_team, $race, RP::CREW));
      }
    }
    $rows = array();
    foreach ($races as $num => $pairs) {
      if ($pairs[RP::SKIPPER] == 0 && $pairs[RP::CREW] == 0)
        continue;
      $rows[] = array($num,
                      $rounds[$num],
                      $opponents[$num],
                      ($pairs[RP::SKIPPER] > 0) ? sprintf("%d skipper(s)", $pairs[RP::SKIPPER]) : "",
                      ($pairs[RP::CREW] > 0) ? sprintf("%d crew(s)", $pairs[RP::CREW]) : "");
    }
        
    if (count($rows) > 0) {
      $p->add($tab = new XQuickTable(array('class'=>'tr-missingrp-table'), array("#", "Round", "Opponent", "Skippers", "Crews")));
      foreach ($rows as $rowIndex => $row)
        $tab->addRow($row, array('class'=>'row' . ($rowIndex % 2)));
    }
    else
      $p->add(new XP(array('class'=>'valid'),
                     array(new XImg(WS::link('/inc/img/s.png'), "✓"), " Information is complete.")));
  }
}
?>