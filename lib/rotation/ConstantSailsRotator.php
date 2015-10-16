<?php
namespace rotation;

/**
 * Appropriate for "no rotation".
 *
 * @author Dayan Paez
 * @version 2015-10-19
 */
class ConstantSailsRotator extends SailsRotator {

  public function rotate() {
    return $this->sails;
  }

}