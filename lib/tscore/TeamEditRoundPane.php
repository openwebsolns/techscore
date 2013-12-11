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


    $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/tablesort.js'));
    $this->PAGE->addContent($p = new XPort("Current rounds"));
    $p->add($f = $this->createForm());
    $f->add(new XP(array(), "Click on the round below to edit the races in that round."));
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
      $tab->addRow(array(new XTD(array(), array(new XTextInput('order[]', $round->relative_order, array('size'=>2, 'class'=>'small')),
                                                new XHiddenInput('round[]', $round->id))),
                         new XTD(array('class'=>'drag'), DB::makeRange($rel)),
                         $lnk),
                   array('class'=>'sortable'));
    }
    $f->add(new XSubmitP('order-rounds', "Reorder"));
  }

  private function fillRound($round) {
    $this->PAGE->addContent(new XP(array(), new XA(WS::link(sprintf('/score/%s/races', $this->REGATTA->id)), "← Back to list of rounds")));

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