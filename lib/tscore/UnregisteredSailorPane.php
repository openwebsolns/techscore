<?php
/**
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once("conf.php");

/**
 * Controls the entry of unregistered sailor information
 *
 * @author Dayan Paez
 * @created 2010-01-23
 */
class UnregisteredSailorPane extends AbstractPane {

  public function __construct(User $user, Regatta $reg) {
    parent::__construct("Unregistered sailors", $user, $reg);
    $this->title = "Unregistered";
  }

  protected function fillHTML(Array $args) {
    $this->PAGE->addHead(new GenericElement("script",
					    array(new Text()),
					    array("type"=>"text/javascript",
						  "src"=>"inc/js/rp.js")));

    $this->PAGE->addContent($p = new Port("Add sailor to temporary list"));
    $p->addChild(new Para("Choose the school from the list below and enter the 
     sailors on one line each: as first name, last name, and 
     year. Notice that only these three will be accepted.
     Follow the example below:",
			  array("style"=>"max-width: 35em;")));
    $p->addChild(new FItem("", new FTextarea("",
					     "John Doe 2011\nJane Dean 2009",
					     array("rows"=>"3",
						   "cols"=>"40",
						   "readonly"=>"readonly"))));

    $p->addChild($form = $this->createForm());

    // Create set of schools
    $schools = array();
    foreach ($this->REGATTA->getTeams() as $team)
      $schools[$team->school->id] = $team->school;
    asort($schools);

    $form->addChild(new FItem("School:",
			      $f_sel = new FSelect("school")));
    $f_sel->addOptions($schools);

    // Add student textarea
    $attrs = array("rows"=>"5",
		   "cols"=>"40",
		   "id"  =>"name-text",
		   "style"=>"max-width: 40%",
		   "onchange"=>"parseNames()");
    $form->addChild($fitem = new FItem("Students:",
				       new FTextarea("sailors", "", $attrs)));
    $fitem->addChild(new Table(array(),
			       array("id"=>"name-valid")));

    $form->addChild(new FSubmit("addtemp", "Add sailors"));
  }

  
  public function process(Array $args) {

    // ------------------------------------------------------------
    // Add temporary sailor
    // ------------------------------------------------------------
    if (isset($_POST['addtemp'])) {

      // ------------------------------------------------------------
      // - Validate school
      
      // Create set of schools
      $schools = array();
      foreach ($this->REGATTA->getTeams() as $team)
	$schools[$team->school->id] = $team->school;

      if (isset($args['school']) &&
	  in_array($args['school'], array_keys($schools)))
	$school = $schools[$args['school']];
      else {
	$mes = sprintf("Invalid or missing school (%s).", $args['school']);
	$this->announce(new Announcement($mes, Announcement::ERRR));
	return $args;
      }

      // ------------------------------------------------------------
      // - Process temporary list of sailors
      if (!isset($args['sailors']) || empty($args['sailors'])) {
	$mes = "No sailors to add.";
	$this->announce(new Announcement($mes, Announcement::WARNING));
	return $args;
      }
	
      $sailors = explode("\n",$args['sailors']);
      $sailor = new Sailor();
      $sailor->school = $school;
      $sailor->registered = false;
      foreach ($sailors as $s) {
	$s = preg_replace("/^\s+/","",$s); // remove beg spaces
	$s = preg_replace("/\s+/"," ",$s); // remove mid spaces
	$s = preg_replace("/\s+$/","",$s); // remove end spaces

	if ( !empty($s) ) {
	  // Split by spaces, get at most 3, and sanitize
	  $s = explode(" ", $s, 4);
	  $sailor->first_name = addslashes(preg_replace("/[^A-Za-z'-]/", "", $s[0]));
	  $sailor->last_name  = addslashes(preg_replace("/[^A-Za-z'-]/", "", $s[1]));
	  $sailor->year       = preg_replace("/[^0-9]/", "",      $s[2]);

	  Preferences::addTempSailor($sailor);
	}
      }
      $this->announce(new Announcement('Temporary sailor list updated.'));
    }
    return $args;
  }

  public function isActive() {
    return count($this->REGATTA->getTeams()) > 1;
  }
}
?>