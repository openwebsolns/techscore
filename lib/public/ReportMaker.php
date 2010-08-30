<?php
/**
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2010-08-24
 */

/**
 * Creates the report page for the given regatta
 *
 */
class ReportMaker {
  private $regatta;
  private $page;

  /**
   * Creates a new report for the given regatta
   *
   */
  public function __construct(Regatta $reg) {
    $this->regatta = $reg;
  }

  private function fill() {
    if ($this->page !== null) return;

    $reg = $this->regatta;
    $this->page = new TPublicPage($reg->get(Regatta::NAME));
    $this->page->addNavigation(new Link(".", $reg->get(Regatta::NAME), array("class"=>"nav")));

    // Links to season
    $season = $reg->get(Regatta::SEASON);
    $this->page->addNavigation(new Link(sprintf("%s/%s", HOME, $season), $season->fullString(),
					array("class"=>"nav", "accesskey"=>"u")));

    // Menu
    if (count($reg->getRotation()->getDivisions()) > 0)
      $this->page->addMenu(new Link("rotations", "Rotations"));

    // Regatta information
    $this->page->addSection($div = new Div());
    $div->addChild(new GenericElement("h2", array(new Text($reg->get(Regatta::NAME)))));
    $div->addAttr("align", "center");
    $div->addAttr("id",    "reg-details");
    
    $stime = $reg->get(Regatta::START_TIME);
    $etime = $reg->get(Regatta::END_DATE);
    if ($stime->format('Y-m-d') == $etime->format('Y-m-d')) // same day
      $date = $stime->format('F j, Y');
    elseif ($stime->format('Y-m') == $etime->format('Y-m')) // same month
      $date = sprintf("%s-%s", $stime->format('F j'), $etime->format('j, Y'));
    elseif ($stime->format('Y') == $etime->format('Y')) // same year
      $date = sprintf('%s - %s', $stime->format('F j'), $etime->format('F j, Y'));
    else // different year
      $date = $stime->format('F j, Y') . ' - ' . $etime->format('F y, Y');

    $hosts = $reg->getHosts();
    $schools = array();
    foreach ($hosts as $host)
      $schools[$host->school->id] = $host->school->nick_name;

    $type = sprintf('%s Regatta', ucfirst($reg->get(Regatta::TYPE)));
    $div->addChild($l = new Itemize());
    $l->addItems(new LItem(implode("/", $schools)),
		 new LItem($date),
		 new LItem($type),
		 new LItem(implode("/", $reg->getBoats())));
    
  }

  /**
   * Generates and returns the HTML code for the given regatta. Note that
   * the report is only generated once per report maker
   *
   * @return TPublicPage
   */
  public function getPage() {
    $this->fill();
    return $this->page->toHTML();
  }
}
?>