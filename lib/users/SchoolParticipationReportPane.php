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
    $seasons = array(Season::forDate(DB::$NOW));
    $confs = array();
    $type = '';
    $limit = 0;

    // ------------------------------------------------------------
    // Step 2: check for parameters
    // ------------------------------------------------------------
    if (isset($args['create'])) {
      try {
        $seasons = array();
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
        foreach (DB::$V->reqList($args, 'confs', null, "Missing conferences for report.") as $id) {
          if (!isset($pos_confs[$id]))
            throw new SoterException("Invalid conference provided: $conf.");
          $confs[$id] = $pos_confs[$id];
        }
        if (count($confs) == 0)
          throw new SoterException("No conferences provided.");

        $type = DB::$V->incID($args, 'type', DB::$ACTIVE_TYPE);
        $limit = DB::$V->incInt($args, 'within-confs', 1, 2, 0);

        // ------------------------------------------------------------
        // Create table
        // ------------------------------------------------------------
        $regattas = array(); // indexed by regatta ID
        $table = array(); // indexed by regatta ID, then by school
        $schools = array(); // indexed by school ID
        foreach ($seasons as $season) {
          foreach ($season->getRegattas() as $reg) {
            if ($type !== null && $reg->type != $type)
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
                $schools[$team->school->id] = $team->school;
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

        // @TODO
        throw new SoterException("Table coming soon.");
        
        return;
      }
      catch (SoterException $e) {
        Session::pa(new PA($e->getMessage(), PA::E));
      }
    }

    // ------------------------------------------------------------
    // Step 1: choose seasons and other parameters
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Choose parameters"));
    $p->add(new XP(array(), "Use this form to create a report of school participation across different regattas. The report can be exported as a CSV file. The columns are the regattas that meet the criteria chosen below, and the rows are the schools that participated in those events."));
    $p->add($form = $this->createForm(XForm::GET));
    $form->add(new FItem("Seasons:", $this->seasonList('sea-', $seasons)));
    $form->add(new FItem("Conferences:", $this->conferenceList('conf-', $confs)));
    $form->add(new FItem("Regatta type:", $sel = new XSelect('type')));

    $sel->add(new XOption("", array(), "[All regattas]"));
    foreach (DB::getAll(DB::$ACTIVE_TYPE) as $t) {
      $sel->add($opt = new XOption($t->id, array(), $t));
      if ($type == $t)
        $opt->set('selected', 'selected');
    }

    $form->add($fi = new FItem("Limit to conferences:", $chk = new XCheckboxInput('within-confs', 1, array('id'=>'chk-limit'))));
    $fi->add(new XLabel('chk-limit', "Only include regattas hosted by the conferences chosen above."));
    if ($limit > 0)
      $chk->set('checked', 'checked');

    $form->add(new XSubmitP('create', "Create report"));
  }

  public function process(Array $args) {
    throw new SoterException("Nothing to process here.");
  }
}
?>