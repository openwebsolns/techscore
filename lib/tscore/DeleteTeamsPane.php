<?php
/**
 * Remove team(s) from regatta
 *
 * @author Dayan Paez
 * @version 2009-10-04
 * @package tscore
 */

require_once('AbstractTeamPane.php');

class DeleteTeamsPane extends AbstractTeamPane {

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Remove Team", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    $teams = $this->REGATTA->getTeams();

    $this->PAGE->addContent($p = new XPort("Remove present teams"));
    if ($this->has_rots || $this->has_scores) {
      $p->add(new XP(array(), "The regatta is currently \"under way\": either the rotation has been created, or finishes have been entered. If you remove a team, you will also remove all information from the rotation and the scores for that team. This will probably result in one or more idle boats in the rotation, and will effectively change the FLEET size for scoring purposes."));
      $p->add(new XP(array(),
                     array("Please note: this process is ",
                           new XStrong("not"),
                           " undoable. Are you sure you don't wish to ",
                           new XA("substitute", "substitute a team"),
                           " instead?")));
    }
    $p->add(new XP(array(), "To remove one or more teams, check the appropriate box and hit \"Remove\"."));
    $p->add($form = $this->createForm());
    $form->add($tab = new XQuickTable(array('class'=>'full left'), array("", "#", "School", "Team name")));

    // Print a row for each team
    $row = 0;
    foreach ($teams as $aTeam) {
      $id = 't'.$aTeam->id;
      $tab->addRow(array(new XCheckboxInput('teams[]', $aTeam->id, array('id'=>$id)),
                         new XLabel($id, $row + 1),
                         new XTD(array('class'=>'left'), new XLabel($id, $aTeam->school)),
                         new XTD(array('class'=>'left'), new XLabel($id, $aTeam->getQualifiedName()))),
                   array('class'=>'row'.($row++ %2)));
    }
    $form->add(new XSubmitP("remove", "Remove"));
  }

  /**
   * Edit details about teams
   */
  public function process(Array $args) {
    // ------------------------------------------------------------
    // Delete team: this time an array of them is possible
    // Rename multiple teams from a school as needed to maintain
    // logical numbering order
    // ------------------------------------------------------------
    if (isset($args['remove'])) {
      $teams = DB::$V->reqList($args, 'teams', null, "Expected list of teams to delete. None found.");
      if (count($teams) == 0)
        throw new SoterException("There must be at least one team to remove.");

      $removed = 0;
      $affected_schools = array();
      foreach ($teams as $id) {
        $team = $this->REGATTA->getTeam($id);
        if ($team !== null) {
          $this->REGATTA->removeTeam($team);
          UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_TEAM, $team->school->id);
          $removed++;
          $affected_schools[$team->school->id] = $team->school;
        }
      }
      if (count($removed) == 0)
        throw new SoterException("No valid teams to remove provided.");
      Session::pa(new PA("Removed $removed team(s)."));
      $rpManager = $this->REGATTA->getRpManager();
      $rpManager->updateLog();

      if (count($this->REGATTA->getTeams()) == 0) {
        $this->REGATTA->setStatus();
        UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_DETAILS);
      }
      else {
        // Rename the teams from the affected school(s)
        foreach ($affected_schools as $school) {
          $this->fixTeamNames($school);
        }
      }
    }
    return array();
  }
}
?>