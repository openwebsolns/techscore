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
   * Does the given name start with root prefix, optionally followed
   * by integers?
   *
   * @return int|false which place in sequence
   */
  protected function nameHasRoot($root, $name) {
    if ($root == $name)
      return 1;

    $length = mb_strlen($root);
    if (mb_substr($name, 0, $length) == $root
        && preg_match('/^ ([0-9]+)$/', mb_substr($name, $length), $match))
      return $match[1];

    return false;
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
    $length = mb_strlen($name);

    $last_team_in_sequence = null;
    $last_num_in_sequence = 0;
    foreach ($this->REGATTA->getTeams($school) as $other) {
      $num = $this->nameHasRoot($name, $other->name);
      if ($num !== false) {
        $last_team_in_sequence = $other;
        $last_num_in_sequence = $num;
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
   * @param Array:Team optional list of teams to use
   * @return Array:Team list of teams changed
   */
  public function fixTeamNames(School $school, Array $teams = array()) {
    $changed = array();
    if (count($teams) == 0)
      $teams = $this->REGATTA->getTeams($school);

    if (count($teams) > 0) {
      // Group the team names by their roots
      $names = $school->getTeamNames();
      $names[] = $school->nick_name;

      $roots = array();
      foreach ($teams as $team) {
        // Find the root
        $found = false;
        foreach ($names as $root) {
          if ($this->nameHasRoot($root, $team->name) !== false) {
            if (!isset($roots[$root]))
              $roots[$root] = array();
            $roots[$root][] = $team;
            $found = true;
            break;
          }
        }
        // Catch-all: reset to first name
        if (!$found) {
          $root = $names[0];
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
            $changed[] = $teams[0];
          }
        }
        else {
          foreach ($teams as $i => $team) {
            $name = $root . ' ' . ($i + 1);
            if ($team->name != $name) {
              $team->name = $name;
              DB::set($team);
              $changed[] = $team;
            }
          }
        }
      }
    }
    return $changed;
  }
}
?>