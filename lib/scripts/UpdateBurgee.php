<?php
/*
 * This file is part of TechScore
 *
 * @package tscore/scripts
 */

require_once('AbstractScript.php');

/**
 * A class to serialize (or remove) a burgee for a particular school.
 *
 * The path to save to is '/inc/img/schools/{SCHOOL_ID}.png'.
 *
 * If necessary, this script will create the directory.
 *
 * @author Dayan Paez
 * @version 2011-01-02
 * @package scripts
 */
class UpdateBurgee extends AbstractScript {

  /**
   * @var String the relative path to the burgee images (with respect
   * to the HTML root).
   */
  public static $filepath = '/inc/img/schools';

  /**
   * Check and update a school's burgee, if necessary
   *
   * @param School $school the school whose burgee to update
   * @throw RuntimeException if unable to execute an action
   */
  public function run(School $school) {
    $file = sprintf('%s/%s.png', self::$filepath, $school->id);

    // There is no burgee
    if ($school->burgee === null) {
      self::remove($file);
      self::errln("Removed burgee for school $school");
    }
    else {
      // Write to file
      $data = base64_decode($school->burgee->filedata);
      self::writeFile($file, $data);
      self::errln("Serialized burgee for school $school");
    }

    // Small burgee
    $file = sprintf('%s/%s-40.png', self::$filepath, $school->id);

    // There is no burgee
    if ($school->burgee_small === null) {
      self::remove($file);
      self::errln("Removed small burgee for school $school");
    }
    else {
      // Write to file
      $data = base64_decode($school->burgee_small->filedata);
      self::writeFile($file, $data);
      self::errln("Serialized small burgee for school $school");
    }
  }

  protected $cli_opts = '<school_id> [...]';
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');

  $P = new UpdateBurgee();
  $opts = $P->getOpts($argv);
  $schools = array();
  foreach ($opts as $opt) {
    if (($school = DB::getSchool($opt)) === null)
      throw new TSScriptException("Invalid school ID: $opt");
    $schools[] = $school;
  }

  if (count($schools) == 0)
    throw new TSScriptException("No schools provided.");
  foreach ($schools as $school)
    $P->run($school);
}
?>