<?php
namespace data;

use \Regatta;
use \Sailor;

use \XA;
use \XSpan;
use \XQuickTable;
use \XStrong;
use \XTime;

/**
 * Table of regattas for public sailor profiles.
 *
 * @author Dayan Paez
 * @version 2015-06-10
 */
class SailorRegattaTable extends XQuickTable {

  /**
   * @var Sailor the sailor for whom the table tolls.
   */
  private $sailor;
  private $rowCount;

  public function __construct(Sailor $sailor) {
    parent::__construct(
      array('class'=>'participation-table'),
      array(
        "Name",
        "Host",
        // "Type",
        // "Scoring",
        "Date",
        // "Status",
        "Position",
        "Place finish",
      )
    );
    $this->sailor = $sailor;
    $this->rowCount = 0;
  }

  /**
   * Add a new row using given regatta.
   *
   * @param Regatta $regatta the regatta to add.
   */
  public function addRegattaRow(Regatta $regatta) {
    $link = new XA(
      $regatta->getURL(),
      new XSpan($regatta->name, array('itemprop'=>'name')),
      array('itemprop'=>'url')
    );

    $placement = $this->getPlacementIn($regatta);

    $this->addRow(
      array(
        $link,
        $regatta->getHostVenue(),
        // $regatta->type,
        // $regatta->getDataScoring(),
        new XTime($regatta->start_time, 'M d', array('itemprop'=>'startDate')),
        // ($regatta->finalized === null) ? "Pending" : new XStrong("Official"),
        $this->getBoatPosition($regatta),
        $placement,
      ),
      array(
        'class' => sprintf('row' . ($this->rowCount % 2)),
        'itemprop'=>'event',
        'itemscope'=>'itemscope',
        'itemtype'=>'http://schema.org/SportsEvent',
      )
    );
    $this->rowCount++;
  }

  /**
   * Helper method: extract positions in boat.
   *
   * @param Regatta $regatta the regatta.
   * @return Xmlable skipper, crew, reserve.
   */
  private function getBoatPosition(Regatta $regatta) {
    $rpManager = $regatta->getRpManager();
    $positions = array();
    foreach ($rpManager->getParticipation($this->sailor) as $rp) {
      $positions[$rp->boat_role] = ucfirst($rp->boat_role);
    }

    // Assume reserve if no boat role found.
    if (count($positions) == 0) {
      return "Reserve";
    }
    return implode(", ", $positions);
  }

  /**
   * Gets the human-readable placement string in given regatta.
   *
   * This string depends on the regatta type, and may be "divisional"
   * or overall.
   *
   * @param Regatta the regatta whose placement to get.
   * @return Xmlable like 3/18.
   */
  private function getPlacementIn(Regatta $regatta) {
    $manager = $regatta->getRpManager();
    $rps = $manager->getParticipation($this->sailor);

    $shouldDistinguishDivision = (
      $regatta->scoring == Regatta::SCORING_STANDARD
      && count($regatta->getDivisions()) > 1
    );
    $shouldLinkToFullScores = ($regatta->scoring == Regatta::SCORING_TEAM);

    // If a sailor has participated in multiple teams, which
    // should not happen, merely report their place for the first
    // team encountered.
    $team = null;
    $placement = array();
    $num_teams = count($regatta->getTeams());
    foreach ($rps as $rp) {
      if ($team === null || $team->id == $rp->team->id) {
        $team = $rp->team;
        if ($shouldDistinguishDivision) {
          $rank = $team->getRank($rp->division);
          if ($rank !== null) {
            $place = sprintf(
              '%d/%d (%s Div)',
              $rank->rank,
              $num_teams,
              $rp->division
            );
            $link = sprintf('%s%s/', $regatta->getURL(), $rp->division);
            $placement[(string) $rp->division] = new XA($link, $place);
          }
        }
        elseif ($team->dt_rank !== null) {
          $place = sprintf('%d/%d', $team->dt_rank, $num_teams);
          $link = $regatta->getURL();
          if ($shouldLinkToFullScores) {
            $link .= sprintf('full-scores/#team-%s', $team->id);
          }

          $placement[] = new XA($link, $place);
        }
      }
    }

    if (count($placement) == 0) {
      return 'N/A';
    }

    ksort($placement);
    $span = new XSpan("", array('class'=>'sailor-placement-container'));
    foreach ($placement as $place) {
      $span->add($place);
    }
    return $span;
  }
}