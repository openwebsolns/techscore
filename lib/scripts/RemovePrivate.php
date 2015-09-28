<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2010-09-18
 * @package scripts
 */

require_once('AbstractScript.php');


/**
 * Scours database and removes private regattas that are more than
 * three months old. Also remove all private regattas, just in case.
 *
 * @author Dayan Paez
 * @created 2012-10-26
 */
class RemovePrivate extends AbstractScript {

  private $dry_run = false;

  /**
   * Sets dry run flag
   *
   * @param boolean $flag true to turn on
   */
  public function setDryRun($flag = false) {
    $this->dry_run = ($flag !== false);
  }

  /**
   * Helper method retrieves regattas that can be deleted.
   *
   * @return Array:FullRegatta
   */
  public function getRegattasToRemove() {
    return DB::getAll(
      DB::T(DB::FULL_REGATTA),
      new DBBool(
        array(
          new DBCond('inactive', null, DBCond::NE),
          new DBBool(
            array(
              new DBCond('private', null, DBCond::NE),
              new DBCond('end_date', new DateTime('4 months ago'), DBCond::LE)
            )
          )
        ),
        DBBool::mOR)
    );
  }

  /**
   * Returns non-registered, non-sailing sailors with no associated regatta.
   *
   * @return Array:Sailor
   */
  public function getOrphanedSailors() {
    return DB::getAll(
      DB::T(DB::MEMBER),
      new DBBool(
        array(
          new DBCond('icsa_id', null),
          new DBCond('regatta_added', null),
          new DBCondIn(
            'id',
            DB::prepGetAll(
              DB::T(DB::ATTENDEE),
              new DBCondIn(
                'id',
                DB::prepGetAll(
                  DB::T(DB::RP_ENTRY),
                  null,
                  array('attendee')
                )
              ),
              array('sailor')
            ),
            DBCondIn::NOT_IN
          )
        )
      )
    );
  }

  public function run() {
    // ------------------------------------------------------------
    // Delete regattas
    // ------------------------------------------------------------
    $regs = $this->getRegattasToRemove();
    foreach ($regs as $reg) {
      if (!$this->dry_run)
        DB::remove($reg);
      self::errln(sprintf("Deleting (%s) %4d: %s", $reg->getSeason(), $reg->id, $reg->name));
    }

    // ------------------------------------------------------------
    // Delete orphaned sailors
    // ------------------------------------------------------------
    $sailors = $this->getOrphanedSailors();
    foreach ($sailors as $sailor) {
      self::errln(sprintf("Removable: %-10s %s", $sailor->school->id, $sailor), 2);
      if (!$this->dry_run) {
        DB::remove($sailor);
      }
    }
    self::errln(sprintf("Removed %d sailors", count($sailors)));

    if ($this->dry_run) {
      self::errln("DRY-RUN only");
    }
  }

  protected $cli_opts = '[-n]';
  protected $cli_usage = ' -n, --dry-run  Do not perform deletion';
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');

  $P = new RemovePrivate();
  $opts = $P->getOpts($argv);
  foreach ($opts as $opt) {
    if ($opt == '-n' || $opt == '--dry-run')
      $P->setDryRun(true);
    else
      throw new TSScriptException("Invalid argument: $opt");
  }
  $P->run();
}

?>