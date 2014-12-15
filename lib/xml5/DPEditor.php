<?php
/**
 * A What-You-Say-Is-What-You-Mean editor with simple plain-text based
 * structure and strict XHTML output. This version uses a more
 * intelligent gobbling-style parser.
 *
 * In addition, it supports inline mode, in which only inline elements
 * are parsed and new lines have no meaning (replaced with spaces).
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
   * @var Array associative map of headings for the table of contents,
   * indexed by their level. For each level, (1, 2, 3), track the last
   * parent of that heading as a tuple of XLi and its child XOl.
   */
  private $toc;

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
   * @var boolean true for inline parsing only (default: false)
   */
  private $inlineMode = false;

  /**
   * Create a new template
   *
   * @param boolean $inline_mode true to parse as one "line"
   */
  public function __construct($inline_mode = false) {
    $this->setFirstHeading(new XH1(""));
    $this->setSecondHeading(new XH2(""));
    $this->setThirdHeading(new XH3(""));
    $this->setFigureClass('figure');
    $this->setInlineMode($inline_mode !== false);
  }

  /**
   * Sets the parsing mode, inline vs. "block" (default)
   *
   * @param boolean $flag true to set to inline
   */
  public function setInlineMode($flag = true) {
    $this->inlineMode = ($flag !== false);
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
   * Creates a new instance of a heading at given level.
   *
   * By default, this method will use the heading elements provided
   * via set*Heading as templates. If the level exceeds 3, then this
   * method returns null (no heading at all).
   *
   * Subclasses may choose to override this method, instead of using
   * set*Heading, in order to have better control of the creation
   * process.
   *
   * @param int $level the level of the heading
   * @return XAbstractHtml|null the heading, or null
   */
  protected function newHeading($level) {
    switch ($level) {
    case 1: return clone($this->oneast_tpl);
    case 2: return clone($this->twoast_tpl);
    case 3: return clone($this->thrast_tpl);
    default: return null;
    }
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
   * Appends a new heading at the given level to the internal TOC
   *
   * @param int $level the level of the heading
   * @param String $text the label
   */
  protected function addTOC($level, $text) {
    // Malformed?
    if (!isset($this->toc[$level - 1]))
      return;

    // Bubble to the current level
    while (count($this->toc) > $level) {
      $i = count($this->toc) - 1;
      if (count($this->toc[$i]) > 0) {
        $size = count($this->toc[$i - 1]);
        $this->toc[$i - 1][$size - 1]->add(new XOl(array(), $this->toc[$i]));
      }
      unset($this->toc[$i]);
    }

    // Add the new one
    $this->toc[$level - 1][] = new XLi($text);
    $this->toc[$level] = array();
  }

  /**
   * Fetches the internally-generated TOC.
   *
   * @return XOl|null
   */
  public function getTOC() {
    // Bubble?
    while (count($this->toc) > 1) {
      $i = count($this->toc) - 1;
      if (count($this->toc[$i]) > 0) {
        $size = count($this->toc[$i - 1]);
        $this->toc[$i - 1][$size - 1]->add(new XOl(array(), $this->toc[$i]));
      }
      unset($this->toc[$i]);
    }
    return (count($this->toc[0]) == 0) ? null : new XOl(array(), $this->toc[0]);
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
    $this->toc = array(array());

    // prep the string
    $input = str_replace("\r\n", "\n", $input);
    $input = preg_replace('/^[ 	]+$/m', '', $input);

    if (!$this->inlineMode)
      $input .= "\n\n";

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

    $classStart = '<';
    $classEnd = '>';
    $allowClass = true;

    // gobble up characters
    $len = mb_strlen($input);
    $i = 0;

    $delimiters = $this->getInlineDelimiters();
    while ($i < $len) {
      $char = mb_substr($input, $i, 1);

      // Inline mode?
      if ($this->inlineMode) {
        if (count($context) == 0) {
          $env = new XDiv();
          $context->unshift($env);
          $this->appendEnvironment($env);
        }
        if ($char == "\n")
          $char = " ";
      }
      else {
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
              $lev = mb_strlen($buf) + 1;
              $item = $this->newHeading($lev);
              if ($item !== null) {
                $this->addToc($lev, $item);
                $context->unshift($item);
              }
              else {
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
              $this->appendEnvironment($table);
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
                  $this->appendEnvironment($lists->ul[0]);
                }
                elseif ($lists->sym[0] == $sym) {
                  // most likely case: just another entry => do nothing here
                }
                elseif (mb_strlen($lists->sym[0]) < mb_strlen($sym)) {
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
            $i -= mb_strlen($buf) + 1;
          }
          elseif ($char == " " || $char == "\t") {
            // trim whitespace
            $i++;
            continue;
          }

          // ------------------------------------------------------------
          // Blockquotes
          // ------------------------------------------------------------
          elseif ($char == '>' &&  $i + 1 < $len) {
            // Fetch entire block, and recurse
            $buf = '';
            while (++$i < $len) {
              $char = mb_substr($input, $i, 1);
              if ($char == "\n") {
                if ($i + 1 >= $len || mb_substr($input, $i + 1, 1) != '>')
                  break;

                $buf .= $char;
                $i++;
              }
              else {
                $buf .= $char;
              }
            }

            // Remove leading spaces
            $buf = preg_replace('/^ +/m', '', $buf);

            $blockquote = new XBlockquote("");
            $this->appendEnvironment($blockquote);

            // Recurse
            $classname = get_class($this);
            $child_parser = new $classname();
            foreach ($child_parser->parse($buf) as $child) {
              $blockquote->add($child);
            }
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

            // End of environment
            if ($num_new_lines >= 2 || $env instanceof XLi || $env instanceof XTD) {
              $buf = '';
              for ($j = $num_envs - 1; $j >= 0; $j--)
                $buf .= ($context->sym[$j] . $context->buf[$j]);
              $env->add(rtrim($buf));

              if (!($env instanceof XLi || $env instanceof XTD)) {
                $this->appendEnvironment($env);

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
            else { // replace new line with space
              $context->set('buf', 0, ($context->buf[0] . ' '));
            }
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
      if ($do_parse && in_array($char, $delimiters)) {
        // (possible) start of inline environment
        //
        // if not the first character, then previous must be word
        // boundary; there must be a 'next' character, and it must be
        // the beginning of a word; and it must not be the same
        // character; and it is allowed
        $a = $context->buf[0];
        if (($i + 1) < $len
            && mb_substr($input, $i + 1, 1) != $char
            && mb_substr($input, $i + 1, 1) != " "
            && mb_substr($input, $i + 1, 1) != "\t"
            && ($a == '' || preg_match('/\B/u', mb_substr($a, mb_strlen($a) - 1, 1)) > 0)
            && ($env = $this->getInlineEnvironment($char, $context)) !== null) {

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
        // Possible badge?
        $res = array();
        if (preg_match('/^[[:alnum:]-\/]+\}/u', mb_substr($input, $i + 1), $res) > 0) {
          $badge = $this->getBadge(mb_substr($res[0], 0, mb_strlen($res[0]) - 1));
          if ($badge !== null) {
            $context->env[0]->add($badge);
            $i += mb_strlen($res[0]) + 1;
            continue;
          }
        }

        // Attempt to find the resource tag: alphanumeric characters
        // followed by a colon (:), e.g. "a:", "img:", etc
        $colon_i = mb_strpos($input, ':', $i);
        if ($colon_i !== false && $colon_i > ($i + 1)) {
          $tag = mb_substr($input, $i + 1, ($colon_i - $i - 1));
          if (preg_match('/^[[:alnum:]-\/]+$/u', $tag) > 0 && ($xtag = $this->getResourceTag($tag)) !== null) {
            // Is a new context necessary?
            if ($this->isResourceBlockLevel($tag)) {
              if (count($context->env) > 1 || mb_strlen($context->buf[0]) > 0) {
                $cont = '';
                for ($j = count($context) - 1; $j >= 0; $j--)
                  $cont .= ($context->sym[$j] . $context->buf[$j]);
                $context->env[0]->add(rtrim($cont));
                $this->appendEnvironment($context->env[0]);
              }
              $context = new DPEConMap();
              $context->unshift(new XDiv());
            }
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
          if (mb_strlen($aChar) > 0 && $aChar[0] == '{') {
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
          && preg_match('/^\{[[:alnum:]-\/]+:$/u', $context->sym[0]) > 0
          && $context->arg[0] == 0) {

        $tag = mb_substr($context->sym[0], 1, mb_strlen($context->sym[0]) - 2);
        $this->setResourceParam(0, $context->env[0], $tag, trim($context->buf[0]));
        $do_parse = $this->getParseForParam(1, $context->env[0], $tag);
        $context->set('buf', 0, '');

        $i++;
        $context->set('arg', 0, 1);
        continue;
      }

      // ------------------------------------------------------------
      // closing delimiter for classes?
      // ------------------------------------------------------------
      if ($char == $classEnd && $context->env[0] instanceof XP
          && $i < $len - 2 && mb_substr($input, $i + 1, 1) == "\n"
          && preg_match('/[^\s]/', mb_substr($input, $i + 2, 1)) > 0
          && preg_match(sprintf('/^%s[[:alnum:]]+$/', $classStart), $context->buf[0]) > 0) {
        $context->env[0]->set('class', mb_substr($context->buf[0], 1));
        $context->set('buf', 0, '');
        $i++;
        continue;
      }

      // ------------------------------------------------------------
      // empty space at the beginning of block environment have no meaning
      // ------------------------------------------------------------
      if (($char == ' ' || $char == "\t") && count($context) == 0) {
        if ($context->buf[0] == '') {
          $context->set('buf', 0, ' ');
        }
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
    // The extra newlines at the end in non-inline mode ascertain that
    // the remaining buffer is "flushed". With inlineMode, the buffer
    // must be manually flushed instead
    if ($this->inlineMode) {
      $j = count($context) - 1;
      $context->env[$j]->add($context->buf[$j]);
      for ($k = $j - 1; $k >= 0; $k--) {
        $context->env[$j]->add($context->sym[$k]);
        $context->env[$j]->add($context->buf[$k]);
      }
      for ($k = 0; $k < $j; $k++)
        $context->shift();      
    }

    mb_internal_encoding($old_enc);
    return $this->list;
  }

  /**
   * Convenience method: appends $elem to list of block-level environments
   *
   * This provides a rudimentary method by which subclasses may wish
   * to edit the $elem before it gets appended. At all times, do not
   * forget to call this base method. Otherwise, the element won't be
   * included.
   *
   * @param XAbstractHtml $elem the block-level element to add
   */
  protected function appendEnvironment(XAbstractHtml $elem) {
    $this->list[] = $elem;
  }

  /**
   * Return list of possible "inline" tags to catch, depending on context
   *
   * @return Array:String list of possible tags, such as '*', '/'.
   */
  protected function getInlineDelimiters() {
    return array('*', '/', '✂');
  }

  /**
   * Return the XAbstractHtml environment for given delimiter
   *
   * @param String $delim one from list returned getInlineDelimiters
   * @param DPEConMap $con the context for the environment
   * @return XAbstractHtml the environment, if any
   */
  protected function getInlineEnvironment($delim, DPEConMap $con) {
    if (in_array($delim, $con->sym))
      return null;

    switch ($delim) {
    case '*': return new XStrong("");
    case '/': return new XEm("");
    case '✂': return new XDel("");
    default: return null;
    }
  }

  /**
   * Is the resource a BLOCK (vs. INLINE) environment?
   *
   * If the resource is a block-level element, then the current
   * block-level environment (usually P) will be closed.
   *
   * @return boolean default: false
   */
  protected function isResourceBlockLevel($tag) {
    return false;
  }

  /**
   * Returns the XAbstractHtml represented by the tag
   *
   * A badge is a string of the form alphanumeric chars in braces {}.
   * The argument to this function is the tag (without braces), and the
   * default return value is null.
   *
   * If the return value is NOT null, it should be an HTMLElement which
   * will be added to the current environment at that point.
   *
   * @param String tag alphanumeric string representing tag
   * @return XAbstractHtml|null null to indicate no tag (default)
   */
  protected function getBadge($tag) {
    return null;
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
   * Toggles parsing on/off (true/false) for resource.
   *
   * Some resources like images, use the second parameter for
   * attributes like alt-text, for which there is no inline
   * parsing. Others, like hyperlinks, allow inline elements to appear
   * as the second argument.
   *
   * @param int $num the argument number
   * @param XAbstractHtml $env the resource in question
   * @param String $tag the tag used
   * @return boolean true if inline parsing should be allowed
   * @see getResourceTag
   * @see setResourceParam
   */
  protected function getParseForParam($num, XAbstractHtml $env, $tag) {
    return !($env instanceof XImg);
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
