<?php
/**
 * Edit pane for regatta's teams.
 *
 * @author Dayan Paez
 * @version 2009-10-04
 * @package tscore
 */

require_once('AbstractPane.php');

class TeamsPane extends AbstractPane {

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Add Team", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    $teams = $this->REGATTA->getTeams();
    if (count($teams) == 0) {
      $this->fillNewRegatta($args);
      return;
    }
    $confs = DB::getConferences();

    // Add teams
    $this->PAGE->addContent($p = new XPort("Add team from ICSA school"));
    $p->set('id', 'add');
    $p->add(new XP(array(), "Choose a school from which to add a new team. Because the regatta is under way, you may only add one team at a time."));

    $p->add($form = $this->createForm());
    $form->add(new FItem("Schools:", $f_sel = new XSelect("addschool", array('size'=>20))));
    foreach ($confs as $conf) {
      // Get schools for that conference
      $f_sel->add($f_grp = new FOptionGroup((string)$conf));
      foreach ($conf->getSchools() as $school)
	$f_grp->add(new FOption($school->id, $school->name));
    }

    // What to do with rotation?
    $form->add($exp = new XP());
    if ($this->has_rots) {
      $exp->add(new XText("The regatta already has rotations. By adding a team, the rotations will need to be fixed. Choose from the options below."));
      $form->add($fi = new FItem("Delete rotation:",
				 new XCheckboxInput('del-rotation', '1',
						    array('id'=>'del-rot',
							  'checked'=>'checked'))));
      $fi->add(new XLabel('del-rot', "Delete current rotation without affecting finishes."));
    }

    // What to do with scores?
    if ($this->has_scores) {
      $exp->add(new XText("The regatta already has finishes entered. After adding the new teams, what should their score be?"));
      $form->add(new FItem("New score:", XSelect::fromArray('new-score', array('DNS' => 'DNS', 'BYE' => 'BYE'))));
    }
    $form->add(new XSubmitInput("invite", "Register team"));
  }

  /**
   * Edit details about teams
   */
  public function process(Array $args) {
    $teams = $this->REGATTA->getTeams();
    if (count($teams) == 0)
      return $this->processNewRegatta($args);

    // ------------------------------------------------------------
    // Add team
    if (isset($args['invite'])) {
      $school = DB::$V->reqID($args, 'addschool', DB::$SCHOOL, "Invalid or missing school to add.");

      // Also validate rotation and finish option, if applicable
      if ($this->has_rots && !isset($args['del-rotation']))
	throw new SoterException("Please choose an action to take with new rotation.");
      
      if ($this->has_scores &&
	  (!isset($args['new-score']) || !in_array($args['new-score'], array('DNS', 'BYE'))))
	throw new SoterException("Please choose an appropriate action to take with scores.");
      
      /*
       * Add a team for each school into the regatta, using the data
       * from the preferences regarding allowed team names. If the
       * list of possible names is exhausted before every team from
       * that school is assigned one, use the default team name with
       * an appended numeral (2, 3, etc...)
       *
       */
      $names  = $school->getTeamNames();
      if (count($names) == 0)
	$names[] = $school->nick_name;

      $num_teams = count($this->REGATTA->getTeams($school));
      if (count($names) > $num_teams)
	$name = $names[$num_teams];
      else
	$name = sprintf("%s %d", $names[0], count($names) - $num_teams + 2);

      $team = new Team();
      $team->school = $school;
      $team->name   = $name;
      $this->REGATTA->addTeam($team);
      
      if (isset($args['del-rotation'])) {
	$rot = $this->REGATTA->getRotation();
	$rot->reset();
	Session::pa(new PA("Rotation has been reset.", PA::I));
	UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      }

      // Scores
      $finishes = array();
      foreach ($this->REGATTA->getScoredRaces() as $race) {
	$finish = $this->REGATTA->createFinish($race, $team);
	$finish->entered = new DateTime();
	$finish->penalty = ($args['new-score'] == Penalty::DNS) ?
	  new Penalty($args['new-score']) : new Penalty($args['new-score']);
	$finishes[] = $finish;
      }
      $this->REGATTA->commitFinishes($finishes);
      $this->REGATTA->doScore();
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE);

      // Messages
      Session::pa(new PA("Added team $team."));
    }
    return array();
  }

  // ------------------------------------------------------------
  // Create teams
  // ------------------------------------------------------------

  private function fillNewRegatta(Array $args) {
    $confs = DB::getConferences();
    $this->PAGE->addContent($p = new XPort("Add team from ICSA school"));
    $p->add(new XP(array(), "Choose schools which are participating by indicating how many teams are invited from each school. Use your browser's search function to help you."));
    $p->add($form = $this->createForm());
    $form->add($list = new XUl(array('id'=>'teams-list')));
    
    foreach ($confs as $conf) {
      $list->add(new XLi(array(new XHeading($conf), $sub = new XUl())));
      foreach ($conf->getSchools() as $school) {
	$sub->add(new XLi(array(new XHiddenInput('school[]', $school->id),
				new XTextInput('number[]', "", array('id'=>$school->id)),
				new XLabel($school->id, $school))));
      }
    }
    $form->add(new XSubmitInput('set-teams', "Register teams"));
  }

  public function processNewRegatta(Array $args) {
    // the only thing to do: register me some teams!
    $map = DB::$V->reqMap($args, array('school', 'number'), null, "Bad input. Please try again.");

    $teams_added = 0;
    foreach ($map['school'] as $i => $id) {
      $number = (int)$map['number'][$i];
      if ($number > 0 && ($school = DB::getSchool($id)) !== null) {
	$names = $school->getTeamNames();
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
    // need two teams for a regatta
    if ($teams_added > 1) {
      Session::pa(new PA("Added $teams_added teams. You can now setup rotations, or start adding finishes."));
      $this->redirect('setup-rotations');
    }
    throw new SoterException("Please add at least two teams to proceed.");
  }
}
?>
