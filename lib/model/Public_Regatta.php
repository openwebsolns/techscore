<?php
/*
 * This file is part of Techscore
 */



/**
 * A non-private regatta: a convenience handle
 *
 * @author Dayan Paez
 * @version 2012-10-26
 */
class Public_Regatta extends Regatta {
  public function db_where() {
    return new DBBool(array(new DBCond('private', null),
                            parent::db_where()));
  }
}
