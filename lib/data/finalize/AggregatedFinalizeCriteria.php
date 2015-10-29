<?php
namespace data\finalize;

use \InvalidArgumentException;
use \Regatta;

/**
 * A composite registry of all other criteria.
 *
 * @author Dayan Paez
 * @version 2015-10-28
 */
class AggregatedFinalizeCriteria extends FinalizeCriterion {

  private $criteria;

  public function canApplyTo(Regatta $regatta) {
    foreach ($this->getCriteria() as $criterion) {
      if ($criterion->canApplyTo($regatta)) {
        return true;
      }
    }
    return false;
  }

  public function getFinalizeStatuses(Regatta $regatta) {
    $list = array();
    foreach ($this->getCriteria() as $criterion) {
      if ($criterion->canApplyTo($regatta)) {
        foreach ($criterion->getFinalizeStatuses($regatta) as $status) {
          $list[] = $status;
        }
      }
    }
    return $list;
  }

  public function setCriteria(Array $criteria) {
    $this->criteria = array();
    foreach ($criteria as $criterion) {
      if (!($criterion instanceof FinalizeCriterion)) {
        throw new InvalidArgumentException("Argument list must contain FinalizeCriterion objects.");
      }
      $this->criteria[] = $criterion;
    }
  }

  private function getCriteria() {
    if ($this->criteria === null) {
      $this->criteria = array(
        new UnsailedMiddleRacesCriterion(),
        new Pr24Criterion(),
        new MissingSummariesCriterion(),
        new CompleteRpCriterion(),
        new MinimumRoundCompletionCriterion(),
      );
    }
    return $this->criteria;
  }
}