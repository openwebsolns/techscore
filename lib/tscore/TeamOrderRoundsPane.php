<?php
use \tscore\utils\RoundGrouper;

require_once('tscore/AbstractRoundPane.php');

/**
 * Page for editing rounds.
 *
 * @author Dayan Paez
 * @version 2012-03-05
 */
class TeamOrderRoundsPane extends AbstractRoundPane {

  const SUBMIT_GROUP = 'group-rounds';

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
      $this->PAGE->addContent(new XWarning("No rounds exist in this regatta."));
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
    $f->add(new FReqItem("Round order:", $tab = new XQuickTable(array('id'=>'divtable', 'class'=>'narrow'), array("#", "Order", "Title"))));
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
      $tab->addRow(array(new XTD(array(), array(new XNumberInput('order[]', $round->relative_order, 1, null, 1, array('size'=>2, 'class'=>'small', 'required'=>'required')),
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
      require_once('xml5/XMultipleSelect.php');
      $f->add(new FReqItem("Rounds:", $ul = new XMultipleSelect('round[]')));
      foreach ($independent_rounds as $round) {
        $ul->addOption($round->id, $round);
      }
      $f->add(new XSubmitP(self::SUBMIT_GROUP, "Group rounds"));
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
      $group = DB::$V->reqID($args, 'round_group', DB::T(DB::ROUND_GROUP), "Invalid or missing group of rounds to unlink.");
      $rounds = $group->getRounds();
      DB::remove($group);

      // renumber the races within rounds. Since rounds are
      // "contiguous", simply number the races in succession
      $other_divisions = $this->REGATTA->getDivisions();
      array_shift($other_divisions);

      $races_changed = array();
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
              $races_changed[] = $r;
            }
            $race->number = $next_number;
            $races_changed[] = $race;
          }
          $next_number++;
        }
      }

      foreach ($races_changed as $r)
        DB::set($r);
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      Session::pa(new PA("Unlinked rounds and re-numbered races."));
    }

    // ------------------------------------------------------------
    // Group rounds
    // ------------------------------------------------------------
    if (array_key_exists(self::SUBMIT_GROUP, $args)) {
      // Create lookup map for fast validation
      $availableRounds = array();
      foreach ($this->REGATTA->getRounds() as $round) {
        $availableRounds[$round->id] = $round;
      }
      $availableRoundIds = array_keys($availableRounds);

      // Validate
      $rounds = array();
      foreach (array_unique(DB::$V->reqList($args, 'round', null, "No rounds provided.")) as $rid) {
        $roundIndex = array_search($rid, $availableRoundIds);
        if ($roundIndex === false) {
          throw new SoterException("Invalid round provided: $rid.");
        }

        $round = $availableRounds[$rid];
        if ($round->round_group !== null) {
          throw new SoterException("Only independent rounds can be grouped.");
        }

        $count = count($rounds);
        if ($count > 0) {
          if ($roundIndex === 0 || $availableRoundIds[$roundIndex - 1] != $rounds[$count - 1]->id) {
            throw new SoterException("Rounds must be in consecutive order before they are grouped.");
          }
        }
        $rounds[] = $round;
      }
      if (count($rounds) < 2) {
        throw new SoterException("At least two rounds must be specified for grouping.");
      }

      $grouper = new RoundGrouper($this->REGATTA);
      foreach ($grouper->group($rounds) as $race) {
        DB::set($race);
      }

      // Create round
      $group = new Round_Group();
      foreach ($rounds as $round) {
        $round->round_group = $group;
        DB::set($round);
      }

      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      Session::pa(new PA("Created round group."));
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
