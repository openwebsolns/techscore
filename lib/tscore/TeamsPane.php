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

  public function __construct(User $user, Regatta $reg) {
    parent::__construct("Add Team", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    $teams = $this->REGATTA->getTeams();
    if (count($teams) == 0) {
      $this->fillNewRegatta($args);
      return;
    }
    $confs = Preferences::getConferences();

    // Add teams
    $this->PAGE->addContent($p = new Port("Add team from ICSA school"));
    $p->add(new Bookmark("add"));
    $p->add(new Para("Choose a school from which to add a new team. Because the regatta is under way, you may only add one team at a time."));

    $p->add($form = $this->createForm());
    $form->add(new FItem("Schools:", $f_sel = new FSelect("addschool", array(), array('size'=>20))));
    foreach ($confs as $conf) {
      // Get schools for that conference
      $schools = Preferences::getSchoolsInConference($conf);
      $schoolOptions = array();
      foreach ($schools as $school) {
	$schoolOptions[$school->id] = $school->name;
      }
      $f_sel->addOptionGroup($conf, $schoolOptions);
    }

    // What to do with rotation?
    $form->add($exp = new Para(""));
    if ($this->has_rots) {
      $exp->add(new XText("The regatta already has rotations. By adding a team, the rotations will need to be fixed. Choose from the options below."));
      $form->add($fi = new FItem("Delete rotation:",
				      new FCheckbox('del-rotation', '1',
						    array('id'=>'del-rot',
							  'checked'=>'checked'))));
      $fi->add(new Label('del-rot', "Delete current rotation without affecting finishes."));
    }

    // What to do with scores?
    if ($this->has_scores) {
      $exp->add(new XText("The regatta already has finishes entered. After adding the new teams, what should their score be?"));
      $form->add(new FItem("New score:", $f_sel = new FSelect('new-score', array())));
      $f_sel->add(new Option('DNS', "DNS", array('selected' => 'selected')));
      $f_sel->add(new Option('BYE', "BYE"));
    }
    $form->add(new FSubmit("invite", "Register team"));
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
      if (!isset($args['addschool']) ||
	  ($school = Preferences::getSchool($args['addschool'])) === null) {
	$this->announce(new Announcement("Invalid or missing school to add.", Announcement::ERROR));
	return array();
      }

      // Also validate rotation and finish option, if applicable
      if ($this->has_rots && !isset($args['del-rotation'])) {
	$this->announce(new Announcement("Please choose an action to take with new rotation.", Announcement::ERROR));
	return array();
      }
      if ($this->has_scores &&
	  (!isset($args['new-score']) || !in_array($args['new-score'], array('DNS', 'BYE')))) {
	$this->announce(new Announcement("Please choose an appropriate action to take with scores.", Announcement::ERROR));
	return array();
      }
      
      /*
       * Add a team for each school into the regatta, using the data
       * from the preferences regarding allowed team names. If the
       * list of possible names is exhausted before every team from
       * that school is assigned one, use the default team name with
       * an appended numeral (2, 3, etc...)
       *
       */
      $names  = Preferences::getTeamNames($school);
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
	$this->announce(new Announcement("Rotation has been reset.", Announcement::WARNING));
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
      $this->announce(new Announcement("Added team $team."));
    }
    return array();
  }

  // ------------------------------------------------------------
  // Create teams
  // ------------------------------------------------------------

  private function fillNewRegatta(Array $args) {
    $confs = Preferences::getConferences();
    $this->PAGE->addContent($p = new Port("Add team from ICSA school"));
    $p->add(new Para("Choose schools which are participating by indicating how many teams are invited from each school. Use your browser's search function to help you."));
    $p->add($form = $this->createForm());
    $form->add($list = new Itemize(array(), array('id'=>'teams-list')));
    
    foreach ($confs as $conf) {
      $list->addItems(new LItem($sub = new Itemize(array(new XHeading($conf)))));
      foreach ($schools = Preferences::getSchoolsInConference($conf) as $school) {
	$sub->addItems($li = new LItem());
	$li->add(new FHidden('school[]', $school->id));
	$li->add(new FText('number[]', "", array('id'=>$school->id)));
	$li->add(new Label($school->id, $school));
      }
    }
    $form->add(new FSubmit('set-teams', "Register teams"));
  }

  public function processNewRegatta(Array $args) {
    // the only thing to do: register me some teams!
    if (!isset($args['school']) || !is_array($args['school']) ||
	!isset($args['number']) || !is_array($args['number']) ||
	count($args['number']) != count($args['school'])) {
      $this->announce(new Announcement("Bad input. Please try again.", Announcement::ERROR));
      return array();
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
    // need two teams for a regatta
    if ($teams_added > 1) {
      $this->announce(new Announcement("Added $teams_added teams. You can now setup rotations, or start adding finishes."));
      $this->redirect('setup-rotations');
    }
    $this->announce(new Announcement("Please add at least two teams to proceed."));
    return array();
  }
}
?>
