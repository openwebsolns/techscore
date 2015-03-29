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
   * @var boolean UI mode (true = participant) default = false
   */
  protected $participant_mode = false;

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
   * Determine whether to use the participant mode
   *
   * @param boolean $participant true to set to partcipant mode
   * @throws PermissionException
   */
  public function setParticipantUIMode($participant = false) {
    $this->participant_mode = ($participant !== false);
    if ($this->participant_mode && !in_array(get_class($this), self::$PARTICIPANT_MODE))
      throw new PermissionException("Participant UI not available for this pane.");
  }

  /**
   * Sets up the HTML page
   *
   */
  protected function setupPage() {
    require_once('xml5/TScorePage.php');

    $title = sprintf("%s | %s",
                     $this->name,
                     $this->REGATTA->name);

    $this->PAGE = new TScorePage($title, $this->USER, $this->REGATTA);
    $this->PAGE->addContent(new XPageTitle($this->name));

    // ------------------------------------------------------------
    // Menu
    // ------------------------------------------------------------
    $menuStructure = $this->getMenuStructure();

    $contextMenu = array(
      'DetailsPane' => null,
      'EnterFinishPane' => null,
      'EnterPenaltyPane' => null,
      'RpEnterPane' => null,
    );
    if ($this->REGATTA->scoring == Regatta::SCORING_TEAM) {
      $contextMenu['TeamRacesPane'] = null;
    }
    else {
      $contextMenu['SailsPane'] = null;
    }

    $accessKeys = array(
      'EnterFinishPane' => 'f',
      'SailsPane' => 's',
      'RpEnterPane' => 'r',
    );

    // Fill panes menu
    $id = $this->REGATTA->id;
    foreach ($menuStructure as $title => $panes) {
      $menu = new XDiv(array('class'=>'menu'), array(new XH4($title), $m_list = new XUl()));
      foreach ($panes as $pane) {
        $t = $this->doTitle($pane);
        $url = $this->doLink($pane);
        if ($this->doActive($pane)) {
          $m_list->add(new XLi($a = new XA($url, $t)));
          if (array_key_exists($pane, $accessKeys)) {
            $a->set('accesskey', $accessKeys[$pane]);
          }
        }
        else {
          $m_list->add(new XLi($t, array('class'=>'inactive')));
          unset($contextMenu[$pane]);
        }

      }

      // Exceptions
      if ($title == "Rounds") {
        // Add one for each round
        foreach ($this->REGATTA->getRounds() as $round) {
          $m_list->add(new XLi(new XA($this->link('round', array('r'=>$round->id)), $round)));
        }
      }

        // Logic to incorporate
        /*
        if ($title == "RP Forms" && $this->REGATTA->scoring == Regatta::SCORING_TEAM) {
          // Downloads
          if ($this->has_teams && ($form = DB::getRpFormWriter($this->REGATTA)) !== null) {
            $m_list->add(new XLi(new XA(WS::link(sprintf('/download/%s/rp', $id)), "Download")));
            if (($name = $form->getPdfName()) !== null)
              $m_list->add(new XLi(new XA(WS::link(sprintf('/download/%s/rp-template', $id)), "RP Template")));
            else
              $m_list->add(new XLi("RP Template", array('class'=>'inactive')));
          }
          else {
            $m_list->add(new XLi("Download", array('class'=>'inactive', 'title'=>"No PDF forms available.")));
            $m_list->add(new XLi("RP Template", array('class'=>'inactive', 'title'=>"No PDF forms available.")));
          }
        }
        */

      $this->PAGE->addMenu($menu);
    }

    /*
    if ($this->REGATTA->scoring != Regatta::SCORING_TEAM) {
      // Downloads
      $menu = new XDiv(array('class'=>'menu'), array(new XH4("Download"), $m_list = new XUl()));
      $add = false;
      if ($this->has_teams && $this->has_races) {
        if (($form = DB::getRpFormWriter($this->REGATTA)) !== null) {
          $add = true;
          $m_list->add(new XLi(new XA(WS::link(sprintf('/download/%s/rp', $id)), "Filled RP")));
          if (($name = $form->getPdfName()) !== null)
            $m_list->add(new XLi(new XA(WS::link(sprintf('/download/%s/rp-template', $id)), "RP Template")));
        }
      }
      if ($add)
        $this->PAGE->addMenu($menu);
    }
    */

    // Context menu
    $this->setContextMenu(
      array_keys($contextMenu),
      array('EnterFinishPane' => WS::link('/inc/img/finish.png'),
            'RpEnterPane' => WS::link('/inc/img/rp.png'),
            'SailsPane' => WS::link('/inc/img/rot.png'),
            'TeamRacesPane' => WS::link('/inc/img/rot.png'),
            'DetailsPane' => WS::link('/inc/img/set.png'),
      )
    );
  }

  /**
   * Redirects the browser to the specified page, or regatta home if
   * none specified
   *
   * @param String $page the page within this regatta to go to
   */
  protected function redirect($page = null, Array $args = array()) {
    WS::go($this->link($page, $args));
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
    if (!$this->participant_mode) {
      if (!$this->has_teams) {
        if (get_class($this) != 'AddTeamsPane')
          Session::pa(new PA(array("No teams have yet been setup. ",
                                   new XA(sprintf('/score/%s/teams', $this->REGATTA->id), "Add teams now"), "."), PA::I));
      }
      elseif (!$this->has_races && get_class($this) != 'RacesPane' && get_class($this) != 'TeamRacesPane')
        Session::pa(new PA(array("No races exist for this regatta. Please ",
                                 new XA(sprintf('/score/%s/races', $this->REGATTA->id), "add races"),
                                 " now."), PA::I));
    }
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
      $token = DB::$V->reqString($args, 'csrf_token', 10, 100, "Invalid request provided (missing CSRF)");
      if ($token !== Session::getCsrfToken())
        throw new SoterException("Stale form. For your security, please try again.");
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
    $form = new XForm($this->getLink(), $method);
    if ($method == XForm::POST && class_exists('Session'))
      $form->add(new XHiddenInput('csrf_token', Session::getCsrfToken()));
    return $form;
  }

  /**
   * Like createForm, for uploading files
   *
   */
  protected function createFileForm() {
    $form = new XFileForm($this->getLink());
    if (class_exists('Session'))
      $form->add(new XHiddenInput('csrf_token', Session::getCsrfToken()));
    return $form;
  }

  /**
   * Creates a new race input, tailored to the regatta type
   *
   * @param String $name the name of the input field to create
   * @param Race $value the race to select
   * @param Array $attrs the extra attributes to use
   * @return XInput one of XRaceInput or XCombinedRaceInput
   */
  protected function newRaceInput($name, Race $value = null, Array $attrs = array()) {
    if ($this->REGATTA->scoring == Regatta::SCORING_STANDARD && count($this->REGATTA->getDivisions()) > 1) {
      return new XRaceInput($name, (string)$value, $attrs);
    }
    return new XCombinedRaceInput($name, (string)$value, count($this->REGATTA->getRaces(Division::A())), $attrs);
  }

  /**
   * Gets all the schools for a given conference
   *
   * This method will take the regatta into consideration, and return
   * only the appropriate list of schools, based on regatta date.
   *
   * @return Array:School the list of schools
   */
  protected function getConferenceSchools(Conference $conf) {
    $season = $this->REGATTA->getSeason();
    return $conf->getSchools($season->isCurrent());
  }

  /**
   * Gets all the schools for the current user
   *
   * This method will take the regatta into consideration, and return
   * only the appropriate list of schools, based on regatta date.
   *
   * @param Conference $conf the possible conference
   * @param boolean $effective
   * @return Array:School the list of schools
   * @see User::getSchools
   */
  protected function getUserSchools(Conference $conf = null, $effective = true) {
    $season = $this->REGATTA->getSeason();
    return $this->USER->getSchools($conf, $effective, $season->isCurrent());
  }

  /**
   * Get the school object to use (active, vs. regular)
   *
   * @return School (or Active_School if regatta in current season)
   */
  protected function getSchoolPrototype() {
    $season = $this->REGATTA->getSeason();
    return ($season->isCurrent()) ? DB::T(DB::ACTIVE_SCHOOL) : DB::T(DB::SCHOOL);
  }

  /**
   * Returns a new instance of a pane with the given URL
   *
   * @param Array $url the URL tokens in order
   * @param Account $r the user
   * @param Regatta $u the regatta
   * @return AbstractPane|null
   */
  public static function getPane(Array $url, Account $r, Regatta $u) {
    if (count($url) == 0)
      $url = array('home');
    switch ($url[0]) {
    case 'home':
    case 'details':
    case 'settings':
      require_once('tscore/DetailsPane.php');
      return new DetailsPane($r, $u);
    case 'finalize':
      require_once('tscore/FinalizePane.php');
      return new FinalizePane($r, $u);
    case 'drop-penalty':
    case 'drop-penalties':
      require_once('tscore/DropPenaltyPane.php');
      return new DropPenaltyPane($r, $u);
    case 'enter-finish':
    case 'enter-finishes':
    case 'finish':
    case 'finishes':
      if ($u->scoring == Regatta::SCORING_TEAM) {
        require_once('tscore/TeamEnterFinishPane.php');
        return new TeamEnterFinishPane($r, $u);
      }
      require_once('tscore/EnterFinishPane.php');
      return new EnterFinishPane($r, $u);
    case 'rank':
      if ($u->scoring != Regatta::SCORING_TEAM)
        return null;
      require_once('tscore/RankTeamsPane.php');
      return new RankTeamsPane($r, $u);
    case 'group':
    case 'groups':
      if ($u->scoring != Regatta::SCORING_TEAM)
        return null;
      require_once('tscore/TeamRankGroupPane.php');
      return new TeamRankGroupPane($r, $u);

    case 'partial':
    case 'partial-rank':
      if ($u->scoring != Regatta::SCORING_TEAM)
        return null;
      require_once('tscore/TeamPartialRankPane.php');
      return new TeamPartialRankPane($r, $u);
      
    case 'add-penalty':
    case 'penalties':
    case 'penalty':
      if ($u->scoring == Regatta::SCORING_TEAM) {
        require_once('tscore/TeamEnterPenaltyPane.php');
        return new TeamEnterPenaltyPane($r, $u);
      }
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
    case 'notice':
    case 'notices':
    case 'board':
      require_once('tscore/NoticeBoardPane.php');
      return new NoticeBoardPane($r, $u);
    case 'delete':
      require_once('tscore/DeleteRegattaPane.php');
      return new DeleteRegattaPane($r, $u);
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

    case 'order-rounds':
    case 'group':
      if ($u->scoring != Regatta::SCORING_TEAM)
        return null;
      require_once('tscore/TeamOrderRoundsPane.php');
      return new TeamOrderRoundsPane($r, $u);

    case 'round':
    case 'rounds':
      if ($u->scoring != Regatta::SCORING_TEAM)
        return null;
      require_once('tscore/TeamEditRoundPane.php');
      return new TeamEditRoundPane($r, $u);

    case 'substitute':
    case 'substitute-team':
    case 'sub-team':
      if ($u->scoring == Regatta::SCORING_TEAM) {
        require_once('tscore/TeamReplaceTeamPane.php');
        return new TeamReplaceTeamPane($r, $u);
      }
      require_once('tscore/ReplaceTeamPane.php');
      return new ReplaceTeamPane($r, $u);
    case 'rp':
    case 'rps':
    case 'enter-rp':
    case 'enter-rps':
      if ($u->scoring == Regatta::SCORING_TEAM) {
        require_once('tscore/TeamRpEnterPane.php');
        return new TeamRpEnterPane($r, $u);
      }
      require_once('tscore/RpEnterPane.php');
      return new RpEnterPane($r, $u);
    case 'missing':
    case 'missing-rp':
      require_once('tscore/RpMissingPane.php');
      return new RpMissingPane($r, $u);
    case 'setup-rotations':
    case 'setup-rotation':
    case 'rotation':
    case 'rotations':
    case 'sails':
    case 'create-rotation':
    case 'create-rotations':
      if ($u->scoring == Regatta::SCORING_TEAM) {
        return null;
      }
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
      require_once('tscore/AddTeamsPane.php');
      return new AddTeamsPane($r, $u);
    case 'edit-team':
    case 'edit-teams':
      if ($u->isSingleHanded())
        return null;
      require_once('tscore/EditTeamsPane.php');
      return new EditTeamsPane($r, $u);
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
    if ($this->participant_mode && !in_array($class_name, self::$PARTICIPANT_MODE))
      return false;

    switch ($class_name) {
    case 'EnterPenaltyPane':
    case 'TeamEnterPenaltyPane':
    case 'RankTeamsPane':
    case 'TeamRankGroupPane':
    case 'TeamPartialRankPane':
    case 'RpMissingPane':
      return $this->has_scores;

    case 'FinalizePane':
      return ($this->has_scores && $this->REGATTA->finalized === null);

    case 'DropPenaltyPane':
      return $this->has_penalty;

    case 'NotesPane':
      return $this->has_races;

    case 'EditTeamsPane':
    case 'DeleteTeamsPane':
      return $this->has_teams;

    case 'TeamReplaceTeamPane':
    case 'ReplaceTeamPane':
    case 'RpEnterPane':
    case 'TeamRpEnterPane':
    case 'UnregisteredSailorPane':
    case 'EnterFinishPane':
    case 'TeamEnterFinishPane':
      return $this->has_teams && $this->has_races;

    case 'SailsPane':
    case 'TeamPenaltyPane':
      return $this->has_teams && $this->has_races;

    case 'TeamRacesPane':
    case 'RacesPane':
      return $this->has_teams;

    case 'TeamEditRoundPane':
    case 'TeamOrderRoundsPane':
      return count($this->REGATTA->getRounds()) > 0;

    case 'ManualTweakPane':
    case 'TweakSailsPane':
      if ($this->REGATTA->scoring == Regatta::SCORING_TEAM)
        return $this->has_races;
      return $this->has_rots;

    case 'RotationDialog':
    case 'TeamRotationDialog':
      if ($this->REGATTA->scoring == Regatta::SCORING_TEAM)
        return $this->has_races;
      return $this->has_rots;

    case 'ScoresChartDialog':
    case 'ScoresCombinedDialog':
    case 'ScoresDivisionalDialog':
    case 'ScoresDivisionDialog':
    case 'ScoresFullDialog':
    case 'ScoresGridDialog':
    case 'TeamRacesDialog':
    case 'TeamRankingDialog':
      return $this->has_scores;
      
    default:
      return true;
    }
  }

  /**
   * Applies exclusively to dialogs
   *
   * @deprecated
   */
  private function doActiveDialog($class_name) {
    switch ($class_name) {
    case 'rotation':
      if ($this->REGATTA->scoring == Regatta::SCORING_TEAM)
        return $this->has_races;
      return $this->has_rots;

    case 'scores':
      return $this->has_scores;

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
    if (array_key_exists($i, self::$TITLES))
      return self::$TITLES[$i];
    throw new InvalidArgumentException("No title registered for pane $i.");
  }

  /**
   * Returns the internal absolute path to 'this' pane.
   *
   * @return String the URL, e.g. /score/<id>/pane.
   * @throws InvalidArgumentException if pane has not been registered.
   */
  protected function getLink() {
    return $this->doLink(get_class($this));
  }

  /**
   * Returns the internal absolute path to the given pane.
   *
   * @param String $classname of pane whose link to generate.
   * @return String the URL, e.g. /score/<id>/pane.
   * @throws InvalidArgumentException if pane has not been registered.
   */
  protected function doLink($classname) {
    if (!array_key_exists($classname, self::$URLS)) {
      throw new InvalidArgumentException("Please register URL for pane $classname.");
    }
    return sprintf(self::$URLS[$classname], $this->REGATTA->id);
  }

  /**
   * Adds the given menu to the page and sets it as the body's contextmenu.
   *
   * @param Array $entries list of pane classnames.
   * @param Array $icons optional list of icon URLs.
   */
  private function setContextMenu(Array $entries, Array $icons = array()) {
    if ($this->PAGE === null)
      return;

    $id = 'context-menu';
    $m = new XElem('menu', array('id'=>$id, 'type'=>'context', 'style'=>'display:none;position:fixed;'));

    foreach ($entries as $pane) {
      $t = $this->doTitle($pane);
      $url = $this->doLink($pane);

      $i = new XElem(
        'menuitem',
        array(
          'label' => $t,
          'onclick' => sprintf('window.location="%s";', $url),
        ));

      if (isset($icons[$pane]))
        $i->set('icon', $icons[$pane]);
      $m->add($i);
    }

    $this->PAGE->body->add($m);
  }

  // ------------------------------------------------------------
  // Menu and pane registration
  // ------------------------------------------------------------

  /**
   * Create menu structure based on regatta/participation.
   *
   * The array structure returned consists of a map indexed by menu name, with
   * corresponding values a list of pane/dialog classnames contained in that
   * menu.
   *
   * @return Array map of menu entries.
   */
  private function getMenuStructure() {
    if ($this->REGATTA->scoring == Regatta::SCORING_TEAM) {
      if ($this->participant_mode) {
        return array(
          "Regatta"  => array(
            'DetailsPane',
            'TeamRotationDialog',
            'TeamRacesDialog',
            'TeamRegistrationsDialog',
          ),
          "RP Forms" => array(
            'TeamRpEnterPane',
            'UnregisteredSailorPane',
            'TeamRegistrationsDialog',
          ),
        );
      }

      return array(
        "Regatta" => array(
          'DetailsPane',
          'SummaryPane',
          'FinalizePane',
          'ScorersPane',
          'NotesPane',
          'NoticeBoardPane',
          'DeleteRegattaPane',
        ),
        "Teams" => array(
          'AddTeamsPane',
          'EditTeamsPane',
          'TeamReplaceTeamPane',
          'DeleteTeamsPane',
        ),
        "Rounds" => array(
          'TeamRacesPane',
          'TeamOrderRoundsPane',
          // 'TweakSailsPane',
          // 'ManualTweakPane',
          'TeamRotationDialog',
        ),
        "RP Forms" => array(
          'TeamRpEnterPane',
          'UnregisteredSailorPane',
          'RpMissingPane',
          'TeamRegistrationsDialog',
        ),
        "Finishes"  => array(
          'TeamEnterFinishPane',
          'TeamEnterPenaltyPane',
          'DropPenaltyPane',
          'TeamPenaltyPane',
          'TeamRacesDialog',
        ),
        "Ranks" => array(
          'RankTeamsPane',
          'TeamRankGroupPane',
          'TeamPartialRankPane'
        ),
        "Windows" => array(
          'RotationDialog',
          'ScoresFullDialog',
          'RegistrationsDialog',
        ),
      );
    }

    // Fleet racing
    if ($this->participant_mode) {
      $score_i = array(
        "Regatta" => array(
          'DetailsPane',
          'RotationDialog',
          'ScoresFullDialog',
          'RegistrationsDialog',
        )
      );
      if (!$this->REGATTA->isSingleHanded()) {
        $score_i["Teams"]  = array(
          'EditTeamsPane',
        );
      }
      $score_i["RP Forms"] = array(
        'RpEnterPane',
        'UnregisteredSailorPane',
        'RegistrationsDialog',
      );
      return $score_i;
    }

    $teamList = array('AddTeamsPane');
    if (!$this->REGATTA->isSingleHanded()) {
      $teamList[] = 'EditTeamsPane';
    }
    $teamList[] = 'ReplaceTeamPane';
    $teamList[] = 'DeleteTeamsPane';

    return array(
      "Regatta" => array(
        'DetailsPane',
        'SummaryPane',
        'FinalizePane',
        'ScorersPane',
        'RacesPane',
        'NotesPane',
        'NoticeBoardPane',
        'DeleteRegattaPane',
      ),
      "Teams" => $teamList,
      "Rotations" => array(
        'SailsPane',
        'TweakSailsPane',
        'ManualTweakPane',
        'RotationDialog',
      ),
      "RP Forms" => array(
        'RpEnterPane',
        'UnregisteredSailorPane',
        'RpMissingPane',
        'RegistrationsDialog',
      ),
      "Finishes" => array(
        'EnterFinishPane',
        'EnterPenaltyPane',
        'DropPenaltyPane',
        'TeamPenaltyPane',
        'ScoresFullDialog',
      ),
      "Downloads" => array(
        // TODO
      ),
      "Windows" => array(
        'RotationDialog',
        'ScoresFullDialog',
        'RegistrationsDialog',
      ),
    );
  }

  /**
   * @var Array list of panes that support "participant" UI mode
   */
  private static $PARTICIPANT_MODE = array(
    'DetailsPane',
    'EditTeamsPane',
    'RpEnterPane',
    'TeamRpEnterPane',
    'UnregisteredSailorPane',

    'BoatsDialog',
    'RegistrationsDialog',
    'RotationDialog',
    'ScoresChartDialog',
    'ScoresCombinedDialog',
    'ScoresDivisionalDialog',
    'ScoresDivisionDialog',
    'ScoresFullDialog',
    'ScoresGridDialog',

    'TeamRacesDialog',
    'TeamRankingDialog',
    'TeamRegistrationsDialog',
    'TeamRotationDialog',
  );

  /**
   * URL generation templates.
   *
   * The route format string key contains a %s where the regatta URL is supposed
   * to go, thus allowing different slugs like /score/<id>/<url> and
   * /view/<id>/<url>.
   */
  private static $URLS = array(
    'AddTeamsPane'           => '/score/%s/teams',
    'DeleteRegattaPane'      => '/score/%s/delete',
    'DeleteTeamsPane'        => '/score/%s/remove-teams',
    'DetailsPane'            => '/score/%s/settings',
    'DropPenaltyPane'        => '/score/%s/drop-penalty',
    'EditTeamsPane'          => '/score/%s/edit-teams',
    'EnterFinishPane'        => '/score/%s/finishes',
    'EnterPenaltyPane'       => '/score/%s/penalty',
    'FinalizePane'           => '/score/%s/finalize',
    'ManualTweakPane'        => '/score/%s/manual-rotation',
    'NotesPane'              => '/score/%s/notes',
    'NoticeBoardPane'        => '/score/%s/notices',
    'RacesPane'              => '/score/%s/races',
    'RankTeamsPane'          => '/score/%s/rank',
    'ReplaceTeamPane'        => '/score/%s/substitute',
    'RpEnterPane'            => '/score/%s/rp',
    'RpMissingPane'          => '/score/%s/missing',
    'SailsPane'              => '/score/%s/rotations',
    'ScorersPane'            => '/score/%s/scorers',
    'SummaryPane'            => '/score/%s/summaries',
    'TeamEditRoundPane'      => '/score/%s/rounds',
    'TeamEnterFinishPane'    => '/score/%s/finishes',
    'TeamEnterPenaltyPane'   => '/score/%s/penalty',
    'TeamOrderRoundsPane'    => '/score/%s/order-rounds',
    'TeamPartialRankPane'    => '/score/%s/partial',
    'TeamPenaltyPane'        => '/score/%s/team-penalty',
    'TeamRacesPane'          => '/score/%s/races',
    'TeamRankGroupPane'      => '/score/%s/group',
    'TeamReplaceTeamPane'    => '/score/%s/substitute',
    'TeamRpEnterPane'        => '/score/%s/rp',
    'TweakSailsPane'         => '/score/%s/tweak-sails',
    'UnregisteredSailorPane' => '/score/%s/unregistered',

    'BoatsDialog'            => '/view/%s/boats',
    'RegistrationsDialog'    => '/view/%s/sailors',
    'RotationDialog'         => '/view/%s/rotation',
    'ScoresChartDialog'      => '/view/%s/chart',
    'ScoresCombinedDialog'   => '/view/%s/combined',
    'ScoresDivisionalDialog' => '/view/%s/ranking',
    'ScoresDivisionDialog'   => '/view/%s/scores/A',
    'ScoresFullDialog'       => '/view/%s/scores',
    'ScoresGridDialog'       => '/view/%s/scores',

    'TeamRacesDialog'        => '/view/%s/races',
    'TeamRankingDialog'      => '/view/%s/ranking',
    'TeamRegistrationsDialog'=> '/view/%s/sailors',
    'TeamRotationDialog'     => '/view/%s/rotation',
  );

  private static $TITLES = array(
    'AddTeamsPane'           => 'Add team',
    'DeleteRegattaPane'      => 'Delete',
    'DeleteTeamsPane'        => 'Remove team',
    'DetailsPane'            => 'Settings',
    'DropPenaltyPane'        => 'Drop penalty',
    'EditTeamsPane'          => 'Edit names',
    'EnterFinishPane'        => 'Enter finish',
    'EnterPenaltyPane'       => 'Add penalty',
    'FinalizePane'           => 'Finalize',
    'ManualTweakPane'        => 'Manual setup',
    'NotesPane'              => 'Race notes',
    'NoticeBoardPane'        => 'Notice Board',
    'RacesPane'              => 'Add/edit races',
    'RankTeamsPane'          => 'Rank teams',
    'ReplaceTeamPane'        => 'Sub team',
    'RpEnterPane'            => 'Enter RP',
    'RpMissingPane'          => 'Missing RP',
    'SailsPane'              => 'Set rotation',
    'ScorersPane'            => 'Scorers',
    'SummaryPane'            => 'Summaries',
    'TeamEditRoundPane'      => 'Edit round',
    'TeamEnterFinishPane'    => 'Enter finish',
    'TeamEnterPenaltyPane'   => 'Add penalty',
    'TeamOrderRoundsPane'    => 'Order rounds',
    'TeamPartialRankPane'    => 'Partial ranking',
    'TeamPenaltyPane'        => 'Team penalty',
    'TeamRacesPane'          => 'Add round',
    'TeamRankGroupPane'      => 'Rank groups',
    'TeamReplaceTeamPane'    => 'Sub team',
    'TeamRpEnterPane'        => 'Enter RP',
    'TweakSailsPane'         => 'Tweak sails',
    'UnregisteredSailorPane' => 'Unregistered',

    'BoatsDialog'            => 'Boat rankings',
    'RegistrationsDialog'    => 'View registrations',
    'RotationDialog'         => 'View rotations',
    'ScoresChartDialog'      => 'Rank chart',
    'ScoresCombinedDialog'   => 'View combined scores',
    'ScoresDivisionalDialog' => 'View division rank',
    'ScoresDivisionDialog'   => 'View division scores',
    'ScoresFullDialog'       => 'View scores',
    'ScoresGridDialog'       => 'View scores',

    'TeamRacesDialog'        => 'View races',
    'TeamRankingDialog'      => 'View rankings',
    'TeamRegistrationsDialog'=> 'View registrations',
    'TeamRotationDialog'     => 'View rotations',
  );
}
?>
