<?php
/*
 * This file is part of Techscore
 */



/**
 * A sponsor to be used on the public site
 *
 * @author Dayan Paez
 * @version 2013-10-31
 */
class Pub_Sponsor extends DBObject {
  public $name;
  public $url;
  public $relative_order;
  protected $logo;
  protected $regatta_logo;

  public function db_type($field) {
    if ($field == 'logo' || $field == 'regatta_logo')
      return DB::T(DB::PUB_FILE_SUMMARY);
    return parent::db_type($field);
  }

  protected function db_order() { return array('relative_order'=>true); }

  public function canSponsorRegattas() {
    return $this->regatta_logo !== null;
  }

  public static function getSponsorsForRegattas() {
    return DB::getAll(DB::T(DB::PUB_SPONSOR), new DBCond('regatta_logo', null, DBCond::NE));
  }

  public static function getSponsorsForSite() {
    return DB::getAll(DB::T(DB::PUB_SPONSOR), new DBCond('logo', null, DBCond::NE));
  }

  public function __toString() {
    return $this->name;
  }
}
