<?php
/*
 * This file is part of Techscore
 */



/**
 * An individual record of participation entry: a specific sailor in a
 * specific race for a specific team, in a specific boat_role
 *
 * @author Dayan Paez
 * @version 2012-01-13
 */
class RPEntry extends DBObject {
  protected $race;
  protected $team;
  protected $attendee;
  public $boat_role;

  public function db_name() { return 'rp'; }
  public function db_type($field) {
    switch ($field) {
    case 'race': return DB::T(DB::RACE);
    case 'team': return DB::T(DB::TEAM);
    case 'attendee': return DB::T(DB::ATTENDEE);
    default:
      return parent::db_type($field);
    }
  }
  protected function db_order() { return array('team'=>true, 'race'=>true); }

  /**
   * Provide the sailor by proxy of attendee.
   *
   */
  public function &__get($name) {
    if ($name == 'sailor') {
      if ($this->attendee === null)
        return null;
      $att = $this->__get('attendee');
      $sailor = $att->sailor;
      return $sailor;
    }
    $ret = parent::__get($name);
    return $ret;
  }

  /**
   * Returns textual representation of sailor.
   *
   * Because sailor might be null (which means no-show) calling this
   * method will return "No show" rather than the empty String.
   *
   * @param boolean $xml true to wrap No shows in XSpan
   * @return String the sailor
   */
  public function getSailor($xml = false) {
    if ($this->sailor === null) {
      if ($xml !== false)
        return new XSpan("No show", array('class'=>'noshow'));
      return "No show";
    }
    return (string)$this->__get('sailor');
  }
}
