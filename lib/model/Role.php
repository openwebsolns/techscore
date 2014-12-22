<?php
/*
 * This file is part of Techscore
 */



/**
 * A bundle of permission entries, as assigned to an account
 *
 * @author Dayan Paez
 * @version 2013-07-17
 */
class Role extends DBObject {
  public $title;
  public $description;
  public $has_all;
  public $is_default;
  protected function db_cache() { return true; }
  protected function db_order() { return array('title' => true); }
  public function __toString() { return $this->title; }

  /**
   * @var Array:Permission internal cache of permissions
   */
  private $permissions = null;
  
  /**
   * Returns list of Permission objects associated with this role
   *
   * @return Array:Permission
   */
  public function getPermissions() {
    if ($this->permissions === null) {
      if ($this->has_all)
        $this->permissions = DB::getAll(DB::T(DB::PERMISSION));
      else {
        $this->permissions = array();
        foreach (DB::getAll(DB::T(DB::ROLE_PERMISSION), new DBCond('role', $this)) as $link) {
          $this->permissions[] = $link->permission;
        }
      }
    }
    return $this->permissions;
  }

  /**
   * Sets the list of permissions associated with this role
   *
   * @param Array:Permission $persm the list of permissions
   */
  public function setPermissions(Array $perms = array()) {
    DB::removeAll(DB::T(DB::ROLE_PERMISSION), new DBCond('role', $this));
    foreach ($perms as $perm) {
      $link = new Role_Permission();
      $link->role = $this;
      $link->permission = $perm;
      DB::set($link);
    }
    $this->permissions = $perms;
  }

  public function hasPermission(Permission $permission) {
    foreach ($this->getPermissions() as $perm) {
      if ($perm->id == $permission->id)
        return true;
    }
    return false;
  }

  public function removePermission(Permission $permission) {
    DB::removeAll(
      DB::T(DB::ROLE_PERMISSION),
      new DBBool(array(
                   new DBCond('role', $this),
                   new DBCond('permission', $permission))));
    $this->permissions = null;
  }

  /**
   * Adds a specific permission to the set
   *
   * When adding multiple permissions, it is more efficient to use the
   * setPermissions method, rather than calling this method multiple
   * times.
   *
   * @param Permission $permission the permission to add
   * @return boolean true if it was added. False if already present
   * @see setPermissions
   */
  public function addPermission(Permission $permission) {
    if (!$this->hasPermission($permission)) {
      $link = new Role_Permission();
      $link->role = $this;
      $link->permission = $permission;
      DB::set($link);
      $this->permissions[] = $permission;
      return true;
    }
    return false;
  }

  /**
   * Indicate that this role has all permissions
   *
   * This method will set the 'has_all' attribute, AND remove any
   * individual permissions associated with this role, if true
   *
   * @param boolean $flag true to set this role as having all
   */
  public function setHasAll($flag = true) {
    if ($flag !== false) {
      $this->has_all = 1;
      $this->setPermissions();
    }
    else {
      $this->has_all = null;
    }
  }

  /**
   * Get accounts that have this role
   *
   * @return Array:Account the account list
   */
  public function getAccounts() {
    return DB::getAll(DB::T(DB::ACCOUNT), new DBCond('ts_role', $this));
  }

  /**
   * Get all the roles that are defined
   *
   * @param boolean $only_non_admin set to true to exclude has_all
   * @return Array:Role
   */
  public static function getRoles($only_non_admin = false) {
    $cond = null;
    if ($only_non_admin !== false)
      $cond = new DBCond('has_all', null);
    return DB::getAll(DB::T(DB::ROLE), $cond);
  }
}
