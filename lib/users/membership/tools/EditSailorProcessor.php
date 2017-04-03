<?php
namespace users\membership\tools;

use \DB;
use \Sailor;
use \SoterException;
use \STN;

/**
 * Centralized processing of sailors.
 *
 * @author Dayan Paez
 * @version 2015-12-10
 */
class EditSailorProcessor {

  /**
   * Edits only the editable fields of the given school, from args.
   *
   * @param Array $args the POST parameters.
   * @param School $school the School to edit.
   * @return Array:String list of fields that were changed.
   * @throws SoterException with invalid input.
   */
  public function process(Array $args, Sailor $sailor) {
    $changed = array();
    if ($this->processFirstName($args, $sailor)) {
      $changed[] = EditSailorForm::FIELD_FIRST_NAME;
    }
    if ($this->processLastName($args, $sailor)) {
      $changed[] = EditSailorForm::FIELD_LAST_NAME;
    }
    if ($this->processYear($args, $sailor)) {
      $changed[] = EditSailorForm::FIELD_YEAR;
    }
    if ($this->processGender($args, $sailor)) {
      $changed[] = EditSailorForm::FIELD_GENDER;
    }
    if ($sailor->isRegistered() && DB::g(STN::SAILOR_PROFILES) !== null) {
      if ($this->processUrl($args, $sailor)) {
        $changed[] = EditSailorForm::FIELD_URL;
      }
    }

    if (count($changed) > 0) {
      DB::set($sailor);
    }
    return $changed;
  }

  private function processFirstName(Array $args, Sailor $sailor) {
    $name = DB::$V->reqString($args, EditSailorForm::FIELD_FIRST_NAME, 1, 200, "Invalid first name provided.");
    if ($name == $sailor->first_name) {
      return false;
    }
    $sailor->first_name = $name;
    return true;
  }

  private function processLastName(Array $args, Sailor $sailor) {
    $name = DB::$V->reqString($args, EditSailorForm::FIELD_LAST_NAME, 1, 200, "Invalid last name provided.");
    if ($name == $sailor->last_name) {
      return false;
    }
    $sailor->last_name = $name;
    return true;
  }

  private function processYear(Array $args, Sailor $sailor) {
    $year = DB::$V->reqInt($args, EditSailorForm::FIELD_YEAR, 1970, 3001, "Invalid year provided.");
    if ($year == $sailor->year) {
      return false;
    }
    $sailor->year = $year;
    return true;
  }

  private function processGender(Array $args, Sailor $sailor) {
    $gender = DB::$V->reqKey($args, EditSailorForm::FIELD_GENDER, Sailor::getGenders(), "Invalid gender provided.");
    if ($gender == $sailor->gender) {
      return false;
    }
    $sailor->gender = $gender;
    return true;
  }

  private function processUrl(Array $args, Sailor $sailor) {
    // If URL was requested, then enforce no collision
    if (DB::$V->incString($args, EditSailorForm::FIELD_URL, 1) != null) {
      $matches = DB::$V->reqRE(
        $args,
        EditSailorForm::FIELD_URL,
        DB::addRegexDelimiters(EditSailorForm::REGEX_URL),
        "Nonconformant URL provided."
      );

      $url = $matches[0];
      if ($url == $sailor->url) {
        return false;
      }

      // collision
      $otherSailor = DB::getSailorByUrl($url);
      if ($otherSailor !== null) {
        throw new SoterException(
          sprintf("Chosen URL belongs to another sailor (%s).", $otherSailor)
        );
      }

      $sailor->url = $url;
      return true;
    }

    // Auto-calculate
    $url = DB::createUrlSlug(
      $sailor->getUrlSeeds(),
      function ($slug) use ($sailor) {
        $other = DB::getSailorByUrl($slug);
        return ($other === null || $other->id == $sailor->id);
      }
    );

    $sailor->url = $url;
    return true;
  }

}