<?php
/**
 * Defines one class, the editor page for merging sailors
 *
 * @package prefs
 */

require_once('users/AbstractUserPane.php');

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
    $p->add(new Para("When a sailor is not found in the database, the scorers " .
			  "can add the sailor temporarily. These temporary sailors " .
			  "appear throughout <strong>TechScore</strong> with an " .
			  "asterisk next to their name."));
    
    $p->add(new Para("It is the school's responsibilities to " .
			  "match the temporary sailors with the actual sailor from " .
			  "the ICSA database once the missing sailor has been approved."));

    $p->add(new Para("Use this form to update the database by matching the " .
			  "temporary sailor with the actual one from the ICSA database. " .
			  "If the sailor does not appear, he/she may have to be approved " .
			  "by ICSA before the changes are reflected in <strong>TechScore" .
			  "</strong>. Also, bear in mind that " .
			  "<strong>TechScore</strong>'s copy of the ICSA " .
			  "membership database might lag ICSA's copy by as much as a week."));

    // Get all the temporary sailors
    $temp = RpManager::getUnregisteredSailors($this->SCHOOL);
    if (empty($temp)) {
      $p->add(new Para("No temporary sailors for this school.",
			    array("class"=>array("strong","center"))));
      return;
    }

    $p->add($form = new Form(sprintf("/pedit/%s/sailor", $this->SCHOOL->id), "post"));
    $form->add($tab = new Table());
    $tab->set("class", "narrow");
    $tab->addHeader(new Row(array(Cell::th("Temporary sailor"),
				  Cell::th("ICSA Match"))));

    // Create choices
    $sailors = RpManager::getSailors($this->SCHOOL);
    $choices = array();
    $coaches = array();
    foreach ($sailors as $sailor)
      $choices[$sailor->id] = (string)$sailor;
    foreach (RpManager::getCoaches($this->SCHOOL, 'all', true) as $sailor)
      $coaches[$sailor->id] = (string)$sailor;

    foreach ($temp as $sailor) {
      $tab->addRow(new Row(array(new Cell($sailor), $c = new Cell())));
      $c->add($f_sel = new FSelect($sailor->id));
      $f_sel->addOptions(array("" => ""));
      $f_sel->addOptionGroup("Sailors", $choices);
      $f_sel->addOptionGroup("Coaches", $coaches);
    }

    // Submit
    $form->add(new FSubmit("match_sailors", "Update database"));
  }

  /**
   * Process requests according to values in associative array
   *
   * @param Array $args an associative array similar to $_REQUEST
   */
  public function process(Array $args) {
    require_once('public/UpdateManager.php');

    // Check $args
    if (!isset($args['match_sailors'])) {
      return;
    }
    unset($args['match_sailors']);

    $divs = Division::getAssoc();
    $reals = RpManager::getSailors($this->SCHOOL);
    $temps = RpManager::getUnregisteredSailors($this->SCHOOL);
    $replaced = 0;
    $affected = array();
    // Process each temp id
    foreach ($args as $id => $value) {

      // Check the value
      if (!empty($value)) {

	// Check that the id and value are valid
	$real = Preferences::getObjectWithProperty($reals, "id", $value);
	$temp = Preferences::getObjectWithProperty($temps, "id", $id);
	if ($real && $temp) {

	  // Notify the affected regattas to redo their RPs
	  foreach ($divs as $div) {
	    foreach (RpManager::getRegattas($temp, null, $div) as $reg) {
	      UpdateManager::queueRequest($reg, UpdateRequest::ACTIVITY_RP, $div);
	      $affected[$reg->id] = $reg;
	    }
	  }

	  // Replace
	  RpManager::replaceTempActual($temp, $real);
	  $replaced++;
	}
	
      }
    }
    if (count($affected) > 0) {
      $this->announce(new Announcement(sprintf("Affected %s regattas retroactively.", count($affected))));
    }
    if ($replaced > 0) {
      $this->announce(new Announcement("Updated $replaced temporary sailor(s)."));
    }
    else {
      $this->announce(new Announcement("No sailors updated.", Announcement::WARNING));
    }
  }
}
?>