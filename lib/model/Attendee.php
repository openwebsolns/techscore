<?php
/*
 * This class is part of Techscore
 */

/**
 * An attendee is a sailor who is present at a regatta. 
 *
 * It *should* be the case that all participants (sailors in the RP
 * form) are also attendees. On the other hand, attendees that are not
 * participants go by a special name: "reserve".
 *
 * The list of attendees serves as a mini roster for a school at a
 * regatta. RP entries are taken from this list.
 *
 * @author Dayan Paez
 * @version 2015-02-28
 */
class Attendee extends DBObject {
  
  protected $team;
  protected $sailor;
  protected $added_by;
  protected $added_on;

  public function db_type($field) {
    switch($field) {
    case 'team':
      return DB::T(DB::TEAM);
    case 'sailor':
      return DB::T(DB::SAILOR);
    case 'added_by':
      return DB::T(DB::ACCOUNT);
    case 'added_on':
      return DB::T(DB::NOW);
    default:
      return parent::db_type($field);
    }
  }
}