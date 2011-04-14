<?php
/**
 * Edit pane for regatta's teams.
 *
 * @author Dayan Paez
 * @created 2009-10-04
 * @package tscore
 */

require_once('AbstractPane.php');

class TeamsPane extends AbstractPane {

  public function __construct(User $user, Regatta $reg) {
    parent::__construct("Teams", $user, $reg);
    $this->title = "Add/delete";
    $this->urls[] = "team";
  }

  protected function fillHTML(Array $args) {
    $teams = $this->REGATTA->getTeams();
    if (count($teams) == 0) {
      $this->fillNewRegatta($args);
      return;
    }
    $confs = Preferences::getConferences();

    // ------------------------------------------------------------
    // Current teams
    
    // Edit team names
    if (count($teams) > 0) {
      $this->PAGE->addContent($p = new Para(""));
      $p->addChild(new Link("#add", "Add schools."));
      $this->PAGE->addContent($p = new Port("Edit present teams"));
      $p->addChild($tab = new Table(array(), array("class"=>"narrow")));
    
      $tab->addHeader(new Row(array(Cell::th(""),
				    Cell::th("School"),
				    Cell::th("Team name"),
				    Cell::th())));

      // Print a row for each team
      $row = 0;
      foreach ($teams as $aTeam) {
	$tab->addRow(new Row(array(new Cell($row + 1),
				   new Cell($aTeam->school),
				   $c_edt = new Cell($aTeam->name),
				   $c_del = new Cell()),
			     array("class"=>"row" . ($row++%2))));
	// Edit
	$c_edt->addAttr("class", "strong");
	$c_edt->addAttr("class", "left");

	// Delete
	$c_del->addChild($form = $this->createForm());
	$form->addChild(new FHidden("team", $aTeam->id));
	$form->addChild(new FSubmit("delete", "Delete", array("class"=>"thin")));
      }
    }

    // Add teams
    $this->PAGE->addContent($p = new Port("Add team from ICSA school"));
    $p->addChild(new Bookmark("add"));
    $p->addChild(new Para("Choose schools for which to add teams. " .
			  "Hold down <kbd>Ctrl</kbd> to select multiple schools.",
			  array("style"=>"max-width:35em")));

    $p->addChild($form = $this->createForm());
    $form->addChild(new FItem("Schools:",
			      $f_sel = new FSelect("addschool[]",
						   array(),
						   array("multiple"=>"multiple",
							 "size"=>"20"))));
    foreach ($confs as $conf) {
      // Get schools for that conference
      $schools = Preferences::getSchoolsInConference($conf);
      $schoolOptions = array();
      foreach ($schools as $school) {
	$schoolOptions[$school->id] = $school->name;
      }
      $f_sel->addOptionGroup($conf, $schoolOptions);
    }
    $form->addChild(new FSubmit("invite", "Register teams"));
  }

  /**
   * Edit details about teams
   */
  public function process(Array $args) {
    $teams = $this->REGATTA->getTeams();
    if (count($teams) == 0)
      return $this->processNewRegatta($args);

    // ------------------------------------------------------------
    // Delete team
    if (isset($args['delete']) &&
	isset($args['team'])   &&
	is_numeric($args['team'])) {
      $id = (int)$args['team'];
      $team = Preferences::getObjectWithProperty($teams, "id", $id);
      if ($team === null) {
	$mes = sprintf("Invalid team id (%s).", $id);
	$this->announce(new Announcement($mes, Announcement::ERROR));
      }
      else {
	$this->REGATTA->removeTeam($team);
	$this->announce(new Announcement(sprintf("Removed team %s", $team)));
      }
    }

    // ------------------------------------------------------------
    // Add team
    if (isset($args['invite'])    &&
	isset($args['addschool']) &&
	is_array($args['addschool'])) {
      $ids = $args['addschool'];

      /*
       * Add a team for each school into the regatta, using the data
       * from the preferences regarding allowed team names. If the
       * list of possible names is exhausted before every team from
       * that school is assigned one, use the default team name with
       * an appended numeral (2, 3, etc...)
       *
       */
      $errors = array();
      $valid  = array();
      foreach ($ids as $id) {
	try {
	  $school = Preferences::getSchool($id);
	  $names  = Preferences::getTeamNames($school);
	  if (count($names) == 0)
	    $names[] = $school->nick_name;

	  $num_teams = 0;
	  foreach ($this->REGATTA->getTeams() as $team) {
	    if ($team->school == $school)
	      $num_teams++;
	  }

	  // Assign team name depending
	  $surplus = $num_teams - count($names);

	  $team = new Team();
	  $team->school = $school;
	  $team->name   = ($surplus < 0) ?
	    $names[$num_teams] :
	    sprintf("%s %d", $names[0], $surplus + 2);

	  $this->REGATTA->addTeam($team);
	}
	catch (Exception $e) {
	  $errors[] = $id;
	}

      } // end foreach

      // Messages
      if (count($errors) > 0) {
	$mes = sprintf("Unable to add team for school with id %s.", implode(", ", $errors));
	$this->announce(new Announcement($mes, Announcement::WARNING));
      }
      if (count($valid) > 0) {
	$mes = sprintf("Added %d teams.", count($ids));
	$this->announce(new Announcement($mes));
      }
    }
  }

  // ------------------------------------------------------------
  // Create teams
  // ------------------------------------------------------------

  private function fillNewRegatta(Array $args) {
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

  public function processNewRegatta(Array $args) {
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

  public function isActive() { return true; }
}

?>