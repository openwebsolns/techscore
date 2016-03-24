<?php
namespace utils\callbacks;

interface IsAvailableCallback {
  /**
   * Determines availability.
   *
   * @return true if considered available.
   */
  public function isAvailable();
}