<?php
namespace rotation;

/**
 * Standard (+1) sails rotator.
 *
 * @author Dayan Paez
 * @version 2015-10-19
 */
class StandardSailsRotator extends SailsRotator {

  public function rotate() {
    $current = $this->sails;

    $first = $this->sails[0];
    $count = count($this->sails);
    for ($i = 0; $i < $count - 1; $i++) {
      $this->sails[$i] = $this->sails[$i + 1];
    }
    $this->sails[$count - 1] = $first;

    return $current;
  }

}