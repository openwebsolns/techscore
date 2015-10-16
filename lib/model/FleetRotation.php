<?php
namespace model;

use \DB;

/**
 * Settings for a rotation in a fleet racing regatta.
 *
 * @author Dayan Paez
 * @version 2015-10-16
 */
class FleetRotation extends AbstractObject {

  const TYPE_STANDARD = 'standard';
  const TYPE_SWAP = 'swap';
  const TYPE_NONE = 'none';

  const STYLE_SIMILAR = 'copy';
  const STYLE_NAVY = 'navy';
  const STYLE_FRANNY = 'fran';

  protected $regatta;
  protected $division_order;
  public $rotation_type;
  public $rotation_style;
  public $races_per_set;
  protected $sails_list;

  public function db_name() {
    return 'fleet_rotation';
  }

  public function db_type($field) {
    switch ($field) {
    case 'regatta':        return DB::T(DB::FULL_REGATTA);
    case 'division_order': return array();
    case 'sails_list':     return DB::T(DB::SAILS_LIST);
    default:               return parent::db_type($field);
    }
  }

  protected function db_order() {
    return array('created_on' => false);
  }

  public static function types() {
    return array(
      self::TYPE_STANDARD,
      self::TYPE_SWAP,
      self::TYPE_NONE
    );
  }

  public static function styles() {
    return array(
      self::STYLE_SIMILAR,
      self::STYLE_NAVY,
      self::STYLE_FRANNY
    );
  }
}