<?php
namespace data;

use \BadFunctionCallException;

use \model\ReducedWinsPenalty;
use \DivisionPenalty;
use \FinishModifier;
use \FullRegatta;
use \Race;

/**
 * Penalty row, as data, and comparable.
 *
 * @author Dayan Paez
 * @version 2017-05-01
 */
class PenaltiesTableEntry {
  private $team;
  private $race;
  private $division;
  private $type;
  private $amount;
  private $comments;

  public function __get($name) {
    if (!property_exists($this, $name)) {
      throw new BadFunctionCallException("$name is not a property");
    }
    return $this->$name;
  }

  /**
   * Return race, if it exists, or division instead.
   *
   * @return String either the race, the division, or "All"
   */
  public function getRaceOrDivision() {
    if ($this->race !== null) {
      return (string) $this->race;
    }
    if ($this->division !== null) {
      return (string) $this->division;
    }
    return "All";
  }

  public static function fromFinishModifier(FinishModifier $pen) {
    $entry = new PenaltiesTableEntry();
    $entry->team = $pen->finish->team;
    $entry->race = $pen->finish->race;
    $entry->type = $pen->type;
    $entry->amount = sprintf(
      "%s points (Orig: %s)",
      $pen->finish->score,
      $pen->finish->earned
    );
    $entry->comments = $pen->comments;
    return $entry;
  }

  public static function fromDivisionPenalty(DivisionPenalty $pen, FullRegatta $reg) {
    $entry = new PenaltiesTableEntry();
    $entry->team = $pen->team;
    $entry->division = $pen->division;
    $entry->type = $pen->type;
    $entry->comments = $pen->comments;
    if ($reg->scoring === FullRegatta::SCORING_TEAM) {
      $entry->amount = '-2 wins, +2 losses';
    } else {
      $entry->amount = '+20';
    }
    return $entry;
  }

  public static function fromReducedWinsPenalty(ReducedWinsPenalty $pen) {
    $entry = new PenaltiesTableEntry();
    $entry->team = $pen->team;
    $entry->race = $pen->race;
    $entry->type = 'Discretionary';
    $entry->amount = sprintf('%s wins', -1 * $pen->amount);
    $entry->comments = $pen->comments;
    return $entry;
  }

  public static function compareCallback() {
    return function(PenaltiesTableEntry $o1, PenaltiesTableEntry $o2) {
      // sort by team first
      $team1 = (string) $o1->team;
      $team2 = (string) $o2->team;
      $cmp = strcmp($team1, $team2);
      if ($cmp !== 0) {
        return $cmp;
      }

      // sort by specificity: those assigned to races in increasing
      // order, then those assigned at divisions, then those assigned to
      // the whole team.
      $race1 = $o1->race;
      $race2 = $o2->race;
      if ($race1 === null && $race2 === null) {
        $div1 = $o1->division;
        $div2 = $o2->division;
        if ($div2 === null) {
          return -1;
        }
        if ($div1 === null) {
          return 1;
        }
        return strcmp((string) $div1, (string) $div2);
      }

      if ($race2 === null) {
        return -1;
      }
      if ($race1 === null) {
        return 1;
      }
      return Race::compareNumber($race1, $race2);
    };
  }
}