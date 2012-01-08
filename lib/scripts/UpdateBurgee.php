<?php
/**
 * A class and a script used to update one or more burgees for one or
 * more schools. When burgee changes occur because a coach uploaded a
 * new school burgee, this script should be called to generate a
 * static version of that burgee on the public version of the
 * site. This, in turn, should be called by some cron job, so that the
 * apache user is not actively writing to the public site.
 *
 * One reason for actively creating/overwriting the new file is to
 * make a public site that is "free-standing" and not relying on data
 * from the database, which is where the burgees (and all previous
 * copies) are ultimately stored. This also aids in the creation of
 * static site mirrors, as only files need to be synced.
 *
 * When run as a script, this file will check each school in the
 * database and find its latest burgee. Based on the timestamp of the
 * database entry and the timestamp of the corresponding image on the
 * filesystem, the program then updates the filesystem copy, or
 * removes it (should rarely happen), or just plain ignores it.
 *
 * Like other scripts, the program may also be called by loading the
 * class and calling its static methods.
 *
 * The path to save to is '.../img/schools/{SCHOOL_ID}.png'.
 *
 * @author Dayan Paez
 * @version 2011-01-02
 * @package scripts
 */
class UpdateBurgee {

  /**
   * @var String the relative path to the burgee images (with respect
   * to THIS file).
   */
  public static $filepath = '../../html/inc/img/schools';

  /**
   * Checks each school in the database for a possible burgee update
   *
   */
  public static function run() {
    foreach (DB::getConferences() as $conf) {
      foreach (DB::getSchoolsInConference($conf) as $school) {
	self::update($school);
      }
    }
  }

  /**
   * Check and update a school's burgee, if necessary
   *
   * @param School $school the school whose burgee to update
   * @throw RuntimeException if unable to execute an action
   */
  public static function update(School $school) {
    $file = sprintf('%s/%s/%s.png', dirname(__FILE__), self::$filepath, $school->id);

    // 1. There is no burgee
    if ($school->burgee === null) {
      // Is there one in the filesystem? Delete it!
      if (file_exists($file) && !(@unlink($file)))
	throw new RuntimeException(sprintf('Unable to remove file for school %s.', $school->id));
      return;
    }

    // 2. Check timestamp
    if (file_exists($file)) {
      if (filemtime($file) >= $school->burgee->last_updated->format('U'))
	return;
    }

    // 3. Transfer data to file
    $res = file_put_contents($file, base64_decode($school->burgee->filedata));
    if ($res === false)
      throw new RuntimeException("Unable to write to file $file.");
  }
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  ini_set('include_path', ".:".realpath(dirname(__FILE__).'/../'));
  require_once('conf.php');
  try {
    UpdateBurgee::run();
  } catch (Exception $e) {
    printf("Error while updating burgees: %s\n", $e->getMessage());
    exit(1);
  }
}
?>