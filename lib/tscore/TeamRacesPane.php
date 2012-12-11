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
    parent::__construct("Edit Races", $user, $reg);
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
    // Add round
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Add new round"));
    $p->add($form = $this->createForm());
    $form->add(new XP(array(),
                   array("Choose the teams which will participate in the round to be added. ",
                         Conf::$NAME,
                         " will create the necessary races so that each team sails head-to-head against every other team (round-robin). Afterwards, you will be able to delete or create new races within the round. Make sure to add an appropriate label for the round.")));
    $form->add(new FItem("Round label:", new XTextInput('title', "Round " . (count($rounds) + 1))));
    $form->add($fi = new FItem("Choose teams:", $ul = new XUl(array('class'=>'fitem-list'))));
    $fi->add(new XSpan("(Click to select/deselect)", array('class'=>'message')));

    foreach ($this->REGATTA->getTeams() as $team) {
      $id = 'team-'.$team->id;
      $ul->add(new Xli(array(new XCheckboxInput('team[]', $team->id, array('id'=>$id)), new XLabel($id, $team))));
    }

    $boats = DB::getBoats();
    $boatOptions = array();
    foreach ($boats as $boat)
      $boatOptions[$boat->id] = $boat->name;

    $form->add(new FItem("Boat:", XSelect::fromArray('boat', $boatOptions)));
    $form->add(new XSubmitP('add-round', "Add round"));

    // ------------------------------------------------------------
    // Current rounds
    // ------------------------------------------------------------
    if (count($rounds) > 0) {
      $this->PAGE->addContent($p = new XPort("Current rounds"));
      $p->add(new XP(array(), "Click on the round below to edit the races in that round."));
      $p->add($ul = new XUl());
      foreach ($rounds as $round)
        $ul->add(new XLi(new XA(WS::link(sprintf('/score/%s/races', $this->REGATTA->id), array('r'=>$round->id)), $round)));
    }
  }

  private function fillRound($round) {
    $this->PAGE->addContent(new XP(array(), new XA(WS::link(sprintf('/score/%s/races', $this->REGATTA->id)), "← Back to races")));

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
    // Current races
    // ------------------------------------------------------------
    $has_finishes = false;
    $cur_races = $this->REGATTA->getRacesInRound($round, Division::A());
    if (count($cur_races) > 0) {
      $this->PAGE->addContent($p = new XPort("Edit races in round $round"));
      $p->add($form = $this->createForm());
      $form->add(new XP(array(), "The teams for the races can only be edited for unscored races. To edit the teams in a scored race, drop the finish and then return here to edit the race."));
      $form->add(new XP(array(), "Extra (unused) races will be removed at the end of the regatta."));
      $form->add(new XNoScript(array(new XP(array(),
                                            array(new XStrong("Important:"), " check the edit column if you wish to edit that race. The race will not be updated regardless of changes made otherwise.")))));
      $form->add($tab = new XQuickTable(array(), array("Edit?", "#", "First team", "Second team", "Boat")));
      foreach ($cur_races as $race) {
        $teams = $this->REGATTA->getRaceTeams($race);
        if (count($this->REGATTA->getFinishes($race)) > 0) {
          $has_finishes = true;
          $tab->addRow(array(new XCheckboxInput('race[]', $race->id),
                             $race->number,
                             $teams[0],
                             $teams[1],
                             XSelect::fromArray('boat['.$race->id.']', $boatOptions, $race->boat->id)));
        }
        else {
          $tab->addRow(array(new XCheckboxInput('race[]', $race->id),
                             $race->number,
                             XSelect::fromArray('team1['.$race->id.']', $teamOpts, $teams[0]->id),
                             XSelect::fromArray('team2['.$race->id.']', $teamOpts, $teams[1]->id),
                             XSelect::fromArray('boat['.$race->id.']', $boatOptions, $race->boat->id)));
        }
      }
      $form->add(new XSubmitP('edit-races', "Edit checked races"));
    }

    // ------------------------------------------------------------
    // With no scored races, offer to delete
    // ------------------------------------------------------------
    if (!$has_finishes) {
      $this->PAGE->addContent($p = new XPort("Delete round"));
      $p->add(new XP(array(), "Since there are no scored races for this round, you may delete the entire round from the regatta, but note that this is not recoverable."));
      $p->add($form = $this->createForm());
      $form->add($p = new XSubmitP('delete-round', "Delete"));
      $p->add(new XHiddenInput('round', $round->id));
    }

    // ------------------------------------------------------------
    // Add races
    // ------------------------------------------------------------
    $team_sel1 = XSelect::fromArray('team1[]', $teamFullOpts);
    $team_sel2 = XSelect::fromArray('team2[]', $teamFullOpts);
    $this->PAGE->addContent($p = new XPort("Add races"));
    $p->add($form = $this->createForm());
    $form->add(new XP(array(), "You can add up to 10 races at a time. For each race, choose the two teams which will be facing each other and the boat they will be racing in. Races with no chosen teams will not be added."));
    $form->add($tab = new XQuickTable(array(), array("#", "First Team", "First Team", "Boat")));

    $boat_sel = XSelect::fromArray('boat[]', $boatOptions);
    $cur_num = count($cur_races);
    for ($i = $cur_num + 1; $i <= $cur_num + 10; $i++) {
      $tab->addRow(array($i, $team_sel1, $team_sel2, $boat_sel));
    }
    $form->add(new XSubmitP('add-races', "Add races"));
  }

  /**
   * Processes new races and edits to existing races
   */
  public function process(Array $args) {
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
        Session::pa(new PA("Edited round data for $round."));
      }
    }

    // ------------------------------------------------------------
    // Delete round
    // ------------------------------------------------------------
    if (isset($args['delete-round'])) {
      $round = DB::$V->reqID($args, 'round', DB::$ROUND, "Invalid round to delete.");
      // Check that there are no finishes
      foreach ($this->REGATTA->getRacesInRound($round) as $race) {
        if (count($this->REGATTA->getFinishes($race)) > 0)
          throw new SoterException("Cannot remove the round because race $race has scored.");
      }
      DB::remove($round);
      Session::pa(new PA("Removed round $round."));
    }

    // ------------------------------------------------------------
    // Add round
    // ------------------------------------------------------------
    if (isset($args['add-round'])) {
      $rounds = $this->REGATTA->getRounds();

      // title
      $round = new Round();
      $round->title = DB::$V->reqString($args, 'title', 1, 81, "Invalid round label. May not exceed 80 characters.");
      foreach ($rounds as $r) {
        if ($r->title == $round->title)
          throw new SoterException("Duplicate round title provided.");
      }

      $boat = DB::$V->reqID($args, 'boat', DB::$BOAT, "Invalid boat provided.");
      $team_ids = DB::$V->reqList($args, 'team', null, "No list of teams provided. Please try again.");

      $teams = array();
      foreach ($team_ids as $id) {
        if (($team = $this->REGATTA->getTeam($id)) !== null)
          $teams[] = $team;
      }
      if (count($teams) < 1)
        throw new SoterException("Not enough teams provided: there must be at least two. Please try again.");

      // Assign next race number
      $count = count($this->REGATTA->getRaces(Division::A()));

      // Create round robin
      $num_added = 0;
      $divs = array(Division::A(), Division::B(), Division::C());
      foreach ($this->pairup($teams) as $pair) {
        $count++;
        foreach ($divs as $div) {
          $race = new Race();
          $race->division = $div;
          $race->number = $count;
          $race->boat = $boat;
          $race->round = $round;
          $this->REGATTA->setRace($race);
        }
        $this->REGATTA->setRaceTeams($race, $pair[0], $pair[1]);
        $num_added++;
      }
      Session::pa(new PA("Added $num_added new races in round $round."));
      return array();
    }

    // ------------------------------------------------------------
    // Edit races
    // ------------------------------------------------------------
    if (isset($args['edit-races'])) {
      $races = DB::$V->reqList($args, 'race', null, "No races to edit were provided.");
      $boats = DB::$V->reqList($args, 'boat', null, "No list of boats were provided.");
      $teams = DB::$V->reqMap($args, array('team1', 'team2'), null, "Invalid list of teams provided.");
      $edited = 0;
      $divs = array(Division::A(), Division::B(), Division::C());
      foreach ($races as $id) {
        $race = $this->REGATTA->getRaceById($id);
        if ($race === null)
          continue;

        // Update all divisions with the same boat!
        foreach ($divs as $div) {
          $therace = $this->REGATTA->getRace($div, $race->number);
          $therace->boat = DB::$V->reqID($boats, $race->id, DB::$BOAT, "Invalid boat provided for race " . $race->number);
          $this->REGATTA->setRace($therace);
        }
        // If unscored, also allow editing the teams
        if (count($this->REGATTA->getFinishes($race)) == 0) {
          $t1 = DB::$V->reqTeam($teams['team1'], $race->id, $this->REGATTA, "Invalid first team specified.");
          $t2 = DB::$V->reqTeam($teams['team2'], $race->id, $this->REGATTA, "Invalid second team specified.");
          $this->REGATTA->setRaceTeams($race, $t1, $t2);
        }
        $edited++;
      }
      if (count($edited) == 0)
        Session::pa(new PA("No races were updated.", PA::I));
      else
        Session::pa(new PA("Updated $edited race(s)."));
    }

    // ------------------------------------------------------------
    // Add races
    // ------------------------------------------------------------
    if (isset($args['add-races'])) {
      $cur_races = $this->REGATTA->getRaces(Division::A());
      $count = count($cur_races);
      $map = DB::$V->incMap($args, array('team1', 'team2', 'boat'), null,
                            array('team1'=>array(), 'team2'=>array(), 'boat'=>array()));

      $num_added = 0;
      $divs = array(Division::A(), Division::B(), Division::C());
      foreach ($map['team1'] as $i => $id1) {
        $t1 = $this->REGATTA->getTeam($id1);
        $t2 = $this->REGATTA->getTeam($map['team2'][$i]);
        $bt = DB::getBoat($map['boat'][$i]);
        if ($t1 === null || $t2 === null || $bt === null)
          continue;
        if ($t1 === $t2) {
          Session::pa(new PA("The same team cannot race against itself.", PA::E));
          continue;
        }

        $count++;
        foreach ($divs as $div) {
          $race = new Race();
          $race->number = $count;
          $race->division = $div;
          $race->boat = $bt;
          $this->REGATTA->setRace($race);
        }
        $this->REGATTA->setRaceTeams($race, $t1, $t2);
        $num_added++;
      }
      if ($num_added == 0)
        Session::pa(new PA("No races were added. Make sure to select both teams for each race.", PA::I));
      else
        Session::pa(new PA("Added $num_added race(s)."));
    }
    return array();
  }

  /**
   * Creates a round-robin from the given items
   *
   * @param Array $items the items to pair up in round robin
   * @return Array:Array a list of all the pairings
   */
  private function pairup($items) {
    if (count($items) < 2)
      throw new InvalidArgumentException("There must be at least two items.");
    if (count($items) == 2)
      return array($items);

    $list = array();
    $first = array_shift($items);
    foreach ($items as $other)
      $list[] = array($first, $other);
    foreach ($this->pairup($items) as $pair)
      $list[] = $pair;
    return $list;
  }
}
?>