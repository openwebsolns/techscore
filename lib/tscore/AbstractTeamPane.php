<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once('tscore/AbstractPane.php');

/**
 * Offers some abstraction for panes that deal with team names
 *
 * @author Dayan Paez
 * @created 2013-11-24
 */
abstract class AbstractTeamPane extends AbstractPane {

  /**
   * Create dropdown of team choices, grouped by conference
   *
   * @return XSelect
   */
  protected function newSchoolSelect($name = 'school') {
    $f_sel = new XSelect($name, array('size'=>20));
    foreach (DB::getConferences() as $conf) {
      // Get schools for that conference
      $f_sel->add($f_grp = new FOptionGroup((string)$conf));
      foreach ($conf->getSchools() as $school)
        $f_grp->add(new FOption($school->id, $school->name));
    }
    return $f_sel;
  }

  /**
   * Add a new team choosing the name from the list of team name
   * preferences for its school. Returns list of affected teams.
   *
   * @param Team $team the team to add (school property must exist)
   * @return Array:Team the other changed team
   */
  protected function calculateTeamName(Team $team) {
    $school = $team->school;

    // Add a team for the school by suffixing a number to the
    // default name for the school. Track teams affected by the
    // change
    $changed = array();
    $names = $school->getTeamNames();
    if (count($names) == 0)
      $names = array($school->nick_name);
    $name = $names[0];
    $re = sprintf('/^%s( [0-9]+)?$/', $name);

    $last_team_in_sequence = null;
    $last_num_in_sequence = 0;
    foreach ($this->REGATTA->getTeams($school) as $other) {
      $match = array();
      if (preg_match($re, $other->name, $match)) {
        $last_team_in_sequence = $other;
        if (count($match) > 1)
          $last_num_in_sequence = $match[1];
        else
          $last_num_in_sequence = 1;
      }
    }

    if ($last_team_in_sequence !== null) {
      $name .= " " . ($last_num_in_sequence + 1);
      if ($last_num_in_sequence == 1) {
        $last_team_in_sequence->name = $names[0] . " " . 1;
        $changed[] = $last_team_in_sequence;
      }
    }

    $team->name = $name;
    return $changed;
  }

  /**
   * Fix names of teams from given school in the current regatta
   *
   * @param School $school the school
   */
  protected function fixTeamNames(School $school) {
    $teams = $this->REGATTA->getTeams($school);
    if (count($teams) > 0) {
      // Group the team names by their roots
      $names = $school->getTeamNames();
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
          if ($teams[0]->name != $root) {
            $teams[0]->name = $root;
            DB::set($teams[0]);
          }
        }
        else {
          foreach ($teams as $i => $team) {
            $name = $root . ' ' . ($i + 1);
            if ($team->name != $name) {
              $team->name = $name;
              DB::set($team);
            }
          }
        }
      }
    }
  }
}
?>