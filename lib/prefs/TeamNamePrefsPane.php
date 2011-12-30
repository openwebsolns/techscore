<?php
/**
 * Defines one class, the editor page for school's team names.
 *
 * @package prefs
 */

require_once('users/AbstractUserPane.php');

/**
 * TeamNamePrefsPane: editing the valid school names to use
 *
 * @author Dayan Paez
 * @version 2009-10-14
 */
class TeamNamePrefsPane extends AbstractUserPane {

  /**
   * Creates a new editor for the specified school
   *
   * @param School $school the school whose logo to edit
   */
  public function __construct(User $usr, School $school) {
    parent::__construct("Team names", $usr, $school);
  }

  /**
   * Sets up the page
   *
   */
  public function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new Port("Choose teams"));
    $p->add(new Para("Edit the team names (mascot) that can be used for this school " .
			  "in the regattas. <strong>TechScore</strong> allows you to choose " .
			  "one primary name and up to four secondary names. The team names " .
			  "are chosen according to this list, with the primary name chosen " .
			  "by default for the first team from this school in the regatta."));

    $p->add(new Para("If a second team from this school is added, <strong>TechScore" .
			  "</strong> will choose the next name from the list. If it runs " .
			  "out of names, it will append a numeral suffix to the primary " .
			  "name."));
    
    $p->add(new Para("For instance, suppose there are four teams from a school that " .
			  "has only two possible team names (primary and one secondary): " .
			  "<em>Mascot<em>, and <em>Other mascot</em>. Then the teams " .
			  "will receive the following names when they are added to a regatta:"));

    $p->add($list = new Enumerate());
    $list->addItems(new LItem("<em>Mascot</em>"),
		    new LItem("<em>Other mascot</em>"),
		    new LItem("<em>Mascot 2</em>"),
		    new LItem("<em>Mascot 3</em>"));

    $p->add($form = new XForm(sprintf("/pedit/%s/team", $this->SCHOOL->id), "post"));

    // Fill form
    $form->add($tab = new Table());
    $tab->set("class", "narrow");
    $tab->addHeader(new Row(array(Cell::th(), Cell::th("Name"))));

    $names = Preferences::getTeamNames($this->SCHOOL);
    // First row
    $tab->addRow($row = new Row(array(new Cell("Primary"), $c = new Cell())));
    $c->add(new FText("name[]", array_shift($names), array("maxlength"=>20)));
    $row->set("style", "background:#EEEEEE; font-weight: bold");

    // Next four
    for ($i = 0; $i < 4; $i++) {
      $tab->addRow(new Row(array(new Cell(), $c = new Cell())));
      $c->add(new FText("name[]", array_shift($names), array("maxlength"=>20)));
    }

    // Submit
    $form->add(new FSubmit("team_names", "Enter names"));
  }

  /**
   * Process requests according to values in associative array
   *
   * @param Array $args an associative array similar to $_REQUEST
   */
  public function process(Array $args) {

    // Check $args
    if (!isset($args['name']) || !is_array($args['name']) || empty($args['name'])) {
      return;
    }

    // Validate name
    $names = array();
    // There must be a valid primary name
    $pri = trim(array_shift($args['name']));
    if (empty($pri)) {
      $mes = "Primary team name must not be empty.";
      $this->announce(new Announcement($mes, Announcement::ERROR));
      return;
    }
    $repeats = false;
    $names[$pri] = $pri;
    foreach ($args['name'] as $name) {
      $name = trim($name);
      if (!empty($name)) {
	if (isset($names[$name]))
	  $repeats = true;
	else
	$names[$name] = $name;
      }
    }

    // Update the team names
    Preferences::setTeamNames($this->SCHOOL, $names);
    $this->announce(new Announcement("Update team name preferences."));
    if ($repeats)
      $this->announce(new Announcement("Team names cannot be repeated.", Announcement::WARNING));
  }
}
?>