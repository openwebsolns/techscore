<?php
use \users\AbstractUserPane;

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
    if ($user->status != Account::STAT_ACCEPTED) {
      Session::pa(new PA("This page is not available.", PA::I));
      WS::go('/');
    }
  }

  /**
   * Shows the license agreement in an uneditable textarea and then
   * prompts for signature
   *
   */
  public function fillHTML(Array $args) {
    $filename = DB::get(DB::T(DB::TEXT_ENTRY), Text_Entry::EULA);
    $license = ($filename !== null) ? new XRawText($filename->html) : new XP(array(), sprintf("I agree to use %s responsibly.", DB::g(STN::APP_NAME)));

    $this->PAGE->addContent($p = new XPort("License Agreement"));
    $p->add(new XP(array(), sprintf("Before using %s, you must read and agree to the terms below. These are short terms of usage that outline what is expected of %s users and your responsibilities as an official scorer. Please read it carefully before clicking on the checkbox below.", DB::g(STN::APP_NAME), DB::g(STN::APP_NAME))));
    $p->add(new XDiv(array('id'=>'license'), array($license)));
    $p->add($f = $this->createForm());
    $f->add($i = new FReqItem(new XCheckBoxInput('agree', '1', array('id'=>'agree')),
                              new XLabel('agree', "I agree with the terms above")));
    $i->set('style', 'margin:1em 0em;background:#ccc;border:black;padding:0.25em;font-size:110%;');
    $f->add(new XSubmitInput('agree-form', "Sign"));
  }

  public function process(Array $args) {
    if (isset($args['agree-form'])) {
      DB::$V->reqInt($args, 'agree', 1, 5, "Please check the checkbox to continue.");
      $this->USER->status = Account::STAT_ACTIVE;
      DB::set($this->USER);
      Session::pa(new PA("Thank you for activating your account!"));
      $this->redirect('');
    }
    return array();
  }
}
?>