<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2009-10-04
 * @package tscore
 */

/**
 * Page for editing races when using team scoring. These team races
 * require not just a number, but also the two teams from the set of
 * teams which will be participating. Note that this pane will
 * automatically allocate 3 divisions for the regatta.
 *
 * Each race must also belong to a particular "round". In a particular
 * round, each team races against every other team in a round robin.
 * It would be useful to have the user choose the teams that will
 * participate in a given round and have the program create the
 * pairings automatically. Then, the user has the option to add/remove
 * or reorder the pairings as needed.
 *
 * @author Dayan Paez
 * @version 2012-03-05
 */
class TeamRacesPane extends AbstractPane {

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Edit Rounds", $user, $reg);
    if ($reg->scoring != Regatta::SCORING_TEAM)
      throw new InvalidArgumentException("TeamRacesPane only available for team race regattas.");
  }

  /**
   * Fills out the pane, allowing the user to add up to 10 races at a
   * time, or edit any one of the previous races
   *
   * @param Array $args (ignored)
   */
  protected function fillHTML(Array $args) {
    // ------------------------------------------------------------
    // Specific round?
    // ------------------------------------------------------------
    $rounds = $this->REGATTA->getRounds();
    if (($round = DB::$V->incID($args, 'r', DB::$ROUND)) !== null) {
      foreach ($rounds as $r) {
        if ($r->id == $round->id) {
          $this->fillRound($round);
          return;
        }
      }
      Session::pa(new PA("Invalid round requested.", PA::E));
      $this->redirect();
    }

    // ------------------------------------------------------------
    // Current rounds (offer to reorder them)
    // ------------------------------------------------------------
    if (count($rounds) > 0) {
      // create map of rounds indexed by ID. The extra "r-" in key is
      // to make sure PHP does not treat the keys as integers, thereby
      // re-assigning them on array_shift, below
      $sole_rounds = array();
      foreach ($rounds as $round)
        $sole_rounds['r-' . $round->id] = $round;


      $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/tablesort.js'));
      $this->PAGE->addContent($p = new XPort("Current rounds"));
      $p->add($f = $this->createForm());
      $f->add(new XP(array(), "Click on the round below to edit the races in that round."));
      $f->add(new FItem("Round order:", $tab = new XQuickTable(array('id'=>'divtable', 'class'=>'narrow'), array("#", "Order", "Title"))));
      while (count($sole_rounds) > 0) {
        $round = array_shift($sole_rounds);
        $rel = array($round->relative_order);
        $lnk = array(new XA($this->link('races', array('r'=>$round->id)), $round));
        if ($round->round_group !== null) {
          foreach ($round->round_group->getRounds() as $other_round) {
            if (isset($sole_rounds['r-' . $other_round->id])) {
              unset($sole_rounds['r-' . $other_round->id]);
              $rel[] = $other_round->relative_order;
              $lnk[] = ", ";
              $lnk[] = new XA($this->link('races', array('r'=>$other_round->id)), $other_round);
            }
          }
        }
        $tab->addRow(array(new XTD(array(), array(new XTextInput('order[]', $round->relative_order, array('size'=>2, 'class'=>'small')),
                                                  new XHiddenInput('round[]', $round->id))),
                           new XTD(array('class'=>'drag'), DB::makeRange($rel)),
                           $lnk),
                     array('class'=>'sortable'));
      }
      $f->add(new XSubmitP('order-rounds', "Reorder"));
    }

    // ------------------------------------------------------------
    // Create from previous
    // ------------------------------------------------------------
    if (count($rounds) > 0) {
      $this->PAGE->addContent($p = new XPort("Create from existing round"));
      $p->add($f = $this->createForm());
      $f->add(new XP(array(), "Create a new round by copying an existing round's races."));
      $f->add(new FItem("Round label:", new XTextInput('title', "Round " . (count($rounds) + 1))));
      $f->add(new FItem("Previous round:", XSelect::fromDBM('template', $rounds)));
      $f->add($fi = new FItem("Swap teams:", new XCheckboxInput('swap', 1, array('id'=>'chk-swap'))));
      $fi->add(new XLabel('chk-swap', "Reverse the teams in each race."));
      $f->add(new XSubmitP('create-from-existing', "Add round"));
    }

    // ------------------------------------------------------------
    // Add round
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Create new round"));
    $p->add($form = $this->createForm());
    $form->add(new XP(array(),
                      array("Choose the teams which will participate in the round to be added. ",
                            Conf::$NAME,
                            " will create the necessary races so that each team sails head-to-head against every other team (round-robin). Make sure to add an appropriate label for the round.")));
    $form->add(new XP(array(),
                      "To include a team, select the checkbox next to that team's name."));
    $form->add(new FItem("Round label:", new XTextInput('title', "Round " . (count($rounds) + 1))));

    $boats = DB::getBoats();
    $boatOptions = array();
    foreach ($boats as $boat)
      $boatOptions[$boat->id] = $boat->name;
    $form->add(new FItem("Boat:", XSelect::fromArray('boat', $boatOptions)));
    // $form->add($fi = new FItem("Meetings:", new XTextInput('meetings', 1)));
    // $fi->add(new XMessage("E.g., 1 for \"single\", 2 for \"double round-robin\""));

    $form->add($ul = new XUl(array('id'=>'teams-list')));
    foreach ($this->REGATTA->getTeams() as $team) {
      $id = 'team-'.$team->id;
      $ul->add(new XLi(array(new XCheckboxInput('team[]', $team->id, array('id'=>$id)),
                             new XLabel($id, $team))));
    }

    $form->add(new XSubmitP('add-round', "Add round"));


    // ------------------------------------------------------------
    // Create "completion" (slave) round, if there are at least two
    // non-slave rounds available
    // ------------------------------------------------------------
    $master_rounds = array();
    foreach ($rounds as $round) {
      if (count($round->getMasters()) == 0)
        $master_rounds[] = $round;
    }
    if (count($master_rounds) > 1) {
      $this->PAGE->addContent($p = new XPort("Create a round to complete previous round(s)"));
      $p->add($form = $this->createForm());
      $form->add(new XP(array(),
                        array("Use this form to create a new round where some of the races come from previously existing round(s). Only as many races as needed to complete a round robin will be created. For each round to \"carry-over from\", indicate the teams that advance from that round. Note that a team may only be imported from one round.")));
      $form->add(new FItem("Round label:", new XTextInput('title', "Round " . (count($rounds) + 1))));
      $form->add(new FItem("Boat:", XSelect::fromArray('boat', $boatOptions)));

      foreach ($master_rounds as $round) {
        if (count($round->getMasters()) > 0)
          continue;

        $form->add(new FItem($round . ":", $ul = new XUl(array('class'=>'inline-list'))));
        $id = sprintf('teams[%d][]', $round->id);
        foreach ($this->REGATTA->getTeamsInRound($round) as $team) {
          $cid = sprintf('round-%d-team-%d', $round->id, $team->id);
          $ul->add(new XLi(array(new XCheckboxInput($id, $team->id, array('id'=>$cid)),
                                 new XLabel($cid, $team))));
        }
      }
      $form->add(new XSubmitP('add-slave-round', "Add round"));
    }
  }

  private function fillRound($round) {
    $this->PAGE->addContent(new XP(array(), new XA(WS::link(sprintf('/score/%s/races', $this->REGATTA->id)), "← Back to list of rounds")));

    $teamOpts = array();
    $teamFullOpts = array("null" => "");
    foreach ($this->REGATTA->getTeams() as $team) {
      $teamOpts[$team->id] = $team;
      $teamFullOpts[$team->id] = $team;
    }

    $boats = DB::getBoats();
    $boatOptions = array();
    foreach ($boats as $boat)
      $boatOptions[$boat->id] = $boat->name;

    // ------------------------------------------------------------
    // Edit round name (and other attributes)
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Edit round information"));
    $p->add($form = $this->createForm());
    $form->add(new FItem("Label:", new XTextInput('title', $round->title)));
    $form->add($p = new XSubmitP('edit-round', "Edit"));
    $p->add(new XHiddenInput('round', $round->id));

    // ------------------------------------------------------------
    // Delete
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Delete round"));
    if ($round->round_group !== null) {
      $p->add(new XP(array('class'=>'warning'),
                     array(new XStrong("Note:"), " You may not delete this round because it is being sailed as part of a group. In order to delete the round, you must first \"unlink\" the round group by visiting the ",
                           new XA($this->link('race-order'), "Order races"),
                           " pane.")));
    }
    else {
      $slaves = $round->getSlaves();
      if (count($slaves) > 0) {
        $p->add(new XP(array('class'=>'warning'),
                       array(new XStrong("Note:"),
                             sprintf(" Races in this round are carried over to %s. Because of this, this round may not be deleted, as this would create incomplete round robins. To delete this round, you must first delete the dependent rounds above.", implode(", ", $slaves)))));
      }
      else {
        $p->add(new XP(array('class'=>'warning'),
                       array(new XStrong("Note:"), " Deleting the round will also delete all the races in the round and all information associated with that race, including finishes, penalties, and rotations.")));
        $attr = array('onclick'=>'return confirm("Are you sure you wish to delete this round\ncurrently underway? All score data will be lost.");');

        $p->add($form = $this->createForm());
        $form->add(new XP(array('class'=>'p-submit'),
                          array(new XSubmitInput('delete-round', "Delete", $attr),
                                new XHiddenInput('round', $round->id))));
      }
    }

    $this->PAGE->addContent($p = new XPort("Races order"));
    $p->add(new XP(array(),
                   array("To change the race order for this round, please visit the ",
                         new XA($this->link('race-order'), "Race order"),
                         " pane.")));
  }

  /**
   * Processes new races and edits to existing races
   */
  public function process(Array $args) {
    // ------------------------------------------------------------
    // Order rounds
    // ------------------------------------------------------------
    if (isset($args['order-rounds'])) {

      // keep each solitary round and the first of each round group
      $rounds = array();       // indexed by round ID
      $round_groups = array(); // indexed by ID of first round in group
      $all_rounds = array();   // indexed by round ID for global uniqueness
      foreach ($this->REGATTA->getRoundGroups() as $group) {
        $rnds = $group->getRounds();
        $first = $rnds[0];
        $rounds[$first->id] = $first;
        $round_groups[$first->id] = array();
        $all_rounds[$first->id] = $first;
        for ($i = 1; $i < count($rnds); $i++) {
          $round_groups[$first->id][] = $rnds[$i];
          $all_rounds[$rnds[$i]->id] = $rnds[$i];
        }
      }
      foreach ($this->REGATTA->getRounds() as $round) {
        if (!isset($all_rounds[$round->id])) {
          $rounds[$round->id] = $round;
          $all_rounds[$round->id] = $round;
        }
      }

      if (count($rounds) == 0)
        throw new SoterException("No rounds exist to reorder.");

      $rids = DB::$V->reqList($args, 'round', count($rounds), "Invalid list of rounds to reorder.");
      $order = DB::$V->incList($args, 'order', count($rids));
      if (count($order) > 0)
        array_multisort($order, SORT_NUMERIC, $rids);

      // validate that all rounds are accounted for, as races are
      // renumbered
      $divs = $this->REGATTA->getDivisions();

      $edited = array();
      $races = array();
      $roundnum = 1;
      $racenum = 1;
      foreach ($rids as $rid) {
        if (!isset($rounds[$rid]))
          throw new SoterException("Invalid round requested.");
        $round = $rounds[$rid];

        // does this round depend on others?
        foreach ($round->getMasters() as $other) {
          if (!isset($edited[$other->id]))
            throw new SoterException(sprintf("Round \"%s\" must come after \"%s\" because it contains races carried over.", $round, $other));
        }

        $round->relative_order = $roundnum++;

        if (isset($round_groups[$rid])) {
          foreach ($round_groups[$rid] as $other_round) {
            $other_round->relative_order = $roundnum++;
            $edited[$other_round->id] = $other_round;
          }
          foreach ($this->REGATTA->getRacesInRoundGroup($round->round_group, Division::A(), false) as $race) {
            foreach ($divs as $div) {
              $r = $this->REGATTA->getRace($div, $race->number);
              $r->number = $racenum;
              $races[] = $r;
            }
            $racenum++;
          }
        }
        else {
          foreach ($this->REGATTA->getRacesInRound($round, Division::A(), false) as $race) {
            foreach ($divs as $div) {
              $r = $this->REGATTA->getRace($div, $race->number);
              $r->number = $racenum;
              $races[] = $r;
            }
            $racenum++;
          }
        }
        unset($rounds[$rid]);
        $edited[$round->id] = $round;
      }

      // commit rounds, and races
      foreach ($edited as $round)
        DB::set($round, true);
      foreach ($races as $r)
        DB::set($r, true);

      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      Session::pa(new PA("Edited the round order."));
    }

    // ------------------------------------------------------------
    // Edit round data
    // ------------------------------------------------------------
    if (isset($args['edit-round'])) {
      $round = DB::$V->reqID($args, 'round', DB::$ROUND, "Invalid round to edit.");
      $title = DB::$V->reqString($args, 'title', 1, 81, "Invalid new label for round.");
      if ($title == $round->title)
        Session::pa(new PA("No change in title.", PA::I));
      else {
        $round->title = $title;
        DB::set($round);
        UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
        Session::pa(new PA("Edited round data for $round."));
      }
    }

    // ------------------------------------------------------------
    // Delete round
    // ------------------------------------------------------------
    if (isset($args['delete-round'])) {
      $rounds = array();
      foreach ($this->REGATTA->getRounds() as $r)
        $rounds[$r->id] = $r;

      $round = $rounds[DB::$V->reqKey($args, 'round', $rounds, "Invalid round to delete.")];
      if ($round->round_group !== null)
        throw new SoterException("Round cannot be deleted until it is unlinked from its round group.");

      if (count($round->getSlaves()) > 0)
        throw new SoterException("Round cannot be deleted because some races are carried over to other rounds.");

      // Check that there are no finishes
      $scored = false;
      foreach ($this->REGATTA->getScoredRounds() as $other) {
        if ($other->id == $round->id) {
          $scored = true;
          break;
        }
      }
      // Remove this round from each race, or entire race if only round
      foreach ($this->REGATTA->getRacesInRound($round, null, false) as $race) {
        if (count($race->getRounds()) == 1)
          DB::remove($race);
        else
          $race->deleteRound($round);
      }
      DB::remove($round);

      // Order races of all rounds AFTER this one
      $divs = $this->REGATTA->getDivisions();

      $round_num = 1;
      $race_num = 1;
      foreach ($rounds as $rid => $other) {
        if ($other->relative_order < $round->relative_order) {
          $race_num += count($this->REGATTA->getRacesInRound($other, Division::A(), false));
          $round_num++;
          continue;
        }
        $other->relative_order = $round_num++;
        DB::set($other, true);
        foreach ($this->REGATTA->getRacesInRound($other, Division::A(), false) as $race) {
          foreach ($divs as $div) {
            $r = $this->REGATTA->getRace($div, $race->number);
            if ($r === null) {
              var_dump($race);
              exit;
            }
            $r->number = $race_num;
            DB::set($r, true);
          }
          $race_num++;
        }
      }
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      Session::pa(new PA("Removed round $round."));
      if ($scored) {
        $this->REGATTA->setRanks();
        foreach ($this->REGATTA->getTeams() as $team)
          UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_RANK, $team->school);
        Session::pa(new PA("Re-ranked teams.", PA::I));
      }
    }

    // ------------------------------------------------------------
    // Create from existing
    // ------------------------------------------------------------
    if (isset($args['create-from-existing'])) {
      $rounds = array();
      foreach ($this->REGATTA->getRounds() as $r)
        $rounds[$r->id] = $r;

      $templ = $rounds[DB::$V->reqKey($args, 'template', $rounds, "Invalid template round provided.")];

      $round = new Round();
      $round->regatta = $this->REGATTA;
      $round->title = DB::$V->reqString($args, 'title', 1, 81, "Invalid round label. May not exceed 80 characters.");
      foreach ($rounds as $r) {
        if ($r->title == $round->title)
          throw new SoterException("Duplicate round title provided.");
      }
      $round->relative_order = count($rounds) + 1;

      $num_added = 0;
      $swap = DB::$V->incInt($args, 'swap', 1, 2, 0);
      $divs = $this->REGATTA->getDivisions();
      $racenum = count($this->REGATTA->getRaces(Division::A()));
      foreach ($this->REGATTA->getRacesInRound($templ, Division::A()) as $race) {
        if ($race->round != $templ) {
          $race->addRound($round);
          continue;
        }

        $racenum++;
        $num_added++;
        foreach ($divs as $div) {
          $tmprace = $this->REGATTA->getRace($div, $race->number);
          $newrace = new Race();
          $newrace->regatta = $this->REGATTA;
          $newrace->number = $racenum;
          $newrace->division = $div;
          $newrace->boat = $tmprace->boat;
          $newrace->round = $round;

          if ($swap > 0) {
            $newrace->tr_team1 = $tmprace->tr_team2;
            $newrace->tr_team2 = $tmprace->tr_team1;
          }
          else {
            $newrace->tr_team1 = $tmprace->tr_team1;
            $newrace->tr_team2 = $tmprace->tr_team2;
          }
          DB::set($newrace, false);
        }
      }

      // Also associate masters
      foreach ($templ->getMasters() as $master)
        $round->addMaster($master);

      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      Session::pa(new PA(array(sprintf("Added new round %s based on %s. ", $round, $templ),
                               new XA($this->link('race-order', array('order-rounds'=>'', 'round'=>array($round->id))), "Order races"),
                               ".")));
    }

    // ------------------------------------------------------------
    // Add round (no longer handles "carry over" behavior
    // ------------------------------------------------------------
    if (isset($args['add-round'])) {
      $rounds = array();
      foreach ($this->REGATTA->getRounds() as $r)
        $rounds[$r->id] = $r;

      // title
      $round = new Round();
      $round->regatta = $this->REGATTA;
      $round->title = DB::$V->reqString($args, 'title', 1, 81, "Invalid round label. May not exceed 80 characters.");
      foreach ($rounds as $r) {
        if ($r->title == $round->title)
          throw new SoterException("Duplicate round title provided.");
      }
      $round->relative_order = count($rounds) + 1;

      $boat = DB::$V->reqID($args, 'boat', DB::$BOAT, "Invalid boat provided.");
      $ids = DB::$V->reqList($args, 'team', null, "No list of teams provided. Please try again.");

      // $meetings = DB::$V->reqInt($args, 'meetings', 1, 11, "Invalid meeting count. Must be between 1 and 10.");
      $meetings = 1;

      $teams = array();
      foreach ($ids as $index => $id) {
        if (($team = $this->REGATTA->getTeam($id)) !== null)
          $teams[] = $team;
      }
      if (count($teams) < 2)
        throw new SoterException("Not enough teams provided: there must be at least two. Please try again.");

      // Assign next race number
      $count = count($this->REGATTA->getRaces(Division::A()));

      // Create round robin
      $num_added = 0;
      
      $added = array();     // races to be added to this round
      $divs = array(Division::A(), Division::B(), Division::C());

      $swap = false;
      for ($meeting = 0; $meeting < $meetings; $meeting++) {
        foreach ($this->makeRoundRobin($teams, $swap) as $pair) {
          $count++;
          foreach ($divs as $div) {
            $race = new Race();
            $race->division = $div;
            $race->number = $count;
            $race->boat = $boat;
            $race->regatta = $this->REGATTA;
            $race->round = $round;
            $race->tr_team1 = $pair[0];
            $race->tr_team2 = $pair[1];
            $added[] = $race;
          }
          $num_added++;
        }
        $swap = !$swap;
      }

      // Insert all at once
      DB::insertAll($added);

      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      $mes = array(sprintf("Added %d new races in round %s. ", $num_added, $round));
      $mes[] = new XA($this->link('race-order', array('order-rounds'=>'', 'round'=>array($round->id))), "Order races");
      $mes[] = ".";
      Session::pa(new PA($mes));
      return array();
    }

    // ------------------------------------------------------------
    // Slave round
    // ------------------------------------------------------------
    if (isset($args['add-slave-round'])) {
      $master_rounds = array();
      $rounds = array();
      foreach ($this->REGATTA->getRounds() as $r) {
        $rounds[$r->id] = $r;
        if (count($r->getMasters()) == 0)
          $master_rounds[$r->id] = $r;
      }

      // title
      $round = new Round();
      $round->regatta = $this->REGATTA;
      $round->title = DB::$V->reqString($args, 'title', 1, 81, "Invalid round label. May not exceed 80 characters.");
      foreach ($rounds as $r) {
        if ($r->title == $round->title)
          throw new SoterException("Duplicate round title provided.");
      }
      $round->relative_order = count($rounds) + 1;

      $boat = DB::$V->reqID($args, 'boat', DB::$BOAT, "Invalid boat provided.");

      // Expect the teams to be passed as an associative array indexed
      // by the existing-round's ID.
      $added_teams = array(); // global list of teams to insure no duplicates
      $teamlist = DB::$V->reqList($args, 'teams', null, "No list of teams provided.");
      $masters = array();
      foreach ($teamlist as $id => $list) {
        if (!isset($master_rounds[$id]))
          throw new SoterException("Invalid round provided: $id.");
        if (!is_array($list))
          throw new SoterException(sprintf("No teams provided for round \"%s\".", $master_rounds[$id]));
        $masters[$id] = array();

        // Create list of possible teams for this round
        $round_teams = array();
        foreach ($this->REGATTA->getTeamsInRound($master_rounds[$id]) as $team)
          $round_teams[$team->id] = $team;

        foreach ($list as $tid) {
          if (!isset($round_teams[$tid]))
            throw new SoterException(sprintf("Invalid team provided for round \"%s\": %s.", $master_rounds[$id], $tid));

          if (isset($added_teams[$tid]))
            throw new SoterException(sprintf("The same team (%s) may not advance from multiple rounds.", $round_teams[$tid]));
          $masters[$id][$tid] = $round_teams[$tid];
          $added_teams[$tid] = $round_teams[$tid];
        }

        // At least two teams must be imported from each round
        if (count($masters[$id]) < 2)
          throw new SoterException("At least two teams must advance from each round.");
      }
      if (count($masters) < 2)
        throw new SoterException("At least two (independent) rounds must be provided.");

      // ------------------------------------------------------------
      // Start creating the races!
      // ------------------------------------------------------------
      // Create list of existing pairs
      $prev_races = array(); // map of "<teamID>-<teamID>" => Race
      foreach ($masters as $id => $teamlist) {
        $r = $master_rounds[$id];
        foreach ($this->REGATTA->getRacesInRound($r, Division::A(), false) as $race) {
          $id = sprintf('%s-%s', $race->tr_team1->id, $race->tr_team2->id);
          $prev_races[$id] = $race;
        }
      }

      $count = count($this->REGATTA->getRaces(Division::A()));
      $divs = $this->REGATTA->getDivisions();
      $added = array();
      $duplicate = array();

      foreach ($this->makeRoundRobin($added_teams) as $pair) {
        $id1 = sprintf('%s-%s', $pair[0]->id, $pair[1]->id);
        $id2 = sprintf('%s-%s', $pair[1]->id, $pair[0]->id);

        $race = null;
        if (isset($prev_races[$id1]))
          $race = $prev_races[$id1];
        elseif (isset($prev_races[$id2]))
          $race = $prev_races[$id2];

        if ($race != null) {
          $duplicate[] = $race;
          for ($i = 1; $i < count($divs); $i++)
            $duplicate[] = $this->REGATTA->getRace($divs[$i], $race->number);
        }
        else {
          // new race
          $count++;
          foreach ($divs as $div) {
            $race = new Race();
            $race->division = $div;
            $race->number = $count;
            $race->boat = $boat;
            $race->regatta = $this->REGATTA;
            $race->round = $round;
            $race->tr_team1 = $pair[0];
            $race->tr_team2 = $pair[1];
            $added[] = $race;
          }
        }
      }

      DB::insertAll($added);

      foreach ($duplicate as $race)
        $race->addRound($round);

      // master-slave relationship
      foreach ($masters as $id => $list) {
        $round->addMaster($master_rounds[$id], count($list));
      }

      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      Session::pa(new PA(array("Added new \"completion\" round. ",
                               new XA($this->link('race-order', array('order-rounds'=>'', 'round'=>array($round->id))), "Order races"),
                               ".")));
      return array();
    }
    return array();
  }

  /**
   * Creates a round-robin from the given items
   *
   * @param Array $items the items to pair up in round robin
   * @param boolean $swap true to switch the normal order of the teams
   * @return Array:Array a list of all the pairings
   */
  private function makeRoundRobin($items, $swap = false) {
    if (count($items) < 2)
      throw new InvalidArgumentException("There must be at least two items.");
    if (count($items) == 2)
      return array($items);

    $list = array();
    $first = array_shift($items);
    foreach ($this->pairup($first, $items, $swap) as $other)
      $list[] = $other;
    foreach ($this->makeRoundRobin($items, $swap) as $pair)
      $list[] = $pair;
    return $list;
  }

  private function pairup($first, Array $rest, $swap = false) {
    foreach ($rest as $other)
      $list[] = ($swap) ? array($other, $first) : array($first, $other);
    return $list;
  }
}
?>