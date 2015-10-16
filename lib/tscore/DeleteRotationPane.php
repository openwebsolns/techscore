<?php
namespace tscore;

use \AbstractPane;
use \Account;
use \Regatta;
use \Session;
use \SoterException;
use \UpdateManager;
use \UpdateRequest;
use \XP;
use \XPort;
use \XSubmitP;
use \XWarning;

/**
 * Remove the set rotation, if one exists.
 *
 * @author Dayan Paez
 * @version 2015-10-17
 */
class DeleteRotationPane extends AbstractPane {

  const SUBMIT_INPUT_NAME = 'remove-rotation';

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Delete rotation", $user, $reg);
  }

  /**
   * Offers to delete a rotation.
   *
   */
  protected function fillHTML(Array $args) {
    $rotationManager = $this->REGATTA->getRotationManager();
    if (!$rotationManager->isAssigned()) {
      $this->PAGE->addContent(new XWarning("No rotation assigned."));
      return;
    }

    $this->PAGE->addContent($p = new XPort("Remove rotation"));
    $p->add($form = $this->createForm());
    $form->add(new XP(array(), "You can replace an existing rotation simply by creating a new one. Note that rotation changes will not affect finishes already entered."));
    $form->add(new XP(array(), "If you wish to not use rotations at all, click the button below. You will still be able to enter finishes using team names instead of sail numbers."));
    $form->add(new XSubmitP(self::SUBMIT_INPUT_NAME, "Remove rotation", array(), true));
  }

  /**
   * Actually deletes the rotation.
   */
  public function process(Array $args) {
    // ------------------------------------------------------------
    // 1b. remove rotation
    // ------------------------------------------------------------
    if (array_key_exists(self::SUBMIT_INPUT_NAME, $args)) {
      $rotationManager = $this->REGATTA->getRotationManager();
      if (!$rotationManager->isAssigned())
        throw new SoterException("Rotations are not assigned.");
      $rotationManager->reset();
      $rotationManager->removeFleetRotation();

      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      Session::info("Rotations removed.");
      $this->redirect('rotations');
    }
  }
}