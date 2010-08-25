<?php
/**
 * This file is part of TechScore
 *
 * @package users
 */

require_once("conf.php");

/**
 * User's home page, subclasses AbstractUserPane
 *
 */
class UserHomePane extends AbstractUserPane {

  const NUM_PER_PAGE = 10;

  /**
   * Create a new User home page for the specified user
   *
   * @param User $user the user whose page to load
   */
  public function __construct(User $user) {
    parent::__construct("Welcome", $user);
  }

  /**
   * Generates and returns the HTML page
   *
   * @param Array $args the arguments to consider
   * @return String the HTML code
   */
  protected function fillHTML(Array $args) {
    $pageset  = (isset($args['page'])) ? (int)$args['page'] : 1;
    if ($pageset < 1)
      WebServer::go("home");
    $startint = self::NUM_PER_PAGE * ($pageset - 1);

    // ------------------------------------------------------------
    // Messages
    // ------------------------------------------------------------
    $num_messages = count(Preferences::getUnreadMessages($this->USER->asAccount()));
    if ($num_messages > 0) {
      $this->PAGE->addContent($p = new Port("Messages"));
      $p->addChild($para = new Para("You have"));
      if ($num_messages == 1)
	$para->addChild(new Link("inbox", "1 unread message."));
      else
	$para->addChild(new Link("inbox", "$num_messages unread messages."));
    }

    // ------------------------------------------------------------
    // Regatta list
    // ------------------------------------------------------------
    $num_regattas = $this->USER->getNumRegattas();
    $this->PAGE->addContent($p = new Port("My Regattas"));
    $regattas = $this->USER->getRegattas($startint, $startint + self::NUM_PER_PAGE);
    usort($regattas, "RegattaSummary::cmpStartDesc");

    // Create table of regattas, if applicable
    if (count($regattas) > 0) {
      $p->addChild($tab = new Table());
      $tab->addAttr("style", "width: 100%");
      $tab->addHeader(new Row(array(Cell::th("Name"),
				    Cell::th("Season"),
				    Cell::th("Date"),
				    Cell::th("Type"),
				    Cell::th("Finalized"))));
    }
    else {
      $p->addChild(new Para('You have no regattas. Go <a href="create">create one</a>!'));
    }
    $row = 0;
    foreach ($regattas as $reg) {
      $link = new Link("score/" . $reg->id, $reg->name);
      $tab->addRow($r = new Row(array(new Cell($link, array("class"=>"left", "style"=>"padding-left: 1em")),
				      new Cell(strtoupper($reg->season)),
				      new Cell($reg->start_time->format("Y-m-d")),
				      new Cell(ucfirst($reg->type)),
				      new Cell($reg->finalized->format("Y-m-d")))));
      $r->addAttr("class", sprintf("row%d", $row++ % 2));
    }
    $last = (int)($num_regattas / self::NUM_PER_PAGE);
    $p->addChild(new PageDiv($last, $pageset, "home"));
  }

  /**
   * This pane does not edit anything
   *
   * @param Array $args can be an empty array
   */
  public function process(Array $args) { return $args; }
}
?>