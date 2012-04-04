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
   * @var Array Errors encountered
   */
  private $errors;

  /**
   * @var Array Warnings issued
   */
  private $warnings;

  /**
   * @var boolean true to report progress
   */
  private $verbose = false;

  const SCHOOLS = 'schools';
  const SAILORS = 'sailors';
  const COACHES = 'coaches';

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

  public function updateSchools() {
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
	$dist = (string)$school->district;
	$sch->conference = DB::getConference($dist);
	if ($sch->conference === null) {
	  $this->errors['conf-'.$dist] = "No valid conference found: " . $dist;
	  continue;
	}

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
  }

  public function updateMember(Member $proto) {
    $src = null;
    if ($proto instanceof Sailor)
      $src = Conf::$SAILOR_API_URL;
    elseif ($proto instanceof Coach)
      $src = Conf::$COACH_API_URL;
    else
      throw new InvalidArgumentException("I do not know how to sync that kind of member.");

    $role = $proto->role;
    if ($src === null) {
      $this->log("Syncing $role list: no URL found. Nothing to do.");
      return;
    }

    $this->log("Starting: fetching and parsing $role list $src");
    if (($xml = @simplexml_load_file($src)) === false) {
      $this->errors[] = "Unable to load XML from $src";
      return;
    }

    $this->log("Inactivating role $role");
    RpManager::inactivateRole($proto->role);
    $this->log(sprintf("%s role deactivated", ucfirst($role)));
    foreach ($xml->$role as $sailor) {
      try {
	$s = clone $proto;

	$school_id = trim((string)$sailor->school);
	$school = DB::getSchool($school_id);
	if ($school !== null) {
	  $s->school = $school;
	  $s->icsa_id = (int)$sailor->id;
	  $s->last_name  = (string)$sailor->last_name;
	  $s->first_name = (string)$sailor->first_name;
	  if ($proto instanceof Sailor) {
	    $s->year = (int)$sailor->year;
	    $s->gender = $sailor->gender;
	  }

	  $this->updateSailor($s);
	  $this->log("Activated $role $s");
	}
	else
	  $this->warnings[$school_id] = "Missing school " . $school_id;
      } catch (Exception $e) {
	$this->warnings[] = "Invalid sailor information: " . print_r($sailor, true);
      }
    }
  }

  /**
   * Runs the update
   *
   * @param boolean $schools true to sync schools
   * @param boolean $sailors true to sync sailors
   * @param boolean $coaches true to sync coaches
   */
  public function update($schools = true, $sailors = true, $coaches = true) {
    // ------------------------------------------------------------
    // Schools
    // ------------------------------------------------------------
    if ($schools !== false)
      $this->updateSchools();

    // ------------------------------------------------------------
    // Sailors
    // ------------------------------------------------------------
    if ($sailors !== false)
      $this->updateMember(DB::$SAILOR);

    // ------------------------------------------------------------
    // Coaches
    // ------------------------------------------------------------
    if ($coaches !== false)
      $this->updateMember(DB::$COACH);
  }
}

if (isset($argv) && basename(__FILE__) == basename($argv[0])) {
  ini_set('include_path', ".:".realpath(dirname(__FILE__).'/../'));
  require_once('conf.php');

  // Parse arguments
  array_shift($argv);
  $verb = false;
  $tosync = array(SyncDB::SCHOOLS => false,
		  SyncDB::SAILORS => false,
		  SyncDB::COACHES => false);
  foreach ($argv as $arg) {
    switch ($arg) {
    case '-v':
      $verb = true;
      break;

    case SyncDB::SCHOOLS:
    case SyncDB::SAILORS:
    case SyncDB::COACHES:
      $tosync[$arg] = true;
      break;

    default:
      printf("usage: php SyncDB.php [-v] [%s]\n", implode("] [", array_keys($tosync)));
      exit(1);
    }
  }
  $db = new SyncDB($verb);
  
  $db->update($tosync[SyncDB::SCHOOLS], $tosync[SyncDB::SAILORS], $tosync[SyncDB::COACHES]);
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
