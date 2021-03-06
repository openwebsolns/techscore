<?php
use \charts\RegattaChartCreator;

/*
 * This file is part of TechScore
 *
 * @package tscore-dialog
 */

require_once('tscore/AbstractScoresDialog.php');
require_once('xml5/SVGLib.php');

/**
 * Displays SVG-based charts of race progress
 *
 * @author Dayan Paez
 * @version 2012-10-29
 */
class ScoresChartDialog extends AbstractScoresDialog {

  /**
   * @var Array:Race the races to include in chart
   */
  private $races;
  /**
   * @var String title to use for chart
   */
  private $title;

  /**
   * Create a new rotation dialog for the given regatta
   *
   * @param Account $user the user
   * @param FullRegatta $reg the regatta
   * @param Division $div the optional division to limit races to
   */
  public function __construct(Account $user, FullRegatta $reg, Division $div = null) {
    parent::__construct("Regatta ranking history", $user, $reg);
    if ($reg->scoring == Regatta::SCORING_COMBINED) {
      $div = Division::A();
      $this->title = sprintf("Rank history of combined divisions for %s", $this->REGATTA->name);
    }
    else {
      $this->title = ($div === null) ?
        sprintf("Rank history across all divisions for %s", $this->REGATTA->name) :
        sprintf("Rank history across Division %s for %s", $div, $this->REGATTA->name);
    }
    $this->races = $reg->getScoredRaces($div);
  }

  /**
   * Create and display the score table
   *
   */
  public function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("Regatta ranking history"));
    if (count($this->races) < 2) {
      $p->add(new XWarning("There are insufficient finishes entered for the chart."));
      return;
    }

    SVGAbstractElem::$namespace = 'svg';
    $this->PAGE->set('xmlns:svg', 'http://www.w3.org/2000/svg');


    $p->add(new XP(array(), "The following chart shows the relative rank of the teams as of the race indicated. Note that the races are ordered by number, then division, which may not represent the order in which the races were actually sailed."));
    $p->add(new XP(array(), "The first place team as of a given race will always be at the top of the chart. The spacing from one team to the next shows relative gains/losses made from one race to the next. You may hover over the data points to display the total score as of that race."));

    $elem = RegattaChartCreator::getChart($this->REGATTA);
    if ($elem !== null) {
      $elem->setIncludeHeaders(false);
      $p->add($elem);
    }
  }
}
?>