<?php
/*
 * This file is part of TechScore
 *
 * @package xml5
 */

require_once('xml5/TS.php');

/**
 * HTML page for e-mails that link back to Techscore
 *
 * Note: TEmailPage, which came before, is used for generic,
 * public-facing email messages.
 *
 * @author Dayan Paez
 * @created 2014-10-17
 */
class TEmailMessage extends XPage {

  private $editor;

  /**
   * Add arg. to the correct place in <body>
   *
   * @param Xmlable $elem
   */
  public function append($elem) {
    $this->body->add($elem);
  }

  /**
   * Convert plain text to HTML
   *
   * @param String $plain_text the input
   * @return Array:Xmlable list of HTML elements
   */
  public function convert($plain_text) {
    if ($this->editor === null) {
      require_once('xml5/TSEditor.php');
      $this->editor = new TSEditor();
    }
    return $this->editor->parse($plain_text);
  }

  /**
   * Convenience method to add plain text
   *
   * @param String $plain_text
   */
  public function convertAndAppend($plain_text) {
    foreach ($this->convert($plain_text) as $sub)
      $this->append($sub);
  }
}
?>