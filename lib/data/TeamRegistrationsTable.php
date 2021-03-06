<?php
namespace data;

use \FullRegatta;
use \Round;
use \RP;

use \InvalidArgumentException;
use \LogicException;

use \XTable;
use \XTHead;
use \XTBody;
use \XTR;
use \XTH;
use \XTD;
use \XBr;

require_once('xml5/HtmlLib.php');

/**
 * Table with sailors who participated in team regatta.
 *
 * @author Dayan Paez
 * @version 2015-03-23
 */
class TeamRegistrationsTable extends XTable {

  /**
   * Create a new registrations table for team racing.
   *
   * Catch the LogicException to show a helpful error message to the end user
   * instead of the table.
   *
   * @param FullRegatta $regatta the regatta.
   * @param Round $round the specific round (creates a grid)
   * @param boolean $public_mode true to create links, as with public site.
   * @throws InvalidArgumentException if bad data supplied.
   * @throws LogicException (IMPORTANT) when no table can be formed.
   */
  public function __construct(FullRegatta $regatta, Round $round, $public_mode = false) {
    parent::__construct(array('class'=>'teamregistrations'), array($tbody = new XTBody()));

    $races = $regatta->getRacesInRound($round);
    if (count($races) == 0)
      throw new InvalidArgumentException("No such round $round in this regatta.");

    // Map all the teams in this round to every other team in the
    // round. For each such pairing, track the list of races in which
    // they have met.
    //
    // The structure is, then, in pseudo-JSON format:
    //
    // {Team1ID : {Team2ID : {Race# : {DIV : Race}}}}
    //
    // Also track corresponding team objects
    $teams = array();
    $map = array();
    foreach ($races as $race) {
      if (!$regatta->hasFinishes($race))
        continue;

      $ts = $regatta->getRaceTeams($race);
      if (count($ts) < 2)
        continue;

      foreach ($ts as $t) {
        $teams[$t->id] = $t;
        if (!isset($map[$t->id]))
          $map[$t->id] = array();
      }

      $t0 = $ts[0];
      $t1 = $ts[1];

      if (!isset($map[$t0->id][$t1->id]))  $map[$t0->id][$t1->id] = array();
      if (!isset($map[$t1->id][$t0->id]))  $map[$t1->id][$t0->id] = array();

      if (!isset($map[$t0->id][$t1->id][$race->number])) $map[$t0->id][$t1->id][$race->number] = array();
      if (!isset($map[$t1->id][$t0->id][$race->number])) $map[$t1->id][$t0->id][$race->number] = array();

      $map[$t0->id][$t1->id][$race->number][(string)$race->division] = $race;
      $map[$t1->id][$t0->id][$race->number][(string)$race->division] = $race;
    }
    if (count($map) == 0) {
      throw new LogicException("No races sailed in this round.");
    }


    // Fill the table
    $tbody->add($header = new XTR(array('class'=>'tr-cols')));
    $header->add(new XTD(array('class'=>'tr-pivot'), "↓ vs →"));

    $rp = $regatta->getRpManager();
    foreach ($teams as $i0 => $t0) {
      // Header
      $header->add(new XTH(array('class'=>'tr-vert-label'), $t0->school->nick_name));
      $row = new XTR(array('class'=>sprintf('tr-row team-%s', $t0->id)),
                     array(new XTH(array('class'=>'tr-horiz-label'), $t0)));

      foreach ($teams as $i1 => $t1) {
        if (!isset($map[$i0][$i1]) || count($map[$i0][$i1]) == 0) {
          $row->add(new XTD(array('class'=>'tr-ns'), "X"));
          continue;
        }

        $subtab = null;
        if (count($map[$i0][$i1]) > 1)
          $row->add(new XTD(array('class'=>'tr-mult'),
                            new XTable(array('class'=>'tr-multtable'),
                                       $subtab = new XTBody())));

        foreach ($map[$i0][$i1] as $num => $races) {
          $subrow = $row;
          if ($subtab !== null)
            $subtab->add($subrow = new XTR());

          // Separate boats in a table
          $subrow->add(new XTD(array('class'=>'tr-boattable'),
                               new XTable(array('class'=>'tr-boats'),
                                          array($boattable = new XTBody()))));
          foreach ($races as $div => $race) {
            $boattable->add(new XTR(array('class'=>'tr-boat-'.$div), array($cell = new XTD())));
            $e = 0;
            foreach (array(RP::SKIPPER, RP::CREW) as $role) {
              foreach ($rp->getRpEntries($t0, $race, $role) as $entry) {
                if ($e++ > 0)
                  $cell->add(new XBr());
                $cell->add($entry->getSailor(true, $public_mode));
              }
            }
          }
        }
      }
      $tbody->add($row);
    }
  }
}