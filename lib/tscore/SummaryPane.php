<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once('conf.php');

/**
 * Changes the daily summaries for the regatta
 *
 * @author Dayan Paez
 * @version 2010-03-24
 */
class SummaryPane extends AbstractPane {

  /**
   * Creates a new editing pane
   *
   */
  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Summaries", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("Daily summaries"));

    $p->add($form = $this->createForm());
    $start = $this->REGATTA->start_time;
    for ($i = 0; $i < $this->REGATTA->get(Regatta::DURATION); $i++) {
      $today = new DateTime(sprintf("%s + %d days", $start->format('Y-m-d'), $i));
      $comms = $this->REGATTA->getSummary($today);
      $form->add(new FItem($today->format('l, F j'),
			   new XTextArea($today->format('Y-m-d'), $comms,
					 array("rows"=>"5", "cols"=>"50"))));
    }
    $form->add(new XSubmitInput("set_comment", "Add/Update"));
  }

  /**
   * Processes changes to daily summaries
   *
   */
  public function process(Array $args) {
    if (isset($args['set_comment'])) {
      unset($args['set_comment']);
      foreach ($args as $day => $value) {
	try {
	  $today = new DateTime($day);
	  $this->REGATTA->setSummary($today, addslashes(trim($value)));
	} catch (Exception $e) {}
      }
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SUMMARY);
      Session::pa(new PA("Updated summaries"));
    }
    return $args;
  }
}
?>
