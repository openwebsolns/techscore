<?php
/**
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
    $this->urls[] = "scorer";
    $this->urls[] = "host";
    $this->urls[] = "hosts";
  }

  protected function fillHTML(Array $args) {
    // ------------------------ Scorers -------------------//
    // Forms to remove scorers
    $this->PAGE->addContent($p = new Port("Approved Scorers"));
    $p->addHelp("node9.html#SECTION00522000000000000000");

    // Get scorers
    $scorers = $this->REGATTA->getScorers();
    $hosts   = $this->REGATTA->getHosts();
    $p->addChild($tab = new Table());
    $tab->addHeader(new Row(array(Cell::th("Name"),
				  Cell::th("Affiliation"),
				  Cell::th("Host?"),
				  Cell::th(""))));
    foreach ($scorers as $s) {
      // Create form to change scorer's host status
      $f1 = new Form(sprintf("edit/%s/scorers", $this->REGATTA->id()));
      if (Preferences::getObjectWithProperty($hosts, "username", $s->username) === null) {
	$button = new GenericElement("button",
				     array(new Text("Add as host")),
				     array("type"=>"submit",
					   "name"=>"set_host",
					   "value"=>$s->username));
      }
      else {
	$button = new GenericElement("button",
				     array(new Text("Remove as host")),
				     array("type"=>"submit",
					   "name"=>"unset_host",
					   "value"=>$s->username));
	if (count($hosts) == 1) $button->addAttr("disabled", "disabled");
      }

      $f1->addChild($button);
      // Create form to delete scorer
      $f2 = new Form(sprintf("edit/%s/scorers", $this->REGATTA->id()));
      $button = new GenericElement("button",
				   array(new Text("Remove scorer")),
				   array("type"=>"submit",
					 "name"=>"delete_scorer",
					 "value"=>$s->username));
      if ($s->username === $this->USER->username() ||
	  (count($hosts) == 1 && $hosts[0] == $s))
	$button->addAttr("disabled", "disabled");
      $f2->addChild($button);

      // Fill row
      $tab->addRow(new Row(array(new Cell(new Link("mailto:" . $s->username, $s->getName())),
				 new Cell($s->school->nick_name),
				 new Cell($f1),
				 new Cell($f2)))); 
    }

    // Form to add scorers
    $this->PAGE->addContent($p = new Port("Add scorers"));
    $p->addHelp("node9.html#SECTION00522100000000000000");
    $p->addChild($s_form = new Form(sprintf("edit/%s/scorer", $this->REGATTA->id())));
    // Conferences
    //   -Get chosen_conference
    $chosen_conf = (isset($args['conference'])) ? 
      $args['conference'] : 
      $this->USER->get(User::SCHOOL)->conference;

    $confs = array();
    foreach (Preferences::getConferences() as $conf)
      $confs[$conf->id] = $conf->nick;
        
    $s_form->addChild(new FItem("Conference:",
				$sel = new FSelect("conference",
						   array($chosen_conf->id))));
    $sel->addOptions($confs);
    $sel->addAttr("onchange","submit('this')");

    // Add accessible submit button
    $s_form->addChild(new FSubmitAccessible("update_conf"));

    // Accounts form
    $p->addChild($s_form = new Form(sprintf("edit/%s/scorer", $this->REGATTA->id()), "post"));

    // Get accounts for this conference
    $s_form->addChild(new FItem("Account:",
				$sel = new FSelect("account[]",
						   array())));
    $sel->addAttr("multiple","multiple");
    $scorers = array();
    foreach (Preferences::getUsersFromConference($chosen_conf) as $user) {
      if ($user->username != $this->USER->username()) {
	$scorers[$user->username] = $user->getName();
      }
    }
    $sel->addOptions($scorers);

    // Is host?
    $s_form->addChild(new FItem(new FCheckbox("is_host", "1", array("id"=>"is_host")),
				new Label("is_host", "Make scorer regatta host.")));

    $s_form->addChild(new FSubmit("add_scorer",
				  "Add scorers"));

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
      if ($account && $account->username !== $this->USER->username) {
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

      $is_host = (!empty($args['is_host']));
      $success = array();
      $errors  = array();
      foreach($args['account'] as $id) {
	$account = AccountManager::getAccount(addslashes($id));
	if ($account) {
	  $this->REGATTA->addScorer($account, $is_host);
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

    // ------------------------------------------------------------
    // Promote to host
    // ------------------------------------------------------------
    if (isset($args["set_host"])) {
      $scorers = $this->REGATTA->getScorers();
      $scorer  = Preferences::getObjectWithProperty($scorers, "username", $args["set_host"]);
      if ($scorer !== null) {
	$this->REGATTA->addScorer($scorer, true);
	$mes = sprintf("Set %s as host of the regatta.", $scorer->username);
	$this->announce(new Announcement($mes));
      }
      else {
	$mes = sprintf("Invalid scorer (%s) to udpate.", $scorer->username);
	$this->announce(new Announcement($mes, Announcement::ERROR));
	return $args;
      }
    }
    
    // ------------------------------------------------------------
    // Demote from host
    // ------------------------------------------------------------
    if (isset($args["unset_host"])) {
      $scorers = $this->REGATTA->getScorers();
      $scorer  = Preferences::getObjectWithProperty($scorers, "username", $args["unset_host"]);
      if ($scorer !== null) {
	$this->REGATTA->addScorer($scorer, false);
	$mes = sprintf("Unset %s as host of the regatta.", $scorer->username);
	$this->announce(new Announcement($mes));
      }
      else {
	$mes = sprintf("Invalid scorer (%s) to udpate.", $scorer->username);
	$this->announce(new Announcement($mes, Announcement::ERROR));
	return $args;
      }
    }
    return $args;
  }

  public function isActive() { return true; }
}