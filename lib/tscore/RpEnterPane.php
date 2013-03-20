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

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Enter RP", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    $teams = $this->REGATTA->getTeams();

    if (count($teams) == 0) {
      $this->PAGE->addContent($p = new XPort("No teams registered"));
      $p->add(new XP(array(),
                     array("In order to register sailors, you will need to ",
                           new XA(sprintf("score/%s/team", $this->REGATTA->id), "register teams"),
                           " first.")));
      return;
    }

    if (!isset($args['chosen_team']) || ($chosen_team = $this->REGATTA->getTeam($args['chosen_team'])) === null) {
      $chosen_team = $teams[0];
    }

    $rpManager = $this->REGATTA->getRpManager();
    $divisions = $this->REGATTA->getDivisions();
    // Output
    $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/rp.js'));
    if ($this->REGATTA->scoring == Regatta::SCORING_TEAM)
      $this->PAGE->head->add(new XScript('text/javascript', null, 'ENFORCE_DIV_SWITCH = false;'));
    $this->PAGE->addContent($p = new XPort("Choose a team"));
    $p->add(new XP(array(),
                   array("Use the form below to enter RP information. If a sailor does not appear in the selection box, it means they are not in the ICSA database, and they have to be manually added to a temporary list in the ",
                         new XA(sprintf('/score/%s/unregistered', $this->REGATTA->id), "Unregistered form"),
                         ".")));
    $p->add(new XP(array(),
                   array(new XStrong("Note:"),
                         " You may only submit up to two sailors in the same role in the same division at a time. To add a third or more skipper or crew in a given division, submit the form multiple times.")));

    // ------------------------------------------------------------
    // Change team
    // ------------------------------------------------------------
    $p->add($form = $this->createForm(XForm::GET));
    $form->add(new FItem("Team:", $f_sel = new XSelect("chosen_team", array("onchange"=>"submit(this)"))));
    $team_opts = array();
    foreach ($teams as $team) {
      $f_sel->add($opt = new FOption($team->id, $team));
      if ($team->id == $chosen_team->id)
        $opt->set('selected', 'selected');
    }
    $form->add(new XSubmitAccessible("change_team", "Get form"));

    // ------------------------------------------------------------
    // What's missing
    // ------------------------------------------------------------
    if ($this->REGATTA->hasFinishes()) {
      $this->PAGE->addContent($p = new XPort(sprintf("What's missing from %s", $chosen_team)));
      $p->add(new XP(array(), "This port shows what information is missing for this team. Note that only scored races are considered."));

      $header = new XTR(array(), array(new XTH(array(), "#")));
      $rows = array();
      foreach ($divisions as $divNumber => $div) {
        $name = "Division " . $div;
        if ($this->REGATTA->scoring == Regatta::SCORING_TEAM)
          $name = $div->getLevel() . " Boat";
        $header->add(new XTH(array('colspan'=>2), $name));

        foreach ($this->REGATTA->getScoredRacesForTeam($div, $chosen_team) as $race) {
          // get missing info
          $skip = null;
          $crew = null;
          if (count($rpManager->getRpEntries($chosen_team, $race, RP::SKIPPER)) == 0)
            $skip = "Skipper";
          $diff = $race->boat->occupants - 1 - count($rpManager->getRpEntries($chosen_team, $race, RP::CREW));
          if ($diff > 0) {
            if ($race->boat->occupants == 2)
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
        $p->add(new XP(array('class'=>'valid'),
                       array(new XImg(WS::link('/inc/img/s.png'), "✓"), " Information is complete.")));
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
    $coaches = $chosen_team->school->getCoaches($active);
    $sailors = $chosen_team->school->getSailors($gender, $active);
    $un_slrs = $chosen_team->school->getUnregisteredSailors($gender);

    $sailor_options = array("" => "",
                            "Coaches" => array(),
                            "Sailors" => array(),
                            "Non-ICSA" => array());
    foreach ($coaches as $s)
      $sailor_options["Coaches"][$s->id] = (string)$s;
    foreach ($sailors as $s)
      $sailor_options["Sailors"][$s->id] = (string)$s;
    foreach ($un_slrs as $s)
      $sailor_options["Non-ICSA"][$s->id] = (string)$s;

    // Representative
    $rep = $rpManager->getRepresentative($chosen_team);
    $rep_id = ($rep === null) ? "" : $rep->id;
    $p->add($form = $this->createForm());
    $form->add(new XP(array('class'=>'warning'),
                      array(new XStrong("Note:"), " It is not possible to add sailors without adding races. When unsure, mark a sailor as sailing all races, and edit later as more information becomes available. ",
                            new XStrong("Hint:"), " Use \"*\" to automatically select all races. Use the ",
                            new XA(WS::link(sprintf('/view/%s/sailors', $this->REGATTA->id)), "Sailors dialog",
                                   array('onclick'=>'this.target="sailors"')),
                            " to see current registrations.")));
    $form->add(new XHiddenInput("chosen_team", $chosen_team->id));
    $form->add(new FItem("Representative:", XSelect::fromArray('rep', $sailor_options, $rep_id)));

    // Remove coaches from list
    unset($sailor_options["Coaches"]);

    // ------------------------------------------------------------
    // - Fill out form
    foreach ($divisions as $div) {
      // Get races and its occupants
      $occ = $this->getOccupantsRaces($div, $chosen_team);

      // Fetch current rp's
      $cur_sk = $rpManager->getRP($chosen_team, $div, RP::SKIPPER);
      $cur_cr = $rpManager->getRP($chosen_team, $div, RP::CREW);

      if ($this->REGATTA->scoring == Regatta::SCORING_TEAM)
        $form->add(new XHeading(sprintf("%s Boat", $div->getLevel())));
      elseif (count($divisions) > 1)
        $form->add(new XHeading("Division $div"));
      $tab_races = new XQuickTable(array(), array("Race #", "Crews"));

      // Create races table
      // $num_entries will track how many races the $chosen_team is
      // participating in (which would be all except for team racing
      // regattas). This will enable us to issue an appropriate message,
      // rather than displaying the input tables.
      $num_entries = 0;
      foreach ($occ as $crews => $races) {
        $num_entries++;
        $tab_races->addRow(array(new XTD(array("name"=>"races" . $div), DB::makeRange($races)),
                                 new XTD(array("name"=>"occ" . $div),   ((int)$crews) - 1)));
      }
      if ($num_entries == 0) {
        $tab_races->addRow(array("N/A", "N/A"));
        $form->add(new XP(array('class'=>'message'), "The current team is not participating in the regatta."));
        continue;
      }

      $form->add($tab_races);
      $form->add($tab_skip = new XQuickTable(array('class'=>'narrow'), array("Skippers", "Races sailed", "")));
      // ------------------------------------------------------------
      // - Create skipper table
      // Write already filled-in spots + 2 more
      for ($spot = 0; $spot < count($cur_sk) + 2; $spot++) {
        $value = ""; // value for "races sailed"
        if ($spot < count($cur_sk))
          $value = DB::makeRange($cur_sk[$spot]->races_nums);

        $cur_sk_id = (isset($cur_sk[$spot])) ? $cur_sk[$spot]->sailor->id : "";
        $select_cell = XSelect::fromArray("sk$div$spot", $sailor_options, $cur_sk_id, array('onchange'=>'check()'));
        $tab_skip->addRow(array($select_cell,
                                new XTextInput("rsk$div$spot", $value,
                                               array("size"=>"8",
                                                     "class"=>"race_text",
                                                     "onchange"=>
                                                     "check()")),
                                new XTD(array('id'=>"csk$div$spot"),
                                        new XImg("/inc/img/question.png", "Waiting to verify"))),
                          array("class"=>"skipper"));
      }

      $num_crews = max(array_keys($occ));
      // Print table only if there is room in the boat for crews
      if ( $num_crews > 1 ) {
        // update crew table
        $form->add($tab_crew = new XQuickTable(array('class'=>'narrow'), array("Crews", "Races sailed", "")));

        //    write already filled-in spots + 2 more
        for ($spot = 0; $spot < count($cur_cr) + 2; $spot++) {
          $value = ""; // value for "races sailed"
          if ($spot < count($cur_cr))
            $value = DB::makeRange($cur_cr[$spot]->races_nums);

          $cur_cr_id = (isset($cur_cr[$spot])) ? $cur_cr[$spot]->sailor->id : "";
          $select_cell = XSelect::fromArray("cr$div$spot", $sailor_options, $cur_cr_id, array('onchange'=>'check()'));
          $tab_crew->addRow(array($select_cell,
                                  new XTextInput("rcr$div$spot", $value,
                                                 array("size"=>"8",
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

    // ------------------------------------------------------------
    // Change teams
    // ------------------------------------------------------------
    $team = DB::$V->reqTeam($args, 'chosen_team', $this->REGATTA, "Missing team choice.");

    // ------------------------------------------------------------
    // RP data
    // ------------------------------------------------------------
    if (isset($args['rpform'])) {

      $rpManager = $this->REGATTA->getRpManager();
      $rpManager->reset($team);

      $cur_season = Season::forDate(DB::$NOW);
      $active = 'all';
      if ((string)$cur_season ==  (string)$this->REGATTA->getSeason())
        $active = true;
      $gender = ($this->REGATTA->participant == Regatta::PARTICIPANT_WOMEN) ?
        Sailor::FEMALE : null;
      $sailors = array();
      foreach ($team->school->getCoaches($active) as $sailor)
        $sailors[$sailor->id] = $sailor;
      foreach ($team->school->getSailors($gender, $active) as $sailor)
        $sailors[$sailor->id] = $sailor;
      foreach ($team->school->getUnregisteredSailors($gender) as $sailor)
        $sailors[$sailor->id] = $sailor;

      // Insert representative
      if (DB::$V->hasID($rep, $args, 'rep', DB::$MEMBER)) {
        if ($rep->school != $team->school)
          throw new SoterException("Invalid representative chosen.");
        $rpManager->setRepresentative($team, $rep);
      }

      // To enter RP information, keep track of the number of crews
      // available in each race. To do this, keep a stacked
      // associative array with the following structure:
      //
      //  $rot[DIVISION][NUM][# of OCCUPANTS],
      //
      // that is, for each race number (sorted by divisions), keep
      // track of the number of occupants available
      $divisions = $this->REGATTA->getDivisions();
      $occupants = array();
      foreach ($divisions as $division) {
        $div = (string)$division;
        $occupants[$div] = array();
        $list = $this->getOccupantsRaces($division, $team);
        foreach ($list as $occ => $race_nums) {
          foreach ($race_nums as $race_num)
            $occupants[$div][$race_num] = $occ;
        }
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

          $div = new Division($s_div);
          if (trim($args["r" . $s]) == "*") {
            $s_race = array();
            foreach ($this->REGATTA->getRaces($div) as $race)
              $s_race[] = $race->number;
          }
          else
            $s_race = DB::parseRange($args["r" . $s]);
          $s_obj  = (isset($sailors[$s_value])) ? $sailors[$s_value] : null;

          if ($s_race !== null && $s_obj  !== null) {
            // Eliminate those races from $s_race for which there is
            // no space for a crew
            $s_race_copy = $s_race;
            if ($s_role == RP::CREW) {
              foreach ($s_race as $i => $num) {
                if ($occupants[$s_div][$num] <= 1) {
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
   * has keys which are the number of occupants and values which are a
   * comma separated list of the race numbers in the division with
   * that many occupants
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
      $occ = $race->boat->occupants;
      if (!isset($list[$occ]))
        $list[$occ] = array();
      $list[$occ][] = $race->number;
    }
    return $list;
  }
}
?>