<?php

/**
 * Request to updatae a conference page.
 *
 * Conference argument may be null, which implies that the conference
 * was deleted. Another way to suggest deletion is with a URL argument
 * that does NOT match the URL of the associated conference.
 *
 * @author Dayan Paez
 * @version 2014-06-20
 */
class UpdateConferenceRequest extends AbstractUpdate {
  protected $conference;
  protected $season;
  public $argument;

  public function db_name() { return 'pub_update_conference'; }
  public function db_type($field) {
    if ($field == 'conference')
      return DB::T(DB::CONFERENCE);
    if ($field == 'season')
      return DB::T(DB::SEASON);
    return parent::db_type($field);
  }

  const ACTIVITY_DETAILS = 'details';
  const ACTIVITY_SEASON = 'season';
  const ACTIVITY_URL = 'url';
  const ACTIVITY_DISPLAY = 'display'; // is the setting enabled?

  public static function getTypes() {
    return array(
      self::ACTIVITY_DETAILS => self::ACTIVITY_DETAILS,
      self::ACTIVITY_SEASON => self::ACTIVITY_SEASON,
      self::ACTIVITY_URL => self::ACTIVITY_URL,
      self::ACTIVITY_DISPLAY => self::ACTIVITY_DISPLAY,
    );
  }

  public function hash() {
    $id = ($this->conference instanceof Conference) ? $this->conference->id : $this->conference;
    $season = ($this->season instanceof Season) ? $this->season->id : $this->season;
    return sprintf('%s-%s-%s', $id, $this->activity, $season);
  }
}
