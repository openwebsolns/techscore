<?php
use \charts\RegattaChartCreator;
use \data\DivisionScoresTableCreator;

/**
 * This file is part of TechScore
 *
 * @package tscore-dialog
 */

require_once('tscore/AbstractScoresDialog.php');
require_once('xml5/SVGLib.php');

/**
 * Displays the scores table for a given regatta's division
 *
 * @author Dayan Paez
 * @version 2010-02-01
 */
class ScoresDivisionDialog extends AbstractScoresDialog {

  private $division;

  /**
   * Create a new rotation dialog for the given regatta
   *
   * @param FullRegatta $reg the regatta
   * @param Division $div the division
   * @throws InvalidArgumentException if the division is not in the
   * regatta
   */
  public function __construct(FullRegatta $reg, Division $div) {
    parent::__construct("Race results", $reg);
    if (!in_array($div, $reg->getDivisions())) {
      throw new InvalidArgumentException("No such division ($div) in this regatta.");
    }
    $this->division = $div;
  }

  /**
   * Create and display the score table
   *
   */
  public function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort(sprintf("Division %s results", $this->division)));
    $races = $this->REGATTA->getScoredRaces($this->division);
    if (count($races) == 0) {
      $p->add(new XWarning( sprintf("There are no finishes for division %s.", $this->division)));
      return;
    }

    $maker = new DivisionScoresTableCreator($this->REGATTA, $this->division);
    $p->add($maker->getScoreTable());
    $legend = $maker->getLegendTable();
    if ($legend !== null) {
      $p->add(new XHeading("Tiebreaker legend"));
      $p->add($legend);
    }

    // Also add a chart
    if (count($races) > 1) {
      $this->PAGE->set('xmlns:svg', 'http://www.w3.org/2000/svg');
      $this->PAGE->addContent($p = new XPort(sprintf("Rank history for division %s", $this->division)));
      $p->add(new XP(array(), "The first place team as of a given race will always be at the top of the chart. The spacing from one team to the next shows relative gains/losses made from one race to the next. You may hover over the data points to display the total score as of that race."));

      SVGAbstractElem::$namespace = 'svg';

      $chart = RegattaChartCreator::getChart($this->REGATTA, $this->division);
      if ($chart !== null) {
        $chart->setIncludeHeaders(false);
        $p->add($chart);
      }
    }
  }
}
