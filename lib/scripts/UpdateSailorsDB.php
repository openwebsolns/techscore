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
 * @author Dayan Paez
 * @created 2010-03-02
 */
class UpdateSailorsDB {

  /**
   * The URL to check for new sailors
   */
  public $SAILOR_URL = 'http://www.collegesailing.org/directory/individual/sailorapi.asp';

  /**
   * The URL to check for new coaches
   */
  public $COACH_URL = 'http://www.collegesailing.org/directory/individual/coachapi.asp';
  
  /**
   * MySQLi connection
   */
  private $con;

  /**
   * Errors encountered
   */
  private $errors;

  /**
   * Warnings issued
   */
  private $warnings;

  /**
   * Creates a new UpdateSailorsDB object
   *
   */
  public function __construct() {
    $this->con = new MySQLi(SQL_HOST, SQL_USER, SQL_PASS, SQL_DB);
    $this->errors = array();
    $this->warnings = array();
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
  private function updateSailor(Sailor $sailor) {
    $s = ($sailor instanceof Coach) ? "coach" : "student";

    // Determine if the icsa_id exists already
    $q = sprintf('select id from sailor where icsa_id = "%s"', $sailor->icsa_id);
    $q = $this->con->query($q);
    
    if ($q->num_rows == 0) {
      // new sailor
      $q2 = sprintf('insert into sailor (icsa_id, school, last_name, first_name, year, role) ' .
		    'values ("%s", "%s", "%s", "%s", "%s", "%s")',
		    $sailor->icsa_id,
		    $sailor->school->id,
		    $sailor->last_name,
		    $sailor->first_name,
		    $sailor->year,
		    $s);
      $this->con->query($q2);
    }
    else {
      $id = $q->fetch_object();
      $q2 = sprintf('update sailor set school = "%s", last_name = "%s", first_name = "%s", year = "%s", role = "%s" ' .
		    'where id = "%s"',
		    $sailor->school->id,
		    $sailor->last_name,
		    $sailor->first_name,
		    $sailor->year,
		    $s,
		    $id->id);
      $this->con->query($q2);
    }
  }

  /**
   * Runs the update
   *
   */
  public function update() {
    $schools = array();
    
    if (($xml = @simplexml_load_file($this->SAILOR_URL)) === false) {
      $this->errors[] = "Unable to load XML from " . $this->SAILOR_URL;
    }
    else {
      // parse data
      foreach ($xml->sailor as $sailor) {
	try {
	  $s = new Sailor();
	  $s->icsa_id = (int)$sailor->id;

	  // keep cache of schools
	  $school_id = (string)$sailor->school;	  
	  if (!isset($schools[$school_id]))
	    $schools[$school_id] = Preferences::getSchool($school_id);

	  if ($schools[$school_id] !== null) {
	    $s->school = $schools[$school_id];
	    $s->last_name  = $this->con->real_escape_string($sailor->last_name);
	    $s->first_name = $this->con->real_escape_string($sailor->first_name);
	    $s->year = (int)$sailor->year;

	    $this->updateSailor($s);
	  }
	  else
	    $this->warnings[$school_id] = "Missing school $school_id.";
	} catch (Exception $e) {
	  $this->warnings[] = "Invalid sailor information: " . str_replace("\n", "/", print_r($sailor, true));
	}
      }
    }

    // Coaches
    if (($xml = @simplexml_load_file($this->COACH_URL)) === false) {
      $this->errors[] = "Unable to load XML from " . $this->COACH_URL;
    }
    else {
      // parse data
      foreach ($xml->sailor as $sailor) {
	try {
	  $s = new Coach();
	  $s->icsa_id = (int)$sailor->id;

	  // keep cache of schools
	  $school_id = (string)$sailor->school;	  
	  if (!isset($schools[$school_id]))
	    $schools[$school_id] = Preferences::getSchool($school_id);
	  
	  $s->school = $schools[$school_id];
	  $s->last_name  = $this->con->real_escape_string($sailor->last_name);
	  $s->first_name = $this->con->real_escape_string($sailor->first_name);
	  $s->year = (int)$sailor->year;

	  $this->updateSailor($s);
	} catch (Exception $e) {
	  $warnings[] = "Invalid coach information: " . str_replace("\n", "/", print_r($sailor, true));
	}
      }
    }
  }
}

if (isset($argv) && basename(__FILE__) == basename($argv[0])) {
  ini_set('include_path', ".:".realpath(dirname(__FILE__).'/../'));
  require_once('conf.php');
  
  $db = new UpdateSailorsDB();
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