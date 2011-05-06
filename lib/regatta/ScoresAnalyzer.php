<?php
/**
 * This file is part of Techscore
 *
 * @author Dayan Paez
 * @created 2011-05-05
 * @package regatta
 */

/**
 * Analyzes scores, like for the All American report, for instance
 *
 * @author Dayan Paez
 * @version 2011-05-05
 */
class ScoresAnalyzer {

  private $regattas;

  /**
   * Creates a new ScoresAnalyzer for the given list of Regatta IDs,
   * which I do not check to see if they make sense or not; but
   * understand that it will use 'dt_team' to do its magic, which
   * means that, at least, the regattas need to be "public"
   */
  public function __construct(Array $regs) {
    $this->regattas = $regs;
  }

  /**
   * Gets all the sailors which have placed AT LEAST as high as the
   * given place finish in the given division
   *
   */
  public function getHighFinishers(Division $div, $place) {
    $q = sprintf('select distinct %s from %s where id in (select sailor from rp where team in (select team from dt_team_division where rank >= %d and division = "%s") and race in (select id from race where regatta in (%s)))',
		 Sailor::FIELDS, Sailor::TABLES, $place, $div, implode(',', $this->regattas));
    $q = Preferences::query($q);
    $list = array();
    while ($obj = $q->fetch_object("Sailor"))
      $list[] = $obj;
    return $list;
  }
  
}
?>