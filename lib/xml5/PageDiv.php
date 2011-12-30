<?php
/**
 * Pagination links
 *
 * @author Dayan Paez
 * @version 2010-07-24
 * @package xml5
 */
class PageDiv extends XDiv {

  /**
   * Creates a smart pagination div for the give number of pages,
   * using the prefix in the links. Pagination is 1-based
   *
   * @param int $num_pages the total number of pages
   * @param int $current the current page number
   * @param String $prefix the prefix for the links
   */
  public function __construct($num_pages, $current, $prefix, $suffix = '') {
    parent::__construct(array("class"=>"navlinks"));

    // always display the first five, if possible
    for ($i = 1; $i < $current && $i < 5; $i++) {
      $this->add($l = new XA(sprintf("%s|%d%s", $prefix, $i, $suffix), $i));
      if ($i == $current) {
	$l->set("class", "current");
      }
    }
    // also print the two before this one
    if ($i < $current - 2)
      $i = $current - 2;

    for (; $i < $current; $i++)
      $this->add($l = new XA(sprintf("%s|%d%s", $prefix, $i, $suffix), $i));

    // also print this one
    $this->add(new XA(sprintf("%s|%d%s", $prefix, $i, $suffix), $i, array('class' => 'current')));
    $this->add(new XText(" "));
    $i++;
    // also print the two after this one
    for (; $i < $current + 3 && $i < $num_pages; $i++) {
      $this->add($l = new XA(sprintf("%s|%d%s", $prefix, $i, $suffix), $i));
      if ($i == $current) {
	$l->set("class", "current");
      }
    }
    // also print the last one
    if ($i < $num_pages) {
      $this->add(new XA(sprintf("%s|%d%s", $prefix, $num_pages, $suffix), $num_pages));
    }
  }
}
?>