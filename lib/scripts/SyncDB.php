<?php
/*
 * This file is part of TechScore
 *
 * @version 2.0
 * @package scripts
 */

require_once('AbstractScript.php');

/**
 * Pulls information from the ICSA database and updates the local
 * sailor database with the data.
 *
 * 2011-09-12: Sets active flag to true
 *
 * @author Dayan Paez
 * @version 2010-03-02
 */
class SyncDB extends AbstractScript {

  /**
   * @var Array Errors encountered
   */
  private $errors;

  /**
   * @var Array Warnings issued
   */
  private $warnings;

  const SCHOOLS = 'schools';
  const SAILORS = 'sailors';
  const COACHES = 'coaches';

  /**
   * Creates a new SyncDB object
   *
   */
  public function __construct() {
    parent::__construct();
    $this->errors = array();
    $this->warnings = array();
    $this->cli_opts = 'schools | sailors | coaches';
    $this->cli_usage = "Provide at least one argument to update";
  }

  /**
   * Fetch the errors
   *
   * @return Array<String> error messages
   */
  public function errors() { return $this->errors; }

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
    if ($sailor->gender === null)
      $sailor->gender = Sailor::MALE;
    DB::set($sailor, $update);
  }

  public function updateSchools() {
    self::errln("Fetching and parsing schools from " . Conf::$SCHOOL_API_URL);

    if (($xml = @simplexml_load_file(Conf::$SCHOOL_API_URL)) === false) {
      $this->errors[] = "Unable to load XML from " . Conf::$SCHOOL_API_URL;
      return;
    }

    self::errln("Inactivating schools", 2);
    DB::inactivateSchools();
    self::errln("Schools deactivated", 2);

    // parse data
    foreach ($xml->school as $school) {
      try {
        $id = trim((string)$school->school_code);
        $sch = DB::getSchool($id);
        $upd = true;
        if ($sch === null) {
          $this->warnings[] = sprintf("New school: %s", $school->school_code);
          $sch = new School();
          $sch->id = $id;
          $upd = false;
        }
        $dist = trim((string)$school->district);
        $sch->conference = DB::getConference($dist);
        if ($sch->conference === null) {
          $this->errors['conf-'.$dist] = "No valid conference found: " . $dist;
          continue;
        }

        // Update fields
        $sch->name = trim((string)$school->school_name);
        if ($sch->nick_name === null)
          $sch->nick_name = School::createNick($school->school_display_name);
        $sch->nick_name = trim($sch->nick_name);
        $sch->city = trim((string)$school->school_city);
        $sch->state = trim((string)$school->school_state);
        $sch->inactive = null;

        DB::set($sch, $upd);
        self::errln(sprintf("Activated school %10s: %s", $sch->id, $sch->name), 2);

      } catch (Exception $e) {
        $this->errors[] = "Invalid school information: " . $e->getMessage();
      }
    }

    // Update the summary page for completeness
    require_once('UpdateSchoolsSummary.php');
    $P = new UpdateSchoolsSummary();
    $P->run();
  }

  public function updateMember(Member $proto) {
    $src = null;
    if ($proto instanceof Sailor)
      $src = Conf::$SAILOR_API_URL;
    elseif ($proto instanceof Coach)
      $src = Conf::$COACH_API_URL;
    else
      throw new TSScriptException("I do not know how to sync that kind of member.");

    $role = ($proto instanceof Sailor) ? 'sailor' : 'coach';
    if ($src === null) {
      $this->errors[] = "No URL found for $role list.";
      return;
    }

    self::errln("Fetching and parsing $role list from $src");
    if (($xml = @simplexml_load_file($src)) === false) {
      $this->errors[] = "Unable to load XML from $src";
      return;
    }

    self::errln("Inactivating role $role", 2);
    RpManager::inactivateRole($proto->role);
    self::errln(sprintf("%s role deactivated", ucfirst($role)), 2);
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
          self::errln("Activated $role $s", 2);
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
  public function run($schools = true, $sailors = true, $coaches = true) {
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

    foreach ($this->warnings as $mes)
      self::errln("Warning: $mes");
  }
}

if (isset($argv) && basename(__FILE__) == basename($argv[0])) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');

  // Parse arguments
  $P = new SyncDB();
  $opts = $P->getOpts($argv);
  if (count($opts) == 0)
    throw new TSScriptException("Missing update argument");
  $tosync = array(SyncDB::SCHOOLS => false,
                  SyncDB::SAILORS => false,
                  SyncDB::COACHES => false);
  foreach ($opts as $arg) {
    switch ($arg) {
    case SyncDB::SCHOOLS:
    case SyncDB::SAILORS:
    case SyncDB::COACHES:
      $tosync[$arg] = true;
      break;

    default:
      throw new TSScriptException("Invalid argument $arg");
    }
  }
  $P->run($tosync[SyncDB::SCHOOLS], $tosync[SyncDB::SAILORS], $tosync[SyncDB::COACHES]);
  $err = $P->errors();
  if (count($err) > 0) {
    echo "----------Error(s)\n";
    foreach ($err as $mes)
      printf("  %s\n", $mes);
  }
}
?>
