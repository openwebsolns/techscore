<?php
/**
 * Pane for substituting one school for another
 *
 * @author Dayan Paez
 * @version 2009-10-04
 * @package tscore
 */

require_once('AbstractTeamPane.php');

class ReplaceTeamPane extends AbstractTeamPane {

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Substitute team", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    $teams = $this->REGATTA->getTeams();

    $this->PAGE->addContent($p = new XPort("Replace team in entire regatta"));
    $p->add(new XP(array(), "Use this space to substitute a team from one school for one from another. The new team will inherit the rotations and place finishes of the old team. Note that the RP information for the old team will be removed!"));

    $p->add($form = $this->createForm());
    $props = array('rows'=>10, 'size'=>10);
    $form->add(new FReqItem("Replace team:", $sel1 = new XSelect('team', $props)));
    $form->add(new FReqItem("With school:",  $this->newSchoolSelect()));
    $form->add(new XSubmitP("replace", "Replace"));

    // team select
    foreach ($teams as $team)
      $sel1->add(new FOption($team->id, (string)$team));
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

      $new = new Team();
      $new->school = $school;
      $changed = $this->calculateTeamName($new);
      foreach ($changed as $other)
        DB::set($other);

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
      $this->fixTeamNames($old_school);
      return array();
    }
  }
}
?>