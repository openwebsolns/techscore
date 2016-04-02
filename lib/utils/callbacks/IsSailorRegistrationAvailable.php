<?php
namespace utils\callbacks;

use \DB;
use \STN;

/**
 * Determines whether sailor registration is available.
 *
 * @author Dayan Paez
 * @version 2016-04-01
 */
class IsSailorRegistrationAvailable implements IsAvailableCallback {

  public function isAvailable() {
    if (!DB::g(STN::ENABLE_SAILOR_REGISTRATION)) {
      return false;
    }
    if (DB::getStudentRole() === null) {
      return false;
    }
    if (!DB::g(STN::ALLOW_SAILOR_REGISTRATION)) {
      return false;
    }
    return true;
  }

}