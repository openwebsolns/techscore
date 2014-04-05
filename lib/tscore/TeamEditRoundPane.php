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
    parent::__construct("Edit Round", $user, $reg);
    if ($reg->scoring != Regatta::SCORING_TEAM)
      throw new InvalidArgumentException("TeamRacesPane only available for team race regattas.");
  }

  const SETTINGS = 'settings';
  const RACES = 'races';
  const SAILS = 'sails';
  const TEAMS = 'teams';
  const DELETE = 'delete';

  private static $SECTIONS = array(self::SETTINGS => "Settings",
                                   self::RACES => "Race order",
                                   self::SAILS => "Sail # and Colors",
                                   self::TEAMS => "Teams",
                                   self::DELETE => "Delete");
  
  private function fillProgressDiv(Round $round, $section) {
    $this->PAGE->head->add(new LinkCSS('/inc/css/round.css'));
    $this->PAGE->addContent($p = new XP(array('id'=>'progressdiv')));
    foreach (self::$SECTIONS as $key => $title) {
      $p->add($span = new XSpan(new XA($this->link('round', array('r'=>$round->id, 'section'=>$key)), $title)));
      if ($section == $key)
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
    $round = DB::$V->incID($args, 'r', DB::$ROUND);
    if ($round !== null && $round->regatta->id != $this->REGATTA->id)
      Session::pa(new PA("Invalid round requested.", PA::E));

    if ($round === null) {
      $rounds = $this->REGATTA->getRounds();
      $round = $rounds[0];
    }

    $this->PAGE->addContent(new XH3($round));

    $section = DB::$V->incKey($args, 'section', self::$SECTIONS, self::SETTINGS);
    $this->fillProgressDiv($round, $section);

    if ($section == self::SETTINGS) {
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

    }

    if ($section == self::DELETE) {
      // ------------------------------------------------------------
      // Delete
      // ------------------------------------------------------------
      $this->PAGE->addContent($p = new XPort("Delete round"));
      if ($round->round_group !== null) {
        $p->add(new XP(array('class'=>'warning'),
                       array(new XStrong("Note:"), " You may not delete this round because it is being sailed as part of a group. In order to delete the round, you must first \"unlink\" the round group by visiting the ",
                             new XA($this->link('order-rounds'), "Order rounds"),
                             " pane.")));
      }
      else {
        $slaves = $round->getSlaves();
        if (count($slaves) > 0) {
          $list = array();
          foreach ($slaves as $rel)
            $list[] = $rel->slave;
          $p->add(new XP(array('class'=>'warning'),
                         array(new XStrong("Note:"),
                               sprintf(" Races in this round are carried over to %s. Because of this, this round may not be deleted, as this would create incomplete round robins. To delete this round, you must first delete the dependent rounds above.", implode(", ", $list)))));
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
    }

    if ($section == self::RACES) {
      // ------------------------------------------------------------
      // Order?
      // ------------------------------------------------------------
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
      for ($i = 0; $i < $round->getRaceOrderCount(); $i++) {
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

      $form->add($p = new XSubmitP('set-order', "Edit races"));
      $p->add(new XHiddenInput('round', $round->id));
    }

    if ($section == self::SAILS) {
      // ------------------------------------------------------------
      // Rotation
      // ------------------------------------------------------------
      $form = $this->createRotationForm($round);
      $form->add(new XSubmitP('set-rotation', "Set sails"));
      $form->add(new XHiddenInput('round', $round->id));
    }

    if ($section == self::TEAMS) {
      // ------------------------------------------------------------
      // Teams
      // ------------------------------------------------------------
      $this->PAGE->addContent($p = new XPort("Teams (seeds)"));
      $p->add($form = $this->createForm());
      $this->fillTeamsForm($form, $round);
      $form->add(new XSubmitP('set-seeds', "Set seeds"));
      $form->add(new XHiddenInput('round', $round->id));
    }
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
      for ($i = 0; $i < $round->getRaceOrderCount(); $i++) {
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
      if ($round->hasRotation()) {
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
      $this->reassignRotation($round, true);
      DB::set($round);
      Session::pa(new PA(sprintf("Updated rotation for \"%s\".", $round)));
    }

    // ------------------------------------------------------------
    // Order
    // ------------------------------------------------------------
    if (isset($args['set-order'])) {
      $other_divisions = $this->REGATTA->getDivisions();
      array_shift($other_divisions);

      $round = DB::$V->reqID($args, 'round', DB::$ROUND, "No round provided.");
      if ($round->regatta->id != $this->REGATTA->id)
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
      $next_number = array_shift($nums);

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
      $newboats = array();
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
        $neworder[] = array($pair[0], $pair[1]);
	$newboats[] = $boat;
        $next_number = array_shift($nums);
      }
      if (count($races) > 0)
        throw new SoterException("Not all races in round are accounted for.");

      $round->setRaceOrder($neworder, $newboats);
      $round->saveRaceOrder();

      foreach ($to_save as $race)
        DB::set($race, true);

      // Fix rotation, if any
      if ($redo_rotation && $round->hasRotation()) {
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
    // Settings
    // ------------------------------------------------------------
    if (isset($args['edit-round'])) {
      $round = DB::$V->reqID($args, 'round', DB::$ROUND, "Invalid round to edit.");
      $title = DB::$V->reqString($args, 'title', 1, 81, "Invalid new label for round.");

      $round->title = $title;
      DB::set($round);
      $round->saveRaceOrder();
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      Session::pa(new PA(sprintf("Edited round data for %s.", $round)));
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
    $this->redirect('races');
  }

  /**
   * Helper function to (re-)apply rotation to a round
   *
   * @param Round $round the round
   */
  private function reassignRotation(Round $round, $reset_boats = false) {
    $divisions = $this->REGATTA->getDivisions();
    $teams = array();
    for ($i = 0; $i < $round->num_teams; $i++)
      $teams[] = null;
    foreach ($round->getSeeds() as $seed)
      $teams[$seed->seed - 1] = $seed->team;

    $rotation = $this->REGATTA->getRotation();
    $rotation->initQueue();

    $sails = $round->assignSails($teams, $divisions);
    $races = $this->REGATTA->getRacesInRound($round, Division::A());
    array_shift($divisions);

    $races_to_update = array();
    foreach ($races as $i => $race) {
      $rotation->reset($race);
      $other_races = array();
      foreach ($divisions as $div) {
        $r = $this->REGATTA->getRace($div, $race->number);
        $rotation->reset($r);
        $other_races[] = $r;
      }

      $pair = $round->getRaceOrderPair($i);

      foreach (array(0, 1) as $pairIndex) {
        $team = $teams[$pair[$pairIndex] - 1];
        if ($team !== null) {
          $sail = clone($sails[$i][$pair[$pairIndex]][(string)$race->division]);
          $sail->race = $race;
          $sail->team = $team;
          $rotation->queue($sail);
          foreach ($other_races as $r) {
            $sail = clone($sails[$i][$pair[$pairIndex]][(string)$r->division]);
            $sail->race = $r;
            $sail->team = $team;
            $rotation->queue($sail);
          }
        }
      }

      // Reset boats
      if ($reset_boats !== false) {
	$boat = $round->getRaceOrderBoat($i);
	if ($race->boat != $boat) {
	  $race->boat = $boat;
	  $races_to_update[] = $race;
	  foreach ($other_races as $r) {
	    $r->boat = $boat;
	    $races_to_update[] = $r;
	  }
	}
      }
    }
    $rotation->commit();
    foreach ($races_to_update as $race)
      DB::set($race, true);
  }
}
?>