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
  private $num_per_page;
  private $page_variable;

  /**
   * Creates a new wizard.
   *
   * @param int $count the total number
   * @param int $NPP the number per page
   * @param Array $args the GET arguments to use to build request
   * @param String $var the GET variable that holds the page
   * @param Array $items the size of the result set
   */
  public function __construct($count, $NPP, $base, Array $args = array(), $var = 'r') {
    $this->count = $count;
    $this->args = $args;
    $this->num_per_page = $NPP;
    $this->num_pages = ceil($this->count / $this->num_per_page);
    $this->base = $base;
    $this->page_variable = $var;
  }

  /**
   * Creates and returns a search form
   *
   * @param String $query the query string
   * @param String $var the name of the search variable for the form
   * @param String $empty_mes the message to provide when there are no results
   */
  public function getSearchForm($query = null, $var = 'q', $empty_mes = "There are no items to show.", $leadin = "Search") {
    $div = new XDiv(array('class'=>'navsearch'));
    if ($this->count == 0 && $query == null) {
      $div->add(new XP(array('class'=>'warning'), $empty_mes));
      return $div;
    }

    if ($this->num_pages > 3 || $query !== null) {
      $div->add($f = new XForm($this->base, XForm::GET, array('class'=>'search-form')));
      $f->add($pa = new XP(array('class'=>'search'),
                           array(new XSearchInput($var, $query, array('size'=>60, 'placeholder'=>$leadin)), " ",
                                 new XSubmitInput('go', "Go", array('class'=>'inline')))));
      if ($query !== null) {
        $pa->add(" ");
        $pa->add(new XA($this->base, "Cancel"));

        if ($this->count == 0)
          $f->add(new XP(array('class'=>'warning'), $empty_mes));
      }
    }
    return $div;
  }

  /**
   * Creates a LinksDiv section from parameters.
   *
   * @param String $anchor optional page anchor
   * @return LinksDiv
   * @see LinksDiv::__construct
   */
  public function getPageLinks($anchor = '') {
    require_once('xml5/LinksDiv.php');
    return new LinksDiv(
      $this->num_pages,
      $this->getCurrentPage(),
      $this->base,
      $this->args,
      $this->page_variable,
      $anchor);
  }

  /**
   * Returns list of elements from array, according to settings.
   *
   * @param iterator $items either an array of ArrayIterator
   * @return Array
   * @throws InvalidArgumentException
   */
  public function getSlice($items) {
    if (!is_array($items) && !($items instanceof ArrayIterator)) {
      throw new InvalidArgumentException("Provided list is not iterable.");
    }
    $list = array();
    $currentPage = $this->getCurrentPage();
    $start = ($currentPage - 1) * $this->num_per_page;
    $end = $start + $this->num_per_page;

    if ($start > count($items)) {
      throw new InvalidArgumentException("List of items provided shorter than current page.");
    }

    for ($i = $start; $i < $end && $i < count($items); $i++) {
      $list[] = $items[$i];
    }
    return $list;
  }

  /**
   * Returns the currently chosen page based on args.
   *
   * @return int the page num, 1-based.
   */
  public function getCurrentPage() {
    return DB::$V->incInt($this->args, $this->page_variable, 1, $this->num_pages + 1, 1);
  }

  /**
   *
   * @deprecated use getPageLinks
   */
  public function getPages($var = 'r', Array $get = array(), $anchor = '') {
    require_once('xml5/LinksDiv.php');
    return new LinksDiv($this->num_pages,
                        DB::$V->incInt($get, $var, 1, $this->num_pages + 1, 1),
                        $this->base,
                        $this->args,
                        $var,
                        $anchor);
  }
}
?>