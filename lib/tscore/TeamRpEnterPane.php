<?php
use \tscore\AbstractRpPane;

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
class TeamRpEnterPane extends AbstractRpPane {

  public function __construct(Account $user, Regatta $reg, $title = "Enter RP") {
    parent::__construct($title, $user, $reg);
  }

  protected function fillHTML(Array $args) {
    $teams = $this->getTeamOptions();

    if (count($teams) == 0) {
      $this->PAGE->addContent($this->createNoTeamPort());
      return;
    }

    $params = $this->getRpPaneParams($args);
    $chosen_team = $params->chosenTeam;

    $this->PAGE->addContent($this->getIntro());

    // ------------------------------------------------------------
    // Change team
    // ------------------------------------------------------------
    $this->PAGE->addContent($this->createChooseTeamPort($teams, $chosen_team));

    // ------------------------------------------------------------
    // What's missing
    // ------------------------------------------------------------
    $this->PAGE->addContent($this->createMissingPort($chosen_team));

    // ------------------------------------------------------------
    // Provide option to include sailors from other schools
    // ------------------------------------------------------------
    if ($this->isCrossRpAllowed()) {
      $this->PAGE->addContent(
        $this->createCrossRpPort(
          $chosen_team,
          $params->participatingSchoolsById,
          $params->requestedSchoolsById
        )
      );
    }

    // ------------------------------------------------------------
    // RP Form
    // ------------------------------------------------------------
    $rpManager = $this->REGATTA->getRpManager();
    $divisions = $this->REGATTA->getDivisions();
    $teamRaces = $this->REGATTA->getTeamRacesFor($chosen_team);

    $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/teamrp.js?v=1')));
    $this->PAGE->addContent($p = new XPort(sprintf("Fill out form for %s", $chosen_team)));
    $p->add($form = $this->createForm());

    // List of sailors
    $attendee_options = $params->attendeeOptions;
    $sailor_options = $params->sailorOptions;

    // Representative
    $rep = $rpManager->getRepresentative($chosen_team);
    $form->set('id', 'rp-form');
    $form->add(new XP(array(), "Fill out the form using one set of sailors at a time. A \"set\" refers to the group of sailors out on the water at the same time. Submit more than once for each different configuration of sailors. Invalid configurations will be ignored."));
    $form->add(new XP(array(), "Remember to choose the races that apply to the specified \"set\" by selecting the opponent from the list of races that appear below."));
    $form->add(new XWarning(
                      array(new XStrong("Note:"), " To clear an RP entry for a given race, leave the sailor list blank, while selecting the race.")));

    $form->add(new XHiddenInput("chosen_team", $chosen_team->id));
    $form->add(new FItem("Representative:", new XTextInput('rep', $rep), "For contact purposes only."));

    $max_crews = $this->getMaxCrews($teamRaces);
    $header = array("Skipper");
    for ($i = 0; $i < $max_crews; $i++) {
      $mes = "Crew";
      if ($max_crews > 1)
        $mes .= ' ' . ($i + 1);
      $header[] = $mes;
    }
    $form->add($tab = new XQuickTable(array('class'=>'tr-rp-set'), $header));
    foreach ($divisions as $div) {
      $row = array(
        XSelect::fromArray(
          sprintf('sk%s', $div),
          $sailor_options,
          null, // chosen
          array('class'=>'no-mselect tr-rp-entry'))
      );
      for ($i = 0; $i < $max_crews; $i++) {
        $row[] = XSelect::fromArray(
          sprintf('cr%s%d', $div, $i),
          $sailor_options,
          null, // chosen
          array('class'=>'no-mselect tr-rp-entry'));
      }
      $tab->addRow($row);
    }

    // ------------------------------------------------------------
    // Separate the races into rounds
    // ------------------------------------------------------------
    $rounds = array();
    $round_races = array();
    foreach ($teamRaces as $race) {
      if (!array_key_exists($race->round->id, $rounds)) {
        $rounds[$race->round->id] = $race->round;
        $round_races[$race->round->id] = array();
      }
      $round_races[$race->round->id][] = $race;
    }

    // list of attendees participating, indexed by ID
    $participating_attendees = array();

    // create round listings
    foreach ($rounds as $id => $round) {
      $form->add(new XHeading($round));
      $form->add(new XTable(array('class'=>'tr-rp-roundtable'),
                            array($tab = new XTBody(array(), array($bod = new XTR())))));
      $rows = array();
      foreach ($divisions as $div) {
        $rows[(string)$div] = new XTR(array('class'=>'tr-sailor-row'));
        $tab->add($rows[(string)$div]);
      }

      foreach ($round_races[$id] as $race) {
        $opp = $race->tr_team1;
        if ($opp !== null && $opp->id == $chosen_team->id)
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
          if (count($skip) > 0) {
            $li[] = new XSpan($skip[0]->getSailor(), array('class' => sprintf('sk%s', $div)));
            if ($skip[0]->attendee !== null) {
              $participating_attendees[$skip[0]->attendee->id] = $skip[0]->attendee;
            }
          }
          $crew = $rpManager->getRpEntries($chosen_team, $r, RP::CREW);
          foreach ($crew as $i => $rp) {
            $li[] = new XSpan($rp->getSailor(), array('class' => sprintf('cr%s%d', $div, $i)));
            if ($rp->attendee !== null) {
              $participating_attendees[$rp->attendee->id] = $rp->attendee;
            }
          }

          if (count($li) > 0) {
            $rows[(string)$div]->add(new XTD(array(), $li));
          }
          else {
            $rows[(string)$div]->add(new XTD(array(), new XImg(WS::link('/inc/img/question.png'), "?")));
          }
        }
      }
    }
    
    // ------------------------------------------------------------
    // - Add submit
    $form->add(new XP(array(),
                      array(new XReset('reset', "Reset"),
                            new XSubmitInput('rpform', "Submit form",
                                             array('id'=>'rpsubmit')))));


    // ------------------------------------------------------------
    // Reserves
    // ------------------------------------------------------------
    if (DB::g(STN::ALLOW_RESERVES) !== null) {
      $this->PAGE->addContent($p = new XPort("Reserves"));
      $p->add($form = $this->createForm());

      $attendees = $rpManager->getAttendees($chosen_team);
      $current_attendees = array();
      foreach ($attendees as $attendee) {
        if (!array_key_exists($attendee->id, $participating_attendees)) {
          $current_attendees[] = $attendee->sailor->id;
        }
      }

      $form->add(
        new FItem(
          "Sailors:",
          XSelectM::fromArray(
            'attendees[]',
            $sailor_options,
            $current_attendees,
            array('id'=>'reserve-list')),
          "Include every sailor in attendance. Sailors added to the form above will be automatically included as reserves and need not be added explicitly here."
        ));

      foreach ($participating_attendees as $attendee) {
        $form->add(new XHiddenInput('attendees[]', $attendee->sailor->id));
      }
      $form->add(new XHiddenInput('chosen_team', $chosen_team->id));
      $form->add(new XSubmitP('set-attendees', "Update"));
    }
  }


  public function process(Array $args) {
    $teams = $this->getTeamOptions();

    // ------------------------------------------------------------
    // Choose teams
    // ------------------------------------------------------------
    $id = DB::$V->reqKey($args, 'chosen_team', $teams, "Missing or invalid team choice.");
    $team = $teams[$id];
    $rpManager = $this->REGATTA->getRpManager();

    // ------------------------------------------------------------
    // Attendees
    // ------------------------------------------------------------
    if (isset($args['set-attendees'])) {
      $sailors = $this->processAttendees($team, $args);
      Session::pa(new PA(sprintf("Added %s as attendees for team %s.", count($sailors), $team)));
    }

    // ------------------------------------------------------------
    // RP data
    // ------------------------------------------------------------
    if (array_key_exists('rpform', $args)) {

      $divisions = $this->REGATTA->getDivisions();
      $rpManager = $this->REGATTA->getRpManager();

      // NOTE: The nature of this form requires that data entered this
      // way only ADDS to the attendee list; it does not replace it.

      $attendingSailorsById = array();
      foreach ($rpManager->getAttendees($team) as $attendee) {
        $attendingSailorsById[$attendee->sailor->id] = $attendee->sailor;
      }

      $params = $this->getRpPaneParams($args);
      $season = $this->REGATTA->getSeason();

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
      $noShowSailor = new Sailor();
      $noShowSailor->id = self::NO_SHOW_ID;

      $config = array();
      $chosen_sailors = array();
      foreach ($divisions as $div) {
        $config[(string)$div] = array(RP::SKIPPER => array(), RP::CREW => array());
        $id = DB::$V->incString($args, sprintf('sk%s', $div), 1);
        if ($id !== null) {
          if ($id !== self::NO_SHOW_ID && array_key_exists($id, $chosen_sailors)) {
            throw new SoterException(sprintf("%s cannot be involved in more than one role or boat at a time.", $chosen_sailors[$id]));
          }
          if ($id === self::NO_SHOW_ID) {
            $sailor = $noShowSailor;
          } else {
            $sailor = $this->validateSailor($id, $team->school);
            $attendingSailorsById[$id] = $sailor;
          }
          $chosen_sailors[$id] = $sailor;
          $config[(string)$div][RP::SKIPPER][] = $sailor;
        }

        for ($i = 0; $i < $max_crews; $i++) {
          $id = DB::$V->incString($args, sprintf('cr%s%d', $div, $i), 1);
          if ($id !== null) {
            if ($id !== self::NO_SHOW_ID && array_key_exists($id, $chosen_sailors)) {
              throw new SoterException(sprintf("%s cannot be involved in more than one role or boat at a time.", $chosen_sailors[$id]));
            }

            if ($id === self::NO_SHOW_ID) {
              $sailor = $noShowSailor;
            } else {
              $sailor = $this->validateSailor($id, $team->school);
              $attendingSailorsById[$id] = $sailor;
            }
            $chosen_sailors[$id] = $sailor;
            $config[(string)$div][RP::CREW][] = $sailor;
          }
        }
      }

      // NOTE: With no sailors, resets the entries for the given races

      // reset attendees, and map sailor ID to attendee
      $rpManager->setAttendees($team, $attendingSailorsById);
      $attendeesBySailorId = array();
      foreach ($rpManager->getAttendees($team) as $attendee) {
        $attendeesBySailorId[$attendee->sailor->id] = $attendee;
      }

      // Place skippers first
      foreach ($divisions as $div) {
        $skipper = $config[(string)$div][RP::SKIPPER];
        $crews = $config[(string)$div][RP::CREW];

        $skipper = $this->translateSailorsToAttendee($skipper, $attendeesBySailorId);
        $crews = $this->translateSailorsToAttendee($crews, $attendeesBySailorId);

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
      $rpManager->resetCacheComplete($team);

      if (count($chosen_sailors) == 0)
        Session::pa(new PA("Removed RP entries for selected races.", PA::I));
      else
        Session::pa(new PA("Updated RP entries for selected races."));
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_RP, $team->school->id);
    }
    return $args;
  }

  /**
   * Configuration: need to accommodate the biggest boat for this team
   *
   * @param Array races whose boats to look in
   * @return int the max number of crews in given races' boats
   */
  private function getMaxCrews($races) {
    $max_crews = 0;
    foreach ($races as $race) {
      if ($race->boat->max_crews > $max_crews)
        $max_crews = $race->boat->max_crews;
    }
    return $max_crews;
  }

  /**
   * Helper method to fetch a sailor with given ID, given restrictions.
   *
   * @param String $id the ID of the sailor to fetch.
   * @param School $school the school for the sailor.
   * @return Sailor
   * @throws SoterException
   */
  private function validateSailor($id, School $school) {
    $sailor = DB::getSailor($id);
    if ($sailor === null) {
      throw new SoterException(
        sprintf("Invalid sailor provided: %s.", $id)
      );
    }
    if ($this->REGATTA->participant == Regatta::PARTICIPANT_WOMEN && Sailor::FEMALE !== $sailor->gender) {
      throw new SoterException(sprintf("Sailor not allowed in this regatta (%s).", $sailor));
    }
    if (!DB::g(STN::ALLOW_CROSS_RP)) {
      if ($sailor->school->id !== $school->id) {
        throw new SoterException(sprintf("Sailor provided (%s) cannot sail for given school.", $sailor));
      }
    }
    return $sailor;
  }

  /**
   * Very specific helper function used before setRpEntries.
   *
   */
  private function translateSailorsToAttendee(Array $sailors, Array $attendeesBySailorId) {
    $output = array();
    foreach ($sailors as $sailor) {
      if ($sailor == null || $sailor->id === self::NO_SHOW_ID) {
        $output[] = null;
      }
      else {
        if (!array_key_exists($sailor->id, $attendeesBySailorId)) {
          throw new InvalidArgumentException("Unable to find sailor $sailor in list of attendees.");
        }
        $output[] = $attendeesBySailorId[$sailor->id];
      }
    }
    return $output;
  }

  /**
   * Helper function to update attendee list based on arguments.
   *
   * @return list of sailors
   * @throws SoterException on invalid arguments.
   */
  protected function processAttendees(Team $team, Array $args) {
    $gender = ($this->REGATTA->participant == Regatta::PARTICIPANT_WOMEN) ?
      Sailor::FEMALE : null;

    $cross_rp = !$this->REGATTA->isSingleHanded() && DB::g(STN::ALLOW_CROSS_RP);

    $sailors = array();
    foreach (DB::$V->reqList($args, 'attendees', null, "Missing list of attendees.") as $id) {
      $sailor = DB::getSailor($id);
      if ($sailor === null) {
        throw new SoterException(sprintf("Invalid sailor ID provided: %s.", $id));
      }
      if (!$cross_rp && $sailor->school->id != $team->school->id) {
        throw new SoterException(sprintf("Sailor provided (%s) cannot sail for given school.", $sailor));
      }
      if ($gender !== null && $gender != $sailor->gender) {
        throw new SoterException(sprintf("Invalid sailor allowed for this regatta (%s).", $sailor));
      }
      $sailors[] = $sailor;
    }
    if (count($sailors) == 0) {
      throw new SoterException("No sailors provided for attendance list.");
    }

    $rpManager = $this->REGATTA->getRpManager();
    $rpManager->setAttendees($team, $sailors);
    return $sailors;
  }
}
