<?php
/*
 * This file is part of Techscore
 */



/**
 * Regatta type, which may be ranked
 *
 * @author Dayan Paez
 * @version 2012-11-05
 */
class Type extends DBObject {
  public $title;
  public $description;
  /**
   * @var int the display rank (lower = more important)
   */
  public $rank;
  public $tweet_summary;
  public $inactive;
  protected $mail_lists;

  public function db_name() { return 'type'; }
  public function db_type($field) {
    if ($field == 'mail_lists')
      return array();
    return parent::db_type($field);
  }
  protected function db_order() { return array('rank'=>true, 'title'=>true); }
  protected function db_cache() { return true; }
  public function __toString() { return $this->title; }
}
