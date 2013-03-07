<?php
/**
 * Atom feed creator, based on the XML Library
 *
 * @author Dayan Paez
 * @created 2011-02-08
 * @package atom
 * @see http://www.atomenabled.org/developers/syndication/atom-format-spec.php
 */

require_once(dirname(__FILE__).'/XmlLib.php');

/**
 * An Atom feed document
 *
 * @author Dayan Paez
 * @version 2011-02-08
 */
class AtomFeed extends XDoc {

  protected $logo;
  protected $title;
  protected $rights;
  protected $updated;
  protected $atom_id;
  protected $subtitle;
  protected $generator;

  /**
   * Creates a new atom feed with the optional children
   *
   * @param String $id the id for the feed
   * @param AtomTitle|String $title the title of the feed
   * @param Array:children the optional children to add
   */
  public function __construct($id, $title, Array $children = array()) {
    parent::__construct('feed', array('xmlns'=>'http://www.w3.org/2005/Atom'));
    $this->add(new AtomId($id));
    if ($title instanceof AtomTitle)
      $this->add($title);
    else
      $this->add(new AtomTitle($title));
    foreach ($children as $child)
      $this->add($child);
    $this->ct = 'application/atom+xml';
  }

  /**
   * Overrides the parent add method to make sure that those children
   * which can only happen once, are only added once.
   *
   * @param Xmlable $elem the XElem to add
   * @throws InvalidArgumentException if attempting to add duplicate
   */
  public function add($elem) {
    if ($elem instanceof AtomId) {
      if ($this->atom_id !== null)
	throw new InvalidArgumentException("Only one atom:id allowed per feed.");
      $this->atom_id = $elem;
    }
    elseif ($elem instanceof AtomTitle) {
      if ($this->title !== null)
	throw new InvalidArgumentException("Only one atom:title allowed per feed.");
      $this->title = $elem;
    }
    elseif ($elem instanceof AtomRights) {
      if ($this->rights !== null)
	throw new InvalidArgumentException("Only one atom:rights allowed per feed.");
      $this->rights = $elem;
    }
    elseif ($elem instanceof AtomGenerator) {
      if ($this->generator !== null)
	throw new InvalidArgumentException("Only one atom:generator allowed per feed.");
      $this->generator = $elem;
    }
    elseif ($elem instanceof AtomSubtitle) {
      if ($this->subtitle !== null)
	throw new InvalidArgumentException("Only one atom:subtitle allowed per feed.");
      $this->subtitle = $elem;
    }
    elseif ($elem instanceof AtomLogo) {
      if ($this->logo !== null)
	throw new InvalidArgumentException("Only one atom:logo allowed per feed.");
      $this->logo = $elem;
    }
    elseif ($elem instanceof AtomUpdated) {
      if ($this->updated !== null)
	throw new InvalidArgumentException("Only one atom:updated allowed per feed.");
      $this->updated = $elem;
    }
    parent::add($elem);
  }

  /**
   * Provides a mechanism to retrieve particular aspects of the feed,
   * just in case.
   *
   * @param String $name the attribute name to retrieve
   * @return XElem the resulting object
   * @throws InvalidArgumentException if requesting non-existing object
   */
  public function __get($name) {
    if (isset($this->$name))
      return $this->$name;
    throw new InvalidArgumentException("No such AtomFeed property \"$name\".");
  }
}

/**
 * A title element: supports only text
 *
 */
class AtomTitle extends XElem {
  /**
   * Create a new title
   *
   * @param XRawText|String $text either XRawText or String, the
   * latter will be wrapped around XText
   *
   * @param String $type the default type is 'text', can be 'xhtml'
   */
  public function __construct($text, $type = 'text') {
    parent::__construct('title', array('type'=>$type));
    if ($text instanceof XRawText)
      $this->add($text);
    else
      $this->add(new XText($text));
  }
}

/**
 * Feed's subtitle
 *
 */
class AtomSubtitle extends XElem {
  /**
   * Creates a new subtitle of given type
   *
   * @param XRawText|String $text the to include
   * @param String $type the type (either text or xhtml)
   * @see AtomTitle
   */
  public function __construct($text, $type = "text") {
    parent::__construct('subtitle', array('type'=>$type));
    if ($text instanceof XRawText)
      $this->add($text);
    else
      $this->add(new XText($text));
  }
}

/**
 * When the feed was last updated
 *
 * @author Dayan Paez
 * @version 2011-02-08
 */
class AtomUpdated extends XElem {
  /**
   * Creates the updated time string.
   *
   * @param DateTime $when
   */
  public function __construct(DateTime $when) {
    parent::__construct('updated', array(), array(new XText($when->format('c'))));
  }
}

/**
 * When the entry was published
 *
 * @author Dayan Paez
 * @version 2011-02-08
 */
class AtomPublished extends XElem {
  /**
   * Creates the updated time string.
   *
   * @param DateTime $when
   */
  public function __construct(DateTime $when) {
    parent::__construct('published', array(), array(new XText($when->format('c'))));
  }
}

/**
 * The unique identifier
 *
 * @author Dayan Paez
 * @version 2011-02-08
 */
class AtomId extends XElem {
  /**
   * @param String $id the ID
   */
  public function __construct($id) {
    parent::__construct('id', array(), array(new XText($id)));
  }
}

/**
 * A link, one of which is required for each feed
 *
 * @author Dayan Paez
 * @version 2011-02-08
 */
class AtomLink extends XElem {
  /**
   * Creates a new link with the given href and default rel of
   * 'alternate'
   *
   * @param String $href the reference for the link
   * @param String $rel either 'self' or 'alternate' (default)
   * @param String $type default is 'text/html'
   */
  public function __construct($href, $rel = 'alternate', $type = 'text/html') {
    parent::__construct('link', array('href'=>$href, 'rel'=>$rel, 'type'=>$type));
  }
}

/**
 * Rights to the feed, only one per feed, please!
 *
 * @author Dayan Paez
 * @version 2011-02-08
 */
class AtomRights extends XElem {
  /**
   * New copyright notice
   *
   * @param String $text the copyright notice
   */
  public function __construct($notice) {
    parent::__construct('rights', array(), array(new XText($notice)));
  }
}

/**
 * The generator of the feed, only one per feed
 *
 * @author Dayan Paez
 * @version 2011-02-08
 */
class AtomGenerator extends XElem {
  /**
   * The generator, with optional attributes. The Atom spec allows for
   * 'uri' and 'version' as possible attributes
   *
   * @param String $text the generator
   * @param Array $attrs the optional attributes
   */
  public function __construct($text, Array $attrs = array()) {
    parent::__construct('generator', $attrs, array(new XText($text)));
  }
}

/**
 * Logo for the feed (at most one per feed), should have aspect ratio
 * of 2:1 according to spec
 *
 * @author Dayan Paez
 * @version 2011-02-08
 */
class AtomLogo extends XElem {
  /**
   * Creates a logo with the given IRI.
   *
   * @param String $iri the logo's location
   * @param Array $attrs the optional attributes
   */
  public function __construct($iri, Array $attrs = array()) {
    parent::__construct('logo', $attrs, array(new XText($iri)));
  }
}

/**
 * Atom entry, the meat of the entire feed process
 *
 * @author Dayan Paez
 * @version 2011-02-08
 */
class AtomEntry extends XElem {
  protected $atom_id;
  protected $title;
  protected $content;
  protected $rights;
  protected $summary;
  protected $updated;
  protected $published;

  /**
   * Creates a new entry in a feed
   *
   * @param String $id the ID of the feed
   * @param AtomTitle|String the title of the feed
   * @param Array $attrs optional other attributes
   * @param Array $child optional the children
   */
  public function __construct($id, $title, Array $attrs = array(), Array $child = array()) {
    parent::__construct('entry', $attrs);
    $this->add(new AtomId($id));
    if ($title instanceof AtomTitle)
      $this->add($title);
    else
      $this->add(new AtomTitle($title));
    foreach ($child as $c)
      $this->add($c);
  }

  /**
   * Overrides parent method for simple element check for those
   * elements which can only appear once.
   *
   * @param Xmlable $elem the element to add
   * @throws InvalidArgumentException if attempting to add duplicat e
   */
  public function add($elem) {
    if ($elem instanceof AtomId) {
      if ($this->atom_id !== null)
	throw new InvalidArgumentException("Only one atom:id allowed per entry.");
      $this->atom_id = $elem;
    }
    elseif ($elem instanceof AtomContent) {
      if ($this->content !== null)
	throw new InvalidArgumentException("Only one atom:content element allowed per atom:entry.");
      $this->content = $elem;
    }
    elseif ($elem instanceof AtomTitle) {
      if ($this->title !== null)
	throw new InvalidArgumentException("Only one atom:title element allowed per atom:entry.");
      $this->title = $elem;
    }
    elseif ($elem instanceof AtomRights) {
      if ($this->rights !== null)
	throw new InvalidArgumentException("Only one atom:rights allowed per entry.");
      $this->rights = $elem;
    }
    elseif ($elem instanceof AtomSummary) {
      if ($this->summary !== null)
	throw new InvalidArgumentException("Only one atom:summary allowed per entry.");
      $this->summary = $elem;
    }
    elseif ($elem instanceof AtomUpdated) {
      if ($this->updated !== null)
	throw new InvalidArgumentException("Only one atom:updated allowed per entry.");
      $this->updated = $elem;
    }
    elseif ($elem instanceof AtomPublished) {
      if ($this->published !== null)
	throw new InvalidArgumentException("Only one atom:published allowed per entry.");
      $this->published = $elem;
    }
    parent::add($elem);
  }
}

/**
 * Represents abstract person object
 *
 * @author Dayan Paez
 * @version 2011-02-08
 * @see http://www.atomenabled.org/developers/syndication/atom-format-spec.php#atomPersonConstruct
 */
abstract class AtomAbstractPerson extends XElem {
  /**
   * Creates a new person with the given name, optional uri and
   * optional e-mail
   *
   * @param String $author the author
   * @param String $uri the optional uri
   * @param String $email the optional e-mail
   */
  public function __construct($name, $author, $uri = null, $email = null) {
    parent::__construct($name);
    $this->add(new XElem('name', array(), array(new XText($author))));
    if ($uri !== null)
      $this->add(new XElem('uri', array(), array(new XText($uri))));
    if ($email !== null)
      $this->add(new XElem('email', array(), array(new XText($email))));
  }
}

/**
 * Author of either a whole feed or just an entry
 *
 * @author Dayan Paez
 * @version 2011-02-08
 */
class AtomAuthor extends AtomAbstractPerson {
  /**
   * Creates a new author
   *
   * @param String $author the author
   * @param String $uri the optional uri
   * @param String $email the optional e-mail
   * @see AtomAbstractPerson
   */
  public function __construct($author, $uri = null, $email = null) {
    parent::__construct('author', $author, $uri, $email);
  }
}

/**
 * Contributor to an entry
 *
 * @author Dayan Paez
 * @version 2011-02-08
 * @see AtomAuthor
 */
class AtomContributor extends AtomAbstractPerson {
  public function __construct($author, $uri = null, $email = null) {
    parent::__construct('contributor', $author, $uri, $email);
  }
}

/**
 * Summary for an entry
 *
 * @author Dayan Paez
 * @version 2011-02-08
 */
class AtomSummary extends XElem {
  /**
   * Creates a new summary, as text
   *
   * @param String $text the summary
   * @param Array $attrs the attributes
   */
  public function __construct($text, Array $attrs = array()) {
    parent::__construct('summary', $attrs, array(new XText($text)));
  }
}

/**
 * Generic content for an entry
 *
 * @author Dayan Paez
 * @version 2011-02-08
 */
class AtomContent extends XElem {
  /**
   * Creates a new content.
   *
   * @param Array $attrs the attributes
   * @param Array $child the children
   */
  public function __construct(Array $attrs = array(), Array $child = array()) {
    parent::__construct('content', $attrs, $child);
  }
}

/**
 * Content that is included in the document as plain text
 *
 * @author Dayan Paez
 * @version 2011-02-08
 */
class AtomContentText extends AtomContent {
  /**
   * Creates a new content
   *
   * @param String $text the content of the page
   */
  public function __construct($text, Array $attrs = array()) {
    parent::__construct($attrs, array(new XText($text)));
    $this->set('type', 'text');
  }
}

/**
 * Content in XHTML format
 *
 * @author Dayan Paez
 * @version 2011-02-08
 */
class AtomContentXhtml extends AtomContent {
  /**
   * Creates a new content using the XHTML type
   *
   * @param XElem $cont the content
   * @param Array $attrs the optional attributes
   */
  public function __construct(XElem $elem, Array $attrs = array()) {
    parent::__construct($attrs, array($elem));
    $this->set('type', 'xhtml');
  }
}
?>