<?php
/**
 * This file is part of TechScore
 *
 * @version 2.0
 * @package tscore
 */

require_once('conf.php');

/**
 * The "home" pane where the regatta's details are edited.
 *
 * 2010-02-24: Allowed scoring rules change
 *
 * @author Dayan Paez
 * @created 2009-09-27
 */
class DetailsPane extends AbstractPane {

  public function __construct(User $user, Regatta $reg) {
    parent::__construct("Settings", $user, $reg);
    $this->urls[] = "home";
    $this->urls[] = "settings";
  }

  protected function fillHTML(Array $args) {
    // Regatta details
    $this->PAGE->addContent($p = new Port('Regatta details'));
    $p->addHelp("node9.html#SECTION00521000000000000000");

    $p->addChild($reg_form = $this->createForm());
    // Name
    $value = $this->REGATTA->get(Regatta::NAME);
    $reg_form->addChild(new FItem("Name:",
				  new FText("reg_name",
					    stripslashes($value),
					    array("maxlength"=>35,
						  "size"     =>20))));

    // Date
    $start_time = $this->REGATTA->get(Regatta::START_TIME);
    $date = date_format($start_time, 'm/d/Y');
    $reg_form->addChild(new FItem("Date:", 
				  new FText("sdate",
					    $date,
					    array("maxlength"=>30,
						  "size"     =>20,
						  "id"=>"datepicker"))));
    // Duration
    $value = $this->REGATTA->get(Regatta::DURATION);
    $reg_form->addChild(new FItem("Duration (days):",
				  new FText("duration",
					    $value,
					    array("maxlength"=>2,
						  "size"     =>2))));
    // On the water
    $value = date_format($start_time, "H:i");
    $reg_form->addChild(new FItem("On the water:",
				  new FText("stime", $value,
					    array("maxlength"=>8,
						  "size"     =>8))));

    // Venue
    $value = "";
    $venue = $this->REGATTA->get(Regatta::VENUE);
    if ($venue !== null)
      $value = $venue->id;
    $reg_form->addChild(new FItem("Venue:", $r_type = new FSelect("venue", array($value))));
    $r_type->addOptions(array("" => ""));
    $venues = array();
    foreach (Preferences::getVenues() as $venue)
      $venues[$venue->id] = $venue->name;
    $r_type->addOptions($venues);

    // Regatta type
    $value = $this->REGATTA->get(Regatta::TYPE);
    $reg_form->addChild(new FItem("Type:",
				  $f_sel = new FSelect("type",
						       array($value))));
    $f_sel->addOptions(Preferences::getRegattaTypeAssoc());

    // Scoring rules
    $value = $this->REGATTA->get(Regatta::SCORING);
    $reg_form->addChild(new FItem("Scoring:",
				  $f_sel = new FSelect("scoring",
						       array($value))));
    $f_sel->addOptions(Preferences::getRegattaScoringAssoc());

    // Update button
    $reg_form->addChild($reg_sub = new FSubmit("edit_reg",
					       "Edit"));
    // If finalized, disable submit
    $finalized = $this->REGATTA->get(Regatta::FINALIZED);
    if ($finalized)
      $reg_sub->addAttr("disabled","disabled");

    // -------------------- Finalize regatta -------------------- //
    if (!$finalized) {
      $this->PAGE->addContent($p = new Port("Finalize regatta"));
      $p->addHelp("node9.html#SECTION00521100000000000000");

      $para = '
        Once <strong>finalized</strong>, all the information (including rp,
        and rotation) about unscored regattas will be removed from the
        database. No <strong>new</strong> information can be entered,
        although you may still be able to edit existing information.';
      $p->addChild(new Para($para));
  
      $p->addChild($form = new Form($this->createForm()));

      $form->addChild(new FItem(new FCheckbox("approve",
					      "on",
					      array("id"=>"approve")),
				new Label("approve",
					  "I wish to finalize this regatta.",
					  array("class"=>"strong"))));

      $form->addChild(new FSubmit("finalize",
				  "Finalize!"));
    }
    else {
      $para = sprintf("This regatta was finalized on %s.",
		      date("l, F j Y", strtotime($finalized)));
      $p->addChild(new Para($para, array("class"=>"strong")));
    }
    

    // ----------------- Winning Team ------------------//
    // TODO
    /*
    if ($finalized) {
      $this->PAGE->addContent($p = new Portlet("Winning Team"));
      $p->addChild(new Text("Not yet implemented."));
    }
    */
  }

  /**
   * Process edits to the regatta
   */
  public function process(Array $args) {

    // ------------------------------------------------------------
    // Details
    if ( isset($args['edit_reg']) ) {

      // Type
      if (isset($args['type']) &&
	  in_array($args['type'], array_keys(Preferences::getRegattaTypeAssoc()))) {
	$this->REGATTA->set(Regatta::TYPE, $args['type']);
      }

      // Name
      if (isset($args['reg_name']) && strlen(trim($args['reg_name'])) > 0 &&
	  ($args['reg_name'] != $this->REGATTA->get(Regatta::NAME))) {
	$this->REGATTA->set(Regatta::NAME, $args['reg_name']);
      }

      // Start time
      if (isset($args['sdate']) &&
	  isset($args['stime']) &&
	  $sdate = new DateTime($args['sdate'] . ' ' . $args['stime'])) {
	$this->REGATTA->set(Regatta::START_TIME, $sdate);
      }

      // Duration
      if (isset($args['duration']) &&
	  is_numeric($args['duration']) &&
	  $args['duration'] > 0) {
	$duration = (int)($args['duration']);
	$edate = new DateTime(sprintf("%s + %d days",
				      $args['sdate'],
				      $duration-1));
	$this->REGATTA->set(Regatta::END_DATE, $edate);
      }

      // Venue
      if (isset($args['venue']) && is_numeric($args['venue']) &&
	  Preferences::getVenue((int)$args['venue']))
	$this->REGATTA->set(Regatta::VENUE, (int)$args['venue']);

      // Scoring
      if (isset($args['scoring']) &&
	  in_array($args['scoring'], array_keys(Preferences::getRegattaScoringAssoc()))) {
	$this->REGATTA->set(Regatta::SCORING, $args['scoring']);
      }

      $this->announce(new Announcement("Edited regatta details."));
    }

    // ------------------------------------------------------------
    // Comments
    if (isset($args['comments'])) {
      $this->REGATTA->set(Regatta::COMMENTS, addslashes($args['comments']));
      $this->announce(new Announcement("Edited regatta comments."));
    }

    // ------------------------------------------------------------
    // Finalize
    if (isset($args['finalize'])) {
      if (isset($args['approve'])) {
	$this->REGATTA->set(Regatta::FINALIZED, date("Y-m-d H:m:i"));
	$this->announce(new Announcement("Regatta has been finalized."));
      }
      else
	$this->announce(new Announcement("Please check the box to finalize.", Announcement::ERROR));
    }
  }

  public function isActive() { return true; }
}
?>