<?php
/*
 * This file is part of TechScore
 *
 * @version 2.0
 * @package scripts
 */

/**
 * Pulls information from the ICSA database and updates the local
 * sailor database with the data.
 *
 * 2011-09-12: Sets active flag to true
 *
 * @author Dayan Paez
 * @version 2010-03-02
 */
class SyncDB {

  /**
   * Errors encountered
   */
  private $errors;

  /**
   * Warnings issued
   */
  private $warnings;

  private $verbose = false;

  /**
   * Creates a new SyncDB object
   *
   */
  public function __construct($verbose = false) {
    $this->errors = array();
    $this->warnings = array();
    $this->verbose = $verbose;
  }

  /**
   * Fetch the errors
   *
   * @return Array<String> error messages
   */
  public function errors() { return $this->errors; }

  /**
   * Fetch the warnings
   *
   * @return Array<String> warning messages
   */
  public function warnings() { return $this->warnings; }

  /**
   * Updates the information in the database about the given sailor
   *
   * @param Sailor $sailor the sailor
   */
  private function updateSailor(Member $sailor) {
    $sailor->active = 1;
    $cur = DB::getICSASailor($sailor->icsa_id);

    $update = false;
    if ($cur !== null) {
      $sailor->id = $cur->id;
      $update = true;
    }
    DB::set($sailor, $update);
  }

  /**
   * If verbose output enabled, prints the given message
   *
   * @param String $mes the message to output, appending a new line,
   * prepending a timestamp
   */
  private function log($mes) {
    if ($this->verbose !== false)
      printf("%s\t%s\n", date('Y-m-d H:i:s'), $mes);
  }

  /**
   * Runs the update
   *
   */
  public function update() {
    // ------------------------------------------------------------
    // Schools
    // ------------------------------------------------------------
    $this->log("Starting: fetching and parsing schools " . Conf::$SCHOOL_API_URL);
    
    if (($xml = @simplexml_load_file(Conf::$SCHOOL_API_URL)) === false) {
      $this->errors[] = "Unable to load XML from " . Conf::$SCHOOL_API_URL;
      return;
    }

    $this->log("Inactivating schools");
    DB::inactivateSchools();
    $this->log("Schools deactivated");

    // parse data
    foreach ($xml->school as $school) {
      try {
	$id = (string)$school->school_code;
	$sch = DB::getSchool($id);
	$upd = true;
	if ($sch === null) {
	  $this->warnings[] = sprintf("New school: %s", $school->school_code);
	  $sch = new School();
	  $sch->id = $id;
	  $upd = false;
	}
	$sch->conference = DB::getConference($school->district);
	if ($sch->conference === null)
	  throw new InvalidArgumentException("No valid conference found: " . $school->district);

	// Update fields
	$sch->name = (string)$school->school_name;
	if ($sch->nick_name === null)
	  $sch->nick_name = School::createNick($school->school_display_name);
	$sch->city = (string)$school->school_city;
	$sch->state = (string)$school->school_state;
	$sch->inactive = null;

	DB::set($sch, $upd);
	$this->log(sprintf("Activated school %10s: %s", $sch->id, $sch->name));

      } catch (Exception $e) {
	$this->errors[] = "Invalid school information: " . $e->getMessage();
      }
    }

    // ------------------------------------------------------------
    // Sailors
    // ------------------------------------------------------------
    $this->log("Starting: fetching and parsing sailors " . Conf::$SAILOR_API_URL);
    $schools = array();
    
    if (($xml = @simplexml_load_file(Conf::$SAILOR_API_URL)) === false) {
      $this->errors[] = "Unable to load XML from " . Conf::$SAILOR_API_URL;
    }
    else {
      $this->log("Inactivating sailors");
      // resets all sailors to be inactive
      RpManager::inactivateRole(Sailor::STUDENT);
      $this->log("Sailors deactivated");
      // parse data
      foreach ($xml->sailor as $sailor) {
	try {
	  $s = new Sailor();
	  $s->icsa_id = (int)$sailor->id;

	  $school_id = trim((string)$sailor->school);
	  $school = DB::getSchool($school_id);
	  if ($school !== null) {
	    $s->school = $school;
	    $s->last_name  = (string)$sailor->last_name;
	    $s->first_name = (string)$sailor->first_name;
	    $s->year = (int)$sailor->year;
	    $s->gender = $sailor->gender;

	    $this->updateSailor($s);
	    $this->log("Activated sailor $s");
	  }
	  else
	    $this->warnings[$school_id] = "Missing school " . $school_id;
	} catch (Exception $e) {
	  $this->warnings[] = "Invalid sailor information: " . print_r($sailor, true);
	}
      }
    }

    // ------------------------------------------------------------
    // Coaches
    // ------------------------------------------------------------
    $this->log("Starting: fetching and parsing coaches " . Conf::$COACH_API_URL);
    if (($xml = @simplexml_load_file(Conf::$COACH_API_URL)) === false) {
      $this->errors[] = "Unable to load XML from " . Conf::$COACH_API_URL;
    }
    else {
      $this->log("Inactivating coaches");
      RpManager::inactivateRole(Sailor::COACH);
      $this->log("Coaches inactivated");
      // parse data
      foreach ($xml->coach as $sailor) {
	try {
	  $s = new Coach();
	  $s->icsa_id = (int)$sailor->id;

	  $school_id = trim((string)$sailor->school);
	  $school = DB::getSchool($school_id);
	  if ($school !== null) {
	    $s->school = $school;
	    $s->last_name  = (string)$sailor->last_name;
	    $s->first_name = (string)$sailor->first_name;
	    $s->year = (int)$sailor->year;
	    $s->gender = $sailor->gender;

	    $this->updateSailor($s);
	    $this->log("Activated coach $s");
	  }
	  else
	    $this->warnings[$school_id] = "Missing school " . $school_id;
	} catch (Exception $e) {
	  $warnings[] = "Invalid coach information: " . print_r($sailor, true);
	}
      }
    }
  }
}

if (isset($argv) && basename(__FILE__) == basename($argv[0])) {
  ini_set('include_path', ".:".realpath(dirname(__FILE__).'/../'));
  require_once('conf.php');

  $opt = getopt('v');
  $db = new SyncDB(isset($opt['v']));
  $db->update();
  $err = $db->errors();
  if (count($err) > 0) {
    echo "----------Error(s)\n";
    foreach ($err as $mes)
      printf("  %s\n", $mes);
  }
  $err = $db->warnings();
  if (count($err) > 0) {
    echo "----------Warning(s)\n";
    foreach ($err as $mes)
      printf("  %s\n", $mes);
  }
}
?>
