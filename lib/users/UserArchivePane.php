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
      WS::go("home");
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
    $whiz = new PageWhiz($num_regattas, self::NUM_PER_PAGE, '/archive', $_GET);
    $p->add($whiz->getSearchForm($qry, 'q', $empty_mes, "Search your regattas: "));
    $p->add($ldiv = $whiz->getPages('r', $_GET));

    // Create table of regattas, if applicable
    if ($num_regattas > 0) {
      $headers = array("Name", "Season");
      if ($this->USER->isAdmin())
        $headers[] = "Host(s)";
      $headers[] = "Date";
      $headers[] = "Type";
      $headers[] = "Scoring";
      $headers[] = "Finalized";
      $p->add($tab = new XQuickTable(array('class' => 'regatta-list'), $headers));

      $row = 0;
      $now = new DateTime('1 day ago');
      for ($i = $startint; $i < $startint + self::NUM_PER_PAGE && $i < $num_regattas; $i++) {
        $reg = $regs[$i];

        $row = array(new XA("score/" . $reg->id, $reg->name),
                     $reg->getSeason()->fullString());

        if ($this->USER->isAdmin()) {
          $hosts = array();
          foreach ($reg->getHosts() as $host)
            $hosts[$host->id] = $host->id;
          $row[] = implode("/", $hosts);
        }

        $row[] = $reg->start_time->format("Y-m-d");
        $row[] = ucfirst($reg->type);
        $row[] = ucfirst($reg->scoring);
        
        $finalized = '--';
        if ($reg->finalized !== null)
          $finalized = $reg->finalized->format("Y-m-d");
        elseif ($reg->end_date < $now)
          $finalized = new XA('score/'.$reg->id.'#finalize', 'PENDING',
                              array('title'=>'Regatta must be finalized!',
                                    'style'=>'color:red;font-weight:bold;font-size:110%;'));
        $row[] = $finalized;

        $tab->addRow($row, array('class'=>'row'.($row++ % 2)));
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
