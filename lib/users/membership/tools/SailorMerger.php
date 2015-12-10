<?php
namespace users\membership\tools;

use \Sailor;
use \SoterException;

/**
 * Merges one record with another.
 *
 * @author Dayan Paez
 * @version 2015-12-10
 */
class SailorMerger {

  /**
   * Replaces a given sailor with another.
   *
   * @param Sailor $original sailor to be merged.
   * @param Sailor $replacement where to merge.
   * @return Array:Regatta list of affected regattas.
   * @throws SoterException on failure to meet conditions.
   */
  public function merge(Sailor $original, Sailor $replacement) {
    // enforce that the school is the same
    if ($original->school != $replacement->school) {
      throw new SoterException(
        "Replacement sailor must be from the same school as unregistered sailor."
      );
    }

    $affected = array();
    foreach ($original->getRegattas() as $regatta) {
      $rpManager = $regatta->getRpManager();
      $replaced = $rpManager->replaceSailor($original, $replacement);
      if (count($replaced) > 0) {
        $affected[] = $regatta;
      }
    }

    return $affected;
  }
}