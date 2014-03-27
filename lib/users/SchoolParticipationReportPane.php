<?php
/*
 * This file is part of TechScore
 *
 * @package users
 */

require_once('users/AbstractUserPane.php');

/**
 * Prepare CSV file of school participations in chosen set of regattas
 *
 * @author Dayan Paez
 * @created 2013-05-24
 */
class SchoolParticipationReportPane extends AbstractUserPane {
  public function __construct(Account $user) {
    parent::__construct("Record of team participation", $user);
    $this->page_url = 'team-participation';
  }

  public function fillHTML(Array $args) {
    $seasons = array();
    if (($season = Season::forDate(DB::$NOW)) !== null)
      $seasons[$season->id] = $season;
    $confs = array();
    $types = array();
    $limit = 0;

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
        foreach (DB::$V->reqList($args, 'confs', null, sprintf("Missing %s for report.", DB::g(STN::CONFERENCE_TITLE))) as $id) {
          if (!isset($pos_confs[$id]))
            throw new SoterException(sprintf("Invalid %s provided: %s.", DB::g(STN::CONFERENCE_TITLE), $id));
          $confs[$id] = $pos_confs[$id];
        }
        if (count($confs) == 0)
          throw new SoterException(sprintf("No %s provided.", DB::g(STN::CONFERENCE_TITLE)));

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

        $limit = DB::$V->incInt($args, 'within-confs', 1, 2, 0);

        // ------------------------------------------------------------
        // Create table
        // ------------------------------------------------------------
        $regattas = array(); // indexed by regatta ID
        $table = array(); // indexed by regatta ID, then by school
        $schools = array(); // indexed by conf ID, then school ID
        foreach ($seasons as $season) {
          foreach ($season->getRegattas() as $reg) {
            if (!isset($types[$reg->type->id]))
              continue;
            if ($limit > 0) {
              $in_conf = false;
              foreach ($reg->getHosts() as $host) {
                if (isset($confs[$host->conference->id])) {
                  $in_conf = true;
                  break;
                }
              }
              if (!$in_conf)
                continue;
            }

            $list = array();
            foreach ($reg->getTeams() as $team) {
              if (isset($confs[$team->school->conference->id])) {

                if (!isset($schools[$team->school->conference->id]))
                  $schools[$team->school->conference->id] = array();
                $schools[$team->school->conference->id][$team->school->id] = $team->school;

                if (!isset($list[$team->school->id]))
                  $list[$team->school->id] = 0;
                $list[$team->school->id]++;
              }
            }

            if (count($list) > 0) {
              $regattas[$reg->id] = $reg;
              $table[$reg->id] = $list;
            }
          }
        }

        // Empty table
        if (count($table) == 0)
          throw new SoterException("No data available for chosen parameters.");

        $csv = "";
        $row = array(sprintf("%s/School", DB::g(STN::CONFERENCE_TITLE)), "Events");
        foreach ($regattas as $reg) {
          $name = $reg->name;
          if (count($seasons) > 1)
            $name .= sprintf(' (%s)', $reg->getSeason()->fullString());
          $row[] = $name;
        }
        $this->rowCSV($csv, $row);

        $grand_total = 0;
        foreach ($confs as $cid => $conf) {
          if (isset($schools[$cid])) {
            $this->rowCSV($csv, array($conf));
            foreach ($schools[$cid] as $sid => $school) {
              $row = array($school->name, 0);
              $tot = 0;
              foreach ($regattas as $rid => $reg) {
                if (isset($table[$rid][$sid])) {
                  $row[] = $table[$rid][$sid];
                  $tot += $table[$rid][$sid];
                }
                else
                  $row[] = "";
              }
              $row[1] = $tot;
              $this->rowCSV($csv, $row);
              $grand_total += $tot;
            }
            $this->rowCSV($csv, array());
          }
        }
        $this->rowCSV($csv, array("Grand Total", $grand_total));

        $name = sprintf('%s-team-record-', date('Y'));
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
                   sprintf("Use this form to create a report of school participation across different regattas. The report can be exported as a CSV file. The columns are the regattas that meet the criteria chosen below, and the rows are the schools that participated in those events (but only from the chosen %ss). The corresponding cell is the number of teams from that school in that regatta.", DB::g(STN::CONFERENCE_TITLE))));
    $p->add($form = $this->createForm(XForm::GET));
    $form->add(new FReqItem("Seasons:", $this->seasonList('sea-', $seasons)));

    $form->add(new FReqItem(sprintf("%ss:", DB::g(STN::CONFERENCE_TITLE)), $this->conferenceList('conf-', $confs)));
    $form->add(new FItem(sprintf("Limit to %ss:", DB::g(STN::CONFERENCE_TITLE)),
			 new FCheckbox('within-confs', 1, sprintf("Only include regattas hosted by the %ss chosen above.", DB::g(STN::CONFERENCE_TITLE)), $limit > 0)));

    $form->add(new FReqItem("Regatta type:", $this->regattaTypeList('types-', $types)));

    $form->add(new XSubmitP('create', "Create report"));
  }

  public function process(Array $args) {
    throw new SoterException("Nothing to process here.");
  }
}
?>
