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
    $orgname = DB::g(STN::ORG_NAME);
    if ($this->participant_mode) {
      $pos_teams = array();
      foreach ($this->USER->getSchools() as $school) {
        foreach ($this->REGATTA->getTeams($school) as $team)
          $pos_teams[] = $team;
      }
    }
    else {
      $pos_teams = $this->REGATTA->getTeams();
    }

    $teams = array();
    $team_races = array();
    $chosen_team = null;
    foreach ($pos_teams as $team) {
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
      if (!isset($teams[$args['chosen_team']])) {
        $keys = array_keys($teams);
        $chosen_team = $teams[$keys[0]];
      }
      else
        $chosen_team = $teams[$args['chosen_team']];
    }

    $rpManager = $this->REGATTA->getRpManager();
    $divisions = $this->REGATTA->getDivisions();

    $this->PAGE->addContent(new XP(array(),
                                   array(sprintf("Use the form below to enter RP information. If a sailor does not appear in the selection box, it means they are not in the %s database, and they have to be manually added to a temporary list in the ", $orgname),
                                         new XA(sprintf('/score/%s/unregistered', $this->REGATTA->id), "Unregistered form"),
                                         ".")));

    if (count($teams) > 1) {
      // ------------------------------------------------------------
      // Change team
      // ------------------------------------------------------------
      $this->PAGE->addContent($p = new XPort("Choose a team"));
      $p->add($form = $this->createForm(XForm::GET));
      $form->add(new FItem("Team:", $sel = XSelect::fromArray('chosen_team', $teams, $chosen_team->id)));
      $sel->set('onchange', 'submit(this);');
      $form->add(new XSubmitAccessible("change_team", "Get form"));
    }

    // ------------------------------------------------------------
    // RP Form
    // ------------------------------------------------------------
    $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/teamrp.js')));
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
                            "Non-Registered" => array(),
                            "No-show" => array('NULL' => "No show"));
    // Representative
    $rep = $rpManager->getRepresentative($chosen_team);
    $p->add($form = $this->createForm());
    $form->set('id', 'rp-form');
    $form->add(new XP(array(), "Fill out the form using one set of sailors at a time. A \"set\" refers to the group of sailors out on the water at the same time. Submit more than once for each different configuration of sailors. Invalid configurations will be ignored."));
    $form->add(new XP(array(), "Remember to choose the races that apply to the specified \"set\" by selecting the opponent from the list of races that appear below."));
    $form->add(new XP(array('class'=>'warning'),
                      array(new XStrong("Note:"), " To clear an RP entry for a given race, leave the sailor list blank, while selecting the race.")));

    $form->add(new XHiddenInput("chosen_team", $chosen_team->id));
    $form->add($fi = new FItem("Representative:", new XTextInput('rep', $rep)));
    $fi->add(new XMessage("For contact purposes only."));

    foreach ($sailors as $s)
      $sailor_options["Sailors"][$s->id] = (string)$s;
    foreach ($un_slrs as $s)
      $sailor_options["Non-Registered"][$s->id] = (string)$s;

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
                            array($tab = new XTBody(array(), array($bod = new XTR())))));
      $rows = array();
      foreach ($divisions as $div) {
        $rows[(string)$div] = new XTR(array('class'=>'tr-sailor-row'));
        $tab->add($rows[(string)$div]);
      }

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

        // Current participants
        foreach ($divisions as $div) {
          $li = array();
          $r = $this->REGATTA->getRace($div, $race->number);
          $skip = $rpManager->getRpEntries($chosen_team, $r, RP::SKIPPER);
          if (count($skip) > 0)
            $li[] = new XSpan($skip[0]->getSailor(), array('class' => sprintf('sk%s', $div)));
          $crew = $rpManager->getRpEntries($chosen_team, $r, RP::CREW);
          foreach ($crew as $i => $rp)
            $li[] = new XSpan($rp->getSailor(), array('class' => sprintf('cr%s%d', $div, $i)));

          if (count($li) > 0)
            $rows[(string)$div]->add(new XTD(array(), $li));
          else
            $rows[(string)$div]->add(new XTD(array(), new XImg(WS::link('/inc/img/question.png'), "?")));
        }
      }
    }
    
    // ------------------------------------------------------------
    // - Add submit
    $form->add(new XP(array(),
                      array(new XReset("reset", "Reset"),
                            new XSubmitInput("rpform", "Submit form",
                                             array("id"=>"rpsubmit")))));
  }


  public function process(Array $args) {

    // ------------------------------------------------------------
    // Choose teams
    // ------------------------------------------------------------
    $pos_teams = array();
    if ($this->participant_mode) {
      foreach ($this->USER->getSchools() as $school) {
        foreach ($this->REGATTA->getTeams($school) as $team)
          $pos_teams[$team->id] = $team;
      }
    }
    else {
      foreach ($this->REGATTA->getTeams() as $team)
        $pos_teams[$team->id] = $team;
    }

    $id = DB::$V->reqKey($args, 'chosen_team', $pos_teams, "Missing team choice.");
    $team = $pos_teams[$id];

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
      $sailors['NULL'] = null; // no-show

      // Insert representative
      $rpManager->setRepresentative($team, DB::$V->incString($args, 'rep', 1, 256, null));

      // Race numbers
      $races = array();
      $max_crews = 0;
      foreach (DB::$V->reqList($args, 'race', null, "Missing list of races.") as $num) {
        $race = $this->REGATTA->getRace(Division::A(), $num);
        if ($race === null)
          throw new SoterException("Invalid race number provided: $num.");
        if ($race->tr_team1->id != $team->id && $race->tr_team2->id != $team->id)
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
        $config[(string)$div] = array(RP::SKIPPER => array(),
                                       RP::CREW => array());

        $id = DB::$V->incKey($args, sprintf('sk%s', $div), $sailors, false);
        if ($id !== false) {
          if ($id !== 'NULL' && isset($chosen_sailors[$id]))
            throw new SoterException(sprintf("%s cannot be involved in more than one role or boat at a time.", $sailors[$id]));
          $sailor = $sailors[$id];
          $chosen_sailors[$id] = $sailor;
          $config[(string)$div][RP::SKIPPER][] = $sailor;
        }

        for ($i = 0; $i < $max_crews; $i++) {
          $id = DB::$V->incKey($args, sprintf('cr%s%d', $div, $i), $sailors, false);
          if ($id !== false) {
            if ($id !== null && isset($chosen_sailors[$id]))
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
          $rpManager->setRpEntries($team, $r, RP::SKIPPER, $skipper);
          $myCrews = array();
          for ($i = 0; $i < $r->boat->max_crews && $i < count($crews); $i++)
            $myCrews[] = $crews[$i];
          $rpManager->setRpEntries($team, $r, RP::CREW, $myCrews);
        }
      }
      $rpManager->updateLog();
      $rpManager->setCacheComplete($team);

      if (count($chosen_sailors) == 0)
        Session::pa(new PA("Removed RP entries for selected races.", PA::I));
      else
        Session::pa(new PA("Updated RP entries for selected races."));
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_RP, $team->school->id);
    }
    return $args;
  }
}
?>
