<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

/**
 * Pane designed to warn and effectuate regatta deletions.
 *
 * @author Dayan Paez
 * @version 2012-11-26
 */
class DeleteRegattaPane extends AbstractPane {

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Delete regatta", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("Delete regatta"));
    $p->add(new XP(array('class'=>'warning'),
                   array("Deleting a regatta is ", new XStrong("permanent"), ". The regatta and all information associated with it will be permanently deleted from the database. If you wish to merely remove the regatta from publication, while keeping it around, go to ", new XA(WS::link(sprintf('/score/%s', $this->REGATTA->id)), "the settings page"), " and mark it as \"Private\" instead.")));

    $p->add($form = $this->createForm());
    $form->add($fitem = new FReqItem("Confirm:", new XCheckboxInput("confirm", 1, array('id'=>'chk-confirm'))));
    $fitem->add(new XLabel('chk-confirm', " I understand that all data from this regatta will be deleted."));
    $form->add(new XSubmitP('delete', "Delete", array(), true));
  }

  public function process(Array $args) {
    if (isset($args['delete'])) {
      DB::$V->reqInt($args, 'confirm', 1, 2, "Please check the \"Confirm\" box before deleting.");
      $this->REGATTA->inactive = new DateTime();
      $this->REGATTA->setData(); // implies update to regatta object
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_DETAILS);
      Session::pa(new PA(sprintf("Regatta \"%s\" has been deleted.", $this->REGATTA->name)));
    }
  }
}
?>