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
    $p->addHelp('/node13.html#sec:scorers');

    // Get scorers
    $p->add($tab = new XQuickTable(array('class'=>'full left'), array("Name", "Affiliation", "")));
    $scorers = array();
    foreach ($this->REGATTA->getScorers() as $i => $s) {
      $scorers[$s->id] = $s;

      // Create form to delete scorer
      $f2 = $this->createForm();
      $hidden = new XHiddenInput("scorer", $s->id);
      $button = new XSubmitDelete("delete_scorer", "Remove scorer", array("style"=>"width:100%"));
      $f2->add($hidden);
      $f2->add($button);
      if ($s->id === $this->USER->id) {
        $button->set("disabled", "disabled");
        $button->set("title", "You cannot delete yourself from the regatta.");
      }

      // Fill row
      $tab->addRow(array(new XA("mailto:" . $s->id, $s->getName()), $s->school->name, $f2), array('class'=>'row' . ($i % 2)));
    }
    if (count($scorers) == 1) {
      $button->set("disabled", "disabled");
      $button->set("title", "You cannot delete the only scorer in the regatta.");
    }

    // Form to add scorers
    $search = DB::$V->incString($args, 'search', 1, 101, null);
    $this->PAGE->addContent($p = new XPort("Add scorers"));
    $p->add($s_form = $this->createForm(XForm::GET));
    $s_form->add(new XP(array('class'=>'search'),
                        array("Search by name: ",
                              new XSearchInput('search', $search, array('size'=>60)), " ",
                              new XSubmitInput('go', "Go", array('class'=>'inline')))));

    if ($search !== null) {
      if (strlen($search) < 5) {
      $p->add(new XP(array('class'=>'warning'), "Search term is too short. Must be at least 5 characters long."));
      }
      else {
        require_once('regatta/Account.php');
        $accnts = DB::searchAccounts($search, null, Account::STAT_ACTIVE);
        if (count($accnts) == 0) {
          $p->add(new XP(array('class'=>'warning'), "No results match your request. Please try again."));
        }
        else {
          // Make sure that there are some sailors left to add
          $num = 0;
          $tab = new XQuickTable(array('class'=>'full left'), array("", "First name", "Last name", "School"));
          $i = 0;
          foreach ($accnts as $user) {
            if (!isset($scorers[$user->id])) {
              $id = 's-' . $user->id;
              $tab->addRow(array(new XRadioInput('account', $user->id, array('id'=>$id)),
                                 new XLabel($id, $user->first_name),
                                 new XLabel($id, $user->last_name),
                                 new XLabel($id, $user->school->name)),
                           array('class'=>'row'.($i++ % 2)));
              $num++;
            }
          }
          if ($num > 0) {
            $p->add($f = $this->createForm());
            $f->add(new XP(array(), "Choose the sailor to add from the list below and click the \"Add scorer\" button."));
            $f->add($tab);
            $f->add(new XSubmitP("add_scorer", "Add scorer"));
          }
          else {
            $p->add(new XP(array('class'=>'warning'), "There are no accounts left to register. Please try a different one."));
          }
        }
      }
    }
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