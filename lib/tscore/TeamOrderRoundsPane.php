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
class TeamOrderRoundsPane extends AbstractRoundPane {

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
    $rounds = $this->REGATTA->getRounds();

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
      $tab->addRow(array(new XTD(array(), array(new XNumberInput('order[]', $round->relative_order, 1, null, 1, array('size'=>2, 'class'=>'small')),
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

  /**
   * Processes new races and edits to existing races
   */
  public function process(Array $args) {

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

      // Ensure proper numbering of other races
      $other_rounds = array();
      $do_add = false;
      $rounds_copy = $affected_rounds;
      foreach ($this->REGATTA->getRounds() as $round) {
        if (!isset($rounds_copy[$round->id])) {
          if ($do_add)
            $other_rounds[] = $round;
        }
        else {
          $do_add = true;
          unset($rounds_copy[$round->id]);
        }
        if (count($rounds_copy) == 0)
          break;
      }

      $others_changed = array();
      foreach ($other_rounds as $round) {
        $changed = false;
        foreach ($this->REGATTA->getRacesInRound($round, Division::A()) as $race) {
          foreach ($other_divisions as $div) {
            $r = $this->REGATTA->getRace($div, $race->number);
            if ($r->number != $race_num) {
              $changed = true;
              $r->number = $race_num;
              $to_save[] = $r;
            }
            $race->number = $race_num;
            $to_save[] = $race;
          }
          $race_num++;
        }
        if ($changed)
          $others_changed[] = $round;
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

      Session::pa(new PA("Created round group."));
      if (count($others_changed) > 0)
        Session::pa(new PA(sprintf("Also re-numbered races for round(s) %s.", implode(", ", $others_changed)), PA::I));
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
        if ($round->sailoff_for_round !== null && !isset($edited[$round->sailoff_for_round->id]))
          throw new SoterException(sprintf("Round \"%s\" must come after \"%s\" because it is a sailoff round.", $round, $round->sailoff_for_round));

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
  }
}
?>