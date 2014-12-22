<?php
/*
 * This file is part of Techscore
 */



/**
 * Link between Permission and Role
 *
 * @author Dayan Paez
 * @version 2013-07-17
 */
class Role_Permission extends DBObject {
  protected $role;
  protected $permission;

  public function db_type($field) {
    switch ($field) {
    case 'role': return DB::T(DB::ROLE);
    case 'permission': return DB::T(DB::PERMISSION);
    default: return parent::db_type($field);
    }
  }
}
