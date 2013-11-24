<?php
/**
 * Pane for substituting one school for another
 *
 * @author Dayan Paez
 * @version 2009-10-04
 * @package tscore
 */

require_once('AbstractPane.php');

class ReplaceTeamPane extends AbstractPane {

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Substitute team", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    $confs = DB::getConferences();
    $teams = $this->REGATTA->getTeams();

    $this->PAGE->addContent($p = new XPort("Replace team in entire regatta"));
    $p->add(new XP(array(), "Use this space to substitute a team from one school for one from another. The new team will inherit the rotations and place finishes of the old team. Note that the RP information for the old team will be removed!"));

    $p->add($form = $this->createForm());
    $props = array('rows'=>10, 'size'=>10);
    $form->add(new FItem("Replace team:", $sel1 = new XSelect('team', $props)));
    $form->add(new FItem("With school:",  $sel2 = new XSelect('school', $props)));
    $form->add(new XSubmitP("replace", "Replace"));

    // team select
    foreach ($teams as $team)
      $sel1->add(new FOption($team->id, (string)$team));

    // school select
    foreach ($confs as $conf) {
      // Get schools for that conference
      $schools = $conf->getSchools();
      $sel2->add($grp = new FOptionGroup($conf));
      foreach ($schools as $school)
        $grp->add(new FOption($school->id, $school->name));
    }
  }

  /**
   * Edit details about teams
   */
  public function process(Array $args) {

    // ------------------------------------------------------------
    // replace team
    if (isset($args['replace'])) {
      $team = DB::$V->reqTeam($args, 'team', $this->REGATTA, "Invalid or missing team to replace.");
      $school = DB::$V->reqID($args, 'school', DB::$SCHOOL, "Invalid or missing school with which to replace $team.");

      // is the team to be substituted from the chosen school?
      $old_school = $team->school;
      if ($school == $old_school)
        throw new SoterException("It is useless to replace a team from the same school with itself. I'll ignore that.");

      // do the replacement, which is like adding a new team for the
      // new school
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

      $new = new Team();
      $new->name = $name;
      $new->school = $school;

      $old_name = (string)$team;
      $this->REGATTA->replaceTeam($team, $new);

      // delete RP
      $rp = $this->REGATTA->getRpManager();
      $rp->reset($team);


      // request team change
      Session::pa(new PA(sprintf("Replaced team \"%s\" with \"%s\".", $old_name, $team)));
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_TEAM, $old_school->id);
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_TEAM, $new->school->id);

      // fix old school naming, as needed
      $teams = $this->REGATTA->getTeams($old_school);
      if (count($teams) > 0) {
        $names = $old_school->getTeamNames();
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
              $changed[] = $teams[0];
            }
          }
          else {
            foreach ($teams as $i => $team) {
              $name = $root . ' ' . ($i + 1);
              if ($team->name != $name) {
                $team->name = $name;
                $changed[] = $team;
              }
            }
          }
        }
      }

      foreach ($changed as $team)
        DB::set($team);

      return array();
    }
  }
}
?>