<?php
/**
 * Pane for creating teams for a brand new regatta
 *
 * @author Dayan Paez
 * @created 2011-04-12
 * @package tscore
 */

require_once('AbstractPane.php');

class CreateTeamsPane extends AbstractPane {

  /**
   * @throws InvalidArgumentException if the regatta already has teams
   *
   */
  public function __construct(User $user, Regatta $reg) {
    parent::__construct("Add teams", $user, $reg);
    $this->title = "New regatta teams";
    $this->urls[] = 'new-teams';
  }

  protected function fillHTML(Array $args) {
    $confs = Preferences::getConferences();
    $this->PAGE->addContent($p = new Port("Add team from ICSA school"));
    $p->addChild(new Para("Choose schools which are participating by indicating how many teams are invited from each school. Use your browser's search function to help you."));
    $p->addChild($form = $this->createForm());
    $form->addChild($list = new Itemize(array(), array('id'=>'teams-list')));
    
    foreach ($confs as $conf) {
      $list->addItems(new LItem($sub = new Itemize(array(new Heading($conf)))));
      foreach ($schools = Preferences::getSchoolsInConference($conf) as $school) {
	$sub->addItems($li = new LItem());
	$li->addChild(new FHidden('school[]', $school->id));
	$li->addChild(new FText('number[]', "", array('id'=>$school->id)));
	$li->addChild(new Label($school->id, $school));
      }
    }
    $form->addChild(new FSubmit('set-teams', "Register teams"));
  }

  public function process(Array $args) {
    // the only thing to do: register me some teams!
    if (!isset($args['school']) || !is_array($args['school']) ||
	!isset($args['number']) || !is_array($args['number']) ||
	count($args['number']) != count($args['school'])) {
      $this->announce(new Announcement("Bad input. Please try again.", Announcement::ERROR));
      return false;
    }
    $teams_added = 0;
    foreach ($args['school'] as $i => $id) {
      $number = (int)$args['number'][$i];
      if ($number > 0 && ($school = Preferences::getSchool($id)) !== null) {
	$names = Preferences::getTeamNames($school);
	if (count($names) == 0)
	  $names[] = $school->nick_name;
	
	for ($num = 0; $num < count($names) && $num < $number; $num++) {
	  $team = new Team();
	  $team->school = $school;
	  $team->name = $names[$num];
	  $this->REGATTA->addTeam($team);
	  $teams_added++;
	}
	// add rest, by appending index to first name
	$name_index = 2;
	for (; $num < count($number); $num++) {
	  $team = new Team();
	  $team->school = $school;
	  $team->name = sprintf("%s %d", $names[0], $name_index++);
	  $this->REGATTA->addTeam($team);
	  $teams_added++;
	}
      }
    }
    // what if they only add one team? Would they do that?
    if ($teams_added > 0) {
      $this->announce(new Announcement("Added $teams_added teams. Setup rotations, or start adding finishes."));
      $this->redirect('setup-rotations');
    }
  }
  public function isActive($posting = false) {
    return (count($this->REGATTA->getTeams()) == 0);
  }
}

?>