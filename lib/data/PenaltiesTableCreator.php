<?php
namespace data;

use \FullRegatta;

use \XQuickTable;
use \XA;
use \XSpan;
use \XTD;

require_once('xml5/HtmlLib.php');

/**
 * Displays ordered list of all penalties in the regatta.
 *
 * @author Dayan Paez
 * @version 2017-05-01
 */
class PenaltiesTableCreator {

  const CLASSNAME = 'penalty-table';
  private $regatta;
  private $linkSchools;
  private $generated = false;
  private $table;

  /**
   * Creates a new creator of penalty table for given regatta.
   *
   * @param FullRegatta $regatta the regatta.
   * @param boolean $link_schools true to produce links, as with public site.
   */
  public function __construct(FullRegatta $regatta, $link_schools = false) {
    $this->regatta = $regatta;
    $this->linkSchools = $link_schools;
  }

  public function getPenaltiesTable() {
    $this->generate();
    return $this->table;
  }

  private function generate() {
    if ($this->generated === true) {
      return;
    }
    $this->generated = true;

    $entries = array();
    foreach ($this->regatta->getFinishModifiers() as $penalty) {
      $entries[] = PenaltiesTableEntry::fromFinishModifier($penalty);
    }
    foreach ($this->regatta->getDivisionPenalties() as $penalty) {
      $entries[] = PenaltiesTableEntry::fromDivisionPenalty($penalty, $this->regatta);
    }
    foreach ($this->regatta->getReducedWinsPenalties() as $penalty) {
      $entries[] = PenaltiesTableEntry::fromReducedWinsPenalty($penalty);
    }
    if (count($entries) === 0) {
      $this->table = null;
      return;
    }

    usort($entries, PenaltiesTableEntry::compareCallback());
    $this->table = new XQuickTable(
      array('class' => self::CLASSNAME),
      array("Team", "Race", "Penalty",  "Amount", "Comments")
    );
    foreach ($entries as $entry) {
      $team = $entry->team;
      if ($this->linkSchools) {
        $team = new XSpan(new XA(sprintf('%s%s/', $entry->team->school->getURL(), $this->regatta->getSeason()), $entry->team->school));
        $team->add(' ');
        $team->add($entry->team->name);
      }
      $this->table->addRow(
        array(
          new XTD(array('class' => 'teamname'), $team),
          $entry->getRaceOrDivision(),
          $entry->type,
          $entry->amount,
          $entry->comments,
        )
      );
    }
  }
}
