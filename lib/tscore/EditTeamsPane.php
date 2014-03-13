<?php
/*
 * This file is part of TechScore
 *
 * @version 2.0
 * @package tscore
 */

require_once('tscore/AbstractTeamPane.php');

/**
 * Edit the names of the teams based on preferences.
 *
 * @author Dayan Paez
 * @created 2013-11-22
 */
class EditTeamsPane extends AbstractTeamPane {

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Edit team names", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("Edit team names"));

    $p->add($f = $this->createForm());
    $teams = array();
    if ($this->participant_mode) {
      foreach ($this->REGATTA->getTeams() as $team) {
        if ($this->USER->hasSchool($team->school))
          $teams[] = $team;
      }
      $f->add(new XP(array(),
                     array("Use this pane to set the squad name to use for your teams in your jurisdiction. To edit the list of squad names, visit the ",
                           new XA(WS::link('/prefs/' . $this->USER->school->id), "preferences"),
                           " page.")));
    }
    else {
      $teams = $this->REGATTA->getTeams();
      $f->add(new XP(array(), "Although the team names are specified by the school's account holders, you may use this pane to choose a different team name from the approved list."));
    }
    $f->add($tab = new XQuickTable(array('class'=>'full left'),
                                   array("#", "School", "Team name", "Suffix")));

    $can_choose = false;
    $options = array();
    $options_plus_nickname = array();
    foreach ($teams as $i => $team) {
      if (!isset($options[$team->school->id])) {
        $options[$team->school->id] = array();
        $options_plus_nickname[$team->school->id] = array($team->school->nick_name);
        $names = $team->school->getTeamNames();

        if (count($names) > 1)
          $can_choose = true;

        foreach ($names as $name) {
          $options[$team->school->id][$name] = $name;
          $options_plus_nickname[$team->school->id][] = $name;
        }
      }

      $cur = $team->name;
      $suf = "";
      foreach ($options_plus_nickname[$team->school->id] as $name) {
        $len = mb_strlen($name);
        if ($name != $team->name && ($num = $this->nameHasRoot($name, $team->name)) !== false) {
          $cur = $name;
          $suf = $num;
          break;
        }
      }

      $elem = null;
      $cell = null;
      if (count($options[$team->school->id]) == 0)
        $elem = new XEm($team->name, array('title'=>"No team names specified by school."));
      elseif (count($options[$team->school->id]) == 1)
        $elem = new XEm($team->name, array('title'=>"Only one team name specified by school."));
      else {
        $elem = XSelect::fromArray('name[]', $options[$team->school->id], $cur);
        $cell = new XHiddenInput('team[]', $team->id);
      }
      $tab->addRow(array(($i + 1),
                         $team->school,
                         new XTD(array(), array($elem, $cell)),
                         $suf),
                   array('class'=>'row' . ($i % 2)));
    }
    if ($can_choose)
      $f->add(new XSubmitP('set-names', "Set names"));
    else {
      $f->add(new XP(array('class'=>'warning'), "None of the teams in this regattas has more than one registered team name."));
    }
  }

  public function process(Array $args) {
    if (isset($args['set-names'])) {
      // Expect a list of team IDs and new team names. This list
      // need not include every team in the regatta, but they will all
      // be considered because of the auto-numbering scheme.
      $map = DB::$V->reqMap($args, array('team', 'name'), null, "No team names provided.");
      if (count($map['team']) == 0)
        throw new SoterException("No new names specified.");

      $new_names = array();
      foreach ($map['team'] as $i => $id)
        $new_names[$id] = $map['name'][$i];

      // Validate all the team names and separate them by school
      $teams_by_school = array();
      $names_by_school = array();
      foreach ($this->REGATTA->getTeams() as $team) {
        if ($this->participant_mode && !$this->USER->hasSchool($team->school))
          continue;

        if (!isset($teams_by_school[$team->school->id])) {
          $teams_by_school[$team->school->id] = array();
          $names_by_school[$team->school->id] = $team->school->getTeamNames();
        }
        $teams_by_school[$team->school->id][] = $team;
        if (isset($new_names[$team->id])) {
          if (!in_array($new_names[$team->id], $names_by_school[$team->school->id]))
            throw new SoterException(sprintf("Invalid new name specified for %s: %s.", $team->school, $new_names[$team->id]));
          if ($team->name != $new_names[$team->id]) {
            $team->name = $new_names[$team->id];
            DB::set($team);
          }
          unset($new_names[$team->id]);
        }
      }

      // Any superfluous IDs provided?
      if (count($new_names) > 0)
        throw new SoterException("Invalid (extra) teams provided for renaming.");

      foreach ($teams_by_school as $id => $teams) {
        $school = $teams[0]->school;
        $this->fixTeamNames($school, $teams);
      }

      $rpManager = $this->REGATTA->getRpManager();
      $rpManager->updateLog();
      Session::pa(new PA("Renamed the teams."));
    }
  }
}
?>