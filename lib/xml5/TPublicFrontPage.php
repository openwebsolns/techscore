<?php
namespace xml5;

use \DateInterval;
use \DateTime;
use \DB;
use \DBBool;
use \DBCond;
use \Regatta;
use \Season;
use \STN;
use \Text_Entry;
use \TPublicPage;

use \XA;
use \XDiv;
use \XElem;
use \XEm;
use \XH1;
use \XH3;
use \XLi;
use \XPort;
use \XQuickTable;
use \XRawText;
use \XSpan;
use \XStrong;
use \XTD;
use \XText;
use \XUl;

require_once('TPublicPage.php');

/**
 * Specific page for front page.
 *
 * @author Dayan Paez
 * @version 2011-03-06
 */
class TPublicFrontPage extends TPublicPage {

  /**
   * Creates a new public page with the given title
   *
   * @param String $title the title of the page
   */
  public function __construct() {
    parent::__construct("Scores");
    $this->setDescription("Official site for live regatta results of the Intercollegiate Sailing Association.");
    $this->fill();
  }

  private function fill() {
    // Add welcome message
    $this->addSection($div = new XDiv(array('id'=>'message-container')));
    $div->add($sub = new XDiv(array('id'=>'welcome'), array($this->h1("Welcome"))));

    $entry = DB::get(DB::T(DB::TEXT_ENTRY), Text_Entry::WELCOME);
    if ($entry !== null && $entry->html !== null) {
      $sub->add(new XRawText($entry->html));
    }

    // Menu
    $this->addMenu(new XA('/', "Home"));
    $this->addMenu(new XA('/schools/', "Schools"));
    $this->addMenu(new XA('/seasons/', "Seasons"));

    // Get current season's coming regattas

    $success = false;
    $seasons = Season::getActive();
    if (count($seasons) == 0) {
      if (($lnk = $this->getOrgLink()) !== null) {
        $this->addMenu($lnk);
      }

      // Wow! There is NO information to report!
      $this->addSection(new XPort("Nothing to show!", array(new XP(array(), "We are sorry, but there are no regattas in the system! Please come back later. Happy sailing!"))));
      return;
    }

    $this->addMenu(new XA($seasons[0]->getURL(), $seasons[0]->fullString()));
    if (($lnk = $this->getOrgLink()) !== null) {
      $this->addMenu($lnk);
    }

    // ------------------------------------------------------------
    // Are there any regattas in progress? Such regattas must be
    // happening now according to date, and have a status not equal to
    // 'SCHEDULED' (which usually indicates that a regatta is not yet
    // ready, and might possibly never be scored).
    $start = new DateTime();
    $start->setTime(23, 59, 59);
    $end = new DateTime();
    $end->setTime(0, 0, 0);
    $potential = DB::getAll(
      DB::T(DB::PUBLIC_REGATTA),
      new DBBool(
        array(
          new DBCond('start_time', $start, DBCond::LE),
          new DBCond('end_date', $end, DBCond::GE),
          new DBCond('dt_status', Regatta::STAT_SCHEDULED, DBCond::NE))
      )
    );
    if (count($potential) > 0) {
      $div->add($this->createInProgressPort($potential, "In progress"));
    }
    else {
      // ------------------------------------------------------------
      // Are there any regattas that ended no more than 2 days ago?
      // Let's show those, too
      $start->sub(new DateInterval('P2DT0H'));
      $end->sub(new DateInterval('P2DT0H'));
      $potential = DB::getAll(
        DB::T(DB::PUBLIC_REGATTA),
        new DBBool(
          array(
            new DBCond('start_time', $start, DBCond::LE),
            new DBCond('end_date', $end, DBCond::GE),
            new DBCond('dt_status', Regatta::STAT_SCHEDULED, DBCond::NE)
          )
        )
      );
      if (count($potential) > 0) {
        $div->add($this->createInProgressPort($potential, "Recent regattas"));
      }
      elseif (DB::g(STN::FLICKR_ID) !== null) {
        $div->add(
          new XDiv(
            array('id'=>'flickwrap'),
            array(
              new XElem(
                'iframe',
                array(
                  'src' => sprintf('//www.flickr.com/slideShow/index.gne?group_id=&user_id=%s', urlencode(DB::g(STN::FLICKR_ID))),
                  'width'=>500,
                  'height'=>500
                ),
                array(new XText(""))
              )
            )
          )
        );
      }
    }

    // ------------------------------------------------------------
    // Fill list of coming soon regattas
    $now = new DateTime('tomorrow');
    $now->setTime(0, 0);
    DB::T(DB::PUBLIC_REGATTA)->db_set_order(array('start_time'=>true));
    $regs = array();
    foreach (DB::getAll(DB::T(DB::PUBLIC_REGATTA), new DBCond('start_time', $now, DBCond::GE)) as $reg) {
      if ($reg->dt_status !== null && $reg->dt_status != Regatta::STAT_SCHEDULED)
        $regs[] = $reg;
    }
    DB::T(DB::PUBLIC_REGATTA)->db_set_order();
    if (count($regs) > 0) {
      $this->addSection($p = new XPort("Upcoming schedule"));
      $p->add(
        $tab = new XQuickTable(
          array('class' => 'coming-regattas'),
          array(
            "Name",
            "Host",
            "Type",
            "Scoring",
            "Start time"
          )
        )
      );
      foreach ($regs as $reg) {
        $tab->addRow(
          array(
            new XA($reg->getURL(), $reg->name),
            $reg->getHostVenue(),
            $reg->type,
            $reg->getDataScoring(),
            $reg->start_time->format('m/d/Y @ H:i')
          )
        );
      }
    }

    // ------------------------------------------------------------
    // Add links to all seasons
    $num = 0;
    $ul = new XUl(array('id'=>'other-seasons'));
    $seasons = Season::getActive();
    foreach ($seasons as $s) {
      if (count($s->getRegattas()) > 0) {
        $num++;
        $ul->add(new XLi(new XA('/'.$s.'/', $s->fullString())));
      }
    }
    if ($num > 0) {
      $this->addSection(
        new XDiv(
          array('id'=>'submenu-wrapper'),
          array(
            new XH3("Other seasons", array('class'=>'nav')),
            $ul
          )
        )
      );
    }
  }

  /**
   * Creates an "#in-progress" port with the list of regattas given
   *
   * @param Array $regs the list of regattas
   * @param String $title the title to use for the port
   * @return XDiv the div created
   */
  private function createInProgressPort($regs, $title) {
    if (!is_array($regs)) {
      $in_prog = array();
      foreach ($regs as $reg) {
        $in_prog[] = $reg;
      }
    }
    usort($in_prog, 'Regatta::cmpTypes');
    $div = new XDiv(
      array('id'=>'in-progress'),
      array(
        $this->h1($title),
        $tab = new XQuickTable(
          array('class'=>'season-summary'),
          array(
            "Name",
            "Type",
            "Scoring",
            "Status",
            "Leading"
          )
        )
      )
    );

    foreach ($in_prog as $i => $reg) {
      $row = array(new XA($reg->getURL(), $reg->name), $reg->type, $reg->getDataScoring());
      $tms = $reg->getRankedTeams();
      if ($reg->dt_status == Regatta::STAT_READY || count($tms) == 0) {
        $row[] = new XTD(array('colspan'=>2), new XEm("No scores yet"));
      }
      else {
        $row[] = new XStrong(ucwords($reg->dt_status));
        $row[] = $tms[0]->school->drawSmallBurgee((string)$tms[0]);
      }
      $tab->addRow($row, array('class'=>'row'.($i % 2)));
    }
    return $div;
  }

  /**
   * Creates a fancy, three-part, h1 heading
   *
   * @param String $heading the title of the heading (arg to XSpan)
   * @return XH1
   */
  private function h1($heading) {
    $h1 = new XH1("");
    $h1->add(new XSpan("", array('class'=>'left-fill')));
    $h1->add(new XSpan($heading));
    $h1->add(new XSpan("", array('class'=>'right-fill')));
    return $h1;
  }

}
