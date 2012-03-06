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
    // Current races
    // ------------------------------------------------------------
    $cur_races = $this->REGATTA->getRaces(Division::A());
    if (count($cur_races) > 0) {
      $this->PAGE->addContent($p = new XPort("Edit current races"));
      $p->add($form = $this->createForm());
      $form->add(new XP(array(), "The teams for the races can only be edited for unscored races. To edit the teams in a scored race, drop the finish and then return here to edit the race."));
      $form->add(new XP(array(), "Extra (unused) races will be removed at the end of the regatta."));
      $form->add(new XNoScript(array(new XP(array(),
					    array(new XStrong("Important:"), " check the edit column if you wish to edit that race. The race will not be updated regardless of changes made otherwise.")))));
      $form->add($tab = new XQuickTable(array(), array("Edit?", "#", "First team", "Second team", "Boat")));
      foreach ($cur_races as $race) {
	$teams = $this->REGATTA->getRaceTeams($race);
	if (count($this->REGATTA->getFinishes($race)) > 0) {
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
}
?>