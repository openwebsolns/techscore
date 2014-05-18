<?php
/*
 * This file is part of TechScore
 *
 * @package users
 */

require_once('users/AbstractUserPane.php');

/**
 * User's archive page, subclasses AbstractUserPane
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
   */
  protected function fillHTML(Array $args) {
    $pageset  = (isset($args['r'])) ? (int)$args['r'] : 1;
    if ($pageset < 1)
      $this->redirect('archive');
    $startint = self::NUM_PER_PAGE * ($pageset - 1);

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
        $regs = $this->USER->searchRegattas($qry, true);
        $num_regattas = count($regs);
        if ($startint > 0 && $startint >= $num_regattas)
          WS::go('/archive?q=' . $qry);
      }
    }
    else {
      $regs = $this->USER->getRegattas(null, true);
      $num_regattas = count($regs);
    }

    $this->PAGE->addContent($p = new XPort("Regattas from all seasons"));

    // Offer pagination awesomeness
    require_once('xml5/PageWhiz.php');
    $whiz = new PageWhiz($num_regattas, self::NUM_PER_PAGE, '/archive', $_GET);
    $p->add($whiz->getSearchForm($qry, 'q', $empty_mes, "Search your regattas"));
    $p->add($ldiv = $whiz->getPages('r', $_GET));

    // Create table of regattas, if applicable
    if ($num_regattas > 0) {
      require_once('xml5/UserRegattaTable.php');
      $p->add($tab = new UserRegattaTable($this->USER));
      for ($i = $startint; $i < $startint + self::NUM_PER_PAGE && $i < $num_regattas; $i++) {
        $reg = $regs[$i];
        $tab->addRegatta($reg);
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
