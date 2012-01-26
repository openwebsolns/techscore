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

  
  public function getPages($var = 'r', Array $get = array()) {
    if ($this->num_pages < 2)
      return "";

    $div = new XDiv(array('class'=>'navlinks'));
    $current = DB::$V->incInt($get, $var, 1, $this->num_pages + 1, 1);
    // always display the first five, if possible
    for ($i = 1; $i < $current && $i < 5; $i++) {
      $this->args[$var] = $i;
      $div->add($l = new XA(sprintf("%s?%s", $this->base, http_build_query($this->args)), $i));
      if ($i == $current)
	$l->set("class", "current");
    }
    // also print the two before this one
    if ($i < $current - 2)
      $i = $current - 2;

    for (; $i < $current; $i++) {
      $this->args[$var] = $i;
      $div->add($l = new XA(sprintf("%s?%s", $this->base, http_build_query($this->args)), $i));
    }

    // also print this one
    $this->args[$var] = $i;
    $div->add(new XA(sprintf("%s?%s", $this->base, http_build_query($this->args)), $i));
    $div->add(new XText(" "));
    $i++;
    // also print the two after this one
    for (; $i < $current + 3 && $i < $this->num_pages; $i++) {
      $this->args[$var] = $i;
      $div->add($l = new XA(sprintf("%s?%s", $this->base, http_build_query($this->args)), $i));
      if ($i == $current)
	$l->set("class", "current");
    }
    // also print the last one
    if ($i <= $this->num_pages) {
      $this->args[$var] = $i;
      $div->add(new XA(sprintf("%s?%s", $this->base, http_build_query($this->args)), $i));
    }
    return $div;
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
  }
}
?>