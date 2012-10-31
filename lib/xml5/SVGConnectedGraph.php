<?php
/**
 * SVG Connected graph
 *
 * @author Dayan Paez
 * @version 2010-09-23
 * @package svg
 */

require_once(dirname(__FILE__).'/SVGChart.php');

/**
 * SVGNode
 *
 * @author Dayan Paez
 * @version 2012-10-28
 */
class SVGNode extends SVGRect {
  private $x;
  private $y;

  public function __construct($x, $y, $size = 5, Array $attrs = array()) {
    parent::__construct($x - $size / 2, $y - $size / 2, $size, $size, $size / 2, $size / 2, $attrs);
    $this->x = $x;
    $this->y = $y;
  }
  public function x() { return $this->x; }
  public function y() { return $this->y; }
}

/**
 * Create a connected graph: A set of nodes (SVGNodes's) which are
 * connected with straight SVGPaths. A rather simple approach to
 * connected graphs, this class creates objects that are best
 * manipulated with javascript.
 *
 * Ask your local javascript guru for help manipulating the elements
 * to your taste.
 *
 * @author Dayan Paez
 * @version 2010-09-30
 */
class SVGConnectedGraph extends SVGChart {
  private $elems;
  private $connections;

  public function __construct($id, $width, $height, $title = null) {
    parent::__construct($id, $width, $height, $title);
    $this->elems = array();
    $this->connections = array();
  }

  /**
   * Overrides the natural add function so as to add a node to this
   * graph. If the rect has no ID, one will be assigned based on the
   * next available ID for this graph. No guarantee is made that that
   * ID won't already exist in the document.
   *
   * @param SVGNode $rect the node to add, or any other element
   */
  public function add($rect) {
    parent::add($rect);
    if (!($rect instanceof SVGNode))
      return;
    $this->elems[] = $rect;
    if ($rect->id() === null)
      $rect->set("id", sprintf("%s-%d", $this->id(), count($this->elems)));
  }

  /**
   * Adds a line graph connecting the given elements.
   *
   * The line graph and the elements (if not already added) will form
   * part of a 'group'
   *
   * @param Array:SVGNode $nodes the nodes to connect
   * @param boolean $add_nodes true (default) will add the nodes if missing
   * @return SVGG the created series
   */
  public function connect($id, Array $attrs, Array $nodes, $add_nodes = true) {
    $group = new SVGG($id, $attrs);
    $data = array();
    $i = 0;
    foreach ($nodes as $node) {
      if ($add_nodes)
	$group->add($node);
      if ($i == 0)
	$data[] = new SVGMoveto($node->x(), $node->y());
      else
	$data[] = new SVGLineto($node->x(), $node->y());
      $i++;
    }
    $node = new SVGPath($data);
    $group->add($node);
    parent::add($group);
    return $node;
  }
}
?>