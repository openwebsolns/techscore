<?php
/*
 * This file is part of TechScore
 *
 * @package tscore/scripts
 */

require_once(dirname(__FILE__) . '/AbstractScript.php');

/**
 * Script to retrieve regattas based on criteria
 *
 * @author Dayan Paez
 * @created 2013-02-27
 */
class Selector extends AbstractScript {

  /**
   * Set the seasons to limit list (empty for no limit)
   *
   * @param Array:Season $seasons list of seasons
   * @throws TSScriptException if non-seasons provided
   */
  public function setSeasons(Array $seasons = array()) {
    foreach ($seasons as $season) {
      if (!($season instanceof Season))
        throw new TSScriptException("Invalid object provided (expected Season).");
    }
    $this->seasons = $seasons;
  }
  private $seasons = array();

  /**
   * Set the types to limit search
   *
   * @param Array:Type the regatta types
   * @throws TSScriptException if non-Types provided
   */
  public function setTypes(Array $types = array()) {
    foreach ($types as $type) {
      if (!($type instanceof Type))
        throw new TSScriptException("Invalid object provided (expected Type).");
    }
    $this->types = $types;
  }
  private $types = array();

  /**
   * Set the scoring types to limit search
   *
   * @param Array:String the scoring types (STANDARD, etc)
   */
  public function setScoringTypes(Array $types = array()) {
    $this->scoring = $types;
  }
  private $scoring = array();

  /**
   * Limit selection by RP status
   *
   * Passing strictly true/false will limit by those that are/are not
   * missing RPs, respectively. Anything else means "do not filter"
   *
   * @param mixed $flag
   */
  public function filterByRpMissing($flag = 'all') {
    $this->rp_missing = $flag;
  }
  private $rp_missing = 'all';

  /**
   * Fetches all regattas matching the given criteria
   *
   */
  public function run() {
    $cond = new DBBool(array(new DBCond(1,1)));

    if (count($this->seasons) > 0) {
      $subcond = new DBBool(array(), DBBool::mOR);
      foreach ($this->seasons as $season) {
        $subcond->add(new DBCond('dt_season', $season->id));
      }
      $cond->add($subcond);
    }

    if (count($this->types) > 0) {
      $subcond = new DBBool(array(), DBBool::mOR);
      foreach ($this->types as $type) {
        $subcond->add(new DBCond('type', $type->id));
      }
      $cond->add($subcond);
    }

    if (count($this->scoring) > 0) {
      $subcond = new DBBool(array(), DBBool::mOR);
      foreach ($this->scoring as $type) {
        $subcond->add(new DBCond('scoring', $type));
      }
      $cond->add($subcond);
    }

    // RP?
    if ($this->rp_missing === true || $this->rp_missing === false) {
      $comparator = ($this->rp_missing) ? DBCondIn::IN : DBCondIn::NOT_IN;
      $cond->add(
        new DBCondIn(
          'id',
          DB::prepGetAll(DB::T(DB::TEAM), new DBCond('dt_complete_rp', null), array('regatta')),
          $comparator
        )
      );
    }

    require_once('regatta/Regatta.php');
    return DB::getAll(DB::T(DB::REGATTA), $cond);
  }

  protected $cli_opts = '[-s]';
  protected $cli_usage = "The following switches are available:

  -s, --seasons   Comma-delimited list of seasons (e.g. f11,s12)
  -t, --types     Comma-delimited list of types (e.g. championship)
  -g, --scoring   Comma-delimited scoring types: standard,combined,team

  --rp-missing    Limit to regattas missing RP
  --no-rp-missing Limit to regattas that are not missing RP";
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');

  // Validate arguments
  $P = new Selector();
  $opts = $P->getOpts($argv);

  // Parse other options
  $seasons = array();
  $types = array();
  $scoring = array();
  $scoring_options = Regatta::getScoringOptions();
  $rp_missing = 'all';
  while (count($opts) > 0) {
    $arg = array_shift($opts);
    switch ($arg) {
    case '-s':
    case '--season':
      if (count($opts) == 0)
        throw new TSScriptException("No season list provided.");
      $opt = array_shift($opts);
      foreach (explode(',', $opt) as $id) {
        $season = DB::getSeason($id);
        if ($season === null)
          throw new TSScriptException("Invalid season provided: $id.");
        $seasons[$season->id] = $season;
      }
      break;

    case '-t':
    case '--types':
      if (count($opts) == 0)
        throw new TSScriptException("No type list provided.");
      $opt = array_shift($opts);
      foreach (explode(',', $opt) as $id) {
        $type = DB::get(DB::T(DB::ACTIVE_TYPE), $id);
        if ($type === null)
          throw new TSScriptException("Invalid type provided: $id.");
        $types[$type->id] = $type;
      }
      break;

    case '-g':
    case '--scoring':
      if (count($opts) == 0)
        throw new TSScriptException("No scoring type list provided.");
      $opt = array_shift($opts);
      foreach (explode(',', $opt) as $id) {
        if (!isset($scoring_options[$id]))
          throw new TSScriptException("Invalid scoring type provided: $id.");
        $scoring[$id] = $id;
      }
      break;

    case '--rp-missing':
      $rp_missing = true;
      break;

    case '--no-rp-missing':
      $rp_missing = false;
      break;

    default:
      throw new TSScriptException("Invalid option: $arg.");
    }
  }

  $P->setSeasons($seasons);
  $P->setTypes($types);
  $P->setScoringTypes($scoring);
  $P->filterByRpMissing($rp_missing);

  $fmt = "%s\n";
  foreach ($P->run() as $reg) {
    printf($fmt, $reg->id);
  }
}
?>