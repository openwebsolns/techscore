<?php
/*
 * This file is part of TechScore
 *
 * @package tscore-dialog
 */

require_once('tscore/AbstractScoresDialog.php');

/**
 * Displays SVG-based charts of race progress
 *
 * @author Dayan Paez
 * @version 2012-10-29
 */
class ScoresChartDialog extends AbstractScoresDialog {
  /**
   * Create a new rotation dialog for the given regatta
   *
   * @param Regatta $reg the regatta
   */
  public function __construct(Regatta $reg) {
    parent::__construct("Regatta ranking history", $reg);
  }

  /**
   * Create and display the score table
   *
   */
  public function fillHTML(Array $args) {
    require_once('xml5/SVGLib.php');
    SVGAbstractElem::$namespace = 'svg';
    $this->PAGE->set('xmlns:svg', 'http://www.w3.org/2000/svg');


    $this->PAGE->addContent($p = new XPort("Regatta ranking history"));
    $p->add(new XP(array(), "The following chart shows the relative rank of the teams as of the race indicated. Note that the races are ordered by number, then division, which may not represent the order in which the races were actually sailed."));
    $p->add(new XP(array(), "The first place team as of a given race will always be at the top of the chart. The spacing from one team to the next shows relative gains/losses made from one race to the next. You may hover over the data points to display the total score as of that race."));
    foreach ($this->getTable() as $elem) {
      if ($elem instanceof XDoc)
	$elem->setIncludeHeaders(false);
      $p->add($elem);
    }
  }

  public function getTable($link_schools = false) {
    require_once('charts/RaceProgressChart.php');
    $maker = new RaceProgressChart($this->REGATTA);
    return array($maker->getChart());
  }
}
?>