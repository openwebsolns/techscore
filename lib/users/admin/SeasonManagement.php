<?php
/*
 * This file is part of TechScore
 *
 * @package users-admin
 */

require_once('users/admin/AbstractAdminUserPane.php');

/**
 * Manages the seasons (dates, etc)
 *
 * @author Dayan Paez
 * @created 2012-11-24
 */
class SeasonManagement extends AbstractAdminUserPane {

  public function __construct(Account $user) {
    parent::__construct("Season management", $user);
  }

  public function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("Seasons"));
    $p->add(new XP(array(), "Use the table below to edit the start and end points of the seasons. Every regatta must belong to one and only one season. Because of this, the following constraints are in effect:"));
    $p->add(new XOl(array(),
                    array(new XLi("Seasons may not overlap."),
                          new XLi("There may only be one \"season type\" (Fall, Spring, etc) per year."),
                          new XLi("Each regatta's start time must belong to a season, which implies:"),
                          new XLi("You cannot remove seasons which contain regattas."),
                          new XLi("Season's end date must come after the start date."))));
    $p->add(new XP(array(),
                   array("You must bear in mind points 3 and 4 when editing the end-points for past seasons: a rare occurrence. Note that the season's year is automatically calculated from the ",
                         new XStrong("start date"), " of the season.")));

    $headers = array("Season", "Start time", "End time", "# Regattas");

    $sponsors = array("" => "");
    foreach (Pub_Sponsor::getSponsorsForRegattas() as $sponsor)
      $sponsors[$sponsor->id] = $sponsor->name;

    $inc_sponsors = (DB::g(STN::REGATTA_SPONSORS) && count($sponsors) > 1);
    if ($inc_sponsors) {
      $p->add(new XP(array(), "You can also specify a sponsor to be used as default for all new regattas created during that season. Note that sponsor changes only affect newly created regattas. You can override the sponsor assignment on a per-regatta basis."));
      $headers[] = "Default sponsor";
    }

    $p->add($f = $this->createForm());
    $f->add($tab = new XQuickTable(array('id'=>'season-table'), $headers));

    $opts = array(Season::FALL => "Fall",
                  Season::SUMMER => "Summer",
                  Season::SPRING => "Spring",
                  Season::WINTER => "Winter");
    $row = array(new XTD(array(), array(XSelect::fromArray('season[]', $opts), new XHiddenInput('id[]', ""))),
                       new XDateInput('start_date[]', null),
                       new XDateInput('end_date[]', null),
                       new XEm("New"));

    if ($inc_sponsors)
      $row[] = XSelect::fromArray('sponsor[]', $sponsors);

    $tab->addRow($row);

    $rowIndex = 1;
    foreach (DB::getAll(DB::$SEASON) as $season) {
      $sel = XSelect::fromArray('season[]', $opts, $season->getSeason());
      $row = array(new XTD(array(), array($sel, new XHiddenInput('id[]', $season->id))),
                         new XDateInput('start_date[]', $season->start_date),
                         new XDateInput('end_date[]', $season->end_date),
                         count($season->getRegattas(true)));

      if ($inc_sponsors) {
        $sponsor = null;
        if ($season->sponsor !== null)
          $sponsor = $season->sponsor->id;
        $row[] = XSelect::fromArray('sponsor[]', $sponsors, $sponsor);
      }

      $tab->addRow($row, array('class'=>'row'. ($rowIndex++ % 2)));
    }
    $f->add(new XSubmitP('edit-seasons', "Edit seasons"));
  }

  public function process(Array $args) {
    if (isset($args['edit-seasons'])) {
      // ------------------------------------------------------------
      // Step 1: Parse all current input. We expect a 4-way map
      // indicating ID, Season, start_date and end_date.
      // ------------------------------------------------------------
      $curr_map = DB::$V->reqMap($args, array('id', 'season', 'start_date', 'end_date'), null, "Invalid arguments for current seasons.");

      // Optionally, a corresponding list of sponsors may be provided
      $sponsors = DB::$V->incList($args, 'sponsor', count($curr_map['id']));

      $opts = array(Season::FALL, Season::SUMMER, Season::SPRING, Season::WINTER);

      // track the original version of a given season's dates in order
      // to later determine which regattas were affected. Do this with
      // a map indexed by the season's ID.
      $original_seasons = array();
      $changed_seasons = array();
      foreach ($curr_map['id'] as $rowIndex => $id) {
        $obj = DB::getSeason($id);
        if ($obj === null) {
          $obj = new Season();
          $obj->season = DB::$V->reqValue($curr_map['season'], $rowIndex, $opts, "Invalid new season type.");
          if (DB::$V->incString($curr_map['start_date'], $rowIndex, 1) !== null)
            $obj->start_date = DB::$V->reqDate($curr_map['start_date'], $rowIndex, null, null, "Invalid start date.");
          if (DB::$V->incString($curr_map['end_date'], $rowIndex, 1) !== null)
            $obj->end_date = DB::$V->reqDate($curr_map['end_date'], $rowIndex, null, null, "Invalid end date.");

          // Sponsor provided?
          if (count($sponsors) > 0) {
            $obj->sponsor = DB::$V->incID($sponsors, $rowIndex, DB::$PUB_SPONSOR);
            if ($obj->sponsor !== null && !$obj->sponsor->canSponsorRegattas())
              throw new SoterException("Invalid sponsor provided for season.");
          }

          // Is one, but not both of the dates null? And, are they in
          // the right order?
          if (($obj->start_date === null) != ($obj->end_date === null))
            throw new SoterException("Please specify both start date and end date for all new seasons.");
          if ($obj->start_date !== null && $obj->end_date !== null) {
            if ($obj->start_date >= $obj->end_date)
              throw new SoterException("Start dates must come before end dates.");
            $obj->url = Season::createUrl($obj);
            $changed_seasons[] = $obj;
          }
          continue;
        }

        $original_seasons[$obj->id] = clone($obj);
        $changed = false;

        $date = DB::$V->reqDate($curr_map['start_date'], $rowIndex, null, null, "Invalid start date.");
        $date->setTime(0, 0, 0);
        if ($date != $obj->start_date) {
          $changed = true;
          $obj->start_date = $date;
        }
        
        $date = DB::$V->reqDate($curr_map['end_date'], $rowIndex, null, null, "Invalid end date.");
        $date->setTime(0, 0, 0);
        if ($date != $obj->end_date) {
          $changed = true;
          $obj->end_date = $date;
        }

        if ($obj->start_date >= $obj->end_date)
          throw new SoterException("Start dates must come before end dates.");

        $season = DB::$V->reqValue($curr_map['season'], $rowIndex, $opts, "Invalid season type.");
        if ($season != $obj->getSeason()) {
          $changed = true;
          $obj->season = $season;
          $obj->url = Season::createUrl($obj);
        }

        // Sponsor provided?
        if (count($sponsors) > 0) {
          $sponsor = DB::$V->incID($sponsors, $rowIndex, DB::$PUB_SPONSOR);
          if ($sponsor !== null && !$sponsor->canSponsorRegattas())
            throw new SoterException("Invalid sponsor provided for season.");
          if ($sponsor != $obj->sponsor) {
            $obj->sponsor = $sponsor;
            $changed = true;
          }
        }

        if ($changed)
          $changed_seasons[] = $obj;
      }

      // ------------------------------------------------------------
      // Step 2: Any changes?
      // ------------------------------------------------------------
      if (count($changed_seasons) == 0) {
        Session::pa(new PA("No changes requested.", PA::I));
        return;
      }

      // ------------------------------------------------------------
      // Step 3: Check for overlaps and duplicates
      // ------------------------------------------------------------
      $all_seasons = array();
      foreach ($changed_seasons as $season) {
        if (isset($all_seasons[$season->id]))
          throw new SoterException("Duplicate entry for season $season.");
        $all_seasons[$season->id] = $season;
      }
      // Add already existing seasons
      foreach (DB::getAll(DB::$SEASON) as $season) {
        if (!isset($all_seasons[$season->id]))
          $all_seasons[$season->id] = $season;
      }

      usort($all_seasons, array($this, 'orderStartDate'));

      // For convenience, track the ranges (start/end) for each season
      // for Step 4: checking for orphaned regattas
      $cond = new DBBool(array());

      // Overlaps
      $prev_end = null;
      foreach ($all_seasons as $season) {
        if ($prev_end !== null && $season->start_date <= $prev_end)
          throw new SoterException(sprintf("Season %s overlaps with previous one.", $season->fullString()));
        $prev_end = $season->end_date;
        $cond->add(new DBBool(array(new DBCond('start_time', $season->start_date, DBCond::LT),
                                    new DBCond('end_date', $season->end_date, DBCond::GT)),
                              DBBool::mOR));
      }

      // ------------------------------------------------------------
      // Step 4: Any orphaned regattas?
      // ------------------------------------------------------------
      require_once('regatta/Regatta.php');
      $regs = DB::getAll(DB::$REGATTA, $cond);
      if (count($regs) > 0) {
        $mes = array("The following regattas conflict with the season dates:", $ul = new XUl());
        foreach ($regs as $i => $reg) {
          if ($i >= 10) {
            $ul->add(new XLi(new XEm(sprintf("%d more...", (count($regs) - $i)))));
            break;
          }

          $ul->add(new XLi(new XA(WS::link(sprintf('/score/%s', $reg->id)), $reg->name)));
        }
        Session::pa(new PA($mes, PA::I));
        throw new SoterException("Unable to edit seasons because some regattas would be left without an associated season.");
      }

      // ------------------------------------------------------------
      // Step 5: Affected regattas
      // ------------------------------------------------------------
      $regs = array();
      $args = array(); // corresponding argument for update request
      foreach ($changed_seasons as $season) {
        if (!isset($original_seasons[$season->id])) // there can be no regattas yet
          continue;

        $orig = $original_seasons[$season->id];

        // map of old regattas
        $orig_regs = array();
        foreach ($orig->getRegattas(true) as $reg)
          $orig_regs[$reg->id] = $reg;

        foreach ($season->getRegattas(true) as $reg) {
          if (!isset($orig_regs[$reg->id])) {
            $regs[$reg->id] = $reg;
            $args[$reg->id] = (string)Season::forDate($reg->start_time);
          }
        }
      }

      if (count($regs) > 0) {
        require_once('public/UpdateManager.php');
        foreach ($regs as $id => $reg) {
          UpdateManager::queueRequest($reg, UpdateRequest::ACTIVITY_SEASON, $args[$id]);
          $reg->setData();
        }
        Session::pa(new PA(sprintf("%d regatta(s) affected by change.", count($regs)), PA::I));
      }

      foreach ($changed_seasons as $season)
        DB::set($season);
      if (count($changed_seasons) == 1)
        Session::pa(new PA("Edited season " . $changed_seasons[0]->fullString()));
      else
        Session::pa(new PA(sprintf("Edited %d seasons.", count($changed_seasons))));
      return;
    }
  }

  protected function orderStartDate(Season $s1, Season $s2) {
    if ($s1->start_date < $s2->start_date)
      return -1;
    elseif ($s1->start_date > $s2->start_date)
      return 1;
    return 0;
  }
}
?>