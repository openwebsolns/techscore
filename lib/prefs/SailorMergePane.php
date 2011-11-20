<?php
/**
 * Defines one class, the editor page for merging sailors
 *
 * @package prefs
 */

/**
 * SailorMergePane: editor pane to merge the unsorted sailors from a
 * given school with those in the actual database.
 *
 * @author Dayan Paez
 * @version 2009-10-14
 */
class SailorMergePane extends AbstractUserPane {

  /**
   * Creates a new editor for the specified school
   *
   * @param School $school the school whose logo to edit
   */
  public function __construct(User $usr, School $school) {
    parent::__construct("Sailors", $usr, $school);
  }

  /**
   * Sets up the page
   *
   */
  public function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new Port("Merge temporary sailors"));
    $p->addChild(new Para("When a sailor is not found in the database, the scorers " .
			  "can add the sailor temporarily. These temporary sailors " .
			  "appear throughout <strong>TechScore</strong> with an " .
			  "asterisk next to their name."));
    
    $p->addChild(new Para("It is the school's responsibilities to " .
			  "match the temporary sailors with the actual sailor from " .
			  "the ICSA database once the missing sailor has been approved."));

    $p->addChild(new Para("Use this form to update the database by matching the " .
			  "temporary sailor with the actual one from the ICSA database. " .
			  "If the sailor does not appear, he/she may have to be approved " .
			  "by ICSA before the changes are reflected in <strong>TechScore" .
			  "</strong>. Also, bear in mind that " .
			  "<strong>TechScore</strong>'s copy of the ICSA " .
			  "membership database might lag ICSA's copy by as much as a week."));

    // Get all the temporary sailors
    $temp = RpManager::getUnregisteredSailors($this->SCHOOL);
    if (empty($temp)) {
      $p->addChild(new Para("No temporary sailors for this school.",
			    array("class"=>array("strong","center"))));
      return;
    }

    $p->addChild($form = new Form(sprintf("/pedit/%s/sailor", $this->SCHOOL->id), "post"));
    $form->addChild($tab = new Table());
    $tab->addAttr("class", "narrow");
    $tab->addHeader(new Row(array(Cell::th("Temporary sailor"),
				  Cell::th("ICSA Match"))));

    // Create choices
    $sailors = RpManager::getSailors($this->SCHOOL);
    $choices = array("" => "");
    foreach ($sailors as $sailor)
      $choices[$sailor->id] = sprintf("%s %s", $sailor->first_name, $sailor->last_name);

    foreach ($temp as $sailor) {
      $tab->addRow(new Row(array(new Cell($sailor), $c = new Cell())));
      $c->addChild($f_sel = new FSelect($sailor->id));
      $f_sel->addOptions($choices);
    }

    // Submit
    $form->addChild(new FSubmit("match_sailors", "Update database"));
  }

  /**
   * Process requests according to values in associative array
   *
   * @param Array $args an associative array similar to $_REQUEST
   */
  public function process(Array $args) {

    // Check $args
    if (!isset($args['match_sailors'])) {
      return;
    }
    unset($args['match_sailors']);

    $reals = RpManager::getSailors($this->SCHOOL);
    $temps = RpManager::getUnregisteredSailors($this->SCHOOL);
    $replaced = 0;
    // Process each temp id
    foreach ($args as $id => $value) {

      // Check the value
      if (!empty($value)) {

	// Check that the id and value are valid
	$real = Preferences::getObjectWithProperty($reals, "id", $value);
	$temp = Preferences::getObjectWithProperty($temps, "id", $id);
	if ($real && $temp) {

	  // Replace
	  RpManager::replaceTempActual($temp, $real);
	  $replaced++;
	}
	
      }
    }
    if ($replaced > 0) {
      $this->announce(new Announcement("Updated $replaced temporary sailors."));
    }
    else {
      $this->announce(new Announcement("No sailors updated.", Announcement::WARNING));
    }
  }
}
?>