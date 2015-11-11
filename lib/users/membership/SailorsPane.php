<?php
namespace users\membership;

use \users\AbstractUserPane;
use \xml5\XExternalA;
use \xml5\PageWhiz;

use \Account;
use \Permission;
use \SoterException;

/**
 * Manages the database of sailors.
 *
 * @author Dayan Paez
 * @version 2015-11-12
 */
class SailorsPane extends AbstractUserPane {

  const NUM_PER_PAGE = 50;
  const EDIT_KEY = 'id';

  const SUBMIT_DELETE = 'delete-sailor';
  const SUBMIT_EDIT = 'edit-sailor';
  const SUBMIT_ADD = 'add-sailor';

  /**
   * @var boolean true if given user can perform edit operations.
   */
  private $canEdit;

  public function __construct(Account $user) {
    parent::__construct("Sailors", $user);
    $this->canEdit = $this->USER->can(Permission::EDIT_SAILOR_LIST);
  }

  public function fillHTML(Array $args) {
    // TODO
  }

  public function process(Array $args) {
    throw new SoterException("Not yet implemented.");
  }

}