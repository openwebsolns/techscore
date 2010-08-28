<?php
/**
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2.0
 * @package tscore
 */
require_once('conf.php');

/**
 * The basic HTML page for TechScore files. This page is a
 * GenericElement and it extends the WebPage class found in the
 * XmlLibrary. It includes facilities for adding items to the menu,
 * and content.
 *
 * @author Dayan Paez
 * @version 2.0
 * @created 2009-10-19
 */
class EditRegattaPage extends TScorePage {

  // Private variables
  private $regatta;
  private $user;

  /**
   * Creates a new page with the given title
   *
   * @param String $title the title of the page
   * @param Regatta $reg the regatta in question
   */
  public function __construct($title, User $user, Regatta $reg) {
    parent::__construct($title);
    $this->regatta = $reg;
    $this->user = $user;
    $this->fillMenu();
  }

  /**
   * Fills the menu
   *
   */
  private function fillMenu() {
    $u = $this->user;
    $r = $this->regatta;

    $score_i = array("Regatta"   => array(new DetailsPane($u, $r),
					  new SummaryPane($u, $r),
					  new ScorersPane($u, $r),
					  new RacesPane($u, $r),
					  new TeamsPane($u, $r),
					  new NotesPane($u, $r),
					  new ReportPane($u, $r)),
		     "Rotations" => array(new SailsPane($u, $r),
					  new TweakSailsPane($u, $r),
					  new ManualTweakPane($u, $r)),
		     "RP Forms"  => array(new RpEnterPane($u, $r),
					  new UnregisteredSailorPane($u, $r)),
		     "Finishes"  => array(new EnterFinishPane($u, $r),
					  new DropFinishPane($u, $r)),
		     "Penalties" => array(new EnterPenaltyPane($u, $r),
					  new DropPenaltyPane($u, $r),
					  new TeamPenaltyPane($u, $r)));


    $dial_i  = array("rotation" => "Rotation",
		     "scores"   => "Scores",
		     "sailors"  => "Sailors");

    // Fill panes menu
    $id = $r->id();
    foreach ($score_i as $title => $panes) {
      $menu = new Div();
      $menu->addAttr("class", "menu");
      $menu->addChild(new Heading($title));
      $menu->addChild($m_list = new GenericList());
      foreach ($panes as $pane) {
	$url = $pane->getMainURL();
	if ($pane->isActive())
	  $m_list->addItems(new LItem(new Link("score/$id/$url", $pane->getTitle())));
	else
	  $m_list->addItems(new LItem($pane->getTitle(), array("class"=>"inactive")));
      }
      $this->addMenu($menu);
    }

    // Downloads
    $menu = new Div();
    $menu->addAttr("class", "menu");
    $menu->addChild(new Heading("Download"));
    $menu->addChild($m_list = new GenericList());
    if (count($r->getTeams()) > 0 && count($r->getDivisions()) > 0) {
      $m_list->addItems(new LItem(new Link("download/$id/regatta", "Regatta")));
      $m_list->addItems(new LItem(new Link("download/$id/rp", "RP Forms")));
    }
    else {
      $m_list->addItems(new LItem("Regatta", array("class"=>"inactive")));
      $m_list->addItems(new LItem("RP Forms", array("class"=>"inactive")));
    }
    $this->addMenu($menu);

    // Dialogs
    $menu = new Div();
    $menu->addAttr("class", "menu");
    $menu->addChild(new Heading("Windows"));
    $menu->addChild($m_list = new GenericList());
    foreach ($dial_i as $url => $title) {
      $link = new Link("view/$id/$url", $title);
      $link->addAttr("class", "frame-toggle");
      $link->addAttr("target", "_blank");
      $m_list->addItems(new LItem($link));
    }
    $this->addMenu($menu);
  }
}

?>