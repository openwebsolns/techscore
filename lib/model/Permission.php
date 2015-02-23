<?php
/*
 * This file is part of Techscore
 */



/**
 * A permission line, used to regulate access to areas of the site
 *
 * @author Dayan Paez
 * @version 2013-07-17
 */
class Permission extends DBObject {
  public $title;
  public $category;
  public $description;
  protected function db_cache() { return true; }
  protected function db_order() { return array('category'=>true, 'title'=>true); }
  public function __toString() { return $this->title; }

  /**
   * Fetch a list of roles that have this permission explicitly
   *
   * @return Array:Role the list of roles
   */
  public function getRoles() {
    return DB::getAll(
      DB::T(DB::ROLE),
      new DBCondIn(
        'id',
        DB::prepGetAll(
          DB::T(DB::ROLE_PERMISSION),
          new DBCond('permission', $this),
          array('role')
        )));
  }

  /**
   * Gets the permission specified by the given ID
   *
   * @param Const $id the ID of the permission
   * @return Permission|null
   */
  public static function g($id) {
    return DB::get(DB::T(DB::PERMISSION), $id);
  }

  // List of permissions. There should be a corresponding entry in the
  // database with ID matching that of the constant below. Permissions
  // that don't exist in the database are implied reserved for super
  // admins.
  const EDIT_ANNOUNCEMENTS = 'edit_announcements';
  const EDIT_BOATS = 'edit_boats';
  const EDIT_EMAIL_TEMPLATES = 'edit_email_templates';
  const EDIT_MAILING_LISTS = 'edit_mailing_lists';
  const EDIT_ORGANIZATION = 'edit_organization';
  const EDIT_PERMISSIONS = 'edit_permissions';
  const EDIT_PUBLIC_FILES = 'edit_public_files';
  const EDIT_REGATTA_TYPES = 'edit_regatta_types';
  const EDIT_SEASONS = 'edit_seasons';
  const EDIT_SPONSORS = 'edit_sponsors';
  const EDIT_TR_TEMPLATES = 'edit_tr_templates';
  const EDIT_USERS = 'edit_users';
  const EDIT_VENUES = 'edit_venues';
  const EDIT_WELCOME = 'edit_welcome';
  const SEND_MESSAGE = 'send_message';
  const SYNC_DATABASE = 'sync_database';
  const VIEW_PENDING_UPDATES = 'view_pending_updates';
  const DEFINE_PERMISSIONS = 'define_permission';

  const DOWNLOAD_AA_REPORT = 'download_aa_report';
  const EDIT_AA_REPORT = 'edit_aa_report';
  const USE_HEAD_TO_HEAD_REPORT = 'use_head_to_head_report';
  const USE_TEAM_RECORD_REPORT = 'use_team_record_report';
  const USE_MEMBERSHIP_REPORT = 'use_membership_report';
  const USE_BILLING_REPORT = 'use_billing_report';

  const EDIT_REGATTA = 'edit_regatta';
  const FINALIZE_REGATTA = 'finalize_regatta';
  const CREATE_REGATTA = 'create_regatta';
  const DELETE_REGATTA = 'delete_regatta';
  const PARTICIPATE_IN_REGATTA = 'participate_in_regatta';
  const USE_REGATTA_SPONSOR = 'use_regatta_sponsor';

  const EDIT_SCHOOL_LOGO = 'edit_school_logo';
  const EDIT_UNREGISTERED_SAILORS = 'edit_unregistered_sailors';
  const EDIT_TEAM_NAMES = 'edit_team_names';

  const EDIT_GLOBAL_CONF = 'edit_global_conf';
  const USURP_USER = 'usurp_user';

  public static function getPossible() {
    $reflection = new ReflectionClass(DB::T(DB::PERMISSION));
    $list = array();
    foreach ($reflection->getConstants() as $constant => $value) {
      if (self::isAvailable($value))
        $list[$constant] = $value;
    }
    return $list;
  }

  public static function isAvailable($permission_name) {
    switch ($permission_name) {
    case self::USE_REGATTA_SPONSOR:
      return DB::g(STN::REGATTA_SPONSORS) !== null;
    default:
      return true;
    }
  }

  /**
   * Returns map of different categories that exist
   *
   * @return Array list of strings
   */
  public static function getPermissionCategories() {
    $q = DB::prepGetAll(DB::T(DB::PERMISSION), new DBCond('category', null, DBCond::NE), array('category'));
    $q->distinct(true);
    $q->order_by(array('category' => true));
    $res = DB::query($q);
    $list = array();
    foreach ($res->fetch_all() as $r)
      $list[] = $r[0];
    return $list;
  }
}
