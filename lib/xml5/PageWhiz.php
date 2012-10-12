<?php
/*
 * This file is part of TechScore
 *
 */

/**
 * Wizard to paginate content. This works very similar to LinksDiv.
 *
 * @author Dayan Paez
 * @version 2012-01-23 
 */
class PageWhiz {

  private $base;
  private $args;

  private $count;
  private $num_pages;

  /**
   * Creates and returns a search form
   *
   * @param String $query the query string
   * @param String $var the name of the search variable for the form
   * @param String $empty_mes the message to provide when there are no results
   */
  public function getSearchForm($query = null, $var = 'q', $empty_mes = "There are no items to show.", $leadin = "Search:") {
    $div = new XDiv(array('class'=>'navsearch'));
    if ($this->count == 0 && $query == null) {
      $div->add(new XP(array('class'=>'warning'), $empty_mes));
      return $div;
    }

    if ($this->num_pages > 3 || $query !== null) {
      $div->add($f = new XForm($this->base, XForm::GET));
      $f->add($pa = new XP(array('class'=>'search'),
                           array($leadin, new XTextInput($var, $query, array('size'=>60)),
                                 new XSubmitInput('go', "Go"))));
      if ($query !== null) {
        $pa->add(" ");
        $pa->add(new XA($this->base, "Cancel"));

        if ($this->count == 0)
          $f->add(new XP(array('class'=>'warning'), $empty_mes));
      }
    }
    return $div;
  }


  public function getPages($var = 'r', Array $get = array(), $anchor = '') {
    require_once('xml5/LinksDiv.php');
    return new LinksDiv($this->num_pages,
                        DB::$V->incInt($get, $var, 1, $this->num_pages + 1, 1),
                        $this->base,
                        $this->args,
                        $var,
			$anchor);
  }

  /**
   * Creates a new wizard
   *
   * @param Array $items the size of the result set
   */
  public function __construct($count, $NPP, $base, Array $args = array()) {
    $this->count = $count;
    $this->args = $args;
    $this->num_pages = ceil($count / $NPP);
    $this->base = $base;

    // Do not include page argument
    unset($this->args['p']);
  }
}
?>