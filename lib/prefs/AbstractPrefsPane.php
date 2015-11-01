<?php
namespace prefs;

use \users\PaneException;
use \users\AbstractUserPane;

use \Account;
use \School;
use \Session;

use \XFileForm;
use \XForm;
use \XHiddenInput;

/**
 * Parent class for Preferences page. Makes certain that school object
 * exists.
 *
 * @author Dayan Paez
 * @version 2012-03-11
 */
abstract class AbstractPrefsPane extends AbstractUserPane {
  /**
   * Creates a new pane for the given USER. The school will be parsed
   * from the $_GET variable directly, and if permissions do not
   * allow, a redirect will be issued.
   *
   * @param String $title the title of the pane
   * @param Account $user the user
   * @param School $school the school
   * @throws PaneException
   */
  public function __construct($title, Account $user, School $school) {
    parent::__construct($title, $user);
    if (!$user->hasSchool($school))
      throw new PaneException(sprintf("No permissions to edit school %s.", $school));
    $this->SCHOOL = $school;
  }

  /**
   * Creates a new form HTML element using the page_name attribute
   *
   * @param Const $method XForm::POST or XForm::GET
   * @return XForm
   */
  protected function createForm($method = XForm::POST) {
    $form = new XForm(sprintf('/prefs/%s/%s', $this->SCHOOL->id, $this->page_url), $method);
    if ($method == XForm::POST && class_exists('Session'))
      $form->add(new XHiddenInput('csrf_token', Session::getCsrfToken()));
    return $form;
  }

  protected function createFileForm() {
    $form = new XFileForm(sprintf('/prefs/%s/%s', $this->SCHOOL->id, $this->page_url));
    if (class_exists('Session'))
      $form->add(new XHiddenInput('csrf_token', Session::getCsrfToken()));
    return $form;
  }

}
