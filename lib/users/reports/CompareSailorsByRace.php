<?php
/*
 * This file is part of TechScore
 *
 * @package users
 */

require_once('AbstractReportPane.php');

/**
 * Compares up to three sailors head to head across a season or more,
 * and include races in common.
 *
 * @author Dayan Paez
 * @version 2011-03-29
 */
class CompareSailorsByRace extends AbstractReportPane {
  /**
   * Creates a new pane
   */
  public function __construct(Account $user) {
    parent::__construct("Compare sailors by race", $user);
  }

  private function doSailors(Array $args) {
    if (isset($args['sailor'])) {
      if (!is_array($args['sailor'])) {
        Session::pa(new PA("Invalid parameter given for comparison.", PA::E));
        return false;
      }
      $list = $args['sailor'];
    }
    elseif (isset($args['sailors']))
      $list = explode(',', (string)$args['sailors']);

    // get sailors
    $sailors = array();
    foreach ($list as $id) {
      $sailor = DB::getSailor($id);
      if ($sailor === null)
        Session::pa(new PA("Invalid sailor id given ($id). Ignoring.", PA::I));
      elseif ($sailor->icsa_id !== null)
        $sailors[] = $sailor;
    }
    if (count($sailors) < 2) {
      Session::pa(new PA("Need at least two valid sailors for comparison.", PA::E));
      return false;
    }

    // seasons. If none provided, choose the default
    $seasons = array();
    if (isset($args['seasons']) && is_array($args['seasons'])) {
      foreach ($args['seasons'] as $s) {
        if (($season = DB::getSeason($s)) !== null)
          $seasons[] = $season;
      }
    }
    else {
      $seasons[] = Season::forDate(DB::T(DB::NOW));
      if ($seasons[0]->season == Season::SPRING)
        $seasons[] = DB::getSeason('f' . ($seasons[0]->start_date->format('Y') - 1));
    }
    $regattas = Season::getRegattasInSeasons($seasons);
    if (count($regattas) == 0) {
      Session::pa(new PA("There are no regattas in the given seasons to consider for comparison.", PA::E));
      return false;
    }

    // the array is organized as $regatta_id => array($race => array($rp))
    $reg_ids = array();
    $reg_rps = array();
    foreach ($regattas as $reg) {
      $rpm = $reg->getRpManager();
      foreach ($sailors as $sailor) {
        $rps = $rpm->getParticipationEntries($sailor, RP::SKIPPER);
        if (count($rps) > 0) {
          if (!isset($reg_ids[$reg->id])) {
            $reg_ids[$reg->id] = $reg;
            $reg_rps[$reg->id] = array();
          }

          foreach ($rps as $rp) {
            $key = (string)$rp->race;
            if (!isset($reg_rps[$reg->id][$key]))
              $reg_rps[$reg->id][$key] = array();
            $reg_rps[$reg->id][$key][$rp->sailor->id] = $rp;
          }
        }
      }
    }

    // only keep race entries for which all sailors are present
    $and_rps = array();
    foreach ($reg_rps as $reg_id => $racelist) {
      foreach ($racelist as $key => $rplist) {
        if (count($rplist) == count($sailors)) {
          if (!isset($and_rps[$reg_id]))
            $and_rps[$reg_id] = array();
          $and_rps[$reg_id][$key] = $rplist;
        }
      }
    }

    // are there any regattas in common?
    if (count($and_rps) == 0) {
      Session::pa(new PA(sprintf("The sailors provided (%s, %s) have not sailed head to head in any race in any regatta in the seasons specified.", $first_sailor, implode(", ", $sailors)), PA::I));
      return false;
    }

    $scores = array(); // track scores
    $this->PAGE->addContent($p = new XPort("Races sailed head-to-head"));
    $p->add(new XP(array(), new XA(WS::link('/compare-by-race'), "← Start over")));
    $p->add(new XTable(array('class'=>'compare-by-race'),
                       array(new XTHead(array(),
                                        array($head = new XTR(array(),
                                                              array(new XTH(array(), "Regatta"),
                                                                    new XTH(array(), "Season"),
                                                                    new XTH(array(), "Race"))))),
                             $tab = new XTBody())));
    foreach ($sailors as $sailor) {
      $head->add(new XTH(array(), $sailor));
      $scores[$sailor->id] = 0;
    }
    // each race
    $reg_index = 0;
    foreach ($and_rps as $reg_id => $racelist) {
      $regatta = $reg_ids[$reg_id];
      $index = 0;
      foreach ($racelist as $race => $rplist) {
        $tab->add($row = new XTR(array('class'=>'row' . ($reg_index) % 2)));
        if ($index == 0) {
          $row->add(new XTH(array('rowspan'=>count($racelist)), $regatta->name));
          $row->add(new XTD(array('rowspan'=>count($racelist)), $regatta->getSeason()->fullString()));
        }
        $row->add(new XTH(array(), $race));
        foreach ($sailors as $sailor) {
          $rp = $rplist[$sailor->id];
          $row->add(new XTD(array(), $this->getPlaceEntry($regatta, $rp)));
        }
        $index++;
      }
      $reg_index++;
    }
    return true;
  }

  /**
   * Returns the content of the cell for the given RP entry
   *
   * If the regatta in question is a team racing regatta, this will be
   * 'W'/'L'/'T', instead of a numerical place.
   */
  private function getPlaceEntry(Regatta $regatta, RPEntry $rp) {
    if ($regatta->scoring == Regatta::SCORING_TEAM) {
      // determine if win/loss
      $score1 = 0;
      $score2 = 0;
      foreach ($regatta->getDivisions() as $div) {
        $race = $regatta->getRace($div, $rp->race->number);
        $fin1 = $regatta->getFinish($race, $rp->race->tr_team1);
        $fin2 = $regatta->getFinish($race, $rp->race->tr_team2);

        $score1 += $fin1->score;
        $score2 += $fin2->score;
      }
      $diff = $score1 - $score2;
      if ($diff == 0)
        return 'T';
      if ($rp->race->tr_team1 == $rp->team) {
        return ($diff < 0) ? 'W' : 'L';
      }
      return ($diff < 0) ? 'L' : 'W';
    }
    $finish = $regatta->getFinish($rp->race, $rp->team);
    return $finish->getPlace();
  }

  public function fillHTML(Array $args) {
    // Look for sailors as an array named 'sailors'
    if (isset($args['sailor']) || isset($args['sailors'])) {
      if ($this->doSailors($args))
        return;
      WS::go('/compare-by-race');
    }

    // ------------------------------------------------------------
    // Provide an input box to choose sailors using AJAX
    // ------------------------------------------------------------
    $this->PAGE->head->add(new LinkCSS('/inc/css/aa.css'));
    $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/aa.js'));
    $this->PAGE->addContent($form = $this->createForm(XForm::GET));

    // Season selection
    $form->add($p = new XPort("Seasons to compare"));
    $p->add(new XP(array(), "Choose at least one season to compare from the list below, then choose the sailors in the next panel."));

    $now = Season::forDate(DB::T(DB::NOW));
    $then = null;
    if ($now->season == Season::SPRING)
      $then = DB::getSeason(sprintf('f%0d', ($now->start_date->format('Y') - 1)));
    $p->add(new FReqItem("Seasons:", $this->seasonList('', array($now, $then))));
    $p->add($ul = new XUl(array('style'=>'list-style-type:none;')));

    // Sailor search
    $form->add($p = new XPort("New sailors"));
    $p->add(new XNoScript(new XP(array(), "Right now, you need to enable Javascript to use this form. Sorry for the inconvenience, and thank you for your understanding.")));
    $p->add(new FItem('Name:', $search = new XSearchInput('name-search', "")));
    $search->set('id', 'name-search');
    $p->add(new XUl(array('id'=>'aa-input'),
                    array(new XLi("No sailors.", array('class'=>'message')))));
    $form->add(new XSubmitP('set-sailors', "Compare sailors"));
  }

  public function process(Array $args) {
    return false;
  }
}
?>