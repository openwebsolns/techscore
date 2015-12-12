<?php
namespace scripts;

use \DB;
use \DBBool;
use \DBCond;
use \DBCondIn;
use \DateTime;
use \Sailor;
use \TSScriptException;

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
          new DBCond('register_status', Sailor::STATUS_UNREGISTERED),
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

  public function runCli(Array $argv) {
    $opts = $this->getOpts($argv);
    foreach ($opts as $opt) {
      if ($opt == '-n' || $opt == '--dry-run') {
        $this->setDryRun(true);
      }
      else {
        throw new TSScriptException("Invalid argument: $opt");
      }
    }
    $this->run();
  }

  protected $cli_opts = '[-n]';
  protected $cli_usage = ' -n, --dry-run  Do not perform deletion';
}
