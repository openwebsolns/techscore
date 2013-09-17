<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2010-09-18
 */

/**
 * Creates tweets for every occasion
 *
 * @author Dayan Paez
 * @created 2013-09-16
 */
class TweetFactory {

  const FINALIZED_EVENT = 'finalized';

  private $maxlength;

  public function __construct() {
    $urlen = DB::getSetting(Setting::TWITTER_URL_LENGTH);
    if ($urlen === null)
      $urlen = 25;
    $this->maxlength = 140 - $urlen;
  }

  protected function addRegattaURL($mes, FullRegatta $reg) {
    if (strlen($mes) < $this->maxlength)
      $mes .= sprintf(" http://%s%s", Conf::$PUB_HOME, $reg->getURL());
    return $mes;
  }

  /**
   * Creates a tweet for the given action
   *
   * @param Const $action one of the class constants
   * @param FullRegatta $reg the regatta in question
   * @return String the tweet
   * @throws InvalidArgumentException
   */
  public function create($action, FullRegatta $reg) {
    switch ($action) {
    case self::FINALIZED_EVENT:
      $tms = $reg->getRankedTeams();
      $art = "";
      if (strlen($reg->name) > 4 && substr(strtolower($reg->name), 0, 4) != "the ")
        $art = "the ";

      // Interesting cases: lead is "very" small
      if ($reg->scoring != Regatta::SCORING_TEAM) {
        $suf = "";
        if (substr($tms[0]->name, -1) != "s")
          $suf .= "s";

        if ($tms[0]->dt_score == $tms[1]->dt_score) {
          $mes = sprintf("%s's %s win%s %s%s on a tiebreaker.",
                         $tms[0]->school->nick_name, $tms[0]->name, $suf, $art, $reg->name);
          return  $this->addRegattaURL($mes, $reg);
        }
        if (($tms[1]->dt_score - $tms[0]->dt_score) * 2 < count($tms)) {
          switch (rand(0, 1)) {
          case 0:
            $mes = sprintf("%s's %s edge%s out the competition to win %s%s.",
                           $tms[0]->school->nick_name, $tms[0]->name, $suf, $art, $reg->name);
            return $this->addRegattaURL($mes, $reg);

          default:
            $mes = sprintf("%s's %s win%s a close one at %s%s!",
                           $tms[0]->school->nick_name, $tms[0]->name, $suf, $art, $reg->name);
            return $this->addRegattaURL($mes, $reg);
          }
        }
      }

      $num = rand(0, 3);
      switch ($num) {
      case 0:
        $suf = "";
        if (substr($tms[0]->name, -1) != "s")
          $suf .= "s";
        $mes = sprintf("Final results: %s's %s win%s %s%s.",
                       $tms[0]->school->nick_name, $tms[0]->name, $suf, $art, $reg->name);
        $mes = $this->addRegattaURL($mes, $reg);
        return $mes;

      case 1:
        $mes = sprintf("It's official! The winner of %s%s is %s's %s.",
                       $art, $reg->name, $tms[0]->school->nick_name, $tms[0]->name);
        return $this->addRegattaURL($mes, $reg);

      case 2:
        $mes = sprintf("%s's %s finish in first place at %s%s.",
                       $tms[0]->school->nick_name, $tms[0]->name, $art, $reg->name);
        return $this->addRegattaURL($mes, $reg);

      default:
        $mes = sprintf("Congratulations to %s's %s on winning %s%s!",
                       $tms[0]->school->nick_name, $tms[0]->name, $art, $reg->name);
        $mes = $this->addRegattaURL($mes, $reg);
        return $mes;
      }
      break;

    default:
      throw new InvalidArgumentException("Unknown action: $action.");
    }
  }
}
?>