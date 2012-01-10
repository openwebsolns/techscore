<?php
/*
 * This file is part of TechScore
 *
 * @package users
 */

require_once('users/AbstractUserPane.php');

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
    $num_messages = count(DB::getUnreadMessages($this->USER->asAccount()));
    if ($num_messages > 0) {
      $this->PAGE->addContent($p = new XPort("Messages"));
      $p->add($para = new XP(array(), "You have "));
      if ($num_messages == 1)
	$para->add(new XA("inbox", "1 unread message."));
      else
	$para->add(new XA("inbox", "$num_messages unread messages."));
    }

    // ------------------------------------------------------------
    // Regatta list
    // ------------------------------------------------------------

    // Search?
    $qry = null;
    $mes = null;
    $regattas = array();
    $num_regattas = 0;
    if (isset($_GET['q'])) {
      $qry = trim($_GET['q']);
      if (strlen($qry) == 0)
	$mes = "No search query given.";
      if (strlen($qry) < 3)
	$mes = "Search string is too short.";
      else {
	$regs = $this->USER->searchRegattas($qry);
	$num_regattas = count($regs);
	if ($num_regattas == 0)
	  $mes = "No results found.";
	if ($startint > 0 && $startint >= $num_regattas)
	  WebServer::go('/?q=' . $qry);
	// Narrow down the list
	for ($i = $startint; $i < $startint + self::NUM_PER_PAGE && $i < count($regs); $i++)
	  $regattas[] = $regs[$i];
      }
    }
    else {
      $num_regattas = $this->USER->getNumRegattas();
      $regattas = $this->USER->getRegattas($startint, $startint + self::NUM_PER_PAGE);
    }
    
    $this->PAGE->addContent($p = new XPort("My Regattas"));
    // usort($regattas, "RegattaSummary::cmpStartDesc");

    // Add search form, if necessary
    if ($num_regattas > self::NUM_PER_PAGE * 3 || $qry !== null) {
      $p->add($f = new XForm('/', XForm::GET));
      $f->add($pa = new XP(array('id'=>'search', 'title'=>"Enter part or all of the name"),
			   array("Search your regattas: ",
				 new XTextInput('q', $qry, array('size'=>60)),
				 new XSubmitInput('go', "Go"))));
      if ($qry !== null) {
	$pa->add(new XText(" "));
	$pa->add(new XA('/', "Cancel"));
      }
      if ($mes !== null)
	$f->add(new XP(array('class'=>'warning', 'style'=>'padding:0.5em;'), $mes));
    }

    // Create table of regattas, if applicable
    if (count($regattas) > 0) {
      $p->add(new XTable(array('style'=>'width:100%'),
			 array(new XTHead(array(),
					  array(new XTR(array(),
							array(new XTH(array(), "Name"),
							      new XTH(array(), "Season"),
							      new XTH(array(), "Date"),
							      new XTH(array(), "Type"),
							      new XTH(array(), "Finalized"))))),
			       $tab = new XTBody())));
    }
    elseif ($qry === null) {
      $p->add(new XP(array(),
		     array("You have no regattas. Go ",
			   new XA("create", "create one"), "!")));
    }
    $row = 0;
    $now = new DateTime('1 day ago');
    foreach ($regattas as $reg) {
      $link = new XA("score/" . $reg->id, $reg->name);
      $finalized = '--';
      if ($reg->finalized !== null)
	$finalized = $reg->finalized->format("Y-m-d");
      elseif ($reg->finalized < $now)
	$finalized = new XA('score/'.$reg->id.'#finalize', 'PENDING',
			    array('title'=>'Regatta must be finalized!',
				  'style'=>'color:red;font-weight:bold;font-size:110%;'));
      $tab->add(new XTR(array('class'=>'row'.($row++ % 2)),
			array(new XTD(array("class"=>"left", "style"=>"padding-left:1em"), $link),
			      new XTD(array(), strtoupper($reg->season)),
			      new XTD(array(), $reg->start_time->format("Y-m-d")),
			      new XTD(array(), ucfirst($reg->type)),
			      new XTD(array(), $finalized))));
    }
    $last = (int)($num_regattas / self::NUM_PER_PAGE);
    if ($last > 1) {
      require_once('xml5/PageDiv.php');
      $suf = ($qry !== null) ? '?q='.$qry : '';
      $p->add(new PageDiv($last, $pageset, 'home', $suf));
    }
  }

  /**
   * This pane does not edit anything
   *
   * @param Array $args can be an empty array
   */
  public function process(Array $args) { return $args; }
}
?>
