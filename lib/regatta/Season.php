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
      $con = Preferences::getConnection();
      $q = sprintf('select * from season where start_date <= "%1$s" and end_date >= "%1$s"',
		   $this->date->format('Y-m-d'));
      $r = $con->query($q);
      $this->season = $r->fetch_object();
    }
    return $this->season->season;
  }
  public function getYear() { return $this->date->format('Y'); }
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
    $con = Preferences::getConnection();
    
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
    $q = $con->query($q);
    $list = array();
    while ($obj = $q->fetch_object("RegattaSummary"))
      $list[] = $obj;
    return $list;
  }
}
?>