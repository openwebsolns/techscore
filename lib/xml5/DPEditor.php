<?php
/**
 * A What-You-Say-Is-What-You-Mean editor with simple plain-text based
 * structure and strict XHTML output. This version uses a more
 * intelligent gobbling-style parser.
 *
 * @author Dayan Paez
 * @created 2011-06-08
 */

require_once(dirname(__FILE__).'/HtmlLib.php');

/**
 * DPEditor: the class responsible for producing and parsing the input
 * into strict XHTML document using HtmlLib
 *
 * @author Dayan Paez
 * @version 2011-06-08
 */
class DPEditor {

  /**
   * @var Array the list of elements as parsed
   */
  private $list;

  /**
   * @var int a counter used to automatically generate an image ID
   */
  private $IMG_COUNTER = 0;

  /**
   * @var XAbstractHTML the object to encapsulate with one, two, or
   * three asterisks. These are by default, XH1, XH2, and XH3, etc.
   */
  private $oneast_tpl = null;
  private $twoast_tpl = null;
  private $thrast_tpl = null;

  private $figure_class = null;

  /**
   * Create a new template
   */
  public function __construct() {
    $this->setFirstHeading(new XH1(""));
    $this->setSecondHeading(new XH2(""));
    $this->setThirdHeading(new XH3(""));
    $this->setFigureClass('figure');
  }

  /**
   * Set the template element to use for first level heading (* ...).
   * This element will be cloned for each new instance
   *
   * @param XAbstractHTML $elem the element to use (default XH1)
   */
  public function setFirstHeading(XAbstractHTML $elem) {
    $this->oneast_tpl = $elem;
  }

  /**
   * Set the template element to use for second level headings.
   * This element will be cloned for each new instance
   *
   * @param XAbstractHTML $elem the element to use (default XH2)
   */
  public function setSecondHeading(XAbstractHTML $elem) {
    $this->twoast_tpl = $elem;
  }

  /**
   * Set the template element to use for third level heading.
   * This element will be cloned for each new instance
   *
   * @param XAbstractHTML $elem the element to use (default XH3)
   */
  public function setThirdHeading(XAbstractHTML $elem) {
    $this->thrast_tpl = $elem;
  }

  /**
   * If a paragraph consists of ONLY images, then it will receive the
   * special 'figure' class (default: figure)
   *
   * Passing the value null will disable this feature
   *
   * @param String $class the classname to use for paragraphs
   */
  public function setFigureClass($class) {
    $this->figure_class = $class;
  }

  /**
   * Parses the given string input and returns the list of
   * XAbstractElems found, ready for inclusion in an XDiv, for instance
   *
   * @param String $input the text to parse
   * @return Array:XAbstractHtml the list of elements found, therein
   */
  public function parse($input) {
    $old_enc = mb_internal_encoding();
    if ($old_enc != 'UTF-8')
      mb_internal_encoding('UTF-8');

    $input = $this->preParse($input);
    $this->list = array();

    // prep the string
    $input = str_replace("\r\n", "\n", $input) . "\n\n";
    $input = preg_replace('/^[         ]+$/m', '', $input);

    // context: three associated stacks, 'env' contains the nested
    // environments (such as p, li, strong, a, etc). 'buf' contains
    // the buffer for each of those environments in turn. 'sym'
    // contains the matching symbols. Thus, given the following input:
    // "Hello *world*.", the stacks would look like the following at
    // positions i=8:
    //
    //   env: "strong" , "p"
    //   buf: "wo", "Hello "
    //   sym: "*", ""
    //
    // These get reset at the start of each block
    //
    // 'arg': for A and IMG, which can take 2 arguments, this index
    // is supposed to indicate which one we are in. This variable has
    // no meaning for other environments.
    $context = new DPEConMap();

    // We break environments at two or more consecutive new lines,
    // so we track these separately
    $num_new_lines = 0;

    // inside certain environments, the parsing rules are relaxed. For
    // instance, in the first argument of an A element (href attr), or
    // in both arguments of an IMG element (src and alt attrs).
    $do_parse = true;

    // index to provide an auto-generated alt text to images
    $image_num = 0;

    // stack of previous list environments in nested fashion.  'sym'
    // contains both the depth and the symbol used, e.g. '   - '
    $lists = new DPEList();

    // table rows must be kept in a queue until they are added either
    // to the head or the body of the table
    $trows = array();
    $table = null;
    $row = null;

    // gobble up characters
    $len = mb_strlen($input);
    $i = 0;

    while ($i < $len) {
      $char = mb_substr($input, $i, 1);

      // beginning of "new" environment
      if (count($context) == 0) {
        $inlist = (count($lists) > 0 && $num_new_lines == 1);

        // ------------------------------------------------------------
        // Headings
        if ($char == '*' && !$inlist) {
          // gobble up to the first non-asterisk
          $buf = '';
          while (++$i < $len && mb_substr($input, $i, 1) == "*")
            $buf .= '*';
          if ($i < $len && mb_substr($input, $i, 1) == " ") {
            switch (strlen($buf)) {
            case 0:
              $context->unshift(clone $this->oneast_tpl); break;
            case 1:
              $context->unshift(clone $this->twoast_tpl); break;
            case 2:
              $context->unshift(clone $this->thrast_tpl); break;
            default:
              $context->unshift(new XP(), $buf);
            }
            $lists = new DPEList();
            $i++;
            continue;
          }
          else
            $i--;
        }

        // ------------------------------------------------------------
        // Tables
        elseif ($char == '|' && !$inlist) {
          $lists = new DPEList();

          // are we already in a table
          if ($table === null) {
            $table = new XTable();
            $trows = array();
            $this->list[] = $table;
          }
          // are we already in a row?
          if ($row === null) {
            $row = new XTR();
            $trows[] = $row;
          }

          $row->add($td = new XTD());
          $context->unshift($td);
          $i++;
          continue;
        }
        elseif ($char == '-' && $table !== null) {
          // all previous rows belong in THEAD. This is particularly
          // painful because the entire content is inside an XTD
          // element thus far.
          $table->add($env = new XTHead());
          foreach ($trows as $j) {
            $env->add(new XRawText(str_replace('</td>','</th>',
                                               str_replace('<td>','<th>', $j->toXML()))));

          }
          $trows = array();
          // consume until the end of the line
          do { $i++; } while ($i < $len && mb_substr($input, $i, 1) != "\n");
          $i++;
          continue;
        }

        // ------------------------------------------------------------
        // Lists. These are mighty complicated, because they can be
        // nested to any depth
        // ------------------------------------------------------------
        elseif ($char == ' ') {
          $buf = ''; // depth
          while (++$i < $len && mb_substr($input, $i, 1) == ' ')
            $buf .= ' ';
          if ($i < $len - 2) {
            $sub = mb_substr($input, $i, 2);
            if ($sub == "- " || $sub == "+ ") {
              $sym = ($buf . $sub);
              // if the previous environment is one of the lists
              // environments, then append this list item there.
              // Recall that that we are more lenient with list items,
              // allowing one empty line between successive entries.
              if (count($lists) == 0) {
                $lists->unshift(($sub == "- ") ? new XUl() : new XOl(), null, $sym);
                $this->list[] = $lists->ul[0];
              }
              elseif ($lists->sym[0] == $sym) {
                // most likely case: just another entry => do nothing here
              }
              elseif (strlen($lists->sym[0]) < strlen($sym)) {
                $env = $lists->li[0];
                $lists->unshift(($sub == "- ") ? new XUl() : new XOl(), null, $sym);
                $env->add($lists->ul[0]);
              }
              else {
                // find the matching depth
                $env = null;
                foreach ($lists->sym as $j => $depth) {
                  if ($depth == $sym) {
                    $env = $lists->li[$j];
                    break;
                  }
                }
                if ($env !== null) {
                  for ($k = 0; $k < $j; $k++)
                    $lists->shift();
                }
                else {
                  // reverse compatibility: not actually a sublist,
                  // but a misaligned -/+. Treat as regular text
                  $context->unshift($lists->li[0], (' ' . $sub), '', 0);
                  $i += 2;
                  continue;
                }
              }

              $context->unshift(new XLi(""));
              $lists->ul[0]->add($context->env[0]);
              $lists->set('li', 0, $context->env[0]);

              $i += 2;
              continue;
            }
          }
          $i -= strlen($buf);
        }
        elseif ($char == " " || $char == "\t") {
          // trim whitespace
          $i++;
          continue;
        }
      }

      // ------------------------------------------------------------
      // Table cell endings
      // ------------------------------------------------------------
      if ($char == '|' && $context->env[0] instanceof XTD) {
        // are we at the end of a line? Let the new line handler do it
        if ($i + 1 >= $len || mb_substr($input, $i + 1, 1) == "\n") {
          $i++;
          continue;
        }

        $cont = '';
        for ($j = count($context) - 1; $j >= 0; $j--)
          $cont .= ($context->sym[$j] . $context->buf[$j]);
        $context->env[0]->add(rtrim($cont));
        $context = new DPEConMap();
        continue;
      }

      // ------------------------------------------------------------
      // New lines? Are we at the end of some environment?
      // ------------------------------------------------------------
      if ($char == "\n") {
        $num_new_lines++;
        $num_envs = count($context);

        if ($num_envs > 0) {
          $env = $context->env[$num_envs - 1];

          if ($num_new_lines >= 2 || $env instanceof XLi || $env instanceof XTD) {
            $buf = '';
            for ($j = $num_envs - 1; $j >= 0; $j--)
              $buf .= ($context->sym[$j] . $context->buf[$j]);
            $env->add(rtrim($buf));

            if (!($env instanceof XLi || $env instanceof XTD)) {
              $this->list[] = $env;

              // ------------------------------------------------------------
              // Handle special 'figures' case
              // ------------------------------------------------------------
              if ($this->figure_class !== null && $env instanceof XP) {
                $is_figure = true;
                foreach ($env->children() as $child) {
                  if ($child instanceof XImg)
                    continue;
                  if ($child instanceof XRawText) {
                    if (trim($child->toXML()) == "")
                      continue;
                  }
                  $is_figure = false;
                  break;
                }
                if ($is_figure)
                  $env->set('class', $this->figure_class);
              }
            }
            $context = new DPEConMap();

            if ($env instanceof XTD)
              $row = null;
          }
          else // replace new line with space
            $context->set('buf', 0, ($context->buf[0] . ' '));
        }
        // hard reset the list
        if ($num_new_lines >= 3)
          $lists = new DPEList();

        // hard reset the table
        if ($table !== null && $num_new_lines >= 2) {
          $table->add(new XTBody(array(), $trows));
          $table = null;
        }


        $i++;
        continue;
      }

      // ------------------------------------------------------------
      // Create an P element by default
      // ------------------------------------------------------------
      if (count($context) == 0) {
        if (!$inlist) {
          $context->unshift(new XP());
          $lists = new DPEList();
        }
        else {
          $context->unshift($lists->li[0], ' ');
        }
      }

      // ------------------------------------------------------------
      // At this point, we have an environment to work with, now
      // consume characters according to inline rules
      // ------------------------------------------------------------
      if ($char == '\\' && ($i + 1) < $len) {
        $next = mb_substr($input, $i + 1, 1);
        $num_envs = count($context);
        $env = $context->env[$num_envs - 1];

        if ($next == "\n" && !($env instanceof XTD)) {
          $buf = '';
          for ($j = $num_envs - 1; $j >= 0; $j--)
            $buf .= ($context->sym[$j] . $context->buf[$j]);
          $env->add(rtrim($buf));
          $env->add(new XBr());

          // remove all but $env
          while (count($context) > 1)
            $context->shift();
          $context->set('buf', 0, '');

          $i += 2;
          continue;
        }
        // Escape commas inside {...} elements
        elseif ($next == ",") {
          $context->set('buf', 0, $context->buf[0] . $next);
          $i += 2;
          continue;
        }
      }
      if ($do_parse && ($char == '*' || $char == '/' || $char == '✂')) {
        // (possible) start of inline environment
        //
        // if not the first character, then previous must be word
        // boundary; there must be a 'next' character, and it must be
        // the beginning of a word; and the environment must not
        // already be in use.
        $a = $context->buf[0];
        if (!in_array($char, $context->sym)
            && ($i + 1) < $len
            && mb_substr($input, $i + 1, 1) != " "
            && mb_substr($input, $i + 1, 1) != "\t"
            && ($a == '' || preg_match('/\B/', $a[strlen($a) - 1]) > 0)) {

          $env = null;
          switch ($char) {
          case '*': $env = new XStrong(""); break;
          case '/': $env = new XEm(""); break;
          case '✂': $env = new XDel(""); break;
          }
          $context->unshift($env, '', $char, 0);
          $i++;
          continue;
        }
        // (possible) end of inline environment. To make sure we are
        // backwards compatible with the regexp version of the parser,
        // we need to check if any inline environments in the stack
        // are being closed, not just the top one. Viz:
        //
        //   Input: I *bought a /blue pony* mom.
        //  Output: I <strong>bought a /blue pony</strong>mom.
        //
        // It would be wrong to wait for the <em> to close before
        // closing the <strong>.
        $closed = false;
        foreach ($context->sym as $j => $aChar) {
          if ($aChar == $char) {
            $closed = true;
            break;
          }
        }
        // do the closing by rebuilding j-th buffer with prior buffers
        // (if any) and appending j-th to parent.
        if ($closed) {
          $context->env[$j]->add($context->buf[$j]);
          for ($k = $j - 1; $k >= 0; $k--) {
            $context->env[$j]->add($context->sym[$k]);
            $context->env[$j]->add($context->buf[$k]);
          }
          for ($k = 0; $k < $j; $k++)
            $context->shift();

          // add myself to my parent and reset his buffer
          $context->env[1]->add($context->buf[1]);
          $context->env[1]->add($context->env[0]);
          $context->set('buf', 1, '');

          $context->shift();
          $i++;
          continue;
        }
      } // end of */- inline

      // ------------------------------------------------------------
      // Opening {} environments (img) and anchors (a), (e)
      // ------------------------------------------------------------
      if ($do_parse && $char == '{') {
        // Attempt to find the resource tag: alphanumeric characters
        // followed by a colon (:), e.g. "a:", "img:", etc
        $colon_i = mb_strpos($input, ':', $i);
        if ($colon_i !== false && $colon_i > ($i + 1)) {
          $tag = mb_substr($input, $i + 1, ($colon_i - $i - 1));
          if (preg_match('/^[A-Za-z0-9]+$/', $tag) > 0 && ($xtag = $this->getResourceTag($tag)) !== null) {
            $context->unshift($xtag, '', ('{' . $tag . ':'), 0);
            $i += 2 + mb_strlen($tag);
            $do_parse = false;
            continue;
          }
        }
      }

      // ------------------------------------------------------------
      // Closing {} environments?
      // ------------------------------------------------------------
      if ($char == '}') {
        // see note about */- elements
        $closed = false;
        foreach ($context->sym as $j => $aChar) {
          if (strlen($aChar) > 0 && $aChar[0] == '{') {
            $closed = true;
            break;
          }
        }
        // do the closing by rebuilding j-th buffer
        if ($closed) {
          $cont = $context->buf[$j];
          for ($k = $j - 1; $k >= 0; $k--) {
            $cont .= $context->sym[$k];
            $cont .= $context->buf[$k];
          }
          for ($k = 0; $k < $j; $k++)
            $context->shift();

          // set the href or src attribute depending on number of
          // arguments for this environment
          $tag = mb_substr($context->sym[0], 1, mb_strlen($context->sym[0]) - 2);
          $this->setResourceParam($context->arg[0], $context->env[0], $tag, $cont, true);

          // add myself to my parent and reset his buffer
          $context->env[1]->add($context->buf[1]);
          $context->env[1]->add($context->env[0]);
          $context->set('buf', 1, '');
          $context->shift();
          $i++;

          $do_parse = true;
          continue;
        }
      } // end closing environments

      // ------------------------------------------------------------
      // commas are important immediately inside A's and IMG's, as
      // they delineate between HREF and innerHTML, or SRC and ALT
      //
      // The last condition limits two arguments per resource
      // ------------------------------------------------------------
      if ($char == ','
          && preg_match('/^\{[A-Za-z0-9]+:$/', $context->sym[0]) > 0
          && $context->arg[0] == 0) {

        $tag = mb_substr($context->sym[0], 1, mb_strlen($context->sym[0]) - 2);
        $this->setResourceParam(0, $context->env[0], $tag, trim($context->buf[0]));
        $context->set('buf', 0, '');

        $i++;
        $context->set('arg', 0, 1);
        continue;
      }

      // ------------------------------------------------------------
      // empty space at the beginning of block environment have no meaning
      // ------------------------------------------------------------
      if (($char == ' ' || $char == "\t") &&
          count($context) == 0 &&
          $context->buf[0] == '') {
        $i++;
        continue;
      }

      // ------------------------------------------------------------
      // Default action: append char to buffer
      // ------------------------------------------------------------
      $num_new_lines = 0;
      $context->set('buf', 0, $context->buf[0] . $char);
      $i++;
    }

    mb_internal_encoding($old_enc);
    return $this->list;
  }

  /**
   * Return the XAbstractHtml object for the given resource tag.
   *
   * The resource tag is the element in {TAG:...[,...]}  environments.
   * This method allows subclasses to extend the list of such parsed
   * environments. The default understood values are 'img' (Ximg), 'a'
   * for (XA) and 'e', also for XA, with mailto: auto-prepended.
   *
   * When overriding this function, it is imperative to also override
   * the <pre>setResourceParam</pre> function as well.
   *
   * @param String $tag alphanumeric string representing tag
   * @return XAbstractHtml|null null to indicate no tag recognized
   * @see setResourceParam
   */
  protected function getResourceTag($tag) {
    switch ($tag) {
    case 'a':
    case 'e':
      return new XA("", "");
    case 'img':
      return new XImg("");
    default:
      return null;
    }
  }

  /**
   * Set the resource's parameter number using $cont.
   *
   * @param int $num either 0 or 1, at this point
   * @param XAbstractHtml $env as returned by <pre>getResourceTag</pre>
   * @param String $tag the tag used for the object
   * @param String $cont the content to use
   * @param boolean $close if this is the last argument
   * @see getResourceTag
   */
  protected function setResourceParam($num, XAbstractHtml $env, $tag, $cont, $close = false) {
    switch ($tag) {
    case 'a':
      if ($num > 0)
        $env->add($cont);
      else {
        $env->set('href', $cont);
        if ($close)
          $env->add($cont);
      }
      return;

    case 'e':
      if ($num > 0)
        $env->add($cont);
      else {
        $env->set('href', 'mailto:' . $cont);
        if ($close)
          $env->add($cont);
      }
      return;

    case 'img':
      if ($num > 0)
        $env->set('alt', $cont);
      else {
        $env->set('src', $cont);
        if ($close)
          $env->set('alt', "Image: " . $cont);
      }
      return;

    default:
      $env->add($cont);
    }
  }

  /**
   * Preprocess the input before being parsed.
   *
   * Subclasses should extend this method as a quick way to extend their
   * grammar to have more semantic meaning. As an example, consider
   * that your site uses "badges" (images with some meaning to them).
   * You could write {img:/path/to/badge.png,Badge #1} every time you
   * wanted to add that badge...
   *
   * OR, you could subclass this class (which you should always do
   * anyways), and override this method to simply replace every
   * instance of {B1} with the text above. Thus, your clients will be
   * writing richer, more syntactically-meaningful code, which is the
   * whole purpose anyways.
   *
   * @param String $inp the string before being parsed
   * @return String the string to be parsed
   */
  protected function preParse($inp) {
    return $inp;
  }

  /**
   * Returns String representation of XHTML code
   *
   * @return String
   */
  public function toXML() {
    if ($this->list === null)
      return "";
    $t = "";
    foreach ($this->list as $e)
      $t .= $e->toXML();
    return $t;
  }

  /**
   * Prints the contents to standard output
   *
   */
  public function printXML() {
    if ($this->list === null) return;
    foreach ($this->list as $e)
      $e->printXML();
  }
}

require_once(dirname(__FILE__) . '/ntable.php');
class DPEConMap extends NTable {
  protected $colnames = array('env', 'buf', 'sym', 'arg');
  protected $defaults = array( null, '', '', 0);
}
class DPEList extends NTable {
  protected $colnames = array('ul', 'li', 'sym');
}
?>
