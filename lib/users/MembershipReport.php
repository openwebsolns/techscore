<?php
/*
 * This file is part of TechScore
 *
 * @package users
 */

require_once('users/AbstractUserPane.php');

/**
 * Generate the "membership" report.
 *
 * The membership report specifies, for each school (grouped by
 * conference),the last regatta in the following categories:
 *
 *  - 2 Division fleet race (coed)
 *  - 2 Division women's (see note)
 *  - Team race
 *  - Team race women's
 *  - Singlehanded
 *
 * For each category, include two fields: the name and the date.
 *
 * Include a regatta if the RP information is complete for the
 * specific team from the given school. A team composed solely of
 * female sailors counts towards the women's requirement (and not
 * towards the coed requirement).
 *
 * Search for regattas among the chosen list of seasons.
 *
 * @author Dayan Paez
 * @created 2013-11-20
 */
class MembershipReport extends AbstractUserPane {

  const COED = 'coed';
  const TEAM = 'team';
  const SINGLE = 'single';

  public function __construct(Account $user) {
    parent::__construct("School participation report", $user);
    $this->page_url = 'membership';
  }

  public function fillHTML(Array $args) {
    $seasons = array();
    if (($season = Season::forDate(DB::$NOW)) !== null)
      $seasons[$season->id] = $season;
    $confs = array();
    $types = array();

    // ------------------------------------------------------------
    // Step 2: check for parameters
    // ------------------------------------------------------------
    if (isset($args['create'])) {
      try {
        foreach (DB::$V->reqList($args, 'seasons', null, "Missing seasons for report.") as $id) {
          $season = DB::getSeason($id);
          if ($season === null)
            throw new SoterException("Invalid season provided: $id.");
          $seasons[$season->id] = $season;
        }
        if (count($seasons) == 0)
          throw new SoterException("No seasons provided.");

        $pos_confs = array();
        foreach ($this->USER->getConferences() as $conf)
          $pos_confs[$conf->id] = $conf;
        foreach (DB::$V->reqList($args, 'confs', null, sprintf("Missing %ss for report.", strtolower(DB::g(STN::CONFERENCE_TITLE)))) as $id) {
          if (!isset($pos_confs[$id]))
            throw new SoterException(sprintf("Invalid %s provided: %s.", DB::g(STN::CONFERENCE_TITLE), $id));
          $confs[$id] = $pos_confs[$id];
        }
        if (count($confs) == 0)
          throw new SoterException(sprintf("No %ss provided.", DB::g(STN::CONFERENCE_TITLE)));

        $pos_types = array();
        foreach (DB::getAll(DB::$ACTIVE_TYPE) as $t)
          $pos_types[$t->id] = $t;
        foreach (DB::$V->reqList($args, 'types', null, "Missing regatta type list.") as $id) {
          if (!isset($pos_types[$id]))
            throw new SoterException("Invalid regatta type provided: $id.");
          $types[$id] = $pos_types[$id];
        }
        if (count($types) == 0)
          throw new SoterException("No regatta types provided.");

        // ------------------------------------------------------------
        // Create table
        // ------------------------------------------------------------

        // DB::setLogFile('/tmp/queries.log');

        $base_cond = new DBBool(array(new DBCond('dt_status', Regatta::STAT_FINAL),
                                      new DBCondIn('type', array_keys($types)),
                                      new DBCondIn('dt_season', array_keys($seasons))));
        $conds = array(self::COED => new DBBool(array($base_cond,
                                                      new DBCond('dt_num_divisions', 2, DBCond::GE),
                                                      new DBCond('scoring', Regatta::SCORING_TEAM, DBCond::NE))),
                       self::TEAM => new DBBool(array($base_cond,
                                                      new DBCond('scoring', Regatta::SCORING_TEAM))),
                       self::SINGLE => new DBBool(array($base_cond, new DBCond('dt_singlehanded', null, DBCond::NE))));

        $csv = "";
        $row = array("School", DB::g(STN::CONFERENCE_TITLE),
                     "2 Divisions Coed", "Date",
                     "2 Divisions Women", "Date",
                     "Team Race Coed", "Date",
                     "Team Race Women", "Date",
                     "Singlehanded", "Date");
        $this->rowCSV($csv, $row);

        require_once('regatta/Regatta.php');
        $regattas = array(); // cache
        foreach ($confs as $conf) {
          foreach ($conf->getSchools() as $school) {
            $row = array($school->name, $school->conference);

            // ------------------------------------------------------------
            // 2-Division, Team (coed and women)
            // ------------------------------------------------------------
            foreach (array(self::COED, self::TEAM) as $axis) {
              $regs = DB::getAll(DB::$PUBLIC_REGATTA,
                                 new DBBool(array($conds[$axis],
                                                  new DBCondIn('id', DB::prepGetAll(DB::$TEAM, new DBCond('school', $school), array('regatta'))))));
              $coed = null;
              $women = null;
              foreach ($regs as $reg) {
                if (!isset($regattas[$reg->id]))
                  $regattas[$reg->id] = $reg;
                else
                  $reg = $regattas[$reg->id];

                $rp = $reg->getRpManager();
                foreach ($reg->getTeams($school) as $team) {
                  if ($team->dt_complete_rp === null)
                    continue;

                  if (!$rp->hasGender(Sailor::MALE, $team)) {
                    if ($women === null)
                      $women = $reg;
                  }
                  elseif ($coed === null) {
                    $coed = $reg;
                  }
                }
                if ($coed !== null && $women !== null)
                  break;
              }

              if ($coed === null) {
                $row[] = "";
                $row[] = "";
              }
              else {
                $row[] = $coed->name;
                $row[] = $coed->start_time->format('m/d/Y');
              }
              if ($women === null) {
                $row[] = "";
                $row[] = "";
              }
              else {
                $row[] = $women->name;
                $row[] = $women->start_time->format('m/d/Y');
              }
            }

            // ------------------------------------------------------------
            // Singlehanded
            // ------------------------------------------------------------
            $coed = null;
            $regs = DB::getAll(DB::$REGATTA,
                               new DBBool(array($conds[self::SINGLE],
                                                new DBCondIn('id', DB::prepGetAll(DB::$TEAM, new DBCond('school', $school), array('regatta'))))));
            foreach ($regs as $reg) {
              if ($coed !== null)
                break;

              $rp = $reg->getRpManager();
              foreach ($reg->getTeams($school) as $team) {
                if ($team->dt_complete_rp !== null) {
                  $coed = $reg;
                  break;
                }
              }
            }

            if ($coed === null) {
              $row[] = "";
              $row[] = "";
            }
            else {
              $row[] = $coed->name;
              $row[] = $coed->start_time->format('m/d/Y');
            }

            
            $this->rowCSV($csv, $row);
          }
        }

        $name = sprintf('%s-membership-', date('Y'));
        if (count($confs) == count($pos_confs))
          $name .= 'all';
        else
          $name .= implode('-', $confs);
        $name .= '.csv';

        header("Content-type: application/octet-stream");
        header("Content-Disposition: attachment; filename=" . $name);
        header("Content-Length: " . strlen($csv));
        echo $csv;
        exit;
      }
      catch (SoterException $e) {
        Session::pa(new PA($e->getMessage(), PA::E));
      }
    }

    // ------------------------------------------------------------
    // Step 1: choose seasons and other parameters
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Choose parameters"));
    $p->add(new XP(array(),
                   sprintf("This report will generate a CSV file that identifies, for each school in the %ss chosen, the last regatta the school participated in each of the provided categories.",
                           DB::g(STN::CONFERENCE_TITLE))));
    $p->add(new XUl(array(),
                    array(new XLi("Minimum 2 division coed"),
                          new XLi("Minimum 2 division women's"),
                          new XLi("Team race coed"),
                          new XLi("Team race women's"),
                          new XLi("Singlehanded"))));
    $p->add($form = $this->createForm(XForm::GET));
    $form->add(new FReqItem("Seasons:", $this->seasonList('sea-', $seasons)));
    $form->add(new FReqItem(sprintf("%ss:", DB::g(STN::CONFERENCE_TITLE)), $this->conferenceList('conf-', $confs)));
    $form->add(new FReqItem("Regatta type:", $this->regattaTypeList('types-', $types)));
    $form->add(new XSubmitP('create', "Create report"));
  }

  public function process(Array $args) {
    throw new SoterException("Nothing to process here.");
  }
}
?>