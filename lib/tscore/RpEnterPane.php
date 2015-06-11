<?php
use \tscore\AbstractRpPane;
use \tscore\utils\FleetRpValidator;

/*
 * This file is part of TechScore
 *
 * @package tscore
 */

/**
 * Controls the entry of RP information.
 *
 * 2015-03-18: Since the introduction of the attendee paradigm, this
 * form has to change, while maintaining its UI as faithful to the
 * former process as possible. This is done by continuing to expose a
 * list of all sailors followed by a list of reserves, the two
 * disjointed and merged during processing to create attendees.
 *
 * 2015-04-01: The RP data is now transmitted more properly composed
 * using the following structure:
 *
 *   { rp:
 *     { division:
 *       { role:
 *         [
 *           { sailor: <sailor id>,
 *             races:  <race nums>
 *           },
 *           ...
 *         ]
 *       }
 *     }
 *   }
 *           
 *     
 *
 * @author Dayan Paez
 * @version 2010-01-21
 */
class RpEnterPane extends AbstractRpPane {

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Enter RP", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    $teams = $this->getTeamOptions();

    if (count($teams) == 0) {
      $this->PAGE->addContent($this->createNoTeamPort());
      return;
    }

    $params = $this->getRpPaneParams($args);
    $chosen_team = $params->chosenTeam;

    // Output
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
    // Fetch, and organize, all RPs
    // ------------------------------------------------------------
    $rpManager = $this->REGATTA->getRpManager();
    $divisions = $this->REGATTA->getDivisions();

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
    $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/fleetrp.js'));
    $this->PAGE->addContent($rpform = $this->createForm());
    $rpform->set('id', 'rpform');
    $rpform->add(new XHiddenInput('chosen_team', $chosen_team->id));
    $rpform->add($p = new XPort(sprintf("Fill out form for %s", $chosen_team)));

    // List of sailors
    $attendee_options = $params->attendeeOptions;
    $sailor_options = $params->sailorOptions;

    // Representative
    $rpManager = $this->REGATTA->getRpManager();
    $rep = $rpManager->getRepresentative($chosen_team);
    $rpform->add(new XP(array(),
                   array(new XStrong("Note:"),
                         " You may only submit up to two sailors in the same role in the same division at a time. To add a third or more skipper or crew in a given division, submit the form multiple times.")));

    $p->add(new FItem("Representative:", new XTextInput('rep', $rep), "For contact purposes only"));

    // ------------------------------------------------------------
    // - Fill out form
    // use a global counter to match corresponding sailor-races-check cells.
    $rps = $params->rps;

    $ENTRY_ID = 0;
    // encode the crew participation information for the benefit of fleetrp.js
    $crews_per_division = array();
    foreach ($divisions as $div) {
      // Get races and its occupants
      $occ = $this->getOccupantsRaces($div, $chosen_team);

      $crews_per_division[(string)$div] = array();
      foreach ($occ as $num => $races) {
        foreach ($races as $race) {
          $crews_per_division[(string)$div][$race] = $num;
        }
      }

      // Fetch current rp's
      $cur_sk = $rps[(string)$div][RP::SKIPPER];
      $cur_cr = $rps[(string)$div][RP::CREW];

      $tab_races = new XQuickTable(array(), array("Race #", "Crews"));

      // Create races table
      // $num_entries will track how many races the $chosen_team is
      // participating in (which would be all except for team racing
      // regattas). This will enable us to issue an appropriate message,
      // rather than displaying the input tables.
      //
      // In most cases, there will only be one number of crews per
      // given division. For these cases, rather than displaying a
      // table with only one row, show the number of crews required
      // for all races as a parenthetical note in the "Crews" table.
      $division_explanation = '';
      $crews_explanation = '';
      $num_entries = 0;
      foreach ($occ as $crews => $races) {
        $range = DB::makeRange($races);
        $division_explanation = sprintf(' (%s)', $range);
        $crews_explanation = sprintf(' (%s)', $crews);

        $num_entries++;
        $tab_races->addRow(array(new XTD(array("name"=>"races" . $div), $range),
                                 new XTD(array("name"=>"occ" . $div),   $crews)));
      }
      if ($num_entries == 0) {
        $p->add(new XP(array('class'=>'message'), "The current team is not participating in the regatta."));
        continue;
      }

      if ($num_entries > 1) {
        $division_explanation = '';
        $crews_explanation = '';
      }
      else {
        $tab_races->set('class', 'hidden');
      }

      if (count($divisions) > 1) {
        $p->add(new XHeading(sprintf("Division %s%s", $div, $division_explanation)));
      }
      else if ($division_explanation != '') {
        $p->add(new XHeading(sprintf("Races: %s", $division_explanation)));
      }

      $p->add($tab_races);
      $p->add($tab_skip = new XQuickTable(array('class'=>'narrow'), array("Skippers", "Races sailed", "")));
      $size = 8;
      // ------------------------------------------------------------
      // - Create skipper table
      // Write already filled-in spots + 2 more
      for ($spot = 0; $spot < count($cur_sk) + 2; $spot++) {
        $ENTRY_ID++;
        $value = ""; // value for "races sailed"
        if ($spot < count($cur_sk))
          $value = DB::makeRange($cur_sk[$spot]->races_nums);

        $cur_sk_id = "";
        if (isset($cur_sk[$spot])) {
          if ($cur_sk[$spot]->sailor === null)
            $cur_sk_id = 'NULL';
          else
            $cur_sk_id = $cur_sk[$spot]->sailor->id;
        }

        $tab_skip->addRow(
          array(
            XSelect::fromArray(
              "rp[$div][skipper][$spot][sailor]",
              $sailor_options,
              $cur_sk_id,
              array(
                'id' => 'rp-sailor-' . $ENTRY_ID,
                'data-rp-division' => $div,
                'data-rp-role' => RP::SKIPPER,
                'data-rp-spot' => $spot)),

            new XRangeInput(
              "rp[$div][skipper][$spot][races]",
              $value,
              array(),
              array(
                'id' => 'rp-races-' . $ENTRY_ID,
                'size' => $size,
                'class' => 'race_text')),

            new XImg(
              '/inc/img/question.png',
              '?',
              array('id' => 'rp-check-' . $ENTRY_ID))
          ),
          array('class'=>'skipper'));
      }

      $num_crews = max(array_keys($occ));
      // Print table only if there is room in the boat for crews
      if ( $num_crews > 0 ) {
        // update crew table
        $p->add($tab_crew = new XQuickTable(array('class'=>'narrow'), array("Crews" . $crews_explanation, "Races sailed", "")));

        //    write already filled-in spots + 2 more
        for ($spot = 0; $spot < count($cur_cr) + 2; $spot++) {
          $ENTRY_ID++;

          $value = ""; // value for "races sailed"
          if ($spot < count($cur_cr))
            $value = DB::makeRange($cur_cr[$spot]->races_nums);

          $cur_cr_id = "";
          if (isset($cur_cr[$spot])) {
            if ($cur_cr[$spot]->sailor === null)
              $cur_cr_id = 'NULL';
            else
              $cur_cr_id = $cur_cr[$spot]->sailor->id;
          }

          $tab_crew->addRow(
            array(
              XSelect::fromArray(
                "rp[$div][crew][$spot][sailor]",
                $sailor_options,
                $cur_cr_id,
                array(
                  'id' => 'rp-sailor-' . $ENTRY_ID,
                  'data-rp-division' => $div,
                  'data-rp-role' => RP::CREW,
                  'data-rp-spot' => $spot)),

              new XTextInput(
                "rp[$div][crew][$spot][races]",
                $value,
                array(
                  'id' => 'rp-races-' . $ENTRY_ID,
                  'size' => $size,
                  'class' => 'race_text')),

              new XImg(
                '/inc/img/question.png',
                '?',
                array('id' => 'rp-check-' . $ENTRY_ID))
            ),
            array('class'=>'crew'));
        }
      } // end if
    }

    // ------------------------------------------------------------
    // Reserves
    // ------------------------------------------------------------
    if (DB::g(STN::ALLOW_RESERVES) !== null) {
      $p->add(new XHeading("Reserves"));

      $attendees = $rpManager->getAttendees($chosen_team);
      $current_attendees = array();
      foreach ($attendees as $attendee) {
        if (!array_key_exists($attendee->sailor->id, $params->participatingSailorsById)) {
          $current_attendees[] = $attendee->sailor->id;
        }
        else {
          // participating sailors should be demoted automatically as reserves
          $p->add(new XHiddenInput('reserves[]', $attendee->sailor->id));
        }
      }

      $p->add(
        new FItem(
          "Sailors:",
          XSelectM::fromArray(
            'reserves[]',
            $attendee_options,
            $current_attendees,
            array('id'=>'reserve-list')),
          "Include every sailor in attendance. Sailors added to the form above will be automatically included as reserves and need not be added explicitly here."
        ));
    }

    // ------------------------------------------------------------
    // - Add submit
    $p->add(new XP(array('class'=>'p-submit'),
                   array(new XReset('reset', 'Reset'),
                         new XSubmitInput('rpform', 'Submit form', array('id'=>'rpsubmit')))));

    $rpform->set('data-crews-per-division', json_encode($crews_per_division));
  }


  public function process(Array $args) {
    $teams = $this->getTeamOptions();

    // ------------------------------------------------------------
    // Choose team
    // ------------------------------------------------------------
    $id = DB::$V->reqKey($args, 'chosen_team', $teams, "Missing or invalid team choice.");
    $team = $teams[$id];
    $rpManager = $this->REGATTA->getRpManager();

    // ------------------------------------------------------------
    // RP data
    // ------------------------------------------------------------
    if (isset($args['rpform'])) {

      $validator = new FleetRpValidator($this->REGATTA);
      $validator->validate($args, $team);
      $sailors = $validator->getSailors();

      // Validation done, re-enter all RP information.
      $rpManager->reset($team);
      $rpManager->setAttendees($team, $sailors);

      // Convert RpInput to RPEntry:
      // attendees indexed by sailor ID to facilitate conversion
      $attendees = array();
      foreach ($rpManager->getAttendees($team) as $attendee) {
        $attendees[$attendee->sailor->id] = $attendee;
      }

      $rps = array();
      foreach ($validator->getRpInputs() as $rpInput) {
        foreach ($rpInput->races as $race) {
          $rp = new RPEntry();
          $rp->team = $rpInput->team;
          $rp->race = $race;
          $rp->boat_role = $rpInput->boat_role;
          $rp->attendee = $attendees[$rpInput->sailor->id];
          $rps[] = $rp;
        }
      }

      // Insert all!
      $rpManager->setRepresentative($team, DB::$V->incString($args, 'rep', 1, 256, null));
      $rpManager->setRP($rps);
      $rpManager->resetCacheComplete($team);
      $rpManager->updateLog();

      // Announce
      Session::pa(new PA("RP info updated."));
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_RP, $team->school->id);
    }
    return;
  }

  /**
   * Return the number of the races in this division organized by
   * number of crews in the boats. The result associative array
   * has keys which are the number of crews (1-3) and values which
   * are a comma separated list of the race numbers in the division
   * with that many occupants.
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
}

