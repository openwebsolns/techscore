<?php
/**
 * Remove team(s) from regatta
 *
 * @author Dayan Paez
 * @version 2009-10-04
 * @package tscore
 */

require_once('AbstractPane.php');

class DeleteTeamsPane extends AbstractPane {

  public function __construct(User $user, Regatta $reg) {
    parent::__construct("Remove Team", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    $teams = $this->REGATTA->getTeams();
    
    $this->PAGE->addContent($p = new Port("Remove present teams"));
    if ($this->has_rots || $this->has_scores) {
      $p->addChild(new Para("The regatta is currently \"under way\": either the rotation has been created, or finishes have been entered. If you remove a team, you will also remove all information from the rotation and the scores for that team. This will probably result in one or more idle boats in the rotation, and will effectively change the FLEET size for scoring purposes."));
      $p->addChild(new Para("Please note: this process is <strong>not</strong> undoable. Are you sure you don't wish to <a href=\"substitute\">substitute a team</a> instead?"));
    }
    $p->addChild(new Para("To remove one or more teams, check the appropriate box and hit \"Remove\"."));
    $p->addChild($form = $this->createForm());
    $form->addChild($tab = new Table());
    $tab->addAttr('class', 'full');
    
    $tab->addHeader(new Row(array(Cell::th(""),
				  Cell::th(""),
				  Cell::th("School"),
				  Cell::th("Team name"))));

    // Print a row for each team
    $row = 0;
    foreach ($teams as $aTeam) {
      $id = 't'.$aTeam->id;
      $tab->addRow(new Row(array(new Cell(new FCheckbox('teams[]', $aTeam->id, array('id'=>$id))),
				 new Cell(new Label($id, $row + 1)),
				 new Cell(new Label($id, $aTeam->school), array('class'=>'left')),
				 new Cell(new Label($id, $aTeam->name), array('class'=>'left'))),
			   array("class"=>"row" . ($row++%2))));
    }
    $form->addChild(new FSubmit("remove", "Remove"));
  }

  /**
   * Edit details about teams
   */
  public function process(Array $args) {
    // ------------------------------------------------------------
    // Delete team: this time an array of them is possible
    if (isset($args['remove'])) {
      if (!isset($args['teams']) || !is_array($args['teams']) || count($args['teams']) == 0) {
	$this->announce(new Announcement("Please select one or more teams to remove.", Announcement::ERROR));
	return false;
      }
      $removed = 0;
      foreach ($args['teams'] as $id) {
	$team = $this->REGATTA->getTeam($id);
	if ($team !== null) {
	  $this->REGATTA->removeTeam($team);
	  $removed++;
	}
      }
      if (count($removed) > 0)
	$this->announce(new Announcement("Removed $removed teams."));
      else
	$this->announce(new Announcement("Please select one or more teams to remove.", Announcement::ERROR));

      if ($this->has_rots)
	UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      if ($this->has_scores)
	UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE);

    }
    return array();
  }
}
?>