<?php
namespace data;

use \FullRegatta;
use \Regatta;
use \Sailor;

use \XA;
use \XSpan;

/**
 * Encapsulates a human-readable summary of a sailor's place finish in
 * a regatta, suitable for tables and reports.
 *
 * @author Dayan Paez
 * @version 2016-07-08
 */
class SailorPlaceFinishDisplay {

  private $sailor;
  private $regatta;
  /**
   * @var Array:String the ordered list of textual displays.
   */
  private $places;
  /**
   * @var Array:String corresponding list of links.
   */
  private $links;

  public function __construct(Sailor $sailor, FullRegatta $regatta) {
    $this->sailor = $sailor;
    $this->regatta = $regatta;
    $this->places = array();
    $this->links = array();
    $this->calculateDisplay();
  }

  private function calculateDisplay() {
    $manager = $this->regatta->getRpManager();
    $rps = $manager->getParticipation($this->sailor);

    $shouldDistinguishDivision = (
      $this->regatta->scoring == Regatta::SCORING_STANDARD
      && count($this->regatta->getDivisions()) > 1
    );
    $shouldLinkToFullScores = ($this->regatta->scoring == Regatta::SCORING_TEAM);

    // If a sailor has participated in multiple teams, which
    // should not happen, merely report their place for the first
    // team encountered.
    $team = null;
    $num_teams = count($this->regatta->getTeams());
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
            $link = sprintf('%s%s/', $this->regatta->getURL(), $rp->division);
            $this->places[(string) $rp->division] = $place;
            $this->links[(string) $rp->division] = $link;
          }
        }
        elseif ($team->dt_rank !== null) {
          $place = sprintf('%d/%d', $team->dt_rank, $num_teams);
          $link = $this->regatta->getURL();
          if ($shouldLinkToFullScores) {
            $link .= sprintf('full-scores/#team-%s', $team->id);
          }

          $this->places[] = $place;
          $this->links[] = $link;
        }
      }
    }

    // TODO: multisort! ksort($placement);
  }

  public function places() {
    return $this->places;
  }

  public function links() {
    return $this->links;
  }

  public function hasPlaces() {
    return count($this->places) > 0;
  }

  /**
   * Returns an Xmlable representation of this data.
   *
   * @return Xmlable ready for inclusion.
   */
  public function asXmlable() {
    if (!$this->hasPlaces()) {
      return 'N/A';
    }

    $span = new XSpan("", array('class'=>'sailor-placement-container'));
    foreach ($this->places as $key => $place) {
      $span->add(new XA($this->links[$key], $place));
    }
    return $span;
  }
}