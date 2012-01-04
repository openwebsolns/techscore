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

  public function __construct(User $user, Regatta $reg) {
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
      if ($s->id === $this->USER->username()) {
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
      Preferences::getConference($args['conference']) : 
      $this->USER->get(User::SCHOOL)->conference;

    if ($chosen_conf === null) {
      $this->announce(new Announcement("Invalid conference chosen. Defaulting to your own.", Announcement::WARNING));
      $chosen_conf = $this->USER->get(User::SCHOOL)->conference;
    }
      

    $confs = array();
    foreach (Preferences::getConferences() as $conf)
      $confs[$conf->id] = $conf;
        
    $s_form->add($fi = new FItem("Conference:", $sel = XSelect::fromArray('conference', $confs, $chosen_conf->id)));
    $sel->set("onchange","submit('this')");

    // Add accessible submit button
    $fi->add(new XSubmitAccessible("update_conf", "Update"));

    // Accounts form
    $p->add($s_form = $this->createForm());

    // Get accounts for this conference
    $accounts = Preferences::getUsersFromConference($chosen_conf);
    if (count($accounts) > 0) {
      $s_form->add(new FItem("Account:", $sel = new XSelectM("account[]", array('size'=>10))));
      foreach ($accounts as $user) {
	if (!isset($scorers[$user->id]))
	  $sel->add(new FOption($user->id, sprintf('%s, %s', $user->last_name, $user->first_name)));
      }
      $s_form->add(new XSubmitInput("add_scorer", "Add scorers"));
    }
    else
      $s_form->add(new XMessage("There are no accounts left to register in this conference Please try a different one."));
    
  }

  public function process(Array $args) {

    // ------------------------------------------------------------
    // Change conference
    // ------------------------------------------------------------
    if (isset($args['conference']) && is_numeric($args['conference'])) {
      if (!($conf = Preferences::getConference((int)$args['conference']))) {
	unset($args['conference']);
	$this->announce(new Announcement("Invalid conference", Announcement::ERROR));
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
      $account = AccountManager::getAccount(addslashes($args['scorer']));
      if ($account !== null && $account->id !== $this->USER->username()) {
	$this->REGATTA->removeScorer($account);
	$mes = sprintf("Removed scorer %s.", $account->getName());
	$this->announce(new Announcement($mes));
      }
      else {
	$mes = sprintf("Invalid scorer username (%s).", $args['scorer']);
	$this->announce(new Announcement($mes, Announcement::ERROR));
      }
    }

    // ------------------------------------------------------------
    // Add scorer
    // ------------------------------------------------------------
    if (isset($args['add_scorer']) &&
	isset($args['account']) &&
	is_array($args['account'])) {

      $success = array();
      $errors  = array();
      foreach($args['account'] as $id) {
	$account = AccountManager::getAccount(addslashes($id));
	if ($account) {
	  $this->REGATTA->addScorer($account);
	  $success[] = $account->getName();
	}
	else {
	  $errors[] = $id;
	}
      }
      if (count($success) > 0) {
	$mes = sprintf('Added scorer(s) %s.', implode(", ", $success));
	$this->announce(new Announcement($mes));
      }
      if (count($errors) > 0) {
	$mes = sprintf('Invalid scorer username(s) (%s).', implode(", ", $errors));
	$this->announce(new Announcement($mes, Announcement::ERROR));
      }
    }
    return $args;
  }
}