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
    // Manual update
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Manual shuffle"));
    $p->add(new XP(array(), "To manually assign a race order, click on the round from the list below."));
    $p->add($ul = new XUl());
    foreach ($rounds as $i => $round)
      $ul->add(new XLi(new XA(WS::link(sprintf('/score/%s/race-order', $this->REGATTA->id), array('r'=>$round->id)), $round)));
  }

  private function fillRound($round) {
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
    // Race ordering
    // ------------------------------------------------------------
    $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/tablesort.js'));
    $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/toggle-tablesort.js'));
    $has_finishes = false;
    $cur_races = $this->REGATTA->getRacesInRound($round, Division::A(), false);
    if (count($cur_races) > 0) {
      $this->PAGE->addContent($p = new XPort("Edit races in $round"));
      $p->add($form = $this->createForm());
      $form->set('id', 'edit-races-form');
      $form->add(new XNoScript("To reorder the races, indicate the relative desired order in the first cell."));
      $form->add(new XScript('text/javascript', null, 'var f = document.getElementById("edit-races-form"); var p = document.createElement("p"); p.appendChild(document.createTextNode("To reorder the races, move the rows below by clicking and dragging on the first cell (\"#\") of that row.")); f.appendChild(p);'));
      $form->add(new XP(array(), "You may also edit the associated boat for each race. Click the \"Edit races\" button to save changes. Extra (unused) races will be removed at the end of the regatta."));
      if (count($cur_races) > 20)
        $form->add(new XP(array('class'=>'warning'), "Hint: For large rotations, click \"Edit races\" at the bottom of page often to save your work."));
      $form->add(new XNoScript(array(new XP(array(),
                                            array(new XStrong("Important:"), " check the edit column if you wish to edit that race. The race will not be updated regardless of changes made otherwise.")))));
      $form->add($tab = new XQuickTable(array('id'=>'divtable', 'class'=>'teamtable'), array("Order", "#", "First team", "← Swap →", "Second team", "Boat")));
      foreach ($cur_races as $i => $race) {
        $teams = $this->REGATTA->getRaceTeams($race);
        if (count($this->REGATTA->getFinishes($race)) > 0)
          $has_finishes = true;
        $tab->addRow(array(new XTD(array(),
                                   array(new XTextInput('order[]', ($i + 1), array('size'=>2)),
                                         new XHiddenInput('race[]', $race->number))),
                           new XTD(array('class'=>'drag'), $race->number),
                           $teams[0],
			   new XCheckboxInput('swap[]', $race->id),
                           $teams[1],
                           XSelect::fromArray('boat[]', $boatOptions, $race->boat->id)),
                     array('class'=>'sortable'));
      }
      $form->add(new XP(array('class'=>'p-submit'),
			array(new XA($this->link('race-order'), "← Cancel"),
			      " ",
			      new XSubmitInput('edit-races', "Edit races"),
			      new XHiddenInput('round', $round->id))));
    }
  }

  /**
   * Processes new races and edits to existing races
   */
  public function process(Array $args) {
    // ------------------------------------------------------------
    // Edit races
    // ------------------------------------------------------------
    if (isset($args['edit-races'])) {
      $rounds = array();
      foreach ($this->REGATTA->getRounds() as $r)
        $rounds[$r->id] = $r;
      $round = $rounds[DB::$V->reqKey($args, 'round', $rounds, "Invalid round provided.")];

      $map = DB::$V->reqMap($args, array('race', 'boat'), null, "Invalid list of race and boats.");
      // If order list provided (and valid), then use it
      $ord = DB::$V->incList($args, 'order', count($map['race']));
      if (count($ord) > 0)
        array_multisort($ord, SORT_NUMERIC, $map['race'], $map['boat']);

      // Make sure that every race number is accounted for
      $numbers = array();
      foreach ($this->REGATTA->getRacesInRound($round) as $r) {
        if (!isset($numbers[$r->number]))
          $numbers[$r->number] = array();
        $numbers[$r->number][] = $r;
      }
      $pool = array_keys($numbers);
      sort($pool);

      $swaplist = DB::$V->incList($args, 'swap');
      $to_save = array();
      foreach ($map['race'] as $i => $number) {
        if (!isset($numbers[$number]))
          throw new SoterException("Invalid race number $number for round $round.");

        $boat = DB::getBoat($map['boat'][$i]);
        if ($boat === null)
          throw new SoterException("Invalid boat specified.");

        foreach ($numbers[$number] as $race) {
          $edited = false;
          if ($race->boat != $boat) {
            $race->boat = $boat;
            $edited = true;
          }
          if ($race->number != $pool[$i]) {
            $race->number = $pool[$i];
            $edited = true;
          }
	  if (in_array($race->id, $swaplist)) {
	    $team1 = $race->tr_team1;
	    $race->tr_team1 = $race->tr_team2;
	    $race->tr_team2 = $team1;
	    $edited = true;
	  }
          if ($edited)
            $to_save[] = $race;
        }
        unset($numbers[$number]);
      }
      if (count($numbers) > 0)
        throw new SoterException("Not all races in round are accounted for.");

      foreach ($to_save as $race)
        DB::set($race, true);
        
      if (count($to_save) == 0)
        Session::pa(new PA("No races were updated.", PA::I));
      else {
        Session::pa(new PA(sprintf("Races updated for round %s.", $round)));
        UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      }
    }
    return array();
  }
}
?>