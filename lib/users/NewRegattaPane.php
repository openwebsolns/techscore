<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2
 * @version 2010-04-19
 * @package tscore
 */

require_once('users/AbstractUserPane.php');

/**
 * Create a new regatta
 *
 */
class NewRegattaPane extends AbstractUserPane {

  /**
   * Create a pane for creating regattas
   *
   * @param Account $user the user creating the regatta
   */
  public function __construct(Account $user) {
    parent::__construct("New regatta", $user);
  }
  
  private function defaultRegatta() {
    $day = new DateTime('next Saturday');
    return array("name"=>"", "start_date"=>$day->format('m/d/Y'), "start_time" => "10:00", "duration"=>2,
		 "venue"=>"", "scoring"=>"standard", "type"=>"conference", "participant"=>"coed",
		 "num_divisions"=>2, "num_races"=>"18");
  }

  protected function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("Create"));
    $p->add($f = new XForm("/create-edit", XForm::POST));

    $r = $this->defaultRegatta();
    // Replace with values from $args
    foreach ($r as $key => $value) {
      if (isset($args[$key]))
	$r[$key] = $args[$key];
    }

    $types = Preferences::getRegattaTypeAssoc();
    unset($types['personal']);

    $f->add(new FItem("Name:", new XTextInput("name", $r["name"], array('maxlength'=>40))));
    $f->add(new FItem("Start date:", new XTextInput("start_date", $r["start_date"])));
    $f->add(new FItem("On the water:", new XTextInput("start_time", $r["start_time"])));
    $f->add(new FItem("Duration (days):", new XTextInput("duration", $r["duration"])));
    $f->add(new FItem("Venue:",   $sel = new XSelect("venue")));
    $f->add(new FItem("Scoring:", XSelect::fromArray("scoring", Preferences::getRegattaScoringAssoc(), $r["scoring"])));
    $f->add(new FItem("Type:", XSelect::fromArray("type",
						  array("Public"=>$types, "Not-published"=>array('personal'=>"Personal")),
						  $r["type"])));
    $f->add(new FItem("Participation:", XSelect::fromArray("participant", Preferences::getRegattaParticipantAssoc(),
							   $r["participant"])));
    $f->add(new FItem("Divisions:",$div = XSelect::fromArray("num_divisions", array(1=>1, 2=>2, 3=>3, 4=>4), $r["num_divisions"])));
    $f->add(new FItem("Number of races:", new XTextInput("num_races", $r["num_races"])));
    // host: if it has more than one host, otherwise send it hidden
    $confs = array(); // array of conference choices
    $schools = $this->USER->getSchools();
    if (count($schools) == 1) {
      $school = array_shift($schools);
      $f->add(new FItem("Host:", new XSpan($school)));
      $f->add(new XHiddenInput('host[]', $school->id));
    }
    else {
      $confs = array();
      foreach ($schools as $school) {
	if (!isset($confs[$school->conference->id]))
	  $confs[$school->conference->id] = array();
	$confs[$school->conference->id][$school->id] = $school;
      }
      $f->add($fi = new FItem("Host(s):", XSelectM::fromArray('host[]', $confs)));
      $fi->add(new XMessage("There must be at least one"));
    }
    $f->add(new XSubmitInput("new-regatta", "Create"));

    // select
    $sel->add(new FOption("", "[No venue]"));
    foreach (DB::getVenues() as $venue) {
      $sel->add($opt = new FOption($venue->id, $venue));
      if ($venue->id == $r["venue"])
	$opt->set('selected', 'selected');
    }
  }

  /**
   * Creates the new regatta
   *
   */
  public function process(Array $args) {
    if (isset($args['new-regatta'])) {
      $error = false;
      // 1. Check name
      if (!isset($args['name']) || addslashes(trim($args['name'])) == "") {
	Session::pa(new PA("Invalid (empty) name.", PA::E));
	$error = true;
      }
      // 2. Check date
      if (!isset($args['start_date']) || ($sd = strtotime($args['start_date'])) === false) {
	Session::pa(new PA("Invalid date given.", PA::E));
	$error = true;
      }
      // 3. Check time
      if (!isset($args['start_time']) || ($st = strtotime($args['start_time'])) === false) {
	Session::pa(new PA("Invalid start time.", PA::E));
	$error = true;
      }
      // 4. Check duration
      if (!isset($args['duration']) || $args['duration'] < 1) {
	Session::pa(new PA("Invalid duration.", PA::E));
	$error = true;
      }
      // 5. Venue
      if (!empty($args['venue']) &&
	  DB::getVenue($args['venue']) === null) {
	Session::pa(new PA("Invalid venue.", PA::E));
	$error = true;
      }
      // 6. Scoring
      $scoring = Preferences::getRegattaScoringAssoc();
      if (!isset($args['scoring']) ||
	  !isset($scoring[$args['scoring']])) {
	Session::pa(new PA("Invalid regatta type.", PA::E));
	$error = true;
      }
      // 7. Type
      $type = Preferences::getRegattaTypeAssoc();
      if (!isset($args['type']) ||
	  !isset($type[$args['type']])) {
	Session::pa(new PA("Invalid regatta type.", PA::E));
	$error = true;
      }
      // 8. Participation
      $part = Preferences::getRegattaParticipantAssoc();
      if (!isset($args['participant']) ||
	  !isset($part[$args['participant']])) {
	Session::pa(new PA("Invalid regatta participation.", PA::E));
	$error = true;
      }
      // 9. Divisions
      if (!isset($args['num_divisions']) || $args['num_divisions'] < 1 || $args['num_divisions'] > 4) {
	Session::pa(new PA("Invalid number of divisions.", PA::E));
	$error = true;
      }
      // 10. Races
      if (!isset($args['num_races']) || $args['num_races'] < 1 || $args['num_races'] > 99) {
	Session::pa(new PA("Invalid number of races.", PA::E));
	$error = true;
      }
      // 11. Host(s)
      if (!isset($args['host']) || !is_array($args['host'])) {
	Session::pa(new PA("No hosts supplied.", PA::E));
	$error = true;
      }
      else {
	$hosts = array();
	$schools = $this->USER->getSchools();
	foreach ($args['host'] as $id) {
	  $school = DB::getSchool($id);
	  if ($school !== null && isset($schools[$school->id])) 
	    $hosts[] = $school;
	}
	if (count($hosts) == 0) {
	  Session::pa(new PA("No valid hosts supplied.", PA::E));
	  $error = true;
	}
      }

      // Submit?
      if ($error)
	return $args;

      $str = sprintf("%s %s", date('Y-m-d', $sd), date('H:i:s', $st));
      $end = date('Y-m-d', $sd + (int)$args['duration'] * 86400);
      try {
	$reg = Regatta::createRegatta($args['name'],
				      new DateTime($str),
				      new DateTime($end),
				      $args['type'],
				      $args['scoring'],
				      $args['participant']);

	$reg->setCreator($this->USER);
	$reg->addScorer($this->USER);
	foreach ($hosts as $school)
	  $reg->addHost($school);

	$divs = array_values(Division::getAssoc());
	$boat = DB::getPreferredBoat($this->USER->school);
	for ($i = 0; $i < $args['num_divisions']; $i++) {
	  $div = $divs[$i];
	  for ($j = 1; $j <= $args['num_races']; $j++) {
	    $race = new Race();
	    $race->division = $div;
	    $race->boat = $boat;
	    $race->number = $j;
	    
	    $reg->setRace($race);
	  }
	}
      } catch (InvalidArgumentException $e) {
	// This should be reached ONLY because of a nick-name mismatch
	Session::pa(new PA("It seems that there is already an active regatta with this name for the current season. This is likely the result of a previous regatta that was not deleted or demoted to \"Personal\" status. If you are a scorer for the other regatta, please delete it or de-activate it before creating this one. Otherwise, you may need to create the current only under a different name.", PA::I));
	return $args;
      }
				    
      // Move to new regatta
      Session::pa(new PA(sprintf("Created new regatta \"%s\". Please add teams now.", $reg->get(Regatta::NAME))));
      WebServer::go("score/".$reg->id()."/teams");
    }
    return array();
  }
}
?>