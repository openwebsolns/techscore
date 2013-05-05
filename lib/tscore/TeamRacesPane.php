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
      $f->add(new XP(array(), "Create a new round by using an existing round's races as references."));
      $f->add(new FItem("Round label:", new XTextInput('title', "Round " . (count($rounds) + 1))));
      $f->add(new FItem("Previous round:", XSelect::fromDBM('template', $rounds)));
      $f->add($fi = new FItem("Swap teams:", new XCheckboxInput('swap', 1, array('id'=>'chk-swap'))));
      $fi->add(new XLabel('chk-swap', "Reverse the teams in each race."));
      $f->add(new XSubmitP('create-from-existing', "Create round"));
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
                      "To include a team, place a number next to the team name indicating their seeding order for the round-robin. Order numbers in ascending order. Teams with no number will not be included in the round-robin."));
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

    // Carry over from previous round(s)
    if (count($rounds) > 0) {
      $form->add(new XP(array(), "It is possible to carry the finishes from one or more previous rounds when creating the new one. In that case, any race in the new round where both teams have met in one of the selected rounds below will be used in the new round. Note that the first such race will be used."));
      $form->add(new FItem("Carry over from:", $ul = new XUl(array('class'=>'inline-list'))));
      foreach ($rounds as $round) {
	$id = 'chk-' . $round->id;
	$ul->add(new XLi(array(new XCheckboxInput('duplicate-round[]', $round->id, array('id'=>$id)),
			       new XLabel($id, $round))));
      }
    }

    $form->add(new XSubmitP('add-round', "Add round"));
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
    elseif (count($this->REGATTA->getRacesCarriedOver($round)) > 0) {
      $p->add(new XP(array('class'=>'warning'),
		     array(new XStrong("Note:"), " Some races in this round are carried over to other rounds. Because of this, this round may not be deleted, as this would create incomplete round robins elsewhere. To delete this round, you must first delete all relying rounds.")));
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
        $round->relative_order = $roundnum++;

	if (isset($round_groups[$rid])) {
	  foreach ($round_groups[$rid] as $other_round) {
	    $other_round->relative_order = $roundnum++;
	    $edited[] = $other_round;
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
        $edited[] = $round;
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

      if (count($this->REGATTA->getRacesCarriedOver($round)) > 0)
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
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      Session::pa(new PA(array(sprintf("Added new round %s with %d race(s). ", $round, $num_added),
			       new XA($this->link('race-order', array('order-rounds'=>'', 'round'=>array($round->id))), "Edit races"),
			       ".")));
    }

    // ------------------------------------------------------------
    // Add round
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

      // Previous races
      $prev_races = array(); // map of "<teamID>-<teamID>" => [Race,
			     // ...]
      foreach (DB::$V->incList($args, 'duplicate-round') as $r) {
	if (!isset($rounds[$r]))
	  throw new SoterException("Invalid previous round from which to carry over races.");
	foreach ($this->REGATTA->getRacesInRound($rounds[$r], Division::A(), false) as $race) {
	  $id = sprintf('%s-%s', $race->tr_team1->id, $race->tr_team2->id);
	  if (!isset($prev_races[$id]))
	    $prev_races[$id] = array();
	  $prev_races[$id][] = $race;
	}
      }

      // Assign next race number
      $count = count($this->REGATTA->getRaces(Division::A()));

      // Create round robin
      $num_added = 0;
      $num_duplicate = 0;
      $divs = array(Division::A(), Division::B(), Division::C());

      $swap = false;
      for ($meeting = 0; $meeting < $meetings; $meeting++) {
        foreach ($this->pairup($teams, $swap) as $pair) {
	  // check for existing race to duplicate
	  $existing_race = null;
	  $id = sprintf('%s-%s', $pair[0]->id, $pair[1]->id);
	  if (isset($prev_races[$id]) && count($prev_races[$id]) > 0)
	    $existing_race = array_shift($prev_races[$id]);
	  
	  $id = sprintf('%s-%s', $pair[1]->id, $pair[0]->id);
	  if (isset($prev_races[$id]) && count($prev_races[$id]) > 0)
	    $existing_race = array_shift($prev_races[$id]);

	  if ($existing_race !== null) {
	    $existing_race->addRound($round);
	    foreach (array(Division::B(), Division::C()) as $div) {
	      $race = $this->REGATTA->getRace($div, $existing_race->number);
	      $race->addRound($round);
	    }
	    $num_duplicate++;
	  }
	  else {
	    $count++;
	    foreach ($divs as $div) {
	      $race = new Race();
	      $race->division = $div;
	      $race->number = $count;
	      $race->boat = $boat;
	      $race->regatta = $this->REGATTA;
	      $race->round = $round;
	      DB::set($race, false);
	    }
	    $this->REGATTA->setRaceTeams($race, $pair[0], $pair[1]);
	    $num_added++;
	  }
        }
        $swap = !$swap;
      }
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      $mes = array("Added $num_added new races in round $round. ");
      if ($num_duplicate > 0)
	$mes[] = "$num_duplicate race(s) carried over from previous rounds. ";
      $mes[] = new XA($this->link('race-order', array('order-rounds'=>'', 'round'=>array($round->id))), "Edit races");
      $mes[] = ".";
      Session::pa(new PA($mes));
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
  private function pairup($items, $swap = false) {
    if (count($items) < 2)
      throw new InvalidArgumentException("There must be at least two items.");
    if (count($items) == 2)
      return array($items);

    $list = array();
    $first = array_shift($items);
    foreach ($items as $other)
      $list[] = ($swap) ? array($other, $first) : array($first, $other);
    foreach ($this->pairup($items, $swap) as $pair)
      $list[] = $pair;
    return $list;
  }
}
?>