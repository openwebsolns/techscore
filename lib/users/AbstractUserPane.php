<?php
/*
 * This file is part of TechScore
 *
 * @package users
 */

require_once('xml5/TS.php');

class PaneException extends Exception {}

/**
 * This is the parent class of all user's editing panes. It insures a
 * function called getHTML() exists which only populates a page if so
 * necessary. This page is modeled after tscore/AbstractPane
 *
 * @author Dayan Paez
 * @version   2010-04-12
 */
abstract class AbstractUserPane {

  protected $USER;
  protected $PAGE;
  protected $SCHOOL;
  protected $title;

  /**
   * Creates a new User editing pane with the given title
   *
   * @param String $title the title of the page
   * @param Account $user the user to whom this applies
   */
  public function __construct($title, Account $user = null) {
    $this->title = (string)$title;
    $this->USER  = $user;
    if ($this->USER !== null)
      $this->SCHOOL = $this->USER->getFirstSchool();
  }

  /**
   * Retrieves the HTML code for this pane
   *
   * @param Array $args the arguments to consider
   * @return String the HTML code
   */
  public function getHTML(Array $args) {
    require_once('xml5/TScorePage.php');
    $this->PAGE = new TScorePage($this->title, $this->USER);

    if ($this->USER === null) {
      // ------------------------------------------------------------
      // menu
      $this->PAGE->addMenu(new XDiv(array('class'=>'menu'),
                                    array(new XH4("Useful Links"),
                                          $m = new XUl(array(),
                                                       array(new XLi(new XA(".", "Sign-in")))))));
      if (DB::g(STN::ALLOW_REGISTER) !== null)
        $m->add(new XLi(new XA("register", "Register")));
      if (($n = DB::g(STN::ORG_NAME)) !== null &&
          ($u = DB::g(STN::ORG_URL)) !== null)
        $m->add(new XLi(new XA($u, sprintf("%s Website", $n))));
      $m->add(new XLi(new XA("http://techscore.sourceforge.net", "Offline TechScore")));

      $this->PAGE->addContent(new XPageTitle($this->title));
      $this->fillHTML($args);
      $this->PAGE->printXML();
      return;
    }

    // ------------------------------------------------------------
    // menu

    $menus = array(
      'Regattas' => array(
        'HomePane',
        'UserSeasonPane',
        'UserArchivePane',
        'NewRegattaPane',
        'GlobalSettings',
      ),

      'My School' => array(
        'PrefsHomePane',
        'EditLogoPane',
        'TeamNamePrefsPane',
        'SailorMergePane',
      ),

      'Reports' => array(
        'AllAmerican',
        'CompareHeadToHead',
        'SchoolParticipationReportPane',
        'MembershipReport',
        'BillingReport',
      ),

      'Messages' => array(
        'MessagePane',
        'SendMessage',
        'EmailTemplateManagement',
      ),

      'Admin' => array(
        'VenueManagement',
        'BoatManagement',
        'RegattaTypeManagement',
        'MailingListManagement',
        'TeamRaceOrderManagement',
        'SeasonManagement',
      ),

      'Users' => array(
        'PendingAccountsPane',
        'AccountsPane',
        'LoggedInUsers',
        'RoleManagementPane',
      ),

      'Text' => array(
      ),

      'Configure' => array(
        'SocialSettingsManagement',
        'SponsorsManagement',
        'PublicFilesManagement',
        'OrganizationConfiguration',
      ),
    );

    foreach ($menus as $title => $items) {
      $list = array();
      foreach ($items as $pane) {
        $li = new XLi(new XA(WS::link('/' . $this->pane_url($pane)), $this->pane_title($pane)));
        if ($this->isPermitted($pane))
          $list[] = $li;
      }
      
      // Special case: Text menu
      if ($title == 'Text' && $this->isPermitted('TextManagement')) {
        foreach (Text_Entry::getSections() as $sec => $tname)
          $list[] = new XLi(new XA(WS::link($this->pane_url('TextManagement') . '/' . $sec), $tname));
      }

      if (count($list) > 0)
        $this->PAGE->addMenu(new XDiv(array('class'=>'menu'),
                                      array(new XH4($title),
                                            new XUl(array(), $list))));
    }

    $this->PAGE->addContent(new XPageTitle($this->title));
    $this->fillHTML($args);
    $this->PAGE->printXML();
  }

  /**
   * Redirects to the given URL, or back to the referer
   *
   * @param String $url the url to go
   */
  protected function redirect($url = null, Array $args = array()) {
    if ($url !== null)
      WS::go(WS::link('/'.$url, $args));
    WS::goBack('/');
  }

  /**
   * Creates a link to this pane with optional GET arguments
   *
   * @param Array $args the optional list of parameters
   * @return String the link
   */
  protected function link(Array $args = array()) {
    return WS::link('/' . $this->pane_url(), $args);
  }

  /**
   * Creates a new form HTML element using the page_name attribute
   *
   * @param Const $method XForm::POST or XForm::GET
   * @return XForm
   */
  protected function createForm($method = XForm::POST) {
    return new XForm('/'.$this->pane_url(), $method);
  }

  protected function createFileForm() {
    return new XFileForm('/'.$this->pane_url());
  }

  /**
   * Wrapper around process method to be used by web clients. Wraps
   * the SoterExceptions as announcements.
   *
   * @param Array $args the parameters to process
   * @return Array parameters to pass to the next page
   */
  public function processPOST(Array $args) {
    try {
      return $this->process($args);
    } catch (SoterException $e) {
      Session::pa(new PA($e->getMessage(), PA::E));
      return array();
    }
  }

  /**
   * Sends e-mail to user to verify account.
   *
   * E-mail will not be sent if no e-mail template exists
   *
   * @param Account $account the account to notify
   * @return true if template exists, and message sent
   */
  protected function sendRegistrationEmail(Account $acc) {
    if (DB::g(STN::MAIL_REGISTER_USER) === null)
      return false;

    $body = DB::keywordReplace(DB::g(STN::MAIL_REGISTER_USER), $acc, $acc->getFirstSchool());
    $body = str_replace('{BODY}', sprintf('%sregister/%s', WS::alink('/'), DB::getHash($acc)), $body);
    return DB::mail($acc->id,
		    sprintf("[%s] New account request", DB::g(STN::APP_NAME)),
		    $body);
  }

  /**
   * Fill this page's content
   *
   * @param Array $args the arguments to process
   */
  abstract protected function fillHTML(Array $args);

  /**
   * Processes the requests made to this page (usually from this page)
   *
   * @param Array $args the arguments to process
   * @return Array the modified arguments
   */
  abstract public function process(Array $args);

  // ------------------------------------------------------------
  // Static methods
  // ------------------------------------------------------------

  /**
   * Fetches the pane based on URL
   *
   * @param Array $uri the URL tokens, in order
   * @param Account $u the responsible account
   * @return AbstractUserPane the specified pane
   * @throws PaneException if malformed request
   * @throws PermissionException if insufficient permissions
   */
  public static function getPane(Array $uri, Account $u) {
    $base = array_shift($uri);
    // ------------------------------------------------------------
    // Preferences
    // ------------------------------------------------------------
    if ($base == 'prefs') {
      // school follows
      if (count($uri) == 0)
        throw new PaneException("No school provided.");
      if (($school = DB::getSchool(array_shift($uri))) === null)
        throw new PaneException("Invalid school requested.");

      $arg = (count($uri) == 0) ? 'home' : array_shift($uri);
      $path = 'prefs/%s/' . $arg;
      $pane = self::pane_from_url($path);
      if ($pane === null)
        throw new PaneException("Invalid preferences page requested.");

      require_once(self::pane_path($pane) . '/' . $pane . '.php');
      $obj = new $pane($u, $school);
      if (!$obj->isPermitted())
        throw new PermissionException("No access for preferences page requested.");
      return $obj;
    }
    // ------------------------------------------------------------
    // Text?
    // ------------------------------------------------------------
    if ($base == 'text') {
      if (count($uri) != 1)
        throw new PaneException("Invalid or missing text section to edit.");
      $secs = Text_Entry::getSections();
      if (!isset($secs[$uri[0]]))
        throw new PaneException(sprintf("Invalid text section: %s.", $uri[0]));

      require_once('users/admin/TextManagement.php');
      $obj = new TextManagement($u, $uri[0]);
      if (!$obj->isPermitted())
        throw new PermissionException("No access to edit requested text entry.");
      return $obj;
    }

    // ------------------------------------------------------------
    // Handle the rest
    // ------------------------------------------------------------
    $pane = self::pane_from_url($base);
    if ($pane === null)
      throw new PaneException(sprintf("Invalid page requested (%s).", $base));
    require_once(self::pane_path($pane) . '/' . $pane . '.php');
    $obj = new $pane($u);
    if (!$obj->isPermitted())
      throw new PermissionException("No access to requested page.");
    return $obj;
  }

  // ------------------------------------------------------------
  // Routing setup
  // ------------------------------------------------------------

  /**
   * Returns the name of the (first) pane for given URL
   *
   * @param String the URL to match
   * @return String|null the classname of matching pane
   */
  public static function pane_from_url($url) {
    foreach (self::$ROUTES as $classname => $obj) {
      if (in_array($url, $obj[self::R_URLS]))
        return $classname;
    }
    return null;
  }

  /**
   * Returns the canonical URL for pane identified by classname
   *
   * @param String $classname leave null to use current class
   * @return String the URL (sans leading /)
   * @throws InvalidArgumentException if unknown classname provided
   */
  public function pane_url($classname = null) {
    if ($classname === null)
      $classname = get_class($this);
    if (!isset(self::$ROUTES[$classname]))
      throw new InvalidArgumentException("No routes exist for class " . $classname);

    // Treat preferences URLs different
    if (in_array($classname, array('PrefsHomePane', 'EditLogoPane', 'SailorMergePane', 'TeamNamePrefsPane')))
      return sprintf(self::$ROUTES[$classname][self::R_URLS][0], $this->SCHOOL->id);
    return self::$ROUTES[$classname][self::R_URLS][0];
  }

  /**
   * Returns the label to use for pane identified by classname
   *
   * @param String $classname leave null to use current class
   * @return String
   * @throws InvalidArgumentException if unknown classname provided
   */
  public function pane_title($classname = null) {
    if ($classname === null)
      $classname = get_class($this);
    if (!isset(self::$ROUTES[$classname]))
      throw new InvalidArgumentException("No routes exist for class " . $classname);
    return self::$ROUTES[$classname][self::R_NAME];
  }

  /**
   * Returns the path to the classname in question
   *
   * @param String $classname leave null to use current class
   * @return String
   * @throws InvalidArgumentException if unknown classname provided
   */
  public static function pane_path($classname) {
    if ($classname === null)
      $classname = get_class($this);
    if (!isset(self::$ROUTES[$classname]))
      throw new InvalidArgumentException("No routes exist for class " . $classname);
    return self::$ROUTES[$classname][self::R_PATH];
  }

  /**
   * Does this pane's user have access?
   *
   * @param String $classname leave null to use current class
   * @return boolean true if access to any of pane's list of permissions
   * @throws InvalidArgumentException if unknown classname provided
   */
  public function isPermitted($classname = null) {
    if ($classname === null)
      $classname = get_class($this);
    if (!isset(self::$ROUTES[$classname]))
      throw new InvalidArgumentException("No routes exist for class " . $classname);

    if (count(self::$ROUTES[$classname][self::R_PERM]) == 0)
      return true;

    return $this->USER->canAny(self::$ROUTES[$classname][self::R_PERM]);
  }

  const R_URLS = 'url';
  const R_NAME = 'name';
  const R_PERM = 'perm';
  const R_PATH = 'path';

  public static $ROUTES = array(
    'AccountPane' => array(
      self::R_NAME => "My Account",
      self::R_PATH => 'users',
      self::R_URLS => array('account'),
      self::R_PERM => array()
    ),

    'AllAmerican' => array(
      self::R_NAME => "All-American",
      self::R_PATH => 'users/reports',
      self::R_URLS => array('aa', 'all-american'),
      self::R_PERM => array(Permission::DOWNLOAD_AA_REPORT, Permission::EDIT_AA_REPORT)
    ),

    'CompareHeadToHead' => array(
      self::R_NAME => "Head to head",
      self::R_PATH => 'users/reports',
      self::R_URLS => array('compare-sailors', 'compare-head-to-head', 'compare-head-head', 'head-to-head'),
      self::R_PERM => array(Permission::USE_HEAD_TO_HEAD_REPORT)
    ),

    'CompareSailorsByRace' => array(
      self::R_NAME => "Compare by race",
      self::R_PATH => 'users/reports',
      self::R_URLS => array('compare-by-race'),
      self::R_PERM => array(Permission::USE_HEAD_TO_HEAD_REPORT)
    ),

    'EULAPane' => array(
      self::R_NAME => "Sign agreement",
      self::R_PATH => 'users',
      self::R_URLS => array('license'),
      self::R_PERM => array()
    ),

    'HelpPost' => array(
      self::R_NAME => "Help",
      self::R_PATH => 'users',
      self::R_URLS => array('help'),
      self::R_PERM => array()
    ),

    'HomePane' => array(
      self::R_NAME => "Home",
      self::R_PATH => 'users',
      self::R_URLS => array('', 'home'),
      self::R_PERM => array()
    ),

    'MembershipReport' => array(
      self::R_NAME => "School participation",
      self::R_PATH => 'users/reports',
      self::R_URLS => array('membership'),
      self::R_PERM => array(Permission::USE_MEMBERSHIP_REPORT)
    ),

    'MessagePane' => array(
      self::R_NAME => "Inbox",
      self::R_PATH => 'users',
      self::R_URLS => array('inbox'),
      self::R_PERM => array()
    ),

    'NewRegattaPane' => array(
      self::R_NAME => "New regatta",
      self::R_PATH => 'users',
      self::R_URLS => array('create'),
      self::R_PERM => array(Permission::CREATE_REGATTA)
    ),

    'SchoolParticipationReportPane' => array(
      self::R_NAME => "Team record",
      self::R_PATH => 'users/reports',
      self::R_URLS => array('team-participation'),
      self::R_PERM => array(Permission::USE_TEAM_RECORD_REPORT)
    ),

    'SearchSailor' => array(
      self::R_NAME => "Search sailors",
      self::R_PATH => 'users',
      self::R_URLS => array('search'),
      self::R_PERM => array()
    ),

    'UserArchivePane' => array(
      self::R_NAME => "All regattas",
      self::R_PATH => 'users',
      self::R_URLS => array('archive'),
      self::R_PERM => array(Permission::EDIT_REGATTA /* TODO: Participation */)
    ),

    'UserSeasonPane' => array(
      self::R_NAME => "Season summary",
      self::R_PATH => 'users',
      self::R_URLS => array('season'),
      self::R_PERM => array(Permission::EDIT_REGATTA /* TODO: Participation */)
    ),

    'AccountsPane' => array(
      self::R_NAME => "All users",
      self::R_PATH => 'users/admin',
      self::R_URLS => array('users', 'accounts'),
      self::R_PERM => array(Permission::EDIT_USERS),
    ),

    'BillingReport' => array(
      self::R_NAME => "Billing report",
      self::R_PATH => 'users/reports',
      self::R_URLS => array('billing'),
      self::R_PERM => array(Permission::USE_BILLING_REPORT)
    ),

    'BoatManagement' => array(
      self::R_NAME => "Boats",
      self::R_PATH => 'users/admin',
      self::R_URLS => array('boats', 'boat'),
      self::R_PERM => array(Permission::EDIT_BOATS)
    ),

    'EmailTemplateManagement' => array(
      self::R_NAME => "Email templates",
      self::R_PATH => 'users/admin',
      self::R_URLS => array('email-templates', 'email-template'),
      self::R_PERM => array(Permission::EDIT_EMAIL_TEMPLATES)
    ),

    'LoggedInUsers' => array(
      self::R_NAME => "Logged-in",
      self::R_PATH => 'users/admin',
      self::R_URLS => array('logged-in', 'active'),
      self::R_PERM => array(Permission::EDIT_USERS)
    ),

    'MailingListManagement' => array(
      self::R_NAME => "Mailing lists",
      self::R_PATH => 'users/admin',
      self::R_URLS => array('lists', 'mailing'),
      self::R_PERM => array(Permission::EDIT_MAILING_LISTS)
    ),

    'OrganizationConfiguration' => array(
      self::R_NAME => "Organization",
      self::R_PATH => 'users/admin',
      self::R_URLS => array('org'),
      self::R_PERM => array(Permission::EDIT_ORGANIZATION)
    ),

    'PendingAccountsPane' => array(
      self::R_NAME => "Pending users",
      self::R_PATH => 'users/admin',
      self::R_URLS => array('pending'),
      self::R_PERM => array(Permission::EDIT_USERS)
    ),

    'PublicFilesManagement' => array(
      self::R_NAME => "Files",
      self::R_PATH => 'users/admin',
      self::R_URLS => array('files', 'file'),
      self::R_PERM => array(Permission::EDIT_PUBLIC_FILES)
    ),

    'RegattaTypeManagement' => array(
      self::R_NAME => "Regatta types",
      self::R_PATH => 'users/admin',
      self::R_URLS => array('types', 'type'),
      self::R_PERM => array(Permission::EDIT_REGATTA_TYPES)
    ),

    'RoleManagementPane' => array(
      self::R_NAME => "Roles",
      self::R_PATH => 'users/admin',
      self::R_URLS => array('roles', 'permissions'),
      self::R_PERM => array(Permission::EDIT_PERMISSIONS)
    ),

    'SeasonManagement' => array(
      self::R_NAME => "Seasons",
      self::R_PATH => 'users/admin',
      self::R_URLS => array('season'),
      self::R_PERM => array(Permission::EDIT_SEASONS)
    ),

    'SendMessage' => array(
      self::R_NAME => "Send message",
      self::R_PATH => 'users/admin',
      self::R_URLS => array('send-message', 'send-messages', 'send-email', 'send-emails'),
      self::R_PERM => array(Permission::SEND_MESSAGE)
    ),

    'SocialSettingsManagement' => array(
      self::R_NAME => "Social settings",
      self::R_PATH => 'users/admin',
      self::R_URLS => array('social'),
      self::R_PERM => array(Permission::EDIT_PUBLIC_FILES)
    ),

    'SponsorsManagement' => array(
      self::R_NAME => "Sponsors",
      self::R_PATH => 'users/admin',
      self::R_URLS => array('sponsor', 'sponsors'),
      self::R_PERM => array(Permission::EDIT_SPONSORS)
    ),

    'TeamRaceOrderManagement' => array(
      self::R_NAME => "Team race orders",
      self::R_PATH => 'users/admin',
      self::R_URLS => array('race-orders', 'race-order'),
      self::R_PERM => array(Permission::EDIT_TR_TEMPLATES)
    ),

    'TextManagement' => array(
      self::R_NAME => "Text settings",
      self::R_PATH => 'users/admin',
      self::R_URLS => array('text'),
      self::R_PERM => array(Permission::EDIT_PUBLIC_FILES)
    ),

    'VenueManagement' => array(
      self::R_NAME => "Venues",
      self::R_PATH => 'users/admin',
      self::R_URLS => array('venues', 'venue'),
      self::R_PERM => array(Permission::EDIT_VENUES)
    ),

    'GlobalSettings' => array(
      self::R_NAME => "Global conf.",
      self::R_PATH => 'users/super',
      self::R_URLS => array('conf'),
      self::R_PERM => array(Permission::EDIT_GLOBAL_CONF)
    ),

    'EditLogoPane' => array(
      self::R_NAME => "School logo",
      self::R_PATH => 'prefs',
      self::R_URLS => array('prefs/%s/logo', 'prefs/%s/burgee'),
      self::R_PERM => array(Permission::EDIT_SCHOOL_LOGO)
    ),

    'SailorMergePane' => array(
      self::R_NAME => "Sailors",
      self::R_PATH => 'prefs',
      self::R_URLS => array('prefs/%s/sailor', 'prefs/%s/sailors'),
      self::R_PERM => array(Permission::EDIT_UNREGISTERED_SAILORS)
    ),

    'TeamNamePrefsPane' => array(
      self::R_NAME => "Team names",
      self::R_PATH => 'prefs',
      self::R_URLS => array('prefs/%s/team', 'prefs/%s/teams'),
      self::R_PERM => array(Permission::EDIT_TEAM_NAMES)
    ),

    'PrefsHomePane' => array(
      self::R_NAME => "Instructions",
      self::R_PATH => 'prefs',
      self::R_URLS => array('prefs/%s/home', 'prefs/%s', 'prefs/%s/'),
      self::R_PERM => array(
        Permission::EDIT_SCHOOL_LOGO,
        Permission::EDIT_UNREGISTERED_SAILORS,
        Permission::EDIT_TEAM_NAMES,
      )
    ),
  );
}
?>
