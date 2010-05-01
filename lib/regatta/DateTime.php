<?php
/* This is a general utility for PHP 5.2 to simulate DateTime in PHP
 * 5.3, with no support for timezones
 *
 * @version 1.0
 * @author Dayan Paez
 * @package regatta
 */

/**
 * Encapsulates a DateTime object
 *
 */
class DateTime2 {

  // Variables
  public $year;
  public $month;
  public $day;
  public $hour;
  public $minute;
  public $second;

  /**
   * Creates a new DateTime object with the default String date and
   * time as accepted by "strtotime". Defaults to "now"
   *
   * @param String $time the date time string: default "now"
   */
  public function __construct($time = "now") {
    $this->setTimestamp(strtotime($time));
  }

  /**
   * Sets the UNIX timestamp for this date
   *
   * @param int $time the timestamp
   */
  public function setTimestamp($time) {
    $this->year   = date('Y', $time);
    $this->month  = date('n', $time);
    $this->day    = date('j', $time);
    $this->hour   = date('H', $time);
    $this->minute = date('i', $time);
    $this->second = date('s', $time);
  }

  /**
   * Returns the UNIX timestamp for the current datetime object
   *
   * @return int the UNIX timestamp
   */
  public function getTimestamp() {
    return strtotime(sprintf('%s-%s-%s %s:%s:%s',
			     $this->year,
			     $this->month,
			     $this->day,
			     $this->hour,
			     $this->minute,
			     $this->second));
  }

  /**
   * Sets the time of day
   *
   * @param int $hour the hour
   * @param int $minute the minute
   * @param int $second the second (defaults to 0)
   */
  public function setTime($hour, $minute, $second = 0) {
    $this->hour = $hour;
    $this->minute = sprintf("%02s", $minute);
  }
}

print sprintf("%02s", 5);
?>