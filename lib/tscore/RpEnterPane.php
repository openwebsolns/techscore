<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once("conf.php");

/**
 * Controls the entry of RP information
 *
 * @author Dayan Paez
 * @version 2010-01-21
 */
class RpEnterPane extends AbstractPane {

  const NO_SAILOR_OPTION = '';
  const NO_SHOW_OPTION_GROUP = "No-show";

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Enter RP", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    $orgname = (string)(DB::g(STN::ORG_NAME));
    if ($this->participant_mode) {
      $teams = array();
      foreach ($this->getUserSchools() as $school) {
        foreach ($this->REGATTA->getTeams($school) as $team)
          $teams[$team->id] = $team;
      }
    }
    else {
      $teams = array();
      foreach ($this->REGATTA->getTeams() as $team)
        $teams[$team->id] = $team;
    }

    if (count($teams) == 0) {
      $this->PAGE->addContent($p = new XPort("No teams registered"));
      if (!$this->participant_mode)
        $p->add(new XP(array(),
                       array("In order to register sailors, you will need to ",
                             new XA(sprintf("score/%s/team", $this->REGATTA->id), "register teams"),
                             " first.")));
      return;
    }

    if (isset($args['chosen_team']) && isset($teams[$args['chosen_team']]))
      $chosen_team = $teams[$args['chosen_team']];
    else {
      $keys = array_keys($teams);
      $chosen_team = $teams[$keys[0]];
    }

    $rpManager = $this->REGATTA->getRpManager();
    $divisions = $this->REGATTA->getDivisions();
    // Output
    $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/rp.js'));
    if (count($teams) > 1) {
      // ------------------------------------------------------------
      // Change team
      // ------------------------------------------------------------
      $this->PAGE->addContent($p = new XPort("Choose a team"));
      $p->add(new XP(array(),
                     array(sprintf("Use the form below to enter RP information. If a sailor does not appear in the selection box, it means they are not in the %s database, and they have to be manually added to a temporary list in the ", $orgname),
                           new XA(sprintf('/score/%s/unregistered', $this->REGATTA->id), "Unregistered form"),
                           ".")));

      $p->add($form = $this->createForm(XForm::GET));
      $form->add(new FReqItem("Team:", $f_sel = new XSelect("chosen_team", array("onchange"=>"submit(this)"))));
      $team_opts = array();
      foreach ($teams as $team) {
        $f_sel->add($opt = new FOption($team->id, $team));
        if ($team->id == $chosen_team->id)
          $opt->set('selected', 'selected');
      }
      $form->add(new XSubmitAccessible("change_team", "Get form"));
    }

    // ------------------------------------------------------------
    // What's missing
    // ------------------------------------------------------------
    if ($this->REGATTA->hasFinishes()) {
      $this->PAGE->addContent($p = new XCollapsiblePort(sprintf("What's missing from %s", $chosen_team)));
      $p->add(new XP(array(), "This port shows what information is missing for this team. Note that only scored races are considered."));

      $this->fillMissing($p, $chosen_team);
    }

    // ------------------------------------------------------------
    // Fetch, and organize, all RPs
    // ------------------------------------------------------------
    $schools = array($chosen_team->school->id => $chosen_team->school);
    $rps = array();
    $roles = array(RP::SKIPPER, RP::CREW);
    foreach ($divisions as $div) {
      $d = (string)$div;
      $rps[$d] = array();
      foreach ($roles as $role) {
        $lst = $rpManager->getRP($chosen_team, $div, $role);
        foreach ($lst as $entry) {
          if ($entry->sailor !== null)
            $schools[$entry->sailor->school->id] = $entry->sailor->school;
        }
        $rps[$d][$role] = $lst;
      }
    }

    // ------------------------------------------------------------
    // Provide option to include sailors from other schools
    // ------------------------------------------------------------
    if (!$this->REGATTA->isSingleHanded() && DB::g(STN::ALLOW_CROSS_RP) !== null) {
      $lst = DB::$V->incList($args, 'schools');

      $this->PAGE->addContent($p = new XCollapsiblePort("Include sailors from other schools?"));
      $p->add($f = $this->createForm(XForm::GET));
      $f->add(new XHiddenInput('chosen_team', $chosen_team->id));
      $f->add(new FItem("Other schools:", $ul = new XSelectM('schools[]', array('size' => '10'))));
      foreach (DB::getConferences() as $conf) {
        $opts = array();
        foreach ($this->getConferenceSchools($conf) as $school) {
          if ($school->id == $chosen_team->school->id)
            continue;

          $opt = new FOption($school->id, $school);
          if (isset($schools[$school->id])) {
            $opt->set('selected', 'selected');
            $opt->set('disabled', 'disabled');
            $opt->set('title', "There are already sailors from this school in the RP form.");
          }
          elseif (in_array($school->id, $lst)) {
            $opt->set('selected', 'selected');
            $schools[$school->id] = $school;
          }
          $opts[] = $opt;
        }
        if (count($opts) > 0)
          $ul->add(new FOptionGroup($conf, $opts));
      }
      $f->add(new XSubmitP('go', "Fetch sailors"));
    }

    // ------------------------------------------------------------
    // Attendees
    // ------------------------------------------------------------
    $attendees = $rpManager->getAttendees($chosen_team->school);
    if (count($attendees) == 0) {
      $p = new XPort("Setup Attendees");
      $p->add(new XP(array(), "Before filling out the RP form, we need to know who in your roster is attending the regatta. You will then be able to choose participants from this list."));
      $p->add(new XP(
                array(),
                array("You can edit this information later, if needed. If sailors do not appear, they may need to be added as a ",
                      new XA($this->link('unregistered'), "temporary (unregistered) sailor"),
                      ".")));
    }
    else {
      $p = new XCollapsiblePort("Attendees");
    }
    $this->PAGE->addContent($p);
    $p->add(new XWarning("Note: all attendees that do not participate will automatically be labeled as \"Reserves\" for this regatta. For this reason, it is important that this information be entered accurately."));
    $p->add($f = $this->createForm());
    $f->add(new XHiddenInput("chosen_team", $chosen_team->id));
    $rpManager = $this->REGATTA->getRpManager();

    // ------------------------------------------------------------
    // - Create option lists
    //   If the regatta is in the current season, then only choose
    //   from 'active' sailors
    $active = 'all';
    $cur_season = Season::forDate(DB::T(DB::NOW));
    if ((string)$cur_season ==  (string)$this->REGATTA->getSeason())
      $active = true;
    $gender = ($this->REGATTA->participant == Regatta::PARTICIPANT_WOMEN) ?
      Sailor::FEMALE : null;
    $sailors = $chosen_team->school->getSailors($gender, $active);
    $un_slrs = $chosen_team->school->getUnregisteredSailors($gender);

    $attendee_sailors = array();
    foreach ($schools as $school) {
      $key = $school->nick_name;
      foreach ($school->getSailors($gender, $active) as $s)
        $attendee_sailors[$key][$s->id] = (string)$s;
      foreach ($school->getUnregisteredSailors($gender, $active) as $s)
        $attendee_sailors[$key][$s->id] = (string)$s;
    }

    $current_attendees = array();
    foreach ($attendees as $attendee) {
      $current_attendees[] = $attendee->sailor->id;
    }

    $f->add(
      new FReqItem(
        "Sailors:", 
        XSelectM::fromArray('attendees[]', $attendee_sailors, $current_attendees, array('id'=>'attendee-list'))
      )
    );

    $f->add(new XSubmitP('set-attendees', "Set attendees"));

    if (count($attendees) == 0) {
      return;
    }

    // ------------------------------------------------------------
    // RP Form
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort(sprintf("Fill out form for %s", $chosen_team)));

    $sailor_options = array(self::NO_SAILOR_OPTION => '');
    foreach ($attendees as $attendee) {
      $sailor = $attendee->sailor;
      $school = $sailor->school;
      if (!array_key_exists($school->nick_name, $sailor_options)) {
        $sailor_options[$school->nick_name] = array();
      }
      $sailor_options[$school->nick_name][$sailor->id] = (string)$sailor;
    }
    // No show option
    $sailor_options[self::NO_SHOW_OPTION_GROUP] = array('NULL' => "No show");

    // Representative
    $rep = $rpManager->getRepresentative($chosen_team);
    $p->add($form = $this->createForm());
    $form->add(new XWarning(
                      array(new XStrong("Note:"), " It is not possible to add sailors without adding races. When unsure, mark a sailor as sailing all races, and edit later as more information becomes available. ",
                            new XStrong("Hint:"), " Use \"*\" to automatically select all races. Use the ",
                            new XA(WS::link(sprintf('/view/%s/sailors', $this->REGATTA->id)), "Sailors dialog",
                                   array('onclick'=>'this.target="sailors"')),
                            " to see current registrations.")));

    $p->add(new XP(array(),
                   array(new XStrong("Note:"),
                         " You may only submit up to two sailors in the same role in the same division at a time. To add a third or more skipper or crew in a given division, submit the form multiple times.")));

    $form->add(new XHiddenInput("chosen_team", $chosen_team->id));
    $form->add(new FItem("Representative:", new XTextInput('rep', $rep), "For contact purposes only"));

    // ------------------------------------------------------------
    // - Fill out form
    foreach ($divisions as $div) {
      // Get races and its occupants
      $occ = $this->getOccupantsRaces($div, $chosen_team);

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
        $form->add(new XP(array('class'=>'message'), "The current team is not participating in the regatta."));
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
        $form->add(new XHeading(sprintf("Division %s%s", $div, $division_explanation)));
      }
      else if ($division_explanation != '') {
        $form->add(new XHeading(sprintf("Races: %s", $division_explanation)));
      }

      $form->add($tab_races);
      $form->add($tab_skip = new XQuickTable(array('class'=>'narrow'), array("Skippers", "Races sailed", "")));
      $size = 8;
      // ------------------------------------------------------------
      // - Create skipper table
      // Write already filled-in spots + 2 more
      for ($spot = 0; $spot < count($cur_sk) + 2; $spot++) {
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
        $select_cell = XSelect::fromArray("sk$div$spot", $sailor_options, $cur_sk_id, array('onchange'=>'check()'));
        $tab_skip->addRow(array($select_cell,
                                new XRangeInput("rsk$div$spot", $value, array(),
                                                array('size'=>$size,
                                                      'class'=>'race_text',
                                                      'onchange'=>'check()')),
                                new XTD(array('id'=>"csk$div$spot"),
                                        new XImg("/inc/img/question.png", "Waiting to verify"))),
                          array("class"=>"skipper"));
      }

      $num_crews = max(array_keys($occ));
      // Print table only if there is room in the boat for crews
      if ( $num_crews > 0 ) {
        // update crew table
        $form->add($tab_crew = new XQuickTable(array('class'=>'narrow'), array("Crews" . $crews_explanation, "Races sailed", "")));

        //    write already filled-in spots + 2 more
        for ($spot = 0; $spot < count($cur_cr) + 2; $spot++) {
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

          $select_cell = XSelect::fromArray("cr$div$spot", $sailor_options, $cur_cr_id, array('onchange'=>'check()'));
          $tab_crew->addRow(array($select_cell,
                                  new XTextInput("rcr$div$spot", $value,
                                                 array("size"=>$size,
                                                       "class"=>"race_text",
                                                       "onchange"=>
                                                       "check()")),
                                  new XTD(array('id'=>"ccr$div$spot"),
                                          new XImg("/inc/img/question.png", "Waiting to verify"))));
        }
      } // end if
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
    if ($this->participant_mode) {
      $teams = array();
      foreach ($this->getUserSchools() as $school) {
        foreach ($this->REGATTA->getTeams($school) as $team)
          $teams[$team->id] = $team;
      }
    }
    else {
      $teams = array();
      foreach ($this->REGATTA->getTeams() as $team)
        $teams[$team->id] = $team;
    }

    // ------------------------------------------------------------
    // Choose team
    // ------------------------------------------------------------
    $id = DB::$V->reqKey($args, 'chosen_team', $teams, "Missing or invalid team choice.");
    $team = $teams[$id];
    $rpManager = $this->REGATTA->getRpManager();

    // ------------------------------------------------------------
    // Attendees
    // ------------------------------------------------------------
    if (isset($args['set-attendees'])) {
      $school = $team->school;

      $cur_season = Season::forDate(DB::T(DB::NOW));
      $active = 'all';
      if ((string)$cur_season ==  (string)$this->REGATTA->getSeason())
        $active = true;
      $gender = ($this->REGATTA->participant == Regatta::PARTICIPANT_WOMEN) ?
        Sailor::FEMALE : null;

      $cross_rp = !$this->REGATTA->isSingleHanded() && DB::g(STN::ALLOW_CROSS_RP);

      $sailors = array();
      foreach (DB::$V->reqList($args, 'attendees', null, "Missing list of attendees.") as $id) {
        $sailor = DB::getSailor($id);
        if ($sailor === null) {
          throw new SoterException(sprintf("Invalid sailor ID provided: %s.", $id));
        }
        if (!$cross_rp && $sailor->school->id != $school->id) {
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

      $rpManager->setAttendees($school, $sailors);
      Session::pa(new PA(sprintf("Added %s as attendees for school %s.", count($sailor), $school->name)));
    }

    // ------------------------------------------------------------
    // RP data
    // ------------------------------------------------------------
    if (isset($args['rpform'])) {

      $rpManager->reset($team);

      $attendees = $rpManager->getAttendees($team->school);
      $sailors = array();
      foreach ($attendees as $attendee) {
        $sailors[$attendee->sailor->id] = $attendee->sailor;
      }

      // Insert representative
      $rpManager->setRepresentative($team, DB::$V->incString($args, 'rep', 1, 256, null));

      // To enter RP information, keep track of the number of crews
      // available in each race. To do this, keep a stacked
      // associative array with the following structure:
      //
      //  $rot[DIVISION][NUM] = # Max Crews
      //
      // that is, for each race number (sorted by divisions), keep
      // track of the number of occupants available
      $divisions = $this->REGATTA->getDivisions();
      $occupants = array();
      foreach ($divisions as $division) {
        $div = (string)$division;
        $occupants[$div] = array();
        foreach ($this->REGATTA->getRacesForTeam($division, $team) as $race)
          $occupants[$div][$race->number] = $race->boat->max_crews;
      }

      // Process each input, which is of the form:
      // ttDp, where tt = sk/cr, D=A/B/C/D (division) and p is position
      $errors = array();
      $rps = array(); // list of RPEntries
      foreach ($args as $s => $s_value) {
        if (preg_match('/^(cr|sk)[ABCD][0-9]+/', $s) > 0) {
          // We have a sailor request upon us
          $s_role = (substr($s, 0, 2) == "sk") ? RP::SKIPPER : RP::CREW;
          $s_div  = substr($s, 2, 1);
          if (!in_array($s_div, $divisions)) {
            $errors[] = "Invalid division requested: $s_div.";
            continue;
          }

          if (!isset($occupants[$s_div]))
            throw new SoterException("Invalid division provided: " . $s_div);

          $div = new Division($s_div);
          if (trim($args["r" . $s]) == "*") {
            $s_race = array_keys($occupants[(string)$div]);
          }
          else {
            $s_race = array();
            foreach (DB::parseRange($args["r" . $s]) as $num) {
              if (array_key_exists($num, $occupants[(string)$div]))
                $s_race[] = $num;
            }
          }

          $s_obj = false;
          if ($s_value == 'NULL')
            $s_obj = null;
          elseif (array_key_exists($s_value, $sailors)) {
            $s_obj = $sailors[$s_value];
          }

          if ($s_race !== null && $s_obj !== false) {
            // Eliminate those races from $s_race for which there is
            // no space for a crew
            $s_race_copy = $s_race;
            if ($s_role == RP::CREW) {
              foreach ($s_race as $i => $num) {
                if ($occupants[$s_div][$num] < 1) {
                  unset($s_race_copy[$i]);
                }
                else
                  $occupants[$s_div][$num]--;
              }
            }
            // Create the objects
            foreach ($s_race_copy as $num) {
              $rp = new RPEntry();
              $rp->team = $team;
              $rp->race = $this->REGATTA->getRace($div, $num);
              $rp->boat_role  = $s_role;
              $rp->sailor     = $s_obj;
              $rps[] = $rp;
            }
          }
        }
      }

      // insert all!
      $rpManager->setRP($rps);
      $rpManager->setCacheComplete($team);
      $rpManager->updateLog();

      // Announce
      if (count($errors) > 0) {
        $mes = "Encountered these errors: " . implode(' ', $errors);;
        Session::pa(new PA($mes, PA::I));
      }
      else {
        Session::pa(new PA("RP info updated."));
      }
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

    $header = new XTR(array(), array(new XTH(array(), "#")));
    $rows = array();
    foreach ($divisions as $divNumber => $div) {
      $name = "Division " . $div;
      $header->add(new XTH(array('colspan'=>2), $name));

      foreach ($this->REGATTA->getScoredRacesForTeam($div, $chosen_team) as $race) {
        // get missing info
        $skip = null;
        $crew = null;
        if (count($rpManager->getRpEntries($chosen_team, $race, RP::SKIPPER)) == 0)
          $skip = "Skipper";
        $diff = $race->boat->min_crews - count($rpManager->getRpEntries($chosen_team, $race, RP::CREW));
        if ($diff > 0) {
          if ($race->boat->min_crews == 1)
            $crew = "Crew";
          else
            $crew = sprintf("%d Crews", $diff);
        }

        if ($skip !== null || $crew !== null) {
          if (!isset($rows[$race->number]))
            $rows[$race->number] = array(new XTH(array(), $race->number));
          // pad the row with previous division
          for ($i = count($rows[$race->number]) - 1; $i < $divNumber * 2; $i += 2) {
            $rows[$race->number][] = new XTD();
            $rows[$race->number][] = new XTD();
          }
          $rows[$race->number][] = new XTD(array(), $skip);
          $rows[$race->number][] = new XTD(array(), $crew);
        }
      }
    }

    if (count($rows) > 0) {
      $p->add(new XTable(array('class'=>'missingrp-table'),
                         array(new XTHead(array(), array($header)),
                               $bod = new XTBody())));
      $rowIndex = 0;
      foreach ($rows as $row) {
        for ($i = count($row); $i < count($divisions) * 2 + 1; $i++)
          $row[] = new XTD();
        $bod->add(new XTR(array('class'=>'row' . ($rowIndex++ % 2)), $row));
      }
    }
    else
      $p->add(new XValid("Information is complete."));
  }
}
?>
