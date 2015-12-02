<?php
namespace users\membership;

use \users\AbstractUserPane;

use \Account;
use \DB;
use \PermissionException;
use \RpManager;
use \Sailor;
use \School;
use \Session;
use \SoterException;
use \STN;

use \FReqItem;
use \XForm;
use \XP;
use \XPort;
use \XQuickTable;
use \XSelect;
use \XSubmitAccessible;
use \XSubmitP;
use \XValid;

/**
 * List and merge unregistered sailors.
 *
 * @author Dayan Paez
 * @version 2015-12-02
 */
class UnregisteredSailorsPane extends AbstractUserPane {

  const KEY_SCHOOL = 'school';
  const SUBMIT_CHOOSE = 'choose';
  const SUBMIT_REPLACE = 'replace-sailors';
  const INPUT_SAILORS = 'sailors';
  const OPTION_EMPTY = '';

  const PORT_CHOOSE = "Change school";
  const PORT_MERGE = "Unregistered sailors for %s";

  /**
   * @var Array:School list of schools that can be edited.
   */
  private $schools;

  public function __construct(Account $user) {
    parent::__construct("Unregistered sailors", $user);
    $this->schools = $user->getSchoolsWithUnregisteredSailors();
  }

  public function fillHTML(Array $args) {
    if (count($this->schools) == 0) {
      $this->fillCongratulations($args);
      return;
    }

    $chosenSchool = $this->getChosenSchool($args, $this->schools[0]);
    if (count($this->schools) > 1) {
      $this->fillSchoolChooser($chosenSchool, $args);
    }

    $this->fillSailorList($chosenSchool, $args);
  }

  private function fillCongratulations(Array $args) {
    $this->PAGE->addContent(
      new XValid(
        "Congratulations! There are no unregistered sailors in your school(s)."
      )
    );
  }

  private function fillSchoolChooser(School $school, Array $args) {
    $this->PAGE->addContent($p = new XPort(self::PORT_CHOOSE));
    $p->add($form = $this->createForm(XForm::GET));
    $form->add(
      new FReqItem(
        "School:",
        XSelect::fromDBM(
          self::KEY_SCHOOL,
          $this->schools,
          $school,
          array('onchange' => 'submit(this);')
        )
      )
    );
    $form->add(new XSubmitAccessible(self::SUBMIT_CHOOSE, "Get sailors"));
  }

  private function fillSailorList(School $school, Array $args) {
    $this->PAGE->addContent($p = new XPort(sprintf(self::PORT_MERGE, $school)));

    $temp = $school->getUnregisteredSailors();
    if (count($temp) == 0) {
      $p->add(new XValid("Great! No temporary sailors for this school."));
      return;
    }

    $orgname = DB::g(STN::ORG_NAME);
    $p->add(new XP(array(), sprintf("When a sailor is not found in the database, the scorers can add the sailor temporarily. These temporary sailors appear throughout %s with an asterisk next to their name.", DB::g(STN::APP_NAME))));

    $p->add(
      new XP(
        array(),
        sprintf(
          "Use this form to update the database by matching the temporary sailor with the actual one from the %s database. If the sailor does not appear, he/she may have to be approved before the changes are reflected in %s. Also, bear in mind that %s's copy of the membership database might lag the official copy by as much as a week.",
          $orgname,
          DB::g(STN::APP_NAME),
          DB::g(STN::APP_NAME)
        )
      )
    );

    $p->add($form = $this->createForm());
    $form->add($tab = new XQuickTable(array('class'=>'narrow'), array("Temporary sailor", sprintf("%s Match", $orgname))));

    // Create choices
    $sailors = $school->getSailors();

    foreach ($temp as $sailor) {
      $tab->addRow(
        array(
          $sailor,
          XSelect::fromDBM(
            sprintf('%s[%s]', self::INPUT_SAILORS, $sailor->id),
            $sailors,
            $this->getBestMatch($sailor, $sailors),
            array(),
            self::OPTION_EMPTY
          )
        )
      );
    }

    // Submit
    $form->add(new XSubmitP(self::SUBMIT_REPLACE, "Update database"));
  }

  public function process(Array $args) {
    // ------------------------------------------------------------
    // Match
    // ------------------------------------------------------------
    if (array_key_exists(self::SUBMIT_REPLACE, $args)) {
      $list = DB::$V->reqList($args, self::INPUT_SAILORS, null, "No sailors provided.");

      // Validate, before comitting changes
      $sailorsById = array();
      $mergeMap = array();
      foreach ($list as $originalId => $replacementId) {
        if ($replacementId != self::OPTION_EMPTY) {
          $original = $this->getSailorWithId($originalId, false);
          $replacement = $this->getSailorWithId($replacementId, true);

          // enforce that the school is the same
          if ($original->school != $replacement->school) {
            throw new SoterException(
              "Replacement sailor must be from the same school as unregistered sailor."
            );
          }

          $sailorsById[$original->id] = $original;
          $sailorsById[$replacement->id] = $replacement;
          $mergeMap[$original->id] = $replacement->id;
        }
      }

      // Change
      foreach ($mergeMap as $originalId => $replacementId) {
        $original = $sailorsById[$originalId];
        $replacement = $sailorsById[$replacementId];
        RpManager::replaceTempActual(
          $original,
          $replacement,
          true
        );
      }

      if (count($mergeMap) == 1) {
        $ids = array_keys($mergeMap);
        Session::info(
          sprintf(
            "Replaced %s with %s.",
            $sailorsById[$ids[0]],
            $sailorsById[$mergeMap[$ids[0]]]
          )
        );
      }
      else {
        Session::info(
          sprintf("Merged %d unregistered sailors.", count($mergeMap))
        );
      }
    }
  }

  /**
   * Convenience, Soter wrapper.
   *
   * @param Array $args the user input.
   * @param School $default to return with missing/invalid input.
   * @return School a non-null school object.
   * @throws SoterException if no default and no valid one found.
   */
  private function getChosenSchool(Array $args, School $default) {
    $school = DB::$V->incSchool($args, self::KEY_SCHOOL);
    if ($school === null) {
      return $default;
    }
    if (!$this->USER->hasSchool($school)) {
      $message = "No access to chosen school.";
      Session::error($message);
      return $default;
    }
    return $school;
  }

  /**
   * Convenience methods applies user's access control.
   *
   * @param String $id the ID of the sailor to fetch.
   * @param boolean $isRegistered enforce the given status
   * @return Sailor the (valid) sailor with the given ID.
   * @throws SoterException if invalid ID or invalid school.
   */
  private function getSailorWithId($id, $isRegistered) {
    $sailor = DB::getSailor($id);
    if ($sailor === null) {
      throw new SoterException(
        sprintf("No sailor with given ID (%s).", $id)
      );
    }

    if (!$this->USER->hasSchool($sailor->school)) {
      throw new SoterException(
        sprintf("No permission to access school for %s.", $sailor)
      );
    }

    if ($sailor->isRegistered() !== $isRegistered) {
      throw new SoterException(
        sprintf("Invalid registration status for sailor %s.", $sailor)
      );
    }

    return $sailor;
  }

  private function getBestMatch(Sailor $needle, $options) {
    $minDistance = 10;
    $bestMatch = null;
    foreach ($options as $option) {
      $distance = levenshtein(strtolower($option->getName()), strtolower($needle->getName()));
      if ($distance < $minDistance) {
        $minDistance = $distance;
        $bestMatch = $option;
      }
    }
    return $bestMatch;
  }

}