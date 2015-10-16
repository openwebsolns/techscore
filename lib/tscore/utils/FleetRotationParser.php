<?php
namespace tscore\utils;

use \model\FleetRotation;
use \InvalidArgumentException;
use \SoterException;

use \DB;
use \FullRegatta;
use \SailsList;

/**
 * Transforms FleetRotation to and from user input.
 *
 * @author Dayan Paez
 * @version 2015-10-19
 */
class FleetRotationParser {

  private $regatta;

  public function __construct(FullRegatta $regatta) {
    $this->regatta = $regatta;
    if (!$regatta->isFleetRacing()) {
      throw new InvalidArgumentException("Only fleet racing regattas allowed.");
    }
  }

  /**
   * Create the fleet rotation object based on GET/POST parameters.
   *
   * Only the arguments that are provided in args are parsed. It is
   * the responsibility of the caller to ascertain that the object is
   * properly populated.
   *
   * @param Array $args the request parameters.
   * @return FleetRotation the (partially-filled) object.
   * @throws SoterException when invalid arguments are provided.
   */
  public function fromArgs(Array $args) {
    $rotation = new FleetRotation();
    if (array_key_exists('rotation_type', $args)) {
      $rotation->rotation_type = DB::$V->reqValue($args, 'rotation_type', FleetRotation::types(), "Invalid rotation type provided.");
    }
    if (array_key_exists('races_per_set', $args)) {
      $rotation->races_per_set = DB::$V->reqInt($args, 'races_per_set', 1, 101, "Invalid races per set provided.");
    }
    if (array_key_exists('rotation_style', $args)) {
      $rotation->rotation_style = DB::$V->reqValue($args, 'rotation_style', FleetRotation::styles(), "Invalid rotation style provided.");
    }

    // Division order
    if (array_key_exists('division_order', $args)) {
      $regattaDivisions = array();
      foreach ($this->regatta->getDivisions() as $division) {
        $regattaDivisions[] = (string) $division;
      }
      $orderedDivisions = DB::$V->reqList($args, 'division_order', count($regattaDivisions), "Invalid division order provided.");
      foreach ($orderedDivisions as $division) {
        if (!in_array($division, $regattaDivisions)) {
          throw new SoterException(sprintf("Division \"%s\" not provided.", $division));
        }
      }
      if (array_key_exists('order', $args)) {
        $order = DB::$V->reqList($args, 'order', count($orderedDivisions), "Invalid order specified for divisions.");
        array_multisort($order, SORT_NUMERIC, $orderedDivisions, SORT_STRING);
      }
      $rotation->division_order = $orderedDivisions;
    }

    // Sails and colors
    if (array_key_exists('sails', $args)) {
      $sails = DB::$V->reqList($args, 'sails', null, "Invalid list of sails provided.");
      $uniqSails = array();
      foreach ($sails as $sail) {
        $sail = (string) $sail;
        if (in_array($sail, $uniqSails)) {
          throw new SoterException(sprintf("Duplicate sail \"%s\" provided.", $sail));
        }
        $uniqSails[] = $sail;
      }

      $sailsList = new SailsList();
      $sailsList->sails = $uniqSails;
      if (array_key_exists('colors', $args)) {
        $sailsList->colors = DB::$V->reqList($args, 'colors', count($uniqSails), "Invalid list of colors provided.");
      }
      $rotation->sails_list = $sailsList;
    }

    return $rotation;
  }

  /**
   * Creates a set of key-value pairs based on given rotation.
   *
   * @param FleetRotation $rotation the rotation to "serialize".
   * @return Array key-value pairs associated with given rotation.
   */
  public function toArgs(FleetRotation $rotation) {
    $args = array();
    if ($rotation->rotation_type !== null) {
      $args['rotation_type'] = $rotation->rotation_type;
    }
    if ($rotation->rotation_style !== null) {
      $args['rotation_style'] = $rotation->rotation_style;
    }
    if ($rotation->races_per_set !== null) {
      $args['races_per_set'] = $rotation->races_per_set;
    }
    if ($rotation->division_order !== null) {
      $args['division_order'] = $rotation->division_order;
      $args['order'] = array();
      foreach ($args['division_order'] as $i => $division) {
        $args['order'][] = ($i + 1);
      }
    }
    if ($rotation->sails_list !== null) {
      $args['sails'] = $rotation->sails_list->sails;
      $args['colors'] = $rotation->sails_list->colors;
    }
    return $args;
  }
}