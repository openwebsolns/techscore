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
class UserArchivePane extends AbstractUserPane {

  const NUM_PER_PAGE = 20;

  /**
   * Create a new User home page for the specified user
   *
   * @param Account $user the user whose page to load
   */
  public function __construct(Account $user) {
    parent::__construct("Regatta archive", $user);
  }

  /**
   * Generates and returns the HTML page
   *
   * @param Array $args the arguments to consider
   * @return String the HTML code
   */
  protected function fillHTML(Array $args) {
    $pageset  = (isset($args['r'])) ? (int)$args['r'] : 1;
    if ($pageset < 1)
      WS::go("home");
    $startint = self::NUM_PER_PAGE * ($pageset - 1);

    // ------------------------------------------------------------
    // Pending users
    // ------------------------------------------------------------
    if ($this->USER->isAdmin()) {
      $pending = DB::getPendingUsers();
      if (($num_pending = count($pending)) > 0) {
        $this->PAGE->addContent($p = new XPort("Pending users"));
        if ($num_pending == 1)
          $p->add(new XP(array(),
                         array("There is one pending account request for ", new XA(WS::link('/pending'), $pending[0]), ".")));
        else
          $p->add(new XP(array(),
                         array("There are ", new XA(WS::link('/pending'), "$num_pending pending account requests"), ".")));
      }
    }

    // ------------------------------------------------------------
    // Messages
    // ------------------------------------------------------------
    $num_messages = count(DB::getUnreadMessages($this->USER));
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
    $empty_mes = array("You have no regattas. Go ", new XA("create", "create one"), "!");
    $regattas = array();
    $num_regattas = 0;
    DB::$V->hasString($qry, $_GET, 'q', 1, 256);
    if ($qry !== null) {
      $empty_mes = "No regattas match your request.";
      if (strlen($qry) < 3)
        $empty_mes = "Search string is too short.";
      else {
        $regs = $this->USER->searchRegattas($qry);
        $num_regattas = count($regs);
        if ($startint > 0 && $startint >= $num_regattas)
          WS::go('/?q=' . $qry);
      }
    }
    else {
      $regs = $this->USER->getRegattas();
      $num_regattas = count($regs);
    }

    $this->PAGE->addContent($p = new XPort("Regattas from previous seasons"));

    // Offer pagination awesomeness
    require_once('xml5/PageWhiz.php');
    $whiz = new PageWhiz($num_regattas, self::NUM_PER_PAGE, '/', $_GET);
    $p->add($whiz->getSearchForm($qry, 'q', $empty_mes, "Search your regattas: "));
    $p->add($ldiv = $whiz->getPages('r', $_GET));

    // Create table of regattas, if applicable
    if ($num_regattas > 0) {
      $p->add(new XTable(array('style'=>'width:100%'),
                         array(new XTHead(array(),
                                          array(new XTR(array(),
                                                        array(new XTH(array(), "Name"),
                                                              // new XTH(array(), "Season"),
                                                              new XTH(array(), "Date"),
                                                              new XTH(array(), "Type"),
                                                              new XTH(array(), "Scoring"),
                                                              new XTH(array(), "Finalized"))))),
                               $tab = new XTBody())));
      $row = 0;
      $now = new DateTime('1 day ago');
      for ($i = $startint; $i < $startint + self::NUM_PER_PAGE && $i < $num_regattas; $i++) {
        $reg = $regs[$i];
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
                                // new XTD(array(), strtoupper($reg->season)),
                                new XTD(array(), $reg->start_time->format("Y-m-d")),
                                new XTD(array(), ucfirst($reg->type)),
                                new XTD(array(), ucfirst($reg->scoring)),
                                new XTD(array(), $finalized))));
      }
    }
    $p->add($ldiv);
  }

  /**
   * This pane does not edit anything
   *
   * @param Array $args can be an empty array
   */
  public function process(Array $args) { return $args; }
}
?>
