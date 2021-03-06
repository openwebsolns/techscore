<?php
namespace scripts;

use \Burgee;
use \DB;
use \DBBool;
use \DBCond;
use \DBCondIn;
use \School;
use \TSScriptException;

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
    $versions = array('burgee' => Burgee::SIZE_FULL,
                      'burgee_small' => Burgee::SIZE_SMALL,
                      'burgee_square' => Burgee::SIZE_SQUARE);
    foreach ($versions as $prop => $size) {
      $file = Burgee::getUrlForSize($school, $size);

      // There is no burgee
      if ($school->$prop === null) {
        self::remove($file);
        self::errln("Removed $prop for school $school");
      }
      else {
        // Write to file
        self::write($file, $school->$prop);
        self::errln("Serialized $prop for school $school");
      }
    }
  }

  /**
   * Removes unused burgees from the database
   *
   * @param School $school if given, only remove stale burgees from
   * this school
   */
  public function runCleanup(School $school = null) {
    $cond = new DBBool(array(new DBCondIn('id', DB::prepGetAll(DB::T(DB::SCHOOL), new DBCond('burgee', null, DBCond::NE), array('burgee')), DBCondIn::NOT_IN),
                             new DBCondIn('id', DB::prepGetAll(DB::T(DB::SCHOOL), new DBCond('burgee_small', null, DBCond::NE), array('burgee_small')), DBCondIn::NOT_IN),
                             new DBCondIn('id', DB::prepGetAll(DB::T(DB::SCHOOL), new DBCond('burgee_square', null, DBCond::NE), array('burgee_square')), DBCondIn::NOT_IN)));
    $mes = "Removed stale burgees.";
    if ($school !== null) {
      $cond->add(new DBCond('school', $school));
      $mes = sprintf("Removed stale burgees for %s.", $school->name);
    }

    $all = DB::getAll(DB::T(DB::BURGEE), $cond);
    foreach ($all as $bur) {
      DB::remove($bur);
      self::errln(sprintf("Removed burgee with ID %s.", $bur->id), 2);
    }
    self::errln($mes);
  }

  public function runCli(Array $argv) {
    $opts = $this->getOpts($argv);
    $all = false;
    $clean = false;
    $schools = array();
    foreach ($opts as $opt) {
      if ($opt == '-c' || $opt == '--clean') {
        $clean = true;
      }
      elseif ($opt == '--all') {
        $all = true;
      }
      else {
        if (($school = DB::getSchool($opt)) === null) {
          throw new TSScriptException("Invalid school ID: $opt");
        }
        $schools[] = $school;
      }
    }

    if ($clean) {
      if (count($schools) == 0 || $all) {
        $this->runCleanup();
      }
      else {
        foreach ($schools as $school) {
          $this->runCleanup($school);
        }
      }
    }
    else {
      if ($all) {
        $schools = DB::getAll(DB::T(DB::SCHOOL));
      }
      if (count($schools) == 0) {
        throw new TSScriptException("No schools provided.");
      }
      foreach ($schools as $school) {
        $this->run($school);
      }
    }
  }

  protected $cli_opts = '<school_id> [...] | -c [<school_id> ...]';
  protected $cli_usage = 'To update a burgee, provide the school ID(s).

To cleanup stale burgees, use -c (--clean) flag and optionally
include the school ID(s) to remove.

  --all          Apply to all schools
  -c  --clean    Remove stale burgees';
}
