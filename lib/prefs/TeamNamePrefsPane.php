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
  public function __construct(Account $usr, School $school) {
    parent::__construct("Team names", $usr, $school);
  }

  /**
   * Sets up the page
   *
   */
  public function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("Choose teams"));
    $p->add(new XP(array(), "Edit the team names (mascot) that can be used for this school in the regattas. TechScore allows you to choose one primary name and up to four secondary names. The team names are chosen according to this list, with the primary name chosen by default for the first team from this school in the regatta."));

    $p->add(new XP(array(), "If a second team from this school is added, TechScore will choose the next name from the list. If it runs out of names, it will append a numeral suffix to the primary name."));
    
    $p->add(new XP(array(),
		   array("For instance, suppose there are four teams from a school that has only two possible team names (primary and one secondary): ",
			 new XEm("Mascot"), ", and ",
			 new XEm("Other mascot"), ". Then the teams will receive the following names when they are added to a regatta:")));

    $p->add(new XOl(array(),
		    array(new XLi(new XEm("Mascot")),
			  new XLi(new XEm("Other mascot")),
			  new XLi(new XEm("Mascot 2")),
			  new XLi(new XEm("Mascot 3")))));
    
    $p->add($form = new XForm(sprintf("/pedit/%s/team", $this->SCHOOL->id), XForm::POST));

    // Fill form
    $form->add($tab = new XQuickTable(array('class'=>'narrow'), array("", "Name")));
    $names = Preferences::getTeamNames($this->SCHOOL);
    // First row
    $tab->addRow(array("Primary", new XTextInput("name[]", array_shift($names), array("maxlength"=>20))),
		 array('style'=>'background:#EEEEEE;font-weight:bold'));

    // Next four
    for ($i = 0; $i < 4; $i++) {
      $tab->addRow(array("", new XTextInput("name[]", array_shift($names), array("maxlength"=>20))));
    }

    // Submit
    $form->add(new XSubmitInput("team_names", "Enter names"));
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
      Session::pa(new PA($mes, PA::E));
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
    Session::pa(new PA("Update team name preferences."));
    if ($repeats)
      Session::pa(new PA("Team names cannot be repeated.", PA::I));
  }
}
?>