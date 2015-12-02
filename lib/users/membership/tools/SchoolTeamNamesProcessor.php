<?php
namespace users\membership\tools;

use \DB;
use \School;
use \SoterException;
use \Team;
use \Team_Name_Prefs;
use \UpdateManager;
use \UpdateRequest;

/**
 * Processes changes to team names.
 *
 * @author Dayan Paez
 * @version 2015-11-17
 */
class SchoolTeamNamesProcessor {

  const REGEX_NUM_SUFFIX = '/^ [0-9]+$/';

  /**
   * Assigns the given list of names as preferences to school.
   *
   * @param School $school the school to attach names to.
   * @param Array $list the non-empty list of names to associate.
   * @return Array list of new names.
   * @throws SoterException with invalid input.
   */
  public function processNames(School $school, Array $list) {
    if (count($list) == 0) {
      throw new SoterException("There must be at least one team name, none given.");
    }

    $re = DB::addRegexDelimiters(Team_Name_Prefs::REGEX_NAME);
    $names = array();
    foreach ($list as $name) {
      $name = trim($name);
      if ($name != '') {
        if (preg_match($re, $name) === 0) {
          throw new SoterException(
            sprintf("Invalid format for name: %s.", $name)
          );
        }
        $names[$name] = $name;
      }
    }

    if (count($names) == 0) {
      throw new SoterException("There must be at least one team name.");
    }
    $names = array_values($names);
    $currentNames = $school->getTeamNames();
    $school->setTeamNames($names);

    // First time? Update previous instances
    if (count($currentNames) == 0) {
      $new_name = $names[0];
      $re = '/^ [0-9]+$/';
      $length = mb_strlen($school->nick_name);
      foreach ($school->getRegattas() as $reg) {
        $changed = false;
        foreach ($reg->getTeams($school) as $team) {
          if ($this->isTeamUsingSchoolName($team)) {
            $team->name = str_replace(
              $school->nick_name,
              $new_name,
              $team->name
            );
            DB::set($team);
            $changed = true;
          }
        }
        if ($changed) {
          UpdateManager::queueRequest($reg, UpdateRequest::ACTIVITY_TEAM, $school->id);
        }
      }
    }

    return $names;
  }

  private function isTeamUsingSchoolName(Team $team) {
    $schoolName = $team->school->nick_name;
    if ($team->name == $schoolName) {
      return true;
    }

    $length = mb_strlen($schoolName);
    if (mb_substr($team->name, 0, $length) != $schoolName) {
      return false;
    }
    return preg_match(self::REGEX_NUM_SUFFIX, mb_substr($team->name, $length)) == 1;
  }
}