<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2009-10-04
 * @package tscore
 */

/**
 * Races order within a round: either manual or through automated process.
 *
 * @author Dayan Paez
 * @version 2012-03-05
 */
class TeamRaceOrderPane extends AbstractPane {

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Race Order", $user, $reg);
    if ($reg->scoring != Regatta::SCORING_TEAM)
      throw new InvalidArgumentException("TeamRaceOrderPane only available for team race regattas.");
  }

  /**
   * Fills out the pane, allowing the user to add up to 10 races at a
   * time, or edit any one of the previous races
   *
   * @param Array $args (ignored)
   */
  protected function fillHTML(Array $args) {
    // ------------------------------------------------------------
    // Specific rounds?
    // ------------------------------------------------------------
    $rounds = array();
    foreach ($this->REGATTA->getRounds() as $round)
      $rounds[$round->id] = $round;

    if (isset($args['order-rounds'])) {
      try {
	$list = DB::$V->reqList($args, 'round', null, "No rounds chosen for ordering. Please try again.");
	$rnds = array();
	foreach ($list as $id) {
	  if (!isset($rounds[$id]))
	    throw new SoterException("Invalid round ID provided: $id.");
	  $rnds[] = $rounds[$id];
	}
	$this->fillRounds($rnds);
	return;
      }
      catch (SoterException $e) {
	Session::pa(new PA($e->getMessage(), PA::I));
      }
    }

    // ------------------------------------------------------------
    // Manual update
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Manual shuffle"));

    $groups = $this->REGATTA->getRoundGroups();
    if (count($groups) > 0) {
      $p->add(new XH4("Grouped rounds"));
      $p->add(new XP(array(), "The following table summarizes the list of rounds whose races are ordered together. To edit the races within each group, click on the group name. To separate the rounds, click the \"Unlink\" button next to the group name."));
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

	$tab->addRow(array(new XA($this->link('race-order', array('order-rounds'=>"", 'round' => $my_round_ids)), implode(", ", $my_rounds)), $f));
      }
    }

    $ul = new XUl(array('class'=>'inline-list'));
    $count = 0;
    foreach ($rounds as $i => $round) {
      if ($round->round_group === null) {
	$id = 'chk-round-' . $i;
	$ul->add(new XLi(array(new XCheckboxInput('round[]', $round->id, array('id'=>$id)),
			       new XLabel($id, $round))));
	$count++;
      }
    }
    if ($count > 0) {
      $p->add(new XH4("Individual rounds"));
      $p->add(new XP(array(), "To manually assign a race order, check the round or rounds from the list below. To schedule two rounds simultaneously, for example, check both rounds, and click \"Order races\"."));
      $p->add(new XP(array('class'=>'warning'), array(new XStrong("Hint:"), " When combining multiple rounds, first order each round individually before combining their races.")));
      $p->add($f = $this->createForm(XForm::GET));
      $f->add(new FItem("Rounds:", $ul));
      $f->add(new XSubmitP('order-rounds', "Order races"));
    }
  }

  private function fillRounds(Array $rounds) {
    $this->PAGE->addContent(new XP(array(), new XA($this->link('race-order'), "← Back to round list")));

    // ------------------------------------------------------------
    // Race order templates to choose from?
    // ------------------------------------------------------------
    if (count($rounds) == 1) {
      $races = $this->REGATTA->getRacesInRound($rounds[0], Division::A());
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
        $p->add(new XP(array(), "You may choose to order the races automatically by using one of the templates below, applicable to this round. Pay close attention to the number of boats per flight. If none of the templates below apply to you, then use the manual ordering scheme below."));
        $p->add(new XP(array(), "Choose the seeding order for the round by placing incrementing numbers next to the team names."));
        $p->add($form = $this->createForm());
        $form->add($tab = new XQuickTable(array(), array("", "Name", "Number of boats", "Description")));
        foreach ($templates as $template) {
          $id = 'inp-' . $template->id;
          $tab->addRow(array($ri = new XRadioInput('template', $template->id, array('id'=>$id)),
                             new XLabel($id, $template->name),
                             new XLabel($id, $template->num_boats),
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
                          array(new XSubmitInput('use-template', "Use template"),
                                new XHiddenInput('round', $rounds[0]->id))));
      }
    }

    $boats = DB::getBoats();
    $boatOptions = array();
    foreach ($boats as $boat)
      $boatOptions[$boat->id] = $boat->name;

    // ------------------------------------------------------------
    // Race ordering
    // ------------------------------------------------------------
    $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/tablesort.js'));
    $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/toggle-tablesort.js'));
    $this->PAGE->addContent($p = new XPort("Edit race orders in " . implode(", ", $rounds)));
    $p->add($form = $this->createForm());
    $form->set('id', 'edit-races-form');
    $form->add(new XNoScript("To reorder the races, indicate the relative desired order in the first cell."));
    $form->add(new XScript('text/javascript', null, 'var f = document.getElementById("edit-races-form"); var p = document.createElement("p"); p.appendChild(document.createTextNode("To reorder the races, move the rows below by clicking and dragging on the first cell (\"#\") of that row.")); f.appendChild(p);'));
    $form->add(new XP(array(), "You may also edit the associated boat for each race. Click the \"Edit races\" button to save changes. Extra (unused) races will be removed at the end of the regatta."));
    $form->add(new XP(array('class'=>'warning'), "Hint: For large rotations, click \"Edit races\" at the bottom of page often to save your work."));
    $form->add(new XNoScript(array(new XP(array(),
					  array(new XStrong("Important:"), " check the edit column if you wish to edit that race. The race will not be updated regardless of changes made otherwise.")))));
    $header = array("Order", "#");
    if (count($rounds) > 1)
      $header[] = "Round";
    $header[] = "First team";
    $header[] = "← Swap →";
    $header[] = "Second team";
    $header[] = "Boat";
    $form->add($tab = new XQuickTable(array('id'=>'divtable', 'class'=>'teamtable'), $header));

    // order races by number
    $races = array();
    foreach ($rounds as $r => $round) {
      foreach ($this->REGATTA->getRacesInRound($round, Division::A(), false) as $i => $race) {
	$races[] = $race;
      }
    }
    usort($races, 'Race::compareNumber');

    foreach ($races as $i => $race) {
      $bgcolor = '';
      if (count($rounds) > 1)
	$bgcolor = ' bgcolor' . ($race->round->relative_order % 7);
      $teams = $this->REGATTA->getRaceTeams($race);
      $row = array(new XTD(array(),
                           array(new XTextInput('order[]', ($i + 1), array('size'=>2)),
                                 new XHiddenInput('race[]', $race->id))),
                   new XTD(array('class'=>'drag'), $race->number));
      if (count($rounds) > 1)
        $row[] = $race->round;
      $row[] = $teams[0];
      $row[] = new XCheckboxInput('swap[]', $race->id);
      $row[] = $teams[1];
      $row[] = XSelect::fromArray('boat[]', $boatOptions, $race->boat->id);
      $tab->addRow($row, array('class'=>'sortable' . $bgcolor));
    }
    $form->add($p = new XSubmitP('edit-races', "Edit races"));
    foreach ($rounds as $round)
      $p->add(new XHiddenInput('round[]', $round->id));
  }

  /**
   * Processes new races and edits to existing races
   */
  public function process(Array $args) {
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
      $this->redirect('race-order');
    }

    // ------------------------------------------------------------
    // Edit races
    // ------------------------------------------------------------
    if (isset($args['edit-races'])) {
      $other_divisions = $this->REGATTA->getDivisions();
      array_shift($other_divisions);

      $group = false; // the group to which these rounds will belong
      $rounds = array();
      $races = array(); // map of race in A division's ID to races
      // the smallest race number among the chosen rounds stays the
      // same. All others may change. Including those of rounds that
      // come after this one. We track the race numbers so we can sort
      // the list and determine the smallest one.
      $nums = array();
      foreach (DB::$V->reqList($args, 'round', null, "No rounds provided.") as $rid) {
	if (($round = DB::get(DB::$ROUND, $rid)) === null || $round->regatta != $this->REGATTA)
	  throw new SoterException("Invalid round provided: $rid.");

	if ($group !== false && $group != $round->round_group)
	  throw new SoterException("All rounds must belong to the same group.");
	$group = $round->round_group;

	foreach ($this->REGATTA->getRacesInRound($round, Division::A(), false) as $race) {
	  $races[$race->id] = array($race);
	  foreach ($other_divisions as $div)
	    $races[$race->id][] = $this->REGATTA->getRace($div, $race->number);
	  $nums[] = $race->number;
	}
	$rounds[$round->id] = $round;
      }
      if (count($rounds) == 0)
	throw new SoterException("Empty round list provided.");

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
	if (count($rounds) == 1)
	  Session::pa(new PA(sprintf("Races updated for round %s.", implode(", ", $rounds))));
	else {
	  // create group if necessary
	  if ($group === null) {
	    $group = new Round_Group();
	    foreach ($rounds as $round) {
	      $round->round_group = $group;
	      DB::set($round);
	    }
	  }

	  // affect all race numbers as necessary
	  $these_rounds = $rounds;
	  $other_rounds = array();
	  $do_add = false;
	  foreach ($this->REGATTA->getRounds() as $round) {
	    if (!isset($these_rounds[$round->id])) {
	      if ($do_add)
		$other_rounds[] = $round;
	    }
	    else {
	      $do_add = true;
	      unset($these_rounds[$round->id]);
	    }
	    if (count($these_rounds) == 0)
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
	  Session::pa(new PA(sprintf("Races updated for rounds %s.", implode(", ", $rounds))));
	  if (count($others_changed) > 0)
	    Session::pa(new PA(sprintf("Also re-numbered races for round(s) %s.", implode(", ", $other_changed)), PA::I));
	}
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
    return array();
  }
}
?>