<?php
namespace utils;
use \Permission;

/*
 * The structure for the non-scoring panes.
 *
 * @author Dayan Paez
 * @created 2015-03-29
 */
return array(
  'AccountPane' => array(
    RouteManager::NAME => "My Account",
    RouteManager::PATH => 'users',
    RouteManager::URLS => array('account'),
    RouteManager::PERMISSIONS => array()
  ),

  'AllAmerican' => array(
    RouteManager::NAME => "All-American",
    RouteManager::PATH => 'users/reports',
    RouteManager::URLS => array('aa', 'all-american'),
    RouteManager::PERMISSIONS => array(Permission::DOWNLOAD_AA_REPORT, Permission::EDIT_AA_REPORT)
  ),

  'CompareHeadToHead' => array(
    RouteManager::NAME => "Head to head",
    RouteManager::PATH => 'users/reports',
    RouteManager::URLS => array('compare-sailors', 'compare-head-to-head', 'compare-head-head', 'head-to-head'),
    RouteManager::PERMISSIONS => array(Permission::USE_HEAD_TO_HEAD_REPORT)
  ),

  'CompareSailorsByRace' => array(
    RouteManager::NAME => "Compare by race",
    RouteManager::PATH => 'users/reports',
    RouteManager::URLS => array('compare-by-race'),
    RouteManager::PERMISSIONS => array(Permission::USE_HEAD_TO_HEAD_REPORT)
  ),

  'EULAPane' => array(
    RouteManager::NAME => "Sign agreement",
    RouteManager::PATH => 'users',
    RouteManager::URLS => array('license'),
    RouteManager::PERMISSIONS => array()
  ),

  'HelpPost' => array(
    RouteManager::NAME => "Help",
    RouteManager::PATH => 'users',
    RouteManager::URLS => array('help'),
    RouteManager::PERMISSIONS => array()
  ),

  'HomePane' => array(
    RouteManager::NAME => "Home",
    RouteManager::PATH => 'users',
    RouteManager::URLS => array('', 'home'),
    RouteManager::PERMISSIONS => array()
  ),

  'LoginPage' => array(
    RouteManager::NAME => "Login",
    RouteManager::PATH => 'users',
    RouteManager::URLS => array('login', 'logout'),
    RouteManager::PERMISSIONS => array()
  ),

  'MembershipReport' => array(
    RouteManager::NAME => "School participation",
    RouteManager::PATH => 'users/reports',
    RouteManager::URLS => array('membership'),
    RouteManager::PERMISSIONS => array(Permission::USE_MEMBERSHIP_REPORT)
  ),

  'MessagePane' => array(
    RouteManager::NAME => "Inbox",
    RouteManager::PATH => 'users',
    RouteManager::URLS => array('inbox'),
    RouteManager::PERMISSIONS => array()
  ),

  'NewRegattaPane' => array(
    RouteManager::NAME => "New regatta",
    RouteManager::PATH => 'users',
    RouteManager::URLS => array('create'),
    RouteManager::PERMISSIONS => array(Permission::CREATE_REGATTA)
  ),

  'RegisterPane' => array(
    RouteManager::NAME => "Register",
    RouteManager::PATH => 'users',
    RouteManager::URLS => array('register'),
    RouteManager::PERMISSIONS => array()
  ),

  'LogoPane' => array(
    RouteManager::NAME => "Logo",
    RouteManager::PATH => 'users',
    RouteManager::URLS => array('logo.png'),
    RouteManager::PERMISSIONS => array()
  ),

  'PasswordRecoveryPane' => array(
    RouteManager::NAME => "Password Recovery",
    RouteManager::PATH => 'users',
    RouteManager::URLS => array('password-recover'),
    RouteManager::PERMISSIONS => array()
  ),

  'SchoolParticipationReportPane' => array(
    RouteManager::NAME => "Team record",
    RouteManager::PATH => 'users/reports',
    RouteManager::URLS => array('team-participation'),
    RouteManager::PERMISSIONS => array(Permission::USE_TEAM_RECORD_REPORT)
  ),

  'SearchSailor' => array(
    RouteManager::NAME => "Search sailors",
    RouteManager::PATH => 'users',
    RouteManager::URLS => array('search'),
    RouteManager::PERMISSIONS => array()
  ),

  'UserArchivePane' => array(
    RouteManager::NAME => "All regattas",
    RouteManager::PATH => 'users',
    RouteManager::URLS => array('archive'),
    RouteManager::PERMISSIONS => array(Permission::EDIT_REGATTA, Permission::PARTICIPATE_IN_REGATTA)
  ),

  'UserSeasonPane' => array(
    RouteManager::NAME => "Season summary",
    RouteManager::PATH => 'users',
    RouteManager::URLS => array('season'),
    RouteManager::PERMISSIONS => array(Permission::EDIT_REGATTA, Permission::PARTICIPATE_IN_REGATTA)
  ),

  'AccountsPane' => array(
    RouteManager::NAME => "All users",
    RouteManager::PATH => 'users/admin',
    RouteManager::URLS => array('users', 'accounts'),
    RouteManager::PERMISSIONS => array(Permission::EDIT_USERS),
  ),

  'BillingReport' => array(
    RouteManager::NAME => "Billing report",
    RouteManager::PATH => 'users/reports',
    RouteManager::URLS => array('billing'),
    RouteManager::PERMISSIONS => array(Permission::USE_BILLING_REPORT)
  ),

  'BoatManagement' => array(
    RouteManager::NAME => "Boats",
    RouteManager::PATH => 'users/admin',
    RouteManager::URLS => array('boats', 'boat'),
    RouteManager::PERMISSIONS => array(Permission::EDIT_BOATS)
  ),

  'EmailTemplateManagement' => array(
    RouteManager::NAME => "Email templates",
    RouteManager::PATH => 'users/admin',
    RouteManager::URLS => array('email-templates', 'email-template'),
    RouteManager::PERMISSIONS => array(Permission::EDIT_EMAIL_TEMPLATES)
  ),

  'LoggedInUsers' => array(
    RouteManager::NAME => "Logged-in",
    RouteManager::PATH => 'users/admin',
    RouteManager::URLS => array('logged-in', 'active'),
    RouteManager::PERMISSIONS => array(Permission::EDIT_USERS)
  ),

  'MailingListManagement' => array(
    RouteManager::NAME => "Mailing lists",
    RouteManager::PATH => 'users/admin',
    RouteManager::URLS => array('lists', 'mailing'),
    RouteManager::PERMISSIONS => array(Permission::EDIT_MAILING_LISTS)
  ),

  'OrganizationConfiguration' => array(
    RouteManager::NAME => "Organization",
    RouteManager::PATH => 'users/admin',
    RouteManager::URLS => array('org'),
    RouteManager::PERMISSIONS => array(Permission::EDIT_ORGANIZATION)
  ),

  'PendingAccountsPane' => array(
    RouteManager::NAME => "Pending users",
    RouteManager::PATH => 'users/admin',
    RouteManager::URLS => array('pending'),
    RouteManager::PERMISSIONS => array(Permission::EDIT_USERS)
  ),

  'PublicFilesManagement' => array(
    RouteManager::NAME => "Files",
    RouteManager::PATH => 'users/admin',
    RouteManager::URLS => array('files', 'file'),
    RouteManager::PERMISSIONS => array(Permission::EDIT_PUBLIC_FILES)
  ),

  'RegattaTypeManagement' => array(
    RouteManager::NAME => "Regatta types",
    RouteManager::PATH => 'users/admin',
    RouteManager::URLS => array('types', 'type'),
    RouteManager::PERMISSIONS => array(Permission::EDIT_REGATTA_TYPES)
  ),

  'RoleManagementPane' => array(
    RouteManager::NAME => "Roles",
    RouteManager::PATH => 'users/admin',
    RouteManager::URLS => array('roles'),
    RouteManager::PERMISSIONS => array(Permission::EDIT_PERMISSIONS)
  ),

  'SeasonManagement' => array(
    RouteManager::NAME => "Seasons",
    RouteManager::PATH => 'users/admin',
    RouteManager::URLS => array('seasons'),
    RouteManager::PERMISSIONS => array(Permission::EDIT_SEASONS)
  ),

  'SendMessage' => array(
    RouteManager::NAME => "Send message",
    RouteManager::PATH => 'users/admin',
    RouteManager::URLS => array('send-message', 'send-messages', 'send-email', 'send-emails'),
    RouteManager::PERMISSIONS => array(Permission::SEND_MESSAGE)
  ),

  'SocialSettingsManagement' => array(
    RouteManager::NAME => "Social settings",
    RouteManager::PATH => 'users/admin',
    RouteManager::URLS => array('social'),
    RouteManager::PERMISSIONS => array(Permission::EDIT_PUBLIC_FILES)
  ),

  'DatabaseSyncManagement' => array(
    RouteManager::NAME => "Database sync",
    RouteManager::PATH => 'users/admin',
    RouteManager::URLS => array('sync'),
    RouteManager::PERMISSIONS => array(Permission::SYNC_DATABASE)
  ),

  'QueuedUpdates' => array(
    RouteManager::NAME => "Pending updates",
    RouteManager::PATH => 'users/admin',
    RouteManager::URLS => array('updates', 'queue'),
    RouteManager::PERMISSIONS => array(Permission::VIEW_PENDING_UPDATES)
  ),

  'SponsorsManagement' => array(
    RouteManager::NAME => "Sponsors",
    RouteManager::PATH => 'users/admin',
    RouteManager::URLS => array('sponsor', 'sponsors'),
    RouteManager::PERMISSIONS => array(Permission::EDIT_SPONSORS)
  ),

  'TeamRaceOrderManagement' => array(
    RouteManager::NAME => "Team race orders",
    RouteManager::PATH => 'users/admin',
    RouteManager::URLS => array('race-orders', 'race-order'),
    RouteManager::PERMISSIONS => array(Permission::EDIT_TR_TEMPLATES)
  ),

  'TextManagement' => array(
    RouteManager::NAME => "Text settings",
    RouteManager::PATH => 'users/admin',
    RouteManager::URLS => array('text'),
    RouteManager::PERMISSIONS => array(Permission::EDIT_PUBLIC_FILES)
  ),

  'EditorParser' => array(
    RouteManager::NAME => "Text Parser",
    RouteManager::PATH => 'users',
    RouteManager::URLS => array('parse', 'parser'),
    RouteManager::PERMISSIONS => array()
  ),

  'VenueManagement' => array(
    RouteManager::NAME => "Venues",
    RouteManager::PATH => 'users/admin',
    RouteManager::URLS => array('venues', 'venue'),
    RouteManager::PERMISSIONS => array(Permission::EDIT_VENUES)
  ),

  'GlobalSettings' => array(
    RouteManager::NAME => "Global conf.",
    RouteManager::PATH => 'users/super',
    RouteManager::URLS => array('conf'),
    RouteManager::PERMISSIONS => array(Permission::EDIT_GLOBAL_CONF)
  ),

  'PermissionManagement' => array(
    RouteManager::NAME => "Permissions",
    RouteManager::PATH => 'users/admin',
    RouteManager::URLS => array('permissions'),
    RouteManager::PERMISSIONS => array(Permission::DEFINE_PERMISSIONS)
  ),

  'EditLogoPane' => array(
    RouteManager::NAME => "School logo",
    RouteManager::PATH => 'prefs',
    RouteManager::URLS => array('prefs/:school/logo', 'prefs/:school/burgee'),
    RouteManager::PERMISSIONS => array(Permission::EDIT_SCHOOL_LOGO)
  ),

  'SailorMergePane' => array(
    RouteManager::NAME => "Sailors",
    RouteManager::PATH => 'prefs',
    RouteManager::URLS => array('prefs/:school/sailor', 'prefs/:school/sailors'),
    RouteManager::PERMISSIONS => array(Permission::EDIT_UNREGISTERED_SAILORS)
  ),

  'TeamNamePrefsPane' => array(
    RouteManager::NAME => "Team names",
    RouteManager::PATH => 'prefs',
    RouteManager::URLS => array('prefs/:school/team', 'prefs/:school/teams'),
    RouteManager::PERMISSIONS => array(Permission::EDIT_TEAM_NAMES)
  ),

  'PrefsHomePane' => array(
    RouteManager::NAME => "Instructions",
    RouteManager::PATH => 'prefs',
    RouteManager::URLS => array('prefs/:school/home', 'prefs/:school', 'prefs/:school/'),
    RouteManager::PERMISSIONS => array(
      Permission::EDIT_SCHOOL_LOGO,
      Permission::EDIT_UNREGISTERED_SAILORS,
      Permission::EDIT_TEAM_NAMES,
    )
  ),

);