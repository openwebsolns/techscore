<?php
namespace xml5;

use \ui\FilterFieldset;
use \utils\SailorSearcher;
use \xml5\GraduationYearInput;

use \Account;
use \DB;
use \Member;
use \Season;

use \FormGroup;
use \XA;
use \XForm;
use \XNumberInput;
use \XP;
use \XSelect;
use \XSpan;
use \XSubmitInput;

/**
 * Creates the form for searching/filtering sailors.
 *
 * @author Dayan Paez
 * @version 2015-11-22
 */
class SailorPageWhizCreator {

  const CLASSNAME_FILTERFORM = 'sailor-filter';

  const EMPTY_SEARCH_MESSAGE = 'There are no items to show.';
  const DEFAULT_LEADIN = 'Search';
  const DEFAULT_MIN_QUERY_SIZE = 3;

  private $args;
  private $account;
  private $numPerPage;
  private $action;

  // Cached objects
  private $searcher;
  private $results;
  private $pageWhiz;

  /**
   * Creates a new pagination/filtering/searching wizard.
   *
   * @param Account $account for filtering.
   * @param Array $args with filter arguments from POST.
   */
  public function __construct(Account $account, Array $args, $numPerPage, $action) {
    $this->account = $account;
    $this->args = $args;
    $this->searcher = SailorSearcher::fromArgs($account, $args);
    $this->numPerPage = $numPerPage;
    $this->action = $action;
  }

  /**
   * Returns a suitable PageWhiz object for this search.
   *
   * @return PageWhiz (non-null).
   */
  public function getPageWhiz() {
    if ($this->pageWhiz === null) {
      $results = $this->getMatchedSailors();
      $this->pageWhiz = new PageWhiz(
        count($results),
        $this->numPerPage,
        $this->action,
        $this->args
      );
    }
    return $this->pageWhiz;
  }

  /**
   * Exposes the searcher created from the arguments and account.
   *
   * @return SailorSearcher
   */
  public function getSailorSearcher() {
    return $this->searcher;
  }

  /**
   * Retrieves the list of sailors based on parameters.
   *
   * @return Array:Sailor the applicable sailors.
   * @see SailorSearcher
   */
  public function getMatchedSailors() {
    if ($this->results === null) {
      $this->results = $this->searcher->doSearch();
    }
    return $this->results;
  }

  /**
   * Interfaces with PageWhiz.
   *
   * @param String $action for the search form.
   */
  public function getSearchForm(
    $emptyMes = self::EMPTY_SEARCH_MESSAGE,
    $leadin = self::DEFAULT_LEADIN,
    $minQuerySize = self::DEFAULT_MIN_QUERY_SIZE
  ) {
    $whiz = $this->getPageWhiz();
    return $whiz->getSearchForm(
      $this->searcher->getQuery(),
      SailorSearcher::FIELD_QUERY,
      $emptyMes,
      $leadin,
      $minQuerySize
    );
  }

  /**
   * Returns a search form and filter form.
   *
   */
  public function getFilterForm() {
    $results = $this->getMatchedSailors();
    $isFilterEmpty = $this->isFilterEmpty();
    if (count($results) == 0 && $isFilterEmpty) {
      return null;
    }

    $fs = new FilterFieldset();
    $fs->add($f = new XForm($this->action, XForm::GET, array('class' => self::CLASSNAME_FILTERFORM)));

    $gender = $this->searcher->getGender();
    $genders = array('' => '');
    foreach (Member::getGenders() as $key => $val) {
      $genders[$key] = $val;
    }

    $status = $this->searcher->getMemberStatus();
    $statuses = array('' => '');
    foreach (Member::getRegisterStatuses() as $key => $value) {
      $statuses[$key] = $value;
    }

    $year = null;
    $years = $this->searcher->getYears();
    if (count($years) > 0) {
      $year = $years[0];
    }

    $chosenSeason = $this->searcher->getEligibilitySeason();
    $seasons = array("" => "[All]");
    foreach (Season::all() as $season) {
      $name = $season->fullString();
      if ($season->isCurrent()) {
        $name .= " (Current)";
      }
      $seasons[$season->id] = $name;
    }

    $f->add(
      $xp = new XP(
        array(),
        array(
          new FormGroup(
            array(
              new FormGroupHeader("Season:"),
              XSelect::fromArray(
                SailorSearcher::FIELD_ELIGIBILITY_SEASON,
                $seasons,
                ($chosenSeason !== null) ? $chosenSeason->id : ''
              )
            )
          ),
          new FormGroup(
            array(
              new FormGroupHeader("Gender:"),
              XSelect::fromArray(SailorSearcher::FIELD_GENDER, $genders, $gender),
            )
          ),
          new FormGroup(
            array(
              new FormGroupHeader("Reg. status:"),
              XSelect::fromArray(SailorSearcher::FIELD_MEMBER_STATUS, $statuses, $status),
            )
          ),
          new FormGroup(
            array(
              new FormGroupHeader("Grad. year:"),
              new GraduationYearInput(SailorSearcher::FIELD_YEAR, $year),
            )
          ),
        )
      )
    );

    $availableSchools = $this->account->getSchools();
    if (count($availableSchools) > 0) {
      $school = null;
      $schools = $this->searcher->getSchools();
      if (count($schools) > 0) {
        $school = $schools[0];
      }

      $xp->add(
        new FormGroup(
          array(
            new FormGroupHeader("School:"),
            XSelect::fromDBM(
              SailorSearcher::FIELD_SCHOOL,
              $availableSchools,
              $school,
              array(),
              "" // default option
            )
          )
        )
      );
    }

    $xp->add(new XSubmitInput('go', "Apply", array('class'=>'inline')));
    if (!$isFilterEmpty) {
      $xp->add(new XA($this->action, "Reset"));
    }

    return $fs;
  }

  /**
   * Determines if there's any active filter settings.
   *
   * @return boolean true if SailorSearcher has no special filtering.
   */
  private function isFilterEmpty() {
    return (
      $this->searcher->getGender() == null
      && $this->searcher->getMemberStatus() == null
      && count($this->searcher->getSchools()) == 0
      && count($this->searcher->getYears()) == 0
    );
  }
}