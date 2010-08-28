<?php
/**
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2010-08-24
 */

/**
 * Creates the report page for the given regatta
 *
 */
class ReportMaker {
  private $regatta;
  private $page;

  /**
   * Creates a new report for the given regatta
   *
   */
  public function __construct(Regatta $reg) {
    $this->regatta = $reg;
  }

  private function fill() {
    if ($this->page !== null) return;

    $this->page = new TPublicPage($this->regatta->get(Regatta::NAME));
    $this->page->addSection(new GenericElement("h1", array(new Text($this->regatta->get(Regatta::NAME)))));
  }

  /**
   * Generates and returns the HTML code for the given regatta. Note that
   * the report is only generated once per report maker
   *
   * @return TPublicPage
   */
  public function getPage() {
    $this->fill();
    return $this->page->toHTML();
  }
}
?>