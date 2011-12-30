<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2.0
 * @package regatta
 */

require_once('conf.php');
require_once('xml/XmlLibrary.php');

/**
 * This class provides static methods for dumping a regatta to file
 * and loading one back from file
 *
 * @author Dayan Paez
 * @version 2010-01-26
 */
class RegattaIO {

  /**
   * Returns the XML representation of a Regatta
   *
   * @param Regatta $reg the regatta
   * @return String text representation of regatta
   */
  public static function toXML(Regatta $reg) {

    // ------------------------------------------------------------
    // Standard sections
    // ------------------------------------------------------------
    $root = new GenericElement("Regatta");
    $root->addAttr("version", VERSION);
    $root->addAttr("xmlns", "http://techscore.mit.edu");
    $root->addAttr("tsid", $reg->id());

    $divisions = $reg->getDivisions();
    $all_races = array();
    $races = array();
    foreach ($divisions as $div) {
      $list = $reg->getRaces($div);
      $races[(string)$div] = $list;
      $all_races = array_merge($all_races, $list);
    }

    // Num of races/divisions
    $root->addAttr("races", count($all_races));
    $root->addAttr("divisions", count($divisions));

    // Details
    //   -Name
    $root->addChild($tag = new GenericElement("RegattaName"));
    $tag->addChild(new XText($reg->get(Regatta::NAME)));

    //   -Start time
    $stime = $reg->get(Regatta::START_TIME);
    $root->addChild($tag = new GenericElement("StartTime"));
    $tag->addChild(new XText($stime->format('F j, Y G:i:s A T')));

    //   -Duration
    $root->addChild($tag = new GenericElement("Duration"));
    $tag->addChild(new XText($reg->get(Regatta::DURATION)));

    //   -Regatta type
    $root->addChild($tag = new GenericElement("RegattaType"));
    $tag->addChild(new XText($reg->get(Regatta::TYPE)));
    $tag->addAttr("class", "ICSA");

    //   -Regatta scoring
    $root->addChild($tag = new GenericElement("Scoring"));
    $tag->addChild(new XText($reg->get(Regatta::SCORING)));
    $tag->addAttr("class", "ICSA");

    //   -Blurb
    $root->addChild($tag = new GenericElement("Comments"));
    for ($i = 0; $i < $reg->get(Regatta::DURATION); $i++) {
      $day = new DateTime(sprintf("%s + %d days", $stime->format('Y-m-d'), $i));
      $tag->addChild($sub = new GenericElement("Comment"));
      $sub->addAttr("day", $i);
      $sub->addChild(new XText($reg->getSummary($day)));
    }

    //   -Venue
    $cont = $reg->get(Regatta::VENUE);
    if ($cont !== null) {
      $root->addChild($tag = new GenericElement("Venue"));
      $tag->addAttr("id", $cont->id);
      $tag->addChild(new XText($cont->name));
    }

    //   -Scorers
    $root->addChild($tag = new GenericElement("Scorers"));
    foreach ($reg->getScorers() as $user) {
      $tag->addChild($sub = new GenericElement("Scorer"));
      $sub->addAttr("id", htmlspecialchars($user->id));
      $sub->addChild(new XText($user));
    }

    //   -Finalized?
    $root->addAttr("finalized", ($reg->get(Regatta::FINALIZED) != null));

    // Boats
    $root->addChild($tag = new GenericElement("Boats"));
    foreach ($divisions as $div) {
      $boat_set = array();
      $race_set = array();
      foreach ($races[(string)$div] as $race) {
	$boat_set[$race->boat->id] = $race->boat;
	if (!isset($race_set[$race->boat->id]))
	  $race_set[$race->boat->id] = array();
	$race_set[$race->boat->id][] = $race->number;
      }

      foreach ($race_set as $id => $set) {
	$boat = $boat_set[$id];
	$tag->addChild($sub = new GenericElement("Boat"));
	$sub->addAttr("id", $id);
	$sub->addAttr("division", $div);
	$sub->addAttr("races", Utilities::makeRange($set));
	$sub->addAttr("occupants", $boat->occupants);
	$sub->addChild(new XText($boat->name));
      }
    }

    // Teams
    $root->addChild($tag = new GenericElement("Teams"));
    $teams = $reg->getTeams();
    foreach ($teams as $team) {
      $tag->addChild($subtag = new GenericElement("Team"));
      $subtag->addAttr("id", $team->id);
      $subtag->addAttr("affiliate", $team->school->id);
      $subtag->addChild($sub = new GenericElement("LongName"));
      $sub->addChild(new XText($team->school->nick_name));
      $subtag->addChild($sub = new GenericElement("ShortName"));
      $sub->addChild(new XText($team->name));
    }

    // Rotations and Finishes:
    //   Step once through each combination of race and team and fetch
    //   the corresponding sail and finish. When entering finishes,
    //   also track the penalties/breakdown
    $rotation = $reg->getRotation();
    $root->addChild($tag  = new GenericElement("Rotations"));
    $root->addChild($tag2 = new GenericElement("Finishes"));
    $root->addChild($tagP = new GenericElement("Penalties"));
    $root->addChild($tagB = new GenericElement("Breakdowns"));
    foreach ($divisions as $div) {
      // For each race and team
      foreach ($races[(string)$div] as $race) {
	foreach ($teams as $team) {
	  // Sail
	  $tag->addChild($sub = new GenericElement("Sail"));
	  $sub->addAttr("team", $team->id);
	  $sub->addAttr("race", $race);
	  $sub->addAttr("sail", $rotation->getSail($race, $team));

	  // Finish
	  $finish = $reg->getFinish($race, $team);
	  if ($finish != null) {

	    $tag2->addChild($sub = new GenericElement("Finish"));
	    $sub->addAttr("team", $team->id);
	    $sub->addAttr("race", $race);
	    $sub->addChild(new XText($finish->entered->format('G:i:s O')));

	    // Penalty and breakdown
	    if ($finish->penalty instanceof Penalty) {
	      $tagP->addChild($sub = new GenericElement("Penalty"));
	      $sub->addAttr("team", $team->id);
	      $sub->addAttr("race", $race);
	      $sub->addAttr("type", $finish->penalty->type);
	      $sub->addChild(new XText($finish->penalty->comments));
	    }
	    elseif ($finish->penalty instanceof Breakdown) {
	      $tagB->addChild($sub = new GenericElement("Breakdown"));
	      $sub->addAttr("team", $team->id);
	      $sub->addAttr("race", $race);
	      $sub->addAttr("type", $finish->penalty->type);
	      $sub->addAttr("handicap", $finish->penalty->amount);
	      $sub->addChild(new XText($finish->penalty->comments));
	    }
	  }
	}
      }
    }

    // Team penalties
    $root->addChild($tag = new GenericElement("TeamPenalties"));
    foreach ($reg->getTeamPenalties() as $penalty) {
      $tag->addChild($sub = new GenericElement("TeamPenalty"));
      $sub->addAttr("team",     $penalty->team->id);
      $sub->addAttr("division", $penalty->division);
      $sub->addAttr("penalty",  $penalty->type);
      $sub->addChild(new XText($penalty->comments));
    }

    // RP information
    $rp = $reg->getRpManager();
    $root->addChild($tag = new GenericElement("RP"));
    foreach ($teams as $team) {
      foreach ($divisions as $div) {
	foreach (array(RP::SKIPPER, RP::CREW) as $role) {
	  $sailors = $rp->getRP($team, $div, $role);
	  foreach ($sailors as $cont) {
	    $tag->addChild($sub = new GenericElement("Sailor"));
	    $sub->addAttr("id",   $cont->sailor->id);
	    $sub->addAttr("team", $team->id);
	    $sub->addAttr("role", $role);
	    $sub->addAttr("division", $div);
	    $sub->addAttr("races", Utilities::makeRange($cont->races_nums));
	  }
	}
      }
    }

    // Notes
    /*
    $root->addChild($tag = new GenericElement("Notes"));
    foreach (getRegattaNotesAssoc() as $note) {
      $tag->addChild($sub = new GenericElement("Note"));
      $sub->addAttr("id",   $note['id']);
      $sub->addAttr("race", $note['number'] . $note['division']);
      $sub->addAttr("observer", $note['observer']);
      $sub->addChild(new XText($note['observation']));
    }
    */

    // ------------------------------------------------------------
    // Sailor database
    // ------------------------------------------------------------
    $root->addChild($tag = new GenericElement("Membership"));
    $school_set = array();
    foreach ($teams as $team)
      $school_set[$team->school->id] = $team->school;
    foreach ($school_set as $school) {
      $tag->addChild($sub = new GenericElement("Affiliate"));
      $sub->addAttr("id", $school->id);

      foreach (RpManager::getSailors($school) as $sailor) {
	$sub->addChild($ssub = new GenericElement("Member"));
	$ssub->addAttr("id", $sailor->id);
	$ssub->addAttr("data", "http://techscore.mit.edu");
	
	$ssub->addChild($sssub = new GenericElement("Name"));
	$sssub->addChild(new XText(sprintf("%s %s",
					  $sailor->first_name,
					  $sailor->last_name)));
	$ssub->addChild($sssub = new GenericElement("Year"));
	$sssub->addChild(new XText($sailor->year));
      }
      
      foreach (RpManager::getUnregisteredSailors($school) as $sailor) {
	$sub->addChild($ssub = new GenericElement("Member"));
	$ssub->addAttr("id", $sailor->id);
	$ssub->addAttr("data", "http://techscore.mit.edu");
	
	$ssub->addChild($sssub = new GenericElement("Name"));
	$sssub->addChild(new XText(sprintf("%s %s*",
					  $sailor->first_name,
					  $sailor->last_name)));

	$ssub->addChild($sssub = new GenericElement("Year"));
	$sssub->addChild(new XText($sailor->year));
      }
    }

    return sprintf("%s%s",
		   '<?xml version="1.0" encoding="utf-8"?>',
		   $root->toXML());
  }


  private $warnings;
  
  /**
   * Reads the regatta description from string and attempts to parse
   * it. Creates a new temporary regatta and assigns it to the given
   * user
   *
   * @param String $doc the XML representation of the file
   * @return TempRegatta the temporary regatta
   * @throws Exception if the string could not be parsed as XML
   */
  public function fromXML($doc) {
    $warnings = array();
    
    $root = new SimpleXmlElement((string)$doc);

    $tsid = (int)$root['tsid'];
    try {
      $other_reg = new Regatta($tsid);
    } catch (Exception $e) {
      throw new InvalidArgumentException("Unable to recognize saved ID.");
    }
    $expire  = new DateTime("now + 1 week", new DateTimeZone("America/New_York"));
    $regatta = TempRegatta::createRegatta($other_reg, $expire);
    
    // ------------------------------------------------------------
    // Edit main details
    // ------------------------------------------------------------
    $regatta->set(Regatta::NAME,     addslashes($root->RegattaName));
    $regatta->set(Regatta::SCORING,  $root->Scoring);
    // $regatta->set(Regatta::COMMENTS, addslashes($root->Blurb));
    try {
      $start = new DateTime($root->StartTime, new DateTimeZone("America/New_York"));
    } catch (Exception $e) {
      $warnings[] = sprintf("Invalid StartTime (%s), defaulting to 'now'.", $root->StartTime);
      $start = new DateTime("now", new DateTimeZone("America/New_York"));
    }
    $regatta->set(Regatta::START_TIME, $start);
    $duration = (int)$root->Duration;
    if ($duration <= 0) {
      $warnings[] = sprintf("Invalid value for duration (%s), defaulting to '1'.", $duration);
      $duration = 1;
    }
    $end = new DateTime(sprintf("%s + %d days", $start->format("Y-m-d H:i:s"), $duration),
			new DateTimeZone("America/New_York"));
    $regatta->set(Regatta::END_DATE, $end);
    $regtype = $root->RegattaType;
    if (!in_array($regtype, array_keys(Preferences::getRegattaTypeAssoc()))) {
      $warnings[] = sprintf("Invalid RegattaType (%s), default to 'personal'.", $regtype);
      $regtype = "personal";
    }
    $regatta->set(Regatta::TYPE, $regtype);


    // - finalized?
    $finalized = null;
    if (isset($root['finalized']) &&
	!empty($root['finalized'])) {
      try {
	$finalized = new DateTime($root['finalized'], new DateTimeZone("America/New_York"));
      } catch (Exception $e) {
	$warnings[] = sprintf("Ignoring invalid datetime for finalized (%s).", $root['finalized']);
      }
    }
    $regatta->set(Regatta::FINALIZED, $finalized);

    /**************************************************************
     * The properties below are of no concern to offline programs *
     * and are no longer imported or changed by uploading.        *
     *                                                            *
     **************************************************************
    // ------------------------------------------------------------
    // Venue
    // ------------------------------------------------------------
    $venue_id = (int)$root->Venue['id'];
    $venue = Preferences::getVenue($venue_id);
    if ($venue == null)
      $warnings[] = sprintf("Ignoring invalid venue ID (%s).", $venue_id);
    else
      $regatta->set(Regatta::VENUE, $venue);

    // ------------------------------------------------------------
    // Scorers
    // ------------------------------------------------------------
    // @TODO: what about principal vs. secondary scorers?
    foreach ($root->Scorers->Scorer as $scorer) {
      if (($acc = AccountManager::getAccount($scorer['id'])) != null) {
	$regatta->addScorer($acc);
      }
      print($scorer['id'] . "\n");
    }
    **************************************************************/

    // ------------------------------------------------------------
    // Races and divisions, through boats
    // ------------------------------------------------------------
    $valid_boats = Preferences::getBoats();
    $race_list   = array(); // associative array of division => race list
    foreach ($root->Boats->Boat as $boat) {
      $id    = (string)$boat['id'];
      $div   = (string)$boat['division'];
      $races = (string)$boat['races'];

      // validate boat ID and divisions
      try {
	$b = Preferences::getObjectWithProperty($valid_boats, "id", $id);
	$d = new Division($div);
	if (!isset($race_list[$div])) $race_list[$div] = array();
	foreach (Utilities::parseRange($races) as $num) {
	  $race = new Race();
	  $race->division = $d;
	  $race->boat     = $b;
	  $race->number   = $num;
	  $race_list[$div][$num - 1] = $race;
	}
      } catch (Exception $e) {
	$warnings[] = "Boat information inaccurate: $b.";
      }
    }
    // At this point, all the boats should be in the list
    foreach ($race_list as $list) {
      foreach ($list as $race)
	$regatta->setRace($race);
    }

    // ------------------------------------------------------------
    // Teams
    // ------------------------------------------------------------
    $ignored_teams = array();
    $removed_teams = array();
    $valid_teams = array();
    $teams = array();                              // alist: keep
						   // track for speed
						   // sake: old_id =>
						   // new team
    foreach ($other_reg->getTeams() as $team)
      $valid_teams[$team->id] = $team;
    foreach ($root->Teams->Team as $team) {
      $id = (string)$team['id'];
      $old_team = Preferences::getObjectWithProperty($valid_teams, "id", $id);
      if (isset($valid_teams[$id])) {
	$regatta->addTeam($valid_teams[$id]);
	$teams[$id] = $valid_teams[$id];
	unset($valid_teams[$id]);
      }
      else {
	$ignored_teams[] = sprintf("'%s %s'", $team->LongName, $team->ShortName);
      }
    }
    // remove teams still in $valid_teams
    foreach ($valid_teams as $team)
      $removed_teams[] = (string)$team;

    // messages
    if (count($removed_teams) > 0)
      $warnings[] = sprintf("Removed the following teams: %s.", implode(", ", $removed_teams));
    if (count($ignored_teams) > 0)
      $warnings[] = sprintf("The following requested teams were not added: %s", implode(", ", $ignored_teams));


    // ------------------------------------------------------------
    // Rotations
    // ------------------------------------------------------------
    $rot = $regatta->getRotation();
    $rot_errors = false;
    foreach ($root->Rotations->Sail as $sail) {
      $team_id = (string)$sail['team'];
      $num     = $sail['sail'];
      $r = null;
      try {
	$r = Race::parse((string)$sail['race']);
      } catch (Exception $e) {
	$warnings[] = sprintf("Invalid race value (%s) in rotation.", (string)$sail['race']);
      }
      if (isset($teams[$team_id]) && ($num > 0) && ($r != null)) {
	try {
	  $race = $regatta->getRace($r->division, $r->number);
	  $s = new Sail();
	  $s->race = $race;
	  $s->team = $teams[$team_id];
	  $s->sail = (int)$num;
	  $rot->setSail($s);
	}
	catch (Exception $e) {
	  $warnings[] = "Missing race $r.";
	}
      }
      else
	$rot_errors = true;
    }
    if ($rot_errors == true)
      $warnings[] = "Problems with one or more sails numbers in the rotation.";

    
    // ------------------------------------------------------------
    // Finishes
    // ------------------------------------------------------------
    $finish_errors = false;
    $finishes = array(); // alist of race_id => array(Finish)
    $races = array();    // alist of race_id => Race
    foreach ($root->Finishes->Finish as $finish) {
      $team_id = (string)$finish['team'];
      if (empty($finish))
	$finish_errors = true;
      else {
	$r = null;
	try {
	  $r = Race::parse($finish['race']);
	  $entered = new DateTime((string)$finish, new DateTimeZone("America/New_York"));
	} catch (Exception $e) {
	  $warnings[] = sprintf("Invalid race (%s) or entered timestamp (%s) in finish.", $finish['race'], $finish);
	}
	if (isset($teams[$team_id]) && ($r != null)) {
	  try {
	    $race = $regatta->getRace($r->division, $r->number);
	    $f = $regatta->createFinish($r, $teams[$team_id]);
	    $f->entered = $entered;
	    if (isset($finishes[$race->id]))
	      $finishes[$race->id] = array();
	    $finishes[$race->id][] = $f;
	    $races[$race->id] = $race;
	  }
	  catch (Exception $e) {
	    $warnings[] = "Missing race $r while importing finishes.";
	  }
	}
	else
	  $finish_errors = true;
      }
    }
    if ($finish_errors)
      $warnings[] = "Problems with one or more finishes.";

    foreach ($finishes as $rid => $list)
      $regatta->setFinishes($races[$rid]);


    // ------------------------------------------------------------
    // Penalties
    // ------------------------------------------------------------
    $penalty_types  = array_keys(Penalty::getList());
    $penalty_errors = false;
    foreach ($root->Penalties->Penalty as $elem) {
      $team_id  = (string)$elem['team'];
      $race_num = (string)$elem['race'];
      $type     = (string)$elem['type'];

      $race = null;
      try {
	$r = Race::parse($race_num);
	$race = $regatta->getRace($r->division, $r->number);
      } catch (Exception $e) {
	$warnings[] = "Unable to parse race or non-existing $race_num in penalty.";
	$penalty_errors = true;
      }

      if (isset($teams[$team_id]) &&
	  ($race != null) &&
	  in_array($type, $penalty_types) &&
	  ($finish = $regatta->getFinish($race, $teams[$team_id])) != null) {
	$finish->penalty = new Penalty($type, -1, (string)$elem);
      }
      else
	$penalty_errors = true;
    }
    if ($penalty_errors)
      $warnings[] = "Problems with one or more penalties.";

    
    // ------------------------------------------------------------
    // Breakdowns
    // ------------------------------------------------------------
    $penalty_types  = array_keys(Breakdown::getList());
    $penalty_errors = false;
    foreach ($root->Breakdowns->Breakdown as $elem) {
      $team_id  = (string)$elem['team'];
      $race_num = (string)$elem['race'];
      $type     = (string)$elem['type'];
      $amount   = (int)$elem['handicap'];

      // default amount: average
      if ($amount < -1) $amount = -1;

      $race = null;
      try {
	$r = Race::parse($race_num);
	$race = $regatta->getRace($r->division, $r->number);
      } catch (Exception $e) {
	$warnings[] = "Unable to parse race or non-existing $race_num in penalty.";
	$penalty_errors = true;
      }

      if (isset($teams[$team_id]) &&
	  ($race != null) &&
	  in_array($type, $penalty_types) &&
	  ($finish = $regatta->getFinish($race, $teams[$team_id])) != null) {
	$finish->penalty = new Breakdown($type, $amount, (string)$elem);
      }
      else
	$penalty_errors = true;
    }
    if ($penalty_errors)
      $warnings[] = "Problems with one or more breakdowns.";


    // ------------------------------------------------------------
    // Team penalties
    // ------------------------------------------------------------
    foreach ($root->TeamPenalties->TeamPenalty as $elem) {
      $team_id = (string)$elem['team'];
      $div     = (string)$elem['division'];
      $type    = (string)$elem['penalty'];

      if (isset($teams[$team_id]) &&
	  in_array($div, $divisions) &&
	  in_array($type, TeamPenalty::getList())) {
	$penalty = new TeamPenalty();
	$penalty->team = $teams[$team_id];
	$penalty->division = new Division($div);
	$penalty->type     = $type;
	$penalty->comments = (string)$elem;

	$regatta->setTeamPenalty($penalty);
      }
      else {
	$warnings[] = "Invalid team, division, or penalty type in team penalty.";
      }
    }

    // ------------------------------------------------------------
    // RP: note that representatives are not saved
    // ------------------------------------------------------------
    $rpman = $regatta->getRpManager();
    $teams_reg  = array();                      // alist of registered sailors
    $teams_ureg = array();                      // alist of unregistered sailors
    $pending_rp = array();                      // list of RP objects with new sailors,
                                                //   to be entered later, if found in
                                                //   the membership section
    $rp_errors = false;
    $divisions = $regatta->getDivisions();
    foreach ($root->RP->Sailor as $elem) {
      $id   = (string)$elem['id'];
      $team_id = (string)$elem['team'];
      $role = (string)$elem['role'];
      $div  = (string)$elem['division'];
      $nums = (string)$elem['races'];

      // validate team, role, division, race_str
      $team = (isset($teams[$team_id])) ? $teams[$team_id] : null;
      $role = (in_array($role, array(RP::SKIPPER, RP::CREW))) ? $role : null;
      $div  = (in_array($div, $divisions)) ? new Division($div) : null;
      $nums = Utilities::parseRange($nums);

      if ($team != null && $role != null && $div != null && $nums != null && !empty($id)) {
	if (!isset($teams_reg[$team->school->id])) {
	  $teams_reg[$team->school->id]  = RpManager::getSailors($team->school);
	  $teams_ureg[$team->school->id] = RpManager::getUnregisteredSailors($team->school);
	}

	// Does the sailor exist in the database? As unregistered? As new?
	$rp = new RP();
	$rp->boat_role  = $role;
	$rp->team       = $team;
	$rp->division   = $div;
	$rp->races_nums = $nums;

	$sailor = Preferences::getObjectWithProperty($teams_reg[$team->school->id], "id", $id);
	if ($sailor == null)
	  $sailor = Preferences::getObjectWithProperty($teams_ureg[$team->school->id], "id", $id);

	if ($sailor == null) {
	  $sailor = new Sailor();
	  $sailor->id = $id;
	  $rp->sailor   = $sailor;
	  $pending_rp[] = $rp;
	}
	else {
	  $rp->sailor   = $sailor;
	  $rpman->setRP($rp);
	}
      }
      else
	$rp_errors = true;
    }
    if ($rp_errors)
      $warnings[] = "Problems while entering some RP information.";


    // ------------------------------------------------------------
    // Remaining RP / new membership
    // ------------------------------------------------------------
    // For efficiency: create a set of the IDs of the pending
    // sailors. Then, step through the affiliations list once and
    // retrieve the new sailors as needed
    $new_ids = array();
    foreach ($pending_rp as $rp)
      $new_ids[$rp->sailor->id] = null;
    foreach ($root->Membership->Affiliate as $aff) {
      $school_id = addslashes((string)$aff['id']);
      $school = Preferences::getSchool($school_id);
      if ($school != null) {
	foreach ($aff->Member as $member) {
	  $id = (string)$member['id'];
	  if (in_array($id, array_keys($new_ids))) {

	    // add temporary sailor, fix the $rp
	    $sailor = new Sailor();
	    $year  = (string)$member->Year;
	    if (is_numeric($year) && $year > 1900 && $year < 2100)
	      $year = (int)$year;
	    else {
	      $year = date('Y');
	      $warnings[] = sprintf("Invalid year found for new sailor (%s), using current.", $member->Name);
	    }

	    $names = explode(" ", addslashes((string)$member->Name));

	    if (count($names) > 0) {
	      $sailor->last_name  = array_pop($names);
	      $sailor->first_name = implode(" ", $names);
	      $sailor->year       = $year;
	      $sailor->role       = "student";
	      $sailor->school     = $school;

	      $sailor = Preferences::addTempSailor($sailor);
	      $new_ids[$id] = $sailor;
	    }
	  }
	}
      }
    }
    foreach ($pending_rp as $rp) {
      if ($new_ids[$rp->sailor->id] != null) {
	$rp->sailor = $new_ids[$rp->sailor->id];
	$rpman->setRP($rp);
      }
      else
	$warnings[] = sprintf("Unable to add temporary sailor with id (%s). RP not added.", $rp->sailor->id);
    }

    $this->warnings = $warnings;
  }
  

  /**
   * Return the warnings from the last IO operation
   *
   * @return Array<String> the list of warnings
   */
  public function getWarnings() {
    return $this->warnings;
  }
}
?>