<?php
/**
 * This file is part of TechScore
 *
 */

/**
 * Simple season object
 *
 * @author Dayan Paez
 * @version 2010-08-24
 * @package regatta
 */
class Season {
  const FALL = "fall";
  const SUMMER = "summer";
  const SPRING = "spring";
  const WINTER = "winter";
  
  protected $date;
  /**
   * @var stdClass simple serialization of season record
   */
  protected $season = null;
  
  /**
   * Creates a new season based on the following date
   *
   * @param DateTime $date the date
   */
  public function __construct(DateTime $date) {
    $this->date = $date;
  }

  public function getTime() { return $this->date; }
  public function getSeason() {
    if ($this->season === null) {
      $q = sprintf('select * from season where start_date <= "%1$s" and end_date >= "%1$s"',
		   $this->date->format('Y-m-d'));
      $r = Preferences::query($q);
      $this->season = $r->fetch_object();
    }
    return $this->season->season;
  }
  /**
   * The year is based on when the season ENDS, and not when it begins
   */
  public function getYear() {
    $this->getSeason();
    return substr($this->season->start_date, 0, 4);
  }
  public function __toString() {
    $v = null;
    switch ($this->getSeason()) {
    case self::FALL:
      $v = "f";
      break;
    case self::WINTER:
      $v = "w";
      break;
    case self::SPRING:
      $v = "s";
      break;
    default:
      $v = "m";
    }
    return sprintf("$v%s", substr($this->getYear(), 2));
  }

  public function fullString() {
    $v = null;
    switch ($this->getSeason()) {
    case self::FALL:
      $v = "Fall";
      break;
    case self::WINTER:
      $v = "Winter";
      break;
    case self::SPRING:
      $v = "Spring";
      break;
    default:
      $v = "Summer";
    }
    return sprintf("$v %s", substr($this->getYear(), 2));
  }

  /**
   * Returns a list of week numbers in this season. Note that weeks go
   * Monday through Sunday.
   *
   * @return Array:int the week number in the year
   */
  public function getWeeks() {
    $this->getSeason();
    $weeks = array();
    for ($i = $this->season->start_date->format('W');
	 $i < $this->season->end_date->format('W');
	 $i++) {
      $weeks[] = $i;
    }
    return $weeks;
  }

  // ------------------------------------------------------------
  // Regattas
  // ------------------------------------------------------------

  /**
   * Returns all the regattas in this season which are not personal,
   * using the given optional indices to limit the list, like the
   * range function in Python.
   *
   * <ul>
   *   <li>To fetch the first ten: <code>getRegattas(10);</code></li>
   *   <li>To fetch the next ten:  <code>getRegattas(10, 20);</code><li>
   * </ul>
   *
   * @param int $start the start index (inclusive)
   * @param int $end   the end index (exclusive)
   * @return Array<RegattaSummary>
   * @throws InvalidArgumentException if one of the parameters is wrong
   */
  public function getRegattas($start = null, $end = null) {
    $limit = "";
    if ($start === null)
      $limit = "";
    else {
      $start = (int)$start;
      if ($start < 0)
	throw new InvalidArgumentException("Start index ($start) must be greater than zero.");
    
      if ($end === null)
	$limit = "limit $start";
      elseif ((int)$end < $start)
	throw new InvalidArgumentException("End index ($end) must be greater than start ($start).");
      else {
	$range = (int)$end - $start;
	$limit = "limit $start, $range";
      }
    }

    $this->getSeason();
    // Setup the query
    $q = sprintf('select %s from %s ' .
		 'where start_time >= "%s" and start_time < "%s" ' .
		 'and regatta.id not in (select regatta from temp_regatta) ' .
		 'and regatta.type <> "personal" ' .
		 'order by start_time desc %s',
		 RegattaSummary::FIELDS,
		 RegattaSummary::TABLES,
		 $this->season->start_date,
		 $this->season->end_date,
		 $limit);
    $q = Preferences::query($q);
    $list = array();
    while ($obj = $q->fetch_object("RegattaSummary"))
      $list[] = $obj;
    return $list;
  }

  /**
   * Get a list of regattas in this season in which the given
   * school participated. This is a convenience method.
   *
   * @param School $school the school whose participation to verify
   * @return Array:RegattaSummary
   */
  public function getParticipation(School $school) {
    $this->getSeason();
    
    $q = sprintf('select %s from %s where id in (select distinct regatta from team where school = "%s")'.
		 ' and start_time >= "%s" and start_time <= "%s"',
		 RegattaSummary::FIELDS, RegattaSummary::TABLES,
		 $school->id, $this->season->start_date, $this->season->end_date);
    $res = Preferences::query($q);
    $list = array();
    while ($obj = $res->fetch_object("RegattaSummary"))
      $list[] = $obj;
    return $list;
  }

  /**
   * Parses the given season into a season object. The string should
   * have the form '[fswm][0-9]{2}'
   *
   * @param String $text the string to parse
   * @return Season|null the season object or null
   */
  public static function parse($text) {
    // Check first character for allowable type
    $text = strtolower($text);
    if (!in_array($text[0], array("f", "s", "w", "m")))
      return null;

    $s = null;
    switch ($text[0]) {
    case "f": $s = "fall";   break;
    case "s": $s = "spring"; break;
    case "m": $s = "summer"; break;
    case "w": $s = "winter"; break;
    }
    $y = substr($text, 1);
    if (!is_numeric($y)) return null;

    $y = (int)$y;
    $y += ($y < 90) ? 2000 : 1900;

    // fetch the correct start_date for this season
    $q = sprintf('select start_date from season where season = "%s" and ' .
		 '(year(start_date) = "%s") limit 1',
		 $s, $y);
    $r = Preferences::query($q);
    if ($r->num_rows == 0)
      return null;
    $r = $r->fetch_object();
    return new Season(new DateTime($r->start_date));
  }
}
?>
