<?php
/*
 * This file is part of TechScore
 *
 * @version 2.0
 * @package regatta
 */

require_once('conf.php');

/**
 * Encapsulates a temporary regatta. A temporary regatta stands in
 * place for an actual regatta (<code>Regatta</code>). They are
 * temporary in that the do not remain in the database.
 *
 * For convenience, <code>TempRegatta</code>'s extends
 * <code>Regatta</code> so all its functions are available.
 *
 * @author Dayan Paez
 * @created 2010-02-16
 */
class TempRegatta extends Regatta {

  /**
   * Property constant for the original regatta
   */
  const ORIGINAL = "original";
  private $original;
  
  /**
   * Property constant for expiration
   */
  const EXPIRES = "expires";
  private $expires;

  /**
   * Retrieves the saved Temporary Regatta with the given ID.
   *
   * @throws InvalidArgumentException if ID is invalid (doesn't exist
   * or regatta is not a temporary regatta)
   */
  public function __construct($id) {
    parent::__construct($id);

    // fetch temporary information
    $q = sprintf('select original, expires from temp_regatta where regatta = "%s"',
		 $this->id());
    $res = $this->query($q);
    if ($res->num_rows == 0)
      throw new InvalidArgumentException("Regatta $id is not a temporary regatta.");

    $res = $res->fetch_object();
    $this->original = new Regatta($res->original);
    $this->expires  = new DateTime($res->expires);
  }

  /**
   * Retrieves the named property: one of the class constants
   *
   * @param TempRegatta::Const $name the property name to retrieve
   * @return object the property (<code>DateTime</code> for
   * TempRegatta::EXPIRES, and <code>Regatta</code> for
   * TempRegatta::ORIGINAL).
   *
   * @throws InvalidArgumentException should the property not exist
   */
  public function __get($name) {
    if (!isset($this->$name))
      return parent::__get($name);
    return $this->$name;
  }

  /**
   * Sets the named property: one of the class constants
   *
   * @param TempRegatta::Const $name the proeprty name to set
   * @param object $value the corresponding value
   *
   * @throws InvalidArgumentException should the argument be awry.
   */
  public function __set($name, $value) {
    if (!isset($this->$name))
      parent::__set($name, $value);

    if ($name == TempRegatta::ORIGINAL) {
      if (!($value instanceof Regatta))
	throw new InvalidArgumentException("Original regatta must be Regatta object.");
      $this->original = $value;
    }
    elseif ($name == TempRegatta::EXPIRES) {
      if (!($value instanceof DateTime))
	throw new InvalidArgumentException("Expiration date must be DateTime object.");
      $this->expires = $value;
    }
    $this->setTemporaryFor();
  }

  /**
   * Sets this regatta as a temporary copy of the given regatta, with
   * expiration date as given, updating the database
   *
   * @param Regatta $other the other regatta
   * @param DateTime $expiration the expiration timestamp
   */
  private function setTemporaryFor() {
    $q = sprintf('replace into temp_regatta values ("%s", "%s", "%s")',
		 $this->id(), $this->original->id(), $this->expires->format("Y-m-d H:i:s"));
    $this->query($q);
  }

  /**
   * Creates a temporary copy of the given regatta.
   *
   * @param Regatta $reg the regatta for which this will be a
   * temporary copy
   * @parma DateTime $expiration lifetime of the temporary regatta information
   */
  public static function createRegatta(Regatta $reg, DateTime $expiration) {
    $id = self::addRegatta(SQL_DB,
			   $reg->get(Regatta::NAME),
			   $reg->get(Regatta::START_TIME),
			   $reg->get(Regatta::END_DATE),
			   $reg->get(Regatta::TYPE),
			   $reg->get(Regatta::SCORING));

    $q = sprintf('replace into temp_regatta values ("%s", "%s", "%s")',
		 $id, $reg->id(), $expiration->format("Y-m-d H:i:s"));
    self::static_query($q);

    $temp_reg = new TempRegatta($id);
    return $temp_reg;
  }
}

if (basename(__FILE__) == $argv[0]) {
  $reg = TempRegatta::createRegatta(new Regatta(110), new DateTime("now", new DateTimeZone("America/New_York")));
  print_r($reg);
}
?>