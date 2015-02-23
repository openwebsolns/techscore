<?php
/*
 * This file is part of Techscore
 */

/**
 * Interface for resources that can be publically published.
 *
 */
interface Publishable {
  /**
   * Return the public path (i.e. /path/to/resource/).
   *
   * @return String slug.
   */
  public function getURL();
}
