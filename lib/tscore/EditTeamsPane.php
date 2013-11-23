<?php
/*
 * This file is part of TechScore
 *
 * @version 2.0
 * @package tscore
 */

require_once('tscore/AbstractPane.php');

/**
 * Edit the names of the teams based on preferences.
 *
 * @author Dayan Paez
 * @created 2013-11-22
 */
class EditTeamsPane extends AbstractPane {

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Edit team names", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("Edit team names"));
    $p->add(new XP(array(), "Although the team names are specified by the school's account holders, you may use this pane to choose a different team name from the approved list."));

    $p->add($f = $this->createForm());
    $f->add($tab = new XQuickTable(array('class'=>'full left'),
                                   array("#", "School", "Team name", "Suffix")));

    $can_choose = false;
    $options = array();
    foreach ($this->REGATTA->getTeams() as $i => $team) {
      if (!isset($options[$team->school->id])) {
        $options[$team->school->id] = array();
        $names = $team->school->getTeamNames();

        if (count($names) > 1)
          $can_choose = true;

        foreach ($names as $name)
          $options[$team->school->id][$name] = $name;
      }

      $cur = $team->name;
      $suf = "";
      foreach ($options[$team->school->id] as $name) {
        $match = array();
        if (preg_match(sprintf('/%s ([0-9])+$/', $name), $team->name, $match) > 0) {
          $cur = $name;
          $suf = $match[1];
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
        if (!isset($teams_by_school[$team->school->id])) {
          $teams_by_school[$team->school->id] = array();
          $names_by_school[$team->school->id] = $team->school->getTeamNames();
        }
        $teams_by_school[$team->school->id][] = $team;
        if (isset($new_names[$team->id])) {
          if (!in_array($new_names[$team->id], $names_by_school[$team->school->id]))
            throw new SoterException(sprintf("Invalid new name specified for %s: %s.", $team->school, $new_names[$team->id]));
          $team->name = $new_names[$team->id];
          unset($new_names[$team->id]);
        }
      }

      // Any superfluous IDs provided?
      if (count($new_names) > 0)
        throw new SoterException("Invalid (extra) teams provided for renaming.");

      foreach ($teams_by_school as $id => $teams) {
        $school = $teams[0]->school;
        // Group names by their roots
        $names = $names_by_school[$id];
        $res = array();
        foreach ($names as $name)
          $res[$name] = sprintf('/^%s( [0-9]+)?$/', $name);
        $res[$school->nick_name] = sprintf('/^%s( [0-9]+)?$/', $school->nick_name);

        $roots = array();
        foreach ($teams as $team) {
          // Find the root
          $found = false;
          foreach ($res as $root => $re) {
            if (preg_match($re, $team->name) > 0) {
              if (!isset($roots[$root]))
                $roots[$root] = array();
              $roots[$root][] = $team;
              $found = true;
              break;
            }
          }
          if (!$found) {
            $root = (count($names) == 0) ? $school->nick_name : $names[0];
            if (!isset($roots[$root]))
              $roots[$root] = array();
            $roots[$root][] = $team;
          }
        }

        // Rename, as necessary
        foreach ($roots as $root => $teams) {
          if (count($teams) == 1) {
            $teams[0]->name = $root;
            DB::set($teams[0]);
          }
          else {
            foreach ($teams as $i => $team) {
              $team->name = $root . ' ' . ($i + 1);
              DB::set($team);
            }
          }
        }
      }

      Session::pa(new PA("Renamed the teams."));
    }
  }
}
?>