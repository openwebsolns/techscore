<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2015-02-20
 * @package scripts
 */

require_once('AbstractScript.php');

/**
 * Erase updates that have been completed and are "old".
 *
 * @author Dayan Paez
 * @created 2015-02-20
 */
class CleanupCompletedUpdates extends AbstractScript {

  /**
   * Returns the first update completed after given deadline.
   *
   * @param AbstractUpdate $template
   * @param DateTime $deadline
   * @return AbstractUpdate of the same type as parameter, or null.
   */
  private function getFirstCompletedSince(AbstractUpdate $template, DateTime $deadline) {
    $all = DB::getAll(
      $template,
      new DBBool(
        array(
          new DBCond('completion_time', null, DBCond::NE),
          new DBCond('completion_time', $deadline, DBCond::GE)
        )
      ),
      1
    );
    return (count($all) == 0) ? null : $all[0];
  }

  /**
   * Returns the last update completed prior to given deadline.
   *
   * @param AbstractUpdate $template
   * @param DateTime $deadline
   * @return AbstractUpdate of the same type as parameter, or null.
   */
  private function getLastCompletedBefore(AbstractUpdate $template, DateTime $deadline) {
    $all = DB::getAll(
      $template,
      new DBBool(
        array(
          new DBCond('completion_time', null, DBCond::NE),
          new DBCond('completion_time', $deadline, DBCond::LT)
        )
      ),
      1
    );
    return (count($all) == 0) ? null : $all[0];
  }

  /**
   * Deletes completed updates across all public resource types.
   *
   * Will keep at least one completed entry in the database.
   */
  public function run() {
    $updateObjects = array(
      DB::T(DB::UPDATE_REQUEST),
      DB::T(DB::UPDATE_SEASON),
      DB::T(DB::UPDATE_SCHOOL),
      DB::T(DB::UPDATE_CONFERENCE),
      DB::T(DB::UPDATE_SAILOR),
      DB::T(DB::UPDATE_FILE),
    );

    $deadline = new DateTime('1 month ago');
    foreach ($updateObjects as $updateObject) {
      $name = get_class($updateObject);

      // We want to delete all completed entries that are older than
      // the given deadline. However, we want to leave at least one
      // completed entry in the database.

      $cond = new DBBool(
        array(
          new DBCond('completion_time', null, DBCond::NE),
          new DBCond('completion_time', $deadline, DBCond::LT)
        )
      );
      if ($this->getFirstCompletedSince($updateObject, $deadline) === null) {
        self::errln(sprintf("%s: No updates since %s, keeping one.", $name, DB::howLongFrom($deadline)));

        $lastOld = $this->getLastCompletedBefore($updateObject, $deadline);
        if ($lastOld !== null) {
          $cond->add(new DBCond('id', $lastOld->id, DBCond::LT));
        }
        else {
          self::errln(sprintf("%s: No updates at all! Skipping.", $name));
          continue;
        }
      }

      DB::removeAll($updateObject, $cond);
      self::errln(sprintf("%s: Removed old completed updates.", $name));
    }
  }
}
// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');

  $P = new CleanupCompletedUpdates();
  $opts = $P->getOpts($argv);

  foreach ($opts as $opt) {
    throw new TSScriptException("Invalid argument: $opt");
  }
  $P->run();
}
?>