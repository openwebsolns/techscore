<?php
use \model\AbstractObject;
use \model\PublicData;
use \model\Publishable;

/**
 * Schools
 *
 * @author Dayan Paez
 * @version 2012-01-07
 */
class School extends AbstractObject implements Publishable {
  public $nick_name;
  public $name;
  public $url;
  public $city;
  public $state;
  protected $conference;
  protected $burgee;
  protected $burgee_small;
  protected $burgee_square;
  protected $inactive;
  protected $sync_log;

  public function db_name() { return 'school'; }
  public function db_type($field) {
    switch ($field) {
    case 'conference': return DB::T(DB::CONFERENCE);
    case 'burgee':
    case 'burgee_small':
    case 'burgee_square':
      return DB::T(DB::BURGEE);
    case 'inactive': return DB::T(DB::NOW);
    case 'sync_log': return DB::T(DB::SYNC_LOG);
    default:
      return parent::db_type($field);
    }
  }
  protected function db_cache() { return true; }
  protected function db_order() { return array('name'=>true); }
  public function __toString() { return $this->name; }

  public function drawBurgeeInline() {
    if ($this->burgee === null || $this->id === null) {
      return null;
    }

    $burgee = $this->__get('burgee');
    $img = new XImg(sprintf('data:image/png;base64,%s', $burgee->filedata), $this->nick_name);
    if ($burgee->width !== null) {
      $img->set('width', $burgee->width);
      $img->set('height', $burgee->height);
    }
    return $img;
  }

  public function setActive($flag = true) {
    if ($flag !== false) {
      $this->inactive = null;
    }
    else {
      $this->inactive = DB::T(DB::NOW);
    }
  }

  public function isActive() {
    return $this->inactive === null;
  }

  /**
   * Return IMG element of burgee, if burgee exists
   *
   * @param mixed $def the element to return if no burgee exists
   * @param Array $attrs extra attributes to use for XImg
   * @return XImg|null
   */
  public function drawBurgee($def = null, Array $attrs = array()) {
    if ($this->burgee === null || $this->id === null) {
      return $def;
    }

    $img = new XImg(sprintf('/inc/img/schools/%s.png', $this->id), $this->nick_name, $attrs);
    if ($this->__get('burgee')->width !== null) {
      $img->set('width', $this->__get('burgee')->width);
      $img->set('height', $this->__get('burgee')->height);
    }
    return $img;
  }

  /**
   * Returns IMG element of small burgee
   *
   * @param mixed $def the element to return if no burgee exists
   * @param Array $attrs extra attributes to use for XImg
   * @return XImg|null
   * @see drawBurgee
   */
  public function drawSmallBurgee($def = null, Array $attrs = array()) {
    if ($this->burgee_small === null || $this->id === null)
      return $def;

    $img = new XImg(sprintf('/inc/img/schools/%s-40.png', $this->id), $this->nick_name, $attrs);
    if ($this->__get('burgee_small')->width !== null) {
      $img->set('width', $this->__get('burgee_small')->width);
      $img->set('height', $this->__get('burgee_small')->height);
    }
    return $img;
  }

  /**
   * Returns IMG element of square burgee
   *
   * @param mixed $def the element to return if no burgee exists
   * @param Array $attrs extra attributes to use for XImg
   * @return XImg|null
   * @see drawBurgee
   */
  public function drawSquareBurgee($def = null, Array $attrs = array()) {
    if ($this->burgee_square === null || $this->id === null)
      return $def;

    $img = new XImg(sprintf('/inc/img/schools/%s-sq.png', $this->id), $this->nick_name, $attrs);
    if ($this->__get('burgee_square')->width !== null) {
      $img->set('width', $this->__get('burgee_square')->width);
      $img->set('height', $this->__get('burgee_square')->height);
    }
    return $img;
  }

  /**
   * Determines whether this school has the given burgee type.
   *
   * This method saves memory by avoiding the direct serialization of
   * the burgee property, bypassing the magic __get method.
   *
   * Since it should be the case that all versions exist, or none
   * at all, the argument to this method is (usually) unnecessary. It
   * is included for precision control, in order to check against the
   * specific version (e.g. '', 'small', 'square').
   *
   * @param String $type (optional) the burgee version (small, etc)
   * @return boolean true if burgee exists
   */
  public function hasBurgee($type = '') {
    switch ($type) {
    case 'small':  return $this->burgee_small !== null;
    case 'square': return $this->burgee_square !== null;
    default:       return $this->burgee !== null;
    }
  }

  /**
   * Returns the public URL root for this school
   *
   * This is /schools/<url>/, where <url> is the "url" property if one
   * exists, or the ID otherwise
   *
   * @return String the URL
   * @throws InvalidArgumentException if no "url" or "id" provided
   */
  public function getURL() {
    if ($this->url !== null)
      return '/schools/' . $this->url . '/';
    if ($this->id === null)
      throw new InvalidArgumentException("No ID exists for this school.");
    return '/schools/' . $this->id . '/';
  }

  public function getPublicData() {
    return (new PublicData(PublicData::V1))
      ->with('id', $this->id)
      ->with('nick', $this->nick_name)
      ->with('name', $this->name)
      ->with('city', $this->city)
      ->with('state', $this->state)
      ->with('conference', $this->__get('conference'))
      ->with('burgee', $this->__get('burgee'))
      ->with('burgee_small', $this->__get('burgee_small'))
      ->with('burgee_square', $this->__get('burgee_square'))
      ;
  }

  /**
   * Returns a list of sailors for the specified school
   *
   * @return Array:RegisteredSailor list of sailors
   */
  public function getSailors() {
    return DB::getAll(DB::T(DB::REGISTERED_SAILOR), new DBCond('school', $this));
  }

  /**
   * Returns list of sailors active in the given season
   *
   * @param Season $season
   * @param Sailor::const $gender null for both
   * @param boolean $registered true/false to limit
   */
  public function getSailorsInSeason(Season $season, $gender = null, $registered = null) {
    $cond = new DBBool(
      array(
        new DBCond('school', $this),
        new DBCondIn(
          'id',
          DB::prepGetAll(
            DB::T(DB::SAILOR_SEASON),
            new DBCond('season', $season),
            array('sailor')
          )
        )
      )
    );

    if ($gender !== null) {
      $cond->add(new DBCond('gender', $gender));
    }

    $obj = DB::T(DB::AVAILABLE_SAILOR);
    if ($registered === true) {
      $obj = DB::T(DB::REGISTERED_SAILOR);
    }
    elseif ($registered === false) {
      $obj = DB::T(DB::UNREGISTERED_SAILOR);
    }
    return DB::getAll($obj, $cond);
  }

  /**
   * Fetch list of seasons a member of this school has participated in.
   *
   * @param boolean $inc_private by default only include public regattas
   * @return Array:Season
   */
  public function getSeasons($inc_private = false) {
    $seasons = array();
    foreach (DB::getAll(DB::T(DB::SEASON)) as $season) {
      $participation = $season->getParticipation($this, $inc_private);
      if (count($participation) > 0) {
        $seasons[] = $season;
      }
    }
    return $seasons;
  }

  /**
   * Returns a list of unregistered sailors for the specified school
   *
   * @param RP::const $gender null for both or the gender code
   * @return Array<Sailor> list of sailors
   */
  public function getUnregisteredSailors($gender = null) {
    $cond = new DBCond('school', $this);
    if ($gender !== null) {
      $cond = new DBBool(
        array(
          $cond,
          new DBCond('gender', $gender),
        )
      );
    }
    return DB::getAll(DB::T(DB::UNREGISTERED_SAILOR), $cond);
  }

  /**
   * Returns an ordered list of the team names for this school
   *
   * @return Array:String ordered list of the school names
   */
  public function getTeamNames() {
    $list = array();
    foreach (DB::getAll(DB::T(DB::TEAM_NAME_PREFS), new DBCond('school', $this)) as $pref)
      $list[] = (string)$pref;
    return $list;
  }

  /**
   * Sets the team names for the given school
   *
   * @param School $school school whose valid team names to set
   * @param Array:String $names an ordered list of team names
   */
  public function setTeamNames(Array $names) {
    // Strategy, update as many as are the same, then remove old extra
    // ones, or add any new ones
    $top_rank = count($names);
    $curr = DB::getAll(DB::T(DB::TEAM_NAME_PREFS), new DBCond('school', $this));
    for ($i = 0; $i < count($names) && $i < count($curr); $i++) {
      $tnp = $curr[$i];
      $tnp->name = $names[$i];
      $tnp->rank = $top_rank--;
      DB::set($tnp);
    }
    for (; $i < count($curr); $i++)
      DB::remove($curr[$i]);
    for (; $i < count($names); $i++) {
      $tnp = new Team_Name_Prefs();
      $tnp->school = $this;
      $tnp->name = $names[$i];
      $tnp->rank = $top_rank--;
      DB::set($tnp);
    }
  }

  /**
   * Fetches list of regattas this school has a team in
   *
   * @param boolean $inc_private true to include private regattas
   * @return Array:Regatta the regatta list
   */
  public function getRegattas($inc_private = false) {
    return DB::getAll(($inc_private !== false) ? DB::T(DB::REGATTA) : DB::T(DB::PUBLIC_REGATTA),
                      new DBCondIn('id', DB::prepGetAll(DB::T(DB::TEAM),
                                                        new DBCond('school', $this),
                                                        array('regatta'))));
  }

  /**
   * Get all the accounts which have access to this school.
   *
   * Access is either assigned directly or indirectly.
   *
   * @param String|null $status a possible Account status
   * @param boolean $effective false to ignore permissions and return
   * only assigned values
   *
   * @return Array:Account
   */
  public function getUsers($status = null, $effective = true) {
    $cond = new DBCondIn('id', DB::prepGetAll(DB::T(DB::ACCOUNT_SCHOOL), new DBCond('school', $this->id), array('account')));
    if ($effective !== false) {
      $cond = new DBBool(array(new DBCond('admin', null, DBCond::NE), $cond), DBBool::mOR);
    }
    if ($status !== null) {
      $statuses = Account::getStatuses();
      if (!isset($statuses[$status]))
        throw new InvalidArgumentException("Invalid status provided: $status.");
      $cond = new DBBool(array($cond, new DBCond('status', $status)));
    }
    return DB::getAll(DB::T(DB::ACCOUNT), $cond);
  }

  /**
   * Creates and returns a nick name for the school, which is of
   * appropriate length (no greater than 20 chars)
   *
   * @param String $str the name, usually
   * @return String the display name
   */
  public static function createNick($str) {
    $str = trim($str);
    $str = str_replace('University of', 'U', $str);
    $str = str_replace(' University', '', $str);
    if (mb_strlen($str) > 20)
      $str = mb_substr($str, 0, 20);
    return $str;
  }
}
