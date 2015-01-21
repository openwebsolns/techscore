<?php
/*
 * This file is part of TechScore
 *
 * @version 2.0
 * @package scripts
 */

/**
 * Temporary table
 *
 * @author Dayan Paez
 * @version 2014-11-18
 */
class TSNewSchema extends DBObject {
  public function db_name() { return '_schema_new_'; }
  protected function db_order() { return array('id' => true); }
}
?>