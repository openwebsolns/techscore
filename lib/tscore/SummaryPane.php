<?php
/**
 * This file is part of TechScore
 */
require_once('conf.php');

/**
 * Changes the daily summaries for the regatta
 *
 * @author Dayan Paez
 * @created 2010-03-24
 */
class SummaryPane extends AbstractPane {

  /**
   * Creates a new editing pane
   *
   */
  public function __construct(User $user, Regatta $reg) {
    parent::__construct("Daily Summaries", $user, $reg);
    $this->title = "Summaries";
  }

  protected function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new Port("Daily summaries"));

    $p->addChild($form = new Form(sprintf("edit/%s/summary", $this->REGATTA->id())));
    $start = $this->REGATTA->get(Regatta::START_TIME);
    for ($i = 0; $i < $this->REGATTA->get(Regatta::DURATION); $i++) {
      $today = new DateTime(sprintf("%s + %d days", $start->format('Y-m-d'), $i));
      $comms = $this->REGATTA->getSummary($today);
      $form->addChild(new FItem($today->format('l, F j'),
				new FTextArea($today->format('Y-m-d'), $comms,
					      array("rows"=>"5", "cols"=>"50"))));
    }
    $form->addChild(new FSubmit("set_comment", "Add/Update"));
  }

  /**
   * Processes changes to daily summaries
   *
   */
  public function process(Array $args) {
    if (isset($args['set_comment'])) {
      foreach ($args as $day => $value) {
	try {
	  $today = new DateTime($day);
	  $this->REGATTA->setSummary($today, addslashes(trim($value)));
	} catch (Exception $e) {}
      }
      $this->announce(new Announcement("Updated summaries"));
    }
    return $args;
  }

  public function isActive() { return true; }
}
?>