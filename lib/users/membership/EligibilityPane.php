<?php
namespace users\membership;

use \model\StudentProfile;
use \xml5\HelpMeLink;

use \Account;
use \Season;

use \FCheckbox;
use \XPort;
use \XQuickTable;
use \XWarning;

/**
 * Set eligibility for a given student profile.
 *
 * @author Dayan Paez
 * @version 2016-04-16
 */
class EligibilityPane extends AbstractProfilePane {
  const INPUT_SELECTED_SEASONS = 'seasons';

  private $seasons;

  public function __construct(Account $user) {
    parent::__construct("Student eligibility", $user);
    $this->seasons = Season::all();
  }

  protected function fillHTML(Array $args) {
    if (count($this->seasons) === 0) {
      $this->PAGE->addContent(new XWarning(array(
        "There are currently no seasons in the system. Please ",
        new HelpMeLink("contact an administrator"),
        " to proceed."
      )));
      return;
    }

    parent::fillHTML($args);
  }

  protected function fillProfile(StudentProfile $profile, Array $args) {
    $eligibilityBySeason = array();
    foreach ($profile->getEligibilities() as $eligibility) {
      $eligibilityBySeason[$eligibility->season->id] = $eligibility;
    }

    $this->PAGE->addContent($p = new XPort(sprintf("Eligibility for %s", $profile->getName())));
    $p->add($form = $this->createForm());
    $form->add($table = new XQuickTable(array('class' => 'eligibility-selection-table'), array("", "Season", "Notes")));
    foreach ($this->seasons as $season) {
      $isChecked = false;
      $notes = "";
      if (array_key_exists($season->id, $eligibilityBySeason)) {
        $isChecked = true;
        $notes = $eligibilityBySeason[$season->id]->reason;
      }

      $table->addRow(array(
        new FCheckbox(self::INPUT_SELECTED_SEASONS . '[]', 1, '', $isChecked),
        $season->fullString(),
        $notes,
      ));
    }
    //var_dump($eligibilityBySeason); exit;
  }

  protected function processProfile(StudentProfile $profile, Array $args) {
    throw new \SoterException("Not yet implemented.");
  }
}