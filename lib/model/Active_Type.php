<?php
/*
 * This file is part of Techscore
 */



/**
 * Active type: different per installation
 *
 * @author Dayan Paez
 * @version 2012-11-05
 */
class Active_Type extends Type {
  public function db_where() {
    return new DBCond('inactive', null);
  }
}
