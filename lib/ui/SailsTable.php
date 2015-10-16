<?php
namespace ui;

use \FullRegatta;
use \InvalidArgumentException;
use \SailsList;
use \XSailCombo;
use \XSpan;
use \XTable;
use \XTHead;
use \XTBody;
use \XTR;
use \XTH;
use \XTD;
use \model\FleetRotation;

/**
 * Sails table for all regattas based on FleetRotation.
 *
 * @author Dayan Paez
 * @version 2015-10-28
 */
class SailsTable extends XTable {

  const BYE_BOAT_NAME = "BYE Boat";

  /**
   * @var FleetRotation the rotation to draw.
   */
  private $rotation;
  /**
   * @var FullRegatta the regatta for the rotation.
   */
  private $regatta;

  /**
   * Creates a new sails table based on the given rotation.
   *
   * @param FleetRotation $rotation complete rotation object.
   */
  public function __construct(FleetRotation $rotation) {
    parent::__construct(array('class' => 'narrow', 'id' => 'sails-table'));
    $this->rotation = $rotation;
    $this->regatta = $rotation->regatta;
    $this->fill();
  }

  private function getTeamNames() {
    if ($this->regatta->scoring == FullRegatta::SCORING_COMBINED) {
      $teamNames = array();
      foreach ($this->regatta->getDivisions() as $division) {
        foreach ($this->regatta->getTeams() as $team) {
          $teamNames[] = new CombinedTeamDivisionSpan($division, $team);
        }
      }
      return $teamNames;
    }

    // Standard
    $teamNames = array();
    foreach ($this->regatta->getTeams() as $team) {
      $teamNames[] = (string) $team;
    }
    return $teamNames;
  }

  private function fill() {
    $rotation = $this->rotation;
    $divisions = $rotation->division_order;
    $firstDivision = $divisions[0];
    $teamNames = $this->getTeamNames();

    // Header
    $this->add(new XTHead(array(), array($tr = new XTr())));
    $tr->add(new XTH(array(), "Team"));
    $columnHeader = sprintf("1-%d", $rotation->races_per_set);
    if (count($divisions) > 1) {
      $columnHeader .= sprintf(" (%s)", $firstDivision);
    }
    $tr->add(new XTH(array(), $columnHeader));

    // Body
    $this->add($body = new XTBody());
    $sailsList = $rotation->sails_list;
    for ($i = 0; $i < $sailsList->count(); $i++) {
      $teamName = self::BYE_BOAT_NAME;
      if ($i < count($teamNames)) {
        $teamName = $teamNames[$i];
      }

      $body->add(
        new XTR(
          array(),
          array(
            new XTD(array(), $teamName),
            new XTD(
              array(),
              new XSailCombo(
                'sails[]',
                'colors[]',
                $sailsList->sailAt($i),
                $sailsList->colorAt($i)
              )
            )
          )
        )
      );
    }
  }

  private function fillCombined() {
    
  }

}