<?php
/*
 * This file is part of TechScore
 *
 * @package regatta
 */

/**
 * Message to a user and all that entails
 *
 * @author Dayan Paez
 * @created 2010-03-25
 */
class Message {

  public $id;
  /**
   * Account $account
   */
  public $account;
  public $created;
  public $read_time;
  public $content;
  public $subject;

  const FIELDS = "id, created, read_time, content, subject";
  const TABLES = "message";

  /**
   * Build a message for the given user
   *
   * @param Account $acc the account to which this belongs
   */
  public function __construct(Account $acc) {
    $this->account = $acc;
    $this->created = new DateTime($this->created);
    $this->read_time = ($this->read_time !== null)
      ? new DateTime($this->read_time) : null;
  }

  /**
   * Returns just the content
   *
   * @return String the content
   */
  public function __toString() {
    return $this->content;
  }
}
?>