<?php
/*
 * This file is part of TechScore
 *
 * @package users
 */

require_once('AbstractReportPane.php');

/**
 * Generate the billing report.
 *
 * The billing report specifies, for each school (grouped by
 * conference), the total number of regattas of the chosen type(s)
 * in the given season(s) that the school participated in. For each
 * regatta type, a dollar amount may be specified which will be used
 * to generate a "total" owed for that school. Note that regattas
 * hosted do not count for host's total.
 *
 * It does not matter how many teams participated for a given school.
 *
 * @author Dayan Paez
 * @created 2013-11-21
 */
class BillingReport extends AbstractReportPane {

  public function __construct(Account $user) {
    parent::__construct("Billing report", $user);
  }

  public function fillHTML(Array $args) {
    $seasons = array();
    if (($season = Season::forDate(DB::$NOW)) !== null)
      $seasons[$season->id] = $season;
    $confs = array();
    $types = array(); // map of type ID => cost

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
            throw new SoterException("Invalid conference provided: $id.");
          $confs[$id] = $pos_confs[$id];
        }
        if (count($confs) == 0)
          throw new SoterException("No conferences provided.");

        $pos_types = array();
        foreach (DB::getAll(DB::$ACTIVE_TYPE) as $t)
          $pos_types[$t->id] = $t;
        $costs = DB::$V->incList($args, 'costs');
        foreach (DB::$V->reqList($args, 'types', null, "Missing regatta type list.") as $i => $id) {
          if (!isset($pos_types[$id]))
            throw new SoterException("Invalid regatta type provided: $id.");

          $cost = DB::$V->incFloat($costs, $i, 0.0, 15000.0, 0.0);
          if ($cost > 0)
            $types[$id] = $cost;
        }
        if (count($types) == 0)
          throw new SoterException("No regatta types/costs provided.");

        // ------------------------------------------------------------
        // Create table
        // ------------------------------------------------------------

        $schools = array();  // map of School ID => School
        $regattas = array(); // map of School ID => map of Regatta ID
        $totals = array();     // map of School ID => cost
        foreach ($confs as $conf) {
          foreach ($conf->getSchools() as $school) {
            $schools[$school->id] = $school;
            $regattas[$school->id] = array();
            $totals[$school->id] = 0;
          }
        }

        require_once('regatta/Regatta.php');
        $all = DB::getAll(DB::$PUBLIC_REGATTA,
                          new DBBool(array(new DBCond('finalized', null, DBCond::NE),
                                           new DBCondIn('type', array_keys($types)),
                                           new DBCondIn('dt_season', array_keys($seasons)))));
        foreach ($all as $regatta) {
          // hosts don't pay
          $hosts = array();
          foreach ($regatta->getHosts() as $host)
            $hosts[$host->id] = $host;

          foreach ($regatta->getTeams() as $team) {
            if (isset($schools[$team->school->id]) &&
                !isset($regattas[$team->school->id][$regatta->id]) &&
                !isset($hosts[$team->school->id])) {

              $totals[$team->school->id] += $types[$regatta->type->id];
              $regattas[$team->school->id][$regatta->id] = $regatta->name;
            }
          }
        }

        $csv = "";
        $row = array("School", DB::g(STN::CONFERENCE_SHORT), "Total", "# of Regattas", "List of regattas");
        $this->rowCSV($csv, $row);

        foreach ($schools as $id => $school) {
          $row = array($school->nick_name,
                       $school->conference,
                       sprintf("%0.2f", $totals[$id]),
                       count($regattas[$id]),
                       implode(", ", $regattas[$id]));
          $this->rowCSV($csv, $row);
        }

        $name = sprintf('%s-billing-', date('Y'));
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
    $p->add(new XP(array(), "Each row in this CSV report represents a different school from the conference(s) chosen below. The columns are:"));
    $p->add(new XUl(array(),
                    array(new XLi("School name"),
                          new XLi(DB::g(STN::CONFERENCE_TITLE)),
                          new XLi(new XEm("Billing total")),
                          new XLi("# of regattas attended"),
                          new XLi("List of regattas attended"))));
    $p->add(new XP(array(), "To include a regatta type in the report, provide a \"cost\" in the box next to the type below. The \"billing total\" is generated by multiplying the cost of each regatta type by the total number of regattas of that type the school participated; and then summing for all regatta types. Leave blank to ignore the regatta type from the report."));

    $p->add($form = $this->createForm(XForm::GET));
    $form->add(new FReqItem("Seasons:", $this->seasonList('sea-', $seasons)));
    $form->add(new FReqItem(sprintf("%ss:", DB::g(STN::CONFERENCE_TITLE)), $this->conferenceList('conf-', $confs)));

    $form->add(new FReqItem("Regatta costs:", $ul = new XUl(array('class'=>'inline-list'))));
    foreach (DB::getAll(DB::$ACTIVE_TYPE) as $t) {
      $id = 'types-' . $t->id;
      $cost = (isset($types[$t->id])) ? $types[$t->id] : "";
      $ul->add(new XLi(array(new XTextInput('costs[]', $cost, array('class'=>'small', 'id'=>$id, 'size'=>4, 'style'=>'text-align:right;')),
                             new XHiddenInput('types[]', $t->id),
                             new XLabel($id, $t))));
    }
    $form->add(new XSubmitP('create', "Create report"));
  }

  public function process(Array $args) {
    throw new SoterException("Nothing to process here.");
  }
}
?>