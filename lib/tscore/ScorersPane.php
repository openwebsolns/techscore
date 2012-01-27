<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once('conf.php');

/**
 * Displays and edits scorers for a given regatta
 *
 */
class ScorersPane extends AbstractPane {

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Scorers", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    // ------------------------ Scorers -------------------//
    // Forms to remove scorers
    $this->PAGE->addContent($p = new XPort("Approved Scorers"));
    $p->addHelp("node9.html#SECTION00522000000000000000");

    // Get scorers
    $p->add($tab = new XQuickTable(array(), array("Name", "Affiliation", "")));
    $scorers = array();
    foreach ($this->REGATTA->getScorers() as $s) {
      $scorers[$s->id] = $s;

      // Create form to delete scorer
      $f2 = $this->createForm();
      $hidden = new XHiddenInput("scorer", $s->id);
      $button = new XSubmitInput("delete_scorer", "Remove scorer", array("style"=>"width:100%"));
      $f2->add($hidden);
      $f2->add($button);
      if ($s->id === $this->USER->id) {
	$button->set("disabled", "disabled");
	$button->set("title", "You cannot delete yourself from the regatta.");
      }

      // Fill row
      $tab->addRow(array(new XA("mailto:" . $s->id, $s->getName()), $s->school->nick_name, $f2));
    }
    if (count($scorers) == 1) {
      $button->set("disabled", "disabled");
      $button->set("title", "You cannot delete the only scorer in the regatta.");
    }

    // Form to add scorers
    $this->PAGE->addContent($p = new XPort("Add scorers"));
    $p->addHelp("node9.html#SECTION00522100000000000000");
    $p->add($s_form = $this->createForm());
    // Conferences
    //   -Get chosen_conference
    $chosen_conf = (isset($args['conference'])) ? 
      DB::getConference($args['conference']) : 
      $this->USER->school->conference;

    if ($chosen_conf === null) {
      Session::pa(new PA("Invalid conference chosen. Defaulting to your own.", PA::I));
      $chosen_conf = $this->USER->school->conference;
    }
      
    $confs = array();
    foreach (DB::getConferences() as $conf)
      $confs[$conf->id] = $conf;
        
    $s_form->add($fi = new FItem("Conference:", $sel = XSelect::fromArray('conference', $confs, $chosen_conf->id)));
    $sel->set("onchange","submit('this')");

    // Add accessible submit button
    $fi->add(new XSubmitAccessible("update_conf", "Update"));

    // Accounts form
    $p->add($s_form = $this->createForm());
    $s_form->set('id', 'list');

    // Get accounts for this conference
    $NPP = 15;
    $accounts = $chosen_conf->getUsers();
    $count = count($accounts);
    $npp = ceil($count / $NPP);
    $num = DB::$V->incInt($_GET, 'r', 1, $npp + 1, 1);
    require_once('xml5/LinksDiv.php');
    if ($count > 0) {
      $s_form->add(new XP(array(), "Choose an account to add from the list below and click \"Add scorer\" below."));
      $s_form->add($l = new LinksDiv($npp, $num, sprintf('/score/%s/scorers', $this->REGATTA->id), array(), 'r', '#list'));
      $s_form->add($tab = new XQuickTable(array(), array("", "First name", "Last name", "School")));
      for ($i = $NPP * ($num - 1); $i < $NPP * $num && $i < $count; $i++) {
	$user = $accounts[$i];
	if (!isset($scorers[$user->id])) {
	  $id = 's-' . $user->id;
	  $tab->addRow(array(new XRadioInput('account', $user->id, array('id'=>$id)),
			     new XTD(array('class'=>'left'), new XLabel($id, $user->first_name)),
			     new XTD(array('class'=>'left'), new XLabel($id, $user->last_name)),
			     new XLabel($id, $user->school->id)));
	}
      }
      $s_form->add($l);
      $s_form->add(new XSubmitP("add_scorer", "Add scorer"));
    }
    else
      $s_form->add(new XMessage("There are no accounts left to register in this conference. Please try a different one."));
    
  }

  public function process(Array $args) {

    // ------------------------------------------------------------
    // Change conference
    // ------------------------------------------------------------
    if (isset($args['conference']) && is_numeric($args['conference'])) {
      if (!($conf = DB::getConference($args['conference']))) {
	unset($args['conference']);
	Session::pa(new PA("Invalid conference", PA::E));
      }
      else {
	$args['conference'] = $conf;
      }
    }

    // ------------------------------------------------------------
    // Delete scorer
    // ------------------------------------------------------------
    if (isset($args['delete_scorer']) &&
	isset($args['scorer'])) {
      $account = DB::getAccount($args['scorer']);
      if ($account !== null && $account->id !== $this->USER->id) {
	$this->REGATTA->removeScorer($account);
	$mes = sprintf("Removed scorer %s.", $account->getName());
	Session::pa(new PA($mes));
      }
      else {
	$mes = sprintf("Invalid scorer username (%s).", $args['scorer']);
	Session::pa(new PA($mes, PA::E));
      }
    }

    // ------------------------------------------------------------
    // Add scorer
    // ------------------------------------------------------------
    if (isset($args['add_scorer'])) {
      $success = array();
      $errors  = array();
      $account = DB::$V->reqID($args, 'account', DB::$ACCOUNT, "Invalid account provided.");
      $this->REGATTA->addScorer($account);
      Session::pa(new PA(sprintf('Added scorer(s) %s.', implode(", ", $success))));
    }
    return $args;
  }
}