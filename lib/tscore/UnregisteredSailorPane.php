<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once("conf.php");

/**
 * Controls the entry of unregistered sailor information
 *
 * 2011-03-22: Changed entry to use a table, with upo to 5 new entries
 * at a time
 *
 * @author Dayan Paez
 * @version 2010-01-23
 */
class UnregisteredSailorPane extends AbstractPane {

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Unregistered sailors", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("Add sailor to temporary list"));
    $p->add(new XP(array(),
                   array("Enter unregistered sailors using the table below, up to five at a time. ",
                         new XStrong("Note:"), " write the year as a four-digit number, e.g. as 2008.")));
    $genders = Sailor::getGenders();

    $p->add($form = $this->createForm());

    // Create set of schools
    $schools = array();
    foreach ($this->REGATTA->getTeams() as $team)
      $schools[$team->school->id] = $team->school->nick_name;
    asort($schools);

    $form->add($tab = new XQuickTable(array('class'=>'short'),
                                      array("School", "First name", "Last name", "Year", "Gender")));
    $gender = XSelect::fromArray('gender[]', $genders);
    $school = XSelect::fromArray('school[]', $schools);
    for ($i = 0; $i < 5; $i++) {
      $tab->addRow(array($school,
                         new XTextInput('first_name[]', ""),
                         new XTextInput('last_name[]', ""),
                         new XTextInput('year[]', "", array('maxlength'=>4, 'size'=>4, 'style'=>'max-width:5em;width:5em;min-width:5em')),
                         $gender));
    }
    $form->add(new XSubmitP("addtemp", "Add sailors"));

    $this->PAGE->addContent($p = new XPort("Review current regatta list"));
    $p->add(new XP(array(), "Below is a list of all the temporary sailors added in this regatta. You are given the option to delete any sailor that is not currently present in any of the RP forms for this regatta. If you made a mistake about a sailor's identity, remove that sailor and add a new one instead."));
    $rp = $this->REGATTA->getRpManager();
    $temp = $rp->getAddedSailors();
    if (count($temp) == 0) {
      $p->add(new XP(array(), "There are no temporary sailors added yet.", array('class'=>'message')));
    }
    else {
      $p->add($tab = new XQuickTable(array(), array("School", "First name", "Last name", "Year", "Gender", "Action")));
      foreach ($temp as $t) {
        // is this sailor currently in the RP forms? Otherwise, offer
        // to delete him/her
        $form = "";
        if (!$rp->isParticipating($t)) {
          $form = $this->createForm();
          $form->add(new XHiddenInput('sailor', $t->id));
          $form->add(new XSubmitInput('remove-temp', "Remove"));
        }
        $tab->addRow(array($t->school,
                           $t->first_name,
                           $t->last_name,
                           $t->year,
                           $genders[$t->gender],
                           $form));
      }
    }
  }

  
  public function process(Array $args) {

    // ------------------------------------------------------------
    // Add temporary sailor
    // ------------------------------------------------------------
    if (isset($args['addtemp'])) {
      // ------------------------------------------------------------
      // Realize that this process requires a 5-way map of arrays
      $cnt = DB::$V->reqMap($args, array('school', 'first_name', 'last_name', 'year', 'gender'), null, "Invalid data format.");

      $rp = $this->REGATTA->getRpManager();
      $added = 0;
      $max_year = date('Y') + 11;
      $genders = Sailor::getGenders();
      for ($i = 0; $i < count($cnt['school']); $i++) {
        $s = new Sailor();
        if (DB::$V->hasID($s->school, $cnt['school'], $i, DB::$SCHOOL) &&
            DB::$V->hasString($s->first_name, $cnt['first_name'], $i, 1, 101) &&
            DB::$V->hasString($s->last_name, $cnt['last_name'], $i, 1, 101) &&
            DB::$V->hasInt($s->year, $cnt['year'], $i, 1990, $max_year) &&
            DB::$V->hasKey($s->gender, $cnt['gender'], $i, $genders)) {
          $rp->addTempSailor($s);
          $added++;
        }
      }
      if ($added > 0)
        Session::pa(new PA("Added $added temporary sailor(s)."));
      else
        Session::pa(new PA("No temporary sailors were added. Please fill in all the fields.", PA::I));
    }

    // ------------------------------------------------------------
    // Remove temp sailor
    // ------------------------------------------------------------
    if (isset($args['remove-temp'])) {
      $rp = $this->REGATTA->getRpManager();
      $sailor = DB::$V->reqID($args, 'sailor', DB::$SAILOR, "No sailor to delete.");
      if ($rp->removeTempSailor($sailor))
        Session::pa(new PA("Removed temporary sailor $sailor."));
      else
        throw new SoterException("Unable to remove sailor $sailor.");
    }
    return $args;
  }
}
?>