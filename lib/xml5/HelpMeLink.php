<?php
namespace xml5;

use \XA;

/**
 * A link that launches the internal help-me form.
 */
class HelpMeLink extends XA {
  const URL = '#help-me';

  public function __construct($content) {
    parent::__construct(self::URL, $content);
  }
}