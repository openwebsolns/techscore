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

    $this->PAGE->addContent($p = new XPort("Substitute team"));
    $p->add(new XP(array(), "Use this space to substitute a team from one school for one from another. The new team will inherit the rotations and place finishes of the old team. Note that the RP information for the old team will be removed!"));

    $p->add($form = $this->createForm());
    $props = array('rows'=>10, 'size'=>10);
    $form->add(new FItem("Replace team:", $sel1 = new XSelect('team', $props)));
    $form->add(new FItem("With school:",  $sel2 = new XSelect('school', $props)));
    $form->add(new XSubmitInput("replace", "Replace"));

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
      // require a team and a school
      if (!isset($args['team']) ||
	  ($team = $this->REGATTA->getTeam($args['team'])) === null) {
	Session::pa(new PA("Invalid or missing team to replace.", PA::E));
	return $args;
      }

      if (!isset($args['school']) ||
	  ($school = DB::getSchool($args['school'])) === null) {
	Session::pa(new PA("Invalid or missing school with which to replace $team.", PA::E));
	return $args;
      }

      // is the team to be subsituted from the chosen school?
      if ($school == $team->school) {
	Session::pa(new PA("It is useless to replace a team from the same school with itself. I'll ignore that.", PA::I));
	return $args;
      }

      // do the replacement
      $names  = Preferences::getTeamNames($school);
      if (count($names) == 0)
	$names[] = $school->nick_name;

      $num_teams = 0;
      foreach ($this->REGATTA->getTeams() as $t) {
	if ($t->school == $school)
	  $num_teams++;
      }

      // Assign team name depending
      $surplus = $num_teams - count($names);

      $new = new Team();
      $new->school = $school;
      $new->name   = ($surplus < 0) ?
	$names[$num_teams] :
	sprintf("%s %d", $names[0], $surplus + 2);

      $this->REGATTA->replaceTeam($team, $new);

      // delete RP
      $rp = $this->REGATTA->getRpManager();
      $rp->reset($team);

      // request recreation of rotation and scores, if applicable
      $rotation = $this->REGATTA->getRotation();
      if ($rotation->isAssigned())
	UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      if ($this->REGATTA->hasFinishes())
	UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE);

      Session::pa(new PA("Replaced team \"$team\" with one from \"$school.\""));
      return array();
    }
  }
}
?>