<?php
/**
 * This file is part of TechScore
 *
 * @package scripts
 */

/**
 * Simple serialization of a public display update request
 *
 * @author Dayan Paez
 * @version 2010-10-11
 */
class UpdateRequest {
  public $id;
  public $regatta;
  public $activity;
  public $argument;
  
  /**
   * @var DateTime the time of the request. Leave as null for current timestamp
   */
  public $request_time;

  const FIELDS = "pub_update_request.id, pub_update_request.regatta, pub_update_request.activity, pub_update_request.request_time, pub_update_request.argument";
  const TABLES = "pub_update_request";

  const ACTIVITY_RP = "rp";
  const ACTIVITY_SYNC = "sync";
  const ACTIVITY_SCORE = "score";
  const ACTIVITY_DETAILS = "details";
  const ACTIVITY_SUMMARY = "summary";
  const ACTIVITY_ROTATION = "rotation";

  /**
   * Returns an associative set of the permissible types
   *
   * @return Array type constants as const => const
   */
  public static function getTypes() {
    return array(self::ACTIVITY_RP => self::ACTIVITY_RP,
		 self::ACTIVITY_SYNC => self::ACTIVITY_SYNC,
		 self::ACTIVITY_SCORE => self::ACTIVITY_SCORE,
		 self::ACTIVITY_DETAILS => self::ACTIVITY_DETAILS,
		 self::ACTIVITY_SUMMARY => self::ACTIVITY_SUMMARY,
		 self::ACTIVITY_ROTATION => self::ACTIVITY_ROTATION);
  }

  public function __construct() {
    if ($this->request_time !== null)
      $this->request_time = new DateTime($this->request_time);
  }
}
?>