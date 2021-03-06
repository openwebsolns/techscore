<?php
namespace pub;

use \Conf;
use \DB;
use \DBCond;
use \DBBool;
use \DateTime;
use \STN;

use \AtomAuthor;
use \AtomContent;
use \AtomEntry;
use \AtomFeed;
use \AtomGenerator;
use \AtomLink;
use \AtomLogo;
use \AtomPublished;
use \AtomRights;
use \AtomUpdated;

require_once('xml5/Atom.php');

/**
 * Atom feed for regattas
 *
 * @author Dayan Paez
 * @created 2013-03-07
 */
class TRegattaFeed extends AtomFeed {

  public function __construct() {
    parent::__construct(Conf::$HOME . ':regattas', sprintf("%s Finalized Regattas", DB::g(STN::ORG_NAME)));
    $this->fill();
  }

  private function fill() {
    $url = sprintf('http://%s', Conf::$PUB_HOME);

    $updated = new AtomUpdated(DB::T(DB::NOW));
    $author = new AtomAuthor(sprintf("%s Scores", DB::g(STN::ORG_NAME)), $url);
    $rights = new AtomRights(DB::g(STN::APP_COPYRIGHT));

    $this->add($author);
    $this->add($updated);
    $this->add($rights);

    $this->add(new AtomLink(sprintf('%s/feed.atom', $url), 'self', 'application/atom+xml'));
    $this->add(new AtomLink($url, 'alternate', 'text/html'));
    $this->add(new AtomLogo(sprintf('%s/inc/img/logo.png', $url)));
    $this->add(new AtomGenerator("OpenWeb Solutions, LLC"));

    // Add the last week's regattas, or the last ten, whichever is
    // greatest
    $cutoff = new DateTime('2 weeks ago');
    $regs = DB::getAll(
      DB::T(DB::PUBLIC_REGATTA),
      new DBBool(
        array(
          new DBCond('finalized', null, DBCond::NE),
          new DBCond('start_time', DB::T(DB::NOW), DBCond::LE)
        )
      )
    );
    $count = 0;
    foreach ($regs as $reg) {
      if ($reg->start_time < $cutoff && $count > 10) {
        break;
      }

      $count++;

      $id = sprintf('%s%s', $url, $reg->getURL());
      $this->add($entry = new AtomEntry($id, $reg->name));
      $entry->add($author);
      $entry->add($rights);
      $entry->add($updated);
      $entry->add(new AtomPublished($reg->finalized));
      // $entry->add(new AtomSummary(""));
      $entry->add(new AtomLink($id, 'alternate', 'text/html'));

      $P = new ReportMaker($reg);
      $page = $P->getScoresPage();
      $entry->add($cont = new AtomContent(array('type'=>'xhtml'), array($page->getPageContent())));
    }
  }
}
