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
    $confs = Preferences::getConferences();
    $teams = $this->REGATTA->getTeams();

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

    // ------------------------------------------------------------
    // Delete team
    if (isset($args['delete']) &&
	isset($args['team'])   &&
	is_numeric($args['team'])) {
      $id = (int)$args['team'];
      $team = Preferences::getObjectWithProperty($this->REGATTA->getTeams(), "id", $id);
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

  public function isActive() { return true; }
}

?>