<?php
namespace utils\callbacks;

use \DB;

/**
 * Returns true if any of the given settings are NOT null.
 *
 * @author Dayan Paez
 * @version 2016-03-24
 */
class IsAvailableForSettings implements IsAvailableCallback {

  private $possibleSettings;

  public function __construct(Array $possibleSettings) {
    $this->possibleSettings = $possibleSettings;
  }

  public function isAvailable() {
    foreach ($this->possibleSettings as $setting) {
      if (DB::g($setting)) {
        return true;
      }
    }
    return false;
  }
}