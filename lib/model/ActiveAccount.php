<?php

/**
 * Filter for accounts with "active" status.
 *
 * @author Dayan Paez
 * @version 2016-03-22
 */
class ActiveAccount extends Account {
  public function db_where() {
    return new DBCond('status', self::STAT_ACTIVE);
  }
}