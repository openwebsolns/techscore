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
    $s = clone($this->REGATTA->start_time);
    for ($i = 0; $i < $this->REGATTA->getDuration(); $i++) {
      $comms = $this->REGATTA->getSummary($s);
      $form->add(new FItem($s->format('l, F j'), new XTextArea($s->format('Y-m-d'), $comms, array("rows"=>"12", "cols"=>"80"))));
      $s->add(new DateInterval('P1DT0H'));
    }
    $form->add(new XSubmitInput("set_comment", "Add/Update"));
  }

  /**
   * Processes changes to daily summaries
   *
   */
  public function process(Array $args) {
    if (isset($args['set_comment'])) {
      $s = clone($this->REGATTA->start_time);
      for ($i = 0; $i < $this->REGATTA->getDuration(); $i++) {
        $day = $s->format('Y-m-d');
        $this->REGATTA->setSummary($s, DB::$V->incString($args, $day, 1, 16000, null));
        $s->add(new DateInterval('P1DT0H'));
      }
      Session::pa(new PA("Updated summaries"));
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SUMMARY);
    }
    return $args;
  }
}
?>
