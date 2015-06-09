<?php
/*
 * This file is part of Techscore
 */

/**
 * Encapsulates a season, either fall/spring, etc, with a start and
 * end date
 *
 * @author Dayan Paez
 * @version 2012-01-16
 */
class Season extends DBObject implements Publishable {
  const FALL = "fall";
  const SUMMER = "summer";
  const SPRING = "spring";
  const WINTER = "winter";

  public $url;
  public $season;
  protected $start_date;
  protected $end_date;
  protected $sponsor;

  public function db_type($field) {
    switch ($field) {
    case 'start_date':
    case 'end_date':
      return DB::T(DB::NOW);
    case 'sponsor':
      return DB::T(DB::PUB_SPONSOR);
    default:
      return parent::db_type($field);
    }
  }
  protected function db_order() { return array('start_date'=>false); }
  protected function db_cache() { return true; }

  /**
   * Wrapper to be deprecated
   *
   */
  public function getSeason() {
    return $this->season;
  }

  public function getYear() {
    return $this->__get('start_date')->format('Y');
  }

  /**
   * Fetches the first Saturday in the season.
   *
   * The first Saturday is the Saturday AFTER the start of the season,
   * except that for Spring and Fall, these are pre-defined as the
   * "First Saturday in February" and "September", respectively.
   *
   * @return DateTime
   */
  public function getFirstSaturday() {
    $start = $this->__get('start_date');
    if ($this->season == Season::FALL)
      $start = new DateTime(sprintf('%s-09-01', $this->getYear()));
    elseif ($this->season == Season::SPRING)
      $start = new DateTime(sprintf('%s-02-01', $this->getYear()));
    $start->add(new DateInterval(sprintf('P%sDT0H', (6 - $start->format('w')))));
    return $start;
  }

  /**
   * For Fall starting in 2011: f11
   *
   */
  public function __toString() {
    return $this->shortString();
  }

  /**
   * Return short representation, such as "f13"
   *
   * @return String
   */
  public function shortString() {
    return $this->url;
  }

  /**
   * For Fall starting in 2011, return "Fall 2011"
   */
  public function fullString() {
    return sprintf("%s %s", ucfirst((string)$this->season), $this->getYear());
  }

  /**
   * Generates public-facing URL: e.g. /f13/
   *
   */
  public function getURL() {
    return sprintf('/%s/', $this->shortString());
  }

  /**
   * Is this the season for the given date (or right now)?
   *
   * @param DateTime|null $time if given, the time to check, or "now"
   * @return boolean true if so
   */
  public function isCurrent(DateTime $time = null) {
    if ($this->__get('start_date') === null || $this->__get('end_date') === null)
      return false;
    if ($time === null)
      $time = DB::T(DB::NOW);
    return ($time > $this->__get('start_date') && $time < $this->__get('end_date'));
  }

  /**
   * Returns a list of week numbers in this season. Note that weeks go
   * Monday through Sunday.
   *
   * @return Array:int the week number in the year
   */
  public function getWeeks() {
    $weeks = array();
    for ($i = $this->start_date->format('W'); $i < $this->end_date->format('W'); $i++)
      $weeks[] = $i;
    return $weeks;
  }

  // ------------------------------------------------------------
  // Regattas
  // ------------------------------------------------------------

  /**
   * Returns all the regattas in this season which are not personal
   *
   * @param boolean $inc_private true to include private regatta in result
   * @return Array:Regatta
   */
  public function getRegattas($inc_private = false) {
    return DB::getAll(($inc_private !== false) ? DB::T(DB::REGATTA) : DB::T(DB::PUBLIC_REGATTA),
                      new DBBool(array(new DBCond('start_time', $this->start_date, DBCond::GE),
                                       new DBCond('start_time', $this->end_date,   DBCond::LT))));
  }

  /**
   * Fetches the regatta, if any, with given URL
   *
   * @param String $url the URL to fetch
   * @return Regatta|null
   */
  public function getRegattaWithURL($url) {
    $res = DB::getAll(DB::T(DB::PUBLIC_REGATTA),
                      new DBBool(array(new DBCond('start_time', $this->start_date, DBCond::GE),
                                       new DBCond('start_time', $this->end_date,   DBCond::LT),
                                       new DBCond('nick', $url))));
    if (count($res) == 0)
      return null;
    return $res[0];
  }

  /**
   * Get a list of regattas in this season in which the given
   * school participated. This is a convenience method.
   *
   * @param School $school the school whose participation to verify
   * @param boolean $inc_private true to include private regattas
   * @return Array:Regatta
   */
  public function getParticipation(School $school, $inc_private = false) {
    return DB::getAll(($inc_private !== false) ? DB::T(DB::REGATTA) : DB::T(DB::PUBLIC_REGATTA),
                      new DBBool(array(new DBCond('start_time', $this->start_date, DBCond::GE),
                                       new DBCond('start_time', $this->end_date,   DBCond::LT),
                                       new DBCondIn('id', DB::prepGetAll(DB::T(DB::TEAM), new DBCond('school', $school), array('regatta'))))));
  }

  /**
   * Get a list of regattas in this season in which the given
   * sailor participated. This is a convenience method.
   *
   * @param Member $sailor the sailor whose participation to verify
   * @param boolean $inc_private true to include private regattas
   * @return Array:Regatta
   */
  public function getSailorParticipation(Member $sailor, $inc_private = false) {
    return DB::getAll(
      ($inc_private !== false) ? DB::T(DB::REGATTA) : DB::T(DB::PUBLIC_REGATTA),
      new DBBool(
        array(
          new DBCond('start_time', $this->start_date, DBCond::GE),
          new DBCond('start_time', $this->end_date,   DBCond::LT),
          new DBCondIn(
            'id',
            DB::prepGetAll(
              DB::T(DB::TEAM),
              new DBCondIn(
                'id',
                DB::prepGetAll(
                  DB::T(DB::RP_ENTRY),
                  new DBCondIn(
                    'attendee',
                    DB::prepGetAll(
                      DB::T(DB::ATTENDEE),
                      new DBCond('sailor', $sailor),
                      array('id')
                    )
                  ),
                  array('team')
                )
              ),
              array('regatta')
            )
          )
        )
      )
    );
  }

  /**
   * Get a list of regattas in this season in which the given sailor attended.
   *
   * This includes reserves or actual participation.
   *
   * @param Member $sailor the sailor whose participation to verify
   * @param boolean $inc_private true to include private regattas
   * @return Array:Regatta
   */
  public function getSailorAttendance(Member $sailor, $inc_private = false) {
    return DB::getAll(
      ($inc_private !== false) ? DB::T(DB::REGATTA) : DB::T(DB::PUBLIC_REGATTA),
      new DBBool(
        array(
          new DBCond('start_time', $this->start_date, DBCond::GE),
          new DBCond('start_time', $this->end_date,   DBCond::LT),
          new DBCondIn(
            'id',
            DB::prepGetAll(
              DB::T(DB::TEAM),
              new DBCondIn(
                'id',
                DB::prepGetAll(
                  DB::T(DB::ATTENDEE),
                  new DBCond('sailor', $sailor),
                  array('team')
                )
              ),
              array('regatta')
            )
          )
        )
      )
    );
  }

  /**
   * Get a list of regattas in this season in which any school from
   * the given conference participated. This is a convenience method.
   *
   * @param Conference $conference the conference whose participation to verify
   * @param boolean $inc_private true to include private regattas
   * @return Array:Regatta
   * @see getParticipation
   */
  public function getConferenceParticipation(Conference $conference, $inc_private = false) {
    return DB::getAll(($inc_private !== false) ? DB::T(DB::REGATTA) : DB::T(DB::PUBLIC_REGATTA),
                      new DBBool(array(new DBCond('start_time', $this->start_date, DBCond::GE),
                                       new DBCond('start_time', $this->end_date,   DBCond::LT),
                                       new DBCondIn('id', DB::prepGetAll(
                                                      DB::T(DB::TEAM),
                                                      new DBCondIn('school', DB::prepGetAll(
                                                                     DB::T(DB::SCHOOL),
                                                                     new DBCond('conference', $conference),
                                                                     array('id'))),
                                                      array('regatta'))))));
  }

  /**
   * Get list of schools active during this season.
   *
   * A school will be returned so long as it was once found active
   * throughout the season.
   *
   * @param Conference $conference optional conference to limit
   * @return Array:School the list of schools
   */
  public function getSchools(Conference $conference = null) {
    $cond = new DBCondIn('id', DB::prepGetAll(DB::T(DB::SCHOOL_SEASON), new DBCond('season', $this), array('school')));
    if ($conference !== null)
      $cond = new DBBool(array(new DBCond('conference', $conference), $cond));
    return DB::getAll(DB::T(DB::SCHOOL), $cond);
  }

  /**
   * Return the next season if it exists in the database.
   *
   * The "next" season is one of either spring or fall.
   *
   * @return Season|null the season
   * @throws InvalidArgumentException if used with either spring/fall
   */
  public function nextSeason() {
    $next = null;
    $year = $this->__get('start_date');
    if ($year === null)
      throw new InvalidArgumentException("There is no date this season. Thus, no nextSeason!");
    $year = $year->format('y');
    switch ($this->season) {
    case Season::SPRING:
      $next = 'f';
      break;

    case Season::FALL:
      $next = 's';
      $year++;
      break;

    default:
      throw new InvalidArgumentException("Next season only valid for spring and fall.");
    }
    return DB::getSeason(sprintf('%s%02d', $next, $year));
  }

  /**
   * Return the previous season if it exists in the database.
   *
   * The "previous" season is one of either spring or fall.
   *
   * @return Season|null the season
   * @throws InvalidArgumentException if used with either spring/fall
   */
  public function previousSeason() {
    $next = null;
    $year = $this->__get('start_date');
    if ($year === null)
      throw new InvalidArgumentException("There is no date this season. Thus, no previousSeason!");
    $year = $year->format('y');
    switch ($this->season) {
    case Season::SPRING:
      $next = 'f';
      $year--;
      break;

    case Season::FALL:
      $next = 's';
      break;

    default:
      throw new InvalidArgumentException("Next season only valid for spring and fall.");
    }
    return DB::getSeason(sprintf('%s%02d', $next, $year));
  }

  // ------------------------------------------------------------
  // Static methods
  // ------------------------------------------------------------

  /**
   * Fetches all the regattas in all the given seasons
   *
   * @param Array:Season all the seasons to consider
   * @return Array:Regatta
   */
  public static function getRegattasInSeasons(Array $seasons) {
    if (count($seasons) == 0)
      return array();
    $cond = new DBBool(array(), DBBool::mOR);
    foreach ($seasons as $season) {
      $cond->add(new DBBool(array(new DBCond('start_time', $season->start_date, DBCond::GE),
                                  new DBCond('start_time', $season->end_date,   DBCond::LT))));
    }
    return DB::getAll(DB::T(DB::REGATTA), $cond);
  }

  /**
   * Returns the season object, if any, that surrounds the given date.
   *
   * This method replaces the former constructor for Season, for which
   * there was no guarantee of a season existing.
   *
   * @param DateTime $date the date whose season to get
   * @return Season|null the season for $date
   */
  public static function forDate(DateTime $date) {
    $time = clone $date;
    $time->setTime(0, 0, 0);
    $res = DB::getAll(DB::T(DB::SEASON), new DBBool(array(new DBCond('start_date', $time, DBCond::LE),
                                                    new DBCond('end_date', $time, DBCond::GE))));
    $r = (count($res) == 0) ? null : $res[0];
    unset($res);
    return $r;
  }

  /**
   * Returns a list of the seasons for which there are public
   * regattas, ordered in descending chronological order.
   *
   * @return Array:Season the list
   */
  public static function getActive() {
    $cond = new DBBool(array(new DBCond('private', null),
                             new DBCond('dt_status',Regatta::STAT_SCHEDULED, DBCond::NE)));
    return DB::getAll(DB::T(DB::SEASON), new DBCondIn('id', DB::prepGetAll(DB::T(DB::REGATTA), $cond, array('dt_season'))));
  }

  /**
   * Creates appropriate shortString or URL for given Season object.
   *
   * Does not assign the url, but uses the object's start_date and
   * reported "season" to determine the appropriate url, such as f11
   * for "Fall 2011"
   *
   * @param Season $obj the object whose ID to create
   * @return String the suitable url
   * @throws InvalidArgumentException if attributes missing
   */
  public static function createUrl(Season $obj) {
    if ($obj->start_date === null || $obj->season === null)
      throw new InvalidArgumentException("Missing either start_date or season.");
    switch ($obj->season) {
    case Season::SPRING: $text = 's'; break;
    case Season::SUMMER: $text = 'm'; break;
    case Season::FALL:   $text = 'f'; break;
    case Season::WINTER: $text = 'w'; break;
    default:
      throw new InvalidArgumentException("Invalid season type.");
    }
    return $text . $obj->start_date->format('y');
  }
}
