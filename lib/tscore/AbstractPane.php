<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once('xml5/TS.php');

/**
 * Parent class of all editing panes. Requires USER and REGATTA.
 *
 * @author Dayan Paez
 * @version 2009-09-27
 */
abstract class AbstractPane {

  // Private variables
  private $name;
  protected $REGATTA;
  protected $PAGE;
  protected $USER;

  // variables to determine validity. See doActive()
  protected $has_races = false;
  protected $has_teams = false;
  protected $has_rots = false;
  protected $has_scores = false;
  protected $has_penalty = false;

  /**
   * Create a new editing pane with the given name
   *
   * @param String $name the name of the editing page
   * @param Account $user the user that is editing
   * @param Regatta $reg the regatta for this page
   */
  public function __construct($name, Account $user, Regatta $reg) {
    $this->name = (string)$name;
    $this->REGATTA = $reg;
    $this->USER = $user;

    $rot = $this->REGATTA->getRotation();
    $this->has_races = count($this->REGATTA->getRaces());
    $this->has_teams = count($this->REGATTA->getTeams()) > 1;
    $this->has_rots = $this->has_teams && $rot->isAssigned();
    $this->has_scores = $this->has_teams && $this->REGATTA->hasFinishes();
    $this->has_penalty = $this->has_scores && $this->REGATTA->hasPenalties();
  }

  /**
   * Sets up the HTML page
   *
   */
  protected function setupPage() {
    require_once('xml5/TScorePage.php');

    $title = sprintf("%s | %s | TS",
                     $this->name,
                     $this->REGATTA->name);

    $this->PAGE = new TScorePage($title, $this->USER, $this->REGATTA);
    $this->PAGE->addContent(new XPageTitle($this->name));

    // ------------------------------------------------------------
    // Menu
    $score_i = array("Regatta"   => array("settings"   => "DetailsPane",
                                          "summaries"  => "SummaryPane",
                                          "scorers"    => "ScorersPane",
                                          "races"      => "RacesPane",
                                          "notes"      => "NotesPane"),
                     "Teams"     => array("teams"      => "TeamsPane",
                                          "substitute" => "ReplaceTeamPane",
                                          "remove-team"=> "DeleteTeamsPane"),
                     "Rotations" => array("rotations"  => "SailsPane",
                                          "tweak-sails"=> "TweakSailsPane",
                                          "manual-rotation" => "ManualTweakPane"),
                     "RP Forms"  => array("rp"         => "RpEnterPane",
                                          "unregistered" => "UnregisteredSailorPane"),
                     "Finishes"  => array("finishes" => "EnterFinishPane",
                                          "drop-finishes" => "DropFinishPane",
                                          "penalty"  => "EnterPenaltyPane",
                                          "drop-penalty" => "DropPenaltyPane",
                                          "team-penalty" => "TeamPenaltyPane"));

    $dial_i  = array("rotation" => "Rotation",
                     "scores"   => "Scores",
                     "sailors"  => "Sailors");

    // Fill panes menu
    $id = $this->REGATTA->id;
    foreach ($score_i as $title => $panes) {
      $menu = new XDiv(array('class'=>'menu'), array(new XH4($title), $m_list = new XUl()));
      foreach ($panes as $url => $pane) {
        $t = $this->doTitle($pane);
        if ($this->doActive($pane))
          $m_list->add(new XLi(new XA("/score/$id/$url", $t)));
        else
          $m_list->add(new XLi($t, array("class"=>"inactive")));
      }
      if ($title == "Regatta")
        $m_list->add(new XLi(new XA('/', "Close", array('accesskey'=>'w'))));

      $this->PAGE->addMenu($menu);
    }

    // Downloads
    $menu = new XDiv(array('class'=>'menu'), array(new XH4("Download"), $m_list = new XUl()));
    // $m_list->add(new XLi(new XA("/download/$id/regatta", "Regatta")));
    $m_list->add(new XLi(new XA("/download/$id/rp", "RP Forms")));
    $this->PAGE->addMenu($menu);

    // Dialogs
    $menu = new XDiv(array('class'=>'menu'), array(new XH4("Windows"), $m_list = new XUl()));
    foreach ($dial_i as $url => $title) {
      if ($this->doActive($url)) {
        $link = new XA("/view/$id/$url", $title);
        $link->set("class", "frame-toggle");
        $link->set("onclick", sprintf('this.target="%s"', $url));
        $item = new XLi($link);
      }
      else
        $item = new XLi($title, array("class"=>"inactive"));
      $m_list->add($item);
    }
    $this->PAGE->addMenu($menu);
  }

  /**
   * Redirects the browser to the specified page, or regatta home if
   * none specified
   *
   * @param String $page the page within this regatta to go to
   */
  protected function redirect($page = null) {
    WS::go($this->link($page));
  }

  /**
   * Creates a link to the given page
   *
   * @return String the link
   */
  protected function link($page = null, Array $args = array()) {
    return WS::link(sprintf('/score/%s/%s', $this->REGATTA->id, $page), $args);
  }

  /**
   * Prints string reprensentation of the HTML page
   *
   * @param Array $args the arguments to this page
   */
  final public function getHTML(Array $args) {
    $this->setupPage();
    if (!$this->has_teams) {
      if (get_class($this) != 'TeamsPane')
        Session::pa(new PA(array("No teams have yet been setup. ",
                                 new XA(sprintf('/score/%s/teams', $this->REGATTA->id), "Add teams now"), "."), PA::I));
    }
    elseif (!$this->has_races && get_class($this) != 'RacesPane' && get_class($this) != 'TeamRacesPane')
      Session::pa(new PA(array("No races exist for this regatta. Please ",
                               new XA(sprintf('/score/%s/races', $this->REGATTA->id), "add races"),
                               " now."), PA::I));

    $this->fillHTML($args);
    $this->PAGE->printXML();
  }

  /**
   * Children of this class must implement this method to be used when
   * displaying the page. The method should fill the protected
   * variable $PAGE, which is an instance of TScorePage
   *
   * @param Array $args arguments to customize the display of the page
   */
  abstract protected function fillHTML(Array $args);

  /**
   * Process the edits described in the arguments array
   *
   * @param Array $args the parameters to process
   * @return Array parameters to pass to the next page
   * @throws SoterException
   */
  abstract public function process(Array $args);

  /**
   * Wrapper around process method to be used by web clients. Wraps
   * the SoterExceptions as announcements.
   *
   * @param Array $args the parameters to process
   * @return Array parameters to pass to the next page
   */
  final public function processPOST(Array $args) {
    try {
      return $this->process($args);
    } catch (SoterException $e) {
      Session::pa(new PA($e->getMessage(), PA::E));
      return array();
    }
  }

  /**
   * Creates a new Form HTML element using the standard URL for this
   * pane. The optional $method is by default post
   *
   * @param $method "post" or "get"
   * @return Form element
   */
  protected function createForm($method = XForm::POST) {
    $i = get_class($this);
    if (!isset(self::$URLS[$i]))
      throw new InvalidArgumentException("Please register URL for pane $i.");
    return new XForm(sprintf("/score/%d/%s", $this->REGATTA->id, self::$URLS[$i]), $method);
  }

  /**
   * Returns a new instance of a pane with the given URL
   *
   * @param $url the URL
   * @return AbstractPane|null
   */
  public static function getPane($url, Account $r, Regatta $u) {
    switch ($url) {
    case 'home':
    case 'details':
    case 'settings':
      require_once('tscore/DetailsPane.php');
      return new DetailsPane($r, $u);
    case 'drop-finishes':
    case 'all-finishes':
    case 'current-finishes':
      require_once('tscore/DropFinishPane.php');
      return new DropFinishPane($r, $u);
    case 'drop-penalty':
    case 'drop-penalties':
      require_once('tscore/DropPenaltyPane.php');
      return new DropPenaltyPane($r, $u);
    case 'enter-finish':
    case 'enter-finishes':
    case 'finish':
    case 'finishes':
      require_once('tscore/EnterFinishPane.php');
      return new EnterFinishPane($r, $u);
    case 'add-penalty':
    case 'penalties':
    case 'penalty':
      require_once('tscore/EnterPenaltyPane.php');
      return new EnterPenaltyPane($r, $u);
    case 'manual-rotation':
      require_once('tscore/ManualTweakPane.php');
      return new ManualTweakPane($r, $u);
    case 'notes':
    case 'note':
    case 'race-note':
    case 'race-notes':
      require_once('tscore/NotesPane.php');
      return new NotesPane($r, $u);
    case 'races':
    case 'race':
    case 'edit-race':
    case 'edit-races':
      if ($u->scoring == Regatta::SCORING_TEAM) {
        require_once('tscore/TeamRacesPane.php');
        return new TeamRacesPane($r, $u);
      }
      require_once('tscore/RacesPane.php');
      return new RacesPane($r, $u);
    case 'substitute':
    case 'substitute-team':
    case 'sub-team':
      require_once('tscore/ReplaceTeamPane.php');
      return new ReplaceTeamPane($r, $u);
    case 'rp':
    case 'rps':
    case 'enter-rp':
    case 'enter-rps':
      require_once('tscore/RpEnterPane.php');
      return new RpEnterPane($r, $u);
    case 'setup-rotations':
    case 'setup-rotation':
    case 'rotation':
    case 'rotations':
    case 'sails':
    case 'create-rotation':
    case 'create-rotations':
      require_once('tscore/SailsPane.php');
      return new SailsPane($r, $u);
    case 'scorer':
    case 'scorers':
      require_once('tscore/ScorersPane.php');
      return new ScorersPane($r, $u);
    case 'summaries':
    case 'daily-summaries':
    case 'summary':
    case 'daily-summary':
      require_once('tscore/SummaryPane.php');
      return new SummaryPane($r, $u);
    case 'team-penalty':
    case 'team-penalties':
      require_once('tscore/TeamPenaltyPane.php');
      return new TeamPenaltyPane($r, $u);
    case 'team':
    case 'teams':
    case 'add-teams':
    case 'set-teams':
    case 'add-team':
    case 'set-team':
      require_once('tscore/TeamsPane.php');
      return new TeamsPane($r, $u);
    case 'remove-team':
    case 'remove-teams':
    case 'delete-team':
    case 'delete-teams':
      require_once('tscore/DeleteTeamsPane.php');
      return new DeleteTeamsPane($r, $u);
    case 'tweak':
    case 'tweak-sails':
    case 'substitute-sails':
    case 'substitute-sail':
    case 'tweak-sail':
      require_once('tscore/TweakSailsPane.php');
      return new TweakSailsPane($r, $u);
    case 'unregistered':
    case 'unregistered-sailors':
    case 'unregistered-sailor':
    case 'new-sailors':
    case 'new-sailor':
      require_once('tscore/UnregisteredSailorPane.php');
      return new UnregisteredSailorPane($r, $u);
    default:
      return null;
    }
  }

  private function doActive($class_name) {
    switch ($class_name) {
    case 'DropFinishPane':
      return $this->has_rots && $this->has_scores && ($this->REGATTA->scoring != Regatta::SCORING_TEAM);

    case 'EnterPenaltyPane':
      return $this->has_scores && ($this->REGATTA->scoring != Regatta::SCORING_TEAM);

    case 'DropPenaltyPane':
      return $this->has_penalty;

    case 'NotesPane':
      return $this->has_races;

    case 'ReplaceTeamPane':
    case 'DeleteTeamsPane':
    case 'RpEnterPane':
    case 'UnregisteredSailorPane':
    case 'EnterFinishPane':
      return $this->has_teams && $this->has_races;

    case 'SailsPane':
    case 'TeamPenaltyPane':
      return $this->has_teams && $this->has_races && ($this->REGATTA->scoring != Regatta::SCORING_TEAM);

    case 'TeamRacesPane':
    case 'RacesPane':
      return $this->has_teams;

    case 'ManualTweakPane':
    case 'TweakSailsPane':
    case 'rotation':
      return $this->has_rots;

    default:
      return true;
    }
  }

  /**
   * Determines whether this pane is active
   *
   * @return boolean true if active, false otherwise
   */
  final public function isActive() {
    return $this->doActive(get_class($this));
  }

  /**
   * Returns the title of this pane
   *
   */
  public function getTitle() {
    return $this->doTitle(get_class($this));
  }
  private function doTitle($i) {
    if (isset(self::$TITLES[$i]))
      return self::$TITLES[$i];
    throw new InvalidArgumentException("No title registered for pane $i.");
  }
  private static $URLS = array("DetailsPane" => "settings",
                               "SummaryPane" => "summaries",
                               "ScorersPane" => "scorers",

                               "TeamRacesPane" => "races",
                               "RacesPane" => "races",

                               "NotesPane" => "notes",
                               "TeamsPane" => "teams",
                               "DeleteTeamsPane" => "remove-teams",
                               "ReplaceTeamPane" => "substitute",
                               "SailsPane" => "rotations",
                               "TweakSailsPane" => "tweak-sails",
                               "ManualTweakPane" => "manual-rotation",
                               "RpEnterPane" => "rp",
                               "UnregisteredSailorPane" => "unregistered",
                               "EnterFinishPane" => "finishes",
                               "DropFinishPane" => "drop-finishes",
                               "EnterPenaltyPane" => "penalty",
                               "DropPenaltyPane" => "drop-penalty",
                               "TeamPenaltyPane" => "team-penalty");

  private static $TITLES = array("DetailsPane" => "Settings",
                                 "SummaryPane" => "Summaries",
                                 "ScorersPane" => "Scorers",
                                 "RacesPane" => "Edit races",
                                 "NotesPane" => "Race notes",
                                 "TeamsPane" => "Add team",
                                 "DeleteTeamsPane" => "Remove team",
                                 "ReplaceTeamPane" => "Sub team",
                                 "SailsPane" => "Setup",
                                 "TweakSailsPane" => "Tweak sails",
                                 "ManualTweakPane" => "Manual setup",
                                 "RpEnterPane" => "Enter RP",
                                 "UnregisteredSailorPane" => "Unregistered",
                                 "EnterFinishPane" => "Enter finish",
                                 "DropFinishPane" => "All finishes",
                                 "EnterPenaltyPane" => "Add penalty",
                                 "DropPenaltyPane" => "Drop penalty",
                                 "TeamPenaltyPane" => "Team penalty");
}
?>