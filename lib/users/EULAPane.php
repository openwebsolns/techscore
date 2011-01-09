<?php
/**
 * This file is part of TechScore
 *
 */

require_once('conf.php');

/**
 * Displays the End-User License Agreement for TechScore and
 * requests/processes the signature
 *
 * @author Dayan Paez
 * @version 2010-07-25
 */
class EULAPane extends AbstractUserPane {

  public function __construct(User $user) {
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

    $this->PAGE->addContent($p = new Port("License Agreement"));
    $p->addChild(new Para("Before using TechScore, you must read and agree to the terms below. " .
			  "These are short terms of usage that outline what we expect of TechScore " .
			  "users and your responsibilities as an official scorer. Please read it " .
			  "carefully before clicking on the checkbox below."));
    $p->addChild(new FTextarea("license", $license, array("readonly"=>"readonly",
							  "style"=>"width:100%;",
							  "cols"=>"80",
							  "rows"=>"8")));
    $p->addChild($f = new Form("license-edit"));
    $f->addChild($i = new FItem(new FCheckBox("agree", "1", array("id"=>"agree")),
				new Label("agree", "I agree with the terms above")));
    $i->addAttr("style", "margin:1em 0em;background:#ccc;border:black;padding:0.25em;font-size:110%;");
    $f->addChild(new FSubmit("agree-form", "Sign"));
  }

  public function process(Array $args) {
    if (isset($args['agree-form'])) {
      if (isset($args['agree']) && $args['agree']) {
	$this->USER->set(User::STATUS, "active");
	$_SESSION['ANNOUNCE'][] = new Announcement("Thank you for activatating your account!");
	WebServer::go('/');
      }
      else {
	$_SESSION['ANNOUNCE'][] = new Announcement("You must sign checkbox to continue.",
						   Announcement::ERROR);
      }
    }
    return $args;
  }
}
?>