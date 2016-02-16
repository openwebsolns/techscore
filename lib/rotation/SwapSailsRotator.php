<?php
namespace rotation;

use \InvalidArgumentException;

/**
 * Swap (odds up, evens down) sails rotator.
 *
 * @author Dayan Paez
 * @version 2015-10-19
 */
class SwapSailsRotator extends SailsRotator {

  private $counter;

  public function __construct(Array $sails) {
    parent::__construct($sails);
    if (count($this->sails) % 2 != 0) {
      throw new InvalidArgumentException("SwapSailsRotator is only available for even number of sails.");
    }
    $this->counter = 0;
  }

  public function rotate() {
    $count = count($this->sails);
    $sails = array_fill(0, $count, null);
    for ($i = 0; $i < $count; $i += 2) {
      // Rotate up
      $sails[$i] = $this->sails[($i + $this->counter) % $count];
      // Rotate down
      $sails[$i + 1] = $this->sails[($i + 1 - ($this->counter % $count) + $count) % $count];
    }
    $this->counter++;
    return $sails;
  }

}