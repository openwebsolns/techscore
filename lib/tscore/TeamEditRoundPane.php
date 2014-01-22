<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2009-10-04
 * @package tscore
 */

require_once('tscore/AbstractRoundPane.php');

/**
 * Page for editing rounds.
 *
 * @author Dayan Paez
 * @version 2012-03-05
 */
class TeamEditRoundPane extends AbstractRoundPane {

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Edit Rounds", $user, $reg);
    if ($reg->scoring != Regatta::SCORING_TEAM)
      throw new InvalidArgumentException("TeamRacesPane only available for team race regattas.");
  }

  private function fillProgressDiv($rounds, Round $round = null) {
    $this->PAGE->head->add(new LinkCSS('/inc/css/round.css'));
    $this->PAGE->addContent($p = new XP(array('id'=>'progressdiv')));
    $p->add($span = new XSpan(new XA($this->link('round'), "All rounds")));
    if ($round === null)
      $span->set('class', 'current');
    foreach ($rounds as $r) {
      $p->add($span = new XSpan(new XA($this->link('round', array('r'=>$r->id)), $r)));
      if ($round !== null && $round->id == $r->id)
        $span->set('class', 'current');
    }
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
          $this->fillProgressDiv($rounds, $round);
          $this->fillRound($round);
          return;
        }
      }
      Session::pa(new PA("Invalid round requested.", PA::E));
      $this->redirect();
    }

    $this->fillProgressDiv($rounds);

    // ------------------------------------------------------------
    // Current rounds (offer to reorder them)
    // ------------------------------------------------------------
    if (count($rounds) == 0) {
      $this->PAGE->addContent(new XP(array('class'=>'warning'), "No rounds exist in this regatta."));
      return;
    }
    // create map of rounds indexed by ID. The extra "r-" in key is
    // to make sure PHP does not treat the keys as integers, thereby
    // re-assigning them on array_shift, below
    $sole_rounds = array();
    foreach ($rounds as $round)
      $sole_rounds['r-' . $round->id] = $round;

    $independent_rounds = array();

    $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/tablesort.js'));
    $this->PAGE->addContent($p = new XPort("Reorder rounds"));
    $p->add($f = $this->createForm());
    $f->add(new FItem("Round order:", $tab = new XQuickTable(array('id'=>'divtable', 'class'=>'narrow'), array("#", "Order", "Title"))));
    while (count($sole_rounds) > 0) {
      $round = array_shift($sole_rounds);
      $rel = array($round->relative_order);
      $lnk = array($round);
      if ($round->round_group !== null) {
        foreach ($round->round_group->getRounds() as $other_round) {
          if (isset($sole_rounds['r-' . $other_round->id])) {
            unset($sole_rounds['r-' . $other_round->id]);
            $rel[] = $other_round->relative_order;
            $lnk[] = ", ";
            $lnk[] = $other_round;
          }
        }
      }
      else {
        $independent_rounds[] = $round;
      }
      $tab->addRow(array(new XTD(array(), array(new XTextInput('order[]', $round->relative_order, array('size'=>2, 'class'=>'small')),
                                                new XHiddenInput('round[]', $round->id))),
                         new XTD(array('class'=>'drag'), DB::makeRange($rel)),
                         $lnk),
                   array('class'=>'sortable'));
    }
    $f->add(new XSubmitP('order-rounds', "Reorder"));

    // ------------------------------------------------------------
    // Round groups
    // ------------------------------------------------------------
    if (count($independent_rounds) > 1) {
      $this->PAGE->addContent($p = new XPort("Group Rounds"));
      $p->add(new XP(array(), "Round groups are rounds that are sailed at the same time. The race order is changed so that one flight from one round is followed by a flight from the next round in the group."));

      $p->add($f = $this->createForm());
      $f->add(new FItem("Rounds:", $ul = new XUl(array('class'=>'inline-list'))));
      foreach ($independent_rounds as $round) {
        $id = 'chk-round-' . $round->id;
        $ul->add(new XLi(array(new XCheckboxInput('round[]', $round->id, array('id'=>$id)),
                               new XLabel($id, $round))));

        $num_races = count($this->REGATTA->getRacesInRound($round, Division::A()));
      }
      $f->add(new XSubmitP('group-rounds', "Group rounds"));
    }

    // ------------------------------------------------------------
    // Dissolve current round groups
    // ------------------------------------------------------------
    $groups = $this->REGATTA->getRoundGroups();
    if (count($groups) > 0) {
      $this->PAGE->addContent($p = new XPort("Current round groups"));
      $p->add(new XP(array(), "The following table summarizes the list of rounds whose races are ordered together. To separate the rounds, click the \"Unlink\" button next to the group name."));
      $p->add($tab = new XQuickTable(array(), array("Group", "")));
      foreach ($groups as $group) {
        $my_rounds = array();
        $my_round_ids = array();
        foreach ($group->getRounds() as $round) {
          $my_rounds[] = $round;
          $my_round_ids[] = $round->id;
        }

        $f = $this->createForm();
        $f->add(new XHiddenInput('round_group', $group->id));
        $f->add(new XSubmitInput('unlink-group', "Unlink"));

        $tab->addRow(array(implode(", ", $my_rounds), $f));
      }
    }
  }

  private function fillRound($round) {
    // $this->PAGE->addContent(new XP(array(), new XA($this->link('round'), "← Back to list of rounds")));

    $teamOpts = array();
    $teamFullOpts = array("null" => "");
    foreach ($this->REGATTA->getTeams() as $team) {
      $teamOpts[$team->id] = $team;
      $teamFullOpts[$team->id] = $team;
    }

    // ------------------------------------------------------------
    // Edit round name (and other attributes)
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Edit round information"));
    $p->add($form = $this->createForm());
    $form->add(new FItem("Label:", new XTextInput('title', $round->title)));

    $masters = $round->getMasters();
    $type = "Simple round robin";
    if (count($masters) > 0) {
      $type = "Completion round for ";
      foreach ($masters as $i => $master) {
        if ($i > 0)
          $type .= ", ";
        $type .= $master->master;
      }
    }
    $form->add(new FItem("Type:", new XStrong($type)));
    $form->add(new FItem("Number of teams:", new XStrong($round->num_teams)));
    $form->add(new FItem("Number of boats:", new XStrong($round->num_boats)));

    $types = Race_Order::getFrequencyTypes();
    $form->add(new FItem("Rotation:", new XStrong($types[$round->rotation_frequency])));
      
    $form->add($p = new XSubmitP('edit-round', "Edit"));
    $p->add(new XHiddenInput('round', $round->id));

    // ------------------------------------------------------------
    // Delete
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Delete round"));
    if ($round->round_group !== null) {
      $p->add(new XP(array('class'=>'warning'),
                     array(new XStrong("Note:"), " You may not delete this round because it is being sailed as part of a group. In order to delete the round, you must first \"unlink\" the round group by visiting the ",
                           new XA($this->link('round'), "Edit rounds"),
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

    // ------------------------------------------------------------
    // Order?
    // ------------------------------------------------------------
    if ($round->round_group !== null) {
      $this->PAGE->addContent($p = new XPort("Races order"));
      $p->add(new XP(array('class'=>'warning'),
                     array(new XStrong("Note:"), " You may not order the races in this round because the round is currently part of a group. To order the races, you must first \"unlink\" the round group by visiting the ",
                           new XA($this->link('round'), "Edit rounds"),
                           " pane.")));
    }
    else {
      $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/tablesort.js'));
      $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/toggle-tablesort.js'));
      $this->PAGE->addContent($p = new XPort("Race order"));
      $p->add($form = $this->createForm());
      $form->set('id', 'edit-races-form');
      $form->add(new XNoScript("To reorder the races, indicate the relative desired order in the first cell."));
      $form->add(new XScript('text/javascript', null, 'var f = document.getElementById("edit-races-form"); var p = document.createElement("p"); p.appendChild(document.createTextNode("To reorder the races, move the rows below by clicking and dragging on the first cell (\"#\") of that row.")); f.appendChild(p);'));
      $form->add(new XP(array(), "You may also edit the associated boat for each race. Click the \"Edit races\" button to save changes. Extra (unused) races will be removed at the end of the regatta."));
      $form->add(new XP(array('class'=>'warning'), "Hint: For large rotations, click \"Edit races\" at the bottom of page often to save your work."));
      $form->add(new XNoScript(array(new XP(array(),
                                            array(new XStrong("Important:"), " check the edit column if you wish to edit that race. The race will not be updated regardless of changes made otherwise.")))));
      $header = array("Order", "#");
      $header[] = "First team";
      $header[] = "← Swap →";
      $header[] = "Second team";
      $header[] = "Boat";
      $form->add($tab = new XQuickTable(array('id'=>'divtable', 'class'=>'teamtable'), $header));

      $boats = DB::getBoats();
      $boatOptions = array();
      foreach ($boats as $boat)
        $boatOptions[$boat->id] = $boat->name;

      $teams = array();
      for ($i = 0; $i < $round->num_teams; $i++)
        $teams[] = new XEm(sprintf("Team %d", ($i + 1)));
      foreach ($round->getSeeds() as $seed)
        $teams[$seed->seed - 1] = $seed->team;

      $races = $this->REGATTA->getRacesInRound($round, Division::A());
      for ($i = 0; $i < count($round->race_order); $i++) {
        $race = $races[$i];
        $pair = $round->getRaceOrderPair($i);
        $t0 = $teams[$pair[0] - 1];
        $t1 = $teams[$pair[1] - 1];

        $tab->addRow(array(array(new XTextInput('order[]', ($i + 1), array('size'=>2)),
                                 new XHiddenInput('race[]', $i)),
                           new XTD(array('class'=>'drag'), ($i + 1)),
                           $t0,
                           new XCheckboxInput('swap[]', $i),
                           $t1,
                           XSelect::fromArray('boat[]', $boatOptions, $race->boat->id)),
                     array('class'=>'sortable'));
      }

      $form->add($p = new XSubmitP('manual-order', "Edit races"));
      $p->add(new XHiddenInput('round', $round->id));
    }

    // ------------------------------------------------------------
    // Rotation
    // ------------------------------------------------------------
    $form = $this->createRotationForm($round);
    $form->add(new XSubmitP('set-rotation', "Set sails"));
    $form->add(new XHiddenInput('round', $round->id));

    // ------------------------------------------------------------
    // Teams
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Teams (seeds)"));
    $p->add($form = $this->createForm());
    $this->fillTeamsForm($form, $round);
    $form->add(new XSubmitP('set-seeds', "Set seeds"));
    $form->add(new XHiddenInput('round', $round->id));
  }

  /**
   * Processes new races and edits to existing races
   */
  public function process(Array $args) {
    // ------------------------------------------------------------
    // Seeds
    // ------------------------------------------------------------
    if (isset($args['set-seeds'])) {
      $round = DB::$V->reqID($args, 'round', DB::$ROUND, "No round provided.");
      if ($round->regatta->id != $this->REGATTA->id)
        throw new SoterException(sprintf("Invalid round provided: %s.", $round));

      $seeds = $this->processSeeds($args, $round, $round->getMasters());
      $teams_in_seeds = array();
      foreach ($seeds as $seed)
        $teams_in_seeds[$seed->team->id] = $seed->team;
        
      // Determine which teams must remain
      $slaves = $round->getSlaves();
      $locked_teams = array();
      foreach ($round->getSeeds() as $seed) {
        if ($this->teamHasScoresInRound($round, $seed->team)) {
          if (!isset($teams_in_seeds[$seed->team->id]))
            throw new SoterException(sprintf("Team %s must be present in round due to scored races.", $seed->team));
          $locked_teams[$seed->team->id] = $seed->team;
        }
        elseif ($this->teamInSlaveRounds($round, $slaves, $seed->team)) {
          if (!isset($teams_in_seeds[$seed->team->id]))
            throw new SoterException(sprintf("Team %s must be present in round because it is being carried over to other round(s).", $seed->team));
          $locked_teams[$seed->team->id] = $seed->team;
        }
      }

      // Scored races complicate matters because they need to be moved
      // according to the matchup, because of foreign key constraints.
      // Map each existing race by team pairing
      $teamRaceMap = array();
      $restRaceMap = array();

      $races = $this->REGATTA->getRacesInRound($round);
      $racenums = array();
      foreach ($races as $i => $race) {
        $racenums[$race->number] = $race->number;

        if (count($this->REGATTA->getFinishes($race)) > 0) {
          $id = sprintf('%s-%s', $race->tr_team1->id,  $race->tr_team2->id);
          if ($race->tr_team1->id > $race->tr_team2->id)
            $id = sprintf('%s-%s', $race->tr_team2->id,  $race->tr_team1->id);

          if (!isset($teamRaceMap[$id]))
            $teamRaceMap[$id] = array();
          $teamRaceMap[$id][(string)$race->division] = $race;
        }
        else {
          if (!isset($restRaceMap[$race->number]))
            $restRaceMap[$race->number] = array();
          $restRaceMap[$race->number][(string)$race->division] = $race;
        }
      }

      $teams = array();
      for ($i = 1; $i <= $round->num_teams; $i++) {
        if (isset($seeds[$i]))
          $teams[] = $seeds[$i]->team;
        else
          $teams[] = null;
      }

      $to_save = array();
      for ($i = 0; $i < count($round->race_order); $i++) {
        $racenum = array_shift($racenums);
        $pair = $round->getRaceOrderPair($i);
        $t1 = $teams[$pair[0] - 1];
        $t2 = $teams[$pair[1] - 1];

        if ($t1 !== null && $t2 !== null) {
          $id = sprintf('%s-%s', $t1->id, $t2->id);
          if ($t1->id > $t2->id)
            $id = sprintf('%s-%s', $t2->id, $t1->id);

          if (isset($teamRaceMap[$id])) {
            foreach ($teamRaceMap[$id] as $race) {
              if ($race->number != $racenum || $race->tr_team1 != $t1) {
                $race->number = $racenum;
                $race->tr_team1 = $t1;
                $race->tr_team2 = $t2;
                $to_save[] = $race;
              }
            }
            unset($teamRaceMap[$id]);
            continue;
          }
        }

        $list = array_shift($restRaceMap);
        foreach ($list as $race) {
          if ($race->number != $racenum || $race->tr_team1 != $t1 || $race->tr_team2 != $t2) {
            $race->number = $racenum;
            $race->tr_team1 = $t1;
            $race->tr_team2 = $t2;
            $to_save[] = $race;
          }
        }
      }

      // Save all information
      $round->setSeeds($seeds);
      foreach ($to_save as $race)
        DB::set($race);

      Session::pa(new PA(sprintf("Updated seeds for \"%s\".", $round)));
      if ($round->rotation !== null) {
        $this->reassignRotation($round);
        Session::pa(new PA("Updated the rotation as well.", PA::I));
      }
    }

    // ------------------------------------------------------------
    // Rotation
    // ------------------------------------------------------------
    if (isset($args['set-rotation'])) {
      $round = DB::$V->reqID($args, 'round', DB::$ROUND, "No round provided.");
      if ($round->regatta->id != $this->REGATTA->id)
        throw new SoterException(sprintf("Invalid round provided: %s.", $round));

      $this->processSails($args, $round);
      $this->reassignRotation($round);
      DB::set($round);
      Session::pa(new PA(sprintf("Updated rotation for \"%s\".", $round)));
    }

    // ------------------------------------------------------------
    // Order
    // ------------------------------------------------------------
    if (isset($args['manual-order'])) {
      $other_divisions = $this->REGATTA->getDivisions();
      array_shift($other_divisions);

      $round = DB::$V->reqID($args, 'round', DB::$ROUND, "No round provided.");
      if ($round->regatta->id != $this->REGATTA->id || $round->round_group !== null)
        throw new SoterException(sprintf("Invalid round provided: %s.", $round));

      $races = array(); // map of race in A division's ID to races
      $nums = array();  // list of race numbers
      foreach ($this->REGATTA->getRacesInRound($round, Division::A()) as $i => $race) {
        $list = array($race);
        foreach ($other_divisions as $div)
          $list[] = $this->REGATTA->getRace($div, $race->number);
        $races[] = $list;
        $nums[] = $race->number;
      }

      sort($nums, SORT_NUMERIC);
      $next_number = $nums[0];
      unset($nums);

      $map = DB::$V->reqMap($args, array('race', 'boat'), count($races), "Invalid list of race and boats.");
      // If order list provided (and valid), then use it
      $ord = DB::$V->incList($args, 'order', count($map['race']));
      if (count($ord) > 0)
        array_multisort($ord, SORT_NUMERIC, $map['race'], $map['boat']);

      // Make sure that boat contains all indices
      for ($i = 0; $i < count($races); $i++) {
        if (!in_array($i, $map['race']))
          throw new SoterException(sprintf("Missing race index %d.", $i));
      }

      $neworder = array();
      $swaplist = DB::$V->incList($args, 'swap');
      $to_save = array();
      $redo_rotation = false;
      $races_to_reset = array();
      foreach ($map['race'] as $i => $rid) {
        if (!isset($races[$rid]))
          throw new SoterException("Invalid race provided.");

        $pair = $round->getRaceOrderPair($rid);

        $boat = DB::getBoat($map['boat'][$i]);
        if ($boat === null)
          throw new SoterException("Invalid boat specified.");

        $list = $races[$rid];
        $race = $list[0];
        $edited = false;
        if ($race->boat != $boat) {
          $edited = true;
          foreach ($list as $r)
            $r->boat = $boat;
        }
        if ($race->number != $next_number) {
          $redo_rotation = true;
          $edited = true;
          foreach ($list as $r)
            $r->number = $next_number;
        }
        if (in_array($i, $swaplist)) {
          $redo_rotation = true;
          $edited = true;
          $team1 = $race->tr_team1;
          $ignore1 = $race->tr_ignore1; // also swap ignore list
          foreach ($list as $r) {
            $r->tr_team1 = $r->tr_team2;
            $r->tr_team2 = $team1;
            $r->tr_ignore1 = $r->tr_ignore2;
            $r->tr_ignore2 = $ignore1;
          }

          $swap = $pair[0];
          $pair[0] = $pair[1];
          $pair[1] = $swap;
        }
        if ($edited) {
          foreach ($list as $r)
            $to_save[] = $r;
        }
        unset($races[$rid]);
        $neworder[] = sprintf("%d-%d", $pair[0], $pair[1]);
        $next_number++;
      }
      if (count($races) > 0)
        throw new SoterException("Not all races in round are accounted for.");

      $round->race_order = $neworder;
      DB::set($round);

      foreach ($to_save as $race)
        DB::set($race, true);

      // Fix rotation, if any
      if ($redo_rotation && $round->rotation !== null) {
        $this->reassignRotation($round);
        Session::pa(new PA("Round rotation has been updated.", PA::I));
      }
        
      if (count($to_save) == 0)
        Session::pa(new PA("No races were updated.", PA::I));
      else {
        Session::pa(new PA(sprintf("Races updated for round %s.", $round)));
        UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      }
    }

    // ------------------------------------------------------------
    // Unlink group
    // ------------------------------------------------------------
    if (isset($args['unlink-group'])) {
      $group = DB::$V->reqID($args, 'round_group', DB::$ROUND_GROUP, "Invalid or missing group of rounds to unlink.");
      $rounds = $group->getRounds();
      DB::remove($group);

      // renumber the races within rounds. Since rounds are
      // "contiguous", simply number the races in succession
      $other_divisions = $this->REGATTA->getDivisions();
      array_shift($other_divisions);

      $next_number = null;
      foreach ($rounds as $round) {
        foreach ($this->REGATTA->getRacesInRound($round, Division::A()) as $race) {
          if ($next_number === null) {
            $next_number = $race->number + 1;
            continue;
          }
          if ($next_number != $race->number) {
            foreach ($other_divisions as $division) {
              $r = $this->REGATTA->getRace($division, $race->number);
              $r->number = $next_number;
              DB::set($r);
            }
            $race->number = $next_number;
            DB::set($race);
          }
          $next_number++;
        }
      }
      Session::pa(new PA("Unlinked rounds and re-numbered races."));
    }

    // ------------------------------------------------------------
    // Group rounds
    // ------------------------------------------------------------
    if (isset($args['group-rounds'])) {
      $other_divisions = $this->REGATTA->getDivisions();

      // Validate
      $affected_rounds = array();
      $rounds = array();
      $races = array();
      $race_index = array();
      $flight_size = array();
      foreach (DB::$V->reqList($args, 'round', null, "No rounds provided.") as $rid) {
        if (($round = DB::get(DB::$ROUND, $rid)) === null || $round->regatta != $this->REGATTA)
          throw new SoterException("Invalid round provided: $rid.");

        if ($round->round_group !== null)
          throw new SoterException("Only independent rounds can be grouped.");

        if (!isset($affected_rounds[$round->id])) {
          $races[$round->id] = $this->REGATTA->getRacesInRound($round, Division::A());
          $race_index[$round->id] = 0;
          $flight_size[$round->id] = $round->num_boats / (2 * count($other_divisions));
          $affected_rounds[$round->id] = $round;
          $rounds[] = $round;
        }
      }
      if (count($rounds) < 2)
        throw new SoterException("At least two rounds must be specified for grouping.");

      // Other divisions
      array_shift($other_divisions);

      // Perform collation
      $to_save = array();

      $race_num = $races[$rounds[0]->id][0]->number;
      $round_index = 0;
      while (true) {
        $round = $rounds[$round_index];
        $end = $race_index[$round->id] + $flight_size[$round->id];
        for (; $race_index[$round->id] < count($races[$round->id]) && $race_index[$round->id] < $end; $race_index[$round->id]++) {
          $race = $races[$round->id][$race_index[$round->id]];
          if ($race->number != $race_num) {
            foreach ($other_divisions as $div) {
              $r = $this->REGATTA->getRace($div, $race->number);
              $r->number = $race_num;
              $to_save[] = $r;
            }
            $race->number = $race_num;
            $to_save[] = $race;
          }
          $race_num++;
        }
        if ($race_index[$round->id] >= count($races[$round->id])) {
          array_splice($rounds, $round_index, 1);
        }
        else {
          $round_index++;
        }
        if (count($rounds) == 0)
          break;

        $round_index = $round_index % count($rounds);
      }

      // Save races
      foreach ($to_save as $race) {
        DB::set($race);
      }

      // Create round
      $group = new Round_Group();
      foreach ($affected_rounds as $round) {
        $round->round_group = $group;
        DB::set($round);
      }

      // Ensure proper numbering of other races
      $other_rounds = array();
      $do_add = false;
      foreach ($this->REGATTA->getRounds() as $round) {
        if (!isset($affected_rounds[$round->id])) {
          if ($do_add)
            $other_rounds[] = $round;
        }
        else {
          $do_add = true;
          unset($affected_rounds[$round->id]);
        }
        if (count($affected_rounds) == 0)
          break;
      }
      $others_changed = array();
      foreach ($other_rounds as $round) {
        $changed = false;
        foreach ($this->REGATTA->getRacesInRound($round, Division::A()) as $race) {
          foreach ($other_divisions as $div) {
            $r = $this->REGATTA->getRace($div, $race->number);
            if ($r->number != $next_number) {
              $changed = true;
              $r->number = $next_number;
              DB::set($r);
            }
            $race->number = $next_number;
            DB::set($race);
          }
          $next_number++;
        }
        if ($changed)
          $others_changed[] = $round;
      }

      Session::pa(new PA("Created round group."));
      if (count($others_changed) > 0)
        Session::pa(new PA(sprintf("Also re-numbered races for round(s) %s.", implode(", ", $other_changed)), PA::I));
    }

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
        foreach ($round->getMasterRounds() as $other) {
          if (!isset($edited[$other->id]))
            throw new SoterException(sprintf("Round \"%s\" must come after \"%s\" because it contains races carried over.", $round, $other));
        }

        $round->relative_order = $roundnum++;

        if (isset($round_groups[$rid])) {
          foreach ($round_groups[$rid] as $other_round) {
            $other_round->relative_order = $roundnum++;
            $edited[$other_round->id] = $other_round;
          }
          foreach ($this->REGATTA->getRacesInRoundGroup($round->round_group, Division::A()) as $race) {
            foreach ($divs as $div) {
              $r = $this->REGATTA->getRace($div, $race->number);
              $r->number = $racenum;
              $races[] = $r;
            }
            $racenum++;
          }
        }
        else {
          foreach ($this->REGATTA->getRacesInRound($round, Division::A()) as $race) {
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
      foreach ($this->REGATTA->getRacesInRound($round) as $race)
        DB::remove($race);
      DB::remove($round);

      // Order races of all rounds AFTER this one
      $divs = $this->REGATTA->getDivisions();

      $round_num = 1;
      $race_num = 1;
      foreach ($rounds as $rid => $other) {
        if ($other->relative_order < $round->relative_order) {
          $race_num += count($this->REGATTA->getRacesInRound($other, Division::A()));
          $round_num++;
          continue;
        }
        $other->relative_order = $round_num++;
        DB::set($other, true);
        foreach ($this->REGATTA->getRacesInRound($other, Division::A()) as $race) {
          foreach ($divs as $div) {
            $r = $this->REGATTA->getRace($div, $race->number);
            if ($r !== null) {
              $r->number = $race_num;
              DB::set($r, true);
            }
          }
          $race_num++;
        }
      }
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      Session::pa(new PA("Removed round $round."));
      $this->REGATTA->setData(); // changed races
      if ($scored) {
        $this->REGATTA->setRanks();
        foreach ($this->REGATTA->getTeams() as $team)
          UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_RANK, $team->school);
        Session::pa(new PA("Re-ranked teams.", PA::I));
      }
    }
    return array();
  }

  /**
   * Helper function to (re-)apply rotation to a round
   *
   * @param Round $round the round
   */
  private function reassignRotation(Round $round) {
    $divisions = $this->REGATTA->getDivisions();
    $teams = array();
    for ($i = 0; $i < $round->num_teams; $i++)
      $teams[] = null;
    foreach ($round->getSeeds() as $seed)
      $teams[$seed->seed - 1] = $seed->team;

    $rotation = $this->REGATTA->getRotation();
    $sails = $round->rotation->assignSails($round, $teams, $divisions, $round->rotation_frequency);
    $races = $this->REGATTA->getRacesInRound($round, Division::A());
    array_shift($divisions);
    foreach ($races as $i => $race) {
      $pair = $round->getRaceOrderPair($i);

      foreach (array(0, 1) as $pairIndex) {
        $team = $teams[$pair[$pairIndex] - 1];
        if ($team !== null) {
          $sail = $sails[$i][$pair[$pairIndex]][(string)$race->division];
          $sail->race = $race;
          $sail->team = $team;
          $rotation->setSail($sail);
          foreach ($divisions as $div) {
            $r = $this->REGATTA->getRace($div, $race->number);
            $sail = $sails[$i][$pair[$pairIndex]][(string)$r->division];
            $sail->race = $r;
            $sail->team = $team;
            $rotation->setSail($sail);
          }
        }
      }
    }
  }
}
?>