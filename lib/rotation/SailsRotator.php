<?php
namespace rotation;

/**
 * Rotate a set of sails.
 *
 * @author Dayan Paez
 * @version 2015-10-19
 */
abstract class SailsRotator {

  /**
   * @var Array 0-based array of sails.
   */
  protected $sails;

  /**
   * Create a new rotator to act on given list of sails.
   *
   * @param Array $sails the sails to rotate.
   */
  public function __construct(Array $sails) {
    $this->sails = array_values($sails);
  }

  /**
   * Return the list of sails, in the first or next order.
   *
   * @return Array the list of sails provided in the constructor.
   */
  abstract public function rotate();
}
