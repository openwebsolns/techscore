<?php
/*
 * This file is part of TechScore
 *
 * @version 2.0
 * @package scripts
 */

/**
 * Pulls information from the ICSA database and updates the local
 * school database with the data.
 *
 * @author Dayan Paez
 * @version 2012-04-01
 */
class UpdateSchoolsDB {

  /**
   * Errors encountered
   */
  private $errors;

  /**
   * Warnings issued
   */
  private $warnings;

  /**
   * @var MySQLi the connection
   */
  private $con;

  private $verbose = false;

  /**
   * Creates a new UpdateSailorsDB object
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
    $this->log("Starting: fetching and parsing schools " . Conf::$SCHOOL_API_URL);
    
    if (($xml = @simplexml_load_file(Conf::$SCHOOL_API_URL)) === false) {
      $this->errors[] = "Unable to load XML from " . Conf::$SCHOOL_API_URL;
      return;
    }

    $this->log("Inactivating schools");
    DB::inactivateSchools();
    $this->log("Schools deactivated");
    DB::$SCHOOL->db_set_cache(false);

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
	$sch->city = (string)$school->city;
	$sch->state = (string)$school->state;
	$sch->inactive = null;

	DB::set($sch, $upd);
	$this->log(sprintf("Activated school %10s: %s", $sch->id, $sch->name));

      } catch (Exception $e) {
	$this->errors[] = "Invalid school information: " . $e->getMessage();
      }
    }
  }
}

if (isset($argv) && basename(__FILE__) == basename($argv[0])) {
  ini_set('include_path', ".:".realpath(dirname(__FILE__).'/../'));
  require_once('conf.php');

  $opt = getopt('v');
  $db = new UpdateSchoolsDB(isset($opt['v']));
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
