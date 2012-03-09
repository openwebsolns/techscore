<?php
/*
 * This file is part of TechScore
 *
 * @package users
 */

require_once('users/AbstractUserPane.php');

/**
 * Displays the End-User License Agreement for TechScore and
 * requests/processes the signature
 *
 * @author Dayan Paez
 * @version 2010-07-25
 */
class EULAPane extends AbstractUserPane {

  public function __construct(Account $user) {
    parent::__construct("Sign agreement", $user);
  }

  /**
   * Shows the license agreement in an uneditable textarea and then
   * prompts for signature
   *
   */
  public function fillHTML(Array $args) {
    $filename = sprintf("%s/%s", dirname(__FILE__), "EULA.txt");
    $license  = (file_exists($filename)) ?
      file_get_contents($filename) :
      "I agree to use TechScore responsibly.";

    $this->PAGE->addContent($p = new XPort("License Agreement"));
    $p->add(new XP(array(), "Before using TechScore, you must read and agree to the terms below. These are short terms of usage that outline what we expect of TechScore users and your responsibilities as an official scorer. Please read it carefully before clicking on the checkbox below."));
    $p->add(new XTextArea("license", $license, array("readonly"=>"readonly",
						     "style"=>"width:100%;",
						     "cols"=>"80",
						     "rows"=>"8")));
    $p->add($f = new XForm("/license-edit", XForm::POST));
    $f->add($i = new FItem(new XCheckBoxInput("agree", "1", array("id"=>"agree")),
			   new XLabel("agree", "I agree with the terms above")));
    $i->set("style", "margin:1em 0em;background:#ccc;border:black;padding:0.25em;font-size:110%;");
    $f->add(new XSubmitInput("agree-form", "Sign"));
  }

  public function process(Array $args) {
    if (isset($args['agree-form'])) {
      DB::$V->reqInt($args, 'agree', 1, 5, "Please check the checkbox to continue.");
      $this->USER->status = Account::STAT_ACTIVE;
      DB::set($this->USER);
      Session::pa(new PA("Thank you for activatating your account!"));
      WS::go('/');
    }
    return array();
  }
}
?>