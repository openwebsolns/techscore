<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2009-10-04
 * @package tscore
 */

/**
 * Page for editing rounds.
 *
 * @author Dayan Paez
 * @version 2012-03-05
 */
class TeamEditRoundPane extends AbstractPane {

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
    $this->PAGE->addContent($p = new XPort("Current rounds"));
    $p->add($f = $this->createForm());
    $f->add(new XP(array(), "Click on the round below to edit that round."));
    $f->add(new FItem("Round order:", $tab = new XQuickTable(array('id'=>'divtable', 'class'=>'narrow'), array("#", "Order", "Title"))));
    while (count($sole_rounds) > 0) {
      $round = array_shift($sole_rounds);
      $rel = array($round->relative_order);
      $lnk = array(new XA($this->link('round', array('r'=>$round->id)), $round));
      if ($round->round_group !== null) {
        foreach ($round->round_group->getRounds() as $other_round) {
          if (isset($sole_rounds['r-' . $other_round->id])) {
            unset($sole_rounds['r-' . $other_round->id]);
            $rel[] = $other_round->relative_order;
            $lnk[] = ", ";
            $lnk[] = new XA($this->link('round', array('r'=>$other_round->id)), $other_round);
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
    if (count($independent_rounds) > 0) {
      $this->PAGE->addContent($p = new XPort("Group Rounds"));
      $p->add(new XP(array(), "Round groups are rounds that are sailed at the same time. The race order is changed so that a number of races from one round are followed by the same number from the next round in the group. This is call the \"collation\" of the group."));

      $p->add($f = $this->createForm());
      $f->add(new FItem("Rounds:", $ul = new XUl(array('class'=>'inline-list'))));
      $max_collation = null;
      foreach ($independent_rounds as $round) {
        $id = 'chk-round-' . $round->id;
        $ul->add(new XLi(array(new XCheckboxInput('round[]', $round->id, array('id'=>$id)),
                               new XLabel($id, $round))));

        $num_races = count($this->REGATTA->getRacesInRound($round, Division::A(), false));
        if ($max_collation === null || $num_races < $max_collation)
          $max_collation = $num_races;
      }
      $f->add(new FItem("Collate every:", new XInput('number', 'collation', count($this->REGATTA->getDivisions()), array('min'=>1, 'max'=>($num_races - 1), 'step'=>1)), "Races"));
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
    $this->PAGE->addContent(new XP(array(), new XA($this->link('round'), "← Back to list of rounds")));

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

    if ($round->round_group !== null) {
      $this->PAGE->addContent($p = new XPort("Races order"));
      $p->add(new XP(array('class'=>'warning'),
                     array(new XStrong("Note:"), " You may not order the races in this round because the round is currently part of a group. To order the races, you must first \"unlink\" the round group by visiting the ",
                           new XA($this->link('round'), "Edit rounds"),
                           " pane.")));
    }
    else {
      // ------------------------------------------------------------
      // Templates
      // ------------------------------------------------------------
      $races = $this->REGATTA->getRacesInRound($round, Division::A());
      $teams = array();
      foreach ($races as $race) {
        $teams[$race->tr_team1->id] = $race->tr_team1;
        $teams[$race->tr_team2->id] = $race->tr_team2;
      }
      $divs = $this->REGATTA->getDivisions();

      $templates = DB::getRaceOrders(count($teams), count($divs));
      if (count($templates) > 0) {
        $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/addTeamToRound.js'));

        $this->PAGE->addContent($p = new XPort("Order races using template"));
        $p->add(new XP(array(), "Order the races automatically by using one of the templates below. Pay close attention to the number of boats per flight. If none of the templates apply, manually order the races by using the form below."));
        $p->add(new XP(array(), "Choose the seeding order for the round by placing incrementing numbers next to the team names."));
        $p->add($form = $this->createForm());
        $form->add($tab = new XQuickTable(array(), array("", "Boats/flight", "Boat rotation", "Description")));

        $frequencies = Race_Order::getFrequencyTypes();
        foreach ($templates as $template) {
          $id = 'inp-' . $template->id;
          $tab->addRow(array($ri = new XRadioInput('template', $template->id, array('id'=>$id)),
                             new XLabel($id, $template->num_boats),
                             new XLabel($id, $frequencies[$template->frequency]),
                             new XLabel($id, $template->description)),
                       array('title' => $template->description));
        }
        if (count($templates) == 1)
          $ri->set('checked', 'checked');

        $form->add(new XH4("Specify seeding order"));
        $form->add($ul = new XUl(array('id'=>'teams-list')));
        $num = 1;
        foreach ($teams as $team) {
          $id = 'team-'.$team->id;
          $ul->add(new XLi(array(new XHiddenInput('team[]', $team->id),
                                 new XTextInput('order[]', $num++, array('id'=>$id)),
                                 new XLabel($id, $team,
                                            array('onclick'=>sprintf('addTeamToRound("%s");', $id))))));
        }

        $form->add(new XP(array('class'=>'p-submit'),
                          array(new XSubmitInput('use-template', "Order races"),
                                new XHiddenInput('round', $round->id))));
      }

      // ------------------------------------------------------------
      // Manual ordering
      // ------------------------------------------------------------
      $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/tablesort.js'));
      $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/toggle-tablesort.js'));
      $this->PAGE->addContent($p = new XPort("Manual race order"));
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

      // order races by number
      $races = array();
      foreach ($this->REGATTA->getRacesInRound($round, Division::A(), false) as $race)
        $races[] = $race;
      usort($races, 'Race::compareNumber');

      $boats = DB::getBoats();
      $boatOptions = array();
      foreach ($boats as $boat)
        $boatOptions[$boat->id] = $boat->name;

      foreach ($races as $i => $race) {
        $teams = $this->REGATTA->getRaceTeams($race);
        $tab->addRow(array(new XTD(array(),
                                   array(new XTextInput('order[]', ($i + 1), array('size'=>2)),
                                         new XHiddenInput('race[]', $race->id))),
                           new XTD(array('class'=>'drag'), $race->number),
                           $teams[0],
                           new XCheckboxInput('swap[]', $race->id),
                           $teams[1],
                           XSelect::fromArray('boat[]', $boatOptions, $race->boat->id)),
                     array('class'=>'sortable'));
      }
      $form->add($p = new XSubmitP('manual-order', "Edit races"));
      $p->add(new XHiddenInput('round', $round->id));
    }
  }

  /**
   * Processes new races and edits to existing races
   */
  public function process(Array $args) {
    // ------------------------------------------------------------
    // Manual order
    // ------------------------------------------------------------
    if (isset($args['manual-order'])) {
      $other_divisions = $this->REGATTA->getDivisions();
      array_shift($other_divisions);

      $round = DB::$V->reqID($args, 'round', DB::$ROUND, "No round provided.");
      if ($round->regatta->id != $this->REGATTA->id || $round->round_group !== null)
        throw new SoterException(sprintf("Invalid round provided: %s.", $round));

      $races = array(); // map of race in A division's ID to races
      $nums = array();  // list of race numbers
      foreach ($this->REGATTA->getRacesInRound($round, Division::A(), false) as $race) {
        $races[$race->id] = array($race);
        foreach ($other_divisions as $div)
          $races[$race->id][] = $this->REGATTA->getRace($div, $race->number);
        $nums[] = $race->number;
      }

      sort($nums, SORT_NUMERIC);
      $next_number = $nums[0];
      unset($nums);

      $map = DB::$V->reqMap($args, array('race', 'boat'), null, "Invalid list of race and boats.");
      // If order list provided (and valid), then use it
      $ord = DB::$V->incList($args, 'order', count($map['race']));
      if (count($ord) > 0)
        array_multisort($ord, SORT_NUMERIC, $map['race'], $map['boat']);

      $rotation = $this->REGATTA->getRotation();
      $swaplist = DB::$V->incList($args, 'swap');
      $to_save = array();
      $sails_to_save = array();
      $races_to_reset = array();
      foreach ($map['race'] as $i => $rid) {
        if (!isset($races[$rid]))
          throw new SoterException("Invalid race provided.");

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
          $edited = true;
          foreach ($list as $r)
            $r->number = $next_number;
        }
        if (in_array($race->id, $swaplist)) {
          $edited = true;
          $team1 = $race->tr_team1;
          $ignore1 = $race->tr_ignore1; // also swap ignore list
          foreach ($list as $r) {
            $r->tr_team1 = $r->tr_team2;
            $r->tr_team2 = $team1;
            $r->tr_ignore1 = $r->tr_ignore2;
            $r->tr_ignore2 = $ignore1;

            // also swap sails, if set
            if ($rotation->isAssigned($r)) {
              $s1 = $rotation->getSail($r, $r->tr_team1);
              $s2 = $rotation->getSail($r, $r->tr_team2);

              $s1->team = $r->tr_team2;
              $s2->team = $r->tr_team1;
              $sails_to_save[] = $s1;
              $sails_to_save[] = $s2;
              $races_to_reset[] = $r;
            }
          }
        }
        if ($edited) {
          foreach ($list as $r)
            $to_save[] = $r;
        }
        unset($races[$rid]);
        $next_number++;
      }
      if (count($races) > 0)
        throw new SoterException("Not all races in round are accounted for.");

      foreach ($to_save as $race)
        DB::set($race, true);
      foreach ($races_to_reset as $race)
        $rotation->reset($race);
      foreach ($sails_to_save as $sail)
        $rotation->setSail($sail);
        
      if (count($to_save) == 0)
        Session::pa(new PA("No races were updated.", PA::I));
      else {
        Session::pa(new PA(sprintf("Races updated for round %s.", $round)));
        UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      }
    }

    // ------------------------------------------------------------
    // Use template
    // ------------------------------------------------------------
    if (isset($args['use-template'])) {
      $divs = $this->REGATTA->getDivisions();

      $template = DB::$V->reqID($args, 'template', DB::$RACE_ORDER, "Invalid or missing template.");
      if ($template->num_divisions != count($divs))
        throw new SoterException("Chosen template is incompatible with this regatta.");

      $round = DB::$V->reqID($args, 'round', DB::$ROUND, "Invalid or missing round.");
      if ($round->regatta != $this->REGATTA)
        throw new SoterException("Invalid round provided.");

      $races = $this->REGATTA->getRacesInRound($round);
      $teams = array();
      $matches = array();
      $nums = array();
      foreach ($races as $race) {
        if ($race->round == $round)
          $nums[] = $race->number;

        $teams[$race->tr_team1->id] = $race->tr_team1;
        $teams[$race->tr_team2->id] = $race->tr_team2;

        $name1 = sprintf("%s-%s", $race->tr_team1->id, $race->tr_team2->id);
        $name2 = sprintf("%s-%s", $race->tr_team2->id, $race->tr_team1->id);
        if (!isset($matches[$name1])) {
          $matches[$name1] = array();
          $matches[$name2] = array();
        }
        $matches[$name1][] = $race;
        $matches[$name2][] = $race;
      }

      if ($template->num_teams != count($teams))
        throw new SoterException("Chosen template is incompatible with number of teams in round.");

      // Seed the teams. Seed them!
      $ids = DB::$V->reqList($args, 'team', count($teams), "Invalid list of team seeds.");
      $order = DB::$V->reqList($args, 'order', count($teams), "Missing list to order teams by.");
      array_multisort($order, SORT_NUMERIC, $ids);

      $seeding = array();
      foreach ($ids as $id) {
        if (!isset($teams[$id]))
          throw new SoterException("Invalid team ID specified.");
        $seeding[] = $teams[$id];
      }

      sort($nums, SORT_NUMERIC);
      $next_number = $nums[0];
      unset($nums);

      $other_divisions = $divs;
      array_shift($other_divisions);

      foreach ($template->template as $pair) {
        $tokens = explode('-', $pair);
        
        $team1 = $seeding[$tokens[0] - 1];
        $team2 = $seeding[$tokens[1] - 1];

        $match = sprintf('%s-%s', $team1->id, $team2->id);
        $races = $matches[$match];
        $race = $races[0];

        if ($race->round == $round) {
          $swap = ($race->tr_team1 != $team1);
          if ($race->number != $next_number || $swap) {
            foreach ($races as $race) {
              $race->number = $next_number;
              if ($swap) {
                $race->tr_team1 = $team1;
                $race->tr_team2 = $team2;
                $ignore1 = $race->tr_ignore1;
                $race->tr_ignore1 = $race->tr_ignore2;
                $race->tr_ignore2 = $ignore1;
              }
              DB::set($race);
            }
          }
          $next_number++;
        }
      }
      Session::pa(new PA("Races sorted according to template."));
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
        foreach ($this->REGATTA->getRacesInRound($round, Division::A(), false) as $race) {
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
      // Validate
      $max_collation = null;
      $rounds = array(); // list of list of races
      $affected_rounds = array();
      foreach (DB::$V->reqList($args, 'round', null, "No rounds provided.") as $rid) {
        if (($round = DB::get(DB::$ROUND, $rid)) === null || $round->regatta != $this->REGATTA)
          throw new SoterException("Invalid round provided: $rid.");

        if ($round->round_group !== null)
          throw new SoterException("Only independent rounds can be grouped.");

        $races = $this->REGATTA->getRacesInRound($round, Division::A(), false);
        $num_races = count($races);
        if ($max_collation === null || $num_races < $max_collation)
          $max_collation = $num_races;

        $list = array();
        foreach ($races as $race)
          $list[] = $race;
        $rounds[] = $list;
        $affected_rounds[$round->id] = $round;
      }
      if (count($rounds) < 2)
        throw new SoterException("At least two rounds must be specified for grouping.");

      $collate = DB::$V->reqInt($args, 'collation', 1, $max_collation, "Invalid collation provided.");

      // Other divisions
      $other_divisions = $this->REGATTA->getDivisions();
      array_shift($other_divisions);

      // Perform collation
      $to_save = array();

      $race_num = $rounds[0][0]->number;
      $race_i = 0;
      $round_i = 0;
      while (true) {
        $race = array_shift($rounds[$round_i]);
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
        $race_i++;

        if (count($rounds[$round_i]) == 0)
          array_splice($rounds, $round_i, 1);

        if (count($rounds) == 0)
          break;

        if (($race_i % $collate) == 0 || !isset($rounds[$round_i]))
          $round_i = ($round_i + 1) % count($rounds);
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
        foreach ($this->REGATTA->getRacesInRound($round, Division::A(), false) as $race) {
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
      foreach ($this->REGATTA->getRacesInRound($round, null, false) as $race)
        DB::remove($race);
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
}
?>