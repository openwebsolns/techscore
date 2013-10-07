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
  const COMING_SOON_EVENT = 'coming_soon';

  private $maxlength;

  public function __construct() {
    $urlen = DB::g(STN::TWITTER_URL_LENGTH);
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
   * @return String|null the tweet
   * @throws InvalidArgumentException
   */
  public function create($action, FullRegatta $reg = null) {
    switch ($action) {
    case self::FINALIZED_EVENT:
      if ($reg === null)
        throw new InvalidArgumentException("Missing Regatta argument for FINALIZED_EVENT");
      $tms = $reg->getRankedTeams();
      $art = "";
      if (strlen($reg->name) > 4 && substr(strtolower($reg->name), 0, 4) != "the ")
        $art = "the ";

      $suf = "";
      if ($reg->isSingleHanded())
        $suf .= "s";

      // Fleet racing
      if ($reg->scoring != Regatta::SCORING_TEAM) {
        // Combined division
        if ($reg->scoring == Regatta::SCORING_COMBINED) {
          // Check for overall winner
          $rnk = $reg->getDivisionRanker();
          $divtms = $rnk->rank($reg);
          if ($divtms[0]->team->id != $tms[0]->id) {
            // Special mention of overall winner
            switch (rand(0, 4)) {
            case 0:
            case 1:
              $mes = sprintf("Team %s %s wins %s%s. Congrats to %s's %s %s division on 1st place all-around.",
                             $tms[0]->school->nick_name, $tms[0]->getQualifiedName(),
                             $art, $reg->name,
                             $divtms[0]->team->school->nick_name, $divtms[0]->team->getQualifiedName(), $divtms[0]->division);
              return $this->addRegattaURL($mes, $reg);

            case 2:
            case 3:
              $mes = sprintf("%s's %s win%s %s%s, while %s's %s %s division squad takes combined top honors.",
                             $tms[0]->school->nick_name, $tms[0]->getQualifiedName(), $suf,
                             $art, $reg->name,
                             $divtms[0]->team->school->nick_name, $divtms[0]->team->getQualifiedName(), $divtms[0]->division);
              return $this->addRegattaURL($mes, $reg);
            }
          }
        }

        // Ties
        if ($tms[0]->dt_score == $tms[1]->dt_score) {
          switch (rand(0, 1)) {
          case 0:
            if (strlen($tms[0]->dt_explanation) > 0) {
              $mes = sprintf("The winner for %s%s is %s's %s: %s.",
                             $art, $reg->name,
                             $tms[0]->school->nick_name, $tms[0]->getQualifiedName(),
                             strtolower($tms[0]->dt_explanation));
              return  $this->addRegattaURL($mes, $reg);
            }
            
          default:
            $mes = sprintf("%s's %s win%s %s%s on a tiebreaker.",
                           $tms[0]->school->nick_name, $tms[0]->getQualifiedName(), $suf, $art, $reg->name);
            return  $this->addRegattaURL($mes, $reg);
          }
        }
        // Interesting cases: lead is "very" small
        if (($tms[1]->dt_score - $tms[0]->dt_score) < count($tms)) {
          switch (rand(0, 4)) {
          case 0:
          case 1:
            $mes = sprintf("%s's %s edge%s out the competition to win %s%s.",
                           $tms[0]->school->nick_name, $tms[0]->getQualifiedName(), $suf, $art, $reg->name);
            return $this->addRegattaURL($mes, $reg);

          case 2:
          case 3:
            $mes = sprintf("%s's %s win%s a close one at %s%s!",
                           $tms[0]->school->nick_name, $tms[0]->getQualifiedName(), $suf, $art, $reg->name);
            return $this->addRegattaURL($mes, $reg);
          }
        }
        // Lead is "very" large
        if (($tms[1]->dt_score - $tms[0]->dt_score) > 2 * count($tms)) {
          switch (rand(0, 3)) {
          case 0:
            $mes = sprintf("%s's %s dominant in victory at %s%s.",
                           $tms[0]->school->nick_name, $tms[0]->getQualifiedName(), $art, $reg->name);
            return $this->addRegattaURL($mes, $reg);

          case 1:
            $mes = sprintf("%s's %s win%s comfortably with a strong perfomance at %s%s.",
                           $tms[0]->school->nick_name, $tms[0]->getQualifiedName(), $suf, $art, $reg->name);
            return $this->addRegattaURL($mes, $reg);

          case 2:
            $mes = sprintf("An impressive performance by %s's %s as they finish in first place at %s%s.",
                           $tms[0]->school->nick_name, $tms[0]->getQualifiedName(), $art, $reg->name);
            return $this->addRegattaURL($mes, $reg);
          }
        }

        if ($reg->scoring == Regatta::SCORING_STANDARD) {
          // Lead change in third or second to last race?
          $races = array();
          foreach ($reg->getRaces() as $race)
            $races[] = $race;
          foreach ($reg->getDivisions() as $div)
            array_pop($races);

          $rnk = $reg->getRanker();
          $lastrank = $rnk->rank($reg, $races);
          if ($lastrank[0]->team->id != $tms[0]->id) {
            switch (rand(0, 8)) {
            case 0:
            case 1:
            case 2:
              $mes = sprintf("%s's %s come%s back to take first place at %s%s.",
                             $tms[0]->school->nick_name, $tms[0]->getQualifiedName(), $suf, $art, $reg->name);
              return $this->addRegattaURL($mes, $reg);

            case 3:
            case 4:
            case 5:
              $mes = sprintf("%s's %s stage%s a come-from-behind victory at %s%s.",
                             $tms[0]->school->nick_name, $tms[0]->getQualifiedName(), $suf, $art, $reg->name);
              return $this->addRegattaURL($mes, $reg);

            case 6:
            case 7:
              $mes = sprintf("Last minute upset by %s's %s to secure the win at %s%s.",
                             $tms[0]->school->nick_name, $tms[0]->getQualifiedName(), $art, $reg->name);
              return $this->addRegattaURL($mes, $reg);
            }
            
          }

          // Significant fleet size?
          if (count($tms) >= 18) {
            switch (rand(0, 6)) {
            case 0:
            case 1:
              $mes = sprintf("Kudos to %s's %s for first place finish against %d teams at %s%s.",
                             $tms[0]->school->nick_name, $tms[0]->getQualifiedName(),
                             count($tms) - 1,
                             $art, $reg->name);
              return $this->addRegattaURL($mes, $reg);

            case 2:
            case 3:
              $mes = sprintf("%s's %s triumphant over a field of %d teams at %s%s.",
                             $tms[0]->school->nick_name, $tms[0]->getQualifiedName(),
                             count($tms) - 1,
                             $art, $reg->name);
              return $this->addRegattaURL($mes, $reg);
            }
          }
        }
      }

      $num = rand(0, 6);
      switch ($num) {
      case 0:
      case 1:
        $mes = sprintf("Final results: %s's %s win%s %s%s.",
                       $tms[0]->school->nick_name, $tms[0]->getQualifiedName(), $suf, $art, $reg->name);
        return $this->addRegattaURL($mes, $reg);

      case 2:
        $mes = sprintf("It's official! The winner of %s%s is %s's %s.",
                       $art, $reg->name, $tms[0]->school->nick_name, $tms[0]->getQualifiedName());
        return $this->addRegattaURL($mes, $reg);

      case 3:
      case 4:
        $mes = sprintf("%s's %s finish in first place at %s%s.",
                       $tms[0]->school->nick_name, $tms[0]->getQualifiedName(), $art, $reg->name);
        return $this->addRegattaURL($mes, $reg);

      default:
        $mes = sprintf("Congratulations to %s's %s on winning %s%s!",
                       $tms[0]->school->nick_name, $tms[0]->getQualifiedName(), $art, $reg->name);
        $mes = $this->addRegattaURL($mes, $reg);
        return $mes;
      }
      break;

      // ------------------------------------------------------------
      // Coming soon
      // ------------------------------------------------------------
    case self::COMING_SOON_EVENT:
      require_once('regatta/Regatta.php');
      // Look at regattas starting in the next 7 days
      $start = clone(DB::$NOW);
      $start->add(new DateInterval('P7DT0H'));
      $potential = DB::getAll(DB::$PUBLIC_REGATTA,
                              new DBBool(array(new DBCond('start_time', DB::$NOW, DBCond::GE),
                                               new DBCond('start_time', $start, DBCond::LE),
                                               new DBCond('dt_status', Regatta::STAT_SCHEDULED, DBCond::NE))));

      if (count($potential) == 0)
        return null;

      // Group all regattas by day, and determine number of schools
      $all_schools = array();
      $schools = array();
      $days = array();
      foreach ($potential as $reg) {
        $day = $reg->start_time->format('Y-m-d');
        if (!isset($days[$day])) {
          $days[$day] = array();
          $schools[$day] = array();
        }
        $days[$day][] = $reg;
        foreach ($reg->getTeams() as $team) {
          $schools[$day][$team->school->id] = $team->school;
          $all_schools[$team->school->id] = $team->school;
        }
      }

      ksort($days);
      $keys = array_keys($days);

      if (count($days) > 1) {
        switch (rand(0, 2)) {
        case 0:
          $day = array_pop($keys);
          $now = clone(DB::$NOW);
          $now->setTime(0, 0);
          $diff = $now->diff(new DateTime($day));
          $dur = sprintf("%d days", $diff->days);
          if ($diff->days <= 2)
            $dur = "couple of days";
          $mes = sprintf("In the next %s, %d schools are scheduled to race in %d regattas. Get up-to-the-minute results at http://%s.",
                         $dur, count($all_schools), count($potential), Conf::$PUB_HOME);
          return $mes;
        }
      }

      switch (rand(0, 3)) {
      case 0:
        

      default:
        $cnt = count($days[$day]);
        $day = array_shift($keys);
        $num = sprintf("across %d regattas", $cnt);
        $lnk = sprintf('http://%s', Conf::$PUB_HOME);
        if ($cnt == 1) {
          $reg = $days[$day][0];
          $art = "";
          if (strlen($reg->name) > 4 && substr(strtolower($reg->name), 0, 4) != "the ")
            $art = "the ";
          $num = sprintf("at %s%s", $art, $reg->name);
          $lnk .= $reg->getUrl();
        }
        elseif ($cnt < 5) {
          $num = sprintf("at %d different regattas", $cnt);
        }

        $now = clone(DB::$NOW);
        $now->setTime(0, 0);
        $diff = $now->diff(new DateTime($day));
        $dur = sprintf("In %d days", $diff->days);
        if ($diff->days == 1)
          $dur = "Tomorrow";
        if ($diff->days == 0)
          $dur = "Today";

        $mes = sprintf("%s, %d schools take to the water %s. Follow the action at %s.",
                       $dur, count($schools[$day]), $num, $lnk);
        return $mes;
      }
      break;

    default:
      throw new InvalidArgumentException("Unknown action: $action.");
    }
  }
}
?>