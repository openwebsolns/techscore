<?php
use \ui\UserRegattaTable;
use \utils\RegattaSearcher;

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
    $searcher = RegattaSearcher::fromArgs($args);
    $searcher->setIncludeAccountAsParticipant(true);

    // ------------------------------------------------------------
    // Regatta list
    // ------------------------------------------------------------

    // Search?
    $empty_mes = array("You have no regattas. Go ", new XA("create", "create one"), "!");
    $regs = $searcher->doSearch();
    $num_regattas = count($regs);
    $qry = $searcher->query;
    if ($qry !== null) {
      $empty_mes = "No regattas match your request.";
    }

    $this->PAGE->addContent($p = new XPort("Regattas from all seasons"));

    // Offer pagination awesomeness
    require_once('xml5/PageWhiz.php');
    $whiz = new PageWhiz($num_regattas, self::NUM_PER_PAGE, $this->link(), $args);
    $p->add($whiz->getSearchForm($qry, 'q', $empty_mes, "Search your regattas"));
    $ldiv = $whiz->getPageLinks();

    // Create table of regattas, if applicable
    if ($num_regattas > 0) {
      $regs = $whiz->getSlice($regs);
      $p->add($ldiv);

      $p->add($tab = new UserRegattaTable($this->USER));
      foreach ($regs as $reg) {
        $tab->addRegatta($reg);
      }
      $p->add($ldiv);
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
